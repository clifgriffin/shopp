<?php
/**
 * Test Mode
 *
 * @author Jonathan Davis
 * @version 1.2
 * @copyright Ingenesis Limited, 5 July, 2011
 * @package Shopp
 * @since 1.1
 * @subpackage TestMode
 *
 * $Id$
 **/

class TestMode extends GatewayFramework {

	var $secure = false;							// SSL not required

	var $refunds = true;
	var $captures = true;
	var $cards = array("visa","mc","disc","amex");	// Support cards

	/**
	 * Setup the TestMode gateway
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void Description...
	 **/
	function __construct () {
		parent::__construct();
		$this->setup('cards','error');

		// Autoset useable payment cards
		$this->settings['cards'] = array();
		foreach ($this->cards as $card)	$this->settings['cards'][] = $card->symbol;

		add_action('shopp_testmode_sale',array(&$this,'sale'));
		add_action('shopp_testmode_auth',array(&$this,'auth'));
		add_action('shopp_testmode_capture',array(&$this,'capture'));
		add_action('shopp_testmode_refund',array(&$this,'refund'));
		add_action('shopp_testmode_void',array(&$this,'void'));
	}

	/**
	 * Process the order
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function process () {
		// If the error option is checked, always generate an error
		if (str_true($this->settings['error']))
			return new ShoppError(__("This is an example error message. Disable the 'always show an error' setting to stop displaying this error.","Shopp"),'test_mode_error',SHOPP_TRXN_ERR);

		// Set the transaction data for the order
		$this->Order->transaction($this->txnid(),'CHARGED');
		return true;
	}

	function sale (OrderEventMessage $Event) {
		$this->handler('authed',$Event);
		$this->handler('captured',$Event);
	}

	function auth (OrderEventMessage $Event) {
		$this->handler('authed',$Event);
	}

	function capture (OrderEventMessage $Event) {
		$this->handler('captured',$Event);
	}

	function refund (OrderEventMessage $Event) {
		$this->handler('refunded',$Event);
	}

	function void (OrderEventMessage $Event) {
		$this->handler('voided',$Event);
	}

	function handler ($type,$Event) {
		if(!isset($Event->txnid)) $Event->txnid = time();
		if (str_true($this->settings['error'])) {
			new ShoppError(__("This is an example error message. Disable the 'always show an error' setting to stop displaying this error.",'Shopp'),'testmode_error',SHOPP_TRXN_ERR);
			return shopp_add_order_event($Event->order,$Event->type.'-fail',array(
				'amount' => $Event->amount,
				'error' => 0,
				'message' => __("This is an example error message. Disable the 'always show an error' setting to stop displaying this error.",'Shopp'),
				'gateway' => $this->module
			));
		}

		shopp_add_order_event($Event->order,$type,array(
			'txnid' => $Event->txnid,
			'txnorigin' => $Event->txnid,
			'fees' => 0,
			'paymethod' => '',
			'payid' => '',
			'paytype' => '',
			'payid' => '1111',
			'amount' => $Event->amount,
			'gateway' => $this->module
		));
	}

	/**
	 * Render the settings for this gateway
	 *
	 * Uses ModuleSettingsUI to generate a JavaScript/jQuery based settings
	 * panel.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function settings () {
		$this->ui->checkbox(0,array(
			'name' => 'error',
			'label' => 'Always show an error',
			'checked' => $this->settings['error']
		));
	}

} // END class TestMode

?>