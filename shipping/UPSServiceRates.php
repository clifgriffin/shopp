<?php
/**
 * UPSServiceRates
 * Uses UPS Online Tools to get live shipping rates based on product weight
 * INSTALLATION INSTRUCTIONS: Upload UPSServiceRates.php to 
 * your Shopp install under: .../wp-content/plugins/shopp/shipping/
 *
 * @author Jonathan Davis
 * @version 1.0.1
 * @copyright Ingenesis Limited, 3 January, 2009
 * @package shopp
 **/

require_once(SHOPP_PATH."/core/model/XMLdata.php");

class UPSServiceRates {
	var $testurl = 'https://wwwcie.ups.com/ups.app/xml/Rate';
	var $liveurl = 'https://www.ups.com/ups.app/xml/Rate';
	var $request = false;
	var $weight = 0;
	var $conversion = 1;
	var $Response = false;
	var $requiresauth = true;
	
	var $codes = array(
		"01" => "UPS Next Day Air",
		"02" => "UPS Second Day Air",
		"03" => "UPS Ground",
		"07" => "UPS Worldwide Express",
		"08" => "UPS Worldwide Expedited",
		"11" => "UPS Standard",
		"12" => "UPS Three-Day Select",
		"13" => "UPS Next Day Air Saver",
		"14" => "UPS Next Day Air Early A.M.",
		"54" => "UPS Worldwide Express Plus",
		"59" => "UPS Second Day Air A.M.",
		"65" => "UPS Saver",
		"82" => "UPS Today Standard",
		"83" => "UPS Today Dedicated Courrier",
		"84" => "UPS Today Intercity",
		"85" => "UPS Today Express",
		"86" => "UPS Today Express Saver");
	
	var $worldwide = array("07","08","11","54","65");
	var $services = array(
		"US" => array("01","02","03","07","08","11","12","13","14","54","59","65"),
		"PR" => array("01","02","03","07","08","14","54","65"),
		"CA" => array("01","02","07","08","11","12","13","14","54","65"),
		"MX" => array("07","08","54","65"),
		"PL" => array("07","08","11","54","65","82","83","84","85","86") );
	
	function UPSServiceRates () {
		global $Shopp;
		$this->settings = $Shopp->Settings->get('UPSServiceRates');
		if (!isset($this->settings['license'])) $this->settings['license'] = '';
		if (!isset($this->settings['postcode'])) $this->settings['postcode'] = '';
		if (!isset($this->settings['userid'])) $this->settings['userid'] = '';
		if (!isset($this->settings['password'])) $this->settings['password'] = '';
		
		$base = $Shopp->Settings->get('base_operations');
		$storeunits = $Shopp->Settings->get('weight_unit');
		$this->settings['country'] = $base['country'];

		$units = array("imperial" => "LBS","metric"=>"KGS");
		$this->settings['units'] = $units[$base['units']];
		if ($storeunits == 'oz') $this->conversion = 0.0625;
		if ($storeunits == 'g') $this->conversion = 0.001;

		// Select service options using base country
		if (array_key_exists($this->settings['country'],$this->services)) 
			$services = $this->services[$this->settings['country']];
		else $services = $this->worldwide;
		
		// Build the service list
		$this->settings['services'] = array();
		foreach ($services as $code) 
			$this->settings['services'][$code] = $this->codes[$code];
		
		add_action('shipping_service_settings',array(&$this,'settings'));
	}
	
	function methods (&$ShipCalc) {
		$ShipCalc->methods[get_class($this)] = __("UPS Service Rates","Shopp");
		
	}
		
