<?php
class Process33{
	private $logger, $db, $validate, $mail, $SKIP_FLAG;
	private $SYSTEM_USER = null;
	
	const M_CORPORATION_EMP         = 'm_corporation_emp';
	
	const I_MSG_001                 = 'm_corporation_emp select結果0件（削除対象なし）';
	const I_MSG_002                 = 'm_corporation_emp select結果0件';
	
	const E_MSG_001                 = '顧客担当者の重複削除に失敗しました';
	
	
	/**
	 * Process33 Class constructor
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
	 * Execute Process 33
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
					$corporation_code = $record['corporation_code'];
					$corporation_emp_name = $record['corporation_emp_name'];
					$email = $record['email'];
					$data = $this->getData($corporation_code, $corporation_emp_name, $email);
					$cnt = count($data);
					if($cnt <= 0){
						$this->logger->info(self::I_MSG_002);
					}
					else {
						foreach($data as $i => $datum){
							$this->db->beginTransaction();
							$result = $this->deleteData($corporation_code, $corporation_emp_name,$email,$datum['update_date'],$datum['corporation_emp_code']);
							if($result < 0){
								$this->isError = true;
								$this->logger->error(self::E_MSG_001.".[corporation_code = ".$corporation_code."][corporation_emp_name = ".$corporation_emp_name."]");
								if($this->SKIP_FLAG == 0){
									$this->db->rollback();
									throw new Exception("Process33 Failed to update. [corporation_code = ".$corporation_code."][corporation_emp_name = ".$corporation_emp_name."]");
								}
							}else{
								$this->logger->info("Duplicate data deleted. [corporation_code = ".$corporation_code."][corporation_emp_name = ".$corporation_emp_name."] deleted from ".self::M_CORPORATION_EMP);
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
	
	// 顧客コード、顧客担当者名全文、Ｅメールの組み合わせが重複しているレコードを取得
	function getAllData(){
		$sql = "SELECT corporation_code, corporation_emp_name, email "
			 . " FROM " . self::M_CORPORATION_EMP
			 . " WHERE delete_flag = false "
			 . " GROUP BY corporation_code, corporation_emp_name, email " 
			 . " HAVING COUNT(0) > 1 ";
		return $this->db->getDataSql($sql);
	}
	
	// 顧客コードと顧客担当者名全文とＥＭＡＩＬを条件に、その顧客担当者の最終更新日付を取得
	function getData($corporation_code, $corporation_emp_name, $email){
		$sql = "SELECT update_date, corporation_emp_code "
			 . " FROM " . self::M_CORPORATION_EMP
			 . " WHERE corporation_code <=> ? "
			 . " AND corporation_emp_name <=> ? "
			 . " AND email <=> ? "
			 . " AND delete_flag = false "
			 . " ORDER BY update_date DESC, corporation_emp_code ASC " 
			 . " LIMIT 1";
		$params = array($corporation_code, $corporation_emp_name, $email);
		return $this->db->getDataSql($sql, $params);
	}
	// 同じ名前とＥメールと顧客についている担当者の中で更新日が新しいもの以外のレコードを論理削除
	function deleteData($corporation_code, $corporation_emp_name, $email, $update_date, $corporation_emp_code){
		$sql = " corporation_code <=> ? "
			 . " AND corporation_emp_name <=> ? "
			 . " AND email <=> ? "
			 . " AND update_date <= ? "
			 . " AND delete_flag = false "
			 . " AND corporation_emp_code != ? ";
		$params = array($corporation_code, $corporation_emp_name, $email, $update_date, $corporation_emp_code);
		$update = array("delete_flag"=>1,"update_date"=>"NOW()");
		return $this->db->updateData(self::M_CORPORATION_EMP,$update,$sql,$params);
	}
}
?>
