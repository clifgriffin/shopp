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
	var $paged = false;
	var $children = array();
	var $child = false;
	var $parent = 0;
	var $total = 0;
	var $description = "";
	var $timestamp = false;
	var $thumbnail = false;
	var $products = array();
	var $pricing = array();
	var $filters = array();
	var $loading = array();
	var $images = array();
	var $facetedmenus = "off";
	var $published = true;
	var $taxonomy = false;
	var $depth = false;

	function __construct ($id=false,$key=false) {
		$this->init(self::$table);
		$this->taxonomy = get_catalog_taxonomy_id('category');

		if (!$id) return;
		if ($this->load($id,$key)) return true;
		return false;
	}

	/**
	 * Load a single record by slug name
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param string $slug The slug name to load
	 * @return boolean loaded successfully or not
	 **/
	function loadby_slug ($slug) {
		$db = DB::get();

		$r = $db->query("SELECT * FROM $this->_table WHERE slug='$slug'");
		$this->populate($r);

		if (!empty($this->id)) return true;
		return false;
	}

	/**
	 * Load sub-categories
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @param array $loading Query configuration array
	 * @return boolean successfully loaded or not
	 **/
	function load_children($loading=array()) {
		if (isset($this->smart)
			|| empty($this->id)
			|| empty($this->uri)) return false;

		$db = DB::get();
		$catalog_table = DatabaseObject::tablename(Catalog::$table);

		$defaults = array(
			'columns' => 'cat.*,count(sc.product) as total',
			'joins' => array("LEFT JOIN $catalog_table AS sc ON sc.parent=cat.id AND sc.taxonomy='$this->taxonomy'"),
			'where' => array("cat.uri like '%$this->uri%' AND cat.id <> $this->id"),
			'orderby' => 'name',
			'order' => 'ASC'
		);
		$loading = array_merge($defaults,$loading);
		extract($loading);

		switch(strtolower($orderby)) {
			case 'id': $orderby = "cat.id"; break;
			case 'slug': $orderby = "cat.slug"; break;
			case 'count': $orderby = "total"; break;
			default: $orderby = "cat.name";
		}

		switch(strtoupper($order)) {
			case 'DESC': $order = "DESC"; break;
			default: $order = "ASC";
		}

		$joins = join(' ',$joins);
		$where = join(' AND ',$where);
		$name_order = ($orderby !== "name")?",name ASC":"";

		$query = "SELECT $columns FROM $this->_table AS cat
					$joins
					WHERE $where
					GROUP BY cat.id
					ORDER BY cat.parent DESC,$orderby $order$name_order";
		$children = $db->query($query,AS_ARRAY);

		$children = sort_tree($children,$this->id);
		foreach ($children as &$child) {
			$this->children[$child->id] = new Category();
			$this->children[$child->id]->populate($child);
			$this->children[$child->id]->depth = $child->depth;
			$this->children[$child->id]->total = $child->total;
		}

		return (!empty($this->children));
	}

	/**
	 * Loads images assigned to this category
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @return boolean Successful load or not
	 **/
	function load_images () {
		$db = DB::get();
		$Settings =& ShoppSettings();

		$ordering = $Settings->get('product_image_order');
		$orderby = $Settings->get('product_image_orderby');

		if ($ordering == "RAND()") $orderby = $ordering;
		else $orderby .= ' '.$ordering;
		$table = DatabaseObject::tablename(CategoryImage::$table);
		if (empty($this->id)) return false;
		$records = $db->query("SELECT * FROM $table WHERE parent=$this->id AND context='category' AND type='image' ORDER BY $orderby",AS_ARRAY);

		foreach ($records as $r) {
			$image = new CategoryImage();
			$image->copydata($r,false,array());
			$image->value = unserialize($image->value);
			$image->expopulate();
			$this->images[] = $image;
		}

		return true;
	}

	/**
	 * Loads a list of products for the category
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.2
	 *
	 * @param array $loading Loading options for the category
	 * @return void
	 **/
	function load_products ($loading=false) {
		global $Shopp;
		$db = DB::get();
		$Storefront =& ShoppStorefront();
		$Settings =& ShoppSettings();

		$catalogtable = DatabaseObject::tablename(Catalog::$table);
		$producttable = DatabaseObject::tablename(Product::$table);
		$pricetable = DatabaseObject::tablename(Price::$table);

		$this->paged = false;
		$this->pagination = $Settings->get('catalog_pagination');
		$paged = get_query_var('paged');
		$this->page = ((int)$paged > 0 || !is_numeric($paged))?$paged:1;

		if (empty($this->page)) $this->page = 1;

		// Hard product limit per category to keep resources "reasonable"
		$hardlimit = apply_filters('shopp_category_products_hardlimit',1000);

		$options = array(
			'columns' => false,		// Include extra columns (string) 'c.col1,c.col2…'
			'useindex' => false,	// FORCE INDEX to be used on the product table (string) 'indexname'
			'joins' => array(),		// JOIN tables array('INNER JOIN table AS t ON p.id=t.column')
			'where' => array(),		// WHERE query conditions array('x=y OR x=z','a!=b'…) (array elements are joined by AND
			'groupby' => false,		// GROUP BY column (string) 'column'
			'having' => array(),	// HAVING filters
			'limit' => false,		// Limit
			'order' => false,		// ORDER BY columns or named methods (string)
									// 'bestselling','highprice','lowprice','newest','oldest','random','chaos','title'

			'nostock' => true,		// Override to show products that are out of stock (string) 'on','off','yes','no'…
			'pagination' => true,	// Enable alpha pagination (string) 'alpha'
			'published' => true,	// Load published or unpublished products (string) 'on','off','yes','no'…
			'adjacent' => false,	//
			'product' => false,		//
			'restat' => false		// Force recalculate product stats
		);

		$loading = array_merge($options,$this->loading,$loading);
		extract($loading);

		if (!empty($where) && is_string($where)) $where = array($where);
		if (!empty($joins) && is_string($joins)) $joins = array($joins);

		// Allow override for loading unpublished products
		if (!value_is_true($published)) $this->published = false;

		// Handle default WHERE clause matching this category id
		if (!empty($this->id))
			$joins[$catalogtable] = "INNER JOIN $catalogtable AS c ON p.id=c.product AND parent=$this->id AND taxonomy='$this->taxonomy'";

		if (!value_is_true($nostock) && $Settings->get('outofstock_catalog') == "off")
			$where[] = "((p.inventory='on' AND p.stock > 0) OR p.inventory='off')";

		// Faceted browsing
		if ($Storefront !== false && !empty($Storefront->browsing[$this->slug])) {
			$spectable = DatabaseObject::tablename(Spec::$table);

			$f = 1;
			$filters = array();
			foreach ($Storefront->browsing[$this->slug] as $facet => $value) {
				if (empty($value)) continue;
				$specalias = "spec".($f++);

				// Handle Number Range filtering
				if (!is_array($value) &&
						preg_match('/^.*?(\d+[\.\,\d]*).*?\-.*?(\d+[\.\,\d]*).*$/',$value,$matches)) {

					if ('price' == strtolower($facet)) { // Prices require complex matching on price line entries
						list(,$min,$max) = array_map('floatvalue',$matches);

						if ($min > 0) $filters[] = "((sale='on' AND (minprice >= $min OR maxprice >= $min))
													OR (sale='on' AND (minprice >= $min OR maxprice >= $min)))";
						if ($max > 0) $filters[] = "((sale='on' AND (minprice <= $max OR maxprice <= $max))
													OR (sale='on' AND (minprice <= $max OR maxprice <= $max)))";

						// Use HAVING clause for filtering by pricing information because of data aggregation
						// $having[] = $match;
						// continue;

					} else { // Spec-based numbers are somewhat more straightforward
						list(,$min,$max) = $matches;
						if ($min > 0) $filters[] = "$specalias.numeral >= $min";
						if ($max > 0) $filters[] = "$specalias.numeral <= $max";
					}
				} else $filters[] = "$specalias.value='$value'"; // No range, direct value match

				$joins[$specalias] = "LEFT JOIN $spectable AS $specalias
										ON $specalias.parent=p.id
										AND $specalias.context='product'
										AND $specalias.type='spec'
										AND $specalias.name='".$db->escape($facet)."'";
			}
			$where[] = join(' AND ',$filters);

		}

		// WP TZ setting based time - (timezone offset:[PHP UTC adjusted time - MySQL UTC adjusted time])
		$now = time()."-(".(time()-date("Z",time()))."-UNIX_TIMESTAMP(UTC_TIMESTAMP()))";

		if ($this->published) $where[] = "(p.status='publish' AND $now >= UNIX_TIMESTAMP(p.publish))";
		else $where[] = "(p.status!='publish' OR $now < UNIX_TIMESTAMP(p.publish))";

		$defaultOrder = $Settings->get('default_product_order');
		if (empty($defaultOrder)) $defaultOrder = '';
		$ordering = isset($Storefront->browsing['orderby'])?
						$Storefront->browsing['orderby']:$defaultOrder;
		if ($order !== false) $ordering = $order;
		switch ($ordering) {
			case 'bestselling': $order = "sold DESC,p.name ASC"; break;
			case 'highprice': $order = "maxprice DESC,p.name ASC"; break;
			case 'lowprice': $order = "minprice ASC,p.name ASC"; $useindex = "lowprice"; break;
			case 'newest': $order = "p.publish DESC,p.name ASC"; break;
			case 'oldest': $order = "p.publish ASC,p.name ASC"; $useindex = "oldest";	break;
			case 'random': $order = "RAND(".crc32($Shopp->Shopping->session).")"; break;
			case 'chaos': $order = "RAND(".time().")"; break;
			case 'title': $order = "p.name ASC"; $useindex = "name"; break;
			case 'recommended':
			default:
				// Need to add the catalog table for access to category-product priorities
				if (!isset($this->smart)) {
					$joins[$catalogtable] = "INNER JOIN $catalogtable AS c ON c.product=p.id AND c.parent='$this->id'";
					$order = "c.priority ASC,p.name ASC";
				} else $order = "p.name ASC";
				break;
		}

		// Handle adjacent product navigation
		if ($adjacent && $product) {

			$field = substr($order,0,strpos($order,' '));
			$op = $adjacent != "next"?'<':'>';

			// Flip the sort order for previous
			$c = array('ASC','DESC'); $r = array('__A__','__D__');
			if ($op == '<') $order = str_replace($r,array_reverse($c),str_replace($c,$r,$order));

			switch ($field) {
				case 'sold':
					if ($product->sold() == 0) {
						$field = 'p.name';
						$target = "'".$db->escape($product->name)."'";
					} else $target = $product->sold();
					$where[] = "$field $op $target";
					break;
				case 'highprice':
					if (empty($product->prices)) $product->load_data(array('prices'));
					$target = !empty($product->max['saleprice'])?$product->max['saleprice']:$product->max['price'];
					$where[] = "$target $op IF (pd.sale='on' OR pr.discount>0,pd.saleprice,pd.price) AND p.id != $product->id";
					break;
				case 'lowprice':
					if (empty($product->prices)) $product->load_data(array('prices'));
					$target = !empty($product->max['saleprice'])?$product->max['saleprice']:$product->max['price'];
					$where[] = "$target $op= IF (pd.sale='on' OR pr.discount>0,pd.saleprice,pd.price) AND p.id != $product->id";
					break;
				case 'p.name': $where[] = "$field $op '".$db->escape($product->name)."'"; break;
				default:
					if ($product->priority == 0) {
						$field = 'p.name';
						$target = "'".$db->escape($product->name)."'";
					} else $target = $product->priority;
					$where[] = "$field $op $target";
					break;
			}

		}

		// Handle alphabetic page requests
		if ($Shopp->Category->controls !== false
				&& ('alpha' === $pagination || !is_numeric($this->page))) {

			$this->alphapages(array(
				'useindex' => $useindex,
				'joins' => $joins,
				'where' => $where
			));

			$this->paged = true;
			if (!is_numeric($this->page)) {
				$where[] = $this->page == "0-9" ?
					"1 = (LEFT(p.name,1) REGEXP '[0-9]')":
					"'$this->page' = IF(LEFT(p.name,1) REGEXP '[0-9]',LEFT(p.name,1),LEFT(SOUNDEX(p.name),1))";
			}

		}

		if (!empty($columns)) {
			if (is_string($columns)) {
				if (strpos($columns,',') !== false) $columns = explode(',',$columns);
				else $columns = array($columns);
			}
		} else $columns = array();

		$columns = array_map('trim',$columns);
		array_unshift($columns,'p.*');
		$columns = join(',', $columns);

 		if (!empty($useindex)) $useindex = "FORCE INDEX($useindex)";
		if (!empty($groupby)) $groupby = "GROUP BY $groupby";

		if (!empty($having)) $having = "HAVING ".join(" AND ",$having);
		else $having = '';

		$joins = join(' ',$joins);
		$where = join(' AND ',$where);

		if (empty($limit)) {
			if ($this->pagination > 0 && is_numeric($this->page) && value_is_true($pagination)) {
				if( !$this->pagination || $this->pagination < 0 )
					$this->pagination = $hardlimit;
				$start = ($this->pagination * ($this->page-1));

				$limit = "$start,$this->pagination";
			} else $limit = $hardlimit;
		}

		$query =   "SELECT SQL_CALC_FOUND_ROWS $columns
					FROM $producttable AS p $useindex
					$joins
					WHERE $where
					$groupby $having
					ORDER BY $order
					LIMIT $limit";

		// Execute the main category products query
		$products = $db->query($query,AS_ARRAY);

		$total = $db->query("SELECT FOUND_ROWS() as count");
		$this->total = $total->count;

		if ($this->pagination > 0 && $this->total > $this->pagination) {
			$this->pages = ceil($this->total / $this->pagination);
			if ($this->pages > 1) $this->paged = true;
		}

		$this->pricing['min'] = 0;
		$this->pricing['max'] = 0;

		$prices = array();
		foreach ($products as $i => &$product) {
			$this->pricing['max'] = max($this->pricing['max'],$product->maxprice);
			$this->pricing['min'] = min($this->pricing['min'],$product->minprice);

			$this->products[$product->id] = new Product();
			$this->products[$product->id]->populate($product);

			if (isset($product->score))
				$this->products[$product->id]->score = $product->score;

			// Special property for Bestseller category
			if (isset($product->sold) && $product->sold)
				$this->products[$product->id]->sold = $product->sold;

			// Special property Promotions
			if (isset($product->promos))
				$this->products[$product->id]->promos = $product->promos;

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

	function alphapages ($loading=array()) {
		$db =& DB::get();

		$catalogtable = DatabaseObject::tablename(Catalog::$table);
		$producttable = DatabaseObject::tablename(Product::$table);
		$pricetable = DatabaseObject::tablename(Price::$table);

		$alphanav = range('A','Z');

		$ac =   "SELECT count(*) AS total,
						IF(LEFT(p.name,1) REGEXP '[0-9]',LEFT(p.name,1),LEFT(SOUNDEX(p.name),1)) AS letter,
						AVG((p.maxprice+p.minprice)/2) as avgprice
					FROM $producttable AS p {$loading['useindex']}
					{$loading['joins']}
					WHERE {$loading['where']}
					GROUP BY letter";

		$alpha = $db->query($ac,AS_ARRAY);

		$entry = new stdClass();
		$entry->letter = false;
		$entry->total = $entry->avg = 0;

		$existing = current($alpha);
		if (!isset($this->alpha['0-9'])) {
			$this->alpha['0-9'] = clone $entry;
			$this->alpha['0-9']->letter = '0-9';
		}

		while (is_numeric($existing->letter)) {
			$this->alpha['0-9']->total += $existing->total;
			$this->alpha['0-9']->avg = ($this->alpha['0-9']->avg+$existing->avg)/2;
			$this->alpha['0-9']->letter = '0-9';
			$existing = next($alpha);
		}

		foreach ($alphanav as $letter) {
			if ($existing->letter == $letter) {
				$this->alpha[$letter] = $existing;
				$existing = next($alpha);
			} else {
				$this->alpha[$letter] = clone $entry;
				$this->alpha[$letter]->letter = $letter;
			}
		}

	}

	/**
	 * Returns the product adjacent to the requested product in the category
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param int $next (optional) Which product to get (-1 for previous, defaults to 1 for next)
	 * @return object The Product object
	 **/
	function adjacent_product($next=1) {
		global $Shopp;

		if ($next < 0) $this->loading['adjacent'] = "previous";
		else $this->loading['adjacent'] = "next";

		$this->loading['limit'] = '1';
		$this->loading['product'] = $Shopp->Requested;
		$this->load_products();

		if (!$this->loaded) return false;

		reset($this->products);
		$product = key($this->products);
		return new Product($product);
	}

	/**
	 * Updates category slug and rebuilds changed URIs
	 *
	 * Generates the slug if empty. Checks for duplicate slugs
	 * and adds a numeric suffix to ensure a unique slug.
	 *
	 * If the slug changes, the category uri is rebuilt and
	 * and all descendant category uri's are rebuilt and updated.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean successfully updated
	 **/
	function update_slug () {
		$db = DB::get();

		if (empty($this->slug)) {
			$name = !empty($_POST['name'])?$_POST['name']:$this->name;
			$this->slug = sanitize_title_with_dashes($name);
		}

		if (empty($this->slug)) return false; // No slug for this category, bail

		$uri = $this->uri;
		$parent = !empty($_POST['parent'])?$_POST['parent']:$this->parent;
		if ($parent > 0) {

			$Catalog = new Catalog();
			$Catalog->load_categories(array(
				'columns' => "cat.id,cat.parent,cat.name,cat.description,cat.uri,cat.slug",
				'where' => array(),
				'joins' => array(),
				'orderby' => false,
				'order' => false,
				'outofstock' => true
			));

			$paths = array();
			if (!empty($this->slug)) $paths = array($this->slug);  // Include self

			$parentkey = -1;
			// If we're saving a new category, lookup the parent
			if ($parent > 0) {
				array_unshift($paths,$Catalog->categories['_'.$parent]->slug);
				$parentkey = $Catalog->categories['_'.$parent]->parent;
			}

			while (isset($Catalog->categories['_'.$parentkey])
					&& $category_tree = $Catalog->categories['_'.$parentkey]) {
				array_unshift($paths,$category_tree->slug);
				$parentkey = '_'.$category_tree->parent;
			}
			if (count($paths) > 1) $this->uri = join("/",$paths);
			else $this->uri = $paths[0];
		} else $this->uri = $this->slug; // end if ($parent > 0)

		// Check for an existing category uri
		$exclude_category = !empty($this->id)?"AND id != $this->id":"";
		$existing = $db->query("SELECT uri FROM $this->_table WHERE uri='$this->uri' $exclude_category LIMIT 1");
		if ($existing) {
			$suffix = 2;
			while($existing) {
				$altslug = preg_replace('/\-\d+$/','',$this->slug)."-".$suffix++;
				$uris = explode('/',$this->uri);
				array_splice($uris,-1,1,$altslug);
				$alturi = join('/',$uris);
				$existing = $db->query("SELECT uri FROM $this->_table WHERE uri='$alturi' $exclude_category LIMIT 1");
			}
			$this->slug = $altslug;
			$this->uri = $alturi;
		}

		if ($uri == $this->uri) return true;

		// Update children uris
		$this->load_children(array(
			'columns' 	=> 'cat.id,cat.parent,cat.uri',
			'where' 	=> array("(cat.uri like '%$uri%' OR cat.parent='$this->id')","cat.id <> '$this->id'")
		));
		if (empty($this->children)) return true;

		$categoryuri = explode('/',$this->uri);
		foreach ($this->children as $child) {
			$childuri = explode('/',$child->uri);
			$changed = reset(array_diff($childuri,$categoryuri));
			array_splice($childuri,array_search($changed,$childuri),1,end($categoryuri));
			$updateduri = join('/',$childuri);
			$db->query("UPDATE $this->_table SET uri='$updateduri' WHERE id='$child->id' LIMIT 1");
		}

	}

	/**
	 * Updates the sort order of category image assets
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @param array $ordering List of image ids in order
	 * @return boolean true on success
	 **/
	function save_imageorder ($ordering) {
		$db = DB::get();
		$table = DatabaseObject::tablename(CategoryImage::$table);
		foreach ($ordering as $i => $id)
			$db->query("UPDATE LOW_PRIORITY $table SET sortorder='$i' WHERE (id='$id' AND parent='$this->id' AND context='category' AND type='image')");
		return true;
	}

	/**
	 * Updates the assigned parent id of images to link them to the category
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @param array $images List of image ids
	 * @return boolean true on successful update
	 **/
	function link_images ($images) {
		if (empty($images) || !is_array($images)) return false;

		$db = DB::get();
		$table = DatabaseObject::tablename(CategoryImage::$table);
		$set = "id=".join(' OR id=',$images);
		$query = "UPDATE $table SET parent='$this->id',context='category' WHERE ".$set;
		$db->query($query);

		return true;
	}

	/**
	 * Deletes image assignments to the category and metadata (not the binary data)
	 *
	 * Removes the meta table record that assigns the image to the category and all
	 * cached image metadata built from the original image. Does NOT delete binary
	 * data.
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @param array $images List of image ids to delete
	 * @return boolean true on success
	 **/
	function delete_images ($images) {
		$db = &DB::get();
		$imagetable = DatabaseObject::tablename(CategoryImage::$table);
		$imagesets = "";
		foreach ($images as $image) {
			$imagesets .= (!empty($imagesets)?" OR ":"");
			$imagesets .= "((context='category' AND parent='$this->id' AND id='$image') OR (context='image' AND parent='$image'))";
		}
		$db->query("DELETE LOW_PRIORITY FROM $imagetable WHERE type='image' AND ($imagesets)");
		return true;
	}

	/**
	 * Generates an RSS feed of products for this category
	 *
	 * NOTE: To modify the output of the RSS generator, use
	 * the filter hooks provided in a separate plugin or
	 * in the theme functions.php file.
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @return string The final RSS markup
	 **/
	function rss () {
		global $Shopp;
		$db = DB::get();

		add_filter('shopp_rss_description','wptexturize');
		add_filter('shopp_rss_description','convert_chars');
		add_filter('shopp_rss_description','make_clickable',9);
		add_filter('shopp_rss_description','force_balance_tags', 25);
		add_filter('shopp_rss_description','convert_smilies',20);
		add_filter('shopp_rss_description','wpautop',30);

		do_action_ref_array('shopp_category_rss',array(&$this));

		if (!$this->products) $this->load_products(array('limit'=>500,'load'=>array('images','prices')));

		$rss = array('title' => get_bloginfo('name')." ".$this->name,
			 			'link' => $this->tag('feed-url'),
					 	'description' => $this->description,
						'sitename' => get_bloginfo('name').' ('.get_bloginfo('url').')',
						'xmlns' => array('shopp'=>'http://shopplugin.net/xmlns',
							'g'=>'http://base.google.com/ns/1.0',
							'atom'=>'http://www.w3.org/2005/Atom',
							'content'=>'http://purl.org/rss/1.0/modules/content/')
						);
		$rss = apply_filters('shopp_rss_meta',$rss);

		$items = array();
		foreach ($this->products as $product) {
			$item = array();
			$item['guid'] = $product->tag('url','return=1');
			$item['title'] = $product->name;
			$item['link'] =  $product->tag('url','return=1');

			// Item Description
			$item['description'] = '';

			$Image = current($product->images);
			if (!empty($Image)) {
				$item['description'] .= '<a href="'.$item['link'].'" title="'.$product->name.'">';
				$item['description'] .= '<img src="'.esc_attr(add_query_string($Image->resizing(96,96,0),shoppurl($Image->id,'images'))).'" alt="'.$product->name.'" width="96" height="96" style="float: left; margin: 0 10px 0 0;" />';
				$item['description'] .= '</a>';
			}

			$pricing = "";
			if ($product->onsale) {
				if ($product->min['saleprice'] != $product->max['saleprice'])
					$pricing .= "from ";
				$pricing .= money($product->min['saleprice']);
			} else {
				if ($product->min['price'] != $product->max['price'])
					$pricing .= "from ";
				$pricing .= money($product->min['price']);
			}
			$item['description'] .= "<p><big><strong>$pricing</strong></big></p>";

			$item['description'] .= $product->description;
			$item['description'] =
			 	'<![CDATA['.apply_filters('shopp_rss_description',($item['description']),$product).']]>';

			// Google Base Namespace
			if ($Image) $item['g:image_link'] = add_query_string($Image->resizing(400,400,0),shoppurl($Image->id,'images'));
			$item['g:condition'] = "new";

			$price = floatvalue($product->onsale?$product->min['saleprice']:$product->min['price']);
			if (!empty($price))	{
				$item['g:price'] = $price;
				$item['g:price_type'] = "starting";
			}

			$item = apply_filters('shopp_rss_item',$item,$product);
			$items[] = $item;
		}
		$rss['items'] = $items;

		return $rss;
	}


	/**
	 * A functional list of support category sort options
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return array The list of supported sort methods
	 **/
	function sortoptions () {
		return apply_filters('shopp_category_sortoptions', array(
			"title" => __('Title','Shopp'),
			"custom" => __('Recommended','Shopp'),
			"bestselling" => __('Bestselling','Shopp'),
			"highprice" => __('Price High to Low','Shopp'),
			"lowprice" => __('Price Low to High','Shopp'),
			"newest" => __('Newest to Oldest','Shopp'),
			"oldest" => __('Oldest to Newest','Shopp'),
			"random" => __('Random','Shopp')
		));
	}

	function pagelink ($page) {
		$type = isset($this->tag)?'tag':'category';
		$alpha = preg_match('/\w/',$page);
		$prettyurl = "$type/$this->uri".($page > 1 || $alpha?"/page/$page":"");
		$queryvars = array("shopp_$type"=>$this->uri);
		if ($page > 1 || $alpha) $queryvars['paged'] = $page;

		return apply_filters('shopp_paged_link',shoppurl(SHOPP_PRETTYURLS?$prettyurl:$queryvars));
	}

	/**
	 * shopp('category','...') tags
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @deprecated 1.2
	 * @see http://docs.shopplugin.net/Category_Tags
	 *
	 **/
	function tag ($property,$options=array()) {
		$options['return'] = 'on';
		return shopp('category',$property,$options, $this);
	}

} // END class Category

