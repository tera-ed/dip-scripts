<?php
require_once('lib/PHPExcel.php');

/**
 * Convert the character code of the CSV file
 *
 * @author Evijoy Jamilan
 *
 */
class Process2{

	private $logger, $mail;
	const FILE_EXCEL = 'excel';
	const FILE_CSV_TXT = 'csv/txt';

	/**
	 * Process2 Class Constructor
	 */
	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
	}

	/*
	 * Converts file encoding SJIS to UTF8
	 *
	 * @param $filename
	 */
	function convFileEncoding($filename, $path_before, $path_after){
		try{
			//create the directory in case import path do not exist
			createDir($path_after);
			// 文字コード変換
			shell_exec('nkf --cp932 -wLu -x '.escapeshellarg($path_before).escapeshellarg($filename).' > '.escapeshellarg($path_after).escapeshellarg($filename));

		}catch(Exception $e){
			throw $e;
		}
		$this->logger->debug('ends writing file ' . $filename);
	}

	/**
	 * get excel version
	 * @param string $filePath excel file path
	 * @return string excel version
	 */
	function getExcelVersion($filePath){
		try{
			$file_ext = pathinfo($filePath, PATHINFO_EXTENSION);
			$excelVersion = '';
			if($file_ext == 'xls'){
				$excelVersion = 'Excel5';
			}else if($file_ext == 'xlsx'){
				$excelVersion = 'Excel2007';
			}
		}catch(Exception $e){
			throw $e;
		}
		return $excelVersion;
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
			if(in_array($file_ext, array('xls','xlsx'))){
				$fileType = self::FILE_EXCEL;
			}else if(in_array($file_ext, array('csv','txt'))){
				$fileType = self::FILE_CSV_TXT;
			}
		}catch(Exception $e){
			throw $e;
		}
		return $fileType;
	}

	/**
	 * convert excel file to csv files
	 * @param string $filename excel filename
	 */
	function convExcelToCsv($filename, $path_before, $path_after){
		$this->logger->debug('starts converting excel to csv =>' . $filename);
		try{
			//create the directory in case import path do not exist
			createDir($path_after);
			//get filepath
			$filePath = $path_before.$filename;
			//get excel version according to file extension
			$excelVersion = $this->getExcelVersion($filePath);

			//create excel reader object
			$objReader = PHPExcel_IOFactory::createReader($excelVersion);
			//read the excel file
			$objPHPExcel = $objReader->load($filePath);
			// 使用メモリ確認 このタイミングで高くなる
			$this->logger->debug('memory_get_usage：'.memory_get_usage());

			//get the number of sheet on the file
			//$sheetCount = $objPHPExcel->getSheetCount();
			// 一番左のシートのみ取り込み 20160212 sakai
			$sheetCount = 1;

			//get list of sheet names on excel file
			$sheetnames = $objReader->listWorksheetNames($filePath);

			//get filename without extension
			$csvFilename = pathinfo($filePath, PATHINFO_FILENAME);

			//loop the sheet
			for($i = 0; $i < $sheetCount; $i++){
				// シート名にアンパサンドがあれば除去
				$sheetnames[$i] = str_replace(array("&"), '', $sheetnames[$i]);
				//get the sheet object
				$sheet = $objPHPExcel->getSheet($i);
				//get the maximum number of row
				$maxRow = $sheet->getHighestRow();
				//get the last cell column
				$maxCol = $sheet->getHighestColumn();


				//open the csv file
				$fp = fopen($path_after.$csvFilename."_$sheetnames[$i].csv", 'w');

				$this->logger->debug("creating $csvFilename"."_$sheetnames[$i].csv file from sheet $sheetnames[$i]");

				//loop the acquired data from excel file
				for ($row = 1; $row <= $maxRow; $row++){
					//read a row of data into an array
					$rowData = $sheet->rangeToArray('A' . $row . ':' . $maxCol . $row,NULL,TRUE,FALSE);
					$output = array();
					//loop all the data on row and put it in array
					foreach ($rowData[0] as $idx => $value) {
						if($row>1 &&($idx == 2 || $idx==26)){
							$value = PHPExcel_Style_NumberFormat::toFormattedString($value, PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDD2);
						}
						$output[] = $value;
					}
					//fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
					//write the data on csv
					fwrite($fp, '"'.implode('","', array_values($output)).'"'.PHP_EOL);
				}
				fclose($fp);
			}
			// メモリ開放確認
			//$this->logger->debug('Free up some of the memory');
			$objPHPExcel->disconnectWorksheets();
			unset($objPHPExcel);
			$this->logger->debug('memory_get_usage：'.memory_get_usage());
		}catch(Exception $e){
			throw $e;
		}
		$this->logger->debug('ends converting excel to csv =>' . $filename);
	}

	/**
	 * Execute Process 2
	 * get all files from import directory and convert from sjis to utf8
	 */
	function execProcess(){
		try{
			$noFiles = true;
			foreach (array('','_9','_11') as &$dir_mei) {
				$path_before = getImportPath(false, $dir_mei);
				$path_after = getImportPath(true, $dir_mei);
				if(file_exists($path_before)){
					// ディレクトリの内容を読み込みます。
					if ($dh = opendir($path_before)) {
						//loop all the files on the export directory
						while (false !== ($filename = readdir($dh))) {
							if ($filename != "." && $filename != "..") {
								$noFiles = false;
								$fileType = $this->getFileType($filename);
								if(self::FILE_CSV_TXT === $fileType){
									//convert the file
									$this->convFileEncoding($filename, $path_before, $path_after);
								}else if(self::FILE_EXCEL === $fileType){
									//get list of valid csv files
									// CSVファイル群取得 30MB以上は省く
									if($this->capacityCheck($filename, $path_before)){
										$this->convExcelToCsv($filename, $path_before, $path_after);
									}
								}
							}
						}
						closedir($dh);
					}
				}
			}
			//If there are no files: write it down on the error file
			if($noFiles){
				$this->logger->error("No file/s on import directory.");
			}
		}catch(Exception $e){
			$this->logger->error($e->getMessage());
			$this->mail->sendMail();
			throw $e;
		}
	}

	/**
	 * returns valid csv files(not more than 30MB)
	 * @return $validFiles files with size <= 30mb
	 */
	function capacityCheck($filename, $path){
		$result = TRUE;
		try{
			if(filesize($path.$filename) > 30000000){
				//acquire filename only
				$this->logger->error("($filename)：30MBを超えている為、処理出来ませんでした。");
				$result = FALSE;
			}
		}catch(Exception $e){
			throw $e;
		}
		return $result;
	}
}
?>