	function ui () {?>
		function UPSServiceRates (methodid,table,rates) {
			table.addClass('services').empty();
			
			uniqueMethod(methodid,'<?php echo get_class($this); ?>');
			
			var services = <?php echo json_encode($this->settings['services']); ?>;
			var settings = '';
			settings += '<tr><td>';
			settings += '<input type="hidden" name="settings[shipping_rates]['+methodid+'][postcode-required]" value="true" />';

			settings += '<div class="multiple-select"><ul id="ups-services">';

			settings += '<li><input type="checkbox" name="select-all" id="ups-services-select-all" /><label for="ups-services-select-all"><strong><?php _e('Select All','Shopp'); ?></strong></label>';

			var even = true;
			
			for (var service in services) {
				var checked = '';
				even = !even;
				for (var r in rates.services) 
					if (rates.services[r] == service) checked = ' checked="checked"';
				settings += '<li class="'+((even)?'even':'odd')+'"><input type="checkbox" name="settings[shipping_rates]['+methodid+'][services][]" value="'+service+'" id="ups-service-'+service+'"'+checked+' /><label for="ups-service-'+service+'">'+services[service]+'</label></li>';
			}

			settings += '</td>';
			
			settings += '<td>';
			settings += '<div><input type="text" name="settings[UPSServiceRates][license]" id="upsrates_license" value="<?php echo $this->settings['license']; ?>" size="16" /><br /><label for="upsrates_license"><?php _e('UPS Access License Number','Shopp'); ?></label></div>';
			settings += '<div><input type="text" name="settings[UPSServiceRates][postcode]" id="upsrates_postcode" value="<?php echo $this->settings['postcode']; ?>" size="7" /><br /><label for="upsrates_postcode"><?php _e('Your postal code','Shopp'); ?></label></div>';
				
			settings += '</td>';
			settings += '<td>';
			settings += '<div><input type="text" name="settings[UPSServiceRates][userid]" id="upsrates_userid" value="<?php echo $this->settings['userid']; ?>" size="16" /><br /><label for="upsrates_userid"><?php _e('UPS User ID','Shopp'); ?></label></div>';
			settings += '<div><input type="password" name="settings[UPSServiceRates][password]" id="upsrates_password" value="<?php echo $this->settings['password']; ?>" size="16" /><br /><label for="upsrates_password"><?php _e('UPS password','Shopp'); ?></label></div>';
			settings += '</td>';
			settings += '</tr>';

			$(settings).appendTo(table);
			
			$('#ups-services-select-all').change(function () {
				if (this.checked) $('#ups-services input').attr('checked',true);
				else $('#ups-services input').attr('checked',false);
			});
			
			quickSelects();

		}

		methodHandlers.register('<?php echo get_class($this); ?>',UPSServiceRates);

		<?php		
	}
	
	function calculate (&$Cart,$fees,$rate,$column) {
		if (empty($Cart->data->Order->Shipping->postcode)) return false;
		$ShipCosts = &$Cart->data->ShipCosts;
		$weight = 0;
		foreach($Cart->shipped as $Item) $weight += (($Item->weight*$this->conversion) * $Item->quantity);

		$this->request = $this->build($Cart->session, $rate['name'], $weight, 
			$Cart->data->Order->Shipping->postcode, $Cart->data->Order->Shipping->country);
		
		$this->Response = $this->send();
		if (!$this->Response) return false;
		if ($this->Response->getElement('Error')) {
			new ShoppError($this->Response->getElementContent('ErrorDescription'),'ups_rate_error',SHOPP_ADDON_ERR);
			return false;
		}

		$estimate = false;
		$RatedShipment = $this->Response->getElement('RatedShipment');
		if (!is_array($RatedShipment)) return false;
		foreach ($RatedShipment as $rated) {
			$ServiceCode = $rated['CHILDREN']['Service']['CHILDREN']['Code']['CONTENT'];
			$TotalCharges = $rated['CHILDREN']['TotalCharges']['CHILDREN']['MonetaryValue']['CONTENT'];
			$DeliveryEstimate = $rated['CHILDREN']['GuaranteedDaysToDelivery']['CONTENT'];
			if (empty($DeliveryEstimate)) $DeliveryEstimate = "1d-5d";
			else $DeliveryEstimate .= "d";
			if (is_array($rate['services']) && in_array($ServiceCode,$rate['services'])) {
				$rate['cost'] = $TotalCharges+$fees;
				$ShipCosts[$this->codes[$ServiceCode]] = $rate;
				$ShipCosts[$this->codes[$ServiceCode]]['name'] = $this->codes[$ServiceCode];
				$ShipCosts[$this->codes[$ServiceCode]]['delivery'] = $DeliveryEstimate;
				if (!$estimate || $rate['cost'] < $estimate['cost']) $estimate = &$ShipCosts[$this->codes[$ServiceCode]];
			}
		}
		return $estimate;
	}
	
