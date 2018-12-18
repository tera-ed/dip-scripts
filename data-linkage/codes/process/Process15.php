<?php
/**
 * After creating the file/s, convert encoding from UTF-8 to SJIS and
 * upload it/them to the designated directory
 *
 * @author Evijoy Jamilan
 *
 */
class Process15{
	private $logger, $mail;

	/**
	 * Process15 Class constructor
	 */
	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
	}

	/*
	 * Converts file encoding UTF8 to SJIS
	 *
	 * @param $filename
	 */
	function convFileEncoding($filename,$permission = 0700){
		global  $FTP_EXPORT_PATH;

		$this->logger->debug('starts writing file ' . $filename);
		try{
			$path_after = getExportPath(true);
			$path_before = getExportPath();
			//create the directory in case export path do not exist
			if(!file_exists($path_after)){
				mkdir($path_after, $permission, true);
			}

			// 文字コード変換
			system('nkf --cp932 -sLw -x '.escapeshellarg($path_before).escapeshellarg($filename).' > '.escapeshellarg($path_after).escapeshellarg($filename) ,$ret1);

			if($ret1 == 1){
				throw new Exception("Process15 文字コード変換失敗　ファイル名：.".$filename);

			}
			// ファイルサーバに格納（マウントしているので$FTP_EXPORT_PATHでOK）
			switch(true){
				case preg_match('/OBC_KEI_Request/', $filename) || preg_match('/OBC_SEI_Request/', $filename):
					$PATH_NAME="Obic";
					break;
				case preg_match('/CRM_Request/', $filename):
					$PATH_NAME="CorpMaster";
					break;
				case preg_match('/KNG_Request/', $filename):
					$PATH_NAME="NGCorp";
					break;
				case preg_match('/MDA_Request/', $filename):
					$PATH_NAME="RivalMedia";
					break;
				default:
					$this->logger->error("ファイルサーバ格納失敗　ファイル名：.".$filename);
					$this->mail->sendMail();
					throw new Exception("Process15 ファイルサーバ格納失敗　ファイル名：.".$filename);
			}

			system('cp '.escapeshellarg($path_after).escapeshellarg($filename).' '.escapeshellarg($FTP_EXPORT_PATH[$PATH_NAME]).'/'.escapeshellarg($filename) ,$ret2);
			if($ret2 == 1){
				throw new Exception("Process15 FTPアップロード失敗　ファイル名：.".$filename);

			}
		}catch(Exception $e){
			throw $e;
		}
		$this->logger->debug('ends writing file ' . $filename);
	}

	/**
	 * Execute Process 15
	 */
	function execProcess(){
		try{
			$noFiles = true;
			$dir = getExportPath();
			//check if file exist
			if(file_exists($dir)){
				$dh  = opendir($dir);
				//loop all the files on the export directory
				while (false !== ($filename = readdir($dh))) {
					if ($filename != "." && $filename != "..") {
						$noFiles = false;
						$this->convFileEncoding($filename);
					}
				}
			}
			//If there are no files: write it down on the error file
			//Skip the process on and after the corresponding process number and
			//proceed to the next process number
			if($noFiles){
				$this->logger->error("No file/s on export directory.".$dir);
			}

		}catch (Exception $e){
			$this->logger->error($e->getMessage());
			$this->mail->sendMail();
			throw $e;
		}
	}

}
?>
