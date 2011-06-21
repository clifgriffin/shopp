<?php
/**
 * Warehouse
 *
 * Flow controller for product management interfaces
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, January 6, 2010
 * @package shopp
 * @subpackage products
 **/

class Warehouse extends AdminController {

	/**
	 * Store constructor
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function __construct () {
		parent::__construct();
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
		} elseif (!empty($_GET['f']) && $_GET['f'] == 'i') {
			do_action('shopp_inventory_manager_scripts');
			add_action('admin_print_scripts',array(&$this,'inventory_cols'));
		} else add_action('admin_print_scripts',array(&$this,'columns'));

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
	 * @return void
	 * @author Jonathan Davis
	 **/
	function admin () {
		if (!empty($_GET['id'])) $this->editor();
		else $this->manager();
	}

	/**
	 * Handles loading, saving and deleting products in the context of workflows
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function workflow () {
		global $Shopp;

		$defaults = array(
			'page' => false,
			'deleting' => false,
			'delete' => false,
			'id' => false,
			'save' => false,
			'duplicate' => false,
			'next' => false
			);
		$args = array_merge($defaults,$_REQUEST);
		extract($args,EXTR_SKIP);

		if (!defined('WP_ADMIN') || !isset($page)
			|| $page != $this->Admin->pagename('products'))
				return false;

		$adminurl = admin_url('admin.php');


		if ($page == $this->Admin->pagename('products')
				&& !empty($deleting)
				&& !empty($delete)
				&& is_array($delete)) {
			foreach($delete as $deletion) {
				$Product = new Product($deletion);
				$Product->delete();
			}
			$redirect = esc_url(add_query_arg(array_merge($_GET,array('delete'=>null,'deleting'=>null)),$adminurl));
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
		} else {
			$Shopp->Product = new Product();
			$Shopp->Product->status = "publish";
		}

		if ($save) {
			$this->save($Shopp->Product);
			$this->Notice = '<strong>'.stripslashes($Shopp->Product->name).'</strong> '.__('has been saved.','Shopp');

			if ($next) {
				if ($next == "new") {
					$Shopp->Product = new Product();
					$Shopp->Product->status = "publish";
				} else {
					$Shopp->Product = new Product($next);
					$Shopp->Product->load_data();
				}
			} else {
				if (empty($id)) $id = $Shopp->Product->id;
				$Shopp->Product = new Product($id);
				$Shopp->Product->load_data();
			}
		}

	}

	/**
	 * Interface processor for the product list manager
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function manager ($workflow=false) {
		global $Shopp,$Products;
		$db = DB::get();
		$Settings = &ShoppSettings();

		if ( ! current_user_can('shopp_products') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$defaults = array(
			'cat' => false,
			'pagenum' => 1,
			'per_page' => 20,
			's' => '',
			'sl' => '',
			'matchcol' => '',
			'f' => ''
			);

		$args = array_merge($defaults,$_GET);
		extract($args,EXTR_SKIP);

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

			$inventory_filters = array(
				'all' => __('View all products','Shopp'),
				'is' => __('In stock','Shopp'),
				'ls' => __('Low stock','Shopp'),
				'oos' => __('Out-of-stock','Shopp'),
				'ns' => __('Not stocked','Shopp')
			);
			$inventory_menu = menuoptions($inventory_filters,$sl,true);
		}

		$subfilters = array('f' => 'featured','p' => 'published','s' => 'onsale','i' => 'inventory');
		$subs = array(
			'all' => array('label' => __('All','Shopp'),'columns' => "count(*) AS total",'where'=>"p.post_type='shopp_product'"),
			'published' => array('label' => __('Published','Shopp'),'total' => 0,'columns' => "count(*) AS total",'where'=>"p.post_status='publish'",'request' => 'p'),
			'onsale' => array('label' => __('On Sale','Shopp'),'total' => 0,'columns' => "count(*) AS total",'where'=>"s.sale='on'",'request' => 's'),
			'featured' => array('label' => __('Featured','Shopp'),'total' => 0,'columns' => "count(*) AS total",'where'=>"s.featured='on'",'request' => 'f'),
			'inventory' => array('label' => __('Inventory','Shopp'),'total' => 0,'columns' => "count(*) AS total",'where'=>"pt.inventory='on' AND pt.type!='N/A'",'grouping'=>'pt.id','request' => 'i')
		);

		if ('i' == $f) $per_page = 50;

		$pagenum = absint( $pagenum );
		if ( empty($pagenum) )
			$pagenum = 1;
		if( !$per_page || $per_page < 0 )
			$per_page = 20;
		$start = ($per_page * ($pagenum-1));

		$pd = WPShoppObject::tablename(Product::$table);
		$pt = DatabaseObject::tablename(Price::$table);
		$ps = DatabaseObject::tablename(ProductSummary::$table);
		// $catt = DatabaseObject::tablename(ProductCategory::$table);
		$clog = DatabaseObject::tablename(Catalog::$table);

		$orderby = "pd.created DESC";

		$having = "";
		$joins = array();
		$where = array();
		if (!empty($s)) {
			$SearchResults = new SearchResults(array('search'=>$s,'debug'=>true,'load'=>array()));
			$SearchResults->load();
			$ids = array_keys($SearchResults->index);
			$where[] = "p.ID IN (".join(',',$ids).")";
		}
		// if (!empty($cat)) $where .= " AND cat.id='$cat' AND (clog.category != 0 OR clog.id IS NULL)";
		if (!empty($cat)) {
			// if ($cat == "-") {
			// 	$having = "HAVING COUNT(cat.id) = 0";
			// } else {
				// $matchcol .= ", GROUP_CONCAT(DISTINCT cat.id ORDER BY cat.id SEPARATOR ',') AS catids";
				// $where[] = " AND cat.id IN (SELECT parent FROM $clog WHERE parent=$cat AND taxonomy='$ct_id')";

				global $wpdb;

				$joins[$wpdb->term_relationships] = "INNER JOIN $wpdb->term_relationships AS tr ON (p.ID=tr.object_id)";
				$joins[$wpdb->term_taxonomy] = "INNER JOIN $wpdb->term_taxonomy AS tt ON (tr.term_taxonomy_id=tt.term_taxonomy_id AND tt.term_id=$cat)";


			// }
		}
		if (!empty($sl)) {
			switch($sl) {
				case "ns": $where[] = "s.inventory='off'"; break;
				case "oos":
					$where[] = "(s.inventory='on' AND s.stock = 0)";
					break;
				case "ls":
					$ls = $Settings->get('lowstock_level');
					if (empty($ls)) $ls = '0';
					$where[] = "(s.inventory='on' AND s.stock <= $ls AND s.stock > 0)";
					break;
				case "is": $where[] = "(s.inventory='on' AND s.stock > 0)";
			}
		}

		if (!empty($f))	$where[] = $subs[$subfilters[$f]]['where'];

		$base = $Settings->get('base_operations');
		if ($base['vat']) $taxrate = shopp_taxrate();
		if (empty($taxrate)) $taxrate = 0;

		if ('i' == $f) { // Inventory products
			$loading = array(
				'columns' => "CONCAT(p.post_title,': ',pt.label) AS post_title,pt.sku AS sku",
				'joins' => array($pt => "JOIN $pt AS pt ON p.ID=pt.product"),
				'where' => $where,
				'groupby' => 'pt.id',
				'orderby' => 'p.ID,pt.sortorder',
				'limit'=>"$start,$per_page"
			);
		} else {
			$loading = array(
				'where' => $where,
				'joins' => $joins,
				'limit'=>"$start,$per_page",
				'load' => array('categories'),
				'published' => false
			);
		}

		$Products = new ProductCollection();
		$Products->load($loading);

		if ($workflow) return $Products->workflow();

		// @todo Add wp_cache support
		foreach ($subs as $name => &$subquery) {
			if ('all' == $name)
				$subquery['total'] = DB::query("SELECT count(*) AS total FROM $pd AS p WHERE p.post_type='shopp_product'",'auto','col','total');

			if ('published' == $name)
				$subquery['total'] = DB::query("SELECT count(*) AS total FROM $pd AS p WHERE p.post_type='shopp_product' AND p.post_status='publish'",'auto','col','total');

			if ('onsale' == $name) {
				$subquery['total'] = DB::query("SELECT count(*) AS total FROM $pd AS p INNER JOIN $ps AS s ON p.ID=s.product AND s.sale='on' WHERE p.post_type='shopp_product'",'auto','col','total');
			}

			if ('featured' == $name) {
				$subquery['total'] = DB::query("SELECT count(*) AS total FROM $pd AS p INNER JOIN $ps AS s ON p.ID=s.product AND s.featured='on' WHERE p.post_type='shopp_product'",'auto','col','total');
			}

			if ('inventory' == $name) {
				$subquery['total'] = DB::query("SELECT count(*) AS total FROM $pd AS p INNER JOIN $ps AS s ON p.ID=s.product AND s.inventory='on' WHERE p.post_type='shopp_product'",'auto','col','total');
			}

		}

		$num_pages = ceil($Products->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg(array("edit"=>null,'pagenum' => '%#%')),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum,
		));

		if ('i' == $f) {
			include(SHOPP_ADMIN_PATH."/products/inventory.php");
			return;
		}

		include(SHOPP_ADMIN_PATH."/products/products.php");
	}

	/**
	 * Registers the column headers for the product list manager
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function columns () {
		register_column_headers('shopp_page_shopp-products', array(
			'cb'=>'<input type="checkbox" />',
			'name'=>__('Name','Shopp'),
			'category'=>__('Category','Shopp'),
			'price'=>__('Price','Shopp'),
			'inventory'=>__('Inventory','Shopp'),
			'featured'=>__('Featured','Shopp'))
		);
	}

	function inventory_cols () {
		register_column_headers('shopp_page_shopp-products', array(
			'inventory'=>__('Inventory','Shopp'),
			'sku'=>__('SKU','Shopp'),
			'name'=>__('Name','Shopp'))
		);
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

		$db = DB::get();

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

		$shiprates = $this->Settings->get('shipping_rates');
		if (!empty($shiprates)) ksort($shiprates);

		$uploader = $Shopp->Settings->get('uploader_pref');
		if (!$uploader) $uploader = 'flash';

		$process = (!empty($Product->id)?$Product->id:'new');
		$_POST['action'] = add_query_arg(array_merge($_GET,array('page'=>$this->Admin->pagename('products'))),admin_url('admin.php'));

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
		$Settings = &ShoppSettings();
		check_admin_referer('shopp-save-product');

		if ( ! current_user_can('shopp_products') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$Settings->saveform(); // Save workflow setting

		// Get needed settings
		$base = $Settings->get('base_operations');
		$taxrate = 0;
		if ($base['vat']) $taxrate = shopp_taxrate(null,true,$Product);

		// Set publish date
		if ('publish' == $_POST['status']) {
			$publishfields = array('month' => '','date' => '','year' => '','hour'=>'','minute'=>'','meridiem'=>'');
			$publishdate = join('',array_merge($publishfields,$_POST['publish']));
			if (!empty($publishdate)) {
				$publish = $_POST['publish'];
				if ($publish['meridiem'] == "PM" && $publish['hour'] < 12)
					$publish['hour'] += 12;
				$publish = mktime($publish['hour'],$publish['minute'],0,$publish['month'],$publish['date'],$publish['year']);
				$Product->status = 'future';
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
		$Product->slug = wp_unique_post_slug($Product->slug, $Product->id, $Product->status, $Product->_post_type, 0);

		if (isset($_POST['content'])) $_POST['description'] = $_POST['content'];
		$Product->updates($_POST,array('meta','categories','prices','tags'));

		do_action('shopp_pre_product_save');
		$Product->save();

		// Save taxonomies
		if ( !empty($_POST['tax_input']) ) {
			foreach ( (array) $_POST['tax_input'] as $taxonomy => $tags ) {
				$taxonomy_obj = get_taxonomy($taxonomy);
				if ( is_array($tags) ) // array = hierarchical, string = non-hierarchical.
					$tags = array_filter($tags);
				if ( current_user_can($taxonomy_obj->cap->assign_terms) )
					wp_set_post_terms( $Product->id, $tags, $taxonomy );
			}
		}

		// Remove deleted issues
		if (!empty($_POST['deleteImages'])) {
			$deletes = array();
			if (strpos($_POST['deleteImages'],",") !== false) $deletes = explode(',',$_POST['deleteImages']);
			else $deletes = array($_POST['deleteImages']);
			$Product->delete_images($deletes);
		}

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

			$Product->maxprice = false;
			$Product->minprice = false;
			$Product->stock = false;
			$Product->sold = 0;

			// Save prices that there are updates for
			foreach($_POST['price'] as $i => $priceline) {
				if (empty($priceline['id'])) {
					$Price = new Price();
					$priceline['product'] = $Product->id;
				} else $Price = new Price($priceline['id']);

				$priceline['sortorder'] = array_search($i,$_POST['sortorder'])+1;

				// Remove VAT amount to save in DB
				if ($base['vat'] && isset($priceline['tax']) && $priceline['tax'] == "on") {
					$priceline['price'] = (floatvalue($priceline['price'])/(1+$taxrate));
					$priceline['saleprice'] = (floatvalue($priceline['saleprice'])/(1+$taxrate));
				}
				$priceline['shipfee'] = floatvalue($priceline['shipfee']);
				if (isset($priceline['recurring']['trialprice']))
					$priceline['recurring']['trialprice'] = floatvalue($priceline['recurring']['trialprice']);

				$priceline['weight'] = floatvalue($priceline['weight']);
				if (isset($pricelines['dimensions']) && is_array($pricelines['dimensions']))
					foreach ($priceline['dimensions'] as &$dimension)
						$dimension = floatvalue($dimension);

				$priceline['settings'] = array();
				$settings = array('donation','recurring','membership');

				foreach ($settings as $setting)
					if (isset($priceline[$setting])) $priceline['settings'][$setting] = $priceline[$setting];

				$Price->updates($priceline);
				$Price->save();

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
			if (!empty($_POST['deletedSpecs'])) {
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
		$File->mime = file_mimetype($_FILES['Filedata']['tmp_name'],$File->name);
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
		if ($context == "category") $Image = new ProductCategoryImage();
		else $Image = new ProductImage();

		$Image->parent = $parent;
		$Image->type = "image";
		$Image->name = "original";
		$Image->filename = $_FILES['Filedata']['name'];
		list($Image->width, $Image->height, $Image->mime, $Image->attr) = getimagesize($_FILES['Filedata']['tmp_name']);
		$Image->mime = image_type_to_mime_type($Image->mime);
		$Image->size = filesize($_FILES['Filedata']['tmp_name']);

		$Existing = new ImageAsset();
		$Existing->uri = $Image->filename;
		$limit = 100;
		while ($Existing->found()) { // Rename the filename of the image if it already exists
			list($name,$ext) = explode(".",$Existing->uri);
			$_ = explode("-",$name);
			$last = count($_)-1;
			$suffix = $last > 0?intval($_[$last])+1:1;
			if ($suffix == 1) $_[] = $suffix;
			else $_[$last] = $suffix;
			$Existing->uri = join("-",$_).'.'.$ext;
			if (!$limit--)
				die(json_encode(array("error" => __('The image already exists, but a new filename could not be generated.','Shopp'))));
		}
		if ($Existing->uri !== $Image->filename)
			$Image->filename = $Existing->uri;

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
		$db = DB::get();

		$catalog = DatabaseObject::tablename(Catalog::$table);
		$category = DatabaseObject::tablename(ProductCategory::$table);
		$products = DatabaseObject::tablename(Product::$table);

		if ($id == "catalog-products") {
			$results = $db->query("SELECT p.id,p.name FROM $products AS p ORDER BY p.name ASC",AS_ARRAY);
		} else $results = $db->query("SELECT p.id,p.name FROM $catalog AS catalog LEFT JOIN $category AS cat ON cat.id = catalog.parent AND catalog.taxonomy='$ct_id' LEFT JOIN $products AS p ON p.id=catalog.product WHERE cat.id='$id' ORDER BY p.name ASC",AS_ARRAY);
		$products = array();

		$products[0] = __("Select a product&hellip;","Shopp");
		foreach ($results as $result) $products[$result->id] = $result->name;
		return menuoptions($products,0,true);

	}

	function index ($Product) {
		$Indexer = new IndexProduct($Product->id);
		$Indexer->index();
	}

} // END Warehouse class

?>