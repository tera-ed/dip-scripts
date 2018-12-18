<?php

/**
 * Process8
 *
 * Acquire OBICデータ (OBIC data)
 *
 * @author Joedel Espinosa
 *
 */
class Process8 {
	private $logger, $db, $validate, $mail;
	private $isError = false;

	const OBIC_CONTRACT = 0;
	const OBIC_BILLING = 1;
	const databaseTable = "m_obic_application";


	const WK_B_TABLE1 = 'wk_t_tmp_kei_result';
	const WK_B_TABLE2 = 'wk_t_tmp_sei_result';

	const SHELL1 = 'load_wk_t_tmp_KEI_Result.sh';
	const SHELL2 = 'load_wk_t_tmp_SEI_Result.sh';

	const LIMIT = 5000;
	const OFFSET_LIMIT = 10000;

	function __construct($logger){
			$this->logger = $logger;
			$this->mail = new Mail();
			$this->validate = new Validation($this->logger);
	}


	/**
	 * Initial Process 8
	 *
	 */
	public function execProcess(){
		global $IMPORT_FILENAME, $procNo;
		$rowContract = 0; $rowBilling = 0;
		try{
			// initialize Database
			$this->db = new Database($this->logger);
			$path = getImportPath(true);
			$limit = self::LIMIT;

			$filename = $IMPORT_FILENAME[$procNo][0];
			$files = getMultipleCsv($filename, $path);
			$header = getDBField($this->db,self::WK_B_TABLE1);
			foreach ($files as &$fName) {
				$offsetCount = 0;
				$this->db->beginTransaction();
				//Acquire from: 「./tmp/csv/Import/after/(yyyymmdd)/YYYYMMDDhhmmss_OBC_KEI_Result.csv」
				if(shellExec($this->logger, self::SHELL1, $fName) === 0){
					$this->db->commit();
					while ($offsetCount <= self::OFFSET_LIMIT) {
						$offset = ($limit * $offsetCount);
						$csvList = $this->db->getLimitOffsetData("*", self::WK_B_TABLE1, null, array(), $limit, $offset);
						if (count($csvList) === 0) {
							// 配列の値がすべて空の時の処理
							break;
						}
						$cntr1 = $this->processData($csvList, self::WK_B_TABLE1.",LIMIT:$limit,OFFSET:$offset", $header, self::OBIC_CONTRACT);
						$offsetCount++;
					}
				} else {
					// shell失敗
					$this->db->rollback();
					$this->logger->error("Error File : " . $fName);
					throw new Exception("Process8 Failed to insert with " . self::SHELL1 . " to " . self::WK_B_TABLE1);
				}
			}

			$filename = $IMPORT_FILENAME[$procNo][1];
			$files = getMultipleCsv($filename, $path);
			$header = getDBField($this->db,self::WK_B_TABLE2);
			foreach ($files as &$fName) {
				$offsetCount = 0;
				$this->db->beginTransaction();
				//Acquire from: 「./tmp/csv/Import/after/(yyyymmdd)/YYYYMMDDhhmmss_OBC_SEI_Result.csv」
				if(shellExec($this->logger, self::SHELL2, $fName) === 0){
					$this->db->commit();
					while ($offsetCount <= self::OFFSET_LIMIT) {
						$offset = ($limit * $offsetCount);
						$csvList = $this->db->getLimitOffsetData("*", self::WK_B_TABLE2, null, array(), $limit, $offset);
						if (count($csvList) === 0) {
							// 配列の値がすべて空の時の処理
							break;
						}
						$cntr2 = $this->processData($csvList, self::WK_B_TABLE2.",LIMIT:$limit,OFFSET:$offset", $header, self::OBIC_BILLING);
						$offsetCount++;
					}
				} else {
					// shell失敗
					$this->db->rollback();
					$this->logger->error("Error File : " . $fName);
					throw new Exception("Process8 Failed to insert with " . self::SHELL2 . " to " . self::WK_B_TABLE2);
				}
			}
		} catch (PDOException $e1){ // database error
			$this->logger->debug("Error found in database.");
			if($this->db) {
				if(isset($cntr1) || isset($cntr2)){
					$this->db->rollback();
				}
				// close database connection
				$this->db->disconnect();
			}
			$this->mail->sendMail();
			throw $e1;
		} catch (Exception $e2){ // error
			// write down the error contents in the error file
			$this->logger->debug("Error found in process.");
			$this->logger->error($e2->getMessage());
			if($this->db) {
				// close database connection
				$this->db->disconnect();
			}
			// If there are no files:
			// Skip the process on and after the corresponding process number
			// and proceed to the next process number (ERR_CODE: 602)
			// For system error pause process
			if(602 != $e2->getCode()) {
				$this->mail->sendMail();
				throw $e2;
			}
		}
		if($this->isError){
			// send mail if there is error
			$this->mail->sendMail();
		}
		if($this->db) {
			// close database connection
			$this->db->disconnect();
		}
	}



	/**
	 *	Checking of Contents
	 *	Refer to sheet: 「OBICデータエラーチェック」
	 * @param $array List data from CSV
	 * @param $processCode int 0 Contract : 1 Billing
	 * @return boolean
	 *
	 **/
	private function errorChecking($array, $row,$csv, $header){
		$isValid = true;
		try {
			$checkArray = $this->fieldsChecking();
			$checkArray = $this->replaceTableKeys($checkArray,$header);
			$isValid = $this->validate->execute($array,$checkArray,$row,$csv, $header);
		} catch(Exception $e){
			$this->logger->debug("Failed Checking Contents Of Array");
			throw $e;
		}
		return $isValid;
	}

