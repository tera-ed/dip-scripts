<?php

/**
 * Process 24
 *
 * Corporation-Office matching file　Nayose integration process
 *
 * @author Joedel Espinosa
 *
 */

class Process24 {
	private $logger, $db, $crm_db, $rds_db, $mail, $SKIP_FLAG, $m_table_lbc;
	private $isError = false;

	const WK_T_TABLE = "wk_t_nayose_crm_result";
	const WK_T_TABLE_LINK = "wk_t_lbc_crm_link";

	const T_TABLE_MEDIA = "t_media_mass";
	const T_TABLE_NEGO = "t_negotiation";

	const M_TABLE_CORP = "m_corporation";

	const KEY_UNIQUE = 0;
	const KEY_MULTIPLE = 1;

	const SHELL = "load_wk_t_nayose_crm_result.sh";

	function __construct($logger){
		global $SKIP_FLAG;
		$this->logger = $logger;
		$this->mail = new Mail();
		$this->SKIP_FLAG = $SKIP_FLAG;
	}


	/**
	 *
	 * Initial Process 24
	 *
	 */
	public function execProcess(){
		global $IMPORT_FILENAME, $procNo;

		try {
			// Initialize Database
			$this->db = new Database($this->logger);
			$this->crm_db = new CRMDatabase($this->logger);
			$this->rds_db = new RDSDatabase($this->logger);
			
			$this->m_table_lbc = $this->db->setSchema("m_lbc");
			
			// true を引数にして nayose_csv/Import/after から取得
			$path = getImportPath(true);
			$filename = $IMPORT_FILENAME[$procNo];
			//Acquisition destination: 「./tmp/csv/nayose/Import/after/(yyyymmdd)/YYYYMMDDhhmmss_CRM_Result_Unique.csv」
			$fileUnique = getMultipleCsv($filename[self::KEY_UNIQUE], $path, $procNo);
			//Acquisition destination: 「./tmp/csv/nayose/Import/after/(yyyymmdd)/YYYYMMDDhhmmss_CRM_Result_Multiple.csv」
			$fileMultiple = getMultipleCsv($filename[self::KEY_MULTIPLE], $path, $procNo);
			$importFiles = array($fileUnique, $fileMultiple);

			$this->db->beginTransaction();

			// Deleting Old Data in wk_xxx_table
			$deleteData = $this->db->truncateData(self::WK_T_TABLE);
			$committed = true;
			if($deleteData){
				// load_data シェルによるファイル取り込み
				foreach ($importFiles as $importFile){
					foreach ($importFile as &$file){
						if($committed === true && shellExec($this->logger,self::SHELL,$file,self::WK_T_TABLE) === 0){
							$committed = true;
						} else {
							// shell失敗
							$committed = false;
							$this->db->rollback();
							$this->logger->error("Error File : " . $file);
							throw new Exception("Process24 Failed to insert with ". self::SHELL . " to " . self::WK_T_TABLE);
						}
					}
				}
			}

			if($committed){
				// 取り込み結果のコミット
				$this->db->commit();
				// ロックされている顧客の更新
				//Search whether the corporation_code of the wk_t_nayose_crm_result exists in the t_lock_lbc_link table
					$this->changeDataStatus();

				// wk_t_lbc_crm_linkにまだ存在していない組み合わせのレコードを登録
				//●If the combination of the Corporation-Office inside the file does 
					//not exist in the wk_t_lbc_crm_link, then register a new one in wk_t_lbc_crm_link
					$this->nonExistCombination();


				// wk_t_lbc_crm_link上ですでに誤りと判断された組み合わせは判断対象外、wk_t_lbc_crm_linkへの更新対象外、CSV出力対象外とする
				// update wk_t_nayose_crm_result.nayose_status = 98 
					$this->existCombinationChangeStatus();

				// test20161019 判断処理前までテスト
				//$this->logger->info("判断処理前までテスト");
				//exit(0);

				// N:1　->　1:1　（Decide which corporation is the correct one）
				$this->logger->info("Start N:1 -> 1:1 Process");
				$severalCorpRes = $this->severalCorporations();
				$this->logger->info("End N:1 -> 1:1 Process");

				// test20161019 N対1まででテスト
				//$this->logger->info("N対1処理終了までテスト");
				//exit(0);

				// 1:N　->　1:1　（Decide which LBC is correct）
				$this->logger->info("Start 1:N　->　1:1 Process");
				$serveralLbcRes = $this->severalLbc();
				$this->logger->info("End 1:N　->　1:1 Process");

				// test20161019 1対Nまででテスト
				//$this->logger->info("1対N処理終了まででテスト");
				//exit(0);

				// N対1、1対N処理でエラーが起きなかった場合はCSV作成処理へ （処理件数0件のときはどちらのResも0）
				if($serveralLbcRes > -1 || $severalCorpRes > -1){
					$this->logger->info("Start Generating CSV");
					$this->generateCSV($this->getCSVData());
					$this->logger->info("End Generating CSV");
				}
			}

		} catch (PDOException $e1){
			$this->logger->debug("Error found in Database.");
			// close database connection
			$this->disconnect();
			$this->mail->sendMail($e1->getMessage());
			throw $e1;
		} catch (Exception $e2){
			$this->logger->debug("Error found in Process.");
			$this->logger->error($e2->getMessage());
			// close database connection
			$this->disconnect();
			// If there are no files:
			// Skip the process on and after the corresponding process number
			// and proceed to the next process number (ERR_CODE: 602)
			// For system error pause process
			if(602 != $e2->getCode()) {
				$this->mail->sendMail($e2->getMessage());
				throw $e2;
			}
		}
		if($this->isError){
			// send mail if there is error
			$this->mail->sendMail();
		}
		// close database connection
		$this->disconnect();
	}
	
	private function disconnect(){
		if($this->db){
			// Close Database Connection
			$this->db->disconnect();
		}
		if($this->rds_db) {
			// close database connection
			$this->rds_db->disconnect();
		}
	}

	/*
	 * ロック顧客はnayose_status=1に更新
	 * Next Process After Registering the Data to WK_T_TABLE
	 * Changing the nayose_status = 1
	 */
	private function changeDataStatus(){
		global $MAX_COMMIT_SIZE;
		$dataStatusRes = null; $counter = 0;
		// ファイル内のロック顧客に対する紐付け情報を抽出
		//●Search whether the corporation_code of 
		// the wk_t_nayose_crm_result exists in the t_lock_lbc_link table
		try {
			$sql = "SELECT corporation_code, office_id FROM wk_t_nayose_crm_result wknc
			WHERE EXISTS(
				SELECT 1 FROM t_lock_lbc_link tll 
				WHERE tll.corporation_code = wknc.corporation_code
				AND tll.lock_status = 1 
				AND tll.delete_flag = false
				)"; // lock_status and delete_flag add  20161019 tyamashita
			$list = $this->db->getDataSql($sql);
			foreach($list as $row => &$data){
				if(($counter % $MAX_COMMIT_SIZE) == 0){
					$this->db->beginTransaction();
				}
				$key = array($data["corporation_code"],$data["office_id"]);
				$update = array("nayose_status" => 1);
				$dataStatusRes = $this->db->updateData(self::WK_T_TABLE,$update,"corporation_code = ? AND office_id = ?", $key);
				if($dataStatusRes < 0){
					$this->logger->error("Failed to Update nayose_status => 1.[corporation_code = ".$data["corporation_code"]."][office_id = ".$data["office_id"]."]");
					if($this->SKIP_FLAG == 0){
						$this->isError = true;
						throw new Exception("Process24 Failed to update nayose_status => 1. [corporation_code = ".$data["corporation_code"]."][office_id = ".$data["office_id"]."]");
					}
				} else {
					$this->logger->info("Data Updated nayose_status => 1. [corporation_code = ".$data["corporation_code"]."] is locked");
				}
				$counter++;
				// 最大コミット件数に到達したか、全件処理した場合にコミット
				if(($counter % $MAX_COMMIT_SIZE) == 0 || ($row + 1) == sizeof($list)){
					$this->db->commit();
				}
			}
		} catch(Exception $e){
			$this->logger->error($sql, $e->getMessage());
			throw $e;
		}
		return $counter;
	}

