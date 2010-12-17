<?php
/**
 * FedEx Rates
 * 
 * Uses FedEx Web Services to get live shipping rates based on product weight
 * 
 * INSTALLATION INSTRUCTIONS
 * Upload FedExRates.php to your Shopp install under:
 * ./wp-content/plugins/shopp/shipping/
 *
 * @author Jonathan Davis
 * @version 1.1.4
 * @copyright Ingenesis Limited, 22 January, 2009
 * @package shopp
 * @since 1.1
 * @subpackage FedExRates
 * 
 * $Id$
 **/

class FedExRates extends ShippingFramework implements ShippingModule {

	var $wsdl_url = "";
	var $url = "https://gateway.fedex.com:443/web-services";
	var $test_url = "https://gatewaybeta.fedex.com:443/web-services";
	var $documentation = "FedEx Shipping Module";
	
	var $packages = array();
	
	var $test = false;
	var $postcode = true;
	var $dimensions = true;
	var $singular = true; // module only can be loaded once
	
	var $services = array(
		'FEDEX_GROUND' => 'FedEx Ground',
		'GROUND_HOME_DELIVERY' => 'FedEx Home Delivery',
		'FEDEX_EXPRESS_SAVER' => 'FedEx Express Saver',
		'FEDEX_2_DAY' => 'FedEx 2Day',
		'STANDARD_OVERNIGHT' => 'FedEx Standard Overnight',
		'PRIORITY_OVERNIGHT' => 'FedEx Priority Overnight',
		'FIRST_OVERNIGHT' => 'FedEx First Overnight',
		'INTERNATIONAL_ECONOMY' => 'FedEx International Economy',
		'INTERNATIONAL_FIRST' => 'FedEx International First',
		'INTERNATIONAL_PRIORITY' => 'FedEx International Priority',
		'EUROPE_FIRST_INTERNTIONAL_PRIORITY' => 'FedEx Europe First International Priority',
		'FEDEX_1_DAY_FREIGHT' => 'FedEx 1Day Freight',
		'FEDEX_2_DAY_FREIGHT' => 'FedEx 2Day Freight',
		'FEDEX_3_DAY_FREIGHT' => 'FedEx 3Day Freight',
		'INTERNATIONAL_ECONOMY_FREIGHT' => 'FedEx Economy Freight',
		'INTERNATIONAL_PRIORITY_FREIGHT' => 'FedEx Priority Freight',
		'FEDEX_FREIGHT' => 'Fedex Freight',
		'FEDEX_NATIONAL_FREIGHT' => 'FedEx National Freight',
		'INTERNATIONAL_GROUND' => 'FedEx International Ground',
		'SMART_POST' => 'FedEx Smart Post'	
		);
	var $deliverytimes = array(
		'ONE_DAY' => '1d',
		'TWO_DAYS' => '2d',
		'THREE_DAYS' => '3d',
		'FOUR_DAYS' => '4d',
		'FIVE_DAYS' => '5d',
		'SIX_DAYS' => '6d',
		'SEVEN_DAYS' => '7d',
		'EIGHT_DAYS' => '8d',
		'NINE_DAYS' => '9d',
		'TEN_DAYS' => '10d',
		'ELEVEN_DAYS' => '11d',
		'TWELVE_DAYS' => '12d',
		'THIRTEEN_DAYS' => '13d',
		'FOURTEEN_DAYS' => '14d',
		'FIFTEEN_DAYS' => '15d',
		'SIXTEEN_DAYS' => '16d',
		'SEVENTEEN_DAYS' => '17d',
		'EIGHTEEN_DAYS' => '18d',
		'NINETEEN_DAYS' => '19d',
		'TWENTY_DAYS' => '20d',
		'UNKNOWN' => '30d'
		);
	
