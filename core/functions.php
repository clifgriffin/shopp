<?php
/**
 * functions.php
 * A library of global utility functions for Shopp
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, November 18, 2009
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 **/

/**
 * Automatically generates a list of number ranges distributed across a number set
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param int $avg Mean average number in the distribution
 * @param int $max The max number in the distribution
 * @param int $min The minimum in the distribution
 * @return array A list of number ranges
 **/
function auto_ranges ($avg,$max,$min) {
	$ranges = array();
	if ($avg == 0 || $max == 0) return $ranges;
	$power = floor(log10($avg));
	$scale = pow(10,$power);
	$median = round($avg/$scale)*$scale;
	$range = $max-$min;
	
	if ($range == 0) return $ranges;
	
	$steps = floor($range/$scale);
	if ($steps > 7) $steps = 7;
	elseif ($steps < 2) {
		$scale = $scale/2;
		$steps = ceil($range/$scale);
		if ($steps > 7) $steps = 7;
		elseif ($steps < 2) $steps = 2;
	}
		
	$base = $median-($scale*floor(($steps-1)/2));
	for ($i = 0; $i < $steps; $i++) {
		$range = array("min" => 0,"max" => 0);
		if ($i == 0) $range['max'] = $base;
		else if ($i+1 >= $steps) $range['min'] = $base;
		else $range = array("min" => $base, "max" => $base+$scale);
		$ranges[] = $range;
		if ($i > 0) $base += $scale;
	}
	return $ranges;
}

/**
 * Calculates the timestamp of a day based on a repeating interval (Fourth Thursday in November (Thanksgiving))
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param int|string $week The week of the month (1-4, -1 or first-fourth, last)
 * @param int|string $dayOfWeek The day of the week (0-6 or Sunday-Saturday)
 * @param int $month The month, uses current month if none provided
 * @param int $year The year, uses current year if none provided
 * @return void
 **/
function datecalc($week=-1,$dayOfWeek=-1,$month=-1,$year=-1) {
	$weekdays = array("sunday" => 0, "monday" => 1, "tuesday" => 2, "wednesday" => 3, "thursday" => 4, "friday" => 5, "saturday" => 6);
	$weeks = array("first" => 1, "second" => 2, "third" => 3, "fourth" => 4, "last" => -1);

	if ($month == -1) $month = date ("n");	// No month provided, use current month
	if ($year == -1) $year = date("Y");   	// No year provided, use current year

	// Day of week is a string, look it up in the weekdays list
	if (!is_numeric($dayOfWeek)) {
		foreach ($weekdays as $dayName => $dayNum) {
			if (strtolower($dayOfWeek) == substr($dayName,0,strlen($dayOfWeek))) {
				$dayOfWeek = $dayNum;
				break;
			}
		}
	}
	if ($dayOfWeek < 0 || $dayOfWeek > 6) return false;
	
	if (!is_numeric($week)) $week = $weeks[$week];	
	
	if ($week == -1) {
		$lastday = date("t", mktime(0,0,0,$month,1,$year));
		$tmp = (date("w",mktime(0,0,0,$month,$lastday,$year)) - $dayOfWeek) % 7;
		if ($tmp < 0) $tmp += 7;
		$day = $lastday - $tmp;
	} else {
		$tmp = ($dayOfWeek - date("w",mktime(0,0,0,$month,1,$year))) % 7;
		if ($tmp < 0) $tmp += 7;
		$day = (7 * $week) - 6 + $tmp;
	}
	
	return mktime(0,0,0,$month,$day,$year);
}

/**
 * Returns the duration (in days) between two timestamps
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param int $start The starting timestamp
 * @param int $end The ending timestamp
 * @return int	Number of days between the start and end
 **/
function duration ($start,$end) {
	return ceil(($end - $start) / 86400);
}

/**
 * Callback to filter out files beginning with a dot
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param string $name The filename to check
 * @return boolean
 **/
function filter_dotfiles ($name) {
	return (substr($name,0,1) != ".");
}

/**
 * Finds files of a specific extension
 *
 * Recursively searches directories and one-level deep of sub-directories for
 * files with a specific extension
 * 
 * NOTE: Files are saved to the $found parameter, an array passed by
 * reference, not a returned value
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param string $extension File extension to search for
 * @param string $directory Starting directory
 * @param string $root Starting directory reference
 * @param string &$found List of files found
 * @return boolean Returns true if files are found
 **/
function find_files ($extension, $directory, $root, &$found) {
	if (is_dir($directory)) {
		
		$Directory = @dir($directory);
		if ($Directory) {
			while (( $file = $Directory->read() ) !== false) {
				if (substr($file,0,1) == "." || substr($file,0,1) == "_") continue;				// Ignore .dot files and _directories
				if (is_dir($directory.DIRECTORY_SEPARATOR.$file) && $directory == $root)		// Scan one deep more than root
					find_files($extension,$directory.DIRECTORY_SEPARATOR.$file,$root, $found);	// but avoid recursive scans
				if (substr($file,strlen($extension)*-1) == $extension)
					$found[] = substr($directory,strlen($root)).DIRECTORY_SEPARATOR.$file;		// Add the file to the found list
			}
			return true;
		}
	}
	return false;
}

/**
 * Determines the mimetype of a file
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param string $file The path to the file
 * @param string $name (optional) The name of the file
 * @return string The mimetype of the file
 **/
