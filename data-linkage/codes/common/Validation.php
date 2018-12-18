<?php
/**
 * Validation
 *
 * Check if array values are valid
 *
 * Sample usage:
 * $pattern = array("M,D,S:11");
 * $data = array("00110111111");
 * Will check if mapped value is Mandatory, Digit only, Strict length 11
 *
 * Available Pattern:
 * M - Mandatory
 * D - Number Only
 * L - Max Length (L:20)
 * S - Strict Length (no max and min length)
 * B - Byte check (if half width)
 * J - Japanese character check
 * N - Half width numeric or hyphen
 * C - A-D,G and Z Only Allowed Capital Letter Check
 * A - Alphanumeric
 *
 * @author user
 *
 */
class Validation {
	private $logger;

	function __construct($logger){
		$this->logger = $logger;
	}

	/**
	 * Validate fields
	 * @param array $data - data to be checked
	 * @param array $pattern - array pattern
	 * @param int $row - row number to be check
	 * @param string $csv -csv file name (optional if more than 1 csv file)
	 * @param array $header
	 * @return boolean
	 */
	public function execute ($data, $pattern, $row=0, $csv = "", $header = array()){
		$isValid = true; $i = 0;
		$csv = $csv != "" ? "[$csv] " : "";
		$rowNum = $row + 1;
		$row = "ROW[$rowNum] :";
		foreach ($pattern as $key => $value){
			$errorMsg = $csv.$row.$header[$i];
			$toCheck = explode(",",$value);
			for($j=0; $j<count($toCheck); $j++){
				if ($toCheck[$j] === "NC" && (strval($data[$key]) != '')){
					$isValid = $this->halfWidthNumCommaCheck($data[$key]);
					if(!$isValid){
						$this->logger->error($errorMsg." must be half width number or [,].");
						break;
					}
				}elseif ($toCheck[$j] === "DATE" && (strval($data[$key]) != '')){
					$isValid = $this->validateDate($data[$key]);
					if(!$isValid){
						$this->logger->error($errorMsg." must be a valid date.");
						break;
					}
				}
					// Required
				elseif($this->startsWith($toCheck[$j], "M") && strval($data[$key]) == ''){
					$isValid = false;
					$this->logger->error($errorMsg." is required");
					break;
					// Must be a number
				} elseif ($this->startsWith($toCheck[$j], "D") && (strval($data[$key]) != '')){
					$isValid = $this->digitCheck($data[$key]);
					if(!$isValid){
						$this->logger->error($errorMsg." must be number");
						break;
					}
					// Exact length
				} elseif ($this->startsWith($toCheck[$j], "S") && (strval($data[$key]) != '')){
					$length = explode(":", $toCheck[$j]);
					$isValid = $this->lengthCheck($data[$key], $length[1]);
					if(!$isValid){
						$this->logger->error($errorMsg." length must be exactly $length[1]");
						break;
					}
					// Must not exceed in character length
				} elseif ($this->startsWith($toCheck[$j], "L") && (strval($data[$key]) != '')){
					$length = explode(":", $toCheck[$j]); //L:20
					$isValid = $this->maxLengthCheck($data[$key], $length[1]);
					if(!$isValid){
						$this->logger->error($errorMsg." must not exceed $length[1] characters.");
						break;
					}
					// Whole width and Whole width with half string "/"
				} elseif ($this->startsWith($toCheck[$j], "J") && (strval($data[$key]) != '')){
					$firstToken = "";
					$secondToken = "";
					$msg = " must be whole width string.";
					if($this->startsWith($toCheck[$j], "J:")){

						$char = explode(":", $toCheck[$j]);
						$tokenVal = explode(".", $char[1]);
						$firstToken = $tokenVal[0];
						if(count($tokenVal) == 2)
						$secondToken = $tokenVal[1];

						if(count($tokenVal) == 1)
						$msg = " must be whole width string or half width [$firstToken]";
						else
						$msg = " must be whole width string or half width space";
					}
					$isValid = $this->japCheck($data[$key], $firstToken, $secondToken);
					if(!$isValid){
						$this->logger->error($errorMsg.$msg);
						break;
					}
					// Half width
				} elseif ($this->startsWith($toCheck[$j], "B") && (strval($data[$key]) != '')){
					$isValid = $this->byteCheck($data[$key]);
					if(!$isValid){
						$this->logger->error($errorMsg." must be half width string.");
						break;
					}
					// Half width numeric with hypen
				} elseif ($this->startsWith($toCheck[$j], "N") && (strval($data[$key]) != '')){
					$isValid = $this->halfWidthNumCheck($data[$key]);
					if(!$isValid){
						$this->logger->error($errorMsg." must be half width number or [-].");
						break;
					}
					// A-D,G and Z Only Allowed Capital Letter Check
				} elseif ($this->startsWith($toCheck[$j], "C") && (strval($data[$key]) != '')){
					$isValid = $this->captialCharCheck($data[$key]);
					if(!$isValid){
						$this->logger->error($errorMsg." Not Allowed Capital Letter.");
						break;
					}
				} elseif($this->startsWith($toCheck[$j], "A") && (strval($data[$key]) != '')){
					$isValid = $this->alphanumericCheck($data[$key]);
					if(!$isValid){
						$this->logger->error($errorMsg." must be alphanumeric.");
					}
				}
			}
			$i++;
			if(!$isValid){
				break;
			}
		}
		return $isValid;
	}

