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
	
	function Category ($id=false,$key=false) {
		$this->init(self::$table);
		if ($this->load($id,$key)) return true;
		return false;
	}
	
	function Smart ($slug) {
		$categories = array("new");
		if (in_array($slug,$categories)) return true;
	}
	
	/**
	 * Load a single record by a slug name */
	function loadby_slug ($slug) {
		$db = DB::get();
		
		$r = $db->query("SELECT * FROM $this->_table WHERE slug='$slug'");
		$this->populate($r);

		if (!empty($this->id)) return true;
		return false;
	}
	
	function load_products ($filtering=false) {
		$db = DB::get();
				
		if (!$filtering) $filtering = array();
		if (empty($filtering['where'])) $filtering['where'] = "catalog.category=$this->id AND (pd.inventory='off' OR (pd.inventory='on' && pd.stock > 0))";
		if (empty($filtering['order'])) $filtering['order'] = "p.name ASC";
		if (empty($filtering['limit'])) $filtering['limit'] = "25";
		
		$catalog_table = DatabaseObject::tablename(Catalog::$table);
		$product_table = DatabaseObject::tablename(Product::$table);
		$price_table = DatabaseObject::tablename(Price::$table);
		$asset_table = DatabaseObject::tablename(Asset::$table);
		$query = "SELECT p.id,p.name,p.summary,img.id AS thumbnail,img.properties AS thumbnail_properties,MAX(pd.price) AS maxprice,MIN(pd.price) AS minprice,IF(pd.sale='on',1,0) AS onsale,MAX(pd.saleprice) as maxsaleprice,MIN(pd.saleprice) AS minsaleprice FROM $catalog_table AS catalog LEFT JOIN $product_table AS p ON catalog.product=p.id LEFT JOIN $price_table AS pd ON pd.product=p.id AND pd.type != 'N/A' LEFT JOIN $asset_table AS img ON img.parent=p.id AND img.context='product' AND img.datatype='thumbnail' AND img.sortorder=0 WHERE {$filtering['where']} GROUP BY p.id ORDER BY {$filtering['order']} LIMIT {$filtering['limit']}";
		$this->products = $db->query($query,AS_ARRAY);
	}
		
	function tag ($property,$options=array()) {
		global $Shopp;
		$pages = $Shopp->Settings->get('pages');
		if (SHOPP_PERMALINKS) {
			$path = "/{$pages['catalog']['name']}";
			$imagepath = "/{$pages['catalog']['name']}/images/";
		} else {
			$page = "?page_id={$pages['catalog']['id']}";
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
			case "row":
				if (key($this->products) % $options['products'] == 0) return true;
				else return false;
				break;
			case "product":
				$product = current($this->products);
				if (SHOPP_PERMALINKS) $link = $path.$this->uri.'/'.sanitize_title_with_dashes($product->name);
				else {
					if (isset($Shopp->Category->smart)) $link = $page.'&shopp_category='.$this->slug.'&shopp_pid='.$product->id;
					else $link = $page.'&shopp_category='.$this->id.'&shopp_pid='.$product->id;
				}
				
				$thumbprops = unserialize($product->thumbnail_properties);
				
				$string = "";
				if (array_key_exists('link',$options)) $string .= '<a href="'.$link.'">';
				if (array_key_exists('thumbnail',$options)) {
					if (!empty($product->thumbnail)) {
						$string .= '<img src="'.$imagepath.$product->thumbnail.'" alt="'.$product->name.' (thumbnail)" width="'.$thumbprops['width'].'" height="'.$thumbprops['height'].'" />';
					}
				}
				if (array_key_exists('name',$options)) $string .= $product->name;
				if (array_key_exists('link',$options)) $string .= "</a>";
				if (array_key_exists('price',$options)) {
					if ($product->onsale) {
						if ($product->minsaleprice != $product->maxsaleprice) $string .= "from ";
						$string .= money($product->minsaleprice);
					} else {
						if ($product->minprice != $product->maxprice) $string .= "from ";
						$string .= money($product->minprice);
					}
				}
				return $string;
				break;				
		}
	}
	
} // end Category class

class NewProducts extends Category {
	static $slug = "new";
	
	function NewProducts ($options=array()) {
		$this->name = "New Products";
		$this->parent = 0;
		$this->slug = NewProducts::$slug;
		$this->uri = "/$this->slug";
		$this->description = "New additions to the store";
		$this->smart = true;
		if (isset($options['show']))
			$this->load_products(array('where'=>"1",'order'=>'p.created DESC','limit'=>$options['show']));
		else $this->load_products(array('where'=>"1",'order'=>'p.created DESC'));
	}
	
}

class FeaturedProducts extends Category {
	static $slug = "featured";
	
	function FeaturedProducts ($options=array()) {
		$this->name = "Featured Products";
		$this->parent = 0;
		$this->slug = FeaturedProducts::$slug;
		$this->uri = "/$this->slug";
		$this->description = "Featured products";
		$this->smart = true;
		if (isset($options['show']))
			$this->load_products(array('where'=>"p.featured='on'",'order'=>'p.modified DESC','limit'=>$options['show']));
		else $this->load_products(array('where'=>"p.featured='on'",'order'=>'p.modified DESC'));
	}
	
}

class OnSaleProducts extends Category {
	static $slug = "onsale";
	
	function OnSaleProducts ($options=array()) {
		$this->name = "On Sale";
		$this->parent = 0;
		$this->slug = OnSaleProducts::$slug;
		$this->uri = "/$this->slug";
		$this->description = "On sale products";
		$this->smart = true;
		if (isset($options['show']))
			$this->load_products(array('where'=>"pd.sale='on'",'order'=>'p.modified DESC','limit'=>$options['show']));
		else $this->load_products(array('where'=>"pd.sale='on'",'order'=>'p.modified DESC'));
	}
	
}

?>