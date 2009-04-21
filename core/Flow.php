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
		global $wp_version;
		$this->Settings = $Core->Settings;
		$this->Cart = $Core->Cart;

		$this->basepath = dirname(dirname(__FILE__));
		$this->uri = ((!empty($_SERVER['HTTPS']))?"https://":"http://").
					$_SERVER['SERVER_NAME'].str_replace("?".$_SERVER['QUERY_STRING'],"",$_SERVER['REQUEST_URI']);
		$this->secureuri = 'https://'.$_SERVER['SERVER_NAME'].$this->uri;
		
		$this->Admin = new stdClass();
		$this->Admin->orders = $Core->directory."-orders";
		$this->Admin->categories = $Core->directory."-categories";
		$this->Admin->editcategory = $Core->directory."-categories-edit";
		$this->Admin->products = $Core->directory."-products";
		$this->Admin->editproduct = $Core->directory."-products-edit";
		$this->Admin->promotions = $Core->directory."-promotions";
		$this->Admin->editpromo = $Core->directory."-promotions-edit";
		$this->Admin->settings = array(
			'settings' => array($Core->directory."-settings",__('General','Shopp')),
			'checkout' => array($Core->directory."-settings-checkout",__('Checkout','Shopp')),
			'payments' => array($Core->directory."-settings-payments",__('Payments','Shopp')),
			'shipping' => array($Core->directory."-settings-shipping",__('Shipping','Shopp')),
			'taxes' => array($Core->directory."-settings-taxes",__('Taxes','Shopp')),
			'presentation' => array($Core->directory."-settings-presentation",__('Presentation','Shopp')),
			'system' => array($Core->directory."-settings-system",__('System','Shopp')),
			'update' => array($Core->directory."-settings-update",__('Update','Shopp'))
		);		
		$this->Admin->help = $Core->directory."-help";
		$this->Admin->welcome = $Core->directory."-welcome";
		$this->Admin->default = $this->Admin->orders;
		
		$this->Pages = $Core->Settings->get('pages');
		if (empty($this->Pages)) {
			$this->Pages = array();
			$this->Pages['catalog'] = array('name'=>'shop','title'=>'Shop','content'=>'[catalog]');
			$this->Pages['cart'] = array('name'=>'cart','title'=>'Cart','content'=>'[cart]');
			$this->Pages['checkout'] = array('name'=>'checkout','title'=>'Checkout','content'=>'[checkout]');
			$this->Pages['account'] = array('name'=>'account','title'=>'Your Orders','content'=>'[account]');
		}

		$this->coremods = array("GoogleCheckout.php", "PayPalExpress.php", 
									"TestMode.php", "FlatRates.php", "ItemQuantity.php", 
									"OrderAmount.php", "OrderWeight.php");
		if (!defined('BR')) define('BR','<br />');
		define("SHOPP_WP27",(!version_compare($wp_version,"2.7","<")));
		define("SHOPP_DEBUG",($Core->Settings->get('error_logging') == 256));
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

		load_plugin_textdomain('Shopp',
			PLUGINDIR.DIRECTORY_SEPARATOR.$Core->directory.DIRECTORY_SEPARATOR.'lang');
	}

	function admin () {
		global $Shopp;
		$db =& DB::get();
		if (!defined('WP_ADMIN') || !isset($_GET['page'])) return;
		$Admin = $Shopp->Flow->Admin;
		$adminurl = $Shopp->wpadminurl."admin.php";
		
		$defaults = array(
			'deleting' => false,
			'delete' => false,
			'id' => false,
			'save' => false,
			'duplicate' => false,
			'next' => false
			);
		$args = array_merge($defaults,$_REQUEST);
		extract($args,EXTR_SKIP);

		if (strstr($page,$Admin->categories)) {
			
			if ($deleting == "category"
					&& !empty($delete) 
					&& is_array($delete)) {
				foreach($delete as $deletion) {
					$Category = new Category($deletion);
					$db->query("UPDATE $Category->_table SET parent=0 WHERE parent=$Category->id");
					$Category->delete();
				}
				header("Location: ".add_query_arg(array_merge($_GET,array('delete[]'=>null,'deleting'=>null)),$adminurl));
				exit();
			}
			
			if ($id && $id != "new")
				$Shopp->Category = new Category($id);
			else $Shopp->Category = new Category();
			
			if ($save) {
				$this->save_category($Shopp->Category);
				$this->Notice = '<strong>'.stripslashes($Shopp->Category->name).'</strong> '.__('has been saved.','Shopp');

				if ($next) {
					if ($next != "new") 
						$Shopp->Category = new Category($next);
					else $Shopp->Category = new Category();
				} else {
					$Shopp->Category = new Category($id);
				}
					
			}
			
		} // end $Admin->categories

		if (strstr($page,$Admin->products)) {
			if ($deleting == "product"
					&& !empty($delete) 
					&& is_array($delete)) {
				foreach($delete as $deletion) {
					$Product = new Product($deletion);
					$Product->delete();
				}
				header("Location: ".add_query_arg(array_merge($_GET,array('delete'=>null,'deleting'=>null)),$adminurl));
				exit();
			}
			
			if ($duplicate) {
				$Product = new Product();
				$Product->load($duplicate);
				$Product->duplicate();
				header("Location: ".add_query_arg('page',$Admin->products,$adminurl));
				exit();
			}

			if ($id && $id != "new") {
				$Shopp->Product = new Product($id);
				$Shopp->Product->load_data(array('prices','specs','categories','tags'));
			} else {
				$Shopp->Product = new Product();
				$Shopp->Product->published = "on";  
			}
			
			if ($save) {
				$this->save_product($Shopp->Product);
				$this->Notice = '<strong>'.stripslashes($Shopp->Product->name).'</strong> '.__('has been saved.','Shopp');
				
				if ($next) {
					if ($next == "new") {
						$Shopp->Product = new Product();
						$Shopp->Product->published = "on";  
					} else {
						$Shopp->Product = new Product($next);
						$Shopp->Product->load_data(array('prices','specs','categories','tags'));
					}
				} else {
					$Shopp->Product = new Product($id);
					$Shopp->Product->load_data(array('prices','specs','categories','tags'));					
				}
			}
		} // end $Admin->products
		
	}


	/**
	 * Catalog flow handlers
	 **/
	function catalog () {
		global $Shopp;
		
		ob_start();
		switch ($Shopp->Catalog->type) {
			case "product": 
				if (file_exists(SHOPP_TEMPLATES."/product-{$Shopp->Product->id}.php"))
					include(SHOPP_TEMPLATES."/product-{$Shopp->Product->id}.php");
				else include(SHOPP_TEMPLATES."/product.php"); break;

			case "category":
				if (isset($Shopp->Category->smart) && 
						file_exists(SHOPP_TEMPLATES."/category-{$Shopp->Category->slug}.php"))
					include(SHOPP_TEMPLATES."/category-{$Shopp->Category->slug}.php");
				elseif (isset($Shopp->Category->id) && 
					file_exists(SHOPP_TEMPLATES."/category-{$Shopp->Category->id}.php"))
					include(SHOPP_TEMPLATES."/category-{$Shopp->Category->id}.php");
				else include(SHOPP_TEMPLATES."/category.php"); break;

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
		if (!isset($_COOKIE['shopp_catalog_view'])) {
			// No cookie preference exists, use shopp default setting
			$view = $Shopp->Settings->get('default_catalog_view');
			if ($view == "list") $classes .= " list";
			if ($view == "grid") $classes .= " grid";
		} else {
			if ($_COOKIE['shopp_catalog_view'] == "list") $classes .= " list";
			if ($_COOKIE['shopp_catalog_view'] == "grid") $classes .= " grid";
		}
		
		return apply_filters('shopp_catalog','<div id="shopp" class="'.$classes.'">'.$content.'<div id="clear"></div></div>');
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
		wp_register_sidebar_widget('shopp-categories',__('Shopp Categories','Shopp'),array(&$this,'categories_widget'),'shopp categories');
		wp_register_widget_control('shopp-categories',__('Shopp Categories','Shopp'),array(&$this,'categories_widget_options'));
	}
	
	
	function init_tagcloud_widget () {
		wp_register_sidebar_widget('shopp-tagcloud',__('Shopp Tag Cloud','Shopp'),array(&$this,'tagcloud_widget'),'shopp tagcloud');
		wp_register_widget_control('shopp-tagcloud',__('Shopp Tag Cloud','Shopp'),array(&$this,'tagcloud_widget_options'));
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
		wp_register_sidebar_widget('shopp-facetedmenu',__('Shopp Faceted Menu','Shopp'),array(&$this,'facetedmenu_widget'),'shopp facetedmenu');
		wp_register_widget_control('shopp-facetedmenu',__('Shopp Faceted Menu','Shopp'),array(&$this,'facetedmenu_widget_options'));
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
		
		if (!empty($Shopp->Category->id) && $Shopp->Category->facetedmenus == "on") {
			$menu = $Shopp->Category->tag('faceted-menu',$options);
			echo $before_widget.$options['title'].$menu.$after_widget;			
		}
	}
	
	
	/**
	 * Shopping Cart flow handlers
	 **/
	function cart ($attrs=array()) {
		$Cart = $this->Cart;

		ob_start();
		include(SHOPP_TEMPLATES."/cart.php");
		$content = ob_get_contents();
		ob_end_clean();

		return apply_filters('shopp_cart_template','<div id="shopp">'.$content.'</div>');
	}

	function init_cart_widget () {
		wp_register_sidebar_widget('shopp-cart',__('Shopp Cart','Shopp'),array(&$this,'cart_widget'),'shopp cart');
		wp_register_widget_control('shopp-cart',__('Shopp Cart','Shopp'),array(&$this,'cart_widget_options'));
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
		$options['title'] = $before_title.$options['title'].$after_title;
		
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
				if ($Cart->data->Errors->exist(SHOPP_COMM_ERR)) {
					include(SHOPP_TEMPLATES."/errors.php");
					$Cart->data->Errors->reset();
				}
				if (!empty($xco)) {
					$gateway = join(DIRECTORY_SEPARATOR,array($Shopp->path,'gateways',$xco.".php"));
					if (file_exists($gateway)) {
						$gateway_meta = $this->scan_gateway_meta($gateway);
						$ProcessorClass = $gateway_meta->tags['class'];
						include_once($gateway);
						$Payment = new $ProcessorClass();
						if ($Payment->checkout) include(SHOPP_TEMPLATES."/checkout.php");
						else {
							if ($Cart->data->Errors->exist(SHOPP_COMM_ERR))
								include(SHOPP_TEMPLATES."/errors.php");
							include(SHOPP_TEMPLATES."/summary.php");
							echo $Payment->tag('button');
						}
					} else include(SHOPP_TEMPLATES."/summary.php");
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
		
		if ($gateway) {
			if (!file_exists($gateway)) {
				new ShoppError(__("There was a problem loading the payment processor to complete this transaction.",'gateway_load',SHOPP_TRXN_ERR));
				return false;
			}

			// Use an external checkout payment gateway
			$gateway_meta = $this->scan_gateway_meta($gateway);
			$ProcessorClass = $gateway_meta->tags['class'];
			$Payment = new $ProcessorClass();
			$Purchase = $Payment->process();
			
			if (!$Purchase) {
				$Payment->error();
				return false;
			}
			
		} else {
			$Order = $Shopp->Cart->data->Order;
			$Order->Totals = $Shopp->Cart->data->Totals;
			$Order->Items = $Shopp->Cart->contents;
			$Order->Cart = $Shopp->Cart->session;

			// Use payment gateway set in payment settings
			$gateway = $Shopp->Settings->get('payment_gateway');
			$authentication = $Shopp->Settings->get('account_system');

			if (!$gateway || !file_exists($gateway)) {
				new ShoppError(__("There was a problem loading the payment processor to complete this transaction.",'gateway_load',SHOPP_TRXN_ERR));
				return false;
			}

			// Process a transaction if the order has a cost (is not free)
			if ($Order->Totals->total > 0) {
				// Dynamically load the payment processing gateway
				$processor_data = $this->scan_gateway_meta($gateway);
				$ProcessorClass = $processor_data->tags['class'];
				include($gateway);

				$Payment = new $ProcessorClass($Order);

				// Process the transaction through the payment gateway
				$processed = $Payment->process();

				// exit();
				// There was a problem processing the transaction, 
				// grab the error response from the gateway so we can report it
				if (!$processed) {
					$Payment->error();
					return false;
				}				
				$gatewayname = $processor_data->name;
				$transactionid = $Payment->transactionid();
			} else {
				$gatewayname = __('N/A','Shopp');
				$transactionid = __('(Free Order)','Shopp');
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
				
				if (username_exists($handle))
					new ShoppError(__('The login name you provided is already in use.  Please choose another login name.','Shopp'),'login_exists',SHOPP_ERR);
				
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
			
			// If the shopper is already logged-in, save their updated customer info
			if ($Shopp->Cart->data->login && $authentication == "wordpress") {
				get_currentuserinfo();
				global $user_ID;
				$Order->Customer->wpuser = $user_ID;
			}

			// Create a WP-compatible password hash to go in the db
			if (empty($Order->Customer->id))
				$Order->Customer->password = wp_hash_password($Order->Customer->password);
			$Order->Customer->save();

			$Order->Billing->customer = $Order->Customer->id;
			$Order->Billing->card = substr($Order->Billing->card,-4);
			$Order->Billing->save();

			if (!empty($Order->Shipping->address)) {
				$Order->Shipping->customer = $Order->Customer->id;
				$Order->Shipping->save();
			}
			
			$Promos = array();
			foreach ($Shopp->Cart->data->PromosApplied as $promo)
				$Promos[$promo->id] = $promo->name;

			$Purchase = new Purchase();
			$Purchase->customer = $Order->Customer->id;
			$Purchase->billing = $Order->Billing->id;
			$Purchase->shipping = $Order->Shipping->id;
			$Purchase->data = $Order->data;
			$Purchase->promos = $Promos;
			$Purchase->copydata($Order->Customer);
			$Purchase->copydata($Order->Billing);
			$Purchase->copydata($Order->Shipping,'ship');
			$Purchase->copydata($Shopp->Cart->data->Totals);
			$Purchase->freight = $Shopp->Cart->data->Totals->shipping;
			$Purchase->gateway = $gatewayname;
			$Purchase->transactionid = $transactionid;
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
		
		// Keep the user loggedin
		$Shopp->Cart->loggedin($Order->Customer);
		
		// Save the purchase ID for later lookup
		$Shopp->Cart->data->Purchase = new Purchase($Purchase->id);
		$Shopp->Cart->data->Purchase->load_purchased();
		// $Shopp->Cart->save();
		
		// Allow other WordPress plugins access to Purchase data to extend
		// what Shopp does after a successful transaction
		do_action_ref_array('shopp_order_success',array(&$Shopp->Cart->data->Purchase));

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
		$receipt['orderid'] = $Purchase->id;
		
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
		if (strpos($gateway,"TestMode.php") !== false || isset($_GET['shopp_xco'])) $ssl = false;
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
		global $Shopp,$Orders;
		$db = DB::get();
		
		$defaults = array(
			'deleting' => false,
			'selected' => false,
			'update' => false,
			'newstatus' => false,
			'pagenum' => 1,
			'per_page' => false,
			'start' => '',
			'end' => '',
			'status' => false,
			's' => '',
			'range' => '',
			'startdate' => '',
			'enddate' => '',
		);
		
		$args = array_merge($defaults,$_GET);
		extract($args, EXTR_SKIP);
		
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.','Shopp'));

		if (isset($deleting)
						&& $deleting == "order"
						&& !empty($selected) 
						&& is_array($selected)) {
			foreach($selected as $selection) {
				$Purchase = new Purchase($selection);
				$Purchase->load_purchased();
				foreach ($Purchase->purchased as $purchased) {
					$Purchased = new Purchased($purchased->id);
					$Purchased->delete();
				}
				$Purchase->delete();
			}
		}

		$statusLabels = $this->Settings->get('order_status');
		if (empty($statusLabels)) $statusLabels = array('');

		if ($update == "order"
						&& !empty($selected) 
						&& is_array($selected)) {
			foreach($selected as $selection) {
				$Purchase = new Purchase($selection);
				$Purchase->status = $newstatus;
				$Purchase->save();
			}
		}

		$Purchase = new Purchase();
		
		$pagenum = absint( $pagenum );
		if ( empty($pagenum) )
			$pagenum = 1;
		if( !$per_page || $per_page < 0 )
			$per_page = 20;
		$start = ($per_page * ($pagenum-1)); 
		
		
		if (!empty($start)) {
			$startdate = $start;
			list($month,$day,$year) = split("/",$startdate);
			$starts = mktime(0,0,0,$month,$day,$year);
		}
		if (!empty($end)) {
			$enddate = $end;
			list($month,$day,$year) = split("/",$enddate);
			$ends = mktime(0,0,0,$month,$day,$year);
		}
		
		$where = '';
		if ($status !== false) $where = "WHERE status='$status'";
		if (!empty($s)) $where .= ((empty($where))?"WHERE ":" AND ")." (id='$s' OR firstname LIKE '%$s%' OR lastname LIKE '%$s%' OR CONCAT(firstname,' ',lastname) LIKE '%$s%' OR transactionid LIKE '%$s%')";
		if (!empty($start) && !empty($end)) $where .= ((empty($where))?"WHERE ":" AND ").' (UNIX_TIMESTAMP(created) >= '.$starts.' AND UNIX_TIMESTAMP(created) <= '.$ends.')';
		
		$ordercount = $db->query("SELECT count(*) as total FROM $Purchase->_table $where ORDER BY created DESC");
		$query = "SELECT * FROM $Purchase->_table $where ORDER BY created DESC LIMIT $start,$per_page";
		$Orders = $db->query($query,AS_ARRAY);

		$num_pages = ceil($ordercount->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg( 'pagenum', '%#%' ),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum
		));
		
		$ranges = array(
			'all' => __('Show All Orders','Shopp'),
			'today' => __('Today','Shopp'),
			'week' => __('This Week','Shopp'),
			'month' => __('This Month','Shopp'),
			'quarter' => __('This Quarter','Shopp'),
			'year' => __('This Year','Shopp'),
			'yesterday' => __('Yesterday','Shopp'),
			'lastweek' => __('Last Week','Shopp'),
			'last30' => __('Last 30 Days','Shopp'),
			'last90' => __('Last 3 Months','Shopp'),
			'lastmonth' => __('Last Month','Shopp'),
			'lastquarter' => __('Last Quarter','Shopp'),
			'lastyear' => __('Last Year','Shopp'),
			'custom' => __('Custom Dates','Shopp')
			);
		
		$exports = array(
			'tab' => __('Tab-separated.txt','Shopp'),
			'csv' => __('Comma-separated.csv','Shopp'),
			'xls' => __('Microsoft&reg; Excel.xls','Shopp')			
			);
		
		$formatPref = $Shopp->Settings->get('purchaselog_format');
		if (!$formatPref) $formatPref = 'tab';
		
		$columns = array_merge(Purchase::exportcolumns(),Purchased::exportcolumns());
		$selected = $Shopp->Settings->get('purchaselog_columns');
		if (empty($selected)) $selected = array_keys($columns);
		
		include("{$this->basepath}/core/ui/orders/orders.php");
	}      
	
	function orders_list_columns () {
		shopp_register_column_headers('toplevel_page_shopp-orders', array(
			'cb'=>'<input type="checkbox" />',
			'order'=>__('Order','Shopp'),
			'name'=>__('Name','Shopp'),
			'destination'=>__('Destination','Shopp'),
			'total'=>__('Total','Shopp'),
			'date'=>__('Date','Shopp'))
		);
	}
	
	function order_manager () {
		global $Shopp;

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.','Shopp'));

		if (preg_match("/\d+/",$_GET['id'])) {
			$Shopp->Cart->data->Purchase = new Purchase($_GET['id']);
			$Shopp->Cart->data->Purchase->load_purchased();
		} else $Shopp->Cart->data->Purchase = new Purchase();
		
		$Purchase = $Shopp->Cart->data->Purchase;

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

		$targets = $this->Settings->get('target_markets');
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
		
		if ($Shopp->Cart->data->login) 
			$Shopp->Cart->data->Order->Customer->management();
					
		ob_start();
		if ($Shopp->Cart->data->login) include(SHOPP_TEMPLATES."/account.php");
		else include(SHOPP_TEMPLATES."/login.php");
		$content = ob_get_contents();
		ob_end_clean();
		
		return apply_filters('shopp_account_template','<div id="shopp">'.$content.'</div>');
		
	}
	
	/**
	 * Products admin flow handlers
	 **/
	function products_list($workflow=false) {
		global $Products,$Shopp;
		$db = DB::get();

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$defaults = array(
			'cat' => false,
			'pagenum' => 1,
			'per_page' => 20,
			's' => '',
			'matchcol' => ''
			);
		
		$args = array_merge($defaults,$_GET);
		extract($args,EXTR_SKIP);

		if (!$workflow) {		
			if (empty($categories)) $categories = array('');
		
			$category_table = DatabaseObject::tablename(Category::$table);
			$query = "SELECT id,name,parent FROM $category_table ORDER BY parent,name";
			$categories = $db->query($query,AS_ARRAY);
			$categories = sort_tree($categories);
			if (empty($categories)) $categories = array();
		
			$categories_menu = '<option value="">View all categories</option>';
			foreach ($categories as $category) {
				$padding = str_repeat("&nbsp;",$category->depth*3);
				if ($cat == $category->id) $categories_menu .= '<option value="'.$category->id.'" selected="selected">'.$padding.$category->name.'</option>';
				else $categories_menu .= '<option value="'.$category->id.'">'.$padding.$category->name.'</option>';
			}
		}
		
		$pagenum = absint( $pagenum );
		if ( empty($pagenum) )
			$pagenum = 1;
		if( !$per_page || $per_page < 0 )
			$per_page = 20;
		$start = ($per_page * ($pagenum-1)); 
		
		$pd = DatabaseObject::tablename(Product::$table);
		$pt = DatabaseObject::tablename(Price::$table);
		$catt = DatabaseObject::tablename(Category::$table);
		$clog = DatabaseObject::tablename(Catalog::$table);

		$orderby = "pd.created DESC";
		
		$where = "true";
		if (!empty($cat)) $where .= " AND cat.id='$cat' AND (clog.category != 0 OR clog.id IS NULL)";
		if (!empty($s)) {
			if (strpos($s,"sku:") !== false) { // SKU search
				$where .= ' AND pt.sku="'.substr($s,4).'"';
				$orderby = "pd.name";
			} else {                                   // keyword search
				$interference = array("'s","'",".","\"");
				$search = preg_replace('/(\s?)(\w+)(\s?)/','\1*\2*\3',str_replace($interference,"", stripslashes($s)));
				$match = "MATCH(pd.name,pd.summary,pd.description) AGAINST ('$search' IN BOOLEAN MODE)";
				$where .= " AND $match";
				$matchcol = ", $match AS score";
				$orderby = "score DESC";         
			}
		}
		
		// Get total product count, taking into consideration for filtering
		if (!empty($s)) $query = "SELECT count($match) as total FROM $pd AS pd LEFT JOIN $pt AS pt ON pd.id=pt.product AND pt.type != 'N/A' LEFT JOIN $clog AS clog ON pd.id=clog.product LEFT JOIN $catt AS cat ON cat.id=clog.category WHERE $where GROUP BY pd.id";
		elseif (!empty($cat)) $query = "SELECT count(*) as total $matchcol FROM $pd AS pd LEFT JOIN $clog AS clog ON pd.id=clog.product LEFT JOIN $catt AS cat ON cat.id=clog.category WHERE (clog.category != 0 OR clog.id IS NULL) AND $where";
		else $query = "SELECT count(*) as total $matchcol FROM $pd WHERE $where";
		$productcount = $db->query($query);
		
		$columns = "pd.id,pd.name,pd.slug,pd.featured,GROUP_CONCAT(DISTINCT cat.name ORDER BY cat.name SEPARATOR ', ') AS categories, MAX(pt.price) AS maxprice,MIN(pt.price) AS minprice,IF(pt.inventory='on','on','off') AS inventory,ROUND(SUM(pt.stock)/count(DISTINCT clog.id),0) AS stock";
		if ($workflow) $columns = "pd.id";
		// Load the products
		$query = "SELECT $columns $matchcol FROM $pd AS pd LEFT JOIN $pt AS pt ON pd.id=pt.product AND pt.type != 'N/A' LEFT JOIN $clog AS clog ON pd.id=clog.product LEFT JOIN $catt AS cat ON cat.id=clog.category WHERE $where GROUP BY pd.id ORDER BY $orderby LIMIT $start,$per_page";
		$Products = $db->query($query,AS_ARRAY);

		$num_pages = ceil($productcount->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg(array("edit"=>null,'pagenum' => '%#%')),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum,
		));
		
		if ($workflow) return $Products;
		
		include("{$this->basepath}/core/ui/products/products.php");
	}

	function products_list_columns () {
		shopp_register_column_headers('shopp_page_shopp-products', array(
			'cb'=>'<input type="checkbox" />',
			'name'=>__('Name','Shopp'),
			'category'=>__('Category','Shopp'),
			'price'=>__('Price','Shopp'),
			'inventory'=>__('Inventory','Shopp'),
			'featured'=>__('Featured','Shopp'))
		);
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
		
		return '<div id="shopp">'.$Shopp->Catalog->tag('product',$atts).'<div class="clear"></div></div>';
	}
	
	function category_shortcode ($atts) {
		global $Shopp;
		
		$tag = 'category';
		if (isset($atts['name'])) {
			$Shopp->Category = new Category($atts['name'],'name');
			unset($atts['name']);
		} elseif (isset($atts['slug'])) {
			switch ($atts['slug']) {
				case SearchResults::$_slug: $tag = 'search-products'; unset($atts['slug']);
				 break;
				case TagProducts::$_slug: $tag = 'tag-products'; unset($atts['slug']);
				 break;
				case BestsellerProducts::$_slug: $tag = 'bestseller-products'; unset($atts['slug']);
				 break;
				case NewProducts::$_slug: $tag = 'new-products'; unset($atts['slug']);
				 break;
				case FeaturedProducts::$_slug: $tag = 'featured-products'; unset($atts['slug']);
				 break;
				case OnSaleProducts::$_slug: $tag = 'onsale-products'; unset($atts['slug']);
				 break;
				case RandomProducts::$_slug: $tag = 'random-products'; unset($atts['slug']);
				 break;
			}
		} elseif (isset($atts['id'])) {
			$Shopp->Category = new Category($atts['id']);
			unset($atts['id']);
		} else return "";
		
		return '<div id="shopp">'.$Shopp->Catalog->tag($tag,$atts).'<div class="clear"></div></div>';
		
	}
	
	function maintenance_shortcode ($atts) {
		return '<div id="shopp" class="update"><p>The store is currently down for maintenance.  We\'ll be back soon!</p><div class="clear"></div></div>';
	}
	
	function product_editor_ui () {
		global $Shopp;
		include("{$this->basepath}/core/ui/products/ui.php");
	}

	function product_editor() {
		global $Shopp;
		$db = DB::get();

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if (empty($Shopp->Product)) {
			$Product = new Product();
			$Product->published = "on";
		} else $Product = $Shopp->Product;
		
		// $Product->load_data(array('images'));
		// echo "<pre>"; print_r($Product->imagesets); echo "</pre>";
		
		$permalink = $Shopp->shopuri;

		require_once("{$this->basepath}/core/model/Asset.php");
		require_once("{$this->basepath}/core/model/Category.php");

		$Price = new Price();
		$priceTypes = array(
			array('value'=>'Shipped','label'=>__('Shipped','Shopp')),
			array('value'=>'Download','label'=>__('Download','Shopp')),
			array('value'=>'Donation','label'=>__('Donation','Shopp')),
			array('value'=>'N/A','label'=>__('Disabled','Shopp')),
		);
		
		$workflows = array(
			"continue" => __('Continue Editing','Shopp'),
			"close" => __('Products Manager','Shopp'),
			"new" => __('New Product','Shopp'),
			"next" => __('Edit Next','Shopp'),
			"previous" => __('Edit Previous','Shopp')
			);
		
		$taglist = array();
		foreach ($Product->tags as $tag) $taglist[] = $tag->name;

		if ($Product->id) {
			$Assets = new Asset();
			$Images = $db->query("SELECT id,src,properties FROM $Assets->_table WHERE context='product' AND parent=$Product->id AND datatype='thumbnail' ORDER BY sortorder",AS_ARRAY);
			unset($Assets);			
		}

		$shiprates = $this->Settings->get('shipping_rates');
		if (!empty($shiprates)) ksort($shiprates);

		$process = (!empty($Product->id)?$Product->id:'new');
		$_POST['action'] = add_query_arg(array_merge($_GET,array('page'=>$this->Admin->products)),$Shopp->wpadminurl."admin.php");
		
		include("{$this->basepath}/core/ui/products/editor.php");

	}

	function save_product($Product) {
		global $Shopp;
		$db = DB::get();
		check_admin_referer('shopp-save-product');

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$this->settings_save(); // Save workflow setting

		if (!$_POST['options']) $Product->options = array();
		else $_POST['options'] = stripslashes_deep($_POST['options']);
		if (empty($Product->slug)) $_POST['slug'] = sanitize_title_with_dashes($_POST['name']);

		// Check for an existing product slug
		$existing = $db->query("SELECT slug FROM $Product->_table WHERE slug='$Product->slug' AND id != $Product->id LIMIT 1");
		if ($existing) {
			$suffix = 2;
			while($existing) {
				$altslug = substr($Product->slug, 0, 200-(strlen($suffix)+1)). "-$suffix";
				$existing = $db->query("SELECT slug FROM $Product->_table WHERE slug='$altslug' AND id != $Product->id LIMIT 1");
				$suffix++;
			}
			$Product->slug = $altslug;
		}
		
		if (isset($_POST['content'])) $_POST['description'] = $_POST['content'];
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
			foreach($_POST['imagedetails'] as $i => $data) {
				$Image = new Asset();
				unset($Image->_datatypes['data'],$Image->data);
				$Image->load($data['id']);
				$Image->properties['title'] = $data['title'];
				$Image->properties['alt'] = $data['alt'];
				$Image->save();
			}
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
		$File->properties = array("mimetype" => file_mimetype($_FILES['Filedata']['tmp_name'],$File->name));
		$File->data = addslashes(file_get_contents($_FILES['Filedata']['tmp_name']));
		$File->save();
		unset($File->data); // Remove file contents from memory
		
		do_action('add_product_download',$File,$_FILES['Filedata']);
		
		echo json_encode(array("id"=>$File->id,"name"=>$File->name,"type"=>$File->properties['mimetype'],"size"=>$File->size));
	}
	
	function add_images () {
			$QualityValue = array(100,92,80,70,60);
			
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
			$SmallSizing->UnsharpMask(75);
			$Small->data = addslashes($SmallSizing->imagefile($QualityValue[$SmallSettings['quality']]));
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
				// case "0": $ThumbnailSizing->scaleToWidth($ThumbnailSettings['width']); break;
				// case "1": $ThumbnailSizing->scaleToHeight($ThumbnailSettings['height']); break;
				case "0": $ThumbnailSizing->scaleToFit($ThumbnailSettings['width'],$ThumbnailSettings['height']); break;
				case "1": $ThumbnailSizing->scaleCrop($ThumbnailSettings['width'],$ThumbnailSettings['height']); break;
			}
			$ThumbnailSizing->UnsharpMask();
			$Thumbnail->data = addslashes($ThumbnailSizing->imagefile($QualityValue[$ThumbnailSettings['quality']]));
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
	function categories_list ($workflow=false) {
		global $Shopp;
		$db = DB::get();

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$defaults = array(
			'pagenum' => 1,
			'per_page' => 20,
			's' => ''
			);
		$args = array_merge($defaults,$_GET);
		extract($args,EXTR_SKIP);

		$pagenum = absint( $pagenum );
		if ( empty($pagenum) )
			$pagenum = 1;
		if( !$per_page || $per_page < 0 )
			$per_page = 20;
		$start = ($per_page * ($pagenum-1)); 
		
		$filters = array();
		// $filters['limit'] = "$start,$per_page";
		if (!empty($s)) 
			$filters['where'] = "cat.name LIKE '%$s%'";
		else $filters['where'] = "true";
		
		$table = DatabaseObject::tablename(Category::$table);
		$Catalog = new Catalog();
		$Catalog->outofstock = true;
		if ($workflow) {
			$filters['columns'] = "cat.id,cat.parent";
			$results = $Catalog->load_categories($filters,false,true);
			return array_slice($results,$start,$per_page);
		} else {
			$filters['columns'] = "cat.id,cat.parent,cat.name,cat.description,cat.uri,cat.slug,cat.spectemplate,cat.facetedmenus,count(DISTINCT pd.id) AS total";
			
			$Catalog->load_categories($filters);
			$Categories = array_slice($Catalog->categories,$start,$per_page);
		}

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

	function categories_list_columns () {
		shopp_register_column_headers('shopp_page_shopp-categories', array(
			'cb'=>'<input type="checkbox" />',
			'name'=>__('Name','Shopp'),
			'description'=>__('Description','Shopp'),
			'links'=>__('Products','Shopp'),
			'templates'=>__('Templates','Shopp'),
			'menus'=>__('Menus','Shopp'))
		);
	}

	function category_editor_ui () {
		global $Shopp;
		include("{$this->basepath}/core/ui/categories/ui.php");
	}
	
	function category_editor () {
		global $Shopp;
		$db = DB::get();
		
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if (empty($Shopp->Category)) $Category = new Category();
		else $Category = $Shopp->Category;

		$Price = new Price();
		$priceTypes = array(
			array('value'=>'Shipped','label'=>__('Shipped','Shopp')),
			array('value'=>'Download','label'=>__('Download','Shopp')),
			array('value'=>'Donation','label'=>__('Donation','Shopp')),
			array('value'=>'N/A','label'=>__('N/A','Shopp')),
		);

		
		// Build permalink for slug editor
		$permalink = trailingslashit($Shopp->link('catalog'))."category/";
		if (!empty($Category->slug))
			$permalink .= substr($Category->uri,0,strpos($Category->uri,$Category->slug));
		
		$pricerange_menu = array(
			"disabled" => __('Price ranges disabled','Shopp'),
			"auto" => __('Build price ranges automatically','Shopp'),
			"custom" => __('Use custom price ranges','Shopp'),
		);
		
		$Images = array();
		if (!empty($Category->id)) {
			$asset_table = DatabaseObject::tablename(Asset::$table);
			$Images = $db->query("SELECT id,src,properties FROM $asset_table WHERE context='category' AND parent=$Category->id AND datatype='thumbnail' ORDER BY sortorder",AS_ARRAY);
		}
		
		$categories_menu = $this->category_menu($Category->parent,$Category->id);
		$categories_menu = '<option value="0" rel="-1,-1">'.__('Parent Category','Shopp').'&hellip;</option>'.$categories_menu;
		
		$workflows = array(
			"continue" => __('Continue Editing','Shopp'),
			"close" => __('Categories Manager','Shopp'),
			"new" => __('New Category','Shopp'),
			"next" => __('Edit Next','Shopp'),
			"previous" => __('Edit Previous','Shopp')
			);
		
		include("{$this->basepath}/core/ui/categories/category.php");
	}
	
	function save_category ($Category) {
		global $Shopp;
		$db = DB::get();
		check_admin_referer('shopp-save-category');
		
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));
		
		$this->settings_save(); // Save workflow setting
		
		$Shopp->Catalog = new Catalog();
		$Shopp->Catalog->load_categories(array('where'=>'true'));
		
		if (!isset($_POST['slug']) && empty($Category->slug))
			$Category->slug = sanitize_title_with_dashes($_POST['name']);
		if (isset($_POST['slug'])) unset($_POST['slug']);

		// Work out pathing
		$paths = array();
		if (!empty($Category->slug)) $paths = array($Category->slug);  // Include self
		
		$parentkey = -1;
		// If we're saving a new category, lookup the parent
		if ($_POST['parent'] > 0) {
			array_unshift($paths,$Shopp->Catalog->categories[$_POST['parent']]->slug);
			$parentkey = $Shopp->Catalog->categories[$_POST['parent']]->parent;
		}

		while ($category_tree = $Shopp->Catalog->categories[$parentkey]) {
			array_unshift($paths,$category_tree->slug);
			$parentkey = $category_tree->parent;
		}

		if (count($paths) > 1) $_POST['uri'] = join("/",$paths);
		else $_POST['uri'] = $paths[0];
					
		if (!empty($_POST['deleteImages'])) {			
			$deletes = array();
			if (strpos($_POST['deleteImages'],","))	$deletes = split(',',$_POST['deleteImages']);
			else $deletes = array($_POST['deleteImages']);
			$Category->delete_images($deletes);
		}

		if (!empty($_POST['images']) && is_array($_POST['images'])) {
			$Category->link_images($_POST['images']);
			$Category->save_imageorder($_POST['images']);
			foreach($_POST['imagedetails'] as $i => $data) {
				$Image = new Asset();
				unset($Image->_datatypes['data'],$Image->data);
				$Image->load($data['id']);
				$Image->properties['title'] = $data['title'];
				$Image->properties['alt'] = $data['alt'];
				$Image->save();
			}
		}

		// Variation price templates
		if (!empty($_POST['price']) && is_array($_POST['price'])) {
			foreach ($_POST['price'] as &$pricing) {
				$pricing['price'] = floatvalue($pricing['price']);
				$pricing['saleprice'] = floatvalue($pricing['saleprice']);
				$pricing['shipfee'] = floatvalue($pricing['shipfee']);
			}
			$Category->prices = stripslashes_deep($_POST['price']);
		}

		if (empty($_POST['specs'])) $Category->specs = array();
		if (empty($_POST['options'])) $Category->options = array();
		else $_POST['options'] = stripslashes_deep($_POST['options']);
		if (isset($_POST['content'])) $_POST['description'] = $_POST['content'];
		
		$Category->updates($_POST);
		$Category->save();
		
		$updated = '<strong>'.$Category->name.'</strong> '.__('category saved.','Shopp');
		
	}
		
	function category_menu ($selection=false,$current=false) {
		$db = DB::get();
		$table = DatabaseObject::tablename(Category::$table);			
		$categories = $db->query("SELECT id,name,parent FROM $table ORDER BY parent,name",AS_ARRAY);
		$categories = sort_tree($categories);

		$options = '';
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
		
		$products[0] = __("Select a product&hellip;","Shopp");
		foreach ($results as $result) $products[$result->id] = $result->name;
		return menuoptions($products,0,true);
		
	}
	
	function promotions_list () {
		global $Shopp;
		$db = DB::get();

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		require_once("{$this->basepath}/core/model/Promotion.php");
		
		$defaults = array(
			'deleting' => false,
			'delete' => false,
			'pagenum' => 1,
			'per_page' => 20,
			's' => ''
			);
			
		$args = array_merge($defaults,$_GET);
		extract($args,EXTR_SKIP);
		
		if ($deleting == "promotion"
				&& !empty($delete) 
				&& is_array($delete)) {
			foreach($delete as $deletion) {
				$Promotion = new Promotion($deletion);
				$Promotion->delete();
			}
		}
		
		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-save-promotion');

			if ($_POST['id'] != "new") {
				$Promotion = new Promotion($_POST['id']);
			} else $Promotion = new Promotion();

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
			
		}
		
		$pagenum = absint( $pagenum );
		if ( empty($pagenum) )
			$pagenum = 1;
		if( !$per_page || $per_page < 0 )
			$per_page = 20;
		$start = ($per_page * ($pagenum-1)); 
		
		
		$where = "";
		if (!empty($s)) $where = "WHERE name LIKE '%$s%'";
		
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

	function promotions_list_columns () {
		shopp_register_column_headers('shopp_page_shopp-promotions', array(
			'cb'=>'<input type="checkbox" />',
			'name'=>__('Name','Shopp'),
			'discount'=>__('Discount','Shopp'),
			'applied'=>__('Applied To','Shopp'),
			'eff'=>__('Status','Shopp'))
		);
	}

	function promotion_editor_ui () {
		global $Shopp;
		include("{$this->basepath}/core/ui/promotions/ui.php");
	}
	
	function promotion_editor () {
		global $Shopp;

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		require_once("{$this->basepath}/core/model/Promotion.php");

		if ($_GET['id'] != "new") {
			$Promotion = new Promotion($_GET['id']);
		} else $Promotion = new Promotion();
		
		$scopes = array(
			'Catalog' => __('Catalog','Shopp'),
			'Order' => __('Order','Shopp')
		);
		
		$types = array(
			'Percentage Off' => __('Percentage Off','Shopp'),
			'Amount Off' => __('Amount Off','Shopp'),
			'Free Shipping' => __('Free Shipping','Shopp'),
			'Buy X Get Y Free' => __('Buy X Get Y Free','Shopp')			
		);
				
		include("{$this->basepath}/core/ui/promotions/editor.php");
	}
	
	/**
	 * Dashboard Widgets
	 */
	function dashboard_stats ($args=null) {
		global $Shopp;
		$db = DB::get();
		$defaults = array(
			'before_widget' => '',
			'before_title' => '',
			'widget_name' => '',
			'after_title' => '',
			'after_widget' => ''
		);
		if (!$args) $args = array();
		$args = array_merge($defaults,$args);
		if (!empty($args)) extract( $args, EXTR_SKIP );

		echo $before_widget;

		echo $before_title;
		echo $widget_name;
		echo $after_title;
		
		$purchasetable = DatabaseObject::tablename(Purchase::$table);

		$results = $db->query("SELECT count(id) AS orders, SUM(total) AS sales, AVG(total) AS average,
		 						SUM(IF(UNIX_TIMESTAMP(created) > UNIX_TIMESTAMP()-(86400*30),1,0)) AS wkorders,
								SUM(IF(UNIX_TIMESTAMP(created) > UNIX_TIMESTAMP()-(86400*30),total,0)) AS wksales,
								AVG(IF(UNIX_TIMESTAMP(created) > UNIX_TIMESTAMP()-(86400*30),total,null)) AS wkavg
		 						FROM $purchasetable");

		$orderscreen = add_query_arg('page',$this->Admin->orders,$Shopp->wpadminurl."admin.php");
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
		global $Shopp;
		$db = DB::get();
		$defaults = array(
			'before_widget' => '',
			'before_title' => '',
			'widget_name' => '',
			'after_title' => '',
			'after_widget' => ''
		);
		if (!$args) $args = array();
		$args = array_merge($defaults,$args);
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
			echo '<td><a class="row-title" href="'.add_query_arg(array('page'=>$this->Admin->orders,'id'=>$Order->id),$Shopp->wpadminurl."admin.php").'" title="View &quot;Order '.$Order->id.'&quot;">'.((empty($Order->firstname) && empty($Order->lastname))?'(no contact name)':$Order->firstname.' '.$Order->lastname).'</a></td>';
			echo '<td>'.date("Y/m/d",mktimestamp($Order->created)).'</td>';
			echo '<td class="num">'.$Order->items.'</td>';
			echo '<td class="num">'.money($Order->total).'</td>';
			echo '<td class="num">'.$statusLabels[$Order->status].'</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		} else {
			echo '<p>'.__('No orders, yet.','Shopp').'</p>';
		}

		echo $after_widget;
		
	}
	
	function dashboard_products ($args=null) {
		global $Shopp;
		$db = DB::get();
		$defaults = array(
			'before_widget' => '',
			'before_title' => '',
			'widget_name' => '',
			'after_title' => '',
			'after_widget' => ''
		);
		
		if (!$args) $args = array();
		$args = array_merge($defaults,$args);
		if (!empty($args)) extract( $args, EXTR_SKIP );

		echo $before_widget;

		echo $before_title;
		echo $widget_name;
		echo $after_title;

		$RecentBestsellers = new BestsellerProducts(array('where'=>'UNIX_TIMESTAMP(pur.created) > UNIX_TIMESTAMP()-(86400*30)','show'=>3));
		$RecentBestsellers->load_products();

		echo '<table><tbody><tr>';
		echo '<td><h4>Recent Bestsellers</h4>';
		echo '<ul>';
		foreach ($RecentBestsellers->products as $product) 
			echo '<li><a href="'.add_query_arg(array('page'=>$this->Admin->editproduct,'id'=>$product->id),$Shopp->wpadminurl."admin.php").'">'.$product->name.'</a> ('.$product->sold.')</li>';
		echo '</ul></td>';
		
		
		$LifetimeBestsellers = new BestsellerProducts(array('show'=>3));
		$LifetimeBestsellers->load_products();
		echo '<td><h4>Lifetime Bestsellers</h4>';
		echo '<ul>';
		foreach ($LifetimeBestsellers->products as $product) 
			echo '<li><a href="'.add_query_arg(array('page'=>$this->Admin->editproduct,'id'=>$product->id),$Shopp->wpadminurl."admin.php").'">'.$product->name.'</a> ('.$product->sold.')</li>';
		echo '</ul></td>';
		echo '</tr></tbody></table>';
		echo $after_widget;
		
	}
		
	/**
	 * Settings flow handlers
	 **/
	
	function settings_general () {
		global $Shopp;
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$country = (isset($_POST['settings']))?$_POST['settings']['base_operations']['country']:'';
		$countries = array();
		$countrydata = $Shopp->Settings->get('countries');
		foreach ($countrydata as $iso => $c) {
			if (isset($_POST['settings']) && $_POST['settings']['base_operations']['country'] == $iso) 
				$base_region = $c['region'];
			$countries[$iso] = $c['name'];
		}
		
		if (!empty($_POST['setup'])) {
			$_POST['settings']['display_welcome'] = "off";
			$this->settings_save();
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

		$operations = $Shopp->Settings->get('base_operations');
		if (!empty($operations['zone'])) {
			$zones = $Shopp->Settings->get('zones');
			$zones = $zones[$operations['country']];
		}
		
		$targets = $Shopp->Settings->get('target_markets');
		if (!$targets) $targets = array();
		
		$statusLabels = $Shopp->Settings->get('order_status');
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
				if (!file_exists($theme_path.DIRECTORY_SEPARATOR.$template)) {
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
		$productOrderOptions = Category::sortoptions();
		
		$orderOptions = array("ASC" => __('Order','Shopp'),
							  "DESC" => __('Reverse Order','Shopp'),
							  "RAND" => __('Shuffle','Shopp'));

		$orderBy = array("sortorder" => __('Custom arrangement','Shopp'),
						 "name" => __('File name','Shopp'),
						 "created" => __('Upload date','Shopp'));
		
		$sizingOptions = array(	__('Scale to fit','Shopp'),
								__('Scale &amp; crop','Shopp'));
								
		$qualityOptions = array(__('Highest quality, largest file size','Shopp'),
								__('Higher quality, larger file size','Shopp'),
								__('Balanced quality &amp; file size','Shopp'),
								__('Lower quality, smaller file size','Shopp'),
								__('Lowest quality, smallest file size','Shopp'));
		
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
		$next = $db->query("SELECT auto_increment as id FROM information_schema.tables WHERE table_schema=database() AND table_name='$purchasetable' LIMIT 1");

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
		$time = array(
			__('30 minutes','Shopp'), __('1 hour','Shopp'),
			__('2 hours','Shopp'),	__('3 hours','Shopp'),
			__('6 hours','Shopp'), __('12 hours','Shopp'),
			__('1 day','Shopp'), __('3 days','Shopp'),
			__('1 week','Shopp'), __('1 month','Shopp'),
			__('3 months','Shopp'),	__('6 months','Shopp'),
			__('1 year','Shopp'),
			);
								
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
							if ($key != "services")
								$_POST['settings']['shipping_rates'][$i][$key][$id] =
									preg_replace("/[^0-9\.\+]/","",$_POST['settings']['shipping_rates'][$i][$key][$id]);
						}
					}
				}
			}

			$_POST['settings']['order_shipfee'] = preg_replace("/[^0-9\.\+]/","",$_POST['settings']['order_shipfee']);
			
	 		$this->settings_save();
			$updated = __('Shipping settings saved.','Shopp');
			$Shopp->ShipCalcs = new ShipCalcs($Shopp->path);
			$rates = $Shopp->Settings->get('shipping_rates');

			$Errors = &ShoppErrors();
			foreach ((array)$rates as $method) {  
				list($ShipCalcClass,$process) = split("::",$method['method']);    
				if (isset($Shopp->ShipCalcs->modules[$ShipCalcClass]->requiresauth)
					&& $Shopp->ShipCalcs->modules[$ShipCalcClass]->requiresauth) {
						$Shopp->ShipCalcs->modules[$ShipCalcClass]->verifyauth();
						if ($Errors->exist()) $autherrors = $Errors->get();
					}
			}
			
			if (!empty($autherrors)) {
				$updated = __('Shipping settings saved but there were errors: ','Shopp');
				foreach ((array)$autherrors as $error) $updated .= '<p>'.$error->message().'</p>';
				$Errors->reset();
			}
			
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

		$gateway_dir = SHOPP_PATH.DIRECTORY_SEPARATOR."gateways".DIRECTORY_SEPARATOR;
		$payment_gateway = $this->Settings->get('payment_gateway');

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-payments');

			// Update the accepted credit card payment methods
			if (!empty($_POST['settings']['payment_gateway'])) {
				$gateway = $this->scan_gateway_meta($_POST['settings']['payment_gateway']);
				$ProcessorClass = $gateway->tags['class'];
				include_once($gateway->file);
				$Processor = new $ProcessorClass();
				$_POST['settings']['gateway_cardtypes'] = $_POST['settings'][$ProcessorClass]['cards'];
			}
			if (is_array($_POST['settings']['xco_gateways'])) {
				foreach($_POST['settings']['xco_gateways'] as $gateway) {
					if (!file_exists($gateway_dir.$gateway)) continue;
					$meta = $this->scan_gateway_meta($gateway_dir.$gateway);
					$ProcessorClass = $meta->tags['class'];
					include_once($gateway_dir.$gateway);
					$Processor = new $ProcessorClass();
				}
			}
			
			do_action('shopp_save_payment_settings');
			
			$this->settings_save();
			$payment_gateway = stripslashes($this->Settings->get('payment_gateway'));
			
			$updated = __('Shopp payments settings saved.','Shopp');
		}

		
		
		// Get all of the installed gateways
		$data = $this->settings_get_gateways();

		$gateways = array();
		$LocalProcessors = array();
		$XcoProcessors = array();
		foreach ($data as $gateway) {
			$ProcessorClass = $gateway->tags['class'];
			include_once($gateway->file);
			$processor = new $ProcessorClass();
			if (isset($processor->type) && strtolower($processor->type) == "xco") {
				$XcoProcessors[] = $processor;
			} else {
				$gateways[$gateway->file] = $gateway->name;
				$LocalProcessors[] = $processor;
			}
		}

		include(SHOPP_ADMINPATH."/settings/payments.php");
	}
	
	function settings_update () {
		global $Shopp;
		
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$ftpsupport = (function_exists('ftp_connect'))?true:false;
		
		$credentials = $this->Settings->get('ftp_credentials');		
		$updatekey = $this->Settings->get('updatekey');
		if (empty($updatekey)) 
			$updatekey = array('key' => '','type' => 'single','status' => 'deactivated');

		if (!empty($_POST['save'])) {
			$updatekey['key'] = $_POST['updatekey'];
			$_POST['settings']['updatekey'] = $updatekey;
			$this->settings_save();
		}

		if (!empty($_POST['activation'])) {
			check_admin_referer('shopp-settings-update');
			$updatekey['key'] = trim($_POST['updatekey']);
			$_POST['settings']['updatekey'] = $updatekey;

			$this->settings_save();	
			
			if ($_POST['activation'] == "Activate Key") $process = "activate-key";
			else $process = "deactivate-key";
			
			$request = array(
				"ShoppServerRequest" => $process,
				"ver" => '1.0',
				"key" => $updatekey['key'],
				"type" => $updatekey['type'],
				"site" => get_bloginfo('siteurl')
			);
			
			$response = $this->callhome($request);
			$response = split("::",$response);

			if (count($response) == 1)
				$activation = '<span class="shopp error">'.$response[0].'</span>';
			
			if ($process == "activate-key" && $response[0] == "1") {
				$updatekey['type'] = $response[1];
				$type = $updatekey['type'];
				$updatekey['key'] = $response[2];
				$updatekey['status'] = 'activated';
				$this->Settings->save('updatekey',$updatekey);
				$activation = __('This key has been successfully activated.','Shopp');
			}
			
			if ($process == "deactivate-key" && $response[0] == "1") {
				$updatekey['status'] = 'deactivated';
				if ($updatekey['type'] == "dev") $updatekey['key'] = '';
				$this->Settings->save('updatekey',$updatekey);
				$activation = __('This key has been successfully de-activated.','Shopp');
			}
		} else {
			if ($updatekey['status'] == "activated") 
				$activation = __('This key has been successfully activated.','Shopp');
			else $activation = __('Enter your Shopp upgrade key and activate it to enable easy, automatic upgrades.','Shopp');
		}
		
		$type = "text";
		if ($updatekey['status'] == "activated" && $updatekey['type'] == "dev") $type = "password";
		
		include(SHOPP_ADMINPATH."/settings/update.php");
	}
	
	function settings_system () {
		global $Shopp;
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$error = false;
		
		// Image path processing
		if (isset($_POST['settings']) && isset($_POST['settings']['image_storage_pref'])) 
			$_POST['settings']['image_storage'] = $_POST['settings']['image_storage_pref'];
		$imagepath = $this->Settings->get('image_path');
		if (isset($_POST['settings']['products_path'])) $imagepath = $_POST['settings']['image_path'];
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
		if (isset($_POST['settings']) && isset($_POST['settings']['product_storage_pref']))
		 	$_POST['settings']['product_storage'] = $_POST['settings']['product_storage_pref'];
		$productspath = $this->Settings->get('products_path');
		if (isset($_POST['settings']['products_path'])) $productspath = $_POST['settings']['products_path'];
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
			
			if (!isset($_POST['settings']['error_notifications'])) 
				$_POST['settings']['error_notifications'] = array();
			
			$this->settings_save();

			// Reinitialize Error System
			$Shopp->Cart->data->Errors = new ShoppErrors();
			$Shopp->ErrorLog = new ShoppErrorLogging($this->Settings->get('error_logging'));
			$Shopp->ErrorNotify = new ShoppErrorNotification($this->Settings->get('merchant_email'),
										$this->Settings->get('error_notifications'));

			$updated = __('Shopp system settings saved.','Shopp');
		}
		
		if (isset($_POST['resetlog'])) $Shopp->ErrorLog->reset();
		
		$notifications = $this->Settings->get('error_notifications');
		if (empty($notifications)) $notifications = array();
		
		$notification_errors = array(
			SHOPP_TRXN_ERR => __("Transaction Errors","Shopp"),
			SHOPP_AUTH_ERR => __("Login Errors","Shopp"),
			SHOPP_ADDON_ERR => __("Add-on Errors","Shopp"),
			SHOPP_COMM_ERR => __("Communication Errors","Shopp")
			);
		
		$errorlog_levels = array(
			0 => __("Disabled","Shopp"),
			SHOPP_ERR => __("General Shopp Errors","Shopp"),
			SHOPP_TRXN_ERR => __("Transaction Errors","Shopp"),
			SHOPP_AUTH_ERR => __("Login Errors","Shopp"),
			SHOPP_ADDON_ERR => __("Add-on Errors","Shopp"),
			SHOPP_COMM_ERR => __("Communication Errors","Shopp"),
			SHOPP_DB_ERR => __("Database Errors","Shopp"),
			SHOPP_PHP_ERR => __("PHP Errors","Shopp"),
			SHOPP_ALL_ERR => __("All Errors","Shopp"),
			SHOPP_DEBUG_ERR => __("Debugging Messages","Shopp")
			);
								
		$filesystems = array("db" => __("Database","Shopp"),"fs" => __("File System","Shopp"));
		
		$loading = array("shopp" => __('Load on Shopp-pages only','Shopp'),"all" => __('Load on entire site','Shopp'));
		
		if ($this->Settings->get('error_logging') > 0)
			$recentlog = $Shopp->ErrorLog->tail(500);
			
		include(SHOPP_ADMINPATH."/settings/system.php");
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
	
	function validate_addons () {
		$addons = array();

		$gateway_path = $this->basepath.DIRECTORY_SEPARATOR."gateways";		
		find_files(".php",$gateway_path,$gateway_path,$gateways);
		foreach ($gateways as $file) {
			if (in_array(basename($file),$this->coremods)) continue;
			$addons[] = md5_file($gateway_path.$file);
		}

		$shipping_path = $this->basepath.DIRECTORY_SEPARATOR."shipping";
		find_files(".php",$shipping_path,$shipping_path,$shipmods);
		foreach ($shipmods as $file) {
			if (in_array(basename($file),$this->coremods)) continue;
			$addons[] = md5_file($shipping_path.$file);
		}
		return $addons;
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
		global $Shopp,$wp_version;
		$db = DB::get();
		$log = array();

		if (!isset($_POST['update'])) die("Update Failed: Update request is invalid.  No update specified.");
		if (!isset($_POST['type'])) die("Update Failed: Update request is invalid. Update type not specified");
		if (!isset($_POST['password'])) die("Update Failed: Update request is invalid. No FTP password provided.");

		$updatekey = $this->Settings->get('updatekey');
		
		$credentials = $this->Settings->get('ftp_credentials');
		if (empty($credentials)) {
			// Try to load from WordPress settings
			$credentials = get_option('ftp_credentials');
			if (!$credentials) $credentials = array();
		}
		
		// Make sure we can connect to FTP
		$ftp = new FTPClient($credentials['hostname'],$credentials['username'],$_POST['password']);
		if (!$ftp->connected) die("ftp-failed");
		else $log[] = "Connected with FTP successfully.";
		
		// Get zip functions from WP Admin
		if (class_exists('PclZip')) $log[] = "ZIP library available.";
		else {
			@require_once(ABSPATH.'wp-admin/includes/class-pclzip.php');
			$log[] = "ZIP library loaded.";
		}
		
		// Put site in maintenance mode
		if ($this->Settings->get('maintenance') != "on") {
			$this->Settings->save("maintenance","on");
			$log[] = "Enabled maintenance mode.";
		}
		
		// Find our temporary filesystem workspace
		$tmpdir = sys_get_temp_dir();
		$log[] = "Found temp directory: $tmpdir";
		
		// Download the new version of Shopp
		$updatefile = tempnam($tmpdir,"shopp_update_");
		if (($download = fopen($updatefile, 'wb')) === false) 
			die(join("\n\n",$log)."\n\nUpdate Failed: Cannot save the Shopp update to the temporary workspace because of a write permission error.");
		
		$query = build_query_request(array(
			"ShoppServerRequest" => "download-update",
			"ver" => "1.0",
		));
		
		$data = build_query_request(array(
			"key" => $updatekey['key'],
			"core" => SHOPP_VERSION,
			"wp" => $wp_version,
			"site" => get_bloginfo('siteurl'),
			"update" => $_POST['update']
		));
		
		$connection = curl_init();
		curl_setopt($connection, CURLOPT_URL, SHOPP_HOME."?".$query); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_HEADER, 0);
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $data);
		curl_setopt($connection, CURLOPT_TIMEOUT, 20); 
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
		
		// Move old updates that still exist in $tmpdir to a new location
		if (file_exists($target.$files[0]['filename']) 
			&& is_dir($target.$files[0]['filename']))
			rename($target.$files[0]['filename'],$updatefile.'_old_update');
		
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
		switch($_POST['type']) {
			case "core":
				$results = $ftp->update($target.$files[0]['filename'],$Shopp->path);
				if (!empty($results)) die(join("\n\n",$log).join("\n\n",$results)."\n\nFTP transfer failed.");
				break;
			case "Payment Gateway":
				$results = $ftp->update($target.$files[0]['filename'],
							$Shopp->path.DIRECTORY_SEPARATOR."gateways".DIRECTORY_SEPARATOR.$files[0]['filename']);
				if (!empty($results)) die(join("\n\n",$log).join("\n\n",$results)."\n\nFTP transfer failed.");
				break;
			case "Shipping Module":
				$results = $ftp->update($target.$files[0]['filename'],
							$Shopp->path.DIRECTORY_SEPARATOR."shipping".DIRECTORY_SEPARATOR.$files[0]['filename']);
				if (!empty($results)) die(join("\n\n",$log).join("\n\n",$results)."\n\nFTP transfer failed.");
				break;
		}
				
		echo "updated"; // Report success!
		exit();
	}
		
	function upgrade () {
		global $Shopp,$table_prefix;
		$db = DB::get();
		require_once(ABSPATH.'wp-admin/includes/upgrade.php');

		// Check for the schema definition file
		if (!file_exists(SHOPP_DBSCHEMA))
		 	die("Could not upgrade the Shopp database tables because the table definitions file is missing: ".SHOPP_DBSCHEMA);
		
		// Check if development version tables exist without the WP $table_prefix
		// Remove this transitionary code in official release
		$setting = substr(DatabaseObject::tablename('setting'),strlen($table_prefix));
		$devtable = $db->query("SHOW CREATE TABLE `$setting`");
		if ($devtable->Table == $setting) {
			$this->Settings->save('data_model','');
			$devtables = array('shopp_asset', 'shopp_billing', 'shopp_cart', 'shopp_catalog', 'shopp_category', 'shopp_customer', 'shopp_discount', 'shopp_price', 'shopp_product', 'shopp_promo', 'shopp_purchase', 'shopp_purchased', 'shopp_setting', 'shopp_shipping', 'shopp_spec', 'shopp_tag');
			$renaming = "";
			foreach ($devtables as $oldtable) $renaming .= ((empty($renaming))?"":", ")."$oldtable TO $table_prefix$oldtable";
			$db->query("RENAME TABLE $renaming");
			$Shopp->Settings = new Settings();
		}

		ob_start();
		include(SHOPP_DBSCHEMA);
		$schema = ob_get_contents();
		ob_end_clean();
		
		// Update the table schema
		$tables = preg_replace('/;\s+/',';',$schema);
		dbDelta($tables);
		
		$this->setup_regions();
		$this->setup_countries();
		$this->setup_zones();
		$this->setup_areas();
		
		// Update the version number
		$settings = DatabaseObject::tablename(Settings::$table);
		$db->query("UPDATE $settings SET value='".SHOPP_VERSION." WHERE name='version'");
		$db->query("DELETE FROM $settings WHERE name='data_model' OR name='shipcalc_lastscan");
		
		return true;
	}

	function callhome ($request=array(),$data=array()) {
		$query = build_query_request($request);
		$data = build_query_request($data);
		
		$connection = curl_init(); 
		curl_setopt($connection, CURLOPT_URL, SHOPP_HOME."?".$query); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_HEADER, 0);
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $data);
		curl_setopt($connection, CURLOPT_TIMEOUT, 20); 
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1); 
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

		$this->Settings->save('show_welcome','on');	
		$this->Settings->save('display_welcome','on');	
		
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
		
		$this->Settings->save('image_storage_pref','db');
		$this->Settings->save('product_storage_pref','db');

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