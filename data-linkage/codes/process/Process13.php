<?php
/**
 * Process13 Class
 *
 * Generate CSV file based on
 * CRM顧客差分データ
 *
 * @author Krishia Valencia
 *
 */
class Process13 {

	private $db;
	private $logger;
	private $mail;

	/**
	 * Process13 constructor
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
	 * Execute Process13
	 * @throws PDOException
	 * @throws Exception
	 */
	public function execProcess() {
		try {
			// initialize database
			$this->db = new Database($this->logger);
			// request data for 「CRM顧客差分データ」.
			$result = $this->reqData();

			// If the search results are 0件 (0 records),
			// skip the process on and after the corresponding process number and
			// proceed to the next process number
			if(empty($result)) {
				// write down the error contents in the error file.
				$this->logger->error("No record found");
				$this->logger->info("当該工程の以降の処理を飛ばし、次工程処理へ");
			}else{
				// create 「CRM顧客差分データ」 CSV file.
				$this->generateCSV($result);
			}
		} catch (PDOException $e) {
			$this->mail->sendMail();
			throw $e;
		} catch(Exception $e) {
			// write down the error contents in the error file
			$this->logger->error($e->getMessage());
			throw $e;
		}
		// close database connection
		$this->db->disconnect();
	}

	/**
	 * Search data from DB
	 * @throws PDOException
	 * @throws Exception
	 * @return result set
	 */
	private function reqData() {
		try {
			// 「m_corporation.office_id」=「blank」
			// search for 「CRM顧客差分データ」
			$fields = $this->getParam();
			$from = "m_corporation a LEFT JOIN m_industry b ";
			$from.= "ON a.business_type = b.industry_code ";
			// 2017/10/19 抽出条件に「顧客情報未削除」を追加
			$condition = "a.delete_flag = FALSE && (a.office_id = ? || a.office_id IS NULL)";
			$result = $this->db->getData($fields, $from, $condition, array(''));
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
	private function generateCSV($data) {
		global $EXPORT_FILENAME, $procNo;

		try {
			// get custom CSV header
			$customHeader = $this->getParam(true);
			// create CSV for CRMマッチング依頼作成
			$csvUtils = new CSVUtils($this->logger);
			$csvUtils->export($EXPORT_FILENAME[$procNo], $data, $customHeader);
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
				"a.corporation_code" => "顧客コード",
				"a.corporation_name" => "名称（企業名）",
				"a.zip_code" => "郵便番号",
				"a.address1" => "都道府県",
				"a.address2" => "市区郡町村名",
				"a.address3" => "町大字通称名",
				"a.address4" => "字丁目",
				"a.address5" => "番地番号",
				"a.address6" => "建物名部屋番号",
				"a.tel" => "TEL",
				"a.fax" => "FAX",
				"a.free_dial" => "フリーダイヤル",
				"a.hp_url" => "ホームページURL",
				"a.branch_office_name" => "ブランド名・支社名",
				"a.store_department" => "店舗名・部門",
				"a.representative_name" => "代表者名",
				"b.industry_name" => "業種",
				"a.corporation_name_kana" => "顧客名よみ"
		);
	}
}
?>