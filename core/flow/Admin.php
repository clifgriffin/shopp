<?php
/**
 * Admin.php
 *
 * Super-controller providing Shopp integration with the WordPress Admin
 *
 * @copyright Ingenesis Limited, January 2010-2014
 * @license   GNU GPL version 3 (or later) {@see license.txt}
 * @package   Shopp/Flow/Admin
 * @version   1.4
 * @since     1.0
 **/

defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit; // Prevent direct access

/**
 * ShoppAdmin
 *
 * @author Jonathan Davis
 * @package admin
 * @since 1.1
 **/
class ShoppAdmin extends ShoppFlowController {

	private $Menus = false;

	/**
	 * Admin constructor
	 *
	 * @since 1.1
	 *
	 * @return void
	 **/
	public function __construct () {
		parent::__construct();

		$this->legacyupdate();

		$this->menus();

		// Dashboard widget support
		add_action('wp_dashboard_setup', array('ShoppAdminDashboard', 'init'));

		add_action('admin_init', array($this, 'updates'));
		add_action('admin_init', array('ShoppTinyMCE', 'init'));

		add_action('switch_theme',    array($this, 'themepath'));

		// Admin favorites menu
		add_filter('favorite_actions', array($this, 'favorites'));

		// WordPress custom theme menus
		add_action('load-nav-menus.php', array('ShoppCustomThemeMenus', 'init'));

		add_filter('wp_dropdown_pages', array($this, 'storepages'));
		add_filter('pre_update_option_page_on_front', array($this, 'frontpage'));

		// Add admin JavaScript & CSS
		add_action('admin_enqueue_scripts', array($this, 'behaviors'), 50);
		add_action('admin_enqueue_scripts', array($this, 'styles'), 50);
		add_action('load-update.php', array($this, 'styles'));

	}

	/**
	 * Setup the Shopp admin menus
	 *
	 * @since 1.4
	 *
	 * @return ShoppAdminMenus The Shopp admin menu manager instance
	 **/
	public function menus () {
		if ( ! $this->Menus )
			$this->Menus = new ShoppAdminMenus();
		return $this->Menus;
	}

	/**
	 * Takes an internal page name reference and builds the full path name
	 *
	 * @since 1.1
	 *
	 * @param string $page The internal reference name for the page
	 * @return string The fully qualified resource name for the admin page
	 **/
	public static function pagename ( $page ) {
		$base = sanitize_key(SHOPP_DIR);
		return "$base-$page";
	}

	/**
	 * Gets the name of the controller for the current request or the specified page resource
	 *
	 * @since 1.1
	 *
	 * @param string $page (optional) The fully qualified reference name for the page
	 * @return string|boolean The name of the controller or false if not available
	 **/
	public function controller ( $page = false ) {
		return $this->menus()->controller($page);
	}

	/**
	 * Dynamically includes necessary JavaScript and stylesheets for the admin
	 *
	 * @since 1.0
	 *
	 * @return void
	 **/
	public function behaviors () {
		if ( ! $this->menus()->shoppscreen() ) return;

		shopp_enqueue_script('shopp');
	}

	/**
	 * Queues the admin stylesheets
	 *
	 * @since 1.1
	 *
	 * @return void
	 **/
	public function styles () {
		if ( ! $this->menus()->shoppscreen() ) return;

		shopp_enqueue_style('colorbox');
		shopp_enqueue_style('admin');
		shopp_enqueue_style('icons');
		shopp_enqueue_style('selectize');

		if ( 'rtl' == get_bloginfo('text_direction') )
			shopp_enqueue_style('admin-rtl');

	}

	/**
	 * Adds update availability notices for new Shopp releases and installed add-ons
	 *
	 * @since 1.3
	 *
	 * @return void
	 **/
	public function updates () {

		add_filter('plugin_row_meta', array('ShoppSupport', 'addons'), 10, 2); // Show installed addons

		if ( ShoppSupport::activated() ) return;

		add_action('in_plugin_update_message-' . SHOPP_PLUGINFILE, array('ShoppSupport', 'wpupdate'), 10, 2);
		add_action('after_plugin_row_' . SHOPP_PLUGINFILE, array('ShoppSupport', 'pluginsnag'), 10, 2);

	}

