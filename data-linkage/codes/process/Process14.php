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

	private $db;
	private $logger;
	private $mail;

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
			
			//foreach ($offsetList as $offset){
			while( $loopjudge ){
				$this->logger->info("データ取得開始　offset : " . $offset);
				// initialize database
				$this->db = new Database($this->logger);
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
				// close database connection
				$this->db->disconnect();
				
				$offset += $limit;
			}
			// close database connection
			$this->db->disconnect();

		} catch (PDOException $e) {
			$this->mail->sendMail();
			throw $e;
		} catch (Exception $e) {
			// write down the error contents in the error file
			$this->logger->error($e->getMessage());
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
			$from = "t_media_match_wait a LEFT JOIN m_media_mass b ";
			$from.= "ON a.compe_media_code = b.compe_media_code";
			$result = $this->db->getLimitOffsetData($fields, $from, null, array(), $limit, $offset);
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
				"a.media_code" => "媒体コード",
				"b.media_name" => "媒体名",
				"a.post_start_date" => "掲載開始日",
				"a.business_content" => "事業内容",
				"a.ad_type" => "職種",
				"a.corporation_name" => "会社名",
				"a.zip_code" => "郵便番号",
				"a.addr_prefe" => "都道府県",
				"a.address1" => "住所1",
				"a.address2" => "住所2",
				"a.address3" => "住所3",
				"a.tel" => "TEL",
				"a.section" => "担当部署",
				"a.corporation_emp_name" => "担当者名",
				"a.listed_marked" => "上場市場",
				"a.employee_number" => "従業員数",
				"a.capital_amount" => "資本金",
				"a.year_sales" => "売上高",
				"a.space" => "広告スペース",
				"a.job_category" => "大カテゴリ",
				"a.job_class" => "小カテゴリ",
				"a.post_count" => "掲載案件数",
				"a.dispatch_flag" => "派遣",
				"a.introduction_flag" => "紹介",
				"a.flag_count" => "フラグ数",
				"a.fax" => "FAX",
				"a.data_get_date" => "データ取得日",
		);
	}
}
?>
