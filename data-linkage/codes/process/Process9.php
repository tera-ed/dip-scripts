<?php

/**
 * Process 9
 *
 * Acquire 他媒体データ(other server data)
 *
 * @author Joedel Espinosa
 *
 */

class Process9 {
	private $logger, $db, $crm_db, $rds_db, $validate, $mail;
	private $isError = false;

	const databaseTable = "t_media_match_wait_evacuation";
	//const databaseTable = "t_media_match_wait";

	const databaseInsertTable1 = "t_media_mass";
	
	const databaseGetValue1 = "m_media_mass";
	const databaseGetValue2 = "m_corporation";
	const databaseGetValue3 = "m_corporation_bak";


	const WK_L_TABLE = "wk_t_lbc_crm_link";
	const WK_B_TABLE = 'wk_t_tmp_mda_result';
	const SHELL = 'load_wk_t_tmp_MDA_Result.sh';
	const LIMIT = 5000;
	const OFFSET_LIMIT = 10000;

	private $tmpCurrentIdArray = Array();

	function __construct($logger){
			$this->logger = $logger;
			$this->mail = new Mail();
			$this->validate = new Validation($this->logger);
	}

	/**
	 * Initial Process 9
	 *
	 */
	public function execProcess(){
		global $IMPORT_FILENAME, $procNo;
		$row = 0;
		$cntr = 0;
		try{
			// Initialize Database
			$this->db = new Database($this->logger);
			$this->crm_db = new CRMDatabase($this->logger);
			$this->rds_db = new RDSDatabase($this->logger);

			$path = getImportPath(true, '_9');
			$filename = $IMPORT_FILENAME[$procNo];
			$files = getMultipleCsv($filename, $path);

			$header = getDBField($this->db,self::WK_B_TABLE);
			$limit = self::LIMIT;
			foreach ($files as $fName) {
				$offsetCount = 0;
				$this->db->beginTransaction();
				// Acquire from:「./tmp/csv/Import/after/(yyyymmdd)/YYYYMMDDhhmmss_MDA_Result.csv」
				if(shellExec($this->logger, self::SHELL, $fName) === 0){
					$this->db->commit();
					while ($offsetCount <=self::OFFSET_LIMIT) {
						$offset = ($limit * $offsetCount);
						$csvList = $this->db->getLimitOffsetData("*", self::WK_B_TABLE, null, array(), $limit, $offset);
						if (count($csvList) === 0) {
							// 配列の値がすべて空の時の処理
							break;
						}
						$cntr += $this->processData($csvList, self::WK_B_TABLE.",LIMIT:$limit,OFFSET:$offset", $header);
						$offsetCount++;
					}
				} else {
					// shell失敗
					$this->db->rollback();
					$this->logger->error("Error File : " . $fName);
					throw new Exception("Process9 Failed to insert with " . self::SHELL . " to " . self::WK_B_TABLE);
				}
			}
		} catch (PDOException $e1){ // database error
			$this->logger->debug("Error found in database.");
			$this->disconnect(isset($cntr) && $cntr > 0);
			
			$this->mail->sendMail();
			throw $e1;
		} catch (Exception $e2){ // error
			// write down the error contents in the error file
			$this->logger->debug("Error found in process.");
			$this->logger->error($e2->getMessage());
			$this->disconnect();
			
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
		
		$this->disconnect();
	}
	
	private function disconnect($isRollback=false){
		if($this->db) {
			if($isRollback){
				$this->db->rollback();
			}
			// close database connection
			$this->db->disconnect();
		}
		if($this->crm_db) {
			if($isRollback){
				$this->crm_db->rollback();
			}
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
	 * Regarding the check contents, refer to sheet: 「他媒体データエラーチェック」
	 * @param $array List data From CSV
	 * @return boolean
	 */
	private function errorChecking($array, $row, $csv, $header){
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
	 * @param $list array Data from Csv
	 *
	 */
	private function processData($list, $csv, $header){
		global $MAX_COMMIT_SIZE;
		$result = null; $counter = 0;
		try {
			$media_code = $header[0];
			$office_id = $header[1];
			
			foreach ($list as $row => &$data){
				$result = null;
				if($this->errorChecking($data, $row, $csv, $header)){
					$condition = "media_code";
					$key = array($data[$media_code]);
					if(($counter % $MAX_COMMIT_SIZE) == 0){
						$this->db->beginTransaction();
						$this->crm_db->beginTransaction();
					}
					//Search for the item: 「他媒体コード」=「t_media_match_wait.media_code」 record
					$dbResult = $this->db->getDataCount(self::databaseTable,$condition."=?",$key);
					//If the search results are not 0件 (0 records)
					if($dbResult > 0){
						$isDataError = false;
						// Getting t_media_match_wait data to be moved.
						$getMediaData = $this->db->getData("*",self::databaseTable,$condition."=?",$key);
						$condition1 = "compe_media_code";
						//$key1 = array($getMediaData[0]["media_code"]);
						$key1 = array($getMediaData[0]["compe_media_code"]);
						$getCompMediaName = $this->rds_db->getData("compe_media_name",self::databaseGetValue1,$condition1."=?",$key1);
						$getMediaName = $this->rds_db->getData("media_name",self::databaseGetValue1,$condition1."=?",$key1);
						if(!$getCompMediaName || !$getMediaName){
							$this->logger->error($condition1." = ".$getMediaData[0]["compe_media_code"]. " No match Found in ".self::databaseGetValue1);
							throw new Exception("Process9 ".$condition1." = ".$getMediaData[0]["compe_media_code"]." No .match Found in ".self::databaseGetValue1);
						} 
						if(empty($this->tmpCurrentIdArray[$data[$office_id]])){
							// 顧客コード検索
							$getCorporationCode = $this->getCorporationCodeList($data[$office_id]);
						}else{
							$getCorporationCode = array(array("corporation_code" => $this->tmpCurrentIdArray[$data[$office_id]]));
						}
						if (sizeof($getCorporationCode) > 0) {
							// 顧客コードが存在する
							
							// office_idは配列ではなく値を渡すよう修正 2017/09/29 tanaka mod
							$finalArray = $this->mergeArray($getMediaName,$getCompMediaName,$data[$office_id]);
							$finalArray = $this->mergeArray($getMediaData,$finalArray,$data[$office_id]);
							$finalArray = $this->mergeArray($getCorporationCode,$finalArray,$data[$office_id]);
							$finalArray = $this->unsetKeys($finalArray);

							// insert Data
							$result = $this->crm_db->insertData(self::databaseInsertTable1, $finalArray[0]);
							if($result){
								// delete Data for the item: 「媒体コード」=「t_media_match_wait.media_code」
								$result = $this->db->deleteData(self::databaseTable, "media_code = ?", array($data[$media_code]));
								if($result){
									$this->logger->info("Data Successfully Moved ".$condition." = ".$data[$media_code]);
								}else{
									$this->logger->error("Failed to deleteData".self::databaseTable."media_code=".$data[$media_code]);
									throw new Exception("Process9 Failed to deleteData".self::databaseTaWble."media_code=".$data[$media_code]);
								}
							}else{
								$this->logger->error("Failed to insertData".self::databaseInsertTable1."media_code=".$data[$media_code]);
								throw new Exception("Process9 Failed to insertData".self::databaseInsertTable1."media_code=".$data[$media_code]);
							}
						}
					} else {
						$this->logger->info($condition." = ".$data[$media_code]. " No match Found in ".self::databaseTable);
					}

					$counter++;
					if(($counter % $MAX_COMMIT_SIZE) == 0 || ($row + 1) == sizeof($list)){
						$this->db->commit();
						$this->crm_db->commit();
					}
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
	 * Created Array for Checking Values
	 * @return Array
	 *
	 */
	private function fieldsChecking(){
		$fields = array(
			"0"=>"",			// media_code
			"1"=>"M,D,L:11",	// office_id
			"2"=>"M,C",			// result_flg
			"3"=>"",			// detail_lvl
			"4"=>""				// detail_content
			);
		return $fields;
	}

	/**
	 * Removing unnecessary from array
	 * @param $array Data Array from database
	 * @return Array
	 *
	 */
	private function unsetKeys($array){
		// 20160701 945 t_media_mass.post_end_date = t_media_match_wait.data_get_date
		if($array[0]['data_get_date'] != null && $array[0]['data_get_date'] != ''){
			$array[0]['post_end_date'] = $array[0]['data_get_date'];
		}else{
			// データ取得日がNULLの場合はバッチ処理日を入れる
			$array[0]['post_end_date'] = date("Y/m/d"); //$array[0]['post_start_date'];
		}
		// 20170501 登録先項目名称変換（t_media_match_wait → t_media_mass）
		$array[0]['free_item1'] = $array[0]['claim_bp_code'];		// 請求取引先CD
		$array[0]['free_item2'] = $array[0]['comp_no'];				// COMP No
		$array[0]['free_item3'] = $array[0]['recruit_emp_form'];	// 募集雇用形態
		$array[0]['free_item4'] = $array[0]['media_type_details'];	// 媒体種別詳細

		// 20180308
		$array[0]['address4'] = $array[0]['address3'];
		$array[0]['address3'] = $array[0]['address2'];
		$array[0]['address2'] = $array[0]['address1'];
		$array[0]['address1'] = $array[0]['addr_prefe'];
	
		$removeKeys = array('area_name',
			'data_get_date',
			'memo',
			//'corporation_name',
			// 'zip_code',
			'addr_prefe',
			// 'address1',
			// 'address2',
			// 'address3',
			// 'tel',
			'section',
			'corporation_emp_name',
			'listed_marked',
			'employee_number',
			'capital_amount',
			'year_sales',
			'dispatch_flag',
			'introduction_flag',
			'fax',
			'business_content',
			'create_date',
			'claim_bp_code',		// 請求取引先CD
			'comp_no',				// COMP No
			'recruit_emp_form',		// 募集雇用形態
			'media_type_details');	// 媒体種別詳細
		foreach($removeKeys as $key){
			unset($array[0][$key]);
		}
		$array[0]['space'] = $this->limitStrLen($array[0]['space'], 400);
		$array[0]['job_category'] = $this->limitStrLen($array[0]['job_category'], 400);

		$array = array_values($array);
		return $array;
	}

	/**
	 * Merging Two Arrays into one Array
	 * @param $array First Array
	 * @param $araay Second Array
	 * @param $string Third String
	 * @return $result Array
	 */
	private function mergeArray($array1, $array2, $office_id){
		$result = array();
		$correctCorp = array();
		// $array1 はoffice_idで検索した結果のcorporation_code
		try{
			$i=0;
			// LBCに紐づく顧客が2つ以上ある場合
			if(sizeof($array1) > 1){
				# 紐付先キャッシュを確認し、空であればループを実行
				if(empty($this->tmpCurrentIdArray[$office_id])){
					foreach($array1 as $key=>$val){
						// 「$val」が連想配列のため、「"corporation_code"」を抽出する 2017/09/29 tanaka mod
						$corp_code = (is_array($val) && isset($val["corporation_code"])) ? $val["corporation_code"] : "";
						// 同じLBCの顧客が分離された場合を考慮して、分離元顧客を検索
						$correctCorp = $this->rds_db->getData("seperate_code",self::WK_L_TABLE,"corporation_code = ? and office_id = ? and current_data_flag = '1' and delete_flag is null and seperate_code is not null",[$corp_code,$office_id]);
						// 分離元顧客が見つかればそちらを正しい顧客とする
						if(sizeof($correctCorp) > 0){
							// 「$correctCorp」が連想配列のため、「"seperate_code"」を抽出する 2017/09/29 tanaka mod
							$sep_code = (is_array($correctCorp[0]) && isset($correctCorp[0]["seperate_code"])) ? $correctCorp[0]["seperate_code"] : "";
							$this->logger->info("分離元顧客 : ".$sep_code." を ".$office_id." の他媒体の紐付け先とします。");
							// 取得した「"seperate_code"」を「"corporation_code"」に設定する
							$array1 = array(array("corporation_code" => $sep_code));
							$this->tmpCurrentIdArray[$office_id] = $array1[0]["corporation_code"];
							break;
						}else{
							// 「"seperate_code"」が取得できない場合、ログに記載する
							$this->logger->info("顧客コード : ".$corp_code." から 分離元顧客を見つけられませんでした。 次のコードを捜査します。");
						}
					}
					// 分離先顧客コードが見つからなかった場合は1番目の顧客コードを入れる
					// TODO 「1番目の顧客コード」が正しい紐付けとなるよう、顧客の取得順を調整する必要あり
					if(sizeof($correctCorp) == 0){
						$array1 = array_slice($array1 , 0, 1);
						# 分離先が見つからないオフィスIDをキャッシュする。
						$this->tmpCurrentIdArray[$office_id] = $array1[0]["corporation_code"];
						$this->logger->info("LBC : ".$office_id." に紐付く分離元顧客を特定できませんでした。");
					}
				}else {
					# キャッシュされた紐付先を設定
					$array1 = array(array("corporation_code" => $this->tmpCurrentIdArray[$office_id]));
				}
			} else {
				$result = $array2;
			}
			foreach($array1 as $key=>$val){
				// 20160308 sakai バッチテストのためにエラー回避
				if($i!=0){
					$this->logger->error("Failed to insertData create ".self::databaseGetValue2.":".$office_id);
					break;
				}
				$i++;
				$val2 = $array2[$key];
				$result[$key] = $val + $val2;
			}
		} catch (Exception $e){
			throw $e;
		}
		return $result;
	}

	/**
	 * register the first 250 characters only
	 * @param string $val
	 * @return string business_content
	 */
	function limitStrLen($val, $maxLen = 250){
		$result = $val;
		if($val != null && $val != ''){
			$result = mb_substr($val,0,$maxLen, "utf-8");
		}
		return $result;
	}

	/**
	 * lbc_crm_link delete insert
	 * @param array $corporationCodeList
	 * @return string office_id
	 */
	function lbcCrmLinkInsertData($corporationCodeList, $office_id){
		$key1 = "corporation_code";
		$key2 = "office_id";
		$condition = $key1."=? and ".$key2."=?";
		try {
			if (sizeof($corporationCodeList) > 0) {
				while($row = array_shift($corporationCodeList)){
					$corporation_code = $row['corporation_code'];
					$dataCount = $this->rds_db->getDataCount(self::WK_L_TABLE, $condition, array($corporation_code, $office_id));
					if($dataCount > 0 ){
						$result1 = $this->crm_db->deleteData(self::WK_L_TABLE, $condition, array($corporation_code, $office_id));
						if($result1){
							$this->logger->info("Data Successfully Moved ".$key1."=".$corporation_code.", ".$key2."=".$office_id);
						}else{
							throw new Exception("Process9 Failed to deleteData ".self::WK_L_TABLE." ".$key1."=".$corporation_code.", ".$key2."=".$office_id);
						}
					}
					$result2 = $this->crm_db->insertData(self::WK_L_TABLE, 
						array(
							"corporation_code" => $corporation_code,
							"office_id" =>  $office_id,
							"match_detail" =>  "Process9 作成",
							"current_data_flag" => "1",
							"delete_flag" => null
						)
					);
					if(!$result2){
						throw new Exception("Process9 Failed to insertTable ".self::WK_L_TABLE." ".$key1."=".$corporation_code.", ".$key2."=".$office_id);
					}
				}
			}
		} catch (Exception $e){
			$this->logger->error($e->getMessage());
			throw $e;
		}
	}
	
	/**
	 * get corporation_code
	 * @return string office_id
	 */
	function getCorporationCodeList($office_id){
		$condition1="corporation_code";
		$condition2 = "office_id";
		$corporationCodeList = array();
		
		$where = "current_data_flag = '1' AND delete_flag is null AND office_id = ?";
		// ①wk_t_lbc_crm_linkから取得
		$codeList1 = $this->rds_db->getData($condition1, self::WK_L_TABLE, $where, array($office_id));
		if($codeList1){
			$this->logger->debug("wk_t_lbc_crm_linkから取得 あり");
			while($row = array_shift($codeList1)){
				$code = $row[$condition1];
				$value = array($condition1 => $code);
				
				// ② wk_t_lbc_crm_link.corporation_codeからm_corporationを検索
				$codeList2 = $this->rds_db->getData($condition1,self::databaseGetValue2, $condition1." = ?", array($code));
				if(!$codeList2){
					// 存在しない場合、m_corporation_bakにから取得
					$mRecord = $this->rds_db->getData('*',self::databaseGetValue3, $condition1." = ?", array($code));
					if(!empty($mRecord)){
						$result = $this->insertUpdateCorporation($mRecord);
						if(!$result){
							$this->logger->error("Failed to insert [$condition1 : $code] to ". self::databaseGetValue2);
							throw new Exception("Process9 Failed to insert [$condition1 : $code] to ". self::databaseGetValue2);
						} else {
							$this->logger->info("Inserting row [$condition1: $code] to ". self::databaseGetValue2);
							$corporationCodeList = array_merge($corporationCodeList, array($value));
						}
					}else{
						// エラー
						$this->logger->error($condition1." = ".$code.", ".$condition2." = ".$office_id. " No match Found in ".self::databaseGetValue2);
						throw new Exception("Process9 ".$condition1." = ".$code.", ".$condition2." = ".$office_id. " No match Found in ".self::databaseGetValue2);
					}
				} else {
					$corporationCodeList = array_merge($corporationCodeList, array($value));
				}
			}
		} else {
			$this->logger->debug("wk_t_lbc_crm_linkから取得 なし");
			// 存在しない場合、顧客から取得
			$corporationCodeList = $this->rds_db->getData("corporation_code",self::databaseGetValue2,$condition2."=?", array($office_id));
			if(!$corporationCodeList){
				// 存在しない場合、m_corporation_bakにから取得
				$mRecord = $this->rds_db->getData('*',self::databaseGetValue3, $condition2." = ?", array($office_id));
				if(!empty($mRecord)){
					$code = $mRecord[0][$condition1];
					$value = array($condition1 => $code);
					
					$result = $this->insertUpdateCorporation($mRecord);
					if(!$result){
						$this->logger->error("Failed to insert [$condition1 : $code, $condition2 : $office_id] to ". self::databaseGetValue2);
						throw new Exception("Process9 Failed to insert [$condition1 : $code, $condition2 : $office_id] to ". self::databaseGetValue2);
					} else {
						$this->logger->info("Inserting row [$condition1 : $code, $condition2 : $office_id] to ". self::databaseGetValue2);
						$corporationCodeList = array_merge($corporationCodeList, array($value));
					}
				}else{
					// エラー
					$this->logger->error($condition2." = ".$office_id. " No match Found in ".self::databaseGetValue2);
					throw new Exception("Process9 ".$condition2." = ".$office_id. " No match Found in ".self::databaseGetValue2);
				}
			}
			// wk_t_lbc_crm_linkに削除・新規作成
			$this->lbcCrmLinkInsertData($corporationCodeList, $office_id);

		}
		return $corporationCodeList;
	}
	
		
	/**
	 * insert update m_corporation
	 * @return array mRecord 
	 */
	function insertUpdateCorporation($mRecord){
		// m_corporationに新規作成
		
		//prepare the update fields
		$tableList = emptyToNull($mRecord);
		
		unset($tableList[0]['create_date']);
		unset($tableList[0]['create_user_code']);
		unset($tableList[0]['update_date']);
		unset($tableList[0]['update_user_code']);
		unset($tableList[0]['delete_flag']);
		$newTableList = $tableList[0];	
		//$this->logger->debug(var_export($newTableList , true));
		//return $this->crm_db->insertUpdateData(self::databaseGetValue2, $newTableList, "corporation_code");
		
		$dataCount = $this->crm_db->getDataCount(self::databaseGetValue2, "corporation_code=?", array($newTableList["corporation_code"]));
		if($dataCount == 0 ){ // Data not found, insert
			$this->logger->debug("corporation_code = ".$newTableList["corporation_code"]);
			return $this->crm_db->insertData(self::databaseGetValue2, $newTableList);
		} else { // Data found, update
			return true;
		}
	}
}
?>