function file_mimetype ($file,$name=false) {
	if (!$name) $name = basename($file);
	if (function_exists('finfo_open')) {
		// Try using PECL module
		$f = finfo_open(FILEINFO_MIME);
		list($mime,$charset) = explode(";",finfo_file($f, $file));
		finfo_close($f);
		new ShoppError('File mimetype detection (finfo_open): '.$mime,false,SHOPP_DEBUG_ERR);
		return $mime;
	} elseif (class_exists('finfo')) {
		// Or class
		$f = new finfo(FILEINFO_MIME);
		new ShoppError('File mimetype detection (finfo class): '.$f->file($file),false,SHOPP_DEBUG_ERR);
		return $f->file($file);
	} elseif (strlen($mime=trim(@shell_exec('file -bI "'.escapeshellarg($file).'"')))!=0) {
		new ShoppError('File mimetype detection (shell file command): '.$mime,false,SHOPP_DEBUG_ERR);
		// Use shell if allowed
		return trim($mime);
	} elseif (strlen($mime=trim(@shell_exec('file -bi "'.escapeshellarg($file).'"')))!=0) {
		new ShoppError('File mimetype detection (shell file command, alt options): '.$mime,false,SHOPP_DEBUG_ERR);
		// Use shell if allowed
		return trim($mime);
	} elseif (function_exists('mime_content_type') && $mime = mime_content_type($file)) {
		// Try with magic-mime if available
		new ShoppError('File mimetype detection (mime_content_type()): '.$mime,false,SHOPP_DEBUG_ERR);
		return $mime;
	} else {
		if (!preg_match('/\.([a-z0-9]{2,4})$/i', $name, $extension)) return false;
				
		switch (strtolower($extension[1])) {
			// misc files
			case 'txt':	return 'text/plain';
			case 'htm': case 'html': case 'php': return 'text/html';
			case 'css': return 'text/css';
			case 'js': return 'application/javascript';
			case 'json': return 'application/json';
			case 'xml': return 'application/xml';
			case 'swf':	return 'application/x-shockwave-flash';
		
			// images
			case 'jpg': case 'jpeg': case 'jpe': return 'image/jpg';
			case 'png': case 'gif': case 'bmp': case 'tiff': return 'image/'.strtolower($matches[1]);
			case 'tif': return 'image/tif';
			case 'svg': case 'svgz': return 'image/svg+xml';
		
			// archives
			case 'zip':	return 'application/zip';
			case 'rar':	return 'application/x-rar-compressed';
			case 'exe':	case 'msi':	return 'application/x-msdownload';
			case 'tar':	return 'application/x-tar';
			case 'cab': return 'application/vnd.ms-cab-compressed';
		
			// audio/video
			case 'flv':	return 'video/x-flv';
			case 'mpeg': case 'mpg':	case 'mpe': return 'video/mpeg';
			case 'mp4s': return 'application/mp4';
			case 'mp3': return 'audio/mpeg3';
			case 'wav':	return 'audio/wav';
			case 'aiff': case 'aif': return 'audio/aiff';
			case 'avi':	return 'video/msvideo';
			case 'wmv':	return 'video/x-ms-wmv';
			case 'mov':	case 'qt': return 'video/quicktime';
		
			// ms office
			case 'doc':	case 'docx': return 'application/msword';
			case 'xls':	case 'xlt':	case 'xlm':	case 'xld':	case 'xla':	case 'xlc':	case 'xlw':	case 'xll':	return 'application/vnd.ms-excel';
			case 'ppt':	case 'pps':	return 'application/vnd.ms-powerpoint';
			case 'rtf':	return 'application/rtf';
		
			// adobe
			case 'pdf':	return 'application/pdf';
			case 'psd': return 'image/vnd.adobe.photoshop';
		    case 'ai': case 'eps': case 'ps': return 'application/postscript';
		
			// open office
		    case 'odt': return 'application/vnd.oasis.opendocument.text';
		    case 'ods': return 'application/vnd.oasis.opendocument.spreadsheet';
		}

		return false;
	}
}

/**
 * Converts a numeric string to a floating point number
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param string $value Numeric string to be converted
 * @param boolean $format (optional) Numerically formats the value to normalize it (Default: true) 
 * @return float
 **/
function floatvalue($value, $format=true) {
	$value = preg_replace("/[^\d,\.]/","",$value); // Remove any non-numeric string data
	$value = preg_replace("/,/",".",$value); // Replace commas with periods
	$value = preg_replace("/[^0-9\.]/","", $value); // Get rid of everything but numbers and periods
	$value = preg_replace("/\.(?=.*\..*$)/s","",$value); // Replace all but the last period
    $value = preg_replace('#^([-]*[0-9\.,\' ]+?)((\.|,){1}([0-9-]{1,2}))*$#e', "str_replace(array('.', ',', \"'\", ' '), '', '\\1') . '.' . sprintf('%02d','\\4')", $value);
	if($format) return number_format(floatval($value),2);
	else return floatval($value);
}

/**
 * Modifies URLs to use SSL connections
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param string $url Source URL to rewrite 
 * @return string $url The secure URL
 **/
function force_ssl ($url) {
	if(isset($_SERVER['HTTPS']) && $_SERVER["HTTPS"] == "on")
		$url = str_replace('http://', 'https://', $url);
	return $url;
}

/**
 * Determines the gateway path to a gateway file
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param string $file The target gateway file 
 * @return string The path fragment for the gateway file
 **/
function gateway_path ($file) {
	return basename(dirname($file)).'/'.basename($file);
}

/**
 * Read the file meta data for Shopp addons
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param string $file The target file
 * @return string The meta block from the file
 **/
function get_filemeta ($file) {
	if (!file_exists($file)) return false;
	if (!is_readable($file)) return false;

	$meta = false;
	$string = "";
	
	$f = @fopen($file, "r");
	if (!$f) return false;
	while (!feof($f)) {
		$buffer = fgets($f,80);
		if (preg_match("/\/\*/",$buffer)) $meta = true;
		if ($meta) $string .= $buffer;
		if (preg_match("/\*\//",$buffer)) break;
	}
	fclose($f);

	return $string;
}

/**
 * Handles sanitizing URLs for use in markup HREF attributes
 *
 * Wrapper for securing URLs generated with the WordPress 
 * add_query_arg() function
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param mixed $param1 Either newkey or an associative_array
 * @param mixed $param2 Either newvalue or oldquery or uri
 * @param mixed $param3 Optional. Old query or uri
 * @return string New URL query string.
 **/
if (!function_exists('href_add_query_arg')) {
	function href_add_query_arg () {
		$args = func_get_args();
		$url = call_user_func_array('add_query_arg',$args);
		list($uri,$query) = explode("?",$url);
		return $uri.'?'.htmlspecialchars($query);
	}
}

/**
 * Formats a number in the Indian numbering format
 *
 * The Indian numbering format involves grouping thousand
 * decimals by two places instead of by three. (e.g. 1,00,00,000)
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param float $number The number to format
 * @param array $format A formatting configuration array 
 * @return string Indian format number
 **/
