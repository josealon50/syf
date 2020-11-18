<?php
    
    class SynchronySettlement{  
        protected $db;

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
        public function getBatchHeader(){
            global $appconfig;
            
            $filler = ' '; 
            $now = date('mdy');
            $batchHeader =    $appconfig['synchrony']['BATCH_RECORD_TYPE_CODE']
                             .$appconfig['synchrony']['merchant_number']
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
            $totalDollarAmount = str_replace( '.', '', $totalDollarAmount );

            $totalSales = str_pad($totalDollarAmount, 9, "0", STR_PAD_LEFT );
            $lastDigit = substr( $totalSales, -1 );
            $negative = $totalSales < 0 ? '-' : '+';
            $trailing = array_search( $negative . $lastDigit, $appconfig['synchrony']['EBCDIC'] );
            $merchantNum = new MorStoreToAspMerchant( $db );
            $merchantNum = $merchantNum->getStoreMerchantNumber( $storeCd, 'SYF' ); 

            $trailer =   $appconfig['synchrony']['TRAILER_RECORD_TYPE_CODE']
                        .$merchantNum->get_MERCHANT_NUM()
                        .str_pad($totalNumOfRecords, 9, $zeroes, STR_PAD_LEFT ) 
                        .$totalDollarAmount
                        .$trailing
                        .str_pad($filler, 51)
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
            $totalDollarAmount = str_replace( '.', '', $totalDollarAmount );

            $totalSales = str_pad($totalDollarAmount, 9, "0", STR_PAD_LEFT );
            $lastDigit = substr( $totalSales, -1 );
            $negative = $totalSales < 0 ? '-' : '+';
            $trailing = array_search( $negative . $lastDigit, $appconfig['synchrony']['EBCDIC'] );

            $trailer =   $appconfig['synchrony']['BANK_TRAILER_RECORD_TYPE_CODE']
                        .$appconfig['synchrony']['FDR_SYSTEM_NUMBER']
                        .$appconfig['synchrony']['PRINCIPAL_BANK_NUMBER']
                        .str_pad($totalNumOfRecords, 7, $zeroes, STR_PAD_LEFT ) 
                        .str_pad($totalDollarAmount, 9, $zeroes, STR_PAD_LEFT )
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
            $transactionDate = new IDate();
            $transactionDate = $transactionDate->setDate( $row['CREATE_DT_TIME'], 'mdy');
            $transactionType = $row['ORD_TP_CD'] == 'SAL' ? 'S' : 'R'; 
            $acctNum = $this->getSynchronyAccountNumber( $db, $row['CUST_CD'], 'SYF', $row['ACCT_CD'] ); 

            $ticket =    $appconfig['synchrony']['DETAIL_RECORD_TYPE_CODE']         //Record Type Code
                        .$acctNum                                       //Account Number
                        .$transactionDate                               //Transaction Date
                        .str_pad(  str_replace( '.', '', $row['AMT'] ), 7, $zeroes, STR_PAD_LEFT )        //Amount
                        .$transactionType                               //Transaction Type
                        .'00'                                           //In store payment
                        .str_pad( $recordId, $zeroes, 6, STR_PAD_LEFT )                   //Merchant Transaction Indetifier
                        .'0000'                                         //Ticket Terms
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
        public function processAddendaFoEachRecord( $row ){
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
        public function getSynchronyAccountNumber( $db, $custCd, $asCd, $acctCD ){
            global $appconfig, $app, $errmsg, $logger;

            $cust = new CustAsp( $db ); 
            $cust = $cust->getCustAspByAcctNumAndAsCdAndCustCd ( $custCd, $asCd, $acctCD, '' );

            //Decrypt the account number
            $decryption = openssl_decrypt($cust->get_ACCT_NUM(), $appconfig['ciphering'], $appconfig['encryption_key'], $appconfig['options'], $appconfig['encryption_iv']);

            return $decryption;
        }


    }




?>
