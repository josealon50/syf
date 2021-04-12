<?php
/**
 * I literally just took the PLFCRIN.PHP and make it fit my ASP_RECON table.  SO LOLOLOLOL.
 * Direct query of custom.TD_FCRIN.
 * The select statement generated from this class does not require any additional runtime
 * parameters.
 *---------------------------------------------------------------------------------------------
 * Programming Log
 * Programmer	Date 		Notes
 * tsl          01/26/2015  Created
 * afa			10/19/2017  Edited
 */
class ASPRecon extends IDBTable {

	public function __construct($db) {
		parent::__construct($db);
		$this->tablename        = 'ASP_RECON';
		$this->dbcolumns        = array(  'CREATE_DT'=>'CREATE_DT'
										, 'ID'=>'ID'
										, 'AS_CD'=>'AS_CD'
										, 'AS_STORE_CD'=>'AS_STORE_CD'
										, 'ORIGIN_STORE'=>'ORIGIN_STORE'
										, 'CREDIT_OR_DEBIT'=>'CREDIT_OR_DEBIT'
										, 'PROCESS_DT' => 'PROCESS_DT'
										, 'STATUS'=>'STATUS'
										, 'RECORD_TYPE'=>'RECORD_TYPE'
										, 'ACCT_NUM_PREFIX'=>'ACCT_NUM_PREFIX'
										, 'BNK_CRD_NUM'=>'BNK_CRD_NUM'
										, 'IVC_CD'=>'IVC_CD'
										, 'AMT'=>'AMT'
										, 'DES'=>'DES'
										, 'EXCEPTIONS' => 'EXCEPTIONS');
						
		$this->dbcolumns_date  	= array(  'CREATE_DT', 'PROCESS_DT' );
		$this->setAutoIDColumn("ID");
	}
    public function isRecordProcessed( $record ){
        global $appconfig;

        $where = "WHERE AS_CD = '" . $record['AS_CD'] . "' AND AMT = '" . $record['AMT'] . "' AND AS_STORE_CD = '" . $record['STORE_CD'] . "' AND IVC_CD = '" . $record['IVC_CD'] . "' ";

        $result = $this->query($where);
        if( $result == 0 ){
            return true;
        }

        return false;
    }
}

?>
