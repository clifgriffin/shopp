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
		if ($this->settings['response'] == "error") return false;
		return true;
	}
	
	function transactionid () {
		if ($this->settings['response'] == "error") return "";
		return "TESTMODE";
	}
	
	function error () {
		if (!$this->Response) {
			$Error = new stdClass();
			$Error->code = "000";
			$Error->message = "This is an example error message triggered by the Test Mode error setting.";
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
			
			gatewayHandlers.register('<?php echo __FILE__; ?>',testmode_settings);

		<?
	}

} // end AuthorizeNet class

?>