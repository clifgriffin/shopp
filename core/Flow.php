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
		$this->Admin->categories = $Core->directory."/categories";
		$this->Admin->products = $Core->directory."/products";
		$this->Admin->promotions = $Core->directory."/promotions";
		$this->Admin->settings = $Core->directory."/settings";
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
									$this->basepath.DIRECTORY_SEPARATOR."templates");
		define("SHOPP_TEMPLATES_URI",($Core->Settings->get('theme_templates') != "off" && 
			 							is_dir($Core->Settings->get('theme_templates')))?
										get_bloginfo('stylesheet_directory')."/shopp":
										$Core->uri."/templates");

		define("SHOPP_PERMALINKS",(get_option('permalink_structure') == "")?false:true);

		define("SHOPP_LOOKUP",(strpos($_SERVER['REQUEST_URI'],"images/") !== false || 
								strpos($_SERVER['REQUEST_URI'],"lookup=") !== false)?true:false);

		$this->uploadErrors = array(
			UPLOAD_ERR_INI_SIZE => __('The uploaded file exceeds the upload_max_filesize directive in PHP\'s configuration file','Shopp'),
			UPLOAD_ERR_FORM_SIZE => __('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.','Shopp'),
			UPLOAD_ERR_PARTIAL => __('The uploaded file was only partially uploaded.','Shopp'),
			UPLOAD_ERR_NO_FILE => __('No file was uploaded.','Shopp'),
			UPLOAD_ERR_NO_TMP_DIR => __('The server\'s temporary folder is missing.','Shopp'),
			UPLOAD_ERR_CANT_WRITE => __('Failed to write the file to disk.','Shopp'),
			UPLOAD_ERR_EXTENSION => __('File upload stopped by extension.','Shopp'),
		);
									
		load_plugin_textdomain('Shopp',PLUGINDIR.DIRECTORY_SEPARATOR.$Core->directory.DIRECTORY_SEPARATOR.'lang');
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
		
		// Disable faceted menus if not in a Shopp category
		// or the category does not have faceted menus enabled
		if ($Shopp->Catalog->type != 'category' || 
			!isset($Shopp->Category->facetedmenus) || 
			$Shopp->Category->facetedmenus == 'off') 
			unregister_sidebar_widget('shopp-faceted-menu');
		
		$classes = $Shopp->Catalog->type;
		// Get catalog view preference from cookie
		if ($_COOKIE['shopp_catalog_view'] == "list") $classes .= " list";
		if ($_COOKIE['shopp_catalog_view'] == "grid") $classes .= " grid";
		if (!isset($_COOKIE['shopp_catalog_view'])) {
			// No cookie preference exists, use shopp default setting
			$view = $Shopp->Settings->get('default_catalog_view');
			if ($view == "list") $classes .= " list";
			if ($view == "grid") $classes .= " grid";
		}
		
		return apply_filters('shopp_catalog','<div id="shopp" class="'.$classes.'">'.$content.'<div id="clear"></div></div>');
	}

	function catalog_css () {
		
		ob_start();
		include(template_path("{$this->basepath}/core/ui/styles/catalog.css"));
		$stylesheet = ob_get_contents();
		ob_end_clean();
		return $stylesheet;
		
	}

	function settings_js () {
		
		ob_start();
		include(template_path("{$this->basepath}/core/ui/behaviors/settings.js"));
		$script = ob_get_contents();
		ob_end_clean();
		return $script;
		
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
		echo '<label><input type="hidden" name="shopp_categories_options[hierarchy]" value="off" /><input type="checkbox" name="shopp_categories_options[hierarchy]" value="on"'.(($options['hierarchy'] == "on")?' checked="checked"':'').' /> Show hierarchy</label><br />';
		echo '</p>';
		echo '<p><label for="pages-sortby">Smart Categories:<select name="shopp_categories_options[showsmart]" class="widefat"><option value="false">Hide</option><option value="before"'.(($options['showsmart'] == "before")?' selected="selected"':'').'>Include before custom categories</option><option value="after"'.(($options['showsmart'] == "after")?' selected="selected"':'').'>Include after custom categories</option></select></label></p>';
		echo '<div><input type="hidden" name="categories_widget_options" value="1" /></div>';
	}
	
	function init_categories_widget () {
		register_sidebar_widget("Shopp Categories",array(&$this,'categories_widget'),'shopp categories');
		register_widget_control('Shopp Categories',array(&$this,'categories_widget_options'));
	}
	
	
	function init_tagcloud_widget () {
		register_sidebar_widget("Shopp Tag Cloud",array(&$this,'tagcloud_widget'),'shopp tagcloud');
		register_widget_control('Shopp Tag Cloud',array(&$this,'tagcloud_widget_options'));
	}
	
	function tagcloud_widget_options ($args=null) {
		global $Shopp;

		if (isset($_POST['shopp_tagcloud_widget_options'])) {
			$options = $_POST['tagcloud_widget_options'];
			$Shopp->Settings->save('tagcloud_widget_options',$options);
		}

		$options = $Shopp->Settings->get('tagcloud_widget_options');

		echo '<p><label>Title<input name="tagcloud_widget_options[title]" class="widefat" value="'.$options['title'].'"></label></p>';
		echo '<div><input type="hidden" name="shopp_tagcloud_widget_options" value="1" /></div>';
	}

	function tagcloud_widget ($args=null) {
		global $Shopp;
		if (!empty($args)) extract($args);
		
		$options = $Shopp->Settings->get('tagcloud_widget_options');
		
		if (empty($options['title'])) $options['title'] = "Product Tags";
		$options['title'] = $before_title.$options['title'].$after_title;
		
		$tagcloud = $Shopp->Catalog->tag('tagcloud',$options);
		echo $before_widget.$options['title'].$tagcloud.$after_widget;
		
	}
	
	
	function init_facetedmenu_widget () {
		register_sidebar_widget("Shopp Faceted Menu",array(&$this,'facetedmenu_widget'),'shopp facetedmenu');
		register_widget_control('Shopp Faceted Menu',array(&$this,'facetedmenu_widget_options'));
	}
	
	function facetedmenu_widget_options ($args=null) {
		global $Shopp;

		if (isset($_POST['shopp_facetedmenu_widget_options'])) {
			$options = $_POST['facetedmenu_widget_options'];
			$Shopp->Settings->save('facetedmenu_widget_options',$options);
		}

		$options = $Shopp->Settings->get('facetedmenu_widget_options');

		// echo '<p><label>Title<input name="tagcloud_widget_options[title]" class="widefat" value="'.$options['title'].'"></label></p>';
		// echo '<div><input type="hidden" name="shopp_tagcloud_widget_options" value="1" /></div>';
	}

	function facetedmenu_widget ($args=null) {
		global $Shopp;
		if (!empty($args)) extract($args);
		
		$options = $Shopp->Settings->get('facetedmenu_widget_options');
		
		if (empty($options['title'])) $options['title'] = __('Product Filters','Shopp');
		$options['title'] = $before_title.$options['title'].$after_title;
		global $wp_registered_widgets;
		
		if (!empty($Shopp->Category)) {
			$menu = $Shopp->Category->tag('faceted-menu',$options);
			echo $before_widget.$options['title'].$menu.$after_widget;			
		}
	}
	
	
	/**
	 * Shopping Cart flow handlers
	 **/
	
	function cart_request () {
		global $Shopp;
		$Cart = $Shopp->Cart;
		do_action('shopp_cart_request');
				
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

		if (!empty($Request['promocode'])) {
			$Cart->data->PromoCodeResult = "";
			if (!in_array($Request['promocode'],$Cart->data->PromoCodes)) {
				$Cart->data->PromoCode = attribute_escape($Request['promocode']);
				$Request['update'] = true;
			} else $Cart->data->PromoCodeResult = __("That code has already been applied.","Shopp");
		}
		
		if (isset($Request['remove'])) $Request['cart'] = "remove";
		if (isset($Request['update'])) $Request['cart'] = "update";
		if (isset($Request['empty'])) $Request['cart'] = "empty";
		
		switch($Request['cart']) {
			case "add":			
				if (isset($Request['product'])) {
					$quantity = (!empty($Request['quantity']))?$Request['quantity']:1; // Add 1 by default

					$Product = new Product($Request['product']);
					$pricing = false;
					if (!empty($Request['options']) && !empty($Request['options'][0])) 
						$pricing = $Request['options'];
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
			default:			
				if (isset($Request['item']) && isset($Request['quantity'])) {
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
		}
		do_action('shopp_cart_updated',$Cart);
	}

	function cart_ajax () {
		global $Shopp;
		if ($_REQUEST['response'] == "html") {
			echo $Shopp->Cart->tag('sidecart');
			exit();
		}
		$AjaxCart = new StdClass();
		$AjaxCart->url = $Shopp->link('cart');
		$AjaxCart->Totals = clone($Shopp->Cart->data->Totals);
		if (isset($Shopp->Cart->added));
			$AjaxCart->Item = clone($Shopp->Cart->added);
		unset($AjaxCart->Item->options);
		$AjaxCart->Contents = array();
		foreach($Shopp->Cart->contents as $item) {
			unset($item->options);
			$AjaxCart->Contents[] = $item;
		}
		echo json_encode($AjaxCart);
	}

	function cart ($attrs=array()) {
		$Cart = $this->Cart;
		ob_start();
		include(SHOPP_TEMPLATES."/cart.php");
		$content = ob_get_contents();
		ob_end_clean();
		
		return apply_filters('shopp_cart_template','<div id="shopp">'.$content.'</div>');
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
		if (!empty($args)) extract($args);
		
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
		return apply_filters('shopp_checkout','<div id="shopp">'.$content.'</div>');
	}
	
	function checkout_order_summary () {
		global $Shopp;
		$Cart = $Shopp->Cart;

		ob_start();
		include(SHOPP_TEMPLATES."/summary.php");
		$content = ob_get_contents();
		ob_end_clean();
		
		return apply_filters('shopp_order_summary',$content);
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
		
		do_action('shopp_order_preprocessing');
		
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

				if (!empty($Order->Customer->login)) $handle = $Order->Customer->login;
				else {
					// No login provided, auto-generate login handle
					list($handle,$domain) = split("@",$Order->Customer->email);

					// The email handle exists, so use first name initial + lastname
					if (username_exists($handle)) 
						$handle = substr($Order->Customer->firstname,0,1).$Order->Customer->lastname;

					// That exists too *bangs head on wall*, ok add a random number too :P
					if (username_exists($handle)) 
						$handle .= rand(1000,9999);
				}
				
				if (username_exists($handle)) {
					$Shopp->Cart->data->OrderError = new StdClass();
					$Shopp->Cart->data->OrderError->code = "0600";
					$Shopp->Cart->data->OrderError->message = __('The login name you provided is already in use.  Please choose another login name.','Shopp');
				}
				
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
		
		// Allow other WordPress plugins access to Purchase data to extend
		// what Shopp does after a successful transaction
		do_action('shopp_order_success',$Purchase);
		
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
		
		$receipt = apply_filters('shopp_email_receipt_data',$receipt);
		
		// echo "<PRE>"; print_r($receipt); echo "</PRE>";
		shopp_email(SHOPP_TEMPLATES."/order.html",$receipt);
		
		if ($Shopp->Settings->get('receipt_copy') == 1) {
			$receipt['to'] = $Shopp->Settings->get('merchant_email');
			$receipt['subject'] = "New Order";
			shopp_email(SHOPP_TEMPLATES."/order.html",$receipt);
		}

		$ssl = true;
		// Test Mode will not require encrypted checkout
		if (strpos($gateway,"TestMode.php") !== false ||
			$_GET['shopp_xco'] == "PayPal".DIRECTORY_SEPARATOR."PayPalExpress") $ssl = false;
		$link = $Shopp->link('receipt',$ssl);
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
		return apply_filters('shopp_order_confirmation','<div id="shopp">'.$content.'</div>');
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

	function order_receipt () {
		global $Shopp;
		$Cart = $Shopp->Cart;
		
		ob_start();
		include(SHOPP_TEMPLATES."/receipt.php");
		$content = ob_get_contents();
		ob_end_clean();
		return apply_filters('shopp_order_receipt','<div id="shopp">'.$content.'</div>');
	}
	
	
	/**
	 * Orders admin flow handlers
	 */
	function orders_list() {
		global $Orders;
		$db = DB::get();

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.','Shopp'));

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
		
		if (isset($_GET['status'])) $where = "WHERE status='{$_GET['status']}'";
		if (isset($_GET['s'])) $where .= ((empty($where))?"WHERE ":" AND ")." (id='{$_GET['s']}' OR firstname LIKE '%{$_GET['s']}%' OR lastname LIKE '%{$_GET['s']}%' OR CONCAT(firstname,' ',lastname) LIKE '%{$_GET['s']}%' OR transactionid LIKE '%{$_GET['s']}%')";
		
		$ordercount = $db->query("SELECT count(*) as total FROM $Purchase->_table $where ORDER BY created DESC");
		$Orders = $db->query("SELECT * FROM $Purchase->_table $where ORDER BY created DESC LIMIT $start,$per_page",AS_ARRAY);

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
			wp_die(__('You do not have sufficient permissions to access this page.','Shopp'));
				
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
			$updated = __('Order status updated.','Shopp');
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
				return '<div id="shopp">'.$content.'</div>';
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
	function products_list($updated=false) {
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

		$orderby = "pd.created DESC";
		
		$where = "";
		if (!empty($_GET['cat'])) $where = "WHERE cat.id='{$_GET['cat']}'";
		if (!empty($_GET['s'])) {
			$match = "MATCH(pd.name,pd.summary,pd.description) AGAINST ('{$_GET['s']}' IN BOOLEAN MODE)";
			$where .= ((empty($where))?"WHERE ":" AND ").$match;
			$matchcol = ", $match  AS score";
			$orderby = "score DESC";
		}
		
		// Get total product count, taking into consideration for filtering
		if (!empty($_GET['s'])) $productcount = $db->query("SELECT count($match) as total FROM $pd AS pd LEFT JOIN $clog AS clog ON pd.id=clog.product LEFT JOIN $cat AS cat ON cat.id=clog.category $where");
		elseif (!empty($_GET['cat'])) $productcount = $db->query("SELECT count(*) as total $matchcol FROM $pd AS pd LEFT JOIN $clog AS clog ON pd.id=clog.product LEFT JOIN $cat AS cat ON cat.id=clog.category $where");
		else $productcount = $db->query("SELECT count(*) as total $matchcol FROM $pd $where");
		
		// Load the products
		$Products = $db->query("SELECT pd.id,pd.name,pd.featured,GROUP_CONCAT(DISTINCT cat.name ORDER BY cat.name SEPARATOR ', ') AS categories, MAX(pt.price) AS maxprice,MIN(pt.price) AS minprice $matchcol FROM $pd AS pd LEFT JOIN $pt AS pt ON pd.id=pt.product AND pt.type != 'N/A' LEFT JOIN $clog AS clog ON pd.id=clog.product LEFT JOIN $cat AS cat ON cat.id=clog.category $where GROUP BY pd.id ORDER BY $orderby LIMIT $start,$per_page",AS_ARRAY);

		$num_pages = ceil($productcount->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg(array("edit"=>null,'pagenum' => '%#%')),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum,
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
				case TagProducts::$slug: 
					$Shopp->Category = new TagProducts(array('tag'=>$atts['tag'])); break;
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
		global $Product,$Shopp;
		$db = DB::get();

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if ($_GET['edit'] != "new") {
			$Product = new Product($_GET['edit']);
			$Product->load_data(array('prices','specs','categories','tags'));
		} else $Product = new Product();

		if (!empty($_POST['save']) || !empty($_POST['save-products'])) {
			$this->save_product($Product);
			
			$Product = new Product($Product->id);
			$Product->load_data(array('prices','specs','categories','tags'));
			$updated = '<strong>'.$Product->name.'</strong> '.__('has been saved.','Shopp');
			if (!empty($_POST['save-products'])) {
				$this->products_list($updated); 
				exit();
			}
		}
		
		if ($Shopp->link('catalog') == trailingslashit(get_bloginfo('wpurl')))
			$permalink = trailingslashit($Shopp->link('catalog'))."shop/new/";
		else $permalink = trailingslashit($Shopp->link('catalog'))."new/";

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
		foreach ($Product->categories as $category) $selectedCategories[] = $category->id;

		$taglist = array();
		foreach ($Product->tags as $tag) $taglist[] = $tag->name;
		
		$Assets = new Asset();
		$Images = $db->query("SELECT id,src FROM $Assets->_table WHERE context='product' AND parent=$Product->id AND datatype='thumbnail' ORDER BY sortorder",AS_ARRAY);
		unset($Assets);

		$shiprates = $this->Settings->get('shipping_rates');
		if (!empty($shiprates)) ksort($shiprates);

		$process = (!empty($Product->id)?$Product->id:'new');
		$action = wp_get_referer();
		if (empty($action)) $action = admin_url("admin.php?page=".$this->Admin->products);
		$action = add_query_arg('edit',$process,$action);

		include("{$this->basepath}/core/ui/products/editor.php");

	}

	function save_product($Product) {
		global $Shopp;
		$db = DB::get();
		check_admin_referer('shopp-save-product');

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));
			
		if (!$_POST['options']) $Product->options = array();
		if (empty($Product->slug)) $_POST['slug'] = sanitize_title_with_dashes($_POST['name']);
		$Product->updates($_POST,array('categories'));
		$Product->save();

		$Product->save_categories($_POST['categories']);
		$Product->save_tags(split(",",$_POST['taglist']));

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
				if (!empty($option['downloadpath'])) {
					$basepath = trailingslashit($Shopp->Settings->get('products_path'));
					$download = $basepath.ltrim($option['downloadpath'],"/");
					if (file_exists($download)) {
						$File = new Asset();
						$File->parent = 0;
						$File->context = "price";
						$File->datatype = "download";
						$File->name = basename($download);
						$File->value = substr(dirname($download),strlen($basepath));
						$File->size = filesize($download);
						$File->properties = array("mimetype" => file_mimetype($download));
						$File->save();
						$Price->attach_download($File->id);
					}
				}
			}
			unset($Price);
		}
			
		if (!empty($_POST['details']) || !empty($_POST['deletedSpecs'])) {
			$deletes = array();
			if (!empty($_POST['deletedSpecs'])) {
				if (strpos($_POST['deletedSpecs'],","))	$deletes = split(',',$_POST['deletedSpecs']);
				else $deletes = array($_POST['deletedSpecs']);
				foreach($deletes as $option) {
					$Spec = new Spec($option);
					$Spec->delete();
				}
				unset($Spec);
			}

			if (is_array($_POST['details'])) {
				foreach ($_POST['details'] as $i => $spec) {
					if (in_array($spec['id'],$deletes)) continue;
					if (isset($spec['new'])) {
						$Spec = new Spec();
						$spec['id'] = '';
						$spec['product'] = $Product->id;
					} else $Spec = new Spec($spec['id']);
					$spec['sortorder'] = array_search($i,$_POST['details-sortorder'])+1;
					
					$Spec->updates($spec);
					if (preg_match('/^.*?(\d+[\.\,\d]*).*$/',$spec['content']))
						$Spec->numeral = preg_replace('/^.*?(\d+[\.\,\d]*).*$/','$1',$spec['content']);
					
					$Spec->save();
				}
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
		return true;
	}
	
	function product_downloads () {
		$error = false;
		if (isset($_FILES['Filedata']['error'])) $error = $_FILES['Filedata']['error'];
		if ($error) die(json_encode(array("error" => $this->uploadErrors[$error])));
		
		// Save the uploaded file
		$File = new Asset();
		$File->parent = 0;
		$File->context = "price";
		$File->datatype = "download";
		$File->name = $_FILES['Filedata']['name'];
		$File->size = filesize($_FILES['Filedata']['tmp_name']);
		$File->properties = array("mimetype" => file_mimetype($_FILES['Filedata']['tmp_name']));
		$File->data = addslashes(file_get_contents($_FILES['Filedata']['tmp_name']));
		$File->save();
		unset($File->data); // Remove file contents from memory
		
		echo json_encode(array("id"=>$File->id,"name"=>$File->name,"type"=>$File->properties['mimetype'],"size"=>$File->size));
	}
	
	function add_images () {
			$error = false;
			if (isset($_FILES['Filedata']['error'])) $error = $_FILES['Filedata']['error'];
			if ($error) die(json_encode(array("error" => $this->uploadErrors[$error])));

			require("{$this->basepath}/core/model/Image.php");
			
			if (isset($_POST['product'])) {
				$parent = $_POST['product'];
				$context = "product";
			}

			if (isset($_POST['category'])) {
				$parent = $_POST['category'];
				$context = "category";
			}
			
			// Save the source image
			$Image = new Asset();
			$Image->parent = $parent;
			$Image->context = $context;
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
			$Small->context = $context;
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
			$SmallSizing->UnsharpMask(80);
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
			$Thumbnail->context = $context;
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
		
		$filters = array();
		// $filters['limit'] = "$start,$per_page";
		if (isset($_GET['s'])) $filters['where'] = "name LIKE '%{$_GET['s']}%'";

		$table = DatabaseObject::tablename(Category::$table);
		$Catalog = new Catalog();
		$Catalog->load_categories($filters);
		$Categories = array_slice($Catalog->categories,$start,$per_page);
		
		$count = $db->query("SELECT count(*) AS total FROM $table");
		$num_pages = ceil($count->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg( array('edit'=>null,'pagenum' => '%#%' )),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum
		));
		
		include("{$this->basepath}/core/ui/categories/categories.php");
	}
	
	function category_editor () {
		global $Shopp;
		$db = DB::get();
		
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if ($_GET['edit'] != "new") {
			$Category = new Category($_GET['edit']);
		} else $Category = new Category();

		$Shopp->Catalog = new Catalog();
		$Shopp->Catalog->load_categories();

		$Price = new Price();
		$priceTypes = $Price->_lists['type'];

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-save-category');
			
			if (empty($_POST['slug'])) $_POST['slug'] = sanitize_title_with_dashes($_POST['name']);
			else $_POST['slug'] = sanitize_title_with_dashes($_POST['slug']);

			// Work out pathing
			$paths = array();
			if (!empty($_POST['slug'])) $paths = array($_POST['slug']);  // Include self
			
			$parentkey = -1;
			// If we're saving a new category, lookup the parent
			if ($_POST['parent'] > 0) {
				for ($i = count($Shopp->Catalog->categories); $i > 0; $i--)
					if ($_POST['parent'] == $Shopp->Catalog->categories[$i]->id) break;
				array_unshift($paths,$Shopp->Catalog->categories[$i]->slug);
				$parentkey = $Shopp->Catalog->categories[$i]->parentkey;
			}

			while ($parentkey > -1) {
				$category_tree = $Shopp->Catalog->categories[$parentkey];
				array_unshift($paths,$category_tree->slug);
				$parentkey = $category_tree->parentkey;
			}

			$_POST['uri'] = join("/",$paths);
			
			if (!empty($_POST['deleteImages'])) {			
				$deletes = array();
				if (strpos($_POST['deleteImages'],","))	$deletes = split(',',$_POST['deleteImages']);
				else $deletes = array($_POST['deleteImages']);
				$Category->delete_images($deletes);
			}

			if (!empty($_POST['images']) && is_array($_POST['images'])) {
				$Category->link_images($_POST['images']);
				$Category->save_imageorder($_POST['images']);
			}

			// Variation price templates
			if (!empty($_POST['price']) && is_array($_POST['price'])) {
				foreach ($_POST['price'] as &$pricing) {
					$pricing['price'] = floatvalue($pricing['price']);
					$pricing['saleprice'] = floatvalue($pricing['saleprice']);
					$pricing['shipfee'] = floatvalue($pricing['shipfee']);
				}
				$Category->prices = $_POST['price'];
			}
			if (empty($_POST['options'])) $Category->options = array();
			
			$Category->updates($_POST);
			$Category->save();
			$updated = '<strong>'.$Category->name.'</strong> '.__('category saved.','Shopp');
		}
		
		$permalink = trailingslashit($Shopp->link('catalog'))."category/";
		
		$pricerange_menu = array(
			"disabled" => __('Price ranges disabled','Shopp'),
			"auto" => __('Build price ranges automatically','Shopp'),
			"custom" => __('Use custom price ranges','Shopp'),
		);
		
		$Assets = new Asset();
		$Images = $db->query("SELECT id,src FROM $Assets->_table WHERE context='category' AND parent=$Category->id AND datatype='thumbnail' ORDER BY sortorder",AS_ARRAY);
		unset($Assets);
		
		$categories_menu = $this->category_menu($Category->parent,$Category->id);
		$categories_menu = '<option value="0" rel="-1,-1">'.__('Parent Category','Shopp').'&hellip;</option>'.$categories_menu;
				
		include("{$this->basepath}/core/ui/categories/category.php");
	}	
	
	function category_menu ($selection=false,$current=false) {
		$db = DB::get();
		$table = DatabaseObject::tablename(Category::$table);			
		$categories = $db->query("SELECT id,name,parent FROM $table ORDER BY parent,name",AS_ARRAY);
		$categories = sort_tree($categories);

		foreach ($categories as $category) {
			$padding = str_repeat("&nbsp;",$category->depth*3);
			$selected = ($category->id == $selection)?' selected="selected"':'';
			$disabled = ($current && $category->id == $current)?' disabled="disabled"':'';
			$options .= '<option value="'.$category->id.'" rel="'.$category->parent.','.$category->depth.'"'.$selected.$disabled.'>'.$padding.$category->name.'</option>';
		}
		return $options;
	}
	
	function category_products () {
		$db = DB::get();
		$catalog = DatabaseObject::tablename(Catalog::$table);
		$category = DatabaseObject::tablename(Category::$table);
		$products = DatabaseObject::tablename(Product::$table);
		$results = $db->query("SELECT p.id,p.name FROM $catalog AS catalog LEFT JOIN $category AS cat ON cat.id = catalog.category LEFT JOIN $products AS p ON p.id=catalog.product WHERE cat.id={$_GET['category']} ORDER BY p.name ASC",AS_ARRAY);
		$products = array();
		
		$products[0] = "Select a product&hellip;";
		foreach ($results as $result) $products[$result->id] = $result->name;
		return menuoptions($products,0,true);
		
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
		
		
		$where = "";
		if (!empty($_GET['s'])) $where = "WHERE name LIKE '%{$_GET['s']}%'";
		
		$table = DatabaseObject::tablename(Promotion::$table);
		$promocount = $db->query("SELECT count(*) as total FROM $table $where");
		$Promotions = $db->query("SELECT * FROM $table $where",AS_ARRAY);
		
		$num_pages = ceil($promocount->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg( 'pagenum', '%#%' ),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum
		));
		
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
				$_POST['ends'] = mktime(23,59,59,$_POST['ends']['month'],$_POST['ends']['date'],$_POST['ends']['year']);
			else $_POST['ends'] = 1;

			$Promotion->updates($_POST);
			$Promotion->save();

			if ($Promotion->scope == "Catalog")
				$Promotion->build_discounts();
			
			$this->promotions_list();
			return true;
		}
		
		include("{$this->basepath}/core/ui/promotions/editor.php");
	}
	
	/**
	 * Dashboard Widgets
	 */
	function dashboard_stats ($args=null) {
		$db = DB::get();
		if (!empty($args)) extract( $args, EXTR_SKIP );

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

		$orderscreen = get_bloginfo('wpurl').'/wp-admin/admin.php?page='.$this->Admin->orders;
		echo '<div class="table"><table><tbody>';
		echo '<tr><th colspan="2">Last 30 Days</th><th colspan="2">Lifetime</th></tr>';

		echo '<tr><td class="amount"><a href="'.$orderscreen.'">'.$results->wkorders.'</a></td><td>'.__('Orders','Shopp').'</td>';
		echo '<td class="amount"><a href="'.$orderscreen.'">'.$results->orders.'</a></td><td>'.__('Orders','Shopp').'</td></tr>';

		echo '<tr><td class="amount"><a href="'.$orderscreen.'">'.money($results->wksales).'</a></td><td>'.__('Sales','Shopp').'</td>';
		echo '<td class="amount"><a href="'.$orderscreen.'">'.money($results->sales).'</a></td><td>'.__('Sales','Shopp').'</td></tr>';

		echo '<tr><td class="amount"><a href="'.$orderscreen.'">'.money($results->wkavg).'</a></td><td>'.__('Average Order','Shopp').'</td>';
		echo '<td class="amount"><a href="'.$orderscreen.'">'.money($results->average).'</a></td><td>'.__('Average Order','Shopp').'</td></tr>';

		echo '</tbody></table></div>';
		
		echo $after_widget;
		
	}
	
	function dashboard_orders ($args=null) {
		$db = DB::get();
		if (!empty($args)) extract( $args, EXTR_SKIP );
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
	
	function dashboard_products ($args=null) {
		$db = DB::get();
		if (!empty($args)) extract( $args, EXTR_SKIP );

		echo $before_widget;

		echo $before_title;
		echo $widget_name;
		echo $after_title;

		$RecentBestsellers = new BestsellerProducts(array('where'=>'UNIX_TIMESTAMP(pur.created) > UNIX_TIMESTAMP()-(86400*30)','show'=>3));
		
		echo '<table><tbody><tr>';
		echo '<td><h4>Recent Bestsellers</h4>';
		echo '<ul>';
		foreach ($RecentBestsellers->products as $product) 
			echo '<li><a href="admin.php?page='.$this->Admin->products.'&edit='.$product->id.'">'.$product->name.'</a> ('.$product->sold.')</li>';
		echo '</ul></td>';
		
		
		$LifetimeBestsellers = new BestsellerProducts(array('show'=>3));
		echo '<td><h4>Lifetime Bestsellers</h4>';
		echo '<ul>';
		foreach ($LifetimeBestsellers->products as $product) 
			echo '<li><a href="admin.php?page='.$this->Admin->products.'&edit='.$product->id.'">'.$product->name.'</a> ('.$product->sold.')</li>';
		echo '</ul></td>';
		echo '</tr></tbody></table>';
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
			$updated = __('Shopp settings saved.');
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
			$_POST['settings']['theme_templates'] = addslashes(template_path(TEMPLATEPATH.DIRECTORY_SEPARATOR."shopp"));
		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-presentation');
			if (empty($_POST['settings']['catalog_pagination']))
				$_POST['settings']['catalog_pagination'] = 0;
			$this->settings_save();
			$updated = __('Shopp presentation settings saved.');
		}
		
		$builtin_path = $this->basepath.DIRECTORY_SEPARATOR."templates";
		$theme_path = template_path(TEMPLATEPATH.DIRECTORY_SEPARATOR."shopp");
		
		// Copy templates to the current WordPress theme
		if (!empty($_POST['install'])) {
			check_admin_referer('shopp-settings-presentation');
			$builtin = array_filter(scandir($builtin_path),"filter_dotfiles");
			foreach ($builtin as $template) {
				if (!file_exists($theme_path.$template)) {
					copy($builtin_path.DIRECTORY_SEPARATOR.$template,$theme_path.DIRECTORY_SEPARATOR.$template);
					chmod($theme_path.DIRECTORY_SEPARATOR.$template,0666);
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

		$category_views = array("grid" => __('Grid','Shopp'),"list" => __('List','Shopp'));
		$row_products = array(2,3,4,5,6,7);
		
		$orderOptions = array("ASC" => __('Order','Shopp'),
							  "DESC" => __('Reverse Order','Shopp'),
							  "RAND()" => __('Shuffle','Shopp'));

		$orderBy = array("sortorder" => __('Custom arrangement','Shopp'),
						 "name" => __('File name','Shopp'),
						 "created" => __('Upload date','Shopp'));
		
		$sizingOptions = array(	__('Scale to fit','Shopp'),
								__('Scale &amp; crop','Shopp'));
								
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
		$db =& DB::get();
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$purchasetable = DatabaseObject::tablename(Purchase::$table);
		$next = $db->query("SELECT auto_increment as id FROM information_schema.tables WHERE table_name='$purchasetable'");

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-checkout');
			if ($_POST['next_order_id'] != $next->id) {
				if ($db->query("ALTER TABLE $purchasetable AUTO_INCREMENT={$_POST['next_order_id']}"))
					$next->id = $_POST['next_order_id'];
			} 
				
			$this->settings_save();
			$updated = __('Shopp checkout settings saved.','Shopp');
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
			$updated = __('Shopp shipping settings saved.','Shopp');
		}

		$methods = $Shopp->ShipCalcs->methods;
		$base = $Shopp->Settings->get('base_operations');
		$regions = $Shopp->Settings->get('regions');
		$region = $regions[$base['region']];
		$useRegions = $Shopp->Settings->get('shipping_regions');

		$areas = $Shopp->Settings->get('areas');
		if (is_array($areas[$base['country']]) && $useRegions == "on") 
			$areas = array_keys($areas[$base['country']]);
		else $areas = array($base['country'] => $base['name']);
		unset($countries,$regions);

		$rates = $Shopp->Settings->get('shipping_rates');
		if (!empty($rates)) ksort($rates);
				
		include(SHOPP_ADMINPATH."/settings/shipping.php");
	}

	function settings_taxes () {
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-taxes');
			$this->settings_save();
			$updated = __('Shopp taxes settings saved.','Shopp');
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
		include("{$this->basepath}/gateways/GoogleCheckout/GoogleCheckout.php");

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-payments');

			// Update the accepted credit card payment methods
			if (!empty($_POST['settings']['payment_gateway'])) {
				$_POST['settings']['payment_gateway'] = stripslashes($_POST['settings']['payment_gateway']);
				$gateway = $this->scan_gateway_meta($_POST['settings']['payment_gateway']);
				$ProcessorClass = $gateway->tags['class'];
				include_once($gateway->file);
				$Processor = new $ProcessorClass();
				$_POST['settings']['gateway_cardtypes'] = $Processor->cards;
			}
			
			// Build the Google Checkout API URL if Google Checkout is enabled
			if (!empty($_POST['settings']['GoogleCheckout']['id']) && !empty($_POST['settings']['GoogleCheckout']['key'])) {
				$GoogleCheckout = new GoogleCheckout();
				$url = $Shopp->link('catalog',true);
				$url .= "?shopp_xorder=GoogleCheckout";
				$url .= "&merc=".$GoogleCheckout->authcode(
										$_POST['settings']['GoogleCheckout']['id'],
										$_POST['settings']['GoogleCheckout']['key']);
				$_POST['settings']['GoogleCheckout']['apiurl'] = $url;
			}
			
			$this->settings_save();
			$updated = __('Shopp payments settings saved.','Shopp');
		}
		
		// Get all of the installed gateways
		$data = $this->settings_get_gateways();
		$PayPalExpress = new PayPalExpress();
		$GoogleCheckout = new GoogleCheckout();

		$gateways = array();
		$Processors = array();
		foreach ($data as $gateway) {
			// Treat PayPal Express and Google Checkout differently
			if ($gateway->name == "PayPal Express" || 
				$gateway->name == "Google Checkout") continue;

			$gateways[$gateway->file] = $gateway->name;
			$ProcessorClass = $gateway->tags['class'];
			include_once($gateway->file);
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
			if (isset($_POST['settings']['ftp_credentials']))
				$updated = __('FTP settings saved.  Click the <b>Check for Updates</b> button to start the update process again.','Shopp');
		}
		
		if (!empty($_POST['activation'])) {
			check_admin_referer('shopp-settings-update');
			$this->settings_save();	
			
			if ($_POST['activation'] == "Activate Key") $process = "activate-key";
			else $process = "deactivate-key";
			
			$request = array(
				"ShoppServerRequest" => $process,
				"v" => SHOPP_VERSION,
				"key" => trim($_POST['settings']['update_key']),
				"site" => get_bloginfo('siteurl')
			);
			
			$activation = $this->callhome($request);
			
			if ($activation != "1")
				$activation = '<span class="shopp error">'.$activation.'</span>';
			
			if ($process == "activate-key" && $activation == "1") {
				$this->Settings->save('updatekey_status','activated');
				$activation = __('This key has been successfully activated.','Shopp');
			}
			
			if ($process == "deactivate-key" && $activation == "1") {
				$this->Settings->save('updatekey_status','deactivated');
				$activation = __('This key has been successfully de-activated.','Shopp');
			}
		} else {
			if ($this->Settings->get('updatekey_status') == "activated") 
				$activation = __('This key has been successfully activated.','Shopp');
			else $activation = __('Enter your Shopp upgrade key and activate it to enable easy, automatic upgrades.','Shopp');
		}
		
		include(SHOPP_ADMINPATH."/settings/update.php");
	}
	
	function settings_system () {
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		// Image path processing
		$_POST['settings']['image_storage'] = $_POST['settings']['image_storage_pref'];
		$imagepath = $this->Settings->get('image_path');
		$imagepath_status = __("File system image hosting is enabled and working.","Shopp");
		if (!file_exists($imagepath)) $error = __("The current path does not exist. Using database instead.","Shopp");
		if (!is_dir($imagepath)) $error = __("The file path supplied is not a directory. Using database instead.","Shopp");
		if (!is_writable($imagepath) || !is_readable($imagepath)) 
			$error = __("Permissions error. This path must be writable by the web server. Using database instead.","Shopp");
		if (empty($imagepath)) $error = __("Enter the absolute path starting from server root to your image storage directory.","Shopp");
		if ($error) {
			$_POST['settings']['image_storage'] = 'db';
			$imagepath_status = '<span class="error">'.$error.'</span>';
		}

		// Product path processing
		$_POST['settings']['product_storage'] = $_POST['settings']['product_storage_pref'];
		$productspath = $this->Settings->get('products_path');
		$error = ""; // Reset the error tracker
		$productspath_status = __("File system product file hosting is enabled and working.","Shopp");
		if (!file_exists($productspath)) $error = __("The current path does not exist. Using database instead.","Shopp");
		if (!is_dir($productspath)) $error = __("The file path supplied is not a directory. Using database instead.","Shopp");
		if (!is_writable($productspath) || !is_readable($productspath)) 
			$error = __("Permissions error. This path must be writable by the web server. Using database instead.","Shopp");
		if (empty($productspath)) $error = __("Enter the absolute path starting from server root to your product file storage directory.","Shopp");
		if ($error) {
			$_POST['settings']['product_storage'] = 'db';
			$productspath_status = '<span class="error">'.$error.'</span>';
		}

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-system');
			$this->settings_save();
			$updated = __('Shopp system settings saved.','Shopp');
		}
		
		$filesystems = array("db" => __("Database","Shopp"),"fs" => __("File System","Shopp"));
		
		include(SHOPP_ADMINPATH."/settings/system.php");
	}	
	

	function settings_ftp () {
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));
		check_admin_referer('shopp-wp_ajax_shopp_update');
		$credentials = $this->Settings->get('ftp_credentials');		
		include(SHOPP_ADMINPATH."/settings/ftp.php");
	}

	function settings_get_gateways () {
		$gateway_path = $this->basepath.DIRECTORY_SEPARATOR."gateways";
		
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
				
		// Find our temporary filesystem workspace
		$tmpdir = sys_get_temp_dir();
		$log[] = "Found temp directory: $tmpdir";
		
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
		// Report error message returned by the server request
		if (filesize($updatefile) < 256) die(join("\n\n",$log)."\nUpdate Failed: ".file_get_contents($updatefile));
		
		// Nothing downloaded... couldn't reach the server?
		if (filesize($updatefile) == 0) die(join("\n\n",$log)."\n\Update Failed: The download did not complete succesfully.");
		
		// Download successful, log the size
		$log[] = "Downloaded update of ".number_format($downloadsize)." bytes";
		
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
		if (!empty($results)) die(join("\n\n",$log).join("\n\n",$results)."\n\nFTP transfer failed.");
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

		// Checkout Settings
		$this->Settings->save('order_confirmation','ontax');	
		$this->Settings->save('receipt_copy','1');	
		$this->Settings->save('account_system','none');	

		// Presentation Settings
		$this->Settings->save('theme_templates','off');
		$this->Settings->save('row_products','3');
		$this->Settings->save('catalog_pagination','25');
		$this->Settings->save('product_image_order','ASC');
		$this->Settings->save('product_image_orderby','sortorder');
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
		$this->Settings->save('GoogleCheckout',array('enabled'=>'off'));
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