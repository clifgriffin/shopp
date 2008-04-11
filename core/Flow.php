<?php
/**
 * Flow controller
 * Main flow controller for all miscellaneous application flow calls
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 31 March, 2008
 * @package Shopp
 **/

class Flow {
	var $Core;
	var $basepath;
	var $baseuri;
	var $secureuri;
	
	function Flow (&$Core) {
		$this->Core =& $Core;
		
		print $this->Core->Settings->get('catalog_url');
		$this->basepath = dirname(dirname(__FILE__));
		$this->uri = ((!empty($_SERVER['HTTPS']))?"https://":"http://").
					$_SERVER['SERVER_NAME'].str_replace("?".$_SERVER['QUERY_STRING'],"",$_SERVER['REQUEST_URI']);
		$this->secureuri = 'https://'.$_SERVER['SERVER_NAME'].$this->uri;
		define("SHOPP_BASEURI",$this->uri);
		define("SHOPP_SECUREURI",$this->uri);	
		define("SHOPP_PLUGINURI",$this->Core->uri);
		define("SHOPP_CATALOGURL",$this->Core->Settings->get('catalog_url'));
		define("SHOPP_CARTURL",$this->Core->Settings->get('cart_url'));
		define("SHOPP_CHECKOUTURL",$this->Core->Settings->get('checkout_url'));
		define("SHOPP_CONFIRMURL",$this->Core->Settings->get('confirm_url'));
		define("SHOPP_RECEIPTURL",$this->Core->Settings->get('receipt_url'));
	}

	
	/**
	 * Shopping Cart flow handlers
	 **/
	
	function cart_post () {
		global $Cart;

		switch($_POST['cart']) {
			case "add":
				if (isset($_POST['product']) && isset($_POST['price'])) {
					$Product = new Product($_POST['product']);
					$Price = new Price($_POST['price']);
					$quantity = (!empty($_POST['quantity']))?$_POST['quantity']:1;
			
					if (isset($_POST['item'])) $Cart->change($_POST['item'],$Product,$Price);
					else $Cart->add($quantity,$Product,$Price);
				}
				break;
			case "update":
				if (!empty($_POST['item']) && isset($_POST['quantity'])) {
					$Cart->update($_POST['item'],$_POST['quantity']);
				} elseif (!empty($_POST['items'])) {
					foreach ($_POST['items'] as $id => $item) {
						if (isset($item['quantity'])) {
							$Cart->update($id,$item['quantity']);	
						}
						if (isset($item['product']) && isset($item['price'])) {
							$Product = new Product($item['product']);
							$Price = new Price($item['price']);
							$Cart->change($id,$Product,$Price);
						}
					}

				} 
			
				break;
		}
				
	}

	function cart_request () {
		global $Cart;

		switch ($_GET['cart']) {
			case "add":		// Received an add product request, add a new item the cart
				if (!empty($_GET['product']) && strpos($_GET['product'],",") !== false) {
					list($product_id,$price_id) = split(",",$_GET['product']);
					$Product = new Product($product_id);
					$Price = new Price($price_id);
					$quantity = (!empty($_GET['quantity']))?$_GET['quantity']:1;
					$Cart->add($quantity,$Product,$Price);				
				}
				break;
			case "update":  // Received an update request

				// Update quantity
				if (isset($_GET['item']) && isset($_GET['quantity'])) 
					$Cart->update($_GET['item'],$_GET['quantity']);

				// Update product/pricing
				if (isset($_GET['item']) && 
						!empty($_GET['product']) && 
						strpos($_GET['product'],",") !== false) {

					list($product_id,$price_id) = split(",",$_GET['product']);
					$Product = new Product($product_id);
					$Price = new Price($price_id);
					$Cart->change($_GET['item'],$Product,$Price);
				}

				break;
		}

	}

	function cart_ajax () {
		// Not implemented
	}

	function cart_default ($attrs) {
		global $Cart;

		ob_start();
		include("{$this->basepath}/ui/cart/cart.html");
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}
	
	
	/**
	 * Checkout flow handlers
	 **/
	function checkout_onestep () {
		global $Cart;

		ob_start();
		$base = $this->Core->Settings->get('base_operations');
		$markets = $this->Core->Settings->get('target_markets');
		$regions = $this->Core->Settings->get('regions');
		foreach ($markets as $iso => $country) $countries[$iso] = $country;
		$states = $regions[$base['country']];
		
		if (isset($Cart->data->OrderError)) include("{$this->basepath}/ui/checkout/errors.html");
		include("{$this->basepath}/ui/checkout/checkout.html");
		$content = ob_get_contents();
		ob_end_clean();

		unset($Cart->data->OrderError);
		return $content;
	}
	
