<?php
/**
 * Direct query of AR.ASP_STORE_FORWARD.
 * The select statement generated from this class does not require any additional runtime
 * parameters.
 *---------------------------------------------------------------------------------------------
 * Programming Log
 * Programmer	Date 		Notes
 * tsl          Mar 3 2015  Created
 */
class ASPStoreForward extends IDBTable {

	public function __construct($db) {
		parent::__construct($db);
		$this->tablename        = 'ASP_STORE_FORWARD';

		$this->dbcolumns        = array(
			  'CO_CD'=>'CO_CD'
			, 'STORE_CD'=>'STORE_CD'
			, 'CSH_DWR_CD'=>'CSH_DWR_CD'
			, 'AS_CD'=>'AS_CD'
			, 'CUST_CD'=>'CUST_CD'
			, 'SO_EMP_SLSP_CD1'=>'SO_EMP_SLSP_CD1'
			, 'EMP_CD_CSHR'=>'EMP_CD_CSHR'
			, 'COV_CDS'=>'COV_CDS'
			, 'DEL_DOC_NUM'=>'DEL_DOC_NUM'
			, 'AS_TRN_TP'=>'AS_TRN_TP'			
			, 'AMT'=>'AMT'
			, 'BNK_CRD_NUM'=>'BNK_CRD_NUM'
			, 'EXP_DT'=>'EXP_DT'
			, 'APP_CD'=>'APP_CD'
			, 'TRACK1'=>'TRACK1'
			, 'TRACK2'=>'TRACK2'
			, 'MANUAL'=>'MANUAL'
			, 'REF_NUM'=>'REF_NUM'
			, 'ORIG_REF_NUM'=>'ORIG_REF_NUM'
			, 'ORIG_HOST_REF_NUM'=>'ORIG_HOST_REF_NUM'
			, 'MEDIUM_TP_CD'=>'MEDIUM_TP_CD'
			, 'BATCH_NUM'=>'BATCH_NUM'
			, 'STAT_CD'=>'STAT_CD'
			, 'ERROR_DES'=>'ERROR_DES'
			, 'ORIGIN_CD'=>'ORIGIN_CD'
			, 'XMIT_DT_TIME'=>'XMIT_DT_TIME'
			, 'EMP_CD_OP'=>'EMP_CD_OP'
			, 'BNK_CRD_NUM_ENC'=>'BNK_CRD_NUM_ENC'
			, 'SEQ_NUM'=>'SEQ_NUM'
			, 'CREATE_DT_TIME'=>'CREATE_DT_TIME'
			);
		$this->dbcolumns_date  	= array('CREATE_DT_TIME', 'XMIT_DT_TIME');
		$this->setAutoIDColumn("BNK_CRD_NUM");

	}
    public function getAccountByDelDocNumCustCdAmountAndFinalDate( $delDocNum, $custCd, $amt, $finalDate ){
        $where = "WHERE ASP_STORE_FORWARD.DEL_DOC_NUM = '" . $delDocNUm . "' AND ASP_STORE_FORWARD.CUST_CD = '" .$custCd . "' AND ASP_STORE_FORWARD.CUST_CD = '" . $custCd . "' AND ASP_STORE_FORWARD.AMT = '" . $amt . "'  AND ASP_STORE_FORWARD.FINAL_DT = '" . $finalDT . "' ";

        $q = parent::query( $where );

        if ( $row = $q->next() ){
            return $row['ACCT_CD'];
        }

        return null;
    }

}

?>
