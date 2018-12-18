<?php
/**
 * Process6 Class
 *
 * @author Krishia Valencia
 *
 */
class Process6 {

	private $db;
	private $logger;
	private $mail;
	private $isError = false;

	const TABLE_1 = 'm_lbc';
	const TABLE_2 = 'm_corporation';
	const LOCK_TABLE_1 = 't_lock_lbc_link';

	const M_LBC = 0; // m_lbc
	const M_COR = 1; // m_corporation

	const KEY_1 = 'office_id';
	const KEY_2 = 'corporation_code';

	const WK_B_TABLE1 = 'wk_t_tmp_crm_result';
	const WK_B_TABLE2 = 'wk_t_tmp_lbc_sbndata';

	const SHELL1 = 'load_wk_t_tmp_CRM_Result.sh';
	const SHELL2 = 'load_wk_t_tmp_LBC_SBNDATA.sh';

	const LIMIT = 5000;
	const OFFSET_LIMIT = 10000;

	/**
	 * Process6 constructor
	 * @param $logger
	 */
	public function __construct($logger) {
		// set logger
		$this->logger = $logger;
		// instantiate mail
		$this->mail = new Mail();
		// instantiate validation
		$this->validate = new Validation($this->logger);
		// set parameters
		$this->setParams();
	}

