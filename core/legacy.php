<?php
/**
 * legacy.php
 * A library of functions for compatibility with older version of PHP and WordPress
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, November 18, 2009
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 **/

if (!function_exists('json_encode')) {
	/**
	 * Builds JSON {@link http://www.json.org/} formatted strings from PHP data structures
	 *
	 * @author Jonathan Davis
	 * @since PHP 5.2.0+
	 *
	 * @param mixed $a PHP data structure
	 * @return string JSON encoded string
	 **/
	function json_encode ($a = false) {
		if (is_null($a)) return 'null';
		if ($a === false) return 'false';
		if ($a === true) return 'true';
		if (is_scalar($a)) {
			if (is_float($a)) {
				// Always use "." for floats.
				return floatval(str_replace(",", ".", strval($a)));
			}

			if (is_string($a)) {
				static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
				return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
			} else return $a;
		}

		$isList = true;
		for ($i = 0, reset($a); $i < count($a); $i++, next($a)) {
			if (key($a) !== $i) {
				$isList = false;
				break;
			}
		}

		$result = array();
		if ($isList) {
			foreach ($a as $v) $result[] = json_encode($v);
			return '[' . join(',', $result) . ']';
		} else {
			foreach ($a as $k => $v) $result[] = json_encode($k).':'.json_encode($v);
			return '{' . join(',', $result) . '}';
		}
	}
}

if(!function_exists('scandir')) {
	/**
	 * Lists files and directories inside the specified path
	 *
	 * @author Jonathan Davis
	 * @since PHP 5.0+
	 *
	 * @param string $dir Directory path to scan
	 * @param int $sortorder The sort order of the file listing (0=alphabetic, 1=reversed)
	 * @return array|boolean The list of files or false if not available
	 **/
	function scandir($dir, $sortorder = 0) {
		if(is_dir($dir) && $dirlist = @opendir($dir)) {
			$files = array();
			while(($file = readdir($dirlist)) !== false) $files[] = $file;
			closedir($dirlist);
			($sortorder == 0) ? asort($files) : rsort($files);
			return $files;
		} else return false;
	}
}

if (!function_exists('property_exists')) {
	/**
	 * Checks an object for a declared property
	 *
	 * @author Jonathan Davis
	 * @since PHP 5.1.0+
	 *
	 * @param object $Object The object to inspect
	 * @param string $property The name of the property to look for
	 * @return boolean True if the property exists, false otherwise
	 **/
	function property_exists($object, $property) {
		return array_key_exists($property, get_object_vars($object));
	}
}

if ( !function_exists('sys_get_temp_dir')) {
	/**
	 * Determines the temporary directory for the local system
	 *
	 * @author Jonathan Davis
	 * @since PHP 5.2.1+
	 *
	 * @return string The path to the system temp directory
	 **/
	function sys_get_temp_dir() {
		if (!empty($_ENV['TMP'])) return realpath($_ENV['TMP']);
		if (!empty($_ENV['TMPDIR'])) return realpath( $_ENV['TMPDIR']);
		if (!empty($_ENV['TEMP'])) return realpath( $_ENV['TEMP']);
		$tempfile = tempnam(uniqid(rand(),TRUE),'');
		if (file_exists($tempfile)) {
			unlink($tempfile);
			return realpath(dirname($tempfile));
		}
	}
}

if (!function_exists('get_class_property')) {
	/**
	 * Gets the property of an uninstantiated class
	 *
	 * Provides support for getting a property of an uninstantiated
	 * class by dynamic name.  As of PHP 5.3.0 this function is no
	 * longer necessary as you can simply reference as $Classname::$property
	 *
	 * @author Jonathan Davis
	 * @since PHP 5.3.0
	 *
	 * @param string $classname Name of the class
	 * @param string $property Name of the property
	 * @return mixed Value of the property
	 **/
	function get_class_property ($classname, $property) {
	  if(!class_exists($classname)) return;
	  if(!property_exists($classname, $property)) return;

	  $vars = get_class_vars($classname);
	  return $vars[$property];
	}
}

