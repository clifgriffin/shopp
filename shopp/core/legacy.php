<?php
/**
 * legacy.php
 * A library of functions for older version of PHP and WordPress
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
	 * @return void Description...
	 **/
	function array_replace (array &$array, array &$array1) {
		$args = func_get_args();
		$count = func_num_args();

		for ($i = 1; $i < $count; $i++) {
			if (is_array($args[$i]))
				foreach ($args[$i] as $k => $v) $array[$k] = $v;
		}

		return $array;
	}
}


?>