	/*
	 * まだwk_t_lbc_ｃrmlinkに存在しない組み合わせを登録しておく
	 * If the combination of the Corporation-Office inside 
	 * the file does not exist in the wk_t_lbc_crm_link,
	 * then register a new one in wk_t_lbc_crm_link
	 */
	private function nonExistCombination(){
		global $MAX_COMMIT_SIZE;
		$nonExistRes = null; $counter = 0;
		$currentDate = date("Y/m/d H:i:s");
		try {
			$halfbit = ".";
			$sql = "SELECT corporation_code, office_id, result_flg as match_result, 
			CONCAT(IFNULL(detail_lvl,''), '".$halfbit."',IFNULL(detail_content,'')) as match_detail 
			FROM wk_t_nayose_crm_result wknc
			WHERE NOT EXISTS
				(SELECT 1 FROM wk_t_lbc_crm_link wklcl 
					WHERE wknc.corporation_code = wklcl.corporation_code
					AND wknc.office_id = wklcl.office_id
					)";
			$list = $this->db->getDataSql($sql);
			foreach($list as $row => &$data){
				if(($counter % $MAX_COMMIT_SIZE) == 0){
					$this->db->beginTransaction();
					$this->crm_db->beginTransaction();
				}
				$tableList = array(
					"corporation_code"=>$data["corporation_code"],
					"office_id"=>$data["office_id"],
					"match_result"=>$data["match_result"],
					"match_detail"=>$data["match_detail"],
					"name_approach_code"=>$data["corporation_code"],
					"name_approach_office_id"=>$data["office_id"],
					"current_data_flag"=>1,
					"delete_flag"=>null);
				$nonExistRes1 = $this->db->insertData(self::WK_T_TABLE_LINK, $tableList);
				$nonExistRes2 = $this->crm_db->insertData(self::WK_T_TABLE_LINK, $tableList);
				if(!$nonExistRes1 && $nonExistRes2){
					$this->logger->error("Failed to Register [corporation_code : $tableList[corporation_code]] to". self::WK_T_TABLE_LINK);
					if($this->SKIP_FLAG == 0){
						$this->isError = true;
						throw new Exception("Process24 Failed to insert. [corporation_code = ".$data["corporation_code"]."][office_id = ".$data["office_id"]."]");
					}
				} else {
					$this->logger->info("NonExistCombination Data corporation_code : $tableList[corporation_code] office_id : $tableList[office_id] inserted to ". self::WK_T_TABLE_LINK);
				}
				$counter++;
				// 最大コミット件数に到達したか、全件処理した場合にコミット
				if(($counter % $MAX_COMMIT_SIZE) == 0 || ($row + 1) == sizeof($list)){
					$this->db->commit();
					$this->crm_db->commit();
				}
			}
		} catch(Exception $e){
			$this->logger->error($sql, $e->getMessage());
			throw $e;
		}
		return $counter;
	}

	/*
	 * wk_t_lbc_ｃrm_link にすでに存在する組み合わせで削除フラグが立っているものは、
	 * wk_t_nayose_crm_result.nayose_status = 98 を立てて比較対象にせず、wk_t_lbc_crm_link への delete_flagの更新に行かない
	 */
	private function existCombinationChangeStatus(){
		global $MAX_COMMIT_SIZE;
		$changeRes = null; $counter = 0;
		$this->logger->info("すでに誤りと判断された組み合わせの判断対象外処理. start");
		try {
			$halfbit = ".";
			// wk_t_nayose_crm_result にあって、delete_flag is not null でwk_t_lbc_crm_linkに存在するかを検索する
			$sql = "SELECT corporation_code, office_id, result_flg as match_result, 
			CONCAT(IFNULL(detail_lvl,''), '".$halfbit."',IFNULL(detail_content,'')) as match_detail 
			FROM wk_t_nayose_crm_result wknc
			WHERE EXISTS
				(SELECT 1 FROM wk_t_lbc_crm_link wklcl 
					WHERE wknc.corporation_code = wklcl.corporation_code
					AND wknc.office_id = wklcl.office_id
					AND ifnull(delete_flag, 0) > 0 
					)";
		// 取得
		$list = $this->db->getDataSql($sql);
		// 取得した既存削除データを名寄せ比較の対象外にするためnayose_status = 98 を立てる
		foreach($list as $row => &$data){
			if(($counter % $MAX_COMMIT_SIZE) == 0){
				$this->db->beginTransaction();
			}
			$key = array($data["corporation_code"],$data["office_id"]);
			$update = array("nayose_status" => 98);// すでに他と比較されて誤りと判断された組み合わせなので判断対象外
			$changeRes = $this->db->updateData(self::WK_T_TABLE,$update,"corporation_code = ? AND office_id = ?", $key);
			if($changeRes < 0){
				$this->logger->error("Failed to Update nayose_status => 98.[corporation_code = ".$data["corporation_code"]."][office_id = ".$data["office_id"]."]");
				if($this->SKIP_FLAG == 0){
					$this->isError = true;
					throw new Exception("Process24 Failed to update nayose_status => 98. [corporation_code = ".$data["corporation_code"]."][office_id = ".$data["office_id"]."]");
				}
			} else {
				$this->logger->info("Data Updated nayose_status => 98. [corporation_code = ".$data["corporation_code"]."][office_id = ".$data["office_id"]."]");
			}
			$counter++;
			// 最大コミット件数に到達したか、全件処理した場合にコミット
			if(($counter % $MAX_COMMIT_SIZE) == 0 || ($row + 1) == sizeof($list)){
				$this->db->commit();
			}
		}
		$this->logger->info("すでに誤りと判断された組み合わせの判断対象外処理. end");
		} catch(Exception $e){
			$this->logger->error($sql, $e->getMessage());
			throw $e;
		}
		return $counter;
	}

