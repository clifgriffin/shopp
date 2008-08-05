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

require_once("Product.php");

class Category extends DatabaseObject {
	static $table = "category";
	
	function Category ($id=false,$key="id") {
		$this->init(self::$table);
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
		
		$catalog_table = DatabaseObject::tablename(Catalog::$table);
		$product_table = DatabaseObject::tablename(Product::$table);
		$price_table = DatabaseObject::tablename(Price::$table);
		$asset_table = DatabaseObject::tablename(Asset::$table);
		$query = "SELECT p.id,p.name,p.summary,img.id AS thumbnail,MAX(pd.price) AS maxprice,MIN(pd.price) AS minprice,IF(pd.sale='on',1,0) AS onsale,MAX(pd.saleprice) as maxsaleprice,MIN(pd.saleprice) AS minsaleprice FROM $catalog_table AS catalog LEFT JOIN $product_table AS p ON catalog.product=p.id LEFT JOIN $price_table AS pd ON pd.product=p.id AND pd.type != 'N/A' LEFT JOIN $asset_table AS img ON img.parent=p.id AND img.context='product' AND img.datatype='thumbnail' AND img.sortorder=0 WHERE catalog.category=$this->id GROUP BY p.id";
		$this->products = $db->query($query,AS_ARRAY);
		
	}
	
	function tag ($property,$options=array()) {
		global $Shopp;
		$pages = $Shopp->Settings->get('pages');
		if (SHOPP_PERMALINKS) {
			$path = "/{$pages[0]['name']}/category";
			$imagepath = "/{$pages[0]['name']}/images/";
		} else {
			$page = "?page_id={$pages[0]['id']}";
			$imagepath = "?shopp_image=";
		}
		
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
				if (SHOPP_PERMALINKS) $link = $path.'/'.$this->uri.'/'.sanitize_title_with_dashes($product->name);
				else $link = $page.'&shopp_category='.$this->id.'&shopp_pid='.$product->id;
				
				$string = "";
				if (array_key_exists('link',$options)) $string .= '<a href="'.$link.'">';
				if (array_key_exists('thumbnail',$options) && !empty($product->thumbnail)) $string .= '<img src="'.$imagepath.$product->thumbnail.'" />';
				if (array_key_exists('name',$options)) $string .= $product->name;
				if (array_key_exists('link',$options)) $string .= "</a>";
				return $string;
				break;
		}
	}
	
} // end Category class

?>