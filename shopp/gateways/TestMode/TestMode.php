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
	var $cards = array("Visa","MasterCard","Discover","American Express");

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
		?>
		<tr id="testmode-settings">
			<th scope="row" valign="top">Test Mode</th>
			<td>
				<input type="hidden" name="settings[TestMode][response]" value="success" /><input type="checkbox" name="settings[TestMode][response]" id="testmode_response" value="error"<?php echo ($this->settings['response'] == "error")?' checked="checked"':''; ?> /><label for="testmode_response"> <?php _e('Test error response'); ?></label>
			</td>
		</tr>
		<?php
	}
	
	function registerSettings () {
		?>
		gatewayHandlers.register('<?php echo addslashes(__FILE__); ?>','testmode-settings');
		<?php
	}
	

} // end AuthorizeNet class

?>