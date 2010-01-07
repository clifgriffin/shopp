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
 * @since 1.1
 * @package shopp
 * @author Jonathan Davis
 **/
class Flow {
	
	var $Controller = false;
	var $Admin = false;
	
	/**
	 * Flow constructor
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function __construct () {
		add_action('admin_menu',array(&$this,'menu'));
		if (defined('WP_ADMIN')) add_action('admin_init',array(&$this,'parse'));
		else add_action('parse_request',array(&$this,'parse'));
	}

	/**
	 * Parses requests and hands off processing to specific subcontrollers
	 *
	 * @return boolean
	 * @author Jonathan Davis
	 **/
	function parse () {
		global $Shopp;
		if (defined('WP_ADMIN')) {
			if ($this->welcome()) return true;

			if (!$this->Admin) return false;
			$controller = $this->Admin->controller(strtolower($_GET['page']));
			return $this->handler($controller);
		} else $this->handler('Shopping');
		
	}
	
	/**
	 * Loads a specified flow controller
	 *
	 * @return void
	 * @param string $controller The base name of the controller file
	 * @author Jonathan Davis
	 **/
	function handler ($controller) {
		if (!$controller) return false;
		error_log('parse_request');
		require_once("$controller.php");
		$this->Controller = new $controller();
		return true;
	}
	
	/**
	 * Initializes the Admin controller
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function admin () {
		if (!defined('WP_ADMIN')) return false;
		$controller = $this->Admin->controller(strtolower($_GET['page']));
		require_once("$controller.php");
		$this->Controller = new $controller();
		$this->Controller->admin();
		return true;
	}
		
	/**
	 * Defines the Shopp admin page and menu structure
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function menu () {
		require_once(SHOPP_FLOW_PATH."/Admin.php");
		$this->Admin = new AdminFlow();
		$this->Admin->menus();
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
	

} // end Flow class

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
	var $Admin = false;
	
	/**
	 * FlowController constructor
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function __construct () {
		global $Shopp;
		$this->Settings = $Shopp->Settings;
		if (!empty($Shopp->Flow->Admin)) $this->Admin = $Shopp->Flow->Admin;
	}

} // end FlowController class

?>