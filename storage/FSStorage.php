<?php
/**
 * FSStorage
 * 
 * Provides file system storage of store assets
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, February 18, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.1
 * @subpackage FSStorage
 **/

if (!defined('WP_CONTENT_DIR')) define('WP_CONTENT_DIR', ABSPATH . 'wp-content');

/**
 * FSStorage
 *
 * Note that storage modules cannot use ShoppError for logging errors
 * as they are used in another context where WordPress is not fully loaded.
 * 
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 **/
class FSStorage extends StorageModule implements StorageEngine {
		
	/**
	 * FSStorage constructor
	 *
	 * @author Jonathan Davis
	 * 
	 * @return void
	 **/
	function __construct () {
		parent::__construct();
		$this->name = __('File system','Shopp');
	}
	
	function actions () {
		add_action('wp_ajax_shopp_storage_suggestions',array(&$this,'suggestions'));
 		add_filter('shopp_verify_stored_file',array(&$this,'verify'));
	}
	
	function context ($context) {
		chdir(WP_CONTENT_DIR);
		$this->context = $context;
		if (isset($this->settings['path'][$context]))
			$this->path = realpath($this->settings['path'][$context]);
	}

	function save ($asset,$data,$type='binary') {
		
		if ($type == "upload") { // $data is an uploaded temp file path, just move the file
			error_reporting(E_ALL);
			ini_set( 'display_errors', 1 );
			ini_set( 'log_errors', 1 );
			
			if (!is_readable($data)) die("$this->module: Could not read the file."); // Die because we can't use ShoppError
			if (move_uploaded_file($data,sanitize_path($this->path.'/'.$asset->filename))) return $asset->filename;
			else die("$this->module: Could not move the uploaded file to the storage repository.");
		} elseif ($type == "file") { // $data is a file path, just move the file
			if (!is_readable($data)) die("$this->module: Could not read the file."); // Die because we can't use ShoppError
			if (rename($data,sanitize_path($this->path.'/'.$asset->filename))) return $asset->filename;
			else die("$this->module: Could not move the file to the storage repository.");
		}
		
		if (file_put_contents(sanitize_path($this->path.'/'.$asset->filename),$data) > 0) return $asset->filename;
		else return false;
	}
	
	function exists ($uri) {
		$filepath = sanitize_path($this->path."/".$uri);
		return (file_exists($filepath) && is_readable($filepath));
	}
	
	function load ($uri) {
		return file_get_contents(sanitize_path($this->path.'/'.$uri));
	}
	
	function meta ($uri) {
		$_ = array();
		$_['size'] = filesize(sanitize_path($this->path.'/'.$uri));
		$_['mime'] = file_mimetype(sanitize_path($this->path.'/'.$uri));
		return $_;
	}
	
	function output ($uri,$etag=false) {
		$filepath = sanitize_path($this->path.'/'.$uri);

		if ($this->context == "download") {
			if (!is_file($filepath)) {
				header("Status: 404 Forbidden");  // File not found?!
				return false;
			}

			$size = @filesize($filepath);
			
			$range = '';
			// Handle resumable downloads
			if (isset($_SERVER['HTTP_RANGE'])) {
				list($units, $reqrange) = explode('=', $_SERVER['HTTP_RANGE'], 2);
				if ($units == 'bytes') {
					// Use first range - http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
					list($range, $extra) = explode(',', $reqrange, 2);
				}
			} 
			
			// Determine download chunk to grab
		    list($start, $end) = explode('-', $range, 2);
			
		    // Set start and end based on range (if set), or set defaults
		    // also check for invalid ranges.
		    $end = (empty($end)) ? ($size - 1) : min(abs(intval($end)),($size - 1));
		    $start = (empty($start) || $end < abs(intval($start))) ? 0 : max(abs(intval($start)),0);

	        // Only send partial content header if downloading a piece of the file (IE workaround)
	        if ($start > 0 || $end < ($size - 1)) header('HTTP/1.1 206 Partial Content');

	        header('Accept-Ranges: bytes');
	        header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
		    header('Content-length: '.($end-$start+1)); 

			// WebKit/Safari resumable download support headers
		    header('Last-modified: '.date('D, d M Y H:i:s O',$this->modified)); 
			if (isset($etag)) header('ETag: '.$etag);

			$file = fopen($filepath, 'rb');
			fseek($file, $start);
			$packet = 1024*1024;
			while(!feof($file)) {
				if (connection_status() !== 0) return false;
				$buffer = fread($file,$packet);
				if (!empty($buffer)) echo $buffer;
				ob_flush(); flush();
			}
			fclose($file);
		} else readfile($filepath);
	}
	
