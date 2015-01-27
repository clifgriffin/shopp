<?php
/**
 * Settings.php
 *
 * Settings screen controller
 *
 * @copyright Ingenesis Limited, January 2015
 * @license   GNU GPL version 3 (or later) {@see license.txt}
 * @package   Shopp/Screen/Settings
 * @version   1.0
 * @since     1.4
 **/

defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit; // Prevent direct access


class ShoppAdminSettings extends ShoppAdminPostController {

	protected $ui = 'settings';

	protected function route () {
		switch ( $this->slug() ) {
			case 'advanced':     return 'ShoppScreenAdvanced';
			case 'checkout':     return 'ShoppScreenCheckout';
			case 'downloads':    return 'ShoppScreenDownloads';
			case 'images':       return 'ShoppScreenImages';
			case 'log':          return 'ShoppScreenLog';
			case 'orders':       return 'ShoppScreenOrders';
			case 'pages':        return 'ShoppScreenPages';
			case 'payments':     return 'ShoppScreenPayments';
			case 'presentation': return 'ShoppScreenPresentation';
			case 'shipping':     return 'ShoppScreenShipping';
			case 'shiprates':    return 'ShoppScreenShippingRates';
			case 'storage':      return 'ShoppScreenStorage';
			case 'taxes':        return 'ShoppScreenTaxes';
			default:             return 'ShoppScreenSetup';
		}
	}

	protected function slug () {
		$page = strtolower($this->request('page'));
		return substr($page, strrpos($page, '-') + 1);
	}

}

class ShoppSettingsScreenController extends ShoppScreenController {

	public $template = false;

	public function title () {
		if ( isset($this->title) )
			return $this->title;
		else return ShoppAdminPages()->Page->label;
	}

	protected function ui ( $file ) {
		$template = join('/', array(SHOPP_ADMIN_PATH, $this->ui, $file));

		if ( is_readable($template) ) {
			$this->template = $template;
			return join('/', array(SHOPP_ADMIN_PATH, $this->ui, 'settings.php'));
		}

		echo '<div class="wrap shopp"><div class="icon32"></div><h2></h2></div>';
		$this->notice(Shopp::__('The requested screen was not found.'), 'error');
		do_action('shopp_admin_notices');
		return false;
	}


	/**
	 * Renders screen tabs from a given associative array
	 *
	 * The tab array uses a tab page slug as the key and the
	 * localized title as the value.
	 *
	 * @since 1.3
	 *
	 * @param array $tabs The tab map array
	 * @return void
	 **/
	protected function tabs () {

		global $plugin_page;

		$tabs = ShoppAdminPages()->tabs( $plugin_page );
		$first = current($tabs);
		$default = $first[1];

		$markup = '';
		foreach ( $tabs as $index => $entry ) {
			list($title, $tab, $parent, $icon) = $entry;
			$classes = array();
			if ( ($plugin_page == $parent && $default == $tab) || $plugin_page == $tab )
				$classes[] = 'current';
			// $markup[] = '<a href="' . add_query_arg(array('page' => $tab), admin_url('admin.php')) . '" class="' . join(' ', $classes) . '">' . $title . '</a>';

			$url = add_query_arg(array('page' => $tab), admin_url('admin.php'));
			$markup .= '<li class="' . esc_attr(join(' ', $classes)) . '"><a href="' . esc_url($url) . '">'
					. '	<div class="shopp-settings-icon ' . $icon . '"></div>'
					. '	<div class="shopp-settings-label">' . esc_html($title) . '</div>'
					. '</a></li>';
		}

		$pagehook = sanitize_key($plugin_page);
		return '<div id="shopp-settings-menu"><ul class="wp-submenu">' . apply_filters('shopp_admin_' . $pagehook . '_screen_tabs', $markup) . '</ul></div>';

	}

}

class ShoppScreenSetup extends ShoppSettingsScreenController {

	public function assets () {
		shopp_enqueue_script('setup');
		shopp_localize_script('setup', '$ss', array(
			'loading' => Shopp::__('Loading&hellip;'),
			'prompt' => Shopp::__('Select your %s&hellip;', '%s'),
		));
		shopp_enqueue_script('selectize');
	}

	public function screen () {

		if ( ! current_user_can('shopp_settings') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		// Welcome screen handling
		if ( ! empty($_POST['setup']) )
			shopp_set_setting('display_welcome', 'off');

		$countries = ShoppLookup::countries();
		$states = array();

		// Save settings
		if ( ! empty($_POST['save']) && isset($_POST['settings']) ) {
			check_admin_referer('shopp-setup');

			if ( ! isset($_POST['settings']['target_markets']) )
				asort($_POST['settings']['target_markets']);

			shopp_set_formsettings();

			if ( isset($_POST['settings']['base_locale']) ) {
				$baseop = &$_POST['settings']['base_locale'];

				if ( isset($countries[ strtoupper($baseop['country']) ]) ) { // Validate country
					$country = strtoupper($baseop['country']);
					$state = '';

					if ( ! empty($baseop['state']) ) { // Valid state
						$states = ShoppLookup::country_zones(array($country));
						if ( isset($states[ $country ][ strtoupper($baseop['state']) ]) )
							$state = strtoupper($baseop['state']);
					}

					ShoppBaseLocale()->save($country, $state);

				}

				shopp_set_setting('tax_inclusive', // Automatically set the inclusive tax setting
					( in_array($country, Lookup::country_inclusive_taxes()) ? 'on' : 'off' )
				);
			}

			$updated = __('Shopp settings saved.', 'Shopp');
		}

		$basecountry = ShoppBaseLocale()->country();
		$countrymenu = Shopp::menuoptions($countries, $basecountry, true);
		$basestates = ShoppLookup::country_zones(array($basecountry));
		$statesmenu = Shopp::menuoptions($basestates[ $basecountry ], ShoppBaseLocale()->state(), true);

		$targets = shopp_setting('target_markets');
		if ( is_array($targets) )
			$targets = array_map('stripslashes', $targets);
		if ( ! $targets ) $targets = array();

		include $this->ui('setup.php');

	}

} // class ShoppScreenSetup

class ShoppScreenOrders extends ShoppSettingsScreenController {

	public function assets () {
		shopp_enqueue_script('jquery-tmpl');
		shopp_enqueue_script('labelset');
		shopp_localize_script('labelset', '$sl', array(
			'prompt' => __('Are you sure you want to remove this order status label?','Shopp'),
		));
	}

