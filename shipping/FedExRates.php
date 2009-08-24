<?php
/**
 * FedExRates
 * Uses FedEx Web Services to get live shipping rates based on product weight
 * INSTALLATION INSTRUCTIONS: Upload FedExRates.php and FedExRateService_v5.wsdl 
 * to your Shopp install under: .../wp-content/plugins/shopp/shipping/
 *
 * @author Jonathan Davis
 * @version 1.0.2
 * @copyright Ingenesis Limited, 22 January, 2009
 * @package shopp
 **/

class FedExRates {
	var $wsdl = "FedExRateService_v5.wsdl";
	var $request = false;
	var $weight = 0;
	var $conversion = 1;
	var $Response = false;
	var $requiresauth = true;
	
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
	
	function FedExRates () {
		global $Shopp;
		
		$this->wsdl = dirname(__FILE__).DIRECTORY_SEPARATOR.$this->wsdl;
		$this->settings = $Shopp->Settings->get('FedExRates');
		if (!isset($this->settings['account'])) $this->settings['account'] = '';
		if (!isset($this->settings['meter'])) $this->settings['meter'] = '';
		if (!isset($this->settings['postcode'])) $this->settings['postcode'] = '';
		if (!isset($this->settings['key'])) $this->settings['key'] = '';
		if (!isset($this->settings['password'])) $this->settings['password'] = '';
		
		$base = $Shopp->Settings->get('base_operations');
		$this->settings['country'] = $base['country'];
   		$storeunits = $Shopp->Settings->get('weight_unit');

		$units = array("imperial" => "LB","metric"=>"KG");
		$this->settings['units'] = $units[$base['units']];
		if ($storeunits == 'oz') $this->conversion = 0.0625;
		if ($storeunits == 'g') $this->conversion = 0.001;
		
		add_action('shipping_service_settings',array(&$this,'settings'));
	}
	
	function methods (&$ShipCalc) {
		if (class_exists('SoapClient') || class_exists('Soap_Client'))
			$ShipCalc->methods[get_class($this)] = __("FedEx Rates","Shopp");
		elseif (class_exists('ShoppError'))
			new ShoppError("The SoapClient class is not enabled for PHP. The FedEx Rates add-on cannot be used without the SoapClient class.","fedexrates_nosoap",SHOPP_ALL_ERR);
	}
		
	function ui () { ?>
		function FedExRates (methodid,table,rates) {
			table.addClass('services').empty();
			
			uniqueMethod(methodid,'<?php echo get_class($this); ?>');
			
			var services = <?php echo json_encode($this->services); ?>;
			var settings = '';
			settings += '<tr><td>';
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
	
	function calculate (&$Cart,$fees,$rate,$column) {
		if (empty($Cart->data->Order->Shipping->postcode)) return false;
		$ShipCosts = &$Cart->data->ShipCosts;
		$weight = 0;
		foreach($Cart->shipped as $Item) $weight += (($Item->weight * $this->conversion) * $Item->quantity);

		$this->request = $this->build($Cart->session, $rate['name'], $weight, 
			$Cart->data->Order->Shipping->postcode, $Cart->data->Order->Shipping->country);
		
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
			if (!in_array($quote->ServiceType,$rate['services'])) continue;
			
			$name = $this->services[$quote->ServiceType];
			if (is_array($quote->RatedShipmentDetails)) 
				$details = &$quote->RatedShipmentDetails[0];
			else $details = &$quote->RatedShipmentDetails;
			
			if (isset($quote->DeliveryTimestamp)) 
				$DeliveryEstimate = $this->timestamp_delivery($quote->DeliveryTimestamp);
			elseif(isset($quote->TransitTime))
				$DeliveryEstimate = $this->deliverytimes[$quote->TransitTime];
			else $DeliveryEstimate = '5d-7d';
			
			$total = $details->ShipmentRateDetail->TotalNetCharge->Amount;

			$rate['cost'] = $total+$fees;
			$ShipCosts[$name] = $rate;
			$ShipCosts[$name]['name'] = $name;
			$ShipCosts[$name]['module'] = get_class($this);
			$ShipCosts[$name]['delivery'] = $DeliveryEstimate;
			if (!$estimate || $rate['cost'] < $estimate['cost']) $estimate = &$ShipCosts[$name];

		}
		return $estimate;
	}
	
	function timestamp_delivery ($datetime) {
		list($year,$month,$day,$hour,$min,$sec) = sscanf($datetime,"%4d-%2d-%2dT%2d:%2d:%2d");
		$days = ceil((mktime($hour,$min,$sec,$month,$day,$year) - mktime())/86400);
		return $days.'d';
	}
	
	function build ($cart,$description,$weight,$postcode,$country) {
		
		$_ = array();

		$_['WebAuthenticationDetail'] = array(
			'UserCredential' => array(
				'Key' => $this->settings['key'], 
				'Password' => $this->settings['password']));

		$_['ClientDetail'] = array(
			'AccountNumber' => $this->settings['account'],
			'MeterNumber' => $this->settings['meter']);

		$_['TransactionDetail'] = array(
			'CustomerTransactionId' => empty($cart->session)?mktime():$cart->session);

		$_['Version'] = array(
			'ServiceId' => 'crs', 
			'Major' => '5', 
			'Intermediate' => '0', 
			'Minor' => '0');

		$_['ReturnTransitAndCommit'] = '1'; 

		$_['RequestedShipment'] = array();
		
		// Valid values REGULAR_PICKUP, REQUEST_COURIER, ...
		$_['RequestedShipment']['DropoffType'] = 'REGULAR_PICKUP'; 
		
		$_['RequestedShipment']['ShipTimestamp'] = date('c');
		
		$_['RequestedShipment']['Shipper'] = array(
			'Address' => array(
				'PostalCode' => $this->settings['postcode'],
				'CountryCode' => $this->settings['country']));

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
						'Value' => number_format(($weight < 0.1)?0.1:$weight,1),
						'Units' => $this->settings['units']));
		
		return $_;
	} 
	
	function verifyauth () {         
		$this->request = $this->build('1','Authentication test',1,'10012','US');
		$response = $this->send();       
		if ($response->HighestSeverity == 'FAILURE' || 
		 	$response->HighestSeverity == 'ERROR') 
		 	new ShoppError($response->Notifications->Message,'fedex_verify_auth',SHOPP_ADDON_ERR);
	}   
	
	function send () {
   		global $Shopp;

		ini_set("soap.wsdl_cache_enabled", "0");
		try {
			if (class_exists('SoapClient')) {
				$client = new SoapClient($this->wsdl, array('trace' => 1));
				$response = $client->getRates($this->request);
			} elseif (class_exists('Soap_Client')) {
				$client = new Soap_Client($this->wsdl, array('trace' => 1));
				$response = $client->call('getRates',$this->request);
				
			}
		} catch (Exception $e) {
			new ShoppError(__("FedEx could not be reached for realtime rates.","Shopp"),'fedex_connection',SHOPP_COMM_ERR);
			return false;
		}
		
		return $response;

	}
	
}
?>