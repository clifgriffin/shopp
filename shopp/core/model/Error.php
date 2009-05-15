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
define('SHOPP_DB_ERR',32);
define('SHOPP_PHP_ERR',64);
define('SHOPP_ALL_ERR',128);
define('SHOPP_DEBUG_ERR',256);

if (!defined('SHOPP_ERROR_REPORTING') && WP_DEBUG) define('SHOPP_ERROR_REPORTING',SHOPP_DEBUG_ERR);
if (!defined('SHOPP_ERROR_REPORTING')) define('SHOPP_ERROR_REPORTING',SHOPP_ALL_ERR);

class ShoppErrors {
	
	var $errors = array();
	var $notifications;
	
	function ShoppErrors () {
		$this->notifications = new CallbackSubscription();

		$types = E_ALL ^ E_NOTICE;
		if (defined('WP_DEBUG') && WP_DEBUG) $types = E_ALL;
		// Handle PHP errors
		if (SHOPP_ERROR_REPORTING >= SHOPP_PHP_ERR)
			set_error_handler(array($this,'phperror'),$types);
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
	
	function exist ($level=SHOPP_DEBUG_ERR) {
		$errors = array();
		foreach ($this->errors as &$error)
			if ($error->level <= $level) $errors[] = &$error;
		return (count($errors) > 0);
	}
	
	function reset () {
		$this->errors = array();
	}
	
	function phperror ($number, $message, $file, $line) {
		if (strpos($file,SHOPP_PATH) !== false)
			new ShoppError($message,'php_error',SHOPP_PHP_ERR,
				array('file'=>$file,'line'=>$line,'phperror'=>$number));
	}
	
	
}

class ShoppError {

	var $code;
	var $source;
	var $messages;
	var $level;
	var $data = array();
	var $php = array(
		E_ERROR           => 'ERROR',
		E_WARNING         => 'WARNING',
		E_PARSE           => 'PARSE ERROR',
		E_NOTICE          => 'NOTICE',
		E_CORE_ERROR      => 'CORE ERROR',
		E_CORE_WARNING    => 'CORE WARNING',
		E_COMPILE_ERROR   => 'COMPILE ERROR',
		E_COMPILE_WARNING => 'COMPILE WARNING',
		E_USER_ERROR      => 'USER ERROR',
		E_USER_WARNING    => 'USER WARNING',
		E_USER_NOTICE     => 'USER NOTICE',
		E_STRICT          => 'STRICT NOTICE',
		E_RECOVERABLE_ERROR  => 'RECOVERABLE ERROR'
	);
    
		
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
		
		$this->source = "Shopp";
		if (isset($this->debug['class'])) $this->source = $this->debug['class'];
		if (isset($this->data['phperror'])) $this->source = "PHP ".$this->php[$this->data['phperror']];
		
		
		$Errors = &ShoppErrors();
		if (!empty($Errors)) $Errors->add($this);
	}
	
	function message ($delimiter="\n") {
		$string = "";
		// Show source if debug is on, or not a general error message
		if ((WP_DEBUG || $this->level > SHOPP_ERR) && 
			!empty($this->source)) $string .= "$this->source: ";
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
		$sitename = sanitize_title_with_dashes(get_bloginfo('sitename'));
		$this->logfile = trailingslashit($this->dir).$sitename."-".$this->file;

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
	
	function reset () {
		$this->log = fopen($this->logfile,'w');
		fwrite($this->log,'');
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
		$_[] = __('This is an automated message notification generated when the Shopp installation at '.get_bloginfo('url').' encountered the following error: ','Shopp');
		$_[] = '';
		$_[] = $error->message();
		$_[] = '';
		if (isset($error->debug['file']) && defined('WP_DEBUG'))
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