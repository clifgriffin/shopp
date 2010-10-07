<?php
/**
 * Error.php
 * Error management system for Shopp
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, 28 March, 2008
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @subpackage errors
 **/

define('SHOPP_ERR',1);
define('SHOPP_TRXN_ERR',2);
define('SHOPP_AUTH_ERR',4);
define('SHOPP_COMM_ERR',8);
define('SHOPP_STOCK_ERR',16);
define('SHOPP_ADDON_ERR',32);
define('SHOPP_ADMIN_ERR',64);
define('SHOPP_DB_ERR',128);
define('SHOPP_PHP_ERR',256);
define('SHOPP_ALL_ERR',1024);
define('SHOPP_DEBUG_ERR',2048);

/**
 * ShoppErrors class
 * 
 * The error message management class that allows other 
 * systems to subscribe to notifications when errors are
 * triggered.
 *
 * @author Jonathan Davis
 * @since 1.0
 * @package shopp
 * @subpackage errors
 **/
class ShoppErrors {
	
	var $errors = array();	// Error message registry
	var $notifications;		// Notification subscription registry
	var $reporting = SHOPP_ALL_ERR;		// level of reporting
	
	/**
	 * Setup error system and PHP error capture
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return void
	 **/
	function __construct ($level = SHOPP_ALL_ERR) {
		ShoppingObject::store('errors',$this->errors);
		
		if (defined('WP_DEBUG') && WP_DEBUG) $this->reporting = SHOPP_DEBUG_ERR;
		if ($level > $this->reporting) $this->reporting = $level;
		
		$this->notifications = new CallbackSubscription();

		$types = E_ALL ^ E_NOTICE;
		if (defined('WP_DEBUG') && WP_DEBUG) $types = E_ALL;
		// Handle PHP errors
		if ($this->reporting >= SHOPP_PHP_ERR)
			set_error_handler(array($this,'phperror'),$types);
	}
	
	/**
	 * Adds a ShoppError to the registry
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param ShoppError $ShoppError The ShoppError object to add
	 * @return void
	 **/
	function add ($ShoppError) {
		if (isset($ShoppError->code)) $this->errors[$ShoppError->code] = $ShoppError;
		else $this->errors[] = $ShoppError;
		$this->notifications->send($ShoppError);
	}
	
	/**
	 * Gets all errors up to a specified error level
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param int $level The maximum error level
	 * @return array A list of errors
	 **/
	function get ($level=SHOPP_DEBUG_ERR) {
		$errors = array();
		foreach ($this->errors as &$error)
			if ($error->level <= $level) $errors[] = &$error;
		return $errors;
	}
	
	/**
	 * Gets all errors of a specific error level
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param int $level The level of errors to retrieve
	 * @return array A list of errors
	 **/
	function level ($level=SHOPP_ALL_ERR) {
		$errors = array();
		foreach ($this->errors as &$error)
			if ($error->level == $level) $errors[] = &$error;
		return $errors;
	}
	
	/**
	 * Gets an error message with a specific error code
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param string $code The name of the error code
	 * @return ShoppError The error object
	 **/
	function code ($code) {
		if (!empty($code) && isset($this->errors[$code])) 
			return $this->errors[$code];
	}
	
	/**
	 * Gets all errors from a specified source (object)
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param string $source The source object of errors
	 * @return array A list of errors
	 **/
	function source ($source) {
		if (empty($source)) return array();
		$errors = array();
		foreach ($this->errors as &$error)
			if ($error->source == $source) $errors[] = &$error;
		return $errors;
	}
	
	/**
	 * Determines if any errors exist up to the specified error level
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param int $level The maximum level to look for errors in
	 * @return void Description...
	 **/
	function exist ($level=SHOPP_DEBUG_ERR) {
		$errors = array();
		foreach ($this->errors as &$error)
			if ($error->level <= $level) $errors[] = &$error;
		return (count($errors) > 0);
	}
	
	/**
	 * Removes an error from the registry
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param ShoppError $error The ShoppError object to remove
	 * @return boolean True when removed, false if removal failed
	 **/
	function remove ($error) {
		if (!isset($this->errors[$error->code])) return false;
		unset($this->errors[$error->code]);
		return true;
	}
	
	/**
	 * Removes all errors from the error registry
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return void
	 **/
	function reset () {
		$this->errors = array();
	}
	
