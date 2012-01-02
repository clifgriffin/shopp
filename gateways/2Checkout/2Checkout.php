<?php
/**
 * 2Checkout.com
 * @class _2Checkout
 *
 * @author Jonathan Davis
 * @version 1.1.5
 * @copyright Ingenesis Limited, 27 May, 2009
 * @package shopp
 * @since 1.1
 * @subpackage _2Checkout
 *
 * $Id$
 **/

class _2Checkout extends GatewayFramework implements GatewayModule {

	// Settings
	var $secure = false;

	// URLs
	var $url = 'https://www.2checkout.com/checkout/purchase';	// Multi-page checkout
	var $surl = 'https://www.2checkout.com/checkout/spurchase'; // Single-page, CC-only checkout

	function __construct () {
		parent::__construct();

		$this->setup('sid','verify','secret','returnurl','testmode');

		global $Shopp;
		$this->settings['returnurl'] = add_query_arg('rmtpay','process',shoppurl(false,'thanks',false));

		add_action('shopp_txn_update',array(&$this,'notifications'));
	}

	function actions () {
		add_action('shopp_process_checkout', array(&$this,'checkout'),9);
		add_action('shopp_init_checkout',array(&$this,'init'));

		add_action('shopp_init_confirmation',array(&$this,'confirmation'));
		add_action('shopp_remote_payment',array(&$this,'returned'));
		add_action('shopp_process_order',array(&$this,'process'));
	}

	function confirmation () {
		add_filter('shopp_confirm_url',array(&$this,'url'));
		add_filter('shopp_confirm_form',array(&$this,'form'));
	}

	function checkout () {
		$this->Order->confirm = true;
	}

	function url ($url) {
		if ($this->settings['singlepage'] == "on") return $this->surl;
		return $this->url;
	}

	function form ($form) {
		$db =& DB::get();

		$purchasetable = DatabaseObject::tablename(Purchase::$table);
		$next = $db->query("SELECT auto_increment as id FROM information_schema.tables WHERE table_schema=database() AND table_name='$purchasetable' LIMIT 1");

		$Order = $this->Order;
		$Order->_2COcart_order_id = date('mdy').'-'.date('His').'-'.$next->id;

		// Build the transaction
		$_ = array();

		// Required
		$_['sid']				= $this->settings['sid'];
		$_['total']				= number_format($Order->Cart->Totals->total,$this->precision, '.', '');
		$_['cart_order_id']		= $Order->_2COcart_order_id;
		$_['vendor_order_id']	= $this->session;
		$_['id_type']			= 1;

		// Extras
		if ($this->settings['testmode'] == "on")
			$_['demo']			= "Y";

		$_['fixed'] 			= "Y";
		$_['skip_landing'] 		= "1";

		$_['x_Receipt_Link_URL'] = $this->settings['returnurl'];

		// Line Items
		foreach($this->Order->Cart->contents as $i => $Item) {
			// $description[] = $Item->quantity."x ".$Item->name.((!empty($Item->optionlabel))?' '.$Item->optionlabel:'');
			$id = $i+1;
			$_['c_prod_'.$id]			= 'shopp_pid-'.$Item->product.','.$Item->quantity;
			$_['c_name_'.$id]			= $Item->name;
			$_['c_description_'.$id]	= !empty($Item->option->label)?$Item->$Item->option->label:'';
			$_['c_price_'.$id]			= number_format($Item->unitprice, $this->precision, '.', '');

		}

		$_['card_holder_name'] 		= $Order->Customer->firstname.' '.$Order->Customer->lastname;
		$_['street_address'] 		= $Order->Billing->address;
		$_['street_address2'] 		= $Order->Billing->xaddress;
		$_['city'] 					= $Order->Billing->city;
		$_['state'] 				= $Order->Billing->state;
		$_['zip'] 					= $Order->Billing->postcode;
		$_['country'] 				= $Order->Billing->country;
		$_['email'] 				= $Order->Customer->email;
		$_['phone'] 				= $Order->Customer->phone;

		$_['ship_name'] 			= $Order->Customer->firstname.' '.$Order->Customer->lastname;
		$_['ship_street_address'] 	= $Order->Shipping->address;
		$_['ship_street_address2'] 	= $Order->Shipping->xaddress;
		$_['ship_city'] 			= $Order->Shipping->city;
		$_['ship_state'] 			= $Order->Shipping->state;
		$_['ship_zip'] 				= $Order->Shipping->postcode;
		$_['ship_country'] 			= $Order->Shipping->country;

		return $form.$this->format($_);
	}