	function checkout_order_summary () {
		global $Cart;

		ob_start();
		include("{$this->basepath}/ui/checkout/summary.html");
		$content = ob_get_contents();
		ob_end_clean();
		
		return $content;
	}
	
	function order_confirmation () {
		global $Cart;
		ob_start();
		include("{$this->basepath}/ui/checkout/confirm.html");
		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}
	

	/**
	 * Transaction flow handlers
	 **/
	function order_receipt () {
		global $Cart;
		require_once("{$this->basepath}/model/Purchase.php");
		$Purchase = new Purchase($Cart->data->Purchase);
		$Purchase->load_purchased();
		ob_start();
		if (!empty($Purchase->id)) include("{$this->basepath}/ui/checkout/receipt.html");
		else echo '<p class="error">There was a problem retrieving your order, although the transaction was successful.</p>';
		$content = ob_get_contents();
		ob_end_clean();		
		return $content;
	}
	
	/**
	 * Orders admin flow handlers
	 */
	function orders_list() {
		global $Orders;
		$db =& DB::get();

		require_once("{$this->basepath}/model/Purchase.php");

		if ($_GET['deleting'] == "order"
						&& !empty($_GET['delete']) 
						&& is_array($_GET['delete'])) {
			foreach($_GET['delete'] as $deletion) {
				$Purchase = new Purchase($deletion);
				$Purchase->load_purchased();
				foreach ($Purchase->purchased as $purchased) {
					$Purchased = new Purchased($purchased->id);
					$Purchased->delete();
				}
				$Purchase->delete();
			}
		}

		$Purchase = new Purchase();

		$statusLabels = $this->Core->Settings->get('order_status');
		if (empty($statusLabels)) $statusLabels = array('');
		else ksort($statusLabels); 

		if (isset($_GET['status'])) $filter = "WHERE status='{$_GET['status']}'";
		$Orders = $db->query("SELECT * FROM $Purchase->_table $filter ORDER BY created DESC",AS_ARRAY);
		include("{$this->basepath}/ui/orders/orders.html");
	}
	
	function order_manager () {
		global $Purchase;
		require("{$this->basepath}/model/Purchase.php");
		if (preg_match("/\d+/",$_GET['manage'])) {
			$Purchase = new Purchase($_GET['manage']);
			$Purchase->load_purchased();
		} else $Purchase = new Purchase();
		
		if (!empty($_POST)) {
			$Purchase->updates($_POST);
			$Purchase->save();
		}

		$statusLabels = $this->Core->Settings->get('order_status');
		if (empty($statusLabels)) $statusLabels = array('');
		else ksort($statusLabels); 
		
		include("{$this->basepath}/ui/orders/order.html");
	}
	
	function order_status_counts () {
		$db =& DB::get();
		
		include_once("{$this->basepath}/model/Purchase.php");
		$p = new Purchase();
		$labels = $this->Core->Settings->get('order_status');
		
		$r = $db->query("SELECT status,COUNT(status) AS total FROM {$p->_table} GROUP BY status ORDER BY status ASC",AS_ARRAY);

		$status = array();
		foreach ($r as $count) $status[$count->status] = $count->total;
		foreach ($labels as $id => $label) if (empty($status[$id])) $status[$id] = 0;
		return $status;
	}
	
	/**
	 * Products admin flow handlers
	 **/
	function products_list() {
		global $Products;
		$db =& DB::get();

		if ($_GET['deleting'] == "product"
				&& !empty($_GET['delete']) 
				&& is_array($_GET['delete'])) {
			foreach($_GET['delete'] as $deletion) {
				$Product = new Product($deletion);
				$Product->load_prices();
				foreach ($Product->prices as $price) {
					$Price = new Price($price->id);
					$Price->delete();
				}
				$Product->delete();
			}
		}
		
		$categories = $this->Core->Settings->get('product_categories');
		if (empty($categories)) $categories = array('');
		
		$Products = $db->query("SELECT pd.id,pd.name,pd.brand,pd.category,MAX(pt.price) AS maxprice,MIN(pt.price) as minprice FROM shopp_product AS pd LEFT JOIN shopp_price AS pt ON pd.id=pt.product GROUP BY pt.product",AS_ARRAY);
		include("{$this->basepath}/ui/products/products.html");
	}
		
