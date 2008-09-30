<?php
/**
 * Flow handlers
 * Main flow handling for all request processing/handling
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 2 April, 2008
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
		$this->Admin->orders = $Core->directory."/orders";
		$this->Admin->settings = $Core->directory."/settings";
		$this->Admin->products = $Core->directory."/products";
		$this->Admin->promotions = $Core->directory."/promotions";
		$this->Admin->help = $Core->directory."/help";
		$this->Admin->default = $this->Admin->orders;
		
		$this->Pages = $Core->Settings->get('pages');
		if (empty($this->Pages)) {
			$this->Pages = array();
			$this->Pages['catalog'] = array('name'=>'shop','title'=>'Shop','content'=>'[catalog]');
			$this->Pages['cart'] = array('name'=>'cart','title'=>'Cart','content'=>'[cart]');
			$this->Pages['checkout'] = array('name'=>'checkout','title'=>'Checkout','content'=>'[checkout]');
			$this->Pages['account'] = array('name'=>'account','title'=>'Your Orders','content'=>'[account]');
		}
		
		define("SHOPP_PATH",$this->basepath);
		define("SHOPP_ADMINPATH",$this->basepath."/core/ui");
		define("SHOPP_PLUGINURI",$Core->uri);
		define("SHOPP_DBSCHEMA",$this->basepath."/core/model/schema.sql");

		define("SHOPP_TEMPLATES",($Core->Settings->get('theme_templates') != "off" && 
		 							is_dir($Core->Settings->get('theme_templates')))?
									$Core->Settings->get('theme_templates'):
									$this->basepath."/templates");
		define("SHOPP_TEMPLATES_URI",($Core->Settings->get('theme_templates') != "off" && 
			 							is_dir($Core->Settings->get('theme_templates')))?
										get_bloginfo('stylesheet_directory')."/shopp":
										$Core->uri."/templates");

		define("SHOPP_PERMALINKS",(get_option('permalink_structure') == "")?false:true);

		define("SHOPP_LOOKUP",(strpos($_SERVER['REQUEST_URI'],"images/") !== false || 
								strpos($_SERVER['REQUEST_URI'],"lookup=") !== false)?true:false);
	}

	/**
	 * Catalog flow handlers
	 **/
	function catalog () {
		global $Shopp;

		ob_start();
		switch ($Shopp->Catalog->type) {
			case "product": include(SHOPP_TEMPLATES."/product.php"); break;
			case "category": include(SHOPP_TEMPLATES."/category.php"); break;
			default: include(SHOPP_TEMPLATES."/catalog.php"); break;
		}
		$content = ob_get_contents();
		ob_end_clean();

		return '<div id="shopp" class="'.$Shopp->Catalog->type.'">'.$content.'<div id="clear"></div></div>';
		
	}

	function catalog_css () {
		
		ob_start();
		include("{$this->basepath}/core/ui/styles/catalog.css");
		$stylesheet = ob_get_contents();
		ob_end_clean();
		return $stylesheet;
		
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
	
	function cart_request () {
		global $Shopp;
		$Cart = $Shopp->Cart;
		// print_r($Cart->data->Promotions);

		$Request = array();
		if (!empty($_GET['cart'])) $Request = $_GET;
		if (!empty($_POST['cart'])) $Request = $_POST;

		if (isset($Request['checkout'])) {
			$pages = $this->Pages;
			header("Location: ".$Shopp->link('checkout',true));
			exit();
		}
		
		if (isset($Request['shopping'])) {
			$pages = $this->Pages;
			header("Location: ".$Shopp->link('catalog'));
			exit();
		}
		
		if (isset($Request['shipping'])) {
			$countries = $Shopp->Settings->get('countries');
			$regions = $Shopp->Settings->get('regions');
			$Request['shipping']['region'] = $regions[$countries[$Request['shipping']['country']]['region']];
			unset($countries,$regions);
			$Cart->shipzone($Request['shipping']);
		} else {
			$base = $Shopp->Settings->get('base_operations');
			$Request['shipping']['country'] = $base['country'];
			$Cart->shipzone($Request['shipping']);
		}

		if (isset($Request['apply-code']) && !empty($Request['promocode'])) {
			if (!in_array($Request['promocode'],$Cart->data->PromoCodes)) {
				$Cart->data->PromoCode = $Request['promocode'];
				$Cart->data->PromoCodes[] = $Request['promocode'];
				$Request['update'] = true;
			}
		}
		
		if (isset($Request['remove'])) $Request['cart'] = "remove";
		if (isset($Request['update'])) $Request['cart'] = "update";
		if (isset($Request['empty'])) $Request['cart'] = "empty";
		
		switch($Request['cart']) {
			case "add":
				if (isset($Request['product']) && (isset($Request['price']) || isset($Request['options']))) {
					$quantity = (!empty($Request['quantity']))?$Request['quantity']:1; // Add 1 by default

					$Product = new Product($Request['product']);
					if (!empty($Request['options'])) $pricing = $Request['options'];
					else $pricing = $Request['price'];
					
					if (isset($Request['item'])) $result = $Cart->change($Request['item'],$Product,$pricing);
					else $result = $Cart->add($quantity,$Product,$pricing);
				}
				break;
			case "remove":
				if (!empty($Cart->contents)) $Cart->remove($Request['remove']);
				break;
			case "empty":
				$Cart->clear();
				break;
			case "update":			
				if (!empty($Request['item']) && isset($Request['quantity'])) {
					$Cart->update($Request['item'],$Request['quantity']);
					
				} elseif (!empty($Request['items'])) {
					foreach ($Request['items'] as $id => $item) {
						if (isset($item['quantity'])) $Cart->update($id,$item['quantity']);	
						if (isset($item['product']) && isset($item['price']) && 
							$item['product'] == $Cart->contents[$id]->product &&
							$item['price'] != $Cart->contents[$id]->price) {
							$Product = new Product($item['product']);
							$Cart->change($id,$Product,$item['price']);
						}
					}
				}
			
				break;
		}
					
	}

	function cart_ajax () {
		$this->cart_request();
		$cart = array();
		$cart['contents'] = $this->Cart->contents;
		$cart['totals'] = $this->Cart->data->Totals;
		echo json_encode($cart);
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
		include(SHOPP_TEMPLATES."/shipping.php");
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
	
	function secure_checkout_link ($linklist) {
		global $Shopp;
		$gateway = $Shopp->Settings->get('payment_gateway');
		if (strpos($gateway,"TestMode.php") !== false) return $linklist;
		$cart_href = $Shopp->link('cart');
		$checkout_href = $Shopp->link('checkout');
		if (empty($gateway)) return str_replace($checkout_href,$cart_href,$linklist);
		$secured_href = str_replace("http://","https://",$checkout_href);
		return str_replace($checkout_href,$secured_href,$linklist);
	}
	
	/**
	 * order()
	 * Processes orders by passing transaction information to the active
	 * payment gateway */
	function order ($gateway = false) {
		global $Shopp;
		$Cart = $Shopp->Cart;
		$db = DB::get();
		
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
			$authentication = $Shopp->Settings->get('account_system');

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

			// Create WordPress account (if necessary)
			if ($authentication == "wordpress" && 
				!$user = get_user_by_email($Order->Customer->email)) {
				require_once(ABSPATH."/wp-includes/registration.php");
				
				list($handle,$domain) = split("@",$Order->Customer->email);

				// The email handle exists, so use first name initial + lastname
				if (username_exists($handle)) 
					$handle = substr($Order->Customer->firstname,0,1).$Order->Customer->lastname;
				
				// That exists too *bangs head on wall*, ok add a random number too :P
				if (username_exists($handle)) 
					$handle .= rand(1000,9999);
				
				// Create the WordPress account
				$wpuser = wp_insert_user(array(
					'user_login' => $handle,
					'user_pass' => $Order->Customer->password,
					'user_email' => $Order->Customer->email,
					'display_name' => $Order->Customer->firstname.' '.$Order->Customer->lastname,
					'nickname' => $handle,
					'first_name' => $Order->Customer->firstname,
					'last_name' => $Order->Customer->lastname
				));
				
				// Keep record of it in Shopp's customer records
				$Order->Customer->wpuser = $wpuser;
			}

			// Create a WP-compatible password hash to go in the db
			$Order->Customer->password = wp_hash_password($Order->Customer->password);
			$Order->Customer->save();

			$Order->Billing->customer = $Order->Customer->id;
			$Order->Billing->card = substr($Order->Billing->card,-4);
			$Order->Billing->save();

			if (!empty($Order->Shipping->address)) {
				$Order->Shipping->customer = $Order->Customer->id;
				$Order->Shipping->save();
			}

			$Purchase = new Purchase();
			$Purchase->customer = $Order->Customer->id;
			$Purchase->billing = $Order->Billing->id;
			$Purchase->shipping = $Order->Shipping->id;
			$Purchase->data = $Order->data;
			$Purchase->copydata($Order->Customer);
			$Purchase->copydata($Order->Billing);
			$Purchase->copydata($Order->Shipping,'ship');
			$Purchase->copydata($Shopp->Cart->data->Totals);
			$Purchase->freight = $Shopp->Cart->data->Totals->shipping;
			$Purchase->gateway = $processor_data->name;
			$Purchase->transactionid = $Payment->transactionid();
			$Purchase->transtatus = "CHARGED";
			$Purchase->save();
			// echo "<pre>"; print_r($Purchase); echo "</pre>";

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
		// $Shopp->Cart->save();

		// Send the e-mail receipt
		$receipt = array();
		$receipt['from'] = '"'.get_bloginfo("name").'"';
		if ($Shopp->Settings->get('merchant_email')) 
			$receipt['from'] .= ' <'.$Shopp->Settings->get('merchant_email').'>';
		$receipt['to'] = "\"{$Purchase->firstname} {$Purchase->lastname}\" <{$Purchase->email}>";
		$receipt['subject'] = __('Order Receipt','Shopp');
		$receipt['receipt'] = $this->order_receipt();
		$receipt['url'] = get_bloginfo('siteurl');
		$receipt['sitename'] = get_bloginfo('name');
		// echo "<PRE>"; print_r($receipt); echo "</PRE>";
		shopp_email(SHOPP_TEMPLATES."/order.html",$receipt);
		
		if ($Shopp->Settings->get('receipt_copy') == 1) {
			$receipt['to'] = $Shopp->Settings->get('shopowner_email');
			$receipt['subject'] = "New Order";
			shopp_email(SHOPP_TEMPLATES."/email.html",$receipt);
		}

		// Test Mode will not require encrypted checkout
		if (strpos($gateway,"TestMode.php") !== false) $link = $Shopp->link('receipt');
		else $link = $Shopp->link('receipt',true);
		header("Location: $link");
		exit();
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

	function login ($email,$password) {
		global $Shopp;
		$Cart = $Shopp->Cart;
		$db = DB::get();
		$authentication = $Shopp->Settings->get('account_system');
		
		switch($authentication) {
			case "shopp":
				$Account = new Customer($email,'email');

				if (empty($Account)) {
					$Cart->data->OrderError->message = "No customer account was found with that email.";
					return false;
				} 

				if (!wp_check_password($password,$Account->password)) {
					$Cart->data->OrderError->message = "The password is incorrect.";
					return false;
				}			
				break;
				
			case "wordpress":
				global $wpdb;
				$Account = new Customer($email,'email');
				if ( !$user = get_user_by_email($Account->email)) {
					$Cart->data->OrderError->message = "No customer account was found with that email.";
					return false;
				}
				
				if (!wp_check_password($password,$user->user_pass)) {
					$Cart->data->OrderError->message = "The password is incorrect.";
					return false;
				}

				break;
			default: return false; break;
		}
		
		// Login successful
		$Cart->data->login = true;
		$Account->password = "";
		$Cart->data->Order->Customer = $Account;
		$Cart->data->Order->Billing = new Billing($Account->id);
		$Cart->data->Order->Billing->card = "";
		$Cart->data->Order->Billing->cardexpires = "";
		$Cart->data->Order->Billing->cardholder = "";
		$Cart->data->Order->Billing->cardtype = "";
		$Cart->data->Order->Shipping = new Shipping($Account->id);

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

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

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

		include("{$this->basepath}/core/ui/orders/orders.php");
	}
	
	function order_manager () {
		global $Shopp;

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));
				
		if (preg_match("/\d+/",$_GET['manage'])) {
			$Purchase = new Purchase($_GET['manage']);
			$Purchase->load_purchased();
		} else $Purchase = new Purchase();

		if (empty($Shopp->Cart->data->Purchase)) 
			$Shopp->Cart->data->Purchase = $Purchase;

		if (!empty($_POST)) {
			check_admin_referer('shopp-save-order');
			$Purchase->updates($_POST);
			if ($_POST['notify'] == "yes") {
				$labels = $this->Settings->get('order_status');
				
				// Send the e-mail notification
				$notification = array();
				$notification['from'] = $Shopp->Settings->get('merchant_email');
				$notification['to'] = "\"{$Purchase->firstname} {$Purchase->lastname}\" <{$Purchase->email}>";
				$notification['subject'] = "Order Updated";
				$notification['url'] = get_bloginfo('siteurl');
				$notification['sitename'] = get_bloginfo('name');

				if ($_POST['receipt'] == "yes")
					$notification['receipt'] = $this->order_receipt();
				
				$notification['status'] = strtoupper($labels[$Purchase->status]);
				$notification['message'] = wpautop($_POST['message']);

				shopp_email(SHOPP_TEMPLATES."/notification.html",$notification);
				
			}
			
			
			$Purchase->save();
			$updated = 'Order status updated.';
		}

		$statusLabels = $this->Settings->get('order_status');
		if (empty($statusLabels)) $statusLabels = array('');
		
		include("{$this->basepath}/core/ui/orders/order.php");
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
		include(SHOPP_ADMINPATH."/orders/account.php");
		$content = ob_get_contents();
		ob_end_clean();
		return '<div id="shopp">'.$content.'</div>';
	}
	
	/**
	 * Products admin flow handlers
	 **/
	function products_list() {
		global $Products;
		$db = DB::get();

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if ($_GET['deleting'] == "product"
				&& !empty($_GET['delete']) 
				&& is_array($_GET['delete'])) {
			foreach($_GET['delete'] as $deletion) {
				$Product = new Product($deletion);
				$Product->delete();
			}
		}
		
		if (empty($categories)) $categories = array('');
		
		$category_table = DatabaseObject::tablename(Category::$table);
		$categories = $db->query("SELECT id,name,parent FROM $category_table ORDER BY parent,name",AS_ARRAY);
		$categories = sort_tree($categories);
		if (empty($categories)) $categories = array();
		
		$categories_menu = '<option value="">View all categories</option>';
		foreach ($categories as $category) {
			$padding = str_repeat("&nbsp;",$category->depth*3);
			if ($_GET['cat'] == $category->id) $categories_menu .= '<option value="'.$category->id.'" selected="selected">'.$padding.$category->name.'</option>';
			else $categories_menu .= '<option value="'.$category->id.'">'.$padding.$category->name.'</option>';
		}
		
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
		
		$where = "";
		if (!empty($_GET['cat'])) $where = " WHERE cat.id='{$_GET['cat']}'";

		$productcount = $db->query("SELECT count(*) as total FROM $pd");
		$Products = $db->query("SELECT pd.id,pd.name,pd.featured,GROUP_CONCAT(DISTINCT cat.name ORDER BY cat.name SEPARATOR ', ') AS categories, MAX(pt.price) AS maxprice,MIN(pt.price) AS minprice FROM $pd AS pd LEFT JOIN $pt AS pt ON pd.id=pt.product AND pt.type != 'N/A' LEFT JOIN $clog AS clog ON pd.id=clog.product LEFT JOIN $cat AS cat ON cat.id=clog.category $where GROUP BY pd.id LIMIT $start,$per_page",AS_ARRAY);

		$num_pages = ceil($productcount->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg( 'pagenum', '%#%' ),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum
		));
		
		include("{$this->basepath}/core/ui/products/products.php");
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
				case SearchResults::$slug: 
					$Shopp->Category = new SearchResults(array('search'=>$atts['search'])); break;
				case BestsellerProducts::$slug: $Shopp->Category = new BestsellerProducts(); break;
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
	
	function maintenance_shortcode ($atts) {
		return '<div id="shopp" class="update"><p>The store is currently down for maintenance.  We\'ll be back soon!</p><div class="clear"></div></div>';
	}
		
	function product_editor() {
		global $Product;
		$db = DB::get();

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

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

		include("{$this->basepath}/core/ui/products/editor.php");

	}

	function save_product($Product) {
		$db = DB::get();
		check_admin_referer('shopp-save-product');

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

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
				if (!empty($option['download'])) $Price->attach_download($option['download']);
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
				// case "0": $SmallSizing->scaleToWidth($SmallSettings['width']); break;
				// case "1": $SmallSizing->scaleToHeight($SmallSettings['height']); break;
				case "0": $SmallSizing->scaleToFit($SmallSettings['width'],$SmallSettings['height']); break;
				case "1": $SmallSizing->scaleCrop($SmallSettings['width'],$SmallSettings['height']); break;
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

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

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
		
		include("{$this->basepath}/core/ui/products/categories.php");
	}
	
	function category_editor () {
		global $Shopp;
		$db = DB::get();
		
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$Shopp->Catalog = new Catalog();
		$Shopp->Catalog->load_categories();
		
		if (empty($_POST['slug'])) $_POST['slug'] = sanitize_title_with_dashes($_POST['name']);
		else $_POST['slug'] = sanitize_title_with_dashes($_POST['slug']);
		
		// Work out pathing
		$paths = array();
		if (!empty($_POST['slug'])) $paths = array($_POST['slug']);
		$uri = "/".$_POST['slug'];
	
		// If we're saving a new category, lookup the parent
		if ($_GET['category'] == "new") {
			for ($i = count($Shopp->Catalog->categories); $i > 0; $i--)
				if ($_POST['parent'] == $Shopp->Catalog->categories[$i]->id) break;
			$paths = array_push($Shopp->Catalog->categories[$i]->slug,$paths);
			$uri = "/".$Shopp->Catalog->categories[$i]->slug.$uri;
		}
		
		$parentkey = $Shopp->Catalog->categories[$i]->parentkey;
		while ($parentkey > -1) {
			$tree_category = $Shopp->Catalog->categories[$parentkey];
			array_unshift($paths,$tree_category->slug);
			$uri = "/".$tree_category->slug.$uri;
			$parentkey = $tree_category->parentkey;
		}

		$_POST['uri'] = join("/",$paths);

		if ($_GET['category'] != "new") {
			$Category = new Category($_GET['category']);
		} else $Category = new Category();
		
		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-save-category');
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

		include("{$this->basepath}/core/ui/products/category.php");
	}	
	
	function promotions_list () {
		$db = DB::get();

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		require_once("{$this->basepath}/core/model/Promotion.php");
		
		if ($_GET['deleting'] == "promotion"
				&& !empty($_GET['delete']) 
				&& is_array($_GET['delete'])) {
			foreach($_GET['delete'] as $deletion) {
				$Promotion = new Promotion($deletion);
				$Promotion->delete();
			}
		}
		
		$pagenum = absint( $_GET['pagenum'] );
		if ( empty($pagenum) )
			$pagenum = 1;
		if( !$per_page || $per_page < 0 )
			$per_page = 20;
		$start = ($per_page * ($pagenum-1)); 
		
		$table = DatabaseObject::tablename(Promotion::$table);
		$Promotions = $db->query("SELECT * FROM $table",AS_ARRAY);
		include("{$this->basepath}/core/ui/promotions/promotions.php");
	}
	
	function promotion_editor () {

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		require_once("{$this->basepath}/core/model/Promotion.php");

		if ($_GET['promotion'] != "new") {
			$Promotion = new Promotion($_GET['promotion']);
		} else $Promotion = new Promotion();
		
		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-save-promotion');
			
			if (!empty($_POST['starts']['month']) && !empty($_POST['starts']['date']) && !empty($_POST['starts']['year']))
				$_POST['starts'] = mktime(0,0,0,$_POST['starts']['month'],$_POST['starts']['date'],$_POST['starts']['year']);
			else $_POST['starts'] = 1;
			
			if (!empty($_POST['ends']['month']) && !empty($_POST['ends']['date']) && !empty($_POST['ends']['year']))
				$_POST['ends'] = mktime(0,0,0,$_POST['ends']['month'],$_POST['ends']['date'],$_POST['ends']['year']);
			else $_POST['ends'] = 1;
			
			$Promotion->updates($_POST);
			
			$Promotion->save();

			if ($Promotion->scope == "Item")
				$Promotion->build_discounts();
			
			$this->promotions_list();
			return true;
		}
		
		include("{$this->basepath}/core/ui/promotions/editor.php");
	}
	
	/**
	 * Dashboard Widgets
	 */
	function dashboard_stats ($args) {
		$db = DB::get();
		extract( $args, EXTR_SKIP );

		echo $before_widget;

		echo $before_title;
		echo $widget_name;
		echo $after_title;
		
		$purchasetable = DatabaseObject::tablename(Purchased::$table);
		
		$results = $db->query("SELECT count(id) AS orders, SUM(total) AS sales, AVG(total) AS average,
		 						SUM(IF(UNIX_TIMESTAMP(created) > UNIX_TIMESTAMP()-(86400*30),1,0)) AS wkorders,
								SUM(IF(UNIX_TIMESTAMP(created) > UNIX_TIMESTAMP()-(86400*30),total,0)) AS wksales,
								AVG(IF(UNIX_TIMESTAMP(created) > UNIX_TIMESTAMP()-(86400*30),total,null)) AS wkavg
		 						FROM $purchasetable");

		echo '<h3 class="reallynow">Last 30 Days</h3>';
		echo '<ul>';
		echo "<li><strong>Orders:</strong> $results->wkorders</li>";
		echo "<li><strong>Sales:</strong> ".money($results->wksales)."</li>";
		echo "<li><strong>Order Average:</strong> ".money($results->wkavg)."</li>";
		echo '</ul>';
		
		echo '<h3>Lifetime</h3>';
		echo '<ul>';
		echo "<li><strong>Orders:</strong> $results->orders</li>";
		echo "<li><strong>Sales:</strong> ".money($results->sales)."</li>";
		echo "<li><strong>Order Average:</strong> ".money($results->average)."</li>";
		echo '</ul>';

		echo $after_widget;
		
	}
	
	function dashboard_orders ($args) {
		$db = DB::get();
		extract( $args, EXTR_SKIP );
		$statusLabels = $this->Settings->get('order_status');
		
		echo $before_widget;

		echo $before_title;
		echo $widget_name;
		echo $after_title;
		
		$purchasetable = DatabaseObject::tablename(Purchase::$table);
		$purchasedtable = DatabaseObject::tablename(Purchased::$table);
		
		$Orders = $db->query("SELECT p.*,count(i.id) as items FROM $purchasetable AS p LEFT JOIN $purchasedtable AS i ON i.purchase=p.id GROUP BY i.purchase ORDER BY created DESC LIMIT 6",AS_ARRAY);

		if (!empty($Orders)) {
		echo '<table class="widefat">';
		echo '<tr><th scope="col">Name</th><th scope="col">Date</th><th scope="col" class="num">Items</th><th scope="col" class="num">Total</th><th scope="col" class="num">Status</th></tr>';
		echo '<tbody id="orders" class="list orders">';
		$even = false; 
		foreach ($Orders as $Order) {
			echo '<tr'.((!$even)?' class="alternate"':'').'>';
			$even = !$even;
			echo '<td><a class="row-title" href="admin.php?page='.$this->Admin->default.'&amp;manage='.$Order->id.'" title="View &quot;Order '.$Order->id.'&quot;">'.((empty($Order->firstname) && empty($Order->lastname))?'(no contact name)':$Order->firstname.' '.$Order->lastname).'</a></td>';
			echo '<td>'.date("Y/m/d",mktimestamp($Order->created)).'</td>';
			echo '<td class="num">'.$Order->items.'</td>';
			echo '<td class="num">'.money($Order->total).'</td>';
			echo '<td class="num">'.$statusLabels[$Order->status].'</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		} else {
			echo '<p>No orders, yet.</p>';
		}

		echo $after_widget;
		
	}
	
	function dashboard_products ($args) {
		$db = DB::get();
		extract( $args, EXTR_SKIP );

		echo $before_widget;

		echo $before_title;
		echo $widget_name;
		echo $after_title;

		$RecentBestsellers = new BestsellerProducts(array('where'=>'UNIX_TIMESTAMP(pur.created) > UNIX_TIMESTAMP()-(86400*30)','show'=>3));
		echo '<h3>Recent Bestsellers</h3>';
		echo '<ul>';
		foreach ($RecentBestsellers->products as $product) 
			echo '<li><a href="admin.php?page='.$this->Admin->products.'&edit='.$product->id.'">'.$product->name.'</a></li>';
		echo '</ul>';
		

		$LifetimeBestsellers = new BestsellerProducts(array('show'=>3));
		echo '<h3>Lifetime Bestsellers</h3>';
		echo '<ul>';
		foreach ($LifetimeBestsellers->products as $product) 
			echo '<li><a href="admin.php?page='.$this->Admin->products.'&edit='.$product->id.'">'.$product->name.'</a></li>';
		echo '</ul>';

		echo $after_widget;
		
	}
	
	
	
	/**
	 * Settings flow handlers
	 **/
	
	function settings_general () {
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$country = $_POST['settings']['base_operations']['country'];
		$countries = array();
		$countrydata = $this->Settings->get('countries');
		foreach ($countrydata as $iso => $c) {
			if ($_POST['settings']['base_operations']['country'] == $iso) 
				$base_region = $c['region'];
			$countries[$iso] = $c['name'];
		}

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-general');
			$zone = $_POST['settings']['base_operations']['zone'];
			$_POST['settings']['base_operations'] = $countrydata[$_POST['settings']['base_operations']['country']];
			$_POST['settings']['base_operations']['country'] = $country;
			$_POST['settings']['base_operations']['zone'] = $zone;
			$_POST['settings']['base_operations']['currency']['format'] = 
				scan_money_format($_POST['settings']['base_operations']['currency']['format']);
			$this->settings_save();
			$updated = 'Shopp settings saved.';
		}

		$operations = $this->Settings->get('base_operations');
		if (!empty($operations['zone'])) {
			$zones = $this->Settings->get('zones');
			$zones = $zones[$operations['country']];
		}
		$targets = $this->Settings->get('target_markets');
		if (!$targets) $targets = array();
		
		$statusLabels = $this->Settings->get('order_status');
		include(SHOPP_ADMINPATH."/settings/settings.php");
	}
	
	function settings_presentation () {
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if (isset($_POST['settings']['theme_templates']) && $_POST['settings']['theme_templates'] == "on") 
			$_POST['settings']['theme_templates'] = TEMPLATEPATH."/shopp";
		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-presentation');
			$this->settings_save();
			$updated = 'Shopp presentation settings saved.';
		}
		
		$builtin_path = $this->basepath."/templates";
		$theme_path = TEMPLATEPATH."/shopp";
		
		// Copy templates to the current WordPress theme
		if (!empty($_POST['install'])) {
			check_admin_referer('shopp-settings-presentation');
			$builtin = array_filter(scandir($builtin_path),"filter_dotfiles");
			foreach ($builtin as $template) {
				if (!file_exists($theme_path.$template)) {
					copy("$builtin_path/$template","$theme_path/$template");
					chmod("$theme_path/$template",0666);
				}
					
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
		
		$sizingOptions = array(	"Scale to fit",
								"Scale &amp; crop");
								
		$qualityOptions = array("Highest quality, largest file size",
								"Higher quality, larger file size",
								"Balanced quality &amp; file size",
								"Lower quality, smaller file size",
								"Lowest quality, smallest file size");
		
		
		include(SHOPP_ADMINPATH."/settings/presentation.php");
	}

	function settings_catalog () {
		// check_admin_referer('shopp-settings-catalog');
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if (!empty($_POST['save'])) $this->settings_save();
		include(SHOPP_ADMINPATH."/settings/catalog.php");
	}

	function settings_cart () {
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if (!empty($_POST['save'])) $this->settings_save();
		include(SHOPP_ADMINPATH."/settings/cart.php");
	}

	function settings_checkout () {
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-checkout');
			$this->settings_save();
			$updated = 'Shopp checkout settings saved.';
		}
		
		$downloads = array("1","2","3","5","10","15","25","100");
		$time = array("30 minutes","1 hour","2 hours","3 hours","6 hours","12 hours","1 day","3 days","1 week","1 month","3 months","6 months","1 year");
								
		include(SHOPP_ADMINPATH."/settings/checkout.php");
	}

	function settings_shipping () {
		global $Shopp;
		
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));
		
		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-shipping');
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
			$updated = 'Shopp shipping settings saved.';
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
		
		include(SHOPP_ADMINPATH."/settings/shipping.php");
	}

	function settings_taxes () {
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-taxes');
			$this->settings_save();
			$updated = 'Shopp taxes settings saved.';
		}
		
		$rates = $this->Settings->get('taxrates');
		$base = $this->Settings->get('base_operations');
		$countries = $this->Settings->get('target_markets');
		$zones = $this->Settings->get('zones');
		
		include(SHOPP_ADMINPATH."/settings/taxes.php");
	}	

	function settings_payments () {
		global $Shopp;

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		include("{$this->basepath}/gateways/PayPal/PayPalExpress.php");
		$PayPalExpress = new PayPalExpress();
		include("{$this->basepath}/gateways/GoogleCheckout/GoogleCheckout.php");
		$GoogleCheckout = new GoogleCheckout();

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-payments');
			if (!empty($_POST['settings']['GoogleCheckout']['id']) && !empty($_POST['settings']['GoogleCheckout']['key'])) {
				$url = $Shopp->link('catalog',true);
				$url .= "?shopp_xorder=GoogleCheckout";
				$url .= "&merc=".$GoogleCheckout->authcode(
										$_POST['settings']['GoogleCheckout']['id'],
										$_POST['settings']['GoogleCheckout']['key']);
				$_POST['settings']['GoogleCheckout']['apiurl'] = $url;
			}
			
			$this->settings_save();
			$updated = 'Shopp payments settings saved.';
		}
		
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
		
		
		include(SHOPP_ADMINPATH."/settings/payments.php");
	}
	
	function settings_update () {
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-update');
			$this->settings_save();
		}
		if (!empty($_POST['activation'])) {
			$this->settings_save();	
			check_admin_referer('shopp-settings-update');
			
			if ($_POST['activation'] == "Activate Key") $process = "activate-key";
			else $process = "deactivate-key";
			
			$request = array(
				"ShoppServerRequest" => $process,
				"v" => SHOPP_VERSION,
				"key" => $_POST['settings']['update_key'],
				"site" => get_bloginfo('siteurl')
			);
			
			$activation = $this->callhome($request);
			
			if ($activation != "1")
				$activation = '<span class="shopp error">'.$activation.'</span>';
			
			if ($process == "activate-key" && $activation == "1") {
				$this->Settings->save('updatekey_status','activated');
				$activation = "This key has been successfully activated.";
			}
			
			if ($process == "deactivate-key" && $activation == "1") {
				$this->Settings->save('updatekey_status','deactivated');
				$activation = "This key has been successfully de-activated.";
			}
		} else {
			if ($this->Settings->get('updatekey_status') == "activated") 
				$activation = "This key has been successfully activated.";
			else $activation = "Enter your Shopp upgrade key and activate it to enable easy, automatic upgrades.";
		}
		
		include(SHOPP_ADMINPATH."/settings/update.php");
	}
	
	function settings_ftp () {
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-ftp');
			$this->settings_save();
		}
		
		$credentials = $this->Settings->get('ftp_credentials');
		
		include(SHOPP_ADMINPATH."/settings/ftp.php");
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
		foreach ($_POST['settings'] as $setting => $value)
			$this->Settings->save($setting,$value);
	}
	
	/**
	 * Installation, upgrade and initialization functions
	 */
	
	function update () {
		global $Shopp;
		$db = DB::get();
		
		$log = array();
		
		$credentials = $this->Settings->get('ftp_credentials');
		if (empty($credentials)) {
			// Try to load from WordPress settings
			$credentials = get_option('ftp_credentials');
			if (!$credentials) $credentials = array();
		}
		
		// Make sure we can connect to FTP
		$ftp = new FTPClient($credentials['hostname'],$credentials['username'],$credentials['password']);
		if (!$ftp->connected) die("ftp-failed");
		else $log[] = "Connected with FTP successfully.";
		
		// Get zip functions from WP Admin
		if (class_exists('PclZip')) $log[] = "ZIP library available.";
		else {
			require_once(ABSPATH.'wp-admin/includes/class-pclzip.php');
			$log[] = "ZIP library loaded.";
		}
		
		// Put site in maintenance mode
		$this->Settings->save("maintenance","on");
		$log[] = "Enabled maintenance mode.";
		
		// $tablelist = array();
		// $results = $db->query("SHOW TABLES LIKE '".SHOPP_DBPREFIX."%'",AS_ARRAY);
		// foreach ($results as $value) {
		// 	foreach ($value as $key => $table)
		// 		$tablelist[] = $table;
		// }
		// $tables = join(" ",$tablelist);
		
		// Backups
		$tmpdir = sys_get_temp_dir();
		$log[] = "Found temp directory: $tmpdir";
		
		// Backup database
		// $dbBackup = SHOPP_DBPREFIX.DB_NAME."-db-".date("YmdHi");
		// $command = "mysqldump --opt -h ".DB_HOST." -u".DB_USER." -p".DB_PASSWORD." ".DB_NAME." $tables > $tmpdir$dbBackup.sql";
		// exec($command);
		// 
		// if (file_exists($tmpdir.$dbBackup.".sql")) {
		// 	$dbarchive = new PclZip($tmpdir.$dbBackup.'.zip');
		// 	$dbarchive->create($tmpdir.$dbBackup.'.sql');
		// }
		
		// Backup files
		// $filesBackup = SHOPP_DBPREFIX.SHOPP_VERSION."-".date("YmdHi").'.zip';
		// $filesArchive = new PclZip($tmpdir.$filesBackup);
		// $filesArchive->create(basename($Shopp->path));

		// Download the new version of Shopp
		$updatefile = tempnam($tmpdir,"shopp_update_");
		if (($download = fopen($updatefile, 'wb')) === false) 
			die(join("\n\n",$log)."\n\nUpdate Failed: Cannot save the Shopp update to the temporary workspace because of a write permission error.");
		
		$query = build_query_request(array(
			"ShoppServerRequest" => "download-update",
			"v" => SHOPP_VERSION,
			"key" => $this->Settings->get('update_key'),
			"site" => get_bloginfo('siteurl')
		));

		$connection = curl_init();
		curl_setopt($connection, CURLOPT_URL, SHOPP_HOME."?".$query); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_HEADER, 0); 
	    curl_setopt($connection, CURLOPT_FILE, $download); 
		curl_exec($connection); 
		curl_close($connection);
		fclose($download);
		
		$downloadsize = filesize($updatefile);
		if (filesize($updatefile) == 0) die(join("\n\n",$log)."\n\Update Failed: The download did not complete succesfully.");
		$log[] = "Downloaded update of $downloadsize bytes";
		
		// Extract data
		$log[] = "Unpacking updates...";
		$archive = new PclZip($updatefile);
		$files = $archive->extract(PCLZIP_OPT_EXTRACT_AS_STRING);
		if (!is_array($files)) die(join("\n\n",$log)."\n\nUpdate Failed: The downloaded update did not complete or is corrupted and cannot be used.");
		else unlink($updatefile);
		$target = trailingslashit($tmpdir);
		
		// Create file structure in working path target
		foreach ($files as $file) {
			if (!$file['folder'] ) {
				if (file_put_contents($target.$file['filename'], $file['content']))
					@chmod($target.$file['filename'], 0644);
			} else {
				if (!is_dir($target.$file['filename'])) {
					if (!@mkdir($target.$file['filename'],0755,true)) 
						die(join("\n\n",$log)."\n\nUpdate Failed: Couldn't create directory $target{$file['filename']}");
				}				
			}
		}
		$log[] = "Successfully unpacked the update.";
		
		// FTP files to make it "easier" than dealing with permissions
		$log[] = "Updating files via FTP connection";
		$results = $ftp->update($target."shopp",$Shopp->path);
		if (!empty($results)) die(join("\n\n",$log).join("\n\nUpdate Failed: ",$results));
		// $ftp->put($tmpdir.$dbBackup,$Shopp->path."/backups"."/$dbBackup");
		// $ftp->put($tmpdir.$filesBackup,$Shopp->path."/backups"."/$filesBackup");
				
		echo "updated"; // Report success!
		exit();
	}
		
	function upgrade () {
		$db = DB::get();
		require_once(ABSPATH.'wp-admin/includes/upgrade.php');
		
		// Check for the schema definition file
		if (!file_exists(SHOPP_DBSCHEMA))
		 	die("Could not upgrade the Shopp database tables because the table definitions file is missing: ".SHOPP_DBSCHEMA);
		
		// Update the table schema
		$tables = preg_replace('/;\s+/',';',file_get_contents(SHOPP_DBSCHEMA));
		dbDelta($tables);
		
		// Update the version number
		$settings = DatabaseObject::tablename(Settings::$table);
		$db->query("UPDATE $settings SET value='".SHOPP_VERSION." WHERE name='version'");
		$db->query("DELETE FROM $settings WHERE name='data_model'");
		
		return true;
	}

	function callhome ($request=array()) {
		$query = build_query_request($request);
		
		$connection = curl_init(); 
		curl_setopt ($connection, CURLOPT_URL, SHOPP_HOME."?".$query); 
		curl_setopt ($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt ($connection, CURLOPT_HEADER, 0); 
		curl_setopt ($connection, CURLOPT_RETURNTRANSFER, 1); 
		$result = curl_exec($connection); 
		curl_close ($connection);
		
		return $result;
	}


	/**
	 * setup()
	 * Initialize default install settings and lists */
	function setup () {
		
		$this->setup_regions();
		$this->setup_countries();
		$this->setup_zones();
		$this->setup_areas();
		
		// General Settings
		$this->Settings->save('version',SHOPP_VERSION);
		$this->Settings->save('shipping','on');	
		$this->Settings->save('order_status',array('Pending','Completed'));	
		$this->Settings->save('shopp_setup','completed');
		$this->Settings->save('maintenance','off');
		$this->Settings->save('dashboard','on');

		// Presentation Settings
		$this->Settings->save('theme_templates','off');
		$this->Settings->save('gallery_small_width','240');
		$this->Settings->save('gallery_small_height','240');
		$this->Settings->save('gallery_small_sizing','1');
		$this->Settings->save('gallery_small_quality','2');
		$this->Settings->save('gallery_thumbnail_width','96');
		$this->Settings->save('gallery_thumbnail_height','96');
		$this->Settings->save('gallery_thumbnail_sizing','1');
		$this->Settings->save('gallery_thumbnail_quality','3');

		// Payment Gateway Settings
		$this->Settings->save('PayPalExpress',array('enabled'=>'off'));
	}

	function setup_regions () {
		global $Shopp;
		include_once("init.php");
		$this->Settings->save('regions',get_global_regions());
	}
	
	function setup_countries () {
		global $Shopp;
		include_once("init.php");
		$this->Settings->save('countries',addslashes(serialize(get_countries())),false);
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
	
}
?>