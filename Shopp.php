<?php
/**
 * Plugin Name: Shopp
 * Plugin URI: http://shopplugin.com
 * Description: An ecommerce framework for WordPress.
 * Version: 1.3dev
 * Author: Ingenesis Limited
 * Author URI: http://ingenesis.net
 * Requires at least: 3.5
 * Tested up to: 3.5.2
 *
 *    Portions created by Ingenesis Limited are Copyright © 2008-2013 by Ingenesis Limited
 *
 *    This file is part of Shopp.
 *
 *    Shopp is free software: you can redistribute it and/or modify
 *    it under the terms of the GNU General Public License as published by
 *    the Free Software Foundation, either version 3 of the License, or
 *    (at your option) any later version.
 *
 *    Shopp is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU General Public License for more details.
 *
 *    You should have received a copy of the GNU General Public License
 *    along with Shopp.  If not, see <http://www.gnu.org/licenses/>.
 *
 **/

defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit; // Prevent direct access

if ( Shopp::services() || Shopp::unsupported() ) return; // Prevent loading the plugin

// Start up the core
$Shopp = new Shopp();
do_action('shopp_loaded');

/**
 * Shopp core plugin management class
 *
 * @author Jonathan Davis
 * @since 1.0
 * @package shopp
 **/
class Shopp {

	const VERSION = '1.3dev';
	const CODENAME = 'Mars';

	public $Settings;		// @deprecated Shopp settings registry
	public $Flow;			// @deprecated Controller routing
	public $Catalog;		// @deprecated The main catalog
	public $Category;		// @deprecated Current category
	public $Product;		// @deprecated Current product
	public $Purchase; 		// @deprecated Currently requested order receipt
	public $Shopping; 		// @deprecated The shopping session
	public $Errors;			// @deprecated Error system
	public $Order;			// @deprecated The current session Order
	public $Promotions;		// @deprecated Active promotions registry
	public $Collections;	// @deprecated Collections registry
	public $Gateways;		// @deprecated Gateway modules
	public $Shipping;		// @deprecated Shipping modules
	public $APIs;			// @deprecated Loaded API modules
	public $Storage;		// @deprecated Storage engine modules

	function __construct () {

		// Autoload system
		require 'core/Loader.php';
		ShoppLoader::includes();

		$this->constants();			// Setup Shopp constants
		$this->paths();				// Determine Shopp paths

		load_plugin_textdomain( 'Shopp', false, SHOPP_DIR . '/lang' );

		ShoppDeveloperAPI::load( SHOPP_PATH );

		// Error system
		ShoppErrors();
		ShoppErrorLogging();
		ShoppErrorNotification();
		ShoppErrorStorefrontNotices();

		// Initialize application control processing
		$this->Flow = new ShoppFlow();

		// Init deprecated properties for legacy add-on module compatibility
		$this->Shopping = ShoppShopping();
		$this->Settings = ShoppSettings();

		// Hooks
		add_action('init', array($this,'init'));

		// Core WP integration
		add_action('shopp_init', array($this,'pages'));
		add_action('shopp_init', array($this,'collections'));
		add_action('shopp_init', array($this,'taxonomies'));
		add_action('shopp_init', array($this,'products'),99);
		add_action('shopp_init', array($this,'rebuild'),99);

		add_filter('rewrite_rules_array',array($this,'rewrites'));
		add_filter('query_vars', array($this,'queryvars'));

		// Theme integration
		add_action('shopp_init', array($this, 'theme_functions'));
		add_action('widgets_init', array($this, 'widgets'));

		// Plugin management
		add_action('after_plugin_row_' . SHOPP_PLUGINFILE, array($this, 'status'), 10, 2);
		add_action('install_plugins_pre_plugin-information', array('ShoppCore', 'changelog'));

		$updates = array(
			'load-plugins', 'load-update.php','load-update-core.php', 'wp_update_plugins', 'shopp_check_updates'
		);
		foreach ( $updates as $action )
			add_action( $action, array($this, 'updates') );

		if ( ! wp_next_scheduled('shopp_check_updates') )
			wp_schedule_event(time(),'twicedaily','shopp_check_updates');

	}