	public function screen () {
		$Shopp = Shopp::object();

		if ( ! current_user_can('shopp_settings_checkout') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$purchasetable = ShoppDatabaseObject::tablename(ShoppPurchase::$table);
		$next = sDB::query("SELECT IF ((MAX(id)) > 0,(MAX(id)+1),1) AS id FROM $purchasetable LIMIT 1");
		$next_setting = shopp_setting('next_order_id');

		if ($next->id > $next_setting) $next_setting = $next->id;

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-setup-management');

			$next_order_id = $_POST['settings']['next_order_id'] = intval($_POST['settings']['next_order_id']);

			if ($next_order_id >= $next->id) {
				if ( sDB::query("ALTER TABLE $purchasetable AUTO_INCREMENT=" . sDB::escape($next_order_id) ) )
					$next_setting = $next_order_id;
			}


			shopp_set_formsettings();
			$this->notice(Shopp::__('Management settings saved.'), 'notice', 20);
		}

		$states = array(
			__('Map the label to an order state:','Shopp') => array_merge(array('' => ''),Lookup::txnstatus_labels())
		);
		$statusLabels = shopp_setting('order_status');
		$statesLabels = shopp_setting('order_states');
		$reasonLabels = shopp_setting('cancel_reasons');

		if (empty($reasonLabels)) $reasonLabels = array(
			__('Not as described or expected','Shopp'),
			__('Wrong size','Shopp'),
			__('Found better prices elsewhere','Shopp'),
			__('Product is missing parts','Shopp'),
			__('Product is defective or damaaged','Shopp'),
			__('Took too long to deliver','Shopp'),
			__('Item out of stock','Shopp'),
			__('Customer request to cancel','Shopp'),
			__('Item discontinued','Shopp'),
			__('Other reason','Shopp')
		);

		$promolimit = array('1','2','3','4','5','6','7','8','9','10','15','20','25');

		$lowstock = shopp_setting('lowstock_level');
		if (empty($lowstock)) $lowstock = 0;

		include $this->ui('management.php');
	}

} // class ShoppScreenOrders

class ShoppScreenShipping extends ShoppSettingsScreenController {

	public function assets () {
		shopp_enqueue_script('jquery-tmpl');
		shopp_enqueue_script('shiprates');
		shopp_localize_script( 'shiprates', '$ps', array(
			'confirm' => __('Are you sure you want to remove this shipping rate?','Shopp'),
		));
	}

	public function layout () {
		ShoppUI::register_column_headers($this->id, array(
			'name'         => Shopp::__('Name'),
			'type'         => Shopp::__('Type'),
			'destinations' => Shopp::__('Destinations'),
		));
	}

