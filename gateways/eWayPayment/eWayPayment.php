<?php
/**
 * eWayPayment
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, 7 January, 2009
 * @package Shopp
 * @since 1.1
 * @subpackage eWayPayment
 * 
 * $Id$
 **/

require_once(SHOPP_PATH."/core/model/XML.php");

class eWayPayment extends GatewayFramework implements GatewayModule {
	
	var $secure = true;

	var $cards = array("visa","mc","amex","dc","jcb");

	var $testid = "87654321";
	var $testurl = "https://www.eway.com.au/gateway_cvn/xmltest/testpage.asp";
	var $liveurl = "https://www.eway.com.au/gateway_cvn/xmlpayment.asp";
	
	function __construct () {
		parent::__construct();
		$this->setup('customerid','testmode');
	}

	function actions () {
		add_action('shopp_process_order',array(&$this,'process'));
	}
	
	function process () {

		$XML = $this->send($this->build());
		$txnid = $this->txnid($XML);

		if ($XML->getElementContent('ewayTrxnStatus') == "True") {
			$this->Order->transaction($txnid,'CHARGED');
			return;
		}
						
		new ShoppError($this->error($XML),'ewaypayments_error',SHOPP_TRXN_ERR);
		
	}
	
	function txnid ($XML) {
		return $XML->getElementContent('ewayTrxnNumber');
	}
	
	function error ($XML) {
		return $XML->getElementContent('ewayTrxnError');
	}
	
		
	function build () {
		$Order = $this->Order;
		$eWayCustomer = ($this->settings['testmode'] == "on")?$this->testid:$this->settings['customerid'];
		
		$InvoiceDescription = array();
		foreach($Order->Cart->contents as $Item)
			$InvoiceDescription[] = $Item->quantity.' x '.$Item->name.' '.((sizeof($Item->options) > 1)?' ('.$Item->optionlabel.')':'');
		
		$_ = array();
		$_[] = '<ewaygateway>';
			$_[] = '<ewayCustomerID>'.$eWayCustomer.'</ewayCustomerID>';
			$_[] = '<ewayTotalAmount>'.str_replace(".","",number_format($Order->Cart->Totals->total,$this->precision)).'</ewayTotalAmount>';
			$_[] = '<ewayCustomerFirstName>'.htmlentities($Order->Customer->firstname).'</ewayCustomerFirstName>';
			$_[] = '<ewayCustomerLastName>'.htmlentities($Order->Customer->lastname).'</ewayCustomerLastName>';
			$_[] = '<ewayCustomerEmail>'.htmlentities($Order->Customer->email).'</ewayCustomerEmail>';
			$_[] = '<ewayCustomerAddress>'.htmlentities($Order->Billing->address).'</ewayCustomerAddress>';
			$_[] = '<ewayCustomerPostcode>'.htmlentities($Order->Billing->postcode).'</ewayCustomerPostcode>';
			$_[] = '<ewayCustomerInvoiceDescription>'.htmlentities(join(", ", $InvoiceDescription)).'</ewayCustomerInvoiceDescription>';
			$_[] = '<ewayCustomerInvoiceRef>'.htmlentities($this->session).'</ewayCustomerInvoiceRef>';
			$_[] = '<ewayCardHoldersName>'.htmlentities($Order->Billing->cardholder).'</ewayCardHoldersName>';
			$_[] = '<ewayCardNumber>'.htmlentities($Order->Billing->card).'</ewayCardNumber>';
			$_[] = '<ewayCardExpiryMonth>'.date("m",$Order->Billing->cardexpires).'</ewayCardExpiryMonth>';
			$_[] = '<ewayCardExpiryYear>'.date("y",$Order->Billing->cardexpires).'</ewayCardExpiryYear>';
			$_[] = '<ewayTrxnNumber>'.htmlentities($this->session).'</ewayTrxnNumber>';
			$_[] = '<ewayCVN>'.$Order->Billing->cvv.'</ewayCVN>';
			$_[] = '<ewayOption1></ewayOption1>';
			$_[] = '<ewayOption2></ewayOption2>';
			$_[] = '<ewayOption3></ewayOption3>';
		$_[] = '</ewaygateway>';

		return join("",apply_filters('shopp_eway_transaction',$_));
	}
	
	function send ($data) {
		if ($this->settings['testmode'] == "on") $url = $this->testurl;
		else $url = $this->liveurl;
		return new XMLdata(parent::send($data,$url));
	}
	
	
	function settings () {
		$this->ui->cardmenu(0,array(
			'name' => 'cards',
			'selected' => $this->settings['cards']
		),$this->cards);

		$this->ui->text(1,array(
			'name' => 'customerid',
			'value' => $this->settings['customerid'],
			'size' => '16',
			'label' => __('Enter your eWay Customer ID.','Shopp')
		));
		
		$this->ui->checkbox(1,array(
			'name' => 'testmode',
			'checked' => $this->settings['testmode'],
			'label' => __('Enable test mode','Shopp')
		));
	}

} // END class eWayPayments

?>