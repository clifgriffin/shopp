<?php
/**
 * Shipping.php
 * 
 * Shipping addresses and shipping rate modules
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, 28 March, 2008
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.1
 * @subpackage shipping
 **/

/**
 * Shipping class
 * 
 * The shipping address manager
 *
 * @author Jonathan Davis
 * @since 1.0
 * @package shipping
 **/
class Shipping extends DatabaseObject {
	static $table = "shipping";
	var $method = false;
	
	function __construct ($id=false,$key=false) {
		$this->init(self::$table);
		if ($id && $this->load($id,$key)) return true;
		else return false;
	}
	
	/**
	 * Registry of supported export fields
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return array
	 **/
	function exportcolumns () {
		$prefix = "s.";
		return array(
			$prefix.'address' => __('Shipping Street Address','Shopp'),
			$prefix.'xaddress' => __('Shipping Street Address 2','Shopp'),
			$prefix.'city' => __('Shipping City','Shopp'),
			$prefix.'state' => __('Shipping State/Province','Shopp'),
			$prefix.'country' => __('Shipping Country','Shopp'),
			$prefix.'postcode' => __('Shipping Postal Code','Shopp'),
			);
	}
	
	/**
	 * Determines the domestic area name from a U.S. ZIP code or
	 * Canadian postal code.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return string
	 **/
	function postarea () {
		global $Shopp;
		$code = $this->postcode;
		$areas = Lookup::country_areas();
		
		// Skip if there are no areas for this country
		if (!isset($areas[$this->country])) return false;

		// If no postcode is provided, return the first regional column
		if (empty($this->postcode)) return key($areas[$this->country]);
		
		// Lookup US area name
		if (preg_match("/\d{5}(\-\d{4})?/",$code)) {
			
			foreach ($areas['US'] as $name => $states) {
				foreach ($states as $id => $coderange) {
					for($i = 0; $i<count($coderange); $i+=2) {
						if ($code >= (int)$coderange[$i] && $code <= (int)$coderange[$i+1]) {
							$this->state = $id;
							return $name;
						}
					}
				}
			}
		}
		
		// Lookup Canadian area name
		if (preg_match("/\w\d\w\s*\d\w\d/",$code)) {
			
			foreach ($areas['CA'] as $name => $provinces) {
				foreach ($provinces as $id => $fsas) {
					if (in_array(substr($code,0,1),$fsas)) {
						$this->state = $id; 
						return $name; 
					}
				}
			}
			return $name;
			
		}
		
		return false;
	}
	
	/**
	 * Sets the shipping address location for calculating
	 * shipping estimates.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void Description...
	 **/
	function destination ($data=false) {
		global $Shopp;
		
		$base = $Shopp->Settings->get('base_operations');
		$countries = Lookup::countries();
		$regions = Lookup::regions();
		
		if ($data) $this->updates($data);

		// Update state if postcode changes for tax updates
		if (isset($this->postcode))
			$this->postarea();
		
		if (empty($this->country))
			$this->country = $base['country'];

		$this->region = $regions[$countries[$this->country]['region']];

	}
	

} // END class Shipping

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

	var $dimensions = false;	// Flags when a module requires product dimensions
	var $postcodes = false;		// Flags when a module requires a post code for shipping estimates
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
		if (!isset($this->active[$module])) return;
		$m = $this->active[$module]->methods();
		if (empty($m) || !is_array($m)) return;
		
		if ($this->active[$module]->postcode) $this->postcodes = true;
		if ($this->active[$module]->dimensions) $this->dimensions = true;

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
	
	var $module = false;		// The module class name
	var $base = false;			// Base of operations settings
	var $postcode = false;		// Flag to enable the postcode field in the cart
	var $rates = array();		// The shipping rates that apply to the module
	var $dimensions = false;	// Uses dimensions in calculating estimates
	var $xml = false;			// Flag to load and enable XML parsing
	var $singular = false;		// Shipping module can only be loaded once
	
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
		if ($this->xml) require_once(SHOPP_MODEL_PATH."/XML.php");

		$rates = $Shopp->Settings->get('shipping_rates');
		$this->rates = array_filter($rates,array(&$this,'myrates'));
		if ($this->singular && is_array($this->rates) && !empty($this->rates))  $this->rate = reset($this->rates); 
		
		add_action('shopp_calculate_shipping_init',array(&$this,'init'));
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
	 * Generic connection manager for sending data
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param string $data The encoded data to send, false for GET queries
	 * @param string $url The URL to connect to
	 * @param string $port (optional) Connect to a specific port
	 * @return string Raw response
	 **/
	function send ($data=false,$url,$port=false) {
		$connection = curl_init();
		curl_setopt($connection,CURLOPT_URL,"$url".($port?":$port":""));
		curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0); 
		curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($connection, CURLOPT_NOPROGRESS, 1); 
		curl_setopt($connection, CURLOPT_VERBOSE, 1); 
		curl_setopt($connection, CURLOPT_FOLLOWLOCATION,1); 
		if ($data !== false) {
			curl_setopt($connection, CURLOPT_POST, 1); 
			curl_setopt($connection, CURLOPT_POSTFIELDS, $data);
		} 
		curl_setopt($connection, CURLOPT_TIMEOUT, SHOPP_SHIPPING_TIMEOUT); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_REFERER, "http://".$_SERVER['SERVER_NAME']); 
		curl_setopt($connection, CURLOPT_FAILONERROR, 1);
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);   
		if ($error = curl_error($connection)) 
			new ShoppError($this->name.": ".$error,'shipping_comm_err',SHOPP_COMM_ERR);
		curl_close($connection);
		
		return $buffer;
		
	}
	
	/**
	 * Helper to encode a data structure into a URL-compatible format
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param array $data Key/value pairs of data to encode
	 * @return string
	 **/
	function encode ($data) {
		$query = "";
		foreach($data as $key => $value) {
			if (is_array($value)) {
				foreach($value as $item) {
					if (strlen($query) > 0) $query .= "&";
					$query .= "$key=".urlencode($item);
				}
			} else {
				if (strlen($query) > 0) $query .= "&";
				$query .= "$key=".urlencode($value);
			}
		}
		return $query;
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


?>