	/**
	 * Initializes the Shopp runtime environment
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	function init () {
		$this->bootstrapmode = false;
		$Shopping = ShoppShopping();

		$this->Order = new ShoppOrder();
		$this->Promotions = ShoppingObject::__new('CartPromotions');
		$this->Gateways = new GatewayModules();
		$this->Shipping = new ShippingModules();
		$this->Storage = new StorageEngines();
		$this->APIs = new ShoppAPIModules();
		$this->Collections = array();

		new ShoppLogin();
		do_action('shopp_init');
	}

	function constants () {
		if ( ! defined('SHOPP_VERSION') )				define( 'SHOPP_VERSION', self::VERSION );
		if ( ! defined('SHOPP_GATEWAY_USERAGENT') )		define( 'SHOPP_GATEWAY_USERAGENT', 'WordPress Shopp Plugin/' . SHOPP_VERSION );
		if ( ! defined('SHOPP_HOME') )					define( 'SHOPP_HOME', 'https://shopplugin.com/' );
		if ( ! defined('SHOPP_CUSTOMERS') )				define( 'SHOPP_CUSTOMERS', 'http://customers.shopplugin.com/');
		if ( ! defined('SHOPP_DOCS') )					define( 'SHOPP_DOCS', SHOPP_HOME.'docs/' );

		// Helper for line break output
		if ( ! defined('BR') ) 							define('BR','<br />');

		// Overrideable config macros
		if ( ! defined('SHOPP_NOSSL') )					define('SHOPP_NOSSL',false);					// Require SSL to protect transactions, overrideable for development
		if ( ! defined('SHOPP_PREPAYMENT_DOWNLOADS') )	define('SHOPP_PREPAYMENT_DOWNLOADS',false);		// Require payment capture granting access to downloads
		if ( ! defined('SHOPP_SESSION_TIMEOUT') )		define('SHOPP_SESSION_TIMEOUT',7200);			// Sessions live for 2 hours
		if ( ! defined('SHOPP_CART_EXPIRES') )			define('SHOPP_CART_EXPIRES',1209600);			// Carts are stashed for up to 2 weeks
		if ( ! defined('SHOPP_QUERY_DEBUG') )			define('SHOPP_QUERY_DEBUG',false);				// Debugging queries is disabled by default
		if ( ! defined('SHOPP_GATEWAY_TIMEOUT') )		define('SHOPP_GATEWAY_TIMEOUT',10);				// Gateway connections timeout after 10 seconds
		if ( ! defined('SHOPP_SHIPPING_TIMEOUT') )		define('SHOPP_SHIPPING_TIMEOUT',10);			// Shipping provider connections timeout after 10 seconds
		if ( ! defined('SHOPP_TEMP_PATH') )				define('SHOPP_TEMP_PATH',sys_get_temp_dir());	// Use the system defined temporary directory
		if ( ! defined('SHOPP_NAMESPACE_TAXONOMIES') )	define('SHOPP_NAMESPACE_TAXONOMIES',true);		// Add taxonomy namespacing for permalinks /shop/category/category-name, /shopp/tag/tag-name
	}

	function paths () {

		$path = sanitize_path(dirname(__FILE__));
		$file = basename(__FILE__);
		$directory = basename($path);

		// Paths
		define('SHOPP_PATH', $path );
		define('SHOPP_DIR', $directory );
		define('SHOPP_PLUGINFILE', "$directory/$file" );
		define('SHOPP_PLUGINURI', set_url_scheme(WP_PLUGIN_URL . "/$directory") );

		define('SHOPP_WPADMIN_URL', admin_url() ); // @deprecated, use admin_url() instead

		define('SHOPP_ADMIN_DIR', '/core/ui');
		define('SHOPP_ADMIN_PATH', SHOPP_PATH . SHOPP_ADMIN_DIR);
		define('SHOPP_ADMIN_URI', SHOPP_PLUGINURI . SHOPP_ADMIN_DIR);
		define('SHOPP_ICONS_URI', SHOPP_ADMIN_URI.'/icons');
		define('SHOPP_FLOW_PATH', SHOPP_PATH.'/core/flow');
		define('SHOPP_MODEL_PATH', SHOPP_PATH.'/core/model');
		define('SHOPP_GATEWAYS', SHOPP_PATH.'/gateways');
		define('SHOPP_SHIPPING', SHOPP_PATH.'/shipping');
		define('SHOPP_STORAGE', SHOPP_PATH.'/storage');
		define('SHOPP_THEME_APIS', SHOPP_PATH.'/api/theme');
		define('SHOPP_DBSCHEMA', SHOPP_MODEL_PATH.'/schema.sql');

	}


	/**
	 * Check if we are in the early stages of activation (ie, potentially before the schema
	 * has been established).
	 */
	protected function bootstrapcheck() {
		global $action, $plugin;

		if ($action === 'activate' && $plugin === SHOPP_PLUGINFILE)
			$this->bootstrapmode = true;
	}


