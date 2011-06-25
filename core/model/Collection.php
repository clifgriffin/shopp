<?php
/**
 * Collection classes
 *
 * Library product collection models
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, May  5, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package Shopp
 * @since 1.0
 * @subpackage Collection
 **/

class ProductCollection implements Iterator {
	var $api = "collection";
	var $loaded = false;
	var $paged = false;
	var $pagination = false;
	var $products = array();
	var $resum = array();
	var $total = 0;

	private $_keys = array();
	private $_position = array();

	function load ($options=array()) {
		$Storefront =& ShoppStorefront();
		$Settings =& ShoppSettings();
		$Processing = new Product();
		$summary_table = DatabaseObject::tablename(ProductSummary::$table);

		$defaults = array(
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
			'load' => array(),		// Product data to load
			'inventory' => false,	// Flag for detecting inventory-based queries
			'debug' => false		// Output the query for debugging
		);
		$loading = array_merge($defaults,$options);
		extract($loading);

		// Setup pagination
		$this->paged = false;
		$this->pagination = $Settings->get('catalog_pagination');
		$paged = get_query_var('paged');
		$this->page = ((int)$paged > 0 || !is_numeric($paged))?$paged:1;

		// Hard product limit per category to keep resources "reasonable"
		$hardlimit = apply_filters('shopp_category_products_hardlimit',1000);

		// Enforce the where parameter as an array
		if (!is_array($where)) return new ShoppError('The "where" parameter for ProductCollection loading must be formatted as an array.','shopp_collection_load',SHOPP_DEBUG_ERR);

		// Check for inventory-based queries (for specialized cache support)
		$wherescan = join('',$where);
		if (strpos($wherescan,'s.inventory') !== false || strpos($wherescan,'s.stock') !== false)
			$inventory = true;

		if ($published) $where[] = "p.post_status='publish'";

		// Sort Order
		$orderby = false;
		$defaultOrder = $Settings->get('default_product_order');
		if (empty($defaultOrder)) $defaultOrder = '';
		$ordering = isset($Storefront->browsing['sortorder'])?
						$Storefront->browsing['sortorder']:$defaultOrder;
		if ($order !== false) $ordering = $order;
		switch ($ordering) {
			case 'bestselling': $orderby = "s.sold DESC,p.post_title ASC"; break;
			case 'highprice': $orderby = "maxprice DESC,p.post_title ASC"; break;
			case 'lowprice': $orderby = "minprice ASC,p.post_title ASC"; /* $useindex = "lowprice"; */ break;
			case 'newest': $orderby = "p.post_date DESC,p.post_title ASC"; break;
			case 'oldest': $orderby = "p.post_date ASC,p.post_title ASC"; /* $useindex = "oldest";	*/ break;
			case 'random': $orderby = "RAND(".crc32($Shopp->Shopping->session).")"; break;
			case 'chaos': $orderby = "RAND(".time().")"; break;
			case 'title': $orderby = "p.post_title ASC"; /* $useindex = "name"; */ break;
			case 'recommended':
			// default:
				// Need to add the catalog table for access to category-product priorities
				// if (!isset($this->smart)) {
				// 	$joins[$catalogtable] = "INNER JOIN $catalogtable AS c ON c.product=p.id AND c.parent='$this->id'";
				// 	$order = "c.priority ASC,p.name ASC";
				// } else $order = "p.name ASC";
				// $orderby = "p.post_title ASC";
				// break;
		}

		if (empty($orderby) && !empty($order)) $orderby = $order;
		elseif (empty($orderby)) $orderby = "p.post_title ASC";

		// Pagination
		if (empty($limit)) {
			if ($this->pagination > 0 && is_numeric($this->page) && value_is_true($pagination)) {
				if( !$this->pagination || $this->pagination < 0 )
					$this->pagination = $hardlimit;
				$start = ($this->pagination * ($this->page-1));

				$limit = "$start,$this->pagination";
			} else $limit = $hardlimit;
		}

		// Core query components

		// Load core product data and product summary columns
		$cols = array(	'p.ID','p.post_title','p.post_name','p.post_excerpt','p.post_status','p.post_date','p.post_modified',
						's.modified AS summed','s.sold','s.grossed','s.maxprice','s.minprice','s.stock','s.inventory','s.featured','s.variants','s.addons','s.sale');

		$columns = "SQL_CALC_FOUND_ROWS ".join(',',$cols).($columns !== false?','.$columns:'');
		$table = "$Processing->_table AS p";
		$where[] = "p.post_type='$Processing->_post_type'";
		$joins[$summary_table] = "LEFT OUTER JOIN $summary_table AS s ON s.product=p.ID";

		$options = compact('columns','useindex','table','joins','where','groupby','having','limit','orderby');
		$query = DB::select($options);

		if ($debug) echo $query.BR.BR;

		// Load from cached results if available, or run the query and cache the results
		$cachehash = md5($query);
		$cached = wp_cache_get($cachehash,'shopp_collection');
		if ($cached) {
			$this->products = $cached->products;
			$this->total = $cached->total;
		} else {
			$expire = apply_filters('shopp_collection_cache_expire',43200);

			$cache = new stdClass();
			$cache->products = $this->products = DB::query($query,'array',array($Processing,'loader'));
			$cache->total = $this->total = DB::query("SELECT FOUND_ROWS() as total",'auto','col','total');

			wp_cache_set($cachehash,$cache,'shopp_collection');

			if ($inventory) { // Keep track of inventory-based query caches
				$caches = $Settings->get('shopp_inventory_collection_caches');
				if (!is_array($caches)) $caches = array();
				$caches[] = $cachehash;
				$Settings->save('shopp_inventory_collection_caches',$caches);
			}

		}

		// Finish up pagination construction
		if ($this->pagination > 0 && $this->total > $this->pagination) {
			$this->pages = ceil($this->total / $this->pagination);
			if ($this->pages > 1) $this->paged = true;
		}

		// Load all associated product meta from other data sources
		$Processing->load_data($load,$this->products);

		// If products are missing summary data, resum them
		if (!empty($this->resum)) {
			$Processing->load_data(array('prices'),$this->resum);
		}

		$this->loaded = true;

		return ($this->size() > 0);
	}

