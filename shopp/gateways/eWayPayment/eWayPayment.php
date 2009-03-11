<?php
/**
 * eWayPayment
 * @class eWayPayment
 *
 * @author Jonathan Davis
 * @version 1.0.2
 * @copyright Ingenesis Limited, 7 January, 2009
 * @package Shopp
 **/

require_once(SHOPP_PATH."/core/model/XMLdata.php");

class eWayPayment {
	var $transaction = array();
	var $settings = array();
	var $Response = false;
	var $cards = array("Visa","MasterCard","American Express","Diners","JCB");
	var $testid = "87654321";
	var $testurl = "https://www.eway.com.au/gateway_cvn/xmltest/testpage.asp";
	var $liveurl = "https://www.eway.com.au/gateway_cvn/xmlpayment.asp";
	
	function eWayPayment (&$Order="") {
		global $Shopp;
		$this->settings = $Shopp->Settings->get('eWayPayment');
		$this->settings['merchant_email'] = $Shopp->Settings->get('merchant_email');
		if (!isset($this->settings['cards'])) $this->settings['cards'] = $this->cards;
		
		// Use the test Customer ID in test mode (the only ID that works in test mode)
		if ($this->settings['testmode'] == "on") $this->settings['customerid'] = $this->testid;
		
		if (!empty($Order)) $this->build($Order);
		return true;
	}
	
	function process () {
		$this->Response = $this->send();
		$status = $this->Response->getElementContent('ewayTrxnStatus');
		if ($status == "True") return true;
		else return false;
	}
	
	function transactionid () {
		$transaction = $this->Response->getElementContent('ewayTrxnNumber');
		if (!empty($transaction)) return $transaction;
		return false;
	}
	
	function error () {
		if (!empty($this->Response)) {
			$message = $this->Response->getElementContent('ewayTrxnError');
			if (empty($message)) return new ShoppError(__("A configuration error occurred while processing this transaction.  Please contact the site administrator.","Shopp"),'eway_trxn_error',SHOPP_TRXN_ERR);
			return new ShoppError($message,'eway_trxn_error',SHOPP_TRXN_ERR);
		}
	}
	
	function build ($Order) {
		
		$InvoiceDescription = array();
		foreach($Order->Items as $Item)
			$InvoiceDescription[] = $Item->quantity.' x '.$Item->name.' '.((sizeof($Item->options) > 1)?' ('.$Item->optionlabel.')':'');
		
		$_ = array();
		$_[] = '<ewaygateway>';
			$_[] = '<ewayCustomerID>'.$this->settings['customerid'].'</ewayCustomerID>';
			$_[] = '<ewayTotalAmount>'.str_replace(".","",number_format($Order->Totals->total,2)).'</ewayTotalAmount>';
			$_[] = '<ewayCustomerFirstName>'.htmlentities($Order->Customer->firstname).'</ewayCustomerFirstName>';
			$_[] = '<ewayCustomerLastName>'.htmlentities($Order->Customer->lastname).'</ewayCustomerLastName>';
			$_[] = '<ewayCustomerEmail>'.htmlentities($Order->Customer->email).'</ewayCustomerEmail>';
			$_[] = '<ewayCustomerAddress>'.htmlentities($Order->Billing->address).'</ewayCustomerAddress>';
			$_[] = '<ewayCustomerPostcode>'.htmlentities($Order->Billing->postcode).'</ewayCustomerPostcode>';
			$_[] = '<ewayCustomerInvoiceDescription>'.htmlentities(join(", ", $InvoiceDescription)).'</ewayCustomerInvoiceDescription>';
			$_[] = '<ewayCustomerInvoiceRef>'.htmlentities($Order->Cart).'</ewayCustomerInvoiceRef>';
			$_[] = '<ewayCardHoldersName>'.htmlentities($Order->Billing->cardholder).'</ewayCardHoldersName>';
			$_[] = '<ewayCardNumber>'.htmlentities($Order->Billing->card).'</ewayCardNumber>';
			$_[] = '<ewayCardExpiryMonth>'.date("m",$Order->Billing->cardexpires).'</ewayCardExpiryMonth>';
			$_[] = '<ewayCardExpiryYear>'.date("y",$Order->Billing->cardexpires).'</ewayCardExpiryYear>';
			$_[] = '<ewayTrxnNumber>'.htmlentities($Order->Cart).'</ewayTrxnNumber>';
			$_[] = '<ewayCVN>'.$Order->Billing->cvv.'</ewayCVN>';
			$_[] = '<ewayOption1></ewayOption1>';
			$_[] = '<ewayOption2></ewayOption2>';
			$_[] = '<ewayOption3></ewayOption3>';
		$_[] = '</ewaygateway>';
		
		$this->transaction = join("",$_);
	}
	
	function send () {
		$connection = curl_init();
		if ($this->settings['testmode'] == "on")
			curl_setopt($connection,CURLOPT_URL,$this->testurl); // Test mode
		else curl_setopt($connection,CURLOPT_URL,$this->liveurl); // Live		
		curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0); 
		curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0); 
		curl_setopt($connection, CURLOPT_NOPROGRESS, 1); 
		curl_setopt($connection, CURLOPT_VERBOSE, 1); 
		curl_setopt($connection, CURLOPT_FOLLOWLOCATION,0); 
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $this->transaction); 
		curl_setopt($connection, CURLOPT_TIMEOUT, 30); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_REFERER, "https://".$_SERVER['SERVER_NAME']); 
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);
		if ($error = curl_error($connection)) 
			new ShoppError($error,'eway_connection',SHOPP_COMM_ERR);
		curl_close($connection);

		$Response = new XMLdata($buffer);
		return $Response;
	}
	
	function response () {
		// Not implemented
	}
	
	function settings () {
		?>
		<tr id="ewaypayment-settings" class="addon">
			<th scope="row" valign="top">eWay Payment</th>
			<td>
				<div><input type="text" name="settings[eWayPayment][customerid]" id="ewaypayment_customerid" value="<?php echo $this->settings['customerid']; ?>" size="16" /><br /><label for="ewaypayment_customerid"><?php _e('Enter your eWay Customer ID.'); ?></label></div>
				<div><input type="hidden" name="settings[eWayPayment][testmode]" value="off" /><input type="checkbox" name="settings[eWayPayment][testmode]" id="ewaypayment_testmode" value="on"<?php echo ($this->settings['testmode'] == "on")?' checked="checked"':''; ?> /><label for="ewaypayment_testmode"> <?php _e('Enable test mode'); ?></label></div>
				<div><strong>Accept these cards:</strong>
				<ul class="cards"><?php foreach($this->cards as $id => $card): 
					$checked = "";
					if (in_array($card,$this->settings['cards'])) $checked = ' checked="checked"';
				?>
					<li><input type="checkbox" name="settings[eWayPayment][cards][]" id="ewaypayment_cards_<?php echo $id; ?>" value="<?php echo $card; ?>" <?php echo $checked; ?> /><label for="ewaypayment_cards_<?php echo $id; ?>"> <?php echo $card; ?></label></li>
				<?php endforeach; ?></ul></div>
				
				<input type="hidden" name="module[<?php echo basename(__FILE__); ?>]" value="eWayPayment" />
				
			</td>
		</tr>
		<?php
	}
	
	function registerSettings () {
		?>
		gatewayHandlers.register('<?php echo addslashes(__FILE__); ?>','ewaypayment-settings');
		<?php
	}

} // end eWayPayment class

?>