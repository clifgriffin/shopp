<?php
/**
 * ShipCalcs class
 * Manages shipping method calculators
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 29 April, 2008
 * @package shopp
 **/

class ShipCalcs {
	var $modules = array();
	var $methods = array();
	
	function ShipCalcs ($basepath) {
		global $Shopp;

		$shipcalcs_path = $basepath.DIRECTORY_SEPARATOR."shipping";
		$lastscan = $Shopp->Settings->get('shipcalc_lastscan');
		$lastupdate = filemtime($shipcalcs_path);
		
		$modfiles = array();
		if (true || $lastupdate > $lastscan) $modfiles = $this->scanmodules($shipcalcs_path);
		else {
			$modfiles = $Shopp->Settings->get('shipcalc_modules');
			if (empty($modfiles)) $modfiles = $this->scanmodules($shipcalcs_path);
		}
	
		if (!empty($modfiles)) {
			foreach ($modfiles as $ShipCalcClass => $file) {
				include($file);
				$this->modules[$ShipCalcClass] = new $ShipCalcClass();
				$this->modules[$ShipCalcClass]->methods($this);
			}
		}
						
	}
	
	function readmeta ($modfile) {
		$metadata = array();

		$meta = get_filemeta($modfile);

		if ($meta) {
			$lines = split("\n",substr($meta,1));
			foreach($lines as $line) {
				preg_match("/^(?:[\s\*]*?\b([^@\*\/]*))/",$line,$match);
				if (!empty($match[1])) $data[] = $match[1];
				preg_match("/^(?:[\s\*]*?@([^\*\/]+?)\s(.+))/",$line,$match);
				if (!empty($match[1]) && !empty($match[2])) $tags[$match[1]] = $match[2];

			}
			$module = new stdClass();
			$module->file = $modfile;
			$module->name = $data[0];
			$module->description = (!empty($data[1]))?$data[1]:"";
			$module->tags = $tags;
			return $module;
		}
		return false;
	}
	
	function scanmodules ($path) {
		global $Shopp;
		$modfilescan = array();
		find_files(".php",$path,$path,$modfilescan);

		if (empty($modfilescan)) return $modfilescan;
		foreach ($modfilescan as $file) {
			if (! is_readable($path.$file)) continue;
			$ShipCalcClass = substr(basename($file),0,-4);
			$modfiles[$ShipCalcClass] = $path.$file;
		}
		
		$Shopp->Settings->save('shipcalc_modules',addslashes(serialize($modfiles)));
		$Shopp->Settings->save('shipcalc_lastscan',mktime());

		return $modfiles;
	}
		
	function ui () {
		foreach ($this->modules as $ShipCalcClass => &$module) $module->ui();
	}

} // end ShipCalcs class

?>