	public function screen () {
		if ( ! current_user_can('shopp_settings_shipping') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$sub = 'settings';
		$term_recount = false;
		if (shopp_setting_enabled('shipping')) $sub = 'rates';
		if ( isset($_GET['sub']) && in_array( $_GET['sub'],array_keys($this->subscreens) ) )
			$sub = $_GET['sub'];

		if (!empty($_POST['save']) && empty($_POST['module']) ) {
			check_admin_referer('shopp-settings-shipping');

			$_POST['settings']['order_shipfee'] = Shopp::floatval($_POST['settings']['order_shipfee']);

			// Recount terms when this setting changes
			if ( isset($_POST['settings']['inventory']) &&
				$_POST['settings']['inventory'] != shopp_setting('inventory')) {
				$term_recount = true;
			}

	 		shopp_set_formsettings();
			$updated = __('Shipping settings saved.','Shopp');
		}

		// Handle ship rates UI
		// if ('rates' == $sub && 'on' == shopp_setting('shipping')) return $this->shiprates();

		if ($term_recount) {
			$taxonomy = ProductCategory::$taxon;
			$terms = get_terms( $taxonomy, array('hide_empty' => 0,'fields'=>'ids') );
			if ( ! empty($terms) )
				wp_update_term_count_now( $terms, $taxonomy );
		}

		$carrierdata = Lookup::shipcarriers();
		$serviceareas = array('*', ShoppBaseLocale()->code());
		foreach ($carrierdata as $c => $record) {
			if (!in_array($record->areas,$serviceareas)) continue;
			$carriers[$c] = $record->name;
		}
		unset($carrierdata);
		$shipping_carriers = shopp_setting('shipping_carriers');
		if (empty($shipping_carriers)) $shipping_carriers = array_keys($carriers);

		$imperial = 'imperial' == ShoppBaseLocale()->units();
		$weights = $imperial ?
					array('oz' => Shopp::__('ounces (oz)'), 'lb' => Shopp::__('pounds (lbs)')) :
					array('g'  => Shopp::__('gram (g)'),    'kg' => Shopp::__('kilogram (kg)'));

		$weightsmenu = menuoptions($weights, shopp_setting('weight_unit'), true);

		$dimensions = $imperial ?
				 		array('in' => Shopp::__('inches (in)'),      'ft' => Shopp::__('feet (ft)')) :
						array('cm' => Shopp::__('centimeters (cm)'), 'm'  => Shopp::__('meters (m)'));

		$dimsmenu = menuoptions($dimensions, shopp_setting('dimension_unit'), true);

		$rates = shopp_setting('shipping_rates');
		if (!empty($rates)) ksort($rates);




		$Shopp = Shopp::object();
		$Shipping = $Shopp->Shipping;
		$Shipping->settings(); // Load all installed shipping modules for settings UIs

		$methods = $Shopp->Shipping->methods;

		$edit = false;
		if ( isset($_REQUEST['id']) ) $edit = (int)$_REQUEST['id'];

		$active = shopp_setting('active_shipping');
		if (!$active) $active = array();

		if (!empty($_GET['delete'])) {
			check_admin_referer('shopp_delete_shiprate');
			$delete = $_GET['delete'];
			$index = false;
			if (strpos($delete,'-') !== false)
				list($delete,$index) = explode('-',$delete);

			if (array_key_exists($delete,$active))  {
				if (is_array($active[$delete])) {
					if (array_key_exists($index,$active[$delete])) {
						unset($active[$delete][$index]);
						if (empty($active[$delete])) unset($active[$delete]);
					}
				} else unset($active[$delete]);
				$updated = __('Shipping method setting removed.','Shopp');

				shopp_set_setting('active_shipping',$active);
			}
		}

		if (isset($_POST['module'])) {
			check_admin_referer('shopp-settings-shiprate');

			$setting = false;
			$module = isset($_POST['module'])?$_POST['module']:false;
			$id = isset($_POST['id'])?$_POST['id']:false;

			if ($id == $module) {
				if (isset($_POST['settings'])) shopp_set_formsettings();
				/** Save shipping service settings **/
				$active[$module] = true;
				shopp_set_setting('active_shipping',$active);
				$updated = __('Shipping settings saved.','Shopp');
				// Cancel editing if saving
				if (isset($_POST['save'])) unset($_REQUEST['id']);

				$Errors = ShoppErrors();
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

			} else {
				/** Save shipping calculator settings **/

				$setting = $_POST['id'];
				if (empty($setting)) { // Determine next available setting ID
					$index = 0;
					if (is_array($active[$module])) $index = count($active[$module]);
					$setting = "$module-$index";
				}

				// Cancel editing if saving
				if (isset($_POST['save'])) unset($_REQUEST['id']);

				$setting_module = $setting; $id = 0;
				if (false !== strpos($setting,'-'))
					list($setting_module,$id) = explode('-',$setting);

				// Prevent fishy stuff from happening
				if ($module != $setting_module) $module = false;

				// Save shipping calculator settings
				$Shipper = $Shipping->get($module);
				if ($Shipper && isset($_POST[$module])) {
					$Shipper->setting($id);

					$_POST[$module]['label'] = stripslashes($_POST[$module]['label']);

					// Sterilize $values
					foreach ($_POST[$module]['table'] as $i => &$row) {
						if (isset($row['rate'])) $row['rate'] = Shopp::floatval($row['rate']);
						if (!isset($row['tiers'])) continue;

						foreach ($row['tiers'] as &$tier) {
							if (isset($tier['rate'])) $tier['rate'] = Shopp::floatval($tier['rate']);
						}
					}

					// Delivery estimates: ensure max equals or exceeds min
					ShippingFramework::sensibleestimates($_POST[$module]['mindelivery'], $_POST[$module]['maxdelivery']);

					shopp_set_setting($Shipper->setting, $_POST[$module]);
					if (!array_key_exists($module, $active)) $active[$module] = array();
					$active[$module][(int) $id] = true;
					shopp_set_setting('active_shipping', $active);
					$this->notice(Shopp::__('Shipping settings saved.'));
				}

			}
		}

		$Shipping->settings(); // Load all installed shipping modules for settings UIs

		$Shipping->ui(); // Setup setting UIs
		$installed = array();
		$shiprates = array();	// Registry for activated shipping rate modules
		$settings = array();	// Registry of loaded settings for table-based shipping rates for JS

		foreach ($Shipping->active as $name => $module) {
			if (version_compare($Shipping->modules[$name]->since,'1.2') == -1) continue; // Skip 1.1 modules, they are incompatible

			$default_name = strtolower($name);
			$fullname = $module->methods();
			$installed[$name] = $fullname;

			if ($module->ui->tables) {
				$defaults[$default_name] = $module->ui->settings();
				$defaults[$default_name]['name'] = $fullname;
				$defaults[$default_name]['label'] = __('Shipping Method','Shopp');
			}

			if (array_key_exists($name,$active)) $ModuleSetting = $active[$name];
			else continue; // Not an activated shipping module, go to the next one

			// Setup shipping service shipping rate entries and settings
			if (!is_array($ModuleSetting)) {
				$shiprates[$name] = $name;
				continue;
			}

			// Setup shipping calcualtor shipping rate entries and settings
			foreach ($ModuleSetting as $id => $m) {
				$setting = "$name-$id";
				$shiprates[$setting] = $name;

				$settings[$setting] = shopp_setting($setting);
				$settings[$setting]['id'] = $setting;
				$settings[$setting] = array_merge($defaults[$default_name],$settings[$setting]);
				if ( isset($settings[$setting]['table']) ) {
					usort($settings[$setting]['table'],array('ShippingFramework','_sorttier'));
					foreach ( $settings[$setting]['table'] as &$r ) {
						if ( isset($r['tiers']) ) usort($r['tiers'],array('ShippingFramework','_sorttier'));
					}
				}
			}

		}

		if ( isset($_REQUEST['id']) ) {
			$edit = $_REQUEST['id'];
			$id = false;
			if (strpos($edit,'-') !== false)
				list($module,$id) = explode('-',$edit);
			else $module = $edit;
			if (isset($Shipping->active[ $module ]) ) {
				$Shipper = $Shipping->get($module);
				if (!$Shipper->singular) {
					$Shipper->setting($id);
					$Shipper->initui($Shipping->modules[$module]->name); // Re-init setting UI with loaded settings
				}
				$editor = $Shipper->ui();
			}

		}

		asort($installed);

		$postcodes = ShoppLookup::postcodes();
		foreach ( $postcodes as &$postcode)
			$postcode = ! empty($postcode);

		$lookup = array(
			'regions' => array_merge(array('*' => Shopp::__('Anywhere')), ShoppLookup::regions()),
			'regionmap' => ShoppLookup::regions('id'),
			'countries' => ShoppLookup::countries(),
			'areas' => ShoppLookup::country_areas(),
			'zones' => ShoppLookup::country_zones(),
			'postcodes' => $postcodes
		);

		$ShippingTemplates = new TemplateShippingUI();
		add_action('shopp_shipping_module_settings', array($Shipping, 'templates'));


		include $this->ui('shipping.php');
	}

} // class ShoppScreenShipping

class ShoppScreenShippingRates extends ShoppScreenController {

	public function assets () {
		shopp_enqueue_script('jquery-tmpl');
		shopp_enqueue_script('shiprates');
		shopp_localize_script( 'shiprates', '$ps', array(
			'confirm' => __('Are you sure you want to remove this shipping rate?','Shopp'),
		));
	}

	public function layout () {
		ShoppUI::register_column_headers($this->id, array(
			'name'         => Shopp::__('Name'),
			'type'         => Shopp::__('Type'),
			'destinations' => Shopp::__('Destinations'),
		));
	}

