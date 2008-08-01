<?php
/**
 * Flow handlers
 * Main flow handling for all request processing/handling
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 31 March, 2008
 * @package Shopp
 **/

class Flow {
	var $Admin;
	var $Settings;

	var $basepath;
	var $baseuri;
	var $secureuri;
	
	function Flow (&$Core) {
		$this->Settings =& $Core->Settings;
		$this->ShipCalcs =& $Core->ShipCalcs;
		$this->Cart =& $Core->Cart;

		$this->basepath = dirname(dirname(__FILE__));
		$this->uri = ((!empty($_SERVER['HTTPS']))?"https://":"http://").
					$_SERVER['SERVER_NAME'].str_replace("?".$_SERVER['QUERY_STRING'],"",$_SERVER['REQUEST_URI']);
		$this->secureuri = 'https://'.$_SERVER['SERVER_NAME'].$this->uri;
		
		$this->Admin = new stdClass();
		$this->Admin->default = $Core->directory."/".$this->Core->file;
		$this->Admin->orders = $this->Admin->default;
		$this->Admin->settings = $Core->directory."/settings";
		$this->Admin->products = $Core->directory."/products";
		
		
		define("SHOPP_BASEURI",$this->uri);
		define("SHOPP_SECUREURI",$this->uri);	
		define("SHOPP_PLUGINURI",$Core->uri);
		define("SHOPP_CATALOGURL",$this->Settings->get('catalog_url'));
		define("SHOPP_CARTURL",$this->Settings->get('cart_url'));
		define("SHOPP_CHECKOUTURL",$this->Settings->get('checkout_url'));
		define("SHOPP_CONFIRMURL",$this->Settings->get('confirm_url'));
		define("SHOPP_RECEIPTURL",$this->Settings->get('receipt_url'));
		define("SHOPP_DBSCHEMA",$this->basepath."/core/model/schema.sql");
	}

	/**
	 * Catalog flow handlers
	 **/
	function catalog () {
		global $wp_rewrite,$Shopp;
		$db =& DB::get();
		// require_once("{$this->basepath}/core/model/Catalog.php");
		// require_once("{$this->basepath}/core/model/Category.php");
		// require_once("{$this->basepath}/core/model/Product.php");

		if ($category = get_query_var('shopp_category')) $page = "category";
		if ($productid = get_query_var('shopp_product_id')) $page = "product";
		if ($productname = get_query_var('shopp_product_name')) $page = "product";

		// echo "<p>category: $category</p>";
		// echo "<p>productid: $productid</p>";
		// echo "<p>productname: $productname</p>";

		// Find product by given ID
		if (!empty($productid) && empty($Shopp->Product->id)) {
			require_once("{$this->basepath}/core/model/Product.php");
			$Shopp->Product = new Product($productid);
		}
		
		if (!empty($category)) {
			require_once("{$this->basepath}/core/model/Category.php");
			$Shopp->Category = new Category($category,"slug");
		}
			
		// Find product by category name and product name
		if (!empty($productname) && empty($Shopp->Product->id)) {
			require_once("{$this->basepath}/core/model/Product.php");
			$Shopp->Product = new Product($productname,"slug");
		}
		
		ob_start();
		
		switch ($page) {
			case "product":
				include("{$this->basepath}/templates/product.html");
				break;
			case "category":
				include("{$this->basepath}/templates/category.php");
				break;
			default:
				include("{$this->basepath}/templates/catalog.php");
				break;
		}
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
		
	}	
	
	/**
	 * Shopping Cart flow handlers
	 **/
	
