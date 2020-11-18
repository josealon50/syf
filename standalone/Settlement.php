<?php
    require_once( '../config.php');
    require_once("..".DIRECTORY_SEPARATOR."public".DIRECTORY_SEPARATOR."libs".DIRECTORY_SEPARATOR."iware".DIRECTORY_SEPARATOR."php".DIRECTORY_SEPARATOR."utils".DIRECTORY_SEPARATOR."IAutoLoad.php");
    global $appconfig;
    $autoload = new IAutoLoad($classpath);
    ini_set('default_socket_timeout', 600);


    /*-----------------------------------------------------------------------------------
     *--------------------------------- sessionConnect ----------------------------------
     *-----------------------------------------------------------------------------------
     *
     * Global routine that facilitates the connection to Mor's GERS database
     *
     * @return mixed $db connection object from IDBResource, or false if there is an error
     * @see IDBResource
     */
    function sessionConnect() {
            global $appconfig;

            $db = new IDBResource($appconfig['dbhost'], $appconfig['dbuser'], $appconfig['dbpwd'],  $appconfig['dbname']);

            try {
                    $db->open();
            }
            catch (Exception $e) {
                        $errmsg   = 'Invalid Username/Password';

                        return false;
            }

            return $db;

    }

    //Variables needed
    $db = sessionConnect();
    $info = new SettlementInfo( $db );
    $settle = new SynchronySettlement( $db, $appconfig, 2 );
    $totalNumOfRecords = 0;
    $netDollarAmount = 0;
    $file = fopen( "syf_morfurniture_st", "w" );

    fwrite( $file, $settle->getBankHeader() );
    fwrite( $file,  $settle->getBatchHeader() );


    //Query for new tickets
    $where = "WHERE AS_CD = 'SYF'"; 

    $result = $info->query( $where );

    if($result < 0 ){
        echo "SOMETHING WENT WRONG";
        exit;
    }

    $netDollarAmount = 0;
    $transactionsPerStore = [];
    while( $row = $info->next() ){
        //Some validation happens first 
        fwrite( $file, $settle->processRecord( $db, $row, $totalNumOfRecords ));
        fwrite( $file,  $settle->processAddendaFoEachRecord( $row ));
        $totalNumOfRecords++;
        
        if ( array_key_exists( $row['STORE_CD'], $transactionsPerStore )){
            $transactionsPerStore[$row['STORE_CD']]['TOTAL_RECORDS'] += 1;
            $transactionsPerStore[$row['STORE_CD']]['AMOUNT'] = $row['ORD_TP_CD'] == 'SAL' ? $row['AMT'] + $transactionsPerStore[$row['STORE_CD']]['AMOUNT'] : $transactionsPerStore[$row['STORE_CD']]['AMOUNT'] - $row['AMT'];
        }
        else{
            $transactionsPerStore[$row['STORE_CD']]['TOTAL_RECORDS'] = 1;
            $transactionsPerStore[$row['STORE_CD']]['AMOUNT'] = $row['AMT'];
        }
    }

    foreach( $transactionsPerStore as $key => $value ){
        fwrite( $file, $settle->getBatchTrailer( $db, $key, $value['TOTAL_RECORDS'], $value['AMOUNT'] ));
        fwrite( $file, $settle->getBankTrailer( $value['TOTAL_RECORDS'], $value['AMOUNT'] ));
    }

?>
