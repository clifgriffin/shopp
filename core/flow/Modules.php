<?php
/**
 * Modules.php
 * 
 * Controller and framework classes for Shopp modules
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, January 15, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @subpackage modules
 **/

/**
 * ShippingModules class
 * 
 * Controller for managing and loading the shipping modules that 
 * are installed.
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage shipping
 **/
class ShippingModules extends ModuleLoader {
	
	var $methods = array();		// Registry of shipping method handles

	/**
	 * Initializes the shipping module loader
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void Description...
	 **/
	function __construct () {

		$this->path = SHOPP_SHIPPING;
		
		// Get hooks in place before getting things started
		add_action('shopp_module_loaded',array(&$this,'addmethods'));
		add_action('shopp_settings_shipping_ui',array(&$this,'ui'));

		$this->installed();
		$this->activated();
		$this->load();
	}	
	
	/**
	 * Determines the activated shipping modules from the configured rates
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return array List of module names for the activated modules
	 **/
	function activated () {
		global $Shopp;
		$rates = $Shopp->Settings->get('shipping_rates');

		$this->activated = array();
		if (!$rates) $rates = array();

		$this->rates = $rates;
		foreach ($rates as $rate) {
			$method = explode('::',$rate['method']);
			if (!in_array($method[0],$this->activated))
				$this->activated[] = $method[0];
		}
		return $this->activated;
	}
	
	/**
	 * Loads all the installed shipping modules for the shipping settings
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function settings () {
		$this->load(true);
	}
	
	/**
	 * Adds active shipping methods to the ShippingModules method registry
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param string $module The module class name
	 * @return void
	 **/
	function addmethods ($module) {
		$m = $this->active[$module]->methods();
		if (empty($m) || !is_array($m)) return;
		
		$methods = array();
		foreach ($m as $method => $name) {
			if (is_int($method)) $method = "$module";
			else $method = "$module::$method";
			$methods[$method] = $name;
		}
		$this->methods = array_merge($this->methods,$methods);
	}
	
	/**
	 * Returns all of the active shipping methods
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return array The list of method handles
	 **/
	function methods () {
		return $this->methods;
	}
	
	/**
	 * Renders the settings interface for all activated shipping modules
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void Description...
	 **/
	function ui () {
		foreach ($this->active as $module)
			$module->ui();
	}
	
} // END class ShippingModules

/**
 * ShippingModule interface
 * 
 * Provides a structured template of object methods that must be implemented
 * in order to have a fully compatible shipping module
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage shipping
 **/
interface ShippingModule {
	
	/**
	 * Registers the functions the shipping module will implement
	 *
	 * @since 1.1
	 * 
	 * @return void
	 **/
	public function methods ();
	
	/**
	 * Embeded JavaScript to render the shipping module settings interface
	 *
	 * @since 1.1
	 * 
	 * @return void
	 **/
	public function ui ();
	
	/**
	 * Determines if the shipping module has been activated
	 * 
	 * NOTE: Automatically implemented by extending the ShippingFramework
	 *
	 * @since 1.1
	 * 
	 * @return boolean
	 **/
	public function activated();
	
	/**
	 * Used to initialize/reset shipping module calculation properties
	 * 
	 * An empty stub function must be defined even if the module does not
	 * use it
	 *
	 * @since 1.1
	 * 
	 * @return void
	 **/
	public function init ();
	
	/**
	 * Used to calculate Item-specific shipping costs
	 *
	 * An empty stub function must be defined even if the module does not
	 * use it
	 * 
	 * @since 1.1
	 * 
	 * @param int $id The index of the Item in the cart contents array
	 * @param Item $Item The cart Item object
	 * @return void
	 **/
	public function calcitem($id,$Item);
	
	/**
	 * Used to calculate aggregate shipping amounts
	 * 
	 * An empty stub function must be defined even if the module does not
	 * use it
	 *
	 * @since 1.1
	 * 
	 * @param array $options A list of current ShippingOption objects
	 * @param Order $Order A reference to the current Order object
	 * @return array The modified $options list
	 **/
	public function calculate($options,$Order);

} // END interface ShippingModule

/**
 * ShippingFramework class
 * 
 * Provides basic shipping module functionality
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage shipping
 **/
abstract class ShippingFramework {
	
	var $module = false;	// The module class name
	var $base = false;		// Base of operations settings
	var $postcode = false;	// Flag to enable the postcode field in the cart
	var $rates = array();	// The shipping rates that apply to the module
	
	/**
	 * Initializes a shipping module
	 * 
	 * Grabs settings that most shipping modules will needs and establishes
	 * the event listeners to trigger module functionality.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function __construct () {
		global $Shopp;
		$this->module = get_class($this);
		$this->settings = $Shopp->Settings->get($this->module);
		$this->base = $Shopp->Settings->get('base_operations');
		$this->units = $Shopp->Settings->get('weight_unit');

		if ($this->postcode) $Shopp->Order->Cart->showpostcode = true;

		$rates = $Shopp->Settings->get('shipping_rates');
		$this->rates = array_filter($rates,array(&$this,'myrates'));
		
		add_action('shopp_calculate_init',array(&$this,'init'));
		add_action('shopp_calculate_shipping',array(&$this,'calculate'),10,2);
		add_action('shopp_calculate_item_shipping',array(&$this,'calcitem'),10,2);
	}
	
	/**
	 * Helper to identify the rates the module handles calculations for
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param array $rate The rate configuration array
	 * @return boolean
	 **/
	function myrates ($rate) {
		$method = explode("::",$rate['method']);
		return ($method[0] == $this->module);
	}
	
