<?php

class CustAsp extends IDBTable {

	public function __construct($db) {
		parent::__construct($db);
		$this->tablename        = 'CUST_ASP';
		$this->dbcolumns        = array(
									  'CUST_CD'=>'CUST_CD'
									, 'AS_CD'=>'AS_CD'
									, 'ACCT_CD'=>'ACCT_CD'
									, 'EXPIR_DT'=>'EXPIR_DT'
									, 'APP_CREDIT_TP'=>'APP_CREDIT_TP'
									, 'AR_TP'=>'AR_TP'
									, 'APP_TP'=>'APP_TP'
									, 'CNTR_CD'=>'CNTR_CD'
									, 'APP_REF_NUM'=>'APP_REF_NUM'
									, 'APP_STAT_CD'=>'APP_STAT_CD'
									, 'APP_CREDIT_LINE'=>'APP_CREDIT_LINE'
									, 'MONTH_INT_RATE'=>'MONTH_INT_RATE'
									, 'APR'=>'APR'
									, 'ASSIGN'=>'ASSIGN'
									, 'EXPIR_DAYS'=>'EXPIR_DAYS'
									, 'PASS_EXP_DT'=>'PASS_EXP_DT'
									, 'PRE_APP_CD'=>'PRE_APP_CD'
									, 'PL_CNTR_CD'=>'PL_CNTR_CD'
									, 'PL_CNTR_SEQ_NUM'=>'PL_CNTR_SEQ_NUM'
									, 'APP_CREDIT_AVAIL'=>'APP_CREDIT_AVAIL'
									, 'ACTIVE'=>'ACTIVE'
                                    , 'ACCT_NUM' => 'ACCT_NUM'
									);

		$this->dbcolumns_date	 = array(
										  'EXPIR_DT'
										, 'PASS_EXP_DT'
										);

 		$this->setAutoIDColumn("CUST_CD");

		$this->errorMsg 			= "";

	}

    public function getCustAspByCustCdAndAsCd( $custCd, $asCd ){
        global $appconfig, $app, $errmsg, $logger;
        
        $where = "WHERE CUST_CD = '" . $custCd . "' AND AS_CD = '" . $asCd . "'";

        $result = $this->query($where, $postclause);
        if($result < 0){
            $errmsg = 'Unable to find customer in  CUST_ASP: ' . $where;

            $return['error'] = true;
            $return['update'] = false;
            $return['msg']   = $errmsg;

            return $return;
        }

        if($row = $this->next()){
            return $this;
        } 

        return NULL;

    }

    public function getCustAspByAcctNumAndAsCdAndCustCd( $custCd, $asCd, $acctCD, $postclause = '' ){
        global $appconfig, $app, $errmsg, $logger;
        
        $where = "WHERE CUST_CD = '" . $custCd . "' AND AS_CD = '" . $asCd . "' AND ACCT_CD = '" . $acctCD . "' ";

        $result = $this->query($where, $postclause);
        if($result < 0){
            $errmsg = 'Unable to find customer in  CUST_ASP: ' . $where;

            $return['error'] = true;
            $return['update'] = false;
            $return['msg']   = $errmsg;

            return $return;
        }

        if($row = $this->next()){
            return $this;
        } 

        return NULL;


    }


}

?>
