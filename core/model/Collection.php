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
	var $api = 'collection';
	var $loaded = false;
	var $paged = false;
	var $pages = 1;
	var $pagination = false;
	var $tag = false;
	var $smart = false;
	var $filters = false;
	var $products = array();
	var $total = 0;

	private $_keys = array();
	private $_position = array();

	function load ($options=array()) {

		$slug = isset($this->slug) ? $this->slug : sanitize_key(get_class($this));

		$Storefront = ShoppStorefront();
		$Shopping = ShoppShopping();
		$Processing = new Product();
		$summary_table = DatabaseObject::tablename(ProductSummary::$table);

		$defaults = array(
			'columns' => false,		// Include extra columns (string) 'c.col1,c.col2…'
			'useindex' => false,	// FORCE INDEX to be used on the product table (string) 'indexname'
			'joins' => array(),		// JOIN tables array('INNER JOIN table AS t ON p.id=t.column')
			'where' => array(),		// WHERE query conditions array('x=y OR x=z','a!=b'…) (array elements are joined by AND
			'groupby' => false,		// GROUP BY column (string) 'column'
			'orderby' => false,		// ORDER BY columns ASC|DESC (string)
			'having' => array(),	// HAVING filters
			'limit' => false,		// Limit
			'order' => false,		// ORDER BY columns or named methods (string)
									// 'bestselling','highprice','lowprice','newest','oldest','random','chaos','title'

			'page' => false,		// Current page number to load
			'paged' => false,		// Entries per page to load
			'nostock' => null,		// Override to show products that are out of stock (string) 'on','off','yes','no'…
			'pagination' => true,	// Enable alpha pagination (string) 'alpha'
			'published' => true,	// Load published or unpublished products (string) 'on','off','yes','no'…
			'ids' => false,			// Flag for loading product IDs only
			'adjacent' => false,	//
			'product' => false,		//
			'load' => array(),		// Product data to load
			'inventory' => false,	// Flag for detecting inventory-based queries
			'debug' => false		// Output the query for debugging
		);
		$loading = array_merge($defaults,$options);
		$loading = apply_filters("shopp_{$slug}_collection_load_options",$loading);
		extract($loading);

		// Setup pagination
		$this->paged = false;
		$this->pagination = (false === $paged)?shopp_setting('catalog_pagination'):$paged;
		$page = (false === $page)?get_query_var('paged'):$page;
		$this->page = ((int)$page > 0 || preg_match('/(0\-9|[A-Z])/',$page) )?$page:1;

		// Hard product limit per category to keep resources "reasonable"
		$hardlimit = apply_filters('shopp_category_products_hardlimit',1000);

		// Enforce the where parameter as an array
		if (!is_array($where)) return new ShoppError('The "where" parameter for ProductCollection loading must be formatted as an array.','shopp_collection_load',SHOPP_DEBUG_ERR);

		// Inventory filtering
		if ( shopp_setting_enabled('inventory') && ((is_null($nostock) && !shopp_setting_enabled('outofstock_catalog')) || (!is_null($nostock) && !str_true($nostock))) )
			$where[] = "( s.inventory='off' OR (s.inventory='on' AND s.stock > 0) )";

		if (str_true($published)) $where[] = "p.post_status='publish'";

		// Sort Order
		if (!$orderby) {
			$defaultOrder = shopp_setting('default_product_order');
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
				case 'random': $orderby = "RAND(".crc32($Shopping->session).")"; break;
				case 'chaos': $orderby = "RAND(".time().")"; break;
				case 'reverse': $orderby = "p.post_title DESC"; break;
				case 'title': $orderby = "p.post_title ASC"; break;
				case 'recommended':
				default:
					if ($order === false) $orderby = (is_subclass_of($this,'ProductTaxonomy'))?"tr.term_order ASC,p.post_title ASC":"p.post_title ASC";
					else $orderby = $order;
					break;
			}
		}

		if (empty($orderby)) $orderby = 'p.post_title ASC';

		// Pagination
		if (empty($limit)) {
			if ($this->pagination > 0 && is_numeric($this->page) && str_true($pagination)) {
				if( !$this->pagination || $this->pagination < 0 )
					$this->pagination = $hardlimit;
				$start = ($this->pagination * ($this->page-1));

				$limit = "$start,$this->pagination";
			} else $limit = $hardlimit;
			$limited = false;	// Flag that the result set does not have forced limits
		} else $limited = true; // The result set has forced limits

		// Core query components

		// Load core product data and product summary columns
		$cols = array(	'p.ID','p.post_title','p.post_name','p.post_excerpt','p.post_status','p.post_date','p.post_modified',
						's.modified AS summed','s.sold','s.grossed','s.maxprice','s.minprice','s.ranges','s.taxed',
						's.stock','s.lowstock','s.inventory','s.featured','s.variants','s.addons','s.sale');

		if ($ids) $cols = array('p.ID');

		$columns = "SQL_CALC_FOUND_ROWS ".join(',',$cols).($columns !== false?','.$columns:'');
		$table = "$Processing->_table AS p";
		$where[] = "p.post_type='".Product::posttype()."'";
		$joins[$summary_table] = "LEFT OUTER JOIN $summary_table AS s ON s.product=p.ID";
		$options = compact('columns','useindex','table','joins','where','groupby','having','limit','orderby');


		// Alphabetic pagination
		if ('alpha' === $pagination || preg_match('/(0\-9|[A-Z])/',$page)) {
			// Setup Roman alphabet navigation
			$alphanav = array_merge(array('0-9'),range('A','Z'));
			$this->alpha = array_combine($alphanav,array_fill(0,count($alphanav),0));

			// Setup alphabetized index query
			$a = $options;
			$a['columns'] = "count(DISTINCT p.ID) AS total,IF(LEFT(p.post_title,1) REGEXP '[0-9]',LEFT(p.post_title,1),LEFT(SOUNDEX(p.post_title),1)) AS letter";
			$a['groupby'] = "letter";
			$alphaquery = DB::select($a);

			$cachehash = 'collection_alphanav_'.md5($alphaquery);
			$cached = wp_cache_get($cachehash,'shopp_collection');
			if ($cached) { // Load from object cache, if available
				$this->alpha = $cached;
				$cached = false;
			} else { // Run query and cache results
				$expire = apply_filters('shopp_collection_cache_expire',43200);
				$alpha = DB::query($alphaquery,'array',array($this,'alphatable'));
				wp_cache_set($cachehash,$alpha,'shopp_collection_alphanav');
			}

			$this->paged = true;
			if ($this->page == 1) $this->page = '0-9';
			$alphafilter = $this->page == "0-9"?
				"(LEFT(p.post_title,1) REGEXP '[0-9]') = 1":
				"IF(LEFT(p.post_title,1) REGEXP '[0-9]',LEFT(p.post_title,1),LEFT(SOUNDEX(p.post_title),1))='$this->page'";
			$options['where'][] = $alphafilter;
		}

		$query = DB::select($options);

		if ($debug) echo $query.BR.BR;

		// Load from cached results if available, or run the query and cache the results
		$cachehash = 'collection_'.md5($query);
		$cached = wp_cache_get($cachehash,'shopp_collection');
		if ($cached) {
			$this->products = $cached->products;
			$this->total = $cached->total;
		} else {
			$expire = apply_filters('shopp_collection_cache_expire',43200);

			$cache = new stdClass();

			if ($ids) $cache->products = $this->products = DB::query($query,'array','col','ID');
			else $cache->products = $this->products = DB::query($query,'array',array($Processing,'loader'));

			$cache->total = $this->total = DB::found();

			// If running a limited set, the reported total found should not exceed the limit (but can because of SQL_CALC_FOUND_ROWS)
			// Don't use the limit if it is offset
			if ($limited && false === strpos($limit,',')) $cache->total = $this->total = min($limit,$this->total);

			wp_cache_set($cachehash,$cache,'shopp_collection');
		}
		if (false === $this->products) $this->products = array();

		if ($ids) return ($this->size() > 0);

		// Finish up pagination construction
		if ($this->pagination > 0 && $this->total > $this->pagination) {
			$this->pages = ceil($this->total / $this->pagination);
			if ($this->pages > 1) $this->paged = true;
		}

		// Load all requested product meta from other data sources
		$Processing->load_data($load,$this->products);

		// If products are missing summary data, resum them
		if (isset($Processing->resum) && !empty($Processing->resum))
			$Processing->load_data(array('prices'),$Processing->resum);

		unset($Processing); // Free memory

		$this->loaded = true;

		return ($this->size() > 0);
	}

	function pagelink ($page) {
		$prettyurls = ( '' != get_option('permalink_structure') );

		$alpha = (false !== preg_match('/([a-z]|0\-9)/',$page));

		$namespace = get_class_property( get_class($this) ,'namespace');
		$prettyurl = "$namespace/$this->slug".($page > 1 || $alpha?"/page/$page":"");

		// Handle catalog landing page category pagination
		if (is_catalog_frontpage()) $prettyurl = ($page > 1 || $alpha?"page/$page":"");

		$queryvars = array($this->taxonomy=>$this->uri);
		if ($page > 1 || $alpha) $queryvars['paged'] = $page;

		return apply_filters('shopp_paged_link', shoppurl($prettyurls?user_trailingslashit($prettyurl):$queryvars, false) );
	}

	// Add alpha-pagination support to category/collection pagination rules
	function pagerewrites ($rewrites) {
		$rules = array_keys($rewrites);
		$queries = array_values($rewrites);

		foreach ($rules as &$rule)
			if (false !== strpos($rule,'/?([0-9]{1,})/?$'))
				$rule = str_replace('[0-9]','0\-9|[A-Z0-9]',$rule);

		return array_combine($rules,$queries);
	}

	function alphatable (&$records,&$record) {
		if (is_numeric($record->letter)) $this->alpha['0-9'] += $record->total;
		elseif (isset($this->alpha[ strtoupper($record->letter) ])) $this->alpha[ strtoupper($record->letter) ] = $record->total;
	}

	/**
	 * Iterates loaded products in buffered batches and generates a feed-friendly item record
	 *
	 * NOTE: To modify the output of the RSS generator, use
	 * the filter hooks provided in a separate plugin or
	 * in the theme functions.php file.
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @return string A feed item record
	 **/
	function feed () {
		$paged = 100; // Buffer 100 products at a time.
		$loop = false;

		$product = ShoppProduct();
		if ($product) {
			$loop = shopp($this,'products');
			$product = ShoppProduct();
		}

		if (! ($product || $loop)) {
			if (!$this->products) $page = 1;
			else $page = $this->page + 1;
			if ($this->pages > 0 && $page > $this->pages) return false;
			$this->load( array('load'=>array('prices','specs','categories','coverimages'), 'paged'=>$paged, 'page' => $page) );
			$loop = shopp($this,'products');
			$product = ShoppProduct();
			if (!$product) return false; // No products, bail
		}

	    if ( shopp_setting_enabled('tax_inclusive') ) {
			$TaxProduct = new Product($product->id);
	        $taxrate = shopp_taxrate(null, true, $TaxProduct);
	    }

		$item = array();
		$item['guid'] = shopp($product,'get-url');
		$item['title'] = $product->name;
		$item['link'] =  shopp($product,'get-url');
		$item['pubDate'] = date('D, d M Y H:i O',$product->publish);

		// Item Description
		$item['description'] = '';

		$item['description'] .= '<table><tr>';
		$Image = current($product->images);
		if (!empty($Image)) {
			$item['description'] .= '<td><a href="'.$item['link'].'" title="'.$product->name.'">';
			$item['description'] .= '<img src="'.esc_attr(add_query_string($Image->resizing(75,75,0),shoppurl($Image->id,'images'))).'" alt="'.$product->name.'" width="75" height="75" />';
			$item['description'] .= '</a></td>';
		}

		$pricing = "";
		if (str_true($product->sale)) {
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
		$item['description'] .= "<td><p><big>$pricing</big></p>";

		$item['description'] .= apply_filters('shopp_rss_description',($product->summary),$product).'</td></tr></table>';
		$item['description'] =
		 	'<![CDATA['.$item['description'].']]>';

		// Google Base Namespace
		// http://www.google.com/support/merchants/bin/answer.py?hl=en&answer=188494

		// Below are Google Base specific attributes
		// You can use the shopp_rss_item filter hook to add new item attributes or change the existing attributes

		if ($Image) $item['g:image_link'] = add_query_string($Image->resizing(400,400,0),shoppurl($Image->id,'images'));
		$item['g:condition'] = 'new';
		$item['g:availability'] = shopp_setting_enabled('inventory') && $product->outofstock?'out of stock':'in stock';

		$price = floatvalue(str_true($product->sale)?$product->min['saleprice']:$product->min['price']);
		if (!empty($price))	{
			$item['g:price'] = $price;
			$item['g:price_type'] = "starting";
		}

		// Include product_type using Shopp category taxonomies
		foreach ($product->categories as $category) {
			$ancestry = array($category->name);
			$ancestors = get_ancestors($category->term_id,$category->taxonomy);
			foreach ((array)$ancestors as $ancestor) {
				$term = get_term($ancestor,$category->taxonomy);
				if ($term) array_unshift($ancestry,$term->name);
			}
			$item['g:product_type['.$category->term_id.']'] = join(' > ',$ancestry);
		}

		$brand = shopp($product,'get-spec','name=Brand');
		if (!empty($brand)) $item['g:brand'] = $brand;

		$gtins = array('UPC','EAN','JAN','ISBN-13','ISBN-10','ISBN');
		foreach ($gtins as $id) {
			$gtin = shopp($product,'get-spec','name='.$id);
			if (!empty($gtin)) {
				$item['g:gtin'] = $gtin; break;
			}
		}
		$mpn = shopp($product,'get-spec','name=MPN');
		if (!empty($mpn)) $item['g:mpn'] = $mpn;

		// Check the product specs for matching Google Base information
		$g_props = array(
			'MPN' => 'mpn',
			'Color' => 'color',
			'Material' => 'material',
			'Pattern' => 'pattern',
			'Size' => 'size',
			'Gender' => 'gender',
			'Age Group' => 'age_group',
			'Google Product Category' => 'google_product_category'
		);
		foreach ( apply_filters('shopp_googlebase_spec_map', $g_props) as $name => $key ) {
			$value = shopp($product,'get-spec','name='.$name);
			if (!empty($value)) $item["g:$key"] = $value;
		}

		return apply_filters('shopp_rss_item',$item,$product);
	}

	function feeditem ($item) {
		foreach ($item as $key => $value) {
			$key = preg_replace('/\[\d+\]$/','',$key); // Remove duplicate tag identifiers
			$attrs = '';
			if (is_array($value)) {
				$rss = $value;
				$value = '';
				foreach ($rss as $name => $content) {
					if (empty($name)) $value = $content;
					else $attrs .= ' '.$name.'="'.esc_attr($content).'"';
				}
			}
			if (strpos($value,'<![CDATA[') === false) $value = esc_html($value);
			if (!empty($value)) echo "\t\t<$key$attrs>$value</$key>\n";
			else echo "\t\t<$key$attrs />\n";
		}
	}

	function worklist () {
		return $this->products;
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
		return isset($this->_keys[$this->_position]) && isset($this->products[ $this->_keys[$this->_position] ]);
	}

}

