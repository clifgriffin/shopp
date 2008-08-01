<?php
/**
 * Setting class
 * Shopp settings
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

class Settings extends DatabaseObject {
	var $registry = array();
	var $unavailable = false;
	var $_table = "";
	
	function Settings () {
		$this->_table = DBPREFIX."setting";
		if (!$this->load()) {
			if (!$this->init('setting')) {
				$this->unavailable = true;
				return true;
			}
		}
	}
	
	/**
	 * Load all settings from the database */
	function load ($name="") {
		$db =& DB::get();
		if (!empty($name)) $results = $db->query("SELECT * FROM $this->_table WHERE name='$name'",AS_ARRAY,false);
		else $results = $db->query("SELECT * FROM $this->_table WHERE autoload='on'",AS_ARRAY,false);
		
		if (!is_array($results) || sizeof($results) == 0) return false;
		while(list($key,$entry) = each($results)) {
			// Return unserialized, if serialized value
			if (preg_match("/^[sibNaO](?:\:.+?\{.*\}$|\:.+;$|;$)/",$entry->value)) 
				$entry->value = unserialize($entry->value);

			$settings[$entry->name] = $entry->value;
		}

		if (!empty($settings)) $this->registry = $settings;
		return true;
	}
	
	/**
	 * Add a new setting to the registry and store it in the database */
	function add ($name, $value,$autoload = true) {
		$db =& DB::get();
		$Setting = $this->setting();
		$Setting->name = $name;
		$Setting->value = $value;
		$Setting->autoload = ($autoload)?'on':'off';
		
		$data = $db->prepare($Setting);
		$dataset = DatabaseObject::dataset($data);
		$this->registry[$name] = $value;
		if (!$db->query("INSERT $this->_table SET $dataset")) return false;
		return true;
	}
	
	/**
	 * Remove a setting from the registry and the database */
	function delete ($name) {
		$db =& DB::get();
		unset($this->registry[$name]);
		if (!$db->query("DELETE FROM $this->_table WHERE name='$name'")) return false;
		return true;
	}
	
	/**
	 * Updates the setting in the registry and the database */
	function update ($name,$value) {
		$db =& DB::get();

		if ($this->get($name) == $value) return true;

		$Setting = $this->setting();
		$Setting->name = $name;
		$Setting->value = $value;
		unset($Setting->autoload);
		$data = $db->prepare($Setting);				// Prepare the data for db entry
		$dataset = DatabaseObject::dataset($data);	// Format the data in SQL
		
		$this->registry[$name] = $value;			// Update the value in the registry
		if (!$db->query("UPDATE $this->_table SET $dataset WHERE name='$Setting->name'")) return false;
		return true;
	}
	
	function save ($name,$value) {
		// Update or Insert as needed
		if ($this->get($name) === false) $this->add($name,$value);
		else $this->update($name,$value);
	}
	
	/**
	 * Get a specific setting from the registry */
	function get ($name) {
		$value = false;
		if (isset($this->registry[$name])) {
			$value = $this->registry[$name];
		} else if ($this->load($name)) {
			$value = $this->registry[$name];
		}
		return $value;
	}
	
	/**
	 * Return a blank setting object */
	function setting () {
		$setting->_datatypes = array("name" => "string", "value" => "string", "autoload" => "list", 
			"created" => "date", "modified" => "date");
		$setting->name = null;
		$setting->value = null;
		$setting->autoload = null;
		$setting->created = null;
		$setting->modified = null;
		return $setting;
	}
	
} // END class Settings

?>