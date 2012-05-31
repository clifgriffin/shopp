<?php
/**
 * ImageServer
 * Provides low-overhead image service support
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 12 December, 2009
 * @package shopp
 * @subpackage image
 **/

// Reduce image display issues by hiding warnings/notices
error_reporting(E_ERROR | E_WARNING | E_PARSE);

chdir(dirname(__FILE__));

if (!class_exists('SingletonFramework')) require(realpath('Framework.php'));
if (!class_exists('DB')) require(realpath('DB.php'));
if (!function_exists('shopp_find_wpload')) require(realpath('functions.php'));
if (!class_exists('ShoppErrors')) require('model/Error.php');
if (!class_exists('Settings')) require('model/Settings.php');
if (!class_exists('ModuleLoader')) require('model/Modules.php');

if (!class_exists('MetaObject')) require('model/Meta.php');
if (!class_exists('ImageAsset')) require('model/Asset.php');
if (!function_exists('shopp_setting')) require(realpath('../api/settings.php'));

/**
 * ImageServer class
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package image
 **/
class ImageServer {

	var $caching = true;	// Set to false to force off image caching

	var $request = false;
	var $parameters = array();
	var $args = array('width','height','scale','sharpen','quality','fill');
	var $scaling = array('all','matte','crop','width','height');
	var $width;
	var $height;
	var $scale = 0;
	var $sharpen = 0;
	var $quality = 80;
	var $fill = false;
	var $valid = false;
	var $Image = false;

	function __construct () {
		if (!defined('SHOPP_PATH'))
			define('SHOPP_PATH',sanitize_path(dirname(dirname(__FILE__))));
		if (!defined('SHOPP_MODEL_PATH'))
			define('SHOPP_MODEL_PATH',SHOPP_PATH.'/core/model');
		if (!defined('SHOPP_STORAGE'))
			define("SHOPP_STORAGE",SHOPP_PATH."/storage");
		if (!defined('SHOPP_QUERY_DEBUG'))
			define('SHOPP_QUERY_DEBUG',true);

		$this->request();
		$this->settings();
		if ($this->load())
			$this->render();
		else $this->error();

	}

	/**
	 * Parses the request to determine the image to load
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function request () {

		if ( isset($_GET['siid']) ) $this->request = $_GET['siid'];

		foreach ($_GET as $arg => $v) {
			if (false !== strpos($arg,',')) {
				$this->parameters = explode(',',$arg);
				$this->valid = array_pop($this->parameters);
			}
		}

		// Handle pretty permalinks
		if (preg_match('/\/images\/(\d+).*$/',$_SERVER['REQUEST_URI'],$matches))
			$this->request = $matches[1];

		foreach ($this->parameters as $index => $arg)
			if ('' != $arg) $this->{$this->args[$index]} = intval($arg);

		if ($this->height == 0 && $this->width > 0) $this->height = $this->width;
		if ($this->width == 0 && $this->height > 0) $this->width = $this->height;
		$this->scale = $this->scaling[$this->scale];

		// Handle clear image requests (used in product gallery to reserve DOM dimensions)
		if ('000' == substr($this->request,0,3)) $this->clearpng();
	}

	/**
	 * Loads the requested image for display
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * @return boolean Status of the image load
	 **/
	function load () {

		$cache = 'image_'.$this->request.($this->valid?'_'.$this->valid:'');
		$cached = wp_cache_get($cache,'shopp_image');
		if ($cached) return ($this->Image = $cached);

		$this->Image = new ImageAsset($this->request);
		if (max($this->width,$this->height) > 0) $this->loadsized();

		wp_cache_set($cache,$this->Image,'shopp_image');

		if (!empty($this->Image->id) || !empty($this->Image->data)) return true;
		else return false;
	}

	function loadsized () {
		// Same size requested, skip resizing
		if ($this->Image->width == $this->width && $this->Image->height == $this->height) return;

		$Cached = new ImageAsset(array(
				'parent' => $this->Image->id,
				'context'=>'image',
				'type'=>'image',
				'name'=>'cache_'.implode('_',$this->parameters)
		));

		// Use the cached version if it exists, otherwise resize the image
		if (!empty($Cached->id) && $this->caching) $this->Image = $Cached;
		else $this->resize(); // No cached copy exists, recreate
	}

