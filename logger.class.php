<?php

class Logger {
	
	var $displayLineNumbers;
	var $displayOnScreen;
	var $startTime;

	function Logger() {
		$this->displayLineNumbers = true;
		$this->displayOnScreen = true;
	}

	function logError($message, $file, $line) {
		$this->display($message, $file, $line);
		error_log($message);
		exit;
	}

	function logWarning($message, $file, $line) {
		$this->display($message, $file, $line);
	}

	function logInfo($message, $file, $line) {
		$this->display($message, $file, $line);
	}

	function logDebug($message, $file = "", $line = 0) {
		$this->display($message, $file, $line);
	}
	
	function setDisplayLineNumbers($displayLineNumbers) {
		$this->displayLineNumbers = $displayLineNumbers;
	}
	
	function getMicrotime() {
	    list ($msec, $sec) = explode(' ', microtime());
	    $microtime = (float)$msec + (float)$sec;
	    return $microtime;
	}
	
	function startTimer() {
		$this->startTime = $this->getMicrotime();
	}
	
	function getElapsedTime() {
		return $this->getMicrotime() - $this->startTime;
	}

	function display($string, $file, $lineNumber) {
		if($this->displayOnScreen) {
			if(php_sapi_name() == 'cli') {
				if(is_array($string) || is_object($string)) {
					print_r($string);
				}
				else {
					echo($string."\n");
				}
				if($this->displayLineNumbers) {
					echo($file.' Line: '.$lineNumber."\n");
				}
			}
			else {
				if(is_array($string) || is_object($string)) {
					echo('<pre>');
					print_r($string);
					echo('</pre>');
				}
				else {
					echo($string.'<br/>');
				}
				if($this->displayLineNumbers) {
					echo($file.' Line: '.$lineNumber."<br/>");
				}
			}
		}
	}

}



?>