function indian_number ($number,$format=false) {
	if (!$format) $format = array("precision"=>1,"decimals"=>".","thousands" => ",");

	$d = explode(".",$number);
	$number = "";
	$digits = substr($d[0],0,-3); // Get rid of the last 3
	
	if (strlen($d[0]) > 3) $number = substr($d[0],-3);
	else $number = $d[0];
	
	for ($i = 0; $i < (strlen($digits) / 2); $i++)
		$number = substr($digits,(-2*($i+1)),2).((strlen($number) > 0)?$format['thousands'].$number:$number);
	if ($format['precision'] > 0) 
		$number = $number.$format['decimals'].substr(number_format('0.'.$d[1],$format['precision']),2);
	return $number;
	
}

/**
 * Generates attribute markup for HTML inputs based on specified options
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param array $options An associative array of options
 * @param array $allowed (optional) Allowable attribute options for the element
 * @return string Attribute markup fragment
 **/
function inputattrs ($options,$allowed=array()) {
	if (!is_array($options)) return "";
	if (empty($allowed)) {
		$allowed = array("accesskey","alt","checked","class","disabled","format",
			"minlength","maxlength","readonly","required","size","src","tabindex",
			"title","value");
	}
	$string = "";
	$classes = "";
	if (isset($options['label'])) $options['value'] = $options['label'];
	foreach ($options as $key => $value) {
		if (!in_array($key,$allowed)) continue;
		switch($key) {
			case "class": $classes .= " $value"; break;
			case "disabled": $classes .= " disabled"; $string .= ' disabled="disabled"'; break;
			case "readonly": $classes .= " readonly"; $string .= ' readonly="readonly"'; break;
			case "required": $classes .= " required"; break;
			case "minlength": $classes .= " min$value"; break;
			case "format": $classes .= " $value"; break;
			default:
				$string .= ' '.$key.'="'.attribute_escape($value).'"';
		}
	}
	$string .= ' class="'.trim($classes).'"';
 	return $string;
}

/**
 * Determines if the current client is a known web crawler bot
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @return boolean Returns true if a bot user agent is detected
 **/
function is_robot() {
	$bots = array("Googlebot","TeomaAgent","Zyborg","Gulliver","Architext spider","FAST-WebCrawler","Slurp","Ask Jeeves","ia_archiver","Scooter","Mercator","crawler@fast","Crawler","InfoSeek sidewinder","Lycos_Spider_(T-Rex)","Fluffy the Spider","Ultraseek","MantraAgent","Moget","MuscatFerret","VoilaBot","Sleek Spider","KIT_Fireball","WebCrawler");
	foreach($bots as $bot)
		if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']),strtolower($bot))) return true;
	return false;
}

/**
 * Determines if the requested page is a Shopp page or if it matches a given Shopp page
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param string $page (optional) Page name to look for in Shopp's page registry
 * @return boolean
 **/
function is_shopp_page ($page=false) {
	global $Shopp,$wp_query;

	if (isset($wp_query->post->post_type) &&
		$wp_query->post->post_type != "page") return false;
	
	$pages = $Shopp->Settings->get('pages');
		
	// Detect if the requested page is a Shopp page
	if (!$page) {
		foreach ($pages as $page)
			if ($page['id'] == $wp_query->post->ID) return true;
		return false;
	}

	// Determine if the visitor's requested page matches the provided page
	if (!isset($pages[strtolower($page)])) return false;
	$page = $pages[strtolower($page)];
	if (isset($wp_query->post->ID) && 
		$page['id'] == $wp_query->post->ID) return true;
	return false;
}

/**
 * Detects SSL requests
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @return boolean 
 **/
function is_shopp_secure () {
	return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on");
}

/**
 * Generates a timestamp from a MySQL datetime format
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param string $datetime A MySQL date time string
 * @return int A timestamp number usable by PHP date functions
 **/
function mktimestamp ($datetime) {
	$h = $mn = $s = 0;
	list($Y, $M, $D, $h, $mn, $s) = sscanf($datetime,"%d-%d-%d %d:%d:%d");
	return mktime($h, $mn, $s, $M, $D, $Y);
}

/**
 * Converts a timestamp number to an SQL datetime formatted string
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param int $timestamp A timestamp number
 * @return string An SQL datetime formatted string
 **/
function mkdatetime ($timestamp) {
	return date("Y-m-d H:i:s",$timestamp);
}

/**
 * Returns the 24-hour equivalent of a the Ante Meridiem or Post Meridem hour
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param int $hour The hour of the meridiem
 * @param string $meridiem Specified meridiem of "AM" or "PM"
 * @return int The 24-hour equivalent
 **/
function mk24hour ($hour, $meridiem) {
	if ($hour < 12 && $meridiem == "PM") return $hour + 12;
	if ($hour == 12 && $meridiem == "AM") return 0;
	return $hour;
}

/**
 * Returns a list marked-up as drop-down menu options */
/**
 * Generates HTML markup for the options of a drop-down menu
 *
 * Takes a list of options and generates the option elements for an HTML 
 * select element.  By default, the option values and labels will be the 
 * same.  If the values option is set, the option values will use the 
 * key of the associative array, and the option label will be the value in 
 * the array.  The extend option can be used to ensure that if the selected
 * value does not exist in the menu, it will be automatically added at the
 * top of the list.
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param array $list The list of options
 * @param int|string $selected The array index, or key name of the selected value
 * @param boolean $values (optional) Use the array key as the option value attribute (defaults to false)
 * @param boolean $extend (optional) Use to add the selected value if it doesn't exist in the specified list of options
 * @return string The markup of option elements
 **/
function menuoptions ($list,$selected=null,$values=false,$extend=false) {
	if (!is_array($list)) return "";
	$string = "";
	// Extend the options if the selected value doesn't exist
	if ((!in_array($selected,$list) && !isset($list[$selected])) && $extend)
		$string .= "<option value=\"$selected\">$selected</option>";
	foreach ($list as $value => $text) {
		if ($values) {
			if ($value == $selected) $string .= "<option value=\"$value\" selected=\"selected\">$text</option>";
			else  $string .= "<option value=\"$value\">$text</option>";
		} else {
			if ($text == $selected) $string .= "<option selected=\"selected\">$text</option>";
			else  $string .= "<option>$text</option>";
		}
	}
	return $string;
}