	/**
	 * Sets up permalink handling for Storefront pages
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	function pages () {

		shopp_register_page( 'ShoppCatalogPage' );
		shopp_register_page( 'ShoppAccountPage' );
		shopp_register_page( 'ShoppCartPage' );
		shopp_register_page( 'ShoppCheckoutPage' );
		shopp_register_page( 'ShoppConfirmPage' );
		shopp_register_page( 'ShoppThanksPage' );

		do_action( 'shopp_init_storefront_pages' );

	}

	function collections () {

		shopp_register_collection( 'CatalogProducts' );
		shopp_register_collection( 'NewProducts' );
		shopp_register_collection( 'FeaturedProducts' );
		shopp_register_collection( 'OnSaleProducts' );
		shopp_register_collection( 'BestsellerProducts' );
		shopp_register_collection( 'SearchResults' );
		shopp_register_collection( 'MixProducts' );
		shopp_register_collection( 'TagProducts' );
		shopp_register_collection( 'RelatedProducts' );
		shopp_register_collection( 'AlsoBoughtProducts' );
		shopp_register_collection( 'ViewedProducts' );
		shopp_register_collection( 'RandomProducts' );
		shopp_register_collection( 'PromoProducts' );

	}

	function taxonomies () {
		ProductTaxonomy::register( 'ProductCategory' );
		ProductTaxonomy::register( 'ProductTag' );
	}

	function products () {
		WPShoppObject::register( 'Product', ShoppPages()->baseslug() );
	}

	/**
	 * Registers theme widgets
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.3
	 *
	 * @return void
	 **/
	function widgets () {

		register_widget( 'ShoppAccountWidget' );
		register_widget( 'ShoppCartWidget' );
		register_widget( 'ShoppCategoriesWidget' );
		register_widget( 'ShoppFacetedMenuWidget' );
		register_widget( 'ShoppProductWidget' );
		register_widget( 'ShoppSearchWidget' );
		register_widget( 'ShoppCategorySectionWidget' );
		register_widget( 'ShoppShoppersWidget' );
		register_widget( 'ShoppTagCloudWidget' );

	}

	/**
	 * If theme content templates are enabled, checks for and includes a functions.php file (if present).
	 * This allows developers to add Shopp-specific presentation logic with the added convenience of knowing
	 * that shopp_init has run.
	 */
	public function theme_functions () {
		if (shopp_setting('theme_templates') !== 'on') return;
		$functions = locate_shopp_template(array('functions.php'));
		if (!empty($functions)) include $functions;
	}

	/**
	 * Adds Shopp-specific mod_rewrite rule for low-resource, speedy image server and downloads request handler
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param array $wp_rewrite_rules An array of existing WordPress rewrite rules
	 * @return array Rewrite rules
	 **/
 	function rewrites ($wp_rewrite_rules) {
 		global $is_IIS;
 		$structure = get_option('permalink_structure');
 		if ('' == $structure) return $wp_rewrite_rules;
 		$path = str_replace('%2F','/',urlencode(join('/',array(PLUGINDIR,SHOPP_DIR,'core'))));

 		// Download URL rewrites
		$AccountPage = ShoppPages()->get('account');
 		$downloads = array( ShoppPages()->baseslug(), $AccountPage->slug(), 'download', '([a-f0-9]{40})', '?$' );
 		if ( $is_IIS && 0 === strpos($structure,'/index.php/') ) array_unshift($downloads,'index.php');
 		$rules = array( join('/',$downloads)
 				=> 'index.php?src=download&shopp_download=$matches[1]',
 		);

 		// Image URL rewrite
 		$images = array( ShoppPages()->baseslug(), 'images', '(\d+)', "?\??(.*)$" );
 		add_rewrite_rule(join('/',$images), $path.'/image.php?siid=$1&$2');

		// print_r($rules + $wp_rewrite_rules);

 		return $rules + $wp_rewrite_rules;
 	}