	/**
	 * Adds contextually appropriate help information to interfaces
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	public static function help () {

		if ( ! ShoppAdmin()->menus()->shoppscreen() ) return;

		$parts = explode('-', self::screen());
		$pagename = end($parts);

		if ( in_array($pagename, array('welcome', 'credits')) ) return;

		$path = SHOPP_ADMIN_PATH . '/help';
		if ( file_exists("$path/$pagename.php") )
			return include "$path/$pagename.php";

	}

	/**
	 * Displays the database update screen
	 *
	 * @return boolean
	 * @author Jonathan Davis
	 **/
	public static function updatedb () {
		$uri = SHOPP_ADMIN_URI . '/styles';
		wp_enqueue_style('shopp.welcome', "$uri/welcome.css", array(), ShoppVersion::cache(), 'screen');
		include( SHOPP_ADMIN_PATH . '/help/update.php');
	}

	/**
	 * Adds a 'New Product' shortcut to the WordPress admin favorites menu
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $actions List of actions in the menu
	 * @return array Modified actions list
	 **/
	public function favorites ( array $actions = array() ) {
		$key = esc_url(add_query_arg(array('page' => $this->pagename('products'), 'id' => 'new'), admin_url('admin.php')));
	    $actions[ $key ] = array(Shopp::__('New Product'), 8);
		return $actions;
	}

	/**
	 * Update the stored path to the activated theme
	 *
	 * Automatically updates the Shopp theme path setting when the
	 * a new theme is activated.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	public function themepath () {
		shopp_set_setting('theme_templates', addslashes(sanitize_path(STYLESHEETPATH . '/' . "shopp")));
	}

	/**
	 * Handle auto-updates from Shopp 1.0
	 *
	 * @since 1.1
	 *
	 * @return void
	 **/
	public function legacyupdate () {
		global $plugin_page;
		if ( 'shopp-settings-update' == $plugin_page && isset($_GET['updated']) && $_GET['updated'] == 'true' )
				Shopp::redirect(add_query_arg('page', ShoppAdmin::pagename('orders'), admin_url('admin.php')));
	}


	/**
	 * Adds Shopp pages to the page_on_front menu
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $menu The current page_on_front menu
	 * @return string The page_on_front menu with the Shopp storefront page included
	 **/
	public function storepages ($menu) {
		$CatalogPage = ShoppPages()->get('catalog');
		$shoppid = ShoppCatalogPage::frontid(); // uses impossibly long number ("Shopp" in decimal)

		$id = "<select name='page_on_front' id='page_on_front'>\n";
		if ( false === strpos($menu,$id) ) return $menu;
		$token = '<option value="0">&mdash; Select &mdash;</option>';

		if ( $shoppid == get_option('page_on_front') ) $selected = ' selected="selected"';
		$storefront = '<optgroup label="' . __('Shopp','Shopp') . '"><option value="' . $shoppid . '"' . $selected . '>' . esc_html($CatalogPage->title()) . '</option></optgroup><optgroup label="' . __('WordPress') . '">';

		$newmenu = str_replace($token,$token.$storefront,$menu);

		$token = '</select>';
		$newmenu = str_replace($token,'</optgroup>'.$token,$newmenu);
		return $newmenu;
	}

	/**
	 * Filters the page_on_front option during save to handle the bigint on non 64-bit environments
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param mixed $value The value to save
	 * @return mixed The value to save
	 **/
	public function frontpage ( $value ) {
		if ( ! isset($_POST['page_on_front']) ) return $value;
		$shoppid = ShoppCatalogPage::frontid(); // uses impossibly long number ("Shopp" in decimal)
		if ( $_POST['page_on_front'] == $shoppid ) return "$shoppid";
		else return $value;
	}

	public static function screen () {
		return get_current_screen()->id;
	}

} // END class ShoppAdmin

class ShoppAdminMenus {

	private $pages = array();	// Defines a map of pages to create menus from
	private $menus = array();	// Map of page names to WP screen ids for initialized Shopp menus
	private $tabs = array();
	private $mainmenu = false;	// The hook name of the main menu (Orders)

	public $Ajax = array();		// List of AJAX controllers
	public $Page = false;		// The current Page
	public $menu = false;		// The current menu