$ShoppTaxonomies = array();

// @todo Document ProductTaxonomy
class ProductTaxonomy extends ProductCollection {
	static $taxon = 'shopp_group';
	static $namespace = 'group';
	static $hierarchical = true;

	protected $context = 'group';

	var $api = 'taxonomy';
	var $id = false;
	var $meta = array();
	var $images = array();

	function __construct ($id=false,$key='id') {
		if (!$id) return;
		if ('id' != $key) $this->loadby($id,$key);
		else $this->load_term($id);
	}

	static function register ($class) {
		global $Shopp,$ShoppTaxonomies;

		$namespace = get_class_property($class,'namespace');
		$taxonomy = get_class_property($class,'taxon');
		$hierarchical = get_class_property($class,'hierarchical');
		$slug = SHOPP_NAMESPACE_TAXONOMIES ? Storefront::slug().'/'.$namespace : $namespace;
		register_taxonomy($taxonomy,array(Product::$posttype), array(
			'hierarchical' => $hierarchical,
			'labels' => call_user_func(array($class,'labels'),$class),
			'show_ui' => true,
			'query_var' => true,
			'rewrite' => array( 'slug' => $slug, 'with_front' => false ),
			'update_count_callback' => '_update_post_term_count',
			'capabilities' => array(
				'manage_terms' => 'shopp_categories',
				'edit_terms'   => 'shopp_categories',
				'delete_terms' => 'shopp_categories',
				'assign_terms' => 'shopp_categories',
			)
		));

		add_filter($taxonomy.'_rewrite_rules',array('ProductCollection','pagerewrites'));

		$ShoppTaxonomies[$taxonomy] = $class;
	}

