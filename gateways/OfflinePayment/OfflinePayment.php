<?php
/**
 * Offline Payment
 *
 * @author Jonathan Davis
 * @version 1.1.5
 * @copyright Ingenesis Limited, 9 April, 2008
 * @package Shopp
 * @since 1.1
 * @subpackage OfflinePayment
 *
 * $Id$
 **/

class OfflinePayment extends GatewayFramework implements GatewayModule {

	var $secure = false;	// SSL not required
	var $authonly = true;	// Auth only transactions
	var $multi = true;		// Support multiple methods
	var $captures = true;	// Supports Auth-only
	var $refunds = true;	// Supports refunds

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
		// $this->setup('instructions');

		// Reset the index count to shift setting indices so we don't break the JS environment
		if (isset($this->settings['label']) && is_array($this->settings['label']))
			$this->settings['label'] = array_merge(array(),$this->settings['label']);
		if (isset($this->settings['instructions']) && is_array($this->settings['instructions']))
		$this->settings['instructions'] = array_merge(array(),$this->settings['instructions']);

		// Scan and build a runtime index of active payment methods
		if (isset($this->settings['label']) && is_array($this->settings['label'])) {
			foreach ($this->settings['label'] as $i => $entry)
				if (isset($this->settings['instructions']) && isset($this->settings['instructions'][$i]))
					$this->methods[$entry] = $this->settings['instructions'][$i];
		}

		add_filter('shopp_themeapi_checkout_offlineinstructions',array(&$this,'tag_instructions'),10,2);

		add_action('shopp_offlinepayment_sale',array(&$this,'auth')); // Process sales as auth-only
		add_action('shopp_offlinepayment_auth',array(&$this,'auth'));
		add_action('shopp_offlinepayment_capture',array(&$this,'capture'));
		add_action('shopp_offlinepayment_refund',array(&$this,'refund'));
		add_action('shopp_offlinepayment_void',array(&$this,'void'));

	}

	function actions () { /* Not Implemented */ }

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
	function auth ($Event) {
		$Order = $this->Order;
		$OrderTotals = $Order->Cart->Totals;
		$Billing = $Order->Billing;
		$Paymethod = $Order->paymethod();

		shopp_add_order_event($Event->order,'authed',array(
			'txnid' => time(),
			'amount' => $OrderTotals->total,
			'fees' => 0,
			'gateway' => $Paymethod->processor,
			'paymethod' => $Paymethod->label,
			'paytype' => $Billing->cardtype,
			'payid' => $Billing->card
		));
	}

	function capture ($Event) {
		shopp_add_order_event($Event->order,'captured',array(
			'txnid' => time(),			// Transaction ID of the CAPTURE event
			'amount' => $Event->amount,	// Amount captured
			'fees' => 0,
			'gateway' => $this->module	// Gateway handler name (module name from @subpackage)
		));
	}

	function refund ($Event) {
		shopp_add_order_event($Event->order,'refunded',array(
			'txnid' => time(),			// Transaction ID for the REFUND event
			'amount' => $Event->amount,	// Amount refunded
			'gateway' => $this->module	// Gateway handler name (module name from @subpackage)
		));
	}

	function void ($Event) {
		shopp_add_order_event($Event->order,'voided',array(
			'txnorigin' => $Event->txnid,	// Original transaction ID (txnid of original Purchase record)
			'txnid' => time(),				// Transaction ID for the VOID event
			'gateway' => $this->module		// Gateway handler name (module name from @subpackage)
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
		add_filter('shopp_offline_payment_instructions', 'stripslashes');
		add_filter('shopp_offline_payment_instructions', 'wptexturize');
		add_filter('shopp_offline_payment_instructions', 'convert_chars');
		add_filter('shopp_offline_payment_instructions', 'wpautop');

		$paymethod = shopp('purchase','get-paymethod');
		$Order = ShoppOrder();
		if ( ! isset($Order->payoptions[ $paymethod ]) ) return;

		$method = $Order->payoptions[ $paymethod ]->setting;
		list($module,$id) = explode('-',$method);

		if ( ! isset($this->settings[$id]) ) return;

		$settings = $this->settings[$id];

		if(!empty($settings['instructions']))
			return apply_filters('shopp_offline_payment_instructions', $settings['instructions']);

		return false;
	}

	function methods ($methods) {
		return $methods+(count($this->methods)-1);
	}

} // END class OfflinePayment

?>