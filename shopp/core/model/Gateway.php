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

interface GatewayModule {
	
	public function actions();
	public function settings();
	
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
	var $cards = false;
	var $secure = true;
	var $multi = false;
	var $settings = array();
	
	function __construct () {
		global $Shopp;
		$this->Order = &ShoppOrder();
		$this->module = get_class($this);
		$this->settings = $Shopp->Settings->get($this->module);
		if (!isset($this->settings['label']) && $this->cards) 
			$this->settings['label'] = __("Credit Card","Shopp");
		$this->_loadcards();
		if ($this->myorder()) $this->actions();
	}

	function setupui ($module,$name) {
		if (!isset($this->settings['label'])) $this->settings['label'] = $name;
		$this->ui = new ModuleSettingsUI('payment',$module,$name,$this->settings['label'],$this->multi);
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
	
	function send ($data,$url,$port=false) {
		$connection = curl_init();
		curl_setopt($connection,CURLOPT_URL,"$url".($port?":$port":""));
		curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0); 
		curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0); 
		curl_setopt($connection, CURLOPT_NOPROGRESS, 1); 
		curl_setopt($connection, CURLOPT_VERBOSE, 1); 
		curl_setopt($connection, CURLOPT_FOLLOWLOCATION,1); 
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $data); 
		curl_setopt($connection, CURLOPT_TIMEOUT, SHOPP_GATEWAY_TIMEOUT); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_REFERER, "http://".$_SERVER['SERVER_NAME']); 
		curl_setopt($connection, CURLOPT_FAILONERROR, 1);
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);   
		if ($error = curl_error($connection)) 
			new ShoppError($this->name.": ".$error,'gateway_comm_err',SHOPP_COMM_ERR);
		curl_close($connection);
		
		return $buffer;
		
	}
	
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
	
	function format ($data) {
		$query = "";
		foreach($data as $key => $value) {
			if (is_array($value)) {
				foreach($value as $item)
					$query .= '<input type="hidden" name="'.$key.'[]" value="'.attribute_escape($item).'" />';
			} else {
				$query .= '<input type="hidden" name="'.$key.'" value="'.attribute_escape($value).'" />';
			}
		}
		return $query;
	}
		
	private function _loadcards () {
		if (empty($this->settings['cards'])) $this->settings['cards'] = $this->cards;
		if ($this->cards) {
			$cards = array();
			$pcs = Lookup::paycards();
			foreach ($this->cards as $card) {
				$card = strtolower($card);
				if (isset($pcs[$card])) $cards[] = $pcs[$card];
			}
			$this->cards = $cards;
		}
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
	var $secure = false;		// SSL-required flag
	
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
		if ($this->active[$module]->secure) $this->secure = true;
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
		foreach ($this->active as $package => &$module)
			$module->setupui($package,$this->modules[$package]->name);
	}
	
}

class PayCard {
	
	var $name;
	var $symbol;
	var $pattern = false;
	var $csc = false;
	var $inputs = array();

	function __construct ($name,$symbol,$pattern,$csc=false,$inputs=array()) {
		$this->name = $name;
		$this->symbol = $symbol;
		$this->pattern = $pattern;
		$this->csc = $csc;
		$this->inputs = $inputs;
	}
	
	function validate ($pan) {
		$n = preg_replace('/[^\d+]/','',$pan);
		return ($this->match($n) && $this->checksum($n));
	}
	
	function match ($number) {
		if ($this->pattern && !preg_match($this->pattern,$number)) return false;
		return true;
	}
	
	function checksum ($number) {
		$code = strrev($number);
		for ($i = 0; $i < strlen($code); $i++) {
			$d = intval($code[$i]);
			if ($i & 1) $d *= 2;
			$cs += $d % 10;
			if ($d > 9) $cs += 1;
		}
		return ($cs % 10 != 0);
	}
	
}


?>