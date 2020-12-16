<?php
    require_once( './config.php');
    require_once("/home/public_html/weblibs/iware/php/utils/IAutoLoad.php");
    //require_once("/var/www/public/weblibs/iware/php/utils/IAutoLoad.php");
    $autoload = new IAutoLoad($classpath);

    require_once( './config.php');
    require_once( './libs/IDBResource.php');
    require_once( './libs/IDBTable.php');
    require_once( './db/SettlementInfo.php');
    require_once( './db/MorStoreToAspMerchant.php');
    require_once( './db/ASPStoreForward.php');
    require_once( './db/CustAsp.php');
    require_once( './db/ASPTrn.php');
    require_once( './db/SoAsp.php' );
    require_once( './src/Finance/FinanceCompany.php');
    require_once( './src/SynchronyFinance.php');
    require_once( './src/ErrorMessages.php');
    //require_once("../../public/libs".DIRECTORY_SEPARATOR."iware".DIRECTORY_SEPARATOR."php".DIRECTORY_SEPARATOR."utils".DIRECTORY_SEPARATOR."IAutoLoad.php");

    set_include_path(get_include_path() . PATH_SEPARATOR . 'libs/phpseclib');
    include 'Net/SFTP.php';
    include 'Crypt/RSA.php';

    global $appconfig;

    //Only generate settlement or run normal
    if ( $argv[1] == 1 ||  $argv[1] == 3 || $argv[1] == 4  ){
        if( $argv[1] !== 4 ){
            $settlement = fopen( $appconfig['synchrony']['REPORT_SYF_SETTLE_OUT_DIR'] . "" . $appconfig['synchrony']['SYF_SETTLE_FILENAME_DEC'], "w+" );
            $mainReport = fopen( $appconfig['synchrony']['REPORT_SYF_REPORT_OUT_DIR'] . "" . $appconfig['synchrony']['SYF_REPORT_FILENAME'], "w+" );
            $exceptionReport = fopen( $appconfig['synchrony']['REPORT_SYF_REPORT_OUT_DIR'] . "" . $appconfig['synchrony']['SYF_EXCEPTION_FILENAME'], "w+" );
        }

        $transactionsPerStore = [];

        $db = sessionConnect();
        $settle = new SettlementInfo($db);
        $syf= new SynchronyFinance( $db );
        $asfm = new ASPStoreForward($db);
        $recordsToUpdate = [];

        if ($argv[1] !== 4 ){
            fwrite( $mainReport, "Settlement process for SYF\n" );
            fwrite( $mainReport, "Date: "  . date("F j, Y, g:i a") . "\n");
            fwrite( $mainReport, "Settlement File name: " . $syf->getFilenameNoExt() . "\n" );
        }


        //Get all the manual tickets
        $manuals = $syf->writeManuals( $settle, $syf, "SYF" );

        //Get all Aging Transactions
        $agingRet = $syf->writeAgingTransactions( $settle, $syf, "SYF" );

        //Get all aging exceptions
        $agingExc = $syf->writeAgingExceptions( $settle, $syf, "SYF", $db );

        //Process Even exchanges. Use input dates start and ending dates.
        $numEvenExchanges = $syf->processEvenExchanges($db);

        //Get all promo code errors from even exchanges
        $evenExchangesErrors = $syf->writePromoCodeErrors( $settle, $syf, "SYF", $db );

        //Query will get all tickets with status code of H and it was created one day before the ticket it's created.
        //Tickets cannot be settle on the same day the sale it is finalized. 
        $where = "WHERE ASP_STORE_FORWARD.AS_CD = 'SYF' AND ASP_STORE_FORWARD.STAT_CD IN ('H') AND TRUNC(CREATE_DT_TIME) < TRUNC(SYSDATE)";

        $postclauses = "ORDER BY STORE_CD, DEL_DOC_NUM";

        $result = $settle->query($where, $postclauses);

        if ( $result < 0 ){
            echo "AspStoreForward query error: " . $settle->getError() . "\n";
            return;
        }

        $rec = $syf->validateRecords( $db, $asfm, $settle, $totalSales, $totalReturns, $exceptions, $simpleRet, $exchanges, $validData, $delDocWrittens, $settlement, $transactionsPerStore );
        $recordsToUpdate = array_merge( $rec, $recordsToUpdate );

        //Get all manual tickets 
        $where = "WHERE ASP_STORE_FORWARD.AS_CD = 'SYF' AND ASP_STORE_FORWARD.STAT_CD = 'S'";

        $postclauses = "ORDER BY STORE_CD, DEL_DOC_NUM";

        $result = $settle->query($where, $postclauses);
        if ( $result < 0 ){
            echo "AspStoreForward query error: " . $settle->getError() . "\n";
            return;
        }
        $rec = $syf->validateRecords( $db, $asfm, $settle, $totalSales, $totalReturns, $exceptions, $simpleRet, $exchanges, $validData, $delDocWrittens, $settlement, $transactionsPerStore );
        $recordsToUpdate = array_merge( $rec, $recordsToUpdate );

        if ($argv[1] !== 4 ){
            //Call to write bank and batch trailer
            foreach( $transactionsPerStore as $key => $value ){
                //Writing to settlement file bank and batch header
                fwrite($settlement, $syf->getBankHeader());
                fwrite($settlement, $syf->getBatchHeader($db, $key));
                fwrite($settlement, $value['records']);
                fwrite($settlement, $syf->getBatchTrailer( $db, $key, $value['total_records'], $value['amount'] ));
                fwrite($settlement, $syf->getBankTrailer( $value['total_records'], $value['amount'] ));
            }
        }

        $strMsg = "";
        
        if ( $argv[1] == 1 ){
            //Check if exceptions are set generate report  
            foreach( $recordsToUpdate as $key => $value ){
                if ( $value['STAT_CD'] == 'E' ){
                    fwrite($exceptionReport, $value['EXCEPTION']);
                }
            }

            fwrite( $mainReport, "Settlement File was not uploaded ran in mode: 1\n");
            fclose( $settlement );
            fclose( $mainReport );
            fclose( $exceptionReport );
            exit();
        }
        else if ( $argv[1] == 3 ){
            if( !$syf->archive() ){
                fwrite( $mainReport, "Settlement: Archiving Failed\n");
            }
            
            if( processOut($syf) ){
                fwrite( $mainReport, "Settlement File Upload Status: Succesful\n");
            }
            else{
                fwrite( $mainReport, "Settlement File Upload Status: Unsuccesful\n");
                fwrite( $mainReport, "Settlement File Upload Error Code: " . $syf->getErrorCodeUpload() . "\n");
                fwrite( $mainReport, "Settlement File Upload Error Message: " . $syf->getErrorUploadMessage() . "\n");
                fwrite( $mainReport, "Please use the SYF upload module to reupload the settlement file\n" );
                exit();
            }

            updateASFMRecords( $asfm, $recordsToUpdate );

            //Check if exceptions are set generate report  
            foreach( $recordsToUpdate as $key => $value ){
                if ( $value['STAT_CD'] == 'E' ){
                    fwrite($exceptionReport, $value['EXCEPTION']);
                }
            }


            //Write complete report
            $report = $syf->createMainReport( $mainReport, $exceptions, $simpleRet, $exchanges, $manuals, $agingRet, $agingExc, $evenExchangesErrors );

            fclose( $settlement );
            fclose( $mainReport );
            fclose( $exceptionReport );

            // Send email that the SETTL process has completed
            //$syf->emailSettleCompleted( $appconfig, "SYF", $appconfig['MAIN_REPORT_DIR'] . $syf->getMainReportName(), $strMsg );        
        }
        else if ( $argv[1] == 4 ){
            updateASFMRecords( $asfm, $recordsToUpdate );
        }
    }
    //Ran in mode 2
    else if ( $argv[1] == 2 ){
        $mainReport = fopen( $appconfig['synchrony']['REPORT_SYF_REPORT_OUT_DIR'] . "" . $appconfig['synchrony']['SYF_REPORT_FILENAME'], "w+" );
        $db = sessionConnect();
        $syf= new SynchronyFinance( $db );

        if( !$syf->archive() ){
            fwrite( $mainReport, "Settlement File Decrypted was not archived\n");
        }

        if( processOut($syf) ){
            fwrite( $mainReport, "Settlement File Upload Status: Succesful\n");
        }
        else{
            fwrite( $mainReport, "Settlement File Upload Status: Unsuccesful\n");
            fwrite( $mainReport, "Settlement File Upload Error Code: " . $syf->getErrorCodeUpload() . "\n");
            fwrite( $mainReport, "Settlement File Upload Error Message: " . $syf->getErrorUploadMessage() . "\n");
            fwrite( $mainReport, "Please use the SYF upload module to reupload the settlement file\n" );
        }
    
    }
    else if ( $argv[1] == 5 ){
        if ( is_null($argv[2]) ){
            echo "Running mode: " . $argv[1] . " needs a store code passed in";
            exit();
        }

        $settlement = fopen( $appconfig['synchrony']['REPORT_SYF_SETTLE_OUT_DIR'] . "" . $appconfig['synchrony']['SYF_SETTLE_FILENAME_DEC'], "w+" );
        $mainReport = fopen( $appconfig['synchrony']['REPORT_SYF_REPORT_OUT_DIR'] . "" . $appconfig['synchrony']['SYF_REPORT_FILENAME'], "w+" );
        $exceptionReport = fopen( $appconfig['synchrony']['REPORT_SYF_REPORT_OUT_DIR'] . "" . $appconfig['synchrony']['SYF_EXCEPTION_FILENAME'], "w+" );

        $transactionsPerStore = [];
        $totalSales = 0;
        $totalReturns = 0;
        $validData = 0;
        $exchanges = '';
        $simpleRet = '';
        $exceptions = [];
        $delDocWrittends = [];

        $db = sessionConnect();
        $settle = new SettlementInfo($db);
        $syf= new SynchronyFinance( $db );
        $asfm = new ASPStoreForward($db);

        $where = "WHERE ASP_STORE_FORWARD.AS_CD = 'SYF' AND ASP_STORE_FORWARD.STAT_CD IN ('H') AND TRUNC(CREATE_DT_TIME) < TRUNC(SYSDATE) AND ASP_STORE_FORWARD.STORE_CD = '" . $argv[2] . "' ";
        $postclauses = "ORDER BY STORE_CD, DEL_DOC_NUM";

        $result = $settle->query($where, $postclauses);
        if ( $result < 0 ){
            echo "AspStoreForward query error: " . $settle->getError() . "\n";
            return;
        }

        $records = $syf->validateRecords( $db, $asfm, $settle, $totalSales, $totalReturns, $exceptions, $simpleRet, $exchanges, $validData, $delDocWrittens, $settlement, $transactionsPerStore );

        //Call to write bank and batch trailer
        foreach( $transactionsPerStore as $key => $value ){
            //Writing to settlement file bank and batch header
            fwrite($settlement, $syf->getBankHeader());
            fwrite($settlement, $syf->getBatchHeader($db, $key));
            fwrite($settlement, $value['records']);
            fwrite($settlement, $syf->getBatchTrailer( $db, $key, $value['total_records'], $value['amount'] ));
            fwrite($settlement, $syf->getBankTrailer( $value['total_records'], $value['amount'] ));
        }

        $strMsg = "";
        //Check if exceptions are set  
        foreach( $records as $key => $value ){
            if ( $value['STAT_CD'] == 'E' ){
                fwrite($exceptionReport, $value['EXCEPTION']);
            }
        }
        fclose( $exceptionReport );
        fclose( $settlement );
        fclose( $mainReport );

    }
    else{
        echo "Mode Unsupported\n";
        exit();
    }

    function sessionConnect() {
        global $appconfig;

        $db = new IDBResource($appconfig['dbhost'], $appconfig['dbuser'], $appconfig['dbpwd'],  $appconfig['dbname']);
        
        try {
            $db->open();
        }
        catch (Exception $e) {
                $errmsg   = 'Invalid Username/Password';
                    
                return false; 
        }

        return $db;

    }

    function updateASFMRecords( $asfm, $updt ){
        foreach ( $updt as $key => $row ){
            $asfm->set_STAT_CD($row['STAT_CD']);

            if ( $row['STAT_CD'] == 'E' ){
                $asfm->set_ERROR_CD( 'EXCEPTION' );
                $tmp = explode( ',', $row['EXCEPTION'] );
                $asfm->set_ERROR_DES( trim($tmp[count($tmp)-1]) );
            }

            $where = "WHERE DEL_DOC_NUM = '" . $row['DEL_DOC_NUM'] . "' "
                    ."AND CUST_CD = '" . $row['CUST_CD'] . "' "
                    ."AND STORE_CD = '" . $row['STORE_CD'] . "' "
                    ."AND AS_CD = '" . $row['AS_CD'] . "' "
                    ."AND AS_TRN_TP = '" . $row['AS_TRN_TP'] . "' "
                    ."AND ROWID = '" . $row['IDROW'] . "' ";

            $result = $asfm->update($where, false);

            if ( $result == false ){
                echo $asfm->getError(); 
            }
        }
    }

    function processOut( $syf ){
        //First encrypt file 
        if ( $syf->encrypt() ){
            if ( $syf->uploadSettlement() ){
                return true;
            }
            else{
                return false;
            }	
        }
    }
?>
