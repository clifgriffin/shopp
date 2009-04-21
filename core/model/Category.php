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
	var $children = false;
	var $child = false;
	var $parent = 0;
	var $description = "";
	var $imguri = "";
	var $productidx = 0;
	var $productloop = false;
	var $products = array();
	var $pricing = array();
	var $filters = array();
	var $loading = array();
	var $images = array();
	var $facetedmenus = "off";
	
	function Category ($id=false,$key=false) {
		global $Shopp;
		$this->init(self::$table);

		if (!$id) return;
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
	
	function load_children() {
		if (isset($this->smart)) return false;
		$db = DB::get();
		$catalog_table = DatabaseObject::tablename(Catalog::$table);
		$children = $db->query("SELECT cat.*,count(sc.product) AS total FROM $this->_table AS cat LEFT JOIN $catalog_table AS sc ON sc.category=cat.id WHERE cat.uri like '%$this->uri%' AND cat.id <> $this->id GROUP BY cat.id ORDER BY parent DESC,name ASC",AS_ARRAY);
		$children = sort_tree($children,$this->id);
		foreach ($children as &$child) {
			$this->children[$child->id] = new Category();
			$this->children[$child->id]->populate($child);
			$this->children[$child->id]->depth = $child->depth;
			$this->children[$child->id]->total = $child->total;
		}

		if (!empty($this->children)) return true;
		return false;
	}
	
	function load_images () {
		global $Shopp;
		$db = DB::get();
		
		$ordering = $Shopp->Settings->get('product_image_order');
		$orderby = $Shopp->Settings->get('product_image_orderby');
		
		if ($ordering == "RAND()") $orderby = $ordering;
		else $orderby .= ' '.$ordering;
		$table = DatabaseObject::tablename(Asset::$table);
		if (empty($this->id)) return false;
		$images = $db->query("SELECT id,name,properties,datatype,src FROM $table WHERE parent=$this->id AND context='category' AND (datatype='image' OR datatype='small' OR datatype='thumbnail') ORDER BY $orderby",AS_ARRAY);

		$this->images = array();
		// Organize images into groupings by type
		foreach ($images as $key => &$image) {
			if (empty($this->images[$image->datatype])) $this->images[$image->datatype] = array();
			$image->properties = unserialize($image->properties);
			$image->uri = $Shopp->imguri.$image->id;
			$this->images[$image->datatype][] = $image;
		}
		$this->thumbnail = $this->images['thumbnail'][0];
		return true;
	}
	
	/**
	 * save_imageorder()
	 * Updates the sortorder of image assets (source, featured and thumbnails)
	 * based on the provided array of image ids */
	function save_imageorder ($ordering) {
		$db = DB::get();
		$table = DatabaseObject::tablename(Asset::$table);
		foreach ($ordering as $i => $id) 
			$db->query("UPDATE LOW_PRIORITY $table SET sortorder='$i' WHERE id='$id' OR src='$id'");
		return true;
	}
	
	/**
	 * link_images()
	 * Updates the product id of the images to link to the product 
	 * when the product being saved is new (has no previous id assigned) */
	function link_images ($images) {
		$db = DB::get();
		$table = DatabaseObject::tablename(Asset::$table);
		
		$query = "UPDATE $table SET parent='$this->id',context='category' WHERE ";
		foreach ($images as $i => $id) {
			if ($i > 0) $query .= " OR ";
			$query .= "id=$id OR src=$id";
		}
		$db->query($query);
		return true;
	}
	
	
	/**
	 * delete_images()
	 * Delete provided array of image ids, removing the source image and
	 * all related images (featured and thumbnails) */
	function delete_images ($images) {
		$db = DB::get();
		$table = DatabaseObject::tablename(Asset::$table);
		
		$query = "DELETE LOW_PRIORITY FROM $table WHERE ";
		foreach ($images as $i => $id) {
			if ($i > 0) $query .= " OR ";
			$query .= "id=$id OR src=$id";
		}
		$db->query($query);
		return true;
	}
	
	function load_products ($loading=false) {
		global $Shopp,$wp;
		$db = DB::get();
		
		$this->paged = false;
		$this->pagination = $Shopp->Settings->get('catalog_pagination');
		$this->page = (isset($wp->query_vars['paged']))?$wp->query_vars['paged']:1;
		
		if (empty($this->page)) $this->page = 1;
		
		$limit = 1000; // Hard product limit per category to keep resources "reasonable"
		
		if (!$loading) $loading = $this->loading;
		else $loading = array_merge($this->loading,$loading);
		
		if (!empty($loading['columns'])) $loading['columns'] = ", ".$loading['columns'];
		else $loading['columns'] = '';
		
		// Handle default WHERE clause
		if (empty($loading['where'])) $loading['where'] = "catalog.category=$this->id";
		if (empty($loading['catalog'])) $loading['catalog'] = "category";
		switch($loading['catalog']) {
			case "tags": $loading['catalog'] = ""; break;
			default: $loading['catalog'] = "AND catalog.category != 0";
		}

		if (!isset($loading['having'])) $loading['having'] = '';
		// Always hide inventory tracked products with no inventory
		if (!isset($loading['nostock']) && ($Shopp->Settings->get('outofstock_catalog') == "off"))
			$loading['having'] = "HAVING (inventory=0 OR (inventory=1 AND stock > 0))";

		if (!isset($loading['joins'])) $loading['joins'] = '';
		if (!empty($Shopp->Cart->data->Category[$this->slug])) {
			$spectable = DatabaseObject::tablename(Spec::$table);
			
			$f = 1;
			$filters = "";
			foreach ($Shopp->Cart->data->Category[$this->slug] as $facet => $value) {
				if (empty($value)) continue;
				$specalias = "spec".($f++);

				// Handle Number Range filtering
				$match = "";
				if (!is_array($value) && 
						preg_match('/^.*?(\d+[\.\,\d]*).*?\-.*?(\d+[\.\,\d]*).*$/',$value,$matches)) {
					if ($facet == "Price") { // Prices require complex matching on price line entries
						$min = floatvalue($matches[1]);
						$max = floatvalue($matches[2]);
						if ($matches[1] > 0) $match .= " ((onsale=0 AND (minprice >= $min OR maxprice >= $min)) OR (onsale=1 AND (minsaleprice >= $min OR maxsaleprice >= $min)))";
						if ($matches[2] > 0) $match .= ((empty($match))?"":" AND ")." ((onsale=0 AND (minprice <= $max OR maxprice <= $max)) OR (onsale=1 AND (minsaleprice <= $max OR maxsaleprice <= $max)))";
					} else { // Spec-based numbers are somewhat more straightforward
						if ($matches[1] > 0) $match .= "$specalias.numeral >= {$matches[1]}";
						if ($matches[2] > 0) $match .= ((empty($match))?"":" AND ")."$specalias.numeral <= {$matches[2]}";
					}
				} else $match = "$specalias.content='$value'"; // No range, direct value match
		
				// Use HAVING clause for filtering by pricing information 
				// because of data aggregation
				if ($facet == "Price") { 
					$loading['having'] .= (empty($loading['having'])?'HAVING ':' AND ').$match;
					continue;
				}
				
				$loading['joins'] .= " LEFT JOIN $spectable AS $specalias ON $specalias.product=p.id AND $specalias.name='$facet'";
				$filters .= (empty($filters))?$match:" AND ".$match;
			}
			if (!empty($filters)) $loading['where'] .= " AND ($filters)";
			
		}
		
		$defaultOrder = $Shopp->Settings->get('default_product_order');
		if (empty($defaultOrder)) $defaultOrder = "title";
		$ordering = isset($Shopp->Cart->data->Category['orderby'])?
						$Shopp->Cart->data->Category['orderby']:$defaultOrder;
		if (!empty($loading['order'])) $ordering = $loading['order'];
		switch ($ordering) {
			case "bestselling":
				$purchasedtable = DatabaseObject::tablename(Purchased::$table);
				$loading['columns'] .= ',count(DISTINCT pur.id) AS sold';
				$loading['joins'] .= "LEFT JOIN $purchasedtable AS pur ON p.id=pur.product";
				$loading['order'] = "sold DESC"; 
				break;
			case "highprice": $loading['order'] = "pd.price DESC"; break;
			case "lowprice": $loading['order'] = "pd.price ASC"; break;
			case "newest": $loading['order'] = "pd.created DESC"; break;
			case "oldest": $loading['order'] = "pd.created ASC"; break;
			case "random": $loading['order'] = "RAND()"; break;
			case "": 
			case "title": $loading['order'] = "p.name ASC"; break;
		}
		
		if (empty($loading['limit'])) {
			if ($this->pagination > 0) {
				if( !$this->pagination || $this->pagination < 0 )
					$this->pagination = $limit;
				$start = ($this->pagination * ($this->page-1)); 
				
				$loading['limit'] = "$start,$this->pagination";
			} else $loading['limit'] = $limit;
		} else $limit = (int)$loading['limit'];
		
		$catalogtable = DatabaseObject::tablename(Catalog::$table);
		$producttable = DatabaseObject::tablename(Product::$table);
		$pricetable = DatabaseObject::tablename(Price::$table);
		$discounttable = DatabaseObject::tablename(Discount::$table);
		$promotable = DatabaseObject::tablename(Promotion::$table);
		$assettable = DatabaseObject::tablename(Asset::$table);
		
		$columns = "p.*,
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
			$columns = "p.*,
						img.id AS thumbnail,img.properties AS thumbnail_properties,
						MAX(pd.price) AS maxprice,MIN(pd.price) AS minprice,
						IF(pd.sale='on',1,0) AS onsale,
						MAX(pd.saleprice) as maxsaleprice,MIN(pd.saleprice) AS minsaleprice,
						IF(pd.inventory='on',1,0) AS inventory,
						SUM(pd.stock) as stock";
		} 
		
		$query = "SELECT SQL_CALC_FOUND_ROWS $columns{$loading['columns']}
					FROM $producttable AS p 
					LEFT JOIN $catalogtable AS catalog ON catalog.product=p.id
					LEFT JOIN $pricetable AS pd ON pd.product=p.id AND pd.type != 'N/A' 
					LEFT JOIN $discounttable AS dc ON dc.product=p.id AND dc.price=pd.id
					LEFT JOIN $promotable AS pr ON pr.id=dc.promo 
					LEFT JOIN $assettable AS img ON img.parent=p.id AND img.context='product' AND img.datatype='thumbnail' AND img.sortorder=0 
					{$loading['joins']}
					WHERE ({$loading['where']}) {$loading['catalog']} AND p.published='on' AND pd.type != 'N/A'
					GROUP BY p.name {$loading['having']}
					ORDER BY {$loading['order']} LIMIT {$loading['limit']}";
		
		// Execute the main category products query
		$products = $db->query($query,AS_ARRAY);

		if ($this->pagination > 0 && $limit > $this->pagination) {
			$total = $db->query("SELECT FOUND_ROWS() as count");
			$this->total = $total->count;
			$this->pages = ceil($this->total / $this->pagination);
			if ($this->pages > 1) $this->paged = true;			
		}

		if ($this->pagination == 0 || $limit < $this->pagination) 
			$this->total = count($this->products);
		
		$this->pricing['min'] = 0;
		$this->pricing['max'] = 0;

		$prices = array();
		foreach ($products as &$product) {
			if ($product->maxsaleprice == 0) $product->maxsaleprice = $product->maxprice;
			if ($product->minsaleprice == 0) $product->minsaleprice = $product->minprice;
						
			$prices[] = $product->onsale ? $product->minsaleprice:$product->minprice;
			
			if (!empty($product->percentoff)) {
				$product->maxsaleprice = $product->maxsaleprice - ($product->maxsaleprice * ($product->percentoff/100));
				$product->minsaleprice = $product->minsaleprice - ($product->minsaleprice * ($product->percentoff/100));
			}
			
			if (!empty($product->amountoff)){
				$product->maxsaleprice = $product->maxsaleprice - $product->amountoff;
				$product->minsaleprice = $product->minsaleprice - $product->amountoff;
			}
			
			if ($this->pricing['max'] == 0 || $product->maxsaleprice > $this->pricing['max'])
				$this->pricing['max'] = $product->maxsaleprice;
		
			if ($this->pricing['min'] == 0 || $product->minsaleprice < $this->pricing['min'])
				$this->pricing['min'] = $product->minsaleprice;
			
			$this->products[$product->id] = new Product();
			$this->products[$product->id]->populate($product);

			// Special field for Bestseller category
			if (isset($product->sold) && $product->sold) 
				$this->products[$product->id]->sold = $product->sold;

			if (!empty($product->thumbnail)) {
				$image = new stdClass();
				$image->properties = unserialize($product->thumbnail_properties);
				if (SHOPP_PERMALINKS) $image->uri = $Shopp->imguri.$product->thumbnail;
				else $image->uri = add_query_arg('shopp_image',$product->thumbnail,$Shopp->imguri);
				$this->products[$product->id]->imagesets['thumbnail'] = array();
				$this->products[$product->id]->imagesets['thumbnail'][] = $image;
				$this->products[$product->id]->thumbnail =& $this->products[$product->id]->imagesets['thumbnail'][0];
			}
			
		}
		$this->pricing['average'] = 0;
		if (count($prices) > 0) $this->pricing['average'] = array_sum($prices)/count($prices);
		
		if (!isset($loading['load'])) $loading['load'] = array('prices');

		if (count($this->products) > 0) {
			$Processing = new Product();
			$Processing->load_data($loading['load'],$this->products);
		}

		$this->loaded = true;

	}
		
	function rss () {
		global $Shopp;
		$db = DB::get();

		if (!$this->products) $this->load_products(array('limit'=>500));

		if (SHOPP_PERMALINKS) $rssurl = $Shopp->shopuri.'feed';
		else $rssurl = add_query_arg('shopp_lookup','products-rss',$Shopp->shopuri);

		$rss = array('title' => get_bloginfo('name')." ".$this->name,
			 			'link' => $rssurl,
					 	'description' => $this->description,
						'sitename' => get_bloginfo('name').' ('.get_bloginfo('url').')');
		$items = array();
		foreach ($this->products as $product) {
			if (isset($product->thumbnail_properties))
				$product->thumbnail_properties = unserialize($product->thumbnail_properties);
			$item = array();
			$item['title'] = attribute_escape($product->name);
			if (SHOPP_PERMALINKS) $item['link'] = $Shopp->shopuri.$product->id;
			else $item['link'] = urlencode(add_query_arg('shopp_pid',$product->id,$Shopp->shopuri));
			$item['description'] = "<![CDATA[";
			if (!empty($product->thumbnail)) {
				$item['description'] .= '<a href="'.$item['link'].'" title="'.$product->name.'">';
				$item['description'] .= '<img src="'.$product->thumbnail->uri.'" alt="'.$product->name.'" width="'.$product->thumbnail->properties['width'].'" height="'.$product->thumbnail->properties['height'].'" style="float: left; margin: 0 10px 0 0;" />';
				$item['description'] .= '</a>';
				$item['g:image_link'] = $product->thumbnail->uri;
			}

			$pricing = "";
			if ($product->onsale) {
				if ($product->pricerange['min']['saleprice'] != $product->pricerange['max']['saleprice']) $pricing .= "from ";
				$pricing .= money($product->pricerange['min']['saleprice']);
			} else {
				if ($product->pricerange['min']['price'] != $product->pricerange['max']['price']) $pricing .= "from ";
				$pricing .= money($product->pricerange['min']['price']);
			}
			$item['g:price'] = number_format(($product->onsale)?
				$product->pricerange['min']['saleprice']:$product->pricerange['min']['price'],2);
			$item['g:price_type'] = "starting";

			$item['description'] .= "<p><big><strong>$pricing</strong></big></p>";
			$item['description'] .= "<p>".attribute_escape($product->description)."</p>";
			$item['description'] .= "]]>";
			//$item['g:quantity'] = $product->stock;
			
			$items[] = $item;
		}
		$rss['items'] = $items;

		return $rss;
	}	
	
	function sortoptions () {
		return array(
			"title" => __('Title','Shopp'),
			"bestselling" => __('Bestselling','Shopp'),
			"highprice" => __('Price High to Low','Shopp'),
			"lowprice" => __('Price Low to High','Shopp'),
			"newest" => __('Newest to Oldest','Shopp'),
			"oldest" => __('Oldest to Newest','Shopp'),
			"random" => __('Random','Shopp')
		);
	}
	
	function tag ($property,$options=array()) {
		global $Shopp;
		$db = DB::get();

		$page = $Shopp->link('catalog');
		if (SHOPP_PERMALINKS) $imageuri = $page."images/";
		else $imageuri = add_query_arg('shopp_image','=',$page);
		
		if (SHOPP_PERMALINKS) {
			$pages = $Shopp->Settings->get('pages');
			if ($page == trailingslashit(get_bloginfo('url')))
				$page .= $pages['catalog']['name']."/";
		}
		
		switch ($property) {
			case "link": 
			case "url": 
				return (SHOPP_PERMALINKS)?
					$Shopp->shopuri."category/$this->uri":
					add_query_arg('shopp_category',$this->id,$Shopp->shopuri);
				break;
			case "name": return $this->name; break;
			case "slug": return $this->slug; break;
			case "description": return wpautop($this->description); break;
			case "total": return $this->total; break;
			case "has-products": 
			case "hasproducts": 
				if (isset($options['load'])) {
					$dataset = split(",",$options['load']);
					$options['load'] = array();
					foreach ($dataset as $name) $options['load'][] = trim($name);
				 } else {
					$options['load'] = array('prices');
				}
				if (!$this->loaded) $this->load_products($options);
				if (count($this->products) > 0) return true; else return false; break;
			case "products":			
				if (!$this->productloop) {
					reset($this->products);
					$Shopp->Product = current($this->products);
					$this->productsidx = 0;
					$this->productloop = true;
				} else {
					$Shopp->Product = next($this->products);
					$this->productsidx++;
				}

				if (current($this->products)) {
					$Shopp->Product = current($this->products);
					return true;
				}
				else {
					$this->productloop = false;
					return false;
				}
				break;
			case "row":
				if (empty($options['products'])) $options['products'] = $Shopp->Settings->get('row_products');
				if ($this->productsidx > 0 && $this->productsidx % $options['products'] == 0) return true;
				else return false;
				break;
			case "has-categories":
			case "hascategories":
				if (empty($this->children)) $this->load_children();
				return (!empty($this->children));
				break;
			case "subcategories":			
				if (!$this->childloop) {
					reset($this->children);
					$this->child = current($this->children);
					$this->childidx = 0;
					$this->childloop = true;
				} else {
					$this->child = next($this->children);
					$this->childidx++;
				}

				if (current($this->children)) {
					$this->child = current($this->children);
					return true;
				} else {
					$this->childloop = false;
					return false;
				}
				break;
			case "subcategory-list":
				if (isset($Shopp->Category->controls)) return false;
				if (!$this->children) $this->load_children();
				if (empty($this->children)) return false;

				$defaults = array(
					'title' => '',
					'before' => '',
					'after' => '',
					'class' => '',
					'depth' => 0,
					'parent' => false,
					'showall' => false,
					'dropdown' => false,
					'hierarchy' => false,
					'products' => false
					);
					
				$options = array_merge($defaults,$options);
				extract($options, EXTR_SKIP);

				$string = "";
				$depthlimit = $depth;
				$depth = 0;

				if (value_is_true($dropdown)) {
					$string .= $title;
					$string .= '<select name="shopp_cats" id="shopp-'.$this->slug.'-subcategories-menu" class="shopp-categories-menu">';
					$string .= '<option value="">Select a sub-category&hellip;</option>';
					foreach ($this->children as &$category) {
						if (value_is_true($hierarchy) && $depthlimit && $category->depth >= $depthlimit) continue;
						if ($category->products == 0) continue; // Only show categories with products
						if (value_is_true($hierarchy) && $category->depth > $depth) {
							$parent = &$previous;
							if (!isset($parent->path)) $parent->path = '/'.$parent->slug;
						}
						$padding = str_repeat("&nbsp;",$category->depth*3);

						if (SHOPP_PERMALINKS) $link = $Shopp->shopuri.'category/'.$category->uri;
						else $link = add_query_arg('shopp_category',$category->id,$Shopp->shopuri);

						$total = '';
						if (value_is_true($products)) $total = '&nbsp;&nbsp;('.$category->products.')';

						$string .= '<option value="'.htmlentities($link).'">'.$padding.$category->name.$total.'</option>';
						$previous = &$category;
						$depth = $category->depth;
						
					}
					$string .= '</select>';
					$string .= '<script type="text/javascript">';
					$string .= 'var menu = document.getElementById(\'shopp-'.$this->slug.'-subcategories-menu\');';
					$string .= 'if (menu)';
					$string .= '	menu.onchange = function () {';
					$string .= '		document.location.href = this.options[this.selectedIndex].value;';
					$string .= '	}';
					$string .= '</script>';
					
				} else {
					if (!empty($class)) $classes = ' class="'.$class.'"';
					$string .= $title.'<ul'.$classes.'>';
					foreach ($this->children as &$category) {
						if (value_is_true($hierarchy) && $depthlimit && 
							$category->depth >= $depthlimit) continue;
						if (value_is_true($hierarchy) && $category->depth > $depth) {
							$parent = &$previous;
							if (!isset($parent->path)) $parent->path = $parent->slug;
							$string .= '<ul class="children">';
						}
						if (value_is_true($hierarchy) && $category->depth < $depth) $string .= '</ul>';
					
						if (SHOPP_PERMALINKS) $link = $Shopp->shopuri.'category/'.$category->uri;
						else $link = add_query_arg('shopp_category',$category->id,$Shopp->shopuri);
					
						$total = '';
						if (value_is_true($products)) $total = ' ('.$category->products.')';
					
						if (value_is_true($showall) || $category->products > 0 || $category->smart) // Only show categories with products
							$string .= '<li><a href="'.$link.'">'.$category->name.'</a>'.$total.'</li>';

						$previous = &$category;
						$depth = $category->depth;
					}
					if (value_is_true($hierarchy))
						for ($i = 0; $i < $depth; $i++) $string .= "</ul>";
					$string .= '</ul>';
				}
				return $string;
				break;
			case "section-list":
				if (isset($Shopp->Category->controls)) return false;
				if (empty($Shopp->Catalog->categories)) $Shopp->Catalog->load_categories();
				if (empty($Shopp->Catalog->categories)) return false;
				if (!$this->children) $this->load_children();
			
				$defaults = array(
					'title' => '',
					'before' => '',
					'after' => '',
					'class' => '',
					'exclude' => '',
					'depth' => 0,
					'parent' => false,
					'showall' => false,
					'dropdown' => false,
					'hierarchy' => false,
					'products' => false,
					'wraplist' => true
					);
			
				$options = array_merge($defaults,$options);
				extract($options, EXTR_SKIP);
			
				$string = "";
				$depthlimit = $depth;
				$depth = 0;
				$wraplist = value_is_true($wraplist);
				$exclude = split(",",$exclude);
				$section = array();

				// Identify root parent
				$parent = $this->id;
				while($parent != 0) {
					if ($Shopp->Catalog->categories[$parent]->parent == 0) break;
					$parent = $Shopp->Catalog->categories[$parent]->parent;
				}
				$root = $Shopp->Catalog->categories[$parent];
				if ($this->id == $parent && empty($this->children)) return false;

				// Build the section
				$section[] = $root;
				$in = false;
				foreach ($Shopp->Catalog->categories as &$c) {
					if ($in && $c->depth == $root->depth) break; // Done
					if ($in) $section[] = $c;
					if (!$in && $c->id == $root->id) $in = true;
				}
				
				if (value_is_true($dropdown)) {
					$string .= $title;
					$string .= '<select name="shopp_cats" id="shopp-'.$this->slug.'-subcategories-menu" class="shopp-categories-menu">';
					$string .= '<option value="">Select a sub-category&hellip;</option>';
					foreach ($section as &$category) {
						if (value_is_true($hierarchy) && $depthlimit && $category->depth >= $depthlimit) continue;
						if (in_array($category->id,$exclude)) continue; // Skip excluded categories
						if ($category->products == 0) continue; // Only show categories with products
						if (value_is_true($hierarchy) && $category->depth > $depth) {
							$parent = &$previous;
							if (!isset($parent->path)) $parent->path = '/'.$parent->slug;
						}
						$padding = str_repeat("&nbsp;",$category->depth*3);
			
						if (SHOPP_PERMALINKS) $link = $Shopp->shopuri.'category/'.$category->uri;
						else $link = add_query_arg('shopp_category',$category->id,$Shopp->shopuri);
			
						$total = '';
						if (value_is_true($products)) $total = '&nbsp;&nbsp;('.$category->products.')';
			
						$string .= '<option value="'.htmlentities($link).'">'.$padding.$category->name.$total.'</option>';
						$previous = &$category;
						$depth = $category->depth;
			
					}
					$string .= '</select>';
					$string .= '<script type="text/javascript">';
					$string .= 'var menu = document.getElementById(\'shopp-'.$this->slug.'-subcategories-menu\');';
					$string .= 'if (menu)';
					$string .= '	menu.onchange = function () {';
					$string .= '		document.location.href = this.options[this.selectedIndex].value;';
					$string .= '	}';
					$string .= '</script>';
			
				} else {
					if (!empty($class)) $classes = ' class="'.$class.'"';
					$string .= $title;
					if ($wraplist) $string .= '<ul'.$classes.'>';
					foreach ($section as &$category) {
						if (in_array($category->id,$exclude)) continue; // Skip excluded categories
						if (value_is_true($hierarchy) && $depthlimit && 
							$category->depth >= $depthlimit) continue;
						if (value_is_true($hierarchy) && $category->depth > $depth) {
							$parent = &$previous;
							if (!isset($parent->path)) $parent->path = $parent->slug;
							$string .= '<ul class="children">';
						}
						if (value_is_true($hierarchy) && $category->depth < $depth) $string .= '</ul>';
			
						if (SHOPP_PERMALINKS) $link = $Shopp->shopuri.'category/'.$category->uri;
						else $link = add_query_arg('shopp_category',$category->id,$Shopp->shopuri);
			
						$total = '';
						if (value_is_true($products)) $total = ' ('.$category->products.')';
			
						if (value_is_true($showall) || $category->products > 0 || $category->smart) // Only show categories with products
							$string .= '<li><a href="'.$link.'">'.$category->name.'</a>'.$total.'</li>';
			
						$previous = &$category;
						$depth = $category->depth;
					}
					if (value_is_true($hierarchy))
						for ($i = 0; $i < $depth; $i++) $string .= "</ul>";
					if ($wraplist) $string .= '</ul>';
				}
				return $string;
				break;
			case "pagination":
				if (!$this->paged) return "";
				
				// Set options
				if (!isset($options['label'])) $options['label'] = __("Pages:","Shopp");
				if (!isset($options['next'])) $options['next'] = __("next","Shopp");
				if (!isset($options['previous'])) $options['previous'] = __("previous","Shopp");
				
				$navlimit = 1000;
				if (!empty($options['show'])) $navlimit = $options['show'];

				$before = "<div>".$options['label']; // Set the label
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
								"$page&shopp_category=$this->uri&paged=$i";
							$string .= '<li><a href="'.$link.'">1</a></li>';

							$pagenum = ($this->page - $jumps);
							if ($pagenum < 1) $pagenum = 1;
							$link = (SHOPP_PERMALINKS)?
								"$page"."category/$this->uri/page/$pagenum/":
								"$page&shopp_category=$this->uri&paged=$pagenum";
								
							$string .= '<li><a href="'.$link.'">&laquo;</a></li>';
						}
					}

					// Add previous button
					if (!value_is_true($options['previous']) && $this->page > 1) {
						$prev = $this->page-1;
						$link = (SHOPP_PERMALINKS)?
							"$page"."category/$this->uri/page/$prev/":
							"$page&shopp_category=$this->uri&paged=$prev";
						$string .= '<li class="previous"><a href="'.$link.'">'.$options['previous'].'</a></li>';
					} else $string .= '<li class="previous disabled">'.$options['previous'].'</li>';
					// end previous button

					while ($i < $visible_pages) {
						$link = (SHOPP_PERMALINKS)?
							"$page"."category/$this->uri/page/$i/":
							"$page&shopp_category=$this->uri&paged=$i";
						if ( $i == $this->page ) $string .= '<li class="active">'.$i.'</li>';
						else $string .= '<li><a href="'.$link.'">'.$i.'</a></li>';
						$i++;
					}
					if ($this->pages > $visible_pages) {
						$pagenum = ($this->page + $jumps);
						if ($pagenum > $this->pages) $pagenum = $this->pages;
						$link = (SHOPP_PERMALINKS)?
							"$page"."category/$this->uri/page/$pagenum/":
							"$page&shopp_category=$this->uri&paged=$pagenum";
						$string .= '<li><a href="'.$link.'">&raquo;</a></li>';

						$link = (SHOPP_PERMALINKS)?
							"$page"."category/$this->uri/page/$this->pages/":
							"$page&shopp_category=$this->uri&paged=$this->pages";
						$string .= '<li><a href="'.$link.'">'.$this->pages.'</a></li>';	
					}
					
					// Add next button
					if (!value_is_true($options['next']) && $this->page < $this->pages) {						
						$next = $this->page+1;
						$link = (SHOPP_PERMALINKS)?
							"$page"."category/$this->uri/page/$next/":
							"$page&shopp_category=$this->uri&paged=$next";
						$string .= '<li class="next"><a href="'.$link.'">'.$options['next'].'</a></li>';
					} else $string .= '<li class="next disabled">'.$options['next'].'</li>';
					
					$string .= '</ul>';
					$string .= $after;
				}
				return $string;
				break;
			case "has-faceted-menu": return ($this->facetedmenus == "on"); break;
			case "faceted-menu":
				if ($this->facetedmenus == "off") return;
				$output = "";
				$CategoryFilters =& $Shopp->Cart->data->Category[$this->slug];
				
				if (strpos($_SERVER['REQUEST_URI'],"?") !== false) 
					list($link,$query) = split("\?",$_SERVER['REQUEST_URI']);
				$query = $_GET;
				unset($query['shopp_catfilters']);
				$query = http_build_query($query);
				if (!empty($query)) $query .= '&';
				
				$list = "";
				if (is_array($CategoryFilters)) {
					foreach($CategoryFilters AS $facet => $filter) {
						$href = $link.'?'.$query.'shopp_catfilters['.$facet.']=';
						if (preg_match('/^(.*?(\d+[\.\,\d]*).*?)\-(.*?(\d+[\.\,\d]*).*)$/',stripslashes($filter),$matches)) {
							$label = $matches[1].' &mdash; '.$matches[3];
							if ($matches[2] == 0) $label = __('Under ','Shopp').$matches[3];
							if ($matches[4] == 0) $label = $matches[1].' '.__('and up','Shopp');
						} else $label = $filter;
						if (!empty($filter)) $list .= '<li><strong>'.$facet.'</strong>: '.$label.' <a href="'.$href.'" class="cancel">X</a></li>';
					}
					$output .= '<ul class="filters enabled">'.$list.'</ul>';
				}

				if ($this->pricerange == "auto" && empty($CategoryFilters['Price'])) {
					if (!$this->loaded) $this->load_products();
					$list = "";
					$this->priceranges = auto_ranges($this->pricing['average'],$this->pricing['max'],$this->pricing['min']);
					foreach ($this->priceranges as $range) {
						$href = $link.'?'.$query.'shopp_catfilters[Price]='.urlencode(money($range['min']).'-'.money($range['max']));
						$label = money($range['min']).' &mdash; '.money($range['max']-0.01);
						if ($range['min'] == 0) $label = __('Under ','Shopp').money($range['max']);
						elseif ($range['max'] == 0) $label = money($range['min']).' '.__('and up','Shopp');
						$list .= '<li><a href="'.$href.'">'.$label.'</a></li>';
					}
					if (!empty($this->priceranges)) $output .= '<h4>'.__('Price Range').'</h4>';
					$output .= '<ul>'.$list.'</ul>';
				}
				
				$catalogtable = DatabaseObject::tablename(Catalog::$table);
				$producttable = DatabaseObject::tablename(Product::$table);
				$spectable = DatabaseObject::tablename(Spec::$table);
				
				$results = $db->query("SELECT spec.name,spec.content,
					IF(spec.numeral > 0,spec.name,spec.content) AS merge,
					count(*) AS total,avg(numeral) AS avg,max(numeral) AS max,min(numeral) AS min 
					FROM $catalogtable AS cat 
					LEFT JOIN $producttable AS p ON cat.product=p.id 
					LEFT JOIN $spectable AS spec ON spec.product=p.id 
					WHERE cat.category=$this->id GROUP BY merge ORDER BY spec.name,merge",AS_ARRAY);

				$specdata = array();
				foreach ($results as $data) {
					if (isset($specdata[$data->name])) {
						if (!is_array($specdata[$data->name]))
							$specdata[$data->name] = array($specdata[$data->name]);
						$specdata[$data->name][] = $data;
					} else $specdata[$data->name] = $data;
				}
				
				if (is_array($this->specs)) {
					foreach ($this->specs as $spec) {
						$list = "";
						if (!empty($CategoryFilters[$spec['name']])) continue;

						// For custom menu presets
						if ($spec['facetedmenu'] == "custom" && !empty($spec['options'])) {
							foreach ($spec['options'] as $option) {
								$href = $link.'?'.$query.'shopp_catfilters['.$spec['name'].']='.urlencode($option['name']);
								$list .= '<li><a href="'.$href.'">'.$option['name'].'</a></li>';
							}
							$output .= '<h4>'.$spec['name'].'</h4><ul>'.$list.'</ul>';

						// For preset ranges
						} elseif ($spec['facetedmenu'] == "ranges" && !empty($spec['options'])) {
							foreach ($spec['options'] as $i => $option) {
								$matches = array();
								$format = '%s';
								$next = 0;
								if (isset($spec['options'][$i+1])) {
									if (preg_match('/(\d+[\.\,\d]*)/',$spec['options'][$i+1]['name'],$matches))
										$next = $matches[0];
								}
								$matches = array();
								$range = array("min" => 0,"max" => 0);
								if (preg_match('/^(.*?)(\d+[\.\,\d]*)(.*)$/',$option['name'],$matches)) {
									$base = $matches[2];
									$format = $matches[1].'%s'.$matches[3];
									if (!isset($spec['options'][$i+1])) $range['min'] = $base;
									else $range = array("min" => $base, "max" => ($next-1));
								}
								if ($i == 1) {
									$href = $link.'?'.$query.'shopp_catfilters['.$spec['name'].']='.urlencode(sprintf($format,'0').'-'.sprintf($format,$range['min']));
									$label = __('Under ','Shopp').sprintf($format,$range['min']);
									$list .= '<li><a href="'.$href.'">'.$label.'</a></li>';
								}

								$href = $link.'?'.$query.'shopp_catfilters['.$spec['name'].']='.urlencode(sprintf($format,$range['min']).'-'.sprintf($format,$range['max']));
								$label = sprintf($format,$range['min']).' &mdash; '.sprintf($format,$range['max']);
								if ($range['max'] == 0) $label = sprintf($format,$range['min']).' '.__('and up','Shopp');
								$list .= '<li><a href="'.$href.'">'.$label.'</a></li>';
							}
							$output .= '<h4>'.$spec['name'].'</h4><ul>'.$list.'</ul>';

						// For automatically building the menu options
						} elseif ($spec['facetedmenu'] == "auto" && isset($specdata[$spec['name']])) {

							if (is_array($specdata[$spec['name']])) { // Generate from text values
								foreach ($specdata[$spec['name']] as $option) {
									$href = $link.'?'.$query.'shopp_catfilters['.$spec['name'].']='.urlencode($option->content);
									$list .= '<li><a href="'.$href.'">'.$option->content.'</a></li>';
								}
								$output .= '<h4>'.$spec['name'].'</h4><ul>'.$list.'</ul>';
							} else { // Generate number ranges
								$format = '%s';
								if (preg_match('/^(.*?)(\d+[\.\,\d]*)(.*)$/',$specdata[$spec['name']]->content,$matches))
									$format = $matches[1].'%s'.$matches[3];

								$ranges = auto_ranges($specdata[$spec['name']]->avg,$specdata[$spec['name']]->max,$specdata[$spec['name']]->min);
								foreach ($ranges as $range) {
									$href = $link.'?'.$query.'shopp_catfilters['.$spec['name'].']='.urlencode($range['min'].'-'.$range['max']);
									$label = sprintf($format,$range['min']).' &mdash; '.sprintf($format,$range['max']);
									if ($range['min'] == 0) $label = __('Under ','Shopp').sprintf($format,$range['max']);
									elseif ($range['max'] == 0) $label = sprintf($format,$range['min']).' '.__('and up','Shopp');
									$list .= '<li><a href="'.$href.'">'.$label.'</a></li>';
								}
								if (!empty($list)) $output .= '<h4>'.$spec['name'].'</h4>';
								$output .= '<ul>'.$list.'</ul>';

							}
						}
					}
				}

				
				return $output;
				break;

			case "thumbnail":
				if (empty($this->images)) $this->load_images();
				if (!empty($options['class'])) $options['class'] = ' class="'.$options['class'].'"';
				if (isset($this->thumbnail)) {
					$img = $this->thumbnail;
					return '<img src="'.$imageuri.$img->id.'" alt="'.$this->name.' '.$img->datatype.'" width="'.$img->properties['width'].'" height="'.$img->properties['height'].'" '.$options['class'].' />'; break;
				}
				break;
			case "has-images": 
				if (empty($options['type'])) $options['type'] = "thumbnail";
				if (empty($this->images)) $this->load_images();
				return (count($this->images[$options['type']]) > 0); break;
			case "images":
				if (empty($options['type'])) $options['type'] = "thumbnail";
				if (!$this->imageloop) {
					reset($this->images[$options['type']]);
					$this->imageloop = true;
				} else next($this->images[$options['type']]);

				if (current($this->images[$options['type']])) return true;
				else {
					$this->imageloop = false;
					return false;
				}
				break;
			case "image":			
				if (empty($options['type'])) $options['type'] = "thumbnail";
				$img = current($this->images[$options['type']]);
				if (!empty($options['class'])) $options['class'] = ' class="'.$options['class'].'"';
				$string = "";
				if (!empty($options['zoom'])) $string .= '<a href="'.$imageuri.$img->src.'/'.str_replace('small_','',$img->name).'" class="shopp-thickbox" rel="product-gallery">';
				$string .= '<img src="'.$imageuri.$img->id.'" alt="'.$this->name.' '.$img->datatype.'" width="'.$img->properties['width'].'" height="'.$img->properties['height'].'" '.$options['class'].' />';
				if (!empty($options['zoom'])) $string .= "</a>";
				return $string;
				break;

		}
	}
	
} // end Category class

