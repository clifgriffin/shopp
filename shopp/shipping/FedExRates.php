<?php
/**
 * FedExRates
 * Uses FedEx Web Services to get live shipping rates based on product weight
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 22 January, 2009
 * @package shopp
 **/

// require_once(SHOPP_PATH."/core/model/XMLdata.php");

class FedExRates {
	var $wsdl = "FedExRateService_v5.wsdl";
	var $request = false;
	var $weight = 0;
	var $Response = false;
	
	var $services = array(
		'FEDEX_1_DAY_FREIGHT' => 'FedEx 1Day Freight',
		'FEDEX_2_DAY' => 'FedEx 2Day',
		'FEDEX_2_DAY_FREIGHT' => 'FedEx 2Day Freight',
		'FEDEX_3_DAY_FREIGHT' => 'FedEx 3Day Freight',
		'FEDEX_EXPRESS_SAVER' => 'FedEx Express Saver',
		'FEDEX_GROUND' => 'FedEx Ground',
		'FIRST_OVERNIGHT' => 'FedEx First Overnight',
		'GROUND_HOME_DELIVERY' => 'FedEx Home Delivery',
		'INTERNATIONAL_ECONOMY' => 'FedEx International Economy',
		'INTERNATIONAL_ECONOMY_FREIGHT' => 'FedEx Economy Freight',
		'INTERNATIONAL_FIRST' => 'FedEx International First',
		'INTERNATIONAL_PRIORITY' => 'FedEx International Priority',
		'INTERNATIONAL_PRIORITY_FREIGHT' => 'FedEx Priority Freight',
		'PRIORITY_OVERNIGHT' => 'FedEx Priority Overnight',
		'STANDARD_OVERNIGHT' => 'FedEx Standard Overnight',
		'EUROPE_FIRST_INTERNTIONAL_PRIORITY' => 'FedEx Europe First International Priority'		
		);
	
	function FedExRates () {
		global $Shopp;
		
		$this->wsdl = dirname(__FILE__).DIRECTORY_SEPARATOR.$this->wsdl;
		$this->settings = $Shopp->Settings->get('FedExRates');
		$base = $Shopp->Settings->get('base_operations');
		$this->settings['country'] = $base['country'];

		$units = array("imperial" => "LB","metric"=>"KG");
		$this->settings['units'] = $units[$base['units']];
		
		add_action('shipping_service_settings',array(&$this,'settings'));
	}
	
	function methods (&$ShipCalc) {
		$ShipCalc->methods[get_class($this)] = __("FedEx Rates","Shopp");
		
	}
		
	function ui () {?>
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
		foreach($Cart->shipped as $Item) $weight += ($Item->weight * $Item->quantity);

		$this->request = $this->build($Cart->session, $rate['name'], $weight, 
			$Cart->data->Order->Shipping->postcode, $Cart->data->Order->Shipping->country);
		
		$this->Response = $this->send();

		$estimate = false;
		
		$RatedReply = &$this->Response->RateReplyDetails;
		if (!is_array($RatedReply)) return false;
		foreach ($RatedReply as $quote) {
			$name = $this->services[$quote->ServiceType];
			// echo "<pre>"; print_r($quote); echo "</pre>";
			if (is_array($quote->RatedShipmentDetails)) 
				$details = &$quote->RatedShipmentDetails[0];
			else $details = &$quote->RatedShipmentDetails;

			$total = $details->ShipmentRateDetail->TotalNetCharge->Amount;

			$rate['cost'] = $total+$fees;
			if (!$estimate) $estimate = $rate['cost'];
			if ($rate['cost'] < $estimate) $estimate = $rate['cost'];
			$ShipCosts[$name] = $rate;
			$ShipCosts[$name]['name'] = $name;
			$ShipCosts[$name]['module'] = get_class($this);
			// $ShipCosts[$name]['delivery'] = $DeliveryEstimate;

		}
		return $estimate;
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
			'CustomerTransactionId' => $cart->session);

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
						'Value' => number_format($weight,1),
						'Units' => $this->settings['units']));
		
		return $_;
	}
	
	function send () {

		ini_set("soap.wsdl_cache_enabled", "0");
		$client = new SoapClient($this->wsdl, array('trace' => 1));
		$response = $client->getRates($this->request);
	    
	    if ($response->HighestSeverity == 'FAILURE' && 
			$response->HighestSeverity == 'ERROR') {

	        echo 'Error in processing transaction.'. $newline. $newline; 
	        foreach ($response->Notifications as $notification) {           
	            if(is_array($response->Notifications)) {              
	               echo $notification->Severity;
	               echo ': ';           
	               echo $notification->Message . $newline;
	            } else {
	                echo $notification . $newline;
	            }
	        } 
				
		}
		
		return $response;

	}
	
}
?>