class Collection extends Category {
	var $smart = true;
	var $slug = false;
	var $uri = false;
	var $name = false;
	var $loading = array();

	function __construct ($options=array()) {
		global $Shopp;
		if (isset($options['show'])) $this->loading['limit'] = $options['show'];
		if (isset($options['pagination'])) $this->loading['pagination'] = $options['pagination'];
		$this->smart($options);
	}

}

class CatalogProducts extends Collection {
	static $_slug = "catalog";

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __("Catalog Products","Shopp");
		if (isset($options['order'])) $this->loading['order'] = $options['order'];
	}

}

class NewProducts extends Collection {
	static $_slug = "new";
	static $_auto = true;

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __("New Products","Shopp");
		$this->loading = array('where'=>"p.id IS NOT NULL",'order'=>'newest');
		if (isset($options['columns'])) $this->loading['columns'] = $options['columns'];
	}

}

class FeaturedProducts extends Collection {
	static $_slug = "featured";
	static $_auto = true;

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __("Featured Products","Shopp");
		$this->loading = array('where'=>"p.featured='on'",'order'=>'p.modified DESC');
	}

}

class OnSaleProducts extends Collection {
	static $_slug = "onsale";
	static $_auto = true;

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __("On Sale","Shopp");
		$this->loading = array('where'=>"p.sale='on'",'order'=>'p.modified DESC');
	}

}