	function resize () {
		$key = (defined('SECRET_AUTH_KEY') && SECRET_AUTH_KEY != '')?SECRET_AUTH_KEY:DB_PASSWORD;
		$message = $this->Image->id.','.implode(',',$this->parameters);
		if ($this->valid != crc32($key.$message)) {
			header("HTTP/1.1 404 Not Found");
			die('');
		}

		if (!class_exists('ImageProcessor'))
			require(SHOPP_MODEL_PATH."/Image.php");
		$Resized = new ImageProcessor($this->Image->retrieve(),$this->Image->width,$this->Image->height);
		$scaled = $this->Image->scaled($this->width,$this->height,$this->scale);
		$alpha = ('image/png' == $this->Image->mime);
		if (-1 == $this->fill) $alpha = true;
		$Resized->scale($scaled['width'],$scaled['height'],$this->scale,$alpha,$this->fill);

		// Post sharpen
		if (!$alpha && $this->sharpen !== false)
			$Resized->UnsharpMask($this->sharpen);

		$ResizedImage = new ImageAsset();
		$ResizedImage->copydata($this->Image,false,array());
		$ResizedImage->name = 'cache_'.implode('_',$this->parameters);
		$ResizedImage->filename = $ResizedImage->name.'_'.$ResizedImage->filename;
		$ResizedImage->parent = $this->Image->id;
		$ResizedImage->context = 'image';
		$ResizedImage->mime = "image/jpeg";
		$ResizedImage->id = false;
		$ResizedImage->width = $Resized->width;
		$ResizedImage->height = $Resized->height;

		foreach ($this->args as $index => $arg)
			$ResizedImage->settings[$arg] = isset($this->parameters[$index])?intval($this->parameters[$index]):false;

		$ResizedImage->data = $Resized->imagefile($this->quality);
		if (empty($ResizedImage->data)) return false;

		$ResizedImage->size = strlen($ResizedImage->data);
		$this->Image = $ResizedImage;
		if ($ResizedImage->store( $ResizedImage->data ) === false)
			return false;

		$ResizedImage->save();

	}

	/**
	 * Output the image to the browser
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * @return void
	 **/
	function render () {

		$found = $this->Image->found();
		if (!$found) return $this->error();

		if (is_array($found) && isset($found['redirect'])) {
			$this->Image->output(false);
		} else $this->Image->output();
		exit();
	}

	/**
	 * Output a default image when the requested image is not found
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * @return void
	 **/
	function error () {
		header("HTTP/1.1 404 Not Found");
		$notfound = sanitize_path(dirname(__FILE__)).'/ui/icons/notfound.png';
		if (defined('SHOPP_NOTFOUND_IMAGE') && file_exists(SHOPP_NOTFOUND_IMAGE))
			$notfound = SHOPP_NOTFOUND_IMAGE;
		if (!file_exists($notfound)) die('<h1>404 Not Found</h1>');
		else {
			header("Cache-Control: no-cache, must-revalidate");
			header("Content-type: image/png");
			header("Content-Disposition: inline; filename=".basename($notfound)."");
			header("Content-Description: Delivered by WordPress/Shopp Image Server");
			header("Content-length: ".@strlen($notfound));
			@readfile($notfound);
		}
		die();
	}

	/**
	 * Renders a transparent PNG of the requested dimensions
	 *
	 * Used in the product gallery to reserve DOM dimensions so the
	 * gallery is rendered with the proper layout
	 *
	 * @author Jonathan Davis
	 * @since 1.1.7
	 *
	 * @return void Description...
	 **/
	function clearpng () {
		if (!class_exists('ImageProcessor'))
			require(SHOPP_MODEL_PATH.'/Image.php');
		$max = 1920;
		$this->width = min($max,$this->width);
		$this->height = min($max,$this->height);
		$ImageData = new ImageProcessor(false,$this->width,$this->height);
		$ImageData->canvas($this->width,$this->height,true);
		$image = $ImageData->imagefile(100);
		header("Cache-Control: no-cache, must-revalidate");
		header("Content-type: image/png");
		header("Content-Disposition: inline; filename=clear.png");
		header("Content-Description: Delivered by WordPress/Shopp Image Server");
		header("Content-length: ".@strlen($image));
		die($image);
	}

	function settings () {
		ShoppSettings();
	}

} // end ImageServer class

/**
 * Stub for compatibility
 **/
if (!function_exists('__')) {
	// Localization API is not available at this point
	function __ ($string,$domain=false) {
		return $string;
	}
}

// Barebones bootstrap (say that 5x fast) for WordPress
if (!defined('ABSPATH') && $loadfile = shopp_find_wpload()) {
	define('SHORTINIT',true);
	require($loadfile);
	global $table_prefix;
}

// Start the server
new ImageServer();

?>