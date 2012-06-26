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

	static $table = 'meta';			// Base settings table name

	private static $instance;
	private $registry = array();	// Registry of setting objects
	private $installed = false;		// Flag when database tables don't exist
	private $loaded = false;		// Flag when settings are successfully loaded
	private $bootup = false;		// Load process in progress

	var $_table;					// Settings runtime table name

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
	function __construct () {
		$this->_table = $this->tablename(self::$table);
	}

	static function &instance () {
		if (!self::$instance instanceof self)
			self::$instance = new self;
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
	function available () {
		return ($this->loaded && !empty($this->registry));
	}

	/**
	 * Load settings from the database
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return boolean
	 **/
	function load ($name='',$arg2=false) {
		$Setting = $this->setting();

		$where = array("parent=0","context='$Setting->context'","type='$Setting->type'");
		if (!empty($name)) $where[] = "name='".DB::clean($name)."'";
		else {
			if ($this->bootup) return false; // Already trying to load all settings, bail out to prevent an infinite loop of DOOM!
			$this->bootup = true;
		}

		$where = join(' AND ',$where);
		$settings = DB::query("SELECT name,value FROM $this->_table WHERE $where",'array',array(&$this,'register'));

		if (!is_array($settings) || count($settings) == 0) return false;
		if (!empty($settings)) $this->registry = array_merge($this->registry,$settings);

		$this->bootup = false;
		return ($this->loaded = true);
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
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param string $name Name of the setting to save
	 * @param mixed $value Value of the setting
	 * @return void
	 **/
	function save ($name=false,$value=false) {
		if (empty($name)) return false;
		// Update or Insert as needed
		if ( is_null($this->get($name)) ) $this->add($name,$value);
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
	 * @return void
	 **/
	function setup ($name,$value) {
		if (is_null($this->get($name))) $this->add($name,$value);
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
	function delete ($name=false) {
		$null = null;
		if (empty($name)) return false;
		$Setting = $this->setting();

		$where = array("context='$Setting->context'","type='$Setting->type'");
		if (!empty($name)) $where[] = "name='".DB::clean($name)."'";
		$where = join(' AND ',$where);

		if (!DB::query("DELETE FROM $this->_table WHERE $where")) return false;
		if (isset($this->registry[$name])) $this->registry[$name] = $null;
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
		$null = null; if ($this->bootup) return $null; // Prevent infinite loop of DOOM!

		if (!$this->available()) $this->load();

		if (array_key_exists($name,$this->registry)) return $this->registry[$name];
		else $this->load($name);

		if (isset($this->registry[$name])) return $this->registry[$name];

		// Return false and add an entry to the registry
		// to avoid repeat database queries
		$this->registry[$name] = $null;
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
		$setting = new stdClass();
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
function &ShoppSettings () {
	return Settings::instance();
}

?>