<?php
/**
 * Provides functional support for older versions of PHP & WordPress
 *
 * @author Jonathan Davis
 * @version $Id$
 * @copyright Ingenesis Limited, 18 November, 2009
 * @package shopp
 **/

if( !function_exists('esc_url') ) {
	/**
	 * Checks and cleans a URL.  From WordPress 2.8.0+  Included for WordPress 2.7 Users of Shopp
	 *
	 * A number of characters are removed from the URL. If the URL is for displaying
	 * (the default behaviour) amperstands are also replaced. The 'esc_url' filter
	 * is applied to the returned cleaned URL.
	 *
	 * @since 2.8.0
	 * @uses esc_url()
	 * @uses wp_kses_bad_protocol() To only permit protocols in the URL set
	 *		via $protocols or the common ones set in the function.
	 *
	 * @param string $url The URL to be cleaned.
	 * @param array $protocols Optional. An array of acceptable protocols.
	 *		Defaults to 'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'gopher', 'nntp', 'feed', 'telnet' if not set.
	 * @return string The cleaned $url after the 'cleaned_url' filter is applied.
	 */
	function esc_url( $url, $protocols = null ) {
		return clean_url( $url, $protocols, 'display' );
	}
}

if (!function_exists('json_encode')) {
	/**
	 * Builds JSON {@link http://www.json.org/} formatted strings from PHP data structures
	 *
	 * @param mixed $a PHP data structure
	 * @return string JSON encoded string
	 * @author Jonathan Davis
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

/**
 * List files and directories inside the specified path */
if(!function_exists('scandir')) {
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

if (!function_exists('attribute_escape_deep')) {
	function attribute_escape_deep($value) {
		 $value = is_array($value) ?
			 array_map('attribute_escape_deep', $value) :
			 attribute_escape($value);
		 return $value;
	}
}


?>