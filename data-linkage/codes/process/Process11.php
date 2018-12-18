<?php

/**
 * Process 11
 *
 * Acquire data from Excel他媒体データ and perform registration to
 * t_excel_media_history and t_media_match_wait
 *
 *
 */

class Process11 {
	private $logger, $db, $validate, $mail, $fieldTitles;
	private $isError = false;

	const WK_B_TABLE = 'wk_t_tmp_mda_excel';
	const WK_B_TABLE2 = 't_excel_media_info';
	const LIMIT = 5000;
	const OFFSET_LIMIT = 100000;

	const SHELL1 = 'load_wk_t_tmp_MDA_EXCEL.sh';
	const SHELL2 = 'load_t_excel_media_history.sh';
	const SHELL3 = 'load_t_media_match_wait.sh';

	const OUT_FILE_NAME1 = '11_t_excel_media_history';
	const OUT_FILE_NAME2 = '11_t_media_match_wait';
	const OUTPUT_PROGRESS = 100000;
	const LF_CODE = "\n";

	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
		$this->validate = new Validation($this->logger);
		$this->fieldTitles = array();

	}

	/**
	 * Execute Process 11
	 *
	 */
	function execProcess(){
		global $MAX_COMMIT_SIZE, $IMPORT_FILENAME, $procNo;
		try{
			//initialize Database
			$this->db = new Database($this->logger);

			$path = getImportPath(true);
			$files = getMultipleCsv($IMPORT_FILENAME[$procNo], $path);

			//initialize field_title list
			// フィールドタイトル取得
			$this->acquireExcelMediaItemsFieldTitle();

			// すでに取り込み済みファイル名取得
			$media_history_array = $this->db->getData("file_name",'t_excel_media_history','file_name != ? GROUP BY file_name',array('初期データ移行'));
			$media_history_array = array_column($media_history_array, null, 'file_name');

			//initialize night_job.keywords
			// ナイトJOB取得 1：事業内容、2：職種、3：会社名毎にキーワード取得
			$this->setNightJobKeywords();
			$header = getDBField($this->db,self::WK_B_TABLE);
			$limit = self::LIMIT;
			foreach ($files as $fName) {
				//csvファイルのヘッダー取得
				// ファイルポインタを開く
				//$this->logger->debug(" -- fopen before --".$fName);
				$handle = fopen($fName, "r");
				//$this->logger->debug(" -- fopen after --".$fName);
				// 最初の一行目取得
				$ret_csv = fgetcsv($handle, 1000, ",");
				for ($i=0; $i < count($ret_csv); $i++) {
					$csvheader[$i] = $ret_csv[$i];
				}
				// 開いたファイルポインタを閉じる
				fclose($handle);

				// ファイル情報
				$fileInfo = pathinfo($fName);

				// ファイル名取得
				$file_name = $fileInfo['basename'];

				// すでに取り込み済みか確認 重複していればerrorログを出力
				if(isset($media_history_array[$file_name])){
					$this->logger->error("$file_name already exist in [Excel他媒体取込済] table");
				}

				// エリア名取得
				$area_name = $this->getAreaName($file_name);

				// OFFSETカウンター
				$offsetCount = 0;
				//Acquire from: 「./tmp/csv/Import/after/(yyyymmdd)/YYYYMMDDhhmmss_.csv」
				$this->db->beginTransaction();
				if(shellExec($this->logger, self::SHELL1, $fName) === 0){
					$this->db->commit();
					while ($offsetCount <=self::OFFSET_LIMIT) {
						$offset = ($limit * $offsetCount);
						$csvList = $this->db->getLimitOffsetData("*", self::WK_B_TABLE, null, array(), $limit, $offset);
						if (count($csvList) === 0) {
							// 配列の値がすべて空の時の処理
							break;
						}

						$file_name1 = self::OUT_FILE_NAME1 . '_' . date("YmdHis") . '.csv';
						$file_name2 = self::OUT_FILE_NAME2 . '_' . date("YmdHis") . '.csv';
						$out_file1 = new SplFileObject($path . $file_name1, 'w');	// ファイル作成
						$out_file2 = new SplFileObject($path . $file_name2, 'w');	// ファイル作成
						$in_file_row_cnt	= 0;	// 入力ファイル行カウンター
						$out_file_row_cnt1	= 0;	// 出力ファイル行カウンター
						$out_file_row_cnt2	= 0;	// 出力ファイル行カウンター
						foreach($csvList as $row => &$data){
							if(($in_file_row_cnt % $MAX_COMMIT_SIZE) == 0){
								$this->db->beginTransaction();
							}
							$tableData = emptyToNull($data);
							$in_file_row_cnt++;
							// メディア名を変更
							$change_media_name = $this->getMediaName($tableData[$header[1]], $area_name);
							$tableData[$header[1]] = $change_media_name;
							if($this->validateData($tableData, $row, $header, $file_name)){
								// t_excel_media_history CSV
								$history_arry = $this->regExcelMediaHistory($tableData, $file_name);

								# t_excel_media_info UPSERT
								$this->upsertExcelMediaInfo($history_arry, $tableData[$header[0]]);

								/* t_excel_media_history CSV出力 *****************************************/
								$history_str	= implode('","', $history_arry);
								$history_str	= '"' . $history_str . '"' . self::LF_CODE;
								$out_file1->fwrite($history_str);
								/* t_excel_media_history LOG出力 *****************************************/
								++$out_file_row_cnt1;
								if($out_file_row_cnt1 % self::OUTPUT_PROGRESS == 0){
									$this->logger->debug($file_name1." [CSV出力件数 : " . $out_file_row_cnt1." ]");
								}
								// t_media_match_wait CSV
								$ins_arry = $this->regMediaMatchWait($tableData, $history_arry[0], $area_name, $header, $file_name);
								if (count($ins_arry) === 0) {
									// csvデータ作成失敗
									// 20170502ナイト系のメッセージレベルをErrorからInfoに変更
									$this->logger->info("Process11 Failed to t_media_match_wait ナイト系 media_code:".$history_arry[0]." ".self::OUT_FILE_NAME2." of " . $file_name);
									/* エラーになったときに入力ファイル行カウンターが max_commit_size に達しているか、ファイルの最後の行なら、コミットして次の処理へ */
									if(($in_file_row_cnt % $MAX_COMMIT_SIZE) == 0 || ($row + 1) == sizeof($csvList)){
										$this->db->commit();
									}
									continue;
								}
								/* t_media_match_wait CSV出力 *****************************************/
								$ins_str	= implode('","', $ins_arry);
								$ins_str	= '"' . $ins_str . '"' . self::LF_CODE;
								$out_file2->fwrite($ins_str);
								/* t_media_match_wait LOG出力 *****************************************/
								++$out_file_row_cnt2;
								if($out_file_row_cnt2 % self::OUTPUT_PROGRESS == 0){
									$this->logger->debug($file_name2." [CSV出力件数 : " . $out_file_row_cnt2." ]");
								}
							}else{
								$this->isError = true;
								$this->logger->info("[".$file_name."] [Row : " . $in_file_row_cnt . " ] Not validateData");
							}
							/* 入力ファイル行カウンターが max_commit_size に達するたびにコミット */
							if(($in_file_row_cnt % $MAX_COMMIT_SIZE) == 0 || ($row + 1) == sizeof($csvList)){
								$this->db->commit();
							}
						}
						$offsetCount++;
						if($out_file_row_cnt1 > 0){
							$this->logger->debug($file_name1." [CSV出力件数（合計） : " . $out_file_row_cnt1." ]");
							$this->db->beginTransaction();
							if(shellExec($this->logger, self::SHELL2, $out_file1->getRealPath()) === 0){
								$this->db->commit();
							} else {
								// shell失敗
								$this->db->rollback();
								$this->logger->error("Error File : " . $out_file1->getRealPath());
								throw new Exception("Process11 Failed to insert with " . self::SHELL2 . " to t_excel_media_history");
							}
							unlink($out_file1->getRealPath());	// ファイル削除
						}
						if($out_file_row_cnt2 > 0){
							$this->logger->debug($file_name2." [CSV出力件数（合計） : " . $out_file_row_cnt2." ]");
							$this->db->beginTransaction();
							if(shellExec($this->logger, self::SHELL3, $out_file2->getRealPath()) === 0){
								$this->db->commit();
							} else {
								// shell失敗
								$this->db->rollback();
								$this->logger->error("Error File : " . $out_file2->getRealPath());
								throw new Exception("Process11 Failed to insertor update with " . self::SHELL3 . " to t_media_match_wait");
							}
							unlink($out_file2->getRealPath());	// ファイル削除
						}
					}
				} else {
					// shell失敗
					$this->db->rollback();
					$this->logger->error("Error File : " . $fName);
					throw new Exception("Process11 Failed to insert with " . self::SHELL1 . " to " . self::WK_B_TABLE);
				}
			}
		} catch (PDOException $e1){ // database error
			$this->logger->debug("Error found in database.");
			if($this->db) {
				if(isset($in_file_row_cnt)){
					if ($in_file_row_cnt > 0) {
						$this->db->rollback();
					}
				}
				// close database connection
				$this->db->disconnect();
			}
			$this->mail->sendMail();
			throw $e1;
		} catch (Exception $e2){ // error
			// write down the error contents in the error file
			$this->logger->debug("Error found in process.");
			$this->logger->error($e2->getMessage());
			if($this->db) {
				// close database connection
				$this->db->disconnect();
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
	 * validate csv data
	 * Regarding the check contents, refer to sheet: 「Excel他媒体データエラーチェック」
	 * @param $array List data from CSV
	 * @return boolean
	 */
	function validateData($data, $row, $header, $filename){
		$bool = true;
		try {
			$media_name=$header[1];

			// 媒体名があるかどうか
			if(!$this->isNull($data[$media_name])){
				if($data[$media_name] == "town_work")$data[$media_name] = "TownWork";
				if($this->db->getDataCount('m_media_mass', 'media_name=?', array($data[$media_name])) > 0){
					$bool = $this->validate->execute($data, $this->fieldsChecking($header,$filename), $row, $filename, $header);
					$this->logger->info("[$filename] ROW[$row] :$media_name:$data[$media_name]"." exist on [他媒体マスター].");
				}else{
					$this->logger->error("[$filename] ROW[$row] :$media_name:$data[$media_name]"." do not exist on [他媒体マスターDBマッチ無].");
					$bool = false;
				}
			}else{
				$row = $row + 1;
				$this->logger->error("[$filename] ROW[$row] :$media_name:$data[$media_name]"." do not exist on [他媒体マスターデータ無].");
				$bool = false;
			}
		} catch (Exception $e){
			$this->logger->debug("Failed Checking Contents of Array");
			throw $e;
		}
		return $bool;
	}

	/**
	 * Acquire field_title list on m_excel_media_items table
	 * @throws Exception
	 * @return array list of field_title
	 */
	function acquireExcelMediaItemsFieldTitle(){
		try{
			$sql  =' SELECT field_title';
			$sql .=' FROM m_excel_media_items';
			$sql .=' WHERE delete_flag = 0';
			$sql .=' ORDER BY CAST(item_code AS DECIMAL)';
			$rows = $this->db->getDataSql($sql);
			while($row = array_shift($rows)){
				$this->fieldTitles[] = $row['field_title'];
			}
		}catch(Exception $e){
			throw $e;
		}
	}

	/**
	 * acquire data from m_nigth_job by diff_item_kbn
	 * @throws Exception
	 */
	function setNightJobKeywords(){
		try{
			for($i = 1; $i <=3; $i++){
				$rows = $this->db->getData("keyword", "m_night_job", "diff_item_kbn=?", array($i));
				while($row = array_shift($rows)){
					$this->{'keywordsKbn'.$i}[] = $row['keyword'];
				}
			}
		}catch(Exception $e){
			throw $e;
		}
	}

	/**
	 * Check if string contains a value in array of keywords
	 * @param int $kbn
	 * @param string $val
	 * @return boolean
	 */
	function isKeywordExist($data){

		foreach($data as $kbn => $val){
			foreach ($this->{'keywordsKbn'.$kbn} as $keyword) {
				if (strpos($val, $keyword) !== FALSE) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Filename check
	 * @throws Exception
	 * @return boolean true/false
	 */
	function isFilenameExist($filename){
		if(!$this->isNull($filename)){
			if($this->db->getDataCount('t_excel_media_history', 'file_name=?', array($filename)) > 0){
				$this->logger->error("$filename already exist in [Excel他媒体取込済] table");
			}
		}
		return;
	}

	/**
	 * acquire the compe_media_code from m_media_mass by media_name
	 * 他媒体マスターからメディア名を使い競合媒体コードとメディアタイプを出力
	 *
	 * @param string $media_name
	 * @throws Exception
	 * @return string compe_media_code
	 */
	function getMediaMassInfoByName($media_name){
		try{
			$val = array('compe_media_code' => null,'media_type' => null);
			if(!$this->isNull($media_name)){
				if($media_name == "town_work")$media_name = "TownWork";
 				$result = $this->db->getData("compe_media_code, media_type", "m_media_mass", "media_name=?", array($media_name));
 				if($result){
 					$val['compe_media_code'] = $result[0]['compe_media_code'];
 					$val['media_type'] = $result[0]['media_type'];
 				}
	 		}
		}catch(Exception $e){
			throw $e;
		}
		return $val;
	}

	/**
	 * returns true if value has no value otherwise returns false
	 * @param string $val
	 * @return boolean true/false
	 */
	function isNull($val){
		$result = true;
		if($val != null && $val != ''){
			$result = false;
		}
		return $result;
	}

	/**
	 * Converts Sting to Date
	 * @param string $val
	 * @return NULL
	 */
	function cnvStrToDate($val){
		$result = null;
		if(!$this->isNull($val)){
			$result = date('Y/m/d',strtotime($val));
		}
		return $result;
	}

	/**
	 * Pull out the parts that are in the 「｛｝」
	 * the file name to be processed.
	 *
	 * @param string $filename
	 * @return area name
	 */
	function getAreaName($filename){
		mb_internal_encoding("UTF8");
		$data="";
		if(preg_match('/大阪市内/', $filename)){
			$data = "関西";
		}else if(preg_match('/ナゴヤ/', $filename)){
			$data = "東海";
		}else{
			preg_match('#\{(.*?)\}#', $filename, $match);
			$data=isset($match[1])? $match[1]:"全エリア";
		}
		$this->logger->info("file_name:$filename エリア名:$data ");
		return $data;
	}

	// townwork特殊処理
	function getMediaName($media_name,$area_name){
		mb_internal_encoding("UTF8");
		$data="";

		if ($media_name != null && $media_name != "") {
			// 空白削除
			$check_media_name  = preg_replace("/( |　)/", "", $media_name );
			if(preg_match('/townwork/', $check_media_name)){
				$change_flg = true;
				//TownWork-関東,TownWorkナゴヤ-東海,TownWork大阪市内-関西,TownWork東海-中部,TownWork東海-東海,TownWork関西-九州,TownWork関西-関西
				if($check_media_name=="townwork" && $area_name=="関東"){
					$data = "TownWork";
				}elseif($check_media_name=="townworkナゴヤ版" && $area_name=="東海"){
					$data = "TownWorkナゴヤ";
				}elseif($check_media_name=="townwork" && $area_name=="中部"){
					$data = "TownWork東海";
				}elseif($check_media_name=="townwork" && $area_name=="東海"){
					$data = "TownWork東海";
				}elseif($check_media_name=="townwork大阪市内版" && $area_name=="関西"){
					$data = "TownWork大阪市内";
				}elseif($check_media_name=="townwork" && $area_name=="九州"){
					$data = "TownWork関西";
				}elseif($check_media_name=="townwork" && $area_name=="関西"){
					$data = "TownWork関西";
				}else{
					$data=$media_name;
					$change_flg = false;
				}
				if ($change_flg) {
					$this->logger->info("townworkメディア名変更 元:$media_name → 修正:$data");
				}
			}elseif (preg_match('/FromAnavi/', $check_media_name)){
				$data = "FromA-Navi";
				$this->logger->info("FromAnaviメディア名変更 元:$media_name → 修正:$data");

			}else{
				$data=$media_name;
			}
		}
		return $data;
	}

	/**
	 * Convert to whole width (2 bytes)
	 * @param String $str
	 * @return string corporation name
	 */
	function fmtCorpName($str){
		if(!$this->isNull($str)){
			mb_internal_encoding("UTF8");
			//  A:「半角」英数字を「全角」 S:「半角」スペースを「全角」 K:「半角カタカナ」を「全角カタカナ」
			//  V:濁点付きの文字を一文字に変換します
			$str =  mb_convert_kana($str, 'ASKV');
			if(substr_count($str, '＊') >= 3){
				$str .= '☆';
			}
		}
		return $str;
	}

	/**
	 * Blank if there is value in [R系電話番号マスター(m_r_type_tell.tel)]
	 * @param string $tel
	 * @throws Exception
	 * @return string tel
	 */
	function getTel($tel){
		$cnt = 0;
		if(!$this->isNull($tel)){
			$cnt = $this->db->getDataCount('m_r_type_tell', ' tel=?', array($tel));
		}
		return $cnt > 0 ? null : $tel;
	}

	/**
	 * register the first 250 characters only
	 * @param string $val
	 * @return string business_content
	 */
	function limitStrLen($val, $maxLen = 250){
		$result = $val;
		if(!$this->isNull($val)){
			$result = mb_substr($val,0,$maxLen, "utf-8");
		}
		return $result;
	}

	/**
	 * Returns 上場市場 (listed name)
	 * @param unknown_type $val
	 */
	function getListedName($val){
		$value= '';
		//If the [上場市場](listed_marked) of the process-target record is
		//(null or blank), substitute 「0」 in the [上場市場(listed_marked)].
		$val = $this->isNull($val) ? '0' : $val;
		try{
			//If the(listed_marked) of the process-target record is not
			//(null or blank), substitute the [m_listed_media.listed_name] of the
			//他媒体上場市場マスター ] and the matched [m_listed_media.listed_code].
			if($val!=0){
				$result = $this->db->getData("new_listed_code", "m_listed_media", "listed_code=?", array($val));
				$value = $result[0]['listed_name'];
			}else{
				$value = $val;
			}
		}catch(Exception $e){
			throw $e;
		}
		return $value;
	}

	/**
	 * Register data to t_excel_media_history table
	 * @param array $data
	 */
	function regExcelMediaHistory($list,$filename){
		$data = array();
		try{
			//Following the increment rule, increment media_code when new
			$data[] = $this->db->getNextVal('MEDIA_CODE');
			//Acquired Excel(CSV) file name
			$data[] = $filename;
			//Bind the all the column data from 1～36 with each item 「'」, then
			//with the 「,」 separator, combine into string and register that value.
			$data[] = "'".implode("','", $list) ."'";
			$data[] = date("Y/m/d H:i:s");
		}catch(Exception $e){
			throw $e;
		}
		return $data;
	}

	function upsertExcelMediaInfo($list,$no){
		$media_code = $list[0]; # Media_code
		$file_name  = $list[1]; # File_Name
		# $date_time  = $list[3]; # Create_date or Update_date

		try {
			$dataCount = $this->db->getDataCount(self::WK_B_TABLE2, " no = ? AND file_name = ? ", array( $no, $file_name ));
			if($dataCount <= 0 ){ // Data not found, insert
				$insertFields = array(
						'no' => $no,
						'file_name' => $file_name,
						'media_code' => $media_code);
				$result = $this->db->insertData(self::WK_B_TABLE2, $insertFields);
			} else { // Data found, update
				$updateFields = array(
						'media_code' => $media_code);
				$result = $this->db->updateData(self::WK_B_TABLE2, $updateFields, " no = ? AND file_name = ? ", array( $no, $file_name ));
			}
		}catch(Exception $e){
			$this->logger->error($e->getMessage());
			throw $e;
		}
		return $result;
	}

	/**
	 * register data to t_media_match_wait
	 * @param array $val csv row data
	 * @param string $filename
	 * @return array
	 */
	function regMediaMatchWait($val, $mediaCode, $area_name, $header, $file_name){
		$data = array();
		try{
			// ナイトチェック 1：事業内容、2：職種、3：会社名毎にキーワード取得
			if($this->isKeywordExist(array(1=>$val[$header[3]],2=>$val[$header[4]],3=>$val[$header[5]]))){
				return $data;
			}

			// 媒体名→競合媒体コード、媒体種別取得
			$mediaMassInfo = $this->getMediaMassInfoByName($val[$header[1]]);

			// リクナビ派遣特殊処理 フラグ数 28列目の[フラグスペース]を25列目の[フラグ数]へ移動
			if(preg_match('/リクナビ派遣/', $file_name)){
				$log_temp=$val[$header[24]];
				$val[$header[24]]=$val[$header[27]];
				$this->logger->info("リクナビ派遣特殊処理：フラグスペースをフラグ数へ 元フラグ数:".$log_temp." → 修正フラグ数:".$val[$header[24]]);
			}

			// ジョブセンスのみ金額、プラン無とする
			if(preg_match('/ジョブセンス/', $file_name)){
				$calc_data['amount']=null;
				$calc_data['plan']="";
			}else{
				// 金額,プラン名取得 @媒体コード 媒体名 エリア名 広告スペース フラグ数
				$calc_data	= $this->getAmountAndPlan($mediaCode, $val[$header[1]], $area_name, $val[$header[18]], $val[$header[24]]);
			}

			// Excel上の掲載案件数が空だった場合は、広告スペースとフラグ数の処理した数を掲載案件数とする 20160818tyamashita
			if( ($val[$header[21]] == null || $val[$header[21]] == "") && $calc_data['post_count'] != 0){
				$this->logger->info("掲載案件数がExcel上にないため、料金処理した数で補完".$mediaCode." 修正元:".$val[$header[21]]." → 修正後:".$calc_data['post_count']);
				$val[$header[21]] = $calc_data['post_count'];
			}
			// 400文字を超えている場合はスペースは空欄に 20160818tyamashita
			if(strlen($val[$header[18]]) >= 400){
				$this->logger->info("スペースが400文字を超えているため空欄にする:".$mediaCode);
				$val[$header[18]] = "";
			}

			$data[]	= $mediaCode;									//媒体コード
			$data[]	= $mediaMassInfo['compe_media_code'];			//競合媒体コード
			$data[]	= $area_name;									//エリア名
			$data[]	= $calc_data['amount']; 						//金額
			$data[]	= $this->cnvStrToDate($val[$header[2]]);		//掲載開始日
			$data[]	= $this->cnvStrToDate($val[$header[26]]);		//データ取得日
			$data[]	= $calc_data['plan'];							//プラン名
			$data[]	= $this->limitStrLen($val[$header[18]], 400);	//スペース(広告スペース)
			$data[]	= $val[$header[24]];							//フラグ数
			$data[]	= $val[$header[21]];							//掲載案件数
			$data[]	= $mediaMassInfo['media_type'];					//媒体種別
			$data[]	= $this->limitStrLen($val[$header[4]]);			//広告種別(職種)
			$data[]	= $val[$header[19]];							//職種(大カテゴリ)
			$data[]	= $val[$header[20]];							//職種分類(小カテゴリ)
			$data[]	= $val[$header[34]];							//備考(memo)
			$data[]	= $this->fmtCorpName($val[$header[5]]);			//企業名(会社名)
			$data[]	= $val[$header[6]];								//郵便番号
			$data[]	= $val[$header[7]];								//都道府県
			$data[]	= $val[$header[8]];								//住所1
			$data[]	= $val[$header[9]];								//住所2
			$data[]	= $val[$header[10]];							//住所3
			$data[]	= $this->getTel($val[$header[11]]);				//TEL
			$data[]	= $val[$header[12]];							//担当部署
			$data[]	= $val[$header[13]];							//担当者名
			$data[]	= $this->getListedName($val[$header[14]]);		//上場市場
			$data[]	= $val[$header[15]];							//従業員数
			$data[]	= $val[$header[16]];							//資本金
			$data[]	= $val[$header[17]];							//売上高
			$data[]	= $val[$header[22]];							//派遣
			$data[]	= $val[$header[23]];							//紹介
			$data[]	= $val[$header[25]];							//FAX
			$data[]	= $this->limitStrLen($val[$header[3]], 400);	//業態メモ(事業内容)
			$data[]	= date("Y/m/d H:i:s");							//更新日
			//20170501 add
			$data[]	= $val[$header[36]];							//請求取引先CD
			$data[]	= $val[$header[37]];							//COMP No
			$data[]	= $val[$header[31]];							//募集雇用形態
			$data[]	= $val[$header[38]];							//媒体種別詳細
		}catch(Exception $e){
			throw $e;
		}
		return $data;
	}

	/**
	 * Created Array for Checking Values
	 * @return Array
	 *
	 */
	function fieldsChecking($header,$filename){

		/*
		 * CSV項目
		*  № 媒体名 掲載開始日 事業内容 職種 会社名 郵便番号 都道府県 住所1 住所2 住所3
		*  TEL 担当部署 担当者名 上場市場 従業員数 資本金 売上高 広告スペース 大カテゴリ 小カテゴリ
		* 掲載案件数 派遣 紹介 フラグ数 FAX データ取得日 メール URL 代表者名 設立日
		* オプション(勤務形態等) オプション(アピール項目等) プレミアム画像 memo 掲載URL
		*/

		$fields = array();
		for($i = 0; $i <= 26; $i++){
			$fields[$header[$i]] = '';
		}
		$fields[$header['2']] = "DATE";
		$fields[$header['18']] = "NC";
		$fields[$header['21']] = "D,L:10";
		$fields[$header['24']] = "D,L:10";
		$fields[$header['26']] = "DATE";

		// リクナビ派遣特殊処理 フラグ数 28列目の[フラグスペース]を25列目の[フラグ数]へ移動させるためチェック追加
		if(preg_match('/リクナビ派遣/', $filename)){
			$fields[$header['27']] = "D,L:10";
		}
		return $fields;
	}

	/**
	 * Returns 他媒体料金マスタ
	 * @param unknown_type $val
	 */
	function getDataMCharge($media_name, $key_kbn, $num, $area_name){
		try{
			$result = $this->db->getData("plan_name,amount", "m_charge", "media_name=? && area=? && kbn=? && number=?", array($media_name, $area_name, $key_kbn, $num));
			// 検索で当てはまらなければエリアを「全エリア」にして再検索
			if(!isset($result[0])){
				# index効くように順番変更
				$result = $this->db->getData("plan_name,amount", "m_charge", "media_name=? && area=? && kbn=? && number=?", array($media_name, "全エリア", $key_kbn, $num));
				$this->logger->info("m_charge 全エリア再検索。media_name:$media_name 区分:$key_kbn number:$num");

			}else{
				$this->logger->info("m_charge 通常検索。media_name:$media_name 区分:$key_kbn number:$num エリア名:$area_name");
			}
		}catch(Exception $e){
			throw $e;
		}
		return $result;
	}

	/**
	 * 他媒体CSV ->
	 *
	 */
	function getAmountAndPlan($media_code, $media_name, $area_name, $space, $flag_count){

		$cond_kbn_space			= "広告スペース";
		$cond_kbn_flag_count	= "フラグ数";

		$space_arry			= array();
		$flag_count_arry	= array();

		// space explode
		if ($space == null || $space == "") {
			$space_arry	= array();
		} else if(strpos($space,',') === false){
			$space_arry = array($space);
		} else {
			$space_arry	= explode(",", $space);
		}

		// flag_count explode
		if ($flag_count == null || $flag_count == "") {
			$flag_count_arry	= array();

		} else if(strpos($flag_count,',') === false){
				$flag_count_arry = array($flag_count);

		} else {
			$flag_count_arry	= explode(",", $flag_count);
		}

		if(empty($space_arry) && empty($flag_count_arry)){
			// 2017/10/26 ログレベルを修正（ERROR→INFO）
			$this->logger->info("t_media_match_wait 広告スペース、フラグ数ともにnullのため料金算出なし。media_code:$media_code");
			return array('amount'=>0,'plan'=>null);
		}

		if(!empty($space_arry)){
			$charger_array[$cond_kbn_space] = $space_arry;
		}

		if(!empty($flag_count_arry)){
			$charger_array[$cond_kbn_flag_count] = $flag_count_arry;
		}

		// 合計金額
		$amount = 0;
		// プラン名保存用
		$plan = "";
		// 区切り文字
		$slash="";
		// 掲載案件数
		$post_count=0;

		foreach ($charger_array as $key_kbn => $dmy) {
			// 配列初期セット
			$aftre_checked_value[$key_kbn] = array();
			foreach ($dmy as $value){
				$value=(int)trim($value);

				// 過去の検索条件と一致するか調べ、同条件だったら金額のみ加算し次のループへ
				if (array_key_exists($value, $aftre_checked_value[$key_kbn])) {
					$amount += $aftre_checked_value[$key_kbn][$value];
					$post_count++;// フラグとスペースの処理数だけ掲載案件数をカウントアップ 20160818
					continue;
				}

				// m_chargeからプランと料金を取得
				$m_charger = $this->getDataMCharge($media_name, $key_kbn, $value, $area_name);

				if(isset($m_charger[0])){
					$amount+=$m_charger[0]['amount'];
					$plan .= $slash.$m_charger[0]['plan_name'];
					if($key_kbn == $cond_kbn_flag_count)$plan .="※ﾌﾗｸﾞ";
					$slash="/";
					// フラグとスペースの処理数だけ掲載案件数をカウントアップ 20160818
					$post_count++;
					// 検索値の金額を保存
					$aftre_checked_value[$key_kbn][$value]=$m_charger[0]['amount'];
				}else{
					$this->logger->error("他媒体料金マスタにヒットしませんでした。media_code:$media_code 条件は以下の通りです。media_name:$media_name area_name:$area_name 区分:$key_kbn 番号:$value");
				}
			}
		}

		$return_array['amount']	= $amount;
		$return_array['plan']	= $plan;
		$return_array['post_count']	= $post_count;// 掲載案件数 20160818

		return $return_array;
	}
}
?>