<?php
/**
 * Logger Class
 *
 * Configuration (./config/config.php):
 * $LOG['err_filename'] - appended to process# as filename for error log
 * $LOG['err_path'] - directory for error log
 * $LOG['path'] - directory for process log
 * $LOG['date_fmt'] - date format inside log file, leave blank to remove date
 *
 * 4 Log levels :
 * LOG_LVL_INFO  = [INFO]
 * LOG_LVL_WARN  = [WARNING]
 * LOG_LVL_DEBUG = [DEBUG]
 * LOG_LVL_ERR   = [ERROR]
 *
 * @author Krishia Valencia
 *
 */
class Logger{
	private $processNo;
	private $logLvl;

	const LOG_LVL_INFO  = '[INFO]';
	const LOG_LVL_WARN  = '[WARNING]';
	const LOG_LVL_DEBUG = '[DEBUG]';
	const LOG_LVL_ERR   = '[ERROR]';
	const LOG_EXT = '.log';

	/**
	 * Logger constructor
	 * @param int $procNo - process number
	 */
	function __construct ($procNo) {
		$this->processNo = $procNo;
	}

	/**
	 * Create info log
	 * @param string $args
	 */
	function info() {
		$this->logLvl = self::LOG_LVL_INFO;
		$this->createLog(func_get_args());
	}

	/**
	 * Create warning log
	 * @param string $args
	 */
	function warn() {
		$this->logLvl = self::LOG_LVL_WARN;
		$this->createLog(func_get_args());
	}

	/**
	 * Create error log
	 * @param string $args
	 */
	function error() {
		$this->logLvl = self::LOG_LVL_ERR;
		$this->createLog(func_get_args());
	}

	/**
	 * Create debug log
	 * @param string $args
	 */
	function debug() {
		$this->logLvl = self::LOG_LVL_DEBUG;
		$this->createLog(func_get_args());
	}

	/**
	 * write string to specific log file
	 * @param array $args
	 */
	function createLog($args) {
		global $LOG;
		$value = "";

		foreach ($args as $key => $val) {
			if($key != 0) {
				$value .= PHP_EOL;
			}
			$value .= $val;
			// debug commandline log
			//echo "NAYOSE : " . $val . PHP_EOL;
		}

		// prepend date to the log directory
		$logDirDate = date('Ymd');
		// append date to message default value = YYYYmmddHHiiss
		// can be configured using $LOG['date_fmt'] from config file
		$logDate = (!empty($LOG['date_fmt']))? "[".date($LOG['date_fmt'])."]" : "";
		// prepend date and log level text
		$logMsg = $logDate.$this->logLvl." ".$value.PHP_EOL;

		if ($this->logLvl == self::LOG_LVL_ERR) {
			$errorPrefix = $this->logLvl." [Process#".$this->processNo."]";
			$logDir = $this->generateDir($logDirDate, true);
			$logFilename = $this->generateFilename($logDir, $logDirDate, true);
			$this->writeLog($logFilename, str_replace($this->logLvl, $errorPrefix, $logMsg));
		}

		$logDir = $this->generateDir($logDirDate);
		$logFilename = $this->generateFilename($logDir, $logDirDate);
		$this->writeLog($logFilename, $logMsg);
	}

	/**
	 * Generate log path and generate directory if it does not exist
	 * @param string $logDirDate
	 * @param string $errorLog
	 * @return string
	 */
	function generateDir($logDirDate, $errorLog = false) {
		global $root, $LOG;

		// directory for process / error log (from configuration).
		$logPath = (!$errorLog)? "path" : "err_path";
		// remove trailing slashes
		$logDir = rtrim(ltrim($LOG[$logPath], "/"), "/");
		// add root path to the specified directory
		$logDir = $root."/".$logDir."/";
		// if true, append date to directory
		if(!$errorLog) $logDir.= $logDirDate."/";

		// create directory for logs
		createDir($logDir);

		return $logDir;
	}

	/**
	 * Generate log filename
	 * @param string $logDir
	 * @param string $logDirDate
	 * @param string $errorLog
	 * @return string
	 */
	function generateFilename($logDir, $logDirDate, $errorLog = false) {
		global $LOG;
		// add process number to the filname
		$logFilename = $this->processNo;

		// if log level is error, append an extra string for
		// error filename (from configuration).
		if ($errorLog)
			$logFilename = $logDirDate.'_'.$LOG['err_filename'];

		// append log file extension
		$logFilename.=self::LOG_EXT;
		// append log directory to filename
		$logFilename = $logDir.$logFilename;

		return $logFilename;
	}

	/**
	 * Write logs
	 * @param string $filename
	 * @param string $msg
	 */
	function writeLog($filename, $msg) {
		// Open for reading and writing  ('a+');
		// place the file pointer at the end of the file.
		// If the file does not exist, attempt to create it.
		// Write logs
		$fp = fopen($filename, 'a+');
		fwrite($fp, $msg);
		fclose($fp);
	}
}

?>