<?php
    class SynchronyFinance extends FinanceCompany{  
        protected $db;
        protected $mainReportName;
        protected $settlementName;
        protected $totalNumRecords;
        protected $totalDollarAmount;
        protected $totalSalesCount;
        protected $totalReturns;
        protected $totalReturnsCount;

        public function __construct($db){
            parent::__construct( "SYF" );
            $this->db = $db;
            $this->totalNumRecords = 0;
            $this->totalDollarAmount = 0;
            $this->totalSales = 0;
            $this->totalSalesCount = 0;
            $this->totalReturns = 0;
            $this->totalReturnsCount = 0;

        }

        /******************************************************
         *  Function getBankHeader                            *
         *  ------------------------------------------------- *
         *  Description: Function will return the bank header *
         *      Pos, Description                              *
         *      1-1, Record Type Code                         *
         *      2-5, FDR System Number                        *
         *      6-9, Principal Bank Number                    *
         *      10-15, Date Of Tape                           *
         *      16-16, Reject Tape Indicator                  *
         *      17-17, Format Code                            *
         *      18-20, Filler                                 *
         *      21-22, Reject Discrepancy                     *
         *      23-24, Filler                                 *
         *      25-25, Full Description Switch                *
         *      26-27, Filler                                 *
         *      28-28, Merchant Page Break Flag               *
         *      29-80, Filler                                 *
         *                                                    *
         *  Parameters: None                                  *
         *  Return: (String) Bank Header                      *
         *                                                    *
         ******************************************************/
        public function getBankHeader(){
            global $appconfig;

            $filler = ' '; 
            $today = date('mdy');
            $bankHeader = $appconfig['synchrony']['RECORD_TYPE_CODE']
                         .$appconfig['synchrony']['FDR_SYSTEM_NUMBER']
                         .$appconfig['synchrony']['PRINCIPAL_BANK_NUMBER']
                         .$today
                         .$appconfig['synchrony']['REJECT_TYPE_INDICATOR']
                         .$appconfig['synchrony']['FORMAT_CODE']
                         .str_pad( $filler, 3)
                         .$appconfig['synchrony']['REJECT_TYPE_DISCREPANCY']
                         .str_pad( $filler, 2)
                         .$appconfig['synchrony']['FULL_DESCRIPTION_SWITCHES']
                         .str_pad( $filler, 2)
                         .$appconfig['synchrony']['MERCHANT_PAGE_BREAK_FLAG']
                         .str_pad( $filler, 52)
                         ."\n";

            return $bankHeader;

        }

        /******************************************************
         *  Function getBatchHeader                           *
         *  ------------------------------------------------- *
         *  Description: Will return the batch header         *
         *      Pos, Description                              *
         *      1-1, Record Type Code                         *
         *      2-17, Merchant Number                         *
         *      18-20, Filler                                 *
         *      21-21, Reference Number Indicator             *
         *      22-23, Float Indicator                        *
         *      24-36, Filler                                 *
         *      37-37, Compliance Indicator                   *
         *      38-56, Filler                                 *
         *      57-57, WST-EBH-DESC-TERMS-SW                  *
         *      58-59, Filler                                 *
         *      60-60, WST-EBH-DEPT-CODE-SW                   *
         *      61-80, Filler                                 *
         *                                                    *
         *  Parameters: None                                  *
         *  Return: (String) Batch Header                     *
         *                                                    *
         ******************************************************/
        public function getBatchHeader( $db, $storeCd ){
            global $appconfig;

            $filler = ' '; 
            $merchantNum = new MorStoreToAspMerchant( $db );
            $merchantNum = $merchantNum->getStoreMerchantNumber( $storeCd, 'SYF' ); 
            $now = date('mdy');
            $batchHeader =    $appconfig['synchrony']['BATCH_RECORD_TYPE_CODE']
                             .$merchantNum->get_MERCHANT_NUM()
                             .str_pad( $filler, 3)
                             .$appconfig['synchrony']['REFERENCE_NUMBER_INDICATOR']
                             .$appconfig['synchrony']['FLOAT_INDICATOR']
                             .str_pad( $filler, 13)
                             .$appconfig['synchrony']['COMPLIANCE_INDICATOR']
                             .str_pad( $filler, 19)
                             .$appconfig['synchrony']['WST-EBH-DESC-TERMS-SW']
                             .str_pad( $filler, 2)
                             .$appconfig['synchrony']['WST-EBH-DEPT-CODE-SW']
                             .str_pad( $filler, 20)
                             ."\n";


            return $batchHeader;
        }

        /******************************************************
         *  Function getBatchTrailer                          *
         *  ------------------------------------------------- *
         *  Description: Will return the batch trailer        *
         *      Pos, Description                              *
         *      1-1, Record Type Code                         *
         *      2-17, Merchant Number                         *
         *      18-24, Number of Detail Records               *
         *      25-33, Net Dollar Amount                      *
         *      34-80, Filler                                 *
         *                                                    *
         *  Parameters: (IDB) Connection Object               *
         *              (String) Store Code                   *
         *              (String) Total Number of Records      *
         *              (String) Total Amount                 *
         *  Return: (String) Batch Trailer                    *
         *                                                    *
         ******************************************************/
        public function getBatchTrailer( $db, $storeCd, $totalNumOfRecords, $totalDollarAmount ){
            global $appconfig;
            $zeroes = "0";
            $filler = " ";

            $negative = $totalDollarAmount < 0 ? '-' : '';
            if ( strpos($totalDollarAmount, '.' ) > 0 ){
                $totalDollarAmount = str_replace( '.', '', $totalDollarAmount );
            }
            else{
                $totalDollarAmount = $totalDollarAmount . "00";

            }
            $totalDollarAmount = str_replace( '-', '', $totalDollarAmount );

            $lastDigit = substr( $totalDollarAmount, -1 );
            $totalDollarAmount = substr( $totalDollarAmount, 0, -1 );
            $trailing = array_search( $negative . $lastDigit, $appconfig['synchrony']['EBCDIC'], true );

            $merchantNum = new MorStoreToAspMerchant( $db );
            $merchantNum = $merchantNum->getStoreMerchantNumber( $storeCd, 'SYF' ); 

            $trailer =   $appconfig['synchrony']['TRAILER_RECORD_TYPE_CODE']
                        .$merchantNum->get_MERCHANT_NUM()
                        .str_pad($totalNumOfRecords, 7, $zeroes, STR_PAD_LEFT ) 
                        .str_pad($totalDollarAmount, 8, $zeroes, STR_PAD_LEFT )
                        .$trailing
                        .str_pad($filler, 47)
                        ."\n"
                        ;

            return $trailer;
        }

        /******************************************************
         *  Function getBankTrailer                           *
         *  ------------------------------------------------- *
         *  Description: Will return the batch trailer        *
         *      Pos, Description                              *
         *      1-1, Record Type Code                         *
         *      2-5, FDR System Number                        *
         *      6-9, Principal Bank Number                    *
         *      10-18, Numer of Detail Records                *
         *      19-29, Net Dollar Amount                      *
         *      30-80, Filler                                 *
         *                                                    *
         *  Parameters: (String) Total Number of Records      *
         *              (String) Total Amount                 *  
         *                                                    *
         *  Return: (String) Bank Trailer                     *
         *                                                    *
         ******************************************************/
        public function getBankTrailer( $totalNumOfRecords, $totalDollarAmount ){
            global $appconfig;
            $zeroes = "0";
            $filler = " ";

            $negative = $totalDollarAmount < 0 ? '-' : '';
            if ( strpos($totalDollarAmount, '.' ) > 0 ){
                $totalDollarAmount = str_replace( '.', '', $totalDollarAmount );
            }
            else{
                $totalDollarAmount = $totalDollarAmount . "00";

            }
            $totalDollarAmount = str_replace( '-', '', $totalDollarAmount );

            $lastDigit = substr( $totalDollarAmount, -1 );
            $totalDollarAmount = substr( $totalDollarAmount, 0, -1 );
            $trailing = array_search( $negative . $lastDigit, $appconfig['synchrony']['EBCDIC'], true );
            $trailer =   $appconfig['synchrony']['BANK_TRAILER_RECORD_TYPE_CODE']
                        .$appconfig['synchrony']['FDR_SYSTEM_NUMBER']
                        .$appconfig['synchrony']['PRINCIPAL_BANK_NUMBER']
                        .str_pad($totalNumOfRecords, 9, $zeroes, STR_PAD_LEFT ) 
                        .str_pad($totalDollarAmount, 10, $zeroes, STR_PAD_LEFT )
                        .$trailing
                        .str_pad($filler, 51)
                        ."\n";

            return $trailer;
        

        }

        /******************************************************
         *  Function processRecord                            *
         *  ------------------------------------------------- *
         *  Description: Will return the record processed,    *
         *  and all records will have an addenda of type      *
         *  one.                                              *
         *                                                    *
         *      Pos, Description                              *
         *      1-1, Record Type Code                         *
         *      2-17, Cardholder Account Number               *
         *      18-23, Transaction Date                       *
         *      24-30, Transaction Amount                     *
         *      31-31, Transaction Code                       *
         *      32-33, In Store Payment Tender Type           *
         *      34-39, Merchant Transaction Identifier        *
         *      40-43, Ticket Terms                           *
         *      44-59, Department Code                        *
         *      60-70, Filler                                 *
         *      80-80, Filler                                 *
         *                                                    *
         *  Parameters: (IDB) Connection String               *
         *              (Array) row                           *
         *  Return: (String) record                           *
         *                                                    *
         ******************************************************/
        public function processRecord( $db, $row, $recordId ){
            global $appconfig;
            
            $filler = ' ';
            $zeroes = '0';

            $custAsp = new CustAsp ( $db );
            $custAsp = $custAsp->getAppRefNumByCustCdAndAsCdAndAcctNum( $row['CUST_CD'], 'SYF', $row['BNK_CRD_NUM'] );
            $where = "WHERE CUST_CD = '" . $row['CUST_CD'] . " AND AS_CD = 'SYF'";

            //Chek for float values for amount 
            $amt = '';
            if( strpos($row['AMT'], '.') > 0 ){
                $amt = str_replace( '.', '', $row['AMT'] );
            }
            else{
                $amt = $row['AMT'] . '00';
            }

            $transactionDate = new IDate();
            $transactionDate = $transactionDate->setDate( $row['CREATE_DT_TIME'], 'mdy');
            $transactionType = $row['ORD_TP_CD'] == 'SAL' ? 'S' : 'R'; 

            $acctNum = $this->getSynchronyAccountNumber( $db, $row['CUST_CD'], 'SYF', $row['ACCT_CD'] ); 
            //$soAsp = new SoAsp( $db );
            //$soAsp = $soAsp->getPromoCode( $row['DEL_DOC_NUM'] );

            $ticket =    $appconfig['synchrony']['DETAIL_RECORD_TYPE_CODE']         //Record Type Code
                        .$acctNum                                       //Account Number
                        .$transactionDate                               //Transaction Date
                        .str_pad( $amt, 7, $zeroes, STR_PAD_LEFT )        //Amount
                        .$transactionType                               //Transaction Type
                        .'00'                                           //In store payment
                        .str_pad( $recordId, 6, $zeroes, STR_PAD_LEFT )                   //Merchant Transaction Indetifier
                        .str_pad( $row['SO_ASP_AS_PROMO_CD'], 4, $zeroes, STR_PAD_LEFT )  //Ticket Terms
                        .str_pad($filler, 16)                           //Department Codes
                        .str_pad($filler, 20)                           //Filler    
                        .'1'                                            //Addenda
                        ."\n";

            return $ticket;

        }

        /******************************************************
         *  Function processAddendaFoEachRecordRecord         *
         *  ------------------------------------------------- *
         *  Description: Will return the record processed,    *
         *  addenda                                           *
         *                                                    *
         *      Pos, Description                              *
         *      1-1, WST CAT Rec Code                         *
         *      2-2, Record Indicator                         *
         *      3-8, Authorization Code                       *
         *      9-10, Entry Mode                              *
         *      11-11, Filler                                 *
         *      12-20, Filler                                 *
         *      21-38, Filler                                 *
         *      39-39, Addenda Indicator                      *
         *      40-80, Filler                                 *
         *                                                    *
         *  Parameters: (Array) row                           *
         *  Return: (String) Addenda                          *
         *                                                    *
         ******************************************************/
        public function processAddendaFoEachRecordRecord( $row ){
            global $appconfig;
            $filler = ' ';
            $zeros = '0';
            $addenda =   $appconfig['synchrony']['WST_CAT_REC_CODE']
                        .$appconfig['synchrony']['RECORD_INDICATOR']
                        .$row['APP_CD']
                        .$appconfig['synchrony']['ENTRY_MODE']
                        .str_pad( $filler, 1 )
                        .str_pad( $zeros, 9, $zeros, STR_PAD_LEFT )
                        .str_pad( $filler, 18 )
                        .$appconfig['synchrony']['ADDENDA_INDICATOR']
                        .str_pad( $filler, 41 )
                        ."\n";

            return $addenda;

        } 
		/*------------------------------------------------------------------------
		 *------------------------ writeTicketToSettleFile -----------------------
		 *------------------------------------------------------------------------
	     * Routine writes ticket data to the settlement file
	     *
	     * @param $db Object: IDBT Resource Connection Object to Oracle.
	     *		  $row Array: Contains all ticket information.
	     *
	     * @return boolean: 
		 *		  TRUE: Ticket written to the file.
		 *		  FALSE: Ticket was not written to the file.
	     *
	     *
	     */
		public function writeTicketToSettleFile( $db, $row, $settle, $counter ){
            global $appconfig;

            $record = $this->processRecord( $db, $row, $counter );
            $addenda = $this->processAddendaFoEachRecordRecord( $row );

            return $record . $addenda;
		}

		/*------------------------------------------------------------------------
		 *---------------------------- download ----------------------------------
		 *------------------------------------------------------------------------
         * Routine will download settlement file from Synchrony
         * 
	     * @param 
         * @return String filename
         *              
	     *
	     */
		public function download(){
            global $appconfig;

            return "syf_recon.txt";
        }

        public function getFilenameNoExt(){
            return "syf";
        }
   
		/*------------------------------------------------------------------------
		 *---------------------- getSynchronyAccountNumber -----------------------
		 *------------------------------------------------------------------------
         * Routine will decrypt and get the synchrony account number
         * 
         * @param $db - IDB database connection
         * @param $custCd - Customer Code
         * @param $asCd - Finance Company Code
         * @param $acctCd - 4 Digit Account Code  
         *
         *
         * @return String account number
	     */
        public function getSynchronyAccountNumber( $db, $custCd, $asCd, $acctCD ){
            global $appconfig, $app, $errmsg, $logger;

            $cust = new CustAsp( $db ); 
            $cust = $cust->getCustAspByAcctNumAndAsCdAndCustCd( $custCd, $asCd, $acctCD, '' );
            if(is_null( $cust )){
                return null;
            }
            //Decrypt the account number
            $decryption = openssl_decrypt($cust->get_ACCT_NUM(), $appconfig['ciphering'], $appconfig['encryption_key'], $appconfig['options'], $appconfig['encryption_iv']);

            return $decryption;
        }

		/*------------------------------------------------------------------------
		 *---------------------------- encyrpt -----------------------------------
		 *------------------------------------------------------------------------
         * Routine will encrypt settlement file 
         * 
         * @return Bool true or false
         */
        public function encrypt(){
            global $appconfig;

            try{ 
                $enc = system( ' gpg --yes --output ' . $appconfig['synchrony']['REPORT_SYF_SETTLE_OUT_DIR'] . $appconfig['synchrony']['SYF_SETTLE_FILENAME'] . ' --encrypt --recipient ' . $appconfig['synchrony']['GPG_RECIPIENT'] . " " . $appconfig['synchrony']['REPORT_SYF_SETTLE_OUT_DIR'] . $appconfig['synchrony']['SYF_SETTLE_FILENAME_DEC']  );
                return true;
            }
            catch( Exception $e ){
                return false;
            }
            

        }

		/*------------------------------------------------------------------------
		 *----------------------------- upload -----------------------------------
		 *------------------------------------------------------------------------
         * Routine will upload the encrpyted settlement file to synchrony SFTP
         *
         *
         * @return Bool true on succes false otherwise
         */
        public function uploadSettlement(){
            global $appconfig; 

            $sftp = new Net_SFTP($appconfig['synchrony']['SFTP_HOST'], $appconfig['synchrony']['SFTP_PORT']);
            $privateKey = new Crypt_RSA();

            $privateKey->loadKey(file_get_contents($appconfig['synchrony']['SFTP_SSH_KEY']));
            // login via sftp
            if (!$sftp->login($appconfig['synchrony']['SFTP_USER'], $privateKey)) {
                //Send email to mis
                return false;
            }

            $sftp->chdir($appconfig['synchrony']['SFTP_INBOUND_FOLDER']);
            $sftp->put($appconfig['synchrony']['SYF_SETTLE_FILENAME'], $appconfig['synchrony']['REPORT_SYF_SETTLE_OUT_DIR'] . $appconfig['synchrony']['SYF_SETTLE_FILENAME'], NET_SFTP_LOCAL_FILE);

            return true;
        }
        //---------------------------------------------------------------------------
        //----------------------------- validateRecords -----------------------------
        //---------------------------------------------------------------------------
        /**
         * Routine will validate the data for the tickets in ASFM
         * @param $db - IDBT connection Object
         *        $settle - IDBT table cursor
         *        $totalSales - Running counter of total sales
         *        $totalReturns - Running counter of total returns 
         * @return 
         */
        public function validateRecords( $db, $asfm, $settle, &$totalSales, &$totalReturns, &$exceptions, &$simpleRet, &$exchanges, &$validData, &$delDocWrittens, $settlement, &$transactionsPerStore ){
            global $appconfig;
            $update = [];

            //Main Driving loop 
            while( $row = $settle->next() ){
                $str = "";
                $valid = $this->validateData($row);
                
                //REVOME THIS STATEMENT ONLY FOR TESTING
                //$valid=[];

                //Check if array contain any errors
                if ( count($valid) === 0 ){
                    //Check for split tickets in ASFM
                    if ( strcmp($row['AS_TRN_TP'], 'PAUTH') === 0 ){
                        $totalSales += $row['AMT'];
                    }

                    if ( strcmp($row['AS_TRN_TP'], 'RET') === 0 ){
                        $totalReturns += $row['AMT'];
                    }
                    
                    //Update ASFM Record
                    array_push( $update, array( "DEL_DOC_NUM" => $row['DEL_DOC_NUM'] ,"CUST_CD" => $row['CUST_CD'] ,"STORE_CD" => $row['STORE_CD'] ,"AS_CD" => $row["AS_CD"] ,"AS_TRN_TP" => $row['AS_TRN_TP'] ,"IDROW" => $row['IDROW'] ,"STAT_CD" => "P"));

                    //Write to upload files
                    $ticket = $this->writeTicketToSettleFile($db, $row, $settlement, $validData );

                    //Keep track of transactions per store
                    if ( array_key_exists( $row['STORE_CD'], $transactionsPerStore )){
                        $transactionsPerStore[$row['STORE_CD']]['total_records'] += 1;
                        $transactionsPerStore[$row['STORE_CD']]['amount'] = $row['ORD_TP_CD'] == 'SAL' ? $row['AMT'] + $transactionsPerStore[$row['STORE_CD']]['amount'] : $transactionsPerStore[$row['STORE_CD']]['amount'] - $row['AMT'];
                        $transactionsPerStore[$row['STORE_CD']]['records'] .= $ticket;
                    }
                    else{
                        $transactionsPerStore[$row['STORE_CD']]['total_records'] = 1;
                        $transactionsPerStore[$row['STORE_CD']]['amount'] = $row['ORD_TP_CD'] == 'SAL' ? 0 + $row['AMT'] : 0 - $row['AMT'];
                        $transactionsPerStore[$row['STORE_CD']]['records'] = $ticket;

                    }
                    $validData++;
                }
                else{
                    if ( array_key_exists('R', $valid) ){
                        if ( count($valid) > 1 ){                        
                            if ( strlen($row['DEL_DOC_NUM']) > 11 || strcmp($row['AS_TRN_TP'], 'RET') === 0 ){
                                //Check if return ticket have a sale side attatch to it
                                if ( array_search( substr($row['DEL_DOC_NUM'], 0, 11), $delDocWrittens ) !== FALSE ) {
                                    continue;
                                }
                                else{ 
                                    $str = $this->getSaleSide( $db, $row );

                                    if ( strcmp( $str,"") === 0 ){
                                        $simpleRet .= $this->writeReturn( $row['DEL_DOC_NUM'], $row['CUST_CD'], $row['ORD_TP_CD'], $row['AMT'],$row['FINAL_DT'],$row['STATUS'], $valid );
                                        array_push( $delDocWrittens, substr($row['DEL_DOC_NUM'], 0, 11) ); 

                                    }
                                    else{
                                        $exchanges .= $str;
                                        $exchanges .=  $this->writeReturn( $row['DEL_DOC_NUM'], $row['CUST_CD'], $row['AS_TRN_TP'], $row['AMT'],$row['FINAL_DT'],$row['STATUS'], $valid );
                                        array_push( $delDocWrittens, substr($row['DEL_DOC_NUM'], 0, 11) ); 

                                    }
                                }
                                continue;

                            }

                            $error = $this->writeExceptions( $row['STORE_CD'], $row['DEL_DOC_NUM'], $row['CUST_CD'], $row['AS_TRN_TP'], $row['APP_CD'], $row['AMT'], $row['SO_ASP_PROMO_CD'], $row['FINAL_DT'], $valid );

                            array_push( $update, array( "DEL_DOC_NUM" => $row['DEL_DOC_NUM'] ,"CUST_CD" => $row['CUST_CD'] ,"STORE_CD" => $row['STORE_CD'] ,"AS_CD" => $row["AS_CD"] ,"AS_TRN_TP" => $row['AS_TRN_TP'] ,"IDROW" => $row['IDROW'] ,"STAT_CD" => "E", "EXCEPTION" => $error));

                            continue;
                        }
                        else{
                            //Check if return ticket have a sale side attatch to it
                            if ( array_search( substr($row['DEL_DOC_NUM'], 0, 11), $delDocWrittens ) !== FALSE ) {
                                continue;
                            }
                            else{ 
                                $str = $this->getSaleSide( $db, $row );
                                if ( strcmp( $str,"") === 0 ){
                                    $simpleRet .= $this->writeReturn( $row['DEL_DOC_NUM'], $row['CUST_CD'], $row['ORD_TP_CD'], $row['AMT'],$row['FINAL_DT'],$row['STATUS'], $valid );
                                    array_push( $delDocWrittens, substr($row['DEL_DOC_NUM'], 0, 11) ); 

                                }
                                else{
                                    $exchanges .= $str;
                                    $exchanges .=  $this->writeReturn( $row['DEL_DOC_NUM'], $row['CUST_CD'], $row['AS_TRN_TP'], $row['AMT'],$row['FINAL_DT'],$row['STATUS'], $valid );
                                    array_push( $delDocWrittens, substr($row['DEL_DOC_NUM'], 0, 11) ); 

                                }
                            }
                            continue;   
                        }
                    }                
                    if ( array_key_exists( 'SPL', $valid )){
                        //Check if return ticket have a sale side attatch to it
                        if ( array_search( substr($row['DEL_DOC_NUM'], 0, 11), $delDocWrittens ) !== FALSE ) {
                            continue;
                        }
                        else{ 
                            $str = $this->getCreditSide( $db, $row );

                            $exchanges .= $str;
                            $exchanges .=  $this->writeReturn( $row['DEL_DOC_NUM'], $row['CUST_CD'], $row['AS_TRN_TP'], $row['AMT'],$row['FINAL_DT'],$row['STATUS'], $valid );
                            array_push( $delDocWrittens, substr($row['DEL_DOC_NUM'], 0, 11) ); 

                            if ( count($valid) > 1 ){
                                $error = $this->writeExceptions( $row['STORE_CD'], $row['DEL_DOC_NUM'], $row['CUST_CD'], $row['AS_TRN_TP'], $row['APP_CD'], $row['AMT'], $row['SO_ASP_PROMO_CD'], $row['FINAL_DT'], $valid );

                                array_push( $update, array( "DEL_DOC_NUM" => $row['DEL_DOC_NUM'] ,"CUST_CD" => $row['CUST_CD'] ,"STORE_CD" => $row['STORE_CD'] ,"AS_CD" => $row["AS_CD"] ,"AS_TRN_TP" => $row['AS_TRN_TP'] ,"IDROW" => $row['IDROW'] ,"STAT_CD" => "E", "EXCEPTION" => $error));
                            }
                        }
                        continue;   
                    }

                    $error = $this->writeExceptions( $row['STORE_CD'], $row['DEL_DOC_NUM'], $row['CUST_CD'], $row['AS_TRN_TP'], $row['APP_CD'], $row['AMT'], $row['SO_ASP_PROMO_CD'], $row['FINAL_DT'], $valid );

                    array_push( $update, array( "DEL_DOC_NUM" => $row['DEL_DOC_NUM'] ,"CUST_CD" => $row['CUST_CD'] ,"STORE_CD" => $row['STORE_CD'] ,"AS_CD" => $row["AS_CD"] ,"AS_TRN_TP" => $row['AS_TRN_TP'] ,"IDROW" => $row['IDROW'] ,"STAT_CD" => "E", "EXCEPTION" => $error));

                }
                    
            }
            return $update;
        }

		/*------------------------------------------------------------------------
		 *------------------------------ validateData ----------------------------
		 *------------------------------------------------------------------------
	     * Method validates each ticket in ASFM. It till check for common errors 
	     * between SYF and Genesis. 
	     *
	     * @param $db Object: IDBT Resource Connection Object to Oracle.
	     *		  $row Array: Contains all ticket information.
	     *
	     * @return Array: Contains all error messages for that ticket. If array it 
	     *				  is empty it means that there is no error for that ticket.
	     *
	     *
	     */
        public function validateData($row){ 
            global $appconfig;

            $errors =[];
            if( is_null($row['ACCT_NUM']) ){
                array_push( $errors, ErrorMessages::CUST_ASP_NO_ACCT );
            }
            //Check if acct number is lees than 16 digit
            $decryption = openssl_decrypt( $row['ACCT_NUM'], $appconfig['ciphering'], $appconfig['encryption_key'], $appconfig['options'], $appconfig['encryption_iv']);
            if ( strlen($decryption) !== 16 ) {
                array_push( $errors, ErrorMessages::INVALID_ACCT_NUMBER_LENGTH );
            } 

            //Check if auth code
            if ( strlen($row['APP_CD']) !== 6 ){
                array_push( $errors, ErrorMessages::INVALID_AUTH_CODE_LENGTH );
            } 

            if( in_array( $row['SO_ASP_PROMO_CD'],  $appconfig['synchrony']['INVALID_PROMO_CODES'] )){
                array_push( $errors, ErrorMessages::INVALID_PROMO_CODES );
            }
            
            return $errors; 
        
        }

		/*------------------------------------------------------------------------
		 *------------------------------ archive ---------------------------------
		 *------------------------------------------------------------------------
         * Function will archive most recent settlement file
	     *
	     * @param 
         * @return Boolean: 
         *          TRUE: No errors.
         *          FALSE: Error archiving 
         *
	     *
	     */
        public function archive(){
            global $appconfig;
            try{
                $error = copy( $appconfig['synchrony']['REPORT_SYF_REPORT_OUT_DIR'] . "/" . $appconfig['synchrony']['SYF_SETTLE_FILENAME_DEC'], $appconfig['synchrony']['SYF_ARCHIVE_PATH'] . "/" . $appconfig['synchrony']['SYF_SETTLE_FILENAME_DEC'] . "." . date("YmdHis") );

                //Archive older report file and timestamp it 
                $error = copy( $appconfig['synchrony']['REPORT_SYF_REPORT_OUT_DIR'] . "/" . $appconfig['synchrony']['SYF_REPORT_FILENAME'], $appconfig['synchrony']['SYF_ARCHIVE_PATH'] . "/" .  $appconfig['synchrony']['SYF_REPORT_FILENAME'] . "." . date("YmdHis") );

                //Archive older encrypted file and timestamp it 
                $error = copy( $appconfig['synchrony']['REPORT_SYF_REPORT_OUT_DIR'] . "/" . $appconfig['synchrony']['SYF_SETTLE_FILENAME'], $appconfig['synchrony']['SYF_ARCHIVE_PATH'] . "/" .  $appconfig['synchrony']['SYF_SETTLE_FILENAME'] . "." . date("YmdHis") );

                return $error; 
            }
            catch( Exception $e ){
                return false;
            }

        }

        /*
        public function emailSettleCompleted(){
            global $appconfig;

            $mail = new PHPMailer;
            $mail->isSMTP();
            $mail->Host = 'morexch.morfurniture.local';
            $mail->Port = 25;
            $mail->From     = 'misgroup@morfurniture.com';
            $mail->FromName = 'Mailer';
            $mail->addAddress('ar@morfurniture.com'); //should go to finance@morfurniture.com
            $mail->addReplyTo('ar@morfurniture.com');
            $attachfile = $appconfig['TD_IN'].$fileName.'.csv';
            //$attachfile = '/gers/live/finance/td/in/'.$fileName.'.csv';
            $mail->WordWrap = 50;
            $mail->addAttachment($attachfile);
            $mail->isHTML(true);
            $mail->Subject = "FCRIN Exception File for ".$argv[2];
            $newline = '<br>';
            $mail->Body    = '<b>You processed '.$processed.' items to FCRIN. Here are the exceptions!</b>'.$newline.$newline ;

            if(!$mail->send()) {
                echo 'Message could not be sent.'."\n";
                echo 'Mailer Error: ' . $mail->ErrorInfo;
                return -1;
            } else {
                echo 'Message has been sent'."\n\n";
            }
            return true;


        }
         */

    }

?>
