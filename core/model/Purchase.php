<?php
/**
 * Purchase class
 * Order invoice logging
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

require("Purchased.php");

class Purchase extends DatabaseObject {
	var $purchased = array();

	function Purchase ($id=false) {
		$this->init('purchase');
		if ($this->load($id)) return true;
		else return false;
	}

	function load_purchased () {
		$db =& DB::get();

		$table = DBPREFIX."purchased";
		if (empty($this->id)) return false;
		$this->purchased = $db->query("SELECT * FROM $table WHERE purchase=$this->id",AS_ARRAY);
		return true;
	}
	
	function copydata ($Object,$prefix="") {
		$ignores = array("_datatypes","_table","_key","_lists","id","created","modified");
		foreach(get_object_vars($Object) as $property => $value) {
			$property = $prefix.$property;
			if (property_exists($this,$property) && 
				!in_array($property,$ignores)) 
				$this->{$property} = $value;
		}
	}
	
	

} // end Purchase class

?>