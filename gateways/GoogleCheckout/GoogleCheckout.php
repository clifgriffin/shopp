<?php
/**
 * Google Checkout
 * @class GoogleCheckout
 *
 * @author Jonathan Davis
 * @version 1.0.3
 * @copyright Ingenesis Limited, 19 August, 2008
 * @package Shopp
 * @since 1.1
 * @subpackage GoogleCheckout
 * 
 * $Id$
 **/

require_once(SHOPP_PATH."/core/model/XMLdata.php");

class GoogleCheckout extends GatewayFramework implements GatewayModule {

	var $secure = false;

	function __construct () {
		parent::__construct();
				
		global $Shopp;
		
		$this->urls['schema'] = 'http://checkout.google.com/schema/2';
		
		$this->urls['checkout'] = array(
			'live' => 'https://%s:%s@checkout.google.com/api/checkout/v2/merchantCheckout/Merchant/%s',
			'test' => 'https://%s:%s@sandbox.google.com/checkout/api/checkout/v2/merchantCheckout/Merchant/%s'
			);
			
		$this->urls['order'] = array(
			'live' => 'https://%s:%s@checkout.google.com/api/checkout/v2/request/Merchant/%s',
			'test' => 'https://%s:%s@sandbox.google.com/checkout/api/checkout/v2/request/Merchant/%s'
			);
			
		$this->urls['button'] = array(
			'live' => 'http://checkout.google.com/buttons/checkout.gif',
			'test' => 'http://sandbox.google.com/checkout/buttons/checkout.gif'
			);
		
		$this->setup('id','key','apiurl');
		$this->settings['merchant_email'] = $Shopp->Settings->get('merchant_email');
		$this->settings['location'] = "en_US";
		$base = $Shopp->Settings->get('base_operations');
		if ($base['country'] == "GB") $this->settings['location'] = "en_UK";
		
		$this->settings['base_operations'] = $Shopp->Settings->get('base_operations');
		$this->settings['currency'] = $this->settings['base_operations']['currency']['code'];
		if (empty($this->settings['currency'])) $this->settings['currency'] = "USD";

		$this->settings['taxes'] = $Shopp->Settings->get('taxrates');
		
		if (isset($_GET['gctest'])) $this->order('');
		
		add_action('shopp_txn_update',array(&$this,'notifications'));
	}
	
	function actions () {
		add_action('shopp_process_checkout', array(&$this,'checkout'),9);
		add_action('shopp_init_checkout',array(&$this,'init'));

		add_action('shopp_save_payment_settings',array(&$this,'apiurl'));
		
	}
	
	function init () {
		add_filter('shopp_checkout_submit_button',array(&$this,'submit'),10,3);
	}
	
	function submit ($tag=false,$options=array(),$attrs=array()) {
		$type = "live";
		if ($this->settings['testmode'] == "on") $type = "test";
		$buttonuri = $this->urls['button'][$type];
		$buttonuri .= '?merchant_id='.$this->settings['id'];
		$buttonuri .= '&'.$this->settings['button'];
		$buttonuri .= '&style='.$this->settings['buttonstyle'];
		$buttonuri .= '&variant=text';
		$buttonuri .= '&loc='.$this->settings['location'];
		
		return '<input type="image" name="process" src="'.$buttonuri.'" id="checkout-button" '.inputattrs($options,$attrs).' />';
	}
	
	
	function checkout () {
		global $Shopp;

		if ($this->Order->Cart->Totals->total == 0) shopp_redirect($Shopp->link('checkout'));

		$stock = true;
		foreach( $this->Order->Cart->contents as $item ) { //check stock before redirecting to Google
			if (!$item->instock()){
				new ShoppError(sprintf(__("There is not sufficient stock on %s to process order."),$item->name),'invalid_order',SHOPP_TXN_ERR);
				$stock = false;
			}
		}
		if (!$stock) shopp_redirect($Shopp->link('cart'));
		
		$message = $this->buildCheckoutRequest();
		$Response = $this->send($message,$this->urls['checkout']);
		
		if (!empty($Response)) {
			if ($Response->getElement('error')) return $this->error();
			$redirect = false;
			$redirect = $Response->getElementContent('redirect-url');
			
			if ($redirect) {
				$Shopp->resession();
				shopp_redirect($redirect);
			}
		}
			
		return false;	
	}
	
