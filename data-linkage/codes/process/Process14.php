<?php
/**
 * Process14 Class
 *
 * Generate CSV file based on
 * 他媒体顧客データ
 *
 * @author Krishia Valencia
 *
 */
class Process14 {

	private $db, $rds_db, $logger, $mail, $mediaNameMap;

	//const T_TABLE = "t_media_match_wait_evacuation";
	const T_TABLE = 't_media_match_wait';
	const M_TABLE = 'm_media_mass';

	/**
	 * Process14 constructor
	 * @param $logger
	 */
	public function __construct($logger){
		// set logger
		$this->logger = $logger;
		// instantiate mail
		$this->mail = new Mail();
		// initialize parameters for query fields and CSV custom header
		$this->initParam();
		$this->mediaNameMap = null;

	}

	/**
	 * Execute Process 14
	 * @throws PDOException
	 * @throws Exception
	 */
	public function execProcess() {
		global $EXPORT_FILENAME, $procNo;
		$limit = '100000';
		$firstLoop = "0";
		$csvFileName = "";
		//$offsetList = array('0', '100000', '200000', '300000', '400000', '500000', '600000', '700000','800000', '900000', '1000000');
		$offset = '0';
		$loopjudge = true;
		
		try {
			// initialize database
			$this->db = new Database($this->logger);
			$this->rds_db = new RDSDatabase($this->logger);
			
			$this->reqMediaData();
			//foreach ($offsetList as $offset){
			while( $loopjudge ){
				$this->logger->info("データ取得開始　offset : " . $offset);
				
				// initialize csv util
				$csvUtils = new CSVUtils($this->logger);
				// Search for 「他媒体顧客データ」
				$result = $this->reqData($limit, $offset);
				
				if ($offset === $firstLoop) {
					// get csv file name
					$csvFileName = $csvUtils->generateFilename($EXPORT_FILENAME[$procNo]);
				}

				// If the search results are 0件 (0 records),
				// skip the process on and after the corresponding process number and
				// proceed to the next process number
				if(empty($result)) {
					// write down the error contents in the error file.
					$this->logger->info("No record found");
					$this->logger->info("処理するデータがないためプロセスを終了します。");
					$loopjudge = false;
					break;
				} else {
					$this->logger->info("csvFileName = " . $csvFileName);
					// create 「他媒体顧客データ」CSV file.
					$this->generateCSV($csvFileName, $result, $offset);
				}
				$offset += $limit;
			}
			// t_media_match_wait情報削除対応
			$this->db->beginTransaction();
			$deleteData = $this->db->truncateData(self::T_TABLE);
			if($deleteData){
				$this->db->commit();
			} else {
				$this->db->rollback();
				throw new Exception("Process14 Failed to truncate table ". self::T_TABLE);
			}
		} catch (PDOException $e1) {
			$this->logger->debug("Error found in database.");
			$this->disconnect();
			$this->mail->sendMail();
			throw $e1;
		} catch (Exception $e2) {
			// write down the error contents in the error file
			$this->logger->debug("Error found in process.");
			$this->logger->error($e2->getMessage());
			$this->disconnect();
			throw $e2;
		}
		
		$this->disconnect();
	}
	
	private function disconnect(){
		if($this->db) {
			// close database connection
			$this->db->disconnect();
		}
		if($this->rds_db) {
			// close database connection
			$this->rds_db->disconnect();
		}
	}

	private function reqMediaData() {
		$this->mediaNameMap = array();
		try {
			$media_result = $this->rds_db->getData("compe_media_code,media_name", self::M_TABLE, null, array());
			foreach($media_result as $key=>$data){
				$this->mediaNameMap = array_merge($this->mediaNameMap,
					array($media_result[$key]["compe_media_code"]=>$media_result[$key]["media_name"])
				);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	 * Search data from DB
	 * @throws PDOException
	 * @throws Exception
	 * @return result set
	 */
	private function reqData($limit, $offset) {
		try {
			$fields = $this->getParam();
			$result = $this->db->getLimitOffsetData($fields, self::T_TABLE, null, array(), $limit, $offset);
			
			foreach($result as $key=>$data){
				$compe_media_code = $result[$key]["media_name"];
				$media_name = NULL;
				if (array_key_exists($compe_media_code, $this->mediaNameMap)) {
					$media_name = $this->mediaNameMap[$compe_media_code];
				}
				$result[$key]["media_name"] = $media_name;
			}
		} catch (PDOException $e) {
			throw $e;
		} catch (Exception $e) {
			throw $e;
		}

		return $result;
	}

	/**
	 * Write down the data of the searched record into the CSV file
	 * @param array $data - record
	 * @throws Exception
	 */
	private function generateCSV($csvFileName, $data, $offset) {

		try {
			// get custom CSV header
			$customHeader = $this->getParam(true);
			// create CSV for 他媒体マッチング依頼作成　offset が0の場合は新規、0でない場合は追記
			$csvUtils = new CSVUtils($this->logger);

			$csvUtils->exportCsvAddData($csvFileName, $data, $customHeader, $offset);

		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	 * Get parameter for query and CSV header
	 * @param boolean $isCSVHeader - if not true return fields for query
	 * @return array | string $param
	 */
	private function getParam($isCSVHeader = false) {
		($isCSVHeader) ?
			$param = array_values($this->procFields)
		: $param = implode(",", array_keys($this->procFields));

		return $param;
	}

	/**
	 * Set value to csv parameters
	 */
	private function initParam() {
		$this->procFields = array(
				"media_code" => "媒体コード",
				"compe_media_code as media_name" => "媒体名",
				"post_start_date" => "掲載開始日",
				"business_content" => "事業内容",
				"ad_type" => "職種",
				"corporation_name" => "会社名",
				"zip_code" => "郵便番号",
				"addr_prefe" => "都道府県",
				"address1" => "住所1",
				"address2" => "住所2",
				"null as address3" => "住所3",
				"tel" => "TEL",
				"section" => "担当部署",
				"corporation_emp_name" => "担当者名",
				"listed_marked" => "上場市場",
				"employee_number" => "従業員数",
				"capital_amount" => "資本金",
				"year_sales" => "売上高",
				"space" => "広告スペース",
				"job_category" => "大カテゴリ",
				"job_class" => "小カテゴリ",
				"post_count" => "掲載案件数",
				"dispatch_flag" => "派遣",
				"introduction_flag" => "紹介",
				"flag_count" => "フラグ数",
				"fax" => "FAX",
				"data_get_date" => "データ取得日",
		);
	}
}
?>
