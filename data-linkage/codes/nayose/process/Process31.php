<?php
class Process31{
	private $logger, $db, $crm_db, $rds_db, $validate, $mail;
	
	const WK_T_LBC_CRM_LINK         = 'wk_t_lbc_crm_link';
	const M_TABLE_CORP              = 'm_corporation';

	const I_MSG_001                 = '処理対象のレコードが存在しません';
	const E_MSG_001                 = '顧客の削除に失敗しました';
	
	const KEY_1 = 'corporation_code';
	
	/**
	 * Process19 Class constructor
	 */
	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
		$this->validate = new Validation($this->logger);
	}

	/**
	 * Execute Process 31
	 */
	function execProcess(){
		global $MAX_COMMIT_SIZE;
		$cntr = 0;
		try {
			//initialize Database
			$this->db = new Database($this->logger);
			$this->crm_db = new CRMDatabase($this->logger);
			$this->rds_db = new RDSDatabase($this->logger);
			
			// 該当データ取得
			$result = $this->getAllData();
			$cnt = count($result);
			$this->logger->debug('件数：'.$cnt);
			// no record
			if($cnt <= 0){
				$this->logger->info(self::I_MSG_001);
			}
			// loop every record
			else {
				foreach($result as $i => $record){
					if(($cntr % $MAX_COMMIT_SIZE) == 0){
						//begin transaction
						$this->db->beginTransaction();
						$this->crm_db->beginTransaction();
					}
					$corporation_code = $record[self::KEY_1];
					$this->logger->debug(self::KEY_1.'：'.$corporation_code);
					
					$result1 = $this->crm_db->updateData(self::M_TABLE_CORP, array("delete_flag"=>true), self::KEY_1."=?", array($corporation_code));
					$result2 = $this->db->updateData(self::M_TABLE_CORP, array("delete_flag"=>true), self::KEY_1."=?", array($corporation_code));
					if(!$result1){
						$this->logger->error("Failed to update delete_flag of row with ".self::KEY_1.": $corporation_code to ". self::M_TABLE_CORP);
						throw new Exception("Process31 Failed to update delete_flag of row with ".self::KEY_1.": $corporation_code to ". self::M_TABLE_CORP);
					} else {
						$this->logger->info("Data found. Updating delete_flag of row with ".self::KEY_1.": $corporation_code to ". self::M_TABLE_CORP);
					}
					$cntr++;
					//commit according to set max commit size on config file
					if(($cntr % $MAX_COMMIT_SIZE) == 0 || ($row + 1) == sizeof($result)){
						$this->db->commit();
						$this->crm_db->commit();
					}
				}
			}
		} catch (PDOException $e1){ // database error
			$this->logger->debug("Error found in database.");
			$this->logger->error(self::E_MSG_001);
			$this->logger->error($e1->getMessage());
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
		} catch(Exception $e2) { // error
			// write down the error contents in the error file
			$this->logger->debug("Error found in process.");
			$this->logger->error(self::E_MSG_001);
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

	// 顧客テーブルに存在しない かつ どこにも名寄せされていないGマッチ判定のレコード取得 重複はさせない
	function getAllData(){
		$sql = "SELECT distinct crm.corporation_code "
			 . " FROM " . self::WK_T_LBC_CRM_LINK . " AS crm " 
			 . " WHERE crm.current_data_flag = '1' " 
			 . " AND crm.delete_flag = '2' "
			 . " AND NOT EXISTS(SELECT 1 "
			 . "   FROM " . self::M_TABLE_CORP . " AS mc " 
			 . "   WHERE crm.corporation_code = mc.corporation_code " 
			 . "   AND mc.delete_flag = true)";
		//$this->logger->debug($sql);
		return $this->rds_db->getDataSql($sql);
	}
}
?>