	function notifications () {
		if ($this->authentication()) {			
			
			// Read incoming request data
			$data = trim(file_get_contents('php://input'));

			// Handle notifications
			$XML = new XMLdata($data);
			$type = key($XML->data);
			$serial = $XML->getElementAttr($type,'serial-number');
			
			switch($type) {
				case "new-order-notification": $this->order($XML); break;
				case "risk-information-notification": $this->risk($XML); break;
				case "order-state-change-notification": $this->state($XML); break;
				case "charge-amount-notification":			// Not implemented
				case "refund-amount-notification":			// Not implemented
				case "chargeback-amount-notification":		// Not implemented
				case "authorization-amount-notification":	// Not implemented
					break;
			}
			// Send acknowledgement
			$this->acknowledge($serial);	
		}
		exit();
	}
	
	/**
	 * authcode()
	 * Build a hash code for the merchant id and merchant key */
	function authcode ($id,$key) {
		return sha1($id.$key);
	}
	
	/**
	 * authentication()
	 * Authenticate an incoming request */
	function authentication () {
		if (isset($_GET['merc'])) $merc = $_GET['merc'];

		if (!empty($this->settings['id']) && !empty($this->settings['key']) 
				&& $_GET['merc'] == $this->authcode($this->settings['id'],$this->settings['key']));
		 	return true;
		
		header('HTTP/1.0 401 Unauthorized');
		die("<h1>401 Unauthorized Access</h1>");
		exit();
	}
	
	/**
	 * acknowledge()
	 * Sends an acknowledgement message back to Google to confirm the notification
	 * was received and processed */
	function acknowledge ($serial) {
		header('HTTP/1.0 200 OK');
		$_ = array('<?xml version="1.0" encoding="utf-8"?>'."\n");
		$_[] .= '<notification-acknowledgement xmlns="'.$this->urls['schema'].'" serial-number="'.$serial.'" />';
		echo join("\n",$_);
	}
		