	/**
	 * @public $caps
	 **/
	 private $caps = array(                                      // Initialize the capabilities, mapping to pages
		'main' => 'shopp_menu',                                //
		'orders' => 'shopp_orders',                            //
		'orders-new' => 'shopp_orders',                        // Capabilities                  Role
		'customers' => 'shopp_customers',                      // _______________________________________________
		'reports' => 'shopp_financials',                       //
		'memberships' => 'shopp_products',                     // shopp_settings                administrator
		'products' => 'shopp_products',                        // shopp_settings_checkout
		'categories' => 'shopp_categories',                    // shopp_settings_payments
		'discounts' => 'shopp_promotions',                     // shopp_settings_shipping
		'system' => 'shopp_settings',                          // shopp_settings_taxes
		'system-payments' => 'shopp_settings_payments',        // shopp_settings_system
		'system-shipping' => 'shopp_settings_shipping',        // shopp_settings_update
		'system-taxes' => 'shopp_settings_taxes',              // shopp_financials              shopp-merchant
		'system-advanced' => 'shopp_settings_system',          // shopp_financials              shopp-merchant
		'system-storage' => 'shopp_settings_system',           // shopp_financials              shopp-merchant
		'system-log' => 'shopp_settings_system',               // shopp_financials              shopp-merchant
		'setup' => 'shopp_settings',                           // shopp_settings_taxes
		'setup-core' => 'shopp_settings',                      // shopp_settings_taxes
		'setup-management' => 'shopp_settings',                // shopp_settings_presentation
		'setup-pages' => 'shopp_settings_presentation',        // shopp_promotions
		'setup-presentation' => 'shopp_settings_presentation', // shopp_products
		'setup-checkout' => 'shopp_settings_checkout',         // shopp_products
		'setup-downloads' => 'shopp_settings_checkout',        // shopp_products
		'setup-images' => 'shopp_settings_presentation',       // shopp_categories
		'welcome' => 'shopp_menu',
		'credits' => 'shopp_menu',
	);

	/**
	 * Defines the Shopp pages used to create WordPress menus
	 *
	 * @since 1.3
	 *
	 * @return void
	 **/
	public function __construct () {

		// Orders menu
		$this->addpage('orders',     Shopp::__('All Orders'), 'ShoppAdminService');
		$this->addpage('orders-new', Shopp::__('New Order'),  'ShoppAdminService');
		$this->addpage('customers',  Shopp::__('Customers'),  'ShoppAdminAccount');
		$this->addpage('reports',  	 Shopp::__('Reports'),    'ShoppAdminReport');

		// Setup tabs
		$this->addpage('setup',              Shopp::__('Setup'),        'ShoppAdminSetup');
		$this->addpage('setup-core',         Shopp::__('Shopp Setup'),  'ShoppAdminSetup', 'setup');
		$this->addpage('setup-management',   Shopp::__('Management'),   'ShoppAdminSetup', 'setup');
		$this->addpage('setup-checkout',     Shopp::__('Checkout'),     'ShoppAdminSetup', 'setup');
		$this->addpage('setup-downloads',    Shopp::__('Downloads'),    'ShoppAdminSetup', 'setup');
		$this->addpage('setup-presentation', Shopp::__('Presentation'),	'ShoppAdminSetup', 'setup');
		$this->addpage('setup-pages',        Shopp::__('Pages'),        'ShoppAdminSetup', 'setup');
		$this->addpage('setup-images',       Shopp::__('Images'),       'ShoppAdminSetup', 'setup');

		// System tabs
		$this->addpage('system',          Shopp::__('System'),   'ShoppAdminSystem');
		$this->addpage('system-payments', Shopp::__('Payments'), 'ShoppAdminSystem', 'system');
		$this->addpage('system-shipping', Shopp::__('Shipping'), 'ShoppAdminSystem', 'system');
		$this->addpage('system-taxes',    Shopp::__('Taxes'),	 'ShoppAdminSystem', 'system');
		$this->addpage('system-storage',  Shopp::__('Storage'),  'ShoppAdminSystem', 'system');
		$this->addpage('system-advanced', Shopp::__('Advanced'), 'ShoppAdminSystem', 'system');

		if ( count(ShoppErrorLogging()->tail(2)) > 1 )
			$this->addpage('system-log', Shopp::__('Log'), 'ShoppAdminSystem', 'system');

		// Catalog menu
		$this->addpage('products',   Shopp::__('Products'),   'ShoppAdminWarehouse',  'products');
		$this->addpage('categories', Shopp::__('Categories'), 'ShoppAdminCategorize', 'products');

		$taxonomies = get_object_taxonomies(ShoppProduct::$posttype, 'object');
		foreach ( $taxonomies as $t ) {
			if ( 'shopp_category' == $t->name ) continue;
			$pagehook = str_replace('shopp_', '', $t->name);
			$this->addpage($pagehook, $t->labels->menu_name, 'ShoppAdminCategorize',  'products');
		}
		$this->addpage('discounts', Shopp::__('Discounts'), 'ShoppAdminDiscounter', 'products');

		$this->addpage('welcome', Shopp::__('Welcome'), 'ShoppAdminWelcome', 'welcome');
		$this->addpage('credits', Shopp::__('Credits'), 'ShoppAdminWelcome', 'credits');

		// Filter hook for adding/modifying Shopp page definitions
		$this->pages = apply_filters('shopp_admin_pages', $this->pages);
		$this->caps = apply_filters('shopp_admin_caps', $this->caps);

		reset($this->pages);
		$this->mainmenu = key($this->pages);

		add_action('admin_menu', array($this, 'taxonomies'), 100);

		shopp_enqueue_style('menus');

		// Set the currently requested page and menu
		if ( isset($_GET['page']) && false !== strpos($_GET['page'], basename(SHOPP_PATH)) ) $page = $_GET['page'];
		else return;

		if ( isset($this->pages[ $page ]) ) $this->Page = $this->pages[ $page ];
		if ( isset($this->menus[ $page ]) ) $this->menu = $this->menus[ $page ];

	}

