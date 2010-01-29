<?php
/**
 * Gateway classes
 * 
 * Generic prototype classes for local and remote payment systems
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, March 17, 2009
 * @package shopp
 * @subpackage gateways
 **/

/**
 * GatewayModule interface
 * 
 * Provides a template of required methods in order for a gateway to be 
 * fully integrated with Shopp.
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage gateways
 **/
interface GatewayModule {
	
}

/**
 * GatewayFramework class
 * 
 * Provides default helper methods for gateway modules.
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage gateways
 **/
abstract class GatewayFramework {
	
	var $Order = false;
	var $name = false;
	
	function __construct () {
		global $Shopp;
		$this->Order = $Shopp->Order;
		$this->module = get_class($this);
		$this->settings = $Shopp->Settings->get($this->module);
		$this->setup('label');
	}

	function setupui ($module,$name) {
		$this->ui = new ModuleSettingsUI('payment',$module,$name,$this->settings['label']);
		$this->settings();
	}
	
	/**
	 * Initialize a list of gateway module settings
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param string $name The name of a setting
	 * @param string $name... (optional) Additional setting names to initialize
	 * @return void
	 **/
	function setup () {
		$settings = func_get_args();
		foreach ($settings as $name)
			if (!isset($this->settings[$name]))
				$this->settings[$name] = false;
	}
	
	function myorder () {
		return ($this->Order->processor == $this->module);
	}
	
	function txnid () {
		return mktime();
	}
	
} // end Gateway class


/**
 * GatewayModules class
 * 
 * Gateway module file manager to load gateways that are active.
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage gateways
 **/
class GatewayModules extends ModuleLoader {
	
	var $selected = false;		// The chosen gateway to process the order
	
	/**
	 * Initializes the shipping module loader
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void Description...
	 **/
	function __construct () {

		$this->path = SHOPP_GATEWAYS;
		
		// Get hooks in place before getting things started
		add_action('shopp_module_loaded',array(&$this,'properties'));
		// add_action('shopp_settings_shipping_ui',array(&$this,'ui'));

		$this->installed();
		$this->activated();
		$this->load();
	}
	
	/**
	 * Determines the activated gateway modules
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return array List of module names for the activated modules
	 **/
	function activated () {
		global $Shopp;
		$this->activated = array();
		$gateways = explode(",",$Shopp->Settings->get('active_gateways'));
		foreach ($this->modules as $gateway)
			if (in_array($gateway->subpackage,$gateways))
				$this->activated[] = $gateway->subpackage;

		return $this->activated;
	}
	
	function properties ($module) {
		if (!isset($this->active[$module])) return;
		$this->active[$module]->name = $this->modules[$module]->name;
	}
	
	
	/**
	 * Loads all the installed gateway modules for the payments settings
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function settings () {
		$this->load(true);
	}
	
	function ui () {
		foreach ($this->active as $package => $module) {
			$module->setupui($package,$this->modules[$package]->name);
		}
	}
	
}


?>