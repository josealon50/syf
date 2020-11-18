<?php

class SoAsp extends IDBTable {

    public function __construct($db) {
        parent::__construct($db);
        $this->tablename        = 'SO_ASP';
        $this->dbcolumns        = array(
                                         'DEL_DOC_NUM' => 'DEL_DOC_NUM'
                                        ,'AS_PROMO_CD' => 'AS_PROMO_CD'
                                        ,'PROMO_CD' => 'PROMO_CD' 
                                       );

    }

    public function query( $where="", $postclauses="" ){
        $newWhere = $where . " " . $postclauses;
            
        return parent::query($newWhere);
    }

    public function getPromoCode( $delDocNum ) {
        global $appconfig, $app, $errmsg, $logger;

        $where = "WHERE DEL_DOC_NUM = '" . $delDocNum . "' ";
            
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

