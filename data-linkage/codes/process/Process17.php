<?php
/**
 * Update process of 顧客属性(client attribute)
 *
 * @author Evijoy Jamilan
 *
 */
class Process17{
	private $logger, $db, $mail, $isError = false;

	/**
	 * Process17 Class constructor
	 */
	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
	}

	/**
	 * Execute Process 17
	 */
	function execProcess(){
		try{
			$this->db = new Database($this->logger);
			$limit = '50000';
			$offsetList = array('0', '50000', '100000', '150000', '200000', '250000', '300000', '350000','400000', '450000', '500000');

			// m_obic_application data get 更新日の判定追加　初回だけすべてのデータを実行
			// 5万件ずつ実行 取得件数が0件になれば処理終了
			foreach ($offsetList as $offset){
				// m_obic_application から5万件ずつデータ取得
				$obic_dataList = $this->getDataObic($limit, $offset);
				//$this->logger->info("sizeof obic_dataList : ".sizeof($obic_dataList));

				// contract_office_idの配列を作成し、空のデータ削除
				//$obic_CofficeList = array_column($obic_dataList, 'contract_office_id');
				//$obic_CofficeList = array_filter($obic_CofficeList);

				// billing_office_idの配列を作成し、空のデータ削除
				//$obic_BofficeList = array_column($obic_dataList, 'billing_office_id');
				//$obic_BofficeList = array_filter($obic_BofficeList);

				// m_corporationから対象ofice_idデータを取得
				//$officeList = array_merge($obic_CofficeList,$obic_BofficeList);

				// array_mergeでメモリエラーが出るためforeachに変更

				// 空でない場合に格納
				$officeList = [];
				foreach ($obic_dataList as &$data) {
					if(is_null($data["contract_office_id"]) || $data["contract_office_id"]==""){}else{
						$officeList[$data["contract_office_id"]]=$data["contract_office_id"];
					}
					if(is_null($data["billing_office_id"]) || $data["billing_office_id"]==""){}else{
						$officeList[$data["billing_office_id"]]=$data["billing_office_id"];
					}
				}

				// 顧客テーブルから対象となるレコードを取得
				$this->logger->info("sizeofofficeList : ".sizeof($officeList));
				if (sizeof($officeList) > 0) {
					$result = $this->getData("office_id, corporation_attr", "m_corporation", "office_id in (".implode(",",$officeList).")");
					$corp_dataList = array_column($result, 'corporation_attr', 'office_id');
				}else{
					$this->logger->info("取得件数0件のため終了処理へ");
					$corp_dataList = [];
				}

				//If there are no records: write it down on the error file
				//Skip the process on and after the corresponding process number and
				//proceed to the next process number
				if(empty($obic_dataList) || empty($corp_dataList)){
					$this->logger->error("No data found.");
					break;
				}else{
					//update results of all the records of the 「office_id」
					$cntr = $this->updateData($obic_dataList,$corp_dataList);
				}
			}
			$this->logger->info("foreach 終了");
		}catch (PDOException $e1){
			if(isset($cntr)){
				//rollback db transaction
				$this->db->rollback();
			}
			$this->db->disconnect();
			$this->mail->sendMail();
			throw $e1;
		}catch(Exception $e2){
			$this->logger->error($e2->getMessage());
		}
		//send mail if there is error
		if($this->isError){
			$this->mail->sendMail();
		}
		$this->db->disconnect();
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

			foreach ($obic_dataList as &$data) {
				if(($cntr % $MAX_COMMIT_SIZE) == 0){
					//begin transaction
					$this->db->beginTransaction();
				}

				// contract_office_idの顧客受注属性を変更(contract_office_idがnullと空以外)
				if(is_null($data["contract_office_id"]) || $data["contract_office_id"]==""){}else{
					$corp_dataList = $this->updateAction($data["contract_office_id"],$data["end_date"],$corp_dataList);
				}

				// billing_office_idの顧客受注属性を変更(billing_office_idがnullと空以外)
				if(is_null($data["billing_office_id"]) || $data["billing_office_id"]==""){}else{
					$corp_dataList = $this->updateAction($data["billing_office_id"],$data["end_date"],$corp_dataList);
				}
				$cntr++;
				//commit according to set max commit size on config file
				if(($cntr % $MAX_COMMIT_SIZE) == 0
				|| $cntr == sizeof($obic_dataList)){
					$this->db->commit();
				}
			}
		}catch(Exception $e){
			throw $e;
		}
		return $cntr;
	}

	/**
	 * Get the corporation_attr according to condition
	 * @param array $data
	 * @return integer corporation_attr(0,1,2)
	 */
	function getCorpAttr($end_date){
		$corp_attr = null;

		#$this->logger->debug("end_date".$end_date);
		#$this->logger->debug("date_check".date('Ymd'));
		//If 「m_obic.end_date」＞ today,
		//update the 「corporation_attr」 of the 「m_corporation」 with 「1」
		if($end_date > date('Ymd')){
			$corp_attr = '1';
		}
		//If 「m_obic.end_date」≦today,
		//update the 「corporation_attr」 of the 「m_corporation」 with 「2」
		else if($end_date <= date('Ymd')){
			$corp_attr = '2';
		}
		#$this->logger->debug("attr".$corp_attr);
		return $corp_attr;
	}


	/**
	 * @return array
	 */
	function getData($fields,$table,$where){
		try{
			$sql  =' SELECT '.$fields.' FROM '.$table.' WHERE '.$where;
			$result = $this->db->getDataSql($sql);

		}catch(Exception $e){
			throw $e;
		}
		return $result;
	}
	/**
	 * Search for those with 「office_id」 from the 「m_obic_application」
	 * @return array
	 */
	function getDataObic($limit, $offset){
		try{
			$sql  =' SELECT ';
			$sql .=' contract_office_id, ';
			$sql .=' billing_office_id, ';
			$sql .=' end_date';
			$sql .=' FROM m_obic_application ';
			$sql .=' WHERE (contract_office_id IS NOT NULL ';
			$sql .=' OR billing_office_id IS NOT NULL) ';

			// 更新日の判定追加　初回だけすべてのデータを実行
			//$time = date("Y-m-d 00:00:00",strtotime("-8 day"));
			//$sql .=' AND update_date>'.$time;
			// 日付指定
			$sql .=' AND update_date >= "20160401" ';

			$sql .=' ORDER BY end_date';
			$sql .=' LIMIT '.$limit.' OFFSET '.$offset;
			//$this->logger->info("sql_check".$sql);
			$result = $this->db->getDataSql($sql);
		}catch(Exception $e){
			throw $e;
		}
		return $result;
	}

	/**
	 * Search for those with 「office_id」 from the 「m_obic」
	 * @return array
	 */
	function updateAction($officeId="",$end_date="",$corp_dataList = array()){

		// 更新する値を取得
		$corporationAttr = $this->getCorpAttr($end_date);

		// 顧客テーブルより作成したチェック配列から、現在の値を取得
		if(array_key_exists($officeId, $corp_dataList)){
			$corporation_attr = $corp_dataList[$officeId];
		}else{
			$this->logger->error("顧客テーブルに [office_id = $officeId] が存在しないので処理を中断します.");
			throw new Exception("Process17 not exists [office_id = $officeId] in m_corporation");
		}
		//$this->logger->warn($corporationAttr);

		// 更新する値(m_obic_application) != 現在の値(m_corporation) ならば更新
		if($corporationAttr != $corporation_attr){
			$updateFields = array("corporation_attr"=>$corporationAttr);
			$condition = "office_id = ?";
			$params = array($officeId);
			//update data
			$result = $this->db->updateData("m_corporation", $updateFields,$condition, $params);
			if(!$result){
				$this->isError = true;
				$this->logger->error("Failed to update. [office_id = $officeId]");
				throw new Exception("Process17 Failed to update. [office_id = $officeId]");
			}else{
				$this->logger->debug("Updated [office_id = $officeId]"."[attr = $corporationAttr]");
				// 更新した値をチェック配列に代入
				$corp_dataList[$officeId]=$corporationAttr;
			}
		}else{
			$this->logger->info("顧客属性が同一なので更新しない [office_id = $officeId]"."[attr = $corporationAttr]");
		}
		// 更新されたチェック配列を返す
		return $corp_dataList;
	}
}
?>
