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
 * @version 1.1
 * @copyright Ingenesis Limited, 22 January, 2009
 * @package shopp
 * @since 1.1 dev
 * @subpackage FedExRates
 * 
 * $Id$
 **/

class FedExRates extends ShippingFramework implements ShippingModule {
	var $test = false;
	var $wsdl_url = "";
	var $url = "https://gateway.fedex.com:443/web-services";
	var $test_url = "https://gatewaybeta.fedex.com:443/web-services";
	var $request = false;
	var $weight = 0;
	var $conversion = 1;
	var $dimensions = true;
	var $Response = false;
	
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
		'INTERNATIONAL_PRIORITY_FREIGHT' => 'FedEx Priority Freight'
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

		$this->setup('account','meter','postcode','key','password');
		
		$units = array("imperial" => "LB","metric"=>"KG");
		$this->settings['units'] = $units[$this->base['units']];
		if ($this->units == 'oz') $this->conversion = 0.0625;
		if ($this->units == 'g') $this->conversion = 0.001;

		if (isset($this->rates[0])) $this->rate = $this->rates[0];
		
		add_action('shipping_service_settings',array(&$this,'settings'));
		add_action('shopp_verify_shipping_services',array(&$this,'verify'));

		$this->wsdl_url = add_query_arg('shopp_fedex','wsdl',get_bloginfo('siteurl'));
		$this->wsdl();
		
		if (defined('SHOPP_FEDEX_TESTMODE')) $this->test = SHOPP_FEDEX_TESTMODE;
		
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
				
