<?php
/**
 * Product class
 * Catalog products
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

require("Price.php");

class Product extends DatabaseObject {
	var $prices = array();
	var $categories = array();
	
	function Product ($id=false) {
		$this->init('product');
		if ($this->load($id)) return true;
		else return false;
	}
	
	function load_prices () {
		$db =& DB::get();
		
		$pricetable = DBPREFIX."price";
		if (empty($this->id)) return false;
		$this->prices = $db->query("SELECT * FROM $pricetable WHERE product=$this->id",AS_ARRAY);
		return true;
	}

	function load_categories () {
		$db =& DB::get();
		
		$catalogtable = DBPREFIX."catalog";
		if (empty($this->id)) return false;
		$this->categories = $db->query("SELECT * FROM $catalogtable WHERE product=$this->id",AS_ARRAY);
		return true;
	}
	
	function save_categories ($new) {
		$db =& DB::get();
		
		$current = array();
		foreach ($this->categories as $catalog) $current[] = $catalog->category;

		$added = array_diff($new,$current);
		$removed = array_diff($current,$new);

		$catalogtable = DBPREFIX."catalog";
		
		foreach ($added as $id) {
			$db->query("INSERT $catalogtable SET category='$id',product='$this->id',created=now(),modified=now()");
		}
		
		foreach ($removed as $id) {
			$db->query("DELETE FROM $catalogtable WHERE category='$id' AND product='$this->id'"); 
		}
		
	}


} // end Product class

?>