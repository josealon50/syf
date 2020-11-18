<?php
class SynchronyRecon extends IDBTable {
	public function __construct($db) {
		parent::__construct($db);
		$this->tablename        = '';

		$this->dbcolumns = array(
              'RECON_DT'=>'RECON_DT'
            , 'CREATE_DT'=>'CREATE_DT'	
            , 'STORE_NUM'=>'STORE_NUM'	
            , 'BATCH_DT'=>'BATCH_DT'	
            , 'PLAN'=>'PLAN'	
            , 'TXN_FLG'=>'TXN_FLAG'	
            , 'AMT'=>'AMT'
            , 'POST_DT'=>'POST_DT'	
            , 'ENTRY_DT'=>'ENTRY_DT'	
            , 'SETTLE_DT'=>'SETTLE_DT'	
            , 'ACCT_NUM'=>'ACCT_NUM'	
            , 'DESCRIPTION'=>'DESCRIPTION'	
            , 'POST_FLAG'=>'POST_FLAG'	
            , 'AUTH_CD'=>'AUTH_CD'	
            , 'DEL_DOC_NUM'=>'DEL_DOC_NUM'	
		);


        $this->dbcolumns_date = array(
            'RECON_DT', 
            'CREATE_DT', 
            'BATCH_DT', 
            'POST_DT', 
            'ENTRY_DT', 
            'SETTL_DT', 
            'XMIT_DT_TIME'
        );
	}
}
?>
