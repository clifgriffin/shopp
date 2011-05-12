<?php
/**
 * API
 *
 * Shopp's Application Programming Interface library manager
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, May 12, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.0
 * @subpackage shopp
 **/

/**
 * ShoppAPILoader
 *
 * @author Jonathan Davis
 * @since 1.2
 * @package shopp
 **/
class ShoppAPIModules extends ModuleLoader {

	protected $loader = 'ShoppAPIFile';

	/**
	 * API constructor
	 *
	 * @author Jonathan Davis
	 *
	 * @return void
	 **/
	function __construct () {
		$this->path = SHOPP_THEME_APIS;

		$this->installed(); // Find modules
		$this->load(true); 	// Load all

	}

} // END class ShoppAPILoader

class ShoppAPIFile extends ModuleFile {

	function load () {
		include_once($this->file);
		$this->register();
	}

	function register () {
		// Hook _context
		$class = $this->subpackage;
		add_filter('shopp_themeapi_object', array($this, 'context'), 10, 2);

		// Define a static $map property as an associative array or tag => member function names.
		// Without the tag key, it will be registered as a general purpose filter for all tags in this context
		if (!empty($class::$register)) {
			foreach ( $class::$register as $tag => $method ) {
				if ( is_callable(array($class, $method)) ) {
					if ( is_numeric($tag) ) add_filter( 'shopp_themeapi_'.strtolower($class::$context), array($class, $method), 10, 4 ); // general filter
					else add_filter( 'shopp_themeapi_'.strtolower($class::$context.'_'.$tag), array($class, $method), 10, 3 );
				}
			}
			return;
		}

		// Otherwise, the register function will assume that all method names (excluding _ prefixed methods) correspond to tag you want.
		// _ prefix members can be used as helper functions
		$methods = array_filter( get_class_methods ($class), create_function( '$m','return ( "_" != substr($m, 0, 1) );' ) );
		foreach ( $methods as $tag )
			add_filter( 'shopp_themeapi_'.strtolower($class::$context.'_'.$tag), array($class, $tag), 10, 3 );
	}

	function context ($Object,$object) {
		$class = $this->subpackage;
		$context = $class::$context;

		if (method_exists($class,'_context')) return $class::_context();

		if (strtolower($object) != strtolower($class::$context)) return $Object; // do nothing

		if (is_object($Object) && self::$context == get_class($Object)) return $Object;  // still do nothing

		global $Shopp;
		if (property_exists($Shopp->{self::$context})) {
			return $Shopp->{self::$context};
		}
		return false;
	}

}

?>