	/**
	 * Generates the Shopp admin menus
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	public function build () {
		global $menu, $plugin_page;

		$access = 'shopp_menu';
		if ( Shopp::maintenance() ) $access = 'manage_options';

		// Add main menus
		$position = shopp_admin_add_menu(Shopp::__('Shopp'), 'orders', 40, false, 'shopp_orders', Shopp::clearpng());
		shopp_admin_add_menu(Shopp::__('Catalog'), 'products', $position, false, 'shopp_products', Shopp::clearpng());

		// Add after the Shopp menus to avoid being purged by the duplicate separator check
		$menu[ $position - 1 ] = array( '', 'read', 'separator-shopp', '', 'wp-menu-separator' );

		// Add menus to WordPress admin
		foreach ( $this->pages as $page ) $this->submenus($page);

		$parent = get_admin_page_parent();

		if ( isset($this->menus[ $parent ]) && false === strpos($this->menus[ $parent ], 'toplevel') ) {
			$current_page = $plugin_page;
			$plugin_page = $parent;
			add_action('adminmenu', create_function('','global $plugin_page; $plugin_page = "' . $current_page. '";'));
		}

		if ( Shopp::maintenance() ) return;

		// Add contextual help menus
		foreach ( $this->menus as $screen )
			add_action("load-$screen", array('ShoppAdmin', 'help'));

	}

	/**
	 * Provide admin support for custom Shopp taxonomies
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	public function taxonomies () {
		global $submenu;
		if ( ! is_array($submenu) ) return;

		$taxonomies = get_object_taxonomies(ShoppProduct::$posttype);
		foreach ( $submenu['shopp-products'] as &$submenus ) {
			$name = str_replace('-', '_', $submenus[2]);
			if ( ! in_array($name, $taxonomies) ) continue;

			$submenus[2] = "edit-tags.php?taxonomy=$name";

			add_filter('manage_edit-' . $name . '_columns', array('ShoppAdminTaxonomies', 'columns'));
			add_filter('manage_' . $name . '_custom_column', array('ShoppAdminTaxonomies', 'products'), 10, 3);
		}

		add_action('admin_print_styles-edit-tags.php',array(ShoppAdmin(), 'styles'));
		add_action('admin_head-edit-tags.php', array('ShoppAdminTaxonomies', 'parentmenu'));
	}

	/**
	 * Registers a new page to the Shopp admin pages
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $name The internal reference name for the page
	 * @param string $label The label displayed in the WordPress admin menu
	 * @param string $controller The name of the controller to use for the page
	 * @param string $parent The internal reference for the parent page
	 * @return void
	 **/
	private function addpage ( $name, $label, $controller, $parent = null ) {
		$page = ShoppAdmin::pagename($name);

		if ( isset($parent) ) $parent = ShoppAdmin::pagename($parent);
		$this->pages[ $page ] = new ShoppAdminPage($name, $page, $label, $controller, $parent);
	}

