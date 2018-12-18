<?php
class Process34{
	private $logger, $db, $validate, $mail, $SKIP_FLAG;
	private $SYSTEM_USER = null;
	
	const M_SALES_LINK              = 'm_sales_link';
	
	const I_MSG_001                 = 'm_sales_link select結果0件（削除対象なし）';
	const I_MSG_002                 = 'm_sales_link select結果0件';
	
	const E_MSG_001                 = '顧客担当者の重複削除に失敗しました';
	
	
	/**
	 * Process34 Class constructor
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
	 * Execute Process 34
	 */
	function execProcess(){
		$this->db = new Database($this->logger);
		$result = $this->getAllData();
		$this->isError = false;
		
		$cnt = count($result);
		try {
			// no record
			if($cnt <= 0){
				$this->logger->info(self::I_MSG_001);
			}
			// loop every record
			else {
				// process 2
				foreach($result as $i => $record){
					$member_code = $record['member_code'];
					$corporation_code = $record['corporation_code'];
					
					$data = $this->getData($member_code, $corporation_code);
					$cnt = count($data);
					if($cnt <= 0){
						$this->logger->info(self::I_MSG_002);
					}
					else {
						foreach($data as $i => $datum){
							$this->db->beginTransaction();
							$result = $this->deleteData($member_code, $corporation_code, $datum['update_date'], $datum['id']);
							if($result < 0){
								$this->isError = true;
								$this->logger->error(self::E_MSG_001.".[corporation_code = ".$corporation_code."][member_code = ".$member_code."]");
								if($this->SKIP_FLAG == 0){
									$this->db->rollback();
									throw new Exception("Process34 Failed to update. [corporation_code = ".$corporation_code."][member_code = ".$member_code."]");
								}
							} else {
								$this->logger->info("Duplicate data deleted. [corporation_code = ".$corporation_code."][member_code = ".$member_code."] deleted from ".self::M_SALES_LINK);
							}
							$this->db->commit();
						}
					}
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
		if($this->isError){
			// send mail if there is error
			$this->mail->sendMail();
		}

		if($this->db) {
			// close database connection
			$this->db->disconnect();
		}
	}

	// 顧客コードと営業担当者コードが重複しているデータを取得
	function getAllData(){
		$sql = "SELECT member_code, corporation_code "
			 . " FROM " . self::M_SALES_LINK
			 . " WHERE (member_code, corporation_code) "
			 . " IN (SELECT member_code, corporation_code " 
			 . "     FROM " . self::M_SALES_LINK 
			 . "     WHERE delete_flag = false " 
			 . "     GROUP BY member_code, corporation_code " 
			 . "     HAVING COUNT(0) > 1) "
			 . " GROUP BY member_code, corporation_code";
		return $this->db->getDataSql($sql);
	}
	
	// 更新日が一番新しいレコードのidを取得
	function getData($member_code, $corporation_code){
		$sql = "SELECT update_date, id "
			 . " FROM " . self::M_SALES_LINK
			 . " WHERE member_code <=> ? "
			 . " AND corporation_code <=> ? "
			 . " AND delete_flag = false "
			 . " ORDER BY update_date DESC, CAST(id AS UNSIGNED) ASC " 
			 . " LIMIT 1";
		$params = array($member_code, $corporation_code);
		return $this->db->getDataSql($sql, $params);
	}
	
	function deleteData($member_code, $corporation_code, $update_date, $id){
		$sql = " member_code <=> ? "
			 . " AND corporation_code <=> ? "
			 . " AND update_date <= ? "
			 . " AND delete_flag = false "
			 . " AND id != ? ";
		$params = array($member_code, $corporation_code, $update_date, $id);
		$update = array("delete_flag"=>1);
		return $this->db->updateData(self::M_SALES_LINK, $update, $sql, $params);
	}
}
?>