	/**
	 * Reports PHP generated errors to the Shopp error system
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param int $number The error type
	 * @param string $message The PHP error message
	 * @param string $file The file the error occurred in
	 * @param int $line The line number the error occurred at in the file
	 * @return void
	 **/
	function phperror ($number, $message, $file, $line) {
		if (strpos($file,SHOPP_PATH) !== false)
			new ShoppError($message,'php_error',SHOPP_PHP_ERR,
				array('file'=>$file,'line'=>$line,'phperror'=>$number));
	}
		
	/**
	 * Provides functionality for the shopp('error') tags
	 * 
	 * Support for triggering errors through the Template API.
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param string $property The error property for the tag
	 * @param array $options Tag options
	 * @return void
	 **/
	function tag ($property,$options=array()) {
		global $Shopp;
		if (empty($options)) return false;
		switch ($property) {
			case "trxn": new ShoppError(key($options),'template_error',SHOPP_TRXN_ERR); break;
			case "auth": new ShoppError(key($options),'template_error',SHOPP_AUTH_ERR); break;
			case "addon": new ShoppError(key($options),'template_error',SHOPP_ADDON_ERR); break;
			case "comm": new ShoppError(key($options),'template_error',SHOPP_COMM_ERR); break;
			case "stock": new ShoppError(key($options),'template_error',SHOPP_STOCK_ERR); break;
			case "admin": new ShoppError(key($options),'template_error',SHOPP_ADMIN_ERR); break;
			case "db": new ShoppError(key($options),'template_error',SHOPP_DB_ERR); break;
			case "debug": new ShoppError(key($options),'template_error',SHOPP_DEBUG_ERR); break;
			default: new ShoppError(key($options),'template_error',SHOPP_ERR); break;
		}
	}
	
}

/**
 * ShoppError class
 * 
 * Triggers an error that is handled by the Shopp error system.
 *
 * @author Jonathan Davis
 * @since 1.0
 * @package shopp
 * @subpackage errors
 **/
class ShoppError {

	var $code;
	var $source;
	var $messages;
	var $level;
	var $data = array();
    
	/**
	 * Creates and registers a new error
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void Description...
	 **/
	function ShoppError($message='',$code='',$level=SHOPP_ERR,$data='') {
		$Errors = &ShoppErrors();
		
		if (!is_a($Errors,'ShoppErrors')) return;
		if ($level > $Errors->reporting) return;
		if (empty($message)) return;

		$php = array(
			1		=> 'ERROR',
			2		=> 'WARNING',
			4		=> 'PARSE ERROR',
			8		=> 'NOTICE',
			16		=> 'CORE ERROR',
			32		=> 'CORE WARNING',
			64		=> 'COMPILE ERROR',
			128		=> 'COMPILE WARNING',
			256		=> 'USER ERROR',
			512		=> 'USER WARNING',
			1024	=> 'USER NOTICE',
			2048	=> 'STRICT NOTICE',
			4096 	=> 'RECOVERABLE ERROR'
		);
		$debug = debug_backtrace();
		
		$this->code = $code;
		$this->messages[] = $message;
		$this->level = $level;
		$this->data = $data;
		$this->debug = $debug[1];

		// Handle template errors
		if (isset($this->debug['class']) && $this->debug['class'] == "ShoppErrors")
			$this->debug = $debug[2];
		
		if (isset($data['file'])) $this->debug['file'] = $data['file'];
		if (isset($data['line'])) $this->debug['line'] = $data['line'];
		unset($this->debug['object'],$this->debug['args']);
		
		$this->source = "Shopp";
		if (isset($this->debug['class'])) $this->source = $this->debug['class'];
		if (isset($this->data['phperror']) && isset($php[$this->data['phperror']])) 
			$this->source = "PHP ".$php[$this->data['phperror']];
		
		$Errors = &ShoppErrors();
		if (!empty($Errors)) $Errors->add($this);
	}
	
	/**
	 * Prevent excess data from being stored in the session
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function __sleep () {
		return array('code','source','messages','level');
	}
	
	/**
	 * Tests if the error message is blank
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return boolean True for blank error messages
	 **/
	function blank () {
		return (join('',$this->messages) == "");
	}
	