	static function labels () {
		return array(
			'name' => __('Catalog Groups','Shopp'),
			'singular_name' => __('Catalog Group','Shopp'),
			'search_items' => __('Search Catalog Group','Shopp'),
			'popular_items' => __('Popular','Shopp'),
			'all_items' => __('Show All','Shopp'),
			'parent_item' => __('Parent Catalog Group','Shopp'),
			'parent_item_colon' => __('Parent Catalog Group:','Shopp'),
			'edit_item' => __('Edit Catalog Group','Shopp'),
			'update_item' => __('Update Catalog Group','Shopp'),
			'add_new_item' => __('New Catalog Group','Shopp'),
			'new_item_name' => __('New Catalog Group Name','Shopp'),
			'separate_items_with_commas' => __('Separate catalog groups with commas','Shopp'),
			'add_or_remove_items' => __('Add or remove catalog groups','Shopp'),
			'choose_from_most_used' => __('Choose from the most used catalog groups','Shopp')
		);
	}

	function load ($options=array()) {
		global $wpdb;
		$summary_table = DatabaseObject::tablename(ProductSummary::$table);

		$options['joins'][$wpdb->term_relationships] = "INNER JOIN $wpdb->term_relationships AS tr ON (p.ID=tr.object_id)";
		$options['joins'][$wpdb->term_taxonomy] = "INNER JOIN $wpdb->term_taxonomy AS tt ON (tr.term_taxonomy_id=tt.term_taxonomy_id AND tt.term_id=$this->id)";

		$loaded =  parent::load(apply_filters('shopp_taxonomy_load_options',$options));

		$query = "SELECT (AVG(maxprice)+AVG(minprice))/2 AS average,MAX(maxprice) AS max,MIN(IF(minprice>0,minprice,NULL)) AS min FROM $summary_table ".str_replace('p.ID','product',join(' ',$options['joins']));
		$this->pricing = DB::query($query);

		return $loaded;
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
		if (empty($this->id)) return;
		$meta = DatabaseObject::tablename(MetaObject::$table);
		DB::query("SELECT * FROM $meta WHERE parent=$this->id AND context='$this->context' AND type='meta'",'array',array($this,'metaloader'),'type');
	}

	function metaloader (&$records,&$record,$property=false) {
		if (empty($record->name)) return;

		$metamap = array(
			'image' => 'images',
			'meta' => 'meta'
		);

		$metaclass = array(
			'image' => 'CategoryImage',
			'meta' => 'MetaObject'
		);

		if ('type' == $property)
			$property = isset($metamap[$record->type])?$metamap[$record->type]:'meta';

		if (!isset($metaclass[$record->type])) $type = 'meta';

		$ObjectClass = $metaclass[$record->type];
		$Object = new $ObjectClass();
		$Object->populate($record);
		if (method_exists($Object,'expopulate'))
			$Object->expopulate();

		$this->{$property}[$Object->id] = &$Object;

		if ('meta' == $property) {
			if ( !isset($this->{$Object->name}) || empty($this->{$Object->name}) )
				$this->{$Object->name} = &$Object->value;
		}

		$record = $Object;
	}