	/**
	 * Adds a ShoppAdminPage entry to the WordPress menus under the Shopp menus
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param mixed $page A ShoppAdminPage object
	 * @return void
	 **/
	private function submenus ( ShoppAdminPage $Page ) {

		$Shopp = Shopp::object();
		$name = $Page->name;
		$pagehook = $Page->page;

		// Set capability
		$capability = isset($this->caps[ $name ]) ? $this->caps[ $name ] : 'none';
		$taxonomies = get_object_taxonomies(ShoppProduct::$posttype, 'names');
		if ( in_array("shopp_$name", $taxonomies) ) $capability = 'shopp_categories';

		// Set controller (callback handler)
		$controller = array($Shopp->Flow, 'admin');

		if ( Shopp::upgradedb() ) $controller = array('ShoppAdmin', 'updatedb');

		$menu = $Page->parent ? $Page->parent : $this->mainmenu;

		shopp_admin_add_submenu(
			$Page->label,
			$pagehook,
			$menu,
			$controller,
			$capability
		);

	}

	/**
	 * Gets or add a ShoppAdmin menu entry
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $name The Shopp-internal name of the menu
	 * @param string $menu The WordPress screen ID
	 * @return string The screen id of the given menu name
	 **/
	public function menu ( $name, $menu = null ) {

		if ( isset($menu) ) $this->menus[ $name ] = $menu;
		if ( isset($this->menus[ $name ]) ) return $this->menus[ $name ];

		return false;

	}

	/**
	 * Provides an array of tabs for the current screen
	 **/
	public function tabs ( $page ) {
		global $submenu;
		if ( ! isset($this->tabs[ $page ]) ) return array();
		$parent = $this->tabs[ $page ];

		if ( isset($submenu[ $parent ]) ) {
			$tabs = array();
			foreach ( $submenu[ $parent ] as $entry ) {
				$title = $entry[0];
				$tab = $entry[2];

				$tabs[ $tab ] = array(
					$title,
					$tab,
					$parent
				);

			}
			return $tabs;
		}

		return array();
	}

	public function addtab ( $tab, $parent ) {
		$this->tabs[ $parent ] = $parent;
		$this->tabs[ $tab ] = $parent;
	}

	/**
	 * Gets the name of the controller for the current request or the specified page resource
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $page (optional) The fully qualified reference name for the page
	 * @return string|boolean The name of the controller or false if not available
	 **/
	public function controller ( $page = false ) {

		if ( ! $page && isset($this->Page->controller) )
			return $this->Page->controller;

		if ( isset($this->pages[ $page ]) && isset($this->pages[ $page ]->controller) )
			return $this->pages[ $page ]->controller;

		return false;
	}

	/**
	 * Gets the Shopp-internal name of the main menu
	 *
	 * @since 1.3
	 *
	 * @return string The menu name
	 **/
	public function mainmenu () {
		return $this->mainmenu;
	}

	/**
	 * Determines if the current request is a Shopp admin screen
	 *
	 * @since 1.4
	 *
	 * @return boolean True if the current screen is Shopp admin screen, false otherwise
	 **/
	public function shoppscreen () {
		global $hook_suffix, $taxonomy;

		if ( in_array($hook_suffix, $this->menus) ) return true;

		if ( isset($taxonomy) ) { // Prevent loading styles if not on Shopp taxonomy editor
			$taxonomies = get_object_taxonomies(ShoppProduct::$posttype);
			if ( in_array($taxonomy, $taxonomies)) return true;
		}

		return false;
	}

} // end ShoppAdminMenus

class ShoppAdminTaxonomies {

	/**
	 * Resets the parent menu to the Shopp Catalog menu
	 **/
	public function parentmenu () {
		global $parent_file, $taxonomy;
		$taxonomies = get_object_taxonomies(ShoppProduct::$posttype);
		if ( in_array($taxonomy, $taxonomies) ) $parent_file = 'shopp-products';
	}

	/**
	 * Defines the column layout for Shopp taxonomy list screens
	 **/
	public function columns ( array $cols ) {

		$cols = array(
			'cb' => '<input type="checkbox" />',
			'name' => __('Name'),
			'description' => __('Description'),
			'slug' => __('Slug'),
			'products' => Shopp::__('Products')
		);

		return $cols;
	}

	/**
	 * Generates the product column markup for taxonomy list screens
	 **/
	public function products ( $markup, $name, $term_id ) {
		global $taxonomy;
		if ( 'products' != $name ) return;

		$term = get_term($term_id, $taxonomy);
		$markup = '<a href="admin.php?page=shopp-products&' . $taxonomy . '=' . $term->slug . '">' . $term->count . '</a>';

		return $markup;
	}

} // end ShoppAdminTaxonomies

/**
 * ShoppAdminPage class
 *
 * A property container for Shopp's admin page meta
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package admin
 **/
