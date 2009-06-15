<?php
/**
 * HSBC ePayments
 * @class HSBCepayments
 *
 * @author Jonathan Davis
 * @version 1.0.3
 * @copyright Ingenesis Limited, 30 March, 2008
 * @package Shopp
 **/

require_once(SHOPP_PATH."/core/model/XMLdata.php");

class HSBCepayments {
	var $transaction = array();
	var $settings = array();
	var $Response = false;
	var $cards = array("Visa", "MasterCard", "Electron","UK Maestro","Solo");
	var $url = "https://www.secure-epayments.apixml.hsbc.com/";
	// var $url = "https://www.uat.apixml.netq.hsbc.com/";
	
	function HSBCepayments (&$Order="") {
		global $Shopp;
		$this->settings = $Shopp->Settings->get('HSBCepayments');
		if (!isset($this->settings['cards'])) $this->settings['cards'] = $this->cards;
		
		if (!empty($Order)) $this->build($Order);
		return true;
	}
	
	function process () {
		$this->Response = $this->send();
		$status = $this->Response->getElementContent('TransactionStatus');
		if ($status == "A") return true;
		else return false;
	}
	
	function transactionid () {
		$transaction = $this->Response->getElement('Transaction');
		if (!empty($transaction)) return $transaction['CHILDREN']['Id']['CONTENT'];
		return false;
	}
	
	function error () {
		if (!empty($this->Response)) {
			$message = $this->Response->getElementContent('CcReturnMsg');
			$code = $this->Response->getElementContent('CcErrCode');
			if (empty($message))
				return new ShoppError(__("A configuration error occurred while processing this transaction. Please contact the site administrator.","Shopp"),'hsbc_transaction_error',SHOPP_TRXN_ERR);			
			return new ShoppError($message,'hsbc_transaction_error',SHOPP_TRXN_ERR,
				array('code'=>$code));
		}
	}
	
	function build (&$Order) {

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
								$_[] = '<Total DataType="Money" Currency="826">'.($Order->Totals->total*100).'</Total>';
							$_[] = '</Totals>';						
						$_[] = '</CurrentTotals>';
					
					$_[] = '</Transaction>';

				$_[] = '</OrderFormDoc>';
			$_[] = '</EngineDoc>';
		$_[] = '</EngineDocList>';
			
		$this->transaction = join("\n",$_);
		
	}
	
	function send () {
		$connection = curl_init();
		curl_setopt($connection,CURLOPT_URL,$this->url);
		curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0); 
		curl_setopt($connection, CURLOPT_NOPROGRESS, 1); 
		curl_setopt($connection, CURLOPT_VERBOSE, 1); 
		curl_setopt($connection, CURLOPT_FOLLOWLOCATION,0); 
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $this->transaction); 
		curl_setopt($connection, CURLOPT_TIMEOUT, 60); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_REFERER, "https://".$_SERVER['SERVER_NAME']); 
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);
		if ($error = curl_error($connection)) 
			new ShoppError($error,'hsbc_connection',SHOPP_COMM_ERR);
		curl_close($connection);

		$Response = new XMLdata($buffer);
		return $Response;
	}
		
	function settings () {
		global $Shopp;
		
		?>
		<tr id="hsbcepayments-settings" class="addon">
			<th scope="row" valign="top">HSBC ePayments</th>
			<td>
				<div><input type="text" name="settings[HSBCepayments][username]" id="hsbcepayments_username" value="<?php echo $this->settings['username']; ?>" size="16" /><br /><label for="hsbcepayments_username"><?php _e('Enter your HSBC ePayments username.'); ?></label></div>
				<div><input type="password" name="settings[HSBCepayments][password]" id="hsbcepayments_password" value="<?php echo $this->settings['password']; ?>" size="24" /><br /><label for="hsbcepayments_password"><?php _e('Enter your HSBC ePayments password.'); ?></label></div>
				<div><input type="text" name="settings[HSBCepayments][clientid]" id="hsbcepayments_clientid" value="<?php echo $this->settings['clientid']; ?>" size="7" /><br /><label for="hsbcepayments_clientid"><?php _e('HSBC ePayments Client ID'); ?></label></div>
				<div><input type="hidden" name="settings[HSBCepayments][testmode]" value="off" /><input type="checkbox" name="settings[HSBCepayments][testmode]" id="hsbcepayments_testmode" value="on"<?php echo ($this->settings['testmode'] == "on")?' checked="checked"':''; ?> /><label for="hsbcepayments_testmode"> <?php _e('Enable test mode'); ?></label></div>
				<div><strong>Accept these cards:</strong>
				<ul class="cards"><?php foreach($this->cards as $id => $card): 
					$checked = "";
					if (in_array($card,$this->settings['cards'])) $checked = ' checked="checked"';
				?>
					<li><input type="checkbox" name="settings[HSBCepayments][cards][]" id="hsbcepayments_cards_<?php echo $id; ?>" value="<?php echo $card; ?>" <?php echo $checked; ?> /><label for="hsbcepayments_cards_<?php echo $id; ?>"> <?php echo $card; ?></label></li>
				<?php endforeach; ?></ul></div>
				
				<input type="hidden" name="module[<?php echo basename(__FILE__); ?>]" value="HSBCepayments" />
				
			</td>
		</tr>
		<?php
	}
	
	function registerSettings () {
		?>
		gatewayHandlers.register('<?php echo addslashes(__FILE__); ?>','hsbcepayments-settings');
		<?php
	}

} // end HSBCepayments class

?>