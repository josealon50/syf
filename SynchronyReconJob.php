<?php
        require_once( './config.php');
        require_once('./src/Finance/FinanceCompany.php');
        require_once('./src/SynchronyFinance.php');
        require_once('./libs/phpexcel/phpexcel/Classes/PHPExcel.php');
        require_once('./libs/IDBResource.php');
        require_once('./libs/IDBTable.php');
        require_once('./libs/Morcommon.php');
        require_once('./db/SynchronyRecon.php');
        require_once('./db/ASPRecon.php');
        require_once('./db/ASPReconView.php');
        require_once('./db/MorStoreToAspMerchant.php');

        global $appconfig;

        $mor = new Morcommon();
        $db = $mor->standAloneAppConnect();
        $syf = new SynchronyFinance();

        //Download file from Synchrony
        $download = $syf->download();
        if ( $download ){
            $handle = fopen( $appconfig['synchrony']['RECON_PATH'] . $appconfi['synchrony']['SYF_RECON_FILENAME'], 'r+' ) or die ( "Unable to open recon file" );
            $records = parseReconciliation( $handle, $db );

            if( count($records) == 0 ){
                //Nothing to do 
                exit();
            }

            //Insert records into ASP_RECON
            $aspRecon = new ASPRecon($db);

            if ( $argv[1] == 3 ) {
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
                creditTag( $db );
                exceptionTag( $db );
                csvExceptions( $db, date('m-D-Y') );
                logResults(  date('m-D-Y') );
                sendEmail( date('m-D-Y') );
                
            }

        }
        else{
            //Error downloading file
            exit();
        }



        function parseReconciliation( $handle, $db ){
            $storeMerchant = new MorStoreToAspMerchant($db);
            $records = [];
            while(($data = fgets($handle, 8192)) !== FALSE){
                $row = explode( "|", $data );
                //Lookup DEL_DOC_NUM by acct_num, amt and AS_CD


                $temp = [ "STORE_CD" => $storeMerchant->get_STORECD(),
                          "CREATE_DT" => date('d-M-Y'),
                          "AS_CD" => "SYF",
                          "CREDIT_OR_DEBIT" => $row[8] == '253' ? 'D' : 'C',
                          "AMT" => number_format( $row[9], 2 ),
                          "PROCESS_DT" => date_create_from_format( 'Ymd', $row[12] ),
                          "DES" => " ",
                          "TYPE" => " ",
                          "IVC_CD" => $delDocNum,
                          "STATUS" => "H",
                          "ORIGIN_STORE" => substr( $delDocNum, 5, 2 ),
                          "ACCT_NUM_PREFIX" => substr( $row[7], -4 ),
                          "BNK_CRD_NUM" => substr( $row[7], -4 )
                      ];

                array_push( $records, $temp );

            }

            return $records;
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

    function creditTag( $db ) {
        $query = new ASPRecon($db);
        $update = new ASPRecon($db);
        $query->query("WHERE ASP_RECON.DES = 'MERCHANDISE RETURN' or ASP_RECON.DES = 'ACQUISITION REVERSAL'");
        while ($query->next()) {
            $update->query('where ID = ' .$query->get_ID());
            $update->set_EXCEPTIONS('Credit or Refund');
            $update->set_STATUS('E');
            $update->update('where ID = ' .$query->get_ID(), false);
        }
    }

    //This tags all non processed items in ASP_RECON for Error to be reviewed
    function exceptionTag( $db ) {  
        $query = new ASPRecon($db);
        $update = new ASPRecon($db);
        $query->query("WHERE ASP_RECON.STATUS = 'H' AND ASP_RECON.RECORD_TYPE = 'T'");
        while ($query->next()) {
            $update->query('where ID = ' .$query->get_ID());
            $update->set_EXCEPTIONS('This record is invalid');
            $update->set_STATUS('E');
            $update->update('where ID = ' .$query->get_ID(), false);
        }
        return true;
    }

    //This will gather all exceptions in the ASP_RECON and package them into a CSV file
    function csvExceptions( $db, $processDate ) {
        //We will use the check_dt varible for querying the correct error set (I hope)
        $precheck_dt = date_create_from_format('m-d-Y', $processDate);
        $check_dt = date_format($precheck_DT, 'd-M-Y');

        $fileName = 'FCRIN_'.$processDate.'_Exceptions';
        //open a file to write to
        if ($out = fopen($appconfig['SYF_SETTL_OUT'].$fileName.'.csv', 'w')) {
        //if ($out = fopen('/gers/live/finance/td/in/'.$fileName.'.csv', 'w')) {
            $a = new ASPRecon($db);

            //write first line of columns
            $array = $a->getDBColumnNames();
            $cols = implode(", ", $array);
            fwrite($out, $cols."\n");

            $a->query("where (PROCESS_DT = '$check_dt' and RECORD_TYPE = 'T' and STATUS = 'E') OR ASP_RECON.RECORD_TYPE = 'S'");

            // $row will be array of columns
            while ($row = $a->next()) {
                fwrite($out, join(',', $row)."\n");
            }

            fclose($out);
        } else {
            echo 'error opening output file';
            return -1;
        }
        return true;
    }

    //This function e-mails the CSV exception file to specified users along with a small report
    function sendEmail( $processDate ) {
        global $appconfig;

        $fileName = 'FCRIN_'.$processDate.'_Exceptions';
        $stmt = oci_parse($db->get_cnx(), "select count(*) from asp_recon where RECORD_TYPE = 'T' and STATUS = 'P'");
        oci_execute($stmt);
        $processed = (oci_fetch_row($stmt)[0])/2;

        $mail = new PHPMailer;
        $mail->isSMTP();
        $mail->Host = 'morexch.morfurniture.local';
        $mail->Port = 25;
        $mail->From     = 'misgroup@morfurniture.com';
        $mail->FromName = 'Mailer';
        $mail->addAddress('ar@morfurniture.com'); //should go to finance@morfurniture.com
        $mail->addReplyTo('ar@morfurniture.com');
        $attachfile = $appconfig['SYF_SETTL_OUT'].$fileName.'.csv';
        //$attachfile = '/gers/live/finance/td/in/'.$fileName.'.csv';
        $mail->WordWrap = 50;
        $mail->addAttachment($attachfile);
        $mail->isHTML(true);
        $mail->Subject = "FCRIN Exception File for ".$processDate;
        $newline = '<br>';
        $mail->Body    = '<b>You processed '.$processed.' items to FCRIN. Here are the exceptions!</b>'.$newline.$newline ;
            if(!$mail->send()) {
                echo 'Message could not be sent.'."\n";
                echo 'Mailer Error: ' . $mail->ErrorInfo;
                return -1;
            } else {
                echo 'Message has been sent'."\n\n";
            }
            return true;
    }

    //This function updates the RPT Print File with a status report
    function logResults( $processDate ) {
        global $appconfig;

        $checkDate = $processDate;
        $precheck_DT = date_create_from_format('m-d-Y', $checkDate);
        $check_dt = date_format($precheck_DT, 'd-M-Y');

        $stmt = oci_parse($db->get_cnx(), "select count(*) from asp_recon where PROCESS_DT = '".$check_dt."' and RECORD_TYPE = 'T' and STATUS = 'P'");
        oci_execute($stmt);
        $log = (oci_fetch_row($stmt)[0])/2;
        $result = fopen($appconfig['GERSHOME'].$argv[4], "w");
        fwrite($result,"ACRIN Processed ".$log." items\n");

        return;
    }
    



?>
