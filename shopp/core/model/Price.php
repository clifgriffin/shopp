<?php
/**
 * Price class
 * Catalog product price variations
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

class Price extends DatabaseObject {
	static $table = "price";
	
	function Price ($id=false,$key=false) {
		$this->init(self::$table);
		if ($this->load($id,$key)) {
			$this->load_download();
			return true;
		}
		else return false;
	}
	
	/**
	 * Load a single record by a slug name */
	function loadby_optionkey ($product,$key) {
		$db = DB::get();
		
		$r = $db->query("SELECT * FROM $this->_table WHERE product='$product' AND optionkey='$key'");
		$this->populate($r);

		if (!empty($this->id)) return true;
		return false;
	}
	
	function load_download () {
		if ($this->type != "Download") return false;
		$this->download = new ProductDownload(array(
			'parent' => $this->id,
			'context' => 'price',
			'type' => 'download'
			));
		
		if (empty($this->download->id)) return false;
		return true;
	}
	
	function attach_download ($id) {
		if (!$id) return false;
		
		$Download = new ProductDownload($id);
		$Download->parent = $this->id;
		$Download->save();
				
		do_action('attach_product_download',$id,$this->id);
		
		return true;
	}

} // end Price class

?>