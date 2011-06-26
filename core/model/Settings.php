<?php
/**
 * Settings.php
 *
 * Shopp settings manager
 *
 * @author Jonathan Davis
 * @version 1.2
 * @copyright 2008-2011 Ingenesis Limited
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.0
 * @subpackage settings
 **/
class Settings extends DatabaseObject {
	static $table = 'meta';
	private static $instance;

	var $registry = array();	// Registry of setting objects
	var $available = true;		// Flag when database tables don't exist
	var $_table = '';			// The table name

	/**
	 * Settings object constructor
	 *
	 * If no settings are available (the table doesn't exist),
	 * the unavailable flag is set.
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @return void
	 **/
	function __construct ($name="") {
		$this->_table = $this->tablename(self::$table);
		if (!$this->load($name))	// If no settings are loaded
			$this->availability();	// update the Shopp tables availability status
	}

	public static function instance () {
		if ( ! self::$instance )
			self::$instance = new self();
		return self::$instance;
	}

	/**
	 * Update the availability status of the settings database table
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	function availability () {
		$this->available = $this->init('meta');
		return $this->available;
	}

	/**
	 * Load settings from the database
	 *
	 * By default, loads all settings with an autoload parameter
	 * set to "on". Otherwise, loads an individual setting explicitly
	 * regardless of the autoload parameter.
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return boolean
	 **/
	function load ($name='') {
		$Setting = $this->setting();

		$where = array("context='$Setting->context'","type='$Setting->type'");
		if (!empty($name)) $where[] = "name='".DB::clean($name)."'";
		$where = join(' AND ',$where);
		$settings = DB::query("SELECT name,value FROM $this->_table WHERE $where",'array',array(&$this,'register'));

		if (!is_array($settings) || count($settings) == 0) return false;

		if (!empty($settings)) $this->registry = array_merge($this->registry,$settings);
		return true;
	}

	function register (&$records,$record) {
		$records[$record->name] = $this->restore($record->value);
	}

	/**
	 * Add a new setting to the registry and store it in the database
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param string $name Name of the setting
	 * @param mixed $value Value of the setting
	 * @param boolean $autoload (optional) Automatically load the setting - default true
	 * @return boolean
	 **/
	function add ($name, $value) {
		$Setting = $this->setting();
		$Setting->name = $name;
		$Setting->value = DB::clean($value);

		$data = DB::prepare($Setting);
		$dataset = DatabaseObject::dataset($data);
		if (DB::query("INSERT $this->_table SET $dataset"))
		 	$this->registry[$name] = $this->restore(DB::clean($value));
		else return false;
		return true;
	}

	/**
	 * Updates the setting in the registry and the database
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param string $name Name of the setting
	 * @param mixed $value Value of the setting to update
	 * @return boolean
	 **/
	function update ($name,$value) {

		if ($this->get($name) == $value) return true;

		$Setting = $this->setting();
		$Setting->name = $name;
		$Setting->value = DB::clean($value);
		$data = DB::prepare($Setting);				// Prepare the data for db entry
		$dataset = DatabaseObject::dataset($data);	// Format the data in SQL

		$where = array("context='$Setting->context'","type='$Setting->type'");
		if (!empty($name)) $where[] = "name='".DB::clean($name)."'";
		$where = join(' AND ',$where);

		if (DB::query("UPDATE $this->_table SET $dataset WHERE $where"))
			$this->registry[$name] = $this->restore($value); // Update the value in the registry
		else return false;
		return true;
	}

	/**
	 * Save a setting to the database
	 *
	 * Sets an autoload parameter to determine whether the data is
	 * automatically loaded into the registry, or must be loaded
	 * explicitly using the get() method.
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param string $name Name of the setting to save
	 * @param mixed $value Value of the setting
	 * @param boolean $autoload (optional) The autoload setting - true by default
	 * @return void
	 **/
	function save ($name,$value) {
		// Update or Insert as needed
		if ($this->get($name) === false) $this->add($name,$value);
		else $this->update($name,$value);
	}


	/**
	 * Save a setting to the database if it does not already exist
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $name Name of the setting to save
	 * @param mixed $value Value of the setting
	 * @param boolean $autoload (optional) The autoload setting - true by default
	 * @return void
	 **/
	function setup ($name,$value,$autoload=true) {
		if ($this->get($name) === false) $this->add($name,$value,$autoload);
	}

	/**
	 * Remove a setting from the registry and the database
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param string $name Name of the setting to remove
	 * @return boolean
	 **/
	function delete ($name) {
		if (empty($name)) return false;
		$Setting = $this->setting();

		$where = array("context='$Setting->context'","type='$Setting->type'");
		if (!empty($name)) $where[] = "name='".DB::clean($name)."'";
		$where = join(' AND ',$where);

		if (!DB::query("DELETE FROM $this->_table WHERE $where")) return false;
		if (isset($this->registry[$name])) unset($this->registry[$name]);
		return true;
	}

	/**
	 * Get a specific setting from the registry
	 *
	 * If no setting is available in the registry, try
	 * loading from the database.
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return mixed The value of the setting
	 **/
	function &get ($name) {
		if (isset($this->registry[$name])) return $this->registry[$name];
		else $this->load($name);

		if (isset($this->registry[$name])) return $this->registry[$name];

		// Return false and add an entry to the registry
		// to avoid repeat database queries
		$this->registry[$name] = false;
		return $this->registry[$name];
	}

	/**
	 * Restores a serialized value to a runtime object/structure
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param string $value A value to restore if necessary
	 * @return mixed
	 **/
	function restore ($value) {
		if (!is_string($value)) return $value;
		// Return unserialized, if serialized value
		if (preg_match("/^[sibNaO](?:\:.+?\{.*\}$|\:.+;$|;$)/s",$value)) {
			$restored = unserialize($value);
			if (!empty($restored)) return $restored;
			$restored = unserialize(stripslashes($value));
			if ($restored !== false) return $restored;
		}
		return $value;
	}

	/**
	 * Provides a blank setting object template
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return object
	 **/
	function setting () {
		$setting->_datatypes = array(   'context' => 'string', 'type' => 'string',
										'name' => 'string', 'value' => 'string',
										'created' => 'date', 'modified' => 'date');
		$setting->context = 'shopp';
		$setting->type = 'setting';
		$setting->name = null;
		$setting->value = null;
		$setting->created = null;
		$setting->modified = null;
		return $setting;
	}

	/**
	 * Automatically collect and save settings from a POST form
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	function saveform () {
		if (empty($_POST['settings']) || !is_array($_POST['settings'])) return false;
		foreach ($_POST['settings'] as $setting => $value)
			$this->save($setting,$value);
	}

	function legacy ($name) {
		$table = DatabaseObject::tablename('setting');
		if ($result = DB::query("SELECT value FROM $table WHERE name='$name'",'object'))
			return $result->value;
		return false;
	}


} // END class Settings

/**
 * Helper to access the Shopp settings registry
 *
 * @author Jonathan Davis
 * @since 1.1
 *
 * @return void Description...
 **/
function ShoppSettings () {
	return Settings::instance();
}

?>