/**
 * Formats a number amount using a specified currency format
 *
 * The number is formatted based on a currency formatting configuration
 * array that  includes the currency symbol position (cpos), the currency 
 * symbol (currency), the decimal precision (precision), the decimal character 
 * to use (decimals) and the thousands separator (thousands).
 * 
 * If the currency format is not specified, the currency format from the 
 * store setting is used.  If no setting is available, the currency format
 * for US dollars is used.
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param float $amount The amount to be formatted
 * @param array $format The currency format to use
 * @return string The formatted amount
 **/
function money ($amount,$format=false) {
	global $Shopp;
	$locale = $Shopp->Settings->get('base_operations');
	if (!$format) $format = $locale['currency']['format'];
	if (empty($format['currency'])) 
		$format = array("cpos"=>true,"currency"=>"$","precision"=>2,"decimals"=>".","thousands" => ",");

	if (isset($format['indian'])) $number = indian_number($amount,$format);
	else $number = number_format($amount, $format['precision'], $format['decimals'], $format['thousands']);
	if ($format['cpos']) return $format['currency'].$number;
	else return $number.$format['currency'];
}


/**
 * Formats a number to telephone number style
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param int $num The number to format
 * @return string The formatted telephone number
 **/
function phone ($num) {
	if (empty($num)) return "";
	$num = preg_replace("/[A-Za-z\-\s\(\)]/","",$num);
	
	if (strlen($num) == 7) sscanf($num, "%3s%4s", $prefix, $exchange);
	if (strlen($num) == 10) sscanf($num, "%3s%3s%4s", $area, $prefix, $exchange);
	if (strlen($num) == 11) sscanf($num, "%1s%3s%3s%4s",$country, $area, $prefix, $exchange);
	//if (strlen($num) > 11) sscanf($num, "%3s%3s%4s%s", $area, $prefix, $exchange, $ext);
	
	$string = "";
	$string .= (isset($country))?"$country ":"";
	$string .= (isset($area))?"($area) ":"";
	$string .= (isset($prefix))?$prefix:"";
	$string .= (isset($exchange))?"-$exchange":"";
	$string .= (isset($ext))?" x$ext":"";
	return $string;

}

/**
 * Formats a numeric amount to a percentage using a specified format
 * 
 * Uses a format configuration array to specify how the amount needs to be
 * formatted.  When no format is specified, the currency format setting 
 * is used only paying attention to the decimal precision, decimal symbol and 
 * thousands separator.  If no setting is available, a default configuration 
 * is used (precision: 1) (decimal separator: .) (thousands separator: ,)
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param float $amount The amount to format
 * @param array $format A specific format for the number
 * @return string The formatted percentage
 **/
function percentage ($amount,$format=false) {
	global $Shopp;
	
	$locale = $Shopp->Settings->get('base_operations');
	if (!$format) {
		$format = $locale['currency']['format'];
		$format['precision'] = 0;
	}
	if (!$format) $format = array("precision"=>1,"decimals"=>".","thousands" => ",");
	if (isset($format['indian'])) return indian_number($amount,$format);
	return number_format(round($amount), $format['precision'], $format['decimals'], $format['thousands']).'%';
}

/**
 * Creates natural language amount of time from a specified timestamp to today
 *
 * The string includes the number of years, months, days, hours, minutes 
 * and even seconds e.g.: 1 year, 5 months, 29 days , 23 hours and 59 minutes
 *
 * @author Timothy Hatcher
 * @since 1.0
 * 
 * @param int $date The original timestamp
 * @return string The formatted time range
 **/
function readableTime($date, $long = false) {

	$secs = time() - $date;
	if (!$secs) return false;
	$i = 0; $j = 1;
	$desc = array(1 => 'second',
				  60 => 'minute',
				  3600 => 'hour',
				  86400 => 'day',

				  604800 => 'week',
				  2628000 => 'month',
				  31536000 => 'year');


	while (list($k,) = each($desc)) $breaks[] = $k;
	sort($breaks);

	while ($i < count($breaks) && $secs >= $breaks[$i]) $i++;
	$i--;
	$break = $breaks[$i];

	$val = intval($secs / $break);
	$retval = $val . ' ' . $desc[$break] . ($val>1?'s':'');

	if ($long && $i > 0) {
		$rest = $secs % $break;
		$break = $breaks[--$i];
		$rest = intval($rest/$break);

		if ($rest > 0) {
			$resttime = $rest.' '.$desc[$break].($rest > 1?'s':'');

			$retval .= ", $resttime";
		}
	}

	return $retval;
}

/**
 * Scans a formatted string to build a list of currency formatting settings
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param string $format A currency formatting string such as $#,###.##
 * @return array Formatting options list
 **/
function scan_money_format ($format) {
	$f = array(
		"cpos" => true,
		"currency" => "",
		"precision" => 0,
		"decimals" => "",
		"thousands" => ""
	);
	
	$ds = strpos($format,'#'); $de = strrpos($format,'#')+1;
	$df = substr($format,$ds,($de-$ds));

	if ($df == "#,##,###.##") $f['indian'] = true;
	
	$f['cpos'] = true;
	if ($de == strlen($format)) $f['currency'] = substr($format,0,$ds);
	else {
		$f['currency'] = substr($format,$de);
		$f['cpos'] = false;
	}

	$i = 0; $dd = 0;
	$dl = array();
	$sdl = "";
	$uniform = true;
	while($i < strlen($df)) {
		$c = substr($df,$i++,1);
		if ($c != "#") {
			if(empty($sdl)) $sdl = $c;
			else if($sdl != $c) $uniform = false;
			$dl[] = $c;
			$dd = 0;
		} else $dd++;
	}
	if(!$uniform) $f['precision'] = $dd;
	
	if (count($dl) > 1) {
		if ($dl[0] == "t") {
			$f['thousands'] = $dl[1];
			$f['precision'] = 0;
		}
		else {
			$f['decimals'] = $dl[count($dl)-1];
			$f['thousands'] = $dl[0];			
		}
	} else $f['decimals'] = $dl[0];

	return $f;
}

/** 
 * Sends an e-mail message in the format of a specified e-mail 
 * template ($template) file providing variable substitution 
 * for variables appearing in the template as a bracketed
 * [variable] with data from the coinciding $data['variable']
 * or $_POST['variable'] */

/**
 * Sends an email message based on a specified template file
 *
 * Sends an e-mail message in the format of a specified e-mail 
 * template file using variable substitution for variables appearing in 
 * the template as a bracketed [variable] with data from the 
 * provided data array or the super-global $_POST array
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param string $template Email template file path
 * @param array $data The data to populate the template with
 * @return boolean True on success, false on failure
 **/
