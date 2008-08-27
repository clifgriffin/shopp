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
		$this->Settings = $Core->Settings;
		$this->Cart = $Core->Cart;

		$this->basepath = dirname(dirname(__FILE__));
		$this->uri = ((!empty($_SERVER['HTTPS']))?"https://":"http://").
					$_SERVER['SERVER_NAME'].str_replace("?".$_SERVER['QUERY_STRING'],"",$_SERVER['REQUEST_URI']);
		$this->secureuri = 'https://'.$_SERVER['SERVER_NAME'].$this->uri;
		
		$this->Admin = new stdClass();
		$this->Admin->default = $Core->directory."/".$this->Core->file;
		$this->Admin->orders = $this->Admin->default;
		$this->Admin->settings = $Core->directory."/settings";
		$this->Admin->products = $Core->directory."/products";
		$this->Admin->help = $Core->directory."/help";
		
		define("SHOPP_PATH",$this->basepath);
		define("SHOPP_ADMINPATH",$this->basepath."/core/ui");
		define("SHOPP_PLUGINURI",$Core->uri);
		define("SHOPP_DBSCHEMA",$this->basepath."/core/model/schema.sql");

		define("SHOPP_TEMPLATES",($Core->Settings->get('theme_templates') != "off" && 
		 							is_dir($Core->Settings->get('theme_templates')))?
									$Core->Settings->get('theme_templates'):
									$this->basepath."/templates");

		define("SHOPP_PERMALINKS",(get_option('permalink_structure') == "")?false:true);

		define("SHOPP_LOOKUP",(strpos($_SERVER['REQUEST_URI'],"images/") !== false || 
								strpos($_SERVER['REQUEST_URI'],"lookup=") !== false)?true:false);
	}

	/**
	 * Catalog flow handlers
	 **/
	function catalog () {
		global $Shopp;
		$db = DB::get();

		if ($category = get_query_var('shopp_category')) $page = "category";
		if ($productid = get_query_var('shopp_pid')) $page = "product";
		if ($productname = get_query_var('shopp_product')) $page = "product";

		// Find product by given ID
		if (!empty($productid) && empty($Shopp->Product->id)) {
			$Shopp->Product = new Product($productid);
		}
		
		if (!empty($category)) {
			if (strpos($category,"/") !== false) {
				$categories = split("/",$category);
				$category = end($categories);
			}
			
			switch ($category) {
				case NewProducts::$slug: $Shopp->Category = new NewProducts(); break;
				case FeaturedProducts::$slug: $Shopp->Category = new FeaturedProducts(); break;
				case OnSaleProducts::$slug: $Shopp->Category = new OnSaleProducts(); break;
				default:
					$key = "id";
					if (!preg_match("/\d+/",$category)) $key = "slug";
					$Shopp->Category = new Category($category,$key);
			}

		}
			
		// Find product by category name and product name
		if (!empty($productname) && empty($Shopp->Product->id)) {
			$Shopp->Product = new Product($productname,"slug");
		}
		
		$Shopp->Catalog = new Catalog();
		
		ob_start();
		switch ($page) {
			case "product":
				include(SHOPP_TEMPLATES."/product.php");
				break;
			case "category":
				include(SHOPP_TEMPLATES."/category.php");
				break;
			default:
				include(SHOPP_TEMPLATES."/catalog.php");
				break;
		}
		$content = ob_get_contents();
		ob_end_clean();

		return '<div id="shopp">'.$content.'<div id="clear"></div></div>';
		
	}	
	
	function categories_widget ($args=null) {
		global $Shopp;
		extract($args);

		$options = array();
		$options = $Shopp->Settings->get('categories_widget_options');
		
		$options['title'] = $before_title.$options['title'].$after_title;
		
		$Catalog = new Catalog();
		$menu = $Catalog->tag('category-list',$options);
		echo $before_widget.$menu.$after_widget;
		
	}
	
	function categories_widget_options ($args=null) {
		global $Shopp;
		
		if (isset($_POST['categories_widget_options'])) {
			$options = $_POST['shopp_categories_options'];
			$Shopp->Settings->save('categories_widget_options',$options);
		}

		$options = $Shopp->Settings->get('categories_widget_options');
		
		echo '<p><label>Title<input name="shopp_categories_options[title]" class="widefat" value="'.$options['title'].'"></label></p>';
		echo '<p>';
		echo '<label><input type="hidden" name="shopp_categories_options[dropdown]" value="off" /><input type="checkbox" name="shopp_categories_options[dropdown]" value="on"'.(($options['dropdown'] == "on")?' checked="checked"':'').' /> Show as dropdown</label><br />';
		echo '<label><input type="hidden" name="shopp_categories_options[products]" value="off" /><input type="checkbox" name="shopp_categories_options[products]" value="on"'.(($options['products'] == "on")?' checked="checked"':'').' /> Show product counts</label><br />';
		echo '<label><input type="hidden" name="shopp_categories_options[hierarchy]" value="off" /><input type="checkbox" name="shopp_categories_options[hierarchy]" value="on"'.(($options['hierarchy'] == "on")?' checked="checked"':'').' /> Show hierarchy</label>';
		echo '</p>';
		echo '<div><input type="hidden" name="categories_widget_options" value="1" /></div>';
	}
	
	function init_categories_widget () {
		register_sidebar_widget("Shopp Categories",array(&$this,'categories_widget'),'shopp categories');
		register_widget_control('Shopp Categories',array(&$this,'categories_widget_options'));
	}
	
	/**
	 * Shopping Cart flow handlers
	 **/
	
	function cart_post () {
		global $Shopp;
		$Cart = $Shopp->Cart;
		
		if (isset($_POST['checkout'])) {
			$pages = $this->Settings->get('pages');
			header("Location: ".$Shopp->link('checkout','',true));
			exit();
		}
		
		if (isset($_POST['shopping'])) {
			$pages = $this->Settings->get('pages');
			header("Location: ".$Shopp->link('catalog'));
			exit();
		}
		
		if (isset($_POST['shipping'])) {
			$countries = $this->Settings->get('countries');
			$regions = $this->Settings->get('regions');
			$_POST['shipping']['region'] = $regions[$countries[$_POST['shipping']['country']]['region']];
			unset($countries,$regions);
			
			$Cart->shipzone($_POST['shipping']);
		}
		
		if (isset($_POST['remove'])) $_POST['cart'] = "remove";
		if (isset($_POST['update'])) $_POST['cart'] = "update";
		if (isset($_POST['empty'])) $_POST['cart'] = "empty";

		switch($_POST['cart']) {
			case "add":
				if (isset($_POST['product']) && (isset($_POST['price']) || isset($_POST['options']))) {
					$Product = new Product($_POST['product']);
					if (!empty($_POST['options'])) {
						$key = $Product->optionkey($_POST['options']);
						$Price = new Price();
						$Price->loadby_optionkey($Product->id,$key);
					} else $Price = new Price($_POST['price']); // Load by id
					
					$quantity = (!empty($_POST['quantity']))?$_POST['quantity']:1; // Add 1 by default
					if (isset($_POST['item'])) $Cart->change($_POST['item'],$Product,$Price);						
					else $Cart->add($quantity,$Product,$Price);
				}
				$Cart->shipping($_POST['shipping']);
				break;
			case "remove":
				if (!empty($Cart->contents)) $Cart->remove($_POST['remove']);
				break;
			case "empty":
				$Cart->clear();
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
					$Cart->shipping();
				}
			
				break;
		}
					
	}

	function cart_request () {
		global $Shopp;
		$Cart = $this->Cart;
		
		if (isset($_POST['checkout'])) {
			header("Location: ".$Shopp->link('checkout','',true));
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
			case "remove":
				if (!empty($Cart->contents)) $Cart->remove($_POST['remove']);
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
		// TODO: Not implemented yet
	}

	function cart ($attrs=array()) {
		$Cart = $this->Cart;
		ob_start();
		include(SHOPP_TEMPLATES."/cart.php");
		$content = ob_get_contents();
		ob_end_clean();

		return '<div id="shopp">'.$content.'</div>';
	}

	function init_cart_widget () {
		register_sidebar_widget("Shopp Cart",array(&$this,'cart_widget'),'shopp cart');
		register_widget_control('Shopp Cart',array(&$this,'cart_widget_options'));
	}
	
	function cart_widget_options ($args=null) {
		global $Shopp;

		if (isset($_POST['shopp_cart_widget_options'])) {
			$options = $_POST['shopp_cart_options'];
			$Shopp->Settings->save('cart_widget_options',$options);
		}

		$options = $Shopp->Settings->get('cart_widget_options');

		echo '<p><label>Title<input name="shopp_cart_options[title]" class="widefat" value="'.$options['title'].'"></label></p>';
		echo '<div><input type="hidden" name="shopp_cart_widget_options" value="1" /></div>';
	}

	function cart_widget ($args=null) {
		global $Shopp;
		extract($args);
		
		$options = $Shopp->Settings->get('cart_widget_options');
		
		if (empty($options['title'])) $options['title'] = "Your Cart";
		$options['title'] = $before_title.'<a href="'.$Shopp->link('cart').'">'.$options['title'].'</a>'.$after_title;
		
		$sidecart = $Shopp->Cart->tag('sidecart',$options);
		echo $before_widget.$options['title'].$sidecart.$after_widget;
		
	}

	function shipping_estimate ($attrs) {
		$Cart = $this->Cart;

		ob_start();
		include(SHOPP_TEMPLATES."/shipping.html");
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}
	
	/**
	 * Checkout flow handlers
	 **/
	function checkout () {
		global $Shopp;
		$Cart = $Shopp->Cart;

		$process = get_query_var('shopp_proc');
		$xco = get_query_var('shopp_xco');
		switch ($process) {
			case "confirm-order": $content = $this->order_confirmation(); break;
			case "receipt": $content = $this->order_receipt(); break;
			default:
				ob_start();
				if (isset($Cart->data->OrderError)) include(SHOPP_TEMPLATES."/errors.php");
				if (!empty($xco)) {
					include(SHOPP_TEMPLATES."/summary.php");
					$gateway = "{$this->basepath}/gateways/$xco.php";
					if (file_exists($gateway)) {
						$gateway_meta = $this->scan_gateway_meta($gateway);
						$ProcessorClass = $gateway_meta->tags['class'];
						$Payment = new $ProcessorClass();
						echo $Payment->tag('button');
					}
				} else include(SHOPP_TEMPLATES."/checkout.php");
				$content = ob_get_contents();
				ob_end_clean();

				unset($Cart->data->OrderError);
		}
		return '<div id="shopp">'.$content.'</div>';
	}
	
	function checkout_order_summary () {
		global $Shopp;
		$Cart = $Shopp->Cart;

		ob_start();
		include(SHOPP_TEMPLATES."/summary.php");
		$content = ob_get_contents();
		ob_end_clean();
		
		return $content;
	}
	
	/**
	 * order()
	 * Processes orders by passing transaction information to the active
	 * payment gateway */
	function order ($gateway = false) {
		global $Shopp;
		$Cart = $Shopp->Cart;
		
		$PaymentGatewayError = new stdClass();
		$PaymentGatewayError->code = "404";
		$PaymentGatewayError->message = "There was a problem with the payment processor. The store owner has been contacted and made aware of this issue.";
		
		if ($gateway) {
			
			if (!file_exists($gateway)) {
				$Shopp->Cart->data->OrderError = $PaymentGatewayError;
				return false;
			}
			
			// Use an external checkout payment gateway
			$gateway_meta = $this->scan_gateway_meta($gateway);
			$ProcessorClass = $gateway_meta->tags['class'];
			$Payment = new $ProcessorClass();
			$Purchase = $Payment->process();
			
			if (!$Purchase) {
				$Shopp->Cart->data->OrderError = $Payment->error();
				return false;
			}
			
		} else {
			// Use payment gateway set in payment settings
			$gateway = $Shopp->Settings->get('payment_gateway');

			if (!$gateway || !file_exists($gateway)) {
				$Shopp->Cart->data->OrderError = $PaymentGatewayError;
				return false;
			}

			// Dynamically the payment processing gateway
			$processor_data = $this->scan_gateway_meta($gateway);
			$ProcessorClass = $processor_data->tags['class'];
			include($gateway);

			$Order = $Shopp->Cart->data->Order;
			$Order->Totals = $Shopp->Cart->data->Totals;
			$Order->Items = $Shopp->Cart->contents;
			$Order->Cart = $Shopp->Cart->session;

			$Payment = new $ProcessorClass($Order);

			// Process the transaction through the payment gateway
			$processed = $Payment->process();
			
			// There was a problem processing the transaction, 
			// grab the error response from the gateway so we can report it
			if (!$processed) {
				$Shopp->Cart->data->OrderError = $Payment->error();
				return false;
			}
			// Transaction successful, save the order
			$Order->Customer->save();

			if (!empty($Order->Shipping->address)) {
				$Order->Shipping->customer = $Order->Customer->id;
				$Order->Shipping->save();
			}

			$Order->Billing->customer = $Order->Customer->id;
			$Order->Billing->card = substr($Order->Billing->card,-4);
			$Order->Billing->save();

			$Purchase = new Purchase();
			$Purchase->customer = $Order->Customer->id;
			$Purchase->billing = $Order->Billing->id;
			$Purchase->shipping = $Order->Shipping->id;
			$Purchase->copydata($Order->Customer);
			$Purchase->copydata($Order->Billing);
			$Purchase->copydata($Order->Shipping,'ship');
			$Purchase->copydata($Shopp->Cart->data->Totals);
			$Purchase->freight = $Shopp->Cart->data->Totals->shipping;
			$Purchase->gateway = $processor_data->name;
			$Purchase->transactionid = $Payment->transactionid();
			$Purchase->save();

			foreach($Shopp->Cart->contents as $Item) {
				$Purchased = new Purchased();
				$Purchased->copydata($Item);
				$Purchased->purchase = $Purchase->id;
				if (!empty($Purchased->download)) $Purchased->keygen();
				$Purchased->save();
				if ($Item->inventory) $Item->unstock();
			}
		}
		
		// Empty cart on successful order
		$Shopp->Cart->unload();
		session_destroy();

		// Start new cart session
		$Shopp->Cart = new Cart();
		session_start();
		
		// Save the purchase ID for later lookup
		$Shopp->Cart->data->Purchase = new Purchase($Purchase->id);
		$Shopp->Cart->data->Purchase->load_purchased();

		// Send the e-mail receipt
		$receipt = array();
		$receipt['from'] = $Shopp->Settings->get('shopowner_email');
		$receipt['to'] = "\"{$Purchase->firstname} {$Purchase->lastname}\" <{$Purchase->email}>";
		$receipt['subject'] = "Order Receipt";
		$receipt['receipt'] = $this->order_receipt();
		$receipt['url'] = $_SERVER['SERVER_NAME'];
		// send_email(SHOPP_TEMPLATES."/order.html",$receipt);
		
		if ($Shopp->Settings->get('receipt_copy') == 1) {
			$receipt['to'] = $Shopp->Settings->get('shopowner_email');
			$receipt['subject'] = "New Order";
			// send_email(SHOPP_TEMPLATES."/email.html",$receipt);
		}

		header("Location: ".$Shopp->link('receipt','',true));
		exit();
	}
	
	
	function account () {
		global $Shopp;
		
		if (!empty($_POST['vieworder'])) {
			$Purchase = new Purchase($_POST['purchaseid']);
			if ($Purchase->email == $_POST['email']) {
				$Shopp->Cart->data->Purchase = $Purchase;
				$Purchase->load_purchased();
				ob_start();
				include(SHOPP_TEMPLATES."/receipt.php");
				$content = ob_get_contents();
				ob_end_clean();
				return $content;
			}
		}
		
		ob_start();
		include(SHOPP_ADMINPATH."/orders/account.html");
		$content = ob_get_contents();
		ob_end_clean();
		return '<div id="shopp">'.$content.'</div>';
	}
	
	
	function order_confirmation () {
		global $Shopp;
		$Cart = $Shopp->Cart;
		
		ob_start();
		include(SHOPP_TEMPLATES."/confirm.php");
		$content = ob_get_contents();
		ob_end_clean();
		return '<div id="shopp">'.$content.'</div>';
	}
	

	/**
	 * Transaction flow handlers
	 **/
	function order_receipt () {
		global $Shopp;
		$Cart = $Shopp->Cart;
		
		ob_start();
		include(SHOPP_TEMPLATES."/receipt.php");
		$content = ob_get_contents();
		ob_end_clean();
		return '<div id="shopp">'.$content.'</div>';
	}
	
	
	/**
	 * Orders admin flow handlers
	 */
	function orders_list() {
		global $Orders;
		$db = DB::get();

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
		
		$pagenum = absint( $_GET['pagenum'] );
		if ( empty($pagenum) )
			$pagenum = 1;
		if( !$per_page || $per_page < 0 )
			$per_page = 20;
		$start = ($per_page * ($pagenum-1)); 
		
		if (isset($_GET['status'])) $filter = "WHERE status='{$_GET['status']}'";
		$ordercount = $db->query("SELECT count(*) as total FROM $Purchase->_table $filter ORDER BY created DESC");
		$Orders = $db->query("SELECT * FROM $Purchase->_table $filter ORDER BY created DESC LIMIT $start,$per_page",AS_ARRAY);

		$num_pages = ceil($ordercount->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg( 'pagenum', '%#%' ),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum
		));

		include("{$this->basepath}/core/ui/orders/orders.html");
	}
	
	function order_manager () {
		global $Purchase;
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
		$db = DB::get();
		
		$purchase_table = DatabaseObject::tablename(Purchase::$table);
		$labels = $this->Settings->get('order_status');
		
		if (empty($labels)) return false;

		$r = $db->query("SELECT status,COUNT(status) AS total FROM $purchase_table GROUP BY status ORDER BY status ASC",AS_ARRAY);

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
		$db = DB::get();

		if ($_GET['deleting'] == "product"
				&& !empty($_GET['delete']) 
				&& is_array($_GET['delete'])) {
			foreach($_GET['delete'] as $deletion) {
				$Product = new Product($deletion);
				$Product->delete();
			}
		}
		
		if (empty($categories)) $categories = array('');
		
		$pagenum = absint( $_GET['pagenum'] );
		if ( empty($pagenum) )
			$pagenum = 1;
		if( !$per_page || $per_page < 0 )
			$per_page = 20;
		$start = ($per_page * ($pagenum-1)); 
		
		$pd = DatabaseObject::tablename(Product::$table);
		$pt = DatabaseObject::tablename(Price::$table);
		$cat = DatabaseObject::tablename(Category::$table);
		$clog = DatabaseObject::tablename(Catalog::$table);

		$productcount = $db->query("SELECT count(*) as total FROM $pd");
		$Products = $db->query("SELECT pd.id,pd.name,pd.featured,GROUP_CONCAT(DISTINCT cat.name ORDER BY cat.name SEPARATOR ', ') AS categories, MAX(pt.price) AS maxprice,MIN(pt.price) AS minprice FROM $pd AS pd LEFT JOIN $pt AS pt ON pd.id=pt.product AND pt.type != 'N/A' LEFT JOIN $clog AS clog ON pd.id=clog.product LEFT JOIN $cat AS cat ON cat.id=clog.category GROUP BY pd.id LIMIT $start,$per_page",AS_ARRAY);

		$num_pages = ceil($productcount->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg( 'pagenum', '%#%' ),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum
		));
		
		include("{$this->basepath}/core/ui/products/products.html");
	}
	
	
	function product_shortcode ($atts) {
		global $Shopp;
		
		if (isset($atts['name'])) {
			$Shopp->Product = new Product($atts['name'],'name');
		} elseif (isset($atts['slug'])) {
			$Shopp->Product = new Product($atts['slug'],'slug');
		} elseif (isset($atts['id'])) {
			$Shopp->Product = new Product($atts['id']);
		} else return "";
				
		ob_start();
		include(SHOPP_TEMPLATES."/related.php");
		$content = ob_get_contents();
		ob_end_clean();
		
		return '<div id="shopp">'.$content.'<div class="clear"></div></div>';
	}
	
	function category_shortcode ($atts) {
		global $Shopp;
		
		if (isset($atts['name'])) {
			$Shopp->Category = new Category($atts['name'],'name');
		} elseif (isset($atts['slug'])) {
			switch ($atts['slug']) {
				case NewProducts::$slug: $Shopp->Category = new NewProducts(); break;
				case FeaturedProducts::$slug: $Shopp->Category = new FeaturedProducts(); break;
				case OnSaleProducts::$slug: $Shopp->Category = new OnSaleProducts(); break;
				default:
					$Shopp->Category = new Category($atts['slug'],'slug');
			}
		} elseif (isset($atts['id'])) {
			$Shopp->Category = new Category($atts['id']);
		} else return "";
		
		ob_start();
		include(SHOPP_TEMPLATES."/category.php");
		$content = ob_get_contents();
		ob_end_clean();
		
		return '<div id="shopp">'.$content.'<div class="clear"></div></div>';
	}
	
		
	function product_editor() {
		global $Product;
		$db = DB::get();

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
		
		$category_table = DatabaseObject::tablename(Category::$table);
		$categories = $db->query("SELECT id,name,parent FROM $category_table ORDER BY parent,name",AS_ARRAY);
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
		$db = DB::get();

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
		
		if (!empty($_POST['images']) && is_array($_POST['images'])) {
			$Product->link_images($_POST['images']);
			$Product->save_imageorder($_POST['images']);
		}
		
		unset($Product);

		$this->products_list();
	}
	
	function product_images () {
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
		$db = DB::get();

		if ($_GET['deleting'] == "category"
				&& !empty($_GET['delete']) 
				&& is_array($_GET['delete'])) {
			foreach($_GET['delete'] as $deletion) {
				$Category = new Category($deletion);
				$db->query("UPDATE $Category->_table SET parent=0 WHERE parent=$Category->id");
				$Category->delete();
			}
		}

		$pagenum = absint( $_GET['pagenum'] );
		if ( empty($pagenum) )
			$pagenum = 1;
		if( !$per_page || $per_page < 0 )
			$per_page = 20;
		$start = ($per_page * ($pagenum-1)); 

		$table = DatabaseObject::tablename(Category::$table);
		$Catalog = new Catalog();
		$Catalog->load_categories(array($start,$per_page));
		$Categories = $Catalog->categories;

		$count = $db->query("SELECT count(*) AS total FROM $table");
		$num_pages = ceil($count->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg( 'pagenum', '%#%' ),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum
		));
		
		include("{$this->basepath}/core/ui/products/categories.html");
	}
	
	function category_editor () {
		global $Shopp;
		$db = DB::get();
		
		$Shopp->Catalog = new Catalog();
		$Shopp->Catalog->load_categories();
		
		if (empty($_POST['slug'])) $_POST['slug'] = sanitize_title_with_dashes($_POST['name']);
		else $_POST['slug'] = sanitize_title_with_dashes($_POST['slug']);
		
		// Work out pathing
		$uri = "/".$_POST['slug'];
	
		// If we're saving a new category, lookup the parent
		if ($_GET['category'] == "new") {
			for ($i = count($Shopp->Catalog->categories); $i > 0; $i--)
				if ($_POST['parent'] == $Shopp->Catalog->categories[$i]->id) break;
			$uri = "/".$Shopp->Catalog->categories[$i]->slug.$uri;
		} else {
			for ($i = count($Shopp->Catalog->categories); $i > 0; $i--)
				if ($_GET['category'] == $Shopp->Catalog->categories[$i]->id) break;
		}
		
		$parentkey = $Shopp->Catalog->categories[$i]->parentkey;
		while ($parentkey > -1) {
			$tree_category = $Shopp->Catalog->categories[$parentkey];
			$uri = "/".$tree_category->slug.$uri;
			$parentkey = $tree_category->parentkey;
		}
		
		$_POST['uri'] = $uri;

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
	
	
	function update () {
		// Backup database
		// Archive Files+DB Backup into zip and FTP to location
		// Download update file
		// Put site in maintenance mode
		// Psuedo-deactivate - use ShoppUpdate() in maintenance mode don't load Shopp()
		// 
		
	}
	
	
	/**
	 * Settings flow handlers
	 **/
	
	function settings_general () {
		$country = $_POST['settings']['base_operations']['country'];
		$countries = array();
		$countrydata = $this->Settings->get('countries');
		foreach ($countrydata as $iso => $c) {
			if ($_POST['settings']['base_operations']['country'] == $iso) 
				$base_region = $c['region'];
			$countries[$iso] = $c['name'];
		}

		if (!empty($_POST['save'])) {
			$_POST['settings']['base_operations'] = $countrydata[$_POST['settings']['base_operations']['country']];
			$_POST['settings']['base_operations']['country'] = $country;
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
		
		// $currencies = array('');
		// $currencylist = $this->Settings->get('currencies');
		// foreach($currencylist as $id => $currency) 
		// 	$currencies[$id] = $currency['name'];
		
		$statusLabels = $this->Settings->get('order_status');
		if ($statusLabels) ksort($statusLabels);
		
		include(SHOPP_ADMINPATH."/settings/settings.html");
	}


	function settings_presentation () {
		if (isset($_POST['settings']['theme_templates']) && $_POST['settings']['theme_templates'] == "on") 
			$_POST['settings']['theme_templates'] = TEMPLATEPATH."/shopp";
		if (!empty($_POST['save'])) $this->settings_save();
		
		$builtin_path = $this->basepath."/templates";
		$theme_path = TEMPLATEPATH."/shopp";
		
		// Copy templates to the current WordPress theme
		if (!empty($_POST['install'])) {
			$builtin = array_filter(scandir($builtin_path),"filter_dotfiles");
			foreach ($builtin as $template) {
				if (!file_exists($theme_path.$template))
					copy("$builtin_path/$template","$theme_path/$template");
			}
		}
		
		$status = "available";
		if (!is_dir($theme_path)) $status = "directory";
		else {
			if (!is_writable($theme_path)) $status = "permissions";
			else {
				$builtin = array_filter(scandir($builtin_path),"filter_dotfiles");
				$theme = array_filter(scandir($theme_path),"filter_dotfiles");
				if (empty($theme)) $status = "ready";
				else if (array_diff($builtin,$theme)) $status = "incomplete";
			}
		}		
		
		$sizingOptions = array(	"Scale to width",
								"Scale to height",
								"Scale to fit",
								"Scale &amp; crop");
								
		$qualityOptions = array("Highest quality, largest file size",
								"Higher quality, larger file size",
								"Balanced quality &amp; file size",
								"Lower quality, smaller file size",
								"Lowest quality, smallest file size");
		
		
		include(SHOPP_ADMINPATH."/settings/presentation.html");
	}

	function settings_catalog () {
		if (!empty($_POST['save'])) $this->settings_save();
		include(SHOPP_ADMINPATH."/settings/catalog.html");
	}

	function settings_cart () {
		if (!empty($_POST['save'])) $this->settings_save();
		include(SHOPP_ADMINPATH."/settings/cart.html");
	}

	function settings_checkout () {
		if (!empty($_POST['save'])) $this->settings_save();
		
		$downloads = array("1","2","3","5","10","15","25","100");
		$time = array("30 minutes","1 hour","2 hours","3 hours","6 hours","12 hours","1 day","3 days","1 week","1 month","3 months","6 months","1 year");
								
		include(SHOPP_ADMINPATH."/settings/checkout.html");
	}

	function settings_shipping () {
		global $Shopp;
		
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

		$methods = $Shopp->ShipCalcs->methods;

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
		
		include(SHOPP_ADMINPATH."/settings/shipping.html");
	}

	function settings_taxes () {
		if (!empty($_POST['save'])) $this->settings_save();
		
		$rates = $this->Settings->get('taxrates');
		$base = $this->Settings->get('base_operations');
		$countries = $this->Settings->get('target_markets');
		$zones = $this->Settings->get('zones');
		
		include(SHOPP_ADMINPATH."/settings/taxes.html");
	}	

	function settings_payments () {
		if (!empty($_POST['save'])) $this->settings_save();
		
		$data = $this->settings_get_gateways();
		
		$gateways = array();
		$Processors = array();
		foreach ($data as $gateway) {
			// Treat PayPal Express and Google Checkout differently
			if ($gateway->name == "PayPal Express" || 
				$gateway->name == "Google Checkout") continue;
				
			$gateways[$gateway->file] = $gateway->name;
			$ProcessorClass = $gateway->tags['class'];
			include($gateway->file);
			$Processors[] = new $ProcessorClass();
		}
		
		include("{$this->basepath}/gateways/PayPal/PayPalExpress.php");
		$PayPalExpress = new PayPalExpress();
		
		include(SHOPP_ADMINPATH."/settings/payments.html");
	}
	
	function settings_update () {
		if (!empty($_POST['save'])) $this->settings_save();
		
		
		include(SHOPP_ADMINPATH."/settings/update.html");
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
	 * Installation, upgrade and initialization functions
	 */
	
	function upgrade () {
		$db = DB::get();
		require_once(ABSPATH.'wp-admin/includes/upgrade.php');
		
		// Check for the schema definition file
		if (!file_exists(SHOPP_DBSCHEMA)) {
		 	trigger_error("Could not upgrade the Shopp database tables because the table definitions file is missing: ".SHOPP_DBSCHEMA);
			exit();
		}
		
		// Update the table schema
		$tables = preg_replace('/;\s+/',';',file_get_contents(SHOPP_DBSCHEMA));
		$updates = dbDelta($tables);
		
		// Update the version number
		$settings = DatabaseObject::tablename(Settings::$table);
		$db->query("UPDATE $settings SET value='".SHOPP_VERSION." WHERE name='version'");
	}

	/**
	 * setup()
	 * Initialize default install settings and lists */
	function setup () {
		
		$this->setup_regions();
		$this->setup_countries();
		$this->setup_zones();
		$this->setup_areas();
		$this->setup_currencies();
		
		// General Settings
		$this->Settings->save('version',SHOPP_VERSION);
		$this->Settings->save('shipping','on');	
		$this->Settings->save('order_status',array('Pending','Completed'));	
		$this->Settings->save('shopp_setup','completed');

		// Presentation Settings
		$this->Settings->save('gallery_small_width','240');
		$this->Settings->save('gallery_small_height','240');
		$this->Settings->save('gallery_small_sizing','3');
		$this->Settings->save('gallery_small_quality','0');
		$this->Settings->save('gallery_thumbnail_width','96');
		$this->Settings->save('gallery_thumbnail_height','96');
		$this->Settings->save('gallery_thumbnail_sizing','3');
		$this->Settings->save('gallery_thumbnail_quality','0');

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