	function pagelink ($page) {
		$type = isset($this->tag)?'tag':'category';
		$alpha = preg_match('/([a-z]|0\-9)/',$page);
		$prettyurl = "$type/$this->uri".($page > 1 || $alpha?"/page/$page":"");
		if ('catalog' == $this->uri) $prettyurl = ($page > 1 || $alpha?"page/$page":"");
		$queryvars = array("shopp_$type"=>$this->uri);
		if ($page > 1 || $alpha) $queryvars['paged'] = $page;

		return apply_filters('shopp_paged_link',shoppurl(SHOPP_PRETTYURLS?$prettyurl:$queryvars));
	}

	function workflow () {
		return array_keys($this->products);
	}

	function size () {
		return count($this->products);
	}

	/** Iterator implementation **/

	function current () {
		return $this->products[ $this->_keys[$this->_position] ];
	}

	function key () {
		return $this->_position;
	}

	function next () {
		++$this->_position;
	}

	function rewind () {
		$this->_position = 0;
		$this->_keys = array_keys($this->products);
	}

	function valid () {
		return isset($this->products[ $this->_keys[$this->_position] ]);
	}

}

// @todo Document ProductTaxonomy
class ProductTaxonomy extends ProductCollection {
	static $taxonomy = 'shopp_group';
	static $namespace = 'group';
	static $hierarchical = true;

	protected $context = 'group';

	var $api = 'taxonomy';
	var $id = false;
	var $meta = array();

	function __construct ($id=false,$key='id') {
		if (!$id) return;
		if ('id' != $key) $this->loadby($id,$key);
		else $this->load_term($id);
	}

	static function register ($class) {
		$slug = SHOPP_NAMESPACE_TAXONOMIES ? Storefront::slug().'/'.$class::$namespace : $class::$namespace;
		register_taxonomy($class::$taxonomy,array(Product::$posttype), array(
			'hierarchical' => $class::$hierarchical,
			'labels' => $class::labels($class),
			'show_ui' => true,
			'query_var' => true,
			'rewrite' => array( 'slug' => $slug ),
		));
	}

