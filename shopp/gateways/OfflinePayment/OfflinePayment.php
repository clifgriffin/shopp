<?php
/**
 * Offline Payment
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, 9 April, 2008
 * @package Shopp
 * @since 1.1
 * @subpackage OfflinePayment
 * 
 * $Id$
 **/

class OfflinePayment extends GatewayFramework implements GatewayModule {

	var $secure = false;	// SSL not required
	var $multi = true;		// Support multiple methods
	var $methods = array(); // List of active OfflinePayment payment methods

	/**
	 * Setup the Offline Payment module
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void Description...
	 **/
	function __construct () {
		parent::__construct();
		$this->setup('instructions');
		
		// Scan and build a runtime index of active payment methods
		foreach ($this->settings['label'] as $i => $entry)
			$this->methods[$entry] = $this->settings['instructions'][$i];
		
		add_filter('shopp_tag_checkout_offline-instructions',array(&$this,'tag_instructions'),10,2);
		add_filter('shopp_payment_methods',array(&$this,'methods'));
	}
	
	function actions () {
		add_action('shopp_process_order',array(&$this,'process'));
		add_action('shopp_save_payment_settings',array(&$this,'reset'));
	}
	
	/**
	 * Process the order
	 * 
	 * Process the order but leave it in PENDING status.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function process () {
		$this->Order->transaction($this->txnid());
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
		
		$this->ui->textarea(0,array(
			'name' => 'instructions',
			'value' => stripslashes_deep($this->settings['instructions'])
		));

		$this->ui->p(1,array(
			'name' => 'help',
			'label' => __('Offline Payment Instructions','Shopp'),
			'content' => __('Use this area to provide your customers with instructions on how to make payments offline.','Shopp')
		));
		
	}
	
	function tag_instructions ($result,$options) {
		global $Shopp;
		$module = $method = false;
		
		add_filter('shopp_offline_payment_instructions', 'stripslashes');
		add_filter('shopp_offline_payment_instructions', 'wptexturize');
		add_filter('shopp_offline_payment_instructions', 'convert_chars');
		add_filter('shopp_offline_payment_instructions', 'wpautop');
		
		if (!empty($Shopp->Order->paymethod)) list($module,$method) = explode(":",$Shopp->Order->paymethod);
		else $module = $Shopp->Order->processor; // Use the current processor for single payment method

		if ($module != $this->module) return;

		if (!$method) $method = current($this->settings['label']); // Only one payment method anyways
		$index = 0;
		foreach ($this->settings['label'] as $index => $label) {
			if ($method == $label) 
				return apply_filters('shopp_offline_payment_instructions',
									$this->settings['instructions'][$index]);
		}
		return false;
	}
	
	function reset () {
		$Settings =& ShoppSettings();
		if (!in_array($this->module,explode(',',$_POST['settings']['active_gateways']))) 
			$Settings->save('OfflinePayment',false);
		
	}
	
	function methods ($methods) {
		return $methods+(count($this->methods)-1);
	}

} // END class TestMode

?>