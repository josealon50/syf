<?php

class MorStoreToAspMerchant extends IDBTable {

    public function __construct($db) {
        parent::__construct($db);
        $this->tablename        = 'STORE2ASP';
        $this->dbcolumns        = array(
                                         'ID' => 'ID'
                                        ,'STORE_CD' => 'Store Code'
                                        ,'AS_CD' => 'Finance Company' 
                                        ,'MERCHANT_NUM' => 'Merchant Number'
                                        ,'CREATED_AT' => 'Created Date'										
                                        ,'UPDATED_AT' => 'Updated Date'										
                                       );

        $this->dbcolumns_date    = array(
                                           "UPDATED_AT"
                                         , "CREATED_AT"
                                        );

        $this->setAutoIDColumn("ID");
    }

    public function query( $where="", $postclauses="" ){
        $newWhere = $where . " " . $postclauses;
            
        return parent::query($newWhere);
    }

    public function getStoreMerchantNumber( $storeCD, $asCD ) {
        global $appconfig, $app, $errmsg, $logger;

        $where = "WHERE STORE_CD = '" . $storeCD . "' AND AS_CD = '" . $asCD . "' ";
            
        $result = $this->query( $where, '' );

        if($result < 0){
            $errmsg = 'Unable to update DEL_RETURNS: ' . $where;

            $return['error'] = true;
            $return['update'] = false;
            $return['msg']   = $errmsg;
                
            return NULL; 

        }

        if($row = $this->next()){
            return $this;
        } 

        return NULL;


    }
        

}

?>