	/**
	 * Force rebuilding rewrite rules when necessary
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	function rebuild () {
		if ( ! shopp_setting_enabled('rebuild_rewrites') ) return;

		flush_rewrite_rules();
		shopp_set_setting('rebuild_rewrites','off');
	}

	/**
	 * Registers the query variables used by Shopp
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.2
	 *
	 * @param array $vars The current list of handled WordPress query vars
	 * @return array Augmented list of query vars including Shopp vars
	 **/
	function queryvars ($vars) {

		$vars[] = 's_iid';			// Shopp image id
		$vars[] = 's_cs';			// Catalog (search) flag
		$vars[] = 's_ff';			// Category filters
		$vars[] = 'src';			// Shopp resource
		$vars[] = 'shopp_page';
		$vars[] = 'shopp_download';

		return $vars;
	}

	/**
	 * Handles request services like the image server and script server
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return boolean The service load status
	 **/
	static function services () {
		if ( WP_DEBUG ) define('SHOPP_MEMORY_PROFILE_BEFORE', memory_get_peak_usage(true) );

		// Image Server request handling
		if ( isset($_GET['siid']) || (false !== strpos($_SERVER['REQUEST_URI'],'/images/') && sscanf($_SERVER['REQUEST_URI'],'%s/images/%d/')))
			return require 'core/image.php';

		// Script Server request handling
		if ( isset($_GET['sjsl']) )
			return require 'core/scripts.php';
	}

	/**
	 * Detects if Shopp is unsupported in the current hosting environment
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean True if requirements are missing, false if no errors were detected
	 **/
	static function unsupported () {
		$activation = false;
		if ( isset($_GET['action']) && isset($_GET['plugin']) ) {
			$activation = ('activate' == $_GET['action']);
			if ($activation) {
				$plugin = $_GET['plugin'];
				if (function_exists('check_admin_referer'))
					check_admin_referer('activate-plugin_' . $plugin);
			}
		}

		$errors = array();

		// Check PHP version
		if ( version_compare(PHP_VERSION, '5.2.4','<') ) array_push($errors,'phpversion','php524');

		// Check WordPress version
		if ( version_compare(get_bloginfo('version'),'3.5','<') )
			array_push($errors,'wpversion','wp35');

		// Check for GD
		if ( ! function_exists('gd_info') ) $errors[] = 'gd';
		elseif ( ! array_keys( gd_info(), array('JPG Support','JPEG Support')) ) $errors[] = 'jpgsupport';

		if ( empty($errors) ) {
			if ( ! defined('SHOPP_UNSUPPORTED') ) define('SHOPP_UNSUPPORTED',false);
			return false;
		}

		$plugin_path = dirname(__FILE__);
		// Manually load text domain for translated activation errors
		$languages_path = str_replace('\\', '/', $plugin_path.'/lang');
		load_plugin_textdomain('Shopp',false,$languages_path);

		// Define translated messages
		$_ = array(
			'header' => __('Shopp Activation Error','Shopp'),
			'intro' => __('Sorry! Shopp cannot be activated for this WordPress install.'),
			'phpversion' => sprintf(__('Your server is running PHP %s!','Shopp'),PHP_VERSION),
			'php524' => __('Shopp requires PHP 5.2.4+.','Shopp'),
			'wpversion' => sprintf(__('This site is running WordPress %s!','Shopp'),get_bloginfo('version')),
			'wp35' => __('Shopp requires WordPress 3.5.','Shopp'),
			'gdsupport' => __('Your server does not have GD support! Shopp requires the GD image library with JPEG support for generating gallery and thumbnail images.','Shopp'),
			'jpgsupport' => __('Your server does not have JPEG support for the GD library! Shopp requires JPEG support in the GD image library to generate JPEG images.','Shopp'),
			'nextstep' => sprintf(__('Try contacting your web hosting provider or server administrator to upgrade your server. For more information about the requirements for running Shopp, see the %sShopp Documentation%s','Shopp'),'<a href="'.SHOPP_DOCS.'Requirements">','</a>'),
			'continue' => __('Return to Plugins page')
		);

		if ( $activation ) {
			$string = '<h1>'.$_['header'].'</h1><p>'.$_['intro'].'</h1></p><ul>';
			foreach ((array)$errors as $error) if (isset($_[$error])) $string .= "<li>{$_[$error]}</li>";
			$string .= '</ul><p>'.$_['nextstep'].'</p><p><a class="button" href="javascript:history.go(-1);">&larr; '.$_['continue'].'</a></p>';
			wp_die($string);
		}

		if ( ! function_exists('deactivate_plugins') )
			require( ABSPATH . 'wp-admin/includes/plugin.php' );

		$plugin = basename($plugin_path).__FILE__;
		deactivate_plugins($plugin,true);

		$phperror = '';
		if ( is_array($errors) && ! empty($errors) ) {
			foreach ( $errors as $error ) {
				if ( isset($_[$error]) )
					$phperror .= $_[$error].' ';
				trigger_error($phperror,E_USER_WARNING);
			}
		}

		if ( ! defined('SHOPP_UNSUPPORTED') )
			define('SHOPP_UNSUPPORTED',true);

		return true;
	}

