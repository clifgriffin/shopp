<?php
/**
 * Error class
 * Error message handler class
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

define('SHOPP_ERR',1);
define('SHOPP_TRXN_ERR',2);
define('SHOPP_AUTH_ERR',4);
define('SHOPP_ADDON_ERR',8);
define('SHOPP_COMM_ERR',16);
define('SHOPP_PHP_ERR',32);
define('SHOPP_ALL_ERR',64);
define('SHOPP_DEBUG_ERR',128);

if (!defined('SHOPP_ERROR_REPORTING') && SHOPP_DEBUG) define('SHOPP_ERROR_REPORTING',SHOPP_DEBUG_ERR);
if (!defined('SHOPP_ERROR_REPORTING')) define('SHOPP_ERROR_REPORTING',SHOPP_ALL_ERR);

class ShoppErrors {
	
	var $errors = array();
	var $notifications;
	
	function ShoppErrors () {
		$this->notifications = new CallbackSubscription();
	}
	
	function add ($ShoppError) {
		$this->errors[$ShoppError->source] = $ShoppError;
		$this->notifications->send($ShoppError);
	}
	
	function get ($level=SHOPP_DEBUG_ERR) {
		$errors = array();
		foreach ($this->errors as &$error)
			if ($error->level <= $level) $errors[] = &$error;
		return $errors;
	}
	
	function exist () {
		return (count($this->errors) > 0);
	}
	
	function reset () {
		$this->errors = array();
	}
	
	function phperror ($number, $message, $file, $line) {
		if (strpos($file,SHOPP_PATH) !== false)
			new ShoppError($message,'php_error',SHOPP_PHP_ERR,
				array('file'=>$file,'line'=>$line,'code'=>$number));
	}
	
	
}

class ShoppError {

	var $code;
	var $source;
	var $messages;
	var $level;
	var $data = array();
		
	function ShoppError($message='',$code='',$level=SHOPP_ERR,$data='') {
		if ($level > SHOPP_ERROR_REPORTING) return;
		if (empty($message)) return;

		$debug = debug_backtrace();
		
		$this->code = $code;
		$this->messages[] = $message;
		$this->level = $level;
		$this->data = $data;
		$this->debug = $debug[1];
		if (isset($data['file'])) $this->debug['file'] = $data['file'];
		if (isset($data['line'])) $this->debug['line'] = $data['line'];
		unset($this->debug['object'],$this->debug['args']);
		
		if (isset($this->debug['class'])) $this->source = $this->debug['class'];
		else $this->source = "Core";
		
		$Errors = &ShoppErrors();
		if (!empty($Errors)) $Errors->add($this);
	}
	
	function message ($delimiter="\n") {
		$string = "";
		if (!empty($this->source)) $string .= "$this->source: ";
		$string .= join($delimiter,$this->messages);
		return $string;
	}
				
}

class ShoppErrorLogging {
	var $dir;
	var $file = "shopp_errors.log";
	var $logfile;
	var $log;
	var $loglevel = 0;
	
	function ShoppErrorLogging ($loglevel=0) {
		$this->loglevel = $loglevel;
		$this->dir = sys_get_temp_dir();
		$this->logfile = $this->dir.$this->file;

		$Errors = &ShoppErrors();
		$Errors->notifications->subscribe($this,'log');
	}
	
	function log (&$error) {
		if ($error->level > $this->loglevel) return;
		$debug = "";
		if (isset($error->debug['file'])) $debug = " [".basename($error->debug['file']).", line ".$error->debug['line']."]";
		$message = date("Y-m-d H:i:s",mktime())." - ".$error->message().$debug."\n";
		$this->log = fopen($this->logfile,'at');
		fwrite($this->log,$message);
		fclose($this->log);
	}
	
	function tail($lines=100) {
		if (!file_exists($this->logfile)) return;
		$f = fopen($this->logfile, "r");
		$c = $lines;
		$pos = -2;
		$beginning = false;
		$text = array();
		while ($c > 0) {
			$t = "";
			while ($t != "\n") {
				if(fseek($f, $pos, SEEK_END) == -1) { $beginning = true; break; }
				$t = fgetc($f);
				$pos--;
			}
			$c--;
			if($beginning) rewind($f);
			$text[$lines-$c-1] = fgets($f);
			if($beginning) break;
		}
		fclose($f);
		return array_reverse($text);
	}

}

class ShoppErrorNotification {
	
	var $recipients;
	var $types=0;
	
	function ShoppErrorNotification ($recipients='',$types=array()) {
		if (empty($recipients)) return;
		$this->recipients = $recipients;
		foreach ($types as $type) $this->types += $type;
		$Errors = &ShoppErrors();
		$Errors->notifications->subscribe($this,'notify');
	}
	
	function notify (&$error) {
		if (!($error->level & $this->types)) return;
		$url = parse_url(get_bloginfo('url'));
		$_ = array();
		$_[] = 'From: "'.get_bloginfo('sitename').'" <shopp@'.$url['host'].'>';
		$_[] = 'To: '.$this->recipients;
		$_[] = 'Subject: '.__('Shopp Error Notification','Shopp');
		$_[] = '';
		$_[] = __('Shopp at '.get_bloginfo('url').' encountered the following error: ','Shopp');
		$_[] = '';
		$_[] = $error->message();
		$_[] = '';
		if (isset($error->debug['file']))
			$_[] = 'DEBUG: '.basename($error->debug['file']).', line '.$error->debug['line'].'';

		shopp_email(join("\r\n",$_));
	}
	
}


class CallbackSubscription {

	var $subscribers = array();

	function subscribe ($target,$method) {
		if (!isset($this->subscribers[get_class($target)]))
			$this->subscribers[get_class($target)] = array(&$target,$method);
	}
	
	function send () {
		$args = func_get_args();
		foreach ($this->subscribers as $callback)
			call_user_func_array($callback,$args);
	}
	
}

function &ShoppErrors () {
	global $Shopp;
	return $Shopp->Cart->data->Errors;
}

function is_shopperror ($e) {
	return (get_class($e) == "ShoppError");
}

?>