	function save () {
		$properties = array('name'=>null,'slug'=>null,'description'=>null,'parent'=>null);
		$updates = array_intersect_key(get_object_vars($this),$properties);

		remove_filter('pre_term_description','wp_filter_kses'); // Allow HTML in category descriptions

		if ($this->id) wp_update_term($this->id,$this->taxonomy,$updates);
		else list($this->id, $this->term_taxonomy_id) = array_values(wp_insert_term($this->name, $this->taxonomy, $updates));

		if (!$this->id) return false;

		// If the term successfully saves, save all meta data too
		foreach ($this->meta as $name => $Meta) {

			if (is_a($Meta,'MetaObject')) {
				$MetaObject = $Meta;
			} else {
				$MetaObject = new MetaObject();
				$MetaObject->populate($Meta);
			}

			$MetaObject->name = $name;
			$MetaObject->parent = $this->id;
			$MetaObject->context = 'category';
			$MetaObject->save();
		}
		return true;
	}

	function delete () {
		if (!$this->id) return false;

		// Remove WP taxonomy term
		$status = wp_delete_term($this->id,$this->taxonomy);

		// Remove meta data & images
		$status = $status && shopp_rmv_meta ( $this->id, 'category' );
		return $status;

	}

	static function tree ($taxonomy,$terms,&$children,&$count,&$results = array(),$page=1,$per_page=0,$parent=0,$level=0) {

		$start = ($page - 1) * $per_page;
		$end = $start + $per_page;

		foreach ($terms as $id => $term_parent) {
			if ( $end > $start && $count >= $end ) break;
			if ($term_parent != $parent ) continue;

			// Render parents when pagination starts in a branch
			if ( $start > 0 && $count == $start && $term_parent > 0 ) {
				$parents = $parent_ids = array();
				$p = $term_parent;
				while ( $p ) {
					$terms_parent = get_term( $p, $taxonomy );
					$terms_parent->id = $terms_parent->term_id;
					$parents[] = $terms_parent;
					$p = $terms_parent->parent;

					if (in_array($p,$parent_ids)) break;

					$parent_ids[] = $p;
				}
				unset($parent_ids);

				$parent_count = count($parents);
				while ($terms_parent = array_pop($parents)) {
					$results[$terms_parent->term_id] = $terms_parent;
					$results[$terms_parent->term_id]->level = $level-($parent_count--);
				}
			}

			if ($count >= $start) {
				if (isset($results[$id])) continue;
				$results[$id] = get_term($id,$taxonomy);
				$results[$id]->id = $results[$id]->term_id;
				$results[$id]->level = $level;
				$results[$id]->_children = isset($children[$id]);
			}
			++$count;
			unset($terms[$id]);

			if (isset($children[$id]))
				self::tree($taxonomy,$terms,$children,$count,$results,$page,$per_page,$id,$level+1);
		}
	}

	function pagelink ($page) {
		$categoryurl = get_term_link($this->slug,$this->taxonomy);

		$alpha = (false !== preg_match('/([A-Z]|0\-9)/',$page));
		$prettyurl = trailingslashit($categoryurl).($page > 1 || $alpha?"page/$page":"");

		$queryvars = array($this->taxonomy=>$this->slug);
		if ($page > 1 || $alpha) $queryvars['paged'] = $page;

		$url = ( '' == get_option('permalink_structure') ? add_query_arg($queryvars,$categoryurl) : user_trailingslashit($prettyurl) );

		return apply_filters('shopp_paged_link',$url);
	}

}

// @todo Document ProductCategory
class ProductCategory extends ProductTaxonomy {
	static $taxon = 'shopp_category';
	static $namespace = 'category';
	static $hierarchical = true;

	protected $context = 'category';
	var $api = 'category';
	var $facets = array();
	var $filters = array();

	var $children = array();
	var $child = false;

	function __construct ($id=false,$key='id',$taxonomy=false) {
		$this->taxonomy = $taxonomy? $taxonomy : self::$taxon;
		parent::__construct($id,$key);
		if (!empty($this->id)) $this->load_meta();
		if (isset($this->facetedmenus) && str_true($this->facetedmenus))
			$this->filters();
	}

	static function labels ($class) {
		return array(
			'name' => __('Catalog Categories','Shopp'),
			'singular_name' => __('Category','Shopp'),
			'search_items' => __('Search Categories','Shopp'),
			'popular_items' => __('Popular','Shopp'),
			'all_items' => __('Show All','Shopp'),
			'parent_item' => __('Parent Category','Shopp'),
			'parent_item_colon' => __('Parent Category:','Shopp'),
			'edit_item' => __('Edit Category','Shopp'),
			'update_item' => __('Update Category','Shopp'),
			'add_new_item' => __('New Category','Shopp'),
			'new_item_name' => __('New Category Name','Shopp'),
			'menu_name' => __('Categories','Shopp')
		);
	}

	function load ($options = array()) {

		// $options['debug'] = true;
		if ($this->filters) add_filter('shopp_taxonomy_load_options',array($this,'facetsql'));

		return parent::load($options);
	}