	static function labels () {
		return array(
			'name' => __('Groups','Shopp'),
			'singular_name' => __('Group','Shopp'),
			'search_items' => __('Search Group','Shopp'),
			'popular_items' => __('Popular','Shopp'),
			'all_items' => __('Show All','Shopp'),
			'parent_item' => __('Parent Group','Shopp'),
			'parent_item_colon' => __('Parent Group:','Shopp'),
			'edit_item' => __('Edit Group','Shopp'),
			'update_item' => __('Update Group','Shopp'),
			'add_new_item' => __('New Group','Shopp'),
			'new_item_name' => __('New Group Name','Shopp'),
			'separate_items_with_commas' => __('Separate groups with commas','Shopp'),
			'add_or_remove_items' => __('Add or remove groups','Shopp'),
			'choose_from_most_used' => __('Choose from the most used groups','Shopp')
		);
	}

	function load ($options=array()) {
		global $wpdb;

		$options['joins'][$wpdb->term_relationships] = "INNER JOIN $wpdb->term_relationships AS tr ON (p.ID=tr.object_id)";
		$options['joins'][$wpdb->term_taxonomy] = "INNER JOIN $wpdb->term_taxonomy AS tt ON (tr.term_taxonomy_id=tt.term_taxonomy_id AND tt.term_id=$this->id)";

		parent::load($options);
	}

	function load_term ($id) {
		$term = get_term($id,$this->taxonomy);
		if (empty($term->term_id)) return false;
		$this->populate($term);
	}

	/**
	 * Load a taxonomy by slug name
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param string $slug The slug name to load
	 * @return boolean loaded successfully or not
	 **/
	function loadby ($id,$key='id') {
		$term = get_term_by($key,$id,$this->taxonomy);
		if (empty($term->term_id)) return false;
		$this->populate($term);
	}

	function populate ($data) {
		foreach(get_object_vars($data) as $var => $value)
			$this->{$var} = $value;

		$this->id = $this->term_id;
	}

	function load_meta () {
		$meta = DatabaseObject::tablename(MetaObject::$table);
		DB::query("SELECT * FROM $meta WHERE parent=$this->id AND context='$this->context' AND type='meta'",'array',array($this,'metaloader'));
	}

	function metaloader (&$records,&$record) {
		$Meta = new MetaObject();
		$Meta->populate($record);
		$this->meta[$record->name] = $Meta;
		if (!isset($this->{$record->name}))
			$this->{$record->name} = &$Meta->value;
	}

	function save () {
		$properties = array('slug','description','parent');
		$updates = array_intersect_key(get_object_vars($this),$properties);

		if ($this->id) wp_update_term($this->name,$this->taxonomy,$updates);
		else wp_insert_term($this->name,$this->taxonomy,$updates);

		if (!$this->id) return false;

		// If the term successfully saves, save all meta data too
 		foreach ($this->meta as $Meta) {
			$Meta->parent = $this->id;
			$Meta->context = 'category';
			$Meta->save();
		}
	}

	function delete () {
		if (!$this->id) return false;

		// Remove WP taxonomy term
		$result = wp_delete_term($this->id,$this->taxonomy);

		// Remove meta data & images
		$meta = DatabaseObject::tablename(MetaObject::$table);
		DB::query("DELETE FROM $meta WHERE parent='$this->id' AND context='category'");

	}

	function tree ($taxonomy,$terms,&$children,&$count,&$results = array(),$page=1,$per_page=0,$parent=0,$level=0) {

		$start = ($page - 1) * $per_page;
		$end = $start + $per_page;

		foreach ($terms as $id => $term_parent) {
			if ( $end > $start && $count >= $end ) break;
			if ($term_parent != $parent ) continue;

			// Render parents when pagination starts in a branch
			if ( $count == $start && $term_parent > 0 ) {
				$parents = $parent_ids = array();
				$p = $term_parent;
				while ( $p ) {
					$terms_parent = get_term( $p, $taxonomy );
					$parents[] = $terms_parent;
					$p = $terms_parent->parent;

					if (in_array($p,$parent_ids)) break;

					$parent_ids[] = $p;
				}
				unset($parent_ids);

				$parent_count = count($parents);
				while ($terms_parent = array_pop($parents)) {
					$results[$terms_parent->term_id] = $terms_parent;
					$results[$terms_parent->term_id]->level = $level-$parent_count;
					$parent_count--;
				}
			}

			if ($count >= $start) {
				if (isset($results[$id])) continue;
				$results[$id] = get_term($id,$taxonomy);
				$results[$id]->level = $level;
				$results[$id]->_children = isset($children[$id]);
			}
			++$count;
			unset($terms[$id]);

			if (isset($children[$id]))
				self::tree($taxonomy,$terms,$children,$count,$results,$page,$per_page,$id,$level+1);
		}
	}


}
// @todo Document ProductCategory
class ProductCategory extends ProductTaxonomy {
	static $taxonomy = 'shopp_category';
	static $namespace = 'category';
	static $hierarchical = true;

