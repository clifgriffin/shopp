<?php
/**
 * Ajax.php
 * 
 * Description…
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, February  6, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @subpackage ajax
 **/

/**
 * AjaxFlow
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 **/
class AjaxFlow {
	
	/**
	 * Ajax constructor
	 *
	 * @author Jonathan Davis
	 * 
	 * @return void
	 **/
	function __construct () {
		add_action('wp_ajax_nopriv_shopp_shipping_costs',array(&$this,'shipping_costs'));
		add_action('wp_ajax_nopriv_shopp_category_menu',array(&$this,'category_menu'));
		add_action('wp_ajax_nopriv_shopp_category_products',array(&$this,'category_products'));
		add_action('wp_ajax_nopriv_shopp_ssl_available',array(&$this,'ssl_available'));
		
		// Flash uploads require unprivileged access
		add_action('wp_ajax_nopriv_shopp_upload_image',array(&$this,'upload_image'));
		add_action('wp_ajax_nopriv_shopp_upload_file',array(&$this,'upload_file'));

		if (!defined('WP_ADMIN') || !is_user_logged_in() || !current_user_can('manage_options')) 
			return;
			
		add_action('wp_ajax_shopp_category_products',array(&$this,'category_products'));
		add_action('wp_ajax_shopp_upload_image',array(&$this,'upload_image'));
		add_action('wp_ajax_shopp_order_receipt',array(&$this,'receipt'));
		add_action('wp_ajax_shopp_category_menu',array(&$this,'category_menu'));
		add_action('wp_ajax_shopp_country_zones',array(&$this,'country_zones'));
		add_action('wp_ajax_shopp_spec_template',array(&$this,'load_spec_template'));
		add_action('wp_ajax_shopp_options_template',array(&$this,'load_options_template'));
		add_action('wp_ajax_shopp_add_category',array(&$this,'add_category'));
		add_action('wp_ajax_shopp_edit_slug',array(&$this,'edit_slug'));
		add_action('wp_ajax_shopp_verify_file',array(&$this,'verify_file'));
		add_action('wp_ajax_shopp_version_check',array(&$this,'version_check'));
		add_action('wp_ajax_shopp_verify_update',array(&$this,'verify_update'));
		add_action('wp_ajax_shopp_update',array(&$this,'update'));
		add_action('wp_ajax_shopp_setup_ftp',array(&$this,'setup_ftp'));
		add_action('wp_ajax_shopp_ssl_available',array(&$this,'ssl_available'));
		add_action('wp_ajax_shopp_order_note_message',array(&$this,'order_note_message'));
		add_action('wp_ajax_shopp_activate_key',array(&$this,'activate_key'));
		add_action('wp_ajax_shopp_deactivate_key',array(&$this,'deactivate_key'));
		add_action('wp_ajax_shopp_rebuild_search_index',array(&$this,'rebuild_search_index'));
		add_action('wp_ajax_shopp_rebuild_search_index_progress',array(&$this,'rebuild_search_index_progress'));
		
	}

	function receipt () {
		global $Shopp;
		if (preg_match("/\d+/",$_GET['id'])) {
			$Shopp->Purchase = new Purchase($_GET['id']);
			$Shopp->Purchase->load_purchased();
		} else die('-1');
		echo "<html><head>";
			echo '<style type="text/css">body { padding: 20px; font-family: Arial,Helvetica,sans-serif; }</style>';
			echo "<link rel='stylesheet' href='".SHOPP_TEMPLATES_URI."/shopp.css' type='text/css' />";
		echo "</head><body>";
		echo $Shopp->Purchase->receipt();
		if (isset($_GET['print']) && $_GET['print'] == 'auto')
			echo '<script type="text/javascript">window.onload = function () { window.print(); window.close(); }</script>';
		echo "</body></html>";
		exit();
	}
	
	function category_menu () {
		require_once(SHOPP_FLOW_PATH."/Categorize.php");
		$Categorize = new Categorize();
		echo $Categorize->menu();
		exit();
	}

	function category_products () {
		if (!isset($_GET['category'])) return;
		$category = $_GET['category'];
		require_once(SHOPP_FLOW_PATH."/Warehouse.php");
		$Warehouse = new Warehouse();
		echo $Warehouse->category($category);
		exit();
	}
	
	function country_zones () {
		$zones = Lookup::country_zones();
		if (isset($_GET['country']) && isset($zones[$_GET['country']]))
			echo json_encode($zones[$_GET['country']]);
		else echo json_encode(false);
		exit();
	}
	
