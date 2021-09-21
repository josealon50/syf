<?php
    class Morcommon{
        /*-----------------------------------------------------------------------------------
         *------------------------------ standAloneAppConnect -------------------------------
         *-----------------------------------------------------------------------------------
         *
         * Global routine that facilitates the connection to Mor's GERS database
         *
         * @return mixed $db connection object from IDBResource, or false if there is an error
         * @see IDBResource
         */
        public function standAloneAppConnect() {
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
        /*-----------------------------------------------------------------------------------
         *------------------------------------- genDocNum -----------------------------------
         *-----------------------------------------------------------------------------------
         *
         * Gernerate a GERS Document number for store codes. Assumes the system date
         *
         * @param $db 	The database connection as an IDBResource instance
         * @param $storeCd The GERS Store Code
         * @return String GERS Document number, False on error
         * 
         */
        public function genDocNum($db, $storeCd) {
            global $logger;
            // Variables 
            $docNum  = "           ";
            $seqNum  = "    ";
            $autoDoc = "           ";

            // Connect to Database
            $conn = $db->getConnection();		        

            /* The call */
            $sql = "CALL MOR_UTILS.genDocNum(:STORECD, sysdate, :DOCNUM, :DOCSEQ) into :TEMP";

            /* Parse connection and sql */
            $stmt = oci_parse($conn, $sql);

            if (! $stmt) {
                $logger->error(oci_error());

                return false;
            }

            /* Binding Parameters */
            oci_bind_by_name($stmt, ':STORECD', $storeCd) ;
            oci_bind_by_name($stmt, ':DOCNUM', $docNum) ;
            oci_bind_by_name($stmt, ':DOCSEQ', $seqNum) ;
            oci_bind_by_name($stmt, ':TEMP', $autoDoc) ;

            /* Execute */
            $res = oci_execute($stmt);

            if (!$res) {
                $logger->error(oci_error());

                return false;
            }

            return $autoDoc;
        }
    }
?>