	public function screen () {
		if ( ! current_user_can('shopp_settings_shipping') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$Shopp = Shopp::object();
		$Shipping = $Shopp->Shipping;
		$Shipping->settings(); // Load all installed shipping modules for settings UIs

		$methods = $Shopp->Shipping->methods;

		$edit = false;
		if ( isset($_REQUEST['id']) ) $edit = (int)$_REQUEST['id'];

		$active = shopp_setting('active_shipping');
		if (!$active) $active = array();

		if (!empty($_GET['delete'])) {
			check_admin_referer('shopp_delete_shiprate');
			$delete = $_GET['delete'];
			$index = false;
			if (strpos($delete,'-') !== false)
				list($delete,$index) = explode('-',$delete);

			if (array_key_exists($delete,$active))  {
				if (is_array($active[$delete])) {
					if (array_key_exists($index,$active[$delete])) {
						unset($active[$delete][$index]);
						if (empty($active[$delete])) unset($active[$delete]);
					}
				} else unset($active[$delete]);
				$updated = __('Shipping method setting removed.','Shopp');

				shopp_set_setting('active_shipping',$active);
			}
		}

		if (isset($_POST['module'])) {
			check_admin_referer('shopp-settings-shiprate');

			$setting = false;
			$module = isset($_POST['module'])?$_POST['module']:false;
			$id = isset($_POST['id'])?$_POST['id']:false;

			if ($id == $module) {
				if (isset($_POST['settings'])) shopp_set_formsettings();
				/** Save shipping service settings **/
				$active[$module] = true;
				shopp_set_setting('active_shipping',$active);
				$updated = __('Shipping settings saved.','Shopp');
				// Cancel editing if saving
				if (isset($_POST['save'])) unset($_REQUEST['id']);

				$Errors = ShoppErrors();
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

			} else {
				/** Save shipping calculator settings **/

				$setting = $_POST['id'];
				if (empty($setting)) { // Determine next available setting ID
					$index = 0;
					if (is_array($active[$module])) $index = count($active[$module]);
					$setting = "$module-$index";
				}

				// Cancel editing if saving
				if (isset($_POST['save'])) unset($_REQUEST['id']);

				$setting_module = $setting; $id = 0;
				if (false !== strpos($setting,'-'))
					list($setting_module,$id) = explode('-',$setting);

				// Prevent fishy stuff from happening
				if ($module != $setting_module) $module = false;

				// Save shipping calculator settings
				$Shipper = $Shipping->get($module);
				if ($Shipper && isset($_POST[$module])) {
					$Shipper->setting($id);

					$_POST[$module]['label'] = stripslashes($_POST[$module]['label']);

					// Sterilize $values
					foreach ($_POST[$module]['table'] as $i => &$row) {
						if (isset($row['rate'])) $row['rate'] = Shopp::floatval($row['rate']);
						if (!isset($row['tiers'])) continue;

						foreach ($row['tiers'] as &$tier) {
							if (isset($tier['rate'])) $tier['rate'] = Shopp::floatval($tier['rate']);
						}
					}

					// Delivery estimates: ensure max equals or exceeds min
					ShippingFramework::sensibleestimates($_POST[$module]['mindelivery'], $_POST[$module]['maxdelivery']);

					shopp_set_setting($Shipper->setting, $_POST[$module]);
					if (!array_key_exists($module, $active)) $active[$module] = array();
					$active[$module][(int) $id] = true;
					shopp_set_setting('active_shipping', $active);
					$this->notice(Shopp::__('Shipping settings saved.'));
				}

			}
		}

		$Shipping->settings(); // Load all installed shipping modules for settings UIs

		$Shipping->ui(); // Setup setting UIs
		$installed = array();
		$shiprates = array();	// Registry for activated shipping rate modules
		$settings = array();	// Registry of loaded settings for table-based shipping rates for JS

		foreach ($Shipping->active as $name => $module) {
			if (version_compare($Shipping->modules[$name]->since,'1.2') == -1) continue; // Skip 1.1 modules, they are incompatible

			$default_name = strtolower($name);
			$fullname = $module->methods();
			$installed[$name] = $fullname;

			if ($module->ui->tables) {
				$defaults[$default_name] = $module->ui->settings();
				$defaults[$default_name]['name'] = $fullname;
				$defaults[$default_name]['label'] = __('Shipping Method','Shopp');
			}

			if (array_key_exists($name,$active)) $ModuleSetting = $active[$name];
			else continue; // Not an activated shipping module, go to the next one

			// Setup shipping service shipping rate entries and settings
			if (!is_array($ModuleSetting)) {
				$shiprates[$name] = $name;
				continue;
			}

			// Setup shipping calcualtor shipping rate entries and settings
			foreach ($ModuleSetting as $id => $m) {
				$setting = "$name-$id";
				$shiprates[$setting] = $name;

				$settings[$setting] = shopp_setting($setting);
				$settings[$setting]['id'] = $setting;
				$settings[$setting] = array_merge($defaults[$default_name],$settings[$setting]);
				if ( isset($settings[$setting]['table']) ) {
					usort($settings[$setting]['table'],array('ShippingFramework','_sorttier'));
					foreach ( $settings[$setting]['table'] as &$r ) {
						if ( isset($r['tiers']) ) usort($r['tiers'],array('ShippingFramework','_sorttier'));
					}
				}
			}

		}

		if ( isset($_REQUEST['id']) ) {
			$edit = $_REQUEST['id'];
			$id = false;
			if (strpos($edit,'-') !== false)
				list($module,$id) = explode('-',$edit);
			else $module = $edit;
			if (isset($Shipping->active[ $module ]) ) {
				$Shipper = $Shipping->get($module);
				if (!$Shipper->singular) {
					$Shipper->setting($id);
					$Shipper->initui($Shipping->modules[$module]->name); // Re-init setting UI with loaded settings
				}
				$editor = $Shipper->ui();
			}

		}

		asort($installed);

		$postcodes = ShoppLookup::postcodes();
		foreach ( $postcodes as &$postcode)
			$postcode = ! empty($postcode);

		$lookup = array(
			'regions' => array_merge(array('*' => Shopp::__('Anywhere')), ShoppLookup::regions()),
			'regionmap' => ShoppLookup::regions('id'),
			'countries' => ShoppLookup::countries(),
			'areas' => ShoppLookup::country_areas(),
			'zones' => ShoppLookup::country_zones(),
			'postcodes' => $postcodes
		);

		$ShippingTemplates = new TemplateShippingUI();
		add_action('shopp_shipping_module_settings', array($Shipping, 'templates'));
		include $this->ui('shiprates.php');
	}

} // class ShoppScreenShippingRates

class ShoppScreenDownloads extends ShoppSettingsScreenController {

	public function screen () {
		if ( ! current_user_can('shopp_settings_checkout') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$downloads = array('1','2','3','5','10','15','25','100');
		$time = array(
			'1800' => Shopp::__('%d minutes', 30),
			'3600' => Shopp::__('%d hour', 1),
			'7200' => Shopp::__('%d hours', 2),
			'10800' => Shopp::__('%d hours', 3),
			'21600' => Shopp::__('%d hours', 6),
			'43200' => Shopp::__('%d hours', 12),
			'86400' => Shopp::__('%d day', 1),
			'172800' => Shopp::__('%d days', 2),
			'259200' => Shopp::__('%d days', 3),
			'604800' => Shopp::__('%d week', 1),
			'2678400' => Shopp::__('%d month', 1),
			'7952400' => Shopp::__('%d months', 3),
			'15901200' => Shopp::__('%d months', 6),
			'31536000' => Shopp::__('%d year', 1),
		);

		if ( ! empty($_POST['save']) ) {
			check_admin_referer('shopp-settings-downloads');

			shopp_set_formsettings();
			$this->notice(Shopp::__('Downloads settings saved.'), 'notice', 20);

		}

		include $this->ui('downloads.php');
	}

} // class ShoppScreenDownloads

class ShoppScreenPayments extends ShoppScreenController {

	public function assets () {
		shopp_enqueue_script('jquery-tmpl');
		shopp_enqueue_script('payments');
		shopp_localize_script( 'payments', '$ps', array(
			'confirm' => __('Are you sure you want to remove this payment system?','Shopp'),
		));
	}

	public function layout () {
		ShoppUI::register_column_headers($this->id, array(
			'name'      => Shopp::__('Name'),
			'processor' => Shopp::__('Processor'),
			'payments'  => Shopp::__('Payments'),
			'ssl'       => Shopp::__('SSL'),
			'captures'  => Shopp::__('Captures'),
			'recurring' => Shopp::__('Recurring'),
			'refunds'   => Shopp::__('Refunds'),
		));
	}

