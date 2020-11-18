<?php
	class SettlementInfo extends IDBTable {

		public function __construct($db) {
			parent::__construct($db);
			$this->tablename        =  "ASP_STORE_FORWARD LEFT OUTER JOIN SO ON ASP_STORE_FORWARD.DEL_DOC_NUM = SO.DEL_DOC_NUM " 
									  ."				  LEFT OUTER JOIN SO_ASP ON SO_ASP.DEL_DOC_NUM = ASP_STORE_FORWARD.DEL_DOC_NUM" 
									  ."				  LEFT OUTER JOIN ASP_PROMO2TIER_DISC ON SO_ASP.PROMO_CD = ASP_PROMO2TIER_DISC.PROMO_CD"
									  ."														 AND SYSDATE BETWEEN BEG_DT AND END_DT"	
									  ."				  LEFT OUTER JOIN CUST_ASP ON CUST_ASP.CUST_CD = ASP_STORE_FORWARD.CUST_CD	AND CUST_ASP.AS_CD = 'SYF' "
									  ."										      AND ASP_STORE_FORWARD.BNK_CRD_NUM = CUST_ASP.ACCT_CD";

			$this->dbcolumns        = array(
													"CO_CD" 					=> 	"CO_CD"
												,	"STORE_CD"					=>	"STORE_CD"
												,	"VALID_STORE_CD"			=>	"VALID_STORE_CD"
												,	"AS_CD"						=>	"AS_CD"
												,	"FINAL_DT"					=>	"FINAL_DT"
												,	"CUST_CD"					=>	"CUST_CD"
												,	"DEL_DOC_NUM"				=>	"DEL_DOC_NUM"
												,	"BNK_CRD_NUM"				=>	"BNK_CRD_NUM"
												,	"ACCT_CD" 					=>	"ACCT_CD"
												,	"ORD_TP_CD"					=>	"ORD_TP_CD"
												,	"AMT"						=>	"AMT"
												,	"APP_CD"					=>	"APP_CD"
												,	"AS_TRN_TP"					=>	"AS_TRN_TP"
												,	"STAT_CD"					=>	"STAT_CD"
												,	"STATUS"					=>	"STATUS"
												,	"AS_TRN_TP_CD"				=>	"AS_TRN_TP_CD"
												,	"SO_ASP_PROMO_CD" 			=>	"SO_ASP_PROMO_CD"
												,	"SO_ASP_AS_PROMO_CD"		=>	"SO_ASP_AS_PROMO_CD"
												,	"PROMO2TIER_AS_PROMO_CD"	=>	"PROMO2TIER_AS_PROMO_CD"
												,	"MEDIUM_TP_CD"				=>	"MEDIUM_TP_CD"
												,	"SO_EMP_SLSP_CD1"			=>	"SO_EMP_SLSP_CD1"
												,	"ORIG_HOST_REF_NUM"			=>	"ORIG_HOST_REF_NUM"
												,	"MANUAL"					=>	"MANUAL"
                                                ,	"CREATE_DT_TIME"			=>	"CREATE_DT_TIME"
                                                ,   "IDROW"                     =>  "IDROW"

											);

			$this->dbcolumns_date  	= array(
													'CREATE_DT_TIME'

											);

	    	$this->dbcolumns_function = array(
	    											"CO_CD"						=>	"DISTINCT ASP_STORE_FORWARD.CO_CD CO_CD"
												,	"STORE_CD"					=>	"ASP_STORE_FORWARD.STORE_CD STORE_CD"	
												, 	"VALID_STORE_CD"			=>	"( CASE WHEN ASP_STORE_FORWARD.STORE_CD LIKE '%T%' THEN 'INVALID' 
																						WHEN ASP_STORE_FORWARD.STORE_CD LIKE '%W%' THEN 'INVALID' 
																						ELSE 'VALID' END ) VALID_STORE_CD"
												,	"AS_CD"						=>	"ASP_STORE_FORWARD.AS_CD AS_CD"
												,	"CUST_CD"					=>	"ASP_STORE_FORWARD.CUST_CD CUST_CD"
												,	"FINAL_DT"					=>	"SO.FINAL_DT FINAL_DT"
												,	"DEL_DOC_NUM"				=>	"ASP_STORE_FORWARD.DEL_DOC_NUM DEL_DOC_NUM"
												,	"BNK_CRD_NUM"				=>	"ASP_STORE_FORWARD.BNK_CRD_NUM BNK_CRD_NUM"
												,	"AMT"						=>	"ASP_STORE_FORWARD.AMT AMT"
												,	"ACCT_CD" 					=>  "CUST_ASP.ACCT_CD ACCT_CD"
												,	"ORD_TP_CD"					=>	"SO.ORD_TP_CD ORD_TP_CD"
												,	"APP_CD"					=>	"ASP_STORE_FORWARD.APP_CD APP_CD"
												,	"STAT_CD"					=>	"ASP_STORE_FORWARD.STAT_CD STAT_CD"
												,	"STATUS"					=>	"SO.STAT_CD STATUS"
												,	"AS_TRN_TP_CD"				=>	"DECODE(AS_TRN_TP, 'PAUTH', 1, 'RET', 2, 'VAUTH', 5) AS_TRN_TP_CD"
												,	"SO_ASP_PROMO_CD"			=>	"SO_ASP.PROMO_CD SO_ASP_PROMO_CD"
												,	"SO_ASP_AS_PROMO_CD"		=>	"SO_ASP.AS_PROMO_CD SO_ASP_AS_PROMO_CD"
												,	"PROMO2TIER_AS_PROMO_CD"	=>	"ASP_PROMO2TIER_DISC.AS_PROMO_CD PROMO2TIER_AS_PROMO_CD"
												,	"MEDIUM_TP_CD"				=>	"ASP_STORE_FORWARD.MEDIUM_TP_CD MEDIUM_TP_CD"
												,	"SO_EMP_SLSP_CD1"			=>	"ASP_STORE_FORWARD.SO_EMP_SLSP_CD1 SO_EMP_SLSP_CD1"
												,	"ORIG_HOST_REF_NUM"			=>	"ASP_STORE_FORWARD.ORIG_HOST_REF_NUM ORIG_HOST_REF_NUM"
												,	"MANUAL"					=>	"ASP_STORE_FORWARD.MANUAL MANUAL"
                                                ,	"CREATE_DT_TIME"			=>	"ASP_STORE_FORWARD.CREATE_DT_TIME CREATE_DT_TIME"
                                                ,   "IDROW"                     =>  "CAST(ASP_STORE_FORWARD.ROWID AS VARCHAR2(300)) IDROW"
                    	    				);	

		}
	    public function query($where="", $postclauses="") {
	        $newwhere = $where." ".$postclauses;

	        return parent::query($newwhere);
	    }

	}

?>