	/*
	 * Nayose Process
	 * N:1　->　1:1　（Decide which corporation is the correct one）
	 *
	 */
	private function severalCorporations(){
		$result = null;
		try {
			//// Search SQL
			// wk_t_nayose_crm_result と m_corporation をUNIONした中から,
			// office_id に複数顧客が紐づくデータを抽出し,
			// 登録されていないレコードがあれば比較対象としてwk_t_nayose_crm_resultに追加する
			//// T: wk_t_nayose_crm_result と m_corporation でCSV上のoffice_idに関連するレコードを全取得
			//// T2: Tと同じものをoffice_idが重複したものだけで絞込み
			//// 最後の not exists 条件の2行で過去に誤りと判断された顧客を除外する 20161201追記
			$searchSql = 
			"SELECT T.office_id, T.corporation_code FROM 
			(SELECT wkn.office_id, wkn.corporation_code FROM wk_t_nayose_crm_result wkn
			WHERE wkn.nayose_status IS NULL
			UNION
			SELECT wkn.office_id, mc.corporation_code FROM wk_t_nayose_crm_result wkn
			INNER JOIN m_corporation mc ON wkn.office_id = mc.office_id
			AND wkn.nayose_status IS NULL AND mc.delete_flag = FALSE) T
			WHERE T.office_id IN
			(SELECT office_id FROM
			(SELECT wkn.office_id, wkn.corporation_code FROM wk_t_nayose_crm_result wkn
			WHERE wkn.nayose_status IS NULL
			UNION ALL
			SELECT wkn.office_id, wkn.corporation_code FROM wk_t_nayose_crm_result wkn
			INNER JOIN m_corporation mc ON wkn.office_id = mc.office_id 
			AND wkn.nayose_status IS NULL AND mc.delete_flag = FALSE) T2
			GROUP BY office_id HAVING COUNT(0) > 1
			) 
			AND not exists(select 1 from wk_t_lbc_crm_link wtlcl 
				where wtlcl.delete_flag > 0 and wtlcl.corporation_code = T.corporation_code and wtlcl.office_id = T.office_id) 
			ORDER BY office_id,corporation_code";

			// $searchSql を取得することでN対1の判断データがすべてwk_t_nayose_crm_resultに揃うので、
			// Process2と3はwk_t_nayose_crm_resultからoffice_id に複数顧客が紐づくデータを抽出
			$searchNayoseResultSql = 
			"SELECT wkn.office_id, wkn.corporation_code 
			FROM wk_t_nayose_crm_result wkn
			WHERE wkn.nayose_status IS NULL
			AND wkn.office_id IN
			(SELECT office_id FROM wk_t_nayose_crm_result 
			WHERE nayose_status IS NULL
			GROUP BY office_id HAVING COUNT(0) > 1
			) 
			ORDER BY office_id,corporation_code";

			// LBCに紐づく最小のLBCを取得
			$receiveSql =
			"SELECT office_id, min(corporation_code) 
			FROM wk_t_nayose_crm_result wkcr 
			WHERE nayose_status IS NULL 
			group by office_id";

			// N対1のデータが存在するか確認
			$N1resultSql = 
			"SELECT office_id 
			FROM wk_t_nayose_crm_result 
			WHERE nayose_status is null 
			GROUP BY office_id HAVING count(0) > 1";

			$this->logger->debug("Start Process1");
			$list = $this->db->getDataSql($searchSql);
			if(count($list) > 0){
				$this->logger->debug("Start m_corporation data add");
				$this->nayoseProcessCorp($list,0);// 既存のm_corporationにある組み合わせとoffice_idがかぶった場合、wk_t_nayoseに無ければ登録
				$this->logger->debug("end m_corporation data add");
				$this->nayoseProcessCorp($list,1);
				$this->logger->debug("End Process1");
			}else{
				$this->logger->debug("N:1 処理データ 0件");
				$this->logger->debug("End Process1");
			}

			$list = $this->db->getDataSql($searchNayoseResultSql);
			if(count($list) > 0){
				$this->logger->debug("Start Process2");
				$this->nayoseProcessCorp($list,2);
				$this->logger->debug("End Process2");
			}

			$list = $this->db->getDataSql($searchNayoseResultSql);
			if(count($list) > 0){
				$this->logger->debug("Start Process3");
				$this->nayoseProcessCorp($list,3);
				$this->logger->debug("End Process3");
			}

			$listData = $this->db->getDataSql($receiveSql);
			if(count($listData) > 0){
				$this->logger->debug("Start Process4");
				$this->nayoseProcessCorp($listData,4);
				$this->logger->debug("End Process4");
			}

			// N対1データがすべて処理されたか確認
			$N1resultList = $this->db->getDataSql($N1resultSql);
			if(count($N1resultList) > 0){
				$this->logger->error("N対1データが残っているため処理を中断します");
				$this->logger->error(print_r($N1resultList,true));
				throw new Exception("Process24 N対1 -> 1対1 Failed on database Table ".self::WK_T_TABLE_LINK);
			}else{
				$this->logger->info("N対1 -> 1対1 処理完了. N対1データ件数0件");
			}

			//Search for the records where the nayose_status value is 10 and above
			// N対1で判断された名寄せ先顧客コードの結果をwk_t_lbc_crm_linkに登録
			$query = $this->db->getData("office_id,corporation_code,nayose_status",self::WK_T_TABLE,"nayose_status >= 10 and nayose_status <= 19");
			if(count($query) > 0){
				foreach($query as $data){
					$this->db->beginTransaction();
					$this->crm_db->beginTransaction();
					
					$params = array($data["corporation_code"], $data["office_id"]);
					// 名寄せ先顧客コードをwk_t_nayose_crm_resultから取得
					$name_approach_code = $this->db->getData("corporation_code",self::WK_T_TABLE,"nayose_status IS NULL AND office_id = ?", array($data["office_id"]));
					// wk_t_nayose_crm_resultの中から名寄せ先顧客コードが見つからなかった場合はm_corporation上のデータを確認
					// wk_t_nayose_crm_resultになく、m_corporation上に存在するようならその顧客コードに名寄せ
					if(count($name_approach_code) <= 0){
						$crm_corp_code = $this->rds_db->getData("corporation_code",self::M_TABLE_CORP,"office_id = ? and delete_flag = false", array($data["office_id"]));
						if (count($crm_corp_code) > 0) {
							$name_approach_code = $crm_corp_code;
						}
					}
					if(count($name_approach_code) > 0){
						$update = array(
						"current_data_flag"=>0,
						"name_approach_code"=> $name_approach_code[0]["corporation_code"],
						"name_approach_office_id"=> $data["office_id"],
						"delete_flag"=>$data["nayose_status"]);// 誤り理由
						$result1 = $this->db->updateData(self::WK_T_TABLE_LINK,$update,"corporation_code = ? AND office_id = ?",$params);
						$result2 = $this->crm_db->updateData(self::WK_T_TABLE_LINK,$update,"corporation_code = ? AND office_id = ?",$params);
						if($result1 < 0){
							$this->isError = true;
							$this->logger->error("N対1 Failed to Update. [Corporation_code : ".$data["corporation_code"]."][office_id : ".$data["office_id"]."]");
							throw new Exception("Process24 N対1 Update Failed on database Table ".self::WK_T_TABLE_LINK." [Corporation_code : ".$data["corporation_code"]."][office_id : ".$data["office_id"]."]");
						} else {
							// 名寄せ情報更新に成功
							$this->logger->info("N対1 Data Updated to wk_t_lbc_crm_link. [Corporation_code : ".$data["corporation_code"]."][office_id : ".$data["office_id"]
								."] -> [Corporation_code : ".$name_approach_code[0]["corporation_code"]."][office_id : ".$data["office_id"]."]");
							// 階層的な名寄せにならないように、更新されたレコードに名寄せされるはずだったレコードの名寄せ情報を、新しい名寄せ先に更新
							$params = array($data["corporation_code"], $data["office_id"]);
							$update = array(
							"current_data_flag"=>0,
							"name_approach_code"=> $name_approach_code[0]["corporation_code"],
							"name_approach_office_id"=> $data["office_id"]);
							$updateResult1 = $this->db->updateData(self::WK_T_TABLE_LINK,$update,"name_approach_code = ? AND name_approach_office_id = ?",$params);
							$updateResult2 = $this->crm_db->updateData(self::WK_T_TABLE_LINK,$update,"name_approach_code = ? AND name_approach_office_id = ?",$params);
							if($updateResult1 < 0){
								$this->isError = true;
								$this->logger->error("N対1 Failed to name_approach Update. [name_approach_code : ".$data["corporation_code"]."][name_approach_office_id : ".$data["office_id"]."]");
								throw new Exception("Process24 N対1 Update Failed on database Table ".self::WK_T_TABLE_LINK." [name_approach_code : ".$data["corporation_code"]."][name_approach_office_id : ".$data["office_id"]."]");
							}else{
								// 名寄せの階層構造除去成功
								$this->logger->info("N対1 Data Updated to wk_t_lbc_crm_link name_approach. [name_approach_code : ".$data["corporation_code"]."][name_approach_office_id : ".$data["office_id"]
								."] -> [name_approach_code : ".$name_approach_code[0]["corporation_code"]."][name_approach_office_id : ".$data["office_id"]."]");
							}
						}
					}else{
						// wk_t_nayose_crm_resultからもm_corporationからも名寄せ先が見つからなかった場合はエラー
						$this->isError = true;
						$this->logger->error("N対1->1対1で判断されたデータの名寄せ先が見つかりませんでした。 [Corporation_code : ".$data["corporation_code"]."][office_id : ".$data["office_id"]."]");
						throw new Exception("Process24 Update Failed on database Table ".self::WK_T_TABLE_LINK);
					}
					$this->db->commit();
					$this->crm_db->commit();
				}
			}else if(count($query) == 0){ // 空CSVが作られるように処理データ0件の場合はresultを0で返す
				$result = 0;
			}
		} catch(Exception $e){
			$this->logger->error($searchSql, $e->getMessage());
			throw $e;
		}
		return $result;
	}

