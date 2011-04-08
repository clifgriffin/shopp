<?php
/**
 * Membership
 *
 * Description…
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, March 30, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.0
 * @subpackage shopp
 **/

/**
 * Membership
 *
 * @author Jonathan Davis
 * @since 1.2
 * @package shopp
 **/
class Membership extends DatabaseObject  {
	static $table = "meta";

	var $_table = false;	// Fully qualified table name
	var $_settings = array('slug','rules','role','continuity');

	// Meta table properties
	var $id = false;
	var $parent = 0;			// Linking reference to the root record
	var $context= 'membership';	// The meta context
	var $type = 'membership';	// Type (class) of object
	var $name = '';

	// Packed object settings
	var $slug = '';
	var $rules = array();
	var $role = 'subscriber';
	var $continuity = 'off';

	/**
	 * Membership constructor
	 *
	 * @author Jonathan Davis
	 *
	 * @return void
	 **/

	function __construct ($id=false,$key='id') {
		$this->init(self::$table);
		$this->load($id,$key);
	}


	/**
	 * Saves updates or creates records for the defined object properties
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void Description...
	 **/
	function save () {

		$this->value = array();
		foreach ($this->_settings as $property) {
			if (!isset($this->$property)) continue;
			$this->value[$property] = $this->$property;
		}

		parent::save();
	}

	/**
	 * Deletes the entire set of meta entries for the combined record
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	function delete () {
		parent::delete();
		// @todo Delete access rule records
	}


	function populate ($data) {
		// Populate normally
		parent::populate($data);

		// Remap values data to real properties
		$values = $this->value;
		foreach ($values as $property => $data)
			$this->$property = $data;
		unset($this->value);

	}


	function _ignore_ ($property) {
		return ($property[0] != "_");
	}

} // END class Membership

class MembershipSequence {

	var $membership = 0;
	var $interval = 0;
	var $period = false;

}

/**
 * MembershipAccess
 *
 * @author Jonathan Davis
 * @since 1.2
 * @package shopp
 **/
class MembershipAccess extends DatabaseObject  {
	static $table = "meta";

	var $_table = false;	// Fully qualified table name
	var $_rules = array('allow','deny');

	// Meta table properties
	var $id = false;
	var $parent = false;		// id of parent membership record
	var $context= 'membership';	// The meta context
	var $type = 'taxonomy';		// Type (class) of object
	var $name = '';				// Target content source name (wp_posts,shopp_products)
	var $value = '';			// Access setting (allow/deny)

	function __construct ($membership,$content,$rule) {
		if (!in_array($rule,$this->_rules)) {
			if (class_exists('ShoppError'))
				return new ShoppError('Invalid membership access rule specified (must use one of "allow" or "deny").','membership_rule_warning',SHOPP_DEBUG_ERR);
		}

		$this->init(self::$table);
		$this->load(array(
			'parent' => $membership,
			'context' => 'membership',
			'type' => 'taxonomy',
			'name' => $content,
			'value' => $rule
		));
		$this->parent = $membership;
		$this->name = $content;
		$this->value = $rule;
	}

}


?>