	function returned () {
		// Run order processing
		if (!empty($_POST['order_number']))
			do_action('shopp_process_order');
	}

	function process () {
		global $Shopp;

		if ($this->settings['verify'] == "on" && !$this->verify($_POST['key'])) {
			new ShoppError(__('The order submitted to 2Checkout could not be verified.','Shopp'),'2co_validation_error',SHOPP_TRXN_ERR);
			shopp_redirect(shoppurl(false,'checkout'));
		}

		if (empty($_POST['order_number'])) {
			new ShoppError(__('The order submitted by 2Checkout did not specify a transaction ID.','Shopp'),'2co_validation_error',SHOPP_TRXN_ERR);
			shopp_redirect(shoppurl(false,'checkout'));
		}

		$txnid = $_POST['order_number'];
		$txnstatus = $_POST['credit_card_processed'] == "Y"?'CHARGED':'PENDING';

		$Shopp->Order->transaction($txnid,$txnstatus);

	}

	function notification () {
		// INS updates not implemented
	}

	function verify ($key) {
		if ($this->settings['testmode'] == "on") return true;
		$order = $_POST['order_number'];

		$verification = strtoupper(md5($this->settings['secret'].
							$this->settings['sid'].
							$order.
							number_format($this->Order->Cart->Totals->total,$this->precision, '.', '')));

		return ($verification == $key);
	}

	function settings () {

		$this->ui->text(0,array(
			'name' => 'sid',
			'size' => 10,
			'value' => $this->settings['sid'],
			'label' => __('Your 2Checkout vendor account number.','Shopp')
		));

		$this->ui->text(0,array(
			'name' => 'returnurl',
			'size' => 40,
			'value' => $this->settings['returnurl'],
			'readonly' => 'readonly',
			'classes' => 'selectall',
			'label' => __('Copy as the <strong>Approved URL</strong> & <strong>Pending URL</strong> in your 2Checkout Vendor Area under the <strong>Account &rarr; Site Management</strong> settings page.','Shopp')
		));

		$this->ui->checkbox(1,array(
			'name' => 'testmode',
			'checked' => $this->settings['testmode'],
			'label' => __('Enable test mode','Shopp')
		));

		$this->ui->checkbox(1,array(
			'name' => 'singlepage',
			'checked' => $this->settings['singlepage'],
			'label' => __('Single-page, credit card only checkout','Shopp')
		));

		$this->ui->checkbox(1,array(
			'name' => 'verify',
			'checked' => $this->settings['verify'],
			'label' => __('Enable order verification','Shopp')
		));

		$this->ui->text(1,array(
			'name' => 'secret',
			'size' => 10,
			'value' => $this->settings['secret'],
			'label' => __('Your 2Checkout secret word for order verification.','Shopp')
		));

		$this->ui->p(1,array(
			'name' => 'returnurl',
			'size' => 40,
			'value' => $this->settings['returnurl'],
			'readonly' => 'readonly',
			'classes' => 'selectall',
			'content' => '<span style="width: 300px;">&nbsp;</span>'
		));

		$this->verifysecret();
	}

	function verifysecret () {
?>
		_2Checkout.behaviors = function () {
			$('#settings-_2checkout-verify').change(function () {
				if ($(this).attr('checked')) $('#settings-_2checkout-secret').parent().show();
				else $('#settings-_2checkout-secret').parent().hide();
			}).change();
		}
<?php
	}

} // END class _2Checkout

?>