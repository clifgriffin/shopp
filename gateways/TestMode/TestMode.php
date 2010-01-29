<?php
/**
 * Test Mode
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, 9 April, 2008
 * @package Shopp
 * @since 1.1 dev
 * @subpackage TestMode
 * 
 * $Id$
 **/

class TestMode extends GatewayFramework {
	var $transaction = array();
	var $settings = array();
	var $Response = false;
	var $cards = array("Visa","MasterCard","Discover","American Express");

	function TestMode () {
		parent::__construct();
		$this->setup('error');
		global $Shopp;
		$this->settings = $Shopp->Settings->get('TestMode');

		add_action('shopp_process_order',array(&$this,'process'));
		return true;
	}
	
	function process () {
		if (!$this->myorder()) return false;

		if ($this->settings['error'] == "on")
			return new ShoppError(__("This is an example error message. Disable the 'always show an error' setting to stop displaying this error.","Shopp"),'test_mode_error',SHOPP_TRXN_ERR);

		$this->Order->transaction($this->txnid());

		return true;
	}
	
	function settings () {
		$this->ui->checkbox(0,array(
			'name' => 'error',
			'label' => 'Always show an error',
			'checked' => $this->settings['error']
		));
	}

} // END class TestMode

?>