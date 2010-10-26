<?php
/**
 * SlimCD
 * 
 * Description
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, October 26, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.1
 * @subpackage SlimCD
 * 
 **/
class SlimCD extends GatewayFramework implements GatewayModule {
	
	// Settings
	var $secure = true;
	var $xml = true;
	var $cards = array("visa", "mc", "amex", "disc");

	// URLs
	var $url = 'https://trans.slimcd.com/wswebservices/transact.asmx/PostXML';

	function __construct () {
		parent::__construct();
		$this->setup('setting','clientid','siteid','priceid','password','key');
		add_filter('shopp_tag_checkout_slimcd-nopaycard',array($this,'nopaycard'));
		
	}
	
	/**
	 * Setup listeners for transaction events
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return void
	 **/
	function actions () {
		add_action('shopp_process_order',array(&$this,'process'));
	}
	
	function nopaycard () {
		$Order = $this->Order;
		$Order->Customer->load_info();
		return (
			isset($Order->Customer->info->named['gateid']) 
			&& !empty($Order->Customer->info->named['gateid']->value)
		);
	}
	
	/**
	 * Builds a transaction request 
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return string All of the form elements
	 **/
	function request () {
		global $Shopp;
		
		$Order = $this->Order;
		$Customer = $Order->Customer;
		$Billing = $Order->Billing;
		
		$_ = array();
		$_[] = '<request>';
			$_[] = '<transtype>SALE</transtype>';
			$_[] = '<client_transref>'.$Shopp->Shopping->session.'</client_transref>';
			$_[] = '<amount>'.number_format($Order->Cart->Totals->total,2).'</amount>';
			if ($this->nopaycard() && empty($Billing->card)) {
				$_[] = '<recurring>yes</recurring>';
				$_[] = '<gateid>'.$Customer->info->named['gateid']->value.'</gateid>';
			} else {
				$_[] = '<cardnumber>'.$Billing->card.'</cardnumber>';
				$_[] = '<expmonth>'.date("m",$Billing->cardexpires).'</expmonth>';
				$_[] = '<expyear>'.date("Y",$Billing->cardexpires).'</expyear>';
				$_[] = '<CVV2>'.$Billing->cvv.'</CVV2>';
				$_[] = '<first_name>'.$Customer->firstname.'</first_name>';
				$_[] = '<last_name>'.$Customer->lastname.'</last_name>';
				$_[] = '<address>'.$Billing->address.'</address>';
				$_[] = '<city>'.$Billing->city.'</city>';
				$_[] = '<state>'.$Billing->state.'</state>';
				$_[] = '<zip>'.$Billing->postcode.'</zip>';
				$_[] = '<country>'.$Billing->country.'</country>';
				if (!empty($Customer->phone))
					$_[] = '<phone>'.$Customer->phone.'</phone>';
			}
		$_[] = '</request>';

		$r = array(
			'ClientID' => $this->settings['clientid'],
			'SiteID' => $this->settings['siteid'],
			'PriceID' => $this->settings['priceid'],
			'Password' => $this->settings['password'],
			'Ver' => SHOPP_VERSION,
			'Product' => SHOPP_GATEWAY_USERAGENT,
			'Key' => $this->settings['key'],
			'XMLData' => join('',$_)
		);
		
		return $this->encode($r);
	}

	/**
	 * Process an order
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return void
	 **/
	function process () {
		global $Shopp;
		
		$request = $this->request();
		$Response = $this->send($request);

		if ($Response->content('responsecode') != "0") return $this->error($Response);
		
		$gateid = $Response->content('gateid');
		if (!empty($gateid)) {
			if (!is_array($Shopp->Order->Customer->info)) 
				$Shopp->Order->Customer->info = array();
			$Shopp->Order->Customer->info['gateid'] = $gateid;
		}
		
		$txnid = $Response->content('invoiceno');
		$txnstatus = 'CHARGED';
		
		$Shopp->Order->transaction($txnid,$txnstatus);
	}

	/**
	 * Handles error reporting
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void Description...
	 **/
	function error ($Response) {
		$auth = $Response->tag('authcode');
		
		switch (strtoupper((string)$auth)) {
			case "N": $error = new ShoppError(__('The transaction was declined.','Shopp'),'slimcd_auth_error',SHOPP_TRXN_ERR); break;
			case "D": $error = new ShoppError(__('The transaction was not allowed due to fraud scrubbing.','Shopp'),'slimcd_auth_error',SHOPP_TRXN_ERR); break;
			default: $error = new ShoppError(__('An error occurred while processing the transaction.','Shopp'),'slimcd_auth_error',SHOPP_TRXN_ERR,array('details' => $Response->content('description'))); break;
		}
		return $error;
	}
		
	/**
	 * Send a message to the payment service server
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return string Response from the server
	 **/
	function send ($data) {
		new ShoppError($data,'slimcd_request',SHOPP_DEBUG_ERR);
		$result = parent::send($data,$this->url);
		return new xmlQuery($result);
	}
	
	/**
	 * Build the payment settings for this gateway module
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return void
	 **/
	function settings () {
		$this->ui->cardmenu(0,array(
			'name' => 'cards',
			'selected' => $this->settings['cards']
		),$this->cards);
		
		$this->ui->text(1,array(
			'name' => 'clientid',
			'value' => $this->settings['clientid'],
			'size' => '5',
			'label' => __('Enter your Slim CD Client ID.','Shopp')
		));

		$this->ui->text(1,array(
			'name' => 'siteid',
			'value' => $this->settings['siteid'],
			'size' => '10',
			'label' => __('Enter your Slim CD Site ID.','Shopp')
		));

		$this->ui->text(1,array(
			'name' => 'priceid',
			'value' => $this->settings['priceid'],
			'size' => '3',
			'label' => __('Enter your Slim CD Price ID.','Shopp')
		));

		$this->ui->password(2,array(
			'name' => 'password',
			'value' => $this->settings['password'],
			'size' => '20',
			'label' => __('Enter your Slim CD account password.','Shopp')
		));

		$this->ui->text(2,array(
			'name' => 'key',
			'value' => $this->settings['key'],
			'size' => '24',
			'label' => __('Enter your Slim CD Key.','Shopp')
		));

	}

} // END class SlimCD

?>