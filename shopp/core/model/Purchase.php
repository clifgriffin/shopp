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
	static $table = "purchase";
	var $purchased = array();

	function Purchase ($id=false) {
		$this->init(self::$table);
		if (!$id) return true;
		if ($this->load($id)) return true;
		else return false;
	}

	function load_purchased () {
		$db = DB::get();

		$table = DatabaseObject::tablename(Purchased::$table);
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
	
	function tag ($property,$options=array()) {
		global $Shopp;
				
		// Return strings with no options
		switch ($property) {
			case "url": return $Shopp->link('cart'); break;
			case "totalitems": return count($this->purchased); break;
			case "hasitems": if (count($this->purchased) > 0) return true; else return false; break;
			case "items":
				if (!$this->looping) {
					reset($this->purchased);
					$this->looping = true;
				} else next($this->purchased);
				
				if (current($this->purchased)) return true;
				else {
					$this->looping = false;
					reset($this->purchased);
					return false;
				}
			case "id": return $this->id;
		}
	}
	
	

} // end Purchase class

?>