	function load_spec_template () {
		$db = DB::get();
		$table = DatabaseObject::tablename(Category::$table);
		$result = $db->query("SELECT specs FROM $table WHERE id='{$_GET['category']}' AND spectemplate='on'");
		echo json_encode(unserialize($result->specs));
		exit();
	}
	
	function load_options_template() {
		$db = DB::get();
		$table = DatabaseObject::tablename(Category::$table);			
		$result = $db->query("SELECT options,prices FROM $table WHERE id='{$_GET['category']}' AND variations='on'");
		if (empty($result)) exit();
		$result->options = unserialize($result->options);
		$result->prices = unserialize($result->prices);
		foreach ($result->options as &$menu) {
			foreach ($menu['options'] as &$option) $option['id'] += $_GET['cat'];
		}
		foreach ($result->prices as &$price) {
			$optionids = explode(",",$price['options']);
			foreach ($optionids as &$id) $id += $_GET['cat'];
			$price['options'] = join(",",$optionids);
			$price['optionkey'] = "";
		}
		
		echo json_encode($result);
		exit();
	}
	
	function upload_image () {
		require_once(SHOPP_FLOW_PATH."/Warehouse.php");
		$Warehouse = new Warehouse();
		echo $Warehouse->images();
		exit();
	}

	function upload_file () {
		require_once(SHOPP_FLOW_PATH."/Warehouse.php");
		$Warehouse = new Warehouse();
		echo $Warehouse->downloads();
		exit();
	}

	function add_category () {
		// Add a category in the product editor
		check_admin_referer('shopp-ajax_add_category');
		if (empty($_GET['name'])) die(0);
	
		$Catalog = new Catalog();
		$Catalog->load_categories();
	
		$Category = new Category();
		$Category->name = $_GET['name'];
		$Category->slug = sanitize_title_with_dashes($Category->name);
		$Category->parent = $_GET['parent'];
							
		// Work out pathing
		$paths = array();
		if (!empty($Category->slug)) $paths = array($Category->slug);  // Include self
	
		$parentkey = -1;
		// If we're saving a new category, lookup the parent
		if ($Category->parent > 0) {
			array_unshift($paths,$Catalog->categories[$Category->parent]->slug);
			$parentkey = $Catalog->categories[$Category->parent]->parent;
		}
	
		while ($category_tree = $Catalog->categories[$parentkey]) {
			array_unshift($paths,$category_tree->slug);
			$parentkey = $category_tree->parent;
		}
	
		if (count($paths) > 1) $Category->uri = join("/",$paths);
		else $Category->uri = $paths[0];
		
		$Category->save();
		echo json_encode($Category);
		exit();
		
	}
	
	function edit_slug () {
		check_admin_referer('shopp-ajax_edit_slug');
						
		switch ($_REQUEST['type']) {
			case "category":
				$Category = new Category($_REQUEST['id']);
				if (empty($_REQUEST['slug'])) $_REQUEST['slug'] = $Category->name;
				$Category->slug = sanitize_title_with_dashes($_REQUEST['slug']);
				if ($Category->save()) echo apply_filters('editable_slug',$Category->slug);
				else echo '-1';
				break;
			case "product":
				$Product = new Product($_REQUEST['id']);
				if (empty($_REQUEST['slug'])) $_REQUEST['slug'] = $Product->name;
				$Product->slug = sanitize_title_with_dashes($_REQUEST['slug']);
				if ($Product->save()) echo apply_filters('editable_slug',$Product->slug);
				else echo '-1';
				break;
		}
		exit();
	}
	
	function verify_file () {
		check_admin_referer('shopp-ajax_verify_file');
		$Settings = &ShoppSettings();
		chdir(WP_CONTENT_DIR); // relative path context for realpath
		$url = $_POST['url'];
		$request = parse_url($url);
		if ($request['scheme'] == "http") {
			$results = @get_headers($url);
			if (substr($url,-1) == "/") die("ISDIR");
			if (strpos($results[0],'200') === false) die("NULL");
		} else {
			$url = str_replace('file://','',$url);	

			if ($url{0} != "/") $url = trailingslashit(sanitize_path(realpath($Settings->get('products_path')))).$url;

			if (!file_exists($url)) die("NULL");
			if (is_dir($url)) die("ISDIR");
			if (!is_readable($url)) die("READ");
		}

		die("OK");
	}
	