	public function screen () {
		if ( ! current_user_can('shopp_settings_payments') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$Shopp = Shopp::object();
		$Gateways = $Shopp->Gateways;

	 	$active_gateways = shopp_setting('active_gateways');
		if ( ! $active_gateways ) $gateways = array();
		else $gateways = explode(',', $active_gateways);

		$gateways = array_filter($gateways, array($Gateways, 'moduleclass'));

		if ( ! empty($_GET['delete']) ) {
			$delete = $_GET['delete'];
			check_admin_referer('shopp_delete_gateway');
			if ( in_array($delete, $gateways) )  {
				$position = array_search($delete, $gateways);
				array_splice($gateways, $position, 1);
				shopp_set_setting('active_gateways', join(',', $gateways));
			}
		}

		if ( ! empty($_POST['save']) ) {
			check_admin_referer('shopp-settings-payments');
			do_action('shopp_save_payment_settings');

			if ( isset($_POST['gateway']) ) {
				$gateway = $_POST['gateway'];

				// Handle Multi-instance payment systems
				$indexed = false;
				if ( preg_match('/\[(\d+)\]/', $gateway, $matched) ) {

					$indexed = '-' . $matched[1];
					$gateway = str_replace($matched[0], '', $gateway);

					// Merge the existing gateway settings with the newly updated settings
					if ( isset($Gateways->active[ $gateway ]) ) {
						$Gateway = $Gateways->active[ $gateway ];
						// Cannot use array_merge() because it adds numeric index values instead of overwriting them
						$_POST['settings'][ $gateway ] = (array) $_POST['settings'][ $gateway ] + (array) $Gateway->settings;
					}

				}

				if ( ! empty($gateway) && isset($Gateways->active[ $gateway ])
						&& ! in_array($gateway . $indexed, $gateways) ) {
					$gateways[] =  $gateway . $indexed;
					shopp_set_setting('active_gateways', join(',', $gateways));
				}

			} // END isset($_POST['gateway])

			shopp_set_formsettings();
			$updated = __('Shopp payments settings saved.','Shopp');
		}

		$Gateways->settings();	// Load all installed gateways for settings UIs
		do_action('shopp_setup_payments_init');

		$installed = array();
		foreach ( (array)$Gateways->modules as $slug => $module )
			$installed[$slug] = $module->name;

		$edit = false;
		$Gateways->ui();		// Setup setting UIs

		if ( isset($_REQUEST['id']) ) {
			$edit = $_REQUEST['id'];
			$gateway = $edit;
			$id = false;		// Instance ID for multi-instance gateways
			if (false !== strpos($edit,'-')) list($gateway,$id) = explode('-',$gateway);
			if (isset($Gateways->active[ $gateway ]) ) {
				$Gateway = $Gateways->get($gateway);
				if ($Gateway->multi && false === $id) {
					unset($Gateway->settings['cards'],$Gateway->settings['label']);
					$id = count($Gateway->settings);
				}
				$editor = $Gateway->ui($id);
			}
		}

		asort($installed);

		add_action('shopp_gateway_module_settings',array($Gateways,'templates'));
		include $this->ui('payments.php');
	}

} // class ShoppScreenPayments

class ShoppScreenTaxes extends ShoppSettingsScreenController {

	public function assets () {
		shopp_enqueue_script('ocupload');
		shopp_enqueue_script('jquery-tmpl');
		shopp_enqueue_script('taxrates');
		shopp_enqueue_script('suggest');
		shopp_localize_script('taxrates', '$tr', array(
			'confirm' => __('Are you sure you want to remove this tax rate?','Shopp'),
		));
	}

	public function layout () {
		ShoppUI::register_column_headers($this->id, array(
			'rate'        => Shopp::__('Rate'),
			'local'       => Shopp::__('Local Rates'),
			'conditional' => Shopp::__('Conditional'),
		));
	}

	public function screen () {

		if ( ! empty($_POST['save']) ) {
			check_admin_referer('shopp-settings-taxes');
			shopp_set_formsettings();
			$updated = __('Tax settings saved.','Shopp');
		}

		$edit = false;
		if ( isset($_REQUEST['id']) ) $edit = (int)$_REQUEST['id'];
		$localerror = false;

		$rates = shopp_setting('taxrates');
		if (!is_array($rates)) $rates = array();

		if (isset($_GET['delete'])) {
			check_admin_referer('shopp_delete_taxrate');
			$delete = (int)$_GET['delete'];
			if (isset($rates[$delete]))
				array_splice($rates,$delete,1);
			shopp_set_setting('taxrates',$rates);
		}

		if (isset($_POST['editing'])) $rates[$edit] = $_POST['settings']['taxrates'][ $edit ];
		if (isset($_POST['addrule'])) $rates[$edit]['rules'][] = array('p'=>'','v'=>'');
		if (isset($_POST['deleterule'])) {
			check_admin_referer('shopp-settings-taxrates');
			list($rateid,$row) = explode(',',$_POST['deleterule']);
			if (isset($rates[$rateid]) && isset($rates[$rateid]['rules'])) {
				array_splice($rates[$rateid]['rules'],$row,1);
				shopp_set_setting('taxrates',$rates);
			}
		}

		if (isset($rates[$edit]['haslocals']))
			$rates[$edit]['haslocals'] = ($rates[$edit]['haslocals'] == 'true' || $rates[$edit]['haslocals'] == '1');
		if (isset($_POST['add-locals'])) $rates[$edit]['haslocals'] = true;
		if (isset($_POST['remove-locals'])) {
			$rates[$edit]['haslocals'] = false;
			$rates[$edit]['locals'] = array();
		}

		$upload = $this->taxrate_upload();
		if ($upload !== false) {
			if (isset($upload['error'])) $localerror = $upload['error'];
			else $rates[$edit]['locals'] = $upload;
		}

		if (isset($_POST['editing'])) {
			// Re-sort taxes from generic to most specific
			usort($rates,array($this,'taxrates_sorting'));
			$rates = stripslashes_deep($rates);
			shopp_set_setting('taxrates',$rates);
		}
		if (isset($_POST['addrate'])) $edit = count($rates);
		if (isset($_POST['submit'])) $edit = false;

		$specials = array(ShoppTax::ALL => Shopp::__('All Markets'));

		if ( ShoppTax::euvat(false, ShoppBaseLocale()->country(), ShoppTax::EUVAT) )
			$specials[ ShoppTax::EUVAT ] = Shopp::__('European Union');

		$countries = array_merge($specials, (array)shopp_setting('target_markets'));

		$zones = Lookup::country_zones();
		/* <form action="<?php echo esc_url($this->url); ?>" id="taxrates" method="post" enctype="multipart/form-data" accept="text/plain,text/xml"> */

		include $this->ui('taxes.php');

	}

