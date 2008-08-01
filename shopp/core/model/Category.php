<?php
/**
 * Category class
 * 
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited,  9 April, 2008
 * @package shopp
 **/

class Category extends DatabaseObject {
	
	function Category ($id=false,$key="id") {
		$this->init('category');
		switch($key) {
			case "id": if ($this->load($id)) return true; break;
			case "slug": if ($this->loadby_slug($id)) return true; break;
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
	
	function load_products () {
		$db =& DB::get();
		
		$catalog_table = DBPREFIX."catalog";
		$product_table = DBPREFIX."product";
		$price_table = DBPREFIX."price";
		$asset_table = DBPREFIX."asset";
		$query = "SELECT p.id,p.name,p.summary,img.id AS thumbnail,MAX(pd.price) AS maxprice,MIN(pd.price) AS minprice,IF(pd.sale='on',1,0) AS onsale,MAX(pd.saleprice) as maxsaleprice,MIN(pd.saleprice) AS minsaleprice FROM $catalog_table AS catalog LEFT JOIN $product_table AS p ON catalog.product=p.id LEFT JOIN $price_table AS pd ON pd.product=p.id AND pd.type != 'N/A' LEFT JOIN $asset_table AS img ON img.parent=p.id AND img.context='product' AND img.datatype='thumbnail' GROUP BY p.id ORDER BY img.sortorder";
		$this->products = $db->query($query,AS_ARRAY);
		
	}
	
	function tag ($property,$options=array()) {
		switch ($property) {
			case "name": return $this->name; break;
			case "slug": return $this->slug; break;
			case "hasproducts": 
				if (empty($this->products)) $this->load_products();
				if (count($this->products) > 0) return true; else return false; break;
			case "products":			
				if (!$this->productloop) {
					reset($this->products);
					$this->productloop = true;
				} else next($this->products);

				if (current($this->products)) return true;
				else {
					$this->productloop = false;
					return false;
				}
				break;
			case "product":
				$product = current($this->products);
				$string = "";
				if (array_key_exists('link',$options)) $string .= '<a href="/shop/'.$this->slug.'/'.sanitize_title_with_dashes($product->name).'">';
				if (array_key_exists('thumbnail',$options) && !empty($product->thumbnail)) $string .= '<img src="/shop/images/'.$product->thumbnail.'" />';
				if (array_key_exists('name',$options)) $string .= $product->name;
				if (array_key_exists('link',$options)) $string .= "</a>";
				return $string;
				break;
		}
	}
	
} // end Category class

?>