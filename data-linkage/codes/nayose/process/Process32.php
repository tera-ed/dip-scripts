<?php
class Process32{
	private $logger, $db, $validate, $mail, $SKIP_FLAG;
	
	const WK_T_LBC_CRM_LINK         = 'wk_t_lbc_crm_link';
	const M_CORPORATION_EMP         = 'm_corporation_emp';
	const M_SALES_LINK              = 'm_sales_link';
	const T_NEGOTIATION             = 't_negotiation';
	const T_MEDIA_MASS              = 't_media_mass';
	
	const I_MSG_001                 = '処理対象レコードがありません';
	const I_MSG_002                 = 'LBC_CRMコード紐付けテーブルに顧客コードが登録されていません';
	const I_MSG_003                 = '名寄せ先が見つかりませんでした';
	
	const E_MSG_001                 = '顧客コードの更新に失敗しました';
	const E_MSG_002                 = '顧客コードに紐づく名寄せ情報が1件もありません';
	
	const W_MSG_001                 = 'LBC_CRMコード紐付けテーブルに顧客コードが登録されていません';
	
	
	private $process3A = false;
	private $process3D = false;
	private $hasError = false;
	
	/**
	 * Process32 Class constructor
	 */
	function __construct($logger){
		global $SKIP_FLAG;
		$this->logger = $logger;
		$this->mail = new Mail();
		$this->validate = new Validation($this->logger);
		$this->SKIP_FLAG = $SKIP_FLAG;
	}

	/**
	 * Execute Process 32
	 */
	function execProcess(){
		$this->db = new Database($this->logger);
		$tables = array(
			self::M_CORPORATION_EMP, // 顧客担当者
			self::M_SALES_LINK,      // 営業担当者
			self::T_NEGOTIATION,     // 商談
			self::T_MEDIA_MASS       // 他媒体
		);
		try{
			// テーブルごとに処理
			foreach($tables as $idx=>$table){
				$this->db->beginTransaction();
				if($this->process($table)){
					$this->db->commit();
				}
				else {
					$this->db->rollback();
				}
			}
		} catch (PDOException $e1){
			$this->logger->debug("Error found in Database.");
			$this->logger->error($e1->getMessage());
			if($this->db){
				// Close Database Connection
				$this->db->disconnect();
			}
			$this->mail->sendMail($e1->getMessage());
			throw $e1;
		} catch (Exception $e2){
			$this->logger->debug("Error found in Process.");
			//$this->logger->error($e2->getMessage());
			if($this->db){
				$this->db->disconnect();
			}
			// If there are no files:
			// Skip the process on and after the corresponding process number
			// and proceed to the next process number (ERR_CODE: 602)
			// For system error pause process
			if(602 != $e2->getCode()) {
				$this->mail->sendMail($e2->getMessage());
				throw $e2;
			}
		}
		if($this->db) {
			// close database connection
			$this->db->disconnect();
		}
	}
	
	/**
	 * Process 1
	 * Acquire the nayose-targeted corporation_code
	 * @param String $table
	 */
	function process($table){
		$this->logger->info("$table 処理開始.");
		$result = $this->getTableData($table);
		$cnt = count($result);
		// no record
		if($cnt <= 0){
			$this->logger->info("$table に".self::I_MSG_001); // '処理対象レコードがありません';
			return true;
		}else{
			$this->logger->info("$table 処理件数:".$cnt);
		}
		
		$bool = false;
		// loop every record
		foreach($result as $i => $record){
			$this->logger->info("$table $i 件目:".$record['corporation_code']);
			$corporation_code = $record['corporation_code'];
			// process 2
			$bool = $this->process2($table, $corporation_code);
		}
		$this->logger->info("$table 処理終了.");
		return $bool;
	}
	
	
	/**
	 * Acquire the wk_t_lbc_crm_link information which are linked to the corporation_code
	 * @param String $table
	 * @param String $corporation_code
	 */
	function process2($table, $corporation_code){
		// 顧客コードを使って名寄せ先顧客情報を取得
		$result = $this->select_wk_t_lbc_crm_link($corporation_code);
		$cnt = count($result);
		$bool = true;
		// no record
		if($cnt <= 0){
			$this->logger->warn($table." ".self::W_MSG_001." corporation_code: ".$corporation_code); //'LBC_CRMコード紐付けテーブルに顧客コードが登録されていません'
			return true;
		}
		else {
			$this->process3A = false;
			$this->process3D = false;
			foreach($result as $x => $record){
				// process 3 
				// true: 処理続行 false: 処理中断
				if($bool){
					$bool = $this->process3($table, $corporation_code, $record);
					// 正しい顧客コードだった時点で名寄せ先検索を終了する $this->process3A がtrueなので、成功処理として判断される
					if($this->process3A){
						break;
					}
				}
			}
			
			// process 4
			if($this->process3A == false && $this->process3D == false){
				//$this->mail->sendMail($table." ".self::E_MSG_002." corporation_code:".$corporation_code);
				$this->logger->error($table." ".self::E_MSG_002." corporation_code:".$corporation_code); // 顧客コードに紐づく名寄せ情報が1件もありません
				// proceed to next process when value is 1
				if($this->SKIP_FLAG == 0){
					$this->db->rollback();
					throw new Exception("Process32 ".$table." ".self::E_MSG_002." corporation_code:".$corporation_code);
					//shell_exec('PAUSE');
				}
			}
		}
		return $bool;
	}
	