			settings += '</td>';
			settings += '<td>';
			settings += '<div><input type="text" name="settings[FedExRates][key]" id="fedexrates_key" value="<?php echo $this->settings['key']; ?>" size="16" /><br /><label for="fedexrates_key"><?php _e('FedEx web services key','Shopp'); ?></label></div>';
			settings += '<div><input type="password" name="settings[FedExRates][password]" id="fedexrates_password" value="<?php echo $this->settings['password']; ?>" size="16" /><br /><label for="fedexrates_password"><?php _e('FedEx web services password','Shopp'); ?></label></div>';
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
		$this->weight = 0;
	}
	
	function calcitem ($id,$Item) {
 		$this->weight += ($Item->weight * $this->conversion) * $Item->quantity;
	}
	
	function calculate ($options,$Order) {
		if (empty($Order->Shipping->postcode)) {
			new ShoppError(__('A postal code for calculating shipping estimates and taxes is required before you can proceed to checkout.','Shopp','fedex_postcode_required',SHOPP_ERR));
			return $options;
		}

		$this->request = $this->build(session_id(), $this->rate['name'], 
			$Order->Shipping->postcode, $Order->Shipping->country);
		
		$this->Response = $this->send();
		if (!$this->Response) return false;
		if ($this->Response->HighestSeverity == 'FAILURE' || 
		 		$this->Response->HighestSeverity == 'ERROR') {
			new ShoppError($this->Response->Notifications->Message,'fedex_rate_error',SHOPP_ADDON_ERR);
			exit();
			return false;
		}

		$estimate = false;
		
		$RatedReply = &$this->Response->RateReplyDetails;
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
		
		return $options;
	}
	
	function timestamp_delivery ($datetime) {
		list($year,$month,$day,$hour,$min,$sec) = sscanf($datetime,"%4d-%2d-%2dT%2d:%2d:%2d");
		$days = ceil((mktime($hour,$min,$sec,$month,$day,$year) - mktime())/86400);
		return $days.'d';
	}
	
	function build ($session,$description,$postcode,$country) {
		
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
			'Major' => '5', 
			'Intermediate' => '0', 
			'Minor' => '0');

		$_['ReturnTransitAndCommit'] = '1'; 

		$_['RequestedShipment'] = array();
		$_['RequestedShipment']['ShipTimestamp'] = date('c');
		
		// Valid values REGULAR_PICKUP, REQUEST_COURIER, ...
		$_['RequestedShipment']['DropoffType'] = 'REGULAR_PICKUP'; 
		
		$_['RequestedShipment']['Shipper'] = array(
			'Address' => array(
				'PostalCode' => $this->settings['postcode'],
				'CountryCode' => $this->base['country']));

		$_['RequestedShipment']['Recipient'] = array(
			'Address' => array(
				'PostalCode' => $postcode,
				'CountryCode' => $country));


		$_['RequestedShipment']['ShippingChargesPayment'] = array(
			'PaymentType' => 'SENDER',
			'Payor' => array('AccountNumber' => $this->settings['account'],
			'CountryCode' => 'US'));
			
		$_['RequestedShipment']['RateRequestTypes'] = 'ACCOUNT'; 
		// $_['RequestedShipment']['RateRequestTypes'] = 'LIST'; 
		$_['RequestedShipment']['PackageCount'] = '1';
		$_['RequestedShipment']['PackageDetail'] = 'INDIVIDUAL_PACKAGES';
		
		$_['RequestedShipment']['RequestedPackages'] = array(
				'SequenceNumber' => '1',
					'Weight' => array(
						'Units' => $this->settings['units'],
						'Value' => number_format(($this->weight < 0.1)?0.1:$this->weight,1,'.','')));
		
		return apply_filters('shopp_fedex_request', $_, $session,$description,$postcode,$country);
	} 
	
	function verify () {         
		if (!$this->activated()) return;
		$this->weight = 1;
		$this->request = $this->build('1','Authentication test','10012','US');
		$response = $this->send();
		if (isset($response->HighestSeverity)
			&& ($response->HighestSeverity == 'FAILURE'
			|| $response->HighestSeverity == 'ERROR')) 
		 	new ShoppError($response->Notifications->Message,'fedex_verify_auth',SHOPP_ADDON_ERR);
	}   
	
	function send () {
		try {
			if (class_exists('SoapClient')) {
				ini_set("soap.wsdl_cache_enabled", "1");
				$client = new SoapClient($this->wsdl_url);
				$response = $client->getRates($this->request);
			} elseif (class_exists('SOAP_Client')) {
				$WSDL = new SOAP_WSDL($this->wsdl_url);
				$client = $WSDL->getProxy();
				$returned = $client->getRates($this->request);
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
		return 'iVBORw0KGgoAAAANSUhEUgAAAGIAAAAeCAMAAAGXnAsQAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAwBQTFRF0dHR6OjobTiVbjKJrq6unXi2ZiyOekieiV2qyMjI9/P5/f3+ZSuO/Pr9ysrK+Pf78/PzjmWtfEug7+nzwMDAoqKi+vj7m5ub2tra2NjY1tbWYiWOZCyQk5OTnnu5e0qffUygy7bZZy6Q6uLwtLS0sbGx3c/mkmmwuZ7MeEad9fX12Mrj8u72warS6+vr5ubmoKCg4+Pj5tzt49nr39/fdkObtJjJaS6Lh1qpbDWUhlqmkmiwcDuX9PD3xsbGoH25kmqwqorB49fp1MXg08LfrY/Dnp6etpvKl3G07ufyr5PGbDSUajOTekedm3a20b7dcz+ZaC+OvaPPuqLNt5zLs5fIsZXGrY/Eq4zCmHGzkWavaTGSZiyQf1CiZCuQe0ead0Wc/Pz8mZyXmpecdUaZtra2zs7Opqam+/n8mJiYZCyO/v7+ZSyO9/f3/f39urq6p6envr6+9O/3lGyy+vj8lpaWlZWVzMzMZS2PiFun+PX6+/v7+vr69/T5z8/Pt5zM3dHmg1WlpaWlqamppKSkuLi4/v3+vLy80cDew8PDmHK0+/r8mZmZaDCR+Pj4287lsrKy4tfqdkaa1sfhubm5u7u7mpicZC2PZS2OazST4ODgt7e35eXl+fn5jV6moojN8evzzc3NekedzbvblGuuuJ3MdEGackGXlW6yt7q27e3t5NrstZvK1cXgo362Zi6SnHa3Zy2NqKqmiWCoiWGsjGGrjGKt6ODu+fb5xLPd597t5t/wuLe4fE6haDKVZy+R+PX5s5rH19L3mnS1tp/S9PH59fL4eUObbjmYpoTA09PTy7jZzbzb/fz9ZCyP+fj7nJyckWay4NToe0uftqLVtaXf0cPf8u31n3y6n32+mJabkWiwvKTO5N/uzbjWz8HjlGyxgVCclm6zz7zc3Nzc8fHxjmSrbjyTglismHe3gFeq2Mnj2svjl5WaeUWeqaaqgVOkazeVhFOlhFWlo4G8p4a+2cvjup/NsZPI1cbhxcXFspTGs5bFl5eXZSyP////J5cw/AAAAQB0Uk5T////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////AFP3ByUAAAV5SURBVHjaYtgqpPAfCDanqwJJhssZIM7/lLz/YJ4fm/O/fztS2jv//gXySm48/vfvX8rfAiAPIIAYhIRAShTS00EK/4F1/fsL1vWvi60jGsjJ///3FFDm30eHhH9//xo2/QUIIIbLQO1M/6EAaNDfvwUQNlhG8R/bP+///8BW/P0rmvn3v/hfRqBMCVCMnZ393z+wHpDLQM75zyBXzPv/fzhIeN6/pcVyqampnakT/j9NZQEIIAYFIYinQMBBaFN6OswakOEwicp/SX///oVL/P+PLAFjg3VY/jPP+X8CxALpYPyb+f9vGVCi68a/nWv/7f+35f9GoER+/v9CkHkgo3j/CbGzL/qn/H8m1Ki/f+0gdvzLuvSv8d+/LxCj/v/NzAR6sXgbKDj+efz/z/PPtXgl0COqqXn/VVIBAohB6B8EMP1HA5X//k0CGYAIQaRwdDczMwvFoiMFqGNheXk5A4aOpfX17hP/h0b/+1cxHyR4EsjQEIToqEtLS5PiTEvjAwU8X1raL7AOEPD592+f7dFF//5F3gTyNOVk/kF0QFzFB4pDlr9/SyUgdnT//99W8Q8YxTCwHWheL0RHHtQxLCCtrTBXlQAZQB0Tocnl379n//9zT4foECksLOQEqoPY9gTIEoLq+O8DMT7m//8ZYEYvIqyaBICE7P8aIJkpABBADKZCYJBgjBZUXAFC796ng4HoXJSwgjm9AU1H0b9/0tMgVvzNJVpHMi4dCsAY/C2MXUczMAI5E9F1OPzHAqA6pmBIgO34Xl//4/91YFr891AZKKYMjMdFtVA7gHHOxwAkpIASEsD4N4H5o6LhHzgK2c78n3wOKgb3R6IskJD6PwdItsB9rvTv38YrGWuB5H/zf/945NiRdfT9B5GSpcDogLgq4HBX5N5//068Wfs6FsgDauD///8VRIdofn5+a83/6lKwVlmoPx4A6c//kIANUMAJoiMf6l8tsI4aqA51IP0BmKfYQUAemDqWAQVWo+rIBmno+Y+k4w/EZCBwBAYDsJSMgroKmBCnAo0u+wvTAtPBnfPvn4XRGmAiFAO5y17oH0qci//9WyYCZBkgdPz3h/rh4P8gNmjgwdNVdTqQMPnfCs5cDLeLi3+DXSOsCXTOiW9A1n2ghhNigsUX96SCgQrn7NTUNKBEa2rq7DqAAGPQ/IcKLNX+4wc6VuDYXX4LmnmgID0XlwZ4wQADTA0ErCiqBFuRkoxqRQHxVlj2k2dFE0ErHnH/JxKgWtGeTVADzAoPFCuK1HS9HMODJi9ACLV5ugSbBbsI/y+yItOKfzlQcOf/NwV4qMWZL/IHK/vJDhWZvt+vEWtANc+6AEnapZBqQ7IHzMvM1EePC0svkAkybOaxD16sB4uY7vq/Ox5qI9AKWHmBbkVL33+GTAhTXOK/XhOUaYge3RVsPGDa/AjYJf2WII7QvXgZsCjrsf//q9aZx2G3AhTdIihCfzPLkQOquPs/Nzf32f8vwXasZ2KyBAKmXmT7T0OC9iozD5IV+Xn/s0EgD1KHzVWBm19aWogaF+rg6un/DoV/uMBeaKG/ywbZF1Py0CJXzwBiQxmLwH+sVvxfAuZxtNVyAUFtFRBk1ApCArEDokKxAtmKqWgpihPhizIG7FYcYgab4FALNu2TBQeHhXVIAESNHDAuMhxxRTcoLiTEockIIuY2B5sV/+8eh8R4hY8ShNHb8f/bzAr0YMNWgDCKQoJIdBY83gtWAa1YlgUGUfxwv2rLIxnl8x0sphEYlwMR2B/BAVIv/fwtWr5gZIGy6kAacidAOLKqAgzdYhlAIMaLEqKei103rPD7qjsRkecPXItQ53AIA7ZvzoN18Caigb5qMFUNi5xsMD+vJhcA9lw4LrQUTaEAAAAASUVORK5CYII=';
	}
	
	function wsdl () {
		$lookup = (isset($_GET['shopp_fedex']))?$_GET['shopp_fedex']:'';
		if ($lookup != 'wsdl') return;
		$wsdl = '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';

		$contents = bzdecompress(base64_decode($wsdl));
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