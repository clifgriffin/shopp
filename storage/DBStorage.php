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
	var $_key = "id";
	
	function __construct () {
		parent::__construct();
		$this->name = __('Database','Shopp');
		$this->_table = DatabaseObject::tablename($this->_table);
	}
	
	function save ($asset,$data,$type='binary') {
		$db = &DB::get();
		
		if (empty($data)) return false;

		if ($type == "file") {
			if (!is_readable($data)) die("Could not read the file."); // Die because we can't use ShoppError
			$data = file_get_contents($data);			
		}
		
		$data = @mysql_real_escape_string($data);

		if (!$asset->id) $uri = $db->query("INSERT $this->_table SET data='$data'");
		else {
			$db->query("UPDATE $this->_table SET data='$data' WHERE $this->_key='$asset->id'");	
			$uri = $asset->id;
		}

		if (isset($uri)) return $uri;
		return false;
	}
	
	function exists ($uri) {
		$db = &DB::get();
		$file = $db->query("SELECT id FROM $this->_table WHERE $this->_key='$uri' LIMIT 1");
		return (!empty($file));
	}

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