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
 * ModuleLoader
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage modules
 **/
abstract class ModuleLoader {
	
	var $legacy = array();		// Legacy module checksums
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
			else $this->legacy[] = md5_file($path.$file);
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
		do_action('shopp_'.strtolower(get_class($this)).'_loaded');
	}
	
	/**
	 * Hashes module files
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return array List of checksums
	 **/
	function checksums () {
		$hashes = array();
		foreach ($this->modules as $module) $hashes[] = md5_file($module->file);
		if (!empty($this->legacy)) $hashes = array_merge($hashes,$this->legacy);
		return $hashes;
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
		$meta = $this->readmeta($this->file);

		if ($meta) {
			$meta = preg_replace('/\r\n/',"\n",$meta); // Normalize line endings
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
				$this->{$tag} = trim($value);
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

		if (!defined('SHOPP_VERSION')) return true;
		$coreversion = '/^([\d\.])\b.*?$/';
		$shopp = preg_replace($coreversion,"$1",SHOPP_VERSION);
		$since = preg_replace($coreversion,"$1",$this->since);
		if (version_compare($shopp,$since) == -1)
			return new ShoppError(sprintf(
				__('%s could not be loaded because it requires version %s (or higher) of Shopp.','Shopp'),
				$this->name, $this->since),
				'addon_core_version',SHOPP_ADDON_ERR);
		return true;
	}
	
	/**
	 * Read the file docblock for Shopp addons
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param string $file The target file
	 * @return string The meta block from the file
	 **/
	function readmeta ($file) {
		if (!file_exists($file)) return false;
		if (!is_readable($file)) return false;

		$meta = false;
		$string = "";

		$f = @fopen($file, "r");
		if (!$f) return false;
		while (!feof($f)) {
			$buffer = fgets($f,80);
			if (preg_match("/\/\*/",$buffer)) $meta = true;
			if ($meta) $string .= $buffer;
			if (preg_match("/\*\//",$buffer)) break;
		}
		fclose($f);

		return $string;
	}
	
	
} // END class ModuleFile

/**
 * ModuleSettingsUI class
 * 
 * Provides a PHP interface for building JavaScript based module setting 
 * widgets using the ModuleSetting Javascript class.
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 **/
class ModuleSettingsUI {
	
	var $type;
	var $module;
	var $name;
	
	/**
	 * Registers a new module setting interface
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void Description...
	 **/
	function __construct($type,$module,$name,$label,$multi=false) {
		$this->type = $type;
		$this->module = $module;
		$this->name = $name;
		$multi = ($multi === false)?'false':'true';
		
		echo "\n\tvar $module = new ModuleSetting('$module','$name',".json_encode($label).",$multi);\n";
		echo "\thandlers.register('$module',$module);\n";
	}
	
	/**
	 * Renders a checkbox input
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param int $column The table column to add the element to
	 * @param array $attributes Element attributes; use 'checked' to set whether the element is toggled on or not
	 * 
	 * @return void
	 **/
	function checkbox ($column=0,$attributes=array()) {
		$attributes['type'] = "checkbox";
		$attributes['normal'] = "off";
		$attributes['value'] = "on";
		
		$attributes['checked'] = (value_is_true($attributes['checked'])?true:false);
		
		$attrs = json_encode($attributes);
		echo "$this->module.newInput($column,$attrs);\n";
	}

	/**
	 * Renders a drop-down menu element
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param int $column The table column to add the element to
	 * @param array $attributes Element attributes; use 'selected' to set the selected option
	 * @param array $options The available options in the menu
	 * 
	 * @return void
	 **/
	function menu ($column=0,$attributes=array(),$options=array()) {
		$attributes['type'] = "menu";
		$attrs = json_encode($attributes);
		$options = json_encode($options);
		echo "$this->module.newInput($column,$attrs,$options);\n";
	}
	
	/**
	 * Renders a multiple-select widget
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param int $column The table column to add the element to
	 * @param array $attributes Element attributes; pass a 'selected' attribute as an array to set the selected options
	 * @param array $options The available options in the menu
	 * 
	 * @return void
	 **/
	function multimenu ($column=0,$attributes=array(),$options=array()) {
		$attributes['type'] = "multimenu";
		$attrs = json_encode($attributes);
		$options = json_encode($options);
		echo "$this->module.newInput($column,$attrs,$options);\n";
	}
	
	/**
	 * Renders a multiple-select widget from a list of payment cards
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param int $column The table column to add the element to
	 * @param array $attributes Element attributes; pass a 'selected' attribute as an array to set the selected payment cards
	 * @param array $options The available payment cards in the menu
	 * 
	 * @return void
	 **/
	function cardmenu ($column=0,$attributes=array(),$cards=array()) {
		$attributes['type'] = "multimenu";
		$options = array();
		foreach ($cards as $card) $options[$card->symbol] = $card->name;
		$attrs = json_encode($attributes);
		$options = json_encode($options);
		echo "$this->module.newInput($column,$attrs,$options);\n";
	}

	/**
	 * Renders a text input
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param int $column The table column to add the element to
	 * @param array $attributes Element attributes; requires a 'name' attribute
	 * 
	 * @return void
	 **/
	function text ($column=0,$attributes=array()) {
		$attributes['type'] = "text";
		$attrs = json_encode($attributes);
		echo "$this->module.newInput($column,$attrs);\n";
	}
	
	/**
	 * Renders a password input
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param int $column The table column to add the element to
	 * @param array $attributes Element attributes; requires a 'name' attribute
	 * 
	 * @return void
	 **/
	function password ($column=0,$attributes=array()) {
		$attributes['type'] = "password";
		$attrs = json_encode($attributes);
		echo "$this->module.newInput($column,$attrs);\n";
	}

	/**
	 * Renders a hidden input
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param int $column The table column to add the element to
	 * @param array $attributes Element attributes; requires a 'name' attribute
	 * 
	 * @return void
	 **/
	function hidden ($column=0,$attributes=array()) {
		$attributes['type'] = "hidden";
		$attrs = json_encode($attributes);
		echo "$this->module.newInput($column,$attrs);\n";
	}

	/**
	 * Renders a text input
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param int $column The table column to add the element to
	 * @param array $attributes Element attributes; requires a 'name' attribute
	 * 
	 * @return void
	 **/
	function textarea ($column=0,$attributes=array()) {
		$attributes['type'] = "textarea";
		$attrs = json_encode($attributes);
		echo "$this->module.newInput($column,$attrs);\n";
	}


	/**
	 * Renders a styled button element
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param int $column The table column to add the element to
	 * @param array $attributes Element attributes; requires a 'name' attribute
	 * 
	 * @return void
	 **/
	function button ($column=0,$attributes=array()) {
		$attributes['type'] = "button";
		$attrs = json_encode($attributes);
		echo "$this->module.newInput($column,$attrs);\n";
	}
	
	/**
	 * Renders a paragraph element
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param int $column The table column to add the element to
	 * @param array $attributes Element attributes; requires a 'name' attribute
	 * 
	 * @return void
	 **/
	function p ($column=0,$attributes=array()) {
		$attributes['type'] = "p";
		$attrs = json_encode($attributes);
		echo "$this->module.newInput($column,$attrs);\n";
	}
		
} // END class ModuleSettingsUI

?>