function shopp_email ($template,$data=array()) {
	
	if (strpos($template,"\r\n") !== false) $f = explode("\r\n",$template);
	else {
		if (file_exists($template)) $f = file($template);
		else new ShoppError(__("Could not open the email template because the file does not exist or is not readable.","Shopp"),'email_template',SHOPP_ADMIN_ERR,array('template'=>$template));
	}

	$replacements = array(
		"$" => "\\\$",		// Treat $ signs as literals
		"€" => "&euro;",	// Fix euro symbols
		"¥" => "&yen;",		// Fix yen symbols
		"£" => "&pound;",	// Fix pound symbols
		"¤" => "&curren;"	// Fix generic currency symbols
	);

	$debug = false;
	$in_body = false;
	$headers = "";
	$message = "";
	$protected = array("from","to","subject","cc","bcc");
	while ( list($linenum,$line) = each($f) ) {
		$line = rtrim($line);
		// Data parse
		if ( preg_match_all("/\[(.+?)\]/",$line,$labels,PREG_SET_ORDER) ) {
			while ( list($i,$label) = each($labels) ) {
				$code = $label[1];
				if (empty($data)) $string = $_POST[$code];
				else $string = $data[$code];

				$string = str_replace(array_keys($replacements),array_values($replacements),$string); 

				if (isset($string) && !is_array($string)) $line = preg_replace("/\[".$code."\]/",$string,$line);
			}
		}

		// Header parse
		if ( preg_match("/^(.+?):\s(.+)$/",$line,$found) && !$in_body ) {
			$header = $found[1];
			$string = $found[2];
			if (in_array(strtolower($header),$protected)) // Protect against header injection
				$string = str_replace(array("\r","\n"),"",urldecode($string));
			if ( strtolower($header) == "to" ) $to = $string;
			else if ( strtolower($header) == "subject" ) $subject = $string;
			else $headers .= $line."\n";
		}
		
		// Catches the first blank line to begin capturing message body
		if ( empty($line) ) $in_body = true;
		if ( $in_body ) $message .= $line."\n";
	}

	if (!$debug) return wp_mail($to,$subject,$message,$headers);
	else {
		echo "<pre>";
		echo "To: $to\n";
		echo "Subject: $subject\n\n";
		echo "Message:\n$message\n";
		echo "Headers:\n";
		print_r($headers);
		echo "<pre>";
		exit();		
	}
}

/**
 * Generates RSS markup in XML from a set of provided data
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param array $data The data to populate the RSS feed with
 * @return string The RSS markup
 **/
function shopp_rss ($data) {
	// RSS filters
	add_filter('shopp_rss_description','convert_chars');
	add_filter('shopp_rss_description','ent2ncr');

	$xmlns = '';
	if (is_array($data['xmlns']))
		foreach ($data['xmlns'] as $key => $value)
			$xmlns .= 'xmlns:'.$key.'="'.$value.'" ';

	$xml = "<?xml version=\"1.0\""." encoding=\"utf-8\"?>\n";
	$xml .= "<rss version=\"2.0\" $xmlns>\n";
	$xml .= "<channel>\n";

	$xml .= '<atom:link href="'.htmlentities($data['link']).'" rel="self" type="application/rss+xml" />'."\n";
	$xml .= "<title>".$data['title']."</title>\n";
	$xml .= "<description>".$data['description']."</description>\n";
	$xml .= "<link>".htmlentities($data['link'])."</link>\n";
	$xml .= "<language>en-us</language>\n";
	$xml .= "<copyright>Copyright ".date('Y').", ".$data['sitename']."</copyright>\n";
	
	if (is_array($data['items'])) {
		foreach($data['items'] as $item) {
			$xml .= "<item>\n";
			foreach ($item as $key => $value) {
				$attrs = '';
				if (is_array($value)) {
					$data = $value;
					$value = '';
					foreach ($data as $name => $content) {
						if (empty($name)) $value = $content;
						else $attrs .= ' '.$name.'="'.$content.'"';
					}
				}
				if (!empty($value)) $xml .= "<$key$attrs>$value</$key>\n";
				else $xml .= "<$key$attrs />\n";
			}
			$xml .= "</item>\n";
		}
	}
	
	$xml .= "</channel>\n";
	$xml .= "</rss>\n";
	
	return $xml;
}

/**
 * Outputs a parsed catalog CSS file to the browser
 *
 * The catalog CSS file provides core styling used to initialize foundational
 * elements of Shopp catalog layout. Some aspects for the CSS can only be
 * known by loading settings and using them to generate dynamic dimensions
 * for some elements (primarily the product gallery).
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @return void
 **/
function shopp_catalog_css () {
	$db =& DB::get();
	$table = DatabaseObject::tablename(Settings::$table);
	$settings = $db->query("SELECT name,value FROM $table WHERE name='gallery_thumbnail_width' OR name='row_products' OR name='row_products' OR name='gallery_small_width' OR name='gallery_small_height'",AS_ARRAY);
	foreach ($settings as $setting) ${$setting->name} = $setting->value;

	$pluginuri = WP_PLUGIN_URL."/".basename(dirname(dirname(__FILE__)))."/";
	$pluginuri = force_ssl($pluginuri);

	if (!isset($row_products)) $row_products = 3;
	$products_per_row = floor((100/$row_products));
	
	ob_start();
	include("ui/styles/catalog.css");
	$file = ob_get_contents();
	ob_end_clean();
	header ("Content-type: text/css");
	header ("Content-Disposition: inline; filename=catalog.css"); 
	header ("Content-Description: Delivered by WordPress/Shopp ".SHOPP_VERSION);
	header ("Content-length: ".strlen($file)); 
	echo $file;
	exit();
}

/**
 * Outputs a parsed JS file to provide Shopp settings to the JS environment
 *
 * The JavaScript file is parsed by PHP in order to load Shopp settings 
 * from the database into the browser's JavaScript environment.
 * 
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @return void
 **/