	function __construct () {
		parent::__construct();
		$Settings = ShoppSettings();
		$this->setup('account','meter','postcode','key','password','smartposthubid','insure','saturday');

		if ($this->singular && is_array($this->rates) && !empty($this->rates))  $this->rate = reset($this->rates); // TODO: remove after 1.1.3
		
		add_action('shipping_service_settings',array(&$this,'settings'));
		add_action('shopp_verify_shipping_services',array(&$this,'verify'));

		$this->wsdl_url = add_query_arg(array('shopp_fedex'=>'wsdl','ver'=>'9'),get_bloginfo('siteurl'));
		$this->wsdl();
		
		if (defined('SHOPP_FEDEX_TESTMODE')) $this->test = SHOPP_FEDEX_TESTMODE;
		
		$this->insure = ($this->settings['insure'] == 'on');
		$this->settings['base_operations'] = $Settings->get('base_operations');
		
	}
		
	function methods () {
		if (class_exists('SoapClient') || class_exists('SOAP_Client'))
			return array(__("FedEx Rates","Shopp"));
		elseif (class_exists('ShoppError'))
			new ShoppError("The SoapClient class is not enabled for PHP. The FedEx Rates add-on cannot be used without the SoapClient class.","fedexrates_nosoap",SHOPP_ALL_ERR);
	}
		
	function ui () { ?>
		function FedExRates (methodid,table,rates) {
			table.addClass('services').empty();
			
			if (!uniqueMethod(methodid,'<?php echo get_class($this); ?>')) return;
			
			var services = <?php echo json_encode($this->services); ?>;
			var settings = '';
			settings += '<tr><td>';
			settings += '<style type="text/css">#shipping-rates th.fedexrates { background: url(data:image/png;base64,<?php echo $this->logo(); ?>) no-repeat 20px 25px; }</style>';
			settings += '<input type="hidden" name="settings[shipping_rates]['+methodid+'][postcode-required]" value="true" />';

			settings += '<div class="multiple-select"><ul id="fedex-services">';
		
			settings += '<li><input type="checkbox" name="select-all" id="fedex-services-select-all" /><label for="fedex-services-select-all"><strong><?php _e('Select All','Shopp'); ?></strong></label>';
			var even = true;

			for (var service in services) {
				var checked = '';
				even = !even;
				for (var r in rates.services) 
					if (rates.services[r] == service) checked = ' checked="checked"';
				settings += '<li class="'+((even)?'even':'odd')+'"><input type="checkbox" name="settings[shipping_rates]['+methodid+'][services][]" value="'+service+'" id="fedex-service-'+service+'"'+checked+' /><label for="fedex-service-'+service+'">'+services[service]+'</label></li>';
			}

			settings += '</td>';
			
			settings += '<td>';
			settings += '<div><input type="text" name="settings[FedExRates][account]" id="fedexrates_account" value="<?php echo $this->settings['account']; ?>" size="11" /><br /><label for="fedexrates_account"><?php _e('Account Number','Shopp'); ?></label></div>';
			settings += '<div><input type="text" name="settings[FedExRates][meter]" id="fedexrates_meter" value="<?php echo $this->settings['meter']; ?>" size="11" /><br /><label for="fedexrates_meter"><?php _e('Meter Number','Shopp'); ?></label></div>';
			settings += '<div><input type="text" name="settings[FedExRates][postcode]" id="fedexrates_postcode" value="<?php echo $this->settings['postcode']; ?>" size="7" /><br /><label for="fedexrates_postcode"><?php _e('Your postal code','Shopp'); ?></label></div>';
			settings += '<div><input type="text" name="settings[FedExRates][smartposthubid]" id="fedexrates_smartposthubid" value="<?php echo $this->settings['smartposthubid']; ?>" size="7" /><br /><label for="fedexrates_smartposthubid"><?php _e('SmartPost HubID (Required for SmartPost)','Shopp'); ?></label></div>';
				
			settings += '</td>';
			settings += '<td>';
			settings += '<div><input type="text" name="settings[FedExRates][key]" id="fedexrates_key" value="<?php echo $this->settings['key']; ?>" size="16" /><br /><label for="fedexrates_key"><?php _e('FedEx web services key','Shopp'); ?></label></div>';
			settings += '<div><input type="password" name="settings[FedExRates][password]" id="fedexrates_password" value="<?php echo $this->settings['password']; ?>" size="16" /><br /><label for="fedexrates_password"><?php _e('FedEx web services password','Shopp'); ?></label></div>';
			settings += '<div><input type="hidden" name="settings[FedExRates][insure]" value="off" /><input type="checkbox" name="settings[FedExRates][insure]" id="fedexrates_insure" value="on"<?php echo ($this->settings['insure'] == "on")?' checked="checked"':''; ?> /><label for="fedexrates_insure"> <?php echo addslashes(__('Rates include insurance.','Shopp')); ?></label></div>';
			settings += '<div><input type="hidden" name="settings[FedExRates][saturday]" value="off" /><input type="checkbox" name="settings[FedExRates][saturday]" id="fedexrates_saturday" value="on"<?php echo ($this->settings['saturday'] == "on")?' checked="checked"':''; ?> /><label for="fedexrates_saturday"> <?php echo addslashes(__('Include only methods with Saturday delivery.','Shopp')); ?></label></div>';
			settings += '</td>';
			settings += '</tr>';


			$(settings).appendTo(table);

			$('#fedex-services-select-all').change(function () {
				if (this.checked) $('#fedex-services input').attr('checked',true);
				else $('#fedex-services input').attr('checked',false);
			});
				
			quickSelects();

		}

		methodHandlers.register('<?php echo get_class($this); ?>',FedExRates);

		<?php		
	}

