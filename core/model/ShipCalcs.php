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
	
	function ShipCalcs (&$Settings,$basepath) {

		$shipcalcs_path = $basepath."/shipping";
		$lastscan = $Settings->get('shipcalc_lastscan');
		$lastupdate = filemtime($shipcalcs_path);

		$modfiles = array();
		
		if ($lastupdate > $lastscan) {
			$modfilescan = array();
			find_files(".php",$shipcalcs_path,$shipcalcs_path,$modfilescan);

			if (empty($modfilescan)) return $modfilescan;
			foreach ($modfilescan as $file) {
				if (! is_readable($shipcalcs_path.$file)) continue;
				$ShipCalcClass = substr(basename($file),0,-4);
				$modfiles[$ShipCalcClass] = $shipcalcs_path.$file;
			}
			$Settings->save('shipcalc_modules',$modfiles);
			$Settings->save('shipcalc_lastscan',mktime());
		} else {
			$modfiles = $Settings->get('shipcalc_modules');
		}
		
		foreach ($modfiles as $ShipCalcClass => $file) {
			include($file);
			$this->modules[$ShipCalcClass] = new $ShipCalcClass();
			$this->modules[$ShipCalcClass]->methods($this);
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
		
	function ui () {
		foreach ($this->modules as $ShipCalcClass => &$module) $module->ui();
	}

} // end ShipCalcs class

?>