class BestsellerProducts extends Collection {
	static $_slug = "bestsellers";
	static $_auto = true;

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __("Bestsellers","Shopp");
		$this->loading = array('order'=>'bestselling');
		if (isset($options['where'])) $this->loading['where'] = $options['where'];
	}

}

class SearchResults extends Collection {
	static $_slug = "search-results";

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$options['search'] = empty($options['search'])?"":stripslashes($options['search']);

		// Load search engine components
		require_once(SHOPP_MODEL_PATH."/Search.php");
		new SearchParser();
		new BooleanParser();
		new ShortwordParser();

		// Sanitize the search string
		$search = $options['search'];

		// Price matching
		$prices = SearchParser::PriceMatching($search);
		if ($prices) {
			$pricematch = false;
			switch ($prices->op) {
				case '>': $pricematch = "((onsale=0 AND (minprice > $prices->target OR maxprice > $prices->target))
							OR (onsale=1 AND (minsaleprice > $prices->target OR maxsaleprice > $prices->target)))"; break;
				case '<': $pricematch = "((onsale=0 AND (minprice < $prices->target OR maxprice < $prices->target))
							OR (onsale=1 AND (minsaleprice < $prices->target OR maxsaleprice < $prices->target)))"; break;
				default: $pricematch = "((onsale=0 AND (minprice >= $prices->min AND maxprice <= $prices->max))
								OR (onsale=1 AND (minsaleprice >= $prices->min AND maxsaleprice <= $prices->max)))";
			}
		}

		// Boolean keyword search
		$boolean = apply_filters('shopp_boolean_search',$search);

		// Exact shortword search
		$shortwords = '';
		if (!(defined('SHOPP_DISABLE_SHORTWORD_SEARCH') && SHOPP_DISABLE_SHORTWORD_SEARCH))
			$shortwords = apply_filters('shopp_shortword_search',$search);

		// Natural language search for relevance
		$search = apply_filters('shopp_search_query',$search);

		if (strlen($options['search']) > 0 && empty($boolean)) $boolean = $options['search'];

		$score = "SUM(MATCH(terms) AGAINST ('$search'))";
		$where = "MATCH(terms) AGAINST ('$boolean' IN BOOLEAN MODE)";
		if (!empty($shortwords)) {
			$score = "SUM(MATCH(terms) AGAINST ('$search'))+SUM(terms REGEXP '[[:<:]](".str_replace(' ','|',$shortwords).")[[:>:]]')";
			$where = "($where OR terms REGEXP '[[:<:]](".str_replace(' ','|',$shortwords).")[[:>:]]')";
		}

		$index = DatabaseObject::tablename(ContentIndex::$table);
		$this->loading = array(
			'joins'=>array($index => "INNER JOIN $index AS search ON search.product=p.id"),
			'columns'=> "$score AS score",
			'where'=> $where,
			'groupby'=>'p.id',
			'orderby'=>'score DESC');
		if (!empty($pricematch)) $this->loading['having'] = $pricematch;
		if (isset($options['show'])) $this->loading['limit'] = $options['show'];

		// No search
		if (empty($options['search'])) $options['search'] = __('(no search terms)','Shopp');
		$this->name = __("Search Results for","Shopp").": {$options['search']}";

	}
}