	/**
	 * Parses parametric search filters from the requests query string
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	function filters () {
		if ('off' == $this->facetedmenus) return;
		$Storefront = ShoppStorefront();
		if (!$Storefront) return;

		if (!empty($this->facets)) return;
		$specs = $this->specs;

		$this->facets = array();
		if (isset($Storefront->browsing[$this->slug]))
			$this->filters = $Storefront->browsing[$this->slug];

		if ('disabled' != $this->pricerange) {
			array_unshift($specs,array('name' => apply_filters('shopp_category_price_facet_label',__('Price Filter','Shopp')),'slug'=> 'price','facetedmenu' => $this->pricerange));
		}

		foreach ($specs as $spec) {
			if (!isset($spec['facetedmenu']) || 'disabled' == $spec['facetedmenu']) continue;

			if (isset($spec['slug'])) $slug = $spec['slug'];
			else $slug = sanitize_title_with_dashes($spec['name']);
			$selected = isset($_GET[$slug]) && str_true(get_query_var('s_ff')) ? $_GET[$slug] : false;

			$Facet = new ProductCategoryFacet();
			$Facet->name = $spec['name'];
			$Facet->slug = $slug;
			$Facet->type = $spec['facetedmenu'];
			if (false !== $selected) {
				$Facet->selected = $selected;
				$this->filters[$slug] = $selected;
			} elseif (isset($this->filters[$slug])) $Facet->selected = $this->filters[$slug];
			$this->facets[$slug] = $Facet;
		}

		$this->filters = array_filter($this->filters);
		$Storefront->browsing[$this->slug] = $this->filters; // Save currently applied filters
	}

	function facetsql ($options) {
		if (!$this->filters) return array();

		$joins = $options['joins'];

		if (!isset($options['where'])) $options['where'] = array();

		$f = 1;
		$where = array();
		$filters = array();
		$facets = array();
		foreach ($this->filters as $filtered => $value) {
			$Facet = $this->facets[ $filtered ];
			if (empty($value)) continue;
			$name = $Facet->name;
			$value = urldecode($value);

			if (!is_array($value) && preg_match('/^.*?(\d+[\.\,\d]*).*?\-.*?(\d+[\.\,\d]*).*$/',$value,$matches)) {
				if ('price' == $Facet->slug) { // Prices require complex matching on summary prices in the main collection query
					list(,$min,$max) = array_map('floatvalue',$matches);
					if ($min > 0) $options['where'][] = "(s.minprice >= $min)";
					if ($max > 0) $options['where'][] = "(s.minprice > 0 AND s.minprice <= $max)";

				} else { // Spec-based numbers are somewhat more straightforward
					list(,$min,$max) = $matches;
					$ranges = array();
					if ($min > 0) $ranges[] = "numeral >= $min";
					if ($max > 0) $ranges[] = "numeral <= $max";
					// $filters[] = "(".join(' AND ',$ranges).")";
					$facets[] = sprintf("name='%s' AND %s",DB::escape($name),join(' AND ',$ranges));
					$filters[] = sprintf("FIND_IN_SET('%s',facets)",DB::escape($name));

				}

			} else { // No range, direct value match
				$filters[] = sprintf("FIND_IN_SET('%s=%s',facets)",DB::escape($name),DB::escape($value));
				$facets[] = sprintf("name='%s' AND value='%s'",DB::escape($name),DB::escape($value));
			}

		}

		$spectable = DatabaseObject::tablename(Spec::$table);
		$jointables = str_replace('p.ID','m.parent',join(' ',$joins)); // Rewrite the joins to use the spec table reference
		$having = "HAVING ".join(' AND ',$filters);

		$query = "SELECT m.parent,GROUP_CONCAT(m.name,IF(m.numeral>0,'','='),IF(m.numeral>0,'',m.value)) AS facets
					FROM $spectable AS m $jointables
					WHERE context='product' AND type='spec' AND (".join(' OR ',$facets).")
					GROUP BY m.parent
					$having";

		// Support cache accelleration
		$cachehash = 'collection_facet_'.md5($query);
		$cached = wp_cache_get($cachehash,'shopp_collection_facet');
		if ($cached) $set = $cached;
		else {
			$set = DB::query($query,'array','col','parent');
			wp_cache_set($cachehash,$set,'shopp_collection_facet');
		}

		if (!empty($set)) {
			$options['where'][] = "p.id IN (".join(',',$set).")";
			unset($options['joins']);
		}

		return $options;
	}

	function load_facets () {
		if ('off' == $this->facetedmenus) return;
		$output = '';
		$this->filters();
		$Storefront = ShoppStorefront();
		if (!$Storefront) return;
		$CategoryFilters =& $Storefront->browsing[$this->slug];

		$Filtered = new ProductCategory($this->id);
		$filtering = array_merge($Filtered->facetsql(array()),array('ids'=>true,'limit'=>1000));
		$Filtered->load($filtering);
		$ids = join(',',$Filtered->worklist());

		// Load price facet filters first
		if ('disabled' != $this->pricerange && isset($this->facets['price'])) {
			$Facet = $this->facets['price'];
			$Facet->link = add_query_arg(array('s_ff'=>'on',urlencode($Facet->slug) => ''),shopp('category','get-url'));

			if (!$this->loaded) $this->load();
			if ('auto' == $this->pricerange) $ranges = auto_ranges($this->pricing->average,$this->pricing->max,$this->pricing->min);
			else $ranges = $this->priceranges;

			$casewhen = '';
			foreach ($ranges as $index => $r) {
				$minprice = $r['max'] > 0?" AND minprice <= {$r['max']}":"";
				$casewhen .= " WHEN (minprice >= {$r['min']}$minprice) THEN $index";
			}

			$sumtable = DatabaseObject::tablename(ProductSummary::$table);
			$query = "SELECT count(*) AS total, CASE $casewhen END AS rangeid
				FROM $sumtable
				WHERE product IN ($ids) GROUP BY rangeid";
			$counts = DB::query($query,'array','col','total','rangeid');

			foreach ($ranges as $id => $range) {
				if ( ! isset($counts[$id]) || $counts[$id] < 1 ) continue;
				$label = money($range['min']).' &mdash; '.money($range['max']);
				if ($range['min'] == 0) $label = sprintf(__('Under %s','Shopp'),money($range['max']));
				if ($range['max'] == 0) $label = sprintf(__('%s and up','Shopp'),money($range['min']));

				$FacetFilter = new ProductCategoryFacetFilter();
				$FacetFilter->label = $label;
				$FacetFilter->param = urlencode($range['min'].'-'.$range['max']);
				$FacetFilter->count = $counts[$id];
				$Facet->filters[$FacetFilter->param] = $FacetFilter;
			}

		}

		// Load spec aggregation data
		$spectable = DatabaseObject::tablename(Spec::$table);
		$query = "SELECT spec.name,spec.value,
			IF(spec.numeral > 0,spec.name,spec.value) AS merge,
			count(*) AS count,avg(numeral) AS avg,max(numeral) AS max,min(numeral) AS min
			FROM $spectable AS spec
			WHERE spec.parent IN ($ids) AND spec.context='product' AND spec.type='spec' AND (spec.value != '' OR spec.numeral > 0) GROUP BY merge";

		$specdata = DB::query($query,'array','index','name',true);

		foreach ($this->specs as $spec) {
			if ('disabled' == $spec['facetedmenu']) continue;
			$slug = sanitize_title_with_dashes($spec['name']);
			if (!isset($this->facets[ $slug ])) continue;
			$Facet = &$this->facets[ $slug ];
			$Facet->link = add_query_arg(array('s_ff'=>'on',urlencode($Facet->slug) => ''),shopp('category','get-url'));

			// For custom menu presets

			switch ($spec['facetedmenu']) {
				case 'custom':
					$data = $specdata[ $Facet->name ];
					$counts = array();
					foreach ($data as $d) $counts[ $d->value ] = $d->count;
					foreach ($spec['options'] as $option) {
						if (!isset($counts[ $option['name'] ]) || $counts[ $option['name'] ] < 1) continue;
						$FacetFilter = new ProductCategoryFacetFilter();
						$FacetFilter->label = $option['name'];
						$FacetFilter->param = urlencode($option['name']);
						$FacetFilter->count = $counts[ $FacetFilter->label ];
						$Facet->filters[$FacetFilter->param] = $FacetFilter;
					}
					break;
				case 'ranges':
					foreach ($spec['options'] as $i => $option) {
						$matches = array();
						$format = '%s-%s';
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
							$href = add_query_arg($slug, urlencode(sprintf($format,'0',$range['min'])),$link);
							$label = __('Under ','Shopp').sprintf($format,$range['min']);
							$list .= '<li><a href="'.$href.'">'.$label.'</a></li>';
						}

						$href = add_query_arg($slug, urlencode(sprintf($format,$range['min'],$range['max'])), $link);
						$label = sprintf($format,$range['min']).' &mdash; '.sprintf($format,$range['max']);
						if ($range['max'] == 0) $label = sprintf($format,$range['min']).' '.__('and up','Shopp');
						$list .= '<li><a href="'.$href.'">'.$label.'</a></li>';
					}
					break;
				default:
					if (!isset($specdata[ $Facet->name  ])) break;
					$data = $specdata[ $Facet->name ];
					if ( ! is_array($data) ) $data = array($data);
					if ( $data[0]->min + $data[0]->max + $data[0]->avg == 0 ) { // Generate facet filters from text values
						foreach ($data as $option) {
							$FacetFilter = new ProductCategoryFacetFilter();
							$FacetFilter->label = $option->value;
							$FacetFilter->param = urlencode($option->value);
							$FacetFilter->count = $option->count;
							$Facet->filters[$FacetFilter->param] = $FacetFilter;
						}
					} else {
						$data = reset($data);

						$format = '%s';
						if (preg_match('/^(.*?)(\d+[\.\,\d]*)(.*)$/',$data->value,$matches))
							$format = $matches[1].'%s'.$matches[3];

						$ranges = auto_ranges($data->avg,$data->max,$data->min);
						$casewhen = '';
						foreach ($ranges as $index => $r)
							$casewhen .= " WHEN (spec.numeral >= {$r['min']} AND spec.numeral <= {$r['max']}) THEN $index";

						$query = "SELECT count(*) AS total, CASE $casewhen END AS rangeid
							FROM $spectable AS spec
							WHERE spec.parent IN ($ids) AND spec.name='$Facet->name' AND spec.context='product' AND spec.type='spec' AND spec.numeral > 0 GROUP BY rangeid";
						$counts = DB::query($query,'array','col','total','rangeid');

						foreach ($ranges as $id => $range) {
							if ( ! isset($counts[$id]) || $counts[$id] < 1 ) continue;

							$label = sprintf($format,$range['min']).' &mdash; '.sprintf($format,$range['max']);
							if ($range['min'] == 0) $label = __('Under ','Shopp').sprintf($format,$range['max']);
							elseif ($range['max'] == 0) $label = sprintf($format,$range['min']).' '.__('and up','Shopp');

							$FacetFilter = new ProductCategoryFacetFilter();
							$FacetFilter->label = $label;
							$FacetFilter->param = urlencode($range['min'].'-'.$range['max']);
							$FacetFilter->count = $counts[$id];
							$Facet->filters[$FacetFilter->param] = $FacetFilter;

						}
					}

			} // END switch

		}
	}

	/**
	 * Load sub-categories
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.2
	 *
	 * @see get_terms() in wp-includes/taxonomy.php for accepted $options values
	 *
	 * @param array $options Named array for WP get_terms
	 * @return boolean successfully loaded or not
	 **/
	function load_children ( $options=array() ) {
		if ( empty($this->id) ) return false;

		$taxonomy = self::$taxon;
		$categories = array(); $count = 0;
		$args = array_merge($options,array('child_of'=>$this->id,'fields'=>'id=>parent'));
		$terms = get_terms( $taxonomy, $args );
		$children = _get_term_hierarchy($taxonomy);
		ProductCategory::tree($taxonomy,$terms,$children,$count,$categories,1,0,$this->id);

		$this->children = array();
		foreach ( $categories as $id => $childterm ) {
			$this->children[$id] = new ProductCategory($id);
			$this->children[$id]->populate($childterm);
		}

		return !empty($this->children);

	}

