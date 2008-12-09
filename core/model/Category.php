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
	var $pricing = array();
	var $filters = array();
	var $images = array();
	
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
	
	function load_children() {
		$db = DB::get();
		$catalog_table = DatabaseObject::tablename(Catalog::$table);
		$this->children = $db->query("SELECT cat.*,count(sc.product) AS products FROM $this->_table AS cat LEFT JOIN $catalog_table AS sc ON sc.category=cat.id WHERE cat.uri like '%$this->uri%' AND cat.id <> $this->id GROUP BY cat.id ORDER BY parent DESC,name ASC",AS_ARRAY);
		$this->children = sort_tree($this->children,$this->id);
		
		if (!empty($this->children)) return true;
		return false;
	}
	
	function load_images () {
		global $Shopp;
		$db = DB::get();
		
		$uri =  trailingslashit(get_bloginfo('wpurl'))."?shopp_image=";
		if (SHOPP_PERMALINKS) {
			$pages = $Shopp->Settings->get('pages');
			$uri = trailingslashit(get_bloginfo('wpurl'))."{$pages['catalog']['permalink']}images/";
		}
		
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
			$image->uri = $uri.$image->id;
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
		if (!empty($Shopp->Cart->data->Category[$this->slug])) {
			$spectable = DatabaseObject::tablename(Spec::$table);
			
			$f = 1;
			$filters = "";
			foreach ($Shopp->Cart->data->Category[$this->slug] as $facet => $value) {
				if (empty($value)) continue;
				$specalias = "spec".($f++);

				// Handle Number Range filtering
				$match = "";
				if (preg_match('/^.*?(\d+[\.\,\d]*).*?\-.*?(\d+[\.\,\d]*).*$/',$value,$matches)) {
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
					$filtering['having'] .= "HAVING $match";
					continue;
				}
				
				$filtering['joins'] .= " LEFT JOIN $spectable AS $specalias ON $specalias.product=p.id AND $specalias.name='$facet'";
				$filters .= (empty($filters))?$match:" AND ".$match;
			}
			if (!empty($filters)) $filtering['where'] .= " AND ($filters)";
			
		}
		
		if (empty($filtering['order'])) {
			switch ($Shopp->Cart->data->Category['orderby']) {
				case "bestselling":
					$purchasedtable = DatabaseObject::tablename(Purchased::$table);
					$filtering['columns'] .= ',count(DISTINCT pur.id) AS sold';
					$filtering['joins'] .= "LEFT JOIN $purchasedtable AS pur ON p.id=pur.product";
					$filtering['order'] = "sold DESC"; 
					break;
				case "price-desc": $filtering['order'] = "pd.price DESC"; break;
				case "price-asc": $filtering['order'] = "pd.price ASC"; break;
				default: $filtering['order'] = "p.name ASC";
			}
		}
		
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

		$columns = "p.id,p.name,p.slug,p.summary,p.description,
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
					GROUP BY p.id {$filtering['having']}
					ORDER BY {$filtering['order']} LIMIT {$filtering['limit']}";

		if ($this->pagination > 0 && $limit > $this->pagination) {
			$count = "SELECT count(DISTINCT p.id) AS count,AVG(IF(pd.sale='on',pd.saleprice,pd.price)) as avgprice 
						FROM $producttable AS p 
						LEFT JOIN $catalogtable AS catalog ON catalog.product=p.id
						LEFT JOIN $pricetable AS pd ON pd.product=p.id AND pd.type != 'N/A' 
						LEFT JOIN $discounttable AS dc ON dc.product=p.id AND dc.price=pd.id
						LEFT JOIN $promotable AS pr ON pr.id=dc.promo 
						LEFT JOIN $assettable AS img ON img.parent=p.id AND img.context='product' AND img.datatype='thumbnail' AND img.sortorder=0 
						{$filtering['joins']}
						WHERE {$filtering['where']} AND p.published='on'";

			$total = $db->query($count);
			$this->total = $total->count;
			$this->pricing['average'] = $total->avgprice;
			$this->pages = ceil($this->total / $this->pagination);
			if ($this->pages > 1) $this->paged = true;			
		}

		$this->products = $db->query($query,AS_ARRAY);
		if ($this->pagination == 0 || $limit < $this->pagination) 
			$this->total = count($this->products);
		
		$this->pricing['min'] = 0;
		$this->pricing['max'] = 0;
		
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
			
			if ($this->pricing['max'] == 0 || $product->maxsaleprice > $this->pricing['max'])
				$this->pricing['max'] = $product->maxsaleprice;

			if ($this->pricing['min'] == 0 || $product->minsaleprice < $this->pricing['min'])
				$this->pricing['min'] = $product->minsaleprice;

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
		$db = DB::get();

		$page = $Shopp->link('catalog');
		if (SHOPP_PERMALINKS) $imageuri = $page."images/";
		else $imageuri = add_query_arg('shopp_image','=',$page);
		
		if (SHOPP_PERMALINKS) {
			$pages = $Shopp->Settings->get('pages');
			if ($page == get_bloginfo('wpurl')."/")
				$page .= $pages['catalog']['name']."/";
		}
		
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
				
			case "subcategory-list":
				if (isset($Shopp->Category->controls)) return false;
				if (empty($this->children)) $this->load_children();
				if (empty($this->children)) return;
				$string = "";
				$depth = 0;
				$parent = false;
				$showall = false;
				if (isset($options['showall'])) $showall = $options['showall'];

				$title = $options['title'];
				if (empty($title)) $title = "";
				if (value_is_true($options['dropdown'])) {
					$string .= $title;
					$string .= '<select name="shopp_cats" id="shopp-'.$this->slug.'-subcategories-menu" class="shopp-categories-menu">';
					$string .= '<option value="">Select a sub-category&hellip;</option>';
					foreach ($this->children as &$category) {
						if ($category->products > 0) // Only show categories with products
							if (value_is_true($options['hierarchy']) && $category->depth > $depth) {
								$parent = &$previous;
								if (!isset($parent->path)) $parent->path = '/'.$parent->slug;
							}
							$padding = str_repeat("&nbsp;",$category->depth*3);

							if (SHOPP_PERMALINKS) $link = $path.'/category/'.$category->uri;
							else $link = $page.'&amp;shopp_category='.$category->id;

							$products = '';
							if (value_is_true($options['products'])) $products = '&nbsp;&nbsp;('.$category->products.')';

							$string .= '<option value="'.$link.'">'.$padding.$category->name.$products.'</option>';
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
					$string .= $title.'<ul>';
					foreach ($this->children as &$category) {
						if (value_is_true($options['hierarchy']) && $category->depth > $depth) {
							$parent = &$previous;
							if (!isset($parent->path)) $parent->path = $parent->slug;
							$string .= '<ul class="children">';
						}
						if (value_is_true($options['hierarchy']) && $category->depth < $depth) $string .= '</ul>';
					
						if (SHOPP_PERMALINKS) $link = $path.'/category/'.$category->uri;
						else $link = $page.'&amp;shopp_category='.$category->id;
					
						$products = '';
						if (value_is_true($options['products'])) $products = ' ('.$category->products.')';
					
						if (value_is_true($showall) || $category->products > 0 || $category->smart) // Only show categories with products
							$string .= '<li><a href="'.$link.'">'.$category->name.'</a>'.$products.'</li>';

						$previous = &$category;
						$depth = $category->depth;
					}
					if (value_is_true($options['hierarchy']))
						for ($i = 0; $i < $depth; $i++) $string .= "</ul>";
					$string .= '</ul>';
				}
				return $string;
				break;				break;
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
				foreach($CategoryFilters AS $facet => $filter) {
					$href = $link.'?'.$query.'shopp_catfilters['.$facet.']=';
					if (preg_match('/^(.*?(\d+[\.\,\d]*).*?)\-(.*?(\d+[\.\,\d]*).*)$/',stripslashes($filter),$matches)) {
						$label = $matches[1].' &mdash; '.$matches[3];
						if ($matches[2] == 0) $label = __('Under ','Shopp').$matches[3];
						if ($matches[4] == 0) $label = $matches[1].__(' and up','Shopp');
					} else $label = $filter;
					if (!empty($filter)) $list .= '<li><strong>'.$facet.'</strong>: '.$label.' <a href="'.$href.'" class="cancel">X</a></li>';
				}
				$output .= '<ul class="filters enabled">'.$list.'</ul>';

				if ($this->pricerange == "auto" && empty($CategoryFilters['Price'])) {
					if (!$this->loaded) $this->load_products();
					$list = "";
					$this->priceranges = auto_ranges($this->pricing['average'],$this->pricing['max'],$this->pricing['min']);
					foreach ($this->priceranges as $range) {
						$href = $link.'?'.$query.'shopp_catfilters[Price]='.urlencode(money($range['min']).'-'.money($range['max']));
						$label = money($range['min']).' &mdash; '.money($range['max']-0.01);
						if ($range['min'] == 0) $label = __('Under ','Shopp').money($range['max']);
						elseif ($range['max'] == 0) $label = money($range['min']).__(' and up','Shopp');
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
							if ($range['max'] == 0) $label = sprintf($format,$range['min']).__(' and up','Shopp');
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
								elseif ($range['max'] == 0) $label = sprintf($format,$range['min']).__(' and up','Shopp');
								$list .= '<li><a href="'.$href.'">'.$label.'</a></li>';
							}
							if (!empty($list)) $output .= '<h4>'.$spec['name'].'</h4>';
							$output .= '<ul>'.$list.'</ul>';
							
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

			case "product":
				$product = current($this->products);

				if (SHOPP_PERMALINKS) $link = $page.$this->uri.'/'.$product->slug.'/';
				else {
					if (isset($Shopp->Category->smart)) $link = $page.'&shopp_category='.$this->slug.'&shopp_pid='.$product->id;
					else $link = $page.'&shopp_category='.$this->id.'&shopp_pid='.$product->id;
				}
				
				$thumbprops = unserialize($product->thumbnail_properties);
				
				$string = "";
				if (array_key_exists('link',$options)) $string .= '<a href="'.$link.'" title="'.$product->name.'">';
				if (array_key_exists('thumbnail',$options)) {
					if (!empty($product->thumbnail)) {
						$string .= '<img src="'.$imageuri.$product->thumbnail.'" alt="'.$product->name.' (thumbnail)" width="'.$thumbprops['width'].'" height="'.$thumbprops['height'].'" />';
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
						$string .= '<form action="'.$Shopp->link('cart').'" method="post">';
						$string .= '<input type="hidden" name="product" value="'.$product->id.'" />';
						$string .= '<input type="hidden" name="cart" value="add" />';
						if (array_key_exists('ajax',$options)) {
							$string .= '<input type="hidden" name="ajax" value="true" />';
							$string .= '<input type="button" name="addtocart" id="addtocart" value="'.$options['label'].'" class="addtocart ajax" />';					
						} else {
							$string .= '<input type="submit" name="addtocart" value="'.$options['label'].'" class="addtocart" />';					
						}
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