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

require("Spec.php");
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
		
		$table = DBPREFIX."price";
		if (empty($this->id)) return false;
		$this->prices = $db->query("SELECT * FROM $table WHERE product=$this->id ORDER BY sortorder ASC",AS_ARRAY);
		return true;
	}
	
	function load_specs () {
		$db =& DB::get();
		
		$table = DBPREFIX."spec";
		if (empty($this->id)) return false;
		$this->specs = $db->query("SELECT * FROM $table WHERE product=$this->id ORDER BY sortorder ASC",AS_ARRAY);
		return true;
	}

	function load_categories () {
		$db =& DB::get();
		
		$table = DBPREFIX."catalog";
		if (empty($this->id)) return false;
		$this->categories = $db->query("SELECT * FROM $table WHERE product=$this->id",AS_ARRAY);
		return true;
	}

	function load_images () {
		$db =& DB::get();
		
		$table = DBPREFIX."asset";
		if (empty($this->id)) return false;
		$images = $db->query("SELECT id,properties,datatype FROM $table WHERE parent=$this->id AND type='product' AND (datatype='image' OR datatype='feature' OR datatype='thumbnail') ORDER BY sortorder",AS_ARRAY);
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

		$table = DBPREFIX."catalog";
		
		foreach ($added as $id) {
			$db->query("INSERT $table SET category='$id',product='$this->id',created=now(),modified=now()");
		}
		
		foreach ($removed as $id) {
			$db->query("DELETE LOW_PRIORITY FROM $table WHERE category='$id' AND product='$this->id'"); 
		}
		
	}
	
	/**
	 * save_imageorder()
	 * Updates the sortorder of image assets (source, featured and thumbnails)
	 * based on the provided array of image ids */
	function save_imageorder ($ordering) {
		$db =& DB::get();
		$table = DBPREFIX."asset";
		foreach ($ordering as $i => $id) 
			$db->query("UPDATE LOW_PRIORITY $table SET sortorder='$i' WHERE id='$id' OR src='$id'");
		return true;
	}
	
	/**
	 * delete_images()
	 * Delete provided array of image ids, removing the source image and
	 * all related images (featured and thumbnails) */
	function delete_images ($images) {
		$db =& DB::get();
		$table = DBPREFIX."asset";
		foreach($images as $i => $id)
			$db->query("DELETE LOW_PRIORITY FROM $table WHERE id='$id' OR src='$id'");
		return true;
	}

	function tag ($property,$options=array()) {
		
		switch ($property) {
			case "found": if (!empty($this->id)) return true; else return false; break;
			case "name": return $this->name; break;
			case "description": return $this->description; break;
			case "details": return $this->details; break;
			case "brand": return $this->brand; break;
			case "price":
				if ($this->options > 1) {

					$min = $max = -1;
					foreach($this->prices as $pricetag) {
						if ($min == -1 || $pricetag->price < $min) $min = $pricetag->price;
						if ($max == -1 || $pricetag->price > $max) $max = $pricetag->price;
					}
					
					if ($min == $max) return money($min);
					else return money($min)." &mdash; ".money($max);
					
				} else return money($this->prices[0]->price);
				break;
			case "onsale":
				if ($this->options > 1) {
					foreach($this->prices as $pricetag) {
						if ($pricetag->sale == "on") return true;
					}
				} else return ($this->prices[0]->sale == "on");
				break;
			case "saleprice":
				if ($this->options > 1) {
					
					$min = $max = -1;
					foreach($this->prices as $pricetag) {
						if ($min == -1 || $pricetag->saleprice < $min) $min = $pricetag->saleprice;
						if ($max == -1 || $pricetag->saleprice > $max) $max = $pricetag->saleprice;
					}
					
					if ($min == $max) return money($min);
					else return money($min)." &mdash; ".money($max);
					
				} else return money($this->prices[0]->saleprice);
				break;
			case "hasoptions": if (count($this->price) > 1) return true; else return false; break;
			case "photo":
				$this->load_images();
				$img = $this->images[0];
				$string .= '<img src="/?lookup=asset&id='.$img->id.'" alt="" width="'.$img->properties['width'].'" height="'.$img->properties['height'].'" />';
				return $string;
				break;
			case "addtocart":
				$string = "";
				$string .= '<input type="hidden" name="product" value="'.$this->id.'" />';
				$string .= '<input type="hidden" name="price" value="'.$this->prices[0]->id.'" />';
				$string .= '<input type="hidden" name="cart" value="add" />';
				$string .= '<input type="button" name="addtocart" value="Add to Cart" class="addtocart" />';
				return $string;
		}
		
		
	}

} // end Product class

?>