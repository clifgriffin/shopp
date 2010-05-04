<?php
/**
 * HSBC ePayments
 * Barclaycard ePDQ URL: https://secure2.epdq.co.uk:11500/
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, 30 March, 2008
 * @package shopp
 * @since 1.1
 * @subpackage HSBCepayments
 * 
 * $Id$
 **/

require_once(SHOPP_PATH."/core/model/XML.php");

class HSBCepayments extends GatewayFramework implements GatewayModule {
	
	var $secure = true;
	var $cards = array("visa", "mc", "amex", "maes", "solo");
	var $status = array("A" => "CHARGED","F" => "PENDING");
	
	// URLs
	var $liveurl = "https://www.secure-epayments.apixml.hsbc.com/";
	var $testurl = "https://www.uat.apixml.netq.hsbc.com/";
	
	function __construct () {
		parent::__construct();
		$this->setup('username','password','clientid','testmode');
	}
	
	function actions () {
		add_action('shopp_process_order',array(&$this,'process'));
	}
	
	function process () {
		global $Shopp;
		
		$message = $this->build();
		$Response = $this->send($message);
		$status = $Response->getElementContent('TransactionStatus');
		
		if ($status == "E" || $status = "D") {
			$message = $Response->getElementContent('CcReturnMsg');
			$code = $Response->getElementContent('CcErrCode');
			if (empty($message)) {
				new ShoppError(__("A configuration error occurred while processing this transaction. Please contact the site administrator.","Shopp"),'hsbc_transaction_error',SHOPP_TRXN_ERR);
				return;
			}
			new ShoppError($message,'hsbc_transaction_error',SHOPP_TRXN_ERR,
				array('code'=>$code));
				return;
		}
		
		$transaction = $this->Response->getElement('Transaction');
		$txnid = $transaction['CHILDREN']['Id']['CONTENT'];
		$txnstatus = $this->status[$status];
		$Shopp->Order->transaction($txnid,$txnstatus);

	}
		
	function build () {
		$Order = $this->Order;
		
		$type = "PreAuth";
		$mode = "P";  // Production Mode
		// Test Modes: N - Rejected test, Y - Accepted test, R - Random test, FN - Rejected, FY - Accepted
		if ($this->settings['testmode'] == "on") $mode = "Y";

		$_ = array('<?xml version="1.0" encoding="utf-8"?>'."\n");
		$_[] = '<EngineDocList>';
			$_[] = '<DocVersion DataType="String">1.0</DocVersion>';
			$_[] = '<EngineDoc>';
				$_[] = '<ContentType DataType="String">OrderFormDoc</ContentType>';
			
				$_[] = '<User>';
					$_[] = '<Name DataType="String">'.$this->settings['username'].'</Name>';
					$_[] = '<Password DataType="String">'.$this->settings['password'].'</Password>';
					$_[] = '<ClientId DataType="S32">'.$this->settings['clientid'].'</ClientId>';
				$_[] = '</User>';
			
				$_[] = '<Instructions>';
					$_[] = '<Pipeline DataType="String">Payment</Pipeline>';  // Use PaymentNoFraud to turn off HSBC Fraud Prevention
				$_[] = '</Instructions>';
			
				$_[] = '<OrderFormDoc>';
					$_[] = '<Mode DataType="String">'.$mode.'</Mode>';

					$_[] = '<Consumer>';
						$_[] = '<PaymentMech>';
							$_[] = '<Type DataType="String">CreditCard</Type>';
							$_[] = '<CreditCard>';
								$_[] = '<Number DataType="String">'.$Order->Billing->card.'</Number>';
								$_[] = '<Expires DataType="ExpirationDate">'.date("m/y",$Order->Billing->cardexpires).'</Expires>';
								$_[] = '<Cvv2Val DataType="String">'.$Order->Billing->cvv.'</Cvv2Val>';
								$_[] = '<Cvv2Indicator DataType="String">1</Cvv2Indicator>'; // Indicate CVV2 present
								
							$_[] = '</CreditCard>';
						$_[] = '</PaymentMech>';
						$_[] = '<BillTo>';
							$_[] = '<Location>';
								$_[] = '<Address>';
									$_[] = '<Street1 DataType="String">'.$Order->Billing->address.'</Street1>';
									$_[] = '<PostalCode DataType="String">'.$Order->Billing->postcode.'</PostalCode>';
								$_[] = '</Address>';
							$_[] = '</Location>';
						$_[] = '</BillTo>';
					$_[] = '</Consumer>';

					$_[] = '<Transaction>';
						$_[] = '<Type DataType="String">'.$type.'</Type>';

						$_[] = '<CurrentTotals>';
							$_[] = '<Totals>';
								$_[] = '<Total DataType="Money" Currency="826">'.($Order->Cart->Totals->total*100).'</Total>';
							$_[] = '</Totals>';						
						$_[] = '</CurrentTotals>';
					
					$_[] = '</Transaction>';

				$_[] = '</OrderFormDoc>';
			$_[] = '</EngineDoc>';
		$_[] = '</EngineDocList>';
			
		return join("\n",$_);
	}
	
	function url () {
		if ($this->settings['testmode'] == "on") return $this->testurl;
		return $this->liveurl;
	}
	
	function send ($message) {
		$url = $this->url();
		$response = parent::send($message,$url);
		return new XMLdata($reponse);
	}
		
	function settings () {
		
		$this->ui->cardmenu(0,array(
			'name' => 'cards',
			'selected' => $this->settings['cards']
		),$this->cards);

		$this->ui->text(1,array(
			'name' => 'username',
			'size' => 16,
			'value' => $this->settings['username'],
			'label' => __('Enter your HSBC ePayments username.','Shopp')
		));

		$this->ui->password(1,array(
			'name' => 'password',
			'size' => 24,
			'value' => $this->settings['password'],
			'label' => __('Enter your HSBC ePayments password.','Shopp')
		));

		$this->ui->text(1,array(
			'name' => 'clientid',
			'size' => 7,
			'value' => $this->settings['clientid'],
			'label' => __('HSBC ePayments Client ID.','Shopp')
		));

		$this->ui->checkbox(1,array(
			'name' => 'testmode',
			'checked' => $this->settings['testmode'],
			'label' => __('Enable test mode','Shopp')
		));
	}
	
} // END class HSBCepayments

?>