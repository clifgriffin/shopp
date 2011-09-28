<?php
/**
 * Warehouse
 *
 * Flow controller for product management interfaces
 *
 * @author Jonathan Davis
 * @version 1.2
 * @copyright Ingenesis Limited, September 15, 2011
 * @package shopp
 * @subpackage products
 **/

class Warehouse extends AdminController {

	var $views = array('featured','published','onsale','bestselling','inventory','trash');
	var $view = 'all';
	var $worklist = array();

	/**
	 * Store constructor
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * @version 1.2
	 *
	 * @return void
	 **/
	function __construct () {
		parent::__construct();

		ShoppingObject::store('worklist',$this->worklist);

		if ('off' == shopp_setting('inventory'))
			array_splice($this->views,4,1);

		if (isset($_GET['view']) && in_array($_GET['view'],$this->views))
			$this->view = $_GET['view'];

		if (!empty($_GET['id'])) {
			wp_enqueue_script('jquery-ui-draggable');
			wp_enqueue_script('postbox');
			wp_enqueue_script('wp-lists');
			if ( user_can_richedit() ) {
				wp_enqueue_script('editor');
				wp_enqueue_script('quicktags');
				add_action( 'admin_print_footer_scripts', 'wp_tiny_mce', 20 );
			}

			shopp_enqueue_script('colorbox');
			shopp_enqueue_script('editors');
			shopp_enqueue_script('scalecrop');
			shopp_enqueue_script('calendar');
			shopp_enqueue_script('product-editor');
			shopp_enqueue_script('priceline');
			shopp_enqueue_script('ocupload');
			shopp_enqueue_script('swfupload');
			shopp_enqueue_script('jquery-tmpl');
			shopp_enqueue_script('suggest');
			shopp_enqueue_script('search-select');
			shopp_enqueue_script('shopp-swfupload-queue');
			do_action('shopp_product_editor_scripts');
			add_action('admin_head',array(&$this,'layout'));
		} else add_action('admin_print_scripts',array(&$this,'columns'));

		if ('inventory' == $this->view && 'on' == shopp_setting('inventory'))
			do_action('shopp_inventory_manager_scripts');

		add_action('load-toplevel_page_shopp-products',array(&$this,'workflow'));
		do_action('shopp_product_admin_scripts');

		// Load the search model for indexing
		if (!class_exists('ContentParser'))
			require(SHOPP_MODEL_PATH.'/Search.php');
		new ContentParser();
		add_action('shopp_product_saved',array(&$this,'index'),99,1);
	}

	/**
	 * Parses admin requests to determine which interface to display
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * @version 1.2
	 *
	 * @return void
	 **/
	function admin () {
		if (!empty($_GET['id'])) $this->editor();
		else {
			$this->manager();

			global $Products;
			if ($Products->total == 0) return;

			// Save workflow list
			$this->worklist = $this->manager(true);
			$this->worklist['query'] = $_GET;
		}
	}