	/**
	 * Checks for available updates
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return array List of available updates
	 **/
	function updates () {

		global $pagenow;
		if ( is_admin()
			&& 'plugins.php' == $pagenow
			&& isset($_GET['action'])
			&& 'deactivate' == $_GET['action']) return array();

		$updates = new StdClass();
		if (function_exists('get_site_transient')) $plugin_updates = get_site_transient('update_plugins');
		else $plugin_updates = get_transient('update_plugins');

		switch ( current_filter() ) {
			case 'load-update-core.php': $timeout = 60; break; // 1 minute
			case 'load-plugins.php': // 1 hour
			case 'load-update.php': $timeout = 3600; break;
			default: $timeout = 43200; // 12 hours
		}

		$justchecked = isset( $plugin_updates->last_checked_shopp ) && $timeout > ( time() - $plugin_updates->last_checked_shopp );
		$changed = isset($plugin_updates->response[SHOPP_PLUGINFILE]);
		if ( $justchecked && ! $changed ) return;

		$addons = array_merge(
			$this->Gateways->checksums(),
			$this->Shipping->checksums(),
			$this->Storage->checksums()
		);

		$request = array('ShoppServerRequest' => 'update-check');
		/**
		 * Update checks collect environment details for faster support service only,
		 * none of it is linked to personally identifiable information.
		 **/
		$data = array(
			'core' => SHOPP_VERSION,
			'addons' => join("-",$addons),
			'site' => get_bloginfo('url'),
			'wp' => get_bloginfo('version').(is_multisite()?' (multisite)':''),
			'mysql' => mysql_get_server_info(),
			'php' => phpversion(),
			'uploadmax' => ini_get('upload_max_filesize'),
			'postmax' => ini_get('post_max_size'),
			'memlimit' => ini_get('memory_limit'),
			'server' => $_SERVER['SERVER_SOFTWARE'],
			'agent' => $_SERVER['HTTP_USER_AGENT']
		);

		$response = Shopp::callhome($request,$data);

		if ($response == '-1') return; // Bad response, bail
		$response = unserialize($response);
		unset($updates->response);

		if (isset($response->key) && !str_true($response->key)) shopp_set_setting( 'updatekey', array(0) );

		if (isset($response->addons)) {
			$updates->response[SHOPP_PLUGINFILE.'/addons'] = $response->addons;
			unset($response->addons);
		}

		if (isset($response->id))
			$updates->response[SHOPP_PLUGINFILE] = $response;

		if (isset($updates->response)) {
			shopp_set_setting('updates',$updates);

			// Add Shopp to the WP plugin update notification count
			if ( isset($updates->response[SHOPP_PLUGINFILE]) )
				$plugin_updates->response[SHOPP_PLUGINFILE] = $updates->response[SHOPP_PLUGINFILE];

		} else unset($plugin_updates->response[SHOPP_PLUGINFILE]); // No updates, remove Shopp from the plugin update count

		$plugin_updates->last_checked_shopp = time();
		if ( function_exists('set_site_transient') ) set_site_transient('update_plugins',$plugin_updates);
		else set_transient('update_plugins',$plugin_updates);

		return $updates;
	}