function shopp_settings_js ($dir="shopp") {
	$db =& DB::get();
	$table = DatabaseObject::tablename(Settings::$table);
	$settings = $db->query("SELECT name,value FROM $table WHERE name='base_operations'",AS_ARRAY);
	foreach ($settings as $setting) ${$setting->name} = $setting->value;
	$base_operations = unserialize($base_operations);
	
	$path = array(PLUGINDIR,$dir,'lang');
	load_plugin_textdomain('Shopp', sanitize_path(join('/',$path)));
	
	ob_start();
	include("ui/behaviors/settings.js");
	$file = ob_get_contents();
	ob_end_clean();
	header ("Content-type: text/javascript");
	header ("Content-Disposition: inline; filename=settings.js"); 
	header ("Content-Description: Delivered by WordPress/Shopp ".SHOPP_VERSION);
	header ("Content-length: ".strlen($file)); 
	echo $file;
	exit();
}

/**
 * Checks for prerequisite technologies needed for Shopp
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @return boolean Returns true if all technologies are available
 **/
function shopp_prereqs () {
	$errors = array();
	// Check PHP version, this won't appear much since syntax errors in earlier
	// PHP releases will cause this code to never be executed
	if (!version_compare(PHP_VERSION, '5.0','>=')) 
		$errors[] = __("Shopp requires PHP version 5.0+.  You are using PHP version ").PHP_VERSION;

	if (version_compare(PHP_VERSION, '5.1.3','==')) 
		$errors[] = __("Shopp will not work with PHP version 5.1.3 because of a critical bug in complex POST data structures.  Please upgrade PHP to version 5.1.4 or higher.");
		
	// Check WordPress version
	if (!version_compare(get_bloginfo('version'),'2.7','>='))
		$errors[] = __("Shopp requires WordPress version 2.7+.  You are using WordPress version ").get_bloginfo('version');
	
	// Check for cURL
	if( !function_exists("curl_init") &&
	      !function_exists("curl_setopt") &&
	      !function_exists("curl_exec") &&
	      !function_exists("curl_close") ) $errors[] = __("Shopp requires the cURL library for processing transactions securely. Your web hosting environment does not currently have cURL installed (or built into PHP).");
	
	// Check for GD
	if (!function_exists("gd_info")) $errors[] = __("Shopp requires the GD image library with JPEG support for generating gallery and thumbnail images.  Your web hosting environment does not currently have GD installed (or built into PHP).");
	else {
		$gd = gd_info();
		if (!isset($gd['JPG Support']) && !isset($gd['JPEG Support'])) $errors[] = __("Shopp requires JPEG support in the GD image library.  Your web hosting environment does not currently have a version of GD installed that has JPEG support.");
	}
	
	if (!empty($errors)) {
		$string .= '<style type="text/css">body { font: 13px/1 "Lucida Grande", "Lucida Sans Unicode", Tahoma, Verdana, sans-serif; } p { margin: 10px; }</style>';
		
		foreach ($errors as $error) $string .= "<p>$error</p>";

		$string .= '<p>'.__('Sorry! You will not be able to use Shopp.  For more information, see the <a href="http://docs.shopplugin.net/Installation" target="_blank">online Shopp documentation.</a>').'</p>';
		
		trigger_error($string,E_USER_ERROR);
		exit();
	}
	return true;
}

/**
 * Returns the platform appropriate page name for Shopp internal pages
 *
 * IIS rewriting requires including index.php as part of the page
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param string $page The normal page name
 * @return string The modified page name
 **/
function shopp_pagename ($page) {
	global $is_IIS;
	$prefix = strpos($page,"index.php/");
	if ($prefix !== false) return substr($page,$prefix+10);
	else return $page;
}

/**
 * Redirects the browser to a specified URL
 *
 * A wrapper for the wp_redirect function
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param string $uri The URI to redirect to
 * @param boolean $exit (optional) Exit immediately after the redirect (defaults to true, set to false to override)
 * @return void
 **/
function shopp_redirect ($uri,$exit=true) {
	if (class_exists('ShoppError'))	new ShoppError('Redirecting to: '.$uri,'shopp_redirect',SHOPP_DEBUG_ERR);
	wp_redirect($uri);
	if ($exit) exit();
}

/**
 * Determines the current taxrate from the store settings and provided options
 *
 * Contextually works out if the tax rate applies or not based on storefront
 * settings and the provided override options 
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param string $override (optional) Specifies whether to override the default taxrate behavior
 * @param string $taxprice (optional) Supports a secondary contextual override
 * @return float The determined tax rate
 **/
function shopp_taxrate ($override=null,$taxprice=true) {
	global $Shopp;
	$rated = false;
	$taxrate = 0;
	$base = $Shopp->Settings->get('base_operations');

	if ($base['vat']) $rated = true;
	if (!is_null($override)) $rated = (value_is_true($override));
	if (!value_is_true($taxprice)) $rated = false;

	if ($rated) $taxrate = $Shopp->Cart->taxrate();
	return $taxrate;
}

/**
 * Recursively sorts a heirarchical tree of data
 *
 * @param array $item The item data to be sorted
 * @param int $parent (internal) The parent item of the current iteration
 * @param int $key (internal) The identified index of the parent item in the current iteration
 * @param int $depth (internal) The number of the nested depth in the current iteration
 * @return array The sorted tree of data
 * @author Jonathan Davis
 **/
function sort_tree ($items,$parent=0,$key=-1,$depth=-1) {
	$depth++;
	$result = array();
	if ($items) { 
		foreach ($items as $item) {
			if ($item->parent == $parent) {
				$item->parentkey = $key;
				$item->depth = $depth;
				$result[] = $item;
				$children = sort_tree($items, $item->id, count($result)-1, $depth);
				$result = array_merge($result,$children); // Add children in as they are found
			}
		}
	}
	$depth--;
	return $result;
}

/**
 * Generates a representation of the current state of an object structure
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param object $object The object to display
 * @return string The object structure 
 **/
function _object_r ($object) {
	global $Shopp;
	ob_start();
	print_r($object);
	$result = ob_get_contents();
	ob_end_clean();
	return $result;
}

/**
 * Converts natural language text to boolean values
 * 
 * Used primarily for handling boolean text provided in shopp() tag options.
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param string $value The natural language value
 * @return boolean The boolean value of the provided text
 **/
function value_is_true ($value) {
	switch (strtolower($value)) {
		case "yes": case "true": case "1": case "on": return true;
		default: return false;
	}
}

/**
 * Determines if a specified type is a valid HTML input element
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param string $type The HTML element type name
 * @return boolean True if valid, false if not
 **/