class ShoppAdminPage {

	public $name = '';
	public $page = '';
	public $label = '';
	public $controller = '';
	public $parent = false;

	public function __construct ( $name, $page, $label, $controller, $parent = null ) {
		$this->name = $name;
		$this->page = $page;
		$this->label = $label;
		$this->controller = $controller;
		$this->parent = $parent;
	}

	public function hook () {
		global $admin_page_hooks;
		if ( isset($admin_page_hooks[ $this->parent ]) ) return $admin_page_hooks[ $this->parent ];
		return 'shopp';
	}

} // END class ShoppAdminPage

class ShoppTinyMCE {

	public static function init () {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) return;
		new self();
	}

	public function __construct () {

		$len = strlen( ABSPATH );
		$p = '';

		for ( $i = 0; $i < $len; $i++ )
			$p .= 'x' . dechex( ord( substr( ABSPATH, $i, 1 ) ) + $len );

		// Add TinyMCE buttons when using rich editor
		if ( 'true' == get_user_option( 'rich_editing' ) ) {
			global $pagenow, $plugin_page;
			$pages = array( 'post.php', 'post-new.php', 'page.php', 'page-new.php' );
			$editors = array( 'shopp-products', 'shopp-categories' );
			if ( ! ( in_array( $pagenow, $pages ) || ( in_array( $plugin_page, $editors ) && ! empty( $_GET['id'] ) ) ) )
				return false;

			wp_localize_script( 'editor', 'ShoppDialog', array(
				'title' => __( 'Insert Product/Category', 'Shopp' ),
				'desc' => __( 'Insert a product or category from Shopp...', 'Shopp' ),
				'p' => $p
			));

			add_filter( 'mce_external_plugins', array( $this, 'plugin' ), 5 );
			add_filter( 'mce_buttons', array( $this, 'button' ), 5 );
		}
	}

	/**
	 * Adds the Shopp TinyMCE plugin to the list of loaded plugins
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param array $plugins The current list of plugins to load
	 * @return array The updated list of plugins to laod
	 **/
	public function plugin ( $plugins ) {
		// Add a changing query string to keep the TinyMCE plugin from being cached & breaking TinyMCE in Safari/Chrome
		$plugins['Shopp'] = SHOPP_ADMIN_URI . '/behaviors/tinymce/tinyshopp.js?ver=' . time();

		return $plugins;
	}

	/**
	 * Adds the Shopp button to the TinyMCE editor
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param array $buttons The current list of buttons in the editor
	 * @return array The updated list of buttons in the editor
	 **/
	public function button ( $buttons ) {
		array_push($buttons, '|', 'Shopp');
		return $buttons;
	}

}

/**
 * Adds ShoppPages and SmartCollection support to WordPress theme menus system
 **/
class ShoppCustomThemeMenus {

	public static function init () {
		new self();
	}

	/**
	 * Adds ShoppPages and SmartCollection support to WordPress theme menus system
	 *
	 * @since 1.2
	 *
	 * @return void
	 **/
	public function __construct () {

		$this->request();
		add_action('wp_setup_nav_menu_item', array($this, 'setup'));

		new ShoppPagesMenusBox('nav-menus', 'side', 'low');
		new ShoppCollectionsMenusBox('nav-menus', 'side', 'low');
	}


	/**
	 * Modify the request for ShoppPages
	 *
	 * @since 1.4
	 *
	 * @return void
	 **/
	protected function request () {
		global $nav_menu_selected_id;

		$request = $_POST;

		if ( ! isset($request['add-shopp-menu-item']) ) return;

		if ( ! isset($request['menu-item']) ) return;

		// $pages = ShoppStorefront::pages_settings();
		$nav_menu_selected_id = isset( $_POST['menu'] ) ? (int) $_POST['menu'] : 0;

		foreach ( (array) $request['menu-item'] as $key => $item ) {
			if ( ! isset($item['menu-item-shopp-page']) ) continue;

			$requested = $item['menu-item-shopp-page'];
			$Page = ShoppPages()->get($requested);

			$menuitem = &$_REQUEST['menu-item'][ $key ];
			$menuitem['menu-item-db-id'] = 0;
			$menuitem['menu-item-object-id'] = $requested;
			$menuitem['menu-item-object'] = $requested;
			$menuitem['menu-item-type'] = ShoppPages::QUERYVAR;
			$menuitem['menu-item-title'] = $Page->title();
		}


	}

