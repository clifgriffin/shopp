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
	var $loaded = false;
	
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
		global $Shopp,$wp;
		$db = DB::get();

		$this->paged = false;
		$this->pagination = $Shopp->Settings->get('catalog_pagination');
		$this->page = $wp->query_vars['paged'];
		if (empty($this->page)) $this->page = 1;

		$limit = 1000; // Hard product limit per category to keep resources "reasonable"
		if (!$filtering) $filtering = array();
		if (!empty($filtering['columns'])) $filtering['columns'] = ", ".$filtering['columns'];
		if (empty($filtering['where'])) $filtering['where'] = "catalog.category=$this->id AND (pd.inventory='off' OR (pd.inventory='on' && pd.stock > 0))";
		if (empty($filtering['order'])) $filtering['order'] = "p.name ASC";
		if (empty($filtering['limit'])) {
			if ($this->pagination > 0) {
				if( !$this->pagination || $this->pagination < 0 )
					$this->pagination = $limit;
				$start = ($this->pagination * ($this->page-1)); 
				
				$filtering['limit'] = "$start,$this->pagination";
			} else $filtering['limit'] = $limit;
		} else $limit = (int)$filtering['limit'];

		$catalogtable = DatabaseObject::tablename(Catalog::$table);
		$producttable = DatabaseObject::tablename(Product::$table);
		$pricetable = DatabaseObject::tablename(Price::$table);
		$discounttable = DatabaseObject::tablename(Discount::$table);
		$promotable = DatabaseObject::tablename(Promotion::$table);
		$assettable = DatabaseObject::tablename(Asset::$table);

		$columns = "p.id,p.name,p.summary,p.description,
					img.id AS thumbnail,img.properties AS thumbnail_properties,
					SUM(DISTINCT IF(pr.type='Percentage Off',pr.discount,0))AS percentoff,
					SUM(DISTINCT IF(pr.type='Amount Off',pr.discount,0)) AS amountoff,
					if (pr.type='Free Shipping',1,0) AS freeshipping,
					if (pr.type='Buy X Get Y Free',pr.buyqty,0) AS buyqty,
					if (pr.type='Buy X Get Y Free',pr.getqty,0) AS getqty,
					MAX(pd.price) AS maxprice,MIN(pd.price) AS minprice,
					IF(pd.sale='on',1,IF (pr.discount > 0,1,0)) AS onsale,
					MAX(pd.saleprice) as maxsaleprice,MIN(pd.saleprice) AS minsaleprice,
					IF(pd.inventory='on',1,0) AS inventory,
					SUM(pd.stock) as stock";

		// Query without promotions for MySQL servers prior to 5
		if (version_compare($db->version,'5.0','<')) {
			$columns = "p.id,p.name,p.summary,
						img.id AS thumbnail,img.properties AS thumbnail_properties,
						MAX(pd.price) AS maxprice,MIN(pd.price) AS minprice,
						IF(pd.sale='on',1,0) AS onsale,
						MAX(pd.saleprice) as maxsaleprice,MIN(pd.saleprice) AS minsaleprice,
						IF(pd.inventory='on',1,0) AS inventory,
						SUM(pd.stock) as stock";
		} 

		$query = "SELECT $columns{$filtering['columns']}
					FROM $producttable AS p 
					LEFT JOIN $catalogtable AS catalog ON catalog.product=p.id
					LEFT JOIN $pricetable AS pd ON pd.product=p.id AND pd.type != 'N/A' 
					LEFT JOIN $discounttable AS dc ON dc.product=p.id AND dc.price=pd.id
					LEFT JOIN $promotable AS pr ON pr.id=dc.promo 
					LEFT JOIN $assettable AS img ON img.parent=p.id AND img.context='product' AND img.datatype='thumbnail' AND img.sortorder=0 
					{$filtering['joins']}
					WHERE {$filtering['where']} AND p.published='on'
					GROUP BY p.id 
					ORDER BY {$filtering['order']} LIMIT {$filtering['limit']}";
		
		if ($this->pagination > 0 && $limit > $this->pagination) {
			$count = "SELECT count(DISTINCT p.id) AS count
						FROM $producttable AS p 
						LEFT JOIN $catalogtable AS catalog ON catalog.product=p.id
						LEFT JOIN $pricetable AS pd ON pd.product=p.id AND pd.type != 'N/A' 
						LEFT JOIN $discounttable AS dc ON dc.product=p.id AND dc.price=pd.id
						LEFT JOIN $promotable AS pr ON pr.id=dc.promo 
						LEFT JOIN $assettable AS img ON img.parent=p.id AND img.context='product' AND img.datatype='thumbnail' AND img.sortorder=0 
						{$filtering['joins']}
						WHERE {$filtering['where']} AND p.published='on";

			$total = $db->query($count);
			$this->total = $total->count;
			$this->pages = ceil($this->total / $this->pagination);
			if ($this->pages > 1) $this->paged = true;			
		} 

		$this->products = $db->query($query,AS_ARRAY);
		if ($this->pagination == 0 || $limit < $this->pagination) 
			$this->total = count($this->products);
		
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
		
		$this->loaded = true;
		
	}
		
	function rss () {
		global $Shopp;
		$db = DB::get();

		if (!$this->products) $this->load_products();

		$baseurl = $Shopp->link('catalog');
		if (SHOPP_PERMALINKS) {
			if ($baseurl == get_bloginfo('siteurl')."/") {
				$pages = $Shopp->Settings->get('pages');
				$baseurl .= $pages['catalog']['name']."/";
			}
			$imagepath = $Shopp->link('catalog')."images/";
		}
		else $imagepath = "?shopp_image=";
		
		$rssurl = $baseurl.((SHOPP_PERMALINKS)?'feed':'&shopp_lookup=products-rss');
		$imageurl = $baseurl."/".((SHOPP_PERMALINKS)?'?shopp_image=':'&shopp_image=');
		$rss = array('title' => get_bloginfo('name')." ".$this->name,
			 			'link' => $rssurl,
					 	'description' => $this->description,
						'sitename' => get_bloginfo('name').' ('.get_bloginfo('siteurl').')');
		$items = array();
		foreach ($this->products as $product) {
			$product->thumbnail_properties = unserialize($product->thumbnail_properties);
			$item = array();
			$item['title'] = $product->name;
			$item['link'] = htmlentities($baseurl.((SHOPP_PERMALINKS)?$product->id:'&shopp_pid='.$product->id));
			$item['description'] = "<![CDATA[";
			if (!empty($product->thumbnail)) {
				$item['description'] .= '<a href="'.$item['link'].'" title="'.$product->name.'">';
				$item['description'] .= '<img src="'.$imageurl.$product->thumbnail.'" alt="'.$product->name.'" width="'.$product->thumbnail_properties['width'].'" height="'.$product->thumbnail_properties['height'].'" style="float: left; margin: 0 10px 0 0;" />';
				$item['description'] .= '</a>';
				$item['g:image_link'] = $imageurl.$product->thumbnail;
			}

			$pricing = "";
			if ($product->onsale) {
				if ($product->minsaleprice != $product->maxsaleprice) $pricing .= "from ";
				$pricing .= money($product->minsaleprice);
			} else {
				if ($product->minprice != $product->maxprice) $pricing .= "from ";
				$pricing .= money($product->minprice);
			}
			$item['g:price'] = number_format(($product->onsale)?$product->minsaleprice:$product->minprice,2);
			$item['g:price_type'] = "starting";

			$item['description'] .= "<p><big><strong>$pricing</strong></big></p>";
			$item['description'] .= "<p>$product->description</p>";
			$item['description'] .= "]]>";
			$item['g:quantity'] = $product->stock;
			
			$items[] = $item;
		}
		$rss['items'] = $items;

		return $rss;
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
			case "description": return wpautop($this->description); break;
			case "link": return (SHOPP_PERMALINKS)?"$page"."category/$this->uri":"$page&shopp_category=$this->id"; break;
			case "total": return $this->total; break;
			case "hasproducts": 
				if (!$this->loaded) $this->load_products();
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
				if (empty($options['products'])) $options['products'] = $Shopp->Settings->get('row_products');
				if (key($this->products) % $options['products'] == 0) return true;
				else return false;
				break;
			case "pagination":
				if (!$this->paged) return "";
				
				$navlimit = 1000;
				if (!empty($options['show'])) $navlimit = $options['show'];

				$before = "<div>".__('Pages: ');
				if (!empty($options['before'])) $before = $options['before'];

				$after = "</div>";
				if (!empty($options['after'])) $after = $options['after'];

				$string = "";
				if ($this->pages > 1) {

					if ( $this->pages > $navlimit ) $visible_pages = $navlimit + 1;
					else $visible_pages = $this->pages + 1;
					$jumps = ceil($visible_pages/2);
					$string .= $before;

					$string .= '<ul class="paging">';
					if ( $this->page <= floor(($navlimit) / 2) ) {
						$i = 1;
					} else {
						$i = $this->page - floor(($navlimit) / 2);
						$visible_pages = $this->page + floor(($navlimit) / 2) + 1;
						if ($visible_pages > $this->pages) $visible_pages = $this->pages + 1;
						if ($i > 1) {
							$link = (SHOPP_PERMALINKS)?
								"$page"."category/$this->uri/page/$i/":
								"$page&shopp_category=$this->slug&paged=$i";
							$string .= '<li><a href="'.$link.'">1</a></li>';

							$pagenum = ($this->page - $jumps);
							if ($pagenum < 1) $pagenum = 1;
							$link = (SHOPP_PERMALINKS)?
								"$page"."category/$this->uri/page/$pagenum/":
								"$page&shopp_category=$this->slug&paged=$pagenum";
								
							$string .= '<li><a href="'.$link.'">&laquo;</a></li>';
						}
					}

					while ($i < $visible_pages) {
						$link = (SHOPP_PERMALINKS)?
							"$page"."category/$this->uri/page/$i/":
							"$page&shopp_category=$this->slug&paged=$i";
						if ( $i == $this->page ) $string .= '<li><span>'.$i.'</span></li>';
						else $string .= '<li><a href="'.$link.'">'.$i.'</a></li>';
						$i++;
					}
					if ($this->pages > $visible_pages) {
						$pagenum = ($this->page + $jumps);
						if ($pagenum > $this->pages) $pagenum = $this->pages;
						$link = (SHOPP_PERMALINKS)?
							"$page"."category/$this->uri/page/$pagenum/":
							"$page&shopp_category=$this->slug&paged=$pagenum";
						$string .= '<li><a href="'.$link.'">&raquo;</a></li>';

						$link = (SHOPP_PERMALINKS)?
							"$page"."category/$this->uri/page/$this->pages/":
							"$page&shopp_category=$this->slug&paged=$this->pages";
						$string .= '<li><a href="'.$link.'">'.$this->pages.'</a></li>';	
					}
					$string .= '</ul>';
					$string .= $after;
				}
				return $string;
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
				if (array_key_exists('link',$options)) $string .= '<a href="'.$link.'" title="'.$product->name.'">';
				if (array_key_exists('thumbnail',$options)) {
					if (!empty($product->thumbnail)) {
						$string .= '<img src="'.$imagepath.$product->thumbnail.'" alt="'.$product->name.' (thumbnail)" width="'.$thumbprops['width'].'" height="'.$thumbprops['height'].'" />';
					}
				}
				if (array_key_exists('name',$options)) $string .= $product->name;
				if (array_key_exists('summary',$options)) $string .= $product->summary;
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
				if (array_key_exists('addtocart',$options) || array_key_exists('buynow',$options)) {
					if (!isset($options['label'])) $options['label'] = "Add to Cart";
					
					if ($product->inventory == "1" && $product->stock == 0) {
						$string .= $Shopp->Settings->get('outofstock_text');
					} else {
						$string .= '<form action="" method="post">';
						$string .= '<input type="hidden" name="product" value="'.$product->id.'" />';
						$string .= '<input type="hidden" name="cart" value="add" />';
						$string .= '<input type="submit" name="addtocart" value="'.$options['label'].'" class="addtocart" />';
						$string .= '</form>';
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
		$this->uri = $this->slug;
		$this->description = "New additions to the store";
		$this->smart = true;
		$loading = array('where'=>"p.id IS NOT NULL",'order'=>'p.created DESC');
		if (isset($options['columns'])) $loading['columns'] = $options['columns'];
		if (isset($options['show'])) $loading['limit'] = $options['show'];
		if (!isset($options['noload'])) $this->load_products($loading);
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
		if (isset($options['show'])) $loading['limit'] = $options['show'];
		if (!isset($options['noload'])) $this->load_products($loading);
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
		if (!isset($options['noload'])) $this->load_products($loading);
	}
	
}

class BestsellerProducts extends Category {
	static $slug = "bestsellers";
	
	function BestsellerProducts ($options=array()) {
		$this->name = "Bestsellers";
		$this->parent = 0;
		$this->slug = BestsellerProducts::$slug;
		$this->uri = $this->slug;
		$this->description = "Best selling products";
		$this->smart = true;
		$purchasedtable = DatabaseObject::tablename(Purchased::$table);
		
		$loading = array(
			'columns'=>'count(DISTINCT pur.id) AS sold',
			'joins'=>"LEFT JOIN $purchasedtable AS pur ON p.id=pur.product",
			'where'=>"TRUE",
			'order'=>'sold DESC');
		if (isset($options['where'])) $loading['where'] = $options['where'];
		if (isset($options['show'])) $loading['limit'] = $options['show'];
		if (!isset($options['noload'])) $this->load_products($loading);
	}
	
}

class SearchResults extends Category {
	static $slug = "search-results";
	
	function SearchResults ($options=array()) {
		if (empty($options['search'])) $options['search'] = "(no search terms)";
		$this->name = "Search Results for &quot;".$options['search']."&quot;";
		$this->parent = 0;
		$this->slug = SearchResults::$slug;
		$this->uri = $this->slug;
		$this->description = "Results for &quot;".$options['search']."&quot;";
		$this->smart = true;
		$loading = array(
			'columns'=> "MATCH(p.name,p.summary,p.description) AGAINST ('{$options['search']}' IN BOOLEAN MODE) AS score",
			'where'=>"MATCH(p.name,p.summary,p.description) AGAINST ('{$options['search']}' IN BOOLEAN MODE)",
			'order'=>'score DESC');
		if (isset($options['show'])) $loading['limit'] = $options['show'];
		if (!isset($options['noload'])) $this->load_products($loading);
	}
	
}

class TagProducts extends Category {
	static $slug = "tag";
	
	function TagProducts ($options=array()) {
		$this->tag = $options['tag'];
		$this->name = "Products tagged &quot;".$options['tag']."&quot;";
		$this->parent = 0;
		$this->slug = TagProducts::$slug;
		$this->uri = $options['tag'];
		$this->description = "Products tagged &quot;".$options['tag']."&quot;";
		$this->smart = true;
		$tagtable = DatabaseObject::tablename(Tag::$table);
		$loading = array(
			'joins'=>"LEFT JOIN $tagtable AS t ON t.id=catalog.tag",
			'where'=>"catalog.tag=t.id AND t.name='{$options['tag']}'");
		if (isset($options['show'])) $loading['limit'] = $options['show'];
		if (!isset($options['noload'])) $this->load_products($loading);
	}
	
}


?>