<?php
/**
 * ImageServer
 * Provides low-overhead image service support
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 12 December, 2009
 * @package shopp
 **/
require_once('DB.php');
require_once('model/Settings.php');
require_once("model/Asset.php");

class ImageServer extends DatabaseObject {
	var $image = false;
	var $Asset = false;
	
	function ImageServer () {
		$this->dbinit();
		$this->request();
		if ($this->load())
			$this->render();
		else $this->error();
	}

	function request () {
		if (isset($_GET['shopp_image'])) $this->image = $_GET['shopp_image'];
		elseif (preg_match('/\/images\/(\d+).*$/',$_SERVER['REQUEST_URI'],$matches)) 
			$this->image = $matches[1];
	}

	function load () {
		$db =& DB::get();

		$table = DatabaseObject::tablename(Settings::$table);
		$settings = $db->query("SELECT * FROM $table WHERE name='image_storage' OR name='image_path'",AS_ARRAY);
		foreach ($settings as $setting) $this->{$setting->name} = $setting->value;
		
		if (empty($this->image)) return false;
		$this->Asset = new Asset($this->image);
		if (empty($this->Asset->id)) return false;
		if (isset($this->image_path)) $this->image_path = sanitize_path(realpath($this->image_path));
		if ($this->image_storage == "fs" && !file_exists($this->image_path.'/'.$this->Asset->name)) 
			return false;
		if ($this->image_storage == "db" && empty($this->Asset->data)) 
			return false;
			
		return true;
	}
	
	function render () {
		header('Last-Modified: '.date('D, d M Y H:i:s', $this->Asset->created).' GMT'); 
		header("Content-type: ".$this->Asset->properties['mimetype']);
		header("Content-Disposition: inline; filename=".$this->Asset->name.""); 
		header("Content-Description: Delivered by WordPress/Shopp Image Server");
		if ($this->image_storage == "fs") {
			header ("Content-length: ".@filesize($this->image_path.'/'.$this->Asset->name)); 
			@readfile($this->image_path.'/'.$this->Asset->name);
		} else {
			header ("Content-length: ".strlen($this->Asset->data)); 
			echo $this->Asset->data;
		} 
		exit();
	}
	
	function error () {
		header("HTTP/1.1 404 Not Found");
		$notfound = sanitize_path(dirname(__FILE__)).'/ui/icons/notfound.png';
		if (defined('SHOPP_NOTFOUND_IMAGE') && file_exists(SHOPP_NOTFOUND_IMAGE))
			$notfound = SHOPP_NOTFOUND_IMAGE;
		if (!file_exists($notfound)) die('<h1>404 Not Found</h1>');
		else {
			header("Cache-Control: no-cache, must-revalidate");
			header("Content-type: image/png");
			header("Content-Disposition: inline; filename=".$notfound.""); 
			header("Content-Description: Delivered by WordPress/Shopp Image Server");
			header("Content-length: ".@strlen($notfound)); 
			@readfile($notfound);
		}
		die();
	}
	
	function dbinit () {
		global $table_prefix;
		$_ = array();
		$root = $_SERVER['DOCUMENT_ROOT'];
		$found = array();
		find_filepath('wp-config.php',$root,$root,$found);
		if (empty($found[0])) $this->error();
		$config = file_get_contents($root.$found[0]);
		
		// Evaluate all define macros
		preg_match_all('/^\s*?(define\(\s*?\'(.*?)\'\s*?,\s*?(.*?)\);)/m',$config,$defines,PREG_SET_ORDER);
		foreach($defines as $defined) if (!defined($defined[2])) {
			$defined[1] = preg_replace('/\_\_FILE\_\_/',"'$root{$found[0]}'",$defined[1]);
			eval($defined[1]);
		}
		chdir(ABSPATH.'wp-content');

		// Evaluate the $table_prefix variable
		preg_match('/\$table_prefix\s*?=\s*?[\'|"](.*?)[\'|"];/',$config,$match);
		$table_prefix = $match[1];

		$db = DB::get();
		$db->connect(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);
		
		if(function_exists("date_default_timezone_set") && function_exists("date_default_timezone_get"))
			@date_default_timezone_set(@date_default_timezone_get());
			
	}

} // end ImageServer class


function find_filepath ($filename, $directory, $root, &$found) {
	if (is_dir($directory)) {
		$Directory = @dir($directory);
		if ($Directory) {
			while (( $file = $Directory->read() ) !== false) {
				if (substr($file,0,1) == "." || substr($file,0,1) == "_") continue;				// Ignore .dot files and _directories
				if (is_dir($directory.'/'.$file) && $directory == $root)		// Scan one deep more than root
					find_filepath($filename,$directory.'/'.$file,$root, $found);	// but avoid recursive scans
				elseif ($file == $filename)
					$found[] = substr($directory,strlen($root)).'/'.$file;		// Add the file to the found list
			}
			return true;
		}
	}
	return false;
}

if (!function_exists('mktimestamp')) {
	function mktimestamp () {}
}

if(!function_exists('sanitize_path')){
	function sanitize_path ($path) {
		return str_replace('\\', '/', $path);
	}
}


new ImageServer();

?>