function valid_input ($type) {
	$inputs = array("text","hidden","checkbox","radio","button","submit");
	if (in_array($type,$inputs) !== false) return true;
	return false;
}

/**
 * Converts timestamps to formatted localized date/time strings
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param string $format A date() format string
 * @param int $timestamp (optional) The timestamp to be formatted (defaults to current timestamp)
 * @return string The formatted localized date/time
 **/
function _d($format,$timestamp=false) {
	$tokens = array(
		'D' => array('Mon' => __('Mon','Shopp'),'Tue' => __('Tue','Shopp'),
					'Wed' => __('Wed','Shopp'),'Thu' => __('Thu','Shopp'),
					'Fri' => __('Fri','Shopp'),'Sat' => __('Sat','Shopp'),
					'Sun' => __('Sun','Shopp')),
		'l' => array('Monday' => __('Monday','Shopp'),'Tuesday' => __('Tuesday','Shopp'),
					'Wednesday' => __('Wednesday','Shopp'),'Thursday' => __('Thursday','Shopp'),
					'Friday' => __('Friday','Shopp'),'Saturday' => __('Saturday','Shopp'),
					'Sunday' => __('Sunday','Shopp')),
		'F' => array('January' => __('January','Shopp'),'February' => __('February','Shopp'),
					'March' => __('March','Shopp'),'April' => __('April','Shopp'),
					'May' => __('May','Shopp'),'June' => __('June','Shopp'),
					'July' => __('July','Shopp'),'August' => __('August','Shopp'),
					'September' => __('September','Shopp'),'October' => __('October','Shopp'),
					'November' => __('November','Shopp'),'December' => __('December','Shopp')),
		'M' => array('Jan' => __('Jan','Shopp'),'Feb' => __('Feb','Shopp'),
					'Mar' => __('Mar','Shopp'),'Apr' => __('Apr','Shopp'),
					'May' => __('May','Shopp'),'Jun' => __('Jun','Shopp'),
					'Jul' => __('Jul','Shopp'),'Aug' => __('Aug','Shopp'),
					'Sep' => __('Sep','Shopp'),'Oct' => __('Oct','Shopp'),
					'Nov' => __('Nov','Shopp'),'Dec' => __('Dec','Shopp'))
	);

	if (!$timestamp) $date = date($format);
	else $date = date($format,$timestamp);

	foreach ($tokens as $token => $strings) {
		if ($pos = strpos($format,$token) === false) continue;
		$string = (!$timestamp)?date($token):date($token,$timestamp);
		$date = str_replace($string,$strings[$string],$date);
	}
	return $date;
}

/**
 * Builds an SQL query fragment for key/value pair assignments
 *
 * Generates a string concatenating the keys and corresponding values
 * for INSERT or UPDATE queries.
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param array $request A list of key/value pairs
 * @return string The query fragment
 **/
function build_query_request ($request=array()) {
	$query = "";
	foreach ($request as $name => $value) {
		if (strlen($query) > 0) $query .= "&";
		$query .= "$name=$value";
	}
	return $query;
}

/**
 * Converts bytes to the largest applicable human readable unit
 *
 * Supports up to petabyte sizes
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param int $bytes The number of bytes
 * @return string The formatted unit size
 **/
function readableFileSize($bytes,$precision=1) {
	$units = array(__("bytes","Shopp"),"KB","MB","GB","TB","PB");
	$sized = $bytes*1;
	if ($sized == 0) return $sized;
	$unit = 0;
	while ($sized > 1024 && ++$unit) $sized = $sized/1024;
	return round($sized,$precision)." ".$units[$unit];
}

/**
 * Copies the builtin template files to the active WordPress theme
 *
 * Handles copying the builting template files to the shopp/ directory of 
 * the currently active WordPress theme.  Strips out the header comment 
 * block which includes a warning about editing the builtin templates.
 *
 * @author Jonathan Davis
 * @since 1.0
 * 
 * @param string $src The source directory for the builtin template files
 * @param string $target The target directory in the active theme
 * @return void
 **/
function copy_shopp_templates ($src,$target) {
	$builtin = array_filter(scandir($src),"filter_dotfiles");
	foreach ($builtin as $template) {
		$target_file = $target.'/'.$template;
		if (!file_exists($target_file)) {
			$src_file = file_get_contents($src.'/'.$template);
			$file = fopen($target_file,'w');
			$src_file = preg_replace('/^<\?php\s\/\*\*\s+(.*?\s)*?\*\*\/\s\?>\s/','',$src_file);
			fwrite($file,$src_file);
			fclose($file);			
			chmod($target_file,0666);
		}
	}
}

// TODO: Clean up for Controllers

function settings_get_gateways () {
	global $Shopp;
	$gateway_path = $Shopp->path.'/'."gateways";
	
	$gateways = array();
	$gwfiles = array();
	find_files(".php",$gateway_path,$gateway_path,$gwfiles);
	if (empty($gwfiles)) return $gwfiles;
	
	foreach ($gwfiles as $file) {
		if (! is_readable($gateway_path.$file)) continue;
		if (! $gateway = scan_gateway_meta($gateway_path.$file)) continue;
		$gateways[$file] = $gateway;
	}

	return $gateways;
}

function validate_addons () {
	$addons = array();

	$gateway_path = $this->basepath.'/'."gateways";		
	find_files(".php",$gateway_path,$gateway_path,$gateways);
	foreach ($gateways as $file) {
		if (in_array(basename($file),$this->coremods)) continue;
		$addons[] = md5_file($gateway_path.$file);
	}

	$shipping_path = $this->basepath.'/'."shipping";
	find_files(".php",$shipping_path,$shipping_path,$shipmods);
	foreach ($shipmods as $file) {
		if (in_array(basename($file),$this->coremods)) continue;
		$addons[] = md5_file($shipping_path.$file);
	}
	return $addons;
}

function scan_gateway_meta ($file) {
	global $Shopp;
	$metadata = array();
	
	$meta = get_filemeta($file);

	if ($meta) {
		$lines = explode("\n",substr($meta,1));
		foreach($lines as $line) {
			preg_match("/^(?:[\s\*]*?\b([^@\*\/]*))/",$line,$match);
			if (!empty($match[1])) $data[] = $match[1];
			preg_match("/^(?:[\s\*]*?@([^\*\/]+?)\s(.+))/",$line,$match);
			if (!empty($match[1]) && !empty($match[2])) $tags[$match[1]] = $match[2];
		
		}
		$gateway = new stdClass();
		$gateway->file = $file;
		$gateway->name = $data[0];
		$gateway->description = (!empty($data[1]))?$data[1]:"";
		$gateway->tags = $tags;
		$gateway->activated = false;
		if ($Shopp->Settings->get('payment_gateway') == $file) $module->activated = true;
		return $gateway;
	}
	return false;
}

