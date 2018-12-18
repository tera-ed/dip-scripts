<?php
require_once('nayose/common/Logger.php');
require_once('nayose/common/Mail.php');
require_once('nayose/common/Database.php');
require_once('nayose/common/CSVUtils.php');
require_once('nayose/common/Validation.php');

$procNo = 0;

/**
 * Function for creating directory
 * @param string $path - directory
 * @param number $permission - default(0700)
 * @param boolean $recursive - Allows the creation of nested directories.
 */
function createDir($path, $permission = 0700, $recursive = true) {
	if(!file_exists($path)){
		mkdir($path, $permission, $recursive);
	}
}

/**
 * Replace data keys with DB field name
 * @param array $fields - array that contains DB field name
 * @param array $data - array that contains actual data
 * @param boolean $useKeyValues - Allows to use array_values of $fields
 */
function mapFields($fields, $data, $useKeyValues = false) {
	$keys = $useKeyValues === true ? array_values($fields) : array_keys($fields);
	$vals = array_values($data);
	$newList = array_combine($keys, $vals);
	return $newList;
}

/**
 * Replace empty string to null
 * @param array $data
 * @return array
 */
function emptyToNull($data) {
	if(is_array($data)) {
		foreach ($data as $key => &$value) {
			if($value == '') {
				$value = null;
			}
		}
	}
	return $data;
}

/**
 * Get all converted CSV files using matched filename
 * @param string $filename
 * @param string $path
 * @param string ProcessNo
 * @return array $fileList
 */
function getMultipleCsv($filename, $path, $procNo){
	$fileList = array();
	try{
		$fileList = glob($path."*".$filename."*.csv");
		// throw an exception if the specified file or
		// a directory path that does not exist. (ERR_CODE: 602)
		if(empty($fileList))
			if($procNo == '22' || $procNo == '23' || $procNo == '24' || $procNo == '29'){
				throw new Exception("処理対象ファイルが存在しません.", 602);
			} else if ($procNo == '25' || $procNo == '26' || $procNo == '27' || $procNo == '28') {
				throw new Exception(date('Ymd').'_処理ファイルなし.', 602);
			} else if ($procNo == '21') { 
				throw new Exception("取り込みファイルが揃っていません.", 602);
			} else {
				throw new Exception("File not found.", 602);
			}
			
	} catch(Exception $e){
		throw $e;
	}
	return $fileList;
}


/**
 * Function to read converted CSV file
 * @param string $filename - csv file name
 * @param string $curRow - current row that show be accessing
 * @return array $dataArray - return the data gathered from csv
 */
function readCsv($filename, $curRow){
	$dataArray = array();
	try {
		global $MAX_READ_SIZE;
		$index = 1;
		$dataArray = array();
		// set pointer to next row
		$curRow++;
		if(($handle = fopen($filename, "r")) !== false){
			$data = fgetcsv($handle);
			while($row = fgetcsv($handle)) {
				$index++;
				if(($index > $curRow) && ($index <= $MAX_READ_SIZE+$curRow)){
					$arr = array();
					foreach($data as $i => $col){
						$arr[$i] = $row[$i];
					}
					$dataArray[] = $arr;
				}
				if($index > $MAX_READ_SIZE+$curRow){
					break;
				}
			}
			fclose($handle);
		}
	}catch(Exception $e){
		throw $e;
	}
	return $dataArray;
}

/**
 * Get total number of rows in CSV file
 * @param string $filename - csv filename
 * @return number - total number of line counts
 */
function getLineSizeCsv($filename, $exludeHeader = true){
	$linecount = 1;
	try {
		// throw an exception if the specified file or
		// a directory path that does not exist. (ERR_CODE: 602)
		if(is_null($filename) || !file_exists($filename))
			throw new Exception("File not found.", 602);

		// Open for reading only;
		$linecount = sizeof(file($filename));

		// exclude header
		if($exludeHeader) $linecount--;
	} catch(Exeption $e){
		throw $e;
	}
	return $linecount;
}

/**
 * returns an array of the column titles
 * @param $filename - path to the file
 * @return an array of header
 */
