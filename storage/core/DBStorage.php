<?php
/**
 * DBStorage
 *
 * Provides database storage in the asset table
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, February 18, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.1
 * @subpackage DBStorage
 **/

/**
 * DBStorage
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 **/
class DBStorage extends StorageModule implements StorageEngine {

	var $_table = "asset";
	var $_metatable = "meta";
	var $_key = "id";

	function __construct () {
		parent::__construct();
		$this->name = __('Database','Shopp');
		$this->_table = DatabaseObject::tablename($this->_table);
		$this->_metatable = DatabaseObject::tablename($this->_metatable);
	}

	/**
	 * Save an asset to the database
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param Asset $asset Asset object the data is associated with
	 * @param string $data Binary data or path to the file to be saved
	 * @param string $type (optional) Type of data provided - binary (default) or file
	 * @return string|boolean A URI for the resource or false if failed
	 **/
	function save ($asset,$data,$type='binary') {
		$db = &DB::get();

		if (empty($data)) return false;

		if ($type != "binary") {
			if (!is_readable($data)) die("Could not read the file."); // Die because we can't use ShoppError
			$data = file_get_contents($data);
		}

		$data = @mysql_real_escape_string($data);

		if (!$asset->id) $uri = $db->query("INSERT $this->_table SET data='$data'");
		else $db->query("UPDATE $this->_table SET data='$data' WHERE $this->_key='$asset->uri'");

		if (isset($uri)) return $uri;
		return false;
	}

	/**
	 * Gets the size and mimetype meta of a stored asset
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $uri The URI for the resource
	 * @param string $filename (optional) File name of the asset
	 * @return array A named list of the stored file size and mimetype
	 **/
	function meta ($uri=false,$filename=false) {
		$db = &DB::get();
		$_ = array();
		if (empty($uri)) return $_;
		$file = $db->query("SELECT LENGTH(data) AS size FROM $this->_table WHERE $this->_key='$uri' LIMIT 1");
		if ($file && isset($file->size)) $_['size'] = $file->size;
		if ($filename !== false) $_['mime'] = file_mimetype(false,$filename);
		return $_;
	}

	/**
	 * Determine if the provided resource exists
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $uri The URI for the resource
	 * @return boolean True if the resource exists
	 **/
	function exists ($uri) {
		$db = &DB::get();
		if (strpos($uri,'.') !== false || (int)$uri == 0) return false;
		$file = $db->query("SELECT id FROM $this->_table WHERE $this->_key='$uri' LIMIT 1");
		return (!empty($file));
	}

	/**
	 * Load the binary data of a specified resource
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $uri The URI for the resource
	 * @return string Binary data
	 **/
	function load ($uri) {
		$db = &DB::get();
		if (!$uri) return false;
		$file = $db->query("SELECT * FROM $this->_table WHERE $this->_key='$uri' LIMIT 1");
		if (empty($file)) {
 			new ShoppError(__('The requested asset could not be loaded from the database.','Shopp'),'dbstorage_load',SHOPP_ADMIN_ERR);
			return false;
		}
		return $file->data;
	}

} // END class DBStorage

?>
