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
	private $media_mass_array, $latest_post_media_array;
	private $isError = false;

	const T_TABLE1 = "t_media_match_wait_evacuation";
	//const T_TABLE1 = "t_media_match_wait";
	const T_TABLE2 = "t_media_mass";
	const T_TABLE3 = "t_latest_post_media";

	const M_TABLE1 = "m_media_mass";
	const M_TABLE2 = "m_corporation";
	const M_TABLE3 = "m_corporation_bak";
	const M_TABLE4 = "m_force_match_url";

	const WK_TABLE1 = "wk_t_lbc_crm_link";
	const WK_TABLE2 = 'wk_t_tmp_mda_result';
	const WK_TABLE3 = 'wk_t_tmp_mda_force_result';

	const SHELL1 = 'load_wk_t_tmp_MDA_Result.sh';
	const SHELL2 = 'load_wk_t_tmp_MDA_FORCE_Result.sh';

	const LIMIT = 5000;
	const OFFSET_LIMIT = 10000;

	private $tmpCurrentIdArray = Array();

	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
		$this->validate = new Validation($this->logger);
		$this->media_mass_array = array();
		$this->latest_post_media_array = array();
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
			$files1 = getMultipleCsv($IMPORT_FILENAME[$procNo][0], $path, false);
			$files2 = getMultipleCsv($IMPORT_FILENAME[$procNo][1], $path, false);
			if(empty($files1) && empty($files2)) {
				throw new Exception("File not found.", 602);
			}
			
			$this->getMasterDataList();
			
			$header1 = getDBField($this->db,self::WK_TABLE2);
			$header2 = getDBField($this->db,self::WK_TABLE3);
			$limit = self::LIMIT;
			if (!empty($files1)) {
				foreach ($files1 as $fName) {
					try{
						$offsetCount = 0;
						$this->db->beginTransaction();
						// Acquire from:「./tmp/csv/Import/after/(yyyymmdd)/YYYYMMDDhhmmss_MDA_Result.csv」
						if(shellExec($this->logger, self::SHELL1, $fName) === 0){
							$this->db->commit();
							while ($offsetCount <=self::OFFSET_LIMIT) {
								$offset = ($limit * $offsetCount);
								$csvList = $this->db->getLimitOffsetData("*", self::WK_TABLE2, null, array(), $limit, $offset);
								if (count($csvList) === 0) {
									// 配列の値がすべて空の時の処理
									break;
								}
								$cntr += $this->processData($csvList, self::WK_TABLE2.",LIMIT:$limit,OFFSET:$offset", $header1);
								$offsetCount++;
							}
						} else {
							// shell失敗
							$this->db->rollback();
							$this->logger->error("Error File : " . $fName);
							throw new Exception("Process9 Failed to insert with " . self::SHELL1 . " to " . self::WK_TABLE2);
						}
					} catch (Exception $e){
						$this->moveErrorTabaitaiCsv($fName);
						throw $e;
					}
				}
			}
			
			if(!empty($files2)) {
				foreach ($files2 as $fName) {
					try{
						$offsetCount = 0;
						$this->db->beginTransaction();
						// Acquire from:「./tmp/csv/Import/after/(yyyymmdd)/YYYYMMDDhhmmss_FORCE_Result.csv」
						if(shellExec($this->logger, self::SHELL2, $fName) === 0){
							$this->db->commit();
							while ($offsetCount <=self::OFFSET_LIMIT) {
								$offset = ($limit * $offsetCount);
								$csvList = $this->db->getLimitOffsetData("*", self::WK_TABLE3, null, array(), $limit, $offset);
								if (count($csvList) === 0) {
									// 配列の値がすべて空の時の処理
									break;
								}
								$cntr = $this->processForceData($csvList, self::WK_TABLE2.",LIMIT:$limit,OFFSET:$offset", $header2);
								$offsetCount++;
							}
						} else {
							// shell失敗
							$this->db->rollback();
							$this->logger->error("Error File : " . $fName);
							throw new Exception("Process9 Failed to insert with " . self::SHELL2 . " to " . self::WK_TABLE3);
						}
					} catch (Exception $e){
						$this->moveErrorTabaitaiCsv($fName);
						throw $e;
					}
				}
			}
			// t_latest_post_media更新・作成
			$cntr = $this->latestPostMediaInsertData();
		} catch (PDOException $e1){ // database error
			$this->logger->debug("Error found in database.");
			$this->disconnect(isset($cntr) && $cntr > 0);
			
			//$this->mail->sendMail();
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
				//$this->mail->sendMail();
				throw $e2;
			}
		}
		if($this->isError){
			// send mail if there is error
			//$this->mail->sendMail();
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
		global $MAX_COMMIT_SIZE, $FORCE_MATCH_COMPE_MEDIA_CODES;
		$result = null; $counter = 0;
		try {
			$media_code = $header[0];
			$office_id = $header[1];
			
			$condition1 = "media_code";
			$condition2 = "compe_media_code";
			foreach ($list as $row => &$data){
				$result = null;
				if($this->errorChecking($data, $row, $csv, $header)){
					$key = array($data[$media_code]);
					if(($counter % $MAX_COMMIT_SIZE) == 0){
						$this->db->beginTransaction();
						$this->crm_db->beginTransaction();
					}
					//Search for the item: 「他媒体コード」=「t_media_match_wait.media_code」 record
					$dbResult = $this->db->getDataCount(self::T_TABLE1,$condition1."=?",$key);
					//If the search results are not 0件 (0 records)
					if($dbResult > 0){
						$isDataError = false;
						// Getting t_media_match_wait data to be moved.
						$getMediaData = $this->db->getData("*",self::T_TABLE1,$condition1."=?",$key);
						$compe_media_code = $getMediaData[0][$condition2];
						$getMediaName = $this->getMMediaMassList($compe_media_code);
		 				if(!$getMediaName){
							$this->logger->error($condition2." = ".$compe_media_code. " No match Found in ".self::M_TABLE1);
							throw new Exception("Process9 ".$condition2." = ".$compe_media_code." No .match Found in ".self::M_TABLE1);
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
							$finalArray = $this->mergeArray($getMediaData,$getMediaName,$data[$office_id]);
							$finalArray = $this->mergeArray($getCorporationCode,$finalArray,$data[$office_id]);
							
							if(in_array($compe_media_code, $FORCE_MATCH_COMPE_MEDIA_CODES)){
								// compe_media_code が対応している場合のみm_force_match_url へ更新を行う
								//$this->logger->debug("m_force_match_url 更新あり [".$condition2."=".$compe_media_code."]");
								$this->setForceMatchUrl($finalArray);
							} else {
								//$this->logger->debug("m_force_match_url 更新なし [".$condition2."=".$compe_media_code."]");
							}
							$this->setMediaData($finalArray);
						} else {
							// エラー
							$this->logger->error($condition1." = ".$data[$media_code].", No Found in corporation_code");
							throw new Exception("Process9 ".$condition1." = ".$data[$media_code].", No Found in corporation_code");
						}
					} else {
						$this->logger->info($condition1." = ".$data[$media_code]. " No match Found in ".self::T_TABLE1);
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
		//$array[0]['address4'] = $array[0]['address3'];
		$array[0]['address3'] = $array[0]['address2'] . $array[0]['address3'];
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
			'media_type_details',	// 媒体種別詳細
			'main_code',
			'sub_code',
			'job_id'
		);
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
						$correctCorp = $this->rds_db->getData("seperate_code",self::WK_TABLE1,"corporation_code = ? and office_id = ? and current_data_flag = '1' and delete_flag is null and seperate_code is not null",[$corp_code,$office_id]);
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
					$this->logger->error("Failed to insertData create ".self::M_TABLE2.":".$office_id);
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
					$dataCount = $this->rds_db->getDataCount(self::WK_TABLE1, $condition, array($corporation_code, $office_id));
					if($dataCount > 0 ){
						$result1 = $this->crm_db->deleteData(self::WK_TABLE1, $condition, array($corporation_code, $office_id));
						if($result1){
							$this->logger->info("Data Successfully Moved ".$key1."=".$corporation_code.", ".$key2."=".$office_id);
						}else{
							throw new Exception("Process9 Failed to deleteData ".self::WK_TABLE1." ".$key1."=".$corporation_code.", ".$key2."=".$office_id);
						}
					}
					$result2 = $this->crm_db->insertData(self::WK_TABLE1, 
						array(
							"corporation_code" => $corporation_code,
							"office_id" =>  $office_id,
							"match_detail" =>  "Process9 作成",
							"current_data_flag" => "1",
							"delete_flag" => null
						)
					);
					if(!$result2){
						throw new Exception("Process9 Failed to insertTable ".self::WK_TABLE1." ".$key1."=".$corporation_code.", ".$key2."=".$office_id);
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
		$codeList1 = $this->rds_db->getData($condition1, self::WK_TABLE1, $where, array($office_id));
		if($codeList1){
			//$this->logger->debug("wk_t_lbc_crm_linkから取得 あり");
			while($row = array_shift($codeList1)){
				$code = $row[$condition1];
				$value = array($condition1 => $code);
				
				// ② wk_t_lbc_crm_link.corporation_codeからm_corporationを検索
				$codeList2 = $this->rds_db->getData($condition1,self::M_TABLE2, $condition1." = ?", array($code));
				if(!$codeList2){
					// 存在しない場合、m_corporation_bakにから取得
					$mRecord = $this->rds_db->getData('*',self::M_TABLE3, $condition1." = ?", array($code));
					if(!empty($mRecord)){
						$result = $this->insertCorporation($mRecord);
						if(!$result){
							$this->logger->error("Failed to insert [$condition1 : $code] to ". self::M_TABLE2);
							throw new Exception("Process9 Failed to insert [$condition1 : $code] to ". self::M_TABLE2);
						} else {
							$this->logger->info("Inserting row [$condition1: $code] to ". self::M_TABLE2);
							$corporationCodeList = array_merge($corporationCodeList, array($value));
						}
					}else{
						// エラー
						$this->logger->error($condition1." = ".$code.", ".$condition2." = ".$office_id. " No match Found in ".self::M_TABLE2);
						throw new Exception("Process9 ".$condition1." = ".$code.", ".$condition2." = ".$office_id. " No match Found in ".self::M_TABLE2);
					}
				} else {
					$corporationCodeList = array_merge($corporationCodeList, array($value));
				}
			}
		} else {
			//$this->logger->debug("wk_t_lbc_crm_linkから取得 なし");
			// 存在しない場合、顧客から取得
			$corporationCodeList = $this->rds_db->getData("corporation_code",self::M_TABLE2,$condition2."=?", array($office_id));
			if(!$corporationCodeList){
				// 存在しない場合、m_corporation_bakにから取得
				$mRecord = $this->rds_db->getData('*',self::M_TABLE3, $condition2." = ?", array($office_id));
				if(!empty($mRecord)){
					$code = $mRecord[0][$condition1];
					$value = array($condition1 => $code);
					
					$result = $this->insertCorporation($mRecord);
					if(!$result){
						$this->logger->error("Failed to insert [$condition1 : $code, $condition2 : $office_id] to ". self::M_TABLE2);
						throw new Exception("Process9 Failed to insert [$condition1 : $code, $condition2 : $office_id] to ". self::M_TABLE2);
					} else {
						$this->logger->info("Inserting row [$condition1 : $code, $condition2 : $office_id] to ". self::M_TABLE2);
						$corporationCodeList = array_merge($corporationCodeList, array($value));
					}
				}else{
					// エラー
					$this->logger->error($condition2." = ".$office_id. " No match Found in ".self::M_TABLE2);
					throw new Exception("Process9 ".$condition2." = ".$office_id. " No match Found in ".self::M_TABLE2);
				}
			}
			// wk_t_lbc_crm_linkに削除・新規作成
			$this->lbcCrmLinkInsertData($corporationCodeList, $office_id);
		}
		return $corporationCodeList;
	}

	/**
	 * update m_corporation
	 * @return array mRecord 
	 */
	function insertCorporation($mRecord){
		// m_corporationに新規作成
		
		//prepare the update fields
		$tableList = emptyToNull($mRecord);
		
		unset($tableList[0]['create_date']);
		unset($tableList[0]['create_user_code']);
		unset($tableList[0]['update_date']);
		unset($tableList[0]['update_user_code']);
		unset($tableList[0]['delete_flag']);
		$newTableList = $tableList[0];	
		$dataCount = $this->crm_db->getDataCount(self::M_TABLE2, "corporation_code=?", array($newTableList["corporation_code"]));
		if($dataCount == 0 ){ // Data not found, insert
			$this->logger->debug("corporation_code = ".$newTableList["corporation_code"]);
			return $this->crm_db->insertData(self::M_TABLE2, $newTableList);
		} else { // Data found, update
			return true;
		}
	}

	/**
	 * update m_force_match_url
	 * @return array record 
	 */
	function setForceMatchUrl($record){
		$corporation_code = $record[0]['corporation_code'];
		$main_code = $record[0]['main_code'];
		$sub_code = $record[0]['sub_code'];
		$job_id = $record[0]['job_id'];
		
		if (empty($corporation_code)) {
			$corporation_code = NULL;
		}
		$condition = "main_code=? and sub_code=?";
		$params= array($main_code, $sub_code);
		if (empty($job_id) || $job_id == NULL) {
			$condition = $condition." and job_id IS NULL";
		} else {
			$params= array_merge($params,array($job_id));
			$condition = $condition." and job_id=? ";
		}
		$dataCount1 = $this->db->getDataCount(self::M_TABLE4, $condition, $params);
		if($dataCount1 <= 0 ){ // Data not found, insert
			$this->logger->error("Record not found corporation_code: $corporation_code, main_code: $main_code, sub_code: $sub_code, job_id: $job_id to ".self::M_TABLE4.".");
			throw new Exception("Process9 Record not found corporation_code: $corporation_code, main_code: $main_code, sub_code: $sub_code, job_id: $job_id to ".self::M_TABLE4.".");
		} else { 
			$dataCount2 = $this->db->getDataCount(self::M_TABLE4, $condition." and corporation_code is null", $params);
			if($dataCount2 > 0 ){ // Data found, update
				$result = $this->db->updateData(self::M_TABLE4, array("corporation_code"=>$corporation_code), $condition, $params);
				if(!$result){
					//$this->logger->error("Failed to update corporation_code of row with corporation_code: $corporation_code, main_code: $main_code, sub_code: $sub_code, job_id: $job_id to ". self::M_TABLE4);
					throw new Exception("Process9 Failed to update corporation_code of row with corporation_code: $corporation_code, main_code: $main_code, sub_code: $sub_code, job_id: $job_id to ". self::M_TABLE4);
				} else {
					$this->logger->info("Data found. Updating corporation_code of row with corporation_code: $corporation_code, main_code: $main_code, sub_code: $sub_code, job_id: $job_id to ". self::M_TABLE4);
				}
			} else {
				$this->logger->debug("Already update main_code: $main_code, sub_code: $sub_code, job_id: $job_id to ".self::M_TABLE4.".");
			}
		}
		return true;
	}

	/**
	 * Processing of Force Data
	 * @param $list array Data from Csv
	 *
	 */
	private function processForceData($list, $csv, $header){
		global $MAX_COMMIT_SIZE;
		$result = null; $counter = 0;
		try {
			$condition1 = $header[0];
			$condition2 = $header[1];
			$condition3 = "compe_media_code";
			
			foreach ($list as $row => &$data){
				$media_code = $data[$condition1];
				$corporation_code = $data[$condition2];
				$key1 = array($media_code);
				$key2 = array($corporation_code);
				$result = null;
				if(($counter % $MAX_COMMIT_SIZE) == 0){
					$this->db->beginTransaction();
					$this->crm_db->beginTransaction();
				}
				
				//Search for the item: 「他媒体コード」=「t_media_match_wait.media_code」 record
				$dbResult = $this->db->getDataCount(self::T_TABLE1,$condition1."=?",$key1);
				//If the search results are not 0件 (0 records)
				if($dbResult > 0){
					$isDataError = false;
					// Getting t_media_match_wait data to be moved.
					$getMediaData = $this->db->getData("*",self::T_TABLE1,$condition1."=?",$key1);
					$compe_media_code = $getMediaData[0][$condition3];
					
					$getMediaMass = $this->getMMediaMassList($compe_media_code);
					$codeList2 = $this->rds_db->getData($condition2,self::M_TABLE2, $condition2." = ?", $key2);
					if(!$codeList2){
						// 存在しない場合、m_corporation_bakにから取得
						$mRecord = $this->rds_db->getData('*',self::M_TABLE3, $condition2." = ?", $key2);
						if(!empty($mRecord)){
							$result = $this->insertCorporation($mRecord);
							if(!$result){
								$this->logger->error("Failed to insert [$condition2 : $corporation_code] to ". self::M_TABLE2);
								throw new Exception("Process9 Failed to insert [$condition2 : $corporation_code] to ". self::M_TABLE2);
							} else {
								$this->logger->info("Inserting row [$condition2: $corporation_code] to ". self::M_TABLE2);
							}
						}else{
							// エラー
							$this->logger->error($condition2." = ".$corporation_code." No match Found in ".self::M_TABLE2);
							throw new Exception("Process9 ".$condition2." = ".$corporation_code." No match Found in ".self::M_TABLE2);
						}
					}
					
					$finalArray = array_merge($getMediaData[0], $getMediaMass[0]);
					$finalArray = array_merge(array("corporation_code" => $corporation_code), $finalArray);
					$this->setMediaData(array($finalArray));
				} else {
					$this->logger->info($condition1." = ".$data[$condition1]. " No match Found in ".self::T_TABLE1);
				}
				$counter++;
				if(($counter % $MAX_COMMIT_SIZE) == 0 || ($row + 1) == sizeof($list)){
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
	 * insert media data
	 * @return array record 
	 */
	function setMediaData($finalArray){
		$record = $this->unsetKeys($finalArray);
		//$this->logger->debug(var_export($record , true));
		
		$condition = "media_code";
		$media_code = $record[0][$condition];
		$dataCount = $this->crm_db->getDataCount(self::T_TABLE2, $condition." = ?", array($media_code));
		if($dataCount <= 0 ){
			// insert Data
			$result = $this->crm_db->insertData(self::T_TABLE2, $record[0]);
			if($result){
				$this->logger->info("Inserting row [$condition: $media_code] to ". self::T_TABLE2);
				// delete Data for the item: 「媒体コード」=「t_media_match_wait.media_code」
				$result = $this->db->deleteData(self::T_TABLE1, $condition." = ?", array($media_code));
				if($result){
					$this->logger->info("Data Successfully Moved ".$condition." = ".$media_code);
				}else{
					$this->logger->error("Failed to deleteData".self::T_TABLE1.$condition." = ".$media_code);
					throw new Exception("Process9 Failed to deleteData".self::databaseTaWble." ".$condition." = ".$media_code);
				}
			}else{
				$this->logger->error("Failed to insertData".self::T_TABLE2." ".$condition." = ".$media_code);
				throw new Exception("Process9 Failed to insertData".self::T_TABLE2." ".$condition." = ".$media_code);
			}
		} else {
			$this->logger->error("Database Result for ".$condition." = ".$media_code ." is ".self::T_TABLE2);
			throw new Exception("Process9 Database Result for ".$condition." = ".$media_code ." is ".self::T_TABLE2);
		}
		$compe_media_code = $finalArray[0]["compe_media_code"];
		$corporation_code = $finalArray[0]["corporation_code"];
		$last_start_date = $finalArray[0]["data_get_date"];
		if (isset($this->latest_post_media_array[$compe_media_code][$corporation_code])){
			$check_last_start_date = $this->latest_post_media_array[$compe_media_code][$corporation_code];
			if($last_start_date != null && $check_last_start_date != null && strtotime($last_start_date) < strtotime($check_last_start_date)) {
				$last_start_date = $check_last_start_date;
			}
		}
		$this->latest_post_media_array[$corporation_code][$compe_media_code] = $last_start_date;
		return true;
	}

	/**
	 * エラーファイルを移動
	 * @param ファイルパス $file_path
	*/
	function moveErrorTabaitaiCsv($file_path){
		// ディレクトリ名
		$errorDirname = dirname($file_path).'/error';
		createDir($errorDirname);
		$res = shell_exec('mv -f '.escapeshellarg($file_path).' '.escapeshellarg($errorDirname));
		$this->logger->debug('moved '.$file_path.' to '.$errorDirname);
	}

	/**
	 * Returns 他媒体マスター
	 * @param unknown_type $val
	 */
	function getMMediaMassList($compe_media_code){
		$result = array();
		if (array_key_exists($compe_media_code, $this->media_mass_array)){
			$result = array($this->media_mass_array[$compe_media_code]);
		}
		return $result;
	}

	function getMasterDataList(){
		try{
			// 他媒体マスター
			$array = $this->rds_db->getData("compe_media_code,compe_media_name, media_name", self::M_TABLE1, null, array());
			foreach ($array as $row) {
				$this->media_mass_array[$row['compe_media_code']] = array(
					"compe_media_name"=>$row["compe_media_name"],
					"media_name"=>$row["media_name"]
				);
			}
		}catch(Exception $e){
			throw $e;
		}
	}
	
	/**
	* 媒体最新出稿情報へlast_start_dateが最大のもの作成する
	*/
	function latestPostMediaInsertData(){
		global $MAX_COMMIT_SIZE;
		$key1 = "compe_media_code";
		$key2 = "corporation_code";
		$key3 = "last_start_date";
		$condition = $key1."=? and ".$key2."=?";
		$counter = 0;
		try{
			foreach ($this->latest_post_media_array as $row1 => &$data1){
				$corporation_code = $row1;
				foreach ($data1 as $row2 => &$data2){
					$compe_media_code = $row2;
					$last_start_date    = $data2;
					$insertFields = array(
						$key1 => $compe_media_code,
						$key2 => $corporation_code,
						$key3 => $last_start_date
					);
					//$this->logger->debug(var_export($insertFields , true));
					
					if(($counter % $MAX_COMMIT_SIZE) == 0){
						$this->crm_db->beginTransaction();
					}
					$dataCount = $this->crm_db->getDataCount(self::T_TABLE3, $condition, array($compe_media_code, $corporation_code));
					if($dataCount > 0){
						$record = $this->crm_db->getData($key3,self::T_TABLE3,$condition, array($compe_media_code, $corporation_code));
						if(!$record){
							$this->logger->error($condition2." = ".$compe_media_code. " No match Found in ".self::M_TABLE1);
							throw new Exception("Process9 ".$key1." = ".$compe_media_code.",".$key2." = ".$corporation_code." No .match Found in ".self::T_TABLE3);
						}
						$check_last_start_date = $record[0][$key3];
						if($last_start_date != null && $check_last_start_date != null && strtotime($last_start_date) > strtotime($check_last_start_date)) {
							// 削除・作成
							$result1 = $this->crm_db->deleteData(self::T_TABLE3, $condition, array($compe_media_code, $corporation_code));
							if($result1){
								$this->logger->info("Data Successfully Moved ".$key1." = ".$compe_media_code.",".$key2." = ".$corporation_code);
							}else{
								throw new Exception("Process9 Failed to deleteData ".self::T_TABLE3." ".$key1." = ".$compe_media_code.",".$key2." = ".$corporation_code);
							}
							$result2 = $this->crm_db->insertData(self::T_TABLE3, $insertFields);
							if(!$result2){
								$this->logger->error("Failed to insertData".self::T_TABLE3." ".$key1." = ".$compe_media_code.",".$key2." = ".$corporation_code);
								throw new Exception("Process9 Failed to insertData".self::T_TABLE3." ".$key1." = ".$compe_media_code.",".$key2." = ".$corporation_code);
							}
						} else {
							$this->logger->debug("最終更新日付($check_last_start_date)より前のため更新されません。[".$key1." = ".$compe_media_code.",".$key2." = ".$corporation_code.",".$key3." = ".$last_start_date."]");
						}
					} else{
						// 新規
						$result1 = $this->crm_db->insertData(self::T_TABLE3, $insertFields);
						if(!$result1){
							$this->logger->error("Failed to insertData".self::T_TABLE3." ".$key1." = ".$compe_media_code.",".$key2." = ".$corporation_code);
							throw new Exception("Process9 Failed to insertData".self::T_TABLE3." ".$key1." = ".$compe_media_code.",".$key2." = ".$corporation_code);
						}
					}
					
					$counter++;
					if(($counter % $MAX_COMMIT_SIZE) == 0 || ($row2 + 1) == sizeof($data1)){
						$this->crm_db->commit();
					}
				}
			}
		}catch(Exception $e){
			throw $e;
		}
		
		return $counter;
	}
}
?>