	/**
	 * Filters menu items to set the type labels shown for WordPress theme menus
	 *
	 * @since 1.2
	 *
	 * @param object $menuitem The menu item object
	 * @return object The menu item object
	 **/
	public function setup ( $menuitem ) {

		switch ( $menuitem->type ) {
			case 'shopp_page':       $menuitem->type_label = 'Shopp'; break;
			case 'shopp_collection': $menuitem->type_label = 'Collection'; break;
		}

		return $menuitem;
	}

}

abstract class ShoppAdminMetabox {

	protected $references = array();

	/** @var string $view The relative path to the metabox view file **/
	protected $id = '';
	protected $view = '';
	protected $title = '';


	public function __construct ( $posttype, $context, $priority, array $args = array() ) {

		$this->references = $args;
		$this->init();
		$this->request($_POST);

		add_meta_box($this->id, $this->title() . self::help($this->id), array($this, 'box'), $posttype, $context, $priority, $args);

	}

	public function box () {
		extract($this->references);
		do_action('shopp_metabox_before_' . $this->id);
		include $this->ui();
		do_action('shopp_metabox_after_' . $this->id);
	}

	protected function title () {
		return 'Untitled';
	}

	protected function init () {
		/* Optionally implemented in concrete class */
	}

	protected function request ( array &$post = array() ) {
		/* Optionally implemented in concrete class */
		if ( ! $post ) $post = array();
	}

	protected function ui () {
		$path = join('/', array(SHOPP_ADMIN_PATH, $this->view));
		if ( is_readable($path) )
			return $path;
	}

	public static function help ( $id ) {
		if ( ! ShoppSupport::activated() ) return '';

		$helpurl = add_query_arg(array('src' => 'help', 'id' => $id), admin_url('admin.php'));
		return apply_filters('shopp_admin_boxhelp', '<a href="' . esc_url($helpurl) . '" class="help shoppui-question"></a>');
	}


} // end ShoppAdminMetaBox

class ShoppAdminMenusMetabox extends ShoppAdminMetabox {

	public function box () {
		global $_nav_menu_placeholder, $nav_menu_selected_id;

		$this->references['navmenu_placeholder'] = &$_nav_menu_placeholder;
		$this->references['navmenu_selected'] = &$nav_menu_selected_id;

		parent::box();
	}

	protected function selecturl () {
		return add_query_arg(array(
					'shopp-pages-menu-item' => 'all',
					'selectall' => 1,
			), remove_query_arg(array(
					'action',
					'customlink-tab',
					'edit-menu-item',
					'menu-item',
					'page-tab',
					'_wpnonce',
			))
		);
	}

} // end ShoppAdminMenusMetabox


class ShoppPagesMenusBox extends ShoppAdminMenusMetabox {

	protected $id = 'add-shopp-pages';
	protected $view = 'admin/pages.php';

	protected function title () {
		return Shopp::__('Catalog Pages');
	}

}

class ShoppCollectionsMenusBox extends ShoppAdminMenusMetabox {

	protected $id = 'add-shopp-collections';
	protected $view = 'admin/collections.php';

	protected function title () {
		return Shopp::__('Catalog Collections');
	}

	public function box () {

		$this->references['selecturl'] = $this->selecturl();
		$this->references['Shopp'] = Shopp::Object();

		parent::box();

	}

}

class ShoppUI {

	/**
	 * Container for metabox callback methods. Pattern: [ id => callback , ... ]
	 *
	 * @var array
	 */
	protected static $metaboxes = array();


	public static function cacheversion () {
		return hash('crc32b', ABSPATH . ShoppVersion::release());
	}

	public static function button ( $button, $name, array $options = array() ) {
		$buttons = array(
			'add' => array('class' => 'add', 'title' => Shopp::__('Add'), 'icon' => 'shoppui-plus', 'type' => 'submit'),
			'delete' => array('class' => 'delete', 'title' => Shopp::__('Delete'), 'icon' => 'shoppui-minus', 'type' => 'submit')
		);

		if ( isset($buttons[ $button ]) )
			$options = array_merge($buttons[ $button ], $options);

		$types = array('submit', 'button');
		if ( ! in_array($options['type'], $types) )
			$options['type'] = 'submit';

		$type = $options['type'];
		$title = $options['title'];
		$icon = $options['icon'];

		return '<button type="' . $type . '" name="' . $name . '"' . inputattrs($options) . '><span class="' . $icon . '"><span class="hidden">' . $title . '</span></span></button>';
	}