	/**
	 * Handles loading, saving and deleting products in the context of workflows
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.2
	 *
	 * @return void
	 **/
	function workflow () {
		global $Shopp,$post;

		$defaults = array(
			'page' => false,
			'action' => false,
			'selected' => array(),
			'id' => false,
			'save' => false,
			'duplicate' => false,
			'next' => false
			);
		$args = array_merge($defaults,$_REQUEST);
		extract($args,EXTR_SKIP);

		if (!is_array($selected)) $selected = array($selected);

		if (!defined('WP_ADMIN') || !isset($page)
			|| $page != $this->Admin->pagename('products'))
				return false;

		$adminurl = admin_url('admin.php');

		if ($page == $this->Admin->pagename('products') && ($action !== false || isset($_GET['delete_all']))) {
			if (isset($_GET['delete_all'])) $action = 'emptytrash';
			switch ($action) {
				case 'publish': 	Product::publishset($selected,'publish'); break;
				case 'unpublish': 	Product::publishset($selected,'draft'); break;
				case 'feature': 	Product::featureset($selected,'on'); break;
				case 'defeature': 	Product::featureset($selected,'off'); break;
				case 'restore': 	Product::publishset($selected,'draft'); break;
				case 'trash': 		Product::publishset($selected,'trash'); break;
				case 'delete':
					foreach ($selected as $id) {
						$P = new Product($id); $P->delete();
					} break;
				case 'emptytrash':
					$Template = new Product();
					$trash = DB::query("SELECT ID FROM $Template->_table WHERE post_status='trash' AND post_type='".$Template->posttype()."'",'array','col','ID');
					foreach ($trash as $id) {
						$P = new Product($id); $P->delete();
					} break;
			}
			wp_cache_delete('shopp_product_subcounts');
			$redirect = add_query_arg($_GET,$adminurl);
			$redirect = remove_query_arg( array('action','selected','delete_all'),$redirect);
			shopp_redirect($redirect);
		}

		if ($duplicate) {
			$Product = new Product($duplicate);
			$Product->duplicate();
			shopp_redirect(add_query_arg('page',$this->Admin->pagename('products'),$adminurl));
		}

		if (isset($id) && $id != "new") {
			$Shopp->Product = new Product($id);
			$Shopp->Product->load_data();
		} else $Shopp->Product = new Product();

		if ($save) {
			wp_cache_delete('shopp_product_subcounts');
			$this->save($Shopp->Product);
			$this->Notice = sprintf(__('%s has been saved.','Shopp'),'<strong>'.stripslashes($Shopp->Product->name).'</strong>');

			// Workflow handler
			if (isset($_REQUEST['settings']) && isset($_REQUEST['settings']['workflow'])) {
				$workflow = $_REQUEST['settings']['workflow'];
				$worklist = $this->worklist;
				$working = array_search($id,$this->worklist);

				switch($workflow) {
					case 'close': $next = 'close'; break;
					case 'new': $next = 'new'; break;
					case 'next': $key = $working+1; break;
					case 'previous': $key = $working-1; break;
				}

				if (isset($key)) $next = isset($worklist[$key]) ? $worklist[$key] : 'close';
			}

			if ($next) {
				if (isset($this->worklist['query'])) $query = array_merge($_GET,$this->worklist['query']);
				$redirect = add_query_arg($query,$adminurl);
				$cleanup = array('action','selected','delete_all');
				if ('close' == $next) { $cleanup[] = 'id'; $next = false; }
				$redirect = remove_query_arg($cleanup, $redirect);
				if ($next) $redirect = add_query_arg('id',$next,$redirect);
				shopp_redirect($redirect);
			}

			if (empty($id)) $id = $Shopp->Product->id;
			$Shopp->Product = new Product($id);
			$Shopp->Product->load_data();
		}

		// WP post type editing support for other plugins
		if (!empty($Shopp->Product->id))
			$post = get_post($Shopp->Product->id);

	}

