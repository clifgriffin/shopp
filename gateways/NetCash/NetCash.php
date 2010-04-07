<?php
/**
 * NetCash
 * @class NetCash
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, 27 May, 2009
 * @package Shopp
 * @since 1.1
 * @subpackage NetCash
 * 
 * $Id$
 **/

class NetCash extends GatewayFramework implements GatewayModule {          

	var $url = 'https://gateway.netcash.co.za/vvonline/ccnetcash.asp';

	var $secure = false;


	function __construct () {
		parent::__construct();
		
		global $Shopp;
		$this->settings = $Shopp->Settings->get('NetCash');
		$this->settings['merchant_email'] = $Shopp->Settings->get('merchant_email');
		$this->settings['base_operations'] = $Shopp->Settings->get('base_operations');

		$this->ipn = add_query_arg('shopp_xorder','NetCash',$Shopp->link('catalog',true));

		add_action('shopp_txn_update',array(&$this,'updates'));

	}
	
	function actions () {
		add_action('shopp_process_checkout', array(&$this,'checkout'),9);
		
		add_action('shopp_init_confirmation',array(&$this,'confirmation'));
		add_action('shopp_process_order',array(&$this,'process'));
		
		add_action('shopp_save_payment_settings',array(&$this,'apiurl'));
		
	}
	
	function confirmation () {
		add_filter('shopp_confirm_url',array(&$this,'url'));
		add_filter('shopp_confirm_form',array(&$this,'form'));
	}
	
	
	function checkout () {
		$this->Order->confirm = true;
	}
	
	/**
	 * form()
	 * Builds a hidden form to submit to NetCash when confirming the order for processing */
	function form ($form) {
		
		$_ = array();
		$_['m_1'] = $this->settings['username'];
		$_['m_2'] = $this->settings['password'];
		$_['m_3'] = $this->settings['PIN'];
		$_['p1'] = $this->settings['terminal'];
		$_['p2'] = mktime();
		$_['p3'] = get_bloginfo('sitename');
		$_['p4'] = number_format($this->Order->Cart->Totals->total,$this->precision);
		$_['p10'] = $Shopp->link('cart',false);
		$_['Budget'] = 'Y';
		$_['m_4'] = $this->session;
		// $_['m_5'] = '';		
		// $_['m_6'] = '';
		$_['m_9'] = $this->Order->Customer->email;
		$_['m_10'] = '_txnupdate=true';
		
		return $form.$this->format($_);
	}
		
	function process () {
		global $Shopp;
		
		$txnid = $_GET['Reference'];
		$Shopp->Order->transaction($txnid,'CHARGED');
		
	}
	
	function updates () {
		global $Shopp;
		// Validate the order notification
		$returned = array('TransactionAccepted','CardHolderIpAddr','Reference','Amount','Extra1');
		foreach($returned as $key) {
			if (!isset($_GET[$key]) || empty($_GET[$key])) {
				new ShoppError(__('An unverifiable order was received from NetCash. Possible fraudulent order attempt!','Shopp'),'netcash_trxn_verification',SHOPP_TRXN_ERR);
				return false;
			}
		}
		
		if ($_GET['TransactionAccepted'] != 'true') {
			new ShoppError(__('The transaction failed: ','Shopp').$_GET['Reason'],'netcash_trxn_verification',SHOPP_TRXN_ERR);
			return false;
		}
		
		// Check for unique transaction id
		$Purchase = new Purchase($_GET['Extra1'],'transactionid');
		if (!empty($Purchase->id)) {
			if (SHOPP_DEBUG) new ShoppError('Order located and already created from a transaction update message.',false,SHOPP_DEBUG_ERR);
			$Shopp->resession();
			$Shopp->Purchase = $Purchase;
			$Shopp->Order->purchase = $Purchase->id;
			shopp_redirect($Shopp->link('thanks',false));
		}

		$Shopp->resession($_POST['custom']);
		$Shopping = &$Shopp->Shopping;
		// Couldn't load the session data
		if ($Shopping->session != $_POST['custom'])
			return new ShoppError("Session could not be loaded: {$_POST['custom']}",false,SHOPP_DEBUG_ERR);
		else new ShoppError("PayPal successfully loaded session: {$_POST['custom']}",false,SHOPP_DEBUG_ERR);
		
		return do_action('shopp_process_order'); // Create order
		
	}
			
	function url ($url) {
		return $this->url;
	}
	
	function settings () {
		$this->ui->text(0,array(
			'name' => 'username',
			'value' => $this->settings['username'],
			'size' => 30,
			'label' => __('Enter your NetCash account username.','Shopp')
		));

		$this->ui->password(0,array(
			'name' => 'password',
			'value' => $this->settings['password'],
			'size' => 30,
			'label' => __('Enter your NetCash account password.','Shopp')
		));

		$this->ui->text(0,array(
			'name' => 'PIN',
			'value' => $this->settings['PIN'],
			'size' => 5,
			'label' => __('Enter your NetCash terminal number.','Shopp')
		));
	
	
		if (!empty($this->settings['apiurl'])) {
			$this->ui->text(0,array(
				'name' => 'apiurl',
				'value' => $this->settings['apiurl'],
				'readonly' => true,
				'classes' => 'selectall',
				'size' => 48,
				'label' => '<strong>Copy this URL to your NetCash backoffice as both the Accept and Reject callback URLs found under:<br />credit cards &rarr; Credit Service Administration &rarr; Adjust Gateway Defaults</strong>'
			));
		}		
	}
	
	function apiurl () {
		global $Shopp;
		if (!empty($_POST['settings']['NetCash']['username']) 
			&& !empty($_POST['settings']['NetCash']['password'])
			&& !empty($_POST['settings']['NetCash']['PIN'])
			&& !empty($_POST['settings']['NetCash']['terminal'])) {

			$url = $Shopp->link('checkout',false);
			$_POST['settings']['NetCash']['apiurl'] = $url;
		}
	}

} // end NetCash class

?>