	/**
	 * Helper to sort tax rates from most specific to most generic
	 *
	 * (more specific) <------------------------------------> (more generic)
	 * more/less conditions, local taxes, country/zone, country, All Markets
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @param array $rates The tax rate settings to sort
	 * @return int The sorting value
	 **/
	public function taxrates_sorting ( $a, $b ) {

		$args = array('a' => $a, 'b' => $b);
		$scoring = array('a' => 0 ,'b' => 0);

		foreach ( $args as $key => $rate ) {
			$score = &$scoring[ $key ];

			// More conditional rules are more specific
			$score += count($rate['rules']);

			// If there are local rates add to specificity
			if ( isset($rate['haslocals']) && $rate['haslocals'] ) $score++;

			if ( isset($rate['zone']) && $rate['zone'] ) $score++;

			if ( '*' != $rate['country'] ) $score++;

			$score += $rate['rate'] / 100;
		}

		if ( $scoring['a'] == $scoring['b'] ) return 0;
		else return ( $scoring['a'] > $scoring['b'] ? 1 : -1 );
	}

	public function taxrate_upload () {
		if (!isset($_FILES['ratefile'])) return false;

		$upload = $_FILES['ratefile'];
		$filename = $upload['tmp_name'];
		if (empty($filename) && empty($upload['name']) && !isset($_POST['upload'])) return false;

		$error = false;

		if ($upload['error'] != 0) return array('error' => Lookup::errors('uploads',$upload['error']));
		if (!is_readable($filename)) return array('error' => Lookup::errors('uploadsecurity','is_readable'));
		if (empty($upload['size'])) return array('error' => Lookup::errors('uploadsecurity','is_empty'));
		if ($upload['size'] != filesize($filename)) return array('error' => Lookup::errors('uploadsecurity','filesize_mismatch'));
		if (!is_uploaded_file($filename)) return array('error' => Lookup::errors('uploadsecurity','is_uploaded_file'));

		$data = file_get_contents($upload['tmp_name']);
		$cr = array("\r\n", "\r");

		$formats = array(0=>false,3=>'xml',4=>'tab',5=>'csv');
		preg_match('/((<[^>]+>.+?<\/[^>]+>)|(.+?\t.+?[\n|\r])|(.+?,.+?[\n|\r]))/',$data,$_);
		$format = $formats[count($_)];
		if (!$format) return array('error' => __('The uploaded file is not properly formatted as an XML, CSV or tab-delimmited file.','Shopp'));

		$_ = array();
		switch ($format) {
			case 'xml':
				/*
				Example XML import file:
					<localtaxrates>
						<taxrate name="Kent">1</taxrate>
						<taxrate name="New Castle">0.25</taxrate>
						<taxrate name="Sussex">1.4</taxrate>
					</localtaxrates>

				Taxrate record format:
					<taxrate name="(Name of locality)">(Percentage of the supplemental tax)</taxrate>

				Tax rate percentages should be represented as percentage numbers, not decimal percentages:
					1.25	= 1.25%	(0.0125)
					10		= 10%	(0.1)
				*/
				if (!class_exists('xmlQuery'))
					require(SHOPP_MODEL_PATH.'/XML.php');
				$XML = new xmlQuery($data);
				$taxrates = $XML->tag('taxrate');
				while($rate = $taxrates->each()) {
					$name = $rate->attr(false,'name');
					$value = $rate->content();
					$_[$name] = $value;
				}
				break;
			case 'csv':
				ini_set('auto_detect_line_endings',true);
				if (($csv = fopen($upload['tmp_name'], 'r')) === false)
					return array('error' => Lookup::errors('uploadsecurity','is_readable'));
				while ( ($data = fgetcsv($csv, 1000)) !== false )
					$_[$data[0]] = !empty($data[1])?$data[1]:0;
				fclose($csv);
				ini_set('auto_detect_line_endings',false);
				break;
			case 'tab':
			default:
				$data = str_replace($cr,"\n",$data);
				$lines = explode("\n",$data);
				foreach ($lines as $line) {
					list($key,$value) = explode("\t",$line);
					$_[$key] = $value;
				}
		}

		if (empty($_)) array('error' => __('No useable tax rates could be found. The uploaded file may not be properly formatted.','Shopp'));

		return apply_filters('shopp_local_taxrates_upload',$_);
	}

} // class ShoppScreenTaxes

class ShoppScreenPresentation extends ShoppSettingsScreenController {

	public function screen () {

		$builtin_path = SHOPP_PATH.'/templates';
		$theme_path = sanitize_path(STYLESHEETPATH.'/shopp');

		$term_recount = false;

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-presentation');
			$updated = __('Shopp presentation settings saved.','Shopp');

			if (isset($_POST['settings']['theme_templates'])
				&& $_POST['settings']['theme_templates'] == 'on'
				&& !is_dir($theme_path)) {
					$_POST['settings']['theme_templates'] = 'off';
					$updated = __('Shopp theme templates can\'t be used because they don\'t exist.','Shopp');
			}

			if (empty($_POST['settings']['catalog_pagination']))
				$_POST['settings']['catalog_pagination'] = 0;

			// Recount terms when this setting changes
			if ( isset($_POST['settings']['outofstock_catalog']) &&
				$_POST['settings']['outofstock_catalog'] != shopp_setting('outofstock_catalog')) {
				$term_recount = true;
			}

			shopp_set_formsettings();
			$this->notice(Shopp::__('Presentation settings saved.'), 'notice', 20);
		}

		if ($term_recount) {
			$taxonomy = ProductCategory::$taxon;
			$terms = get_terms( $taxonomy, array('hide_empty' => 0,'fields'=>'ids') );
			if ( ! empty($terms) )
				wp_update_term_count_now( $terms, $taxonomy );
		}

		// Copy templates to the current WordPress theme
		if (!empty($_POST['install'])) {
			check_admin_referer('shopp-settings-presentation');
			copy_shopp_templates($builtin_path,$theme_path);
		}

		$status = 'available';
		if (!is_dir($theme_path)) $status = 'directory';
		else {
			if (!is_writable($theme_path)) $status = 'permissions';
			else {
				$builtin = array_filter(scandir($builtin_path),'filter_dotfiles');
				$theme = array_filter(scandir($theme_path),'filter_dotfiles');
				if (empty($theme)) $status = 'ready';
				else if (array_diff($builtin,$theme)) $status = 'incomplete';
			}
		}

		$category_views = array('grid' => __('Grid','Shopp'),'list' => __('List','Shopp'));
		$row_products = array(2,3,4,5,6,7);
		$productOrderOptions = ProductCategory::sortoptions();
		$productOrderOptions['custom'] = __('Custom','Shopp');

