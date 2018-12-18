<?php
/**
 * Process4 Class
 *
 * Acquire the 統合データ(integration data)
 *
 * @author Maricris S. Cuerdo
 *
 */
class Process4{
	private $logger, $db, $mail, $validate;
	private $isError = false;

	const TABLE_1 = 'm_lbc';
	const TABLE_2 = 'm_corporation';
	const TABLE_3 = 'm_obic_application';
	const LOCK_TABLE_1 = 't_lock_lbc_link';

	const KEY = 'office_id';
	const KEY_1 = 'contract_office_id';
	const KEY_2 = 'billing_office_id';
	const DATA_0 = "OLD_LBC";
	const DATA_1 = "NEW_LBC";

	const WK_B_TABLE = 'wk_t_tmp_quarter_integration';
	const SHELL = 'load_wk_t_tmp_Quarter_Integration.sh';
	const LIMIT = 5000;
	const OFFSET_LIMIT = 10000;

	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
		$this->validate = new Validation($this->logger);
	}

	/**
	 * Initial Process 4
	 */
	public function execProcess() {
		$this->logger->debug(ini_get("memory_limit")."\n");
		global $IMPORT_FILENAME, $procNo;
		$currentRowSize = 0;
		try{
			$this->db = new Database($this->logger);

			$path = getImportPath(true);
			$filename = $IMPORT_FILENAME[$procNo];
			// $path(import_after)内の名称に$filenameを含むファイル群取得
			$files = getMultipleCsv($filename, $path);
			$header = getDBField($this->db,self::WK_B_TABLE);

			$limit = self::LIMIT;
			foreach ($files as $fName) {
				$offsetCount = 0;
				$this->db->beginTransaction();
				// Acquire from: 「./tmp/csv/Import/after/(yyyymmdd)/YYYYMMDDhhmmss_Quarter_Integration.csv」
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
					throw new Exception("Process4 Failed to insert with " . self::SHELL . " to " . self::WK_B_TABLE);
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
	 * Check contents
	 * Refer to sheet: 「統合データエラーチェック」
	 * @param $array - array values to check
	 * @return boolean
	 */
	private function checkContents($array, $row, $csv, $header){
		$isValid = true;
		try {
			$validCheck = $this->createArrayChecking();
			$validArray[self::DATA_0] = $validCheck[0];
			$validArray[self::DATA_1] = $validCheck[1];
			$checkArray[self::DATA_0] = $array['old_lbc'];
			$checkArray[self::DATA_1] = $array['new_lbc'];
			$isValid = $this->validate->execute($checkArray, $validArray, $row, $csv, $header);
			unset($validArray);
			unset($checkArray);
		} catch(Exception $e){
			throw $e;
		}
		return $isValid;
	}

	/**
	 * Begin transaction
	 * Search item 「OLD_LBC」 from 「m_lbc.office_id」
	 * 		if search results are 0, do noting, proceed to new process
	 * 		else, update the delete_flag of the search record
	 * Search item 「OLD_LBC」 from 「m_corporation.office_id」
	 * 		if search results are 0, do nothing, proceed to new process
	 * 		else, update 「office_id」 of the searched record with the item: 「NEW_LBC」
	 *
	 * @param array $data
	 */
	private function processData($dataInsert, $csv, $header){
		global $MAX_COMMIT_SIZE;
		$cntr = 0; $result1 = true; $result2 = true;
		try{
			$OLD_LBC_officeIdHeader = $header[0]; // OLD_LBC office_id
			$NEW_LBC_officeIdHeader = $header[1]; // NEW_LBC office_id
			foreach ($dataInsert as $row => $data){
				if(($cntr % $MAX_COMMIT_SIZE) == 0){
					$this->db->beginTransaction();
				}
				// check data if there are errors
				$tableData = $data;
				$validRow = $this->checkContents($data, $row, $csv, $header);
				if($validRow === true) {
					$key = array($tableData[$OLD_LBC_officeIdHeader]); // OLD_LBC
					// Search item 「OLD_LBC」 from 「m_lbc.office_id」
					$this->logger->info("Search item 「OLD_LBC」 from 「m_lbc.office_id」");
					$result1 = true;
					$recordCount1 = $this->db->getDataCount(self::TABLE_1, self::KEY."=?", $key);
					if($recordCount1 > 0){// if search result != 0, delete the search record
						$result1 = $this->db->updateData(self::TABLE_1, array("delete_flag"=>true), self::KEY."=?", $key);
						if(!$result1){
							$this->logger->error("Failed to update delete_flag of row with $OLD_LBC_officeIdHeader: $tableData[$OLD_LBC_officeIdHeader] to ". self::TABLE_1);
							# 2016/09/08 四半期処理のために処理中断をスキップに
							#throw new Exception("Process4 Failed to update delete_flag of row with $OLD_LBC_officeIdHeader: $tableData[$OLD_LBC_officeIdHeader] to ". self::TABLE_1);
						} else {
							$this->logger->info("Data found. Updating delete_flag of row with $OLD_LBC_officeIdHeader: $tableData[$OLD_LBC_officeIdHeader] to ". self::TABLE_1);
						}
					} else {
						$this->logger->error("Record not found [$OLD_LBC_officeIdHeader: $tableData[$OLD_LBC_officeIdHeader]] in ".self::TABLE_1.".");
					}

					// Search item 「OLD_LBC」 from 「m_corporation.office_id」
					$this->logger->info("Search item 「OLD_LBC」 from 「m_corporation.office_id」");
					$result2 = true;
					$recordCount2 = $this->db->getDataCount(self::TABLE_2, self::KEY."=?", $key);
					// ロック顧客かどうか office_id で確認 20161004lock_add
					$lockCount = $this->db->getDataCount(self::LOCK_TABLE_1." lo inner join ".self::TABLE_2." mc on lo.corporation_code = mc.corporation_code ",
					 "mc.".self::KEY."=? and lo.lock_status = 1 and lo.delete_flag = false", $key);
					// 顧客が存在して、かつまだロックされていない顧客は更新 20161004lock_add
					if($recordCount2 > 0 && $lockCount <= 0){
						$mLbcRecord = $this->db->getData("*", self::TABLE_1, self::KEY."=?", array($tableData[$NEW_LBC_officeIdHeader]));
						if(!empty($mLbcRecord)){
							//prepare the update fields
							$tableList = emptyToNull($mLbcRecord);
							$tableList = $this->replaceTableKeys($tableList);
							$newTableList = $this->insertDefaultValue($tableList);
							// Update the 「office_id」 of the searched record with the item: 「NEW_LBC」
							$newTableList[self::KEY] = $tableData[$NEW_LBC_officeIdHeader];
							$result2 = $this->db->updateData(self::TABLE_2, $newTableList, self::KEY."=?", $key);
							if(!$result2){
								$this->logger->error("Failed to update [$NEW_LBC_officeIdHeader = $key]");
								# 2016/09/08 四半期処理のために処理中断をスキップに
								#throw new Exception("Process4 Failed to update [$OLD_LBC_officeIdHeader = $key]");
							} else {
								$this->logger->info("Data found. Updating row with new $NEW_LBC_officeIdHeader: $tableData[$NEW_LBC_officeIdHeader] to ". self::TABLE_2);
							}
						}else{
							$this->logger->error("Failed to update [".self::TABLE_1."->".self::KEY.": key data not found] ");
						}
					// 顧客が存在して、かつロックされている場合は処理スキップ 20161004lock_add
					}else if($recordCount2 > 0 && $lockCount > 0){
						$this->logger->info("ロックされている顧客のため顧客テーブルへの更新をスキップします。 [$OLD_LBC_officeIdHeader: $tableData[$OLD_LBC_officeIdHeader]] in ".self::LOCK_TABLE_1.".");
					}
					 else {
						$this->logger->error("Record not found [$OLD_LBC_officeIdHeader: $tableData[$OLD_LBC_officeIdHeader]] in ".self::TABLE_2.".");
					}

					// Search item 「OLD_LBC」 from 「m_obic_application.contract_office_id」
					$this->logger->info("Search item 「OLD_LBC」 from 「m_obic_application.contract_office_id」");
					$result3 = true;
					$recordCount3 = $this->db->getDataCount(self::TABLE_3, self::KEY_1."=?", $key);
					if($recordCount3 > 0){// if search result != 0, delete the search record
						$result3 = $this->db->updateData(self::TABLE_3, array(self::KEY_1=>$tableData[$NEW_LBC_officeIdHeader]), self::KEY_1."=?", $key);
						if(!$result3){
							$this->logger->error("Failed to update delete_flag of row with ".self::KEY_1.": $tableData[$NEW_LBC_officeIdHeader] to ". self::TABLE_3);
							# 2016/09/08 四半期処理のために処理中断をスキップに
							#throw new Exception("Process4 Failed to update delete_flag of row with ".self::KEY_1.": $tableData[$NEW_LBC_officeIdHeader] to ". self::TABLE_3);
						} else {
							$this->logger->info("Data found. Updating delete_flag of row with ".self::KEY_1.": $tableData[$NEW_LBC_officeIdHeader] to ". self::TABLE_3);
						}
					} else {
						// 2017/11/14 ログレベルを修正（ERROR→INFO）
						$this->logger->info("Record not found [".self::KEY_1.": $tableData[$OLD_LBC_officeIdHeader]] in ".self::TABLE_3.".");
					}

					// Search item 「OLD_LBC」 from m_obic_application.billing_office_id」
					$this->logger->info("Search item 「OLD_LBC」 from m_obic_application.billing_office_id」");
					$result4 = true;
					$recordCount4 = $this->db->getDataCount(self::TABLE_3, self::KEY_2."=?", $key);
					if($recordCount4 > 0){// if search result != 0, delete the search record
						$result4 = $this->db->updateData(self::TABLE_3, array(self::KEY_2=>$tableData[$NEW_LBC_officeIdHeader]), self::KEY_2."=?", $key);
						if(!$result4){
							$this->logger->error("Failed to update delete_flag of row with ".self::KEY_2.": $tableData[$NEW_LBC_officeIdHeader] to ". self::TABLE_3);
							# 2016/09/08 四半期処理のために処理中断をスキップに
							#throw new Exception("Process4 Failed to update delete_flag of row with ".self::KEY_2.": $tableData[$NEW_LBC_officeIdHeader] to ". self::TABLE_3);
						} else {
							$this->logger->info("Data found. Updating delete_flag of row with ".self::KEY_2.": $tableData[$NEW_LBC_officeIdHeader] to ". self::TABLE_3);
						}
					} else {
						// 2017/11/14 ログレベルを修正（ERROR→INFO）
						$this->logger->info("Record not found [".self::KEY_2.": $tableData[$OLD_LBC_officeIdHeader]] in ".self::TABLE_3.".");
					}

					if(!$result1 || !$result2 || !$result3 || !$result4){
						$this->isError = true;
					}
					$cntr++;
				}
				else{ // 20160908 validation_check でエラーあった場合はログ表示して該当レコードスキップ （バリデーションの内容はValidation.phpの中でログ表示）
					$this->logger->error("validation check error $OLD_LBC_officeIdHeader: $tableData[$OLD_LBC_officeIdHeader] , $NEW_LBC_officeIdHeader: $tableData[$NEW_LBC_officeIdHeader]");
					$cntr++;
				}
				if(($cntr % $MAX_COMMIT_SIZE) == 0 || ($row + 1) == sizeof($dataInsert)){
					$this->db->commit();
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
			"addressall" => $array["address1"].$array["address2"].$array["address3"].$array["address4"].$array["address5"].$array["address6"],
			"business_type" => $array["industry_code1"]
			);
		return array_merge($array, $addedFields);
	}

	/**
	 * Create array for fields checking
	 * @return multitype:string
	 */
	private function createArrayChecking(){
		$fields = array(
			"0" => "M,S:11,D",	// old_office_id
			"1" => "S:11,D"		// new_office_id
		);
		return $fields;
	}
}
?>