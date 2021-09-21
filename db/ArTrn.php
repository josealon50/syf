<?php

class ArTrn extends IDBTable {

	public function __construct($db) {
		parent::__construct($db);
		$this->tablename        = 'AR_TRN';
		$this->dbcolumns        = array(
									  'CO_CD'=>'CO_CD'
									, 'CUST_CD'=>'CUST_CD'
									, 'MOP_CD'=>'MOP_CD'
									, 'EMP_CD_CSHR'=>'EMP_CD_CSHR'
									, 'EMP_CD_OP'=>'EMP_CD_OP'
									, 'ORIGIN_STORE'=>'ORIGIN_STORE'
									, 'CSH_DWR_CD'=>'CSH_DWR_CD'
									, 'AMT'=>'AMT'
									, 'TRN_TP_CD'=>'TRN_TP_CD'
									, 'POST_DT'=>'POST_DT'
									, 'CREATE_DT'=>'CREATE_DT'
									, 'STAT_CD'=>'STAT_CD'
									, 'AR_TP'=>'AR_TP'
									, 'IVC_CD'=>'IVC_CD'
									, 'PMT_STORE'=>'PMT_STORE'
									, 'ORIGIN_CD'=>'ORIGIN_CD'
									, 'DOC_SEQ_NUM'=>'DOC_SEQ_NUM'
									);

		$this->dbcolumns_date	 = array(
										  'POST_DT'
										, 'CREATE_DT'
										);


		$this->errorMsg 			= "";

	}
}

?>
