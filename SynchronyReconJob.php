<?php
        /***************************************************************************************
        ****************************************************************************************
        ****************************************************************************************
        * Author: Jose Leon
        * Date: 04/15/21
        * Usage: php SynchronyReconJob.php FROM_DATE TO_DATE STORES
        *   - Example Parameters
        *      * FROM_DATE: 2020-02-03
        *      * TO_DATE: 2020-02-04
        *      * STORES: If runtime parameter is empty will assume all stores 
        *           * EE,FF,XX  
        * Description: The following script will connect to Synchrony SFTP server and download 
        * the files given the following date range. After downloading the file the script will 
        * parse the file and check for only sale transactions if any (credits) payments to SYF
        * will be reported as error. There will be a transaction lookup on SO with customers 
        * account number, last 4 digits of account and transaction date if no transaction found 
        * records will need to be manually settled. After parsing the file data will be staged 
        * into table ASP_RECON. After the data has been staged in ASP_RECON records will be 
        * moved to AR_TRN. Finally an email will be sent to the AR team for each file processed
        * with the following attachments transactions processed and errors.
        * 
        ****************************************************************************************
        ****************************************************************************************
        ***************************************************************************************/

        include_once( '../config.php');
        include_once( './autoload.php' );
        include_once( './libs/SimpleXLSX.php' );
        include_once( './libs/PHPMailer/PHPMailerAutoload.php' );

        global $appconfig, $logger;

	    $logger = new ILog($appconfig['username'], "recon" . date('ymdhms') . ".log", $appconfig['log_folder'], $appconfig['log_priority']);
        $mor = new Morcommon();
        $db = $mor->standAloneAppConnect();
        $syf = new SynchronyFinance( $db );
        $now = new IDate();
        $date = new IDate();

        $errors = [];
        $dates = [];
        $stores = [];
        $file = '';
        $decrypt = TRUE;
        $total = 0;
        $audit = $argv[1] == 1;

        //Download file from Synchrony
        $logger->debug( "Synchrony Reconciliation: Starting process " . date("Y-m-d h:i:sa") );
        if ( $argv[1] == 2 ){ 
            $logger->debug( "Synchrony Reconciliation: Process Only ASP_RECON staged records" );
            $storesTotal = processASPRecon($db, false, $mor);
            exit();

        }
        //Check if there is any recon files in folder
        if (empty($appconfig['recon']['RECON_FOLDER'])) {
            $logger->debug( "Synchrony Reconciliation: No files found" );
            exit();
        }
        else{
            $logger->debug( "Synchrony Reconciliation: Recon files found" );

            //Open directory and read files
            if( is_dir( $appconfig['recon']['RECON_FOLDER'] )){
                if ( $dir = opendir( $appconfig['recon']['RECON_FOLDER'] )){
                    while (($file = readdir($dir)) !== false) {
                        if( $file == '.' || $file == '.DS_Store' || $file == '..' ) continue;
                        $logger->debug( "Synchrony Reconciliation: Processing " . $file );

                        //Read file 
                        if( ($handle = fopen( $appconfig['recon']['RECON_FOLDER'] . '/' . $file, 'r' )) !== FALSE ){ 
                            while (($line = fgetcsv($handle, 1000, ",")) !== FALSE) {
                                $aspRecon = new ASPRecon($db);

                                //If no auth code process next record
                                if( $line[7] == '' ){
                                    continue;
                                }
                                if( $line[1] === "MERCHANT NBR" ){
                                    continue;
                                }
                                $line[8] = str_replace( ',', '', $line[8] );

                                $so = new SalesOrder( $db );
                                //Find SO record with approval code and amount 
                                $where  = "WHERE ORIG_FI_AMT = '" . $line[8] . "' AND APPROVAL_CD = '" . $line[7] . "' ";

                                $result = $so->query( $where );
                                if ( $result < 0 ) {
                                    $logger->debug( "Synchrony Reconciliation: Could not query SO" );
                                    exit();
                                }
                                if( $so->next() ){
                                    $logger->debug( "Synchrony Reconciliation: SO Record found" );
                                    $record = buildRecord( $so, $line );

                                    processIntoAsp( $db, $aspRecon, $record, '' );
                            
                                }
                                else{
                                    $logger->debug( "Synchrony Reconciliation: SO Record not found" );
                                    $logger->debug( print_r($line, 1) );

                                    $record = buildRecord( null, $line );
                                    processIntoAsp( $db, $aspRecon, $record, "SO RECORD NOT FOUND" );
                                }
                            }
                            //Archive file 
                            rename( $appconfig['recon']['RECON_FOLDER'] . '/' . $file, "./archive/" . $file . '.' . date("Y-m-d h:i:sa") );

                            $storesTotal = processASPRecon( $db, $audit, $mor );
                            
                            $logger->debug( "Synchrony Reconciliation: Total by Stores " );
                            $logger->debug( print_r($storesTotal, 1) );
                            $logger->debug( "Synchrony Reconciliation: Total " . number_format($total, 2, '.', '') );
                            $logger->debug( $total );

                            if( count($storesTotal) > 0 ){
                                //Send Email 
                                $style = " style='border: 1px solid black;'";
                                $body = "<table" .$style . "><tr" . $style ."><th" . $style . ">Store Code</th><th" . $style . ">Total Transactions Processed</th><th" . $style . ">Total Amount Processed</th></tr>";
                                foreach( $storesTotal as $key => $store ){
                                    $body .= "<tr" . $style . ">";
                                    $body .= "<td" . $style . ">" . $key  . "</td>"; 
                                    $body .= "<td" . $style . ">" . $store['total_records']   . "</td>"; 
                                    $body .= "<td" . $style . ">" . $store['total']  . "</td>"; 
                                    $body .= "</tr>"; 
                                }
                                $body .= "</table>";
                    
                                //Build csv errors array
                                $handle = fopen( './out/syf_recon_error.csv', 'w+');
                                $header = "STORE_CD,AS_CD,AMT,BNK_CRD_NUM,SYF_PROCESS_DT\n"; 
                                fwrite( $handle, $header );  
                                foreach( $errors as $error ){
                                    fwrite( $handle, $error['ORIGIN_STORE'] . "," . 'SYF' . "," . $error['AMT'] . "," . $error['BNK_CRD_NUM'] . "," . $error['PROCESS_DT']->format( 'Y-m-d') . "\n" );
                                }
                                fclose($handle);
                                if ( !emailRecon( $body, $date ) ){
                                   $logger->debug("Synchrony Reconciliation: Email did not send" ); 
                                }
                            }
                        }
                    }
                }
            }
        }
        

        function emailRecon( $body, $date ){
            global $appconfig;

            $mail = new PHPMailer;
            $mail->isSMTP();
            $mail->Host = $appconfig['email']['HOST'];
            $mail->Port = $appconfig['email']['PORT'];
            $mail->From     =  $appconfig['email']['FROM'];
            $mail->FromName = $appconfig['email']['FROM_NAME'];
            $mail->addAddress($appconfig['email']['TO']); //should go to finance@morfurniture.com
            $mail->addReplyTo('');
            $mail->WordWrap = 50;

            $mail->isHTML(true);
            $mail->Subject = 'Synchrony Reconciliation for date: ' . $date->toString();
            
            $mail->Body    = $body;

            if(!$mail->send()) {
                return -1;
            } 
            return true;


        }

        function getDatesFromRange( $from, $to ){
            global $appconfig;

            $period = new DatePeriod( new DateTime($from), new DateInterval('P1D'), new DateTime($to) );
            return iterator_to_array($period);


        }

        function buildRecord( $so, $transaction ) {
            $tmp = array();

            $tmp['ORIGIN_STORE'] = is_null($so) ? '00' : $so->get_SO_STORE_CD();
            $tmp['STATUS'] = 'H';
            $tmp['BNK_CRD_NUM'] = substr( $transaction[4], -4 );
            $tmp['DEL_DOC_NUM'] = is_null($so) ? '' : $so->get_DEL_DOC_NUM();
            $tmp['AMT'] = $transaction[8];
            $tmp['DES'] = $transaction[2];
            $tmp['DISCOUNT'] = $transaction[9] == '' ? '0' : number_format($transaction[9] * -1, 2, '.', '' );
            $tmp['TOTAL_AMT'] = number_format( $tmp['AMT'] - $tmp['DISCOUNT'], 2, '.', '' );
            $tmp['AS_CD'] = 'SYF';
            $tmp['PROCESS_DT'] = $transaction[3];
            $tmp['TYPE'] = 'S';
            $tmp['CREDIT_OR_DEBIT'] = 'D';
            
            //Use post date 
            $postIDate = new IDate($transaction[8], 'Ymd'); 
            $tmp['POST_DT'] = $postIDate->toStringOracle();

            return $tmp;

        }

        function processIntoAsp( $db, $aspRecon, $record, $error ){
            global $appconfig, $logger;

            $date = new IDate();
            $now = new IDate();
            $storesTotal = [];
            $date->setDate( $record['PROCESS_DT'] );

            $aspRecon->set_CREATE_DT( $now->toStringOracle() );
            $aspRecon->set_AS_CD( 'SYF' );
            $aspRecon->set_AS_STORE_CD( $record['ORIGIN_STORE'] );
            $aspRecon->set_ORIGIN_STORE( $record['ORIGIN_STORE'] );
            $aspRecon->set_CREDIT_OR_DEBIT( $record['CREDIT_OR_DEBIT'] );
            $aspRecon->set_PROCESS_DT( $record['POST_DT'] );
            $aspRecon->set_STATUS( $error == '' ? $record['STATUS'] : 'E' );
            $aspRecon->set_RECORD_TYPE( $record['TYPE'] );
            $aspRecon->set_BNK_CRD_NUM( $record['BNK_CRD_NUM'] );
            $aspRecon->set_IVC_CD( $record['DEL_DOC_NUM'] );
            $aspRecon->set_AMT( $record['TOTAL_AMT'] );
            $aspRecon->set_DES( 'PURCHASE' );
            $aspRecon->set_EXCEPTIONS($error); 

            $processed = $aspRecon->isRecordProcessed( $record );
            if ( $processed ){
                $logger->debug( "Synchrony Reconciliation: Record has been processed: " . print_r( $record, 1) );
                $error .= ',RECORD HAS BEEN PROCESSED'; 
                $record['STATUS'] = 'E';
            }
            if( $record['DES'] !== 'SALE' ){
                $logger->debug( "Synchrony Reconciliation:  Credit Record found" );
                if ( strlen($error) > 0 ){
                    $error .= ',';
                }
                $error .= 'CREDIT RECORD FOUND';
                $record['STATUS'] = 'E';
            }

            if ( !$processed  ){
                //Check if the there is any staged records
                if ( !$aspRecon->isRecordStaged($record) ){;
                    $error = $aspRecon->insert( true, false );
                    if( !$error ){
                        $logger->debug( "Synchrony Reconciliation: Error on INSERT ASP_RECON" );
                    }

                    //Insert discount record
                    $aspRecon = new ASPRecon($db); 

                    $aspRecon->set_CREATE_DT( $now->toStringOracle() );
                    $aspRecon->set_AS_CD( 'SYF' );
                    $aspRecon->set_AS_STORE_CD( $record['ORIGIN_STORE'] );
                    $aspRecon->set_ORIGIN_STORE( $record['ORIGIN_STORE'] );
                    $aspRecon->set_CREDIT_OR_DEBIT( $record['CREDIT_OR_DEBIT'] );
                    $aspRecon->set_PROCESS_DT( $record['POST_DT'] );
                    $aspRecon->set_STATUS( $record['STATUS'] );
                    $aspRecon->set_RECORD_TYPE( $record['TYPE'] );
                    $aspRecon->set_BNK_CRD_NUM( $record['BNK_CRD_NUM'] );
                    $aspRecon->set_IVC_CD( $record['DEL_DOC_NUM'] );
                    $aspRecon->set_AMT( $record['DISCOUNT'] );
                    $aspRecon->set_DES( 'ACQUISITION' );
                    $aspRecon->set_EXCEPTIONS(''); 

                    $error = $aspRecon->insert( true, false );
                    if( !$error ){
                        $logger->debug( "Synchrony Reconciliation: Error on INSERT ASP_RECON #2" );
                    }
                }
                else{
                    return;
                }
            }
        }

        function processASPRecon( $db, $audit, $mor ){
            global $logger;
            
            //After prepping data insert into AR_TRN
            $aspRecon = new ASPRecon($db);
            $storesTotal = [];
            $result = $aspRecon->query("WHERE STATUS = 'H' and DES in ( 'PURCHASE', 'ACQUISITION' ) AND AS_CD='SYF'");

            if( $result <  0 ){
                $logger->debug("Synchrony Reconciliation: Error query on ASP_RECON" );
            }
            if ( $audit ){
                //Auditing inserts to AR_TRN 
                $auditArTrn = fopen( './out/audit_ar_trn.csv', 'w+' );
                fwrite( $auditArTrn, "CO_CD,CUST_CD,MOP_CD,EMP_CD_CSHR,EMP_CD_OP,ORIGIN_STORE,CSH_DWR_CD,TRN_TP_CD,POST_DT,CREATE_DT,STAT_CD,AR_TP,IVC_CD,PMT_STORE,ORIGIN_CD\n");
            }
            while ($aspRecon->next()) {
                if ( !$audit ){
                    $artrn = new ArTrn($db);
                    $artrn->set_CO_CD('BSS');
                    $artrn->set_CUST_CD('SYF');
                    $artrn->set_MOP_CD('CS');
                    $artrn->set_EMP_CD_CSHR('92388');
                    $artrn->set_EMP_CD_OP('92388');
                    $artrn->set_ORIGIN_STORE($aspRecon->get_ORIGIN_STORE());
                    $artrn->set_CSH_DWR_CD('00');
                    //Set TRN_TP_CD depending on description
                    $artrn->set_TRN_TP_CD($aspRecon->get_DES() == 'PURCHASE' ? 'PMT' : 'FDC' );
                    $artrn->set_AMT($aspRecon->get_AMT());
                    $artrn->set_POST_DT( $aspRecon->get_PROCESS_DT() );

                    //date the FCRIN file was created on SYF end
                    $artrn->set_CREATE_DT($aspRecon->get_CREATE_DT('d-M-Y'));
                    $artrn->set_STAT_CD('T');
                    $artrn->set_AR_TP('O');
                    $artrn->set_IVC_CD($aspRecon->get_IVC_CD());
                    $artrn->set_PMT_STORE('00');
                    $artrn->set_ORIGIN_CD('FCRIN');
                    $artrn->set_DOC_SEQ_NUM('');

                    $insertCheck = $artrn->insert( false, false );

                    if ($insertCheck === false) {
                        $logger->debug( "Synchrony Reconciliation: Error on insert ");
                    } 
                    else {
                        //UPDATES THE RECORD TO 'P' from 'H' if the insert is successful
                        $updt = new ASPRecon($db);
                        $result = $updt->query('where ID = ' .$aspRecon->get_ID());
                        if ( $result < 0 ) {
                            $logger->debug( "Synchrony Reconciliation: Could not query SO" );
                        }

                        if ($updt->next()) {
                            $updt->set_STATUS('P');
                            $result = $updt->update('where ID = ' .$updt->get_ID(), false);

                            if( $result < 0 ){
                                $logger->debug( "Synchrony Reconciliation: Could not update ASP_RECON #2" );
                            }

                            //return stores total
                            if ( !isset($storesTotal[$aspRecon->get_ORIGIN_STORE()]) ){
                                $storesTotal[$aspRecon->get_ORIGIN_STORE()]['total'] = $aspRecon->get_AMT();
                                $storesTotal[$aspRecon->get_ORIGIN_STORE()]['total_records'] = 1;
                            }
                            else{
                                $storesTotal[$aspRecon->get_ORIGIN_STORE()]['total'] = floatval( $storesTotal[$aspRecon->get_ORIGIN_STORE()] ) + floatval( $aspRecon->get_AMT() );
                                $storesTotal[$aspRecon->get_ORIGIN_STORE()]['total_records'] = $storesTotal[$aspRecon->get_ORIGIN_STORE()]['total_records'] + 1;
                            }
                        }
                    }
                }
                else{
                    fwrite( $auditArTrn, "BSS,SYF,CS,92388,92388," . $aspRecon->get_ORIGIN_STORE() . ",00,PMT," . $now->toStringOracle() . "," . $aspRecon->get_CREATE_DT('d-M-Y') . ",T,0," . $aspRecon->get_IVC_CD() . ",00,FCRIN\n");
                }
            }
            if ( $audit ){
                fclose($auditArTrn);
            }

            return $storesTotal;


        }
?>