	/**
	 * Determines if the current module is configured to be activated or not
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return boolean
	 **/
	function activated () {
		global $Shopp;
		$activated = $Shopp->Shipping->activated();
		return (in_array($this->module,$activated));
	}
	
	/**
	 * Initialize a list of shipping module settings
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
	
	/**
	 * Send a request to a shipping rate service provider
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return mixed The response from the service provider
	 **/
	function send ($url,$port,$data) {   
		$connection = curl_init();
		curl_setopt($connection,CURLOPT_URL,"$url:$port");
		
		// alternative port not used in some libcurl builds
		curl_setopt($connection, CURLOPT_PORT, $port);
		curl_setopt($connection, CURLOPT_FOLLOWLOCATION,0); 
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $data); 
		curl_setopt($connection, CURLOPT_TIMEOUT, 10); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_REFERER, "http://".$_SERVER['SERVER_NAME']); 
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);
		if ($error = curl_error($connection)) 
			new ShoppError($error,$this->module.'_connection',SHOPP_COMM_ERR);
		curl_close($connection);
		
		return apply_filters($this->module.'_response',$buffer);
	}
	
	/**
	 * Identify the applicable column rate from the Order shipping information
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param array $rate The shipping rate to be used
	 * @return string The column index name
	 **/
	function ratecolumn ($rate) {
		global $Shopp;
		
		$Shipping = $Shopp->Order->Shipping;

		if ($Shipping->country == $this->base['country']) {
			// Use country/domestic region
			if (isset($rate[$this->base['country']]))
				$column = $this->base['country'];  // Use the country rate
			else $column = $Shipping->postarea(); // Try to get domestic regional rate
		} else if (isset($rate[$Shipping->region])) {
			// Global region rate
			$column = $Shipping->region;
		} else $column = __('Worldwide','Shopp');

		return $column;
	}
	
} // END class ShippingFramework

/**
 * ModuleLoader
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage modules
 **/
abstract class ModuleLoader {
	
	var $modules = array();		// Installed available modules
	var $activated = array();	// List of selected modules to be activated
	var $active = array();		// Instantiated module objects
	var $path = false;			// Source path for target module files

	/**
	 * Indexes the install module files
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function installed () {
		if (!is_dir($this->path)) return false;

		$path = $this->path;
		$files = array();
		find_files(".php",$path,$path,$files);
		if (empty($files)) return $files;
	
		foreach ($files as $file) {
			// Skip if the file can't be read or isn't a real file at all
			if (!is_readable($path.$file) && !is_dir($path.$file)) continue; 			
			// Add the module file to the registry
			$module = new ModuleFile($path,$file);
			if ($module->addon) $this->modules[$module->subpackage] = $module;
		}

	}
	
	/**
	 * Loads the activated module files
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param boolean $all Loads all installed modules instead
	 * @return void
	 **/
	function load ($all=false) {
		if ($all) $activate = array_keys($this->modules);
		else $activate = $this->activated;

		foreach ($activate as $module) {
			// Module isn't available, skip it
			if (!isset($this->modules[$module])) continue; 
			// Load the file
			$this->active[$module] = &$this->modules[$module]->load();
			do_action_ref_array('shopp_module_loaded',array($module));
		}
	}
	

} // END class ModuleLoader

/**
 * ModuleFile class
 * 
 * Manages a module file
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage modules
 **/
class ModuleFile {

	var $file = false;			// The full path to the file
	var $filename = false;		// The name of the file
	var $name = false;			// The proper name of the module
	var $description = false;	// A description of the module
	var $subpackage = false;	// The class name of the module
	var $version = false;		// The version of the module
	var $since = false;			// The core version required
	var $addon = false;			// The valid addon flag
	
	/**
	 * Parses the module file meta data and validates it
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param string $path The directory the file lives in
	 * @param string $file The file name
	 * @return void
	 **/
	function __construct ($path,$file) {
		if (!is_readable($path.$file)) return;

		$this->filename = $file;
		$this->file = $path.$file;
		$meta = get_filemeta($this->file);

		if ($meta) {
			$lines = explode("\n",substr($meta,1));
			foreach($lines as $line) {
				preg_match("/^(?:[\s\*]*?\b([^@\*\/]*))/",$line,$match);
				if (!empty($match[1])) $data[] = $match[1];
				
				preg_match("/^(?:[\s\*]*?@([^\*\/]+?)\s(.+))/",$line,$match);
				if (!empty($match[1]) && !empty($match[2])) $tags[$match[1]] = $match[2];
			}

			$this->name = $data[0];
			$this->description = (!empty($data[1]))?$data[1]:"";

			foreach ($tags as $tag => $value)
				$this->{$tag} = $value;
		}
		if ($this->valid() !== true) return;
		$this->addon = true;
		
	}
	
	/**
	 * Loads the module file and instantiates the module
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function load () {
		if ($this->addon) {
			include_once($this->file);
			return new $this->subpackage();
		}
	}
	
	/**
	 * Determines if the module is a valid and compatible Shopp module
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function valid () {
		if (empty($this->version) || empty($this->since) || empty($this->subpackage)) 
			return new ShoppError(sprintf(
				__('%s could not be loaded because the file descriptors are incomplete.','Shopp'),
				$this->name),
				'addon_missing_meta',SHOPP_ADDON_ERR);
		if (version_compare(SHOPP_VERSION,$this->since) == -1)
			return new ShoppError(sprintf(
				__('%s could not be loaded because it requires version %s (or higher) of Shopp.','Shopp'),
				$this->name, $this->since),
				'addon_core_version',SHOPP_ADDON_ERR);
		return true;
	}
	
} // END class ModuleFile

?>