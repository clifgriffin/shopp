<?php
/**
 * Asset class
 * Catalog product assets (metadata, images, downloads)
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

class Asset extends DatabaseObject {
	static $table = "asset";
	
	function Asset ($id=false) {
		$this->init(self::$table);
		if ($this->load($id)) return true;
		else return false;
	}
	
	/**
	 * Save a record, updating when we have a value for the primary key,
	 * inserting a new record when we don't */
	function save () {
		global $Shopp;
		$db =& DB::get();

		$storage = 'db';
		if ($this->datatype == "image" || 
			$this->datatype == "small" || 
			$this->datatype == "thumbnail") $storage = $Shopp->Settings->get('image_storage');
		if ($this->datatype == "download")  $storage = $Shopp->Settings->get('product_storage');
		
		$data = $db->prepare($this);
		$id = $this->{$this->_key};
		if ($storage == "fs") {
			$this->savefile();
			unset($data['data']);
		}

		// Update record
		if (!empty($id)) {
			if (isset($data['modified'])) $data['modified'] = "now()";
			$dataset = $this->dataset($data);
			$db->query("UPDATE $this->_table SET $dataset WHERE $this->_key=$id");
			return true;
		// Insert new record
		} else {
			if (isset($data['created'])) $data['created'] = "now()";
			if (isset($data['modified'])) $data['modified'] = "now()";
			$dataset = $this->dataset($data);
			$this->id = $db->query("INSERT $this->_table SET $dataset");
			return $this->id;
		}
	}
	
	function savefile () {
		global $Shopp;
		if (empty($this->data)) return true;
		
		if ($this->datatype == "image" || 
			$this->datatype == "small" || 
			$this->datatype == "thumbnail") {
			$path = trailingslashit($Shopp->Settings->get('image_path'));
		} else {
			$path = trailingslashit($Shopp->Settings->get('products_path'));
		}
		if (file_put_contents($path.$this->name,stripslashes($this->data)) > 0) return true;
		return false;
	}

} // end Asset class

?>