	/**
	 * Nayose process (Search for nayose corporation_code)
	 * @param String $table
	 * @param Array $record
	 */
	function process3($table, $corporation_code, $record){
		// process 3.A
		if($corporation_code == $record['name_approach_code']
			&& $record['office_id'] == $record['name_approach_office_id']){
			// proceed
			$this->logger->info("$table ".$corporation_code." 正しい顧客コードです");
			$this->process3A = true;
			return true;
		}
		// process 3.B
		else {
			// process 3.C
			return $this->process3C($table, $corporation_code, $record['name_approach_code'], $record['name_approach_office_id']);
		}
	}
	
	
	function process3C($table, $corporation_code, $code, $office_id){
		$this->logger->info($table." 名寄せ先検索. corporation_code: $code, office_id: $office_id");
		$result = $this->getProcess3CData($code, $office_id);
		$cnt = count($result);
		if($cnt <= 0){
			$this->logger->info($table." ".self::I_MSG_003.". corporation_code: $code, office_id: $office_id");// 名寄せ先が見つかりませんでした
			return true;
		}
		else {
			$result = $result[0];
			// process 3.D
			if($code == $result['name_approach_code']
				&& $office_id == $result['name_approach_office_id']){
				$this->logger->info($table." 名寄せ先が見つかりました. corporation_code: ".$corporation_code." -> ".$result['name_approach_code']);
				$bool = $this->process3G($table, $corporation_code, $result['name_approach_code']);
				$this->process3D = true;
				return $bool;
			}
			// process 3.F
			else {
				return $this->process3C($table, $corporation_code, $result['name_approach_code'], $result['name_approach_office_id']);
			}
		}
	}
	
	
	function process3G($table, $corporation_code, $name_approach_code){
		$bool = true;
		try {
			$sql = "UPDATE {$table} SET corporation_code = ? , update_user_code = 'SYSTEM', update_date = now() WHERE corporation_code = ? ";
			$this->db->executeQuery($sql, array($name_approach_code, $corporation_code));
		} catch (Exception $e){
			$this->hasError = true;
			//$this->mail->sendMail($table." ".self::E_MSG_001."  $corporation_code -> $name_approach_code");
			$this->logger->error($table." ".self::E_MSG_001."  $corporation_code -> $name_approach_code");// 顧客コードの更新に失敗しました
			$this->logger->error($e->getMessage());
			$bool = false;
			// proceed to next process when value is 1
			if($this->SKIP_FLAG == 0){
			//	shell_exec('PAUSE');
				$this->db->rollback();
				throw new Exception("Process32 ".$table." ".self::E_MSG_001." $corporation_code -> $name_approach_code");
			}
		}
		return $bool;
	}
	
	// 顧客コードとLBCコードを使って次の名寄せ先を検索する
	function getProcess3CData($name_approach_code, $name_approach_office_id){
		$sql = "SELECT office_id, name_approach_code, name_approach_office_id "
				. " FROM " . self::WK_T_LBC_CRM_LINK
				. " WHERE corporation_code = ? and office_id = ? ";
		return $this->db->getDataSql($sql, array($name_approach_code, $name_approach_office_id));
	}
	
	// 顧客コードに紐づく名寄せ情報を取得 N件 
	//// 現在の正しい紐付け先を優先的に処理するためにdelete_flagをORDERBY
	function select_wk_t_lbc_crm_link($corporation_code){
		$sql = "SELECT office_id, name_approach_code, name_approach_office_id "
				. " FROM " . self::WK_T_LBC_CRM_LINK
				. " WHERE corporation_code = ?"
				. " ORDER BY delete_flag ";
		return $this->db->getDataSql($sql, array($corporation_code));
	}
	// 処理対象テーブルから重複排除した顧客コードを取得
	// ロック顧客は除く
	function getTableData($table){
		$sql = "SELECT DISTINCT corporation_code FROM {$table} T1 
		WHERE corporation_code IS NOT NULL AND corporation_code != ''
		AND NOT EXISTS(SELECT 1 FROM t_lock_lbc_link 
			WHERE T1.corporation_code = corporation_code AND lock_status = 1 AND delete_flag = false
			)";
		return $this->db->getDataSql($sql);
	}
}
?>
