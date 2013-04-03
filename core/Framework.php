<?php
/**
 * Framework
 *
 * Library of abstract design pattern templates
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, May  5, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package
 * @since 1.0
 * @subpackage framework
 **/

defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit; // Prevent direct access

/**
 * Implements a list manager with internal iteration support
 *
 * @author Jonathan Davis
 * @since 1.2
 * @package shopp
 **/
class ListFramework implements Iterator {

	private $_list = array();
	private $_keys = array();
	private $_added = '';
	private $_position = 0;
	private static $_false = false;

	public function &add ( scalar $key, $entry ) {
		$this->_list[$key] = $entry;
		$this->_added = $key;
		$this->rekey();
		return $this->get($key);
	}

	public function added ( $key = false ) {
		if ( false !== $key && $this->exists($key) )
			$this->_added = $key;
		if ( $this->exists($this->_added) )
			return $this->get($this->_added);
		return false;
	}

	public function populate ($records) {
		$this->_list = $records;
		$this->rekey();
	}

	public function update ($key,$entry) {
		if ( ! $this->exists($key) ) return false;
		if ( is_array($this->_list[$key]) && is_array($entry) )
			$entry = array_merge($this->_list[$key],$entry);
		else $this->_list[$key] = $entry;
	}

	public function &get ($key) {
		if ( $this->exists($key) ) return $this->_list[$key];
		else return $this->_false;
	}

	public function exists ($key) {
		return array_key_exists($key,$this->_list);
	}

	public function remove ($key) {
		if ( ! $this->exists($key) ) return false;
		unset($this->_list[$key]);
		$this->rekey();
	}

	private function rekey () {
		$this->_keys = array_keys($this->_list);
	}

	public function keyin ( $position = false ) {
		if ( false !== $position && isset($this->_keys[ (int)$position ]) )
			return $this->_keys[ (int)$position ];
		return $this->key();
	}

	public function &current () {
		if ( $this->valid() )
			return $this->_list[ $this->_keys[$this->_position] ];
		return $this->_false;
	}

	public function key ( ) {
		if ( isset($this->_keys[ $this->_position ]) )
			return $this->_keys[ $this->_position ];
		return false;
	}

	public function next () {
		++$this->_position;
		return $this->current();
	}

	public function rewind () {
		$this->_position = 0;
		return $this->current();
	}

	public function valid () {
		return (
			array_key_exists($this->_position,$this->_keys)
			&& array_key_exists($this->_keys[$this->_position],$this->_list)
		);
	}

}

/**
 * Implements a Singleton pattern object
 *
 * @author Jonathan Davis
 * @since 1.2
 * @package shopp
 **/
class SingletonFramework {

	// @todo Requires Late-Static Binding in PHP 5.3 before extending the framework for instance return method to work

	// protected static $instance;

	// public static function instance () {
	// 	if (!self::$instance instanceof self)
	// 		self::$instance = new self;
	// 	return self::$instance;
	// }

	/**
	 * Prevents constructing new instances of singletons
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	protected function __construct () {}

	/**
	 * Prevents cloning singletons
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	protected function __clone () {}

}

class AutoObjectFramework {

	function __construct ($input) {
		$properties = get_object_vars($this);
		$args = func_num_args();
		if ($args > 1) {
			$params = func_get_args();
			$propkeys = array_keys($properties);
			$keys = array_splice($propkeys,0,$args);
			$inputs = array_combine($keys,$params);
		}
		else $inputs = $input;

		if (!is_array($inputs)) return;
		foreach ($inputs as $name => $value)
			if (property_exists($this,$name))
				$this->$name = $value;
	}

}

class SubscriberFramework {

	private $subscribers = array();

	function subscribe ($target,$method) {
		if ( ! isset($this->subscribers[get_class($target)]))
			$this->subscribers[get_class($target)] = array(&$target,$method);
	}

	function send () {
		$args = func_get_args();
		foreach ($this->subscribers as $callback) {
			call_user_func_array($callback,$args);
		}
	}

}