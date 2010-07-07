<?php
/**
 * Google Checkout
 * @class GoogleCheckout
 *
 * @author Jonathan Davis
 * @version 1.0.4
 * @copyright Ingenesis Limited, 19 August, 2008
 * @package Shopp
 * @since 1.1
 * @subpackage GoogleCheckout
 * 
 * $Id$
 **/

require_once(SHOPP_PATH."/core/model/XML.php");

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
		
		$this->merchant_calc_url = (
			SHOPP_PERMALINKS ?
			$Shopp->link('catalog').'?_txnupdate=gc' :
			add_query_arg(array('_txnupdate' => 'gc'), $Shopp->link('catalog'))
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
		add_filter('shopp_shipping_hasestimates',array(&$this, 'hasestimates_filter'));
		add_filter('shopp_checkout_submit_button',array(&$this,'submit'),10,3);
	}
	
	function hasestimates_filter () { return false; }
	
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
			if ($Response->getElement('error')) {
				new ShoppError($Response->getElementContent('error-message'),'google_checkout_error',SHOPP_TXN_ERR);
				return $this->error();
			}
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
			if(SHOPP_DEBUG) new ShoppError($data,'google_incoming_request',SHOPP_DEBUG_ERR);
			// Handle notifications
			$XML = new XMLdata($data);
			$type = key($XML->data);
			$serial = $XML->getElementAttr($type,'serial-number');
			
			$ack = true;			
			switch($type) {
				case "new-order-notification": $this->order($XML); break;
				case "risk-information-notification": $this->risk($XML); break;
				case "order-state-change-notification": $this->state($XML); break;
				case "merchant-calculation-callback": $ack = $this->merchant_calc($XML); break;
				case "charge-amount-notification":			// Not implemented
				case "refund-amount-notification":			// Not implemented
				case "chargeback-amount-notification":		// Not implemented
				case "authorization-amount-notification":	// Not implemented
					break;
			}
			// Send acknowledgement
			if($ack) $this->acknowledge($serial);	
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
		
		header('HTTP/1.1 401 Unauthorized');
		die("<h1>401 Unauthorized Access</h1>");
		exit();
	}
	
	/**
	 * acknowledge()
	 * Sends an acknowledgement message back to Google to confirm the notification
	 * was received and processed */
	function acknowledge ($serial) {
		header('HTTP/1.1 200 OK');
		$_ = array("<?xml version=\"1.0\" encoding=\"UTF-8\"?>");
		$_[] .= '<notification-acknowledgment xmlns="'.$this->urls['schema'].'" serial-number="'.$serial.'"/>';
		echo join("\n",$_);
	}

	/**
	* response()
	* Send a response for a callback
	* $message is a array containing XML response lines
	* 
	* */
	function response ($message) {
		header('HTTP/1.1 200 OK');
		echo join("\n",$message);
	} 
		
	function buildCheckoutRequest () {
		global $Shopp;
		$Cart = $this->Order->Cart;
		
		$_ = array('<?xml version="1.0" encoding="UTF-8"?>');
		$_[] = '<checkout-shopping-cart xmlns="'.$this->urls['schema'].'">';
			
			// Build the cart
			$_[] = '<shopping-cart>';
				$_[] = '<items>';
				foreach($Cart->contents as $i => $Item) {
					// if(SHOPP_DEBUG) new ShoppError("Item $i: "._object_r($Item),'google_checkout_item_'.$i,SHOPP_DEBUG_ERR);
					$_[] = '<item>';
					$_[] = '<item-name>'.htmlspecialchars($Item->name).htmlspecialchars((!empty($Item->optionlabel))?' ('.$Item->optionlabel.')':'').'</item-name>';
					$_[] = '<item-description>'.htmlspecialchars($Item->description).'</item-description>';
					if ($Item->type == 'Download') $_[] = '<digital-content><description>'.
						apply_filters('shopp_googlecheckout_download_instructions', __('You will receive an email with download instructions upon receipt of payment.','Shopp')).
						'</description>'.
						apply_filters('shopp_googlecheckout_download_delivery_markup', '<email-delivery>true</email-delivery>').
						'</digital-content>';
					// Shipped Item
					if ($Item->weight > 0) $_[] = '<item-weight unit="LB" value="'.number_format(convert_unit($Item->weight,'lb'),2).'" />';
					$_[] = '<unit-price currency="'.$this->settings['currency'].'">'.number_format($Item->unitprice,$this->precision).'</unit-price>';
					$_[] = '<quantity>'.$Item->quantity.'</quantity>';
					if (!empty($Item->sku)) $_[] = '<merchant-item-id>'.$Item->sku.'</merchant-item-id>';
					$_[] = '<merchant-private-item-data>';
						$_[] = '<shopp-product-id>'.$Item->product.'</shopp-product-id>';
						$_[] = '<shopp-price-id>'.$Item->option->id.'</shopp-price-id>';
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
						$_[] = '<unit-price currency="'.$this->settings['currency'].'">'.number_format($Cart->Totals->discount*-1,$this->precision).'</unit-price>';
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
				// Merchant Calculations
				$_[] = '<merchant-calculations>';
				$_[] = '<merchant-calculations-url>'.$this->merchant_calc_url.'</merchant-calculations-url>';
				$_[] = '</merchant-calculations>';

				if ($this->settings['use_google_shipping'] != 'on' && $Cart->shipped()) {
					if ($Cart->freeshipping === true) { // handle free shipping case and ignore all shipping methods
						$_[] = '<shipping-methods>';
						$_[] = '<flat-rate-shipping name="'.$Shopp->Settings->get('free_shipping_text').'">';
						$_[] = '<price currency="'.$this->settings['currency'].'">0.00</price>';
						$_[] = '<shipping-restrictions>';
						$_[] = '<allowed-areas><world-area /></allowed-areas>';
						$_[] = '</shipping-restrictions>';
						$_[] = '</flat-rate-shipping>';
						$_[] = '</shipping-methods>';
					}
					elseif (!empty($Cart->shipping)) {
						$_[] = '<shipping-methods>';
							foreach ($Cart->shipping as $i => $shipping) {
								$label = __('Shipping Option','Shopp').' '.($i+1);
								if (!empty($shipping->name)) $label = $shipping->name;
								$_[] = '<merchant-calculated-shipping name="'.$label.'">';
								$_[] = '<price currency="'.$this->settings['currency'].'">'.number_format($shipping->amount,$this->precision).'</price>';
								$_[] = '<shipping-restrictions>';
								$_[] = '<allowed-areas><world-area /></allowed-areas>';
								$_[] = '</shipping-restrictions>';
								$_[] = '</merchant-calculated-shipping>';
							}
						$_[] = '</shipping-methods>';
					}
				}

				if ($this->settings['use_google_taxes'] != 'on' && is_array($this->settings['taxes'])) {
					$_[] = '<tax-tables>';
						$_[] = '<default-tax-table>';
							$_[] = '<tax-rules>';
							foreach ($this->settings['taxes'] as $tax) {
								$_[] = '<default-tax-rule>';
									$_[] = '<shipping-taxed>'.($Shopp->Settings->get('tax_shipping') == 'on' ? 'true' : 'false').'</shipping-taxed>';
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
		$request = join("\n", apply_filters('shopp_googlecheckout_build_request', $_));
		
		if(SHOPP_DEBUG) new ShoppError($request,'googlecheckout_build_request',SHOPP_DEBUG_ERR);
		return $request;
	}
	
	
	/**
	 * order()
	 * Handles new order notifications from Google */
	function order ($XML) {		
		global $Shopp;
		
		if (empty($XML)) {
			new ShoppError("No transaction data was provided by Google Checkout.",'google_missing_txn_data',SHOPP_DEBUG_ERR);
			$this->error();
		}

		$sessionid = $XML->getElementContent('shopping-session');

		$Shopp->resession($sessionid);
		$Shopp->Order = ShoppingObject::__new('Order');
		
		$Shopping = &$Shopp->Shopping;
		$Order = &$Shopp->Order;

		// Couldn't load the session data
		if ($Shopping->session != $sessionid) {
			new ShoppError("Session could not be loaded: $sessionid",'google_session_load_failure',SHOPP_DEBUG_ERR);
			$this->error();
		} else new ShoppError("Google Checkout successfully loaded session: $sessionid",'google_session_load_success',SHOPP_DEBUG_ERR);

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
		$Order->Customer->marketing = ($XML->getElementContent('email-allowed') != 'false' ? 'yes' : 'no');
		$Order->Billing->address = $buyer['address1']['CONTENT'];
		$Order->Billing->xaddress = $buyer['address2']['CONTENT'];
		$Order->Billing->city = $buyer['city']['CONTENT'];
		$Order->Billing->state = $buyer['region']['CONTENT'];
		$Order->Billing->country = $buyer['country-code']['CONTENT'];
		$Order->Billing->postcode = $buyer['postal-code']['CONTENT'];
		
		$shipto = $XML->getElement('buyer-shipping-address');
		$shipto = $shipto['CHILDREN'];
		$Order->Shipping->address = $shipto['address1']['CONTENT'];
		$Order->Shipping->xaddress = $shipto['address2']['CONTENT'];
		$Order->Shipping->city = $shipto['city']['CONTENT'];
		$Order->Shipping->state = $shipto['region']['CONTENT'];
		$Order->Shipping->country = $shipto['country-code']['CONTENT'];
		$Order->Shipping->postcode = $shipto['postal-code']['CONTENT'];
		
		$Shopp->Order->gateway = $this->name;

 		$txnid = $XML->getElementContent('google-order-number');

		// Google Adjustments
		$Order->Shipping->method = $XML->getElementContent('shipping-name');
		$Order->Cart->Totals->shipping = $XML->getElementContent('shipping-cost');
		$Order->Cart->Totals->tax = $XML->getElementContent('total-tax');
		$Order->Cart->Totals->total = $XML->getElementContent('order-total');
		
		$Shopp->Order->transaction($txnid);

	}
	
	function risk ($XML) {
 		$id = $XML->getElementContent('google-order-number');
		if (empty($id)) {
			new ShoppError("No transaction ID was provided with a risk information message sent by Google Checkout",false,SHOPP_DEBUG_ERR);
			$this->error();
		}

		$Purchase = new Purchase($id,'txnid');
		if ( empty($Purchase->id) ) { 
			new ShoppError('Transaction update on non existing order.','google_order_state_missing_order',SHOPP_DEBUG_ERR);
			return;
		}
		
		$Purchase->ip = $XML->getElementContent('ip-address');
		$Purchase->card = $XML->getElementContent('partial-cc-number');
		$Purchase->save();
	}
	
	function state ($XML) {
 		$id = $XML->getElementContent('google-order-number');
		if (empty($id)) {
			new ShoppError("No transaction ID was provided with an order state change message sent by Google Checkout",'google_state_notification_error',SHOPP_DEBUG_ERR);
			$this->error();
		}

		$state = $XML->getElementContent('new-financial-order-state');
		$Purchase = new Purchase($id,'txnid');
		if ( empty($Purchase->id) ) { 
			new ShoppError('Transaction update on non existing order.','google_order_state_missing_order',SHOPP_DEBUG_ERR);
			return;
		}
		
		$Purchase->txnstatus = $state;
		$Purchase->card = $XML->getElementContent('partial-cc-number');
		$Purchase->save();
		
		if (strtoupper($state) == "CHARGEABLE" && $this->settings['autocharge'] == "on") {
			$_ = array('<?xml version="1.0" encoding="UTF-8"?>'."\n");
			$_[] = '<charge-order xmlns="'.$this->urls['schema'].'" google-order-number="'.$id.'">';
			$_[] = '<amount currency="'.$this->settings['currency'].'">'.number_format($Purchase->total,$this->precision).'</amount>';
			$_[] = '</charge-order>';
			$Response = $this->send(join("\n",$_), $this->urls['order']);
			if ($Response->getElement('error')) {
				new ShoppError($Response->getElementContent('error-message'),'google_checkout_error',SHOPP_TXN_ERR);
				return;
			}			
		}
	}

	/**
	* merchant_calc()
	* Callback function for merchant calculated shipping and taxes
	* taxes calculations unimplemented
	* returns false when it responds, as acknowledgement of merchant calculations is unnecessary
	* */
	function merchant_calc ($XML) {
		global $Shopp;

		if ($XML->getElementContent('shipping') == 'false') return true;  // ack
				
		$sessionid = $XML->getElementContent('shopping-session');
		$Shopp->resession($sessionid);
		$Shopp->Order = ShoppingObject::__new('Order');		
		$Shopping = &$Shopp->Shopping;
		$Order = &$Shopp->Order;
		
		// Get new address information on order
		$shipto = $XML->getElement('anonymous-address');
		$shipto = $shipto['CHILDREN'];
		
		$Order->Shipping->city = $shipto['city']['CONTENT'];
		$Order->Shipping->state = $shipto['region']['CONTENT'];
		$Order->Shipping->country = $shipto['country-code']['CONTENT'];
		$Order->Shipping->postcode = $shipto['postal-code']['CONTENT'];
		
		// Calculate shipping options
		$Shipping = new CartShipping();
		$Shipping->calculate();
		$options = $Shipping->options();
		if (empty($options)) return true; // acknowledge, but don't respond
		
		$methods_data = $XML->getElements('method');
		// $methods = $methods['CHILDREN'];
		$methods = array();
		foreach ($methods_data[0] as $data) {
			$methods[] = $data['ATTRS']['name'];
		}
		$address_id = $XML->getElementAttr('anonymous-address','id');		
		$_ = array('<?xml version="1.0" encoding="UTF-8"?>');
		$_[] = "<merchant-calculation-results xmlns=\"http://checkout.google.com/schema/2\">";
		$_[] = "<results>";
		foreach ($options as $option) {
			if (in_array($option->name, $methods)) {	
				$_[] = '<result shipping-name="'.$option->name.'" address-id="'.$address_id.'">';
				$_[] = '<shipping-rate currency="'.$this->settings['currency'].'">'.number_format($option->amount,$this->precision).'</shipping-rate>';
				$_[] = '<shippable>true</shippable>';
				$_[] = '</result>';
			} 
		}
		$_[] = "</results>";		
		$_[] = "</merchant-calculation-results>";
		
		$this->response($_);
		return false; //no ack
	}

	function send ($message,$url) {
		$type = ($this->settings['testmode'] == "on")?'test':'live';
		$url = sprintf($url[$type],$this->settings['id'],$this->settings['key'],$this->settings['id']);
		$response = parent::send($message,$url);
		return new XMLdata($response);
	}
	
	function error () { // Error response
		header('HTTP/1.1 500 Internal Server Error');
		die("<h1>500 Internal Server Error</h1>");
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
		
		$this->ui->checkbox(1,array(
			'name' => 'use_google_taxes',
			'checked' => ($this->settings['use_google_taxes']),
			'label' => __('Use Google tax settings','Shopp')
		));

		$this->ui->checkbox(1,array(
			'name' => 'use_google_shipping',
			'checked' => ($this->settings['use_google_shipping']),
			'label' => __('Use Google shipping rate settings','Shopp')
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