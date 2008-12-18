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
	
	var $storage = "db";
	var $path = "";
	
	function Asset ($id=false) {
		$this->init(self::$table);
		if ($this->load($id)) return true;
		else return false;
	}
	
	function setstorage ($type=false) {
		global $Shopp;
		if (!$type) $type = $this->datatype;
		switch ($type) {
			case "image":
			case "small":
			case "thumbnail":
				$this->storage = $Shopp->Settings->get('image_storage');
				$this->path = trailingslashit($Shopp->Settings->get('image_path'));
				break;
			case "download":
				$this->storage = $Shopp->Settings->get('product_storage');
				$this->path = trailingslashit($Shopp->Settings->get('product_path'));
				break;
		}
	}
	
	/**
	 * Save a record, updating when we have a value for the primary key,
	 * inserting a new record when we don't */
	function save () {
		$db =& DB::get();
		
		$data = $db->prepare($this);
		$id = $this->{$this->_key};

		$this->setstorage();
	
		// Hook for outputting files to filesystem
		if ($this->storage == "fs") {
			if (!$this->savefile()) return false;
			unset($data['data']); // Keep from duplicating data in DB
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
		if (empty($this->data)) return true;
		if (file_put_contents($this->path.$this->name,stripslashes($this->data)) > 0) return true;
		return false;
	}
	
	function deleteset ($keys,$type="image") {
		$db =& DB::get();

		if ($type == "image") $this->setstorage('image');
		if ($type == "download") $this->setstorage('download');

		$selection = "";
		foreach ($keys as $value) 
			$selection .= ((!empty($selection))?" OR ":"")."{$this->_key}=$value OR src=$value";

		if ($this->storage == "fs") $this->deletefiles($selection);

		$query = "DELETE LOW_PRIORITY FROM $this->_table WHERE $selection";
		$db->query($query);
	}
	
	function deletefiles ($selection) {
		$db =& DB::get();
		
		$files = $db->query("SELECT name FROM $this->_table WHERE $selection",AS_ARRAY);
		foreach ($files as $file) 
			unlink($this->path.$file->name);

		return true;
	}
	
} // end Asset class

?>