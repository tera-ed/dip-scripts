<?php

/**
 * Convert the character code of the CSV file  (DB is utf8)
 *
 * @author Maricris C. Fajutagana
 *
 */
class Process22 {
	private $logger, $mail;
	const FILE_CSV_TXT = 'csv/txt';

	/**
	 * Process22 Class constructor
	 */
	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
	}

	/**
	 * Execute Process 22
	 */
	function execProcess(){
		try {
			$noFiles = true;
			$dir = getImportPath();
			if(file_exists($dir)){
				// ディレクトリの内容を読み込みます。
				if ($dh = opendir($dir)) {
					//loop all the files on the export directory
					while (false !== ($filename = readdir($dh))) {
						if($filename != "." && $filename != ".."){
							$noFiles = false;
							$fileType = $this->getFileType($filename);
							if(self::FILE_CSV_TXT === $fileType){
								$this->convFileEncoding($filename);
							}
						}
					}
					closedir($dh);
				}
			}
			//If there are no files: write it down on the error file
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
	 * returns file type
	 * @param string $filename
	 * @return string filetype
	 */
	function getFileType($filename){
		$fileType = 0;
		try{
			$file_ext = pathinfo($filename, PATHINFO_EXTENSION);
			if(in_array($file_ext, array('csv','txt'))){
				$fileType = self::FILE_CSV_TXT;
			}
		}catch(Exception $e){
			throw $e;
		}
		return $fileType;
	}

	/*
	 * Converts file encoding SJIS to UTF8
	 *
	 * @param $filename
	 */
	function convFileEncoding($filename,$permission = 0700){

		try{
			$path_after = getImportPath(true);
			$path_before = getImportPath();
			//create the directory in case import path do not exist
			if(!file_exists($path_after)){
				mkdir($path_after, $permission, true);
			}
			// ファイル文字コードの確認
			ob_start();
			system('nkf -g '.escapeshellarg($path_before).escapeshellarg($filename),$returnEncode);
			$str = str_replace(array("\r\n", "\r", "\n"), '', ob_get_contents());
			$this->logger->debug($filename.' file Character code is ' . $str);
			ob_end_clean();
			// 想定内の文字コードかエラーチェック
			//if($str == "Shift_JIS" || $str == "ASCII" || $str == "BINARY"){
			if (preg_match('/^Shift_JIS/', $str) 
				|| preg_match('/^ASCII/', $str) 
				|| preg_match('/^BINARY/', $str)) {
				// Character code conversion sjis -> utf-8
				system('nkf --cp932 -x -wLu '.escapeshellarg($path_before).escapeshellarg($filename).' > '.escapeshellarg($path_after).escapeshellarg($filename), $return);
			}else{
				throw new Exception("Process22 Character code invalid [".$str."] file name ：".$filename);
			}

			if($return !== 0){ // failed
				$this->logger->error("Character code conversion failed. file name ：.".$filename);
				throw new Exception("Process22 Character code conversion failed file name ：".$filename);
			}else{
				$this->logger->info("sjis -> utf-8 Character code conversion failed. file name ：".$filename);
			}
			
		}catch(Exception $e){
			throw $e;
		}
		$this->logger->debug('ends writing file ' . $filename);
	}
}
?>