	/**
	 * Execute Process6
	 * @throws PDOException
	 * @throws Exception
	 */
	public function execProcess() {
		global $IMPORT_FILENAME, $procNo;
		try {

			// instantiate database
			$this->db = new Database($this->logger);
			// get import path
			$path = getImportPath(true);
			$resultFiles = getMultipleCsv($IMPORT_FILENAME[$procNo][0], $path);
			$sbndataFiles = getMultipleCsv($IMPORT_FILENAME[$procNo][1], $path);

			// temporary_crm_result の処理はなくなった 代わりに crm_result テーブルに値があります
			foreach ($resultFiles as &$fName) {
				$this->db->beginTransaction();
				// Acquire from:「./tmp/csv/Import/after/(yyyymmdd)/YYYYMMDDhhmmss_CRM_Result.csv」
				if(shellExec($this->logger, self::SHELL1, $fName) === 0){
					$this->db->commit();
				} else {
					// shell失敗
					$this->db->rollback();
					$this->logger->error("Error File : " . $fName);
					throw new Exception("Process6 Failed to insert with " . self::SHELL1 . " to " . self::WK_B_TABLE1);
				}
			}
			$header = getDBField($this->db,self::WK_B_TABLE2);
			$limit = self::LIMIT;
			foreach ($sbndataFiles as &$fName) {
				$offsetCount = 0;
				$this->db->beginTransaction();
				// Acquire from: 「./tmp/csv/Import/after/(yyyymmdd)/YYYYMMDDhhmmss_LBC_SBNDATA.csv」
				if(shellExec($this->logger, self::SHELL2, $fName) === 0){
					$this->db->commit();
					while ($offsetCount <=self::OFFSET_LIMIT) {
						$offset = ($limit * $offsetCount);
						$csvList = $this->db->getLimitOffsetData("*", self::WK_B_TABLE2, null, array(), $limit, $offset);
						if (count($csvList) === 0) {
							// 配列の値がすべて空の時の処理
							break;
						}
						$cntr = $this->processData($csvList, self::WK_B_TABLE2.",LIMIT:$limit,OFFSET:$offset", $header);
						$offsetCount++;
					}
				} else {
					// shell失敗
					$this->db->rollback();
					$this->logger->error("Error File : " . $fName);
					throw new Exception("Process6 Failed to insert with " . self::SHELL2 . " to " . self::WK_B_TABLE2);
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
		} catch(Exception $e2) { // error
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
	 * Insert/update data from csv
	 * @param array $data - array data from csv
	 * @param string $csvFile
	 * @param string $csvHeader
	 * @throws Exception
	 * @return number
	 */
	private function processData($data, $csvFile, $csvHeader) {
		global $MAX_COMMIT_SIZE;
		$cntr = 0;
		$pKey1	= self::KEY_1;
		$pKey2	= self::KEY_2;
		try {
			$this->logger->info("process data");
			foreach ($data as $row => &$col) {
				if(($cntr % $MAX_COMMIT_SIZE) == 0){
					//begin transaction
					$this->db->beginTransaction();
				}
				// replace empty string to null
				$lblParam = emptyToNull($col);
				// map fields for m_lbc table
				$lblParam = mapFields($this->fields[self::M_LBC], $lblParam);
				// convert NULL -> 0
				$lblParam = $this->setLbcParams($lblParam);
				// map fields for m_corporation table
				$corParam = mapFields($this->fields[self::M_COR], $lblParam);
				// add additional fields for m_corporation
				$corParam = $this->setParams($corParam, true);

				$officeId = $lblParam[$pKey1];

				// validate values to be registered
				$isValid = $this->validate->execute($lblParam, $this->fields[self::M_LBC], $row, $csvFile, $csvHeader);
				// Values are not valid.
				if(!$isValid) {
					// Write down on the error file and
					// Move the process to the next record
					$this->logger->error("Error found in data [$pKey1 = $officeId]");
				} else {
					// If all fields are valid and
					$result1 = $this->db->insertUpdateData(self::TABLE_1, $lblParam, $pKey1);
					if($result1) {
						$count = $this->db->getDataCount(self::WK_B_TABLE1, $pKey1."=?", array($officeId));
						if($count > 0) {
							$crm_esultRecord = $this->db->getData($pKey2, self::WK_B_TABLE1, $pKey1."=?", array($officeId));
							//prepare the update fields
							$tableList = emptyToNull($crm_esultRecord);
							$corporation_code = $tableList[0][$pKey2];
							// 顧客テーブルに存在するか確認
							$corporationCount = $this->db->getDataCount(self::TABLE_2, $pKey2."=?", array($corporation_code));
							// ロック顧客かどうか corporation_code で確認 20161004lock_add
							$lockCount = $this->db->getDataCount(self::LOCK_TABLE_1, $pKey2."=? and lock_status = 1 and delete_flag = false", array($corporation_code));
							// 顧客が存在して、かつまだロックされていない顧客はm_corporationのoffice_idを更新 20161004lock_add
							if($corporationCount > 0 && $lockCount <= 0) {
								// If search results are not 0件 (0 records),
								// update record on m_corporation
								$updateFields = array();
								$updateFields[$pKey1] = $officeId;
								$result2 = $this->db->updateData(self::TABLE_2, $updateFields, $pKey2."=?", array($corporation_code));
								if(!$result2){
									$tbl =  self::TABLE_2;
									$this->isError = true;
									$this->logger->error("Failed to register ROW[$row] : [$pKey2 = $corporation_code] to $tbl");
									throw new Exception("Process6 Failed to register ROW[$row] : [$pKey2 = $corporation_code] to $tbl");

								}
							// 顧客が存在して、かつロックされている場合は処理スキップ 20161004lock_add
							}else if($corporationCount > 0 && $lockCount > 0){
								$lockTbl =  self::LOCK_TABLE_1;
								$this->logger->info("ロックされている顧客のため顧客テーブルへの更新をスキップします。 ROW[$row] : [$pKey2 = $corporation_code] in $lockTbl");
							} else {
								$tbl =  self::TABLE_2;
								$this->isError = true;
								$this->logger->error("Failed to register ROW[$row] : [$pKey2 = $corporation_code] to $tbl");
								throw new Exception("Process6 Failed to register ROW[$row] : [$pKey2 = $corporation_code] to $tbl");

							}
						} else {
							// 処理中のoffice_idがまだ存在しない場合は顧客テーブルへ登録
							$count = $this->db->getDataCount(self::TABLE_2, $pKey1."=?", array($officeId));
							if($count == 0) {
								$dataParam = $this->setInsertParams($corParam);
								$result2 = $this->db->insertData(self::TABLE_2, $dataParam);
								if(!$result2){
									$tbl =  self::TABLE_2;
									$this->isError = true;
									$this->logger->error("Failed to register ROW[$row] : [$pKey1 = $officeId] to $tbl");
									throw new Exception("Process6 Failed to register ROW[$row] : [$pKey1 = $officeId] to $tbl");
								}
								// office_idを使った新規登録分のレコードは、まだ名寄せ情報テーブルに無いはずなので、新規登録
								// pkey1 -> office_id,  table2 -> m_corporation
								// office_idで顧客コードを検索して、その組み合わせをwk_t_lbc_crm_linkに新規登録
								$newCorp = $this->db->getData("corporation_code",self::TABLE_2, $pKey1."=?", array($officeId));
								$currentDate = date("Y/m/d H:i:s");
								$insertData = array(
									"corporation_code"=>$newCorp[0]["corporation_code"],
									"office_id"=>$officeId,
									"match_result"=>"A",
									"match_detail"=>null,
									"name_approach_code"=>$newCorp[0]["corporation_code"],
									"name_approach_office_id"=>$officeId,
									"current_data_flag"=>"1",
									"lock_status"=>"0",
									"create_date"=>$currentDate,
									"update_date"=>$currentDate
								);
								$result3 = $this->db->insertData("wk_t_lbc_crm_link", $insertData);
								if(!$result3){
									$tbl =  "wk_t_lbc_crm_link";
									$this->isError = true;
									$this->logger->error("Failed to register ROW[$row] : [$pKey1 = $officeId][corporation_code = $newCorp] to $tbl");
									throw new Exception("Process6 Failed to register ROW[$row] : [$pKey1 = $officeId][corporation_code = $newCorp] to $tbl");
								}

							}else{
								$tbl =  self::TABLE_2;
								$this->logger->info("Failed すでにオフィスIDが登録されています。 ROW[$row] : [$pKey1 = $officeId] to $tbl");

							}
						}
					} else {
						$tbl =  self::TABLE_1;
						$this->isError = true;
						$this->logger->error("Failed to register ROW[$row] : [$pKey1 = $officeId] to $tbl");
						throw new Exception("Process6 Failed to register ROW[$row] : [$pKey1 = $officeId] to $tbl");

					}

					$this->logger->debug("Registered [$pKey1 = $officeId]");
					$cntr++;
				}
				//commit according to set max commit size on config file
				if(($cntr % $MAX_COMMIT_SIZE) == 0 || ($row + 1) == sizeof($data)){
					$this->db->commit();
				}
			}
		} catch (Exception $e) {
			throw $e;
		}

		return $cntr;
	}

	/* 
	 * 数値系カラムに対する null -> 0 への変換  
	 */
	private function setLbcParams($data) {
		// 会社状況フラグ 0-15の数値  NULL：非倒産 の場合に 0:非倒産 に変換
		if ($data["company_stat"] === null && $data["company_stat_name"] === "非倒産"){ 
			$data["company_stat"] = 0;
		}
		// 事業所状況フラグ 0-9の数値  NULL：非閉鎖 の場合に 0:非閉鎖 に変換
		if ($data["office_stat"] === null && $data["office_stat_name"] === "非閉鎖"){
			$data["office_stat"] = 0;
		}
		// 電話番号コールチェックフラグ 0-9の数値  NULLの場合に 0（未チェック/番号なしの意味） に変換
		if ($data["tel_cc_flag"] === null && $data["tel_cc_date"] === null){
			$data["tel_cc_flag"] = 0;
		}
		// FAX番号コールチェックフラグ 0-9の数値  NULLの場合に 0（未チェック/番号なしの意味） に変換
		if ($data["fax_cc_flag"] === null && $data["fax_cc_date"] === null){
			$data["fax_cc_flag"] = 0;
		}
		return $data;
	}

	/**
	 * Set parameters for insert validation
	 */
	private function setInsertParams($data) {
		$corId = $this->db->getNextVal('M_CORPORATION_CODE');
		$data['corporation_code'] = $corId;
		$data['head_bulk_flag'] = false;
		$data['orders_ban_flag'] = false;
		$data['post_ban_flag'] = false;
		$data['call_dm_ban_flag'] = false;
		$data['claim_flag'] = false;
		$data['corporation_attr'] = "0";
		$data['country_name'] = "日本";
		$data['free_item1'] = "";
		$data['free_item2'] = "";
		$data['free_item3'] = "";
		$data['free_item4'] = "";
		$data['free_item5'] = "";
		$data['free_item6'] = "";
		$data['free_item7'] = "";
		$data['free_item8'] = "";
		$data['free_item9'] = "";
		$data['free_item10'] = "";
		$data['free_item11'] = "";
		$data['free_item12'] = "";
		$data['free_item13'] = "";
		$data['free_item14'] = "";
		$data['free_item15'] = "";
		$data['free_item16'] = "";
		$data['free_item17'] = "";
		$data['free_item18'] = "";
		$data['free_item19'] = "";
		$data['free_item20'] = "";

		$data['latitude'] = NULL;
		$data['longitude'] = NULL;
		$data['free_dial'] = NULL;
		$data['branch_office_name'] = NULL;
		$data['store_department'] = NULL;
		$data['dispatch_licensing_han'] = NULL;
		$data['dispatch_licensing_toku'] = NULL;
		$data['dispatch_licensing_sho'] = NULL;
		$data['nego_record_date'] = NULL;

		return $data;
	}

	/**
	 * Set parameters for insert/update, validation
	 */
	private function setParams($data = array(), $addFields = false) {
		if($addFields) {
			// additional fields
			$addressAll = $data['address1'].$data['address2'];
			$addressAll.= $data['address3'].$data['address4'];
			$addressAll.= $data['address5'].$data['address6'];
			$data['addressall'] = $addressAll;
			$data['business_type'] = $data['industry_code1'];
			// 会社状況フラグ 0-15の数値  NULL：非倒産 の場合に 0:非倒産 に変換
			if ($data["company_stat"] === null && $data["company_stat_name"] === "非倒産"){ 
				$data["company_stat"] = 0;
			}
			// 事業所状況フラグ 0-9の数値  NULL：非閉鎖 の場合に 0:非閉鎖 に変換
			if ($data["office_stat"] === null && $data["office_stat_name"] === "非閉鎖"){
				$data["office_stat"] = 0;
			}
			// 電話番号コールチェックフラグ 0-9の数値  NULLの場合に 0（未チェック/番号なしの意味） に変換
			if ($data["tel_cc_flag"] === null && $data["tel_cc_date"] === null){
				$data["tel_cc_flag"] = 0;
			}
			// FAX番号コールチェックフラグ 0-9の数値  NULLの場合に 0（未チェック/番号なしの意味） に変換
			if ($data["fax_cc_flag"] === null && $data["fax_cc_date"] === null){
				$data["fax_cc_flag"] = 0;
			}
			return $data;
		}
		// key : DB field name
		// val : validation args
		$this->fields = array(
			array(
				"office_id"                  => "M,S:11,D",
				"head_office_id"             => "S:11,D",
				"top_head_office_id"         => "S:11,D",
				"top_affiliated_office_id1"  => "S:11,D",
				"top_affiliated_office_id2"  => "S:11,D",
				"top_affiliated_office_id3"  => "S:11,D",
				"top_affiliated_office_id4"  => "S:11,D",
				"top_affiliated_office_id5"  => "S:11,D",
				"top_affiliated_office_id6"  => "S:11,D",
				"top_affiliated_office_id7"  => "S:11,D",
				"top_affiliated_office_id8"  => "S:11,D",
				"top_affiliated_office_id9"  => "S:11,D",
				"top_affiliated_office_id10" => "S:11,D",
				"affiliated_office_id1"      => "S:11,D",
				"affiliated_office_id2"      => "S:11,D",
				"affiliated_office_id3"      => "S:11,D",
				"affiliated_office_id4"      => "S:11,D",
				"affiliated_office_id5"      => "S:11,D",
				"affiliated_office_id6"      => "S:11,D",
				"affiliated_office_id7"      => "S:11,D",
				"affiliated_office_id8"      => "S:11,D",
				"affiliated_office_id9"      => "S:11,D",
				"affiliated_office_id10"     => "S:11,D",
				"relation_flag1"             => "S:4,D",
				"relation_flag2"             => "S:4,D",
				"relation_flag3"             => "S:4,D",
				"relation_flag4"             => "S:4,D",
				"relation_flag5"             => "S:4,D",
				"relation_flag6"             => "S:4,D",
				"relation_flag7"             => "S:4,D",
				"relation_flag8"             => "S:4,D",
				"relation_flag9"             => "S:4,D",
				"relation_flag10"            => "S:4,D",
				"relation_name1"             => "L:96",
				"relation_name2"             => "L:96",
				"relation_name3"             => "L:96",
				"relation_name4"             => "L:96",
				"relation_name5"             => "L:96",
				"relation_name6"             => "L:96",
				"relation_name7"             => "L:96",
				"relation_name8"             => "L:96",
				"relation_name9"             => "L:96",
				"relation_name10"            => "L:96",
				"listed_flag"                => "S:1,D",
				"Listed_name"                => "L:96",
				"sec_code"                   => "L:6",
				"yuho_number"                => "S:6,A",
				"company_stat"               => "D",
				"company_stat_name"          => "L:96",
				"office_stat"                => "D",
				"office_stat_name"           => "L:96",
				"move_office_id"             => "S:11,D",
				"tousan_date"                => "L:6,D",
				"company_vitality"           => "L:3",
				"company_name"               => "L:256",
				"company_name_kana"          => "L:256,B",
				"office_name"                => "L:256",
				"company_zip"                => "S:7,D",
				"company_pref_id"            => "S:2,D",
				"company_city_id"            => "S:5,D",
				"company_addr1"              => "L:256,J",
				"company_addr2"              => "L:256",
				"company_addr3"              => "L:256",
				"company_addr4"              => "L:256",
				"company_addr5"              => "L:256",
				"company_addr6"              => "L:256",
				"company_tel"                => "L:13,N",
				"company_fax"                => "L:13,N",
				"office_count"               => "D",
				"capital"                    => "D",
				"representative_title"       => "L:256",
				"representative"             => "L:256",
				"representative_kana"        => "L:256,B",
				"industry_code1"             => "L:4,D",
				"industry_name1"             => "L:96",
				"industry_code2"             => "S:4,D",
				"industry_name2"             => "L:96",
				"industry_code3"             => "S:4,D",
				"industry_name3"             => "L:96",
				"license"                    => "L:256,J:/.space",
				"party"                      => "L:256,J:/.space",
				"url"                        => "L:256,B",
				"tel_cc_flag"                => "D",
				"tel_cc_date"                => "S:8,D",
				"move_tel_no"                => "L:13,N",
				"fax_cc_flag"                => "D",
				"fax_cc_date"                => "S:8,D",
				"move_fax_no"                => "L:13,N",
				"inv_date"                   => "S:8,D",
				"emp_range"                  => "S:2,D",
				"sales_range"                => "S:2,D",
				"income_range"               => "S:2,D"
			),
			array(
					"office_id"                  => "M,S:11,D",
					"head_office_id"             => "S:11,D",
					"top_head_office_id"         => "S:11,D",
					"top_affiliated_office_id1"  => "S:11,D",
					"top_affiliated_office_id2"  => "S:11,D",
					"top_affiliated_office_id3"  => "S:11,D",
					"top_affiliated_office_id4"  => "S:11,D",
					"top_affiliated_office_id5"  => "S:11,D",
					"top_affiliated_office_id6"  => "S:11,D",
					"top_affiliated_office_id7"  => "S:11,D",
					"top_affiliated_office_id8"  => "S:11,D",
					"top_affiliated_office_id9"  => "S:11,D",
					"top_affiliated_office_id10" => "S:11,D",
					"affiliated_office_id1"      => "S:11,D",
					"affiliated_office_id2"      => "S:11,D",
					"affiliated_office_id3"      => "S:11,D",
					"affiliated_office_id4"      => "S:11,D",
					"affiliated_office_id5"      => "S:11,D",
					"affiliated_office_id6"      => "S:11,D",
					"affiliated_office_id7"      => "S:11,D",
					"affiliated_office_id8"      => "S:11,D",
					"affiliated_office_id9"      => "S:11,D",
					"affiliated_office_id10"     => "S:11,D",
					"relation_flag1"             => "S:4,D",
					"relation_flag2"             => "S:4,D",
					"relation_flag3"             => "S:4,D",
					"relation_flag4"             => "S:4,D",
					"relation_flag5"             => "S:4,D",
					"relation_flag6"             => "S:4,D",
					"relation_flag7"             => "S:4,D",
					"relation_flag8"             => "S:4,D",
					"relation_flag9"             => "S:4,D",
					"relation_flag10"            => "S:4,D",
					"relation_name1"             => "L:96",
					"relation_name2"             => "L:96",
					"relation_name3"             => "L:96",
					"relation_name4"             => "L:96",
					"relation_name5"             => "L:96",
					"relation_name6"             => "L:96",
					"relation_name7"             => "L:96",
					"relation_name8"             => "L:96",
					"relation_name9"             => "L:96",
					"relation_name10"            => "L:96",
					"listed_marked"              => "S:1,D",
					"listed_name"                => "L:96",
					"securities_code"            => "L:6",
					"yuho_number"                => "S:6,A",
					"company_stat"               => "D",
					"company_stat_name"          => "L:96",
					"office_stat"                => "D",
					"office_stat_name"           => "L:96",
					"move_office_id"             => "S:11,D",
					"bankruptcy_date"            => "L:6,D",
					"company_vitality"           => "L:3",
					"corporation_name"           => "L:256",
					"corporation_name_kana"      => "L:256,B",
					"office_name"                => "L:256",
					"zip_code"                   => "S:7,D",
					"company_pref_id"            => "S:2,D",
					"company_city_id"            => "S:5,D",
					"address1"                   => "L:256,J",
					"address2"                   => "L:256",
					"address3"                   => "L:256",
					"address4"                   => "L:256",
					"address5"                   => "L:256",
					"address6"                   => "L:256",
					"tel"                        => "L:13,N",
					"fax"                        => "L:13,N",
					"office_number"              => "D",
					"capital_amount"             => "D",
					"representative_title"       => "L:256",
					"representative_name"        => "L:256",
					"representative_kana"        => "L:256,B",
					"industry_code1"             => "L:4,D",
					"industry_name1"             => "L:96",
					"industry_code2"             => "S:4,D",
					"industry_name2"             => "L:96",
					"industry_code3"             => "S:4,D",
					"industry_name3"             => "L:96",
					"license"                    => "L:256,J:/.space",
					"party"                      => "L:256,J:/.space",
					"hp_url"                     => "L:256,B",
					"tel_cc_flag"                => "D",
					"tel_cc_date"                => "S:8,D",
					"move_tel_no"                => "L:13,N",
					"fax_cc_flag"                => "D",
					"fax_cc_date"                => "S:8,D",
					"move_fax_no"                => "L:13,N",
					"inv_date"                   => "S:8,D",
					"employee_number"            => "S:2,D",
					"year_sales"                 => "S:2,D",
					"income_range"               => "S:2,D",
			)
		);
	}
}
?>