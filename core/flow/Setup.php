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

	var $screen = false;
	
	/**
	 * Setup constructor
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function __construct () {
		parent::__construct();
		
		$pages = explode("-",$_GET['page']);
		$this->screen = end($pages);
		switch ($this->screen) {
			case "taxes":
				wp_enqueue_script("suggest");
				shopp_enqueue_script('ocupload');
				shopp_enqueue_script('taxes');
				break;
			case "system":
				shopp_enqueue_script('colorbox');
				break;
			case "settings":
				shopp_enqueue_script('setup');
				break;
		}
		
	}
	
	/**
	 * Parses settings interface requests
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function admin () {
		switch($this->screen) {
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

	/**
	 * Displays the General Settings screen and processes updates
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return void
	 **/
	function general () {
		global $Shopp;
		if ( !(current_user_can('manage_options') && current_user_can('shopp_settings')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$updatekey = $Shopp->Settings->get('updatekey');
		$activated = ($updatekey[0] == "1");
		$type = "text";
		$key = $updatekey[1];
		if (isset($updatekey[2]) && $updatekey[2] == "dev") {
			$type = "password";
			$key = preg_replace('/\w/','?',$key);
		}

		$country = (isset($_POST['settings']))?$_POST['settings']['base_operations']['country']:'';
		$countries = array();
		$countrydata = Lookup::countries();
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
			$vat_countries = Lookup::vat_countries();
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
			$updated = __('Shopp settings saved.', 'Shopp');
		}

		$operations = $Shopp->Settings->get('base_operations');
		if (!empty($operations['zone'])) {
			$zones = Lookup::country_zones();
			$zones = $zones[$operations['country']];
		}
		
		$targets = $Shopp->Settings->get('target_markets');
		if (!$targets) $targets = array();
		
		$statusLabels = $Shopp->Settings->get('order_status');
		include(SHOPP_ADMIN_PATH."/settings/settings.php");
	}
	
	function presentation () {
		if ( !(current_user_can('manage_options') && current_user_can('shopp_settings_presentation')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$builtin_path = SHOPP_PATH.'/templates';
		$theme_path = sanitize_path(STYLESHEETPATH.'/shopp');

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-presentation');
			$updated = __('Shopp presentation settings saved.','Shopp');

			if (isset($_POST['settings']['theme_templates']) 
				&& $_POST['settings']['theme_templates'] == "on"
				&& !is_dir($theme_path)) {
					$_POST['settings']['theme_templates'] = "off";	
					$updated = __('Shopp theme templates can\'t be used because they don\'t exist.','Shopp');
			}
			
			if (empty($_POST['settings']['catalog_pagination']))
				$_POST['settings']['catalog_pagination'] = 0;
			$this->settings_save();
		}
		

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
		$productOrderOptions['custom'] = __('Custom','Shopp');
		
		$orderOptions = array("ASC" => __('Order','Shopp'),
							  "DESC" => __('Reverse Order','Shopp'),
							  "RAND" => __('Shuffle','Shopp'));

		$orderBy = array("sortorder" => __('Custom arrangement','Shopp'),
						 "name" => __('File name','Shopp'),
						 "created" => __('Upload date','Shopp'));
		

		include(SHOPP_ADMIN_PATH."/settings/presentation.php");
	}

	function checkout () {
		global $Shopp;
		$db =& DB::get();
		if ( !(current_user_can('manage_options') && current_user_can('shopp_settings_checkout')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$purchasetable = DatabaseObject::tablename(Purchase::$table);
		$next = $db->query("SELECT IF ((MAX(id)) > 0,(MAX(id)+1),1) AS id FROM $purchasetable LIMIT 1");
		$next_setting = $Shopp->Settings->get('next_order_id');
		
		if ($next->id > $next_setting) $next_setting = $next->id;
		
		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-checkout');
			
			$next_order_id = $_POST['settings']['next_order_id'] = intval($_POST['settings']['next_order_id']);

			if ($next_order_id >= $next->id) {
				if ($db->query("ALTER TABLE $purchasetable AUTO_INCREMENT=".$db->escape($next_order_id)))
					$next_setting = $next_order_id;
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

	/**
	 * Renders the shipping settings screen and processes updates
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function shipping () {
		global $Shopp;
		
		if ( !(current_user_can('manage_options') && current_user_can('shopp_settings_shipping')) )
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
			
			// Reload the currently active shipping modules
			$active = $Shopp->Shipping->activated();
			$Shopp->Shipping->settings();

			$Errors = &ShoppErrors();
			do_action('shopp_verify_shipping_services');
			
			if ($Errors->exist()) {
				// Get all addon related errors
				$failures = $Errors->level(SHOPP_ADDON_ERR);
				if (!empty($failures)) {
					$updated = __('Shipping settings saved but there were errors: ','Shopp');
					foreach ($failures as $error)
						$updated .= '<p>'.$error->message(true,true).'</p>';
				}
			}
			
		}
		
		$Shopp->Shipping->settings();

		$methods = $Shopp->Shipping->methods;
		$base = $Shopp->Settings->get('base_operations');
		$regions = Lookup::regions();
		$region = $regions[$base['region']];
		$useRegions = $Shopp->Settings->get('shipping_regions');

		$areas = Lookup::country_areas();
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
		if ( !(current_user_can('manage_options') && current_user_can('shopp_settings_taxes')) )
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

		
		$zones = Lookup::country_zones();
		
		include(SHOPP_ADMIN_PATH."/settings/taxes.php");
	}	

	function payments () {
		global $Shopp;
		
		if ( !(current_user_can('manage_options') && current_user_can('shopp_settings_payments')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		add_action('shopp_gateway_module_settings',array(&$this,'payments_ui'));
		
		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-payments');
			do_action('shopp_save_payment_settings');
		
			$this->settings_save();			
			$updated = __('Shopp payments settings saved.','Shopp');
		}

	 	$active_gateways = $Shopp->Settings->get('active_gateways');
		if (!$active_gateways) $gateways = array();
		else $gateways = explode(",",$active_gateways);

		include(SHOPP_ADMIN_PATH."/settings/payments.php");
	}
	
	function payments_ui () {
		global $Shopp;
		$Shopp->Gateways->settings();
		$Shopp->Gateways->ui();
	}
		
	function system () {
		global $Shopp;
		if ( !(current_user_can('manage_options') && current_user_can('shopp_settings_system')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		add_action('shopp_storage_module_settings',array(&$this,'storage_ui'));

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-system');

			if (!isset($_POST['settings']['error_notifications'])) 
				$_POST['settings']['error_notifications'] = array();
			
			$this->settings_save();

			// Reinitialize Error System
			$Shopp->Errors = new ShoppErrors($this->Settings->get('error_logging'));
			$Shopp->ErrorLog = new ShoppErrorLogging($this->Settings->get('error_logging'));
			$Shopp->ErrorNotify = new ShoppErrorNotification($this->Settings->get('merchant_email'),
										$this->Settings->get('error_notifications'));

			$updated = __('Shopp system settings saved.','Shopp');
		} elseif (!empty($_POST['rebuild'])) {
			$db =& DB::get();
			
			$assets = DatabaseObject::tablename(ProductImage::$table);
			$query = "DELETE FROM $assets WHERE context='image' AND type='image'";
			if ($db->query($query))
				$updated = __('All cached images have been cleared.','Shopp');
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
		
		// Load Storage settings
		$Shopp->Storage->settings();
		
		// Build the storage options menu
		$storage = array();
		foreach ($Shopp->Storage->active as $module)
			$storage[$module->module] = $module->name;

		$loading = array("shopp" => __('Load on Shopp-pages only','Shopp'),"all" => __('Load on entire site','Shopp'));
		
		if ($this->Settings->get('error_logging') > 0)
			$recentlog = $Shopp->ErrorLog->tail(500);
						
		include(SHOPP_ADMIN_PATH."/settings/system.php");
	}
	
	function storage_ui () {
		global $Shopp;
		$Shopp->Storage->settings();
		$Shopp->Storage->ui();
	}
	
		
	function settings_save () {
		if (empty($_POST['settings']) || !is_array($_POST['settings'])) return false;
		foreach ($_POST['settings'] as $setting => $value)
			$this->Settings->save($setting,$value);
	}	

} // END class Setup

?>