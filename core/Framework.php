<?php
/**
 * Framework
 *
 * Library of abstract design pattern templates
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, May  5, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package
 * @since 1.0
 * @subpackage framework
 **/


/**
 * Utility class of Shopp helper functions
 *
 * @author Jonathan Davis
 * @since
 * @package
 **/
class ShoppKit {

	/**
	 * Wraps mark-up in a #shopp container, if needed
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $string The content markup to be wrapped
	 * @param array $classes CSS classes to add to the container
	 * @return string The wrapped markup
	 **/
	function shoppdiv ($string) {

		$classes = array();

		$views = array('list','grid');
		$view = shopp_setting('default_catalog_view');
		if (empty($view)) $view = 'grid';

		// Handle catalog view style cookie preference
		if (isset($_COOKIE['shopp_catalog_view'])) $view = $_COOKIE['shopp_catalog_view'];
		if (in_array($view,$views)) $classes[] = $view;

		// Add collection slug
		$Collection = ShoppCollection();
		if (!empty($Collection))
			if ($category = shopp('collection','get-slug')) $classes[] = $category;

		// Add product id & slug classes
		$Product = ShoppProduct();
		if (!empty($Product)) {
			if ($productid = shopp('product','get-id')) $classes[] = 'product-'.$productid;
			if ($product = shopp('product','get-slug')) $classes[] = $product;
		}

		$classes = apply_filters('shopp_content_container_classes',$classes);
		$classes = esc_attr(join(' ',$classes));

		if (false === strpos($string,'<div id="shopp"'))
			return '<div id="shopp"'.(!empty($classes)?' class="'.$classes.'"':'').'>'.$string.'</div>';
		return $string;
	}

	function shopp_daytimes () {
		$args = func_get_args();
		$periods = array("h"=>3600,"d"=>86400,"w"=>604800,"m"=>2592000);

		$total = 0;
		foreach ($args as $timeframe) {
			if (empty($timeframe)) continue;
			list($i,$p) = sscanf($timeframe,'%d%s');
			$total += $i*$periods[$p];
		}
		return ceil($total/$periods['d']).'d';
	}

