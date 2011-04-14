<?php
/**
 * Shipping.php
 *
 * Shipping module control and framework
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
	var $soap = false;			// Flag to load and SOAP client helper
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
		if (!has_soap() && $this->soap) require_once(SHOPP_MODEL_PATH."/SOAP.php");

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
		curl_setopt($connection, CURLOPT_TIMEOUT, SHOPP_SHIPPING_TIMEOUT);
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT);
		curl_setopt($connection, CURLOPT_REFERER, "http://".$_SERVER['SERVER_NAME']);
		curl_setopt($connection, CURLOPT_FAILONERROR, 1);
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);

		if ($data !== false) {
			curl_setopt($connection, CURLOPT_POST, 1);
			curl_setopt($connection, CURLOPT_POSTFIELDS, $data);
		}

		if (!(ini_get("safe_mode") || ini_get("open_basedir")))
			curl_setopt($connection, CURLOPT_FOLLOWLOCATION,1);

		if (defined('SHOPP_PROXY_CONNECT') && SHOPP_PROXY_CONNECT) {
	        curl_setopt($connection, CURLOPT_HTTPPROXYTUNNEL, 1);
	        curl_setopt($connection, CURLOPT_PROXY, SHOPP_PROXY_SERVER);
			if (defined('SHOPP_PROXY_USERPWD'))
			    curl_setopt($connection, CURLOPT_PROXYUSERPWD, SHOPP_PROXY_USERPWD);
	    }

		$buffer = curl_exec($connection);
		if ($error = curl_error($connection))
			new ShoppError($error,'shipping_comm_err',SHOPP_COMM_ERR);
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
		$Order = &ShoppOrder();

		$Shipping = &$Order->Shipping;

		if ($Shipping->country == $this->base['country']) {
			// Use country/domestic region
			if (isset($rate[$this->base['country']]))
				$column = $this->base['country'];  // Use the country rate
			else $column = $Shipping->postarea(); // Try to get domestic regional rate
		} else if (isset($rate[$Shipping->region])) {
			// Global region rate
			$column = $Shipping->region;
		} else $column = 'Worldwide';

		return $column;
	}

} // END class ShippingFramework


/**
*
* Packaging Utility Class
*
* Default packaging types
* package by weight/mass
* package like items together
* package each piece
* package all together
*
*/
class ShippingPackager {

	var $types = array('mass', 'like', 'piece', 'all');
	var $pack = 'mass'; // default packing behavior

	var $module = false;

	var $items = array();
	var $packages = array();

	function __construct( $options = array(), $module = false ) {
		if ($module !== false) $this->module = $module;

		$this->options = apply_filters('shopp_packager_options', $options, $module);
		$this->pack = apply_filters('shopp_packager_type',
			(isset($options['type']) && in_array($options['type'], $this->types) ? $options['type']: $this->pack),
			$module); // set packing behavior

		foreach ($this->types as $pack) add_action('shopp_packager_add_'.$pack, array(&$this, $pack.'_add'));
	}

	/**
	 * add
	 *
	 * have packager add item
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @param Item $item the item to add to packages
	 **/
	function add_item (&$item = false) {
		if ($item === false) return;
		$this->items[] = $item;

		if (isset($item->packaging) && $item->packaging == "on")
			do_action_ref_array('shopp_packager_add_piece', array(&$item, &$this) );
		else do_action_ref_array('shopp_packager_add_'.$this->pack, array(&$item, &$this) );
	}


	/**
	 * packages
	 *
	 * packages is the packages container iterator
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @return true while more packages
	 **/
	function packages () {
		if (!$this->packages) return false;
		if (!isset($this->_loop)) {
			reset($this->packages);
			$this->_loop = true;
		} else next($this->packages);

		if (current($this->packages) !== false) return true;
		else {
			unset($this->_loop);
			return false;
		}
		break;
	}

	/**
	 * package
	 *
	 * return current package
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @return Package current package, false if no packages
	 **/
	function package () {
		if (!$this->packages || !isset($this->_loop)) return false;
		return current($this->packages);
	}

	/**
	 * mass_add
	 *
	 * mass_add used to add new item in package by mass
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @param array $p packages
	 * @param Item $item Item to add
	 **/
	function mass_add (&$item) {
		$this->all_add($item, 'mass');
	}

