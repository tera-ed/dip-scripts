<?php
/**
 * CSVUtils Class
 *
 * Exports data to CSV file
 *
 * Configuration (./config/config.php):
 * $EXPORT_PATH['before'] - CSV export directory
 * $CSV_EXPORT['encoding'] - CSV encoding (default value : UTF-8)
 * $CSV_EXPORT['date_fmt'] - appended to export filename
 *
 * @author Krishia Valencia
 *
 */
class CSVUtils {

	private $logger;
	public $csvPath;
	private $csvEncoding = "UTF-8";

	/**
	 * CSVUtils constructor
	 * @param $logger
	 */
	public function __construct($logger){
		// initialize logger
		$this->logger = $logger;
		// define csv export directory
		$this->csvPath = getExportPath();
		// set csv encoding
		$this->setCSVEncoding();
	}

	/**
	 * Set csv encoding from configuration file
	 */
	private function setCSVEncoding() {
		global $CSV_EXPORT;
		// default encoding is UTF-8.
		// set if configured.
		if (!empty($CSV_EXPORT['encoding'])) {
			$this->csvEncoding = $CSV_EXPORT['encoding'];
		}
	}

	/**
	 * Write CSV header to file
	 * @param $handler - resource handler
	 * @param $data - first row of csv content
	 * @param $customHeader - customized csv header
	 */
	public function generateCSVHeader($handler, $data, $customHeader = array()) {
		// enclose csv header with double quote.
		(count($customHeader) > 0) ?
		$csvHeader = '"'.implode('","', $customHeader).'"'
		: $csvHeader = '"'.implode('","', array_keys($data)).'"';
		// add line break (use PHP_EOL for platform-specific line break)
		$csvHeader.=PHP_EOL;
		// detect current encoding
		$currentEncoding  = mb_detect_encoding($csvHeader);

		if ($this->csvEncoding != $currentEncoding) {
			// convert encoding and write csv header
			fwrite($handler, mb_convert_encoding($csvHeader, $this->csvEncoding,
			$currentEncoding));
		} else {
			// write csv header
			fwrite($handler, $csvHeader);
		}
	}

	/**
	 * Generate and appends date to csv filename
	 * @param string $filename
	 * @return string
	 */
	public function generateFilename($filename) {
		global $CSV_EXPORT;

		// if set, apply configuration format for date.
		if (isset($CSV_EXPORT['date_fmt']) && !empty($CSV_EXPORT['date_fmt'])) {
			// append date to filename
			$filename = date($CSV_EXPORT['date_fmt'])."_$filename";
		}

		return "$filename.csv";
	}

	/**
	 * Export CSV from data
	 * @param string $filename - csv filename
	 * @param array $data - csv data
	 * @param array $customHeader - csv custom header
	 * @throws Exception
	 */
	public function export($filename, $data, $customHeader) {
		try {
			// throw an exception if filename is not set and return an error
			if(isset($filename) && empty($filename))
			throw new Exception("Cannot create CSV, filename is not set.");
			// if the passed csv data is not an array, throw an exception and
			// return an error.
			if(!isset($data) || !is_array($data))
			throw new Exception("Cannot create CSV, wrong data format.");
			// if there is custom header and not an array throw an exception and
			// return an error.
			if(isset($customHeader) && !is_array($customHeader))
			throw new Exception("Cannot create CSV, wrong custom header format.");

			// set filename
			$csvFilename = $this->generateFilename($filename);

			$this->logger->info("start exporting data to $csvFilename");

			// create directory for CSV
			createDir($this->csvPath);

			$csvFile = $this->csvPath.$csvFilename;

			// Open for writing only ('w');
			// place the file pointer at the beginning of the file and
			// truncate the file to zero length.
			// If the file does not exist, attempt to create it.
			$fp = fopen($csvFile, 'w');

			// if resource file pointer failed. throw an exception.
			if(!$fp) {
				throw new Exception("Failed to open resource file $csvFile");
			}

			// write CSV header
			$this->generateCSVHeader($fp, $data[0], $customHeader);

			// csv content
			foreach ($data as $idx => $value) {
				// enclose csv data with double quote and
				// add line break(use PHP_EOL for platform-specific line break) per row.
				$csvData = '"'.implode('","', array_values($value)).'"'.PHP_EOL;
				// detect current encoding
				$currentEncoding = mb_detect_encoding($csvData);

				if ($this->csvEncoding != $currentEncoding) {
					// convert encoding and  write csv content
					fwrite($fp, mb_convert_encoding($csvData, $this->csvEncoding,
					$currentEncoding));
				} else {
					// write csv content
					fwrite($fp, $csvData);
				}
			}
			// closes an open file pointer
			fclose($fp);
			$this->logger->info("done exporting data to $csvFilename");
		} catch (Exception $e) {
			throw $e;
		}
	}


	/**
	 * Export CSV from data
	 * @param string $filename - csv filename
	 * @param array $data - csv data
	 * @param array $customHeader - csv custom header
	 * @throws Exception
	 */
	public function exportCsvAddData($csvFilename, $data, $customHeader, $offset) {
		try {
			// throw an exception if filename is not set and return an error
			if(isset($filename) && empty($filename))
			throw new Exception("Cannot create CSV, filename is not set.");
			// if the passed csv data is not an array, throw an exception and
			// return an error.
			if(!isset($data) || !is_array($data))
			throw new Exception("Cannot create CSV, wrong data format.");
			// if there is custom header and not an array throw an exception and
			// return an error.
			if(isset($customHeader) && !is_array($customHeader))
			throw new Exception("Cannot create CSV, wrong custom header format.");

			// set filename
			#$csvFilename = $this->generateFilename($filename);

			$this->logger->info("start exporting data to $csvFilename");

			// create directory for CSV
			createDir($this->csvPath);

			$csvFile = $this->csvPath.$csvFilename;

			// Open for writing only ('w');
			// place the file pointer at the beginning of the file and
			// truncate the file to zero length.
			// If the file does not exist, attempt to create it.

			// １回目のループは上書き、次からは追記
			if ($offset === '0') {
				$fp = fopen($csvFile, 'w');
				// write CSV header
				$this->generateCSVHeader($fp, $data[0], $customHeader);
			} else {
				$fp = fopen($csvFile, 'a');
			}

			// if resource file pointer failed. throw an exception.
			if(!$fp) {
				throw new Exception("Failed to open resource file $csvFile");
			}

			// csv content
			foreach ($data as $idx => $value) {
				// enclose csv data with double quote and
				// add line break(use PHP_EOL for platform-specific line break) per row.
				$csvData = '"'.implode('","', array_values($value)).'"'.PHP_EOL;
				// detect current encoding
				$currentEncoding = mb_detect_encoding($csvData);

				if ($this->csvEncoding != $currentEncoding) {
					// convert encoding and  write csv content
					fwrite($fp, mb_convert_encoding($csvData, $this->csvEncoding,
					$currentEncoding));
				} else {
					// write csv content
					fwrite($fp, $csvData);
				}
			}
			// closes an open file pointer
			fclose($fp);
			$this->logger->info("done exporting data to $csvFilename");

		} catch (Exception $e) {
			throw $e;
		}
	}
}
?>