	function product_editor() {
		global $Product;
		$db =& DB::get();

		if ($_GET['edit'] != "new") {
			$Product = new Product($_GET['edit']);
			$Product->load_prices();
			$Product->load_categories();
		} else $Product = new Product();

		if (!empty($_POST['save'])) {
			$this->save_product($Product);	
			return true;
		}

		$brands = array('');
		$brandnames = $db->query("SELECT brand FROM $Product->_table GROUP BY brand",AS_ARRAY);
		foreach($brandnames as $name) $brands[] = $name->brand;
		
		require_once("{$this->basepath}/model/Category.php");
		$Category = new Category();
		$categories = $db->query("SELECT id,name,parent FROM $Category->_table ORDER BY parent,name",AS_ARRAY);
		unset($Category);
		$categories = sort_tree($categories);
		if (empty($categories)) $categories = array('');
		
		$categories_menu = '<option value="0" rel="-1,-1">Parent Category&hellip;</option>';
		foreach ($categories as $category) {
			$padding = str_repeat("&nbsp;",$category->depth*3);
			$categories_menu .= '<option value="'.$category->id.'" rel="'.$category->parent.','.$category->depth.'">'.$padding.$category->name.'</option>';
		}
		
		$selectedCategories = array();
		foreach ($Product->categories as $catalog) $selectedCategories[] = $catalog->category;

		include("{$this->basepath}/ui/products/editor.html");

	}

	function save_product($Product) {
		
		$Product->updates($_POST,array('categories'));
		$Product->save();

		if (is_array($_POST['categories'])) 
			$Product->save_categories($_POST['categories']);
			
		if (!empty($_POST['price']) && is_array($_POST['price'])) {

			// Delete prices that were marked for removal
			if (!empty($_POST['deletePrices'])) {
				$deletes = array();
				if (strpos($_POST['deletePrices'],","))	$deletes = split(',',$_POST['deletePrices']);
				else $deletes = array($_POST['deletePrices']);

				foreach($deletes as $option) {
					$Price = new Price($option);
					$Price->delete();
				}
			}

			// Save prices that there are updates for
			foreach($_POST['price'] as $option) {
				if (empty($option['id'])) {
					$Price = new Price();
					$option['product'] = $Product->id;
				} else $Price = new Price($option['id']);
				
				$Price->updates($option);
				$Price->save();
			}
		}

		$this->products_list();
	}
	
	/**
	 * Category flow handlers
	 **/
	function categories_list () {
		$db =& DB::get();
		require_once("{$this->basepath}/model/Category.php");
		require_once("{$this->basepath}/model/Catalog.php");

		if ($_GET['deleting'] == "category"
				&& !empty($_GET['delete']) 
				&& is_array($_GET['delete'])) {
			foreach($_GET['delete'] as $deletion) {
				$Category = new Category($deletion);
				$db->query("UPDATE $Category->_table SET parent=0 WHERE parent=$Category->id");
				$Category->delete();
			}
		}

		$Category = new Category();
		$Catalog = new Catalog();
		
		$Categories = $db->query("select cat.*,count(sc.product) as products from $Category->_table as cat left join $Catalog->_table as sc on sc.category=cat.id group by cat.id order by parent,name",AS_ARRAY);
		$Categories = sort_tree($Categories);
		
		unset($Category);
		unset($Catalog);

		include("{$this->basepath}/ui/products/categories.html");
	}
	
	function category_editor () {
		$db =& DB::get();
		require_once("{$this->basepath}/model/Category.php");
		
		if ($_GET['category'] != "new") {
			$Category = new Category($_GET['category']);
		} else $Category = new Category();
		
		if (!empty($_POST['save'])) {
			$Category->updates($_POST);
			$Category->save();
			$this->categories_list();
			return true;
		}		
		
		$categories = $db->query("SELECT id,name,parent FROM $Category->_table ORDER BY parent,name",AS_ARRAY);
		$categories = sort_tree($categories);

		$categories_menu = '<option value="0" rel="-1,-1">Parent Category&hellip;</option>';
		foreach ($categories as $category) {
			$padding = str_repeat("&nbsp;",$category->depth*3);
			if ($Category->parent == $category->id) $selected = ' selected="selected"';
			else $selected = "";
			if ($Category->id != $category->id) $categories_menu .= '<option value="'.$category->id.'" rel="'.$category->parent.','.$category->depth.'"'.$selected.'>'.$padding.$category->name.'</option>';
		}


		include("{$this->basepath}/ui/products/category.html");
	}
	
	
	
	/**
	 * Settings flow handlers
	 **/
	
