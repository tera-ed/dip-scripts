<?php

/**
 * Publish-disabled-LBC matching file: nayose integration process
 *
 * @author Maricris C. Fajutagana
 *
 */
class Process28{
	private $logger;
	private $mail;
	private $db;
	private $isError = false;

	const WK_T_TABLE = 'wk_t_nayose_kng_result';
	const WK_BATCH_SYNC_NO_KEY_NAME = 'wk_t_nayose_kng_result_seq_no';

	const KEY_1 = 'kng_in_seq';
	const KEY_2 = 'kng_in_crpnam';
	const KEY_3 = 'office_id';

	const CSV_UNIQUE = 0;
	const CSV_MULTIPLE = 1;

	const SHELL = 'load_wk_t_nayose_kng_result.sh';
	/**
	 * Process28 Class constructor
	 */
	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
	}

	/**
	 * Execute Process 28
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
				
			// execute delete data from database first
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
							throw new Exception("Process28 Failed to insert with " . self::SHELL . " to " . self::WK_T_TABLE);
						}
					}
				}

				if($commited){
					$this->db->commit();
				}

				// update delete flag in incorrect records
				$updateResult = $this->updateRecord($batchSyncNo);

				if($updateResult >= 0){
					// request data for nayose process
					$reqResult = $this->reqData($batchSyncNo);
					// generate csv from nayose process
					// データ0件の場合も空ファイル作成
					//if(!empty($reqResult)){
					$this->generateCSV($reqResult);
					//}
				}
			}else {
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
			"0"=>"kng_in_seq",
			"1"=>"kng_in_crpnam",
			"2"=>"kng_in_keiflg",
			"3"=>"office_id",
			"4"=>"result_flg",
			"5"=>"detail_lvl",
			"6"=>"detail_content"
			);
			if($isHeader) // return the header of the csv to be created
			return array_map('strtoupper', $fields);
			else // return the map fields to data
			return mapFields($fields, $array, true);
	}

	/**
	 * Update incorrect records
	 * @return PDOStatement
	 */
	private function updateRecord($batchSyncNo){
		try {
			// begin transaction
			$this->db->beginTransaction();
			// update records
			$updateDelFlag = array('delete_flag'=>1);
			$condition = " batch_sync_seq_no = '".$batchSyncNo."' AND ".self::KEY_3." NOT IN (SELECT ".self::KEY_3." FROM (SELECT MIN(".self::KEY_3.") AS ".self::KEY_3." FROM
					".self::WK_T_TABLE." GROUP BY ".self::KEY_1.") as kei) OR ".self::KEY_3." IS NULL ";
			$result = $this->db->updateData(self::WK_T_TABLE, $updateDelFlag, $condition, null);
			// commit
			$this->db->commit();
		}catch (PDOException $e1){
			throw $e1;
		} catch (Exception $e2){
			throw $e2;
		}
		return $result;
	}

	/**
	 * Request data to be inserted into new CSV
	 * @return the result data
	 */
	private function reqData($batchSyncNo){
		try { // execute nayose process
			// request data
			$result = $this->db->getData("kng_in_seq, kng_in_crpnam, kng_in_keiflg, office_id, result_flg, detail_lvl, detail_content",
			self::WK_T_TABLE, " delete_flag IS NULL AND batch_sync_seq_no = '".$batchSyncNo."'" , null);
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