	/**
	 * Loads images assigned to this category
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.2
	 *
	 * @return boolean Successful load or not
	 **/
	function load_images () {

		$ordering = shopp_setting('product_image_order');
		$orderby = shopp_setting('product_image_orderby');

		if ('RAND' == $ordering) $orderby = 'RAND()';
		else $orderby .= ' '.$ordering;

		$table = DatabaseObject::tablename(CategoryImage::$table);
		if (empty($this->id)) return false;
		$query = "SELECT * FROM $table WHERE parent=$this->id AND context='category' AND type='image' ORDER BY $orderby";
		$records = DB::query($query,'array',array($this,'metaloader'),'type');

		return true;
	}

	// function alphapages ($loading=array()) {
	// 	$db =& DB::get();
	//
	// 	$catalogtable = DatabaseObject::tablename(Catalog::$table);
	// 	$producttable = DatabaseObject::tablename(Product::$table);
	// 	$pricetable = DatabaseObject::tablename(Price::$table);
	//
	// 	$alphanav = range('A','Z');
	//
	// 	$ac =   "SELECT count(*) AS total,
	// 					IF(LEFT(p.name,1) REGEXP '[0-9]',LEFT(p.name,1),LEFT(SOUNDEX(p.name),1)) AS letter,
	// 					AVG((p.maxprice+p.minprice)/2) as avgprice
	// 				FROM $producttable AS p {$loading['useindex']}
	// 				{$loading['joins']}
	// 				WHERE {$loading['where']}
	// 				GROUP BY letter";
	//
	// 	$alpha = $db->query($ac,AS_ARRAY);
	//
	// 	$entry = new stdClass();
	// 	$entry->letter = false;
	// 	$entry->total = $entry->avg = 0;
	//
	// 	$existing = current($alpha);
	// 	if (!isset($this->alpha['0-9'])) {
	// 		$this->alpha['0-9'] = clone $entry;
	// 		$this->alpha['0-9']->letter = '0-9';
	// 	}
	//
	// 	while (is_numeric($existing->letter)) {
	// 		$this->alpha['0-9']->total += $existing->total;
	// 		$this->alpha['0-9']->avg = ($this->alpha['0-9']->avg+$existing->avg)/2;
	// 		$this->alpha['0-9']->letter = '0-9';
	// 		$existing = next($alpha);
	// 	}
	//
	// 	foreach ($alphanav as $letter) {
	// 		if ($existing->letter == $letter) {
	// 			$this->alpha[$letter] = $existing;
	// 			$existing = next($alpha);
	// 		} else {
	// 			$this->alpha[$letter] = clone $entry;
	// 			$this->alpha[$letter]->letter = $letter;
	// 		}
	// 	}
	//
	// }

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
	 * A functional list of support category sort options
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return array The list of supported sort methods
	 **/
	static function sortoptions () {
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

} // END class ProductCategory

class ProductCategoryFacet {

