<?php

/**
 * Arrange CSV file (to where the file used  for the weekly batch is placed)
 *
 * @author Maricris C. Fajutagana
 *
 */
class Process30{
	private $logger, $mail;

	/**
	 * Process30 Class constructor
	 */
	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
	}

	/**
	 * Execute Process 30
	 */
	function execProcess(){
		try {
			$target_array = array('CorpMaster'=>array(23,24)
			,'RivalMedia'=>array(25)
			,'Obic'=>array(26,27)
			,'NGCorp'=>array(28));
			// 6つのファイルの存在チェック（成功時はファイル数を返却 -1はエラー）
			$target_noFiles=$this->checkFileExist($target_array);
			if($target_noFiles == -1){
				throw new Exception("処理対象ファイルが揃っていません");
			} else {
				// 週次バッチ用ディレクトリに配置する
				$this->prepareTransfer($target_array);
			}
		} catch (Exception $e){
			$this->logger->error($e->getMessage());
			$this->mail->sendMail($e->getMessage());
			throw $e;
		}
	}

	/**
	 * Check if file exists
	 */
	function checkFileExist($data){
		global $EXPORT_PATH,$EXPORT_FILENAME;

		$file_num = 0;
		$data_num = 0;
		$filename="";
		$checkPath = getExportPath(true); // /tmp/nayose_csv/Export/after/YYYYMMDD

		// file_num:実際のファイルをカウントした数（ファイルが足りなかった場合はdata_numより小さくなる）
		// data_num:本来あるはずのファイル数（ファイルの確認をした数）
		foreach ($data as $key => $dmy){
			foreach($dmy as $val){
				if(is_array($EXPORT_FILENAME[$val])){
					foreach($EXPORT_FILENAME[$val] as $name){
						$dir = $checkPath.'/*_'.$name.'.csv';
						$file_num+=count(glob($dir));
						$data_num++;
						if(count(glob($dir)) === 0)
							$this->logger->error("file not found : ".$name.'.csv');
					}
				}else{
					$check_filename='/*_'.$EXPORT_FILENAME[$val].'*.csv'; // $val = 23-28
					$dir = $checkPath.$check_filename; // /tmp/nayose_csv/Export/after/*_xxx.csv
					$file_num+=count(glob($dir));
					$data_num++;
					if(count(glob($dir)) === 0)
						$this->logger->error("file not found : ".$check_filename.'*.csv');
				}
			}
		}
		// 実際のファイル数==本来あるはずのファイル数なら成功
		if($file_num == $data_num){
			return $file_num;
		}
		return -1;
	}

	/**
	 * Prepare path of files to transfer
	 * @param $data
	 */
	function prepareTransfer($data){
		global $FTP_RESPONSE_PATH, $EXPORT_FILENAME;
		$path_after = getExportPath(true);
		$filename = "";

		foreach ($data as $key => $dmy){
			foreach ($dmy as $val){

				if(is_array($EXPORT_FILENAME[$val])){
					foreach($EXPORT_FILENAME[$val] as $name){
						$fName = '*_'.$name.'.csv';
						$this->transferFile($fName,$key,$val);
					}
				}else{
					$check_filename='*_'.$EXPORT_FILENAME[$val].'.csv';
					$fName = $check_filename;
					$this->transferFile($fName,$key,$val);
				}
			}
		}
	}
	
	/**
	 * Transfer file
	 * @param $filename
	 * @throws Exception
	 */
	function transferFile($fName,$type,$val){
		global  $FTP_RESPONSE_PATH;

		$path_after = getExportPath(true);

		$f_name = $path_after;
		$f_name = str_replace(array(" "), '\ ', $f_name);

		$this->logger->info("Start copying file with filename ".$fName." to ".$FTP_RESPONSE_PATH[$type]."/");
		system('cp '.escapeshellarg($f_name).$fName. ' ' .escapeshellarg($FTP_RESPONSE_PATH[$type].'/'), $return);
		if($return != 0){ // Error copying file
			$this->logger->error("ファイルのコピーに失敗しました. file：".$fName." to ".$FTP_RESPONSE_PATH[$type]);
			throw new Exception("Failed to copy file：".$fName." to ".$FTP_RESPONSE_PATH[$type]);
		}else // Success copying file
			$this->logger->info("end copying file with filename ".$fName);
		
	}
}
?>