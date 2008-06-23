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
		$this->prices = $db->query("SELECT * FROM $pricetable WHERE product=$this->id ORDER BY sortorder ASC",AS_ARRAY);
		return true;
	}

	function load_categories () {
		$db =& DB::get();
		
		$catalogtable = DBPREFIX."catalog";
		if (empty($this->id)) return false;
		$this->categories = $db->query("SELECT * FROM $catalogtable WHERE product=$this->id",AS_ARRAY);
		return true;
	}

	function load_images () {
		$db =& DB::get();
		
		$assettable = DBPREFIX."asset";
		if (empty($this->id)) return false;
		$images = $db->query("SELECT id,properties,datatype FROM $assettable WHERE parent=$this->id AND type='product' AND (datatype='image' OR datatype='feature' OR datatype='thumbnail') ORDER BY sortorder",AS_ARRAY);
		foreach ($images as $image) 
			$image->properties = unserialize($image->properties);
		$this->images = $images;
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
			$db->query("DELETE LOW_PRIORITY FROM $catalogtable WHERE category='$id' AND product='$this->id'"); 
		}
		
	}
	
	/**
	 * save_imageorder()
	 * Updates the sortorder of image assets (source, featured and thumbnails)
	 * based on the provided array of image ids */
	function save_imageorder ($ordering) {
		$db =& DB::get();
		$assettable = DBPREFIX."asset";
		foreach ($ordering as $i => $id) 
			$db->query("UPDATE LOW_PRIORITY $assettable SET sortorder='$i' WHERE id='$id' OR src='$id'");
		return true;
	}
	
	/**
	 * delete_images()
	 * Delete provided array of image ids, removing the source image and
	 * all related images (featured and thumbnails) */
	function delete_images ($images) {
		$db =& DB::get();
		$assettable = DBPREFIX."asset";
		foreach($images as $i => $id)
			$db->query("DELETE LOW_PRIORITY FROM $assettable WHERE id='$id' OR src='$id'");
		return true;
	}

} // end Product class

?>