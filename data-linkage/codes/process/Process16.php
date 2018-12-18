<?php
/**
 * Update process of 受注禁止フラグ(disallow order flag)
 *
 * @author Evijoy Jamilan
 *
 */
class Process16{
	private $logger, $db, $crm_db, $rds_db, $mail, $isError = false;

	const T_TABLE1 = 't_lbc_obic_map';
	const M_TABLE1 = 'm_corporation';

	/**
	 * Process16 Class constructor
	 */
	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
	}

	/**
	 * Execute Process 16
	 */
	function execProcess(){
		try{
			//initialize Database
			$this->db = new Database($this->logger);
			$this->crm_db = new CRMDatabase($this->logger);
			$this->rds_db = new RDSDatabase($this->logger);
			
			$limit = '50000';
			$offsetList = array('0', '50000', '100000', '150000', '200000', '250000', '300000', '350000','400000', '450000', '500000');

			// t_lbc_obic_map data get 更新日の判定追加　初回だけすべてのデータを実行
			// 5万件ずつ実行 取得件数が0件になれば処理終了
			foreach ($offsetList as $offset){
				$this->logger->info("データ取得開始　offset : " . $offset);
				$orderby = " order by update_date "; // 更新日が後のものを後に処理
				$limitoffset = " limit ".$limit." offset ".$offset;

				// t_lbc_obic_map　から受注禁止フラグを取得
				// 全件
				//$obic_dataList = $this->getData("office_id, disable_dorder_flg", self::T_TABLE1, "office_id IS NOT NULL AND office_id != '' ".$limitoffset);
				// 週次指定
				//$time = "'".date("Y-m-d 00:00:00",strtotime("-8 day"))."'";
				// 日付指定
				$time = " '2016-04-01 00:00:00' ";
				$obic_dataList = $this->getData("office_id, disable_dorder_flg", self::T_TABLE1, "office_id IS NOT NULL AND office_id != '' AND update_date > ".$time.$orderby.$limitoffset);

				// 対象となるoffice_idを抽出
				$obic_officeList = array_column($obic_dataList, 'office_id');
				$size = sizeof($obic_officeList);
				// 更新対象の顧客テーブルから現在の値を取得、格納
				if (sizeof($obic_officeList) > 0) {
					$result = $this->getData("office_id, orders_ban_flag+0", self::M_TABLE1, "office_id in (".implode(",",$obic_officeList).")");
					$corp_dataList = array_column($result, 'orders_ban_flag+0', 'office_id');
				}else {
					$this->logger->info("取得件数0件のため終了処理へ");
					$corp_dataList = [];
				}

				//$where_data = substr(str_repeat(',?', count($obic_officeList)), 1);
				//$this->logger->debug(count($obic_officeList).$where_data);
				//exit;

				//If there are no records: write it down on the error file
				//Skip the process on and after the corresponding process number and
				//proceed to the next process number
				// データがあった場合はアップデート処理へ
				if(empty($obic_dataList) || empty($corp_dataList)){
					//$this->logger->error("No data found.");
					$this->logger->info("No data found.");
					break;
				}else{
					//update results of all the records of the 「office_id」
					$cntr = $this->updateData($obic_dataList,$corp_dataList);
				}
			}
			$this->logger->info("foreach 終了");
		}catch (PDOException $e1){
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
		}catch(Exception $e2){ // error
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
			// If there are no files:
			// Skip the process on and after the corresponding process number
			// and proceed to the next process number (ERR_CODE: 602)
			// For system error pause process
			if(602 != $e2->getCode()) {
				$this->mail->sendMail();
				throw $e2;
			}
		}
		//send mail if there is error
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

	/**
	 * Update data by office_id
	 * @param array $dataList
	 * @throws Exception
	 * @return number
	 */
	function updateData($obic_dataList,$corp_dataList){
		global $MAX_COMMIT_SIZE;
		$cntr = 0;
		try{
			foreach ($obic_dataList as $row => $data){
				if(($cntr % $MAX_COMMIT_SIZE) == 0){
					//begin transaction
					$this->db->beginTransaction();
					$this->crm_db->beginTransaction();
				}
				// 更新する値を取得
				$corp_dataList = $this->updateAction($data["office_id"], $data["disable_dorder_flg"], $corp_dataList);
				$cntr++;
				//commit according to set max commit size on config file
				if(($cntr % $MAX_COMMIT_SIZE) == 0 || ($row+1) == sizeof($obic_dataList)){
					$this->db->commit();
					$this->crm_db->commit();
				}
			}
		}catch(Exception $e){
			throw $e;
		}
		return $cntr;
	}

	/**
	 * Get the orders_ban_flag according to condition
	 * @param array $data
	 * @return boolean orderBanFlg(true/false)
	 */
	function getOrderBanFlg($disable_dorder_flg){
		//If the 「disable_dorder_flg」 of 「t_lbc_obic_map」 is "1"
		//Update the 「orders_ban_flag」 of the same 「office_id」 of the 「m_corporation」 to "1"
		if($disable_dorder_flg == 1){
			$orderBanFlg = true;
		}
		//If the 「disable_dorder_flg」 of 「t_lbc_obic_map」 is "0"
		//Update the 「orders_ban_flag」 of the same 「office_id」 of the 「m_corporation」 to "0"
		else{
			$orderBanFlg = false;
		}
		return $orderBanFlg;
	}

	/**
	 * Search for those with 「office_id」 from the 「t_lbc_obic_map」「m_corporation」
	 * @return array
	 */
	function getData($fields,$table,$where){
		try{
			$sql  =' SELECT '.$fields.' FROM '.$table.' WHERE '.$where;
			//$this->logger->info("sqlcheck : $sql");
			if(in_array($table, array(self::M_TABLE1))){
				$result = $this->rds_db->getDataSql($sql);
 			} else {
 				$result = $this->db->getDataSql($sql);
 			}
		}catch(Exception $e){
			throw $e;
		}
		return $result;
	}
	
	/**
	 * Search for those with 「disable_dorder_flg」 from the 「t_lbc_obic_map」
	 * @return array
	 */
	function updateAction($officeId="", $disableDorder="", $corp_dataList = array()){
		// 顧客テーブルより作成したチェック配列から、現在の値を取得 (0 or 1)
		if(array_key_exists($officeId, $corp_dataList)){
			$oldDisableDorderFlg = $corp_dataList[$officeId];
		}else{ // corp_dataList に存在しなかった場合
			$this->logger->error("not exists [office_id = $officeId] in ".self::M_TABLE1.".");
			throw new Exception("Process16 Failed to update. [office_id = $officeId]");
		}

		// 更新する値(マッピングテーブル) != 現在の値(顧客テーブル) ならば更新 (0 or 1)
		if($disableDorder != $oldDisableDorderFlg){
			// 更新する値を boolean に変換 ([0 or 1] -> [false or true])
			$orders_ban_flag = $this->getOrderBanFlg($disableDorder);
			// update data t_lbc_obic_map.disable_dorder_flgをm_corporation.orders_ban_flagにセット
			$updateFields = array("orders_ban_flag"=>$orders_ban_flag);
			$condition = "office_id = ?";
			$params = array($officeId);
			$result1 = $this->db->updateData(self::M_TABLE1, $updateFields, $condition, $params);
			$result2 = $this->crm_db->updateData(self::M_TABLE1, $updateFields, $condition, $params);
			
			if(!$result1 || !$result2){ // update に失敗した場合は処理中断
				$this->isError = true;
				$this->logger->error("Failed to update. [office_id = $officeId] [orders_ban_flag = ".var_export($orders_ban_flag, TRUE)."]");
				throw new Exception("Process16 Failed to update. [office_id = $officeId]");
			}else{
				$this->logger->debug("Updated [office_id = $officeId]"."[orders_ban_flag = ".var_export($orders_ban_flag, TRUE)."]");
				// 成功した場合は更新した値をチェック配列に代入 ([false or true] -> [0 or 1])
				if($orders_ban_flag){
					$corp_dataList[$officeId] = 1;
				}else{
					$corp_dataList[$officeId] = 0;
				}
				//$corp_dataList[$officeId]=$orders_ban_flag;
			}
		}else{
			$this->logger->info("受注禁止コードが同一なので更新しない [office_id = $officeId]"."[orders_ban_flag = ".$disableDorder."]");
		}
		// 更新されたチェック配列を返す
		return $corp_dataList;
	}
}
?>
