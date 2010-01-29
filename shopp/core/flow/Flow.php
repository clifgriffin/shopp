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
	var $Logins = false;
	
	/**
	 * Flow constructor
	 *
	 * @author Jonathan Davis
	 * 
	 * @return void
	 **/
	function __construct () {
		// register_deactivation_hook(SHOPP_PLUGINFILE, array(&$this, 'activate'));
		// register_activation_hook(SHOPP_PLUGINFILE, array(&$this, 'deactivate'));
			
		add_action('admin_menu',array(&$this,'menu'));
		if (defined('WP_ADMIN')) add_action('admin_init',array(&$this,'parse'));
		else add_action('parse_request',array(&$this,'parse'));
	}

	/**
	 * Parses requests and hands off processing to specific subcontrollers
	 *
	 * @author Jonathan Davis
	 * 
	 * @return boolean
	 **/
	function parse () {
		global $Shopp;
		
		$this->transactions();
		
		if (defined('WP_ADMIN') && isset($_GET['page'])) {
			$controller = $this->Admin->controller(strtolower($_GET['page']));
			if (!empty($controller)) $this->handler($controller);
		} else $this->handler("Storefront");
	}
	
	function logins () {
		
		if (!empty($_POST['process-login']) && $_POST['process-login'] == "true") 
			do_action('shopp_auth');
		
	}
	
	function transactions () {
		
		if (isset($_REQUEST['stn'])) return do_action('shopp_txn_notification');
		
		if (isset($_POST['checkout'])) {
			if ($_POST['checkout'] == "process") do_action('shopp_process_checkout');
			if ($_POST['checkout'] == "confirmed") do_action('shopp_confirm_order');
		}
		
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
		require_once(SHOPP_FLOW_PATH."/$controller.php");
		$this->Controller = new $controller();
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
		require_once(SHOPP_FLOW_PATH."/$controller.php");
		$this->Controller = new $controller();
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
		require_once(SHOPP_FLOW_PATH."/Admin.php");
		$this->Admin = new AdminFlow();
		$this->Admin->menus();
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
		require_once(SHOPP_FLOW_PATH."/Install.php");
		$Installation = new ShoppInstallation();
		$Installation->install();
	}
		
	/**
	 * deactivate()
	 * Resets the data_model to prepare for potential upgrades/changes to the table schema */
	function deactivate() {
		global $wpdb,$wp_rewrite;

		// Unpublish/disable Shopp pages
		$filter = "";
		$pages = $this->Settings->get('pages');
		if (!is_array($pages)) return true;
		foreach ($pages as $page) $filter .= ($filter == "")?"ID={$page['id']}":" OR ID={$page['id']}";	
		if ($filter != "") $wpdb->query("UPDATE $wpdb->posts SET post_status='draft' WHERE $filter");

		// Update rewrite rules
		$wp_rewrite->flush_rules();
		$wp_rewrite->wp_rewrite_rules();

		$this->Settings->save('data_model','');

		return true;
	}
	
	
	/**
	 * Displays the welcome screen
	 *
	 * @return boolean
	 * @author Jonathan Davis
	 **/
	function welcome () {
		global $Shopp;
		if ($Shopp->Settings->get('display_welcome') == "on" && empty($_POST['setup'])) {
			include(SHOPP_ADMIN_PATH."/help/welcome.php");
			return true;
		}
		return false;
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
	
	var $Settings = false;
	
	/**
	 * FlowController constructor
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function __construct () {
		if (defined('WP_ADMIN')) {
			add_action('admin_init',array(&$this,'settings'));
			$this->settings();
		} else add_action('shopp_init',array(&$this,'settings'));
	}
	
	function settings () {
		global $Shopp;
		if (!$this->Settings && !empty($Shopp)) 
			$this->Settings = &$Shopp->Settings;
	}

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

	/**
	 * AdminController constructor
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function __construct () {
		parent::__construct();
		global $Shopp;
		if (!empty($Shopp->Flow->Admin)) $this->Admin = &$Shopp->Flow->Admin;
	}
	
}

?>