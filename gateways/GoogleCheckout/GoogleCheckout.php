<?php
/**
 * Google Checkout
 * @class GoogleCheckout
 *
 * @author Jonathan Davis, John Dillick
 * @version 1.2
 * @copyright Ingenesis Limited, 2008-2012
 * @package Shopp
 * @since 1.2
 * @subpackage GoogleCheckout
 *
 * $Id$
 **/

class GoogleCheckout extends GatewayFramework implements GatewayModule {

	// Gateway parameters
	var $secure = true; // SSL required
	var $refunds = true; // refunds supported
	var $captures = true; // merchant initiated capture supported
	var $recurring = true; // recurring payments supported
	var $authonly = true;

	var $xml = true;

	var $debug = false;
	var $dev = false;

	function __construct () {
		parent::__construct();

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
			'live' => (is_ssl()?'https':'http').'://checkout.google.com/buttons/checkout.gif',
			'test' => (is_ssl()?'https':'http').'://sandbox.google.com/checkout/buttons/checkout.gif'
			);

		$this->setup('id','key','apiurl','use_google_taxes','use_google_shipping');

		if ( $this->dev ) $this->settings['apiurl'] = str_replace('https://', 'http://', $this->settings['apiurl']);
		$this->merchant_calc_url = $this->settings['apiurl'];

		$this->settings['merchant_email'] = shopp_setting('merchant_email');
		$this->settings['location'] = "en_US";
		$base = shopp_setting('base_operations');
		if ($base['country'] == "GB") $this->settings['location'] = "en_UK";

		$this->settings['base_operations'] = shopp_setting('base_operations');
		$this->settings['currency'] = $this->settings['base_operations']['currency']['code'];
		if (empty($this->settings['currency'])) $this->settings['currency'] = "USD";

		$this->settings['taxes'] = shopp_setting('taxrates');

		add_action('shopp_save_payment_settings',array($this,'apiurl'));
		add_action('shopp_txn_update',array($this,'notifications'));
		add_filter('shopp_checkout_submit_button',array($this,'submit'),10,3);
		add_action('get_header',array($this,'analytics'));
		add_filter('shopp_themeapi_cart_google',array($this,'cartcheckout'));
		add_action('parse_request',array($this,'intercept_cartcheckout'));

		// add charge method as capture event as well as authed event handler
		add_action('shopp_googlecheckout_capture', array($this,'charge'));
		add_action('shopp_googlecheckout_authed', array($this,'charge'));

		// add refund event handler
		add_action('shopp_googlecheckout_refund', array($this, 'refund'));