if (!function_exists('array_replace')) {
	/**
	 * Replaces elements from passed arrays into the first array
	 *
	 * Provides backwards compatible support for the PHP 5.3.0
	 * array_replace() function.
	 *
	 * @author Jonathan Davis
	 * @since PHP 5.3.0
	 *
	 * @return array
	 **/
	function array_replace (&$array, &$array1) {
		$args = func_get_args();
		$count = func_num_args();

		for ($i = 1; $i < $count; $i++) {
			if (is_array($args[$i]))
				foreach ($args[$i] as $k => $v) $array[$k] = $v;
		}

		return $array;
	}
}

if (!function_exists('array_intersect_key')) {
	/**
	 * Computes the intersection of arrays using keys for comparison
	 *
	 * @author Jonathan Davis
	 * @since PHP 5.1.0
	 *
	 * @return array
	 **/
	function array_intersect_key () {
		$arrays = func_get_args();
		$result = array_shift($arrays);
		foreach ($arrays as $array) {
			foreach ($result as $key => $v)
				if (!array_key_exists($key, $array)) unset($result[$key]);
		}
		return $result;
	}
}

if (defined('SHOPP_PROXY_CONNECT') && SHOPP_PROXY_CONNECT) {
	/**
	 * Converts legacy Shopp proxy config macros to WP_HTTP_Proxy config macros
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @deprecated SHOPP_PROXY_CONNECT
	 * @deprecated SHOPP_PROXY_SERVER
	 * @deprecated SHOPP_PROXY_USERPWD
	 *
	 * @return void
	 **/
	function shopp_convert_proxy_config () {
		if (!defined('SHOPP_PROXY_SERVER') || !defined('SHOPP_PROXY_USERPWD')) return;
		$host = SHOPP_PROXY_SERVER;
		$user = SHOPP_PROXY_USERPWD;

		if (false !== strpos($host,':')) list($host,$port) = explode(':',$host);
		if (false !== strpos($user,':')) list($user,$pwd) = explode(':',$user);

		if (!defined('WP_PROXY_HOST')) define('WP_PROXY_HOST',$host);
		if (!defined('WP_PROXY_PORT') && $port) define('WP_PROXY_PORT',$port);
		if (!defined('WP_PROXY_USERNAME')) define('WP_PROXY_USERNAME',$user);
		if (!defined('WP_PROXY_PASSWORD') && $pwd) define('WP_PROXY_PASSWORD',$pwd);
	}
	shopp_convert_proxy_config();
}

if (!function_exists('get_class_property')) {
	/**
	 * Provides dynamic access to a specified class property
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @param string $Class Name of the class to lookup a property from
	 * @param string $property The name of the property to retrieve
	 * @return mixed The value of the requested property
	 **/
	function get_class_property ($Class,$property) {
	  if(!class_exists($Class)) return null;
	  if(!property_exists($Class, $property)) return null;

	  $vars = get_class_vars($Class);
	  return $vars[$property];
	}
}

if (!function_exists('shopp_suhosin_warning')) {
	/**
	 * Detect Suhosin enabled with problematic settings
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void Description...
	 **/
	function shopp_suhosin_warning () {

		return ( // Is Suhosin loaded or available?
				(extension_loaded('Suhosin') || (defined('SUHOSIN_PATCH') && SUHOSIN_PATCH))
				&& // Are the known problem settings defined?
				(
					@ini_get('suhosin.max_array_index_length') > 0 && @ini_get('suhosin.max_array_index_length') < 256
					&& @ini_get('suhosin.post.max_array_index_length') > 0 && @ini_get('suhosin.post.max_array_index_length') < 256
					&& @ini_get('suhosin.post.max_totalname_length') > 0 && @ini_get('suhosin.post.max_totalname_length') < 65535
					&& @ini_get('suhosin.post.max_vars') > 0 && @ini_get('suhosin.post.max_vars') < 1024
					&& @ini_get('suhosin.request.max_array_index_length') > 0 && @ini_get('suhosin.request.max_array_index_length') < 256
					&& @ini_get('suhosin.request.max_totalname_length') > 0 && @ini_get('suhosin.request.max_totalname_length') < 65535
					&& @ini_get('suhosin.request.max_vars') > 0 && @ini_get('suhosin.request.max_vars') < 1024
				)
		);
	}
}

/**
 * Checks for prerequisite technologies needed for Shopp
 *
 * @author Jonathan Davis
 * @since 1.0
 * @version 1.2
 *
 * @return void
 **/
