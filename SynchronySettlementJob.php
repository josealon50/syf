<?php
    require_once( './config.php');
    require_once("/home/public_html/weblibs/iware/php/utils/IAutoLoad.php");
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
    //require_once("../../public/libs".DIRECTORY_SEPARATOR."iware".DIRECTORY_SEPARATOR."php".DIRECTORY_SEPARATOR."utils".DIRECTORY_SEPARATOR."IAutoLoad.php");

    set_include_path(get_include_path() . PATH_SEPARATOR . 'libs/phpseclib');
    include 'Net/SFTP.php';
    include 'Crypt/RSA.php';

    global $appconfig;

    //Only generate settlement or run normal
    if ( $argv[1] == 1 ||  $argv[2] == 3 ){
        $settlement = fopen( $appconfig['synchrony']['REPORT_SYF_SETTLE_OUT_DIR'] . "" . $appconfig['synchrony']['SYF_SETTLE_FILENAME_DEC'], "w+" );
        $mainReport = fopen( $appconfig['synchrony']['REPORT_SYF_REPORT_OUT_DIR'] . "" . $appconfig['synchrony']['SYF_REPORT_FILENAME'], "w+" );

        $transactionsPerStore = [];

        $db = sessionConnect();
        $settle = new SettlementInfo($db);
        $syf= new SynchronyFinance( $db );
        $asfm = new ASPStoreForward($db);

        fwrite( $mainReport, "Settlement process for SYF\n" );
        fwrite( $mainReport, "Date: "  . date("F j, Y, g:i a") . "\n");
        fwrite( $mainReport, "Settlement File name: " . $syf->getFilenameNoExt() . "\n" );


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

        //Get all manual tickets 
        $where = "WHERE ASP_STORE_FORWARD.AS_CD = 'SYF' AND ASP_STORE_FORWARD.STAT_CD = 'S'";

        $postclauses = "ORDER BY STORE_CD, DEL_DOC_NUM";

        $result = $settle->query($where, $postclauses);
        if ( $result < 0 ){
            echo "AspStoreForward query error: " . $settle->getError() . "\n";
            return;
        }
        $rec = $syf->validateRecords( $db, $asfm, $settle, $totalSales, $totalReturns, $exceptions, $simpleRet, $exchanges, $validData, $delDocWrittens, $settlement, $transactionsPerStore );

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
        
        if ( $argv[1] == 1 ){
            fwrite( $mainReport, "Settlement File was not upload ran in mode: 1\n");
            fclose( $settlement );
            fclose( $mainReport );
            exit();
        }
        else{
            //First encrypt file 
            if ( $syf->encrypt() ){
                if ( $syf->uploadSettlement() ){
                    $strMsg = "File successfully uploaded.";
                    echo $strMsg."\n";
                    fwrite( $mainReport, "Settlement File Upload Status: Succesful\n");
                }
                else{
                    $strMsg = "Upload File: " . $syf->getFilename() . "  To SYF failed. Please contact MIS immediately.";
                    echo $strMsg."\n";

                    fwrite( $mainReport, "Settlement File Upload Status: Unsuccesful\n");
                    fwrite( $mainReport, "Settlement File Upload Error Code: " . $syf->getErrorCodeUpload() . "\n");
                    fwrite( $mainReport, "Settlement File Upload Error Message: " . $syf->getErrorUploadMessage() . "\n");
                    fwrite( $mainReport, "Please use the SYF upload module to reupload the settlement file\n" );
                }	
            }
            else{
                exit();
            }
        }
        //Check for records that are finalized in SO that are not in ASFM
        $finalizedSales = new FinalizedSales($db);

        $where =  "WHERE ASP_TRN.DEL_DOC_NUM IS NULL "
                 ."AND FINAL_DT BETWEEN TO_DATE('" . $beg_final_dt . "', 'DD-MON-YY') AND TO_DATE('" . $end_final_dt . "', 'DD-MON-YY') "
                 ."AND SO.STAT_CD = 'F' "
                 ."AND SO.FIN_CUST_CD = 'SYF' "
                 ."AND AS_CD = 'SYF'";

        $result = $finalizedSales->query($where);

        if ( $result < 0 ){
            echo "Error finalizedSales: " . $finalizedSales->getError();
        }

        while( $finalizedSales->next() ){
            echo "Del Doc Num in ASFM: " . $finalizedSales->get_DEL_DOC_NUM() . "\n";
        }

        //Write complete report
        $report = $syf->createMainReport( $mainReport, $exceptions, $simpleRet, $exchanges, $manuals, $agingRet, $agingExc, $evenExchangesErrors );

        //$syf->closeReportFiles();

        // Send email that the SETTL process has completed
        $syf->emailSettleCompleted( $appconfig, "SYF", $appconfig['MAIN_REPORT_DIR'] . $syf->getMainReportName(), $strMsg );        
    }
    else{
        $mainReport = fopen( $appconfig['synchrony']['REPORT_SYF_REPORT_OUT_DIR'] . "" . $appconfig['synchrony']['SYF_REPORT_FILENAME'], "w+" );
        $db = sessionConnect();
        $syf= new SynchronyFinance( $db );

        //First encrypt file 
        if ( $syf->encrypt() ){
            if ( $syf->uploadSettlement() ){
                $strMsg = "File successfully uploaded.";
                fwrite( $mainReport, "Settlement File Upload Status: Succesful\n");
            }
            else{
                $strMsg = "Upload File: " . $syf->getFilename() . "  To SYF failed. Please contact MIS immediately.";

                fwrite( $mainReport, "Settlement File Upload Status: Unsuccesful\n");
                fwrite( $mainReport, "Settlement File Upload Error Code: " . $syf->getErrorCodeUpload() . "\n");
                fwrite( $mainReport, "Settlement File Upload Error Message: " . $syf->getErrorUploadMessage() . "\n");
                fwrite( $mainReport, "Please use the SYF upload module to reupload the settlement file\n" );
            }	
        }
        else{
            fwrite( $mainReport, "Settlement File: Encryption Failed \n");
            exit();
        }
    
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
?>
