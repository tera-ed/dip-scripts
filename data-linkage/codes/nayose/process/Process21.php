<?php

/**
 * Acquire CSV file from the folder (used for storing)(LBC matching results data)
 *
 * @author Maricris C. Fajutagana
 *
 */
class Process21 {
	private $logger, $mail;

	/**
	 * Process21 Class constructor
	 */
	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
	}

	/**
	 * Execute Process 21
	 */
	function execProcess(){
		try {
			$target_array = array('CorpMaster'=>array(23,24)
			,'RivalMedia'=>array(25)
			,'Obic'=>array(26,27)
			,'NGCorp'=>array(28));

			$target_noFiles=$this->checkFileExist($target_array);

			if($target_noFiles == -1){
				throw new Exception("処理対象ファイルが揃っていません");
			} else {
				$path_before = getImportPath();
				createDir($path_before);
				$this->logger->info("Move the file found");
				// copy file
				$this->fileCopy($target_array);
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
		global  $FTP_RESPONSE_PATH,$IMPORT_FILENAME;

		$file_num = 0;
		$data_num = 0;
		$filename="";

		// file_num:実際のファイルをカウントした数（ファイルが足りなかった場合はdata_numより小さくなる）
		// data_num:本来あるはずのファイル数（ファイルの確認をした数）
		foreach ($data as $key => $dmy){
			foreach($dmy as $val){
				if(is_array($IMPORT_FILENAME[$val])){
					foreach($IMPORT_FILENAME[$val] as $name){
						$dir = $FTP_RESPONSE_PATH[$key].'/*_'.$name.'.csv';
						$file_num+=count(glob($dir));
						$data_num++;
						
						//$this->logger->debug($dir);
						if(count(glob($dir)) === 0)
							$this->logger->error("file not found : ".$name.'.csv');
					}
				}else{
					$check_filename='/*_'.$IMPORT_FILENAME[$val].'.csv';
					$dir = $FTP_RESPONSE_PATH[$key].$check_filename;
					$file_num+=count(glob($dir));
					$data_num++;
					
					//$this->logger->debug($dir);
					if(count(glob($dir)) === 0)
						$this->logger->error("file not found : ".$check_filename);
				}
			}
		}
		//echo "\nfilenum " . $file_num;
		//echo "\ndatanum " . $data_num;
		// 実際のファイル数==本来あるはずのファイル数なら成功
		if($file_num == $data_num){
			return $file_num;
		}
		return -1;
	}

	/**
	 * Prepare the files to copy
	 */
	function fileCopy($data){
		global  $FTP_RESPONSE_PATH,$IMPORT_FILENAME;

		$path_before = getExportPath();
		$filename="";

		foreach ($data as $key => $dmy){
			foreach($dmy as $val){
				if(is_array($IMPORT_FILENAME[$val])){
					foreach($IMPORT_FILENAME[$val] as $name){
						$dir = $FTP_RESPONSE_PATH[$key].'/*_'.$name.'.csv';
						$this->fileCopyMoveExec($dir,$key,$val);
					}
				}else{
					$check_filename='/*_'.$IMPORT_FILENAME[$val].'.csv';
					$dir = $FTP_RESPONSE_PATH[$key].$check_filename;
					$this->fileCopyMoveExec($dir,$key,$val);
				}
			}
		}
		return true;
	}

	/**
	 * Copy file to $IMPORT_PATH['before']
	 * Move file to Backup file
	 */
	function fileCopyMoveExec($dir,$type,$val){
		global  $FTP_RESPONSE_PATH,$IMPORT_FILENAME;

		$path_before = getImportPath();

		foreach(glob($dir) as $f_name){
			$cplog = "";$mvlog = "";
			$f_name=str_replace(array(" "), '\ ', $f_name);
			$file_name =shell_exec('basename '.$f_name);
			$file_name=str_replace(array("\r\n","\n","\r"), '', $file_name);

			$file_name_mv=$file_name;

			$file_name_mv=str_replace(array("\ "," "), '', $file_name_mv);

			// file transfer
			exec('cp '.escapeshellarg($FTP_RESPONSE_PATH[$type]).'/'.escapeshellarg($file_name).' '.escapeshellarg($path_before).escapeshellarg($file_name_mv).' 2>&1',$cplog, $return1);
			if($return1 !== 0){ // write error contents to error file, pause process
				if(sizeof($cplog) > 0)
					$this->logger->error(implode(". ",$cplog));
				throw new Exception("Process21 File transfer failed file name ： ".$file_name);
			} else // Success
				$this->logger->info("Copy file：".$file_name);

			//file backup make directory if not exist
			createDir($FTP_RESPONSE_PATH[$type].'/'.date('Ymd').'_BK/');
			exec('mv '.escapeshellarg($FTP_RESPONSE_PATH[$type]).'/'.escapeshellarg($file_name).' '.escapeshellarg($FTP_RESPONSE_PATH[$type].'/'.date('Ymd').'_BK/').escapeshellarg($file_name_mv)." 2>&1",$mvlog, $return2);
			if($return2 !== 0){ // Output error log
				if(sizeof($mvlog) > 0)
					$this->logger->error(implode(". ",$mvlog));
				throw new Exception("Process21 File backup failed file name ： ".$file_name);
			} else // Success
				$this->logger->info("Move file：".$file_name);

		}

	}
}
?>