	function version_check () {
		check_admin_referer('shopp-wp_ajax_shopp_update');
		global $Shopp;

		require_once(SHOPP_FLOW_PATH."/Install.php");
		$Installer = new ShoppInstallation();
		$addons = array_merge($Shopp->Gateways->checksums(),$Shopp->Shipping->checksums());

		$request = array(
			"ShoppServerRequest" => "version-check",
			"ver" => '1.0'
		);
		$data = array(
			'core' => SHOPP_VERSION,
			'addons' => join("-",$addons)
		);
		echo $Installer->callhome($request,$data);
		exit();
		
	}
	
	function verify_update () {
		if ($this->Settings->get('maintenance') == "on") die('1');
	}
	
	function update () {
		check_admin_referer('shopp-wp_ajax_shopp_update');
		require_once(SHOPP_FLOW_PATH."/Install.php");
		$Installer = new ShoppInstallation();
		$Installer->update();
		exit();
	}
	
	function setup_ftp () {
		check_admin_referer('shopp-wp_ajax_shopp_update');
		$Settings = &ShoppSettings();
		$Settings->saveform();
		$updates = $Settings->get('ftp_credentials');
		exit();
	}
	
	function shipping_costs () {
		// $this->ShipCalcs = new ShipCalcs($this->path);
		// if (isset($_GET['method'])) {
		// 	$this->Cart->data->Order->Shipping->method = $_GET['method'];
		// 	$this->Cart->retotal = true;
		// 	$this->Cart->updated();
		// 	$this->Cart->totals();
		// 	echo json_encode($this->Cart->data->Totals);
		// }
		exit();
	}
	
	function ssl_available () {
		global $Shopp;
		if ($Shopp->secure) die('1');
		die('0');
	}
	
	function order_note_message () {
		// check_admin_referer('shopp-ajax_edit_order_note');
		if (!isset($_GET['id'])) die('1');
		
		$Note = new MetaObject($_GET['id']);
		die($Note->value->message);
	}
	
	function activate_key () {
		global $Shopp;
		$updatekey = $Shopp->Settings->get('updatekey');
		$request = array(
			"ShoppServerRequest" => "activate-key",
			"ver" => '1.1',
			'key' => $_GET['key'],
			'site' => get_bloginfo('siteurl')
		);
		$response = $Shopp->callhome($request);
		$result = json_decode($response);
		if ($result[0] == "1")
			$Shopp->Settings->save('updatekey',$result);
		echo $response;
		exit();
	}
	
	function deactivate_key () {
		global $Shopp;
		$updatekey = $Shopp->Settings->get('updatekey');
		$request = array(
			"ShoppServerRequest" => "deactivate-key",
			"ver" => '1.1',
			'key' => $updatekey[1],
			'site' => get_bloginfo('siteurl')
		);
		$response = $Shopp->callhome($request);
		$result = json_decode($response);
		if ($result[0] == "0" && $updatekey[2] == "dev") $Shopp->Settings->save('updatekey',array("0"));
		else $Shopp->Settings->save('updatekey',$result);
		echo $response;
		exit();
	}
	
	function rebuild_search_index () {
		$db = DB::get();
		require(SHOPP_MODEL_PATH."/Search.php");
		new ContentParser();

		$set = 10;
		$product_table = DatabaseObject::tablename(Product::$table);
		$index_table = DatabaseObject::tablename(ContentIndex::$table);
		
		$total = $db->query("SELECT count(id) AS products,now() as start FROM $product_table");
		if (empty($total->products)) die('-1');

		$Settings = &ShoppSettings();
		$Settings->save('searchindex_build',mktimestamp($total->start));
		
		$indexed = 0;
		for ($i = 0; $i*$set < $total->products; $i++) {
			$row = $db->query("SELECT id FROM $product_table LIMIT ".($i*$set).",$set",AS_ARRAY);
			foreach ($row as $index => $product) {
				$Indexer = new IndexProduct($product->id);
				$Indexer->index();
				$indexed++;
			}
		}
		echo "1";
		exit();
	}
	
	function rebuild_search_index_progress () {
		$db = DB::get();
		require(SHOPP_MODEL_PATH."/Search.php");
		$product_table = DatabaseObject::tablename(Product::$table);
		$index_table = DatabaseObject::tablename(ContentIndex::$table);
		
		$Settings = &ShoppSettings();
		$lastbuild = $Settings->get('searchindex_build');
		
		$status = $db->query("SELECT count(DISTINCT product.id) AS products, count(DISTINCT product) AS indexed FROM $product_table AS product LEFT JOIN $index_table AS indx ON product.id=indx.product AND $lastbuild < UNIX_TIMESTAMP(indx.modified)");
		
		if (empty($status)) die('');
		die($status->indexed.':'.$status->products);
	}

} // END class AjaxFlow

?>