	/**
	 * Processing of Data
	 * @param $list array data from Csv
	 * @param $processCode int if 0 Contract : 1 Billing
	 */
	private function processData($list,$csv,$header, $processCode){
		global $MAX_COMMIT_SIZE;
		$resultContract = null; $resultBilling = null;
		$code = $header[0];
		$office_id = $header[1];
		if($processCode == SELF::OBIC_CONTRACT){
			try{
				$counter = 0;
				foreach ($list as $row => &$data){
					if(($counter % $MAX_COMMIT_SIZE) == 0){
						$this->db->beginTransaction();
					}
					$validRow = $this->errorChecking($data,$row,$csv,$header);
					if($validRow === true){
						$condition = "contract_code";
						$key = array($data[$code]);
						// Search for item:「契約取引先CD」=「m_obic_application.contract_code」 record
						$dbResult = $this->db->getDataCount(self::databaseTable,$condition."= ?", $key);
						$this->logger->debug("Database Result for ".$condition." = ".$data[$code]." is ".$dbResult);
						//If the search results are not 0件 (0 records)
						//Update the contents of the item: 「LBC」 with the 「contract_office_id」 of the searched record
						if($dbResult > 0){
							$dbcount = $this->db->getDataCount(self::databaseTable,"contract_code=? and contract_office_id=?", array($data[$code], $data[$office_id]));
							if($dbResult != $dbcount){
								$update = array("contract_office_id" => $data[$office_id]);
								$resultContract = $this->db->updateData(self::databaseTable, $update, $condition."= ?", $key);
								if(!$resultContract){
									$this->isError = true;
									$this->logger->error("Failed to update. [".$condition." = ".$data[$code]."]");
									throw new Exception("Process8 Failed to update. [".$condition." = ".$data[$code]."]");

								}else{
									$this->logger->info("Data Updated. ".$condition.": ".$data[$code]);
								}

							}else{
								$this->logger->info("contract_office_idが同じ値なので更新しない. ".$condition.": ".$data[$code]." contract_office_id: ".$data[$office_id]);
							}
						} else {
							$this->isError = true;
							// 2017/10/26 ログレベルを修正（ERROR→INFO）
							$this->logger->info($condition." = ".$data[$code]." Not found in ".self::databaseTable);
						}
						$counter++;

					}
					if(($counter % $MAX_COMMIT_SIZE) == 0 || ($row+1) == sizeof($list)){
						$this->db->commit();
					}
				}
			} catch ( Exception $e){
				throw $e;
			}
			return $counter;
		} else if ($processCode == SELF::OBIC_BILLING){
			try{
				$counter=0;
				foreach ($list as $row => &$data){
					if(($counter % $MAX_COMMIT_SIZE) == 0){
						$this->db->beginTransaction();
					}

					$validRow = $this->errorChecking($data,$row,$csv,$header);
					if($validRow === true){
						$condition = "billing_code";
						$key = array($data[$code]);
						//Search for item:「請求取引先CD」=「m_obic_application.billing_code」 record
						$dbResult = $this->db->getDataCount(self::databaseTable,$condition."= ?", $key);
						$this->logger->debug("Database Result for ".$condition." = ".$data[$code]." is ".$dbResult);
						//If the search results are not 0件 (0 records)
						//Update the content of the item: 「LBC」 with the 「billing_office_id」 of the searched record
						if($dbResult > 0){
							$dbcount = $this->db->getDataCount(self::databaseTable,"billing_code=? and billing_office_id=?", array($data[$code], $data[$office_id]));
							if($dbResult != $dbcount){
								$update = array("billing_office_id" => $data[$office_id]);
								$resultBilling = $this->db->updateData(self::databaseTable, $update, $condition."= ?", $key);
								if(!$resultBilling){
									$this->isError = true;
									$this->logger->error("Failed to update. [".$condition." = ".$data[$code]."]");
									throw new Exception("Process8 Failed to update. [".$condition." = ".$data[$code]."]");
								}else{
									$this->logger->info("Data Updated. ".$condition.": ".$data[$code]);
								}
							}else{
								$this->logger->info("billing_office_idが同じ値なので更新しない. ".$condition.": ".$data[$code]." billing_office_id: ".$data[$office_id]);
							}
						} else {
							$this->isError = true;
							// 2017/10/26 ログレベルを修正（ERROR→INFO）
							$this->logger->info($condition." = ".$data[$code]." Not found in ".self::databaseTable);
						}
						$counter++;
					}
					if(($counter % $MAX_COMMIT_SIZE) == 0 || ($row+1) == sizeof($list)){
						$this->db->commit();
					}

				}
			} catch ( Exception $e){
				throw $e;
			}
			return $counter;
		}

	}

	/**
	 * Replace array keys with the table columns (m_lbc)
	 * @param array $array - current array list
	 * @return array - array with the new keys
	 */
	private function replaceTableKeys($array,$header){
		return mapFields($header, $array, true);
	}

	/**
	 * Created array for Checking Values
	 * @return array
	 */
	private function fieldsChecking(){
		$fields = array(
			"0"=>"M,D,L:9",		// contract_code
			"1"=>"M,D,L:11",	// office_id
			"2"=>"M,C",			// result_flg
			"3"=>"",			// detail_lvl
			"4"=>""				// detail_content
			);
		return $fields;
	}
}
?>