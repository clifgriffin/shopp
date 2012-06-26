<?php
/**
 * Flow
 *
 * Super controller for handling low level request processing
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, January 6, 2010
 * @package shopp
 * @subpackage shopp
 **/

// Image Server request handling
if (isset($_GET['siid']) || preg_match('/images\/\d+/',$_SERVER['REQUEST_URI']))
	require(dirname(dirname(__FILE__)).'/image.php');

// Script Server request handling
if (isset($_GET['sjsl']))
	require(dirname(dirname(__FILE__)).'/scripts.php');

/**
 * Flow
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 **/
class Flow {

	var $Controller = false;
	var $Admin = false;
	var $Installer = false;
	var $Logins = false;

	/**
	 * Flow constructor
	 *
	 * @author Jonathan Davis
	 *
	 * @return void
	 **/
	function __construct () {
		register_deactivation_hook(SHOPP_PLUGINFILE, array($this, 'deactivate'));
		register_activation_hook(SHOPP_PLUGINFILE, array($this, 'activate'));
		if (defined('DOING_AJAX')) add_action('admin_init',array($this,'ajax'));

		add_action( 'admin_menu', array($this,'menu') );
		add_action( 'admin_bar_menu', array($this, 'adminbar'), 50 );

		// Handle automatic updates
		add_action('update-custom_shopp',array($this,'update'));

		if (defined('WP_ADMIN')) add_action('admin_init',array($this,'parse'));
		else add_action('parse_request',array($this,'parse'));
	}

	/**
	 * Parses requests and hands off processing to specific subcontrollers
	 *
	 * @author Jonathan Davis
	 *
	 * @return boolean
	 **/
	function parse ($wp) {
		$request = empty($wp->query_vars)?$_GET:$wp->query_vars;
		$resource = isset($request['src']);

		if ($resource) $this->resources($request);

		if (defined('WP_ADMIN')) {
			if (!isset($_GET['page'])) return;
			if ($this->Admin === false) {
				require(SHOPP_FLOW_PATH."/Admin.php");
				$this->Admin = new AdminFlow();
			}
			$controller = $this->Admin->controller(strtolower($request['page']));
			if (!empty($controller)) $this->handler($controller);
		} else $this->handler('Storefront');
	}

	/**
	 * Loads a specified flow controller
	 *
	 * @author Jonathan Davis
	 *
	 * @param string $controller The base name of the controller file
	 * @return void
	 **/
	function handler ($controller) {
		if (!$controller) return false;
		if ( is_a($this->Controller,$controller) ) return true; // Already initialized
		if (!class_exists($controller))	require(SHOPP_FLOW_PATH."/$controller.php");

		$this->Controller = new $controller();
		do_action('shopp_'.strtolower($controller).'_init');
		return true;
	}

	/**
	 * Initializes the Admin controller
	 *
	 * @author Jonathan Davis
	 *
	 * @return void
	 **/
	function admin () {
		if (!defined('WP_ADMIN')) return false;
		$controller = $this->Admin->controller(strtolower($_GET['page']));
		$this->handler($controller);
		$this->Controller->admin();
		return true;
	}

	/**
	 * Defines the Shopp admin page and menu structure
	 *
	 * @author Jonathan Davis
	 *
	 * @return void
	 **/
	function menu () {
		require(SHOPP_FLOW_PATH."/Admin.php");
		$this->Admin = new AdminFlow();
		$this->Admin->menus();
	}

	function ajax () {
		if (!isset($_REQUEST['action']) || !defined('DOING_AJAX')) return;
		require(SHOPP_FLOW_PATH."/Ajax.php");
		$this->Ajax = new AjaxFlow();
	}

	function resources ($request) {
		require(SHOPP_FLOW_PATH."/Resources.php");
		$this->Controller = new Resources($request);
	}

