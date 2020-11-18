<?php
	class FinanceCompany{
		protected $filename = "";
		protected $fileFolder = "";
		protected $excelObj;
        protected $filePtr;
        protected $headerExc;
        protected $headerRet;
        protected $financeCompany;
        protected $spreadSheetReportNameWithPath;

		public function __construct( $financeCompany ){
            $this->financeCompany = $financeCompany;
            $this->headerExc = "Store Code,Invoice Number,Customer Code,Transaction Type,Approval Code,Amount,Promo Code,Final Date,Exceptions\n";
            $this->headerRet = "Invoice Number,Customer Code,Transaction Type,Amount,Final Date,Sales Order Status,Exceptions\n";
            //$this->excelObj = new PHPExcel();
            $this->spreadSheetReportNameWithPath = 'dummy.xlsx';
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
		public function validateData( $db, &$row ){
			$errors = array();

			return $errors;
		}
		/*------------------------------------------------------------------------
		 *-------------------------- emailSettleCompleted ------------------------
		 *------------------------------------------------------------------------
	     * Method will send and attaced to an email settlement file 
	     *
         * @param asCd: (String) Finance Compnany
         *        file: (String) path to file name 
         *        ftpMesg: (String) Statistic message
	     *
         * @return (Boolean) T: If email was sent succesful
         *                   F: If email was not sent succesful
	     *
	     */
		public function emailSettleCompleted( $asCd, $file, $ftpMesg ){
            global $appconfig;

            $mail = new PHPMailer;
            $mail->isSMTP();                    // Set mailer to use SMTP
            $mail->Host = 'morexch.morfurniture.local'; // Specify main and backup SMTP servers
            $mail->Port = 25;                   // TCP port to connect to
            $mail->From     = $appconfig['EMAIL']['FROM'];
            $mail->FromName = 'Mailer';
            $mail->Subject = "SETTL for ".$asCd." has completed.";
            
            //Add Recipients to the email
            foreach( $appconfig['EMAIL']['TO'] as $key => $value ){
                $mail->addAddress($value);   
            }

            //Add CC's to the email
            foreach( $appconfig['EMAIL']['CC'] as $key => $value ){
                $mail->addCC( $value );
            }

            $mail->addReplyTo($appconfig['EMAIL']['REPLY_TO']);

            $mail->WordWrap = 50;                               // Set word wrap to 50 characters
            $mail->isHTML(true);                                // Set email format to HTML
            
            $newline = '<br>';
            $tab     = '&nbsp;&nbsp;&nbsp;&nbsp;';       
            
            //Add atachment to the email using the path where the file is created.
            $mail->addAttachment( $file );                  //Attach file to email
            
            $mail->Body  = '<b><big>Exception File.</big></b>'.$newline.$newline;
            $mail->Body .= "File Name".$tab.$tab.$tab." &nbsp;&nbsp; = ".$file.$newline;
            $mail->Body .= "File Date".$tab.$tab.$tab." &nbsp;&nbsp; = ". date("F j, Y, g:i a").$newline.$newline;
            $mail->Body .= $ftpMesg.$newline;
            
            if(!$mail->send()) {
                echo 'Message could not be sent.'."\n";
                echo 'Mailer Error: ' . $mail->ErrorInfo;
                return false;
            } 
            else {
                echo 'Message has been sent'."\n\n";
                return true;
            }
		}		
		/*------------------------------------------------------------------------
		 *------------------------ processEvenExchanges --------------------------
		 *------------------------------------------------------------------------
	     * Routine will process all the even exchanges. The process starts by quer-
	     * ying table ASP_STORE_FORWARD for any tickets that have AS_CD = 'SYF', 
	     * tickets with status code of H, transaction type to be return, and their
	     * del doc number ends with an A. For each return we check in AR_TRN and SO
	     * for if the ticket its finalized and the amounts match for both records.
	     * If both records match the ticket gets delete from ASFM.
	     *
	     * @param $db Object: IDBT Connection to Oracle.
	     *		  $begDate String: Starting date
	     *		  $endDate String: Ending date.
	     *
	     * @return (String): All invoices that need to be review.
	     *
	     *
	     */
		public function processEvenExchanges($db){
			$asfm = new ASPStoreForward($db);

            $soasp = new SOASP($db);

			//Get all returns from asfm that end with an A in their del doc num
			$evenExchangesNum = 0;

            $exchanges = "";

			$where =  "WHERE ASP_STORE_FORWARD.AS_CD = 'SYF' "
					 ."AND ASP_STORE_FORWARD.STAT_CD = 'H' "
					 ."AND ASP_STORE_FORWARD.AS_TRN_TP = 'RET' "
					 ."AND SUBSTR(DEL_DOC_NUM,12,1) = 'A' "
					 ."AND CREATE_DT_TIME < SYSDATE ";

			$postclauses = "ORDER BY DEL_DOC_NUM";

			$result = $asfm->query($where, $postclauses);

			if ( $result < 0 ){
				echo $asfm->getError();
			}

			$returns = array(array());
			
			//Process the result set
			while( $row = $asfm->next() ){
				$deldocnum = substr($asfm->get_DEL_DOC_NUM(), 0, 11);

				$returns[$deldocnum]['STORE_CD'] = $asfm->get_STORE_CD();
				$returns[$deldocnum]['CSH_DRWR_CD'] = $asfm->get_CSH_DRWR_CD();
				$returns[$deldocnum]['AS_CD'] = $asfm->get_CSH_DRWR_CD();
				$returns[$deldocnum]['CUST_CD'] = $asfm->get_CUST_CD();
				$returns[$deldocnum]['SO_EMP_SLSP_CD1'] = $asfm->get_SO_EMP_SLSP_CD1();
				$returns[$deldocnum]['COV_CDS'] = $asfm->get_COV_CDS();
				$returns[$deldocnum]['DEL_DOC_NUM'] = $asfm->get_DEL_DOC_NUM();
				$returns[$deldocnum]['AS_TRN_TP'] = $asfm->get_AS_TRN_TP();
				$returns[$deldocnum]['AMT'] = $asfm->get_AMT();
				$returns[$deldocnum]['BNK_CRD_NUM'] = $asfm->get_BNK_CRD_NUM();
				$returns[$deldocnum]['EXP_DT'] = $asfm->get_EXP_DT();
				$returns[$deldocnum]['APP_CD'] = $asfm->get_APP_CD();
				$returns[$deldocnum]['TRACK1'] = $asfm->get_TRACK1();
				$returns[$deldocnum]['TRACK2'] = $asfm->get_TRACK2();
				$returns[$deldocnum]['MANUAL'] = $asfm->get_MANUAL();
				$returns[$deldocnum]['REF_NUM'] = $asfm->get_REF_NUM();
				$returns[$deldocnum]['ORIG_REF_NUM'] = $asfm->get_ORIG_REF_NUM();
				$returns[$deldocnum]['ORIG_HOST_REF_NUM'] = $asfm->get_ORIG_HOST_REF_NUM();
				$returns[$deldocnum]['MEDIUM_TP_CD'] = $asfm->get_MEDIUM_TP_CD();
				$returns[$deldocnum]['BATCH_NUM'] = $asfm->get_BATCH_NUM();
				$returns[$deldocnum]['STAT_CD'] = $asfm->get_STAT_CD();
				$returns[$deldocnum]['ERROR_DES'] = $asfm->get_ERROR_DES();
				$returns[$deldocnum]['ORIGIN_CD'] = $asfm->get_ORIGIN_CD();
				$returns[$deldocnum]['XMIT_DT_TIME'] = $asfm->get_XMIT_DT_TIME();
				$returns[$deldocnum]['EMP_CD_OP'] = $asfm->get_EMP_CD_OP();
				$returns[$deldocnum]['BNK_CRD_NUM_ENC'] = $asfm->get_BNK_CRD_NUM_ENC();
				$returns[$deldocnum]['SEQ_NUM'] = $asfm->get_SEQ_NUM();
				$returns[$deldocnum]['CREATE_DT_TIME'] = $asfm->get_CREATE_DT_TIME();

			}
			unset($returns[0]);
			
			if ( count($returns) > 0 ){
				$invoices = array_keys($returns);
                
				$asfm = new ASPStoreForward($db);
                $exchanges = 0;

				//Left join with SO
				foreach( $invoices as $key => $value ){
					$evenExchanges = new EvenExchanges($db, $value);
					$where = "WHERE AR_TRN.IVC_CD = '" . $value  . "' "
							."AND AR_TRN.TRN_TP_CD = 'SAL' ";
					$result = $evenExchanges->query($where);
					
					if ( $result < 0 ){
						echo $evenExchanges->getError();
					}
					while( $row = $evenExchanges->next() ){
						if ( array_key_exists($row['IVC_CD'], $returns) ){
							if ( strcmp($row["AMT"], $returns[$row['IVC_CD']]["AMT"]) === 0 && strcmp($row['STAT_CD'],'F') === 0 && strcmp($row['SEQ_STAT_CD'], 'F') === 0 ){
                                //Check promo codes for both the sale side and the credit memo
                                $where = "WHERE DEL_DOC_NUM = '" . $value . "' AND PROMO_CD IS NOT NULL ";

                                $result = $soasp->query( $where );

                                if ( $result < 0 ){
                                    echo "Query error processEvenExchange: " . $soasp->getError();
                                }              
                                //Check promo codes for both sale and crm tickets 
                                if ( $soasp->next() ){
                                    //Get promotional code 
                                    $promoCodeSale = $soasp->get_PROMO_CD();

                                    $where = "WHERE DEL_DOC_NUM = '" . $returns[$value]['DEL_DOC_NUM'] . "' ";
                                    
                                    //Query promo code for the promo code
                                    $result = $soasp->query( $where );

                                    if ( $result < 0 ){
                                        echo "Credit Memo promo code query error: " . $soasp->getError() . "\n";
                                    }
                                    //Check CRM promo code 
                                    if ( $soasp->next() ){
                                        $promoCodeCRM = $soasp->get_PROMO_CD();
                                        
                                        if ( strcmp( $promoCodeCRM, $promoCodeSale ) === 0 ){
                                            //If both promo codes are the same treat it as an even exchange
								            $asfm->set_STAT_CD('X');
    
                                            $where = "WHERE DEL_DOC_NUM = '" . $returns[$value]['DEL_DOC_NUM'] . "' ";
    
	            							//Update to status Code X for even exchange
				            				$result = $asfm->update($where, false);
								
							            	if ( $result < 0 ){
									            echo $asfm->getError();
								            }

								            $asfm->set_STAT_CD('X');
    
	            							$where = " WHERE DEL_DOC_NUM = '" . $value . "' ";
    
	            							//Update to status Code X for even exchange
				            				$result = $asfm->update($where, false);
								
							            	if ( $result < 0 ){
									            echo $asfm->getError();
								            }
                                            
                                            continue;
                                            
                                        }
                                        //Promo Codes are different sale side goes through, and set credit side to review 
                                        else{
								            $asfm->set_STAT_CD('R');
    
                                            $where = "WHERE DEL_DOC_NUM = '" . $returns[$value]['DEL_DOC_NUM'] . "' ";
    
	            							//Update to status Code to R in order the credit to be review
				            				$result = $asfm->update($where, false);
								
							            	if ( $result < 0 ){
									            echo $asfm->getError();
								            }
                                            $so = new SO($db);

                                            $where = "WHERE DEL_DOC_NUM = '" . $returns[$value]['DEL_DOC_NUM'] . "' ";

                                            $result = $so->query($where);

                                            if ( $result < 0 ){
                                                echo "CRM query for sale order: " . $so->getError() . "\n";
                                            }
                                            $so->next();
                                            //Add invoice information to array  
                                            $exchanges .= $returns[$value]['DEL_DOC_NUM'] . "," . $returns[$value]['CUST_CD'] . "," . $returns[$value]['AS_TRN_TP'] . "," . $so->get_FINAL_DT() . "," . $so->get_STAT_CD() . "," . "CRM Promo Code Error\n";
                                            continue;

                                        }
                                    }
                                    else{
                                        //No Promo Code on sale side treat it as an even exchange
										$asfm->set_STAT_CD('X');

		                                $where = "WHERE DEL_DOC_NUM = '" . $returns[$value]['DEL_DOC_NUM'] . "' ";

										//Update to status Code X for even exchange
										$result = $asfm->update($where, false);
										
										if ( $result < 0 ){
											echo $asfm->getError();
		                                }

										$asfm->set_STAT_CD('X');

		                                $where = "WHERE DEL_DOC_NUM = '" . $value .  "' ";

										//Update to status Code X for even exchange
										$result = $asfm->update($where, false);
										
										if ( $result < 0 ){
											echo $asfm->getError();
										}	
                                    }

                                }
                                else{
	                                //No Promo Code on sale side treat it as an even exchange
									$asfm->set_STAT_CD('X');
	                                $where = "WHERE DEL_DOC_NUM = '" . $returns[$value]['DEL_DOC_NUM'] . "' ";

									//Update to status Code X for even exchange
									$result = $asfm->update($where, false);
									
									if ( $result < 0 ){
										echo $asfm->getError();
	                                }

									$asfm->set_STAT_CD('X');

	                                $where = "WHERE DEL_DOC_NUM = '" . $value .  "' ";

									//Update to status Code X for even exchange
									$result = $asfm->update($where, false);
									
									if ( $result < 0 ){
										echo $asfm->getError();
									}
								}
							}
						}
					}
				}
			}
			return $exchanges;
        }

        /*------------------------------------------------------------------------
		 *--------------------------- createMainReport ---------------------------
		 *------------------------------------------------------------------------
	     * Routine will create a main report that will consist of all 3 reports 
	     * that are created.
	     *
	     * @param exceptions: Exception report
	     *		  simpleRet: Simple Return
	     *		  exchanges: Exchanges
         *		  manuals: Manual tickets
	     *
	     */
		function createMainReport( $ptr, $exceptions, $simpleRet, $exchanges, $manuals, $agingRet, $agingExc, $evenExchangesErrors ){
            //Write Manual Tickets 
			fwrite( $mainReportPtr, "MANUALS\n");
			fwrite( $mainReportPtr, $this->headerRet);
			fwrite( $mainReportPtr, $manuals);
			fwrite( $mainReportPtr, "\n\n\n\n");
            
            //Write Aging Return transactions
			fwrite( $mainReportPtr, "AGING TRAN\n");
			fwrite( $mainReportPtr, $this->headerRet);
			fwrite( $mainReportPtr, $agingRet);
			fwrite( $mainReportPtr, "\n\n\n\n");

            //Write exception header
			fwrite( $mainReportPtr, "EXCEPTIONS\n");
			fwrite( $mainReportPtr, $this->headerExc);
			fwrite( $mainReportPtr, $exceptions);
            fwrite( $mainReportPtr, "\n\n");
            fwrite( $mainReportPtr, $agingExc );
			fwrite( $mainReportPtr, "\n\n\n\n");


			//Write Exchange header
			fwrite( $mainReportPtr, "SIMPLE RETURNS\n");
			fwrite( $mainReportPtr, $this->headerRet);
			fwrite( $mainReportPtr, $simpleRet);
			fwrite( $mainReportPtr, "\n\n\n\n");


			//Write simple return header
			fwrite( $mainReportPtr, "EXCHANGES\n");
			fwrite( $mainReportPtr, $this->headerRet);
			fwrite( $mainReportPtr, $exchanges);
            fwrite( $mainReportPtr, "\n\n");
			fwrite( $mainReportPtr, $evenExchangesErrors);
			
		}
		//---------------------------------------------------------------------------
        //------------------------------ Email --------------------------------------
        //---------------------------------------------------------------------------
        /**
        * Routine will send and email if upload process was succesful
        * @param 
        * @return 
        */
        public function email( $appconfig, $totalSales, $totalSalesCount, $totalReturns, $totalReturnsCount, $totalAmount, $totalCount ) {
            global $appconfig;

            $mail = new PHPMailer;
            $mail->isSMTP();                    // Set mailer to use SMTP
            $mail->Host = 'morexch.morfurniture.local'; // Specify main and backup SMTP servers
            $mail->Port = 25;                   // TCP port to connect to
            $mail->From     = $appconfig['EMAIL']['FROM'];
            $mail->FromName = 'Mailer';
            $mail->Subject = $this->financeCompany() . " Settlement Report for " . date('m-d-Y');
            
            //Add Recipients to the email
            foreach( $appconfig['EMAIL']['TO'] as $key => $value ){
                $mail->addAddress($value);   
            }

            //Add CC's to the email
            foreach( $appconfig['EMAIL']['CC'] as $key => $value ){
                $mail->addCC( $value );
            }

            $mail->addReplyTo($appconfig['EMAIL']['REPLY_TO']);

            $mail->WordWrap = 50;                               // Set word wrap to 50 characters
            $mail->isHTML(true);                                // Set email format to HTML
            
            $newline = '<br>';
            $tab     = '&nbsp;&nbsp;&nbsp;&nbsp;';       
            
            $mail->addAttachment( $this->getSpreadSheetReportNameWithPath() );                  //Attach file to email
            
            $mail->Body  = '<b><big>Results of settlement file submitted.</big></b>'.$newline.$newline;
            $mail->Body .= "File Name".$tab.$tab.$tab." &nbsp;&nbsp; = ".$this->getReportName().$newline;
            $mail->Body .= "File Date".$tab.$tab.$tab." &nbsp;&nbsp;  = ". date('m-d-Y').$newline;
            $mail->Body .= "Data Count".$tab.$tab.$tab."&nbsp; = ".$this->totalNumRecords.$newline.$newline;
            $mail->Body .= "Sales".$tab.$tab.$tab.$tab.$tab." &nbsp;&nbsp; = ".$this->totalSalesCount.$newline;
            $mail->Body .= "Sales Amount".$tab.$tab."&nbsp; = $".$this->totalSales.$newline;
            $mail->Body .= "Returns".$tab.$tab.$tab.$tab." &nbsp;&nbsp; = ".$this->totalReturnsCount.$newline;
            $mail->Body .= "Returns Amount".$tab."&nbsp; = $".$this->totalReturns.$newline.$newline;
            $mail->Body .= "<b><font size ='5'><span style='background-color: yellow;'>Net Deposit Amount = $".$this->totalDollarAmount."</span></font></b>".$newline;
            
            if(!$mail->send()) {
                echo 'Message could not be sent.'."\n";
                echo 'Mailer Error: ' . $mail->ErrorInfo;
                return false;
            } 
            else {
                echo 'Message has been sent'."\n\n";
                return true;
            }
        }

		/*------------------------------------------------------------------------
		 *------------------------------- writeHeader ----------------------------
		 *------------------------------------------------------------------------
	     * Routine will write the header for excel spreadsheet
	     *
	     * @return String: File folder path
	     *
	     *
	     */
		public function writeHeader(){
			$filename = $this->getFilename();
			
			$fileDtTime = date("Y/m/d");
			
			$this->excelObj->getSheet(0)->setTitle('Settlement Batch Detail');		
	
            // Spreadsheet Header
            $this->excelObj->setActiveSheetIndex(0)->setCellValue("A1", $this->getFinanceCompany() . " Settlement Report - Detail");
            $this->excelObj->setActiveSheetIndex(0)->setCellValue("A2", "File date          : $fileDtTime");
            
            // Column Headers
            $this->excelObj->setActiveSheetIndex(0)->setCellValue("A6", "Store");
            $this->excelObj->setActiveSheetIndex(0)->setCellValue("B6", "Customer");
            $this->excelObj->setActiveSheetIndex(0)->setCellValue("C6", "Invoice#");
            $this->excelObj->setActiveSheetIndex(0)->setCellValue("D6", "Account#");
            $this->excelObj->setActiveSheetIndex(0)->setCellValue("E6", "Approval");          
            $this->excelObj->setActiveSheetIndex(0)->setCellValue("F6", "Promo");
            $this->excelObj->setActiveSheetIndex(0)->setCellValue("G6", "Amount");
            $this->excelObj->setActiveSheetIndex(0)->setCellValue("H6", "Type");
		}

		public function writeRow($row, $activeRow, $store_num){
    		$this->excelObj->setActiveSheetIndex(0)->setCellValue("A".$activeRow, $store_num );
            $this->excelObj->setActiveSheetIndex(0)->setCellValue("B".$activeRow, $row['CUST_CD'] );
            $this->excelObj->setActiveSheetIndex(0)->setCellValue("C".$activeRow, $row['DEL_DOC_NUM'] );
            $this->excelObj->setActiveSheetIndex(0)->setCellValue("D".$activeRow, $row['BNK_CRD_NUM'] );
            $this->excelObj->setActiveSheetIndex(0)->setCellValue("E".$activeRow, $row['APP_CD'] );
            $this->excelObj->setActiveSheetIndex(0)->setCellValue("F".$activeRow, $row['SO_ASP_PROMO_CD'] );
            $this->excelObj->setActiveSheetIndex(0)->setCellValue("H".$activeRow, $row['AS_TRN_TP'] );

        	if ( strcmp( $row['AS_TRN_TP'], 'RET' ) === 0 ){
            	$this->excelObj->setActiveSheetIndex(0)->setCellValue("G".$activeRow, ((-1) * $row['AMT'] ));
           	}
        	if ( strcmp( $row['AS_TRN_TP'], 'PAUTH' ) === 0 ){ 
            	$this->excelObj->setActiveSheetIndex(0)->setCellValue("G".$activeRow, $row['AMT'] );
            }

   		 	return $activeRow++;
		}

		public function writeSpreadSheetTotals($column,$method, $totalRows) {
    		if ($method == 'SUM') {
        		$this->excelObj->setActiveSheetIndex(0)->setCellValue($column.$totalRows, "=SUBTOTAL(9, ".$column."7:".$column.($totalRows-1).")") ;
    		}
		}

		public function formatSpreadSheet( $totalRows ){
			
			// set column widths
		    $this->ColumnWidth('A',14);
		    $this->ColumnWidth('B',20);
		    $this->ColumnWidth('C',24);
		    $this->ColumnWidth('D',18);
		    $this->ColumnWidth('E',12);
		    $this->ColumnWidth('F',12);
		    $this->ColumnWidth('G',20);
		    $this->ColumnWidth('H',10);

			// set row height for header rows
    		$this->excelObj->getActiveSheet()->getRowDimension('1')->setRowHeight(30);
    		$this->excelObj->getActiveSheet()->getRowDimension('5')->setRowHeight(8);          

			// Merge Cells
			$this->excelObj->setActiveSheetIndex(0)->mergeCells('A1:C1')->mergeCells('A2:C2')->mergeCells('A3:C3');           

			//Set Font size
    		$this->excelObj->getActiveSheet()->getStyle("A1:H1")->getFont()->setSize(20);

    		$this->excelObj->getActiveSheet()->getStyle("A2:H3".$totalRows)->getFont()->setSize(12);

    		$this->excelObj->getActiveSheet()->getStyle("A5:H".$totalRows)->getFont()->setSize(14);
    		
    		$this->BoldText('A1:H3');
    		$this->BoldText('A'.($totalRows).':H'.($totalRows));

		    // Set Borders and header section
		    $this->cellBorder('A1:C1','ThickOut');
		    $this->cellBorder('A2:C3','ThickOut');
		    $this->cellBorder('A5:H5','ThickOut');

		    // Body Outlines
		    $this->cellBorder('A5:H'.($totalRows-1),'ThickOut');
		            
		    // Worksheet body gridline
		    $this->cellBorder('A6:H'.($totalRows-1),'Light');

		    // Grand total row
		    $this->cellBorder('G'.$totalRows.':G'.$totalRows,'Heavy');

			// set background colors for rows
		    $this->cellColor('A1:C1', 'FFFF00');       //  highlight yellow
		    $this->cellColor('A2:C3', '99CCFF');       // shade light blue
		    $this->cellColor('A5:H5', '0066CC');       // shade deep blue
		    $this->cellColor('A6:H6', '99CCFF');       // shade light blue
		    $this->cellColor('G' .($totalRows).':G'.($totalRows), 'FFFF00');     // highlight yellow

			//set Alignment
    		$this->excelObj->getActiveSheet()->getStyle('A3:D'.$totalRows)
    									  ->getAlignment()->setWrapText(true)
    									  ->setVertical(PHPExcel_Style_Alignment::VERTICAL_BOTTOM)
    									  ->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

			// Center justify non-money columns
		    $this->AlignColumn('A1:C1', 'HORIZONTAL_CENTER');           
		    $this->AlignColumn('A1:C3', 'HORIZONTAL_LEFT');
		    $this->AlignColumn('A6:H6', 'HORIZONTAL_CENTER');
		    $this->AlignColumn('A7:F'.$totalRows, 'HORIZONTAL_LEFT');
		    $this->AlignColumn('G7:H'.$totalRows, 'HORIZONTAL_RIGHT');
		    $this->AlignColumn('H7:H'.$totalRows, 'HORIZONTAL_CENTER');

			//Format Dollars
    		$this->excelObj->getActiveSheet()->getStyle('G7:G'.$totalRows)->getNumberFormat()->setFormatCode("[blue]$#,##0.00;[red]$(-#,##0.00)");         

			// Right pad column that have dollar values
			$this->excelObj->getActiveSheet()->getStyle('G7:G'.$totalRows)->getAlignment()->setIndent(2);   

			// Left pad Customer, Invoice and Acct# columns
			$this->excelObj->getActiveSheet()->getStyle('A7:F'.$totalRows)->getAlignment()->setIndent(1);   

			// Set Print Area
			$this->excelObj->getActiveSheet()->getPageSetup()->setPrintArea('A1:H'.$totalRows);			
		}

		public function createAndSaveSpreadSheet( $appconfig, $prgid ){
			$objWriter = PHPExcel_IOFactory::createWriter($this->excelObj, 'Excel2007');
			$objWriter->save( $this->getSpreadSheetReportNameWithPath() );

		}

		public function BoldText($cells) {
	    	$this->excelObj->getActiveSheet()->getStyle($cells)->getFont()->setBold(true);
    	}


		public function cellColor($cells,$color) {
	    	$this->excelObj->getActiveSheet()->getStyle($cells)->getFill()->applyFromArray(array('type' => PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => $color)));
	    }


		public function ColumnWidth($column,$width) {
	    	$this->excelObj->getActiveSheet()->getColumnDimension($column)->setWidth($width);
	    }


		public function AlignColumn($cells,$alignment) {
		    if ($alignment == 'HORIZONTAL_LEFT'){
		        $this->excelObj->getActiveSheet()->getStyle($cells)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);  
		    }
		    if ($alignment == 'HORIZONTAL_CENTER'){
		        $this->excelObj->getActiveSheet()->getStyle($cells)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
		    }    
		}


		public function cellBorder($cells,$weight) {
		    //  Define Styles
		    $styleArrayLight = array(
		       'borders' => array(
		             'outline' => array(
		                    'style' => PHPExcel_Style_Border::BORDER_MEDIUM,
		                    'color' => array('argb' => 'FF000000'),
		             ),
		             'inside' => array(
		                    'style' => PHPExcel_Style_Border::BORDER_THIN,
		                    'color' => array('argb' => 'FF000000'),
		             ),
		       ),
		    );      

		    //Style array for thin inner borders with medium outline 
		    $styleArrayMedium = array(
		        'borders' => array(
		            'outline' => array(
		                'style' => PHPExcel_Style_Border::BORDER_MEDIUM,
		                'color' => array('argb' => 'FF000000'),
		            ),
		            'inside' => array(
		                'style' => PHPExcel_Style_Border::BORDER_MEDIUM,
		                'color' => array('argb' => 'FF000000'),
		            ),
		        ),
		    );          

		    //Style array for thick outline border with
		    $styleArrayHeavy = array(
		        'borders' => array(
		            'outline' => array(
		                'style' => PHPExcel_Style_Border::BORDER_THICK,
		                'color' => array('argb' => 'FF000000'),
		            ),
		            'inside' => array(
		                'style' => PHPExcel_Style_Border::BORDER_MEDIUM,
		                'color' => array('argb' => 'FF000000'),
		            ),
		        ),
		    );          

		    //Style array for thick outline border
		    $styleArrayThickOut = array(
		        'borders' => array(
		            'outline' => array(
		                'style' => PHPExcel_Style_Border::BORDER_THICK,
		                'color' => array('argb' => 'FF000000'),
		            ),
		        ),
		    );     

		    if  ($weight == 'Light'){
		        $this->excelObj->getActiveSheet()->getStyle($cells)->applyFromArray($styleArrayLight);
		    }
		    if  ($weight == 'Medium'){
		        $this->excelObj->getActiveSheet()->getStyle($cells)->applyFromArray($styleArrayMedium);
		    }
		   	if  ($weight == 'Heavy'){
		       	$this->excelObj->getActiveSheet()->getStyle($cells)->applyFromArray($styleArrayHeavy);
		    }
		    if  ($weight == 'ThickOut'){
		      	$this->excelObj->getActiveSheet()->getStyle($cells)->applyFromArray($styleArrayThickOut);  
			}

		}
		/*------------------------------------------------------------------------
		 *--------------------- getSpreadSheetReportNameWithPath -----------------
		 *------------------------------------------------------------------------
	     * Routine returns spreadsheet name 
	     *
	     * @return String: name of the spreadsheet report
	     *
	     *
	     */
        public function getSpreadSheetReportNameWithPath(){
            return $this->spreadSheetReportNameWithPath;
        }

		/*------------------------------------------------------------------------
		 *--------------------- setSpreadSheetReportNameWithPath -----------------
		 *------------------------------------------------------------------------
	     * Routine returns spreadsheet name 
	     *
	     * @return String: name of the spreadsheet report
	     *
	     *
	     */
        public function setSpreadSheetReportNameWithPath( $name ){
            $this->spreadSheetReportNameWithPath = $name;
        }

        public function getFinanceCompany(){
            return $this->financeCompany;
        }

        public function setFinanceCompany( $financeCompany ){
            $this->financeCompany = $financeCompany;
        }

        //---------------------------------------------------------------------------
        //----------------------------- writeManuals --------------------------------
        //---------------------------------------------------------------------------
        /**
         * Routine will get all manual tickets
         *
         * @param 
         *      $settleInfo = Settlement IDBT Object
         *      $finco = Finance Company Object
         *      $ascd = AS Code 
         *
         *
         */
        function writeManuals( $settle, $finco, $ascd ){
            $manuals = "";
            $where = "WHERE ASP_STORE_FORWARD.STAT_CD = 'S' and ASP_STORE_FORWARD.AS_CD = '" . $ascd . "' ";
            $result = $settle->query($where);

            if ( $result < 0 ){
                echo "WriteManuals error: " . $settle->getError();
            }
            while( $row = $settle->next() ){
                $manuals .= $finco->writeReturn( $row['DEL_DOC_NUM'], $row['CUST_CD'], $row['AS_TRN_TP'], $row['AMT'], $row['FINAL_DT'], $row['STATUS'], array() );
            }
            return $manuals;
        }

        public function writeReturn( $delDoc, $custCode, $asTrnTp, $amt, $finalDate, $status, $exceptions ){
			$exc = $delDoc.",".$custCode.",".$asTrnTp.",".$amt.",".$finalDate.",".$status;
			foreach( $exceptions as $key => $value ){
				$exc .=  "," . $value;
			}
			$exc .= "\n";
			return $exc;
        }
        //---------------------------------------------------------------------------
        //------------------------- writeAgingTransactions --------------------------
        //---------------------------------------------------------------------------
        /**
         * Routine will get all aging transactions
         *
         * @param 
         *      $settleInfo = Settlement IDBT Object
         *      $finco = Finance Company Object
         *      $ascd = AS Code 
         *
         *
         */
        function writeAgingTransactions( $settle, $finco, $ascd ){
            $ageTransactions = "";
            $where = "WHERE ASP_STORE_FORWARD.STAT_CD = 'R' and ASP_STORE_FORWARD.AS_CD = '" . $ascd . "' ";
            $result = $settle->query($where);
            if ( $result < 0 ){
                echo "WriteAgingTransactions error: " . $settle->getError();
            }

            while( $row = $settle->next() ){
                $ageTransactions .= $finco->writeReturn( $row['DEL_DOC_NUM'], $row['CUST_CD'], $row['AS_TRN_TP'], $row['AMT'], $row['FINAL_DT'], $row['STATUS'], array() );
            }
            return $ageTransactions;
        }
        //---------------------------------------------------------------------------
        //-------------------------- writeAgingExceptions ---------------------------
        //---------------------------------------------------------------------------
        /**
         * Routine will get all aging exceptions
         *
         * @param 
         *      $settleInfo = Settlement IDBT Object
         *      $finco = Finance Company Object
         *      $ascd = AS Code 
         *
         *
         */
        public function writeAgingExceptions( $settle, $finco, $ascd, $db ){
            $ageTransactions = "";
            $where = "WHERE ASP_STORE_FORWARD.STAT_CD = 'E' AND ASP_STORE_FORWARD.AS_CD = '" . $ascd . "' AND ASP_STORE_FORWARD.AS_TRN_TP IN ( 'PAUTH', 'RET' )";
            $result = $settle->query($where);

            if ( $result < 0 ){
                echo "WriteAgingExceptions error: " . $settle->getError();
            }
            
            while( $row = $settle->next() ){
                $exc = $this->validateData( $db, $row );
                $ageTransactions .= $finco->writeReturn( $row['DEL_DOC_NUM'], $row['CUST_CD'], $row['AS_TRN_TP'], $row['AMT'], $row['FINAL_DT'], $row['STATUS'], $exc );
            }

            return $ageTransactions;
        }
        //---------------------------------------------------------------------------
        //-------------------------- writePromoCodeErrors ---------------------------
        //---------------------------------------------------------------------------
        /**
         * Routine will get all promo code errors from even exchanges
         *
         * @param 
         *      $settleInfo = Settlement IDBT Object
         *      $finco = Finance Company Object
         *      $ascd = AS Code 
         *
         *
         */
        public function writePromoCodeErrors( $settle, $finco, $ascd, $db ){
            $trans = "";
            $where = "WHERE ASP_STORE_FORWARD.STAT_CD = 'R' AND ASP_STORE_FORWARD.AS_CD = '" . $ascd . "' AND ASP_STORE_FORWARD.AS_TRN_TP = 'RET'";
            $result = $settle->query($where);

            if ( $result < 0 ) echo "WriteAgingExceptions error: " . $settle->getError(); 

            $exc = array( "0" => ErrorMessages::PROMO_CD_EXC_ERR ); 
            while( $row = $settle->next() ){
                $saleSide = $finco->getSaleSide( $db, $row );
                $trans .= $saleSide;
                $trans .= $finco->writeReturn( $row['DEL_DOC_NUM'], $row['CUST_CD'], $row['AS_TRN_TP'], $row['AMT'], $row['FINAL_DT'], $row['STATUS'], $exc );
            }

            return $trans;
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
        public function validateRecords( $db, $asfm, $settle, &$totalSales, &$totalReturns, &$exceptions, &$simpleRet, &$exchanges, &$validData, &$delDocWrittens, $settlement, &$transactionsPerStore){
            global $appconfig;
            /*
            //Main Driving loop 
            while( $row = $settle->next() ){
                $str = "";
                //$valid = $this->validateData($db,$row);
                $valid=[];

                //Check if array contain any errors
                if ( count($valid) === 0 ){
                    //Check for split tickets in ASFM
                    if ( strcmp($row['AS_TRN_TP'], 'PAUTH') === 0 ){
                        $totalSales += $row['AMT'];
                    }

                    if ( strcmp($row['AS_TRN_TP'], 'RET') === 0 ){
                        $totalReturns += $row['AMT'];
                    }

                    //Update ticket status code to 'P'
                    $asfm->set_STAT_CD('P');

                    $where = "WHERE DEL_DOC_NUM = '" . $row['DEL_DOC_NUM'] . "' "
                            ."AND CUST_CD = '" . $row['CUST_CD'] . "' "
                            ."AND STORE_CD = '" . $row['STORE_CD'] . "' "
                            ."AND AS_CD = '" . $row['AS_CD'] . "' "
                            ."AND AS_TRN_TP = '" . $row['AS_TRN_TP'] . "' "
                            ."AND ROWID = '" . $row['IDROW'] . "' ";

                    $result = $asfm->update($where, false);

                    if ( $result == false ){
                        echo $asfm->getError(); 
                    }
                    //Write to upload files
                    $ticket = $this->writeTicketToSettleFile($db, $row, $settlement, $validData );
                    echo $ticket . "\n";

                    //Keep track of transactions per store
                    if ( array_key_exists( $row['STORE_CD'], $transactionsPerStore )){
                        $transactionsPerStore[$row['STORE_CD']]['total_records'] += 1;
                        $transactionsPerStore[$row['STORE_CD']]['amount'] = $row['ORD_TP_CD'] == 'SAL' ? $row['AMT'] + $transactionsPerStore[$row['STORE_CD']]['amount'] : $transactionsPerStore[$row['STORE_CD']]['amount'] - $row['AMT'];
                        $transactionsPerStore[$row['STORE_CD']]['records'] .= $ticket;
                    }
                    else{
                        $transactionsPerStore[$row['STORE_CD']]['total_records'] = 1;
                        $transactionsPerStore[$row['STORE_CD']]['amount'] = $row['AMT'];
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
                            $exceptions .= $this->writeExceptions( $row['STORE_CD'], $row['DEL_DOC_NUM'], $row['CUST_CD'], $row['AS_TRN_TP'], $row['APP_CD'], $row['AMT'], $row['SO_ASP_PROMO_CD'], $row['FINAL_DT'], $valid );
                            
                            //Update ticket to status code to 'E'
                            $asfm->set_STAT_CD('E');

                            $where = "WHERE DEL_DOC_NUM = '" . $row['DEL_DOC_NUM'] . "' "
                                    ."AND CUST_CD = '" . $row['CUST_CD'] . "' "
                                    ."AND STORE_CD = '" . $row['STORE_CD'] . "' "
                                    ."AND AS_CD = '" . $row['AS_CD'] . "' "
                                    ."AND AS_TRN_TP = '" . $row['AS_TRN_TP'] . "' "
                                    ."AND ROWID = '" . $row['IDROW'] . "' ";

                            $result = $asfm->update($where, false);           
                            if ( $result == false ){
                                echo $asfm->getError();
                            }
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
                                    $exceptions .= $this->writeExceptions( $row['STORE_CD'], $row['DEL_DOC_NUM'], $row['CUST_CD'], $row['AS_TRN_TP'], $row['APP_CD'], $row['AMT'], $row['SO_ASP_PROMO_CD'], $row['FINAL_DT'], $valid );

                                    $asfm->set_STAT_CD('E');

                                    $where = "WHERE DEL_DOC_NUM = '" . $row['DEL_DOC_NUM'] . "' "
                                            ."AND CUST_CD = '" . $row['CUST_CD'] . "' "
                                            ."AND STORE_CD = '" . $row['STORE_CD'] . "' "
                                            ."AND AS_CD = '" . $row['AS_CD'] . "' "
                                            ."AND AS_TRN_TP = '" . $row['AS_TRN_TP'] . "' "
                                            ."AND ROWID = '" . $row['IDROW'] . "' ";

                                    $result = $asfm->update($where, false);

                                    if ( $result == false ){
                                        echo "Update to E error: " . $asfm->getError() . "\n";
                                    }
                                }


                            }

                            continue;   
                    }

                    $exceptions .= $this->writeExceptions( $row['STORE_CD'], $row['DEL_DOC_NUM'], $row['CUST_CD'], $row['AS_TRN_TP'], $row['APP_CD'], $row['AMT'], $row['SO_ASP_PROMO_CD'], $row['FINAL_DT'], $valid );

                    //Update ticket status code to 'E'
                    $asfm->set_STAT_CD('E');
                    $where = "WHERE DEL_DOC_NUM = '" . $row['DEL_DOC_NUM'] . "' "
                            ."AND CUST_CD = '" . $row['CUST_CD'] . "' "
                            ."AND STORE_CD = '" . $row['STORE_CD'] . "' "
                            ."AND AS_CD = '" . $row['AS_CD'] . "' "
                            ."AND AS_TRN_TP = '" . $row['AS_TRN_TP'] . "' "
                            ."AND ROWID = '" . $row['IDROW'] . "' ";
                    $result = $asfm->update($where, false);

                    if ( $result == false ){
                        echo "Update to E error: " . $asfm->getError() . "\n";
                    }
                }
                    
            }
             */
        }

		/*------------------------------------------------------------------------
		 *----------------------------- getSaleSide ------------------------------
		 *------------------------------------------------------------------------
	     * Routine will get the sale side of a return by queryin table SO and getting
	     * the base document number and then requerying SO to get the sale side 
	     * of the ticket.
	     *
	     * @param: $db: IDBT Connection object
	     *		   $row: ASFM ticket information
	     * 
	     * @return: Empty String: Sale side not found
	     *			String not empty: Sale side found
	     *
	     *
	     */
	    public function getSaleSide( $db, $row ){
	        $so = new SO($db);
            $asfm = new ASPStoreForward( $db );
	        $where = "WHERE SO.DEL_DOC_NUM  LIKE '" . substr($row['DEL_DOC_NUM'], 0, 11) . "%' and del_doc_num <> '" . $row['DEL_DOC_NUM'] . "' ";
	        $result = $so->query($where);

	        if ( $result < 0 ){
	            echo "getSaleSide Query error: " . $so->getError(); 
	        }
            $str = "";
            $simpleRet = true;
	        //Query table SO for the return side of the ticket in order to get the base document
	        while ( $ret = $so->next() ){
	        	$artrn = new ArTrn($db);
	            $doc = $so->get_DEL_DOC_NUM();

                //Check if any of the sales order have a sale side.
                if ( strcmp($so->get_ORD_TP_CD(), 'SAL') === 0 ){
                    $simpleRet = false;
                } 

	            $where = "WHERE IVC_CD = '" . $doc . "' AND TRN_TP_CD = 'SAL' ";
	            //Query SO to get the sale side of the return ticket with the base doc number
	            $result = $artrn->query($where);
	            if ( $result < 0 ){
	                echo "getSaleSide Query error: " . $so->getError();
	            }
	            if ( $data = $artrn->next() ){
                    //Writing sale side for that return
	                $str .= $this->writeReturn( $so->get_DEL_DOC_NUM(), $so->get_CUST_CD(), $so->get_ORD_TP_CD(), $artrn->get_AMT(), $so->get_FINAL_DT(), $so->get_STAT_CD(), array() );    
	            }
                else{
                    if ( strcmp( $row['DEL_DOC_NUM'], $doc ) === 0 ){
	                    $str .= $this->writeReturn( $so->get_DEL_DOC_NUM(), $so->get_CUST_CD(), $so->get_ORD_TP_CD(), $row['AMT'], $so->get_FINAL_DT(), $so->get_STAT_CD(), array() );    
                    }
                    else{
                        $where = "WHERE DEL_DOC_NUM = '" . $doc . "' ";

                        $result = $asfm->query( $where );

                        if ( $result < 0 ){
                            echo "Query Sale side error: " . $asfm->getError() . "\n";
                        }
                        if ( $asfm->next() ){
	                        $str .= $this->writeReturn( $so->get_DEL_DOC_NUM(), $so->get_CUST_CD(), $so->get_ORD_TP_CD(), $asfm->get_AMT(), $so->get_FINAL_DT(), $so->get_STAT_CD(), array() );    
                        }
                        else{
	                        $str .= $this->writeReturn( $so->get_DEL_DOC_NUM(), $so->get_CUST_CD(), $so->get_ORD_TP_CD(), " ", $so->get_FINAL_DT(), $so->get_STAT_CD(), array() );    
                        }
                    }
                }

	        }
            if ( $simpleRet ){
                return "";
            }
            else{
	            return $str;
            }
	    }
		//---------------------------------------------------------------------------
        //----------------------------- getCreditSide -------------------------------
        //---------------------------------------------------------------------------
        /**
        * Routine will get credit side for the ticket sale
        * @param $db - IDBT Connection Object
        *        $row - Ticket Data from ASFM
        *
        * @return 
        */
        public function getCreditSide( $db, $row ){
	        $so = new SO($db);
            $asfm = new ASPStoreForward( $db );
            $artrn = new ArTrn( $db );
	        $where = "WHERE SO.DEL_DOC_NUM  LIKE '" . substr($row['DEL_DOC_NUM'], 0, 11) . "%' and del_doc_num <> '" . $row['DEL_DOC_NUM'] . "' ";

	        $result = $so->query($where);
	        if ( $result < 0 ) echo "getSaleSide Query error: " . $so->getError(); 

            $str = "";
            while( $credit = $so->next() ){
                if  ( strcmp( $credit['ORD_TP_CD'], 'CRM') === 0 ){
                    $where = "WHERE DEL_DOC_NUM = '" . $so->get_DEL_DOC_NUM() . "' ";

                    $result = $asfm->query($where);

                    if ( $result < 0 ){
                        echo "Error Query getCreditSide: " . $so->getError() . "\n";
                    }
                    if( $asfm->next() ){ 
                        $str .= $this->writeReturn( $so->get_DEL_DOC_NUM(), $so->get_CUST_CD(), $so->get_ORD_TP_CD(), $asfm->get_AMT(), $so->get_FINAL_DT(), $so->get_STAT_CD(), array() );  
                    }
                    else{
                        $str .= $this->writeReturn( $so->get_DEL_DOC_NUM(), $so->get_CUST_CD(), $so->get_ORD_TP_CD(), $row['AMT'], $so->get_FINAL_DT(), $so->get_STAT_CD(), array() );  

                    }
                }
                else{
                    $str .= $this->writeReturn( $so->get_DEL_DOC_NUM(), $so->get_CUST_CD(), $so->get_ORD_TP_CD(), " ", $so->get_FINAL_DT(), $so->get_STAT_CD(), array() );  
                }
            }
            return $str;
        }
    }







?>