	/**
	 * Interface processor for the product list manager
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.2
	 *
	 * @param boolean $workflow True to get workflow data
	 * @return void
	 **/
	function manager ($workflow=false) {
		global $Shopp,$Products;

		if ( ! current_user_can('shopp_products') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$defaults = array(
			'cat' => false,
			'pagenum' => 1,
			'per_page' => 20,
			's' => '',
			'sl' => '',
			'matchcol' => '',
			'view' => $this->view,
			'is_inventory' => false,
			'is_trash' => false,
			'is_bestselling' => false,
			'categories_menu' => false,
			'inventory_menu' => false,
			'lowstock' => 0,
			'columns' => '',
			'where' => array(),
			'joins' => array()
		);

		$args = array_merge($defaults,$_GET);
		extract($args,EXTR_SKIP);

		$url = add_query_arg(array_merge($_GET,array('page'=>$this->Admin->pagename('products'))),admin_url('admin.php'));

		if (!$workflow) {
			if (empty($categories)) $categories = array('');

			$categories_menu = wp_dropdown_categories(array(
				'show_option_all' => __('View all categories','Shopp'),
				'show_option_none' => __('Uncategorized','Shopp'),
				'hide_empty' => 0,
				'hierarchical' => 1,
				'show_count' => 0,
				'orderby' => 'name',
				'selected' => $cat,
				'echo' => 0,
				'taxonomy' => 'shopp_category'
			));

			if ('on' == shopp_setting('inventory')) {
				$inventory_filters = array(
					'all' => __('View all products','Shopp'),
					'is' => __('In stock','Shopp'),
					'ls' => __('Low stock','Shopp'),
					'oos' => __('Out-of-stock','Shopp'),
					'ns' => __('Not stocked','Shopp')
				);
				$inventory_menu = '<select name="sl">'.menuoptions($inventory_filters,$sl,true).'</select>';
			}
		}

		$subs = array(
			'all' => 		array('label' => __('All','Shopp'), 		'where'=>array("p.post_status != 'trash'")),
			'published' => 	array('label' => __('Published','Shopp'),	'where'=>array("p.post_status='publish'")),
			'onsale' => 	array('label' => __('On Sale','Shopp'),		'where'=>array("s.sale='on' AND p.post_status != 'trash'")),
			'featured' => 	array('label' => __('Featured','Shopp'),	'where'=>array("s.featured='on' AND p.post_status != 'trash'")),
			'bestselling'=> array('label' => __('Bestselling','Shopp'),	'where'=>array("p.post_status != 'trash'"),'order' => 'bestselling'),
			'inventory' => 	array('label' => __('Inventory','Shopp'),	'where'=>array("s.inventory='on' AND p.post_status != 'trash'"),'grouping'=>'pt.id'),
			'trash' => 		array('label' => __('Trash','Shopp'),		'where'=>array("p.post_status = 'trash'"))
		);

		if ('off' == shopp_setting('inventory')) unset($subs['inventory']);

		switch ($view) {
			case 'inventory':
				if ('on' == shopp_setting('inventory')) $is_inventory = true;
				break;
			case 'trash': $is_trash = true; break;
			case 'bestselling': $is_bestselling = true; break;
		}

		if ($is_inventory) $per_page = 50;

		$pagenum = absint( $pagenum );
		$start = ($per_page * ($pagenum-1));

		if (!empty($s)) {
			$SearchResults = new SearchResults(array('search'=>$s,'load'=>array()));
			$SearchResults->load();
			$ids = array_keys($SearchResults->products);
			$where[] = "p.ID IN (".join(',',$ids).")";
		}

		if (!empty($cat)) {
			global $wpdb;
			$joins[$wpdb->term_relationships] = "INNER JOIN $wpdb->term_relationships AS tr ON (p.ID=tr.object_id)";
			$joins[$wpdb->term_taxonomy] = "INNER JOIN $wpdb->term_taxonomy AS tt ON (tr.term_taxonomy_id=tt.term_taxonomy_id AND tt.term_id=$cat)";
		}

		if (!empty($sl) && 'on' == shopp_setting('inventory')) {
			switch($sl) {
				case "ns": $where[] = "s.inventory='off'"; break;
				case "oos":
					$where[] = "(s.inventory='on' AND s.stock = 0)";
					break;
				case "ls":
					$ls = shopp_setting('lowstock_level');
					if (empty($ls)) $ls = '0';
					$where[] = "(s.inventory='on' AND s.stock <= $ls AND s.stock > 0)";
					break;
				case "is": $where[] = "(s.inventory='on' AND s.stock > 0)";
			}
		}

		$lowstock = shopp_setting('lowstock_level');
		if (str_true(shopp_setting('tax_inclusive'))) $taxrate = shopp_taxrate();
		if (empty($taxrate)) $taxrate = 0;

		// Setup queries
		$pd = WPDatabaseObject::tablename(Product::$table);
		$pt = DatabaseObject::tablename(Price::$table);
		$ps = DatabaseObject::tablename(ProductSummary::$table);

		$where = array_merge($where,$subs[$this->view]['where']);
		$order = false;
		if (isset($subs[$this->view]['order'])) $order = $subs[$this->view]['order'];

		if (in_array($this->view,array('onsale','featured','inventory')))
			$joins[$ps] = "INNER JOIN $ps AS s ON p.ID=s.product";

		$loading = array(
			'where' => $where,
			'joins' => $joins,
			'limit'=>"$start,$per_page",
			'load' => array('categories','coverimages'),
			'published' => false,
			'order' => $order
		);

		if ($is_inventory) { // Override for inventory products
			$loading = array(
				'columns' => "CONCAT(p.post_title,': ',pt.label) AS post_title,pt.sku AS sku",
				'joins' => array($pt => "INNER JOIN $pt AS pt ON p.ID=pt.product"),
				'where' => $where,
				'groupby' => 'pt.id',
				'order' => 'p.ID,pt.sortorder',
				'limit'=>"$start,$per_page",
				'published' => false
			);
		}

		// Override loading product meta and limiting by pagination in the workflow list
		if ($workflow) {
			unset($loading['limit']);
			$loading['ids'] = true;
			$loading['load'] = array();
		};

		$Products = new ProductCollection();
		$Products->load($loading);

		// Return a list of product keys for workflow list requests
		if ($workflow) return $Products->workflow();

		// Get sub-screen counts
		$subcounts = wp_cache_get('shopp_product_subcounts','shopp_admin');
		if ($subcounts) {
			foreach ($subcounts as $name => $total)
				if (isset($subs[$name])) $subs[$name]['total'] = $total;
		} else {
			$subcounts = array();
			foreach ($subs as $name => &$subquery) {
				$subquery['total'] = 0;
				$query = array(
					'columns' => "count(*) AS total",
					'table' => "$pd as p",
					'joins' => array(),
					'where' => array()
				);

				$query = array_merge($query,$subquery);
				$query['where'][] = "p.post_type='shopp_product'";

				if (in_array($name,array('onsale','bestselling','featured','inventory')))
					$query['joins'][$ps] = "INNER JOIN $ps AS s ON p.ID=s.product";

				$query = DB::select($query);
				$subquery['total'] = DB::query($query,'auto','col','total');
				$subcounts[$name] = $subquery['total'];
			}
			wp_cache_set('shopp_product_subcounts',$subcounts,'shopp_admin');
		}

		$actions_menu = array(
			'publish' => __('Publish','Shopp'),
			'unpublish' => __('Unpublish','Shopp'),
			'feature' => __('Feature','Shopp'),
			'defeature' => __('De-feature','Shopp'),
			'trash' => __('Move to trash','Shopp')
		);

		if ($is_trash) {
			$actions_menu = array(
				'restore' => __('Restore','Shopp'),
				'delete' => __('Delete permanently','Shopp')
			);
		}

		$num_pages = ceil($Products->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg(array("edit"=>null,'pagenum' => '%#%')),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum,
		));

		$path = SHOPP_ADMIN_PATH."/products";
		$ui = "products.php";
		switch ($view) {
			case 'inventory': if ('on' == shopp_setting('inventory')) $ui = "inventory.php"; break;
		}
		include("$path/$ui");
	}

