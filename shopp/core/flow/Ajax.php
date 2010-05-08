<?php
/**
 * Ajax.php
 * 
 * Handles AJAX calls from Shopp interfaces
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
		// Flash uploads require unprivileged access
		add_action('wp_ajax_nopriv_shopp_upload_image',array(&$this,'upload_image'));
		add_action('wp_ajax_nopriv_shopp_upload_file',array(&$this,'upload_file'));

		// These must have nonce protection
		if (!isset($_REQUEST['_wpnonce'])) return;

		add_action('wp_ajax_shopp_shipping_costs',array(&$this,'shipping_costs')); // @todo not implemented
		add_action('wp_ajax_shopp_category_menu',array(&$this,'category_menu'));
		add_action('wp_ajax_shopp_category_products',array(&$this,'category_products'));			
		add_action('wp_ajax_shopp_order_receipt',array(&$this,'receipt'));
		add_action('wp_ajax_shopp_category_menu',array(&$this,'category_menu'));
		add_action('wp_ajax_shopp_country_zones',array(&$this,'country_zones'));
		add_action('wp_ajax_shopp_spec_template',array(&$this,'load_spec_template'));
		add_action('wp_ajax_shopp_options_template',array(&$this,'load_options_template'));
		add_action('wp_ajax_shopp_add_category',array(&$this,'add_category'));
		add_action('wp_ajax_shopp_edit_slug',array(&$this,'edit_slug'));
		add_action('wp_ajax_shopp_verify_file',array(&$this,'verify_file'));
		add_action('wp_ajax_shopp_order_note_message',array(&$this,'order_note_message'));
		add_action('wp_ajax_shopp_activate_key',array(&$this,'activate_key'));
		add_action('wp_ajax_shopp_deactivate_key',array(&$this,'deactivate_key'));
		add_action('wp_ajax_shopp_rebuild_search_index',array(&$this,'rebuild_search_index'));
		add_action('wp_ajax_shopp_rebuild_search_index_progress',array(&$this,'rebuild_search_index_progress'));
		add_action('wp_ajax_shopp_suggestions',array(&$this,'suggestions'));
		add_action('wp_ajax_shopp_upload_local_taxes',array(&$this,'upload_local_taxes'));
		add_action('wp_ajax_shopp_feature_product',array(&$this,'feature_product'));
		
	}

	function receipt () {
		check_admin_referer('wp_ajax_shopp_order_receipt');
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
		check_admin_referer('wp_ajax_shopp_category_menu');
		require_once(SHOPP_FLOW_PATH."/Categorize.php");
		$Categorize = new Categorize();
		echo $Categorize->menu();
		exit();
	}

	function category_products () {
		check_admin_referer('wp_ajax_shopp_category_products');
		if (!isset($_GET['category'])) return;
		$category = $_GET['category'];
		require_once(SHOPP_FLOW_PATH."/Warehouse.php");
		$Warehouse = new Warehouse();
		echo $Warehouse->category($category);
		exit();
	}
	
	function country_zones () {
		check_admin_referer('wp_ajax_shopp_country_zones');
		$zones = Lookup::country_zones();
		if (isset($_GET['country']) && isset($zones[$_GET['country']]))
			echo json_encode($zones[$_GET['country']]);
		else echo json_encode(false);
		exit();
	}
	
	function load_spec_template () {
		check_admin_referer('wp_ajax_shopp_spec_template');
		$db = DB::get();
		$table = DatabaseObject::tablename(Category::$table);
		$result = $db->query("SELECT specs FROM $table WHERE id='{$_GET['category']}' AND spectemplate='on'");
		echo json_encode(unserialize($result->specs));
		exit();
	}
	
	function load_options_template() {
		check_admin_referer('wp_ajax_shopp_options_template');
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
		//check_admin_referer('wp_ajax_shopp_upload_image');
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
		check_admin_referer('wp_ajax_shopp_add_category');
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
		check_admin_referer('wp_ajax_shopp_edit_slug');
						
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
	
	function order_note_message () {
		check_admin_referer('wp_ajax_shopp_order_note_message');
		if (!isset($_GET['id'])) die('1');
		
		$Note = new MetaObject($_GET['id']);
		die($Note->value->message);
	}
	
	function activate_key () {
		check_admin_referer('wp_ajax_shopp_activate_key');
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
		check_admin_referer('wp_ajax_shopp_deactivate_key');
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
		check_admin_referer('wp_ajax_shopp_rebuild_search_index');		
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
		check_admin_referer('wp_ajax_shopp_rebuild_search_index_progress');
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
	
	function suggestions () {
		check_admin_referer('wp_ajax_shopp_suggestions');
		$db = DB::get();
		switch($_GET['t']) {
			case "product-name": $table = DatabaseObject::tablename(Product::$table); break;
			case "product-tags": $table = DatabaseObject::tablename(Tag::$table); break;
			case "product-category": $table = DatabaseObject::tablename(Category::$table); break;
		}
		
		$entries = $db->query("SELECT name FROM $table WHERE name LIKE '%{$_GET['q']}%'",AS_ARRAY);
		$results = array();
		foreach ($entries as $entry) $results[] = $entry->name;
		echo join("\n",$results);
		exit();
	}
	
	function upload_local_taxes () {
		check_admin_referer('wp_ajax_shopp_upload_local_taxes');
		if (isset($_FILES['shopp']['error'])) $error = $_FILES['shopp']['error'];
		if ($error) die(json_encode(array("error" => $this->uploadErrors[$error])));
			
		if (!file_exists($_FILES['shopp']['tmp_name']))
			die(json_encode(array("error" => __('The file could not be saved because the upload was not found on the server.','Shopp'))));
			
		if (!is_readable($_FILES['shopp']['tmp_name']))
			die(json_encode(array("error" => __('The file could not be saved because the web server does not have permission to read the upload.','Shopp'))));

		if ($_FILES['shopp']['size'] == 0) 
			die(json_encode(array("error" => __('The file could not be saved because the uploaded file is empty.','Shopp'))));

		$data = file_get_contents($_FILES['shopp']['tmp_name']);

		$formats = array(0=>false,3=>"xml",4=>"tab",5=>"csv");
		preg_match('/((<[^>]+>.+?<\/[^>]+>)|(.+?\t.+?\n)|(.+?,.+?\n))/',$data,$_);
		$format = $formats[count($_)];
		if (!$format) die(json_encode(array("error" => __('The file format could not be detected.','Shopp'))));

		$_ = array();
		switch ($format) {
			case "xml":
				// Example:
				// <localtaxrates>
				// 	<taxrate name="Kent">1</taxrate>
				// 	<taxrate name="New Castle">0.25</taxrate>
				// 	<taxrate name="Sussex">1.4</taxrate>
				// </localtaxrates>
				require_once(SHOPP_MODEL_PATH."/XML.php");
				$XML = new xmlQuery($data);
				$taxrates = $XML->tag('taxrate');
				while($rate = $taxrates->each()) {
					$name = $rate->attr(false,'name');
					$value = $rate->content();
					$_[$name] = $value;
				}
				break;
			case "csv":
				if (($csv = fopen($_FILES['shopp']['tmp_name'], "r")) === false) die('');
				while (($data = fgetcsv($csv, 1000, ",")) !== false)
					$_[$data[0]] = !empty($data[1])?$data[1]:0;
				fclose($csv);
				break;
			case "tab":
			default:
				$lines = explode("\n",$data);
				foreach ($lines as $line) {
					list($key,$value) = explode("\t",$line);
					$_[$key] = $value;
				}
		}

		echo json_encode($_);
		exit();
		
	}
	
	function feature_product () {
		check_admin_referer('wp_ajax_shopp_feature_product');
		
		if (empty($_GET['feature'])) die('0');
		$Product = new Product($_GET['feature']);
		if ($Product->featured == "on") $Product->featured = "off";
		else $Product->featured = "on";
		$Product->save();
		echo $Product->featured;
		exit();
	}
		
} // END class AjaxFlow

?>