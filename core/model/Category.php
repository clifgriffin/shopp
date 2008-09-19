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
		
		$catalogtable = DatabaseObject::tablename(Catalog::$table);
		$producttable = DatabaseObject::tablename(Product::$table);
		$pricetable = DatabaseObject::tablename(Price::$table);
		$discounttable = DatabaseObject::tablename(Discount::$table);
		$promotable = DatabaseObject::tablename(Promotion::$table);
		$assettable = DatabaseObject::tablename(Asset::$table);

		$query = "SELECT p.id,p.name,p.summary,
					img.id AS thumbnail,img.properties AS thumbnail_properties,
					SUM(DISTINCT IF(pr.type='Percentage Off',pr.discount,0))AS percentoff,
					SUM(DISTINCT IF(pr.type='Amount Off',pr.discount,0)) AS amountoff,
					if (pr.type='Free Shipping',1,0) AS freeshipping,
					if (pr.type='Buy X Get Y Free',pr.buyqty,0) AS buyqty,
					if (pr.type='Buy X Get Y Free',pr.getqty,0) AS getqty,
					MAX(pd.price) AS maxprice,MIN(pd.price) AS minprice,
					IF(pd.sale='on',1,IF (pr.discount > 0,1,0)) AS onsale,
					MAX(pd.saleprice) as maxsaleprice,MIN(pd.saleprice) AS minsaleprice 
					FROM $producttable AS p 
					LEFT JOIN $catalogtable AS catalog ON catalog.product=p.id
					LEFT JOIN $pricetable AS pd ON pd.product=p.id AND pd.type != 'N/A' 
					LEFT JOIN $discounttable AS dc ON dc.product=p.id AND dc.price=pd.id
					LEFT JOIN $promotable AS pr ON pr.id=dc.promo 
					LEFT JOIN $assettable AS img ON img.parent=p.id AND img.context='product' AND img.datatype='thumbnail' AND img.sortorder=0 
					WHERE {$filtering['where']} 
					GROUP BY p.id 
					ORDER BY {$filtering['order']} LIMIT {$filtering['limit']}";
		
		// Query without promotions for MySQL servers prior to 5
		if (version_compare($db->version,'5.0','<')) {
			$query = "SELECT p.id,p.name,p.summary,
						img.id AS thumbnail,img.properties AS thumbnail_properties,
						MAX(pd.price) AS maxprice,MIN(pd.price) AS minprice,
						IF(pd.sale='on',1,0) AS onsale,
						MAX(pd.saleprice) as maxsaleprice,MIN(pd.saleprice) AS minsaleprice 
						FROM $producttable AS p 
						LEFT JOIN $catalogtable AS catalog ON catalog.product=p.id
						LEFT JOIN $pricetable AS pd ON pd.product=p.id AND pd.type != 'N/A' 
						LEFT JOIN $assettable AS img ON img.parent=p.id AND img.context='product' AND img.datatype='thumbnail' AND img.sortorder=0 
						WHERE {$filtering['where']} 
						GROUP BY p.id 
						ORDER BY {$filtering['order']} LIMIT {$filtering['limit']}";
		} 

		$this->products = $db->query($query,AS_ARRAY);
		
		foreach ($this->products as &$product) {
			if ($product->maxsaleprice == 0) $product->maxsaleprice = $product->maxprice;
			if ($product->minsaleprice == 0) $product->minsaleprice = $product->minprice;
						
			if (!empty($product->percentoff)) {
				$product->maxsaleprice = $product->maxsaleprice - ($product->maxsaleprice * ($product->percentoff/100));
				$product->minsaleprice = $product->minsaleprice - ($product->minsaleprice * ($product->percentoff/100));
			}
			if (!empty($product->amountoff)){
				$product->maxsaleprice = $product->maxsaleprice - $product->amountoff;
				$product->minsaleprice = $product->minsaleprice - $product->amountoff;
			}
				
			
		}
		
	}
		
	function tag ($property,$options=array()) {
		global $Shopp;
		
		$page = $Shopp->link('catalog');
		if (SHOPP_PERMALINKS) {
			if ($page == get_bloginfo('siteurl')."/") {
				$pages = $Shopp->Settings->get('pages');
				$page .= $pages['catalog']['name']."/";
			}
			$imagepath = $Shopp->link('catalog')."images/";
		}
		else $imagepath = "?shopp_image=";
		
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

				if (SHOPP_PERMALINKS) $link = $page.$this->uri.'/'.sanitize_title_with_dashes($product->name);
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
				if ($product->onsale) {
					if (array_key_exists('saved',$options))
						$string .= money($product->minprice - $product->minsaleprice);
					if (array_key_exists('savings',$options))
						$string .= " (".percentage(100-(($product->minsaleprice/$product->minprice)*100)).")";
						
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
		$this->uri = $this->slug;
		$this->description = "New additions to the store";
		$this->smart = true;
		$loading = array('where'=>"p.id IS NOT NULL",'order'=>'p.created DESC');
		if (isset($options['show'])) $loading['limit'] = $options['show'];
		$this->load_products($loading);
	}
	
}

class FeaturedProducts extends Category {
	static $slug = "featured";
	
	function FeaturedProducts ($options=array()) {
		$this->name = "Featured Products";
		$this->parent = 0;
		$this->slug = FeaturedProducts::$slug;
		$this->uri = $this->slug;
		$this->description = "Featured products";
		$this->smart = true;
		$loading = array('where'=>"p.featured='on'",'order'=>'p.modified DESC');
		$this->load_products($loading);
	}
	
}

class OnSaleProducts extends Category {
	static $slug = "onsale";
	
	function OnSaleProducts ($options=array()) {
		$this->name = "On Sale";
		$this->parent = 0;
		$this->slug = OnSaleProducts::$slug;
		$this->uri = $this->slug;
		$this->description = "On sale products";
		$this->smart = true;
		$loading = array('where'=>"pd.sale='on' OR pr.discount > 0",'order'=>'p.modified DESC');
		if (isset($options['show'])) $loading['limit'] = $options['show'];
		$this->load_products($loading);
	}
	
}

?>