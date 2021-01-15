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
    }
?>
