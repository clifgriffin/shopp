<?php
/**
 * Payson
 * @class Payson
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, 27 May, 2009
 * @package Shopp
 * @since 1.1
 * @subpackage Payson
 * 
 * $Id$
 **/
class Payson extends GatewayFramework implements GatewayModule {

	var $secure = false;

	var $testurl = 'https://www.payson.se/testagent/default.aspx';
	var $liveurl = 'https://www.payson.se/merchant/default.aspx';
	
	function actions () {
		add_action('shopp_init_checkout',array(&$this,'init'));
		add_action('shopp_init_confirmation',array(&$this,'confirmation'));

		add_action('shopp_process_checkout', array(&$this,'checkout'),9);
		add_action('shopp_remote_payment',array(&$this,'returned'));
		add_action('shopp_process_order',array(&$this,'process'));
	}
	
	function confirmation () {
		add_filter('shopp_confirm_url',array(&$this,'url'));
		add_filter('shopp_confirm_form',array(&$this,'form'));
	}
	
	function init () {
		add_filter('shopp_checkout_submit_button',array(&$this,'submit'),10,3);
	}
	
	/* Handle the checkout form */
	function checkout () {
		$this->Order->Billing->cardtype = "Payson";
		$this->Order->confirm = true;
	}
	
	function url ($url) {
		if ($this->settings['testmode'] == "on")
			return $this->testurl;
		else return $this->liveurl;
	}
	
	function submit ($tag=false,$options=array(),$attrs=array()) {
		return '<input type="image" name="process" src="'.SHOPP_PLUGINURI.'/gateways'.'/'.$this->module.'/betala_knapp.gif" id="checkout-button" '.inputattrs($options,$attrs).' />';
	}
	
	/**
	 * form()
	 * Builds a hidden form to submit to Payson when confirming the order for processing */
	function form ($form) {
		global $Shopp;
		$Order = $this->Order;
		
		$_ = array();
		
		$_['Agentid']				= $this->settings['agentid'];
		$_['SellerEmail']			= $this->settings['email'];
		$_['GuaranteeOffered']		= $this->settings['guarantee'];
		$_['PaymentMethod']			= $this->settings['payment'];
		$_['Description']			= $this->settings['description'];
		
		$_['BuyerEmail']			= $Order->Customer->email;
		$_['BuyerFirstName']		= $Order->Customer->firstname;
		$_['BuyerLastname']			= $Order->Customer->lastname;
		
		$_['Cost']					= number_format($Order->Cart->Totals->total-$Order->Cart->Totals->shipping,$this->precision,",","");
		$_['ExtraCost']				= number_format($Order->Cart->Totals->shipping,$this->precision,",","");
		
		$_['RefNr']					= $Shopp->Shopping->session;
		
		$_['OkUrl']					= shoppurl(array('rmtpay'=>'true'),'checkout');
		$_['CancelUrl']				= shoppurl(false,'cart');
		
		$checkfields = array(
			$_['SellerEmail'],
			$_['Cost'],
			$_['ExtraCost'],
			$_['OkUrl'],
			$_['GuaranteeOffered'].$this->settings['key']
		);
		$_['MD5']					= md5(join(':',$checkfields));
	
		return $form.$this->format($_);
	}
		
	function process () {
		global $Shopp;
		$Shopp->Order->transaction($_GET['Paysonref'],'CHARGED',$_GET['Fee']);
	}
	
	function returned () {
		if (!isset($_REQUEST['Paysonref'])) return false;

		global $Shopp;
		// Validate the order notification
		$returned = array('Paysonref','OkURL','RefNr','MD5');
		foreach($returned as $key) {
			if (!isset($_GET[$key]) || empty($_GET[$key])) {
				new ShoppError(__('An unverifiable order was received from Payson. Possible fraudulent order attempt!','Shopp'),'paypal_trxn_verification',SHOPP_TRXN_ERR);
				return false;
			}
		}

		// Check for unique transaction id
		$Purchase = new Purchase($_GET['Paysonref'],'txnid');
		if (!empty($Purchase->id)) {
			$Shopp->Purchase = $Purchase;
			$Shopp->Order->purchase = $Purchase->id;
			shopp_redirect(shoppurl(false,'thanks',false));
			return false;
		}
		
		if ($Shopp->Shopping->session != $_GET['RefNr']) {
			$Shopp->resession($_GET['RefNr']);
			$Shopp->Order = ShoppingObject::__new('Order',$Shopp->Order);
			$Order = &$Shopp->Order;
		}
		
		$checkfields = array(
			$_GET['OkURL'],
			$_GET['Paysonref'],
			$this->settings['key']
		);
		$checksum = md5(join('',$checkfields));
		
		if ($Shopp->Shopping->session != $_GET['RefNr'] || $checksum != $_GET['MD5']) {
			new ShoppError(__('An order was received from Payson that could not be validated against existing pre-order data.  Possible order spoof attempt!','Shopp'),'payson_trxn_validation',SHOPP_TRXN_ERR);
			return false;
		}
				
		// Run order processing
		do_action('shopp_process_order'); 
	}
		
	function send ($message) {
		$url = $this->liveurl;
		if ($this->settings['testmode'] == "on") $url = $this->testurl;
		return parent::send($message,$url);
	}
	
	function settings () {
		$this->ui->text(0,array(
			'name' => 'agentid',
			'value' => $this->settings['agentid'],
			'size' => 7,
			'label' => __('Enter your Payson Agent ID.','Shopp')
		));

		$this->ui->text(0,array(
			'name' => 'email',
			'value' => $this->settings['email'],
			'size' => 30,
			'label' => __('Enter your Payson seller email address.','Shopp')
		));

		$this->ui->text(0,array(
			'name' => 'key',
			'value' => $this->settings['key'],
			'size' => 40,
			'label' => __('Enter your Payson secret key.','Shopp')
		));
		
		$this->ui->text(1,array(
			'name' => 'description',
			'value' => $this->settings['description'],
			'size' => 40,
			'label' => __('Enter a name or description for your store.','Shopp')
		));
		
		$this->ui->menu(1,array(
			'name' => 'payment',
			'selected' => $this->settings['payment'],
			'label' => __('Choose the payment methods accepted.','Shopp')
		),array(
			__('Credit Cards, Internet Banks &amp; Payson','Shopp'),
			__('Credit Cards Only','Shopp'),
			__('Internet Banks Only','Shopp'),
			__('Payson Account Funds Only','Shopp'),
			__('Internet Banks &amp; Payson Account Funds','Shopp')
		));
		
		$this->ui->checkbox(1,array(
			'name' => 'guarantee',
			'label' => __('Offer Payson Guarantee payments.','Shopp'),
			'checked' => $this->settings['guarantee']
		));
		

		$this->ui->checkbox(1,array(
			'name' => 'testmode',
			'label' => __('Enable test mode','Shopp'),
			'checked' => $this->settings['testmode']
		));
		
	}

} // END class Payson

?>