	var $name;	// Display name
	var $slug;	// Sanitized name
	var $type;
	var $link;	// Link to remove facet
	// var $min;	// Min numeral val
	// var $max;	// Max numeral val
	// var $avg;	// Avg numeral val

	var $filters = array();

	static function range_labels ($range) {

		if (preg_match('/^(.*?(\d+[\.\,\d]*).*?)\-(.*?(\d+[\.\,\d]*).*)$/',$filter,$matches)) {
			$label = $matches[1].' &mdash; '.$matches[3];
			if ($matches[2] == 0) $label = __('Under ','Shopp').$matches[3];
			if ($matches[4] == 0) $label = $matches[1].' '.__('and up','Shopp');
		}

	}

}

class ProductCategoryFacetFilter {

	var $label; // Display name
	var $param;	// Santized name
	var $count = 0;	// Product count

}


// @todo Document ProductTag
class ProductTag extends ProductTaxonomy {
	static $taxon = 'shopp_tag';
	static $namespace = 'tag';
	static $hierarchical = false;

	protected $context = 'tag';

	var $api = 'category';

	function __construct ($id=false,$key='id',$taxonomy=false) {
		$this->taxonomy = $taxonomy? $taxonomy : self::$taxon;
		parent::__construct($id,$key);
	}

	static function labels ($class) {
		return array(
			'name' => __('Catalog Tags','Shopp'),
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
			'choose_from_most_used' => __('Type to search, or wait for popular tags&hellip;','Shopp'),
			'menu_name' => __('Tags','Shopp')
		);
	}

}

// @todo Document SmartCollection
class SmartCollection extends ProductCollection {
	static $taxon = 'shopp_collection';
	static $namespace = 'collection';
	var $smart = true;
	var $slug = false;
	var $uri = false;
	var $name = false;
	var $loading = array();

	function __construct ($options=array()) {
		if (isset($options['show'])) $this->loading['limit'] = $options['show'];
		if (isset($options['pagination'])) $this->loading['pagination'] = $options['pagination'];
		$this->taxonomy = self::$taxon;
		$this->smart($options);
	}

	function load ($options=array()) {
		$this->loading = array_merge($this->loading,$options);
		parent::load($this->loading);
	}

	function register () {

		if ('' == get_option('permalink_structure') ) return;

		$args['rewrite'] = wp_parse_args($args['rewrite'], array(
			'slug' => sanitize_title_with_dashes($taxonomy),
			'with_front' => false,
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

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __('Featured Products','Shopp');
		$this->loading = array('where'=>array("s.featured='on'"),'order'=>'newest');
	}

}

// @todo Document OnSaleProducts
class OnSaleProducts extends SmartCollection {
	static $_slug = 'onsale';

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __('On Sale','Shopp');
		$this->loading = array('where'=>array("s.sale='on'"),'order'=>'p.post_modified DESC');
	}

}

// @todo Document BestsellerProducts
class BestsellerProducts extends SmartCollection {
	static $_slug = "bestsellers";
	static $_altslugs = array('bestsellers','bestseller','bestselling');

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __('Bestsellers','Shopp');

		if (isset($options['range']) && is_array($options['range']) && 2 == count($options['range'])) {
			$start = $options['range'][0];
			$end = $options['range'][1];
			if (!$end) $end = current_time('timestamp');
			$purchased = DatabaseObject::tablename(Purchased::$table);
			$this->loading['columns'] = "COUNT(*) AS sold";
			$this->loading['joins'] = array($purchased => "INNER JOIN $purchased as pur ON pur.product=p.id");
			$this->loading['where'] = array("pur.created BETWEEN '".DB::mkdatetime($start)."' AND '".DB::mkdatetime($end)."'");
			$this->loading['orderby'] = 'sold DESC';
			$this->loading['groupby'] = 'pur.product';
		} else {
			$this->loading['where'] = array(BestsellerProducts::threshold()." < s.sold");
			$this->loading['order'] = 'bestselling';	// Use overall bestselling stats
			$this->loading = array_merge($this->loading,$options);
		}
	}

	static function threshold () {
		// Get mean sold for bestselling threshold
		$summary = DatabaseObject::tablename(ProductSummary::$table);
		return (float)DB::query("SELECT AVG(sold) AS threshold FROM $summary WHERE 0 < sold",'auto','col','threshold');
	}

}

// @todo Document SearchResults
class SearchResults extends SmartCollection {
	static $_slug = 'search-results';
	static $_altslugs = array('search');
	var $search = false;

	function __construct ($options=array()) {
		parent::__construct($options);
		add_filter('shopp_themeapi_category_url',array($this,'permalink'),10,3);
	}

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$options['search'] = empty($options['search'])?"":stripslashes($options['search']);

		// $this->loading['debug'] = true;
		// Load search engine components
		if (!class_exists('SearchParser'))
			require(SHOPP_MODEL_PATH.'/Search.php');
		new SearchParser();
		new BooleanParser();
		new ShortwordParser();

		// Sanitize the search string
		$search = $options['search'];
		$this->search = $search;

		if (ShoppStorefront()) ShoppStorefront()->search = $search;

		// Price matching
		$prices = SearchParser::PriceMatching($search);
		if ($prices) {
			$pricematch = false;
			switch ($prices->op) {
				case '>': $pricematch = "minprice > $prices->target OR maxprice > $prices->target"; break;
				case '<': $pricematch = "minprice < $prices->target OR maxprice < $prices->target"; break;
				default: $pricematch = "minprice >= $prices->min AND maxprice <= $prices->max"; break;
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
			'joins'=>array($index => "INNER JOIN $index AS search ON search.product=p.ID"),
			'columns'=> "$score AS score",
			'where'=> array($where),
			'groupby'=>'p.ID',
			'order'=>'score DESC');
		if (!empty($pricematch)) $this->loading['having'] = array($pricematch);
		if (isset($options['show'])) $this->loading['limit'] = $options['show'];

		// No search
		if (empty($options['search'])) $options['search'] = __('(no search terms)','Shopp');
		$this->name = sprintf(__('Search Results for: %s','Shopp'),esc_html($options['search']));

	}

