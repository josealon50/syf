<?php

class SyfSalesOrder extends IDBTable {

	public function __construct($db) {
		parent::__construct($db);
		$this->tablename        = 'SO so JOIN SO_ASP sa ON so.DEL_DOC_NUM = sa.DEL_DOC_NUM JOIN CUST_ASP c ON c.CUST_CD = so.CUST_CD';
		$this->dbcolumns        = array(
									  'CUST_CD'=>'CUST_CD'
									, 'DEL_DOC_NUM'=>'DEL_DOC_NUM'
									);
        $this->dbcolumns_function = array(  
                                        'CUST_CD' => 'SO.CUST_CD',
                                        'DEL_DOC_NUM' => 'SO.DEL_DOC_NUM' );


		$this->errorMsg 			= "";

	}

    public function getSyfSalesOrder( $amt, $storeCd, $promoCd, $acctNum ){
        global $appconfig, $logger; 

        $where = "WHERE ORD_TP_CD = 'SAL' AND SO_STORE_CD = '" . $storeCd . "' AND FIN_CUST_CD = 'SYF' AND ORIG_FI_AMT = '" . $amt . "' AND AS_PROMO_CD = '" . $promoCd . "' AND c.ACCT_NUM = '" . $acctNum . "' ";

        $result = $this->query( $where );

        if ( $result < 0 ){
            $logger->debug( "Synchrony Reconciliation: Error query getSyfSalesOrder" );
        }

        if( $row = $this->next() ){
            return $this;
        }

        return null;

    }


}

?>
