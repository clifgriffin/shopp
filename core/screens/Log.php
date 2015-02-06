<?php
/**
 * Log.php
 *
 * Log settings screen controller
 *
 * @copyright Ingenesis Limited, February 2015
 * @license   GNU GPL version 3 (or later) {@see license.txt}
 * @package   Shopp/Admin/Settings
 * @version   1.0
 * @since     1.4
 **/

defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit; // Prevent direct access

class ShoppScreenLog extends ShoppSettingsScreenController {

	public function updates () {
		if ( ! isset($_POST['resetlog']) ) return;

		ShoppErrorLogging()->reset();
		$this->notice(Shopp::__('The log file has been reset.'));
	}

	public function screen () {
		include $this->ui('log.php');
	}

} // class ShoppScreenLog