class TagProducts extends Collection {
	static $_slug = "tag";

	function smart ($options=array()) {
		$this->slug = self::$_slug;
		$tagtable = DatabaseObject::tablename(CatalogTag::$table);
		$catalogtable = DatabaseObject::tablename(Catalog::$table);
		$this->taxonomy = get_catalog_taxonomy_id('tag');
		$this->tag = urldecode($options['tag']);
		$tagquery = "";
		if (strpos($options['tag'],',') !== false) {
			$tags = explode(",",$options['tag']);
			foreach ($tags as $tag)
				$tagquery .= empty($tagquery)?"tag.name='$tag'":" OR tag.name='$tag'";
		} else $tagquery = "tag.name='{$this->tag}'";

		$this->name = __("Products tagged","Shopp")." &quot;".stripslashes($this->tag)."&quot;";
		$this->uri = urlencode($this->tag);
		$this->loading = array('joins'=> array("INNER JOIN $catalogtable AS catalog ON p.id=catalog.product AND catalog.taxonomy='$this->taxonomy' JOIN $tagtable AS tag ON catalog.parent=tag.id"),'where' => $tagquery);

	}
}

class RelatedProducts extends Collection {
	static $_slug = "related";
	var $product = false;

	function smart ($options=array()) {
		$this->slug = self::$_slug;

		global $Shopp;
		$Cart = $Shopp->Order->Cart;
		$tagtable = DatabaseObject::tablename(CatalogTag::$table);
		$catalogtable = DatabaseObject::tablename(Catalog::$table);
		$this->taxonomy = get_catalog_taxonomy_id('tag');

		// Use the current product if available
		if (!empty($Shopp->Product->id))
			$this->product = $Shopp->Product;

		// Or load a product specified
		if (isset($options['product'])) {
			if ($options['product'] == "recent-cartitem") 			// Use most recently added item in the cart
				$this->product = new Product($Cart->Added->product);
			elseif (preg_match('/^[\d+]$/',$options['product']) !== false) 	// Load by specified id
				$this->product = new Product($options['product']);
			else
				$this->product = new Product($options['product'],'slug'); // Load by specified slug
		}

		if (empty($this->product->id)) return false;

		// Load the product's tags if they are not available
		if (empty($this->product->tags))
			$this->product->load_data(array('tags'));

		if (empty($this->product->tags)) return false;

		$tagscope = "";
		if (isset($options['tagged'])) {
			$tagged = new CatalogTag($options['tagged'],'name');

			if (!empty($tagged->id)) {
				$tagscope .= (empty($tagscope)?"":" OR ")."catalog.parent=$tagged->id";
			}

		}

		foreach ($this->product->tags as $tag)
			if (!empty($tag->id))
				$tagscope .= (empty($tagscope)?"":" OR ")."catalog.parent=$tag->id";

		if (!empty($tagscope)) $tagscope = "($tagscope) AND catalog.taxonomy='$this->taxonomy'";

		$this->tag = "product-".$this->product->id;
		$this->name = __("Products related to","Shopp")." &quot;".stripslashes($this->product->name)."&quot;";
		$this->uri = urlencode($this->tag);
		$this->controls = false;

		$exclude = "";
		if (!empty($this->product->id)) $exclude = " AND p.id != {$this->product->id}";

		$this->loading = array(
			'columns'=>'count(DISTINCT catalog.id)+SUM(IF('.$tagscope.',100,0)) AS score',
			'joins'=>"LEFT JOIN $catalogtable AS catalog ON catalog.product=p.id LEFT JOIN $tagtable AS t ON t.id=catalog.parent AND catalog.product=p.id",
			'where'=>"($tagscope) $exclude",
			'orderby'=>'score DESC'
			);
		if (isset($options['order'])) $this->loading['order'] = $options['order'];
		if (isset($options['controls']) && value_is_true($options['controls']))
			unset($this->controls);
	}

}

class RandomProducts extends Collection {
	static $_slug = "random";

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __("Random Products","Shopp");
		$this->loading = array('order'=>'random');
		if (isset($options['exclude'])) {
			$where = array();
			$excludes = explode(",",$options['exclude']);
			global $Shopp;
			if (in_array('current-product',$excludes) &&
				isset($Shopp->Product->id)) $where[] = '(p.id != $Shopp->Product->id)';
			if (in_array('featured',$excludes)) $where[] = "(p.featured='off')";
			if (in_array('onsale',$excludes)) $where[] = "(pd.sale='off' OR pr.discount=0)";
			$this->loading['where'] = $where;
		}
		if (isset($options['columns'])) $this->loading['columns'] = $options['columns'];
	}
}

class PromoProducts extends Collection {
	static $_slug = "promo";

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;

		$id = urldecode($options['id']);

		$Promo = new Promotion($id);
		$this->name = $Promo->name;

		$pricetable = DatabaseObject::tablename(Price::$table);
		$this->loading = array('where' => "p.id IN (SELECT product FROM $pricetable WHERE 0 < FIND_IN_SET($Promo->id,discounts))");
	}
}


?>