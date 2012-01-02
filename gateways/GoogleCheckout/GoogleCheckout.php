<?php
/**
 * Google Checkout
 * @class GoogleCheckout
 *
 * @author Jonathan Davis, John Dillick
 * @version 1.1.8
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
			'live' => (is_shopp_secure()?'https':'http').'://checkout.google.com/buttons/checkout.gif',
			'test' => (is_shopp_secure()?'https':'http').'://sandbox.google.com/checkout/buttons/checkout.gif'
			);

		$this->merchant_calc_url = esc_url(add_query_string('_txnupdate=gc',shoppurl(false,'catalog',true)));

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
		add_filter('shopp_checkout_submit_button',array(&$this,'submit'),10,3);
		add_action('get_header',array(&$this,'analytics'));
		add_filter('shopp_tag_cart_google',array($this,'cartcheckout'));
		add_action('parse_request',array(&$this,'intercept_cartcheckout'));

	}

	function analytics() {  do_action('shopp_google_checkout_analytics'); }

	function actions () {
		add_action('shopp_init_checkout',array(&$this,'init'));

		add_action('shopp_save_payment_settings',array(&$this,'apiurl'));
		add_action('shopp_process_order',array(&$this, 'process'));
		add_filter('shopp_ordering_no_shipping_costs',array(&$this, 'hasshipping_filter'));

	}

	function init () {
		if (count($this->Order->payoptions) == 1) add_filter('shopp_shipping_hasestimates',array(&$this, 'hasestimates_filter'));
	}


	function hasestimates_filter () { return false; }

	function hasshipping_filter ( $valid ) {
		if ($this->settings['use_google_shipping'] == 'on') {
			return true;
		}
		return $valid;
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

		$tag[$this->settings['label']] = '<input type="image" name="process" src="'.$buttonuri.'" '.inputattrs($options,$attrs).' />';
		return $tag;

	}

	function cartcheckout ($result) {
		$tag = $this->submit();
		$form = '<form id="checkout" action="'.shoppurl(false,'checkout').'" method="post" >'
		.'<input type="hidden" name="google_cartcheckout" value="true" />'
		.shopp('checkout','function','return=1')
		.$tag[$this->settings['label']].'</form>';
		return $form;
	}

	function intercept_cartcheckout () {
		if (!empty($_POST['google_cartcheckout'])) {
			$this->process();
		}
	}

	function process () {
		global $Shopp;

		$stock = true;
		foreach( $this->Order->Cart->contents as $item ) { //check stock before redirecting to Google
			if (!$item->instock()){
				new ShoppError(sprintf(__("There is not sufficient stock on %s to process order."),$item->name),'invalid_order',SHOPP_TRXN_ERR);
				$stock = false;
			}
		}
		if (!$stock) shopp_redirect(shoppurl(false,'cart',false));

		$message = $this->buildCheckoutRequest();
		$Response = $this->send($message,$this->urls['checkout']);

		if (!empty($Response)) {
			if ($Response->tag('error')) {
				new ShoppError($Response->content('error-message'),'google_checkout_error',SHOPP_TRXN_ERR);
				shopp_redirect(shoppurl(false,'checkout'));
			}
			$redirect = false;
			$redirect = $Response->content('redirect-url');

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
			$XML = new xmlQuery($data);
			$type = $XML->context();
			if ( $type === false ) {
				if(SHOPP_DEBUG) new ShoppError('Unable to determine context of request.','google_checkout_unknown_notification',SHOPP_DEBUG_ERR);
				return;
			}
			$serial = $XML->attr($type,'serial-number');

			$ack = true;
			switch($type) {
				case "new-order-notification": $this->order($XML); break;
				case "risk-information-notification": $this->risk($XML); break;
				case "order-state-change-notification": $this->state($XML); break;
				case "merchant-calculation-callback": $ack = $this->merchant_calc($XML); break;
				case "charge-amount-notification":	break;		// Not implemented
				case "refund-amount-notification":	$this->refund($XML); break;
				case "chargeback-amount-notification":		break;	// Not implemented
				case "authorization-amount-notification":	break;	// Not implemented
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
					$_[] = '<item-name>'.htmlspecialchars($Item->name).htmlspecialchars((!empty($Item->option->label))?' ('.$Item->option->label.')':'').'</item-name>';
					$_[] = '<item-description>'.htmlspecialchars($Item->description).'</item-description>';
					if ($Item->type == 'Download') $_[] = '<digital-content><description>'.
						apply_filters('shopp_googlecheckout_download_instructions', __('You will receive an email with download instructions upon receipt of payment.','Shopp')).
						'</description>'.
						apply_filters('shopp_googlecheckout_download_delivery_markup', '<email-delivery>true</email-delivery>').
						'</digital-content>';
					// Shipped Item
					$_[] = '<item-weight unit="LB" value="'.($Item->weight > 0 ? number_format(convert_unit($Item->weight,'lb'),2,'.','') : 0).'" />';
					$_[] = '<unit-price currency="'.$this->settings['currency'].'">'.number_format($Item->unitprice,$this->precision,'.','').'</unit-price>';
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

					if ($this->settings['use_google_taxes'] != 'on' && is_array($this->settings['taxes'])) { // handle tax free or per item tax
						if ($Item->taxable === false)
							$_[] = '<tax-table-selector>non-taxable</tax-table-selector>';
						elseif ($item_tax_table_selector = apply_filters('shopp_google_item_tax_table_selector', false, $Item) !== false)
							$_[] = $item_tax_table_selector;
					}

					$_[] = '</item>';
				}

				// Include any discounts
				if ($Cart->Totals->discount > 0) {
					foreach($Cart->discounts as $promo) $discounts[] = $promo->name;
					$_[] = '<item>';
						$_[] = '<item-name>Discounts</item-name>';
						$_[] = '<item-description>'.join(", ",$discounts).'</item-description>';
						$_[] = '<unit-price currency="'.$this->settings['currency'].'">'.number_format($Cart->Totals->discount*-1,$this->precision,'.','').'</unit-price>';
						$_[] = '<quantity>1</quantity>';
						$_[] = '<item-weight unit="LB" value="0" />';
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
							$_[] = '<shopp-order-data name="'.esc_attr($name).'">'.esc_attr($data).'</shopp-order-data>';
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
						$free_shipping_text = $Shopp->Settings->get('free_shipping_text');
						if (empty($free_shipping_text)) $free_shipping_text = __('Free Shipping!','Shopp');
						$_[] = '<shipping-methods>';
						$_[] = '<flat-rate-shipping name="'.$free_shipping_text.'">';
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
								$_[] = '<price currency="'.$this->settings['currency'].'">'.number_format($shipping->amount,$this->precision,'.','').'</price>';
								$_[] = '<address-filters>';
									$_[] = '<allowed-areas><world-area /></allowed-areas>';
								$_[] = '</address-filters>';
								$_[] = '</merchant-calculated-shipping>';
							}
						$_[] = '</shipping-methods>';
					}
				}

				if ($this->settings['use_google_taxes'] != 'on' && is_array($this->settings['taxes'])) {
					$_[] = '<tax-tables>';

					$_[] = '<alternate-tax-tables>';
						$_[] = '<alternate-tax-table standalone="true" name="non-taxable">'; // Built-in non-taxable table
							$_[] = '<alternate-tax-rules>';
								$_[] = '<alternate-tax-rule>';
									$_[] = '<rate>'.number_format(0,4).'</rate><tax-area><world-area /></tax-area>';
								$_[] = '</alternate-tax-rule>';
							$_[] = '</alternate-tax-rules>';
						$_[] = '</alternate-tax-table>';
						if ($alternate_tax_tables_content = apply_filters('shopp_google_alternate_tax_tables_content', false) !== false)
							$_[] = $alternate_tax_tables_content;
					$_[] = '</alternate-tax-tables>';

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

				if (isset($_POST['analyticsdata'])) $_[] = '<analytics-data>'.$_POST['analyticsdata'].'</analytics-data>';
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

		$sessionid = $XML->content('shopping-session:first');
		$order_summary = $XML->tag('order-summary');

		$Shopp->Order->unhook();
		$Shopp->resession($sessionid);
		$Shopp->Order = ShoppingObject::__new('Order',$Shopp->Order);
		$Shopp->Order->listeners();

		$Shopping = &$Shopp->Shopping;
		$Order = &$Shopp->Order;

		// Couldn't load the session data
		if ($Shopping->session != $sessionid) {
			new ShoppError("Session could not be loaded: $sessionid",'google_session_load_failure',SHOPP_DEBUG_ERR);
			$this->error();
		} else new ShoppError("Google Checkout successfully loaded session: $sessionid",'google_session_load_success',SHOPP_DEBUG_ERR);

		// // Check if this is a Shopp order or not
		// $origin = $order_summary->content('shopping-cart-agent');
		// if (empty($origin) ||
		// 	substr($origin,0,strpos("/",SHOPP_GATEWAY_USERAGENT)) == SHOPP_GATEWAY_USERAGENT)
		// 		return true;

		$buyer = $XML->tag('buyer-billing-address'); // buyer billing address not in order summary
		$name = $buyer->tag('structured-name');

		$Order->Customer->firstname = $name->content('first-name');
		$Order->Customer->lastname = $name->content('last-name');
		if (empty($name)) {
			$name = $buyer->content('contact-name');
			$names = explode(" ",$name);
			$Order->Customer->firstname = $names[0];
			$Order->Customer->lastname = $names[count($names)-1];
		}

		$email = $buyer->content('email');
		$Order->Customer->email = !empty($email) ? $email : '';
		$phone = $buyer->content('phone');
		$Order->Customer->phone = !empty($phone) ? $phone : '';

		$Order->Customer->marketing = $order_summary->content('buyer-marketing-preferences > email-allowed') != 'false' ? 'yes' : 'no';
		$Order->Billing->address = $buyer->content('address1');
		$Order->Billing->xaddress = $buyer->content('address2');
		$Order->Billing->city = $buyer->content('city');
		$Order->Billing->state = $buyer->content('region');
		$Order->Billing->country = $buyer->content('country-code');
		$Order->Billing->postcode = $buyer->content('postal-code');
		$Order->Billing->cardtype = "GoogleCheckout";

		$shipto = $order_summary->tag('buyer-shipping-address');
		$Order->Shipping->address = $shipto->content('address1');
		$Order->Shipping->xaddress = $shipto->content('address2');
		$Order->Shipping->city = $shipto->content('city');
		$Order->Shipping->state = $shipto->content('region');
		$Order->Shipping->country = $shipto->content('country-code');
		$Order->Shipping->postcode = $shipto->content('postal-code');

		$Shopp->Order->gateway = $this->name;


 		$txnid = $order_summary->content('google-order-number');

		// Google Adjustments
		$order_adjustment = $order_summary->tag('order-adjustment');
		$Order->Shipping->method = $order_adjustment->content('shipping-name') ? $order_adjustment->content('shipping-name') : $Order->Shipping->method;
		$Order->Cart->Totals->shipping = $order_adjustment->content('shipping-cost');
		$Order->Cart->Totals->tax = $order_adjustment->content('total-tax');

		// New total from order summary
		$Order->Cart->Totals->total = $order_summary->content('order-total');

		$Shopp->Order->transaction($txnid);

	}

	function risk ($XML) {
		$summary = $XML->tag('order-summary');
 		$id = $summary->content('google-order-number');

		if (empty($id)) {
			new ShoppError("No transaction ID was provided with a risk information message sent by Google Checkout",false,SHOPP_DEBUG_ERR);
			$this->error();
		}

		$Purchase = new Purchase($id,'txnid');
		if ( empty($Purchase->id) ) {
			new ShoppError('Transaction update on non existing order.','google_order_state_missing_order',SHOPP_DEBUG_ERR);
			return;
		}

		$Purchase->ip = $XML->content('ip-address');
		$Purchase->card = $XML->content('partial-cc-number');
		$Purchase->save();
	}

	function state ($XML) {
		global $Shopp;
		$summary = $XML->tag('order-summary');
 		$id = $summary->content('google-order-number');
		if (empty($id)) {
			new ShoppError("No transaction ID was provided with an order state change message sent by Google Checkout",'google_state_notification_error',SHOPP_DEBUG_ERR);
			$this->error();
		}

		$state = $XML->content('new-financial-order-state');

		$Purchase = new Purchase($id,'txnid');
		if ( empty($Purchase->id) ) {
			new ShoppError('Transaction update on non existing order.','google_order_state_missing_order',SHOPP_DEBUG_ERR);
			return;
		}

		$Purchase->card = $XML->content('partial-cc-number');
		$Purchase->save();
		$Shopp->Order->transaction($id, $state); // new transaction state

		if (strtoupper($state) == "CHARGEABLE" && $this->settings['autocharge'] == "on") {
			$_ = array('<?xml version="1.0" encoding="UTF-8"?>'."\n");
			$_[] = '<charge-order xmlns="'.$this->urls['schema'].'" google-order-number="'.$id.'">';
			$_[] = '<amount currency="'.$this->settings['currency'].'">'.number_format($Purchase->total,$this->precision,'.','').'</amount>';
			$_[] = '</charge-order>';
			$Response = $this->send(join("\n",$_), $this->urls['order']);
			if ($Response->tag('error')) {
				new ShoppError($Response->content('error-message'),'google_checkout_error',SHOPP_TRXN_ERR);
				return;
			}
		}
	}

	/**
	 * refund
	 *
	 * Currently only sets the transaction status to refunded
	 *
	 * @author John Dillick
	 * @since 1.1
	 *
	 * @param string $XML The XML request from google
	 * @return void
	 **/
	function refund($XML) {
		$summary = $XML->tag('order-summary');
 		$id = $summary->content('google-order-number');
		$refund = $summary->content('total-refund-amount');

		if (empty($id)) {
			new ShoppError("No transaction ID was provided with an order refund notification sent by Google Checkout",'google_refund_notification_error',SHOPP_DEBUG_ERR);
			$this->error();
		}
		$Purchase = new Purchase($id,'txnid');
		if ( empty($Purchase->id) ) {
			new ShoppError('Transaction refund on non-existing order.','google_refund_missing_order',SHOPP_DEBUG_ERR);
			return;
		}

		$Purchase->txnstatus = "REFUNDED";
		$Purchase->save();
	}


	/**
	* merchant_calc()
	* Callback function for merchant calculated shipping and taxes
	* taxes calculations unimplemented
	* returns false when it responds, as acknowledgement of merchant calculations is unnecessary
	* */
	function merchant_calc ($XML) {
		global $Shopp;

		if ($XML->content('shipping') == 'false') return true;  // ack

		$sessionid = $XML->content('shopping-session');
		$Shopp->resession($sessionid);
		$Shopp->Order = ShoppingObject::__new('Order',$Shopp->Order);
		$Shopp->Order->listeners();
		$Shopping = &$Shopp->Shopping;
		$Order = &$Shopp->Order;


		$options = array();
		$google_methods = $XML->attr('method','name');
		$addresses = $XML->tag('anonymous-address');

		// Calculate all shipping methods for every potential address google returns
		// Really Google? You're just gonna send all the possible shipping addresses for that customer every time?
		while ( $shipto = $addresses->each() ) {
			$address_id = $shipto->attr(false,'id');
			$Order->Shipping->city = $shipto->content('city');
			$Order->Shipping->state = $shipto->content('region');
			$Order->Shipping->country = $shipto->content('country-code');
			$Order->Shipping->postcode = $shipto->content('postal-code');

			// Calculate shipping options
			$Shipping = new CartShipping();
			$Shipping->calculate();

			$options[$address_id] = array();
			foreach ( $Shipping->options() as $option )
				$options[$address_id][$option->name] = $option;
		}

		$_ = array('<?xml version="1.0" encoding="UTF-8"?>');
		$_[] = "<merchant-calculation-results xmlns=\"http://checkout.google.com/schema/2\">";
		$_[] = "<results>";
		foreach ( $options as $address_id => $methods ) {
			foreach ( $google_methods as $methodname ) {
				if ( isset($methods[$methodname]) ) { // new quote for this address exists
					$_[] = '<result shipping-name="'.$option->name.'" address-id="'.$address_id.'">';
					$_[] = '<shipping-rate currency="'.$this->settings['currency'].'">'.number_format($option->amount,$this->precision,'.','').'</shipping-rate>';
					$_[] = '<shippable>true</shippable>';
					$_[] = '</result>';
				} else { // google is expecting a result, but there is none for this address
					$_[] = '<result shipping-name="'.$option->name.'" address-id="'.$address_id.'">';
					$_[] = '<shippable>false</shippable>';
					$_[] = '</result>';
				}
			}
		}
		$_[] = "</results>";
		$_[] = "</merchant-calculation-results>";

		if(SHOPP_DEBUG) new ShoppError(join("\n",$_),'google-merchant-calculation-results',SHOPP_DEBUG_ERR);
		$this->response($_);
		return false; //no ack
	}

	function send ($message,$url) {
		$type = ($this->settings['testmode'] == "on")?'test':'live';
		$url = sprintf($url[$type],$this->settings['id'],$this->settings['key'],$this->settings['id']);
		$response = parent::send($message,$url);
		return new xmlQuery($response);
	}

	function error () { // Error response
		header('HTTP/1.1 500 Internal Server Error');
		die("<h1>500 Internal Server Error</h1>");
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
				),shoppurl(false,'checkout',true));
			$_POST['settings']['GoogleCheckout']['apiurl'] = $url;
		}
	}

} // END class GoogleCheckout

?>