	/**
	 * Check if characters do not exceed on max length
	 * @param $value
	 * @param number $length
	 * @return boolean - false if string exceeds on max length
	 */
	private function maxLengthCheck($value, $length){
		$isValid = true;
		if(mb_strlen($value, "UTF-8") > $length){
			$isValid = false;
		}
		return $isValid;
	}

	/**
	 * Check if characters are of the given length
	 * @param $value
	 * @param number $length
	 * @return boolean - false if string is not in the given length
	 */
	private function lengthCheck($value, $length){
		$isValid = true;
		if(mb_strlen($value, "UTF-8") != $length){
			$isValid = false;
		}
		return $isValid;
	}

	/**
	 * Check if value contains digits
	 * @param $value
	 * @return boolean - false if not numeric
	 */
	private function digitCheck ($value){
		$isValid = true;
		if(is_numeric($value) != true){
			$isValid = false;
		}
		return $isValid;
	}

	/**
	 * Check if value are single byte only
	 * @param $value
	 * @return boolean - false if multibyte character
	 */
	private function byteCheck ($value){
		$isValid = true;
		$pattern = "/^[\x{ff61}-\x{ffdc}\x{ffe8}-\x{ffee}\x{0020}-\x{007e}]+$/u";
		if (preg_match($pattern, $value) != 1) {
			$isValid = false;
		}
		return $isValid;
	}

	/**
	 * Check if value are japanese characters (whole width)
	 * @param $value
	 * @param $token
	 * @return boolean
	 */
	private function japCheck ($value, $token = "", $token2 = ""){
		$isValid = true;
		if($token == "/")
			$token = "\/"; // allow half width slash(/)
		if($token2 == "space")
			$token = "\x{0020}" . $token; //allow half width space
		$pattern = "/^[\x{3040}-\x{309f}". //Hiragana
			"\x{30a0}-\x{30ff}". //katakana
			"\x{3000}-\x{303f}". //Punctuations
			"\x{4e00}-\x{9faf}". //CJK unified ideographs - Common and uncommon Kanji
			"\x{3400}-\x{4dbf}". //CJK unified ideographs Extension A - Rare Kanji
			"\x{ff00}-\x{ff60}". //Fullwidth ASCII variants (Full-width Roman)
			"\x{ffE0}-\x{ffE6}". //Fullwidth symbol currency
			"\x{2019}\x{0027}\x{ff07}". // apostrophe ’ &#x2019;' &#x0027;全角 &#xFF07;
		$token.
			"]+$/u";
		if(preg_match($pattern, $value) != 1){
			$isValid = false;
		}
		return $isValid;
	}

	/**
	 * Check if value is capital A-D,G,Z
	 * @param $value
	 * @return boolean - false if not capital
	 */
	private function captialCharCheck($value){
		$isValid = true;
		if(preg_match('/([^A-DGZ])/', $value)){
			$isValid = false;
		}
		return $isValid;
	}

	/**
	 * Check if halfwidth number or hypen[-]
	 * @param unknown_type $value
	 * @return boolean
	 */
	private function halfWidthNumCheck($value){
		$isValid = true;
		if(preg_match('/^[0-9-]+$/m', $value) != 1){
			$isValid = false;
		}
		return $isValid;
	}

	/**
	 * Check if halfwidth number or comma[,]
	 * @param string $value
	 * @return boolean
	 */
	private function halfWidthNumCommaCheck($value){
		$isValid = true;
		if(preg_match('/^[0-9,]+$/m', $value) != 1){
			$isValid = false;
		}
		return $isValid;
	}

	/**
	 * Check if alphanumeric only
	 * @param string $value
	 * @return boolean
	 */
	private function alphanumericCheck($value){
		$isValid = true;
		if(preg_match('/^[0-9a-zA-Z]+$/m', $value) != 1 ){
			$isValid = false;
		}
		return $isValid;
	}

	/**
	 * Check if $haystack starts with the needle
	 * @param $haystack
	 * @param $needle
	 * @return boolean
	 */
	private function startsWith($haystack, $needle) {
		// search backwards starting from haystack length characters from the end
		return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
	}

	/**
	 * Check if date is an existent date
	 * @param date $date
	 * @return boolean true/false
	 */
	private function validateDate($date){
		$timestamp = strtotime($date);
		return $timestamp ? true : false;
	}
}
?>