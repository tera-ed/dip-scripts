<?php
/**
 * Process12  Class
 *
 * Generate CSV file based on
 * OBIC契約取引先差分データ and OBIC請求取引先差分データ
 *
 * @author Krishia Valencia
 *
 */
class Process12 {

	private $db;
	private $logger;
	private $mail;

	const OBC_KEI = 0; // 契約先情報
	const OBC_SEI = 1; // 請求先情報

	/**
	 * Process12 constructor
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
	 * Execute Process12
	 * @throws PDOException
	 * @throws Exception
	 */
	public function execProcess() {
		try {
			// initialize database
			$this->db = new Database($this->logger);
			// request data for 「OBIC契約取引先データ」 and 「OBIC請求取引先データ」.
			$result = $this->reqData();

			// If the search results are 0件 (0 records) for both
			// 「OBIC契約取引先データ」 and 「OBIC請求取引先データ」,
			// skip the process on and after the corresponding process number and
			// proceed to the next process number
			if (empty($result[self::OBC_KEI]) && empty($result[self::OBC_SEI])) {
				// write down the error contents in the error file.
				$this->logger->error("No record found");
				$this->logger->info("当該工程の以降の処理を飛ばし、次工程処理へ");
			} else {
				if (!empty($result[self::OBC_KEI])) {
					// create 「OBIC契約取引先データ」 CSV file.
					$this->generateCSV($result[self::OBC_KEI], self::OBC_KEI);
				}
				if (!empty($result[self::OBC_SEI])) {
					// create 「OBIC請求取引先データ」 CSV file.
					$this->generateCSV($result[self::OBC_SEI], self::OBC_SEI);
				}
			}
		} catch (PDOException $e) {
			$this->mail->sendMail();
			throw $e;
		} catch (Exception $e) {
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
			// 「t_lbc_obic_map.customer_code」,
			// extract the one with 「00」 value in the last digits
			$from = "t_lbc_obic_map a LEFT JOIN m_obic_application b ";

			// search for 「OBIC契約取引先データ」
			$condition = "a.customer_code LIKE ? && (a.office_id = ? || a.office_id IS NULL) && b.contract_code IS NOT NULL";
			$fields = $this->getParam(self::OBC_KEI);
			$sql = "SELECT $fields FROM $from";
			$sql.= "ON a.customer_code = b.contract_code ";
			$sql.= "WHERE $condition GROUP BY contract_code";
			$result[] = $this->db->getDataSql($sql, array('%00',''));

			// search for 「OBIC請求取引先データ」
			$condition = "a.customer_code NOT LIKE ? && (a.office_id = ? || a.office_id IS NULL) && b.billing_code IS NOT NULL";
			$fields = $this->getParam(self::OBC_SEI);
			$sql = "SELECT $fields FROM $from";
			$sql.= "ON a.customer_code = b.billing_code ";
			$sql.= "WHERE $condition GROUP BY billing_code";
			$result[] = $this->db->getDataSql($sql, array('%00',''));

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
	 * @param number $type - 0 : OBIC契約取引先データ, 1 : OBIC請求取引先データ
	 * @throws Exception
	 */
	private function generateCSV($data, $type = 0) {
		global $EXPORT_FILENAME, $procNo;

		try {
			// get custom CSV header
			$customHeader = $this->getParam($type, true);
			// create CSV for OBIC契約取引先データ or OBIC請求取引先データ
			$csvUtils = new CSVUtils($this->logger);
			$csvUtils->export($EXPORT_FILENAME[$procNo][$type],
					$data, $customHeader);
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	 * Get parameter for query and CSV header
	 * @param number $type - 0 : OBIC契約取引先データ, 1 : OBIC請求取引先データ
	 * @param boolean $isCSVHeader - if not true return fields for query
	 * @return array | string $param
	 */
	private function getParam($type = 0, $isCSVHeader = false) {
		($isCSVHeader) ?
			$param = array_values($this->procFields[$type])
		: $param = implode(",", array_keys($this->procFields[$type]));

		return $param;
	}

	/**
	 * Set value to csv parameters
	 */
	private function initParam() {
		$this->procFields = array(
			array("b.contract_code" => "契約取引先CD",
						"b.contract_name" => "契約取引先名",
						"b.contract_post_code" => "契約取引先_郵便番号",
						"b.contract__address" => "契約取引先_住所",
						"b.contract_tel" => "契約取引先_TEL"),
			array("b.billing_code" => "請求取引先CD",
						"b.billing_name" => "請求取引先名",
						"b.billing_post_code" => "請求取引先_郵便番号",
						"b.billing_address" => "請求取引先_住所",
						"b.billing_tel" => "請求取引先_TEL")
		);
	}
}
?>