		// add void event handler
		add_action('shopp_googlecheckout_void', array($this, 'cancelorder'));
	}

	function analytics() {  do_action('shopp_google_checkout_analytics'); }

	function actions () {
		add_action('shopp_init_checkout',array(&$this,'init'));
		add_action('shopp_process_order',array(&$this, 'process'));
		add_filter('shopp_ordering_no_shipping_costs',array(&$this, 'hasshipping_filter'));
	}

	function init () {
		if (count($this->Order->payoptions) == 1) add_filter('shopp_shipping_hasestimates',array(&$this, 'hasestimates_filter'));
	}


	function hasestimates_filter () { return false; }

	function hasshipping_filter ( $valid ) { return str_true($this->settings['use_google_shipping']) || $valid;	}

	function submit ($tag=false,$options=array(),$attrs=array()) {
		$type = "live";
		if ( str_true($this->settings['testmode']) ) $type = "test";
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
		if ( $this->debug ) return;

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
				Shopping::resession();
				shopp_redirect($redirect);
			}
		}

		return false;
	}

	function notifications () {
		// not a GC notification, fail silently
		if ( 'gc' != $_REQUEST['_txnupdate'] ) return;

		if ($this->authentication()) {

			// Read incoming request data
			$data = trim(file_get_contents('php://input'));
			shopp_debug($data);

			// Handle notifications
			$XML = new xmlQuery($data);
			$type = $XML->context();
			shopp_debug("google checkout notification type $type");
			if ( $type === false ) {
				shopp_debug('Unable to determine context of request.');
				return;
			}
			$serial = $XML->attr($type,'serial-number');

			$ack = true;
			switch($type) {
				case "new-order-notification": $this->order($XML); break;
				case "risk-information-notification": $this->risk($XML); break;
				case "order-state-change-notification": $this->state($XML); break;
				case "merchant-calculation-callback": $ack = $this->merchant_calc($XML); break;
				case "charge-amount-notification":	$this->charged($XML); break;
				case "refund-amount-notification":	$this->refunded($XML); break;
				case "chargeback-amount-notification":	$this->chargebacked($XML);	break;
				case "authorization-amount-notification":	$this->authed($XML); break;
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
		return false;
	}

	/**
	 * acknowledge()
	 * Sends an acknowledgement message back to Google to confirm the notification
	 * was received and processed */
	function acknowledge ($serial) {
		shopp_debug("Sending ack to google on serial $serial.");
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
		$Cart = $this->Order->Cart;

		$_ = array('<?xml version="1.0" encoding="UTF-8"?>');
		$_[] = '<checkout-shopping-cart xmlns="'.$this->urls['schema'].'">';

			// Build the cart
			$_[] = '<shopping-cart>';
				$_[] = '<items>';
				foreach($Cart->contents as $i => $Item) {
					$_[] = '<item>';
					$_[] = '<item-name><![CDATA['.htmlspecialchars($Item->name).htmlspecialchars((!empty($Item->option->label))?' ('.$Item->option->label.')':'').']]></item-name>';
					$_[] = '<item-description><![CDATA['.htmlspecialchars($Item->description).']]></item-description>';
					if ($Item->type == 'Download') $_[] = '<digital-content><description><![CDATA['.
						apply_filters('shopp_googlecheckout_download_instructions', __('You will receive an email with download instructions upon receipt of payment.','Shopp')).
						']]></description>'.
						apply_filters('shopp_googlecheckout_download_delivery_markup', '<email-delivery>true</email-delivery>').
						'</digital-content>';

					// Shipped Item
					if ( 'Shipped' == $Item->type ) $_[] = '<item-weight unit="LB" value="'.($Item->weight > 0 ? number_format(convert_unit($Item->weight,'lb'),2,'.','') : 0).'" />';

					if ( 'Subscription' == $Item->type ) {
						shopp_debug("Item $i: "._object_r($Item));
						$trial_item = array();
						$recurring = $Item->option->recurring;

						// set the closest period
						$period = $this->map_sub_period( $recurring['interval'], $recurring['period'] );
						$period = ' period="'.$period.'"';

						// determine subscription start date
						$sub_start_date = '';
						if ( str_true($recurring['trial']) ) {
							$start = $this->calc_sub_date($recurring['trialint'], $recurring['trialperiod']);
							if ( $start ) $sub_start_date = ' start-date="'.$start.'"';
							$from = $this->calc_sub_date($recurring['trialint'], $recurring['trialperiod'], false);
						}

						$no_charge_after = '';
						if ( $recurring['cycles'] ) {
							$end = $this->calc_sub_date((int) $recurring['cycles'] * $recurring['interval'], $recurring['period'], true, $from);
							$no_charge_after = ' no-charge-after="'.$end.'"';
						}

						$_[] = '<subscription type="google"'.$period.$sub_start_date.$no_charge_after.' >';
							$_[] = '<payments>';
								$cycles = '';
								if ( $recurring['cycles'] ) $cycles = ' times="'.$recurring['cycles'].'"';
								$_[] = '<subscription-payment'.$cycles.'>';
									$_[] = '<maximum-charge currency="'.$this->settings['currency'].'">'.number_format($Item->total, $this->precision,'.','').'</maximum-charge>';
								$_[] = '</subscription-payment>';
							$_[] = '</payments>';
							if ( str_true($recurring['trial']) ) {
								$trial_labels = array(
									'd'=> sprintf(_n("Trial for %d day.","Trial for %d days.", $recurring['trialint'], 'Shopp'), $recurring['trialint']),
									'w'=> sprintf(_n("Trial for %d week.","Trial for %d weeks.", $recurring['trialint'], 'Shopp'), $recurring['trialint']),
									'm'=> sprintf(_n("Trial for %d month.","Trial for %d months.", $recurring['trialint'], 'Shopp'), $recurring['trialint']),
									'y'=> sprintf(_n("Trial for %d year.","Trial for %d years.", $recurring['trialint'], 'Shopp'), $recurring['trialint'])
								);

								// create a trial item for immediate charge
								$trial_item[] = '<item>';
								$trial_item[] = '<item-name><![CDATA['.htmlspecialchars($Item->name.' ('.$trial_labels[$recurring['trialperiod']].')').htmlspecialchars((!empty($Item->option->label))?' ('.$Item->option->label.')':'').']]></item-name>';
								$trial_item[] = '<item-description><![CDATA['.htmlspecialchars($Item->description.' '.$trial_labels[$recurring['trialperiod']]).']]></item-description>';
								$trial_item[] = '<unit-price currency="'.$this->settings['currency'].'">'.($recurring['trialprice'] ? number_format($recurring['trialprice'],$this->precision,'.','') : 0).'</unit-price>';
								$trial_item[] = '<quantity>'.$Item->quantity.'</quantity>';
								$trial_item[] = '</item>';
							}

							$_[] = '<recurrent-item>';
							$_[] = '<item-name><![CDATA['.htmlspecialchars($Item->name).htmlspecialchars((!empty($Item->option->label))?' ('.$Item->option->label.')':'').']]></item-name>';
							$_[] = '<item-description><![CDATA['.htmlspecialchars($Item->description).']]></item-description>';
							$_[] = '<unit-price currency="'.$this->settings['currency'].'">'.number_format($Item->unitprice,$this->precision,'.','').'</unit-price>';
							$_[] = '<quantity>'.$Item->quantity.'</quantity>';

							// Build recurrent item, so we can track our subscription
							$_[] = '<merchant-private-item-data>';
								$_[] = '<shopp-product-id>'.$Item->product.'</shopp-product-id>';
								$_[] = '<shopp-price-id>'.$Item->option->id.'</shopp-price-id>';
								if (is_array($Item->data) && count($Item->data) > 0) {
									$_[] = '<shopp-item-data-list>';
									foreach ($Item->data AS $name => $data) {
										$_[] = '<shopp-item-data name="'.esc_attr($name).'"><![CDATA['.esc_attr($data).']]></shopp-item-data>';
									}
									$_[] = '</shopp-item-data-list>';
								}
								$_[] = '<shopping-session><![CDATA['.$this->session.']]></shopping-session>';
								$_[] = '<shopping-cart-agent><![CDATA['.SHOPP_GATEWAY_USERAGENT.']]></shopping-cart-agent>';
								$_[] = '<customer-ip>'.$_SERVER['REMOTE_ADDR'].'</customer-ip>';
							$_[] = '</merchant-private-item-data>';
							$_[] = '</recurrent-item>';
						$_[] = '</subscription>';
					}
					if ( 'Subscription' == $Item->type && $sub_start_date )
						$_[] = '<unit-price currency="'.$this->settings['currency'].'">0</unit-price>';
					else
						$_[] = '<unit-price currency="'.$this->settings['currency'].'">'.number_format($Item->unitprice,$this->precision,'.','').'</unit-price>';

					$_[] = '<quantity>'.$Item->quantity.'</quantity>';
					if (!empty($Item->sku)) $_[] = '<merchant-item-id>'.$Item->sku.'</merchant-item-id>';
					$_[] = '<merchant-private-item-data>';
						$_[] = '<shopp-product-id>'.$Item->product.'</shopp-product-id>';
						$_[] = '<shopp-price-id>'.$Item->option->id.'</shopp-price-id>';
						if (is_array($Item->data) && count($Item->data) > 0) {
							$_[] = '<shopp-item-data-list>';
							foreach ($Item->data AS $name => $data) {
								$_[] = '<shopp-item-data name="'.esc_attr($name).'"><![CDATA['.esc_attr($data).']]></shopp-item-data>';
							}
							$_[] = '</shopp-item-data-list>';
						}
					$_[] = '</merchant-private-item-data>';

					if ( ! str_true($this->settings['use_google_taxes']) && is_array($this->settings['taxes']) ) { // handle tax free or per item tax
						if ($Item->istaxed === false)
							$_[] = '<tax-table-selector>non-taxable</tax-table-selector>';
						elseif ($item_tax_table_selector = apply_filters('shopp_google_item_tax_table_selector', false, $Item) !== false)
							$_[] = $item_tax_table_selector;
					}

					$_[] = '</item>';
					if ( isset($trial_item) && ! empty($trial_item) ) $_ = array_merge($_, $trial_item);
				}

				// Include any discounts
				if ($Cart->Totals->discount > 0) {
					foreach($Cart->discounts as $promo) $discounts[] = $promo->name;
					$_[] = '<item>';
						$_[] = '<item-name>Discounts</item-name>';
						$_[] = '<item-description><![CDATA['.join(", ",$discounts).']]></item-description>';
						$_[] = '<unit-price currency="'.$this->settings['currency'].'">'.number_format($Cart->Totals->discount*-1,$this->precision,'.','').'</unit-price>';
						$_[] = '<quantity>1</quantity>';
						$_[] = '<item-weight unit="LB" value="0" />';
					$_[] = '</item>';
				}
				$_[] = '</items>';

				// Include notification that the order originated from Shopp
				$_[] = '<merchant-private-data>';
					$_[] = '<shopping-session><![CDATA['.$this->session.']]></shopping-session>';
					$_[] = '<shopping-cart-agent><![CDATA['.SHOPP_GATEWAY_USERAGENT.']]></shopping-cart-agent>';
					$_[] = '<customer-ip>'.$_SERVER['REMOTE_ADDR'].'</customer-ip>';

					if (is_array($this->Order->data) && count($this->Order->data) > 0) {
						$_[] = '<shopp-order-data-list>';
						foreach ($this->Order->data AS $name => $data) {
							$_[] = '<shopp-order-data name="'.esc_attr($name).'"><![CDATA['.esc_attr($data).']]></shopp-order-data>';
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
				$_[] = '<merchant-calculations-url><![CDATA['.$this->merchant_calc_url.']]></merchant-calculations-url>';
				$_[] = '</merchant-calculations>';

				if ( ! str_true($this->settings['use_google_shipping']) && $Cart->shipped() ) {
					if ($Cart->freeshipping === true) { // handle free shipping case and ignore all shipping methods
						$free_shipping_text = shopp_setting('free_shipping_text');
						if (empty($free_shipping_text)) $free_shipping_text = __('Free Shipping!','Shopp');
						$_[] = '<shipping-methods>';
						$_[] = '<flat-rate-shipping name="'.esc_attr($free_shipping_text).'">';
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

				if ( ! str_true($this->settings['use_google_taxes']) && is_array($this->settings['taxes']) ) {
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
									$_[] = '<shipping-taxed>'.( shopp_setting_enabled('tax_shipping') ? 'true' : 'false' ).'</shipping-taxed>';
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

				if (isset($_POST['analyticsdata'])) $_[] = '<analytics-data><![CDATA['.$_POST['analyticsdata'].']]></analytics-data>';
				$_[] = '</merchant-checkout-flow-support>';
			$_[] = '</checkout-flow-support>';


		$_[] = '</checkout-shopping-cart>';
		$request = join("\n", apply_filters('shopp_googlecheckout_build_request', $_));

		shopp_debug($request);
		return $request;
	}


	/**
	 * order()
	 * Handles new order notifications from Google */
	function order ( $XML ) {
		if ( ! $XML || ! is_a($XML, 'xmlQuery') ) {
			new ShoppError("No transaction data was provided by Google Checkout.",'google_missing_txn_data',SHOPP_DEBUG_ERR);
			$this->error();
		}

		$order_summary = $XML->tag('order-summary');
		$txnid = $order_summary->content('google-order-number');

		$cart = $order_summary->tag('shopping-cart');
		$orderdata = $cart->tag('merchant-private-data');

		// no merchant private data on a new order notification means the order didn't originate from a Shopp session, might be Google generated recurring
		if ( ! $orderdata ) {
			// check for item data, for signs of recurring payment
			$items = $cart->tag('items');
			$price = false;
			$sessionid = false;
			while ( $item = $items->each() ) {
				$itemdata = $item->tag('merchant-private-item-data');
				if ( ! $itemdata ) continue;
				$price = $itemdata->content('shopp-price-id');
				$sessionid = $itemdata->content('shopping-session');
				break;
			}
			if ( ! ( $price && $sessionid ) ) {
				new ShoppError("Insufficient transaction data was provided by Google Checkout.",'google_missing_txn_data',SHOPP_DEBUG_ERR);
				$this->error();
			}

			// if for a non-Shopp originating order, we find session id and subscription item in the private item data, look up the original order number by metadata
			$Price = new Price($price);
			if ( 'Subscription' == $Price->type ) $recur = $this->is_recurring( $sessionid );
		}

		// ordinary session lookup possible
		if ( ! isset($recur) ) {
			// get session id
			$sessionid = $orderdata->content('shopping-session');

			// load the desired session, which leaves the previous/defunct Order object intact
			Shopping::resession($sessionid);

			// destroy the defunct Order object from defunct session and restore the Order object from the loaded session
			// also assign the restored Order object as the global Order object
			$this->Order = ShoppOrder( ShoppingObject::__new('Order', ShoppOrder()) );
			$Cart = $this->Order->Cart;
			$Customer = $this->Order->Customer;
			$Billing = $this->Order->Billing;
			$Shipping = $this->Order->Shipping;
			$Shopping = ShoppShopping();

			// remove undesired Order object events
			remove_action('shopp_purchase_order_created',array(ShoppOrder(),'invoice'));
			remove_action('shopp_purchase_order_created',array(ShoppOrder(),'process'));

			// give our order number to google after purchase creation
			add_action('shopp_purchase_order_created', array($this, 'add_order'));

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

			$Customer->firstname = $name->content('first-name');
			$Customer->lastname = $name->content('last-name');
			if (empty($name)) {
				$name = $buyer->content('contact-name');
				$names = explode(" ",$name);
				$Customer->firstname = $names[0];
				$Customer->lastname = $names[count($names)-1];
			}

			$email = $buyer->content('email');
			$Customer->email = !empty($email) ? $email : '';
			$phone = $buyer->content('phone');
			$Customer->phone = !empty($phone) ? $phone : '';

			$Customer->marketing = $order_summary->content('buyer-marketing-preferences > email-allowed') != 'false' ? 'yes' : 'no';
			$Billing->address = $buyer->content('address1');
			$Billing->xaddress = $buyer->content('address2');
			$Billing->city = $buyer->content('city');
			$Billing->state = $buyer->content('region');
			$Billing->country = $buyer->content('country-code');
			$Billing->postcode = $buyer->content('postal-code');
			$Billing->cardtype = "GoogleCheckout";

			$shipto = $order_summary->tag('buyer-shipping-address');
			$Shipping->address = $shipto->content('address1');
			$Shipping->xaddress = $shipto->content('address2');
			$Shipping->city = $shipto->content('city');
			$Shipping->state = $shipto->content('region');
			$Shipping->country = $shipto->content('country-code');
			$Shipping->postcode = $shipto->content('postal-code');

			$this->Order->gateway = $this->name;

			// Google Adjustments
			$order_adjustment = $order_summary->tag('order-adjustment');
			$Shipping->method = $order_adjustment->content('shipping-name') ? $order_adjustment->content('shipping-name') : $Shipping->method;
			$Cart->Totals->shipping = $order_adjustment->content('shipping-cost');
			$Cart->Totals->tax = $order_adjustment->content('total-tax');

			// New total from order summary
			$Cart->Totals->total = $order_summary->content('order-total');

			shopp_add_order_event(false, 'purchase', array(
				'gateway' => $this->module,
				'txnid' => $txnid
			));

			// load the newly created purchase
			$Purchase = new Purchase($txnid, 'txnid');
			if ( ! $Purchase->id ) {
				new ShoppError("Unable to attach sessionid meta data to new order.",'google_session_load_failure',SHOPP_DEBUG_ERR);
				return;
			}

			// Attach a meta record to the new order, so we can look up recurring payments by meta data
			shopp_set_meta ( $Purchase->id, 'purchase', 'sessionid', $sessionid, 'sessionid');
			return;

		} // end of ordinary new order handling

		// handle recurring payment
		if ( isset($recur) && $recur ) {

			// get the original order
			$Purchase = new Purchase($recur);
			$Purchase->txnid = $txnid; // not committing this id to the original purchase record
			$this->add_order($Purchase); // tell Google to associate this recurring payment with original order number

			shopp_add_order_event($Purchase->id, 'review', array(
				'kind' => 'recurring',	// Fraud review trigger type: AVS (address verification system), CVN (card verification number), FRT (fraud review team)
				'note' => sprintf(__('New recurring payment received with id %s.','Shopp'), $txnid)
			));

			return;
		}

		// something slipped through the cracks here
		new ShoppError("No transaction data was provided by Google Checkout.",'google_missing_txn_data',SHOPP_DEBUG_ERR);
		$this->error();
	}

	/**
	 * is_recurring
	 *
	 * returns the original Shopp order id associated with the shopping session id
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @param string $sessionid the sessionid from the original shopping session
	 * @return int|bool false if no purchase meta record exists associated with the session id, purchase id on successful recurring lookup
	 **/
	function is_recurring ( $sessionid ) {
		shopp_debug("Possible recurring payment for $sessionid");

		// try to find order number for original order
		$Meta = new MetaObject();
		$loading = array( 'context' => 'purchase', 'type' => 'sessionid', 'name' => 'sessionid', 'value' => $sessionid );
		$Meta->load( $loading );

		if ( ! $Meta->id || ! $Meta->parent ) {
			new ShoppError("Unable to lookup recurring order id from $sessionid",'google_missing_txn_data',SHOPP_DEBUG_ERR);
			return false;
		}

		// load the parent purchase
		$Purchase = new Purchase( $Meta->parent );
		return ( isset($Purchase->id) && $Purchase->id ? $Purchase->id : false );
	}

	function add_order ( $Purchase ) {
		if ( ! isset($Purchase->txnid) || $this->debug ) return;

		$_ = array('<?xml version="1.0" encoding="UTF-8"?>'."\n");
		$_[] = '<add-merchant-order-number xmlns="http://checkout.google.com/schema/2" google-order-number="'.$Purchase->txnid.'">';
		$_[] = '<merchant-order-number>'.$Purchase->id.'</merchant-order-number>';
		$_[] = '</add-merchant-order-number>';

		$Response = $this->send(join("\n",$_), $this->urls['order']);
		if ($Response->tag('error')) {
			new ShoppError($Response->content('error-message'),'google_checkout_error',SHOPP_TRXN_ERR);
			return;
		}
	}

	function risk ( $XML ) {
		if ( ! $XML || ! is_a($XML, 'xmlQuery') ) {
			new ShoppError("No transaction data was provided by Google Checkout.",'google_missing_txn_data',SHOPP_DEBUG_ERR);
			$this->error();
		}

		$summary = $XML->tag('order-summary');
		$txnid = $summary->content('google-order-number');
		$order = $summary->content('merchant-order-number');
		$risk = $summary->tag('risk-information');
		$avs = $risk->content('avs-response');
		$cvn = $risk->content('cvn-response');
		$eligible = $risk->content('eligible-for-protection');

		shopp_debug("avs-response on order $order: $avs");
		shopp_debug("cvn-response on order $order: $cvn");
		shopp_debug("eligible-for-protection on order $order: $eligible");

		if ( ! $order && ! $txnid ) {
			new ShoppError("No transaction data was provided by Google Checkout.",'google_missing_txn_data',SHOPP_DEBUG_ERR);
			$this->error();
		}

		// always try the attached order id first, as the txnid may not be associated with the original order
		$Purchase = new Purchase($order);
		if ( ! $Purchase->id ) $Purchase = new Purchase($txnid, 'txnid');
		if ( ! $Purchase->id ) {
			new ShoppError("Transaction update on non existing order $order or non-associated transaction id $txnid.",'google_order_state_missing_order',SHOPP_DEBUG_ERR);
			$this->error();
		}

		if ( ! $Purchase->ip ) {
			$Purchase->ip = $XML->content('ip-address');
			if ( $Purchase->ip ) $Purchase->save();
		}

		shopp_add_order_event($Purchase->id, 'review', array(
			'kind' => 'AVS',	// Fraud review trigger type: AVS (address verification system), CVN (card verification number), FRT (fraud review team)
			'note' => ('Y' == $avs ? __('Address verification service approved.', 'Shopp') : __('Address verification service denied.', 'Shopp'))
		));

		shopp_add_order_event($Purchase->id, 'review', array(
			'kind' => 'CVN',	// Fraud review trigger type: AVS (address verification system), CVN (card verification number), FRT (fraud review team)
			'note' => ('M' == $cvn ? __('Credit verification match.', 'Shopp') : __('Credit verification failed.', 'Shopp'))
		));

		shopp_add_order_event($Purchase->id, 'review', array(
			'kind' => 'protection_eligible',	// Fraud review trigger type: AVS (address verification system), CVN (card verification number), FRT (fraud review team)
			'note' => ('true' == $eligible ? __('Eligible for Google protection.', 'Shopp') : __('Not eligible for Google protection.', 'Shopp'))
		));
	}

	/**
	 * state
	 *
	 * Handle all Google Checkout
	 *
	 * @author John Dillick, Jonathan Davis
	 * @since 1.1
	 *
	 * @return void Description...
	 **/
	function state ( $XML ) {
		if ( ! $XML || ! is_a($XML, 'xmlQuery') ) {
			new ShoppError("No transaction data was provided by Google Checkout.",'google_missing_txn_data',SHOPP_DEBUG_ERR);
			$this->error();
		}

		$summary = $XML->tag('order-summary');
 		$id = $summary->content('google-order-number');

		if (empty($id)) {
			new ShoppError("No transaction ID was provided with an order state change message sent by Google Checkout",'google_state_notification_error',SHOPP_DEBUG_ERR);
			$this->error();
		}

		$state = $XML->content('new-financial-order-state');

		$Purchase = new Purchase($id, 'txnid');
		if ( empty($Purchase->id) ) {
			new ShoppError('Transaction update on non existing order.','google_order_state_missing_order',SHOPP_DEBUG_ERR);
			return;
		}

		switch ( $state ) {
			case 'PAYMENT_DECLINED':
				shopp_add_order_event($Purchase->id, 'auth-fail', array(
					'amount' => $Purchase->total,	// Amount to be authorized
					'gateway' => $this->module,		// Gateway handler name (module name from @subpackage)
				));
				break;
			case 'CANCELLED':
			case 'CANCELLED_BY_GOOGLE':
				shopp_add_order_event($Purchase->id, 'voided', array(
					'txnid' => $id,						// Transaction ID
					'txnorigin' => $id,					// Original Transaction ID
					'gateway' => $this->module			// Gateway handler name (module name from @subpackage)
				));
				break;

			// do nothing...
			case 'CHARGEABLE': // moved to authed()
			case 'CHARGED': // moved to charged()
			case 'REVIEWING':
			case 'CHARGING':
				break;
		}
	}

	function authed ( $XML ) {
		if ( ! $XML || ! is_a($XML, 'xmlQuery') ) {
			new ShoppError("No transaction data was provided by Google Checkout.",'google_missing_txn_data',SHOPP_DEBUG_ERR);
			$this->error();
		}

		$sessionid = $XML->content('shopping-session');
		add_filter('shopp_agent_is_robot', create_function('$isrobot','return false;'));
		Shopping::resession($sessionid);
		$this->Order = ShoppOrder(ShoppingObject::__new('Order',ShoppOrder()));
		$Shopping = ShoppShopping();

		$amount = $XML->content('authorization-amount:first');
		$card = $XML->content('partial-cc-number');

		$summary = $XML->tag('order-summary');
		$txnid = $summary->content('google-order-number');
		$order = $summary->content('merchant-order-number');

		if ( ! $order && ! $txnid ) {
			new ShoppError("No transaction data was provided by Google Checkout.",'google_missing_txn_data',SHOPP_DEBUG_ERR);
			$this->error();
		}

		// always try the attached order id first, as the txnid may not be associated with the original order
		$Purchase = ShoppPurchase(shopp_order($order));
		if ( ! $Purchase->id ) $Purchase = ShoppPurchase(shopp_order($txnid, 'trans'));
		if ( ! $Purchase->id ) {
			new ShoppError("Transaction update on non existing order $order or non-associated transaction id $txnid.",'google_order_state_missing_order',SHOPP_DEBUG_ERR);
			$this->error();
		}
		// Re-establish Purchase object listeners
		$Purchase->listeners();

		$total = $summary->content('order-total');

		// These callbacks are usually established by the Order::auth() or Order::sale() methods, as determined by Order::process()
		// Instead, in Google Checkout, order Order::process() is disabled because synchronous auth/sale action is not possible.
		// Capture is always deferred (at least) until we have received an asynchronous authed notification.
		add_action('shopp_authed_order_event', array($this->Order,'accounts')); // account creation
		add_action('shopp_authed_order_event', array($this->Order,'notify')); // order email notification
		add_action('shopp_authed_order_event', array($this,'success')); // override Order::success


		shopp_add_order_event($Purchase->id, 'authed', array(
			'txnid' => $txnid,				// Transaction ID
			'amount' => $amount,			// Gross amount authorized
			'gateway' => $this->module,		// Gateway handler name (module name from @subpackage)
			'paymethod' => '',				// Payment method (payment method label from payment settings)
			'paytype' => '',				// Type of payment (check, MasterCard, etc)
			'payid' => $card				// Payment ID (last 4 of card or check number)
		));
	}

	/**
	 * Overrides of Order::success()
	 *
	 * @author John Dillick
	 * @since 1.2.2
	 *
	 * @param AuthedOrderEvent $e the authed order event
	 * @return void no action
	 **/
	function success ( AuthedOrderEvent $e ) {
		$this->Order->purchase = $this->Order->inprogress;
		$this->Order->inprogress = false;
		do_action('shopp_order_success',ShoppPurchase());
		Shopping::resession();
	}

	/**
	 * charge
	 *
	 * charge order event handler
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @param OrderEventMessage $Event the CaptureOrderEvent event triggering the charge order event, or auto charge on the AuthedOrderEvent event.
	 * @return void Description...
	 **/
	function charge ( OrderEventMessage $Event ) {
		// Invoice on the authed event
		if ( is_a($Event, 'AuthedOrderEvent') ) {
			$id = $Event->txnid;
			$Purchase = new Purchase($id, 'txnid');

			shopp_add_order_event($Purchase->id, 'invoiced', array(
				'gateway' => $this->module,		// Gateway handler name (module name from @subpackage)
				'amount' => $Event->amount	// Capture of entire order amount
			));
		}

		if ( is_a($Event, 'AuthedOrderEvent')  && str_true($this->settings['autocharge']) || is_a($Event, 'CaptureOrderEvent')  ) {
			if ( $this->debug ) return;

			$id = $Event->txnid;
			$amount = $Event->amount;

			$_ = array('<?xml version="1.0" encoding="UTF-8"?>'."\n");
			$_[] = '<charge-order xmlns="'.$this->urls['schema'].'" google-order-number="'.$id.'">';
			$_[] = '<amount currency="'.$this->settings['currency'].'">'.number_format($amount, $this->precision,'.','').'</amount>';
			$_[] = '</charge-order>';

			$Response = $this->send(join("\n",$_), $this->urls['order']);
			if ($Response->tag('error')) {
				new ShoppError($Response->content('error-message'),'google_checkout_error',SHOPP_TRXN_ERR);
				return;
			}
		}
	}

	function charged ( $XML ) {
		if ( ! $XML || ! is_a($XML, 'xmlQuery') ) {
			new ShoppError("No transaction data was provided by Google Checkout.",'google_missing_txn_data',SHOPP_DEBUG_ERR);
			$this->error();
		}

		$charge_fee = $XML->tag('latest-charge-fee');
		$amount = $XML->content('latest-charge-amount:first');
		$fee = $charge_fee->content('total');

		$summary = $XML->tag('order-summary');
		$txnid = $summary->content('google-order-number');
		$order = $summary->content('merchant-order-number');

		if ( ! $order && ! $txnid ) {
			new ShoppError("No transaction data was provided by Google Checkout.",'google_missing_txn_data',SHOPP_DEBUG_ERR);
			$this->error();
		}

		// always try the attached order id first, as the txnid may not be associated with the original order
		$Purchase = new Purchase($order);
		if ( ! $Purchase->id ) $Purchase = new Purchase($txnid, 'txnid');
		if ( ! $Purchase->id ) {
			new ShoppError("Transaction update on non existing order $order or non-associated transaction id $txnid.",'google_order_state_missing_order',SHOPP_DEBUG_ERR);
			$this->error();
		}

		shopp_add_order_event($Purchase->id, 'captured', array(
			'txnid' => $txnid,				// Transaction ID of the CAPTURE event
			'amount' => $amount,			// Amount captured
			'fees' => $fee ? $fee : 0.0,	// Transaction fees taken by the gateway net revenue = amount-fees
			'gateway' => $this->module		// Gateway handler name (module name from @subpackage)
		));
	}

	/**
	 * refund
	 *
	 * Handles the refund order event
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @param RefundOrderEvent $Event the refund order event
	 * @return void
	 **/
	function refund ( RefundOrderEvent $Event ) {
		if ( $this->debug ) return;

		$_ = array('<?xml version="1.0" encoding="UTF-8"?>'."\n");
		$_[] = '<refund-order xmlns="'.$this->urls['schema'].'" google-order-number="'.$Event->txnid.'">';
		$_[] = '<amount currency="'.$this->settings['currency'].'">'.number_format($Event->amount, $this->precision,'.','').'</amount>';
		$_[] = '<reason>'.$Event->reason.'</reason>';
		$_[] = '</refund-order>';

		$Response = $this->send(join("\n",$_), $this->urls['order']);
		if ($Response->tag('error')) {
			new ShoppError($Response->content('error-message'),'google_refund_error',SHOPP_TRXN_ERR);
			return;
		}
	}

	/**
	 * refunded
	 *
	 * handle the refunded order notification from Google Checkout
	 *
	 * @author John Dillick
	 * @since 1.1
	 *
	 * @param string $XML The XML request from google
	 * @return void
	 **/
	function refunded ( $XML ) {
		if ( ! $XML || ! is_a($XML, 'xmlQuery') ) {
			new ShoppError("No transaction data was provided by Google Checkout.",'google_missing_txn_data',SHOPP_DEBUG_ERR);
			$this->error();
		}


		$summary = $XML->tag('order-summary');
		$txnid = $summary->content('google-order-number');
		$order = $summary->content('merchant-order-number');
		$refund = $summary->content('total-refund-amount');

		if ( ! $order && ! $txnid ) {
			new ShoppError("No transaction data was provided by Google Checkout.",'google_missing_txn_data',SHOPP_DEBUG_ERR);
			$this->error();
		}

		// always try the attached order id first, as the txnid may not be associated with the original order
		$Purchase = new Purchase($order);
		if ( ! $Purchase->id ) $Purchase = new Purchase($txnid, 'txnid');
		if ( ! $Purchase->id ) {
			new ShoppError("Transaction update on non existing order $order or non-associated transaction id $txnid.",'google_order_state_missing_order',SHOPP_DEBUG_ERR);
			$this->error();
		}

		// Initiate shopp refunded event
		shopp_add_order_event($Purchase->id, 'refunded', array(
			'txnid' => $txnid,				// Transaction ID for the REFUND event
			'amount' => $refund,		// Amount refunded
			'gateway' => $this->module	// Gateway handler name (module name from @subpackage)
		));
	}

	/**
	 * cancelorder
	 *
	 * cancel an order, overrides the GatewayFramework cancelorder() method, which the framework fires on refunded order.
	 * the method is also called on the void order event.
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @param OrderEventMessage handles both a VoidOrderEvent|RefundedOrderEvent $Event the order event
	 * @return void
	 **/
	function cancelorder ( OrderEventMessage $Event ) {
		if ( $this->debug ) return;

		$Purchase = new Purchase($Event->order);

		// do not cancel order unless completely refunded
		if ( is_a($Event, 'RefundedOrderEvent') && $Event->amount != $Purchase->total ) return;

		// void order not allowed if the order has been captured
		if ( is_a($Event, 'VoidOrderEvent') && $Purchase->captured ) {
			new ShoppError(__('Unable to cancel an order that has been charged.','Shopp'),'google_void_error',SHOPP_TRXN_ERR);
			return;
		}

		$_ = array('<?xml version="1.0" encoding="UTF-8"?>'."\n");
		$_[] = '<cancel-order xmlns="'.$this->urls['schema'].'" google-order-number="'.$Event->txnid.'">';
		$_[] = '<reason>'.(isset($Event->reason) && $Event->reason ? $Event->reason : __('Order canceled', 'Shopp')).'</reason>';
		$_[] = '</cancel-order>';

		$Response = $this->send(join("\n",$_), $this->urls['order']);
		if ($Response->tag('error')) {
			new ShoppError($Response->content('error-message'),'google_void_error',SHOPP_TRXN_ERR);
		}
	}


	function chargebacked ( $XML ) {
		if ( ! $XML || ! is_a($XML, 'xmlQuery') ) {
			new ShoppError("No transaction data was provided by Google Checkout.",'google_missing_txn_data',SHOPP_DEBUG_ERR);
			$this->error();
		}


		$summary = $XML->tag('order-summary');
		$txnid = $summary->content('google-order-number');
		$order = $summary->content('merchant-order-number');
		$refund = $summary->content('total-chargeback-amount');

		if ( ! $order && ! $txnid ) {
			new ShoppError("No transaction data was provided by Google Checkout.",'google_missing_txn_data',SHOPP_DEBUG_ERR);
			$this->error();
		}

		// always try the attached order id first, as the txnid may not be associated with the original order
		$Purchase = new Purchase($order);
		if ( ! $Purchase->id ) $Purchase = new Purchase($txnid, 'txnid');
		if ( ! $Purchase->id ) {
			new ShoppError("Transaction update on non existing order $order or non-associated transaction id $txnid.",'google_order_state_missing_order',SHOPP_DEBUG_ERR);
			$this->error();
		}

		// Initiate shopp refunded event
		shopp_add_order_event($Purchase->id, 'refunded', array(
			'txnid' => $id,				// Transaction ID for the REFUND event
			'amount' => $refund,		// Amount refunded
			'gateway' => $this->module	// Gateway handler name (module name from @subpackage)
		));

		shopp_add_order_event($Purchase->id, 'review', array(
			'kind' => 'chargeback',	// Fraud review trigger type: AVS (address verification system), CVN (card verification number), FRT (fraud review team)
			'note' => __('Google Checkout issued a chargeback on this order.', 'Shopp')
		));
	}


	/**
	* merchant_calc()
	* Callback function for merchant calculated shipping and taxes
	* taxes calculations unimplemented
	* returns false when it responds, as acknowledgement of merchant calculations is unnecessary
	* */
	function merchant_calc ( $XML ) {
		if ( ! $XML || ! is_a($XML, 'xmlQuery') ) {
			new ShoppError("No transaction data was provided by Google Checkout.",'google_missing_txn_data',SHOPP_DEBUG_ERR);
			$this->error();
		}

		if ($XML->content('shipping') == 'false') return true;  // ack

		$sessionid = $XML->content('shopping-session');
		add_filter('shopp_agent_is_robot', create_function('$isrobot','return false;'));
		Shopping::resession($sessionid);
		$this->Order = ShoppOrder(ShoppingObject::__new('Order',ShoppOrder()));
		$Shopping = ShoppShopping();

		$options = array();
		$google_methods = $XML->attr('method','name');
		$addresses = $XML->tag('anonymous-address');

		// Calculate shipping options
		$Shipping = new CartShipping();
		$previous_options = $Shipping->options();
		shopp_debug("previous_options: "._object_r($previous_options));
		shopp_debug("google_methods: "._object_r($google_methods));

		// Calculate all shipping methods for every potential address google returns
		// Really Google? You're just gonna send all the possible shipping addresses for that customer every time?
		while ( $shipto = $addresses->each() ) {
			$address_id = $shipto->attr(false,'id');
			$Shipping->city = $shipto->content('city');
			$Shipping->state = $shipto->content('region');
			$Shipping->country = $shipto->content('country-code');
			$Shipping->postcode = $shipto->content('postal-code');

			$Shipping->calculate();
			$current_options = $Shipping->options;
			shopp_debug("current_options: "._object_r($current_options));

			$options[$address_id] = array();
			foreach ( $current_options as $option )
				$options[$address_id][$option->name] = $option;

			$unavailable = array();
			foreach ( $google_methods as $expected ) {
				if ( ! in_array($expected, array_keys($options[$address_id])) ) $unavailable[] = $expected;
			}
		}

		$_ = array('<?xml version="1.0" encoding="UTF-8"?>');
		$_[] = "<merchant-calculation-results xmlns=\"http://checkout.google.com/schema/2\">";
		$_[] = "<results>";
		foreach ( $options as $address_id => $methods ) {
			foreach ( $methods as $option ) {
				$_[] = '<result shipping-name="'.$option->name.'" address-id="'.$address_id.'">';
				$_[] = '<shipping-rate currency="'.$this->settings['currency'].'">'.number_format($option->amount,$this->precision,'.','').'</shipping-rate>';
				$_[] = '<shippable>true</shippable>';
				$_[] = '</result>';
			}
			foreach ( $unavailable as $name ) {
				$_[] = '<result shipping-name="'.$name.'" address-id="'.$address_id.'">';
				$_[] = '<shippable>false</shippable>';
				$_[] = '</result>';
			}
		}
		$_[] = "</results>";
		$_[] = "</merchant-calculation-results>";

		if(SHOPP_DEBUG) new ShoppError(join("\n",$_),'google-merchant-calculation-results',SHOPP_DEBUG_ERR);
		$this->response($_);
		return false; //no ack
	}

	function send ($message,$url) {
		$type = ( str_true($this->settings['testmode']) ? 'test' : 'live' );
		$url = sprintf($url[$type],$this->settings['id'],$this->settings['key'],$this->settings['id']);
		$response = parent::send($message,$url);
		return new xmlQuery($response);
	}

	function error () { // Error response
		header('HTTP/1.1 500 Internal Server Error');
		die("<h1>500 Internal Server Error</h1>");
	}

	function settings () {
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
			'checked' => str_true($this->settings['testmode']),
			'label' => sprintf(__('Use the %s','Shopp'),'<a href="http://docs.shopplugin.net/Google_Checkout_Sandbox">Google Checkout Sandbox</a>')
		));

		$this->ui->menu(1,array(
			'name' => 'button',
			'keyed'=> true,
			'selected' => $this->settings['button']
		),$buttons);

		$this->ui->menu(1,array(
			'name' => 'buttonstyle',
			'keyed'=> true,
			'selected' => $this->settings['buttonstyle'],
			'label' => __('Select the preferred size and style of the Google Checkout button.','Shopp')
		),$styles);

		$this->ui->checkbox(1,array(
			'name' => 'autocharge',
			'checked' => str_true($this->settings['autocharge']),
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
		// Build the Google Checkout API URL if Google Checkout is enabled
		if (!empty($_POST['settings'][$this->module]['id']) && !empty($_POST['settings'][$this->module]['key'])) {
			$id = $_POST['settings'][$this->module]['id'];
			$key = $_POST['settings'][$this->module]['key'];
			$url = add_query_arg(
				array(
					'_txnupdate' => 'gc',
					'merc' => $this->authcode($id,$key)
				),
				shoppurl(false,'checkout',true));
			$_POST['settings'][$this->module]['apiurl'] = $url;
		}
	}

	/**
	 * map_sub_period
	 *
	 * map the recurrence/subscription period to an nearest smaller period acceptable to Google Checkout
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @param int $interval the items interval
	 * @param string $period one of d, w, m, y
	 * @return string the Google Checkout compatible period
	 **/
	function map_sub_period ( $interval, $period ) {
		/*
			TODO document Google Checkout recurrence limitations
		*/
		$periods = array('1d'=>'DAILY', '1w'=>'WEEKLY', '2w'=>'SEMI_MONTHLY', '1m'=>'MONTHLY', '2m'=>'EVERY_TWO_MONTHS', '3m'=>'QUARTERLY', '1y'=>'YEARLY');

		$ranges = array(
			'd' => array(1 => '1d', 7 => '1w', 14 => '2w', 30 => '1m', 60 => '2m', 90 => '3m', 365 => '1y'),
			'w' => array(1 => '1w', 2 => '2w', 4 => '1m', 8 => '2m', 12 => '3m', 52 => '1y'),
			'm' => array(1 => '1m', 2 => '2m', 3 => '3m', 12 => '1y'),
			'y' => array(1 => '1y'),
		);

		$map = $periods['1d'];
		foreach ( $ranges[$period] as $limit => $p ) {
			if ( $interval >= $limit ) $map = $periods[$p];
		}

		return $map;
	}

	/**
	 * calc_sub_date
	 *
	 * get the ISO 8601 date of the start of the subscription
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @param int $interval the trial interval
	 * @param string $period one of d, w, m, y
	 * @param bool $iso true for iso 8601 format, false for mktime
	 * @return string|int ISO 8601 formatted date string, or mktime int
	 **/
	function calc_sub_date ( $interval, $period, $iso = true, $from = false ) {
		$names = array('d'=>'days', 'w'=>'weeks', 'm'=>'months', 'y'=>'years');

		$date = strtotime( "+$interval {$names[$period]}", $from ? $from : time() );

		if ( $iso)
			return date('c', $date);
		return $date;
	}

} // END class GoogleCheckout

?>