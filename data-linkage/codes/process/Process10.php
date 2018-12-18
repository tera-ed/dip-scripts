<?php

/**
 * Process 10
 *
 * Acquire Disallowed Publish Data
 *
 * @author Joedel Espinosa
 *
 */

class Process10 {
	private $logger, $db, $crm_db, $rds_db, $validate, $mail;
	private $isError = false;

	const databaseTable = "m_corporation";

	const WK_B_TABLE = 'wk_t_tmp_kng_result';
	const SHELL = 'load_wk_t_tmp_KNG_Result.sh';
	const LIMIT = 5000;
	const OFFSET_LIMIT = 10000;

	function __construct($logger){
			$this->logger = $logger;
			$this->mail = new Mail();
			$this->validate = new Validation($this->logger);
	}


	/**
	 * Initial Process 10
	 *
	 */
	public function execProcess(){
		global $IMPORT_FILENAME, $procNo;
		$row = 0;
	  try{
			//initialize Database
			$this->db = new Database($this->logger);
			$this->crm_db = new CRMDatabase($this->logger);
			$this->rds_db = new RDSDatabase($this->logger);

		  	$path = getImportPath(true);
		  	$filename = $IMPORT_FILENAME[$procNo];
		  	$files = getMultipleCsv($filename,$path);

		  	$header = getDBField($this->db,self::WK_B_TABLE);
		  	$limit = self::LIMIT;

		  	foreach ($files as $fName) {
		  		$offsetCount = 0;
		  		$this->db->beginTransaction();
		  		//Acquire from: 「./tmp/csv/Import/after/(yyyymmdd)/YYYYMMDDhhmmss_KNG_Result.csv」
		  		if(shellExec($this->logger, self::SHELL, $fName) === 0){
		  			$this->db->commit();
		  			while ($offsetCount <=self::OFFSET_LIMIT) {
		  				$offset = ($limit * $offsetCount);
		  				$csvList = $this->db->getLimitOffsetData("*", self::WK_B_TABLE, null, array(), $limit, $offset);
		  				if (count($csvList) === 0) {
		  					// 配列の値がすべて空の時の処理
		  					break;
		  				}

		  				//初期化
		  				$m_corp=array();
		  				$m_corp_officeList=array();

						$kng_officeList = array_column($csvList, 'office_id');
						//$this->logger->debug(implode(",",$kng_officeList));

						$m_corp = $this->getData("office_id, post_ban_flag+0", "m_corporation", "office_id in (".implode(",",$kng_officeList).")");
						$m_corp_officeList = array_column($m_corp, 'post_ban_flag+0', 'office_id');

			 		   	$cntr = $this->processData($csvList, self::WK_B_TABLE.",LIMIT:$limit,OFFSET:$offset", $header, $m_corp_officeList);
		  				$offsetCount++;
		  			}
		  		} else {
					// shell失敗
					$this->db->rollback();
					$this->logger->error("Error File : " . $fName);
					throw new Exception("Process10 Failed to insert with " . self::SHELL . " to " . self::WK_B_TABLE);
				}
		  	}
		} catch (PDOException $e1){ // database error
			$this->logger->debug("Error found in database.");
			if($this->db) {
				if(isset($cntr)){
					$this->db->rollback();
				}
				// close database connection
				$this->db->disconnect();
			}
			if($this->crm_db) {
				if(isset($cntr)){
					$this->crm_db->rollback();
				}
				// close database connection
				$this->crm_db->disconnect();
			}
			if($this->rds_db) {
				// close database connection
				$this->rds_db->disconnect();
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
			if($this->crm_db) {
				// close database connection
				$this->crm_db->disconnect();
			}
			if($this->rds_db) {
				// close database connection
				$this->rds_db->disconnect();
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
		if($this->crm_db) {
			// close database connection
			$this->crm_db->disconnect();
		}
		if($this->rds_db) {
			// close database connection
			$this->rds_db->disconnect();
		}
	}

	/**
	 * Checking of Contents
	 * Regarding the check contents, refer to sheet: 「掲載禁止データエラーチェック」
	 * @param $array List data from CSV
	 * @return boolean
	 */
	private function errorChecking($array, $row,$csv, $header){
		$isValid = true;
		try {
			$checkArray = $this->fieldsChecking();
			$checkArray = $this->replaceTableKeys($checkArray,$header);
			$isValid = $this->validate->execute($array, $checkArray, $row, $csv, $header);
		} catch (Exception $e){
			$this->logger->debug("Failed Checking Contents of Array");
			throw $e;
		}
		return $isValid;
	}

	/**
	 * Processing of Data
	 * @param $list array data from Csv
	 *
	 */
	private function processData($list,$csv,$header,$m_corp_officeList){
		global $MAX_COMMIT_SIZE;
		$result = null; $counter = 0;
		try {
			$kng_in_keiflg = $header[2];
			$office_id = $header[3];
			foreach ($list as $row => &$data){
				if(($counter % $MAX_COMMIT_SIZE) == 0){
					$this->db->beginTransaction();
					$this->crm_db->beginTransaction();
				}
				$validRow = $this->errorChecking($data,$row,$csv,$header);
				if($validRow === true){
					$key = array($data[$office_id]);
					//Search for item: 「LBC」=「m_corporation.office_id」 record
					$dbResult = $this->rds_db->getDataCount(self::databaseTable,"office_id=?",$key);
					$this->logger->debug("Database Result for office_id = ". $data[$office_id] ." is ".$dbResult);
					// If the search results are not 0件 (0 records)
					if($dbResult > 0){
						$m_corp_officeList = $this->updateAction($data[$office_id], $data[$kng_in_keiflg], $m_corp_officeList);
					} else {
						$this->logger->error("office_id = ".$data[$office_id]." No match found in ".self::databaseTable);
						throw new Exception("Process10 office_id = ".$data[$office_id]." No match found in ".self::databaseTable);
					}
					$counter++;
				}
				if(($counter % $MAX_COMMIT_SIZE) == 0 || ($row+1) == sizeof($list)){
					$this->db->commit();
					$this->crm_db->commit();
				}

			}
		} catch (Exception $e){
			throw $e;
		}
		return $counter;
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
	 * @return Array
	 *
	 */
	private function fieldsChecking(){
		$fields = array(
			"0"=>"",			// kng_in_seq
			"1"=>"",			// kng_in_crpnam
			"2"=>"M,D,S:1",		// kng_in_keiflg
			"3"=>"M,D,L:11",	// office_id
			"4"=>"M,C",			// result_flg
			"5"=>"",			// detail_lvl
			"6"=>""				// detail_content
			);
		return $fields;
	}

	/**
	 * Search for those with 「office_id」 from the 「m_corporation」
	 * @return array
	 */
	function getData($fields,$table,$where){
		try{
			$sql  =' SELECT '.$fields.' FROM '.$table.' WHERE '.$where;
			$result = $this->rds_db->getDataSql($sql);

		}catch(Exception $e){
			throw $e;
		}
		return $result;
	}


	/**
	 * Search for those with 「kng_in_keiflg」 from the 「wk_t_tmp_kng_result」
	 * @return array
	 */
	function updateAction($officeId="", $postBanFlag="", $m_corp_officeList = array()){
		// 元々の値を取得
		$kng_in_keiflg = $m_corp_officeList[$officeId];
		if($kng_in_keiflg != $postBanFlag){
			if($postBanFlag == "0"){
				//When the item: 「掲載不可フラグ」 is "0"(掲載可),
				//update the 「post_ban_flag」 of the searched record to 「false」
				$updateFields = array('post_ban_flag' => false);
			} else {
				//When the item: 「掲載不可フラグ」 is "1"(掲載不可),
				//update the searched record: 「post_ban_flag」 with 「true」
				$updateFields = array('post_ban_flag' => true);
			}
			$condition = "office_id = ?";
			$params = array($officeId);
			//update data
			$result = $this->db->updateData(self::databaseTable, $updateFields,$condition, $params);
			$result2 = $this->crm_db->updateData(self::databaseTable, $updateFields,$condition, $params);
			
			if(!$result){
				$this->isError = true;
				$this->logger->error("Failed to update. [office_id = $officeId]");
				throw new Exception("Process10 Failed to update. [office_id = $officeId]");
			}else{
				$this->logger->debug("Updated [office_id = $officeId]"."[post_ban_flag = $postBanFlag]");
				// 更新した値をチェック配列に代入
				$m_corp_officeList[$officeId]=$postBanFlag;
			}
		}else{
			$this->logger->info("掲載禁止フラグが同一なので更新しない [office_id = $officeId]"."[post_ban_flag = $postBanFlag]");
		}
		return $m_corp_officeList;
	}
}
?>