if (!function_exists('shopp_prereqs')) {
	function shopp_prereqs () {
		$activation = false;
		if ( isset($_GET['action']) && isset($_GET['plugin']) ) {
			$activation = ('activate' == $_GET['action']);
			if ($activation) {
				$plugin = $_GET['plugin'];
				if (function_exists('check_admin_referer'))
					check_admin_referer('activate-plugin_' . $plugin);
			}
		}

		$errors = array();

		// Check PHP version
		if (version_compare(PHP_VERSION, '5.0','<')) array_push($errors,'phpversion','php5');
		if (version_compare(PHP_VERSION, '5.1.3','==')) array_push($errors,'phpversion','php513');

		// Check WordPress version
		if (version_compare(get_bloginfo('version'),'3.1','<'))
			array_push($errors,'wpversion','wp31');

		// Check for cURL
		$curl_func = array('curl_init','curl_setopt','curl_exec','curl_close');
		$curl_support = array_filter($curl_func,'function_exists');
		if (count($curl_func) != count($curl_support)) $errors[] = 'curl';

		// Check for GD
		if (!function_exists("gd_info")) $errors[] = 'gd';
		else if (!array_keys(gd_info(),array('JPG Support','JPEG Support'))) $errors[] = 'jpgsupport';

		if (empty($errors)) return (!defined('SHOPP_UNSUPPORTED')?define('SHOPP_UNSUPPORTED',false):true);

		$plugin_path = dirname(dirname(__FILE__));
		// Manually load text domain for translated activation errors
		$languages_path = str_replace('\\', '/', $plugin_path.'/lang');
		load_plugin_textdomain('Shopp',false,$languages_path);

		// Define translated messages
		$_ = array(
			'header' => __('Shopp Activation Error','Shopp'),
			'intro' => __('Sorry! Shopp cannot be activated for this WordPress install.'),
			'phpversion' => sprintf(__('Your server is running PHP %s!','Shopp'),PHP_VERSION),
			'php5' => __('Shopp requires PHP 5.0+.','Shopp'),
			'php513' => __('Shopp will not work with PHP 5.1.3 because of a critical bug in that version.','Shopp'),
			'wpversion' => sprintf(__('This site is running WordPress %s!','Shopp'),get_bloginfo('version')),
			'wp31' => __('Shopp requires WordPress 3.1+.','Shopp'),
			'curl' => __('Your server does not have cURL support available! Shopp requires the cURL library for server-to-server communication.','Shopp'),
			'gdsupport' => __('Your server does not have GD support! Shopp requires the GD image library with JPEG support for generating gallery and thumbnail images.','Shopp'),
			'jpgsupport' => __('Your server does not have JPEG support for the GD library! Shopp requires JPEG support in the GD image library to generate JPEG images.','Shopp'),
			'nextstep' => sprintf(__('Try contacting your web hosting provider or server administrator to upgrade your server. For more information about the requirements for running Shopp, see the %sShopp Documentation%s','Shopp'),'<a href="'.SHOPP_DOCS.'Requirements">','</a>'),
			'continue' => __('Return to Plugins page')
		);

		if ($activation) {
			$string = '<h1>'.$_['header'].'</h1><p>'.$_['intro'].'</h1></p><ul>';
			foreach ((array)$errors as $error) if (isset($_[$error])) $string .= "<li>{$_[$error]}</li>";
			$string .= '</ul><p>'.$_['nextstep'].'</p><p><a class="button" href="javascript:history.go(-1);">&larr; '.$_['continue'].'</a></p>';
			wp_die($string);
		}

		if (!function_exists('deactivate_plugins'))
			require( ABSPATH . 'wp-admin/includes/plugin.php' );

		$plugin = basename($plugin_path)."/Shopp.php";
		deactivate_plugins($plugin,true);

		$phperror = '';
		if ( is_array($errors) && ! empty($errors) ) {
			foreach ( $errors as $error ) {
				if ( isset($_[$error]) )
					$phperror .= $_[$error].' ';
				trigger_error($phperror,E_USER_WARNING);
			}
		}
		if (!defined('SHOPP_UNSUPPORTED'))
			define('SHOPP_UNSUPPORTED',true);
	}
}
shopp_prereqs(); // Check for Shopp requisite technologies for activation

?>