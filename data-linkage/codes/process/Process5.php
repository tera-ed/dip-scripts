<?php
/**
 * Process5 Class
 *
 * Acquire 更新_新規データ(update_new data)（m_corporation）
 *
 * @author Maricris S. Cuerdo
 *
 */
class Process5{
	private $logger, $db, $crm_db, $rds_db, $mail, $validate;
	private $isError = false;

	const TABLE_1 = 'm_corporation';
	const LOCK_TABLE_1 = 't_lock_lbc_link';

	const KEY_1 = 'office_id';
	const KEY_2 = 'corporation_code';
	const SEQ = 'M_CORPORATION_CODE';


	const WK_B_TABLE = 'wk_t_tmp_quarter_lbc_data';
	const SHELL = 'load_wk_t_tmp_Quarter_LBC_DATA.sh';
	const LIMIT = 5000;
	const OFFSET_LIMIT = 10000;

	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
		$this->validate = new Validation($this->logger);
	}

	/**
	 * Initial Process 5
	 */
	public function execProcess() {
		$this->logger->debug(ini_get("memory_limit")."\n");
		global $IMPORT_FILENAME, $procNo;
		$currentRowSize = 0;
		try{
			//initialize Database
			$this->db = new Database($this->logger);
			$this->crm_db = new CRMDatabase($this->logger);
			$this->rds_db = new RDSDatabase($this->logger);

			$path = getImportPath(true);
			$filename = $IMPORT_FILENAME[$procNo];
			// $path(import_after)内の名称に$filenameを含むファイル群取得
			$files = getMultipleCsv($filename, $path);
			$header = getDBField($this->db,self::WK_B_TABLE);

			$limit = self::LIMIT;
			foreach ($files as $fName) {
				$offsetCount = 0;
				$this->db->beginTransaction();
				// Acquire from: 「./tmp/csv/Import/after/(yyyymmdd)/YYYYMMDDhhmmss_LBC_DATA_xx.csv」
				if(shellExec($this->logger, self::SHELL, $fName) === 0){
					$this->db->commit();
					while ($offsetCount <= self::OFFSET_LIMIT) {
						$offset = ($limit * $offsetCount);
						$csvList = $this->db->getLimitOffsetData("*", self::WK_B_TABLE, null, array(), $limit, $offset);
						if (count($csvList) === 0) {
							// 配列の値がすべて空の時の処理
							break;
						}
						$cntr = $this->processData($csvList, self::WK_B_TABLE.",LIMIT:$limit,OFFSET:$offset", $header);
						$offsetCount++;
					}
				} else {
					// shell失敗
					$this->db->rollback();
					$this->logger->error("Error File : " . $fName);
					throw new Exception("Process5 Failed to insert with " . self::SHELL . " to " . self::WK_B_TABLE);
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
	 * Check contents
	 * Refer to sheet: 「更新_新規データエラーチェック」
	 * @param $array - array values to check
	 * @return boolean
	 */
	private function checkContents($array, $row, $csv, $header){
		$isValid = false;
		try {
			$validArray = $this->createArrayChecking();
			$validArray = $this->replaceTable1Keys($validArray);
			$isValid = $this->validate->execute($array, $validArray, $row, $csv, $header);
			unset($validArray);
		} catch(Exception $e){
			$this->logger->debug("Failed checking contents of array");
			throw $e;
		}
		return $isValid;
	}

	/**
	 * Begin transaction
	 * Insert new record if m_lbc.office_id not found
	 * Update record if m_lbc.office_id found
	 *
	 * @param unknown_type $data
	 */
	private function processData($dataInsert, $csv, $header){
		global $MAX_COMMIT_SIZE;
		$cntr = 0; $result = true; $nextId = 0;
		try{
			$officeIdHeader = $header[0]; // office_id
			foreach ($dataInsert as $row => &$data){
				if(($cntr % $MAX_COMMIT_SIZE) == 0){
					$this->db->beginTransaction();
					$this->crm_db->beginTransaction();
				}
				$tableData = emptyToNull($data);
				$newTableList = $this->replaceTable1Keys($tableData);
				//check if row contents are valid
				$validRow = $this->checkContents($newTableList, $row, $csv, $header);
				if($validRow === true){
					$key = array($tableData[$officeIdHeader]); //office_id
					// Search item [office_id] from 「m_corporation.office_id」
					//$this->crm_db->setMCorporationByOfficeId($tableData[$officeIdHeader], $db);
					$resultCount = $this->crm_db->getDataCount(self::TABLE_1, self::KEY_1."=?", $key);
					// ロック顧客かどうか office_id で確認 20161004lock_add
					$lockCount = $this->crm_db->getDataCount(self::LOCK_TABLE_1." lo inner join ".self::TABLE_1." mc on lo.corporation_code = mc.corporation_code ",
					 "mc.".self::KEY_1."=? and lo.lock_status = 1 and lo.delete_flag = false", $key);
					//prepare the data
					$table2List = $this->insertDefaultValue2($tableData);
					$newTable2List = $this->replaceTable2Keys($table2List);
					// まだ存在しない場合は新規登録
					if($resultCount <= 0) { // insert new record
						// When inserting, newly increment the 「m_corporation.corporation_code」, then set corporation_code
						// "C"+8 digits
						$nextId = $this->crm_db->getNextVal(self::SEQ);
						$newTable2List[self::KEY_2] = $nextId;
						$result1 = $this->crm_db->insertData(self::TABLE_1, $newTable2List);
						$result2 = $this->db->insertData(self::TABLE_1, $newTable2List);
						if(!$result1 || !$result2){
							$this->logger->error("Failed to register [$officeIdHeader: $tableData[$officeIdHeader]] to ". self::TABLE_1);
							# 2016/09/08 四半期処理のために処理中断をスキップに
							#throw new Exception("Process5 Failed to register [$officeIdHeader: $tableData[$officeIdHeader]] to ". self::TABLE_1);
						} else {
							$this->logger->info("Inserting row [$officeIdHeader: $tableData[$officeIdHeader]] to ". self::TABLE_1);
						}

					}else if($resultCount > 0 && $lockCount > 0){// 顧客が存在して、かつロックされている場合は処理スキップ 20161004lock_add
						$this->logger->info("ロックされている顧客のため顧客テーブルへの更新をスキップします。 [$officeIdHeader: $tableData[$officeIdHeader]] in ".self::LOCK_TABLE_1.".");
					}else{
						// office_id が同じで、 全角半角除去した住所全文[addressall]が一致しない場合は、緯度経度を null にする
						$addressall = $newTable2List['addressall'];
						$changeAddressFlag = $this->rds_db->getDataCount(self::TABLE_1, self::KEY_1."=?"." and replace(replace(addressall,'　',''),' ','') != replace(replace('".$addressall."','　',''),' ','') " , $key );
						if($changeAddressFlag > 0){
							$this->logger->info("住所が一致しなかったため緯度経度を null に更新します. ".self::KEY_1." = ".$key[0]);
							// latitude と longitude の更新カラムを付け足し
							$table3List = $this->insertDefaultValue3($table2List);
							$newTable2List = $this->replaceTable3Keys($table3List);
						}else {
							$this->logger->info("住所が一致したため緯度経度を更新しません.".self::KEY_1." = ".$key[0]);
						}

						// update fields already updated on $newTableList
						$result1 = $this->crm_db->updateData(self::TABLE_1, $newTable2List, self::KEY_1."=?", $key);// key = office_id
						$result2 = $this->db->updateData(self::TABLE_1, $newTable2List, self::KEY_1."=?", $key);
						if(!$result){
							$this->logger->info("Failed to update [$officeIdHeader: $tableData[$officeIdHeader]] to ". self::TABLE_1);
							# 2016/09/08 四半期処理のために処理中断をスキップに
							#throw new Exception("Process5 Failed to update [$officeIdHeader: $tableData[$officeIdHeader]] to ". self::TABLE_1);
						} else {
							$this->logger->info("Data found. Updating row [$officeIdHeader: $tableData[$officeIdHeader]] to ". self::TABLE_1);
						}
					}
					if(!$result){
						$this->isError = true;
					}
					$cntr++;
				}
				else{ // 20160908 validation_check でエラーあった場合はログ表示して該当レコードスキップ （バリデーションの内容はValidation.phpの中でログ表示）
					$this->logger->error("validation check error [$officeIdHeader: $tableData[$officeIdHeader]] ");
					$cntr++;
				}
				if(($cntr % $MAX_COMMIT_SIZE) == 0 || ($row+1) == sizeof($dataInsert)){
					$this->db->commit();
					$this->crm_db->commit();
				}
			}
		} catch( Exception $e) {
			throw $e;
		}
		return $cntr;
	}

	/**
	 * Replace array keys with the table 1 columns (m_lbc)
	 * @param array $array - current array list
	 * @return array - array with the new keys
	 */
	private function replaceTable1Keys($array){
		$fields = array(
			"0"=>"office_id",
			"1"=>"head_office_id",
			"2"=>"top_head_office_id",
			"3"=>"top_affiliated_office_id1",
			"4"=>"top_affiliated_office_id2",
			"5"=>"top_affiliated_office_id3",
			"6"=>"top_affiliated_office_id4",
			"7"=>"top_affiliated_office_id5",
			"8"=>"top_affiliated_office_id6",
			"9"=>"top_affiliated_office_id7",
			"10"=>"top_affiliated_office_id8",
			"11"=>"top_affiliated_office_id9",
			"12"=>"top_affiliated_office_id10",
			"13"=>"affiliated_office_id1",
			"14"=>"affiliated_office_id2",
			"15"=>"affiliated_office_id3",
			"16"=>"affiliated_office_id4",
			"17"=>"affiliated_office_id5",
			"18"=>"affiliated_office_id6",
			"19"=>"affiliated_office_id7",
			"20"=>"affiliated_office_id8",
			"21"=>"affiliated_office_id9",
			"22"=>"affiliated_office_id10",
			"23"=>"relation_flag1",
			"24"=>"relation_flag2",
			"25"=>"relation_flag3",
			"26"=>"relation_flag4",
			"27"=>"relation_flag5",
			"28"=>"relation_flag6",
			"29"=>"relation_flag7",
			"30"=>"relation_flag8",
			"31"=>"relation_flag9",
			"32"=>"relation_flag10",
			"33"=>"relation_name1",
			"34"=>"relation_name2",
			"35"=>"relation_name3",
			"36"=>"relation_name4",
			"37"=>"relation_name5",
			"38"=>"relation_name6",
			"39"=>"relation_name7",
			"40"=>"relation_name8",
			"41"=>"relation_name9",
			"42"=>"relation_name10",
			"43"=>"listed_flag",
			"44"=>"listed_name",
			"45"=>"sec_code",
			"46"=>"yuho_number",
			"47"=>"company_stat",
			"48"=>"company_stat_name",
			"49"=>"office_stat",
			"50"=>"office_stat_name",
			"51"=>"move_office_id",
			"52"=>"tousan_date",
			"53"=>"company_vitality",
			"54"=>"company_name",
			"55"=>"company_name_kana",
			"56"=>"office_name",
			"57"=>"company_zip",
			"58"=>"company_pref_id",
			"59"=>"company_city_id",
			"60"=>"company_addr1",
			"61"=>"company_addr2",
			"62"=>"company_addr3",
			"63"=>"company_addr4",
			"64"=>"company_addr5",
			"65"=>"company_addr6",
			"66"=>"company_tel",
			"67"=>"company_fax",
			"68"=>"office_count",
			"69"=>"capital",
			"70"=>"representative_title",
			"71"=>"representative",
			"72"=>"representative_kana",
			"73"=>"industry_code1",
			"74"=>"industry_name1",
			"75"=>"industry_code2",
			"76"=>"industry_name2",
			"77"=>"industry_code3",
			"78"=>"industry_name3",
			"79"=>"license",
			"80"=>"party",
			"81"=>"url",
			"82"=>"tel_cc_flag",
			"83"=>"tel_cc_date",
			"84"=>"move_tel_no",
			"85"=>"fax_cc_flag",
			"86"=>"fax_cc_date",
			"87"=>"move_fax_no",
			"88"=>"inv_date",
			"89"=>"emp_range",
			"90"=>"sales_range",
			"91"=>"income_range"
			);
			return mapFields($fields, $array, true);
	}

	/**
	 * Replace array keys with the table 2 columns (m_corporation)
	 * @param array $array - current array list
	 * @return array - array with the new keys
	 */
	private function replaceTable2Keys($array){
		$fields = array(
			"0"=>"office_id",
			"1"=>"head_office_id",
			"2"=>"top_head_office_id",
			"3"=>"top_affiliated_office_id1",
			"4"=>"top_affiliated_office_id2",
			"5"=>"top_affiliated_office_id3",
			"6"=>"top_affiliated_office_id4",
			"7"=>"top_affiliated_office_id5",
			"8"=>"top_affiliated_office_id6",
			"9"=>"top_affiliated_office_id7",
			"10"=>"top_affiliated_office_id8",
			"11"=>"top_affiliated_office_id9",
			"12"=>"top_affiliated_office_id10",
			"13"=>"affiliated_office_id1",
			"14"=>"affiliated_office_id2",
			"15"=>"affiliated_office_id3",
			"16"=>"affiliated_office_id4",
			"17"=>"affiliated_office_id5",
			"18"=>"affiliated_office_id6",
			"19"=>"affiliated_office_id7",
			"20"=>"affiliated_office_id8",
			"21"=>"affiliated_office_id9",
			"22"=>"affiliated_office_id10",
			"23"=>"relation_flag1",
			"24"=>"relation_flag2",
			"25"=>"relation_flag3",
			"26"=>"relation_flag4",
			"27"=>"relation_flag5",
			"28"=>"relation_flag6",
			"29"=>"relation_flag7",
			"30"=>"relation_flag8",
			"31"=>"relation_flag9",
			"32"=>"relation_flag10",
			"33"=>"relation_name1",
			"34"=>"relation_name2",
			"35"=>"relation_name3",
			"36"=>"relation_name4",
			"37"=>"relation_name5",
			"38"=>"relation_name6",
			"39"=>"relation_name7",
			"40"=>"relation_name8",
			"41"=>"relation_name9",
			"42"=>"relation_name10",
			"43"=>"listed_marked",
			"44"=>"listed_name",
			"45"=>"securities_code",
			"46"=>"yuho_number",
			"47"=>"company_stat",
			"48"=>"company_stat_name",
			"49"=>"office_stat",
			"50"=>"office_stat_name",
			"51"=>"move_office_id",
			"52"=>"bankruptcy_date",
			"53"=>"company_vitality",
			"54"=>"corporation_name",
			"55"=>"corporation_name_kana",
			"56"=>"office_name",
			"57"=>"zip_code",
			"58"=>"company_pref_id",
			"59"=>"company_city_id",
			"60"=>"address1",
			"61"=>"address2",
			"62"=>"address3",
			"63"=>"address4",
			"64"=>"address5",
			"65"=>"address6",
			"66"=>"tel",
			"67"=>"fax",
			"68"=>"office_number",
			"69"=>"capital_amount",
			"70"=>"representative_title",
			"71"=>"representative_name",
			"72"=>"representative_kana",
			"73"=>"industry_code1",
			"74"=>"industry_name1",
			"75"=>"industry_code2",
			"76"=>"industry_name2",
			"77"=>"industry_code3",
			"78"=>"industry_name3",
			"79"=>"license",
			"80"=>"party",
			"81"=>"hp_url",
			"82"=>"tel_cc_flag",
			"83"=>"tel_cc_date",
			"84"=>"move_tel_no",
			"85"=>"fax_cc_flag",
			"86"=>"fax_cc_date",
			"87"=>"move_fax_no",
			"88"=>"inv_date",
			"89"=>"employee_number",
			"90"=>"year_sales",
			"91"=>"income_range",
			"92"=>"addressall",
			"93"=>"business_type"
			);
			$newList = mapFields($fields, $array, true);
			//change tel_cc_flag and fax_cc_flag bit to false
			// -> tel_cc_flag and fax_cc_flag は0-9の数値なのでコメントアウト
			//$newList['tel_cc_flag'] = $newList['tel_cc_flag'] === "1" ?
			//	true : "";
			//$newList['fax_cc_flag'] = $newList['fax_cc_flag'] === "1" ?
			//	true : "";

			// 会社状況フラグ 0-15の数値  NULL：非倒産 の場合に 0:非倒産 に変換
			if ($newList["company_stat"] === null && $newList["company_stat_name"] === "非倒産"){ 
				$newList["company_stat"] = 0;
			}
			// 事業所状況フラグ 0-9の数値  NULL：非閉鎖 の場合に 0:非閉鎖 に変換
			if ($newList["office_stat"] === null && $newList["office_stat_name"] === "非閉鎖"){
				$newList["office_stat"] = 0;
			}
			// 電話番号コールチェックフラグ 0-9の数値  NULLの場合に 0（未チェック/番号なしの意味） に変換
			if ($newList["tel_cc_flag"] === null && $newList["tel_cc_date"] === null){
				$newList["tel_cc_flag"] = 0;
			}
			// FAX番号コールチェックフラグ 0-9の数値  NULLの場合に 0（未チェック/番号なしの意味） に変換
			if ($newList["fax_cc_flag"] === null && $newList["fax_cc_date"] === null){
				$newList["fax_cc_flag"] = 0;
			}
			
			return $newList;
	}
	/**
	 * Replace array keys with the table 1 columns (m_lbc)
	 * @param array $array - current array list
	 * @return array - array with the new keys
	 */
	private function replaceTable3Keys($array){
		$fields = array(
			"0"=>"office_id",
			"1"=>"head_office_id",
			"2"=>"top_head_office_id",
			"3"=>"top_affiliated_office_id1",
			"4"=>"top_affiliated_office_id2",
			"5"=>"top_affiliated_office_id3",
			"6"=>"top_affiliated_office_id4",
			"7"=>"top_affiliated_office_id5",
			"8"=>"top_affiliated_office_id6",
			"9"=>"top_affiliated_office_id7",
			"10"=>"top_affiliated_office_id8",
			"11"=>"top_affiliated_office_id9",
			"12"=>"top_affiliated_office_id10",
			"13"=>"affiliated_office_id1",
			"14"=>"affiliated_office_id2",
			"15"=>"affiliated_office_id3",
			"16"=>"affiliated_office_id4",
			"17"=>"affiliated_office_id5",
			"18"=>"affiliated_office_id6",
			"19"=>"affiliated_office_id7",
			"20"=>"affiliated_office_id8",
			"21"=>"affiliated_office_id9",
			"22"=>"affiliated_office_id10",
			"23"=>"relation_flag1",
			"24"=>"relation_flag2",
			"25"=>"relation_flag3",
			"26"=>"relation_flag4",
			"27"=>"relation_flag5",
			"28"=>"relation_flag6",
			"29"=>"relation_flag7",
			"30"=>"relation_flag8",
			"31"=>"relation_flag9",
			"32"=>"relation_flag10",
			"33"=>"relation_name1",
			"34"=>"relation_name2",
			"35"=>"relation_name3",
			"36"=>"relation_name4",
			"37"=>"relation_name5",
			"38"=>"relation_name6",
			"39"=>"relation_name7",
			"40"=>"relation_name8",
			"41"=>"relation_name9",
			"42"=>"relation_name10",
			"43"=>"listed_marked",
			"44"=>"listed_name",
			"45"=>"securities_code",
			"46"=>"yuho_number",
			"47"=>"company_stat",
			"48"=>"company_stat_name",
			"49"=>"office_stat",
			"50"=>"office_stat_name",
			"51"=>"move_office_id",
			"52"=>"bankruptcy_date",
			"53"=>"company_vitality",
			"54"=>"corporation_name",
			"55"=>"corporation_name_kana",
			"56"=>"office_name",
			"57"=>"zip_code",
			"58"=>"company_pref_id",
			"59"=>"company_city_id",
			"60"=>"address1",
			"61"=>"address2",
			"62"=>"address3",
			"63"=>"address4",
			"64"=>"address5",
			"65"=>"address6",
			"66"=>"tel",
			"67"=>"fax",
			"68"=>"office_number",
			"69"=>"capital_amount",
			"70"=>"representative_title",
			"71"=>"representative_name",
			"72"=>"representative_kana",
			"73"=>"industry_code1",
			"74"=>"industry_name1",
			"75"=>"industry_code2",
			"76"=>"industry_name2",
			"77"=>"industry_code3",
			"78"=>"industry_name3",
			"79"=>"license",
			"80"=>"party",
			"81"=>"hp_url",
			"82"=>"tel_cc_flag",
			"83"=>"tel_cc_date",
			"84"=>"move_tel_no",
			"85"=>"fax_cc_flag",
			"86"=>"fax_cc_date",
			"87"=>"move_fax_no",
			"88"=>"inv_date",
			"89"=>"employee_number",
			"90"=>"year_sales",
			"91"=>"income_range",
			"92"=>"addressall",
			"93"=>"business_type",
			"94"=>"latitude",
			"95"=>"longitude"
			);
			//return mapFields($fields, $array, true);
			$newList = mapFields($fields, $array, true);

			return $newList;
	}


	/**
	 * Add missing fields to table 2 from gathered data
	 * @param array $array - current array list
	 * @return array - merged array
	 */
	private function insertDefaultValue2($array){
		$addedFields = array(
			"92" => $array['company_addr1'].$array['company_addr2'].$array['company_addr3'].$array['company_addr4'].$array['company_addr5'].$array['company_addr6'], //addressall (company_addr 1-6) 60-65
			"93" => $array['industry_code1'] //business_type (industry_code1)
			);
		return array_merge($array, $addedFields);
	}
	/**
	 * Add missing fields to table 2 from gathered data
	 * @param array $array - current array list
	 * @return array - merged array
	 */
	private function insertDefaultValue3($array){
		$addedFields = array(
			"94" => null, // latitude
			"95" => null // longitude
			);
		return array_merge($array, $addedFields);
	}

	/**
	 * Create array for fields checking
	 * @return array
	 */
	private function createArrayChecking (){
		$fields = array(
			"0" =>"M,S:11,D",			// office_id
			"1" =>"S:11,D",				// head_office_id
			"2" =>"S:11,D",				// top_head_office_id
			"3" =>"S:11,D",				// top_affiliated_office_id1
			"4" =>"S:11,D",				// top_affiliated_office_id2
			"5" =>"S:11,D",				// top_affiliated_office_id3
			"6" =>"S:11,D",				// top_affiliated_office_id4
			"7" =>"S:11,D",				// top_affiliated_office_id5
			"8" =>"S:11,D",				// top_affiliated_office_id6
			"9" =>"S:11,D",				// top_affiliated_office_id7
			"10" =>"S:11,D",			// top_affiliated_office_id8
			"11" =>"S:11,D",			// top_affiliated_office_id9
			"12" =>"S:11,D",			// top_affiliated_office_id10
			"13" =>"S:11,D",			// affiliated_office_id1
			"14" =>"S:11,D",			// affiliated_office_id2
			"15" =>"S:11,D",			// affiliated_office_id3
			"16" =>"S:11,D",			// affiliated_office_id4
			"17" =>"S:11,D",			// affiliated_office_id5
			"18" =>"S:11,D",			// affiliated_office_id6
			"19" =>"S:11,D",			// affiliated_office_id7
			"20" =>"S:11,D",			// affiliated_office_id8
			"21" =>"S:11,D",			// affiliated_office_id9
			"22" =>"S:11,D",			// affiliated_office_id10
			"23" =>"S:4,D",				// relation_flag1
			"24" =>"S:4,D",				// relation_flag2
			"25" =>"S:4,D",				// relation_flag3
			"26" =>"S:4,D",				// relation_flag4
			"27" =>"S:4,D",				// relation_flag5
			"28" =>"S:4,D",				// relation_flag6
			"29" =>"S:4,D",				// relation_flag7
			"30" =>"S:4,D",				// relation_flag8
			"31" =>"S:4,D",				// relation_flag9
			"32" =>"S:4,D",				// relation_flag10
			"33" =>"L:96",				// relation_name1
			"34" =>"L:96",				// relation_name2
			"35" =>"L:96",				// relation_name3
			"36" =>"L:96",				// relation_name4
			"37" =>"L:96",				// relation_name5
			"38" =>"L:96",				// relation_name6
			"39" =>"L:96",				// relation_name7
			"40" =>"L:96",				// relation_name8
			"41" =>"L:96",				// relation_name9
			"42" =>"L:96",				// relation_name10
			"43" =>"S:1,D",				// listed_flag
			"44" =>"L:96",				// Listed_name
			"45" =>"L:6",				// sec_code
			"46" =>"S:6,A",				// yuho_number
			"47" =>"D",					// company_stat
			"48" =>"L:96",				// company_stat_name
			"49" =>"D",					// office_stat
			"50" =>"L:96",				// office_stat_name
			"51" =>"S:11,D",			// move_office_id
			"52" =>"L:6,D",				// tousan_date
			"53" =>"L:3",				// company_vitality
			"54" =>"L:256",				// company_name
			"55" =>"L:256,B",			// company_name_kana
			"56" =>"L:256",				// office_name
			"57" =>"S:7,D",				// company_zip
			"58" =>"S:2,D",				// company_pref_id
			"59" =>"S:5,D",				// company_city_id
			"60" =>"L:256,J",			// company_addr1
			"61" =>"L:256",				// company_addr2
			"62" =>"L:256",				// company_addr3
			"63" =>"L:256",				// company_addr4
			"64" =>"L:256",				// company_addr5
			"65" =>"L:256",				// company_addr6
			"66" =>"L:13,N",			// company_tel
			"67" =>"L:13,N",			// company_fax
			"68" =>"D",					// office_count
			"69" =>"D",					// capital
			"70" =>"L:256",				// representative_title
			"71" =>"L:256",				// representative
			"72" =>"L:256,B",			// representative_kana
			"73" =>"L:4,D",				// industry_code1
			"74" =>"L:96",				// industry_name1
			"75" =>"S:4,D",				// industry_code2
			"76" =>"L:96",				// industry_name2
			"77" =>"S:4,D",				// industry_code3
			"78" =>"L:96",				// industry_name3
			"79" =>"L:256,J:/.space",	// license
			"80" =>"L:256,J:/.space",	// party
			"81" =>"L:256,B",			// url
			"82" =>"D",					// tel_cc_flag
			"83" =>"S:8,D",				// tel_cc_date
			"84" =>"L:13,N",			// move_tel_no
			"85" =>"D",					// fax_cc_flag
			"86" =>"S:8,D",				// fax_cc_date
			"87" =>"L:13,N",			// move_fax_no
			"88" =>"S:8,D",				// inv_date
			"89" =>"S:2,D",				// emp_range
			"90" =>"S:2,D",				// sales_range
			"91" =>"S:2,D"				// income_range
		);
		return $fields;
	}
}
?>