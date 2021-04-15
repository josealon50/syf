<?php
        include_once( '../config.php');
        include_once( './autoload.php' );
        include_once( './libs/PHPMailer/PHPMailerAutoload.php' );

        set_include_path(get_include_path() . PATH_SEPARATOR . 'libs/phpseclib');
        include 'Net/SFTP.php';
        include 'Crypt/RSA.php';


        global $appconfig, $logger;

	    $logger = new ILog($appconfig['username'], "recon" . date('ymdhms') . ".log", $appconfig['log_folder'], $appconfig['log_priority']);
        $mor = new Morcommon();
        $db = $mor->standAloneAppConnect();
        $syf = new SynchronyFinance( $db );
        $now = new IDate();

        $dates = getDatesFromRange( $argv[1], $argv[2] );
        $errors = [];

        //Build array of stores to be processed
        $stores = [];
        if( count($argv) == 4 ){
            $stores = explode( ',', $argv[3] );
        }

        //Download file from Synchrony
        $logger->debug( "Synchrony Reconciliation: Starting proces " . date("Y-m-d") );
        foreach( $dates as $date ){
            $files = $syf->download( $date->format("Ymd") );
            //$files = [ 'recon.20210310090019.txt' ];
            if ( count($files) > 0 ){
                foreach( $files as $file ){
                    $logger->debug( "Synchrony Reconciliation: Processing " . $file );
                    if ( $syf->decrypt($file) ){
                        $logger->debug( "Synchrony Reconciliation: Decrypting " . $file  . " Succesful ");
                        $handle = fopen( $appconfig['synchrony']['SYF_RECON_IN'] . $file, 'r' ) or die ( $logger->debug("Synchrony Reconciliation: Unable to open recon file: " . $file) );
                        $records = $syf->parseSYFRecon( $handle, $stores );

                        if( count($records) == 0 ){
                            $logger->debug( "Synchrony Reconciliation: No records to process");
                            continue;
                        }
                        //Insert records into ASP_RECON
                        $aspRecon = new ASPRecon($db);

                        $storesTotal = [];
                        $total = 0;
                        $logger->debug( "Synchrony Reconciliation: Processing records " . count($records) );
                        foreach( $records as $stores ){
                            foreach( $stores as $record ){
                                $acct = encryptAcct( $record['ACCT_NUM'] );

                                //Query record on MOR ASP history table 
                                $syfSo = new SyfSalesOrder($db);
                                $syfSo = $syfSo->getSyfSalesOrder( $record['AMT'], $record['ORIGIN_STORE'], $record['PROMO_CD'], $acct );

                                if( is_null($syfSo) ){
                                    $logger->debug( "Synchrony Reconciliation: History transaction Record not found" );
                                    $logger->debug( print_r($record, 1) );
                                    array_push( $errors, $record );
                                }
                                else{
                                    $record['DEL_DOC_NUM'] = $syfSo->get_DEL_DOC_NUM();
                                    //Check if records have been processed
                                    $processed = $aspRecon->isRecordProcessed( $record );

                                    $error = '';
                                    if ( $processed ){
                                        $logger->debug( "Synchrony Reconciliation: Record has been processed: " . print_r( $record, 1) );
                                        $error = 'RECORD HAS BEEN PROCESSED'; 
                                    }
                                    if( $record['DES'] == 'CREDIT' ){
                                        $logger->debug( "Synchrony Reconciliation:  Credit Record found" );
                                        if ( strlen($error) > 0 ){
                                            $error .= ',';
                                        }
                                        $error .= 'CREDIT RECORD FOUND';
                                    }

                                    if ( !isset($storesTotal[$record['ORIGIN_STORE']]) ){
                                        $storesTotal[$record['ORIGIN_STORE']]['total'] = $record['AMT'];
                                        $storesTotal[$record['ORIGIN_STORE']]['total_records'] = 1;
                                    }
                                    else{
                                        $storesTotal[$record['ORIGIN_STORE']]['total'] = floatval( $storesTotal[$record['ORIGIN_STORE']] ) + floatval($record['AMT']);
                                        $storesTotal[$record['ORIGIN_STORE']]['total_records'] = $storesTotal[$record['ORIGIN_STORE']]['total_records'] + 1;
                                    }
                                    $total = floatval($total) + floatval($storesTotal[$record['ORIGIN_STORE']]);

                                    $date = new IDate();
                                    $date->setDate( $record['PROCESS_DT']->format('Y-m-d H:i:s') );

                                    $aspRecon->set_CREATE_DT( $now->toStringOracle() );
                                    $aspRecon->set_AS_CD( 'SYF' );
                                    $aspRecon->set_AS_STORE_CD( $record['ORIGIN_STORE'] );
                                    $aspRecon->set_ORIGIN_STORE( $record['ORIGIN_STORE'] );
                                    $aspRecon->set_CREDIT_OR_DEBIT( $record['CREDIT_OR_DEBIT'] );
                                    $aspRecon->set_PROCESS_DT( $date->toStringOracle() );
                                    $aspRecon->set_STATUS( $error !== '' ? $record['STATUS'] : 'E' );
                                    $aspRecon->set_RECORD_TYPE( $record['TYPE'] );
                                    //$aspRecon->set_ACCT_NUM_PREFIX( $record['BNK_CRD_NUM'] );
                                    $aspRecon->set_BNK_CRD_NUM( $record['BNK_CRD_NUM'] );
                                    $aspRecon->set_IVC_CD( $record['DEL_DOC_NUM'] );
                                    $aspRecon->set_AMT( $record['AMT'] );
                                    $aspRecon->set_DES( $record['DES'] );
                                    $aspRecon->set_EXCEPTIONS($error); 

                                    if ( $processed && $error !== '' ){
                                        $where = "WHERE AS_CD = 'SYF' AND AS_STORE_CD = '" .$record['ORIGIN_STORE'] . "' AND IVC_CD = '" . $record['DEL_DOC_NUM'] . "' AND AMT = '" . $record['AMT'] . "' ";
                                        $error = $aspRecon->update( $where, false );
                                        if( $error < 0 ){
                                            $logger->debug( "Synchrony Reconciliation: Error on UPDATING ASP_RECON" );
                                            $logger->debug( print_r($record, 1) );
                                        }

                                    }
                                    else{
                                        $error = $aspRecon->insert( true, false );
                                        if( !$error ){
                                            $logger->debug( "Synchrony Reconciliation: Error on INSERT ASP_RECON" );
                                        }
                                    }
                                }
                                //After prepping data insert into AR_TRN
                                $aspRecon = new ASPRecon($db);
                                $result = $aspRecon->query("WHERE STATUS = 'H' and DES = 'PURCHASE'");

                                if( $result <  0 ){
                                    $logger->debug("Synchrony Reconciliation: Error query on ASP_RECON" );
                                }
                                while ($aspRecon->next()) {
                                    $artrn = new ArTrn($db);
                                    $artrn->set_CO_CD('BSS');
                                    $artrn->set_CUST_CD('SYF');
                                    $artrn->set_MOP_CD('CS');
                                    $artrn->set_EMP_CD_CSHR('92388');
                                    $artrn->set_EMP_CD_OP('92388');
                                    $artrn->set_ORIGIN_STORE($aspRecon->get_ORIGIN_STORE());
                                    $artrn->set_CSH_DWR_CD('00');
                                    $artrn->set_TRN_TP_CD('PMT');
                                    
                                    //converts the 2nd argument into the POST_DT
                                    $now = new IDate();
                                    $artrn->set_POST_DT($now->toStringOracle());

                                    //date the FCRIN file was created on SYF end
                                    $artrn->set_CREATE_DT($aspRecon->get_CREATE_DT('d-M-Y'));
                                    $artrn->set_STAT_CD('T');
                                    $artrn->set_AR_TP('O');
                                    $artrn->set_IVC_CD($aspRecon->get_IVC_CD());
                                    $artrn->set_PMT_STORE('00');
                                    $artrn->set_ORIGIN_CD('FCRIN');
                                    $artrn->set_DOC_SEQ_NUM(genDocNum($db, '00'));
                                    $insertCheck = $artrn->artrn();
                                    if ($insertCheck === false) {
                                        $logger->debug( "Synchrony Reconciliation: Error on insert ");
                                    } 
                                    else {
                                        //UPDATES THE RECORD TO 'P' from 'H' if the insert is successful
                                        $aspRecon->aspRecon('where ID = ' .$aspRecon->get_ID());
                                        if ($aspRecon->next()) {
                                            $aspRecon->set_STATUS('P');
                                            $aspRecon->update('where ID = ' .$aspRecon->get_ID(), false);
                                        }
                                    }
                                }
                            }

                            $logger->debug( "Synchrony Reconciliation: Archiving " . $file );
                            if( !copy($appconfig['synchrony']['SYF_RECON_IN'] . $file, $appconfig['synchrony']['SYF_RECON_ARCHIVE'] . $file . '.' . date('Ymd')) ){
                                $logger->debug( "Synchrony Reconciliation: Error archiving " . $file );
                            }
                            else{
                                $logger->debug( "Synchrony Reconciliation: Archiving " . $file . " Succesful ");
                                //unlink( $appconfig['synchrony']['SYF_RECON_IN'] . $file );
                            }
                        }
                    }
                    else{
                        $logger->debug( "Synchrony Reconciliation: Decrypting " . $file  . " Failed ");
                    }
                }
            }

            $logger->debug( "Synchrony Reconciliation: Total by Stores " );
            $logger->debug( print_r($storesTotal, 1) );
            $logger->debug( "Synchrony Reconciliation: Total " . number_format($total, 2, '.', '') );
            $logger->debug( $total );

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
            $header = 'STORE_CD,AS_CD,AMT,ACCT_NUM,BNK_CRD_NUM,SYF_PROCESS_DT'; 
            fwrite( $handle, $header );  
            foreach( $errors as $error ){
                fwrite( $handle, $error['ORIGIN_STORE_CD'] . "," . 'SYF' . "," . $error['AMT'] . "," . $error['ACCT_NUM'] . "," . $error['BNK_CRD_NUM'] . "," . $error['PROCESS_DATE']->format( 'Y-m-d') . "\n" );
            }
            fclose($handle);

            if ( !emailRecon( $body ) ){
               $logger->debug("Synchrony Reconciliation: Email did not send" ); 
            }

        }

        function emailRecon( $body ){
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
            $mail->Subject = $appconfig['syf']['SYF_RECON_EMAIL_SUBJECT'];
            
            $mail->Body    = $body;

            if(!$mail->send()) {
                echo 'Message could not be sent.'."\n";
                echo 'Mailer Error: ' . $mail->ErrorInfo;
                return -1;
            } else {
                echo 'Message has been sent'."\n\n";
            }
            return true;


        }

        function getDatesFromRange( $from, $to ){
            global $appconfig;

            $period = new DatePeriod( new DateTime($from), new DateInterval('P1D'), new DateTime($to) );
            return iterator_to_array($period);


        }

        function encryptAcct( $acctNum ) {
            global $appconfig;

            //Encrypt Account number
            $encryption = openssl_encrypt($acctNum, $appconfig['ciphering'], $appconfig['encryption_key'], $appconfig['options'], $appconfig['encryption_iv']);


            return $encryption;
        }



?>