	function init () {
		$this->packages = array();
	}
	
	function calcitem ($id,$Item) {
		$precision = $this->settings['base_operations']['currency']['format']['precision'];
		
		for ($i = 0; $i < $Item->quantity; $i++) {
			$count = count($this->packages) + 1;
			$this->packages[$count] = array(
				'weight' => convert_unit($Item->weight,'lb'),
				'height' => convert_unit($Item->height, 'in'),
				'length' => convert_unit($Item->length, 'in'),
				'width' => convert_unit($Item->width, 'in'),
				'insure' => number_format($Item->unitprice,$precision)
				);
		}
		if(SHOPP_DEBUG) new ShoppError('packages '._object_r($this->packages),false,SHOPP_DEBUG_ERR);
	}
	
	function calculate ($options,$Order) {
		// Don't get an estimate without a postal code
		if (empty($Order->Shipping->postcode)) return $options;

		$request = $this->build(session_id(), $this->rate['name'], 
			$Order->Shipping);
		$Response = $this->send($request);
		if(SHOPP_DEBUG) new ShoppError('RESPONSE: '._object_r($Response),false,SHOPP_DEBUG_ERR);
		if (!$Response) {
			new ShoppError(apply_filters('shopp_fedex_error',__('There was an error obtaining FedEx Rates.','Shopp')), 'fedex_rate_error', SHOPP_ADDON_ERR); 
			return apply_filters('shopp_fedex_rates', false, &$options, &$Order); // useful for adding your own hardcoded options
		}	
		if ($Response->HighestSeverity == 'FAILURE' || 
		 		$Response->HighestSeverity == 'ERROR') {
			new ShoppError(apply_filters('shopp_fedex_error', $Response->Notifications->Message),'fedex_rate_error',SHOPP_ADDON_ERR);
			return apply_filters('shopp_fedex_rates', false, &$options, &$Order); // useful for adding your own hardcoded options
		}

		$estimate = false;
		
		$RatedReply = &$Response->RateReplyDetails;
		if (!is_array($RatedReply)) return false;
		foreach ($RatedReply as $quote) {
			if (!in_array($quote->ServiceType,$this->rate['services'])) continue;
			
			$name = $this->services[$quote->ServiceType];
			if (is_array($quote->RatedShipmentDetails)) 
				$details = &$quote->RatedShipmentDetails[0];
			else $details = &$quote->RatedShipmentDetails;
			
			if (isset($quote->DeliveryTimestamp)) 
				$delivery = $this->timestamp_delivery($quote->DeliveryTimestamp);
			elseif(isset($quote->TransitTime))
				$delivery = $this->deliverytimes[$quote->TransitTime];
			else $delivery = '5d-7d';
			
			$amount = apply_filters('shopp_fedex_total',$details->ShipmentRateDetail->TotalNetCharge->Amount,$details);

			$rate = array();
			$rate['name'] = $name;
			$rate['amount'] = $amount;
			$rate['delivery'] = $delivery;
			$options[$rate['name']] = new ShippingOption($rate);
		}
		
		return apply_filters('shopp_fedex_rates', $options, &$options, &$Order); // added for completeness
	}
	