	/**
	 * Registers the column headers for the product list manager
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function columns () {

		$headings = array(
			'default' => array(
				'cb'=>'<input type="checkbox" />',
				'name'=>__('Name','Shopp'),
				'category'=>__('Category','Shopp'),
				'price'=>__('Price','Shopp'),
				'inventory'=>__('Inventory','Shopp'),
				'featured'=>__('Featured','Shopp'),
				'date'=>__('Date','Shopp')
			),
			'inventory' => array(
				'inventory'=>__('Inventory','Shopp'),
				'sku'=>__('SKU','Shopp'),
				'name'=>__('Name','Shopp')
			),
			'bestselling' => array(
				'cb'=>'<input type="checkbox" />',
				'name'=>__('Name','Shopp'),
				'sold'=>__('Sold','Shopp'),
				'gross'=>__('Gross Sales','Shopp'),
				'price'=>__('Price','Shopp'),
				'inventory'=>__('Inventory','Shopp'),
				'featured'=>__('Featured','Shopp'),
				'date'=>__('Date','Shopp')
			)
		);

		$columns = isset($headings[$this->view]) ? $headings[$this->view] : $headings['default'];

		if ('on' != shopp_setting('inventory'))
			unset($columns['inventory']);

		// Remove category column from the "trash" view
		if ('trash' == $this->view) unset($columns['category']);

		register_column_headers('shopp_page_shopp-products', $columns);
	}

	/**
	 * Provides overall layout for the product editor interface
	 *
	 * Makes use of WordPress postboxes to generate panels (box) content
	 * containers that are customizable with drag & drop, collapsable, and
	 * can be toggled to be hidden or visible in the interface.
	 *
	 * @author Jonathan Davis
	 * @return
	 **/
	function layout () {
		global $Shopp;
		$Admin =& $Shopp->Flow->Admin;
		include(SHOPP_ADMIN_PATH."/products/ui.php");
	}