		$orderOptions = array('ASC' => __('Order','Shopp'),
							  'DESC' => __('Reverse Order','Shopp'),
							  'RAND' => __('Shuffle','Shopp'));

		$orderBy = array('sortorder' => __('Custom arrangement','Shopp'),
						 'created' => __('Upload date','Shopp'));


		include $this->ui('presentation.php');

	}

} // class ShoppScreenPresentation

class ShoppScreenPages extends ShoppScreenController {

	public function assets () {
		shopp_enqueue_script('jquery-tmpl');
		shopp_enqueue_script('pageset');
	}

	public function layout () {
		ShoppUI::register_column_headers($this->id, array(
			'title'      => Shopp::__('Title'),
			'slug'       => Shopp::__('Slug'),
			'decription' => Shopp::__('Description'),
		));
	}

	public function screen () {

		if ( ! empty($_POST['save']) ) {
			check_admin_referer('shopp-settings-pages');

			$CatalogPage = ShoppPages()->get('catalog');
			$catalog_slug = $CatalogPage->slug();
			$defaults = ShoppPages()->settings();
			$_POST['settings']['storefront_pages'] = array_merge($defaults,$_POST['settings']['storefront_pages']);
			shopp_set_formsettings();

			// Re-register page, collection, taxonomies and product rewrites
			// so that the new slugs work immediately
			$Shopp = Shopp::object();
			$Shopp->pages();
			$Shopp->collections();
			$Shopp->taxonomies();
			$Shopp->products();

			// If the catalog slug changes
			// $hardflush is false (soft flush... plenty of fiber, no .htaccess update needed)
			$hardflush = ( ShoppPages()->baseslug() != $catalog_slug );
			flush_rewrite_rules($hardflush);
		}

		$pages = ShoppPages()->settings();
		include $this->ui('pages.php');

	}

} // class ShoppScreenPages

class ShoppScreenImages extends ShoppScreenController {

	public function assets () {
		shopp_enqueue_script('jquery-tmpl');
		shopp_enqueue_script('imageset');
		shopp_localize_script( 'imageset', '$is', array(
			'confirm' => __('Are you sure you want to remove this image preset?','Shopp'),
		));
	}

	public function layout () {
		ShoppUI::register_column_headers($this->id, array(
			'cb'         => '<input type="checkbox" />',
			'name'       => Shopp::__('Name'),
			'dimensions' => Shopp::__('Dimensions'),
			'fit'        => Shopp::__('Fit'),
			'quality'    => Shopp::__('Quality'),
			'sharpness'  => Shopp::__('Sharpness'),
		));
	}

	public function screen () {

		$defaults = array(
			'paged' => 1,
			'per_page' => 25,
			'action' => false,
			'selected' => array(),
		);
		$args = array_merge($defaults,$_REQUEST);
		extract($args,EXTR_SKIP);

		$edit = false;
		if (isset($_GET['id']))  {
			$edit = (int)$_GET['id'];
			if ('new' == $_GET['id']) $edit = 'new';
		}

		if (isset($_GET['delete']) || 'delete' == $action) {
			check_admin_referer('shopp-settings-images');

			if (!empty($_GET['delete'])) $selected[] = (int)$_GET['delete'];
			$selected = array_filter($selected);
			foreach ($selected as $delete) {
				$Record = new ImageSetting( (int)$delete );
				$Record->delete();
			}
		}

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-settings-images');

			$ImageSetting = new ImageSetting($edit);
			$_POST['name'] = sanitize_title_with_dashes($_POST['name']);
			$_POST['sharpen'] = floatval(str_replace('%','',$_POST['sharpen']));
			$ImageSetting->updates($_POST);
			if (!empty($ImageSetting->name)) $ImageSetting->save();
		}

		$start = ($per_page * ($paged-1));

		$ImageSetting = new ImageSetting($edit);
		$table = $ImageSetting->_table;
		$columns = 'SQL_CALC_FOUND_ROWS *';
		$where = array(
			"type='$ImageSetting->type'",
			"context='$ImageSetting->context'"
		);
		$limit = "$start,$per_page";

		$options = compact('columns','useindex','table','joins','where','groupby','having','limit','orderby');
		$query = sDB::select($options);
		$settings = sDB::query($query,'array',array($ImageSetting,'loader'));
		$total = sDB::found();

		$num_pages = ceil($total / $per_page);
		$ListTable = ShoppUI::table_set_pagination( $this->screen, $total, $num_pages, $per_page );

		$fit_menu = $ImageSetting->fit_menu();
		$quality_menu = $ImageSetting->quality_menu();

		$actions_menu = array(
			'delete' => __('Delete','Shopp')
		);

		$json_settings = array();
		$skip = array('created','modified','numeral','context','type','sortorder','parent');
		foreach ($settings as &$Setting)
			if (method_exists($Setting,'json'))
				$json_settings[$Setting->id] = $Setting->json($skip);

		include $this->ui('images.php');

	}

} // class ShoppScreenImages

class ShoppScreenStorage extends ShoppSettingsScreenController {

	public function assets () {
		shopp_enqueue_script('jquery-tmpl');
		shopp_enqueue_script('storage');
	}

	public function layout () {
		$Shopp = Shopp::object();
		$Shopp->Storage->settings();
		$Shopp->Storage->ui();
	}

	public function screen () {

		$Shopp = Shopp::object();
		$Storage = $Shopp->Storage;
		$Storage->settings();	// Load all installed storage engines for settings UIs

		if ( ! empty($_POST['save']) ) {
			check_admin_referer('shopp-system-storage');

			shopp_set_formsettings();

			// Re-initialize Storage Engines with new settings
			$Storage->settings();

			$this->notice(Shopp::__('Shopp system settings saved.'));

		} elseif (!empty($_POST['rebuild'])) {
			$assets = ShoppDatabaseObject::tablename(ProductImage::$table);
			$query = "DELETE FROM $assets WHERE context='image' AND type='image'";
			if (sDB::query($query))
				$updated = __('All cached images have been cleared.','Shopp');
		}

		// Build the storage options menu
		$storage = $engines = $storageset = array();
		foreach ($Storage->active as $module) {
			$storage[$module->module] = $module->name;
			$engines[$module->module] = sanitize_title_with_dashes($module->module);
			$storageset[$module->module] = $Storage->get($module->module)->settings;
		}

		$Storage->ui();		// Setup setting UIs

		$ImageStorage = false;
		$DownloadStorage = false;
		if (isset($_POST['image-settings']))
			$ImageStorage = $Storage->get(shopp_setting('image_storage'));

		if (isset($_POST['download-settings']))
			$DownloadStorage = $Storage->get(shopp_setting('product_storage'));

		add_action('shopp_storage_engine_settings',array($Storage,'templates'));

		include $this->ui('storage.php');
	}

} // class ShoppScreenStorage

class ShoppScreenAdvanced extends ShoppSettingsScreenController {

