<?php

class MorCustAspAppHist extends IDBTable {

	public function __construct($db) {
		parent::__construct($db);
		$this->tablename        = 'MOR_CUST_ASP_APP_HIST';
		$this->dbcolumns        = array(
										  'CUST_CD'=>'CUST_CD'
										, 'AS_CD'=>'AS_CD'
										, 'APPLICATION_DT'=>'APPLICATION_DT'
										, 'APPLICATION_STATUS'=>'APPLICATION_STATUS'
										, 'APPLICATION_ID'=>'APPLICATION_ID'
										, 'CREDIT_LIMIT' => 'CREDIT_LIMIT'
										, 'ACCT_CD' => 'ACCT_CD'
										, 'STORE_CD'=>'STORE_CD'
										, 'STORE_NUMBER'=>'STORE_NUMBER'
										, 'EMP_INIT'=>'EMP_INIT'
										, 'JOINT_INDICATOR'=>'JOINT_INDICATOR'
										, 'INSURANCE_PRODUCT'=>'INSURANCE_PRODUCT'
										, 'CREDIT_DESIRED'=>'CREDIT_DESIRED'
										, 'FIRST_NAME'=>'FIRST_NAME'
										, 'LAST_NAME'=>'LAST_NAME'
										, 'ADDR1'=>'ADDR1'
										, 'ADDR2'=>'ADDR2'
										, 'CITY'=>'CITY'
										, 'STATE'=>'STATE'
										, 'ZIP_CD'=>'ZIP_CD'
										, 'DOB'=>'DOB'
										, 'HOME_PHONE'=>'HOME_PHONE'
										, 'EMPLOYER_NAME'=>'EMPLOYER_NAME'
										, 'EMPLOYER_PHONE'=>'EMPLOYER_PHONE'
										, 'EMPLOYED_MONTHS'=>'EMPLOYED_MONTHS'
										, 'MONTHLY_INCOME'=>'MONTHLY_INCOME'
										, 'OTHER_MONTHLY_INCOME'=>'OTHER_MONTHLY_INCOME'
										, 'MONTHLY_HOUSE_PMT'=>'MONTHLY_HOUSE_PMT'
										, 'HOUSING_TYPE'=>'HOUSING_TYPE'
										, 'YEARS_AT_ADDRESS'=>'YEARS_AT_ADDRESS'
										, 'EMAIL_ADDR'=>'EMAIL_ADDR'
										, 'VIP_OK'=>'VIP_OK'
										, 'ID_TYPE'=>'ID_TYPE'
										, 'ID_STATE'=>'ID_STATE'
										, 'ID_NUM'=>'ID_NUM'
										, 'ID_EXP_DT'=>'ID_EXP_DT'
										, 'COAPP_ADDR1'=>'COAPP_ADDR1'
										, 'COAPP_FIRST_NAME'=>'COAPP_FIRST_NAME'
										, 'COAPP_LAST_NAME'=>'COAPP_LAST_NAME'
										, 'COAPP_EMPLOYER_NAME'=>'COAPP_EMPLOYER_NAME'
										, 'COAPP_ADDR2'=>'COAPP_ADDR2'
										, 'COAPP_CITY'=>'COAPP_CITY'
										, 'COAPP_OTHER_MONTHLY_INCOME'=>'COAPP_OTHER_MONTHLY_INCOME'
										, 'COAPP_MONTHLY_HOUSE_PMT'=>'COAPP_MONTHLY_HOUSE_PMT'										
										, 'COAPP_HOME_PHONE'=>'COAPP_HOME_PHONE'
										, 'COAPP_STATE'=>'COAPP_STATE'
										, 'COAPP_MONTHLY_INCOME'=>'COAPP_MONTHLY_INCOME'
										, 'COAPP_EMPLOYER_PHONE'=>'COAPP_EMPLOYER_PHONE'
										, 'COAPP_ZIP_CD'=>'COAPP_ZIP_CD'
										, 'COAPP_DOB'=>'COAPP_DOB'
										, 'TD_TRANSACTION_LINK'=>'TD_TRANSACTION_LINK'
										, 'AS_REF_NUM'=>'AS_REF_NUM'
										, 'AS_REF_NUM'=>'AS_REF_NUM'
                                        , 'PRIMARY_ID_TYPE' => 'PRIMARY_ID_TYPE'
                                        , 'PRIMARY_ID_PLACE' => 'PRIMARY_ID_PLACE'
                                        , 'PRIMARY_ID_EXP_DT' => 'PRIMARY_ID_EXP_DT' 
                                        , 'PRIMARY_ID_EXPMON' => 'PRIMARY_ID_EXP_MON'
                                        , 'PRIMARY_ID_EXPYR' => 'PRIMARY_ID_EXP_YR' 
                                        , 'SECONDARY_ID_TYPE' => 'SECONDARY_ID_TYPE'
                                        , 'SECONDARY_ID_EXP_DT' => 'SECONDARY_ID_EXP_DT'
                                        , 'SECONDARY_ID_EXPMON' => 'SECONDARY_ID_EXPMON'
                                        , 'SECONDARY_ID_EXPYR' => 'SECONDARY_ID_EXPYR'
                                        , 'JOINT_PRIMARY_ID_TYPE' => 'JOINT_PRIMARY_ID_TYPE'
                                        , 'JOINT_PRIMARY_ID_PLACE' => 'JOINT_PRIMARY_ID_PLACE'
                                        , 'JOINT_PRIMARY_ID_EXP_DT' => 'JOINT_PRIMARY_ID_EXP_DT' 
                                        , 'JOINT_PRIMARY_ID_EXPMON' => 'JOINT_PRIMARY_ID_EXMON'
                                        , 'JOINT_PRIMARY_ID_EXPYR' => 'JOINT_PRIMARY_ID_EXPRYR'
                                        , 'JOINT_SECONDARY_ID_TYPE' => 'JOINT_SECONDARY_ID_TYPE'
                                        , 'JOINT_SECONDARY_ID_EXP_DT' => 'JOINT_SECONDARY_ID_EXP_DT'
                                        , 'JOINT_SECONDARY_ID_EXPMON' => 'JOINT_SECONDARY_ID_EXP_MON'
                                        , 'JOINT_SECONDARY_ID_EXPYR' => 'JOINT_SECONDARY_ID_EXPYR'
									);

		$this->dbcolumns_date	 = array(
										  'APPLICATION_DT'
										 , 'DOB'
										 , 'ID_EXP_DT'
										 , 'COAPP_DOB'
										);

 		$this->setAutoIDColumn("CUST_CD");

		$this->errorMsg 			= "";

	}
	/*-----------------------------------------------------------------------------------
	 *---------------------- getApplicationByCustCdAndAsCdAndKeyNum ---------------------
	 *-----------------------------------------------------------------------------------
	 * Get Application Status by Customer Code, Finance company, and Key Number
	 *
     *
     * Return: Get application history for that customer code, finance company and key
     * number 
	 */
    public function getApplicationByCustCdAndAsCdAndKeyNum( $custCd, $asCd, $keyNum ){
		global $appconfig, $app, $errmsg, $logger;

        $where = "WHERE CUST_CD = '" . $custCd . "' AND AS_CD = '" . $asCd . "' AND TD_TRANSACTION_LINK = '" . $keyNum . "'";
        $result = $this->query( $where );
        
        if($result < 0){
			return null;
        }

        return $this;
    }