	/**
	 * like_add
	 *
	 * like_add adds item to package if a like item
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @param Item $item Item to add
	 **/
	function like_add (&$item) {
		$limits = array();
		$defaults = array('wtl'=>-1,'wl'=>-1,'hl'=>-1,'ll'=>-1);
		extract($this->options);
		array_merge($defaults,$limits);

		// one quantity, check for existing package
		if (!empty($this->packages) && $item->quantity == 1) {
			$package = $this->packages[count($this->packages)-1];
			if(in_array("[{$item->product}][{$item->price}]",array_keys($package->contents)) && $package->limits($item)) {
				$package->add($item);
				return;
			}
		}
		$package = new ShippingPackage(true,$limits);

		if ( $package->limits($item) ) {
			$package->add($item);
			$this->packages[] = $package;
		} else if ($item->quantity > 1) {
			$pieces = clone $item;
			$piece = clone $item;
			$pieces->quantity = $pieces->quantity - 1;
			$piece->quantity = 1;

			// break one item off and recurse
			$this->like_add($pieces);
			$this->like_add($piece);
		} else {
			// doesn't "fit", and by itself
			$this->piece_add($item);
		}
	}

	/**
	 * piece_add
	 *
	 * piece_add used to add new item in piece mail packaging
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @param Item $item Item to add
	 * @return void Description...
	 **/
	function piece_add (&$item) {
		$count = $item->quantity;

		$piece = clone $item;
		$piece->quantity = 1;

		for ($i=0; $i < $count;$i++) {
			$this->packages[] = $package = new ShippingPackage(true); // no limits on individual add
			$package->add($piece);
			$package->full = true;
		}
	}

	/**
	 * all_add
	 *
	 * all_add used to add all items to one package
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @param Item $item Item to add
	 * @param string $type expect dimensions, or just mass
	 * @return void Description...
	 **/
	function all_add (&$item, $type='dims') {
		$limits = array();
		$defaults = array('wtl'=>-1,'wl'=>-1,'hl'=>-1,'ll'=>-1);
		extract($this->options);
		array_merge($defaults,$limits);

		if (empty($this->packages)) {
			$this->packages[] = new ShippingPackage(($type == 'dims'),$limits);
		} else {
			foreach($this->packages as $current) if($current->limits($item)) { $current->add($item); return;}
		}
		$current = $this->packages[count($this->packages)-1];

		if($item->quantity > 1) {  //try breaking them up
			$pieces = clone $item;
			$piece = clone $item;
			$pieces->quantity = $pieces->quantity - 1;
			$piece->quantity = 1;

			// break one item off and recurse
			$this->all_add($pieces,$type);
			$this->all_add($piece,$type);
		} else if(!empty($current->contents)) { // full, need new package
			$this->packages[] = new ShippingPackage(($type == 'dims'));
			$this->all_add($item,$type);
		} else { // never fit, ship separately
			$current->limits = $defaults;
			$current->add($item);
			$current->full = true;
		}
	}
}

class ShippingPackage {
	var $boxtype = 'custom';
	var $wt = 0; //current weight
	var $w = 0;  //current width
	var $h = 0;  //current height
	var $l = 0;  //current length

	var $dims = false; // no dimensions

	// limits for this package
	var $wtl = -1; // no weight limit
	var $wl = -1; // width limit
	var $hl = -1; // height limit
	var $ll = -1; // lenght limit

	var $full = false; // accepting items
	var $contents = array(); // Item array

	function __construct( $dims = false, $limits = array('wtl'=>-1,'wl'=>-1,'hl'=>-1,'ll'=>-1), $boxtype = 'custom' ) {
		$this->dims = $dims;
		$this->limits = $limits;
		$this->boxtype = $boxtype;
		$this->date = mktime();
	}

	function add(&$item) {
		if ($this->limits($item)) {
			if(!empty($this->contents["[{$item->product}][{$item->price}]"])) $this->contents["[{$item->product}][{$item->price}]"]->quantity += $item->quantity;
			else $this->contents["[{$item->product}][{$item->price}]"] = $item;
			$this->wt += $item->weight * $item->quantity;

			if ($this->dims) {
				$this->w = max($this->w,$item->width);
				$this->l = max($this->l,$item->length);
				$this->h = $this->h + $item->height * $item->quantity;
			}
		} else $this->full = true;

		return !$this->full;
	}

	function limits(&$item) {
		if($this->full) return apply_filters('shopp_package_limit',false, $item, $this);

		$underlimit = true;
		extract($this->limits);

		if ($this->dims && $wl > 0 && $hl > 0 && $ll > 0) {
			$underlimit = ($wl > max($this->w,$item->width) &&
				$ll > max($this->l,$item->length) &&
				$hl > ($this->h + $item->height * $item->quantity)
			);
		}

		if($wtl > 0) {
			$underlimit = $underlimit && ($wtl > ($this->wt + $item->weight * $item->quantity));
		}

		return apply_filters('shopp_package_limit',$underlimit, $item, $this); // stub, always fits
	}

}


?>