// TODO END: Clean up for Controllers

if(!function_exists('sanitize_path')){
	/**
	 * Normalizes path separators to always use forward-slashes
	 *
	 * PHP path functions on Windows-based systems will return paths with 
	 * backslashes as the directory separator.  This function is used to 
	 * ensure we are always working with forward-slash paths
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param string $path The path to clean up
	 * @return string $path The forward-slash path
	 **/
	function sanitize_path ($path) {
		return str_replace('\\', '/', $path);
	}
}

/**
 * A lightweight FTP-client for handling Shopp upgrades
 *
 * @author Jonathan Davis
 * @since 1.0
 * @package shopp
 **/
class FTPClient {
	var $connected = false;
	var $log = array();
	var $remapped = false;
	
	/**
	 * Sets up a connection to an FTP server
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param string $host The hostname of the server to connect to
	 * @param string $user The user name to authenticate with
	 * @param string $password The password to authenticate with
	 * @return boolean True when connected, false on failure
	 **/
	function __construct ($host, $user, $password) {
		$this->connect($host, $user, $password);
		if ($this->connected) ftp_pasv($this->connection,true);
		else return false;
		return true;
	}
	
	/**
	 * Connects to the FTP server
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param string $host The hostname of the server to connect to
	 * @param string $user The user name to authenticate with
	 * @param string $password The password to authenticate with
	 * @return boolean True when connected, false on failure
	 **/
	function connect($host, $user, $password) {
		$this->connection = @ftp_connect($host,0,20);
		if (!$this->connection) return false;
		$this->connected = @ftp_login($this->connection,$user,$password);
		if (!$this->connected) return false;
		return true;
	}
	
	/**
	 * Recursively copies files from a working path to the remote FTP path
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param string $path The path to the working files
	 * @param string $remote The remote FTP path
	 * @return string A log of actions
	 **/
	function update ($path,$remote) {
		if (is_dir($path)){
			$path = trailingslashit($path);
			// $this->log[] = "The source path is $path";
			$files = scandir($path);	
			$remote = trailingslashit($remote);
			// $this->log[] = "The destination path is $remote";
		} else {
			$files = array(basename($path));
			$path = trailingslashit(dirname($path));
			// $this->log[] = "The source path is $path";
			$remote = trailingslashit(dirname($remote));
			// $this->log[] = "The destination path is $remote";
		}
		
		if (!$this->remapped) $remote = $this->remappath($remote);
		// $this->log[] = "The remapped destination path is $remote";
		
		$excludes = array(".","..");
		foreach ((array)$files as $file) {
			if (in_array($file,$excludes)) continue;
			if (is_dir($path.$file)) {
				if (!@ftp_chdir($this->connection,$remote.$file)) 
					$this->mkdir($remote.$file);
				$this->update($path.$file,$remote.$file);				
			} else $this->put($path.$file,$remote.$file);
		}
		return $this->log;
	}
	
	/**
	 * Delete the target file, recursively delete directories
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param string $file The file path on the remote system
	 * @return boolean True on success, false on failure
	 **/
	function delete ($file) {
		if (empty($file)) return false;
		if (!$this->isdir($file)) return @ftp_delete($this->connection, $file);
		$files = $this->scan($file);
		if (!empty($files)) foreach ($files as $target) $this->delete($target);
		return @ftp_rmdir($this->connection, $file);
	}
	
	/**
	 * Copies the target file to the remote location
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param string $file Path to the source file on the local machine
	 * @param string $remote Path to the remote target location
	 * @return boolean True on success, false on failure
	 **/
	function put ($file,$remote) {
		if (@ftp_put($this->connection,$remote,$file,FTP_BINARY))
			return @ftp_chmod($this->connection, 0644, $remote);
		else $this->log[] = "Could not move the file from $file to $remote";
	}
	
	/**
	 * Makes a new remote directory with correct permissions
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param string $path The remote path for the new directory
	 * @return boolean True on success, false on failure
	 **/
	function mkdir ($path) {
		if (@ftp_mkdir($this->connection,$path)) 
			@ftp_chmod($this->connection,0755,$path);
		else $this->log[] = "Could not create the directory $path";
	}
	
	/**
	 * Gets the current directory on the remote system
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return string The current working directory path
	 **/
	function pwd () {
		return ftp_pwd($this->connection);
	}
	
	/**
	 * scan()
	 * Gets a list of files in a directory/current directory */
	/**
	 * Gets a list of files in a given directory or the current directory
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param string $path (optional) The path to look for files (defaults to the current working directory)
	 * @return array A list of files
	 **/
	function scan ($path=false) {
		if (!$path) $path = $this->pwd();
		return @ftp_nlist($this->connection,$path);
	}
	
	/**
	 * Determines if the file is a directory or a file
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param string $file (optional) The file path to check
	 * @return boolean True if the path is a directory, false if it is a file
	 **/
	function isdir ($file=false) {
		if (!$file) $file = $this->pwd();
	    if (@ftp_size($this->connection, $file) == '-1')
	        return true; // Directory
	    else return false; // File
	}
	
	/**
	 * Remap a path to the real root path of the remote server
	 *
	 * Remaps the path to a real full-path taking into account root jails
	 * common in most FTP setups.
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param string $path The path to remap
	 * @return string The real full path
	 **/
	function remappath ($path) {
		$files = $this->scan();
		foreach ($files as $file) {
			$filepath = trailingslashit(sanitize_path($this->pwd())).basename($file);
			if (!$this->isdir($filepath)) continue;
			$index = strrpos($path,$filepath);
			if ($index !== false) {
				$this->remapped = true;
				return substr($path,$index);
			}
		}
		// No remapping needed
		return $path;
	}

}

if (function_exists('date_default_timezone_set') && get_option('timezone_string')) 
	date_default_timezone_set(get_option('timezone_string'));

// Run pre-req check when this file is included
shopp_prereqs();

?>