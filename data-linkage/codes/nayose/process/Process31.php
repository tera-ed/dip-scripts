<?php
class Process31{
	private $logger, $db, $validate, $mail, $SKIP_FLAG;
	private $SYSTEM_USER = null;
	
	const WK_T_LBC_CRM_LINK         = 'wk_t_lbc_crm_link';
	const T_TABLE_UNIFICATION  = 't_nayose_unification';
	const I_MSG_001                 = '処理対象のレコードが存在しません';
	const E_MSG_001                 = '顧客の削除に失敗しました';
	
	
	
	/**
	 * Process19 Class constructor
	 */
	function __construct($logger){
		global $SKIP_FLAG, $SYSTEM_USER;
		$this->logger = $logger;
		$this->mail = new Mail();
		$this->validate = new Validation($this->logger);
		$this->SKIP_FLAG = $SKIP_FLAG;
		$this->SYSTEM_USER = $SYSTEM_USER;
	}

	/**
	 * Execute Process 31
	 */
	function execProcess(){
		$this->db = new Database($this->logger);
		// 該当データ取得
		$result = $this->getAllData();
		
		$cnt = count($result);
		try {
			// no record
			if($cnt <= 0){
				$this->logger->info(self::I_MSG_001);
			}
			// loop every record
			else {
				foreach($result as $i => $record){
					// insert record to T_TABLE_UNIFICATION
					// 統合済み顧客テーブルに登録
					$this->db->beginTransaction();
					$data = $this->setData($record);
					$this->db->insertDataWithoutCommon(self::T_TABLE_UNIFICATION, $data);
					$this->logger->info("登録成功. id:".$data['id']." code:".$data['corporation_code']);
					$this->db->commit();
				}
			}
		} catch (PDOException $e1){
			$this->logger->debug("Error found in Database.");
			$this->logger->error(self::E_MSG_001);
			$this->logger->error($e1->getMessage());
			$this->db->rollback();
			if($this->db){
				// Close Database Connection
				$this->db->disconnect();
			}
			$this->mail->sendMail($e1->getMessage());
			throw $e1;
		} catch (Exception $e2){
			$this->logger->debug("Error found in Process.");
			$this->logger->error(self::E_MSG_001);
			$this->logger->error($e2->getMessage());
			$this->db->rollback();
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
	
	function setData($record){
		$data = array();
		$unificationId = $this->db->getNextVal('T_NAYOSE_UNIFICATION_ID');
		$data['id'] = $unificationId;
		$data['corporation_code'] = $record['corporation_code'];
		//$data['integration_code'] = $record['name_approach_code'];
		$data['create_user_code'] = $this->SYSTEM_USER;
		$data['update_user_code'] = $this->SYSTEM_USER;
		return $data;
	}
	
	// 統合済み顧客テーブルに存在しない かつ どこにも名寄せされていないGマッチ判定のレコード取得 重複はさせない
	function getAllData(){
		$sql = "SELECT distinct crm.corporation_code "
			 . " FROM " . self::WK_T_LBC_CRM_LINK . " AS crm " 
			 . " WHERE crm.current_data_flag = '1' " 
			 . " AND crm.delete_flag = '2' "
			 . " AND NOT EXISTS(SELECT 1 "
			 . "   FROM " . self::T_TABLE_UNIFICATION . " AS tic " 
			 . "   WHERE crm.corporation_code = tic.corporation_code " 
			 . "   AND tic.delete_flag = false)";
		return $this->db->getDataSql($sql);
	}
}
?>
