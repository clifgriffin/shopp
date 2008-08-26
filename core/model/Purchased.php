<?php
/**
 * Purchased class
 * Purchased line items for orders
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

class Purchased extends DatabaseObject {
	static $table = "purchased";

	function Purchased ($id=false,$key=false) {
		$this->init(self::$table);
		if ($this->load($id,$key)) return true;
		else return false;
	}
	
	function keygen() {
		$message = $this->name.$this->purchase.$this->product.$this->price.$this->download;
		$key = sha1($message);
		if (empty($key)) $key = md5($message);
		$this->dkey = $key;
	}

} // end Purchased class

?>