function getCsvHeader($filename){
	$header = null;
	try {
		$rows = array_map('str_getcsv', file($filename));
		$header = array_shift($rows);
		unset($rows);
	} catch(Exception $e){
		throw $e;
	}
	return $header;
}

/**
 * returns the import path (before/after)
 * @param $isAfter true if after otherwise false
 * @return string import path
 */
function getImportPath($isAfter = false){
	global $root, $IMPORT_PATH;
	$mode = $isAfter? 'after' : 'before';
	return $root.$IMPORT_PATH[$mode]."/".date('Ymd')."/";
}

/**
 * returns the export path (before/after)
 * @param bool $isAfter - true if after otherwise false
 * @return string export path
 */
function getExportPath($isAfter = false){
	global $root, $EXPORT_PATH;
	$mode = $isAfter? 'after' : 'before';
	return $root.$EXPORT_PATH[$mode]."/".date('Ymd')."/";
}



/**
 * shell Exec
 * @param $logger
 * @param $shellPass
 * @param $csvFilePass
 * @return string export path
 */
function shellExec($logger, $shellPass, $csvFilePass, $table=null) {
	global $root, $IMPORT_PATH;
	try{
		if($logger == null || $logger ==''){
			return -1;
		}else if($shellPass == null || $shellPass == ''){
			$logger->debug("Error found in shell file.");
			return -2;
		}else if($csvFilePass == null || $csvFilePass == ''){
			$logger->debug("Error found in csv file.");
			return -3;
		}else{
			$logger->debug("SHELL START");
			$logger->debug($csvFilePass);
			$logDirDate = date('Ymd');
			$logDir = $logger->generateDir($logDirDate, false);
			$logFilename = $logger->generateFilename($logDir, $logDirDate);
			$cmd = 'bash '.$root.$IMPORT_PATH['shellDir'].$shellPass.' '.$csvFilePass.' '.$logFilename.' '.$table.' 2>&1';
			exec($cmd, $output, $exit);
			$logger->debug("SHELL END");
			if ( $exit ) {
				while(list($key, $val) = each($output)) {
					$logger->error("SHELL ERROR : ".$key."=>".$val);
					unset($output[$key]);
				}
				return $exit;
			} else {
				return 0;
			}
		}
	} catch (Exception $e){
		throw $e;
	}
}

/**
 * shell Exec
 * @param $logger
 * @param $shellPass
 * @param $csvFilePass
 * @return string export path
 */
function shellExecAddSyncNo($logger, $shellPass, $csvFilePass, $table=null,$batchSyncNo=null) {
	global $root, $IMPORT_PATH;
	try{
		if($logger == null || $logger ==''){
			return -1;
		}else if($shellPass == null || $shellPass == ''){
			$logger->debug("Error found in shell file.");
			return -2;
		}else if($csvFilePass == null || $csvFilePass == ''){
			$logger->debug("Error found in csv file.");
			return -3;
		}else{
			$logger->debug("SHELL START");
			$logger->debug($csvFilePass);
			$logDirDate = date('Ymd');
			$logDir = $logger->generateDir($logDirDate, false);
			$logFilename = $logger->generateFilename($logDir, $logDirDate);
			$cmd = 'bash '.$root.$IMPORT_PATH['shellDir'].$shellPass.' '.$csvFilePass.' '.$logFilename.' '.$table.' '.$batchSyncNo.' 2>&1';
			exec($cmd, $output, $exit);
			$logger->debug("SHELL END");
			if ( $exit ) {
				while(list($key, $val) = each($output)) {
					$logger->error("SHELL ERROR : ".$key."=>".$val);
					unset($output[$key]);
				}
				return $exit;
			} else {
				return 0;
			}
		}
	} catch (Exception $e){
		throw $e;
	}
}


/**
 * returns an array of the column titles
 * @param $filename - path to the file
 * @return an array of header
 * 対象DBのフィールドを取得
 */
function getDBField($db,$db_name){
	$header = null;
	try {
		$sql  = "DESCRIBE ".$db_name.";";
		$query = $db->executeQuery($sql, array(), false);
		$data = $query->fetchAll(PDO::FETCH_ASSOC);
		foreach ($data as $key => $val){
			$header[]=$val['Field'];
		}

	} catch(Exception $e){
		throw $e;
	}
	return $header;
}


?>