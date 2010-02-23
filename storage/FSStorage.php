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

/**
 * FSStorage
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
	
	function context ($context) {
		chdir(WP_CONTENT_DIR);
		$this->context = $context;
		if (isset($this->settings['path'][$context]))
			$this->path = realpath($this->settings['path'][$context]);
	}

	function save ($data,$asset) {
		if (file_put_contents($this->path.'/'.$asset->filename,$data) > 0) return $asset->filename;
		else return false;
	}
	
	function exists ($uri) {
		$filepath = $this->path."/".$uri;
		return (file_exists($filepath) && is_readable($filepath));
	}
	
	function load ($uri) {
		return file_get_contents($this->path.'/'.$uri);
	}
	
	function output ($uri,$etag=false) {
		$filepath = $this->path.'/'.$uri;

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
		$this->ui->text(0,array(
			'name' => 'path',
			'value' => $this->settings['path'],
			'size' => 40,
			'label' => __('The file system path to your storage directory.','Shopp')
		));
		
	}
	

} // END class FSStorage

?>