	/*-----------------------------------------------------------------------------------
	 *-------------------------- getAppHistoryByCustCdAndAsCd ---------------------------
	 *-----------------------------------------------------------------------------------
     * Get all history application for Customer Code and finance company
     *
     * Return: All history applications
     *
	 */
    public function getAppHistoryByCustCdAndAsCd( $custCd, $asCd ){
		global $appconfig, $app, $errmsg, $logger;

        $pending = '';
        if( $asCd == 'SYF' ){
            $pending = " AND  APPLICATION_STATUS IN ( '002', '003' ) ";
        }
        $where = "WHERE CUST_CD = '" . $custCd . "' AND AS_CD = '" . $asCd . "' " . $pending;
        $result = $this->query( $where );
        
        if($result < 0){
			return null;
        }

        return $this;
    }


	/*-----------------------------------------------------------------------------------
	 *---------------------- updateAppHistoryByCustCdAndAsCdAndKeyNum -------------------
	 *-----------------------------------------------------------------------------------
     * Update application history for customer, finance company and key number
     *
     *
     * Return: All history applications
     *
	 */
    public function updateAppHistoryByCustCdAndAsCdAndKeyNum( $custCd, $asCd, $keyNum, $updt ){
		global $appconfig, $app, $errmsg, $logger;

        $where = "WHERE CUST_CD = '" . $custCd . "' AND AS_CD = '" . $asCd . "' AND TD_TRANSACTION_LINK = '" . $keyNum . "' ";

        foreach( $updt as $key => $value ){
            $col = "set_" . $key;
            $this->$col( $value );
        }

        $result = $this->update( $where, false );
        
        if($result < 0){
			return null;
        }
        
    }
}

?>
