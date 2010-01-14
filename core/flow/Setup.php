<?php
/**
 * Setup
 * 
 * Flow controller for settings management
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, January 6, 2010
 * @package shopp
 * @subpackage shopp
 **/

/**
 * Setup
 *
 * @package shopp
 * @author Jonathan Davis
 **/
class Setup extends FlowController {
	
	/**
	 * Setup constructor
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function __construct () {
		parent::__construct();
	}
	
	/**
	 * Parses settings interface requests
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function admin () {
		$pages = explode("-",$_GET['page']);
		$screen = end($pages);
		switch($screen) {
			case "catalog": 		$this->catalog(); break;
			case "cart": 			$this->cart(); break;
			case "checkout": 		$this->checkout(); break;
			case "payments": 		$this->payments(); break;
			case "shipping": 		$this->shipping(); break;
			case "taxes": 			$this->taxes(); break;
			case "presentation":	$this->presentation(); break;
			case "system":			$this->system(); break;
			case "update":			$this->update(); break;
			default: 				$this->general();
		}
	}
	
	function general () {
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
			$vat_countries = $Shopp->Settings->get('vat_countries');
			$zone = $_POST['settings']['base_operations']['zone'];
			$_POST['settings']['base_operations'] = $countrydata[$_POST['settings']['base_operations']['country']];
			$_POST['settings']['base_operations']['country'] = $country;
			$_POST['settings']['base_operations']['zone'] = $zone;
			$_POST['settings']['base_operations']['currency']['format'] = 
				scan_money_format($_POST['settings']['base_operations']['currency']['format']);
			if (in_array($_POST['settings']['base_operations']['country'],$vat_countries)) 
				$_POST['settings']['base_operations']['vat'] = true;
			else $_POST['settings']['base_operations']['vat'] = false;
			
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
		include(SHOPP_ADMIN_PATH."/settings/settings.php");
	}
	
	function presentation () {
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if (isset($_POST['settings']['theme_templates']) && $_POST['settings']['theme_templates'] == "on") 
			$_POST['settings']['theme_templates'] = addslashes(sanitize_path(STYLESHEETPATH.'/'."shopp"));
		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-presentation');
			if (empty($_POST['settings']['catalog_pagination']))
				$_POST['settings']['catalog_pagination'] = 0;
			$this->settings_save();
			$updated = __('Shopp presentation settings saved.');
		}
		
		$builtin_path = SHOPP_PATH.'/'."templates";
		$theme_path = sanitize_path(STYLESHEETPATH.'/'."shopp");

		// Copy templates to the current WordPress theme
		if (!empty($_POST['install'])) {
			check_admin_referer('shopp-settings-presentation');
			copy_shopp_templates($builtin_path,$theme_path);
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
		
		include(SHOPP_ADMIN_PATH."/settings/presentation.php");
	}

	// function catalog () {
	// 	// check_admin_referer('shopp-settings-catalog');
	// 	if ( !current_user_can('manage_options') )
	// 		wp_die(__('You do not have sufficient permissions to access this page.'));
	// 
	// 	if (!empty($_POST['save'])) $this->settings_save();
	// 	include(SHOPP_ADMIN_PATH."/settings/catalog.php");
	// }

	function cart () {
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if (!empty($_POST['save'])) $this->settings_save();
		include(SHOPP_ADMIN_PATH."/settings/cart.php");
	}

	function checkout () {
		global $Shopp;
		$db =& DB::get();
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$purchasetable = DatabaseObject::tablename(Purchase::$table);
		$next = $db->query("SELECT IF ((MAX(id)) > 0,(MAX(id)+1),1) AS id FROM $purchasetable LIMIT 1");
		$next_setting = $Shopp->Settings->get('next_order_id');
		
		if ($next->id > $next_setting) $next_setting = $next->id;
		
		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-checkout');
			if ($_POST['settings']['next_order_id'] != $next->id) {
				if ($db->query("ALTER TABLE $purchasetable AUTO_INCREMENT={$_POST['settings']['next_order_id']}"))
					$next->id = $_POST['settings']['next_order_id'];
			} 
				
			$this->settings_save();
			$updated = __('Shopp checkout settings saved.','Shopp');
		}
		
		
		$downloads = array("1","2","3","5","10","15","25","100");
		$promolimit = array("1","2","3","4","5","6","7","8","9","10","15","20","25");
		$time = array(
			'1800' => __('30 minutes','Shopp'),
			'3600' => __('1 hour','Shopp'),
			'7200' => __('2 hours','Shopp'),
			'10800' => __('3 hours','Shopp'),
			'21600' => __('6 hours','Shopp'),
			'43200' => __('12 hours','Shopp'),
			'86400' => __('1 day','Shopp'),
			'172800' => __('2 days','Shopp'),
			'259200' => __('3 days','Shopp'),
			'604800' => __('1 week','Shopp'),
			'2678400' => __('1 month','Shopp'),
			'7952400' => __('3 months','Shopp'),
			'15901200' => __('6 months','Shopp'),
			'31536000' => __('1 year','Shopp'),
			);
								
		include(SHOPP_ADMIN_PATH."/settings/checkout.php");
	}

	function shipping () {
		global $Shopp;

		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));
		
		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-shipping');
			
			// Sterilize $values
			foreach ($_POST['settings']['shipping_rates'] as $i => &$method) {
				$method['name'] = stripslashes($method['name']);
				foreach ($method as $key => &$mr) {
					if (!is_array($mr)) continue;
					foreach ($mr as $id => &$v) {
						if ($v == ">" || $v == "+" || $key == "services") continue;
						$v = floatvalue($v);								
					}
				}
			}
			
			$_POST['settings']['order_shipfee'] = floatvalue($_POST['settings']['order_shipfee']);
			
	 		$this->settings_save();
			$updated = __('Shipping settings saved.','Shopp');
			$Shopp->ShipCalcs = new ShipCalcs($Shopp->path);
			$rates = $Shopp->Settings->get('shipping_rates');

			$Errors = &ShoppErrors();
			foreach ($rates as $rate) {
				$process = '';
				$ShipCalcClass = $rate['method'];
				if (strpos($rate['method'],'::') != false)
					list($ShipCalcClass,$process) = explode("::",$rate['method']);
					
				if (isset($Shopp->ShipCalcs->modules[$ShipCalcClass]->requiresauth)
					&& $Shopp->ShipCalcs->modules[$ShipCalcClass]->requiresauth) {
						$Shopp->ShipCalcs->modules[$ShipCalcClass]->verifyauth();
						if ($Errors->exist()) $autherrors = $Errors->level(SHOPP_ADDON_ERR);
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
		
		$lowstock = $Shopp->Settings->get('lowstock_level');
		if (empty($lowstock)) $lowstock = 0;
				
		include(SHOPP_ADMIN_PATH."/settings/shipping.php");
	}

	function taxes () {
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-taxes');
			$this->settings_save();
			$updated = __('Shopp taxes settings saved.','Shopp');
		}
		
		$rates = $this->Settings->get('taxrates');
		$base = $this->Settings->get('base_operations');
		
		$countries = array_merge(array('*' => __('All Markets','Shopp')),
			$this->Settings->get('target_markets'));

		
		$zones = $this->Settings->get('zones');
		
		include(SHOPP_ADMIN_PATH."/settings/taxes.php");
	}	

	function payments () {
		global $Shopp;
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$payment_gateway = gateway_path($this->Settings->get('payment_gateway'));

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-payments');

			// Update the accepted credit card payment methods
			if (!empty($_POST['settings']['payment_gateway']) 
					&& file_exists(SHOPP_GATEWAYS.$_POST['settings']['payment_gateway'])) {
				$gateway = $this->scan_gateway_meta(SHOPP_GATEWAYS.$_POST['settings']['payment_gateway']);
				$ProcessorClass = $gateway->tags['class'];
				// Load the gateway in case there are any save-time processes to be run
				$Processor = $Shopp->gateway($_POST['settings']['payment_gateway'],true);
				$_POST['settings']['gateway_cardtypes'] = $_POST['settings'][$ProcessorClass]['cards'];
			}
			if (is_array($_POST['settings']['xco_gateways'])) {
				foreach($_POST['settings']['xco_gateways'] as &$gateway) {
					$gateway = str_replace("\\","/",stripslashes($gateway));
					if (!file_exists(SHOPP_GATEWAYS.$gateway)) continue;
					$meta = $this->scan_gateway_meta(SHOPP_GATEWAYS.$gateway);
					$_POST['settings'][$ProcessorClass]['path'] = str_replace("\\","/",stripslashes($_POST['settings'][$ProcessorClass]['path']));
					$ProcessorClass = $meta->tags['class'];
					// Load the gateway in case there are any save-time processes to be run
					$Processor = $Shopp->gateway($gateway);
				}
			}
			
			do_action('shopp_save_payment_settings');

			$this->settings_save();
			$payment_gateway = stripslashes($this->Settings->get('payment_gateway'));
			
			$updated = __('Shopp payments settings saved.','Shopp');
		}

		
		// Get all of the installed gateways
		$data = settings_get_gateways();

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
				$gateways[gateway_path($gateway->file)] = $gateway->name;
				$LocalProcessors[] = $processor;
			}
		}

		include(SHOPP_ADMIN_PATH."/settings/payments.php");
	}
	
	function update () {
		global $Shopp;
		
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$ftpsupport = (function_exists('ftp_connect'))?true:false;
		
		$credentials = $this->Settings->get('ftp_credentials');	
		if (!isset($credentials['password'])) $credentials['password'] = false;
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
			
			if ($_POST['activation'] == __('Activate Key','Shopp')) $process = "activate-key";
			else $process = "deactivate-key";
			
			$request = array(
				"ShoppServerRequest" => $_POST['process'],
				"ver" => '1.0',
				"key" => $updatekey['key'],
				"type" => $updatekey['type'],
				"site" => get_bloginfo('siteurl')
			);
			
			$response = $this->callhome($request);
			$response = explode("::",$response);

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
		
		include(SHOPP_ADMIN_PATH."/settings/update.php");
	}
	
	function system () {
		global $Shopp;
		if ( !current_user_can('manage_options') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$error = false;
		chdir(WP_CONTENT_DIR);
		
		// Image path processing
		if (isset($_POST['settings']) && isset($_POST['settings']['image_storage_pref'])) 
			$_POST['settings']['image_storage'] = $_POST['settings']['image_storage_pref'];
		$imagepath = sanitize_path(realpath($this->Settings->get('image_path')));
		if (isset($_POST['settings']['products_path'])) {
			$imagepath = $_POST['settings']['image_path'];
			$imagepath = sanitize_path(realpath($imagepath));
		}
		$imagepath_status = __("File system image hosting is enabled and working.","Shopp");
		if (!file_exists($imagepath)) $error = __("The current path does not exist. Using database instead.","Shopp");
		if (!is_dir($imagepath)) $error = __("The file path supplied is not a directory. Using database instead.","Shopp");
		if (!is_writable($imagepath) || !is_readable($imagepath)) 
			$error = __("Permissions error. This path must be writable by the web server. Using database instead.","Shopp");
		if (empty($imagepath)) $error = __("Enter the absolute path starting from the root of the server file system to your image storage directory.","Shopp");
		if ($error) {
			$_POST['settings']['image_storage'] = 'db';
			$imagepath_status = '<span class="error">'.$error.'</span>';
		}

		// Product path processing
		if (isset($_POST['settings']) && isset($_POST['settings']['product_storage_pref']))
		 	$_POST['settings']['product_storage'] = $_POST['settings']['product_storage_pref'];
		$productspath = sanitize_path(realpath($this->Settings->get('products_path')));
		if (isset($_POST['settings']['products_path'])) {
			$productspath = $_POST['settings']['products_path'];
			$productspath = sanitize_path(realpath($productspath));
		}
		$error = ""; // Reset the error tracker
		$productspath_status = __("File system product file hosting is enabled and working.","Shopp");
		if (!file_exists($productspath)) $error = __("The current path does not exist. Using database instead.","Shopp");
		if (!is_dir($productspath)) $error = __("The file path supplied is not a directory. Using database instead.","Shopp");
		if (!is_writable($productspath) || !is_readable($productspath)) 
			$error = __("Permissions error. This path must be writable by the web server. Using database instead.","Shopp");
		if (empty($productspath)) $error = __("Enter the absolute path starting from the root of the server file system to your product file storage directory.","Shopp");
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
			SHOPP_COMM_ERR => __("Communication Errors","Shopp"),
			SHOPP_STOCK_ERR => __("Inventory Warnings","Shopp")
			);
		
		$errorlog_levels = array(
			0 => __("Disabled","Shopp"),
			SHOPP_ERR => __("General Shopp Errors","Shopp"),
			SHOPP_TRXN_ERR => __("Transaction Errors","Shopp"),
			SHOPP_AUTH_ERR => __("Login Errors","Shopp"),
			SHOPP_ADDON_ERR => __("Add-on Errors","Shopp"),
			SHOPP_COMM_ERR => __("Communication Errors","Shopp"),
			SHOPP_STOCK_ERR => __("Inventory Warnings","Shopp"),
			SHOPP_ADMIN_ERR => __("Admin Errors","Shopp"),
			SHOPP_DB_ERR => __("Database Errors","Shopp"),
			SHOPP_PHP_ERR => __("PHP Errors","Shopp"),
			SHOPP_ALL_ERR => __("All Errors","Shopp"),
			SHOPP_DEBUG_ERR => __("Debugging Messages","Shopp")
			);
								
		$filesystems = array("db" => __("Database","Shopp"),"fs" => __("File System","Shopp"));
		
		$loading = array("shopp" => __('Load on Shopp-pages only','Shopp'),"all" => __('Load on entire site','Shopp'));
		
		if ($this->Settings->get('error_logging') > 0)
			$recentlog = $Shopp->ErrorLog->tail(500);
			
		include(SHOPP_ADMIN_PATH."/settings/system.php");
	}	
		
	function settings_save () {
		if (empty($_POST['settings']) || !is_array($_POST['settings'])) return false;
		foreach ($_POST['settings'] as $setting => $value)
			$this->Settings->save($setting,$value);
	}
	

} // end Setup class

?>