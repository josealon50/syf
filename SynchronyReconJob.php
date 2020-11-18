<?php
        require_once( './config.php');
        require_once('./src/Finance/FinanceCompany.php');
        require_once('./src/SynchronyFinance.php');
        require_once('./libs/phpexcel/phpexcel/Classes/PHPExcel.php');
        require_once('./libs/IDBResource.php');
        require_once('./libs/IDBTable.php');
        require_once('./libs/Morcommon.php');
        require_once('./db/SynchronyRecon.php');

        global $appconfig;

        $mor = new Morcommon();
        $db = $mor->standAloneAppConnect();

        $recon = new SynchronyRecon($db);
        $syf = new SynchronyFinance($db);

        //Download file from Synchrony
        $name = $syf->download();
        $handle = fopen( $appconfig['synchrony']['RECON_PATH'] . DIRECTORY_SEPARATOR . $name, 'r+' ) or die ( "Unable to open recon file" );

        while(($data = fgets($handle, 8192)) !== FALSE){
            $row = explode( "|", $data );

            $reconDate  = date('d-M-Y');
            $recon->set_RECON_DT($reconDate);
            $recon->set_CREATE_DT($reconDate);                

            $store_num  = substr($data,12,9);
            $recon->set_STORE_NUM($store_num);

            $JulianDay  = substr($data,25,3);
            $JulianDay -= 1;

            $Year       = substr($data,21,4);
            $DateYear   = date("Y/m/d", mktime(0, 0, 0, 1, 1, $Year));
            $GregDate   = new DateTime($DateYear);
            $GregDate->modify("+$JulianDay day");
            $batchDt   = $GregDate->format('d-M-Y');
            $recon->set_BATCH_DT($batchDt, 'd-M-Y');

            $plan       = substr($data,56,5);
            $recon->set_PLAN($plan);

            $txn_flag   = substr($data,67,1);
            $recon->set_TXN_FLAG($txn_flag);

            $amt        = ((int)(substr($data,68,17)))/100;
            $recon->set_AMT($amt);

            $Year       = substr($data,85,4);                
            $JulianDay  = substr($data,89,3);
            $JulianDay -= 1;

            $DateYear   = date("Y/m/d", mktime(0, 0, 0, 1, 1, $Year));
            $GregDate   = new DateTime($DateYear);
            $GregDate->modify("+$JulianDay day");
            $Post_dt    = $GregDate->format('d-M-Y');

            $recon->set_POST_DT($Post_dt, 'd-M-Y');

            $Year       = substr($data,92,4);                
            $JulianDay  = substr($data,96,3);
            $JulianDay -= 1;

            $DateYear   = date("Y/m/d", mktime(0, 0, 0, 1, 1, $Year));
            $GregDate   = new DateTime($DateYear);
            $GregDate->modify("+$JulianDay day");
            $entry_dt   = $GregDate->format('d-M-Y');

            $recon->set_ENTRY_DT($entry_dt, 'd-M-Y');

            $Year       = substr($data,99,4);                
            $JulianDay  = substr($data,103,3);
            $JulianDay -= 1;

            $DateYear   = date("Y/m/d", mktime(0, 0, 0, 1, 1, $Year));
            $GregDate   = new DateTime($DateYear);
            $GregDate->modify("+$JulianDay day");
            $settle_dt  = $GregDate->format('d-M-Y');

            $recon->set_SETTLE_DT($settle_dt, 'd-M-Y');

            $acct_num   = substr($data,122,10);
            $recon->set_ACCT_NUM($acct_num);

            $description = substr($data,148,40);
            $recon->set_DESCRIPTION(trim($description));

            $post_flag   = substr($data,203,2);
            $recon->set_POST_FLAG($post_flag);

            $auth_cd     = substr($data,205,6);
            $recon->set_AUTH_CD($auth_cd);

            $del_doc_num = substr($data,211,19);         // updated from 12 to 19 by tsl on 3-31-2015       
            $recon->set_DEL_DOC_NUM(trim($del_doc_num));     

            //$result = $recon->insert(false,false);
            
        }

?>