	/**
	 * Sets the default timezone based on the WordPress option (if available)
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function shopp_default_timezone () {
		if (function_exists('date_default_timezone_set'))
			date_default_timezone_set('UTC');
	}

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

		$debug = false;
		$in_body = false;
		$headers = array();
		$message = '';
		$to = '';
		$subject = '';
		$protected = array('from','to','subject','cc','bcc');
		$replacements = array(
			"$" => "\\\$",		// Treat $ signs as literals
			"€" => "&euro;",	// Fix euro symbols
			"¥" => "&yen;",		// Fix yen symbols
			"£" => "&pound;",	// Fix pound symbols
			"¤" => "&curren;"	// Fix generic currency symbols
		);

		if (false == strpos($template,"\n") && file_exists($template)) {
			$templatefile = $template;
			// Include to parse the PHP and Theme API tags
			ob_start();
			include($templatefile);
			$template = ob_get_contents();
			ob_end_clean();

			if (empty($template))
				return new ShoppError(__('Could not open the email template because the file does not exist or is not readable.','Shopp'),'email_template',SHOPP_ADMIN_ERR,array('template'=>$templatefile));

		}

		// Sanitize line endings
		$template = str_replace(array("\r\n","\r"),"\n",$template);
		$f = explode("\n",$template);

		while ( list($linenum,$line) = each($f) ) {
			$line = rtrim($line);
			// Data replacement
			if ( preg_match_all("/\[(.+?)\]/",$line,$labels,PREG_SET_ORDER) ) {
				while ( list($i,$label) = each($labels) ) {
					$code = $label[1];
					if (empty($data)) $string = (isset($_POST[$code])?$_POST[$code]:'');
					else $string = apply_filters('shopp_email_data', $data[$code], $code);

					$string = str_replace(array_keys($replacements),array_values($replacements),$string);

					if (isset($string) && !is_array($string)) $line = preg_replace("/\[".$code."\]/",$string,$line);
				}
			}

			// Header parse
			if (!$in_body && false !== strpos($line,':')) {
				list($header,$value) = explode(':',$line);

				// Protect against header injection
				if (in_array(strtolower($header),$protected))
					$value = str_replace("\n","",urldecode($value));

				if ( 'to' == strtolower($header) ) $to = $value;
				elseif ( 'subject' == strtolower($header) ) $subject = $value;
				else $headers[] = $line;
			}

			// Catches the first blank line to begin capturing message body
			if ( !$in_body && empty($line) ) $in_body = true;
			if ( $in_body ) $message .= $line."\n";
		}

		// Use only the email address, discard everything else
		if (strpos($to,'<') !== false) {
			list($name, $email) = explode('<',$to);
			$to = trim(rtrim($email,'>'));
		}

		// If not already in place, setup default system email filters
		if (!class_exists('ShoppEmailDefaultFilters')) {
			require(SHOPP_MODEL_PATH.'/Email.php');
			new ShoppEmailDefaultFilters();
		}

		// Message filters first
		$headers = apply_filters('shopp_email_headers',$headers,$message);
		$message = apply_filters('shopp_email_message',$message,$headers);

		if (!$debug) return wp_mail($to,$subject,$message,$headers);

		header('Content-type: text/plain');
		echo "To: ".htmlspecialchars($to)."\n";
		echo "Subject: $subject\n\n";
		echo "Headers:\n";
		print_r($headers);

		echo "\nMessage:\n$message\n";
		exit();
	}

	/**
	 * Locate the WordPress bootstrap file
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return string Absolute path to wp-load.php
	 **/
	function shopp_find_wpload () {
		global $table_prefix;

		$loadfile = 'wp-load.php';
		$wp_abspath = false;

		$syspath = explode('/',$_SERVER['SCRIPT_FILENAME']);
		$uripath = explode('/',$_SERVER['SCRIPT_NAME']);
		$rootpath = array_diff($syspath,$uripath);
		$root = '/'.join('/',$rootpath);

		$filepath = dirname(!empty($_SERVER['SCRIPT_FILENAME'])?$_SERVER['SCRIPT_FILENAME']:__FILE__);

		if ( file_exists(sanitize_path($root).'/'.$loadfile))
			$wp_abspath = $root;

		if ( isset($_SERVER['SHOPP_WP_ABSPATH'])
			&& file_exists(sanitize_path($_SERVER['SHOPP_WP_ABSPATH']).'/'.$configfile) ) {
			// SetEnv SHOPP_WPCONFIG_PATH /path/to/wpconfig
			// and SHOPP_ABSPATH used on webserver site config
			$wp_abspath = $_SERVER['SHOPP_WP_ABSPATH'];

		} elseif ( strpos($filepath, $root) !== false ) {
			// Shopp directory has DOCUMENT_ROOT ancenstor, find wp-config.php
			$fullpath = explode ('/', sanitize_path($filepath) );
			while (!$wp_abspath && ($dir = array_pop($fullpath)) !== null) {
				if (file_exists( sanitize_path(join('/',$fullpath)).'/'.$loadfile ))
					$wp_abspath = join('/',$fullpath);
			}

		} elseif ( file_exists(sanitize_path($root).'/'.$loadfile) ) {
			$wp_abspath = $root; // WordPress install in DOCUMENT_ROOT
		} elseif ( file_exists(sanitize_path(dirname($root)).'/'.$loadfile) ) {
			$wp_abspath = dirname($root); // wp-config up one directory from DOCUMENT_ROOT
	    } else {
	        /* Last chance, do or die */
			$filepath = sanitize_path($filepath);
	        if (($pos = strpos($filepath, 'wp-content/plugins')) !== false)
	            $wp_abspath = substr($filepath, 0, --$pos);
	    }

		$wp_load_file = realpath(sanitize_path($wp_abspath).'/'.$loadfile);

		if ( $wp_load_file !== false ) return $wp_load_file;
		return false;

	}

