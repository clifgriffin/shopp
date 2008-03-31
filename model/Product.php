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

} // end Product class

?>