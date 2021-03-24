<?php
        include_once( '../config.php');
        include_once( './autoload.php' );
        include_once( './libs/PHPMailer/PHPMailerAutoload.php' );

        set_include_path(get_include_path() . PATH_SEPARATOR . 'libs/phpseclib');
        include 'Net/SFTP.php';
        include 'Crypt/RSA.php';


        global $appconfig, $logger;

        $mor = new Morcommon();
        $db = $mor->standAloneAppConnect();
        $syf = new SynchronyFinance( $db );


        //Download file from Synchrony
        $files = $syf->download();
        $logger->debug( "Synchrony Reconciliation: Starting proces " . date("Y-m-d") );
        if ( count($files) > 0 ){
            foreach( $files as $file ){
                $logger->debug( "Synchrony Reconciliation: Processing " . $file );
                if ( $syf->decrypt($file) ){
                    $logger->debug( "Synchrony Reconciliation: Decrypting " . $file  . " Succesful ");
                    $handle = fopen( $appconfig['synchrony']['SYF_RECON_IN'] . $appconfig['synchrony']['SYF_RECON_FILENAME'], 'r' ) or die ( "Unable to open recon file" );
                    $records = $syf->parseSYFRecon( $handle );

                    if( count($records) == 0 ){
                        $logger->debug( "Synchrony Reconciliation: No records to process");
                        //Nothing to do 
                        exit();
                    }

                    //Insert records into ASP_RECON
                    $aspRecon = new ASPRecon($db);

                    if ( $argv[1] == 3 ) {
                        $logger->debug( "Synchrony Reconciliation: Processing records " . $count($records) );
                        foreach( $records as $record ){
                            foreach( $record as $key => $value ){
                                $set = "set_" . $key;
                                $aspRecon->$set( $value );
                            }
                            $error = $aspRecon->insert( true, false );
                            if( !$error ){
                                echo $aspRecon->getError() . "\n";
                                exit();
                            }
                        }
                    }
                        
                    if( $argv[1] == 3 ){
                        $errors = autoFCRIN( $db, date('d-M-Y') );
                    }

                    if ( !$errors && $argv[1] == 3 ){
                        sendEmail( date('m-D-Y') );
                        
                    }
                    $logger->debug( "Synchrony Reconciliation: Archiving " . $file );
                    if( !$syf->moveReconFiles( $file, $appconfig['synchrony']['SYF_RECON_IN'], $appconfig['synchrony']['SYF_RECON_ARCHIVE']) ){
                        $logger->debug( "Synchrony Reconciliation: Error archiving " . $file );
                    }
                    else{
                        $logger->debug( "Synchrony Reconciliation: Archiving " . $file . " Succesful ");
                        //Delete file 
                        unlink( $file );
                    }


                }
                else{
                    $logger->debug( "Synchrony Reconciliation: Decrypting " . $file  . " Failed ");
                }
                else{
                    //Error downloading file
                    exit();
                }
            }
        }


        function autoFCRIN( $db, $processDate ){
            $query = new ASPReconView($db, 'T', 'SYF');
            $update = new ASPRecon($db);

            // This will collect an array of discounts by invoice which we can use to apply to the payment later
            $rec_discounts = new ASPRecon($db);
            $rec_discounts->query("where DES = 'ACQUISITION' and RECORD_TYPE = 'T' and STATUS = 'H'");
            $discounts = array();

            while ($rec_discounts->next()) {
                $discounts[$rec_discounts->get_IVC_CD()] = $rec_discounts->get_AMT();
            }

            //select * from asp_recon join (select ivc_cd from asp_recon group by IVC_CD having count(IVC_CD) = 2) a2 on asp_recon.ivc_cd = a2.ivc_cd AND record_type = 'T' WHERE EXISTS (select null from AR_TRN where IVC_CD = asp_recon.ivc_cd) and status = 'H' and (DES = 'PURCHASE' or DES = 'ACQUISITION');
            $query->query(" and STATUS = 'H' and (DES = 'PURCHASE' or DES = 'ACQUISITION')");

            while ($query->next()) {
                $artrn = new ArTrn($db);
                $artrn->set_CO_CD('BSS');
                $artrn->set_CUST_CD('SYF');
                $artrn->set_MOP_CD('CS');
                $artrn->set_EMP_CD_CSHR('92388');
                $artrn->set_EMP_CD_OP('92388');
                $artrn->set_ORIGIN_STORE($query->get_ORIGIN_STORE());
                $artrn->set_CSH_DWR_CD('00');

                //IF STATEMENT TO DETERMINE THE TRN_TP_CD AND UPDATE THE AMT TO REFLECT PAYMENT DISCOUNT
                if (strpos($query->get_DES(), 'PURCHASE') !== false) {
                    $artrn->set_TRN_TP_CD('PMT');
                    $artrn->set_AMT($query->get_AMT() - $discounts[$query->get_IVC_CD()]);
                } elseif (strpos($query->get_DES(), 'ACQUISITION') !== false) {
                    $artrn->set_TRN_TP_CD('FDC');
                    $artrn->set_AMT($query->get_AMT());
                } else {
                    $artrn->set_TRN_TP_CD(NULL);
                    $artrn->set_AMT($query->get_AMT());
                }
                
                //converts the 2nd argument into the POST_DT
                $argdate = $argv[2];
                $prepost_dt = date_create_from_format('m-d-y', $argdate);
                $post_dt = date_format($prepost_dt, 'd-m-y');
                $artrn->set_POST_DT($POST_DT);

                //date the FCRIN file was created on TD end
                $artrn->set_CREATE_DT($query->get_CREATE_DT('d-M-Y'));
                $artrn->set_STAT_CD('T');
                $artrn->set_AR_TP('O');
                $artrn->set_IVC_CD($query->get_IVC_CD());
                $artrn->set_PMT_STORE('00');
                $artrn->set_ORIGIN_CD('FCRIN');
                $artrn->set_DOC_SEQ_NUM(genDocNum($db, '00'));
                $insertCheck = $artrn->artrn();
                if ($insertCheck === false) {
                    return false;
                } else {
                    //UPDATES THE RECORD TO 'P' from 'H' if the insert is successful
                    $update->query('where ID = ' .$query->get_ID());
                    if ($update->next()) {
                        $update->set_STATUS('P');
                        $update->update('where ID = ' .$query->get_ID(), false);
                    }
                }
            }
            return true;
        }
    



?>