	/**
	 * Displays messages registered to a specific error code
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param boolean $remove (optional) (Default true) Removes the error after retrieving it
	 * @param boolean $source Prefix the error with the source object where the error was triggered
	 * @param string $delimiter The delimeter used to join multiple error messages
	 * @return string A collection of concatenated error messages
	 **/
	function message ($remove=false,$source=false,$delimiter="\n") {
		$string = "";
		// Show source if debug is on, or not a general error message
		if (((defined('WP_DEBUG') && WP_DEBUG) || $this->level > SHOPP_ERR) && 
			!empty($this->source) && $source) $string .= "$this->source: ";
		$string .= join($delimiter,$this->messages);
		if ($remove) {
			$Errors = &ShoppErrors();
			if (!empty($Errors->errors)) $Errors->remove($this);
		}
		return $string;
	}
				
}

/**
 * ShoppErrorLogging class
 *
 * Subscribes to error notifications in order to log any errors 
 * generated.
 * 
 * @author Jonathan Davis
 * @since 1.0
 * @package shopp
 * @subpackage errors
 **/
class ShoppErrorLogging {
	var $dir;
	var $file = "shopp_debug.log";
	var $logfile;
	var $log;
	var $loglevel = 0;
	
	/**
	 * Setup for error logging
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return void
	 **/
	function __construct ($loglevel=0) {
		$this->loglevel = $loglevel;
		$this->dir = defined('SHOPP_LOG_PATH') ? SHOPP_LOG_PATH : sys_get_temp_dir();
		$this->dir = sanitize_path($this->dir); // Windows path sanitiation

		$siteurl = parse_url(get_bloginfo('siteurl'));
		$sub = (!empty($path)?"_".sanitize_title_with_dashes($path):'');
		$this->logfile = trailingslashit($this->dir).$siteurl['host'].$sub."_".$this->file;

		$Errors = &ShoppErrors();
		$Errors->notifications->subscribe($this,'log');
	}
	
	/**
	 * Logs an error to the error log file
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param ShoppError $error The error object to log
	 * @return void
	 **/
	function log ($error) {
		if ($error->level > $this->loglevel) return;
		$debug = "";
		if (isset($error->debug['file'])) $debug = " [".basename($error->debug['file']).", line ".$error->debug['line']."]";
		$message = date("Y-m-d H:i:s",mktime())." - ".$error->message(false,true).$debug."\n";
		if (($this->log = @fopen($this->logfile,'at')) !== false) {
			fwrite($this->log,$message);
			fclose($this->log);
		} else error_log($message);
	}
	
	/**
	 * Empties the error log file
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return void
	 **/
	function reset () {
		$this->log = fopen($this->logfile,'w');
		fwrite($this->log,'');
		fclose($this->log);
	}
	
	/**
	 * Gets the end of the log file
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param int $lines The number of lines to get from the end of the log file
	 * @return array List of log file lines
	 **/
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

/**
 * ShoppErrorNotification class
 * 
 * Sends error notification emails when errors are triggered 
 * to specified recipients
 *
 * @author Jonathan Davis
 * @since 1.0
 * @package shopp
 * @subpackage errors
 **/
class ShoppErrorNotification {
	
	var $recipients;	// Recipient addresses to send to
	var $types=0;		// Error types to send
	
	/**
	 * Relays triggered errors to email messages
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param string $recipients List of email addresses
	 * @param array $types The types of errors to report
	 * @return void
	 **/
	function __construct ($recipients='',$types=array()) {
		if (empty($recipients)) return;
		$this->recipients = $recipients;
		foreach ((array)$types as $type) $this->types += $type;
		$Errors = &ShoppErrors();
		$Errors->notifications->subscribe($this,'notify');
	}
	
	/**
	 * Generates and sends an email of an error to the recipient list
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param ShoppError $error The error object
	 * @return void
	 **/
	function notify ($error) {
		if (!($error->level & $this->types)) return;
		$url = parse_url(get_bloginfo('url'));
		$_ = array();
		$_[] = 'From: "'.get_bloginfo('sitename').'" <shopp@'.$url['host'].'>';
		$_[] = 'To: '.$this->recipients;
		$_[] = 'Subject: '.__('Shopp Notification','Shopp');
		$_[] = '';
		$_[] = __('This is an automated message notification generated when the Shopp installation at '.get_bloginfo('url').' encountered the following:','Shopp');
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
		foreach ($this->subscribers as $callback) {
			call_user_func_array($callback,$args);
		}
	}
	
}

/**
 * Helper to access the error system
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @return void Description...
 **/
function &ShoppErrors () {
	global $Shopp;
	return $Shopp->Errors;
}

/**
 * Detects ShoppError objects
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param object $e The object to test
 * @return boolean True if the object is a ShoppError
 **/
function is_shopperror ($e) {
	return (get_class($e) == "ShoppError");
}

?>