class NewProducts extends Category {
	static $_slug = "new";
	
	function NewProducts ($options=array()) {
		$this->name = __("New Products","Shopp");
		$this->slug = self::$_slug;
		$this->uri = $this->slug;
		$this->smart = true;
		$this->loading = array('where'=>"p.id IS NOT NULL",'order'=>'p.created DESC');
		if (isset($options['columns'])) $this->loading['columns'] = $options['columns'];
		if (isset($options['show'])) $this->loading['limit'] = $options['show'];
	}
	
}

class FeaturedProducts extends Category {
	static $_slug = "featured";
	
	function FeaturedProducts ($options=array()) {
		$this->name = __("Featured Products","Shopp");
		$this->slug = self::$_slug;
		$this->uri = $this->slug;
		$this->smart = true;
		$this->loading = array('where'=>"p.featured='on'",'order'=>'p.modified DESC');
		if (isset($options['show'])) $this->loading['limit'] = $options['show'];
	}
	
}

class OnSaleProducts extends Category {
	static $_slug = "onsale";
	
	function OnSaleProducts ($options=array()) {
		$this->name = __("On Sale","Shopp");
		$this->slug = self::$_slug;
		$this->uri = $this->slug;
		$this->smart = true;
		$this->loading = array('where'=>"pd.sale='on' OR pr.discount > 0",'order'=>'p.modified DESC');
		if (isset($options['show'])) $this->loading['limit'] = $options['show'];
	}
	
}

