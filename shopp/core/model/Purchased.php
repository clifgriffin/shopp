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

	function Purchased ($id=false) {
		$this->init(self::$table);
		if ($this->load($id)) return true;
		else return false;
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

} // end Purchased class

?>