	function settings_general () {
		if (!empty($_POST['save'])) {
			ksort($_POST['settings']['order_status']);
			$this->settings_save();
		}

		$countries = array();
		foreach ($this->Core->Settings->get('countries') as $iso => $country)
			$countries[$iso] = $country['name'];
			
		$operations = $this->Core->Settings->get('base_operations');
		if (!empty($operations['region'])) {
			$regions = $this->Core->Settings->get('regions');
			$regions = $regions[$operations['country']];
		}
		
		$targets = $this->Core->Settings->get('target_markets');
		if (!$targets) $targets = array();
		
		$statusLabels = $this->Core->Settings->get('order_status');
		if ($statusLabels) ksort($statusLabels);
		
		include("{$this->basepath}/ui/settings/settings.html");
	}


	function settings_catalog () {
		if (!empty($_POST['save'])) $this->settings_save();
		include("{$this->basepath}/ui/settings/catalog.html");
	}

	function settings_cart () {
		if (!empty($_POST['save'])) $this->settings_save();
		include("{$this->basepath}/ui/settings/cart.html");
	}

	function settings_checkout () {
		if (!empty($_POST['save'])) $this->settings_save();
		include("{$this->basepath}/ui/settings/checkout.html");
	}

	function settings_shipping () {
		if (!empty($_POST['save'])) $this->settings_save();
		include("{$this->basepath}/ui/settings/shipping.html");
	}

	function settings_taxes () {
		if (!empty($_POST['save'])) $this->settings_save();
		
		$rates = $this->Core->Settings->get('taxrates');
		$base = $this->Core->Settings->get('base_operations');
		$countries = $this->Core->Settings->get('target_markets');
		$regions = $this->Core->Settings->get('regions');
		
		include("{$this->basepath}/ui/settings/taxes.html");
	}	

	function settings_payments () {
		if (!empty($_POST['save'])) $this->settings_save();
		
		$data = $this->settings_get_gateways();
		
		$gateways = array();
		$Processors = array();
		foreach ($data as $gateway) {
			$gateways[$gateway->file] = $gateway->name;
			$ProcessorClass = $gateway->tags['class'];
			include($gateway->file);
			$Processors[] = new $ProcessorClass();
		}
		
		include("{$this->basepath}/ui/settings/payments.html");
	}

	function settings_get_gateways () {
		$gateway_path = $this->basepath."/gateways";
		
		$gateways = array();
		$gwfiles = array();
		find_files(".php",$gateway_path,$gateway_path,$gwfiles);
		if (empty($gwfiles)) return $gwfiles;
		
		foreach ($gwfiles as $file) {
			if (! is_readable($gateway_path.$file)) continue;
			if (! $gateway = $this->scan_gateway_meta($gateway_path.$file)) continue;
			$gateways[$file] = $gateway;
		}

		return $gateways;
	}

	function scan_gateway_meta ($file) {
		$metadata = array();
		
		$meta = get_filemeta($file);
		
		if ($meta) {
			$lines = split("\n",substr($meta,1));
			foreach($lines as $line) {
				preg_match("/^(?:[\s\*]*?\b([^@\*\/]*))/",$line,$match);
				if (!empty($match[1])) $data[] = $match[1];
				preg_match("/^(?:[\s\*]*?@([^\*\/]+?)\s(.+))/",$line,$match);
				if (!empty($match[1]) && !empty($match[2])) $tags[$match[1]] = $match[2];
			
			}
			$gateway = new stdClass();
			$gateway->file = $file;
			$gateway->name = $data[0];
			$gateway->description = (!empty($data[1]))?$data[1]:"";
			$gateway->tags = $tags;
			$gateway->activated = false;
			if ($this->Core->Settings->get('payment_gateway') == $file) $module->activated = true;
			return $gateway;
		}
		return false;
	}
	
	function settings_save () {
		if (empty($_POST['settings']) || !is_array($_POST['settings'])) return false;
		foreach ($_POST['settings'] as $setting => $value) {
			if (is_array($value)) asort($value);
			$this->Core->Settings->save($setting,$value);
		}
	}
		
	/**
	 * Setup - set up all the lists
	 */
	function development_setup () {
		$this->setup_countries();
		$this->setup_regions();
		$this->setup_currencies();
		$this->Core->Settings->save('shipping','on');	
		$this->Core->Settings->save('shopp_setup','completed');	
	}
	
	function setup_countries () {
		global $Shopp;
		include_once("init.php");
		$this->Core->Settings->save('countries',get_countries(),false);
	}
	
	function setup_regions () {
		global $Shopp;
		include_once("init.php");
		$this->Core->Settings->save('regions',get_country_regions(),false);
	}

	function setup_currencies () {
		global $Shopp;
		// include_once("init.php");
		// $this->Core->Settings->save('currencies',$currencies,false);
		// unset($currencies);
	}
	
}
?>