class BestsellerProducts extends Category {
	static $_slug = "bestsellers";
	
	function BestsellerProducts ($options=array()) {
		$this->name = __("Bestsellers","Shopp");
		$this->slug = self::$_slug;
		$this->uri = $this->slug;
		$this->smart = true;
		$purchasedtable = DatabaseObject::tablename(Purchased::$table);
		
		$this->loading = array(
			'columns'=>'count(DISTINCT pur.id) AS sold',
			'joins'=>"LEFT JOIN $purchasedtable AS pur ON p.id=pur.product",
			'where'=>"TRUE",
			'order'=>'sold DESC');
		if (isset($options['where'])) $this->loading['where'] = $options['where'];
		if (isset($options['show'])) $this->loading['limit'] = $options['show'];
	}
	
}

class SearchResults extends Category {
	static $_slug = "search-results";
	
	function SearchResults ($options=array()) {
		if (empty($options['search'])) $options['search'] = "(no search terms)";
		$this->name = __("Search Results for","Shopp")." &quot;".stripslashes($options['search'])."&quot;";
		$this->slug = self::$_slug;
		$this->uri = $this->slug;
		$this->smart = true;

		$search = str_replace('\\\'','',$options['search']);
		$search = preg_replace('/(\s?)(\w+)(\s?)/','\1*\2*\3',$options['search']);
		$this->loading = array(
			'columns'=> "MATCH(p.name,p.summary,p.description) AGAINST ('$search' IN BOOLEAN MODE) AS score",
			'where'=>"MATCH(p.name,p.summary,p.description) AGAINST ('$search' IN BOOLEAN MODE)",
			'order'=>'score DESC');
		if (isset($options['show'])) $this->loading['limit'] = $options['show'];
	}
	
}

