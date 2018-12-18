<?php

/**
 * Convert character code of the CSV file
 *
 * @author Maricris C. Fajutagana
 *
 */
class Process29{
	private $logger, $mail;

	/**
	 * Process29 Class constructor
	 */
	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
	}

	/**
	 * Execute Process 29
	 */
	function execProcess(){
		try {
			$noFiles = true;
			$dir = getExportPath();
			//check if file exist
			if(file_exists($dir)){
				$dh = opendir($dir);
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
				$this->logger->error("処理対象ファイルが存在しません");
			}
		} catch (Exception $e){
			$this->logger->error($e->getMessage());
			$this->mail->sendMail($e->getMessage());
			throw $e;
		}
	}

	/**
	 * Character code conversion
	 * @param unknown_type $filename
	 * @param unknown_type $permission
	 * @throws Exception
	 */
	function convFileEncoding($filename,$permission = 0700){
		global $FTP_EXPORT_PATH;

		$this->logger->debug('starts writing file ' . $filename);
		try {
			$path_after = getExportPath(true);
			$path_before = getExportPath();
			//create the directory in case export path do not exist
			if(!file_exists($path_after)){
				mkdir($path_after, $permission, true);
			}
			// ファイル文字コードの確認
			ob_start();
			system('nkf -g '.escapeshellarg($path_before).escapeshellarg($filename),$returnEncode);
			// 改行コード除去して取得
			$str = str_replace(array("\r\n", "\r", "\n"), '', ob_get_contents());
			$this->logger->debug($filename.' file Character code is ' . $str);
			ob_end_clean();
			// 想定内の文字コードかエラーチェック
			//if($str == "UTF-8" || $str == "ASCII"){
			if (preg_match('/^UTF-8/', $str) || preg_match('/^ASCII/', $str)) {
				// Character code conversion utf-8 -> sjis
				system('nkf --cp932 -sLw -x '.escapeshellarg($path_before).escapeshellarg($filename).' > '.escapeshellarg($path_after).escapeshellarg($filename) ,$return);
			}else{
				throw new Exception("Process29 Character code invalid [".$str."] file name ：".$filename);
			}

			if($return == 1){ // failed
				$this->logger->error("文字コード変換に失敗しました. file name ：.".$filename);
				throw new Exception("Process29 Character code conversion failed file name ：".$filename);
			}else{
				$this->logger->info("utf-8 -> sjis 文字コード変換に成功しました. file name ：".$filename);
			}
		} catch (Exception $e){
			$this->logger->error($e->getMessage());
			throw $e;
		}
		$this->logger->debug('ends writing file ' . $filename);
	}
}
?>