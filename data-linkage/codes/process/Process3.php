<?php
/**
 * Process3 Class
 *
 * Acquire 更新_新規データ(update_new data)
 *
 * @author Maricris S. Cuerdo
 *
 */
class Process3{

	private $logger, $db, $mail, $validate;
	private $isError = false;

	const TABLE = 'm_lbc';
	const KEY = 'office_id';

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
	 * Initial Process 3
	 */
	public function execProcess() {
		$this->logger->debug(ini_get("memory_limit")."\n");
		global $IMPORT_FILENAME, $procNo;
		$newFile = "";
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
					throw new Exception("Process3 Failed to insert with " . self::SHELL . " to " . self::WK_B_TABLE);
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
	 * Refer to sheet: 「更新_新規データエラーチェック」
	 * @param $array - array values to check
	 * @param array $dataKey - key of the row to be checked
	 * @return boolean
	 */
	private function checkContents($array, $row, $csv, $header){
		$isValid = true;
		try {
			$validArray = $this->createArrayChecking();
			$validArray = $this->replaceTableKeys($validArray,$header);
			$isValid = $this->validate->execute($array, $validArray, $row, $csv, $header);
			unset($validArray);
		} catch(Exception $e){
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
		$result = null;
		$cntr = 0;
		try{
			$officeIdHeader = $header[0]; // office_id
			foreach ($dataInsert as $row => $data){
				if(($cntr % $MAX_COMMIT_SIZE) == 0){
					$this->db->beginTransaction();
				}
				$tableList = emptyToNull($data);
				//check if row contents are valid
				$validRow = $this->checkContents($tableList, $row, $csv, $header);
				if($validRow === true){
					$key = array($tableList[$officeIdHeader]);
					//insert update data on self::TABLE_1
					$result=$this->db->insertUpdateData(self::TABLE, $tableList, self::KEY, $key);
					if(!$result){
						$this->logger->error("Failed to register [$officeIdHeader $tableList[$officeIdHeader]] to ". self::TABLE);
						$this->isError = true;
						# 2016/09/08 四半期処理のために処理中断をスキップに
						#throw new Exception("Process3 Failed to register [$officeIdHeader $tableList[$officeIdHeader]] to ". self::TABLE);
					} else {
						$this->logger->info("Data with $officeIdHeader: $tableList[$officeIdHeader] inserted/updated to ". self::TABLE);
					}
					$cntr++;
				}else{ // 20160908 validation_check でエラーあった場合はログ表示して該当レコードスキップ （バリデーションの内容はValidation.phpの中でログ表示）
					$this->logger->error("validation check error $officeIdHeader: $tableList[$officeIdHeader] ");
					$cntr++;
				}
				# 1000件ごとにコミット
				if(($cntr % $MAX_COMMIT_SIZE) == 0 || ($row +1) == sizeof($dataInsert)){
					$this->db->commit();
				}
			}
		} catch( Exception $e) {
			throw $e;
		}
		return $cntr;
	}

	/**
	 * Replace array keys with the table columns (m_lbc)
	 * @param array $array - current array list
	 * @return array - array with the new keys
	 */
	private function replaceTableKeys($array,$header){
		$newList = mapFields($header, $array, true);
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
	 * Create array for fields checking
	 * @return array
	 */
	private function createArrayChecking (){
		return array(
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
	}
}
?>