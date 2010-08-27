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
		register_deactivation_hook(SHOPP_PLUGINFILE, array(&$this, 'deactivate'));
		register_activation_hook(SHOPP_PLUGINFILE, array(&$this, 'activate'));

		if (defined('DOING_AJAX')) add_action('admin_init',array(&$this,'ajax'));

		add_action('admin_menu',array(&$this,'menu'));

		// Handle automatic updates
		add_action('update-custom_shopp',array(&$this,'update'));
				
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
		global $Shopp,$wp;

		$this->transactions();

		if (isset($wp->query_vars['src']) || 
			(defined('WP_ADMIN') && isset($_GET['src']))) $this->resources();
		
		if (defined('WP_ADMIN') && isset($_GET['page'])) {
			$controller = $this->Admin->controller(strtolower($_GET['page']));
			if (!empty($controller)) $this->handler($controller);
		} else $this->handler("Storefront");
		
	}
	
	function transactions () {
		
		if (!empty($_REQUEST['_txnupdate'])) {
			return do_action('shopp_txn_update');
		}
		
		if (!empty($_REQUEST['rmtpay'])) {
			return do_action('shopp_remote_payment');
		}
		
		
		if (isset($_POST['checkout'])) {
			if ($_POST['checkout'] == "process") do_action('shopp_process_checkout');
			if ($_POST['checkout'] == "confirmed") do_action('shopp_confirm_order');
		} else {
			if (!empty($_POST['shipmethod'])) do_action('shopp_process_shipmethod');
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
	
	function ajax () {
		if (!isset($_REQUEST['action']) || !defined('DOING_AJAX')) return;
		require_once(SHOPP_FLOW_PATH."/Ajax.php");
		$this->Ajax = new AjaxFlow();
	}

	function resources () {
		require_once(SHOPP_FLOW_PATH."/Resources.php");
		$this->Controller = new Resources();
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

		require_once(SHOPP_FLOW_PATH."/Install.php");
		if (!$this->Installer) $this->Installer = new ShoppInstallation();
	}
	
	function update () {
		$this->installation();
		do_action('shopp_autoupdate');
	}
		
	function save_settings () {
		if (empty($_POST['settings']) || !is_array($_POST['settings'])) return false;
		foreach ($_POST['settings'] as $setting => $value)
			$this->Settings->save($setting,$value);
		return true;
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
		} else add_action('shopp_loaded',array(&$this,'settings'));
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