	function cart_post () {
		$Cart =& $this->Cart;
		
		if (isset($_POST['checkout'])) {
			header("Location: ".SHOPP_CHECKOUTURL);
			exit();
		}

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
			case "empty":
				$Cart->clear();
				break;
			case "update":
				if (!empty($_POST['shipping'])) $Cart->shipping($_POST['shipping']);
			
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
			case "shipestimate":
				$countries = $this->Settings->get('countries');
				$regions = $this->Settings->get('regions');
				$_POST['shipping']['region'] = $regions[$countries[$_POST['shipping']['country']]['region']];
				unset($countries,$regions);
				$Cart->shipzone($_POST['shipping']);
				$Cart->shipping();
				break;
		}
				
	}

	function cart_request () {
		global $Cart;
		
		if (isset($_POST['checkout'])) {
			header("Location: ".SHOPP_CHECKOUTURL);
			exit();
		}
		
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
			case "empty": $Cart->clear(); break;
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
		include("{$this->basepath}/templates/cart.html");
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}

	function shipping_estimate ($attrs) {
		global $Cart;

		ob_start();
		include("{$this->basepath}/templates/shipping.html");
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
		$base = $this->Settings->get('base_operations');
		$markets = $this->Settings->get('target_markets');
		$regions = $this->Settings->get('regions');
		foreach ($markets as $iso => $country) $countries[$iso] = $country;
		$states = $regions[$base['country']];
		
		if (isset($Cart->data->OrderError)) include("{$this->basepath}/templates/errors.html");
		include("{$this->basepath}/templates/checkout.html");
		$content = ob_get_contents();
		ob_end_clean();

		unset($Cart->data->OrderError);
		return $content;
	}
	
	function checkout_order_summary () {
		global $Cart;

		ob_start();
		include("{$this->basepath}/templates/summary.html");
		$content = ob_get_contents();
		ob_end_clean();
		
		return $content;
	}
	
	function order_confirmation () {
		global $Cart;
		ob_start();
		include("{$this->basepath}/templates/confirm.html");
		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}
	

	/**
	 * Transaction flow handlers
	 **/
	function order_receipt () {
		global $Cart;
		require_once("{$this->basepath}/core/model/Purchase.php");
		$Purchase = new Purchase($Cart->data->Purchase);
		$Purchase->load_purchased();
		ob_start();
		if (!empty($Purchase->id)) include("{$this->basepath}/templates/receipt.html");
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

		require_once("{$this->basepath}/core/model/Purchase.php");

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

		$statusLabels = $this->Settings->get('order_status');
		if (empty($statusLabels)) $statusLabels = array('');
		else ksort($statusLabels); 

		if (isset($_GET['status'])) $filter = "WHERE status='{$_GET['status']}'";
		$Orders = $db->query("SELECT * FROM $Purchase->_table $filter ORDER BY created DESC",AS_ARRAY);
		include("{$this->basepath}/core/ui/orders/orders.html");
	}
	
	function order_manager () {
		global $Purchase;
		require("{$this->basepath}/core/model/Purchase.php");
		if (preg_match("/\d+/",$_GET['manage'])) {
			$Purchase = new Purchase($_GET['manage']);
			$Purchase->load_purchased();
		} else $Purchase = new Purchase();
		
		if (!empty($_POST)) {
			$Purchase->updates($_POST);
			$Purchase->save();
		}

		$statusLabels = $this->Settings->get('order_status');
		if (empty($statusLabels)) $statusLabels = array('');
		else ksort($statusLabels); 
		
		include("{$this->basepath}/core/ui/orders/order.html");
	}
	
	function order_status_counts () {
		$db =& DB::get();
		
		include_once("{$this->basepath}/core/model/Purchase.php");
		$p = new Purchase();
		$labels = $this->Settings->get('order_status');
		
		if (empty($labels)) return false;

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
				$Product->delete();
			}
		}
		
		if (empty($categories)) $categories = array('');
		
		$pd = new Product();
		$pt = new Price();
		$cat = new Category();
		$clog = new Catalog();
		$Products = $db->query("SELECT pd.id,pd.name,pd.featured,GROUP_CONCAT(DISTINCT cat.name ORDER BY cat.name SEPARATOR ', ') AS categories, MAX(pt.price) AS maxprice,MIN(pt.price) AS minprice FROM $pd->_table AS pd LEFT JOIN $pt->_table AS pt ON pd.id=pt.product AND pt.type != 'N/A' LEFT JOIN $clog->_table AS clog ON pd.id=clog.product LEFT JOIN $cat->_table AS cat ON cat.id=clog.category GROUP BY pd.id",AS_ARRAY);
		unset($pd,$pt,$cat,$clog);
		
		include("{$this->basepath}/core/ui/products/products.html");
	}
		
	function product_editor() {
		global $Product;
		$db =& DB::get();

		if ($_GET['edit'] != "new") {
			$Product = new Product($_GET['edit']);
			$Product->load_prices();
			$Product->load_specs();
			$Product->load_categories();
		} else $Product = new Product();

		if (!empty($_POST['save'])) {
			// print_r($_POST);
			$this->save_product($Product);	
			return true;
		}

		require_once("{$this->basepath}/core/model/Asset.php");
		require_once("{$this->basepath}/core/model/Category.php");

		$Price = new Price();
		$priceTypes = $Price->_lists['type'];
		
		$Category = new Category();
		$categories = $db->query("SELECT id,name,parent FROM $Category->_table ORDER BY parent,name",AS_ARRAY);
		unset($Category);
		$categories = sort_tree($categories);
		if (empty($categories)) $categories = array();
		
		$categories_menu = '<option value="0" rel="-1,-1">Parent Category&hellip;</option>';
		foreach ($categories as $category) {
			$padding = str_repeat("&nbsp;",$category->depth*3);
			$categories_menu .= '<option value="'.$category->id.'" rel="'.$category->parent.','.$category->depth.'">'.$padding.$category->name.'</option>';
		}		
		
		$selectedCategories = array();
		foreach ($Product->categories as $catalog) $selectedCategories[] = $catalog->category;

		$Assets = new Asset();
		$Images = $db->query("SELECT id,src FROM $Assets->_table WHERE context='product' AND parent=$Product->id AND datatype='thumbnail' ORDER BY sortorder",AS_ARRAY);
		unset($Assets);

		$shiprates = $this->Settings->get('shipping_rates');
		if (!empty($shiprates)) ksort($shiprates);

		include("{$this->basepath}/core/ui/products/editor.html");

	}

	function save_product($Product) {
		$db =& DB::get();
		
		if (!$_POST['options']) $Product->options = array();
		$_POST['slug'] = sanitize_title_with_dashes($_POST['name']);
		$Product->updates($_POST,array('categories'));
		$Product->save();

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
			foreach($_POST['price'] as $i => $option) {
				if (empty($option['id'])) {
					$Price = new Price();
					$option['product'] = $Product->id;
				} else $Price = new Price($option['id']);
				$option['sortorder'] = array_search($i,$_POST['sortorder'])+1;
				
				$Price->updates($option);
				$Price->save();
			}
			unset($Price);
		}
				
		if (!empty($_POST['details']) && is_array($_POST['details'])) {
			if (!empty($_POST['deletedSpecs'])) {
				$deletes = array();
				if (strpos($_POST['deletedSpecs'],","))	$deletes = split(',',$_POST['deletedSpecs']);
				else $deletes = array($_POST['deletedSpecs']);
			
				foreach($deletes as $option) {
					$Spec = new Spec($option);
					$Spec->delete();
				}
				unset($Spec);
			}
			
			foreach ($_POST['details'] as $i => $spec) {
				if (empty($spec['id'])) {
					$Spec = new Spec();
					$spec['product'] = $Product->id;
				} else $Spec = new Spec($spec['id']);
				$spec['sortorder'] = array_search($i,$_POST['detailsorder'])+1;
				
				$Spec->updates($spec);
				$Spec->save();
			}
		}
		
		if (!empty($_POST['deleteImages'])) {			
			$deletes = array();
			if (strpos($_POST['deleteImages'],","))	$deletes = split(',',$_POST['deleteImages']);
			else $deletes = array($_POST['deleteImages']);
			$Product->delete_images($deletes);
		}
		
		if (!empty($_POST['images']) && is_array($_POST['images'])) 
			$Product->save_imageorder($_POST['images']);

		unset($Product);

		$this->products_list();
	}
	
	function product_images () {
			require("{$this->basepath}/core/model/Asset.php");
			require("{$this->basepath}/core/model/Image.php");
			
			// TODO: add some error handling here
			
			// Save the source image
			$Image = new Asset();
			$Image->parent = $_POST['product'];
			$Image->context = "product";
			$Image->datatype = "image";
			$Image->name = $_FILES['Filedata']['name'];
			list($width, $height, $mimetype, $attr) = getimagesize($_FILES['Filedata']['tmp_name']);
			$Image->properties = array(
				"width" => $width,
				"height" => $height,
				"mimetype" => image_type_to_mime_type($mimetype),
				"attr" => $attr);
			$Image->data = addslashes(file_get_contents($_FILES['Filedata']['tmp_name']));
			$Image->save();
			unset($Image->data); // Save memory for small image & thumbnail processing

			// Generate Small Size
			$SmallSettings = array();
			$SmallSettings['width'] = $this->Settings->get('gallery_small_width');
			$SmallSettings['height'] = $this->Settings->get('gallery_small_height');
			$SmallSettings['sizing'] = $this->Settings->get('gallery_small_sizing');
			$SmallSettings['quality'] = $this->Settings->get('gallery_small_quality');
			
			$Small = new Asset();
			$Small->parent = $Image->parent;
			$Small->context = "product";
			$Small->datatype = "small";
			$Small->src = $Image->id;
			$Small->name = "small_".$Image->name;
			$Small->data = file_get_contents($_FILES['Filedata']['tmp_name']);
			$SmallSizing = new ImageProcessor($Small->data,$width,$height);
			
			switch ($SmallSettings['sizing']) {
				case "0": $SmallSizing->scaleToWidth($SmallSettings['width']); break;
				case "1": $SmallSizing->scaleToHeight($SmallSettings['height']); break;
				case "2": $SmallSizing->scaleToFit($SmallSettings['width'],$SmallSettings['height']); break;
				case "3": $SmallSizing->scaleCrop($SmallSettings['width'],$SmallSettings['height']); break;
			}
			$SmallSizing->UnsharpMask();
			$Small->data = addslashes($SmallSizing->imagefile($SmallSettings['quality']));
			$Small->properties = array();
			$Small->properties['width'] = $SmallSizing->Processed->width;
			$Small->properties['height'] = $SmallSizing->Processed->height;
			$Small->properties['mimetype'] = "image/jpeg";
			unset($SmallSizing);
			$Small->save();
			unset($Small);
			
			// Generate Thumbnail
			$ThumbnailSettings = array();
			$ThumbnailSettings['width'] = $this->Settings->get('gallery_thumbnail_width');
			$ThumbnailSettings['height'] = $this->Settings->get('gallery_thumbnail_height');
			$ThumbnailSettings['sizing'] = $this->Settings->get('gallery_thumbnail_sizing');
			$ThumbnailSettings['quality'] = $this->Settings->get('gallery_thumbnail_quality');

			$Thumbnail = new Asset();
			$Thumbnail->parent = $Image->parent;
			$Thumbnail->context = "product";
			$Thumbnail->datatype = "thumbnail";
			$Thumbnail->src = $Image->id;
			$Thumbnail->name = "thumbnail_".$Image->name;
			$Thumbnail->data = file_get_contents($_FILES['Filedata']['tmp_name']);
			$ThumbnailSizing = new ImageProcessor($Thumbnail->data,$width,$height);
			
			switch ($ThumbnailSettings['sizing']) {
				case "0": $ThumbnailSizing->scaleToWidth($ThumbnailSettings['width']); break;
				case "1": $ThumbnailSizing->scaleToHeight($ThumbnailSettings['height']); break;
				case "2": $ThumbnailSizing->scaleToFit($ThumbnailSettings['width'],$ThumbnailSettings['height']); break;
				case "3": $ThumbnailSizing->scaleCrop($ThumbnailSettings['width'],$ThumbnailSettings['height']); break;
			}
			$ThumbnailSizing->UnsharpMask();
			$Thumbnail->data = addslashes($ThumbnailSizing->imagefile($ThumbnailSettings['quality']));
			$Thumbnail->properties = array();
			$Thumbnail->properties['width'] = $ThumbnailSizing->Processed->width;
			$Thumbnail->properties['height'] = $ThumbnailSizing->Processed->height;
			$Thumbnail->properties['mimetype'] = "image/jpeg";
			unset($ThumbnailSizing);
			$Thumbnail->save();
			unset($Thumbnail->data);
			
			echo json_encode(array("id"=>$Thumbnail->id,"src"=>$Thumbnail->src));
	}
	
	
	
	/**
	 * Category flow handlers
	 **/
	function categories_list () {
		$db =& DB::get();
		require_once("{$this->basepath}/core/model/Category.php");
		require_once("{$this->basepath}/core/model/Catalog.php");

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
		
		unset($Category,$Catalog);

		include("{$this->basepath}/core/ui/products/categories.html");
	}
	
	function category_editor () {
		$db =& DB::get();
		require_once("{$this->basepath}/core/model/Category.php");
		
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


		include("{$this->basepath}/core/ui/products/category.html");
	}
	
	
	
	/**
	 * Settings flow handlers
	 **/
	
	function settings_general () {
		$countries = array();
		foreach ($this->Settings->get('countries') as $iso => $country) {
			if ($_POST['settings']['base_operations']['country'] == $iso) 
				$base_region = $country['region'];
			$countries[$iso] = $country['name'];
		}

		if (!empty($_POST['save'])) {
			$_POST['settings']['base_operations']['name'] = $countries[$_POST['settings']['base_operations']['country']];
			$_POST['settings']['base_operations']['region'] = $base_region;
			ksort($_POST['settings']['order_status']);
			$this->settings_save();
		}

		$operations = $this->Settings->get('base_operations');
		if (!empty($operations['zone'])) {
			$zones = $this->Settings->get('zones');
			$zones = $zones[$operations['country']];
		}
		$targets = $this->Settings->get('target_markets');
		if (!$targets) $targets = array();
		
		$currencies = array('');
		$currencylist = $this->Settings->get('currencies');
		foreach($currencylist as $id => $currency) 
			$currencies[$id] = $currency['name'];
		
		$statusLabels = $this->Settings->get('order_status');
		if ($statusLabels) ksort($statusLabels);
		
		include("{$this->basepath}/core/ui/settings/settings.html");
	}


	function settings_product_page () {
		if (!empty($_POST['save'])) $this->settings_save();
		
		$sizingOptions = array(	"Scale to width",
								"Scale to height",
								"Scale to fit",
								"Scale &amp; crop");
								
		$qualityOptions = array("Highest quality, largest file size",
								"Higher quality, larger file size",
								"Balanced quality &amp; file size",
								"Lower quality, smaller file size",
								"Lowest quality, smallest file size");
		
		
		include("{$this->basepath}/core/ui/settings/products.html");
	}

	function settings_catalog () {
		if (!empty($_POST['save'])) $this->settings_save();
		include("{$this->basepath}/core/ui/settings/catalog.html");
	}

	function settings_cart () {
		if (!empty($_POST['save'])) $this->settings_save();
		include("{$this->basepath}/core/ui/settings/cart.html");
	}

	function settings_checkout () {
		if (!empty($_POST['save'])) $this->settings_save();
		include("{$this->basepath}/core/ui/settings/checkout.html");
	}

	function settings_shipping () {
		
		if (!empty($_POST['save'])) {
			// Sterilize $values
			foreach ($_POST['settings']['shipping_rates'] as $i => $method) {
				foreach ($method as $key => $rates) {
					if (is_array($rates)) {
						foreach ($rates as $id => $value) {
							$_POST['settings']['shipping_rates'][$i][$key][$id] = preg_replace("/[^0-9\.\+]/","",$_POST['settings']['shipping_rates'][$i][$key][$id]);
						}
					}
				}
			}
	 		$this->settings_save();			
		}

		$methods = $this->ShipCalcs->methods;

		$base = $this->Settings->get('base_operations');
		$regions = $this->Settings->get('regions');
		$region = $regions[$base['region']];
		$useRegions = $this->Settings->get('shipping_regions');

		$areas = $this->Settings->get('areas');
		if (is_array($areas[$base['country']]) && $useRegions == "on") 
			$areas = array_keys($areas[$base['country']]);
		else $areas = array($base['country'] => $base['name']);
		unset($countries,$regions);

		$rates = $this->Settings->get('shipping_rates');
		if (!empty($rates)) ksort($rates);
		
		// print "<pre>";
		// print_r($rates);
		// print "</pre>";
		
		include("{$this->basepath}/core/ui/settings/shipping.html");
	}

	function settings_taxes () {
		if (!empty($_POST['save'])) $this->settings_save();
		
		$rates = $this->Settings->get('taxrates');
		$base = $this->Settings->get('base_operations');
		$countries = $this->Settings->get('target_markets');
		$zones = $this->Settings->get('zones');
		
		include("{$this->basepath}/core/ui/settings/taxes.html");
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
		
		include("{$this->basepath}/core/ui/settings/payments.html");
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
			if ($this->Settings->get('payment_gateway') == $file) $module->activated = true;
			return $gateway;
		}
		return false;
	}
	
	function settings_save () {
		if (empty($_POST['settings']) || !is_array($_POST['settings'])) return false;
		foreach ($_POST['settings'] as $setting => $value) {
			if (is_array($value)) asort($value);
			$this->Settings->save($setting,$value);
		}
	}
		
	/**
	 * Setup - set up all the lists
	 */
	function setup () {
		$this->setup_regions();
		$this->setup_countries();
		$this->setup_zones();
		$this->setup_areas();
		$this->setup_currencies();		
		$this->Settings->save('shipping','on');	
		$this->Settings->save('order_status',array('Pending','Completed'));	
		$this->Settings->save('shopp_setup','completed');

	}

	function setup_regions () {
		global $Shopp;
		include_once("init.php");
		$this->Settings->save('regions',get_global_regions(),false);
	}
	
	function setup_countries () {
		global $Shopp;
		include_once("init.php");
		$this->Settings->save('countries',get_countries(),false);
	}
	
	function setup_zones () {
		global $Shopp;
		include_once("init.php");
		$this->Settings->save('zones',get_country_zones(),false);
	}

	function setup_areas () {
		global $Shopp;
		include_once("init.php");
		$this->Settings->save('areas',get_country_areas(),false);
	}

	function setup_currencies () {
		global $Shopp;
		include_once("init.php");
		$this->Settings->save('currencies',get_currencies(),false);
	}
	
}
?>