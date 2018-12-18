<?php

/**
 * Process 23
 *
 * LBC_SBN edit process
 *
 * @author Joedel Espinosa
 *
 */
class Process23 {
	private $logger, $db, $mail;
	private $isError = false;

	const WK_T_TABLE = "wk_t_nayose_lbc_sbndata";
	const WK_T_TABLE_OUTPUT = "wk_t_nayose_lbc_sbndata_output";
	const SHELL = 'load_wk_t_nayose_lbc_sbndata.sh';
	const SHELL_OUTPUT = 'write_wk_t_nayose_lbc_sbndata.sh';


	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
	}

	/**
	 * Initial Process 23
	 *
	 */
	public function execProcess(){
		global $IMPORT_FILENAME, $procNo;
		
		try {
			// Database Initialization
			$this->db = new Database($this->logger);

			$path = getImportPath(true);
			$filename = $IMPORT_FILENAME[$procNo];

			$lbcFiles = getMultipleCsv($filename, $path, $procNo);

			$this->db->beginTransaction();
			
			// Delete Old Data
			$deleteData = $this->db->truncateData(self::WK_T_TABLE);
			$committed = true;
			
			foreach($lbcFiles as &$file){
				if($committed === true && shellExec($this->logger, self::SHELL, $file, self::WK_T_TABLE) === 0){
					$committed = true;
				} else {
					// shell失敗
					$committed = false;
					$this->db->rollback();
					$this->logger->error("Error File : " . $file);
					throw new Exception("Process23 Failed to insert with " . self::SHELL . " to " . self::WK_T_TABLE);
				}
			}
			if($committed){
				$this->db->commit();
				$data = $this->editProcessData();
				if(!empty($data)){
					$this->generateCSV($data);
				}
			}

		} catch (PDOException $e1){
			$this->logger->debug("Error found in Database.");
			if($this->db){
				if(isset($count)){
					//$this->db->rollback();
				}
				// Close Database Connection
				// $this->db->disconnect();
			}
			$this->mail->sendMail($e1->getMessage());
			//throw $e1;
		} catch (Exception $e2){
			$this->logger->debug("Error found in Process.");
			$this->logger->error($e2->getMessage());
			if($this->db){
				// $this->db->disconnect();
			}
			// If there are no files:
			// Skip the process on and after the corresponding process number
			// and proceed to the next process number (ERR_CODE: 602)
			// For system error pause process
			if(602 != $e2->getCode()) {
				$this->mail->sendMail($e2->getMessage());
				//throw $e2;
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

	/**
	 *
	 * Edit format
	 * Check the 「memo_LBC_corporation information_insert.sql」 sheet 
	 * and shape the data with the written contents
	 *
	 */
	private function editProcessData(){
		//Check the 「memo_LBC_corporation information_insert.sql」 
		// sheet and shape the data with the written contents
		try {
			// execute delete data from database first
			$this->db->beginTransaction();
			
			$deleteResult = $this->db->truncateData(self::WK_T_TABLE_OUTPUT);
			
			$sql = "INSERT INTO wk_t_nayose_lbc_sbndata_output
			(
				SELECT
				T.office_id , T.head_office_id, T.top_head_office_id, 
				T.top_affiliated_office_id1, T.top_affiliated_office_id2, 
				T.top_affiliated_office_id3, T.top_affiliated_office_id4,
				T.top_affiliated_office_id5, T.top_affiliated_office_id6, 
				T.top_affiliated_office_id7, T.top_affiliated_office_id8, 
				T.top_affiliated_office_id9, T.top_affiliated_office_id10,
				T.affiliated_office_id1, T.affiliated_office_id2, 
				T.affiliated_office_id3, T.affiliated_office_id4, 
				T.affiliated_office_id5, T.affiliated_office_id6, 
				T.affiliated_office_id7, T.affiliated_office_id8, 
				T.affiliated_office_id9, T.affiliated_office_id10, 
				T.relation_flag1, T.relation_flag2, T.relation_flag3, 
				T.relation_flag4, T.relation_flag5, T.relation_flag6, 
				T.relation_flag7, T.relation_flag8, T.relation_flag9, 
				T.relation_flag10, T.relation_name1, T.relation_name2, 
				T.relation_name3,  T.relation_name4, T.relation_name5, 
				T.relation_name6, T.relation_name7, T.relation_name8, 
				T.relation_name9, T.relation_name10, T.listed_flag, 
				T.listed_name, T.sec_code, T.yuho_number, 
				T.company_stat, T.company_stat_name, T.office_stat, 
				T.office_stat_name, T.move_office_id, T.tousan_date, 
				T.company_vitality, 
				replace(replace(T.company_name, char(10), ''), char(13), '') as company_name, 
				T.company_name_kana, T.office_name, 

/*				(CASE 
					WHEN 
						T.company_zip ='森下1丁目5-5?' THEN null
					WHEN
						T.company_zip ='大央町48-1' THEN null
					WHEN
						length(T.company_zip) > 7 then
						replace(replace(replace(T.company_zip, '-', ''), '〒', ''), '‐', '')
					ELSE
					T.company_zip
				END) as company_zip,
*/
				(CASE 
					WHEN 
						(replace(replace(replace(replace(replace(replace(replace(replace(T.company_zip,'〒',''),' ',''),'　',''),'‐',''),'-',''),'－',''),'ー',''),'―','') regexp '[^0-9]') = 1 THEN ''
					WHEN
						length(replace(replace(replace(replace(replace(replace(replace(replace(T.company_zip,'〒',''),' ',''),'　',''),'‐',''),'-',''),'－',''),'ー',''),'―','')) = 7 
						THEN replace(replace(replace(replace(replace(replace(replace(replace(T.company_zip,'〒',''),' ',''),'　',''),'‐',''),'-',''),'－',''),'ー',''),'―','')
					ELSE ''
				END) as company_zip,

				T.company_pref_id, T.company_city_id, 
				T.company_addr1, T.company_addr2, T.company_addr3, 
				T.company_addr4, T.company_addr5, T.company_addr6,

				(CASE
					WHEN length(T.company_tel) > 13 THEN
					(CASE
						WHEN instr(T.company_tel,'080-') = 1 THEN
						null
						WHEN instr(T.company_tel,'090-') = 1 THEN
						null
						WHEN instr(T.company_tel,'070-') = 1 THEN
						null
						WHEN instr(T.company_tel,'(') = 1 THEN
						null
						WHEN instr(T.company_tel, ' ') > 0 then
						substr(replace(replace(T.company_tel, '-', ''),'_重複',''), 1,instr(replace(replace(T.company_tel, '-', ''),'_重複',''), ' '))
						WHEN T.company_tel = 'mgm-touin@bz04.plala' THEN
						null
					else
						trim(substr(replace(replace(T.company_tel, '-', ''),'_重複',''),1,13))
					END)
				ELSE
					T.company_tel
				END) as company_tel,
	
				(CASE when length(T.company_fax) > 13 then 
					replace(replace(T.company_fax, '03-03-','03-'), '-', '')
				ELSE
					T.company_fax
				END) as company_fax,
				T.office_count, T.capital,
				T.representative_title, T.representative, T.representative_kana, 
				T.industry_code1, T.industry_name1, 
				T.industry_code2, T.industry_name2, 
				T.industry_code3, T.industry_name3,
				T.license, T.party, T.url, T.tel_cc_flag, T.tel_cc_date, T.move_tel_no,
				T.fax_cc_flag, T.fax_cc_date, T.move_fax_no, T.inv_date, T.emp_range, 
				T.sales_range, T.income_range,
				0

	 			FROM wk_t_nayose_lbc_sbndata T)";
			// 引数は空
			$params = array();
			$query = $this->db->executeQuery($sql,$params);
			//$this->logger->info("memo_LBC_corporation information_insert.sql Executed");
			$this->logger->info("変換処理成功");
			$columns = "office_id,head_office_id,top_head_office_id,top_affiliated_office_id1,top_affiliated_office_id2,top_affiliated_office_id3,top_affiliated_office_id4,top_affiliated_office_id5,top_affiliated_office_id6,top_affiliated_office_id7,top_affiliated_office_id8,top_affiliated_office_id9,top_affiliated_office_id10,affiliated_office_id1,affiliated_office_id2,affiliated_office_id3,affiliated_office_id4,affiliated_office_id5,affiliated_office_id6,affiliated_office_id7,affiliated_office_id8,affiliated_office_id9,affiliated_office_id10,relation_flag1,relation_flag2,relation_flag3,relation_flag4,relation_flag5,relation_flag6,relation_flag7,relation_flag8,relation_flag9,relation_flag10,relation_name1,relation_name2,relation_name3,relation_name4,relation_name5,relation_name6,relation_name7,relation_name8,relation_name9,relation_name10,listed_flag,listed_name,sec_code,yuho_number,company_stat,company_stat_name,office_stat,office_stat_name,move_office_id,tousan_date,company_vitality,company_name,company_name_kana,office_name,company_zip,company_pref_id,company_city_id,company_addr1,company_addr2,company_addr3,company_addr4,company_addr5,company_addr6,company_tel,company_fax,office_count,capital,representative_title,representative,representative_kana,industry_code1,industry_name1,industry_code2,industry_name2,industry_code3,industry_name3,license,party,url,tel_cc_flag,tel_cc_date,move_tel_no,fax_cc_flag,fax_cc_date,move_fax_no,inv_date,emp_range,sales_range,income_range";
			$data = $this->db->getData($columns,self::WK_T_TABLE_OUTPUT, null);
			$this->db->commit();
		} catch(Exception $e){
			$this->db->rollback();
			$this->logger->error("変換処理でエラーが発生しました");
			$this->logger->error($sql, $e->getMessage());
			throw $e;
		}
		return $data;
	}

	private function replaceTableKeys($array){
		$fields = array(
			"0"=>"office_id",
			"1"=>"head_office_id",
			"2"=>"top_head_office_id",
			"3"=>"top_affiliated_office_id1",
			"4"=>"top_affiliated_office_id2",
			"5"=>"top_affiliated_office_id3",
			"6"=>"top_affiliated_office_id4",
			"7"=>"top_affiliated_office_id5",
			"8"=>"top_affiliated_office_id6",
			"9"=>"top_affiliated_office_id7",
			"10"=>"top_affiliated_office_id8",
			"11"=>"top_affiliated_office_id9",
			"12"=>"top_affiliated_office_id10",
			"13"=>"affiliated_office_id1",
			"14"=>"affiliated_office_id2",
			"15"=>"affiliated_office_id3",
			"16"=>"affiliated_office_id4",
			"17"=>"affiliated_office_id5",
			"18"=>"affiliated_office_id6",
			"19"=>"affiliated_office_id7",
			"20"=>"affiliated_office_id8",
			"21"=>"affiliated_office_id9",
			"22"=>"affiliated_office_id10",
			"23"=>"relation_flag1",
			"24"=>"relation_flag2",
			"25"=>"relation_flag3",
			"26"=>"relation_flag4",
			"27"=>"relation_flag5",
			"28"=>"relation_flag6",
			"29"=>"relation_flag7",
			"30"=>"relation_flag8",
			"31"=>"relation_flag9",
			"32"=>"relation_flag10",
			"33"=>"relation_name1",
			"34"=>"relation_name2",
			"35"=>"relation_name3",
			"36"=>"relation_name4",
			"37"=>"relation_name5",
			"38"=>"relation_name6",
			"39"=>"relation_name7",
			"40"=>"relation_name8",
			"41"=>"relation_name9",
			"42"=>"relation_name10",
			"43"=>"listed_flag",
			"44"=>"listed_name",
			"45"=>"sec_code",
			"46"=>"yuho_number",
			"47"=>"company_stat",
			"48"=>"company_stat_name",
			"49"=>"office_stat",
			"50"=>"office_stat_name",
			"51"=>"move_office_id",
			"52"=>"tousan_date",
			"53"=>"company_vitality",
			"54"=>"company_name",
			"55"=>"company_name_kana",
			"56"=>"office_name",
			"57"=>"company_zip",
			"58"=>"company_pref_id",
			"59"=>"company_city_id",
			"60"=>"company_addr1",
			"61"=>"company_addr2",
			"62"=>"company_addr3",
			"63"=>"company_addr4",
			"64"=>"company_addr5",
			"65"=>"company_addr6",
			"66"=>"company_tel",
			"67"=>"company_fax",
			"68"=>"office_count",
			"69"=>"capital",
			"70"=>"representative_title",
			"71"=>"representative",
			"72"=>"representative_kana",
			"73"=>"industry_code1",
			"74"=>"industry_name1",
			"75"=>"industry_code2",
			"76"=>"industry_name2",
			"77"=>"industry_code3",
			"78"=>"industry_name3",
			"79"=>"license",
			"80"=>"party",
			"81"=>"url",
			"82"=>"tel_cc_flag",
			"83"=>"tel_cc_date",
			"84"=>"move_tel_no",
			"85"=>"fax_cc_flag",
			"86"=>"fax_cc_date",
			"87"=>"move_fax_no",
			"88"=>"inv_date",
			"89"=>"emp_range",
			"90"=>"sales_range",
			"91"=>"income_range"
			);
		return mapFields($fields, $array, true);
	}

	private function generateCSV($data){
		global $EXPORT_FILENAME, $procNo;

		try {
			$exportPath = getExportPath(true);
			
			// get Custom CSV Header
			$cusHeader = $this->initHeader();

			//Create CSV Output data (select * from wk_t_nayose_lbc_sbndata_output)
			$csvUtils = new CSVUtils($this->logger);
			
			$filename = $csvUtils->generateFilename($EXPORT_FILENAME[$procNo]);
			$csvUtils->export($EXPORT_FILENAME[$procNo], $data, $cusHeader);
			/*
			$csvUtils->export($EXPORT_FILENAME[$procNo], $data, $cusHeader, $exportPath);
			if(shellExec($this->logger, self::SHELL_OUTPUT, $exportPath.$filename, self::WK_T_TABLE_OUTPUT) === 0){
				$this->logger->error("Successfully written CSV File");
			} else {
				// shell失敗
				$this->logger->error("Error File : " . $exportPath.$filename);
				throw new Exception("Process23 Failed to write CSV from " . self::SHELL_OUTPUT);
			}
			//*/
		
		} catch (Exception $e){
			$this->logger->error("CSV出力に失敗しました. ".$EXPORT_FILENAME[$procNo]);
			throw $e;
		}
	}
	
	private function initHeader(){
		return $procFields = array(
			"office_id",
			"head_office_id",
			"top_head_office_id",
			"top_affiliated_office_id1",
			"top_affiliated_office_id2",
			"top_affiliated_office_id3",
			"top_affiliated_office_id4",
			"top_affiliated_office_id5",
			"top_affiliated_office_id6",
			"top_affiliated_office_id7",
			"top_affiliated_office_id8",
			"top_affiliated_office_id9",
			"top_affiliated_office_id10",
			"affiliated_office_id1",
			"affiliated_office_id2",
			"affiliated_office_id3",
			"affiliated_office_id4",
			"affiliated_office_id5",
			"affiliated_office_id6",
			"affiliated_office_id7",
			"affiliated_office_id8",
			"affiliated_office_id9",
			"affiliated_office_id10",
			"relation_flag1",
			"relation_flag2",
			"relation_flag3",
			"relation_flag4",
			"relation_flag5",
			"relation_flag6",
			"relation_flag7",
			"relation_flag8",
			"relation_flag9",
			"relation_flag10",
			"relation_name1",
			"relation_name2",
			"relation_name3",
			"relation_name4",
			"relation_name5",
			"relation_name6",
			"relation_name7",
			"relation_name8",
			"relation_name9",
			"relation_name10",
			"listed_flag",
			"listed_name",
			"sec_code",
			"yuho_number",
			"company_stat",
			"company_stat_name",
			"office_stat",
			"office_stat_name",
			"move_office_id",
			"tousan_date",
			"company_vitality",
			"company_name",
			"company_name_kana",
			"office_name",
			"company_zip",
			"company_pref_id",
			"company_city_id",
			"company_addr1",
			"company_addr2",
			"company_addr3",
			"company_addr4",
			"company_addr5",
			"company_addr6",
			"company_tel",
			"company_fax",
			"office_count",
			"capital",
			"representative_title",
			"representative",
			"representative_kana",
			"industry_code1",
			"industry_name1",
			"industry_code2",
			"industry_name2",
			"industry_code3",
			"industry_name3",
			"license",
			"party",
			"url",
			"tel_cc_flag",
			"tel_cc_date",
			"move_tel_no",
			"fax_cc_flag",
			"fax_cc_date",
			"move_fax_no",
			"inv_date",
			"emp_range",
			"sales_range",
			"income_range"
		);
	}
}
?>