	function timestamp_delivery ($datetime) {
		list($year,$month,$day,$hour,$min,$sec) = sscanf($datetime,"%4d-%2d-%2dT%2d:%2d:%2d");
		$days = ceil((mktime($hour,$min,$sec,$month,$day,$year) - mktime())/86400);
		return $days.'d';
	}
	
	function build ($session,$description,$Shipping) {
		
		$_ = array();

		$_['WebAuthenticationDetail'] = array(
			'UserCredential' => array(
				'Key' => $this->settings['key'], 
				'Password' => $this->settings['password']));

		$_['ClientDetail'] = array(
			'AccountNumber' => $this->settings['account'],
			'MeterNumber' => $this->settings['meter']);

		$_['TransactionDetail'] = array(
			'CustomerTransactionId' => empty($session)?mktime():$session);

		$_['Version'] = array(
			'ServiceId' => 'crs', 
			'Major' => '9', 
			'Intermediate' => '0', 
			'Minor' => '0');

		$_['ReturnTransitAndCommit'] = '1'; 

		$_['RequestedShipment'] = array();
		$_['RequestedShipment']['ShipTimestamp'] = date('c');
		
		// Valid values REGULAR_PICKUP, REQUEST_COURIER, ...
		$_['RequestedShipment']['DropoffType'] = 'REGULAR_PICKUP';
		$_['RequestedShipment']['ShipTimestamp'] = date('c');
		 
		$_['RequestedShipment']['PackagingType'] = 'YOUR_PACKAGING'; // valid values FEDEX_BOX, FEDEX_PAK, FEDEX_TUBE, YOUR_PACKAGING, ...
		
		$_['RequestedShipment']['Shipper'] = array(
			'Address' => array(
				'PostalCode' => $this->settings['postcode'],
				'CountryCode' => $this->base['country']));

		$_['RequestedShipment']['Recipient'] = array(
			'Address' => array(
				'Residential' => (apply_filters('shopp_fedex_residential', false, $Shipping) === true ? '1':'0'),
				'StreetLines' => array($Shipping->address,$Shipping->xaddress),
				'City' => $Shipping->city,
				'StateOrProvinceCode' => $Shipping->state,
				'PostalCode' => $Shipping->postcode,
				'CountryCode' => $Shipping->country));


		$_['RequestedShipment']['ShippingChargesPayment'] = array(
			'PaymentType' => 'SENDER',
			'Payor' => array('AccountNumber' => $this->settings['account'],
			'CountryCode' => 'US'));
		
		if (in_array('SMART_POST', $this->rate['services']) && !empty($this->settings['smartposthubid']) ){
			$_['RequestedShipment']['SmartPostDetail'] = array(
				'Indicia' => 'PARCEL_SELECT',
				'HubId' => $this->settings['smartposthubid']);
		}
			
		$_['RequestedShipment']['RateRequestTypes'] = 'ACCOUNT'; 
		// $_['RequestedShipment']['RateRequestTypes'] = 'LIST';
		 
		$_['RequestedShipment']['PackageCount'] = count($this->packages);
		$_['RequestedShipment']['PackageDetail'] = 'INDIVIDUAL_PACKAGES';
		if ($this->settings['saturday'] == 'on') $_['RequestedShipment']['SpecialServicesRequested'] = array(
			'SpecialServiceTypes' => array('SATURDAY_DELIVERY')
		);
		
		$requested = array();
		$count = 0;
		foreach ($this->packages as $seq => $package) {
			$requested["$count"] = array();
			$requested["$count"]['SequenceNumber'] = $seq;
			if($this->insure) $requested["$count"]['InsuredValue'] = array(
				'Amount' => $package['insure'],
				'Currency' => 'USD'
				);
			if ($package['description']) $requested["$count"]['ItemDescription'] = $package['description'];
			$requested["$count"]['Dimensions'] = array(
				'Length' => $package['length'],
				'Width' => $package['width'],
				'Height' => $package['height'],
				'Units' => "IN"
				);
			$requested["$count"]['Weight'] = array(
				'Units' => 'LB',
				'Value' => number_format( ($package["weight"] < 0.1 ? 0.1 : $package["weight"]), 1, '.', '')
				);
			$count++;
		}
		$_['RequestedShipment']['RequestedPackageLineItems'] = $requested;
		
		return apply_filters('shopp_fedex_request', $_, $session,$description,$postcode,$country);
	} 
	
