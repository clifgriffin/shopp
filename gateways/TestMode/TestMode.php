<?php
/**
 * Test Mode
 *
 * @author Jonathan Davis
 * @version 1.1.5
 * @copyright Ingenesis Limited, 9 April, 2008
 * @package Shopp
 * @since 1.1
 * @subpackage TestMode
 *
 * $Id$
 **/

class TestMode extends GatewayFramework {

	var $secure = false;							// SSL not required
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
	}

	function actions () {
		add_action('shopp_process_order',array(&$this,'process'));
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
		if ($this->settings['error'] == "on")
			return new ShoppError(__("This is an example error message. Disable the 'always show an error' setting to stop displaying this error.","Shopp"),'test_mode_error',SHOPP_TRXN_ERR);

		// Set the transaction data for the order
		$this->Order->transaction($this->txnid(),'CHARGED');
		return true;
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