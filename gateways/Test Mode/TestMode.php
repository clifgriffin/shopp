<?php
/**
 * Test Mode
 * @class TestMode
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 9 April, 2008
 * @package Shopp
 **/

class TestMode {
	var $transaction = array();
	var $settings = array();
	var $Response = false;

	function TestMode (&$Order="") {
		global $Shopp;
		$this->settings = $Shopp->Settings->get('TestMode');
		return true;
	}
	
	function process () {
		if ($this->settigns['response'] == "error") return false;
		return true;
	}
	
	function transactionid () {
		if ($this->settigns['response'] == "error") return "";
		return "TESTMODE";
	}
	
	function error () {
		if (!empty($this->Response)) {
			$Error = new stdClass();
			$Error->code = $this->Response->reasoncode;
			$Error->message = $this->Response->reason;
			return $Error;
		}
	}
	
	function build (&$Order) {
	}
	
	function response () {
	}
	
	function settings () {
		global $Shopp;
		$settings = $Shopp->Settings->get('TestMode');
		?>
				
		var testmode_settings = function () {

			addSetting("Test Mode Response",
							{'name':'settings[TestMode][response]',
							 'id':'gateway_testmode',
							 'type':'checkbox',
							 'value':'error',
							 'unchecked':'success',
							 'checked':<?php echo ($settings['response'] == "error")?'true':'false'; ?>},
							 "Test Error Response");
			}
			
			settings_callback['<?php echo __FILE__; ?>'] = testmode_settings;

		<?
	}

} // end AuthorizeNet class

?>