	function verify () {         
		if (!$this->activated()) return;
		
		if (!$this->wsdl(true)) return;
		
		$this->packages[1] = array(
			'weight' => 2,
			'height' => 3,
			'length' => 10,
			'width' => 10,
			'insure' => 10
			);
		$Shipping = new stdClass();
		$Shipping->postcode = 10012;
		$Shipping->country = US;
		
		$request = $this->build('1','Authentication test',$Shipping);
		$request['RequestedShipment']['PackageCount'] = '2';
		$request['RequestedShipment']['PackageDetail'] = 'INDIVIDUAL_PACKAGES';  //  Or PACKAGE_SUMMARY
		$request['RequestedShipment']['RequestedPackageLineItems'] = array('0' => array('Weight' => array('Value' => 2.0,
		                                                                                    'Units' => 'LB'),
		                                                                                    'Dimensions' => array('Length' => 10,
		                                                                                        'Width' => 10,
		                                                                                        'Height' => 3,
		                                                                                        'Units' => 'IN')),
		                                                                   '1' => array('Weight' => array('Value' => 5.0,
		                                                                                    'Units' => 'LB'),
		                                                                                    'Dimensions' => array('Length' => 20,
		                                                                                        'Width' => 20,
		                                                                                        'Height' => 10,
		                                                                                        'Units' => 'IN')));
		// if(SHOPP_DEBUG) new ShoppError("FedEx verify test request: "._object_r($request),false,SHOPP_DEBUG_ERR);
		$response = $this->send($request);
		// if(SHOPP_DEBUG) new ShoppError("FedEx verify test response: "._object_r($response),false,SHOPP_DEBUG_ERR);
		if (isset($response->HighestSeverity)
			&& ($response->HighestSeverity == 'FAILURE'
			|| $response->HighestSeverity == 'ERROR')) 
		 	new ShoppError($response->Notifications->Message,'fedex_verify_auth',SHOPP_ADDON_ERR);
	}   
	
	function send ($request) {
		try {
			if (class_exists('SoapClient')) {
				ini_set("soap.wsdl_cache_enabled", "1");
				$client = new SoapClient($this->wsdl_url);
				$response = $client->getRates($request);
			} elseif (class_exists('SOAP_Client')) {
				$WSDL = new SOAP_WSDL($this->wsdl_url);
				$client = $WSDL->getProxy();
				$returned = $client->getRates($request);
				$response = new StdClass();
				foreach ($returned as $key => $value) {
					if (empty($key)) continue;
					$response->{$key} = $value;
				}
				if (is_array($response->RateReplyDetails) && is_array($response->RateReplyDetails[0]))
					$response->RateReplyDetails = $this->fix_pear_soap_result_bug($response->RateReplyDetails);
				if(is_object($response->RateReplyDetails))
					$response->RateReplyDetails = array($response->RateReplyDetails);

			}
		} catch (Exception $e) {
			new ShoppError(__("FedEx could not be reached for realtime rates.","Shopp"),'fedex_connection',SHOPP_COMM_ERR);
			if(SHOPP_DEBUG) new ShoppError("Exception: ".$e->getMessage(),false,SHOPP_DEBUG_ERR);
			return false;
		}

		return $response;
	}
	
	// Workaround for a severe parse bug in PEAR-SOAP 0.12 beta				
	function fix_pear_soap_result_bug ($array) {
		$rates = array();
		foreach ($array as $value) {
			if (is_object($value)) $rates[] = $value;
			else $rates = array_merge($rates,$this->fix_pear_soap_result_bug($value));
		}
		return $rates;
	}
	