	/*
	 * Nayose Process
	 * 1:N -> 1:1 （Decide which LBC is correct）
	 */
	private function severalLbc(){
		$result = null;
		try{
			$sql = 
			" SELECT * FROM wk_t_nayose_crm_result 
			 WHERE nayose_status IS NULL AND corporation_code IN
			 (SELECT corporation_code FROM wk_t_nayose_crm_result 
			 WHERE nayose_status IS NULL 
			 GROUP BY corporation_code HAVING COUNT(0) >= 2) 
			 ORDER BY corporation_code";

			// 1対Nのデータが存在するか確認
			$result1NSql = 
			"SELECT corporation_code 
			FROM wk_t_nayose_crm_result 
			WHERE nayose_status is null 
			GROUP BY corporation_code HAVING count(0) > 1";

			$this->logger->info("Start Process1");
			$list = $this->db->getDataSql($sql);
			if(count($list) > 0){
				$this->nayoseProcessLBC($list,1);
				$this->logger->info("End Process1");
			}else{
				$this->logger->info("1:N 処理データ 0件");
				$this->logger->info("End Process1");
			}

			$list = $this->db->getDataSql($sql);
			if(count($list) > 0){
				$this->logger->info("Start Process2");
				$this->nayoseProcessLBC($list,2);
				$this->logger->info("End Process2");
			}

			$list = $this->db->getDataSql($sql);
			if(count($list) > 0){
				$this->logger->info("Start Process3");
				$this->nayoseProcessLBC($list,3);
				$this->logger->info("End Process3");
			}

			$list = $this->db->getDataSql($sql);
			if(count($list) > 0){
				$this->logger->info("Start Process4");
				$this->nayoseProcessLBC($list,4);
				$this->logger->info("End Process4");
			}

			$list = $this->db->getDataSql($sql);
			if(count($list) > 0){
				$this->logger->info("Start Process5");
				$this->nayoseProcessLBC($list,5);
				$this->logger->info("End Process5");
			}

			$list = $this->db->getDataSql($sql);
			if(count($list) > 0){
				$this->logger->info("Start Process6");
				$this->nayoseProcessLBC($list,6);
				$this->logger->info("End Process6");
			}

			// 1対Nデータがすべて処理されたか確認
			$result1NList = $this->db->getDataSql($result1NSql);
			if(count($result1NList) > 0){
				$this->logger->error("1対Nデータが残っているため処理を中断します");
				$this->logger->error(print_r($result1NList,true));
				throw new Exception("Process24 1対N -> 1対1 Failed on database Table ".self::WK_T_TABLE_LINK);
			}else{
				$this->logger->info("1対N -> 1対1 処理完了. 1対Nデータ件数0件");
			}

			//Search for the records where the nayose_status value is 10 and above
			// 1対Nで判断された名寄せ先LBCの結果をwk_t_lbc_crm_linkに登録
			$query = $this->db->getData("office_id,corporation_code,nayose_status",self::WK_T_TABLE,"1 < nayose_status and nayose_status < 10");
			if(count($query) > 0){
				foreach($query as $data){
					$this->db->beginTransaction();
					$this->crm_db->beginTransaction();
					
					$params = array($data["corporation_code"], $data["office_id"]);
					// 他に名寄せされたらcurrent_data_flag=0にする
					$current_data_flag = 0;
					// 名寄せ先LBCコード取得
					$name_approach_office_id = $this->db->getData("office_id",self::WK_T_TABLE,"nayose_status IS NULL AND corporation_code = ?", array($data["corporation_code"]));
					// Gマッチ判定は名寄せ先が見つからない場合があるのでその場合は自身のコードで埋める
					if(count($name_approach_office_id) == 0 && $data["nayose_status"] == 2 ){
						$name_approach_office_id[0]["office_id"] = $data["office_id"];
						// どこにも名寄せされていないのでcurrent_data_flag = 1
						$current_data_flag = 1;
					}

					if(count($name_approach_office_id) > 0){
						$update = array(
						"current_data_flag"=>$current_data_flag,// 名寄せされたら0にする
						"name_approach_code"=> $data["corporation_code"],// LBCの名寄せなのでcorporation_codeは同じものを入れる
						"name_approach_office_id"=> $name_approach_office_id[0]["office_id"],// 名寄せ先のLBCコード
						"delete_flag"=>$data["nayose_status"]);// 誤り理由 Gマッチ判定のレコードはwk_t_lbc_crm_linkのdelete_flagを2で登録しておく （Process31の削除用）
						// 登録
						$result1 = $this->db->updateData(self::WK_T_TABLE_LINK,$update,"corporation_code = ? AND office_id = ?",$params);
						$result2 = $this->crm_db->updateData(self::WK_T_TABLE_LINK,$update,"corporation_code = ? AND office_id = ?",$params);
						if($result1 < 0){
							$this->isError = true;
							$this->logger->error("Failed to 1対N Data Update.  [Corporation_code : ".$data["corporation_code"]."][office_id : ".$data["office_id"]."]");
							throw new Exception("Process24 1対N Data Update Failed on database Table ".self::WK_T_TABLE_LINK."[Corporation_code : ".$data["corporation_code"]."][office_id : ".$data["office_id"]."]");
						} else {
							$this->logger->info("1対N Data Updated to wk_t_lbc_crm_link. [Corporation_code : ".$data["corporation_code"]."][office_id : ".$data["office_id"]
								."] -> [Corporation_code : ".$data["corporation_code"]."][office_id : ".$name_approach_office_id[0]["office_id"]."]");
							// 階層的な名寄せにならないように、更新されたレコードに名寄せされるはずだったレコードの名寄せ情報を、新しい名寄せ先に更新
							$params = array($data["corporation_code"], $data["office_id"]);
							$update = array(
							"current_data_flag"=>0,// 名寄せされたので0にする
							"name_approach_code"=> $data["corporation_code"],// LBCの名寄せなのでcorporation_codeは同じものを入れる
							"name_approach_office_id"=> $name_approach_office_id[0]["office_id"]);// 名寄せ先のLBCコード
							$updateResult1 = $this->db->updateData(self::WK_T_TABLE_LINK,$update,"name_approach_code = ? AND name_approach_office_id = ?",$params);
							$updateResult2 = $this->crm_db->updateData(self::WK_T_TABLE_LINK,$update,"name_approach_code = ? AND name_approach_office_id = ?",$params);
							if($updateResult1 < 0){
								$this->isError = true;
								$this->logger->error("Failed to 1対N Data name_approach Update. [name_approach_code : ".$data["corporation_code"]."][name_approach_office_id : ".$data["office_id"]."]");
								throw new Exception("Process24  1対N Data Update Failed on database Table ".self::WK_T_TABLE_LINK."[name_approach_code : ".$data["corporation_code"]."][name_approach_office_id : ".$data["office_id"]."]");
							}else{
								// 名寄せの階層構造除去成功
								$this->logger->info("1対N Data Updated to wk_t_lbc_crm_link name_approach. [name_approach_code : ".$data["corporation_code"]."][name_approach_office_id : ".$data["office_id"]
								."] -> [name_approach_code : ".$data["corporation_code"]."][name_approach_office_id : ".$name_approach_office_id[0]["office_id"]."]");
							}
						}

					}else{
						// wk_t_nayose_crm_resultから名寄せ先が見つからなかった場合はエラー
						$this->isError = true;
						$this->logger->error("1対N->1対1処理で判断されたデータの名寄せ先が見つかりませんでした。 [Corporation_code : ".$data["corporation_code"]."][office_id : ".$data["office_id"]."]");
						throw new Exception("Process24 Update Failed on database Table ".self::WK_T_TABLE_LINK);
					}
					$this->db->commit();
					$this->crm_db->commit();
				}
			}else if(count($query) == 0){ // 空CSVが作られるように処理データ0件の場合はresultを0で返す
				$result = 0;
			}
		} catch (Exception $e){
			$this->logger->error($sql,$e->getMessage());
			throw $e;
		}
		return $result;
	}