	public static function template ( $ui, array $data = array() ) {
		$ui = str_replace(array_keys($data), $data, $ui);
		return preg_replace('/\${[-\w]+}/', '', $ui);
	}

	/**
	 * Register column headers for a particular screen.
	 *
	 * Compatibility function for Shopp list table views
	 *
	 * @since 1.2
	 *
	 * @param string $screen The handle for the screen to add help to. This is usually the hook name returned by the add_*_page() functions.
	 * @param array $columns An array of columns with column IDs as the keys and translated column names as the values
	 * @see get_column_headers(), print_column_headers(), get_hidden_columns()
	 */
	public static function register_column_headers ( $screen, $columns ) {
		new ShoppAdminListTable($screen, $columns);
	}

	/**
	 * Prints column headers for a particular screen.
	 *
	 * @since 1.2
	 */
	public static function print_column_headers ( $screen, $id = true ) {
		$wp_list_table = new ShoppAdminListTable($screen);

		$wp_list_table->print_column_headers($id);
	}

	public static function table_set_pagination ( $screen, $total_items, $total_pages, $per_page ) {
		$wp_list_table = new ShoppAdminListTable($screen);

		$wp_list_table->set_pagination( array(
			'total_items' => $total_items,
			'total_pages' => $total_pages,
			'per_page' => $per_page
		) );

		return $wp_list_table;
	}

	/**
	 * Registers a new metabox for use within Shopp admin screens.
	 *
	 * @param string $id
	 * @param string $title
	 * @param $callback callable function
	 * @param string $posttype
	 * @param string $context [optional]
	 * @param string $priority [optional]
	 * @param array $args [optional]
	 */
	public static function addmetabox ( $id, $title, $callback, $posttype, $context = 'advanced', $priority = 'default', array $args = null ) {
		self::$metaboxes[$id] = $callback;
		$args = (array) $args;
		array_unshift($args, $id);
		add_meta_box($id, $title, array(__CLASS__, 'metabox'), $posttype, $context, $priority, $args);
	}

	/**
	 * Handles metabox callbacks - this allows additional output to be appended and prepended by devs using
	 * the shopp_metabox_before_{id} and shopp_metabox_after_{id} actions.
	 */
	public static function metabox($object, $args) {
		$id = array_shift($args['args']);
		$callback = isset(self::$metaboxes[$id]) ? self::$metaboxes[$id] : false;

		if (false === $callback) return;
		do_action('shopp_metabox_before_' . $id);
		call_user_func($callback, $object, $args);
		do_action('shopp_metabox_after_' . $id);
	}

} // END class ShoppUI


class ShoppAdminListTable extends WP_List_Table {

	public $_screen;
	public $_columns;
	public $_sortable;

	public function __construct ( $screen, $columns = array()) {
		if ( is_string( $screen ) )
			$screen = convert_to_screen( $screen );

		$this->_screen = $screen;

		if ( !empty( $columns ) ) {
			$this->_columns = $columns;
			add_filter( 'manage_' . $screen->id . '_columns', array( &$this, 'get_columns' ), 0 );
		}

	}

	public function get_column_info() {
		$columns = get_column_headers( $this->_screen );
		$hidden = get_hidden_columns( $this->_screen );
		$screen = get_current_screen();

		$_sortable = apply_filters( "manage_{$screen->id}_sortable_columns", $this->get_sortable_columns() );

		$sortable = array();
		foreach ( $_sortable as $id => $data ) {
			if ( empty( $data ) )
				continue;

			$data = (array) $data;
			if ( !isset( $data[1] ) )
				$data[1] = false;

			$sortable[$id] = $data;
		}


		return array( $columns, $hidden, $sortable );
	}

	public function get_columns() {
		return $this->_columns;
	}

	public function get_sortable_columns () {
		$screen = get_current_screen();
		$sortables = array(
			'toplevel_page_shopp-products' => array(
				'name'=>'name',
				'price'=>'price',
				'sold'=>'sold',
				'gross'=>'gross',
				'inventory'=>'inventory',
				'sku'=>'sku',
				'date'=>array('date',true)
			)
		);
		if (isset($sortables[ $screen->id ])) return $sortables[ $screen->id ];

		return array();
	}

	// public wrapper to set pagination
	// @todo refactor this whole class to be used more effectively with Shopp MVC style UI
	public function set_pagination ( array $args ) {
		$this->set_pagination_args($args);
	}

}