	function pagelink ($page) {
		$link = parent::pagelink($page);

		return add_query_arg(array('s'=>urlencode($this->search),'s_cs'=>1),$link);
	}

	function permalink ($result, $options, $O) {
		if (get_class($this) != get_class($O)) return $result;
		if (!isset($this->search) || !isset($O->search)) return $result;
		if ($this->search != $O->search) return $result;

		return add_query_arg(array('s'=>urlencode($this->search),'s_cs'=>1),$result);
	}

}

// @todo Document TagProducts
class TagProducts extends SmartCollection {
	static $_slug = "tag";

	function smart ($options=array()) {
		if (!isset($options['tag'])) {
			new ShoppError('No tag option provided for the requested TagProducts collection','doing_it_wrong',SHOPP_DEBUG_ERR);
			return false;
		}

		$this->slug = self::$_slug;
		$this->tag = stripslashes(urldecode($options['tag']));

		$term = get_term_by('name',$this->tag,ProductTag::$taxon);

		$tagquery = "";
		if (strpos($options['tag'],',') !== false) {
			$tags = explode(",",$options['tag']);
			foreach ($tags as $tag)
				$tagquery .= empty($tagquery)?"tag.name='$tag'":" OR tag.name='$tag'";
		} else $tagquery = "tag.name='{$this->tag}'";

		$this->name = sprintf(__('Products tagged "%s"','Shopp'),$this->tag);
		$this->uri = urlencode($this->tag);

		global $wpdb;
		$joins = array();
		$joins[$wpdb->term_relationships] = "INNER JOIN $wpdb->term_relationships AS tr ON (p.ID=tr.object_id)";
		$joins[$wpdb->term_taxonomy] = "INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id=tt.term_taxonomy_id";
		$where = array("tt.term_id='$term->term_id'");
		$columns = 'COUNT(p.ID) AS score';
		$groupby = 'p.ID';
		$order = 'score DESC';
		$this->loading = compact('columns','joins','where','groupby','order');

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

		// Use the current product is available
		if (!empty($Product->id))
			$this->product = ShoppProduct();

		// Or load a product specified
		if (isset($options['product'])) {
			if ($options['product'] == "recent-cartitem") 			// Use most recently added item in the cart
				$this->product = new Product($Cart->Added->product);
			elseif (preg_match('/^[\d+]$/',$options['product'])) 	// Load by specified id
				$this->product = new Product($options['product']);
			else $this->product = new Product($options['product'],'slug'); // Load by specified slug
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

// @todo Document AlsoBoughtProducts
class AlsoBoughtProducts extends SmartCollection {
	static $_slug = "alsobought";
	var $product = false;

	function smart ($options=array()) {
		$this->slug = self::$_slug;
		$this->name = __('Customers also bought&hellip;','Shopp');
		$this->uri = urlencode($this->slug);
		$this->controls = false;

		$where = array("true=false");
		$scope = array();

		$Product = ShoppProduct();
		$Order = ShoppOrder();
		$Cart = $Order->Cart;

		// Use the current product is available
		if (!empty($Product->id))
			$this->product = ShoppProduct();

		// Or load a product specified
		if (isset($options['product'])) {
			if ($options['product'] == "recent-cartitem") { 			// Use most recently added item in the cart
				$this->product = new Product($Cart->Added->product);
			} elseif (preg_match('/^[\d+]$/',$options['product'])) {	// Load by specified id
				$this->product = new Product($options['product']);
			} else {
				$this->product = new Product($options['product'],'slug'); // Load by specified slug
			}
		}

		if (empty($this->product->id)) return ($this->loading = compact('where'));
		$this->name = sprintf(__('Customers that bought "%s" also bought&hellip;','Shopp'),$this->product->name);

 		// Pearson correlation coefficient
		// @todo Add WP_Cache support
		$purchased = DatabaseObject::tablename(Purchased::$table);
		$matches = DB::query("SELECT  p2,((psum - (sum1 * sum2 / n)) / sqrt((sum1sq - pow(sum1, 2.0) / n) * (sum2sq - pow(sum2, 2.0) / n))) AS r, n
						FROM (
							SELECT n1.product AS p1,n2.product AS p2,SUM(n1.quantity) AS sum1,SUM(n2.quantity) AS sum2,
								SUM(n1.quantity * n1.quantity) AS sum1sq,SUM(n2.quantity * n2.quantity) AS sum2sq,
								SUM(n1.quantity * n2.quantity) AS psum,COUNT(*) AS n
							FROM $purchased AS n1
							LEFT JOIN $purchased AS n2 ON n1.purchase = n2.purchase
							WHERE n1.product != n2.product
							GROUP BY n1.product,n2.product
						) AS step1
						ORDER BY r DESC, n DESC",'array','col','p2');
		if (empty($matches)) return ($this->loading = compact('where'));

		$where = array("p.id IN (".join(',',$matches).")");
		$this->loading = compact('columns','joins','where','groupby','order');

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
			if (in_array('current-product',$excludes) &&
				isset(ShoppProduct()->id)) $where[] = '(p.id != '.ShoppProduct()->id.')';
			if (in_array('featured',$excludes)) $where[] = "(p.featured='off')";
			if (in_array('onsale',$excludes)) $where[] = "(pd.sale='off' OR pr.discount=0)";
			$this->loading['where'] = $where;
		}

		if (isset($options['columns'])) $this->loading['columns'] = $options['columns'];
	}
}

// @todo Document ViewedProducts
class ViewedProducts extends SmartCollection {
	static $_slug = "viewed";

	function smart ($options=array()) {
		$Storefront = ShoppStorefront();
		$viewed = isset($Storefront->viewed)?array_filter($Storefront->viewed):array();
		$this->slug = $this->uri = self::$_slug;
		$this->name = __('Recently Viewed','Shopp');
		$this->loading = array();
		if (empty($viewed)) $this->loading['where'] = 'true=false';
		$this->loading['where'] = array("p.id IN (".join(',',$viewed).")");
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
		$this->slug = $this->uri = sanitize_title_with_dashes($this->name);

		$pricetable = DatabaseObject::tablename(Price::$table);
		$this->loading = array('where' => array("p.id IN (SELECT product FROM $pricetable WHERE 0 < FIND_IN_SET($Promo->id,discounts))"));
	}

}

?>