	function settings () {
		$error = false;

		$default = __('The file system path to your storage directory.','Shopp');
		$label = array('image' => $default,'download'=>$default);

		chdir(WP_CONTENT_DIR);

		if (!is_array($this->settings))
			$this->settings = array('path' => array('image' => false, 'download' => false));

		foreach ($this->settings['path'] as $method => &$path) {
			$error = false;
			$path = stripslashes($path);
			$p = sanitize_path(realpath($path));

				if	(empty($path)) continue;
			elseif	(!file_exists($p)) $error = __("The path does not exist.","Shopp");
			elseif	(!is_dir($p)) $error = __("The path supplied is not a directory.","Shopp");
			elseif	(!is_writable($p)) $error = __("The path must be <strong>writable</strong> by the web server.","Shopp");
			elseif	(!is_readable($p)) $error = __("The path must be <strong>readable</strong> by the web server.","Shopp");

			if ($error !== false)
				$label[$method] = '<span class="error">'.$error.'</span>';
			
		}
		
		$this->ui->text(0,array(
			'name' => 'path',
			'value' => $this->settings['path'],
			'size' => 40,
			'label' => $label
		));
		
	}
	
	function suggestions () {
		if (!$this->handles('download')) return;
		check_admin_referer('wp_ajax_shopp_storage_suggestions');
		if (empty($_GET['q']) || strlen($_GET['q']) < 3) return;
		if ($_GET['t'] == "image") $this->context('image');
		else $this->context('download');
		
		global $Shopp;
		if ($Shopp->Storage->engines[$this->context] != $this->module) return;
		
		$directory = false;	// The directory to search
		$search = false;	// The file name to search for
		$relpath = false;	// The related path
		$sep = DIRECTORY_SEPARATOR;

		$url = parse_url($_GET['q']);
		if ((isset($url['scheme']) && $url['scheme'] != 'file') || !isset($url['path'])) 
			return;
		
		$query = sanitize_path($url['path']);
		$search = basename($query);
		if (strlen($search) < 3) return;

		if ($url['scheme'] == "file") {
			$directory = dirname($query);
			$uri = array($url['scheme'].':','',$directory);
			$relpath = join("/",$uri).'/';
		}
		if (!$directory && $query[0] == "/") $directory = dirname($query);
		if (!$directory) {
			$directory = realpath($this->path.$sep.dirname($query));
			$relpath = dirname($query);
			$relpath = ($relpath == ".")?false:$relpath.$sep;
		}

		$Directory = @dir($directory);
		if ($Directory) {
			while (( $file = $Directory->read() ) !== false) {
				if (substr($file,0,1) == "." || substr($file,0,1) == "_") continue;
				if (strpos(strtolower($file),strtolower($search)) === false) continue;
				if (is_dir($directory.$sep.$file)) $results[] = $relpath.$file.$sep;
				else $results[] = $relpath.$file;
			}
		}
		echo join("\n",$results);
		exit();
	}
	
	function verify ($uri) {
		if (!$this->handles('download')) return $uri;

		$this->context('download');
		$path = trailingslashit(sanitize_path($this->path));
		
		$url = $path.$uri;
		if (!file_exists($url)) die('NULL');
		if (is_dir($url)) die('ISDIR');
		if (!is_readable($url)) die('READ');

		die('OK');
	}
	
} // END class FSStorage

?>