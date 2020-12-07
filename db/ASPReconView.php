<?php
//This extension allows us to run a join within the IDBTABLE Rules specifically for the AutoFCRIN process.

class ASPReconView extends IDBTable {

	public function __construct($db, $recType, $finCo) {
		parent::__construct($db);
		$this->tablename        	= "asp_recon join (select ivc_cd from asp_recon group by IVC_CD having count(IVC_CD) = 2) a2 on asp_recon.ivc_cd = a2.ivc_cd" 
									. " AND record_type = '".$recType."'";
        $this->basewhere        	= "WHERE EXISTS (select null from AR_TRN where IVC_CD = asp_recon.ivc_cd"
                                	. " AND   CUST_CD = '".$finCo."')";		
		$this->dbcolumns        	= array(  'CREATE_DT'=>'CREATE_DT'
											, 'ID'=>'ID'
											, 'AS_CD'=>'AS_CD'
											, 'AS_STORE_CD'=>'AS_STORE_CD'
											, 'ORIGIN_STORE'=>'ORIGIN_STORE'
											, 'CREDIT_OR_DEBIT'=>'CREDIT_OR_DEBIT'
											, 'PROCESS_DT' => 'PROCESS_DT'
											, 'STATUS'=>'STATUS'
											, 'RECORD_TYPE'=>'RECORD_TYPE'
											, 'ACCT_NUM_PREFIX'=>'ACCT_NUM_PREFIX'
											, 'BNK_CRD_NUM'=>'BNK_CRD_NUM'
											, 'IVC_CD'=>'IVC_CD'
											, 'AMT'=>'AMT'
											, 'DES'=>'DES'
											, 'EXCEPTIONS' => 'EXCEPTIONS');

		$this->dbcolumns_function	= array(  'CREATE_DT'=>'ASP_RECON.CREATE_DT'
											, 'ID'=>'ASP_RECON.ID'
											, 'AS_CD'=>'ASP_RECON.AS_CD'
											, 'AS_STORE_CD'=>'ASP_RECON.AS_STORE_CD'
											, 'ORIGIN_STORE'=>'ASP_RECON.ORIGIN_STORE'
											, 'CREDIT_OR_DEBIT'=>'ASP_RECON.CREDIT_OR_DEBIT'
											, 'PROCESS_DT' => 'ASP_RECON.PROCESS_DT'
											, 'STATUS'=>'ASP_RECON.STATUS'
											, 'RECORD_TYPE'=>'ASP_RECON.RECORD_TYPE'
											, 'ACCT_NUM_PREFIX'=>'ASP_RECON.ACCT_NUM_PREFIX'
											, 'BNK_CRD_NUM'=>'ASP_RECON.BNK_CRD_NUM'
											, 'IVC_CD'=>'ASP_RECON.IVC_CD'
											, 'AMT'=>'ASP_RECON.AMT'
											, 'DES'=>'ASP_RECON.DES'
											, 'EXCEPTIONS' => 'ASP_RECON.EXCEPTIONS');
		$this->dbcolumns_date  		= array(  'CREATE_DT', 'PROCESS_DT' );
		$this->setAutoIDColumn("ID");
	}
	public function query($where="", $postclauses="") {
        $newwhere = $this->basewhere." ".$where;
        return parent::query($newwhere);
    }
}

?>