	function logo () {
		return 'iVBORw0KGgoAAAANSUhEUgAAAGIAAAAeCAMAAADgmzuGAAAC/VBMVEX///9lLI+YmJhmLI79/f58S6CioqJ9TKBpLotsNZT7+fyVlZVlLY9tOJXR0dH+/v7Pz8+JXarIyMiWlpb39Pmee7l6R52np6fo6OiSarD6+vp4Rp3z8/OHWqmOZa1wO5eurq62m8pqM5Pv6fObdrZzP5lkK5D8/PympqbAwMB6SJ77+/tlLI739/f9/f36+Pu+vr6bm5va2tplK464uLj8+v3KysqDVaWlpaVnLpDRwN7Dw8OYcrT7+vyZmZloMJH4+Pji1+rVxeBnL5FuOZjZy+OXl5e0tLSxsbGpqamkpKSgoKDj4+NkLI66urpmLpLW1tb+/f68vLzBqtLOzs7r6+uTk5N2Q5u0mMn38/l7Sp/49/uGWqb07/eUbLLLttluMomqisH49frTwt+tj8Pq4vCXcbTd0ebu5/Kvk8bY2Ni4ncx0QZqzl8jf399iJY7dz+bRvt2SabC5nsy6os1kLJDbzuWysrKtj8TWx+G7u7tkLY9lLY5rNJPg4OC3t7fl5eX5+fmrjMKYcbORZq9nLY1/UKJ8TqF7R5p3RZyffLqUbLG9o8+6n82ylMbn3u3MzMz18vhpMZKjfrbm3O2BUJyWbrOcdrfj1+moqqaJYKhuPJOCWKyYd7eAV6rYyePay+OXlZp5RZ6ppqr6+PymhMCxk8jT09OzlsWZnJfLuNn08Pf19fWNXqZ5Q5vPvNzc3Nzx8fGOZKuJYayMYauMYq3o4O7UxeDEs91oL47m3/C4t7ial5xoMpV1Rpn49fmzmsfX0veadLW2n9L08fn59vmdeLaIW6e2trbj2et2RprNvNv9/P1kLI/5+PucnJyRZrLg1Oh7S5+2otW1pd/Rw9/y7fXYyuOffb6YlpuRaLC8pM7k3+7NuNbPweO5ubm3nMuamJyenp63nMyxlcaSaLDm5uby7vbGxsZsNJSiiM3x6/PNzc3Nu9uUa66BU6RrN5WEU6WEVaWjgbynhr5mLJCgfblyQZfVxuHFxcWVbrK3urbt7e3k2uy1m8qIXDLQAAAAAXRSTlMAQObYZgAAA95JREFUeF7NllO0K0kYhXd3bB7btm1d27Zt2x7btm3btm1zTfdfnaQ7t5Mzc57me9qVrKovf/XaSXDjaIMugGGv4TGEZ2xMhrDj/NMvOC07QJS+eytCEccpMS1BeD6ZwAks/uW1yzrlZBcgFLoghW02wjPxEVI8/p5SERVa8XwfFXff+X9WmFb1TVHdq8KQkjJG5NDCtP+k6PnyCOG2Z/WmOFkLFXpXDHMgQHhFjLlvincK/7VillZxzJLhc6euObD2C/gZOmf6ijErpqdh4oQ+TmGYf/ADgZSLW4CXF1YdI6/1pM9ngjHzokh6JXK5N1HxLJzOESKVQ+xuJwvSXKWV9MZvi/y9yJd4Ax0GzkemMXIpGX7N5Rjr29KrVHvRs+kZCwWPnQzzGmnF8x8H98I6ebaJgkZjFMWaz54AsLb9KS6AqkKfFT2Kp8QPAbClhLKlXzSCFaa54odcqTHu2n/mYHKNPozb90hDCVOEUvRrhp1nsaQUI6ulOADHKWpSxbDzrheuMD/YGkl5O141kqEsLiIid2UIRVQzUOmbaF6yh+6ML0Wwwqopo2B8ACKr6M50b+1h5z58FJh2gjFTfYoCADmdCvgjkCky0u+vr6/fvm+3RnwO7beee0tr6zm1u0RFW8YxmuGG8RB46OydcoV+2CCivC4WQF6xR2YYdQnkikkN0Gq1T+IsGmOwzWYSsDVxMi4H8eKUMpliUCEcIoWsglu7/AKPpxgKxf4GVrDvrbTUcMeRdDOIU8krUyi4MtnvKI9VKpZdCLoGAxeK3WkgDieF/QIZ2Z8JLMnxUFVUfEMrmy7Xz7hIdl0LpKL/tTHcFI66TonGT9UV+JpWrqHesQLeaQJmbyIrZS2IyVa5Yp0DCtyBZ2GxqysupQOSWiBjKlNcb4aAdsd6uUKfU0ysc9PN2y2dARqvVVXcN4UcMV76wN8luFwJH72bwRERRwEz+UL1orSEMi/V/P0tagrtvnxxOTDhmhOf+/vbDWKuKorhGHt//kFHIcRv9wAy8JbqHMnRP1pFgTevZgW3ptaw0FSLjo00GhFGEa2nZNFvQo5P/CcAXZACS1M5OT/dC+DAhkBRappU/0dV58VnS6VeBMSWs8xHxQMLBhI7xsPHK+NkhtSD5F1+W2Y+M7Rtc4kbFp9xnlLRE+0rnRMCBaslX3c8Il6fJHDToQr4SVsTx+6Ka5/VAcbbV3F0b+3zi2YkChuejbjjj0dldNW5uzeLYfUI1pTryrvE5eZkJxqKzAJFFZAz56sZp/ye/uPwFi183PPStmWumA9bgKdpR0VWEM15FPIcYDhoXRhb8A/W6cfa+TMAogAAAABJRU5ErkJggg==';
	}
	