	function buildCheckoutRequest () {
		$Cart = $this->Order->Cart;
		
		$_ = array('<?xml version="1.0" encoding="utf-8"?>'."\n");
		$_[] = '<checkout-shopping-cart xmlns="'.$this->urls['schema'].'">';
			
			// Build the cart
			$_[] = '<shopping-cart>';
				$_[] = '<items>';
				foreach($Cart->contents as $i => $Item) {
					$_[] = '<item>';
					$_[] = '<item-name>'.htmlspecialchars($Item->name).htmlspecialchars((!empty($Item->optionlabel))?' ('.$Item->optionlabel.')':'').'</item-name>';
					$_[] = '<item-description>'.htmlspecialchars($Item->description).'</item-description>';
					$_[] = '<unit-price currency="'.$this->settings['currency'].'">'.number_format($Item->unitprice,2).'</unit-price>';
					$_[] = '<quantity>'.$Item->quantity.'</quantity>';
					if (!empty($Item->sku)) $_[] = '<merchant-item-id>'.$Item->sku.'</merchant-item-id>';
					$_[] = '<merchant-private-item-data>';
						$_[] = '<shopp-product-id>'.$Item->product.'</shopp-product-id>';
						$_[] = '<shopp-price-id>'.$Item->price.'</shopp-price-id>';
						if (is_array($Item->data) && count($Item->data) > 0) {
							$_[] = '<shopp-item-data-list>';
							foreach ($Item->data AS $name => $data) {
								$_[] = '<shopp-item-data name="'.esc_attr($name).'">'.esc_attr($data).'</shopp-item-data>';
							}
							$_[] = '</shopp-item-data-list>';
						}
					$_[] = '</merchant-private-item-data>';
					$_[] = '</item>';
				}
				
				// Include any discounts
				if ($Cart->Totals->discount > 0) {
					foreach($Cart->discounts as $promo) $discounts[] = $promo->name;
					$_[] = '<item>';
						$_[] = '<item-name>Discounts</item-name>';
						$_[] = '<item-description>'.join(", ",$discounts).'</item-description>';
						$_[] = '<unit-price currency="'.$this->settings['currency'].'">'.number_format($Cart->Totals->discount*-1,2).'</unit-price>';
						$_[] = '<quantity>1</quantity>';
					$_[] = '</item>';
				}
				$_[] = '</items>';
				
				// Include notification that the order originated from Shopp
				$_[] = '<merchant-private-data>';
					$_[] = '<shopping-session>'.$this->session.'</shopping-session>';
					$_[] = '<shopping-cart-agent>'.SHOPP_GATEWAY_USERAGENT.'</shopping-cart-agent>';
					$_[] = '<customer-ip>'.$_SERVER['REMOTE_ADDR'].'</customer-ip>';

					if (is_array($this->Order->data) && count($this->Order->data) > 0) {
						$_[] = '<shopp-order-data-list>';
						foreach ($this->Order->data AS $name => $data) {
							$_[] = '<shopp-order-data name="'.esc_attr($name).'">'.esc_attr($data).'</shopp-item-data>';
						}
						$_[] = '</shopp-order-data-list>';
					}
				$_[] = '</merchant-private-data>';
				
			$_[] = '</shopping-cart>';
						
			// Build the flow support request
			$_[] = '<checkout-flow-support>';
				$_[] = '<merchant-checkout-flow-support>';

				// Shipping Methods
				if ($Cart->data->Shipping && !empty($Cart->data->ShipCosts)) {
					$_[] = '<shipping-methods>';
						foreach ($Cart->data->ShipCosts as $i => $shipping) {
							$label = __('Shipping Option','Shopp').' '.($i+1);
							if (!empty($shipping['name'])) $label = $shipping['name'];
							$_[] = '<flat-rate-shipping name="'.$label.'">';
							$_[] = '<price currency="'.$this->settings['currency'].'">'.number_format($shipping['cost'],2).'</price>';
							$_[] = '</flat-rate-shipping>';
						}
					$_[] = '</shipping-methods>';
				}

				if (is_array($this->settings['taxes'])) {
					$_[] = '<tax-tables>';
						$_[] = '<default-tax-table>';
							$_[] = '<tax-rules>';
							foreach ($this->settings['taxes'] as $tax) {
								$_[] = '<default-tax-rule>';
									$_[] = '<shipping-taxed>false</shipping-taxed>';
									$_[] = '<rate>'.number_format($tax['rate']/100,4).'</rate>';
									$_[] = '<tax-area>';
										if ($tax['country'] == "US" && isset($tax['zone'])) {
											$_[] = '<us-state-area>';
												$_[] = '<state>'.$tax['zone'].'</state>';
											$_[] = '</us-state-area>';
										} elseif ($tax['country'] == "*") {
											$_[] = '<world-area />';
										} else {
											$_[] = '<postal-area>';
												$_[] = '<country-code>'.$tax['country'].'</country-code>';
											$_[] = '</postal-area>';
										}
									$_[] = '</tax-area>';
								$_[] = '</default-tax-rule>';
							}
							$_[] = '</tax-rules>';
						$_[] = '</default-tax-table>';
					$_[] = '</tax-tables>';
				}
			
				$_[] = '</merchant-checkout-flow-support>';
			$_[] = '</checkout-flow-support>';
			
			
		$_[] = '</checkout-shopping-cart>';
		// echo "<pre>"; print_r($_); echo "</pre>";
		return join("\n",$_);
	}
	
	
	/**
	 * order()
	 * Handles new order notifications from Google */
	function order ($XML) {
		global $Shopp;

		add_action('shopp_order_success',array(&$this,'success'),1);
		
		$sessionid = $XML->getElement('shopping-session');
		
		$Shopp->resession($sessionid['CONTENT']);
		$Shopping = &$Shopp->Shopping;
		$Order = &$Shopp->Order;

		// Couldn't load the session data
		if ($Shopping->session != $sessionid['CONTENT'])
			return new ShoppError("Session could not be loaded: {$sessionid['CONTENT']}",false,SHOPP_DEBUG_ERR);
		else new ShoppError("Google Checkout successfully loaded session: {$sessionid['CONTENT']}",false,SHOPP_DEBUG_ERR);

		// Check if this is a Shopp order or not
		$origin = $XML->getElementContent('shopping-cart-agent');
		if (empty($origin) || 
			substr($origin,0,strpos("/",SHOPP_GATEWAY_USERAGENT)) == SHOPP_GATEWAY_USERAGENT) 
				return true;
		
		$buyer = $XML->getElement('buyer-billing-address');
		$buyer = $buyer['CHILDREN'];
		
		$name = $XML->getElement('structured-name');
		$Order->Customer->firstname = $buyer['structured-name']['CHILDREN']['first-name']['CONTENT'];
		$Order->Customer->lastname = $buyer['structured-name']['CHILDREN']['last-name']['CONTENT'];
		if (empty($name)) {
			$name = $buyer['contact-name']['CONTENT'];
			$names = explode(" ",$name);
			$Order->Customer->firstname = $names[0];
			$Order->Customer->lastname = $names[count($names)-1];
		}
		
		if (!empty($buyer['email']['CONTENT'])) 
			$Order->Customer->email = $buyer['email']['CONTENT'];
		if (!empty($buyer['phone']['CONTENT']))
			$Order->Customer->phone = $buyer['phone']['CONTENT'];

		$Order->Billing->address = $buyer['address1']['CONTENT'];
		$Order->Billing->xaddress = $buyer['address2']['CONTENT'];
		$Order->Billing->city = $buyer['city']['CONTENT'];
		$Order->Billing->state = $buyer['region']['CONTENT'];
		$Order->Billing->country = $buyer['country-code']['CONTENT'];
		$Order->Billing->postcode = $buyer['postal-code']['CONTENT'];
		$Order->Billing->card = $XML->getElementContent('partial-cc-number');
		
		$shipto = $XML->getElement('buyer-shipping-address');
		$shipto = $shipto['CHILDREN'];
		$Order->Shipping->address = $shipto['address1']['CONTENT'];
		$Order->Shipping->xaddress = $shipto['address2']['CONTENT'];
		$Order->Shipping->city = $shipto['city']['CONTENT'];
		$Order->Shipping->state = $shipto['region']['CONTENT'];
		$Order->Shipping->country = $shipto['country-code']['CONTENT'];
		$Order->Shipping->postcode = $shipto['postal-code']['CONTENT'];

 		$txnid = $XML->getElementContent('google-order-number');
		
		$Shopp->Order->transaction($txnid);

	}
	