	/**
	 * Interface processor for the product editor
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function editor () {
		global $Shopp;

		if ( ! current_user_can('shopp_products') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if (empty($Shopp->Product)) {
			$Product = new Product();
			$Product->status = "publish";
		} else $Product = $Shopp->Product;

		$Product->slug = apply_filters('editable_slug',$Product->slug);
		$permalink = trailingslashit(shoppurl());

		$Price = new Price();

		$priceTypes = Price::types();
		$billPeriods = Price::periods();

		$workflows = array(
			"continue" => __('Continue Editing','Shopp'),
			"close" => __('Products Manager','Shopp'),
			"new" => __('New Product','Shopp'),
			"next" => __('Edit Next','Shopp'),
			"previous" => __('Edit Previous','Shopp')
		);

		$taglist = array();
		foreach ($Product->tags as $tag) $taglist[] = $tag->name;

		if ($Product->id && !empty($Product->images)) {
			$ids = join(',',array_keys($Product->images));
			$CoverImage = reset($Product->images);
			$image_table = $CoverImage->_table;
			//array($Image,'loader')
			$cropped = DB::query("SELECT * FROM $image_table WHERE context='image' AND type='image' AND '2'=SUBSTRING_INDEX(SUBSTRING_INDEX(name,'_',4),'_',-1) AND parent IN ($ids)",'array');
		}

		// if ($Product->id) {
		// 	$ProductImage = new ProductImage();
		// 	$results = $db->query("SELECT * FROM $ProductImage->_table WHERE context='product' AND parent=$Product->id AND type='image' ORDER BY sortorder",AS_ARRAY);
		//
		// 	$ProductImages = array();
		// 	foreach ((array)$results as $i => $image) {
		// 		$image->value = unserialize($image->value);
		// 		$ProductImages[$i] = new ProductImage();
		// 		$ProductImages[$i]->copydata($image,false,array());
		// 		$ProductImages[$i]->expopulate();
		//
		// 		// Load any cropped image cache
		// 		$cropped = $db->query("SELECT * FROM $ProductImage->_table WHERE context='image' AND type='image' AND parent='$image->id' AND '2'=SUBSTRING_INDEX(SUBSTRING_INDEX(name,'_',4),'_',-1)",AS_ARRAY);
		// 		foreach ((array)$cropped as $c => $cache) {
		// 			$cache->value = unserialize($cache->value);
		// 			$CachedImage = new ProductImage();
		// 			$CachedImage->copydata($cache,false,array());
		// 			$CachedImage->expopulate();
		// 			$ProductImages[$i]->cropped[$c] = $CachedImage;
		// 		}
		//
		// 	}
		// }

		$shiprates = shopp_setting('shipping_rates');
		if (!empty($shiprates)) ksort($shiprates);

		$uploader = shopp_setting('uploader_pref');
		if (!$uploader) $uploader = 'flash';

		$process = (!empty($Product->id)?$Product->id:'new');
		$_POST['action'] = add_query_arg(array_merge($_GET,array('page'=>$this->Admin->pagename('products'))),admin_url('admin.php'));
		$post_type = Product::posttype();

		include(SHOPP_ADMIN_PATH."/products/editor.php");

	}

	/**
	 * Handles saving updates from the product editor
	 *
	 * Saves all product related information which includes core product data
	 * and supporting elements such as images, digital downloads, tags,
	 * assigned categories, specs and pricing variations.
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param Product $Product
	 * @return void
	 **/
	function save (Product $Product) {
		check_admin_referer('shopp-save-product');

		if ( ! current_user_can('shopp_products') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		ShoppSettings()->saveform(); // Save workflow setting

		// Get needed settings
		$taxrate = 0;
		if (str_true(shopp_setting('tax_inclusive'))) $taxrate = shopp_taxrate(null,true,$Product);

		// Set publish date
		if ('publish' == $_POST['status']) {
			$publishing = isset($_POST['publish'])?$_POST['publish']:array();
			$fields = array('month' => '','date' => '','year' => '','hour'=>'','minute'=>'','meridiem'=>'');
			$publishdate = join('',array_merge($fields,$publishing));
			if (!empty($publishdate)) {
				$publish =& $_POST['publish'];
				if ($publish['meridiem'] == "PM" && $publish['hour'] < 12)
					$publish['hour'] += 12;
				$publish = mktime($publish['hour'],$publish['minute'],0,$publish['month'],$publish['date'],$publish['year']);
				$Product->status = 'future';
				unset($_POST['status']);
			} else {
				unset($_POST['publish']);
				// Auto set the publish date if not set (or more accurately, if set to an irrelevant timestamp)
				if ($Product->publish <= 86400) $Product->publish = time();
			}
		} else {
			unset($_POST['publish']);
			$Product->publish = 0;
		}

		// Set a unique product slug
		if (empty($Product->slug)) $Product->slug = sanitize_title_with_dashes($_POST['name']);
		$Product->slug = wp_unique_post_slug($Product->slug, $Product->id, $Product->status, $Product->posttype(), 0);

		if (isset($_POST['content'])) $_POST['description'] = $_POST['content'];
		$Product->updates($_POST,array('meta','categories','prices','tags'));

		do_action('shopp_pre_product_save');
		$Product->save();

		foreach ( Product::$_taxonomies as $taxonomy => $type) {
			$tags = '';
			$taxonomy_obj = get_taxonomy($taxonomy);

			if ( isset($_POST['tax_input']) && isset($_POST['tax_input'][$taxonomy]) ) {
				$tags = $_POST['tax_input'][$taxonomy];
				if ( is_array($tags) ) // array = hierarchical, string = non-hierarchical.
					$tags = array_filter($tags);
			}

			if ( current_user_can($taxonomy_obj->cap->assign_terms) )
				wp_set_post_terms( $Product->id, $tags, $taxonomy );
		}

		// Remove deleted images
		if (!empty($_POST['deleteImages'])) {
			$deletes = array();
			if (strpos($_POST['deleteImages'],",") !== false) $deletes = explode(',',$_POST['deleteImages']);
			else $deletes = array($_POST['deleteImages']);
			$Product->delete_images($deletes);
		}

		// Update image data
		if (!empty($_POST['images']) && is_array($_POST['images'])) {
			$Product->link_images($_POST['images']);
			$Product->save_imageorder($_POST['images']);
			if (!empty($_POST['imagedetails']))
				$Product->update_images($_POST['imagedetails']);
		}

		// Update Prices
		if (!empty($_POST['price']) && is_array($_POST['price'])) {

			// Delete prices that were marked for removal
			if (!empty($_POST['deletePrices'])) {
				$deletes = array();
				if (strpos($_POST['deletePrices'],","))	$deletes = explode(',',$_POST['deletePrices']);
				else $deletes = array($_POST['deletePrices']);

				foreach($deletes as $option) {
					$Price = new Price($option);
					$Price->delete();
				}
			}

			$Product->resum();

			// Save prices that there are updates for
			foreach($_POST['price'] as $i => $priceline) {
				if (empty($priceline['id'])) {
					$Price = new Price();
					$priceline['product'] = $Product->id;
				} else $Price = new Price($priceline['id']);

				$priceline['sortorder'] = array_search($i,$_POST['sortorder'])+1;

				// Remove VAT amount to save in DB
				if (str_true(shopp_setting('tax_inclusive')) && isset($priceline['tax']) && $priceline['tax'] == "on") {
					$priceline['price'] = (floatvalue($priceline['price'])/(1+$taxrate));
					$priceline['saleprice'] = (floatvalue($priceline['saleprice'])/(1+$taxrate));
				}
				$priceline['shipfee'] = floatvalue($priceline['shipfee']);
				if (isset($priceline['recurring']['trialprice']))
					$priceline['recurring']['trialprice'] = floatvalue($priceline['recurring']['trialprice']);

				if ($Price->stock != $priceline['stocked']) {
					$priceline['stock'] = $priceline['stocked'];
				} else unset($priceline['stocked']);

				$Price->updates($priceline);
				$Price->save();

				// Save 'price' meta records after saving the price record
				if (isset($pricelines['dimensions']) && is_array($pricelines['dimensions']))
					array_map('floatvalue',$priceline['dimensions']);

				$settings = array('donation','recurring','membership','dimensions');

				$priceline['settings'] = array();
				foreach ($settings as $setting) {
					if (! isset($priceline[$setting]) ) continue;
					$priceline['settings'][$setting] = $priceline[$setting];
				}

				if ( ! empty($priceline['settings']) ) shopp_set_meta ( $Price->id, 'price', 'settings', $priceline['settings'] );

				if ( ! empty($priceline['options']) ) shopp_set_meta ( $Price->id, 'price', 'options', $priceline['options'] );

				$Product->sumprice($Price);

				if (!empty($priceline['download'])) $Price->attach_download($priceline['download']);

				if (!empty($priceline['downloadpath'])) { // Attach file specified by URI/path
					if (!empty($Price->download->id) || (empty($Price->download) && $Price->load_download())) {
						$File = $Price->download;
					} else $File = new ProductDownload();

					$stored = false;
					$tmpfile = sanitize_path($priceline['downloadpath']);

					$File->storage = false;
					$Engine = $File->_engine(); // Set engine from storage settings

					$File->parent = $Price->id;
					$File->context = "price";
					$File->type = "download";
					$File->name = !empty($priceline['downloadfile'])?$priceline['downloadfile']:basename($tmpfile);
					$File->filename = $File->name;

					if ($File->found($tmpfile)) {
						$File->uri = $tmpfile;
						$stored = true;
					} else $stored = $File->store($tmpfile,'file');

					if ($stored) {
						$File->readmeta();
						$File->save();
					}

				} // END attach file by path/uri
			} // END foreach()
			unset($Price);
		} // END if (!empty($_POST['price']))

		$Product->sumup();

		if (!empty($_POST['meta']['options']))
			$_POST['meta']['options'] = stripslashes_deep($_POST['meta']['options']);
		else $_POST['meta']['options'] = false;

		// No variation options at all, delete all variation-pricelines
		if (!empty($Product->prices) && is_array($Product->prices)
				&& (empty($_POST['meta']['options']['v']) || empty($_POST['meta']['options']['a']))) {

			foreach ($Product->prices as $priceline) {
				// Skip if not tied to variation options
				if ($priceline->optionkey == 0) continue;
				if ((empty($_POST['meta']['options']['v']) && $priceline->context == "variation")
					|| (empty($_POST['meta']['options']['a']) && $priceline->context == "addon")) {
						$Price = new Price($priceline->id);
						$Price->delete();
				}
			}
		}

		// Handle product spec/detail data
		if (!empty($_POST['details']) || !empty($_POST['deletedSpecs'])) {

			// Delete specs queued for removal
			$ids = array();
			$deletes = array();
			if (!empty($_POST['deletedSpecs'])) {
				if (strpos($_POST['deleteImages'],",") !== false) $deletes = explode(',',$_POST['deleteImages']);
				else $deletes = array($_POST['deletedSpecs']);

				$ids = db::escape($_POST['deletedSpecs']);
				$Spec = new Spec();
				db::query("DELETE FROM $Spec->_table WHERE id IN ($ids)");
			}

			if (is_array($_POST['details'])) {
				foreach ($_POST['details'] as $i => $spec) {
					if (in_array($spec['id'],$deletes)) continue;
					if (isset($spec['new'])) {
						$Spec = new Spec();
						$spec['id'] = '';
						$spec['parent'] = $Product->id;
					} else $Spec = new Spec($spec['id']);
					$spec['sortorder'] = array_search($i,$_POST['details-sortorder'])+1;

					$Spec->updates($spec);
					$Spec->save();
				}
			}
		}

		// Save any meta data
		if (isset($_POST['meta']) && is_array($_POST['meta'])) {
			foreach ($_POST['meta'] as $name => $value) {
				$Meta = new MetaObject(array('parent'=>$Product->id,'context'=>'product','type'=>'meta','name'=>$name));
				$Meta->parent = $Product->id;
				$Meta->name = $name;
				$Meta->value = $value;
				$Meta->save();
			}
		}

		do_action_ref_array('shopp_product_saved',array(&$Product));

		unset($Product);
	}

	/**
	 * AJAX behavior to process uploaded files intended as digital downloads
	 *
	 * Handles processing a file upload from a temporary file to a
	 * the correct storage container (DB, file system, etc)
	 *
	 * @author Jonathan Davis
	 * @return string JSON encoded result with DB id, filename, type & size
	 **/
	function downloads () {
		$error = false;
		if (isset($_FILES['Filedata']['error'])) $error = $_FILES['Filedata']['error'];
		// @todo Replace $this->uploadErrors with an Lookup::errors of common PHP upload errors translated into more helpful messages
		if ($error) die(json_encode(array("error" => $this->uploadErrors[$error])));

		if (!is_uploaded_file($_FILES['Filedata']['tmp_name']))
			die(json_encode(array("error" => __('The file could not be saved because the upload was not found on the server.','Shopp'))));

		if (!is_readable($_FILES['Filedata']['tmp_name']))
			die(json_encode(array("error" => __('The file could not be saved because the web server does not have permission to read the upload.','Shopp'))));

		if ($_FILES['Filedata']['size'] == 0)
			die(json_encode(array("error" => __('The file could not be saved because the uploaded file is empty.','Shopp'))));

		// Save the uploaded file
		$File = new ProductDownload();
		$File->parent = 0;
		$File->context = "price";
		$File->type = "download";
		$File->name = $_FILES['Filedata']['name'];
		$File->filename = $File->name;
		$filetype = wp_check_filetype_and_ext($_FILES['Filedata']['tmp_name'],$File->name);
		$File->mime = $filetype['type'];
		if (!empty($filetype['proper_filename']))
			$File->name = $File->filename = $filetype['proper_filename'];
		$File->size = filesize($_FILES['Filedata']['tmp_name']);
		$File->store($_FILES['Filedata']['tmp_name'],'upload');
		$File->save();

		do_action('add_product_download',$File,$_FILES['Filedata']);

		echo json_encode(array("id"=>$File->id,"name"=>stripslashes($File->name),"type"=>$File->mime,"size"=>$File->size));
	}

	/**
	 * AJAX behavior to process uploaded images
	 *
	 * TODO: Find a better place for this code so products & categories can both use it
	 *
	 * @author Jonathan Davis
	 * @return string JSON encoded result with thumbnail id and src
	 **/
	function images () {
		$context = false;

		$error = false;
		if (isset($_FILES['Filedata']['error'])) $error = $_FILES['Filedata']['error'];
		if ($error) die(json_encode(array("error" => $this->uploadErrors[$error])));
		if (!class_exists('ImageProcessor'))
			require(SHOPP_MODEL_PATH."/Image.php");

		if (isset($_REQUEST['type'])) {
			$parent = $_REQUEST['parent'];
			switch (strtolower($_REQUEST['type'])) {
				case "product":
					$context = "product";
					break;
				case "category":
					$context = "category";
					break;
			}
		}

		if (!$context)
			die(json_encode(array("error" => __('The file could not be saved because the server cannot tell whether to attach the asset to a product or a category.','Shopp'))));

		if (!is_uploaded_file($_FILES['Filedata']['tmp_name']))
			die(json_encode(array("error" => __('The file could not be saved because the upload was not found on the server.','Shopp'))));

		if (!is_readable($_FILES['Filedata']['tmp_name']))
			die(json_encode(array("error" => __('The file could not be saved because the web server does not have permission to read the upload from the server\'s temporary directory.','Shopp'))));

		if ($_FILES['Filedata']['size'] == 0)
			die(json_encode(array("error" => __('The file could not be saved because the uploaded file is empty.','Shopp'))));

		// Save the source image
		if ($context == "category") $Image = new CategoryImage();
		else $Image = new ProductImage();

		$Image->parent = $parent;
		$Image->type = "image";
		$Image->name = "original";
		$Image->filename = $_FILES['Filedata']['name'];
		list($Image->width, $Image->height, $Image->mime, $Image->attr) = getimagesize($_FILES['Filedata']['tmp_name']);
		$Image->mime = image_type_to_mime_type($Image->mime);
		$Image->size = filesize($_FILES['Filedata']['tmp_name']);

		if ( ! $Image->unique() ) die(json_encode(array("error" => __('The image already exists, but a new filename could not be generated.','Shopp'))));

		$Image->store($_FILES['Filedata']['tmp_name'],'upload');
		$Image->save();

		if (empty($Image->id))
			die(json_encode(array("error" => __('The image reference was not saved to the database.','Shopp'))));

		echo json_encode(array("id"=>$Image->id));
	}

	/**
	 * Loads all categories for the product list manager category filter menu
	 *
	 * @author Jonathan Davis
	 * @return string HTML for a drop-down menu of categories
	 **/
	function category ($id) {
		global $wpdb;
		$p = "$wpdb->posts AS p";
		$joins[$wpdb->term_relationships] = "INNER JOIN $wpdb->term_relationships AS tr ON (p.ID=tr.object_id)";
		$joins[$wpdb->term_taxonomy] = "INNER JOIN $wpdb->term_taxonomy AS tt ON (tr.term_taxonomy_id=tt.term_taxonomy_id AND tt.term_id=$id)";

		if ('catalog-products' == $id)
			$products = DB::query("SELECT p.id,p.post_title AS name FROM $p ORDER BY name ASC",'array','col','name','id');
		else $products = DB::query("SELECT p.id,p.post_title AS name FROM $p ".join(' ',$joins)." ORDER BY name ASC",'array','col','name','id');

		return menuoptions($products,0,true);
	}

	function index ($Product) {
		$Indexer = new IndexProduct($Product->id);
		$Indexer->index();
	}

} // END Warehouse class

?>