	/**
	 * そのLBCに紐づく正しい顧客の判断処理
	 * LBCコードに対して複数の顧客コードが紐づいている組み合わせを抽出してこの関数で1対1になるまで処理
	 * @param $list array of acquired Data (order by office_id)
	 * @param $process Type of number to be process.
	 */
	private function nayoseProcessCorp($list, $process){
		try {
			$nayoseResult = null;
			if($process == '0'){// あとのwk_t_lbc_crm_linkの更新のために、顧客コードが複数紐づいているものの中で、wk_t_nayoseに無くm_corporationに今ある組み合わせを登録
				foreach($list as $key=>$data){
					// wk_t_nayose_crm_resultにいないか確認
					$existResult = $this->db->getDataCount(self::WK_T_TABLE, "corporation_code = ? AND office_id = ? ", array($data["corporation_code"],$data["office_id"]) );
					if($existResult == 0){
						// いなければ登録
						$this->db->beginTransaction();
						$tableList = array(
							"corporation_code"=>$data["corporation_code"],
							"office_id"=>$data["office_id"],
							"result_flg"=>null,
							"detail_lvl"=>null,
							"detail_content"=>null,
							"delete_flag"=>1);// N対1判断のためにm_corporationからインサートしたレコードは、正顧客となっても出力しない
						$nonExistCorp = $this->db->insertData(self::WK_T_TABLE, $tableList);
						if(!$nonExistCorp){
							$this->logger->error("Failed to Register [corporation_code : $tableList[corporation_code]] to". self::WK_T_TABLE);
							if($this->SKIP_FLAG == 0){
								$this->isError = true;
								throw new Exception("Process24 Failed to insert. [corporation_code = ".$data["corporation_code"]."][office_id = ".$data["office_id"]."]");
							}
						} else {
							$this->logger->info("NonExistCombination Data corporation_code : $tableList[corporation_code] office_id : $tableList[office_id] inserted to ". self::WK_T_TABLE." from m_corporation");
						}
						$this->db->commit();
					}
				}
			} else if($process == '1'){// N対1 他媒体数は多いほうが正
				foreach($list as $key=>$data){
					$list[$key]["tabaitai_count"] = $this->rds_db->getDataCount(self::T_TABLE_MEDIA, "corporation_code = ? AND delete_flag = false ", array($list[$key]["corporation_code"]));
				}
				// 並び替える列の列方向の配列を得る
				foreach ($list as $key => $row) {
					$volume[$key] = $row['office_id'];
					$edition[$key] = $row['tabaitai_count'];
				}
				// office_idの昇順、他媒体数の降順で並び替え
				array_multisort($volume, SORT_ASC, $edition, SORT_DESC, $list);
				// office_idの順番に並んでいてほしいのでsortはしない
				//$newList = $this->orderBy($list,'tabaitai_count');
				//$newList = array_reverse($newList);

				$changeBase = false;
				// リストの最初のレコードを基準レコードとする
				$base_corporation_code = $list[0]["corporation_code"];
				$base_office_id = $list[0]["office_id"];
				$base_tabaitai_count = $list[0]["tabaitai_count"];
				$update = array("nayose_status" => 11);

				foreach($list as $key=>$data){
					$this->db->beginTransaction();

					// 処理レコードの情報を格納
					$procCorp_code = $list[$key]["corporation_code"];
					$procOff_id = $list[$key]["office_id"];
					$procTabaitai_count = $list[$key]["tabaitai_count"];

					// 基準レコードと処理レコードのoffice_idが同じ場合はどちらの顧客コードが正しいか判断
					if($base_office_id == $procOff_id){
						if($base_tabaitai_count > $procTabaitai_count){
							// 基準レコードの方が正しかった場合は処理レコードの名寄せステータスを11に更新
							$params = array($procCorp_code,$procOff_id);
							$result = $this->db->updateData(self::WK_T_TABLE,$update,"corporation_code = ? AND office_id = ? AND nayose_status IS NULL", $params);
							if($result < 0){
								$this->logger->error("Failed to Update. [Corporation_code : ".$procCorp_code."][office_id : ".$procOff_id."]");
								if($this->SKIP_FLAG == 0){
									$this->isError = true;
									throw new Exception("Process24 Failed to update. [Corporation_code : ".$procCorp_code."][office_id : ".$procOff_id."]");
								}
							} else {
								$this->logger->info("Data Updated nayose_status => 11. [Corporation_code : ".$procCorp_code."][office_id : ".$procOff_id."]");
							}
						} else if($base_tabaitai_count < $procTabaitai_count){
							// 処理レコードの方が正しかった場合は基準レコードの名寄せステータスを11に更新
							$params = array($base_corporation_code,$base_office_id);
							$result = $this->db->updateData(self::WK_T_TABLE,$update,"corporation_code = ? AND office_id = ? AND nayose_status IS NULL", $params);
							if($result < 0){
								$this->logger->error("Failed to Update. [Corporation_code : ".$base_corporation_code."][office_id : ".$base_office_id."]");
								if($this->SKIP_FLAG == 0){
									$this->isError = true;
									throw new Exception("Process24 Failed to update. [Corporation_code : ".$base_corporation_code."][office_id : ".$base_office_id."]");
								}
							} else {
								$this->logger->info("Data Updated nayose_status => 11. [Corporation_code : ".$base_corporation_code."][office_id : ".$base_office_id."]");
							}
							//Make the processed record the standard
							// 処理レコードの方が正しかったので次のレコードとの比較基準にはそちらを使う
							$base_corporation_code = $procCorp_code;
							$base_office_id = $procOff_id;
							$base_tabaitai_count = $procTabaitai_count;
						} else if ($base_tabaitai_count == $procTabaitai_count){
							// 同じだった場合は次の判断に任せてここでは何もしない
							$this->logger->info("Data no Updated. [Corporation_code : ".$procCorp_code."][office_id : ".$procOff_id."]");
						}
					} else {
						//Make the processed record the standard
						// 基準レコードと処理レコードのoffice_idが異なる場合は次のoffice_idの判断に行くために今の処理レコードを基準レコードにする
						$base_corporation_code = $procCorp_code;
						$base_office_id = $procOff_id;
						$base_tabaitai_count = $procTabaitai_count;
					}
					$this->db->commit();
				}
				$this->logger->info("N:1 Process1 Done.");
			} else if($process == '2'){// N対1 商談数は多いほうが正
				// 商談数の取得
				foreach($list as $key=>$data){
					$tabaitai_count = $this->rds_db->getDataCount(self::T_TABLE_MEDIA, "corporation_code = ? AND delete_flag = false ", array($list[$key]["corporation_code"]));
					$nego_count = $this->rds_db->getDataCount(self::T_TABLE_NEGO,"corporation_code = ? AND record_type IN (70,71,72) AND delete_flag = false", array($list[$key]["corporation_code"]));
					$list[$key]["sum_count"] = $tabaitai_count + $nego_count;
				}
				// 並び替える列の列方向の配列を得る
				foreach ($list as $key => $row) {
					$volume[$key] = $row['office_id'];
					$edition[$key] = $row['sum_count'];
				}
				// office_idの昇順、（他媒体数＋商談数）の降順で並び替え
				array_multisort($volume, SORT_ASC, $edition, SORT_DESC, $list);

				$changeBase = false;
				// リストの最初のレコードを基準レコードとする
				$base_office_id = $list[0]["office_id"];
				$base_corporation_code = $list[0]["corporation_code"];
				$base_sum_count = $list[0]["sum_count"];
				$update = array("nayose_status" => 12);

				// 比較開始
				foreach($list as $key=>$data){
					$arrayCount = count($list);
					$this->db->beginTransaction();

					$procOff_id = $list[$key]["office_id"];
					$procCorp_code = $list[$key]["corporation_code"];
					$proc_sum_count = $list[$key]["sum_count"];

					// 基準レコードと処理レコードのoffice_idが同じ場合はどちらの顧客コードが正しいか判断
					if($base_office_id == $procOff_id){
						if($base_sum_count > $proc_sum_count){
							// 基準レコードが正の場合は処理レコードのnayose_status => 12
							$params = array($procCorp_code,$procOff_id);
							$result = $this->db->updateData(self::WK_T_TABLE,$update,"corporation_code = ? AND office_id = ? AND nayose_status IS NULL", $params);
							if($result < 0){
								$this->logger->error("Failed to Update. [Corporation_code : ".$procCorp_code."][office_id : ".$procOff_id."]");
								if($this->SKIP_FLAG == 0){
									$this->isError = true;
									throw new Exception("Process24 Failed to update. [Corporation_code : ".$procCorp_code."][office_id : ".$procOff_id."]");
								}
							} else {
								$this->logger->info("Data Updated nayose_status => 12. [Corporation_code : ".$procCorp_code."][office_id : ".$procOff_id."]");
							}
						} else if($base_sum_count < $proc_sum_count){
							// 処理レコードが正の場合は基準レコードのnayose_status => 12
							$params = array($base_corporation_code,$base_office_id);
							$result = $this->db->updateData(self::WK_T_TABLE,$update,"corporation_code = ? AND office_id = ? AND nayose_status IS NULL", $params);
							if($result < 0){
								$this->logger->error("Failed to Update. [Corporation_code : ".$base_corporation_code."][office_id : ".$base_office_id."]");
								if($this->SKIP_FLAG == 0){
									$this->isError = true;
									throw new Exception("Process24 Failed to update. [Corporation_code : ".$base_corporation_code."][office_id : ".$base_office_id."]");
								}
							} else {
								$this->logger->info("Data Updated nayose_status => 12. [Corporation_code : ".$base_corporation_code."][office_id : ".$base_office_id."]");
							}
							//Make the processed record the standard
							// 処理レコードの方が正しかったので次のレコードとの比較基準にはそちらを使う
							$base_corporation_code = $procCorp_code;
							$base_office_id = $procOff_id;
							$base_sum_count = $proc_sum_count;
						} else if ($base_sum_count == $proc_sum_count){
							// 同じだった場合は次の判断に任せてここでは何もしない
							$this->logger->info("Data no Updated. [Corporation_code : ".$base_corporation_code."][office_id : ".$base_office_id."]");
						}
					} else {
						// 基準レコードと処理レコードのoffice_idが異なる場合は次のoffice_idの判断に行くために今の処理レコードを基準レコードにする
						$base_corporation_code = $procCorp_code;
						$base_office_id = $procOff_id;
						$base_sum_count = $proc_sum_count;
					}
					$this->db->commit();
				}
				$this->logger->info("N:1 Process2 Done.");
			} else if($process == '3'){// 顧客の最終更新日付は古いものが正
				foreach($list as $key=>$data){
					$updateDate = $this->rds_db->getData("update_date",self::M_TABLE_CORP,"corporation_code = ? AND delete_flag = 0 ", array($list[$key]["corporation_code"]));
					// 顧客テーブルに存在しない場合はupdate_date取れないので
					if(sizeof($updateDate) > 0){
						$list[$key]["update_date"] = $updateDate[0]["update_date"];
					}else{
						// 2017/10/19 ログレベルを修正（ERROR→INFO）
						$this->logger->info("Data not found in m_corporation. [Corporation_code : ".$list[$key]["corporation_code"]."]");
						$list[$key]["update_date"] = null;
					}
				}
				// 並び替える列の列方向の配列を得る
				foreach ($list as $key => $row) {
					$volume[$key] = $row['office_id'];
					$edition[$key] = $row['update_date'];
				}
				// office_idの昇順、更新日付の昇順（古いものから）で並び替え
				array_multisort($volume, SORT_ASC, $edition, SORT_ASC, $list);

				$changeBase = false;
				// リストの最初のレコードを基準レコードとする
				$base_corporation_code = $list[0]["corporation_code"];
				$base_office_id = $list[0]["office_id"];
				$base_update_date = $list[0]["update_date"];
				$update = array("nayose_status" => 13);

				foreach($list as $key=>$data){
					$arrayCount = count($list);
					$this->db->beginTransaction();

					$procCorp_code = $list[$key]["corporation_code"];
					$procOff_id = $list[$key]["office_id"];
					$process_update_date = $list[$key]["update_date"];

					// 基準レコードと処理レコードのoffice_idが同じ場合はどちらの顧客コードが正しいか判断(最終更新日が古いものが正)
					// 片方がNULLの場合はもう片方が正
					if($base_office_id == $procOff_id){
						if( ($base_update_date != null && $process_update_date == null) ||
						 ($base_update_date != null && $process_update_date != null && strtotime($base_update_date) < strtotime($process_update_date)) ){
							// 基準レコードが正の場合は処理レコードのnayose_status => 13
							$params = array($procCorp_code,$procOff_id);
							$result = $this->db->updateData(self::WK_T_TABLE,$update,"corporation_code = ? AND office_id = ? AND nayose_status IS NULL", $params);
							if($result < 0){
								$this->logger->error("Failed to Update. [Corporation_code : ".$procCorp_code."][office_id : ".$procOff_id."]");
								if($this->SKIP_FLAG == 0){
									$this->isError = true;
									throw new Exception("Process24 Failed to update. [Corporation_code : ".$procCorp_code."][office_id : ".$procOff_id."]");
								}
							} else {
								$this->logger->info("Data Updated nayose_status => 13. [Corporation_code : ".$procCorp_code."][office_id : ".$procOff_id."]");
							}
						} else if( ($base_update_date == null && $process_update_date != null) ||
						 ($base_update_date != null && $process_update_date != null && strtotime($base_update_date) > strtotime($process_update_date)) ){
							// 処理レコードが正の場合は基準レコードのnayose_status => 13
							$params = array($base_corporation_code,$base_office_id);
							$result = $this->db->updateData(self::WK_T_TABLE,$update,"corporation_code = ? AND office_id = ? AND nayose_status IS NULL", $params);
							if($result < 0){
								$this->logger->error("Failed to Update. [Corporation_code : ".$base_corporation_code."][office_id : ".$base_office_id."]");
								if($this->SKIP_FLAG == 0){
									$this->isError = true;
									throw new Exception("Process24 Failed to update. [Corporation_code : ".$base_corporation_code."][office_id : ".$base_office_id."]");
								}
							} else {
								$this->logger->info("Data Updated nayose_status => 13. [Corporation_code : ".$base_corporation_code."][office_id : ".$base_office_id."]");
							}
							//Make the processed record the standard
							// 処理レコードの方が正しかったので次のレコードとの比較基準にはそちらを使う
							$base_corporation_code = $procCorp_code;
							$base_office_id = $procOff_id;
							$base_update_date = $process_update_date;
						} else if (strtotime($base_update_date) == strtotime($process_update_date)){
							// 同じだった場合は次の判断に任せてここでは何もしない
							$this->logger->info("Data no Updated. [Corporation_code : ".$base_corporation_code."][office_id : ".$base_office_id."]");
						}
					} else {
						// 基準レコードと処理レコードのoffice_idが異なる場合は次のoffice_idの判断に行くために今の処理レコードを基準レコードにする
						$base_corporation_code = $procCorp_code;
						$base_office_id = $procOff_id;
						$base_update_date = $process_update_date;
					}
					$this->db->commit();
				}
				$this->logger->info("N:1 Process3 Done.");
			} else if($process == '4'){// 顧客コードは最小を正とする
				//$newList = $this->orderBy($list,'corporation_code');
				foreach($list as $key=>$data){
					$this->db->beginTransaction();
					$params = array($list[$key]["office_id"],$list[$key]["min(corporation_code)"]);
					$update = array("nayose_status" => 19);
					$result = $this->db->updateData(self::WK_T_TABLE,$update,"office_id = ? AND corporation_code != ? AND nayose_status IS NULL", $params);
					if($result < 0){
						$this->logger->error("Failed to Update. [Corporation_code : ".$list[$key]["min(corporation_code)"]."][office_id : ".$list[$key]["office_id"]."]");
						if($this->SKIP_FLAG == 0){
							$this->isError = true;
							throw new Exception("Process24 Failed to update. [Corporation_code : ".$list[$key]["min(corporation_code)"]."][office_id : ".$list[$key]["office_id"]."]");
						}
					} else if ($result == 0) { 
						$this->logger->info("No Changes in Database. [Corporation_code : ".$list[$key]["min(corporation_code)"]."][office_id : ".$list[$key]["office_id"]."]");
					} else {
						$this->logger->info("Data Updated nayose_status => 19.  [Corporation_code : ".$list[$key]["min(corporation_code)"]."][office_id : ".$list[$key]["office_id"]."]");
					}
					$this->db->commit();
				}
			}
		} catch (Exception $e){
			$this->logger->error($sql, $e->getMessage());
			throw $e;
		}
	}

