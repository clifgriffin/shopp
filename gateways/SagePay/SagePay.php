<?php
/**
 * SagePay
 * 
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, March  2, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.1
 * @subpackage SagePay
 **/

class SagePay extends GatewayFramework implements GatewayModule {
	
	var $secure = true;
	
	var $cards = array('visa','mc','delta','maestro','amex','dc','jcb','lasr');
	
	var $simurl = 'https://test.sagepay.com/Simulator/VSPDirectGateway.asp';
	var $testurl = 'https://test.sagepay.com/gateway/service/vspdirect-register.vsp';
	var $liveurl = 'https://live.sagepay.com/gateway/service/vspdirect-register.vsp';

	function __construct () {
		parent::__construct();
		
		$Settings = ShoppSettings();
		$this->settings['base_operations'] = $Settings->get('base_operations');
		$this->settings['currency_code'] = $this->currencies = "GBP"; // Use GBP by default
		// if (!empty($this->settings['base_operations']['currency']['code']))
		// 	$this->settings['currency_code'] = $this->settings['base_operations']['currency']['code'];
		
	}
	
	function actions () {
		add_action('shopp_process_order',array(&$this,'process'));
	}
	
	function process () {
		$transaction = $this->build();
		$Response = $this->send($transaction);
		if (trim($Response->Status) == "OK") {
			$this->Order->transaction($this->txnid($Response),'CHARGED');
			return;
		}
		
		$this->error($Response);
	}
	
	function txnid ($Response) {
		if (!empty($Response->VPSTxId)) return $Response->VPSTxId;
		return parent::txnid();
	}
	
	function error ($Response) {
		new ShoppError($Response->StatusDetail,'sagepay_error',SHOPP_TRXN_ERR);
	}
	
	function build () {
		$Order = $this->Order;
		$_ = array();

		// Options
		$_['VPSProtocol'] 			= "2.23";
		$_['TxType'] 				= "PAYMENT";
		
		// Auth
		$_['Vendor'] 				= $this->settings['vendor'];
		
		// Transaction
		$_['VendorTxCode'] 			= mktime();
		$_['Amount']				= $Order->Cart->Totals->total;
		$_['Currency']				= $this->settings['currency_code'];
		$_['Description']			= SHOPP_GATEWAY_USERAGENT;
		
		// Payment Details
		$_['CardHolder']			= $Order->Billing->cardholder;
		$_['CardNumber']			= $Order->Billing->card;
		$_['ExpiryDate']			= date("my",$Order->Billing->cardexpires);
		$_['CV2']					= $Order->Billing->cvv;
		$_['CardType']				= $Order->Billing->cardtype;
		
		// Billing
		$_['BillingSurname']		= $Order->Customer->lastname;
		$_['BillingFirstnames']		= $Order->Customer->firstname;
		$_['BillingAddress1']		= $Order->Billing->address;
		$_['BillingAddress2']		= $Order->Billing->xaddress;
		$_['BillingCity']			= $Order->Billing->city;
		$_['BillingState']			= $Order->Billing->state;
		$_['BillingPostCode']		= $Order->Billing->postcode;
		$_['BillingCountry']		= $Order->Billing->country;

		$_['BillingPhone']			= $Order->Customer->phone;

		// Shipping
		if (!empty($Order->Cart->shipped)) {
			$_['DeliverySurname']		= $Order->Customer->lastname;
			$_['DeliveryFirstnames']		= $Order->Customer->firstname;
			$_['DeliveryAddress1']		= $Order->Shipping->address;
			$_['DeliveryAddress2']		= $Order->Shipping->xaddress;
			$_['DeliveryCity']			= $Order->Shipping->city;
			$_['DeliveryState']			= $Order->Shipping->state;
			$_['DeliveryPostCode']		= $Order->Shipping->postcode;
			$_['DeliveryCountry']		= $Order->Shipping->country;
		}

		$_['CustomerEMail']				= $Order->Customer->email;
		$_['ClientIPAddress']			= $_SERVER['REMOTE_ADDR'];
		
		$Basket = array();
		$Basket[] = count($Order->Cart->contents)+2;
		foreach($Order->Cart->contents as $Item) {
			$Basket[] = htmlentities($Item->name.' '.((sizeof($Item->options) > 1)?' ('.$Item->optionlabel.')':''));
			$Basket[] = $Item->quantity;
			$Basket[] = number_format($Item->unitprice,$this->precision);
			$Basket[] = number_format($Item->tax,$this->precision);
			$Basket[] = number_format($Item->tax,$this->precision)+number_format($Item->unitprice,$this->precision);
			$Basket[] = number_format($Item->total,$this->precision);
		}
		array_push($Basket,__('Shipping','Shopp'),'','','','',$Order->Cart->Totals->shipping);
		array_push($Basket,__('Discount','Shopp'),'','','','',$Order->Cart->Totals->discount*-1);
		$_['Basket'] = implode(':',$Basket);

		return $this->encode($_);
	}
	
	function send ($data) {
		$url = ($this->settings['testmode'] == "on")?$this->testurl:$this->liveurl;
		$url = $this->simurl;
		return $this->response(parent::send($data,$url));
	}
	
	function response ($buffer) {
		$data = explode("\n",$buffer);
		$_ = new stdClass();
		foreach ($data as $pair) {
			list($key,$value) = explode("=",$pair);
			if (!empty($key)) $_->{$key} = $value;
		}
		return $_;
	}
	
	function settings () {
		$this->ui->cardmenu(0,array(
			'name' => 'cards',
			'selected' => $this->settings['cards']
		),$this->cards);

		$this->ui->text(1,array(
			'name' => 'vendor',
			'size' => 16,
			'value' => $this->settings['vendor'],
			'label' => __('Enter your Sage Pay vendor name.','Shopp')
		));

		$this->ui->checkbox(1,array(
			'name' => 'testmode',
			'checked' => $this->settings['testmode'],
			'label' => __('Enable test mode','Shopp')
		));
		
	}
	
} // END class SagePay

?>