<?php

/* functions.php
 * Library of global utility functions */

/**
 * Adds table schema to the install query. */
function install_schema ($queries) {
	$db =& DB::get();
	$queries = explode(";\n", $queries);
	array_pop($queries);
	foreach ($queries as $query) if (!empty($query)) $db->query($query);
	return true;
}

/**
 * Calculate the time based on a repeating interval in a given 
 * month and year. Ex: Fourth Thursday in November (Thanksgiving). */
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
 * Converts a datetime value from a MySQL datetime format to a Unix timestamp. */
function mktimestamp ($datetime) {
	$h = $mn = $s = 0;
	list($Y, $M, $D, $h, $mn, $s) = sscanf($datetime,"%d-%d-%d %d:%d:%d");
	return mktime($h, $mn, $s, $M, $D, $Y);
}

/**
 * Converts a Unix timestamp value to a datetime format suitable for entry in a
 * MySQL record. */
function mkdatetime ($timestamp) {
	return date("Y-m-d H:i:s",$timestamp);
}

/**
 * Returns the corresponding 24-hour $hour based on a 12-hour based $hour
 * and the AM (Ante Meridiem) / PM (Post Meridiem) $meridiem. */
function mk24hour ($hour, $meridiem) {
	if ($hour < 12 && $meridiem == "PM") return $hour + 12;
	if ($hour == 12 && $meridiem == "AM") return 0;
	return $hour;
}

/**
 * Returns a string of the number of years, months, days, hours, 
 * minutes and even seconds from a specified date ($date). */
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

function duration ($start,$end) {
	return ceil(($end - $start) / 86400);
}

/**
 * Get rid of evil magic quotes!! */
function no_magic_quotes() {
	if (get_magic_quotes_gpc()) {
		if (!empty($_GET))    $_GET    = strip_magic_quotes($_GET);
		if (!empty($_POST))   $_POST   = strip_magic_quotes($_POST);
		if (!empty($_COOKIE)) $_COOKIE = strip_magic_quotes($_COOKIE);
	}
}

/**
 * Removes any extra quotes added to a given string ($arr)
 * by the built-in PHP GPC (Get, Post, Cookie) magic quote
 * functions. */
function strip_magic_quotes ($arr) {
	foreach ($arr as $k => $v) {
		if (is_array($v)) $arr[$k] = strip_magic_quotes($v);
		else $arr[$k] = stripslashes($v);
	}
	return $arr;
}

/** 
 * Sends an e-mail message in the format of a specified e-mail 
 * template ($template) file providing variable substitution 
 * for variables appearing in the template as a bracketed
 * [variable] with data from the coinciding $_POST['variable']; */
function send_email ($template) {
	
	if ( file_exists($template) ) $f = file($template);
	else $msg = "Could not open the template file because the file does not exist or is not readable.";
	if ( isset($msg) ) user_error($msg,WARNING);

	$in_body = false;
	$headers = "";
	$message = "";
	$protected = array("from","to","subject","cc","bcc");
	while ( list($linenum,$line) = each($f) ) {
		// Data parse
		if ( preg_match_all("/\[(.+?)\]/",$line,$labels,PREG_SET_ORDER) ) {
			while ( list($i,$label) = each($labels) ) {
				if (in_array(strtolower($label[1]),$protected)) // Protect against header injection
					$_POST[$label[1]] = str_replace(array("\r","\n"),"",urldecode($_POST[$label[1]]));  
				if (isset($_POST[$label[1]]) && ! is_array($_POST[$label[1]])) $line = preg_replace("/\[".$label[1]."\]/",$_POST[$label[1]],$line);
			}
		}

		// Header parse
		if ( preg_match("/^(.+?):\s(.+)\n$/",$line,$header_data) && ! $in_body ) {
			if ( strtolower($header_data[1]) == "to" ) $to = $header_data[2];
			else if ( strtolower($header_data[1]) == "subject" ) $subject = $header_data[2];
			else $headers .= $line;
		}
		
		// Catches the first blank line to begin capturing message body
		if ( $line == "\n" ) $in_body = true;
		if ( $in_body ) $message .= $line;
	}

	mail($to,$subject,$message,$headers);
	/* -- DEBUG CODE -- */
	//echo "TO: $to<BR>SUBJECT: $subject<BR>MESSAGE:<BR>$message<BR><BR>HEADERS:<BR>$headers";
	//exit();
}

/**
 * Generates an RSS-compliant string from an associative 
 * array ($data) with a specific RSS-structure. */
function build_rss ($data) {
	$xml = "";
	$xml .= "<?xml version=\"1.0\""."?".">\n";
	$xml .= "<rss version=\"2.0\">\n";
	$xml .= "<channel>\n";
	
	$xml .= "<title>".$data['title']."</title>\n";
	$xml .= "<description>".$data['description']."</description>\n";
	$xml .= "<link>".$data['link']."</link>\n";
	$xml .= "<language>en-us</language>\n";
	$xml .= "<copyright>Copyright ".date('Y').", gochampaign.com</copyright>\n";
	
	foreach($data['items'] as $item) {
		$xml .= "<item>\n";
		$xml .= "<title>".$item['title']."</title>\n";
		$xml .= "<description>".$item['description']."</description>\n";
		$xml .= "<link>".$item['link']."</link>\n";
		$xml .= "<pubDate>".$item['pubDate']."</pubDate>\n";
		$xml .= "</item>\n";
	}
	
	$xml .= "</channel>\n";
	$xml .= "</rss>\n";
	
	return $xml;
}

/**
 * Formats a number into a standardized telephone number format */
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
 * Find and mark-out inappropriate language */