	protected $context = 'category';
	var $api = 'category';

	function __construct ($id,$key='id') {
		$this->taxonomy = self::$taxonomy;
		parent::__construct($id,$key);
	}

	// static $table = "category";
	// var $loaded = false;
	// var $paged = false;
	// var $children = array();
	// var $child = false;
	// var $parent = 0;
	// var $total = 0;
	// var $description = "";
	// var $timestamp = false;
	// var $thumbnail = false;
	// var $products = array();
	// var $pricing = array();
	// var $filters = array();
	// var $loading = array();
	// var $images = array();
	// var $facetedmenus = "off";
	// var $published = true;
	// var $taxonomy = false;
	// var $depth = false;

	static function labels ($class) {
		return array(
			'name' => __('Categories','Shopp'),
			'singular_name' => __('Category','Shopp'),
			'search_items' => __('Search Categories','Shopp'),
			'popular_items' => __('Popular','Shopp'),
			'all_items' => __('Show All','Shopp'),
			'parent_item' => __('Parent Category','Shopp'),
			'parent_item_colon' => __('Parent Category:','Shopp'),
			'edit_item' => __('Edit Category','Shopp'),
			'update_item' => __('Update Category','Shopp'),
			'add_new_item' => __('New Category','Shopp'),
			'new_item_name' => __('New Category Name','Shopp')
		);
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
			$this->children[$child->id] = new ProductCategory();
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
	    $base = $Shopp->Settings->get('base_operations');

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
		    if ($base['vat']) {
				$Product = new Product($product->id);
				$Item = new Item($Product);
		        $taxrate = shopp_taxrate(null, true, $Item);
		    }

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
				if ($taxrate) $product->min['saleprice'] += $product->min['saleprice'] * $taxrate;
				if ($product->min['saleprice'] != $product->max['saleprice'])
					$pricing .= __("from ",'Shopp');
				$pricing .= money($product->min['saleprice']);
			} else {
				if ($taxrate) {
					$product->min['price'] += $product->min['price'] * $taxrate;
					$product->max['price'] += $product->max['price'] * $taxrate;
				}

				if ($product->min['price'] != $product->max['price'])
					$pricing .= __("from ",'Shopp');
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

		$categoryurl = get_term_link($this->name,$this->taxonomy);
		$alpha = preg_match('/([a-z]|0\-9)/',$page);
		$prettyurl = $categoryurl.($page > 1 || $alpha?"page/$page":"");
		if ($page > 1 || $alpha) $queryvars['paged'] = $page;

		$url = SHOPP_PRETTYURLS?$prettyurl:add_query_args($queryvars,$categoryurl);

		return apply_filters('shopp_paged_link',$url);
	}

} // END class ProductCategory


// @todo Document ProductTag
class ProductTag extends ProductTaxonomy {
	static $taxonomy = 'shopp_tag';
	static $namespace = 'tag';
	static $hierarchical = false;

	protected $context = 'tag';

	function __construct ($id,$key='id') {
		$this->taxonomy = self::$taxonomy;
		parent::__construct($id,$key);
	}

