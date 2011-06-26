<?php
/**
 * settings
 *
 * plugin API for getting, setting/creating, and deleting Shopp settings.
 *
 * @author Jonathan Davis, John Dillick
 * @version 1.0
 * @copyright Ingenesis Limited, June 23, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.2
 * @subpackage shopp
 **/

/**
 * shopp_setting - returns a named Shopp setting
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param string $name The name of the setting
 * @return mixed the value saved to the named setting, or false if not set.  returns null if empty name is provided
 **/
function shopp_setting ( $name ) {
	$setting = null;

	if ( empty($name) ) {
		if(SHOPP_DEBUG) new ShoppError("shopp_setting lookup failed: Setting name parameter required.",'shopp_setting',SHOPP_DEBUG_ERR);
		return false;
	}

	$setting = ShoppSettings()->get($name);

	return $setting;
}

/**
 * shopp_set_setting - saves a name value pair as a Shopp setting
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param string $name The name of the setting that is to be stored.
 * @param mixed $value The value saved to the named setting.
 * @return bool true on success, false on failure.
 **/
function shopp_set_setting ( $name, $value ) {
	if ( empty($name) ) {
		if(SHOPP_DEBUG) new ShoppError("shopp_set_setting failed: Setting name parameter required.",'shopp_set_setting',SHOPP_DEBUG_ERR);
		return false;
	}

	ShoppSettings()->save($name, $value);
	return true;
}

/**
 * shopp_rmv_setting - deletes a named setting
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param string $name Name of the Shopp setting to be deleted
 * @return bool true on success, false on failure
 **/
function shopp_rmv_setting ($name) {
	if ( empty($name) ) {
		if(SHOPP_DEBUG) new ShoppError("shopp_rmv_setting failed: Setting name parameter required.",'shopp_rmv_setting',SHOPP_DEBUG_ERR);
		return false;
	}
	return ShoppSettings()->delete($name);
}

?>