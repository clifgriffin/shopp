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
	var $images = array();
	var $specs = array();
	
	function Product ($id=false,$key="id") {
		$this->init('product');
		switch ($key) {
			case "slug": if ($this->loadby_slug($id)) return true;
			default: if ($this->load($id)) return true;
		}
		return false;
	}
	
	
	/**
	 * Load a single record by a slug name */
	function loadby_slug ($slug) {
		$db =& DB::get();
		
		$r = $db->query("SELECT * FROM $this->_table WHERE slug='$slug'");
		$this->populate($r);

		if (!empty($this->id)) return true;
		return false;
	}
	
	function load_prices () {
		$db =& DB::get();
		
		$table = DBPREFIX."price";
		if (empty($this->id)) return false;
		$this->prices = $db->query("SELECT * FROM $table WHERE product=$this->id ORDER BY sortorder ASC",AS_ARRAY);
		
		foreach ($this->prices as &$price) {
			$this->pricekey[$this->xorkey($price->options)] = &$price;
		}
		
		
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

	function save_categories ($updates) {
		$db =& DB::get();
		
		if (empty($updates)) $updates = array();
		
		$current = array();
		foreach ($this->categories as $catalog) $current[] = $catalog->category;

		$added = array_diff($updates,$current);
		$removed = array_diff($current,$updates);
		
		$table = DBPREFIX."catalog";
		
		foreach ($added as $id) {
			$db->query("INSERT $table SET category='$id',product='$this->id',created=now(),modified=now()");
		}
		
		foreach ($removed as $id) {
			$db->query("DELETE LOW_PRIORITY FROM $table WHERE category='$id' AND product='$this->id'"); 
		}
		
	}
	
	function load_images () {
		$db =& DB::get();
		
		$table = DBPREFIX."asset";
		if (empty($this->id)) return false;
		$images = $db->query("SELECT id,properties,datatype,src FROM $table WHERE parent=$this->id AND context='product' AND (datatype='image' OR datatype='feature' OR datatype='thumbnail') ORDER BY datatype,sortorder",AS_ARRAY);
		foreach ($images as $image) 
			$image->properties = unserialize($image->properties);
		$this->images = $images;
		return true;
	}
		
	
	/**
	 * xorkey
	 * There is no Zul only XOR! */
	function xorkey ($ids) {
		for ($key = 0,$i = 0; $i < count($ids); $i++) 
			$key = $key ^ ($ids[i]*101);  // Use a prime for variation
		return $key;
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
	
	/**
	 * Deletes the record associated with this object */
	function delete () {
		$db =& DB::get();
		
		// Delete record
		$id = $this->{$this->_key};
		if (!empty($id)) $db->query("DELETE FROM $this->_table WHERE $this->_key='$id'");
		
		// Delete from categories
		$table = DBPREFIX."catalog";
		$db->query("DELETE LOW_PRIORITY FROM $table WHERE product='$this->id'");

		// Delete prices
		$table = DBPREFIX."price";
		$db->query("DELETE LOW_PRIORITY FROM $table WHERE product='$this->id'");

		// Delete specs
		$table = DBPREFIX."spec";
		$db->query("DELETE LOW_PRIORITY FROM $table WHERE product='$this->id'");

		// Delete images/files
		$table = DBPREFIX."asset";
		$db->query("DELETE LOW_PRIORITY FROM $table WHERE parent='$this->id' AND context='product'");

	}
	
	

	function tag ($property,$options=array()) {

		switch ($property) {
			case "found": if (!empty($this->id)) return true; else return false; break;
			case "name": return $this->name; break;
			case "summary": return $this->summary; break;
			case "description": return wpautop($this->description); break;
			case "brand": return $this->brand; break;
			case "price":
				if (empty($this->prices)) $this->load_prices();
				if ($this->options > 1) {

					$min = $max = -1;
					foreach($this->prices as $pricetag) {
						if ($pricetag->type != "N/A") {
							if ($min == -1 || $pricetag->price < $min) $min = $pricetag->price;
							if ($max == -1 || $pricetag->price > $max) $max = $pricetag->price;
						}
					}
					
					if ($min == $max) return money($min);
					else return money($min)." &mdash; ".money($max);
					
				} else return money($this->prices[0]->price);
				break;
			case "onsale":
				if (empty($this->prices)) $this->load_prices();
				if (count($this->prices) > 1) {
					foreach($this->prices as $pricetag) {
						if ($pricetag->sale == "on" && $pricetag->type != "N/A") return true;
					}
					return false;
				} else return ($this->prices[0]->sale == "on" && $this->prices[0]->type != "N/A");
				break;
			case "saleprice":
				if (empty($this->prices)) $this->load_prices();
				if ($this->options > 1) {
					
					$min = $max = -1;
					foreach($this->prices as $pricetag) {
						if ($pricetag->type != "N/A") {
							if ($min == -1 || $pricetag->saleprice < $min) $min = $pricetag->saleprice;
							if ($max == -1 || $pricetag->saleprice > $max) $max = $pricetag->saleprice;
						}
					}
					
					if ($min == $max) return money($min);
					else return money($min)." &mdash; ".money($max);
					
				} else return money($this->prices[0]->saleprice);
				break;
			case "image":
				if (empty($this->images)) $this->load_images();
				$img = $this->images[0];
				$string .= '<img src="/shop/images/'.$img->id.'" alt="" width="'.$img->properties['width'].'" height="'.$img->properties['height'].'" />';
				return $string;
				break;
			case "thumbnails":
				if (empty($this->images)) $this->load_images();
				$string = "";
				foreach ($this->images as $img) 
					if ($img->datatype == "thumbnail") $string .= '<img src="/shop/images/'.$img->id.'" alt="" width="'.$img->properties['width'].'" height="'.$img->properties['height'].'" />';
				return $string;
				break;

			case "hasspecs": 
				if (empty($this->specs)) $this->load_specs();
				if (count($this->specs) > 0) return true; else return false; break;
			case "specs":			
				if (!$this->specloop) {
					reset($this->specs);
					$this->specloop = true;
				} else next($this->specs);

				if (current($this->specs)) return true;
				else {
					$this->specloop = false;
					return false;
				}
				break;
			case "spec":
				$spec = current($this->specs);
				$string = "";
				$separator = ": ";
				if (isset($options['separator'])) $separator = $options['separator'];
				if (array_key_exists('name',$options) && array_key_exists('content',$options))
					$string = "{$spec->name}{$separator}{$spec->content}";
				else if (array_key_exists('name',$options)) $string = $spec->name;
				else if (array_key_exists('content',$options)) $string = $spec->content;
				else $string = "{$spec->name}{$separator}{$spec->content}";
				return $string;
				break;
			case "hasoptions":
				if (isset($this->options['variations']) || isset($this->options['addons'])) return true; else return false; break;
			case "options":
				if (array_key_exists('variations',$options)) {
					if (!$this->optionloop) {
						reset($this->options['variations']);
						$this->optionloop = true;
					} else next($this->options['variations']);

					$this->option = current($this->options['variations']);

					if (current($this->options['variations'])) return true;
					else {
						$this->optionloop = false;
						return false;
					}
				}
				return false;
				break;
			case "option":
				$optionset = $this->option;
				$string = "";
				if (array_key_exists('label',$options)) $string .= $optionset['menu'];
				else {
					$string .= '<select name="options[]">';
					if (isset($options['default'])) $string .= '<option value="">'.$options['default'].'</option>';
					foreach ($optionset['label'] as $menuoption) $string .= '<option value="">'.$menuoption.'</option>';
					$string .= '</select>';
				}
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