	/**
	 * Ties the key status and update key together
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	function shopp_keybind ($data) {
		if (!isset($data[1]) || empty($data[1])) $data[1] = str_repeat('0',40);
		return pack(Lookup::keyformat(true),$data[0],$data[1]);
	}

	/**
	 * Generates RSS markup in XML from a set of provided data
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @deprecated Functionality moved to the Storefront
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

		$xml .= '<atom:link href="'.esc_attr($data['link']).'" rel="self" type="application/rss+xml" />'."\n";
		$xml .= "<title>".esc_html($data['title'])."</title>\n";
		$xml .= "<description>".esc_html($data['description'])."</description>\n";
		$xml .= "<link>".esc_html($data['link'])."</link>\n";
		$xml .= "<language>".get_option('rss_language')."</language>\n";
		$xml .= "<copyright>".esc_html("Copyright ".date('Y').", ".$data['sitename'])."</copyright>\n";

		if (is_array($data['items'])) {
			foreach($data['items'] as $item) {
				$xml .= "\t<item>\n";
				foreach ($item as $key => $value) {
					$attrs = '';
					if (is_array($value)) {
						$data = $value;
						$value = '';
						foreach ($data as $name => $content) {
							if (empty($name)) $value = $content;
							else $attrs .= ' '.$name.'="'.esc_attr($content).'"';
						}
					}
					if (strpos($value,'<![CDATA[') === false) $value = esc_html($value);
					if (!empty($value)) $xml .= "\t\t<$key$attrs>$value</$key>\n";
					else $xml .= "\t\t<$key$attrs />\n";
				}
				$xml .= "\t</item>\n";
			}
		}

		$xml .= "</channel>\n";
		$xml .= "</rss>\n";

		return $xml;
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
	 * Parses tag option strings or arrays
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @param string|array $options URL-compatible query string or associative array of tag options
	 * @return array API-ready options list
	 **/
	function shopp_parse_options ($options) {

		$paramset = array();
		if ( empty($options) ) return $paramset;
		if ( is_string($options) ) parse_str($options,$paramset);
		else $paramset = $options;

		$options = array();
		foreach ( array_keys($paramset) as $key )
			$options[ strtolower($key) ] = $paramset[$key];

		if ( get_magic_quotes_gpc() )
			$options = stripslashes_deep( $options );

		return $options;

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
	function shopp_redirect ($uri,$exit=true,$status=302) {
		if (class_exists('ShoppError'))	new ShoppError('Redirecting to: '.$uri,'shopp_redirect',SHOPP_DEBUG_ERR);
		wp_redirect($uri,$status);
		if ($exit) exit();
	}

	/**
	 * Safely handles redirect requests to ensure they remain onsite
	 *
	 * Derived from WP 2.8 wp_safe_redirect
	 *
	 * @author Mark Jaquith, Ryan Boren
	 * @since 1.1
	 *
	 * @param string $location The URL to redirect to
	 * @param int $status (optional) The HTTP status to send to the browser
	 * @return void
	 **/
	function shopp_safe_redirect($location, $status = 302) {

		// Need to look at the URL the way it will end up in wp_redirect()
		$location = wp_sanitize_redirect($location);

		// browsers will assume 'http' is your protocol, and will obey a redirect to a URL starting with '//'
		if ( substr($location, 0, 2) == '//' )
			$location = 'http:' . $location;

		// In php 5 parse_url may fail if the URL query part contains http://, bug #38143
		$test = ( $cut = strpos($location, '?') ) ? substr( $location, 0, $cut ) : $location;

		$lp  = parse_url($test);
		$wpp = parse_url(get_option('home'));

		$allowed_hosts = (array) apply_filters('allowed_redirect_hosts', array($wpp['host']), isset($lp['host']) ? $lp['host'] : '');

		if ( isset($lp['host']) && ( !in_array($lp['host'], $allowed_hosts) && $lp['host'] != strtolower($wpp['host'])) )
			$location = shoppurl(false,'account');

		wp_redirect($location, $status);
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
	function shopp_taxrate ($override=null,$taxprice=true,$Item=false) {
		$Taxes = new CartTax();
		$rated = false;
		$taxrate = 0;

		if ( shopp_setting_enabled('tax_inclusive') ) $rated = true;
		if ( ! is_null($override) ) $rated = $override;
		if ( ! str_true($taxprice) ) $rated = false;

		if ($rated) $taxrate = $Taxes->rate($Item);
		return $taxrate;
	}

	/**
	 * Helper to prefix theme template file names
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @param string $name The name of the template file
	 * @return string Prefixed template file
	 **/
	function shopp_template_prefix ($name) {
		return apply_filters('shopp_template_directory','shopp').'/'.$name;
	}

	/**
	 * Returns the URI for a template file
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @param string $name The name of the template file
	 * @return string The URL for the template file
	 **/
	function shopp_template_url ($name) {
		$themepath = get_stylesheet_directory();
		$themeuri = get_stylesheet_directory_uri();
		$builtin = SHOPP_PLUGINURI.'/templates';
		$template = rtrim(shopp_template_prefix(''),'/');

		$path = "$themepath/$template";

		if ('off' != shopp_setting('theme_templates')
				&& is_dir(sanitize_path( $path )) )
			$url = "$themeuri/$template/$name";
		else $url = "$builtin/$name";

		return sanitize_path($url);
	}

	/**
	 * Generates canonical storefront URLs that respects the WordPress permalink settings
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * @version 1.2
	 *
	 * @param mixed $request Additional URI requests
	 * @param string $page The gateway page
	 * @param boolean $secure (optional) True for secure URLs, false to force unsecure URLs
	 * @return string The final URL
	 **/
	function shoppurl ($request=false,$page='catalog',$secure=null) {

		$structure = get_option('permalink_structure');
		$prettyurls = ('' != $structure);

		$path[] = Storefront::slug('catalog');

		// Build request path based on Storefront shopp_page requested
		if ('images' == $page) {
			$path[] = 'images';
			if (!$prettyurls) $request = array('siid'=>$request);
		} else {
			if ('confirm-order' == $page) $page = 'confirm'; // For compatibility with 1.1 addons
			if (false !== $page)
				$page_slug = Storefront::slug($page);
			if ($page != 'catalog') {
				if (!empty($page_slug)) $path[] = $page_slug;
			}
		}

		// Change the URL scheme as necessary
		$scheme = null; // Full-auto
		if ($secure === false) $scheme = 'http'; // Contextually forced off
		elseif (($secure || is_ssl()) && !SHOPP_NOSSL) $scheme = 'https'; // HTTPS required

		$url = home_url(false,$scheme);
		if ($prettyurls) $url = home_url(join('/',$path),$scheme);
		if (strpos($url,'?') !== false) list($url,$query) = explode('?',$url);
		$url = trailingslashit($url);

		if (!empty($query)) {
			parse_str($query,$home_queryvars);
			if ($request === false) {
				$request = array();
				$request = array_merge($home_queryvars,$request);
			} else {
				$request = array($request);
				array_push($request,$home_queryvars);
			}
		}

		if (!$prettyurls) $url = isset($page_slug)?add_query_arg('shopp_page',$page_slug,$url):$url;

		// No extra request, return the complete URL
		if (!$request) return apply_filters('shopp_url',$url);

		// Filter URI request
		$uri = false;
		if (!is_array($request)) $uri = urldecode($request);
		if (is_array($request) && isset($request[0])) $uri = array_shift($request);
		if (!empty($uri)) $uri = join('/',array_map('urlencode',explode('/',$uri))); // sanitize

		$url = user_trailingslashit($url.$uri);

		if (!empty($request) && is_array($request)) {
			$request = array_map('urldecode',$request);
			$request = array_map('urlencode',$request);
			$url = add_query_arg($request,$url);
		}

		return apply_filters('shopp_url',$url);
	}

}


/**
 * Implements a Registry pattern with internal iteration support
 *
 * @author Jonathan Davis
 * @since 1.2
 * @package shopp
 **/
class RegistryManager implements Iterator {

	private $_list = array();
	private $_keys = array();
	private $_false = false;

	public function __construct() {
        $this->_position = 0;
	}

	public function add ($key,$entry) {
		$this->_list[$key] = $entry;
		$this->rekey();
	}

	public function populate ($records) {
		$this->_list = $records;
		$this->rekey();
	}

	public function update ($key,$entry) {
		if (!$this->exists($key)) return false;
		$entry = array_merge($this->_list[$key],$entry);
		$this->_list[$key] = $entry;
	}

	public function &get ($key) {
		if ($this->exists($key)) return $this->_list[$key];
		else return $_false;
	}

	public function exists ($key) {
		return array_key_exists($key,$this->_list);
	}

	public function remove ($key) {
		if (!$this->exists($key)) return false;
		unset($this->_list[$key]);
		$this->rekey();
	}

	private function rekey () {
		$this->_keys = array_keys($this->_list);
	}


	function current () {
		return $this->_list[ $this->keys[$this->_position] ];
	}

	function key () {
		return $this->keys[$this->_position];
	}

	function next () {
		++$this->_position;
	}

	function rewind () {
		$this->_position = 0;
	}

	function valid () {
		return (
			array_key_exists($this->_position,$this->_keys)
			&& array_key_exists($this->keys[$this->_position],$this->_list)
		);
	}

}

/**
 * Implements a Singleton pattern object
 *
 * @author Jonathan Davis
 * @since 1.2
 * @package shopp
 **/
class SingletonFramework {

	// @todo Requires Late-Static Binding in PHP 5.3 before extending the framework for instance return method to work

	// protected static $instance;

	// public static function instance () {
	// 	if (!self::$instance instanceof self)
	// 		self::$instance = new self;
	// 	return self::$instance;
	// }

	/**
	 * Prevents constructing new instances of singletons
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	protected function __construct () {}

	/**
	 * Prevents cloning singletons
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	protected function __clone () {}

}

class AutoObjectFramework {

	function __construct ($input) {
		$properties = get_object_vars($this);
		$args = func_num_args();
		if ($args > 1) {
			$params = func_get_args();
			$propkeys = array_keys($properties);
			$keys = array_splice($propkeys,0,$args);
			$inputs = array_combine($keys,$params);
		}
		else $inputs = $input;

		if (!is_array($inputs)) return;
		foreach ($inputs as $name => $value)
			if (property_exists($this,$name))
				$this->$name = $value;
	}

}