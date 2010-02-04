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
		$meta = get_filemeta($this->file);

		if ($meta) {
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
				$this->{$tag} = $value;
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
	
} // END class ModuleFile

?>