	function success () {
		die('Success');
	}
	
	function risk ($XML) {
 		$id = $XML->getElementContent('google-order-number');
		$Purchase = new Purchase($id,'transactionid');
		$Purchase->ip = $XML->getElementContent('ip-address');
		$Purchase->card = $XML->getElementContent('partial-cc-number');
		$Purchase->save();
	}
	
	function state ($XML) {
 		$id = $XML->getElementContent('google-order-number');
		$state = $XML->getElementContent('new-financial-order-state');
		$Purchase = new Purchase($id,'transactionid');
		$Purchase->transtatus = $state;
		$Purchase->save();
		
		if (strtoupper($state) == "CHARGEABLE" && $this->settings['autocharge'] == "on") {
			$_ = array('<?xml version="1.0" encoding="utf-8"?>'."\n");
			$_[] = '<charge-order xmlns="'.$this->urls['schema'].'" google-order-number="'.$id.'">';
			$_[] = '<amount currency="'.$this->settings['currency'].'">'.$Purchase->total.'</amount>';
			$_[] = '</charge-order>';
			$this->transaction = join("\n",$_);
			$Reponse = $this->send($this->urls['order']);
			exit();
		}
	}
	
	function send ($message,$url) {
		$type = ($this->settings['testmode'] == "on")?'test':'live';
		$url = sprintf($url[$type],$this->settings['id'],$this->settings['key'],$this->settings['id']);
		$response = parent::send($message,$url);
		return new XMLdata($response);
	}
	
