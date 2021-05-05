<?php

class MorAspTrnHist extends IDBTable {

	public function __construct($db) {
		parent::__construct($db);
		$this->tablename        = 'MOR_ASP_TRN_HIST';
		$this->dbcolumns        = array(
										  'AS_CD'=>'AS_CD'
										, 'AS_REF_NO'=>'AS_REF_NO'
										, 'TRAN_DT'=>'TRAN_DT'
										, 'EMP_INIT'=>'EMP_INIT'
										, 'DEL_DOC_NUM'=>'DEL_DOC_NUM'
										, 'ORDER_TP'=>'ORDER_TP'
										, 'STORE_CD'=>'STORE_CD'
										, 'STORE_NUMBER'=>'STORE_NUMBER'
										, 'ACCT_CD'=>'ACCT_CD'
										, 'TRAN_AMT'=>'TRAN_AMT'
										, 'AUTH_CD'=>'AUTH_CD'
										, 'CREDIT_PLAN'=>'CREDIT_PLAN'
										, 'ERROR_MSG'=>'ERROR_MSG'
									);

		$this->dbcolumns_date	 = array(
										  'TRAN_DT'
										);

 		$this->setAutoIDColumn("AS_CD");

		$this->errorMsg 			= "";

	}

    /*------------------------------------------------------------------------
     *---------------- getTransactionByStoreCdAcctNunAndAsCd  ----------------
     *------------------------------------------------------------------------
     * Function will get transaction by store code, account number and finance
     * company
     *
     * @param 
     *      STORE_CD : Store code
     *      ACCT_NUM : Last 4 digits of account number
     *      AS_CD : Finance Company
     *
     * @return IDB Object if found null otherwise
     *
     *
     */
    public function getTransactionByStoreCdAcctNunAndAsCd( $storeCd, $acctNum, $asCd ) {
        global $appconfig, $logger;

        $where = "WHERE STORE_CD = '" . $storeCd . "' AND ACCT_CD = '" . $acctNum . "' AND AS_CD = '" . $asCd . "' ";
        $result = $this->query( $where );
        if( $result < 0 ){
            return false;
        }
        if( $row = $this->next() ){
            return $this;
        }
        return null;
    }

    /*------------------------------------------------------------------------
     *---------------- getTransactionByStoreCdAcctNunAmtAndAsCd  ----------------
     *------------------------------------------------------------------------
     * Function will get transaction by store code, account number and finance
     * company
     *
     * @param 
     *      STORE_CD : Store code
     *      ACCT_NUM : Last 4 digits of account number
     *      AS_CD : Finance Company
     *
     * @return IDB Object if found null otherwise
     *
     *
     */
    public function getTransactionByStoreCdAcctNumAmtAndAsCd( $storeCd, $acctNum, $amt, $asCd ) {
        global $appconfig, $logger;

        $where = "WHERE STORE_CD = '" . $storeCd . "' AND ACCT_CD = '" . $acctNum . "' AND TRAN_AMT = '" . $amt . "'  AND AS_CD = '" . $asCd . "' ";
        $result = $this->query( $where );
        if( $result < 0 ){
            return false;
        }
        if( $row = $this->next() ){
            return $this;
        }
        return null;
    }


}

?>