	function wsdl ($verify=false) {
		$lookup = (isset($_GET['shopp_fedex']))?$_GET['shopp_fedex']:'';
		if ($lookup != 'wsdl' && !$verify) return;
		$wsdl = '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';

		$contents = "";
		$error = false;
		if (defined('SHOPP_FEDEX_WSDL_FILE')) {
			if (!file_exists(SHOPP_FEDEX_WSDL_FILE)) $error = __('The specified SHOPP_FEDEX_WSDL_FILE does not exist.','Shopp');
			else $contents = file_get_contents(SHOPP_FEDEX_WSDL_FILE);
		} else {
			if (true) $error = __('PHP does not support bzip2 decompression of the embedded WSDL file.','Shopp');
			else $contents = bzdecompress(base64_decode($wsdl));
		}
		
		if ($error === false && empty($contents)) $error = __('An unknown error occurred while reading the WSDL file.','Shopp');
		
		if ($error !== false) {
			$msg = sprintf(__('Unable to read the web services description file to establish a SOAP connection to FedEx. %s See the <strong>Troubleshooting</strong> section of the "%s" article in the online documentation for help with this issue.','Shopp'),$error,'<a href="'.SHOPP_DOCS.str_replace(" ","_",$this->documentation).'#Troubleshooting">'.$this->documentation.'</a>');

			global $Shopp;
			if (!isset($Shopp->Errors)) {
				$Shopp->Errors = new ShoppErrors($Shopp->Settings->get('error_logging'));
				$Shopp->ErrorLog = new ShoppErrorLogging($Shopp->Settings->get('error_logging'));
			}
			new ShoppError($msg,'wsdl_decompress',SHOPP_ADDON_ERR);
			
			if ($verify) return false;
			status_header('404');
			wp_die($msg,__('FedEx Rates WSDL Error','Shopp'),array('response'=>404));
			exit();
		}
		
		$contents = str_replace('__SERVICE_URL__',($this->test?$this->test_url:$this->url),$contents);

		$expire = 31536000;
		header('Content-Type: text/xml; charset=UTF-8');
		header('Vary: Accept-Encoding'); // for proxies
		header('Expires: '.gmdate("D, d M Y H:i:s", time() + $expire).' GMT');
		header("Cache-Control: public, max-age=$expire");

		if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos(strtolower($_SERVER['HTTP_ACCEPT_ENCODING']), 'gzip') !== false) {
			$encoded = gzencode($contents);
			header('Content-Encoding: gzip');
			header("Content-length: ".strlen($encoded));
			echo $encoded;
		} else echo $contents;
		exit();
	}
	
}

?>