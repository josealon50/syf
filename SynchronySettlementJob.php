<?php
    include_once( '../config.php');
    include_once( './autoload.php' );
    include_once( './libs/PHPMailer/PHPMailerAutoload.php' );

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
        $where = "WHERE ASP_STORE_FORWARD.AS_CD = 'SYF' AND ASP_STORE_FORWARD.STAT_CD IN ( 'H' ) ";

        if ( $appconfig['synchrony']['PROCESS_STORE_CD'] !== '' ){
            $where .= " AND STORE_CD IN ( " . $appconfig['synchrony']['PROCESS_STORE_CD'] . " ) ";
        }

        if ( $appconfig['synchrony']['PROCESS_ONLY_SAL'] ){
            $where .= " AND ASP_STORE_FORWARD.AS_TRN_TP = 'PAUTH' ";
        }

        if ( $appconfig['synchrony']['PROCESS_FROM_DATE'] !== '' && $appconfig['synchrony']['PROCESS_TO_DATE'] !== '' ){
            $where = "AND TRUNC(CREATE_DT_TIME) BETWEEN '" . $appconfig['synchrony']['PROCESS_FROM_DATE'] . "' AND '" . $appconfig['synchrony']['PROCESS_TO_DATE'] . "' ";
        }
        else {
            //Calculate dates 
            $processedDates = getSettlementDates();
            $where .= "AND TRUNC(CREATE_DT_TIME) BETWEEN '" . $processedDates['FROM_DATE'] . "' AND '" . $processedDates['TO_DATE'] . "' ";
        }

        $recordsToUpdate = $syf->validateRecords( $db, $asfm, $settle, $totalSales, $totalReturns, $exceptions, $simpleRet, $exchanges, $validData, $delDocWrittens, $settlement, $transactionsPerStore );

        //PROCESS MANUAL TICKETS
        $where = "WHERE ASP_STORE_FORWARD.AS_CD = 'SYF' AND ASP_STORE_FORWARD.STAT_CD IN ( 'S' ) ";

        $postclauses = "ORDER BY STORE_CD, DEL_DOC_NUM";

        $result = $settle->query($where, $postclauses);

        if ( $result < 0 ){
            echo "AspStoreForward query error: " . $settle->getError() . "\n";
            return;
        }

        $tmp = $syf->validateRecords( $db, $asfm, $settle, $totalSales, $totalReturns, $exceptions, $simpleRet, $exchanges, $validData, $delDocWrittens, $settlement, $transactionsPerStore );

        $postclauses = "ORDER BY STORE_CD, DEL_DOC_NUM";

        $result = $settle->query($where, $postclauses);

        if ( $result < 0 ){
            echo "AspStoreForward query error: " . $settle->getError() . "\n";
            return;
        }

        $recordsToUpdate =  array_merge( $recordsToUpdate, $tmp );
        
        if ($argv[1] !== 4 ){
            //Generate settlement file 
            $totalAmountForBatch = 0;
            $totalRecordsForBatch = 0;

            fwrite($settlement, $syf->getBankHeader());
            //Call to write bank and batch trailer
            foreach( $transactionsPerStore as $key => $value ){
                //Make sure to format the string correctly
                $value['amount'] = number_format( $value['amount'], 2, '.', '' );

                //Sum totals for Bank trailer
                $totalAmountForBatch += number_format( $value['amount'], 2, '.', '' );
                $totalRecordsForBatch += $value['total_records']; 

                //Writing to settlement file bank and batch header
                fwrite($settlement, $syf->getBatchHeader($db, $key));
                fwrite($settlement, $value['records']);
                fwrite($settlement, $syf->getBatchTrailer( $db, $key, $value['total_records'], $value['amount'] ));
            }

            fwrite($settlement, $syf->getbanktrailer( $totalrecordsforbatch, number_format($totalamountforbatch, 2, '.', '') ));

        }

        $strMsg = "";
        
        if ( $argv[1] == 1 ){
            //Build exception file
            $error = buildExceptionFile( $exceptionReport, $records );
            if ( $error ){
                fwrite($mainReport, "SYF Settlement: Error Building Exception file\n" );
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

            //Build exception file
            $error = buildExceptionFile( $exceptionReport, $recordsToUpdate );
            if ( $error ){
                fwrite($mainReport, "SYF Settlement: Error Building Exception file\n" );
            }

            fclose( $settlement );
            fclose( $mainReport );
            fclose( $exceptionReport );

        }
        else if ( $argv[1] == 4 ){
            updateASFMRecords( $asfm, $recordsToUpdate );
        }
    }
    //Ran in mode 2
    else if ( $argv[1] == 2 ){
        $mainReport = fopen( $appconfig['synchrony']['REPORT_SYF_REPORT_OUT_DIR'] . "" . $appconfig['synchrony']['SYF_REPORT_FILENAME'], "w+" );
        $handle = fopen( $appconfig['synchrony']['REPORT_SYF_REPORT_OUT_DIR'] .  $appconfig['synchrony']['SYF_STORE_TOTALS'], 'r' );
        $db = sessionConnect();
        $syf= new SynchronyFinance( $db );

        if( !$syf->archive() ){
            fwrite( $mainReport, "Settlement File Decrypted was not archived\n");
        }

        if( processOut($syf) ){
            fwrite( $mainReport, "Settlement File Upload Status: Succesful\n");
            $today = new IDate();
            $body = "Settlement has ran succesful: " . $today->toString() . "\n\n\n";
            $body .= buildEmailBody( $handle );
            $syf->email($body);
        }
        else{
            fwrite( $mainReport, "Settlement File Upload Status: Unsuccesful\n");
            fwrite( $mainReport, "Settlement File Upload Error Code: " . $syf->getErrorCodeUpload() . "\n");
            fwrite( $mainReport, "Settlement File Upload Error Message: " . $syf->getErrorUploadMessage() . "\n");
            fwrite( $mainReport, "Please use the SYF upload module to reupload the settlement file\n" );
        }

        fclose($mainReport);
        fclose($handle);
    
    }
    else if ( $argv[1] == 5 ){
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

        $where = "WHERE ASP_STORE_FORWARD.AS_CD = 'SYF' AND ASP_STORE_FORWARD.STAT_CD IN ('H') ";

        if ( $appconfig['synchrony']['PROCESS_STORE_CD'] !== '' ){
            $where .= " AND ASP_STORE_FORWARD.STORE_CD IN ( " . $appconfig['synchrony']['PROCESS_STORE_CD'] . " ) ";
        }

        if ( $appconfig['synchrony']['PROCESS_ONLY_SAL'] ){
            $where .= " AND ASP_STORE_FORWARD.AS_TRN_TP = 'PAUTH' ";
        }

        if ( $appconfig['synchrony']['PROCESS_FROM_DATE'] !== '' && $appconfig['synchrony']['PROCESS_TO_DATE'] !== '' ){
            $where .= "AND TRUNC(CREATE_DT_TIME) BETWEEN '" . $appconfig['synchrony']['PROCESS_FROM_DATE'] . "' AND '" . $appconfig['synchrony']['PROCESS_TO_DATE'] . "' ";
        }
        else {
            //Calculate dates 
            $processedDates = getSettlementDates();
            $where .= "AND TRUNC(CREATE_DT_TIME) BETWEEN '" . $processedDates['FROM_DATE'] . "' AND '" . $processedDates['TO_DATE'] . "' ";
        }

        $postclauses = "ORDER BY STORE_CD, DEL_DOC_NUM";

        $result = $settle->query($where, $postclauses);
        if ( $result < 0 ){
            echo "AspStoreForward query error: " . $settle->getError() . "\n";
            exit();
        }

        $records = $syf->validateRecords( $db, $asfm, $settle, $totalSales, $totalReturns, $exceptions, $simpleRet, $exchanges, $validData, $delDocWrittens, $settlement, $transactionsPerStore );

        //Generate settlement file 
        $totalAmountForBatch = 0;
        $totalRecordsForBatch = 0;
        
        if( count($transactionsPerStore) > 0 ){
            fwrite($settlement, $syf->getBankHeader());
            //Call to write bank and batch trailer
            foreach( $transactionsPerStore as $key => $value ){
                //Make sure to format the string correctly
                $value['amount'] = number_format( $value['amount'], 2, '.', '' );

                //Sum totals for Bank trailer
                $totalAmountForBatch += number_format( $value['amount'], 2, '.', '' );
                $totalRecordsForBatch += $value['total_records']; 

                //Writing to settlement file bank and batch header
                fwrite($settlement, $syf->getBatchHeader($db, $key));
                fwrite($settlement, $value['records']);
                fwrite($settlement, $syf->getBatchTrailer( $db, $key, $value['total_records'], $value['amount'] ));
            }

            fwrite($settlement, $syf->getBankTrailer( $totalRecordsForBatch, number_format($totalAmountForBatch, 2, '.', '') ));

            //Build exception file
            $error = buildExceptionFile( $exceptionReport, $records );
            if ( $error ){
                fwrite($mainReport, "SYF Settlement: Error Building Exception file\n" );
            }

            if ( $argv[2] == 1 ){
                $handle = fopen( $appconfig['synchrony']['REPORT_SYF_SETTLE_OUT_DIR'] . "" . $appconfig['synchrony']['SYF_STORE_TOTALS'], "w+" );
                buildTotalReport( $handle, $transactionsPerStore );
                updateASFMRecords( $asfm, $records );

                fclose($handle);

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
                $asfm->set_ERROR_DES(trim(substr(implode($row['EXCEPTIONS'], ","), 0, 180)));
            }
            else{
                $asfm->set_ERROR_DES('');
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
    function buildExceptionFile( $handle, $records ){
        fwrite( $handle, "DEL_DOC_NUM, CUST_CD, STORE_CD, AS_CD, AS_TRN_TP, AMT, AUTH_CD, PROMO, SYF_PROMO_CD, FINAL_DATE, EXCEPTIONS\n" );
        //Check if exceptions are set  
        foreach( $records as $key => $value ){
            try{ 
                if ( $value['STAT_CD'] == 'E' ){
                    fwrite( $handle, $value['DEL_DOC_NUM'] . "," .$value['CUST_CD'] . "," . $value['STORE_CD'] . "," . $value['AS_CD'] . "," . $value['AS_TRN_TP'] . "," . $value['AMT'] . "," . $value['APP_CD'] . "," . $value['SO_ASP_PROMO_CD'] . "," . $value['SO_ASP_AS_PROMO_CD'] . "," . $value['FINAL_DT'] . "," . implode(",", $value['EXCEPTIONS']) . "\n" );
                }
            }
            catch( Exception $e ){
                return false;
            }
       }
        return true;
    }

    function getSettlementDates(){
        global $appconfig;
        
        $dates = [];

        $fromDate = new IDate();
        $toDate = new IDate();

        //Check if today is Monday 
        if ( date('D') == 'Mon' ){
            $twoDays = date("Y-m-d", strtotime("-3 day"));      
            $fromDate->setDate( $twoDays );

            $oneDay = date("Y-m-d", strtotime("-1 day"));      
            $toDate->setDate( $oneDay );

            $dates['FROM_DATE'] = $fromDate->toStringOracle();
            $dates['TO_DATE'] = $toDate->toStringOracle();

            return $dates;
            
        }

        $oneDay = date("Y-m-d", strtotime("-1 day"));      
        $fromDate->setDate( $oneDay );
        $toDate->setDate( $oneDay );

        $dates['FROM_DATE'] = $fromDate->toStringOracle();
        $dates['TO_DATE'] = $toDate->toStringOracle();

        return $dates;
    }

    function buildEmailBody( $handle ) {
        global $appconfig;
        $total = 0;
        $style = " style='border: 1px solid black;'";
        $body = "<table" .$style . "><tr" . $style ."><th" . $style . ">Store Code</th><th" . $style . ">Total Transactions Processed</th><th" . $style . ">Total Amount Processed</th></tr>";
        $row = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row++;
            if($row == 1) continue;
            $total = number_format( $total + $data['2'], 2, '.', '' );
            $body .= "<tr" . $style . ">";
            $body .= "<td" . $style . ">" . $data[0]  . "</td>"; 
            $body .= "<td" . $style . ">" . $data[1]   . "</td>"; 
            $body .= "<td" . $style . ">" . $data['2']  . "</td>"; 
            $body .= "</tr>"; 
        }
        $body .= "</table>";
        return $body;

    }

    function buildTotalReport( $handle, $transactionsPerStore ){
        global $appconfig;

        $total = 0;
        fwrite( $handle,  "Store Code,Total Transactions Processed,Total Amount Processed\n" );
        foreach( $transactionsPerStore as $key => $value ){
            $total = number_format( $total + $value['amount'], 2, '.', '' );
            $body = $key . "," . $value['total_records'] . "," . $value['amount'] . "\n"; 
            fwrite( $handle, $body );
        }   
        fwrite( $handle, ",," . $total . "\n" );

        return;
    }

?>