	function error () {
		$message = $this->Response->getElementContent('error-message');
		if (!empty($message)) 
			return new ShoppError($message,'google_checkout_error',SHOPP_TRXN_ERR);
	}
		
	function tag ($property,$options=array()) {
		global $Shopp;
		switch ($property) {
			case "button": 
				$type = "live";
				if ($this->settings['testmode'] == "on") $type = "test";
				$buttonuri = $this->urls['button'][$type];
				$buttonuri .= '?merchant_id='.$this->settings['id'];
				$buttonuri .= '&'.$this->settings['button'];
				$buttonuri .= '&style='.$this->settings['buttonstyle'];
				$buttonuri .= '&variant=text';
				$buttonuri .= '&loc='.$this->settings['location'];
				if (SHOPP_PERMALINKS) $url = $Shopp->link('checkout')."?shopp_xco=GoogleCheckout/GoogleCheckout";
				else $url = add_query_arg('shopp_xco','GoogleCheckout/GoogleCheckout',$Shopp->link('checkout'));
				return '<p class="google_checkout"><a href="'.$url.'"><img src="'.$buttonuri.'" alt="Checkout with Google Checkout" /></a></p>';
		}
	}
	
	function settings () {
		global $Shopp;
		$buttons = array("w=160&h=43"=>"Small (160x43)","w=168&h=44"=>"Medium (168x44)","w=180&h=46"=>"Large (180x46)");
		$styles = array("white"=>"On White Background","trans"=>"With Transparent Background");
		
		$this->ui->text(0,array(
			'name' => 'id',
			'value' => $this->settings['id'],
			'size' => 18,
			'label' => __('Enter your Google Checkout merchant ID.','Shopp')
		));
		
		$this->ui->text(0,array(
			'name' => 'key',
			'value' => $this->settings['key'],
			'size' => 24,
			'label' => __('Enter your Google Checkout merchant key.','Shopp')
		));

 		if (!empty($this->settings['apiurl'])) {
			$this->ui->text(0,array(
				'name' => 'apiurl',
				'value' => $this->settings['apiurl'],
				'size' => 48,
				'readonly' => true,
				'classes' => 'selectall',
				'label' => __('Copy this URL to your Google Checkout integration settings API callback URL.','Shopp')
			));
		}

		$this->ui->checkbox(0,array(
			'name' => 'testmode',
			'checked' => ($this->settings['testmode'] == "on"),
			'label' => sprintf(__('Use the %s','Shopp'),'<a href="http://docs.shopplugin.net/Google_Checkout_Sandbox">Google Checkout Sandbox</a>')
		));
		
		$this->ui->menu(1,array(
			'name' => 'button',
			'selected' => $this->settings['button']
		),$buttons);

		$this->ui->menu(1,array(
			'name' => 'buttonstyle',
			'selected' => $this->settings['buttonstyle'],
			'label' => __('Select the preferred size and style of the Google Checkout button.','Shopp')
		),$styles);
		
		$this->ui->checkbox(1,array(
			'name' => 'autocharge',
			'checked' => ($this->settings['autocharge'] == "on"),
			'label' => __('Automatically charge orders','Shopp')
		));
	}

	function apiurl () {
		global $Shopp;
		// Build the Google Checkout API URL if Google Checkout is enabled
		if (!empty($_POST['settings']['GoogleCheckout']['id']) && !empty($_POST['settings']['GoogleCheckout']['key'])) {
			$GoogleCheckout = new GoogleCheckout();
			$url = add_query_arg(array(
				'_txnupdate' => 'gc',
				'merc' => $GoogleCheckout->authcode(
										$_POST['settings']['GoogleCheckout']['id'],
										$_POST['settings']['GoogleCheckout']['key'])
				),$Shopp->link('checkout',true));
			$_POST['settings']['GoogleCheckout']['apiurl'] = $url;
		}
	}

} // END class GoogleCheckout

?>