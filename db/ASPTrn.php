<?php
/**
 * Direct query of AR.ASP_TRN.
 * The select statement generated from this class does not require any additional runtime
 * parameters.
 *---------------------------------------------------------------------------------------------
 * Programming Log
 * Programmer	Date 		Notes
 * tsl          Mar 19 2015  Created
 */
class ASPTrn extends IDBTable {

	public function __construct($db) {
		parent::__construct($db);
		$this->tablename        = 'ASP_TRN';

		$this->dbcolumns        = array(
			  'CO_CD'=>'CO_CD'
			, 'AS_CD'=>'AS_CD'	
			, 'MEDIUM_TP_CD'=>'MEDIUM_TP_CD'
			, 'DEL_DOC_NUM'=>'DEL_DOC_NUM'
			, 'APP_CD'=>'APP_CD'
			, 'WR_DT'=>'WR_DT'
			, 'LST_CHG_DT'=>'LST_CHG_DT'
			, 'BNK_CRD_NUM'=>'BNK_CRD_NUM'
			, 'EXP_DT'=>'EXP_DT'
			, 'AMT'=>'AMT'
			, 'STAT_CD'=>'STAT_CD'
			, 'XMIT_DT_TIME'=>'XMIT_DT_TIME'
			, 'HOST_REF_NUM'=>'HOST_REF_NUM'
			, 'CUST_CD'=>'CUST_CD'
			, 'SO_EMP_SLSP_CD1'=>'SO_EMP_SLSP_CD1'
			, 'ORIGIN_CD'=>'ORIGIN_CD'
			, 'BATCH_FILENAME'=>'BATCH_FILENAME'
			, 'AS_TRN_TP'=>'AS_TRN_TP'	
			, 'STORE_CD'=>'STORE_CD'
			, 'REF_NUM'=>'REF_NUM'
			, 'MANUAL'=>'MANUAL'
			);
		$this->dbcolumns_date  	= array('WR_DT', 'LST_CHG_DT', 'EXP_DT', 'XMIT_DT_TIME');
		$this->setAutoIDColumn("BNK_CRD_NUM");
	}
}

?>
