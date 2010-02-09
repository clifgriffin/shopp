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
		
		add_filter('shopp_tag_checkout_offline-instructions',array(&$this,'tag_instructions'),10,2);

	}
	
	function actions () {
		add_action('shopp_process_order',array(&$this,'process'));
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
			'value' => $this->settings['instructions']
		));

		$this->ui->p(1,array(
			'name' => 'help',
			'label' => __('Offline Payment Instructions','Shopp'),
			'content' => __('Use this area to provide your customers with instructions on how to make payments offline.','Shopp')
		));
		
	}
	
	function tag_instructions ($result,$options) {
		global $Shopp;
		list($module,$method) = explode(":",$Shopp->Order->paymethod);
		if ($module != $this->module) return;

		$index = 0;
		foreach ($this->settings['label'] as $index => $label) {
			if ($method == $label) return $this->settings['instructions'][$index];
		}
		return "";
	}

} // END class TestMode

?>