	static function labels ($class) {
		return array(
			'name' => __('Tags','Shopp'),
			'singular_name' => __('Tag','Shopp'),
			'search_items' => __('Search Tag','Shopp'),
			'popular_items' => __('Popular','Shopp'),
			'all_items' => __('Show All','Shopp'),
			'edit_item' => __('Edit Tag','Shopp'),
			'update_item' => __('Update Tag','Shopp'),
			'add_new_item' => __('New Tag','Shopp'),
			'new_item_name' => __('New Tag Name','Shopp'),
			'separate_items_with_commas' => __('Separate tags with commas','Shopp'),
			'add_or_remove_items' => sprintf(__('Type a tag name and press tab %s to add it.','Shopp'),'<abbr title="'.__('tab key','Shopp').'">&#8677;</abbr>'),
			'choose_from_most_used' => __('Type to search, or wait for popular tags&hellip;','Shopp')
		);
	}

}

// @todo Document SmartCollection
class SmartCollection extends ProductCollection {
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

	function load () {
		parent::load($this->loading);
	}

	function register () {

		if ('' == get_option('permalink_structure') ) return;

		$args['rewrite'] = wp_parse_args($args['rewrite'], array(
			'slug' => sanitize_title_with_dashes($taxonomy),
			'with_front' => true,
		));
		add_rewrite_tag("%$taxonomy%", '([^/]+)', $args['query_var'] ? "{$args['query_var']}=" : "taxonomy=$taxonomy&term=");
		add_permastruct($taxonomy, "{$args['rewrite']['slug']}/%$taxonomy%", $args['rewrite']['with_front']);
	}

}

// @todo Document CatalogProducts
class CatalogProducts extends SmartCollection {
	static $_slug = "catalog";

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __('Catalog Products','Shopp');
		if (isset($options['order'])) $this->loading['order'] = $options['order'];
	}

}

// @todo Document NewProducts
class NewProducts extends SmartCollection {
	static $_slug = "new";
	static $_auto = true;

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __('New Products','Shopp');
		$this->loading = array('order'=>'newest');
		if (isset($options['columns'])) $this->loading['columns'] = $options['columns'];
	}

}

// @todo Document FeaturedProducts
class FeaturedProducts extends SmartCollection {
	static $_slug = 'featured';
	static $_auto = true;

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __('Featured Products','Shopp');
		$this->loading = array('where'=>array("s.featured='on'"),'order'=>'newest');
	}

}

// @todo Document OnSaleProducts
class OnSaleProducts extends SmartCollection {
	static $_slug = "onsale";
	static $_auto = true;

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __("On Sale","Shopp");
		$this->loading = array('where'=>"p.sale='on'",'order'=>'p.modified DESC');
	}

}

// @todo Document BestsellerProducts
class BestsellerProducts extends SmartCollection {
	static $_slug = "bestsellers";
	static $_auto = true;

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __('Bestsellers','Shopp');
		$this->loading['order'] = 'bestselling';
		if (isset($options['where'])) $this->loading['where'] = $options['where'];
	}

	static function threshold () {
		// Get mean sold for bestselling threshold
		$summary = DatabaseObject::tablename(ProductSummary::$table);
		return DB::query("SELECT AVG(sold) AS threshold FROM $summary WHERE 0 < sold",'auto','col','threshold');
	}

}

// @todo Document SearchResults
class SearchResults extends SmartCollection {
	static $_slug = "search-results";

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$options['search'] = empty($options['search'])?"":stripslashes($options['search']);

		// Load search engine components
		if (!class_exists('SearchParser'))
			require(SHOPP_MODEL_PATH.'/Search.php');
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

		/*
			@todo Fix product id associations in product index
		*/
		$index = DatabaseObject::tablename(ContentIndex::$table);
		$this->loading = array(
			'joins'=>array($index => "INNER JOIN $index AS search ON search.product=p.ID"),
			'columns'=> "$score AS score",
			'where'=> array($where),
			'groupby'=>'p.ID',
			'orderby'=>'score DESC');
		if (!empty($pricematch)) $this->loading['having'] = $pricematch;
		if (isset($options['show'])) $this->loading['limit'] = $options['show'];

		// No search
		if (empty($options['search'])) $options['search'] = __('(no search terms)','Shopp');
		$this->name = __("Search Results for","Shopp").": {$options['search']}";

	}
}

// @todo Document TagProducts
class TagProducts extends SmartCollection {
	static $_slug = "tag";

