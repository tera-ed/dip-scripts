<?php
/**
 * Process7 Class
 *
 * Acquire CRMデータ (CRM data)
 *
 * @author Maricris S. Cuerdo
 *
 */
class Process7{
	private $logger, $db, $crm_db, $rds_db, $recolin_db, $mail, $validate;
	private $isError = false;

	const TABLE_1 = 'm_corporation';
	const TABLE_2 = 'm_lbc';
	const LOCK_TABLE_1 = 't_lock_lbc_link';

	const KEY_1 = 'corporation_code';
	const KEY_2 = 'office_id';

	const WK_B_TABLE = 'wk_t_tmp_crm_result';
	const SHELL = 'load_wk_t_tmp_CRM_Result.sh';
	const LIMIT = 5000;
	const OFFSET_LIMIT = 10000;

	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
		$this->validate = new Validation($this->logger);
	}

	/**
	 * Initial Process 7
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
			$this->recolin_db = new RecolinDatabase($this->logger);
			
			$path = getImportPath(true);
			$filename = $IMPORT_FILENAME[$procNo];
			$files = getMultipleCsv($filename, $path);

			$header = getDBField($this->db,self::WK_B_TABLE);
			$limit = self::LIMIT;
			foreach ($files as $fName) {
				$offsetCount = 0;
				$this->db->beginTransaction();
				// Acquire from:「./tmp/csv/Import/after/(yyyymmdd)/YYYYMMDDhhmmss_CRM_Result.csv」
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
					throw new Exception("Process7 Failed to insert with " . self::SHELL . " to " . self::WK_B_TABLE);
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
			if($this->recolin_db) {
				// close database connection
				$this->recolin_db->disconnect();
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
			if($this->recolin_db) {
				// close database connection
				$this->recolin_db->disconnect();
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
		if($this->recolin_db) {
			// close database connection
			$this->recolin_db->disconnect();
		}
	}

	/**
	 * Check contents
	 * Refer to the sheet: 「CRMデータエラーチェック」
	 */
	private function checkContents($array, $row, $csv, $header){
		$isValid = true;
		try {
			$validCheck = $this->createArrayChecking();
			$validArray[self::KEY_1] = $validCheck[0];
			$validArray[self::KEY_2] = $validCheck[1];
			$checkArray[self::KEY_1] = $array[self::KEY_1];
			$checkArray[self::KEY_2] = $array[self::KEY_2];
			$isValid = $this->validate->execute($checkArray, $validArray,$row, $csv, $header);
		} catch(Exception $e){
			throw $e;
		}
		return $isValid;
	}

	/**
	 * Begin transaction
	 * Use 「顧客コード」 as key to search for 「m_corporation.corporation_code」
	 * 		If result is 0, write on error file, skip record
	 * 		else, use 「LBC」 as key to search for 「m_lbc.office_id」
	 * 			if result is 0, write on error file, skip record
	 * 			else, Update the 「m_lbc」 information to the 「m_corporation」
	 *
	 * @param array $dataList
	 */
	private function processData($dataList, $csv, $header){
		global $MAX_COMMIT_SIZE;
		$cntr = 0; $result = true;
		try{
			$corpCodeHeader = $header[0];
			$officeIdHeader = $header[1];
			foreach ($dataList as $row => &$data){
				if(($cntr % $MAX_COMMIT_SIZE) == 0){
					$this->db->beginTransaction();
					$this->crm_db->beginTransaction();
				}
				//check if row contents are valid
				$tableData = emptyToNull($data);
				$validRow = $this->checkContents($data, $row, $csv, $header);
				if($validRow === true){
					// Using the item: 「顧客コード」 as key, search for 「m_corporation.corporation_code」
					$key1 = array($tableData[$corpCodeHeader]);
					$this->crm_db->setMCorporationByCode($tableData[$corpCodeHeader], $db);
					
					$recordCount1 = $this->crm_db->getDataCount(self::TABLE_1, self::KEY_1."=?", $key1);
					// ロック顧客かどうか corporation_code で確認 20161004lock_add
					$lockCount = $this->rds_db->getDataCount(self::LOCK_TABLE_1, self::KEY_1."=? and lock_status = 1 and delete_flag = false", $key1);
					// 顧客が存在して、かつまだロックされていない顧客はm_corporationのLBC情報を更新 20161004lock_add
					if($recordCount1 > 0 && $lockCount <= 0){
						// Using the item: 「LBC」 as key, search for 「m_lbc.office_id」
						$key2 = array($tableData[$officeIdHeader]);
						$recordCount2 = $this->recolin_db->getDataCount(self::TABLE_2, self::KEY_2."=?", $key2);
						if($recordCount2 > 0){
							$mLbcRecord = $this->recolin_db->getData("*", self::TABLE_2, self::KEY_2."=?", $key2);
							//prepare the update fields 更新カラム準備
							$tableList = emptyToNull($mLbcRecord);
							$tableList = $this->replaceTableKeys($tableList);
							$newTableList = $this->insertDefaultValue($tableList);

							// 更新対象の顧客の住所全文[addressall]が LBC情報より作成した住所全文と一致しない場合(半角全角空白除去)は 緯度経度を null にする更新カラムを newTableList に追加
							// LBCより作成した住所全文
							$addressall = $newTableList['addressall'];
							// 住所全文が一致しないかを顧客コード指定で確認
							$changeAddressFlag = $this->rds_db->getDataCount(self::TABLE_1, self::KEY_1."=?"." and replace(replace(addressall,'　',''),' ','') != replace(replace('".$addressall."','　',''),' ','') " , $key1 );
							if($changeAddressFlag > 0){
								$this->logger->info("住所が一致しなかったため緯度経度を null に更新します. ".self::KEY_1." = ".$key1[0]);
								// latitude と longitude の更新カラムを付け足し
								$newTableList = $this->insertDefaultValue2($newTableList);
							}else {
								$this->logger->info("住所が一致したため緯度経度を更新しません.".self::KEY_1." = ".$key1[0]);
							}
							
							// LBCの情報($newTableList)を使って顧客コード指定（$key1）で更新
							$result1 = $this->crm_db->updateData(self::TABLE_1, $newTableList, self::KEY_1."=?", $key1);
							$result2 = $this->db->updateData(self::TABLE_1, $newTableList, self::KEY_1."=?", $key1);
							if(!$result1){
								// 顧客テーブル更新エラー
								$this->logger->error("Failed to update [".self::KEY_1." = ".$key1[0]."] [$officeIdHeader = $tableData[$officeIdHeader]] in ".self::TABLE_1.".");
								$this->isError = true;
								throw new Exception("Process7 Failed to update [".self::KEY_1." = ".$key1[0]."] [$officeIdHeader = $tableData[$officeIdHeader]] in ".self::TABLE_1.".");

							} else {
								// 顧客テーブル更新成功
								$this->logger->info("Data found. Updating row [".self::KEY_1." = ".$key1[0]."] [$officeIdHeader: $tableData[$officeIdHeader]] to ". self::TABLE_1);
							}

						} else {
							// m_lbcからLBCコードが見つからなかった場合
							$this->logger->error("Record not found [$officeIdHeader = $tableData[$officeIdHeader]] in ".self::TABLE_2.".");
						}
						$cntr++;
					// 顧客が存在して、かつロックされている場合は処理スキップ 20161004lock_add
					}else if($recordCount1 > 0 && $lockCount > 0){
						$this->logger->info("ロックされている顧客のため顧客テーブルへの更新をスキップします。 [$corpCodeHeader = $tableData[$corpCodeHeader]] in ".self::LOCK_TABLE_1.".");
						$cntr++;
					// 顧客が存在しない場合
					} else {
						$this->logger->error("Record not found [$corpCodeHeader = $tableData[$corpCodeHeader]] in ".self::TABLE_1.".");

					}
				}else{
					$this->logger->debug("Error in processData.key = ".$tableData[$corpCodeHeader]);

				}
				if(($cntr % $MAX_COMMIT_SIZE) == 0 || ($row + 1) == sizeof($dataList)){
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
	 * Replace array keys with the table columns (m_corporation)
	 * @param array $array - current array list
	 * @return array - array with the new keys
	 */
	private function replaceTableKeys($array){
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
			"91"=>"income_range"
			);
			//unset the excess fields from $array[0]
			unset($array[0]['create_date']);
			unset($array[0]['create_user_code']);
			unset($array[0]['update_date']);
			unset($array[0]['update_user_code']);
			unset($array[0]['delete_flag']);
			$newList = mapFields($fields, $array[0], true);
			
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
	 * Add missing fields to table from gathered data
	 * @param array $array - current array list
	 * @return array - merged array
	 */
	private function insertDefaultValue($array){
		$addedFields = array(
			"addressall" => $array["address1"].$array["address2"].$array["address3"].$array["address4"].$array["address5"].$array["address6"], //addressall (company_addr 1-6) 60-65
			"business_type" => $array["industry_code1"] //business_type (industry_code1)
			);
		return array_merge($array, $addedFields);
	}
	/**
	 * Add missing fields to table from latitude,longitude delete data
	 * @param array $array - current array list
	 * @return array - merged array
	 */
	private function insertDefaultValue2($array){
		$addedFields2 = array(
			"latitude" => null, // 緯度
			"longitude" => null // 経度
			);
		return array_merge($array, $addedFields2);
	}


	/**
	 * Create array for fields checking
	 * @return array
	 */
	private function createArrayChecking(){
		$fields = array(
			"0" => "M,L:9",		// corporation_code
			"1" => "M,S:11,D"	// office_id
		);
		return $fields;
	}
}
?>