	/**
	 * Activates the plugin
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	function activate () {
		$this->installation();
		do_action('shopp_activate');
	}

	/**
	 * deactivate()
	 * Resets the data_model to prepare for potential upgrades/changes to the table schema */
	function deactivate() {
		$this->installation();
		do_action('shopp_deactivate');
	}

	function installation () {
		if (!defined('WP_ADMIN')) return;
		if ($this->Installer !== false) return;

		require(SHOPP_FLOW_PATH."/Install.php");
		if (!$this->Installer) $this->Installer = new ShoppInstallation();
	}

	function update () {
		$this->installation();
		do_action('shopp_autoupdate');
	}

	function save_settings () {
		if (empty($_POST['settings']) || !is_array($_POST['settings'])) return false;
		foreach ($_POST['settings'] as $setting => $value)
			shopp_set_setting($setting,$value);
		return true;
	}

	// Admin Bar
	function adminbar ( $wp_admin_bar ) {
		$posttype = get_post_type_object(Product::posttype());
		if (empty( $posttype ) || !current_user_can( $posttype->cap->edit_post )) return;
		$wp_admin_bar->add_menu( array(
			'parent' => 'new-content',
			'id' => 'new-'.Product::posttype(),
			'title' => $posttype->labels->singular_name,
			'href' => admin_url( str_replace('%d','new',$posttype->_edit_link) )
		) );

		$object = get_queried_object();
		if (!empty($object) && isset($object->post_type)
				&& $object->post_type == $posttype->name) {
			$wp_admin_bar->add_menu( array(
				'id' => 'edit',
				'title' => $posttype->labels->edit_item,
				'href' => get_edit_post_link( $object->ID )
			) );
		}

	}

} // End class Flow

/**
 * FlowController
 *
 * Provides a template for flow controllers
 *
 * @since 1.1
 * @package shopp
 * @author Jonathan Davis
 **/
abstract class FlowController  {

	/**
	 * FlowController constructor
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function __construct () {
		// if (defined('WP_ADMIN')) {
		// 	add_action('admin_init',array(&$this,'settings'));
		// 	$this->settings();
		// } else add_action('shopp_loaded',array(&$this,'settings'));
	}

	// function settings () {
	// 	ShoppSettings();
	// }

} // END class FlowController

/**
 * AdminController
 *
 * Provides a template for admin controllers
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 **/
abstract class AdminController extends FlowController {

	var $Admin = false;
	var $url;

	private $notices = array();

	/**
	 * AdminController constructor
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function __construct () {
		// parent::__construct();
		global $Shopp;
		if (!empty($Shopp->Flow->Admin)) $this->Admin = &$Shopp->Flow->Admin;
		$this->url = add_query_arg(array('page'=>esc_attr($_GET['page'])),admin_url('admin.php'));

		add_action('shopp_admin_notices',array($this,'notices'));
	}

	function notice ($message,$style='updated',$priority=10) {
		$notice = new StdClass();
		$notice->message = $message;
		$notice->style = $style;
		array_splice($this->notices,$priority,0,array($notice));
	}

	function notices () {
		if (empty($this->notices)) return;
		$markup = array();
		foreach ($this->notices as $notice) {
			$markup[] = '<div class="'.$notice->style.' below-h2">';
			$markup[] = '<p>'.$notice->message.'</p>';
			$markup[] = '</div>';
		}
		if ( ! empty($markup) ) echo join('',$markup);
		$this->notices = array(); // Reset output buffer
	}

}

/**
 * Helper to access the Shopp Storefront contoller
 *
 * @author Jonathan Davis
 * @since 1.1.5
 *
 * @return Storefront|false
 **/
function &ShoppStorefront () {
	global $Shopp;
	$false = false;
	if (!isset($Shopp->Flow) || !is_object($Shopp->Flow->Controller)) return $false;
	if (get_class($Shopp->Flow->Controller) != "Storefront") return $false;
	return $Shopp->Flow->Controller;
}

add_filter('shopp_update_key','shopp_keybind');
add_filter('shopp_update_key','base64_encode');

?>