class TagProducts extends Category {
	static $_slug = "tag";
	
	function TagProducts ($options=array()) {
		$tagtable = DatabaseObject::tablename(Tag::$table);

		$this->tag = $options['tag'];
		$this->name = __("Products tagged","Shopp")." &quot;".stripslashes($options['tag'])."&quot;";
		$this->slug = self::$_slug;
		$this->uri = urlencode($options['tag']);
		$this->smart = true;
		$this->loading = array(
			'catalog'=>'tags',
			'joins'=>"LEFT JOIN $tagtable AS t ON t.id=catalog.tag",
			'where'=>"catalog.tag=t.id AND t.name='{$options['tag']}'");
		if (isset($options['show'])) $this->loading['limit'] = $options['show'];
	}
	
}

class RandomProducts extends Category {
	static $_slug = "random";
	
	function RandomProducts ($options=array()) {
		$this->name = __("Random Products","Shopp");
		$this->slug = self::$_slug;
		$this->uri = $this->slug;
		$this->smart = true;
		$this->loading = array('where'=>'true','order'=>'RAND()');
		if (isset($options['exclude'])) {
			$where = array();
			$excludes = split(",",$options['exclude']);
			if (in_array('featured',$excludes)) $where[] = "(p.featured='off')";
			if (in_array('onsale',$excludes)) $where[] = "(pd.sale='off' OR pr.discount=0)";
			$this->loading['where'] = join(" AND ",$where);
		}
		if (isset($options['columns'])) $this->loading['columns'] = $options['columns'];
		if (isset($options['show'])) $this->loading['limit'] = $options['show'];
	}
	
}


?>