	/**
	 * 顧客に紐づく正しいLBCの判断処理
	 * 顧客コードに対して複数LBCコードが紐づいている組み合わせを抽出してこの関数で1対1になるまで処理
	 * 判断材料は基本的にm_lbcから取得するがまだ登録されていない可能性もあるのでwk_t_nayose_crm_resultからも確認する
	 * @param $list array of Acquired Data (order by corporation_code)
	 * @param $process Type of number to be process
	 */
	private function nayoseProcessLBC($list, $process){
		//1、Place nayose_status = 2 on the records of 
		// the wk_t_nayose_crm_result where match_result = G
		if($process == 1){
			foreach($list as $data){
				$this->db->beginTransaction();
				$params = array($data["corporation_code"]);
				$update = array("nayose_status"=> 2);
				// マッチング結果がGのレコードは処理対象外にする
				$result = $this->db->updateData(self::WK_T_TABLE,$update,"corporation_code = ? AND result_flg = 'G' AND nayose_status IS NULL ", $params);
				if($result < 0){
					$this->logger->error("Failed to Updated Nayose_status = 2. corporation_code:".$data["corporation_code"]." and office_id:".$data["office_id"]);
					if($this->SKIP_FLAG == 0){
						$this->isError = true;
						throw new Exception("Process24 Failed to updated Nayose_status = 2. For corporation_code:".$data["corporation_code"]." and office_id:".$data["office_id"]);
					}
				} else if($result == 0) {
					// 処理対象データなし
					$this->logger->info("No Data Match nayose_status = 2. Corporation_code: ".$data["corporation_code"]." - Office_id:".$data["office_id"]);
				} else {
					$this->logger->info("Data Updated nayose_status = 2. Corporation_code:".$data["corporation_code"]." - Office_id:".$data["office_id"]);
				}
				$this->db->commit();
			}

		// Start of Process 2-0 -> 2-3
		} else if ($process == 2){
			foreach($list as $data){
				$this->db->beginTransaction();
				//$params = array($data["office_id"],$data["office_id"]);
				$params = array($data["corporation_code"]);
				$update = array("nayose_status"=> 3);
				//Among the records in 2-0, search for the records where the 
				//LBC(office_id) and 親会社LBC(top_head_office_id) are the same from the m_lbc 

				// 2-1 Office_id == top_head_office_id
				$sameList = $this->db->getData("nayose.corporation_code,nayose.office_id",
				self::WK_T_TABLE." as nayose INNER JOIN ".$this->m_table_lbc." ml ON nayose.office_id = ml.office_id ",
				" nayose.corporation_code = ? and nayose.nayose_status is null and ifnull(ml.office_id,'') = ifnull(ml.top_head_office_id,'') ", $params);
				// 今回のバッチで新規登録されるLBCの場合はm_lbcにまだ無い可能性があるので、p23のwk_t_nayose_lbc_sbndata_outputからも検索
				if(count($sameList) <= 0){
					$sameList = $this->db->getData("nayose.corporation_code,nayose.office_id",
					self::WK_T_TABLE." as nayose INNER JOIN wk_t_nayose_lbc_sbndata_output ml ON nayose.office_id = ml.office_id ",
					" nayose.corporation_code = ? and nayose.nayose_status is null and ifnull(ml.office_id,'') = ifnull(ml.top_head_office_id,'') ", $params);
				}

				// 2-2 Office_id != top_head_office_id
				$diffList = $this->db->getData("nayose.corporation_code,nayose.office_id",
				self::WK_T_TABLE." as nayose INNER JOIN ".$this->m_table_lbc." ml ON nayose.office_id = ml.office_id ",
				" nayose.corporation_code = ? and nayose.nayose_status is null and ifnull(ml.office_id,'') != ifnull(ml.top_head_office_id,'') ", $params);
				// 今回のバッチで新規登録されるLBCの場合はm_lbcにまだ無い可能性があるので、p23のwk_t_nayose_lbc_sbndata_outputからも検索
				if(count($diffList) <= 0){
					$diffList = $this->db->getData("nayose.corporation_code,nayose.office_id",
					self::WK_T_TABLE." as nayose INNER JOIN wk_t_nayose_lbc_sbndata_output ml ON nayose.office_id = ml.office_id ",
					" nayose.corporation_code = ? and nayose.nayose_status is null and ifnull(ml.office_id,'') != ifnull(ml.top_head_office_id,'') ", $params);
				}

				// LBCと親会社LBCが同じものが正なので
				// 2-1 と 2-2　どちらもデータがある場合はdiffListのレコードのnayose_status => 3
				if(count($sameList) > 0 && count($diffList)){
					foreach($diffList as $diffData){
						$updateParam = array($diffData["corporation_code"],$diffData["office_id"]);
						$updateNayose = $this->db->updateData(self::WK_T_TABLE,$update,"corporation_code = ? AND office_id = ? AND nayose_status IS NULL",$updateParam);
						if($updateNayose < 0){
							$this->logger->error("Failed to Update nayose_status = 3 of. Corporation_code: ".$diffData["corporation_code"]." - Office_id:".$diffData["office_id"]);
							if($this->SKIP_FLAG == 0){
								$this->isError = true;
								throw new Exception("Process24 Failed to update nayose_status = 3 of Corporation_code: ".$diffData["corporation_code"]." - Office_id:".$diffData["office_id"]);
							}
						} else if($updateNayose == 0) {
							// 処理対象データなし
							$this->logger->info("No Data Match nayose_status = 3. Corporation_code: ".$diffData["corporation_code"]." - Office_id:".$diffData["office_id"]);
						} else {
							$this->logger->info("Data Updated nayose_status => 3. Corporation_code: ".$diffData["corporation_code"]." - Office_id:".$diffData["office_id"]);
						}
					}
				}
				$this->db->commit();
			}

		// Start of Process 3-0 -> 3-2
		} else if ($process == 3){
			foreach($list as $data){
				$this->db->beginTransaction();
				$params = array($data["corporation_code"]);
				$update = array("nayose_status"=>4);
				// 最高のマッチング結果を取得
				$match_result = $this->db->getData("corporation_code,IFNULL(min(CONCAT(result_flg,detail_lvl)),'') as rank",self::WK_T_TABLE,"corporation_code = ? AND nayose_status IS NULL GROUP BY corporation_code", $params);
				$updateParams = array($data["corporation_code"],$match_result[0]["rank"]);
				// 最高のマッチング結果以外のレコードの nayose_status => 4
				$updateData = $this->db->updateData(self::WK_T_TABLE,$update,"nayose_status IS NULL AND corporation_code = ? AND IFNULL(CONCAT(result_flg,detail_lvl),'') != ?", $updateParams);
				if($updateData < 0){
					$this->logger->error("Failed to Update nayose_status = 4 of Corporation_code: ".$data["corporation_code"]);
					if($this->SKIP_FLAG == 0){
						$this->isError = true;
						throw new Exception("Process24 Failed to update nayose_status = 4 of Corporation_code: ".$data["corporation_code"]);
					}
				} else if ($updateData == 0) {
					// 処理対象データなし
					$this->logger->info("No Data Match nayose_status = 4. Corporation_code: ".$data["corporation_code"]);
				} else {
					$this->logger->info("Data Updated nayose_status => 4. Corporation_code: ".$data["corporation_code"]);
				}
				$this->db->commit();
			}

		// Start of Process 4-0 -> 4-3
		} else if ($process == 4){
			foreach($list as $data){
				$this->db->beginTransaction();
				//$params = array($data["office_id"]);
				$searchParams = array($data["corporation_code"]);
				$updateParams = array($data["corporation_code"],$data["office_id"]);
				$update = array("nayose_status"=> 5);

				// 4-1 医が付いていないLBCの組み合わせ
				$nonMedicalList = $this->db->getData("nayose.corporation_code,nayose.office_id",
				self::WK_T_TABLE." as nayose INNER JOIN ".$this->m_table_lbc." ml ON nayose.office_id = ml.office_id ",
				" nayose.corporation_code = ? and nayose.nayose_status is null and ml.company_name NOT LIKE '%（医%' ", $searchParams);
				// 今回のバッチで新規登録されるLBCの場合はm_lbcにまだ無い可能性があるので、p23のwk_t_nayose_lbc_sbndata_outputからも検索
				if(count($nonMedicalList) <= 0){
					$nonMedicalList = $this->db->getData("nayose.corporation_code,nayose.office_id",
					self::WK_T_TABLE." as nayose INNER JOIN wk_t_nayose_lbc_sbndata_output ml ON nayose.office_id = ml.office_id ",
					" nayose.corporation_code = ? and nayose.nayose_status is null and ml.company_name NOT LIKE '%（医%' ", $searchParams);
				}

				// 4-2 医のあるLBCの組み合わせ
				$medicalList = $this->db->getData("nayose.corporation_code,nayose.office_id",
				self::WK_T_TABLE." as nayose INNER JOIN ".$this->m_table_lbc." ml ON nayose.office_id = ml.office_id ",
				" nayose.corporation_code = ? and nayose.nayose_status is null and ml.company_name LIKE '%（医%' ", $searchParams);
				// 今回のバッチで新規登録されるLBCの場合はm_lbcにまだ無い可能性があるので、p23のwk_t_nayose_lbc_sbndata_outputからも検索
				if(count($medicalList) <= 0){
					$medicalList = $this->db->getData("nayose.corporation_code,nayose.office_id",
					self::WK_T_TABLE." as nayose INNER JOIN wk_t_nayose_lbc_sbndata_output ml ON nayose.office_id = ml.office_id ",
					" nayose.corporation_code = ? and nayose.nayose_status is null and ml.company_name LIKE '%（医%' ", $searchParams);
				}

				// 医がないものが正なので
				// どちらもデータがある場合はmedicalListのレコードのnayose_status => 5
				if(count($nonMedicalList) > 0 && count($medicalList)){
					foreach($medicalList as $medicalData){
						$result = $this->db->updateData(self::WK_T_TABLE,$update,"corporation_code = ? AND office_id = ? AND nayose_status IS NULL", array($medicalData["corporation_code"],$medicalData["office_id"]));
						if($result < 0){
							$this->logger->error("Failed to Update nayose_status = 5. of. Corporation_code: ".$medicalData["corporation_code"]." - Office_id:".$medicalData["office_id"]);
							if($this->SKIP_FLAG == 0){
								$this->isError = true;
								throw new Exception("Process24 Failed to update nayose_status = 5. of Corporation_code: ".$medicalData["corporation_code"]." - Office_id:".$medicalData["office_id"]);
							}
						} else {
							$this->logger->info("Data Updated nayose_status => 5. Corporation_code: ".$medicalData["corporation_code"]." - Office_id:".$medicalData["office_id"]);
						}
					}
				}
				$this->db->commit();
			}

		// Start of Process 5-0 -> 5-3
		} else if ($process == 5){
			foreach($list as $data){
				$this->db->beginTransaction();
				$update = array("nayose_status"=>6);
				$corporation = $data["corporation_code"];
				$officeId = $data["office_id"];
				//5-1、Among 5-0, search for the corporation_code where both LBC 社名(company_name) 
				// and m_corporation table 顧客名(corporation_name) have the text: 
				// 「株」 and the positions of that text match
				
				// 5-1 株の位置が同じ組み合わせ
				$likeSql = 
				"SELECT nayose.corporation_code,nayose.office_id 
				FROM wk_t_nayose_crm_result nayose 
				INNER JOIN ".$this->m_table_lbc." ml ON nayose.office_id = ml.office_id 
				INNER JOIN m_corporation mc ON  nayose.corporation_code = mc.corporation_code 
				WHERE nayose.corporation_code = '".$corporation."' 
				AND nayose.nayose_status is null 
				AND ml.company_name like '%株%' 
				AND mc.corporation_name like '%株%' 
				AND LOCATE('株', ml.company_name) = LOCATE('株', mc.corporation_name)";
				$dataLikeList = $this->db->getDataSql($likeSql);
				// 今回のバッチで新規登録されるLBCの場合はm_lbcにまだ無い可能性があるので、p23のwk_t_nayose_lbc_sbndata_outputからも検索
				if(count($dataLikeList) <= 0){
					$likeSql = 
					"SELECT nayose.corporation_code,nayose.office_id 
					FROM wk_t_nayose_crm_result nayose 
					INNER JOIN wk_t_nayose_lbc_sbndata_output ml ON nayose.office_id = ml.office_id 
					INNER JOIN m_corporation mc ON  nayose.corporation_code = mc.corporation_code 
					WHERE nayose.corporation_code = '".$corporation."' 
					AND nayose.nayose_status is null 
					AND ml.company_name like '%株%' 
					AND mc.corporation_name like '%株%' 
					AND LOCATE('株', ml.company_name) = LOCATE('株', mc.corporation_name)";
					$dataLikeList = $this->db->getDataSql($likeSql);
				}

				// 5-2 株の位置が異なる組み合わせ
				$notLikeSql =
				"SELECT nayose.corporation_code,nayose.office_id 
				FROM wk_t_nayose_crm_result nayose 
				INNER JOIN ".$this->m_table_lbc." ml ON nayose.office_id = ml.office_id 
				INNER JOIN m_corporation mc ON  nayose.corporation_code = mc.corporation_code 
				WHERE nayose.corporation_code = '".$corporation."' 
				AND nayose.nayose_status is null 
				AND ml.company_name like '%株%' 
				AND mc.corporation_name like '%株%' 
				AND LOCATE('株', ml.company_name) != LOCATE('株', mc.corporation_name)";
				$dataNotLikeList = $this->db->getDataSql($notLikeSql);
				// 今回のバッチで新規登録されるLBCの場合はm_lbcにまだ無い可能性があるので、p23のwk_t_nayose_lbc_sbndata_outputからも検索
				if(count($notLikeSql) <= 0){
					$notLikeSql =
					"SELECT  nayose.corporation_code,nayose.office_id 
					FROM wk_t_nayose_crm_result nayose 
					INNER JOIN wk_t_nayose_crm_result ml ON nayose.office_id = ml.office_id 
					INNER JOIN m_corporation mc ON  nayose.corporation_code = mc.corporation_code 
					WHERE nayose.corporation_code = '".$corporation."' 
					AND nayose.nayose_status is null 
					AND ml.company_name like '%株%' 
					AND mc.corporation_name like '%株%' 
					AND LOCATE('株', ml.company_name) != LOCATE('株', mc.corporation_name)";
					$dataNotLikeList = $this->db->getDataSql($notLikeSql);
				}

				// 株位置が同じものが正なので
				// 同じ顧客コードで検索して5-1と5-2がどちらもあるならdataNotLikeListのレコードのnayose_status => 6
				if(count($dataLikeList) > 0 && count($dataNotLikeList) > 0){
					foreach($dataNotLikeList as $notLikeData){
						$result = $this->db->updateData(self::WK_T_TABLE,$update,"corporation_code = ? AND office_id = ? AND nayose_status IS NULL",array($notLikeData["corporation_code"],$notLikeData["office_id"]));
						if($result < 0){
							$this->logger->error("Failed to Update nayose_status = 6 of. Corporation_code: ".$notLikeData["corporation_code"]." - Office_id:".$notLikeData["office_id"]);
							if($this->SKIP_FLAG == 0){
								$this->isError = true;
								throw new Exception("Process24 Failed to update nayose_status = 6 of Corporation_code: ".$notLikeData["corporation_code"]." - Office_id:".$notLikeData["office_id"]);
							}
						} else {
							$this->logger->info("nayose_status = 6 updated for Corporation_code: ".$notLikeData["corporation_code"]." - Office_id:".$notLikeData["office_id"]);
						}
					}
				}
				$this->db->commit();
			}

		// Start of Process 6
		} else if ($process == 6){
			foreach($list as $data){
				$this->db->beginTransaction();
				$params = array($data["corporation_code"]);
				$update = array("nayose_status"=>9);
				// 顧客コードに紐づく最小のLBCを取得
				$match_result = $this->db->getData("corporation_code,min(office_id) as min_id ",self::WK_T_TABLE,"corporation_code = ? AND nayose_status IS NULL GROUP BY corporation_code",$params);
				if(count($match_result) > 0){
					$updateParams = array($data["corporation_code"],$match_result[0]["min_id"]);
					// 最小のLBC以外のレコードのnayose_status => 9
					$updateData = $this->db->updateData(self::WK_T_TABLE,$update,"corporation_code = ? AND office_id != ? AND nayose_status IS NULL",$updateParams);
					if($updateData < 0){
						$this->logger->error("Failed to Update nayose_status = 9 of. Corporation_code: ".$data["corporation_code"]);
						if($this->SKIP_FLAG == 0){
							$this->isError = true;
							throw new Exception("Process24 Failed to update nayose_status = 9 of Corporation_code: ".$data["corporation_code"]);
						}
					} else if ($updateData == 0) {
						// 処理対象データなし
						$this->logger->info("No Data Match nayose_status = 9. Corporation_code: ".$data["corporation_code"]);
					} else {
						$this->logger->info("nayose_status = 9 updated for Corporation_code: ".$data["corporation_code"]);
					}
				}
				$this->db->commit();
			}
		}

	}