	function smart ($options=array()) {
		$this->slug = self::$_slug;
		// $tagtable = DatabaseObject::tablename(CatalogTag::$table);
		// $catalogtable = DatabaseObject::tablename(Catalog::$table);
		// $this->taxonomy = get_catalog_taxonomy_id('tag');

		$terms = get_terms(ProductTag::$taxonomy);
		// print_r($terms);
		return;

		$this->tag = urldecode($options['tag']);
		$tagquery = "";
		if (strpos($options['tag'],',') !== false) {
			$tags = explode(",",$options['tag']);
			foreach ($tags as $tag)
				$tagquery .= empty($tagquery)?"tag.name='$tag'":" OR tag.name='$tag'";
		} else $tagquery = "tag.name='{$this->tag}'";

		$this->name = __("Products tagged","Shopp")." &quot;".stripslashes($this->tag)."&quot;";
		$this->uri = urlencode($this->tag);

		global $wpdb;
		$joins[$wpdb->term_relationships] = "INNER JOIN $wpdb->term_relationships AS tr ON (p.ID=tr.object_id)";
		$joins[$wpdb->term_taxonomy] = "INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id=tt.term_taxonomy_id";
		$where[] = "tt.term_id IN (".join(',',$scope).")";
		$columns = 'COUNT(p.ID) AS score';
		$groupby = 'p.ID';
		$order = 'score DESC';
		$this->loading = compact('columns','joins','where','groupby','order');

		// $this->loading = array('joins'=> array("INNER JOIN $catalogtable AS catalog ON p.id=catalog.product AND catalog.taxonomy='$this->taxonomy' JOIN $tagtable AS tag ON catalog.parent=tag.id"),'where' => $tagquery);

	}
}

// @todo Document ReleatedProducts
class RelatedProducts extends SmartCollection {
	static $_slug = "related";
	var $product = false;

	function smart ($options=array()) {
		$this->slug = self::$_slug;
		$where = array();
		$scope = array();

		$Product = ShoppProduct();
		$Order = ShoppOrder();
		$Cart = $Order->Cart;

		// Use the current product if available
		if (!empty($Product->id))
			$this->product = ShoppProduct();

		// Or load a product specified
		if (isset($options['product'])) {
			if ($options['product'] == "recent-cartitem") 			// Use most recently added item in the cart
				$this->product = new Product($Cart->Added->product);
			elseif (preg_match('/^[\d+]$/',$options['product']) !== false) 	// Load by specified id
				$this->product = new Product($options['product']);
			else
				$this->product = new Product($options['product'],'slug'); // Load by specified slug

		}

		if (isset($options['tagged'])) {
			$tagged = new ProductTag($options['tagged'],'name');
			if (!empty($tagged->id)) $scope[] = $tagged->id;
			$name = $tagged->name;
			$slug = $tagged->slug;
		}

		if (!empty($this->product->id)) {
			$name = $this->product->name;
			$slug = $this->product->slug;
			$where = array("p.id != {$this->product->id}");
			// Load the product's tags if they are not available
			if (empty($this->product->tags))
				$this->product->load_data(array('tags'));

			if (!$scope) $scope = array_keys($this->product->tags);

		}
		if (empty($scope)) return false;

		$this->name = __("Products related to","Shopp")." &quot;".stripslashes($name)."&quot;";
		$this->uri = urlencode($slug);
		$this->controls = false;

		global $wpdb;
		$joins[$wpdb->term_relationships] = "INNER JOIN $wpdb->term_relationships AS tr ON (p.ID=tr.object_id)";
		$joins[$wpdb->term_taxonomy] = "INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id=tt.term_taxonomy_id";
		$where[] = "tt.term_id IN (".join(',',$scope).")";
		$columns = 'COUNT(p.ID) AS score';
		$groupby = 'p.ID';
		$order = 'score DESC';
		$this->loading = compact('columns','joins','where','groupby','order');

		if (isset($options['order'])) $this->loading['order'] = $options['order'];
		if (isset($options['controls']) && value_is_true($options['controls']))
			unset($this->controls);
	}

}

// @todo Document RandomProducts
class RandomProducts extends SmartCollection {
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

// @todo Document PromoProducts
class PromoProducts extends SmartCollection {
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