	/**
	 * Loads the change log for an available update
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	static function changelog () {
		if ( 'shopp' != $_REQUEST['plugin'] ) return;

		$request = array('ShoppServerRequest' => 'changelog');
		if ( isset($_GET['core']) && ! empty($_GET['core']) )
			$request['core'] = $_GET['core'];
		if ( isset($_GET['addon']) && ! empty($_GET['addon']) )
			$request['addons'] = $_GET['addon'];

		$data = array();
		$response = Shopp::callhome($request,$data);

		include SHOPP_ADMIN_PATH.'/help/changelog.php';
		exit;
	}

	/**
	 * Reports on the availability of new updates and the update key
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function status () {
		$updates = shopp_setting('updates');
		$keysetting = Shopp::keysetting();
		$key = $keysetting['k'];

		$activated = ('1' == $keysetting['s']);
		$core = isset($updates->response[SHOPP_PLUGINFILE])?$updates->response[SHOPP_PLUGINFILE]:false;
		$addons = isset($updates->response[SHOPP_PLUGINFILE.'/addons'])?$updates->response[SHOPP_PLUGINFILE.'/addons']:false;

		$plugin_name = 'Shopp';
		$store_url = SHOPP_HOME.'store/';
		$account_url = SHOPP_HOME.'store/account/';

		if ( ! empty($core)	// Core update available
				&& isset($core->new_version)	// New version info available
				&& version_compare($core->new_version,SHOPP_VERSION,'>') // New version is greater than current version
			) {
			$details_url = admin_url('plugin-install.php?tab=plugin-information&plugin='.($core->slug).'&core='.($core->new_version).'&TB_iframe=true&width=600&height=800');
			$update_url = wp_nonce_url('update.php?action=shopp&plugin='.SHOPP_PLUGINFILE,'upgrade-plugin_shopp');

			if ( ! $activated ) { // Key not active
				$update_url = $store_url;
				$message = sprintf(__('There is a new version of %1$s available. %2$s View version %5$s details %4$s or %3$s purchase a %1$s key %4$s to get access to automatic updates and official support services.','Shopp'),
							$plugin_name, '<a href="'.$details_url.'" class="thickbox" title="'.esc_attr($plugin_name).'">', '<a href="'.$update_url.'">', '</a>', $core->new_version );

				shopp_set_setting('updates',false);
			} else $message = sprintf(__('There is a new version of %1$s available. %2$s View version %5$s details %4$s or %3$s upgrade automatically %4$s.'),
								$plugin_name, '<a href="'.$details_url.'" class="thickbox" title="'.esc_attr($plugin_name).'">', '<a href="'.$update_url.'">', '</a>', $core->new_version );

			echo '<tr class="plugin-update-tr"><td colspan="3" class="plugin-update"><div class="update-message">'.$message.'</div></td></tr>';

			return;
		}

		if ( ! $activated ) { // No update available, key not active
			$message = sprintf(__('Please activate a valid %1$s access key for automatic updates and official support services. %2$s Find your %1$s access key %4$s or %3$s purchase a new key at the Shopp Store. %4$s','Shopp'), $plugin_name, '<a href="'.$account_url.'" target="_blank">', '<a href="'.$store_url.'" target="_blank">','</a>');

			echo '<tr class="plugin-update-tr"><td colspan="3" class="plugin-update"><div class="update-message">'.$message.'</div></td></tr>';
			shopp_set_setting('updates',false);

			return;
		}

	    if ( $addons ) {
			// Addon update messages
			foreach ( $addons as $addon ) {
				$details_url = admin_url('plugin-install.php?tab=plugin-information&plugin=shopp&addon='.($addon->slug).'&TB_iframe=true&width=600&height=800');
				$update_url = wp_nonce_url('update.php?action=shopp&addon='.$addon->slug.'&type='.$addon->type, 'upgrade-shopp-addon_'.$addon->slug);
				$message = sprintf(__('There is a new version of the %1$s add-on available. %2$s View version %5$s details %4$s or %3$s upgrade automatically %4$s.','Shopp'),
						esc_html($addon->name), '<a href="'.$details_url.'" class="thickbox" title="'.esc_attr($addon->name).'">', '<a href="'.esc_url($update_url).'">', '</a>', esc_html($addon->new_version) );

				echo '<tr class="plugin-update-tr"><td colspan="3" class="plugin-update"><div class="update-message">'.$message.'</div></td></tr>';
			}
		}

	}


	/**
	 * Detect if the Shopp installation needs maintenance
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	static function maintenance () {

		$db_version = intval(shopp_setting('db_version'));
		return ( ! ShoppSettings()->available() || $db_version != DB::$version || shopp_setting_enabled('maintenance') );

		// Settings unavailable
		if (!ShoppSettings()->available() || ! shopp_setting('shopp_setup') != "completed")
			return false;

		shopp_set_setting('maintenance','on');
		return true;
	}

	/**
	 * Communicates with the Shopp update service server
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $request (optional) A list of request variables to send
	 * @param array $data (optional) A list of data variables to send
	 * @param array $options (optional)
	 * @return string The response from the server
	 **/
	static function callhome ($request=array(),$data=array(),$options=array()) {
		$query = http_build_query(array_merge(array('ver'=>'1.1'),$request),'','&');
		$data = http_build_query($data,'','&');

		$defaults = array(
			'method' => 'POST',
			'timeout' => 20,
			'redirection' => 7,
			'httpversion' => '1.0',
			'user-agent' => SHOPP_GATEWAY_USERAGENT.'; '.get_bloginfo( 'url' ),
			'blocking' => true,
			'headers' => array(),
			'cookies' => array(),
			'body' => $data,
			'compress' => false,
			'decompress' => true,
			'sslverify' => false
		);
		$params = array_merge($defaults,$options);

		$URL = SHOPP_HOME . "?$query";

		$connection = new WP_Http();
		$result = $connection->request($URL,$params);
		extract($result);

		if ( isset($response['code']) && 200 != $response['code'] ) { // Fail, fallback to http instead
			$URL = str_replace('https://', 'http://', $URL);
			$connection = new WP_Http();
			$result = $connection->request($URL,$params);
			extract($result);
		}

		if ( is_wp_error($result) ) {
			$errors = array(); foreach ($result->errors as $errname => $msgs) $errors[] = join(' ',$msgs);
			$errors = join(' ',$errors);

			shopp_add_error("Shopp: ".Lookup::errors('callhome','fail')." $errors ".Lookup::errors('contact','admin')." (WP_HTTP)",SHOPP_COMM_ERR);

			return false;
		} elseif ( empty($result) || !isset($result['response']) ) {
			shopp_add_error("Shopp: ".Lookup::errors('callhome','noresponse'),SHOPP_COMM_ERR);
			return false;
		} else extract($result);

		if ( isset($response['code']) && 200 != $response['code'] ) {
			$error = Lookup::errors('callhome','http-'.$response['code']);
			if (empty($error)) $error = Lookup::errors('callhome','http-unkonwn');
			shopp_add_error("Shopp: $error",'callhome_comm_err',SHOPP_COMM_ERR);
			return $body;
		}

		return $body;

	}

	static function key ($action,$key) {
		$actions = array('deactivate','activate');
		if (!in_array($action,$actions)) $action = reset($actions);
		$action = "$action-key";

		$request = array( 'ShoppServerRequest' => $action,'key' => $key,'site' => get_bloginfo('siteurl') );
		$response = Shopp::callhome($request);
		$result = json_decode($response);

		$result = apply_filters('shopp_update_key',$result);

		shopp_set_setting( 'updatekey',$result );

		return $response;
	}

	static function keysetting () {
		$updatekey = shopp_setting('updatekey');

		// @legacy
		if (is_array($updatekey)) {
			$keys = array('s','k','t');
			return array_combine(array_slice($keys,0,count($updatekey)),$updatekey);
		}

		$data = base64_decode($updatekey);
		if (empty($data)) return false;
		return unpack(Lookup::keyformat(),$data);
	}

	static function activated () {
		$key = Shopp::keysetting();
		return ('1' == $key['s']);
	}

} // END class Shopp