function language_censors ($text,$blackout="&bull;") {
	$CENSORED_WORDS = array(
		"/(\s)(shit|sh1t|sh!t|\*shit\*)/ie",
		"/(\s)(motherfucker|fucked|fucker|fucking|fucks|fuck\w*?|phuck\w*|fuk\w*?|\*fuck\w*?\*)/ie",
		"/(\s)(bitchy|bitch|\*bitch\*)/ie",
		"/(\s)(dumbass|asshole|ass\s|fatass|jackass|asses\s)/ie",
		"/(\s)(cuntrag|cunt\w*?|twat|pussy|dyke|dike|dooche\w*?|douche\w*?)/ie",
		"/(\s)(foreskin|dick|cock|c0ck|cum\s|jizz)/ie",
		"/(\s)(wank|masturbate|masturbater|masterbation|masterbating)/ie",
		"/(\s)(tit\b|titt\w*?|boob\w*?|b00b)/ie",
		"/(\s)(chink|nigger|nigga|ngr|spic|gook|injun|fag|faggot|queer)\s/ie",
		"/(\s)(Goddamn|God\sdamn)/ie",
	);
	
	$text = preg_replace($CENSORED_WORDS,"('\\1').str_repeat('$blackout',strlen('\\2'))", $text);
	return $text;
}

/**
 * Determines if the current client is a known web crawler bot */
function is_robot() {
	$bots = array("Googlebot","TeomaAgent","Zyborg","Gulliver","Architext spider","FAST-WebCrawler","Slurp","Ask Jeeves","ia_archiver","Scooter","Mercator","crawler@fast","Crawler","InfoSeek sidewinder","Lycos_Spider_(T-Rex)","Fluffy the Spider","Ultraseek","MantraAgent","Moget","MuscatFerret","VoilaBot","Sleek Spider","KIT_Fireball","WebCrawler");
	foreach($bots as $bot) {
		if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']),strtolower($bot))) return true;
	}
	return false;
}

/**
 * Generates JavaScript object(s) for AJAX responses from PHP data */
function json ($data) {
	$string = "";
	if (is_array($data)) {
		$objects = "";
		foreach($data as $element) {
			if (empty($objects)) $objects = json_object($element);
			else $objects .= ", ".json_object($element);
		}
		
		$string = "{\"results\":[".$objects."]}";
	} elseif (is_object($data)) {
		$string = json_object($data);
	}
	echo $string;
}

/**
 * Generates a JavaScript object for AJAX responses from a PHP object */
function json_object ($object) {
	$output = "{";
	$string = "";
	foreach (get_object_vars($object) as $property => $value) {
		if ($property[0] != "_") {
			if (is_int($value)) $pair = "\"$property\":$value";
			else $pair = "\"$property\":\"$value\"";

			if (empty($string)) $string = $pair;
			else $string .= ", ".$pair;
		}
	}
	$output .= $string;
	$output .= "}";
	return $output;
}

/**
 * parse_xml
 * Parses a string of XML data into a organizable data structure */
function parse_xml ($xml) {

	$parser = xml_parser_create();
	xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
	xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
	xml_parse_into_struct($parser, $xml, $tags);
	xml_parser_free($parser);

	$elements = array();	// the currently filling [child] XML element list
	$stack = array();		// our worskspace stack
	foreach ($tags as $tag) {
		$index = count($elements);
		if ($tag['type'] == "complete" || $tag['type'] == "open") {
			$elements[$index] = new XML();
			$elements[$index]->name = $tag['tag'];
			$elements[$index]->attributes = (isset($tag['attributes']))?$tag['attributes']:'';
			$elements[$index]->content = (isset($tag['value']))?$tag['value']:'';
			if ($tag['type'] == "open") {  // push
				$elements[$index]->children = array();
				$stack[count($stack)] = &$elements;
				$elements = &$elements[$index]->children;
			}
		}
		if ($tag['type'] == "close") {  // remove close tag elements from the list
			$elements = &$stack[count($stack) - 1];
			unset($stack[count($stack) - 1]);
		}
	}
	return $elements[0];  // the single top-level element
}

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
 * Recursively searches directories and one-level deep of 
 * sub-directories for files with a specific extension
 * NOTE: Files are saved to the $found parameter, 
 * an array passed by reference, not a returned value */
function find_files ($extension, $directory, $root, &$found) {
	if (is_dir($directory)) {
		$Directory = @dir($directory);
		if ($Directory) {
			while (( $file = $Directory->read() ) !== false) {
				if (substr($file, 0, 1) == ".") continue; 					 // Ignore .dot files
				if (is_dir("$directory/$file") && $directory == $root)		 // Scan one deep more than root
					find_files($extension,"$directory/$file",$root, $found); // but avoid recursive scans
				if (substr($file,strlen($extension)*-1) == $extension)
					$found[] = substr($directory,strlen($root))."/$file";	 // Add the file to the found list
			}
			return true;
		}
	}
	return false;
}

/**
 * List files and directories inside the specified path */
if(!function_exists('scandir')) {
	function scandir($dir, $sortorder = 0) {
		if(is_dir($dir) && $dirlist = @opendir($dir)) {
			while(($file = readdir($dirlist)) !== false) {
				$files[] = $file;
			}
			closedir($dirlist);
			($sortorder == 0) ? asort($files) : rsort($files);
			return $files;
		} else return false;
	}
}

/**
 * Checks an object for a declared property
 * if() checks to see if the function is already available (as in PHP 5) */
if (!function_exists('property_exists')) {
	function property_exists($object, $property) {
		return array_key_exists($property, get_object_vars($object));
	}
}

?>