	public function assets () {
		shopp_enqueue_script('colorbox');
		shopp_enqueue_script('system');
		shopp_localize_script( 'system', '$sys', array(
			'indexing' => __('Product Indexing','Shopp'),
			'indexurl' => wp_nonce_url(add_query_arg('action','shopp_rebuild_search_index',admin_url('admin-ajax.php')),'wp_ajax_shopp_rebuild_search_index')
		));
	}

	public function screen () {

		if ( ! empty($_POST['save']) ) {
			check_admin_referer('shopp-system-advanced');

			if ( ! isset($_POST['settings']['error_notifications']) )
				$_POST['settings']['error_notifications'] = array();

			shopp_set_formsettings();

			// Reinitialize Error System
			ShoppErrors()->reporting( (int)shopp_setting('error_logging') );
			ShoppErrorLogging()->loglevel( (int)shopp_setting('error_logging') );
			ShoppErrorNotification()->setup();

			if ( isset($_POST['shopp_services_plugins']) && $this->helper_installed() ) {
				add_option('shopp_services_plugins'); // Add if it doesn't exist
				update_option('shopp_services_plugins', $_POST['shopp_services_plugins']);
			}

			$this->notice(Shopp::__('Advanced settings saved.'));

		} elseif ( ! empty($_POST['rebuild']) ) {
			check_admin_referer('shopp-system-advanced');
			$assets = ShoppDatabaseObject::tablename(ProductImage::$table);
			$query = "DELETE FROM $assets WHERE context='image' AND type='image'";
			if ( sDB::query($query) )
				$this->notice(Shopp::__('All cached images have been cleared.'));

		} elseif ( ! empty($_POST['resum']) ) {
			check_admin_referer('shopp-system-advanced');
			$summaries = ShoppDatabaseObject::tablename(ProductSummary::$table);
			$query = "UPDATE $summaries SET modified='" . ProductSummary::RECALCULATE . "'";
			if ( sDB::query($query) )
				$this->notice(Shopp::__('Product summaries are set to recalculate.'));

		} elseif ( isset($_POST['shopp_services_helper']) ) {
			check_admin_referer('shopp-system-advanced');

			$plugin = 'ShoppServices.php';
			$source = SHOPP_PATH . "/core/library/$plugin";
			$install = WPMU_PLUGIN_DIR . '/' . $plugin;

			if ( false === ( $creds = request_filesystem_credentials($this->url, '', false, false, null) ) )
				return true; // stop the normal page form from displaying

			if ( ! WP_Filesystem($creds) ) { // credentials were no good, ask for them again
				request_filesystem_credentials($this->url, '', false, false, null);
				return true;
			}

			global $wp_filesystem;

			if ( 'install' == $_POST['shopp_services_helper'] ) {

				if ( ! $wp_filesystem->exists($install) ) {
					if ( $wp_filesystem->exists(WPMU_PLUGIN_DIR) || $wp_filesystem->mkdir(WPMU_PLUGIN_DIR, FS_CHMOD_DIR) ) {
						// Install the mu-plugin helper
						$wp_filesystem->copy($source, $install, true, FS_CHMOD_FILE);
					} else $this->notice(Shopp::_mi('The services helper could not be installed because the `mu-plugins` directory could not be created. Check the file permissions of the `%s` directory on the web aserver.', WP_CONTENT_DIR), 'error');
				}

				if ( $wp_filesystem->exists($install) ) {
					shopp_set_setting('shopp_services_helper', 'on');
					$this->notice(Shopp::__('Services helper installed.'));
				} else $this->notice(Shopp::__('The services helper failed to install.'), 'error');

			} elseif ( 'remove' == $_POST['shopp_services_helper'] ) {
				global $wp_filesystem;

				if ( $wp_filesystem->exists($install) )
					$wp_filesystem->delete($install);

				if ( ! $wp_filesystem->exists($install) ) {
					shopp_set_setting('shopp_services_helper', 'off');
					$this->notice(Shopp::__('Services helper uninstalled.'));
				} else {
					$this->notice(Shopp::__('Services helper could not be uninstalled.'), 'error');
				}
			}
		}

		$notifications = shopp_setting('error_notifications');
		if ( empty($notifications) ) $notifications = array();

		$notification_errors = array(
			SHOPP_TRXN_ERR  => Shopp::__('Transaction Errors'),
			SHOPP_AUTH_ERR  => Shopp::__('Login Errors'),
			SHOPP_ADDON_ERR => Shopp::__('Add-on Errors'),
			SHOPP_COMM_ERR  => Shopp::__('Communication Errors'),
			SHOPP_STOCK_ERR => Shopp::__('Inventory Warnings')
		);

		$errorlog_levels = array(
			0               => Shopp::__('Disabled'),
			SHOPP_ERR       => Shopp::__('General Shopp Errors'),
			SHOPP_TRXN_ERR  => Shopp::__('Transaction Errors'),
			SHOPP_AUTH_ERR  => Shopp::__('Login Errors'),
			SHOPP_ADDON_ERR => Shopp::__('Add-on Errors'),
			SHOPP_COMM_ERR  => Shopp::__('Communication Errors'),
			SHOPP_STOCK_ERR => Shopp::__('Inventory Warnings'),
			SHOPP_ADMIN_ERR => Shopp::__('Admin Errors'),
			SHOPP_DB_ERR    => Shopp::__('Database Errors'),
			SHOPP_PHP_ERR   => Shopp::__('PHP Errors'),
			SHOPP_ALL_ERR   => Shopp::__('All Errors'),
			SHOPP_DEBUG_ERR => Shopp::__('Debugging Messages')
		);

		$plugins = get_plugins();
		$service_plugins = get_option('shopp_services_plugins');

		include $this->ui('advanced.php');
	}

	public function helper_installed () {
		$plugins = wp_get_mu_plugins();
		foreach ( $plugins as $plugin )
			if ( false !== strpos($plugin, 'ShoppServices.php') ) return true;
		return false;
	}

	public static function install_services_helper () {
		if ( ! self::filesystemcreds() ) {

		}
	}

	protected static function filesystemcreds () {
		if ( false === ( $creds = request_filesystem_credentials($this->url, '', false, false, null) ) ) {
			return false; // stop the normal page form from displaying
		}

		if ( ! WP_Filesystem($creds) ) { // credentials were no good, ask for them again
			request_filesystem_credentials($this->url, $method, true, false, $form_fields);
			return false;
		}
		return $creds;
	}

} // class ShoppScreenAdvanced

class ShoppScreenLog extends ShoppSettingsScreenController {

	public function screen () {

		if ( isset($_POST['resetlog']) ) {
			check_admin_referer('shopp-system-log');
			ShoppErrorLogging()->reset();
			$this->notice(Shopp::__('The log file has been reset.'));
		}

		include $this->ui('log.php');

	}

} // class ShoppScreenLog