	// 指定されたfieldで並び替え
	private function orderBy($data,$field){
		$code = "return strnatcmp(\$a['$field'], \$b['$field']);";
		usort($data,create_function('$a,$b', $code));
		return $data;
	}

	// 更新日付でソート
	private function sortDateTime($a,$b){
		if($a['update_date'] == $b['update_date']){
			return 0;
		}
		return strtotime($a['update_date'])<strtotime($b['update_date'])?1:-1;
	}

	// CSV出力
	private function generateCSV($data){
		global $EXPORT_FILENAME, $procNo;
		try {
			// get Custom CSV Header
			$cusHeader = $this->initHeader();
			//Create CSV Output data (select * from wk_t_nayose_lbc_sbndata_output)
			$csvUtils = new CSVUtils($this->logger);
			$csvUtils->export($EXPORT_FILENAME[$procNo], $data, $cusHeader);
		} catch (Exception $e){
			throw $e;
		}
	}

	// CSVヘッダ用意
	private function initHeader(){
		return $procFields = array(
			"corporation_code",
			"office_id",
			"result_flg",
			"detail_lvl",
			"detail_content"
			);
	}

	// CSV出力情報取得
	private function getCSVData(){
		try{
			$data = $this->db->getData("corporation_code,office_id,result_flg,detail_lvl,detail_content",self::WK_T_TABLE,"nayose_status IS NULL and delete_flag IS NULL");
		} catch(Exception $e){
			$this->logger->error("ERROR getting CSV Data.");
			throw $e;
		}
		return $data;
	}

}

?>