	function build ($cart,$description,$weight,$postcode,$country) {

		$_ = array('<?xml version="1.0" encoding="utf-8"?>');
		$_[] = '<AccessRequest xml:lang="en-US">';
			$_[] = '<AccessLicenseNumber>'.$this->settings['license'].'</AccessLicenseNumber>';
			$_[] = '<UserId>'.$this->settings['userid'].'</UserId>';
			$_[] = '<Password>'.$this->settings['password'].'</Password>';
		$_[] = '</AccessRequest>';
		$_[] = '<?xml version="1.0" encoding="utf-8"?>';
		$_[] = '<RatingServiceSelectionRequest xml:lang="en-US">';
		$_[] = '<Request>';
			$_[] = '<TransactionReference>';
				$_[] = '<CustomerContext>'.$cart.'</CustomerContext>';
			$_[] = '</TransactionReference>';
			$_[] = '<RequestAction>Rate</RequestAction>';
			$_[] = '<RequestOption>Shop</RequestOption>';
		$_[] = '</Request>';
		$_[] = '<PickupType><Code>01</Code></PickupType>';
		$_[] = '<Shipment>';
			$_[] = '<Description>'.$description.'</Description>';
			$_[] = '<Shipper>';
				// $_[] = '<ShipperNumber>'.$this->settings['account'].'</ShipperNumber>';
				$_[] = '<Address>';
					$_[] = '<PostalCode>'.$this->settings['postcode'].'</PostalCode>';
					$_[] = '<CountryCode>'.$this->settings['country'].'</CountryCode>';
				$_[] = '</Address>';
			$_[] = '</Shipper>';
			$_[] = '<ShipTo>';
				$_[] = '<Address>';
					$_[] = '<PostalCode>'.$postcode.'</PostalCode>';
					$_[] = '<CountryCode>'.$country.'</CountryCode>';
					$_[] = '<ResidentialAddressIndicator/>';
				$_[] = '</Address>';
			$_[] = '</ShipTo>';
			$_[] = '<Package>';
				$_[] = '<PackagingType>';
					$_[] = '<Code>02</Code>';
				$_[] = '</PackagingType>';
				$_[] = '<PackageWeight>';
					$_[] = '<UnitOfMeasurement>';
						$_[] = '<Code>'.$this->settings['units'].'</Code>';
					$_[] = '</UnitOfMeasurement>';
					$_[] = '<Weight>'.$weight.'</Weight>';
				$_[] = '</PackageWeight>   ';
			$_[] = '</Package>';
		$_[] = '</Shipment>';
		$_[] = '</RatingServiceSelectionRequest>';
		
		return join("\n",$_);
	}  
	     
	function verifyauth () {         
		$this->request = $this->build('1','Authentication test',1,'10012','US');
		$Response = $this->send();
		if ($Response->getElement('Error')) new ShoppError($Response->getElementContent('ErrorDescription'),'ups_verify_auth',SHOPP_ADDON_ERR);
	}   
	
	function send () {   
		global $Shopp;
		$connection = curl_init();
		curl_setopt($connection,CURLOPT_URL,$this->liveurl);
		curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0); 
		curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0); 
		// curl_setopt($connection, CURLOPT_VERBOSE, 1); 
		curl_setopt($connection, CURLOPT_FOLLOWLOCATION,0); 
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $this->request); 
		curl_setopt($connection, CURLOPT_TIMEOUT, 60); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_REFERER, "https://".$_SERVER['SERVER_NAME']); 
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);
		if ($error = curl_error($connection)) 
			new ShoppError($error,'ups_connection',SHOPP_COMM_ERR);
		curl_close($connection);

		// echo '<!-- '. $buffer. ' -->';		
		// echo "<pre>".htmlentities($this->request)."</pre>";
		// echo "<pre>".htmlentities($buffer)."</pre>";
		
		$Response = new XMLdata($buffer);
		return $Response;
	}
	
}
?>