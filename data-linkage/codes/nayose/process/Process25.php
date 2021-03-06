<?php

/**
 * Other media-LBC matching file: nayose integration process
 *
 * @author Maricris C. Fajutagana
 *
 */
class Process25{
	private $logger, $mail, $db, $isError = false;

	const WK_T_TABLE = 'wk_t_nayose_mda_result';
	const WK_BATCH_SYNC_NO_KEY_NAME = 'wk_t_nayose_mda_result_seq_no';

	const KEY_1 = 'media_code';
	const KEY_2 = 'office_id';

	const CSV_UNIQUE = 0;
	const CSV_MULTIPLE = 1;

	const SHELL = 'load_wk_t_nayose_mda_result.sh';

	/**
	 * Process25 Class constructor
	 */
	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
	}

	/**
	 * Execute Process 25
	 */
	function execProcess(){
		global $IMPORT_FILENAME, $procNo;
		try {
			// instantiate database
			$this->db = new Database($this->logger);
			
			$this->logger->info("Acquiring CSV...");
			// get import path
			$importPath = getImportPath(true);
			$uniqueFile = getMultipleCsv($IMPORT_FILENAME[$procNo][self::CSV_UNIQUE], $importPath, $procNo);
			$multipleFile = getMultipleCsv($IMPORT_FILENAME[$procNo][self::CSV_MULTIPLE], $importPath, $procNo);
			$importFiles = array($uniqueFile, $multipleFile);
			$this->logger->info("All files are found.");

			// begin database transaction
			$this->db->beginTransaction();
			
			// execute delete data from database
			# $this->logger->info("Start truncating table ". self::WK_T_TABLE);
			# $deleteResult = $this->db->truncateData(self::WK_T_TABLE);
			# 今回用の処理番号出力
			$batchSyncNo = $this->db->getBatchSyncNo(self::WK_BATCH_SYNC_NO_KEY_NAME);

			// if successful deleted or there's no deletion
			if($batchSyncNo) {
				#$this->logger->info("Table ".self::WK_T_TABLE." truncated.");
				$commited = true;
				// loop for the unique and multiple csv file
				foreach ($importFiles as $importFile){
					// loop for inserting data from csv file
					foreach ($importFile as &$file){
						if($commited === true && shellExecAddSyncNo($this->logger, self::SHELL, $file, self::WK_T_TABLE, $batchSyncNo) === 0){
							$commited = true;
						} else {
							// shell失敗
							$commited = false;
							$this->db->rollback();
							$this->logger->error("Error File : " . $file);
							throw new Exception("Process25 Failed to insert with " . self::SHELL . " to " . self::WK_T_TABLE);
						}
					}
				}

				if($commited){
					$this->db->commit();
				}
				// request data for nayose process
				$result = $this->reqData($batchSyncNo);
				
				// generate csv from nayose process
				// データ0件の場合も空ファイル作成
				//if(!empty($result)){
				$this->generateCSV($result);
				//}
			} else {
				// rolllback transaction
				$this->db->rollback();
				throw new Exception("Failed to truncate table ". self::WK_T_TABLE);
			}
		} catch (Exception $e1){ // error
			// write down the error contents in the error file
			$this->logger->error($e1->getMessage());
			// If there are no files:
			// Skip the process on and after the corresponding process number
			// and proceed to the next process number (ERR_CODE: 602)

			// For system error pause process
			if(602 != $e1->getCode()) {
				$this->mail->sendMail($e1->getMessage());
				throw $e1;
			}
		} catch (PDOException $e2){ // database error
			if($this->db) {
				// close database connection
				$this->db->disconnect();
			}
			$this->mail->sendMail($e2->getMessage());
			// Pause process
			throw $e2;
		}
		if($this->isError){
			// send mail if there is error
			$this->mail->sendMail();
		}
		// close database connection
		$this->db->disconnect();
	}

	/**
	 * Get the table columns
	 * @param $array
	 * @param $isHeader
	 * @return string
	 */
	private function tableKeys($array, $isHeader = false){
		$fields = array(
			"0"=>"media_code",
			"1"=>"office_id",
			"2"=>"result_flg",
			"3"=>"detail_lvl",
			"4"=>"detail_content"
			);
			if($isHeader) // return the header of the csv to be created
			return array_map('strtoupper', $fields);
			else // return the map fields to data
			return mapFields($fields, $array, true);
	}

	/**
	 * Request data to be inserted into new CSV
	 * @return the result data
	 */
	private function reqData($batchSyncNo){
		try { // execute nayose process
			$paramKey = array(self::KEY_1);
			// request data
			$sql = "SELECT kei.media_code, kei.office_id, kei.result_flg, kei.detail_lvl, kei.detail_content 
					FROM ( SELECT * FROM ".self::WK_T_TABLE." WHERE batch_sync_seq_no = '".$batchSyncNo."') kei INNER JOIN
					(SELECT media_code, MIN(office_id) as office_id 
						FROM ".self::WK_T_TABLE." WHERE batch_sync_seq_no = '".$batchSyncNo."' GROUP BY ".self::KEY_1.") kei2 ON 
						kei.".self::KEY_1." <=> kei2.".self::KEY_1." 
						AND kei.".self::KEY_2." <=> kei2.".self::KEY_2;
			$result = $this->db->getDataSql($sql, null);
		} catch (PDOException $e1){
			throw $e1;
		} catch (Exception $e2){
			throw $e2;
		}
		return $result;
	}

	/**
	 * Generate CSV in export path
	 * @param $data
	 */
	private function generateCSV($data) {
		global $EXPORT_FILENAME, $procNo;

		try {
			// get custom CSV header
			$customHeader = $this->tableKeys(null, true);
			// create MDA_Result csv
			$csvUtils = new CSVUtils($this->logger);
			$csvUtils->export($EXPORT_FILENAME[$procNo], $data, $customHeader);
		} catch (Exception $e) {
			$this->logger->error("CSV出力に失敗しました. ".$EXPORT_FILENAME[$procNo]);
			throw $e;
		}
	}

}
?>