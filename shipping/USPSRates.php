<?php
/**
 * USPSRates
 * Uses USPS Webtools to get live shipping rates based on product weight
 * INSTALLATION INSTRUCTIONS: Upload USPSRates.php to 
 * your Shopp install under: .../wp-content/plugins/shopp/shipping/
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 26 February, 2009
 * @package shopp
 **/

require_once(SHOPP_PATH."/core/model/XMLdata.php");

class USPSRates {
	var $testurl = 'http://testing.shippingapis.com/ShippingAPITest.dll';
	var $liveurl = 'http://production.shippingapis.com/ShippingAPI.dll';
	var $request = false;
	var $weight = 0;
	var $conversion = 1;
	var $Response = false;
	var $requiresauth = true;
	
	var $services = array(
		"0" => "First-Class",
		"1" => "Priority Mail",
		"2" => "Express Mail Hold for Pickup",
		"3" => "Express Mail PO to Addressee",
		"4" => "Parcel Post",
		"5" => "Bound Printed Matter",
		"6" => "Media Mail",
		"7" => "Library",
		"12" => "First-Class Postcard Stamped",
		"13" => "Express Mail Flat-Rate Envelope",
		"16" => "Priority Mail Flat-Rate Envelope",
		"17" => "Priority Mail Flat-Rate Boxes",
		"18" => "Priority Mail Keys and IDs",
		"19" => "First-Class Keys and IDs",
		"22" => "Priority Mail Flat-Rate Large Box",
		"23" => "Express Mail Sunday/Holiday",
		"25" => "Express Mail Flat-Rate Envelope Sunday/Holiday",
		"27" => "Express Mail Flat-Rate Envelope Hold for Pickup",
		"28" => "Priority Mail Small Flat-Rate Box"
	);
				
	function USPSRates () {
		global $Shopp;
		$this->settings = $Shopp->Settings->get('USPSRates');
		if (!isset($this->settings['userid'])) $this->settings['userid'] = '';
		if (!isset($this->settings['postcode'])) $this->settings['postcode'] = '';
		
		$base = $Shopp->Settings->get('base_operations');
		$this->settings['country'] = $base['country'];
		$this->settings['units'] = $Shopp->Settings->get('weight_unit');

		// Select service options using base country
		if (array_key_exists($this->settings['country'],$this->services)) 
			$services = $this->services[$this->settings['country']];
		
		// Build the service list
		$this->settings['services'] = $this->services;
		
		add_action('shipping_service_settings',array(&$this,'settings'));
	}
	
	function methods (&$ShipCalc) {
		$ShipCalc->methods[get_class($this)] = __("USPS Rates","Shopp");
		
	}
		
	function ui () {?>
		function USPSRates (methodid,table,rates) {
			table.addClass('services').empty();
			
			uniqueMethod(methodid,'<?php echo get_class($this); ?>');
			
			var services = <?php echo json_encode($this->settings['services']); ?>;
			var settings = '';
			settings += '<tr><td>';
			settings += '<input type="hidden" name="settings[shipping_rates]['+methodid+'][postcode-required]" value="true" />';

			settings += '<div class="multiple-select"><ul id="usps-services">';

			settings += '<li><input type="checkbox" name="select-all" id="usps-services-select-all" /><label for="ups-services-select-all"><strong><?php _e('Select All','Shopp'); ?></strong></label>';

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
			settings += '<div><input type="text" name="settings[USPSRates][userid]" id="uspsrates_userid" value="<?php echo $this->settings['userid']; ?>" size="16" /><br /><label for="uspsrates_userid"><?php _e('USPS User ID','Shopp'); ?></label></div>';
			settings += '<div><input type="text" name="settings[USPSRates][postcode]" id="upsrates_postcode" value="<?php echo $this->settings['postcode']; ?>" size="7" /><br /><label for="upsrates_postcode"><?php _e('Your postal code','Shopp'); ?></label></div>';
				
			settings += '</td><td width="33%">&nbsp;</td>';
			settings += '</tr>';

			$(settings).appendTo(table);
			
			$('#usps-services-select-all').change(function () {
				if (this.checked) $('#usps-services input').attr('checked',true);
				else $('#usps-services input').attr('checked',false);
			});
			
			quickSelects();

		}

		methodHandlers.register('<?php echo get_class($this); ?>',USPSRates);

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
			new ShoppError($this->Response->getElementContent('Description'),'usps_rate_error',SHOPP_TRXN_ERR);
			return false;
		}

		$estimate = false;
		$Postage = $this->Response->getElement('Postage');
		
		if (!is_array($Postage)) return false;
		foreach ($Postage as $rated) {
			$ServiceCode = $rated['ATTRS']['CLASSID'];
			$TotalCharges = $rated['CHILDREN']['Rate']['CONTENT'];
			$DeliveryEstimate = "1d-5d";
			if (is_array($rate['services']) && in_array($ServiceCode,$rate['services'])) {
				$rate['cost'] = $TotalCharges+$fees;
				$ShipCosts[$this->services[$ServiceCode]] = $rate;
				$ShipCosts[$this->services[$ServiceCode]]['name'] = $this->services[$ServiceCode];
				$ShipCosts[$this->services[$ServiceCode]]['delivery'] = $DeliveryEstimate;
				if (!$estimate || $rate['cost'] < $estimate['cost']) $estimate = &$ShipCosts[$this->services[$ServiceCode]];
			}
		}
		return $estimate;
	}
	
	function build ($cart,$description,$weight,$postcode,$country) {
		$weight = number_format($weight,3);
		if ($this->settings['units'] == "oz")
			$pounds = $weight/16;
		list($pounds,$ounces) = split("\.",$weight);
		$ounces = ceil($ounces*16);
		
		$_ = array('API=RateV3&XML=<?xml version="1.0" encoding="utf-8"?>');
		$_[] = '<RateV3Request USERID="'.$this->settings['userid'].'">';
			$_[] = '<Package ID="1ST">';
				$_[] = '<Service>ALL</Service>';
				$_[] = '<ZipOrigination>'.$this->settings['postcode'].'</ZipOrigination>';
				$_[] = '<ZipDestination>'.$postcode.'</ZipDestination>';
				$_[] = '<Pounds>'.$pounds.'</Pounds>';
				$_[] = '<Ounces>'.$ounces.'</Ounces>';
				$_[] = '<Size>REGULAR</Size>';
				$_[] = '<Machinable>TRUE</Machinable>';
			$_[] = '</Package>';
		$_[] = '</RateV3Request>';

		return join("\n",$_);
	}  

	function verifyauth () {         
		$this->request = $this->build('1','Authentication test',1,'10022','US');
		$Response = $this->send();
		if ($Response->getElement('Error')) new ShoppError($Response->getElementContent('Description'),'usps_verify_auth',SHOPP_ADDON_ERR);
	}   
	     	
	function send () {   
		global $Shopp;
		$connection = curl_init();
		curl_setopt($connection,CURLOPT_URL,$this->liveurl);
		curl_setopt($connection, CURLOPT_FOLLOWLOCATION,0); 
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $this->request); 
		curl_setopt($connection, CURLOPT_TIMEOUT, 60); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_REFERER, "http://".$_SERVER['SERVER_NAME']); 
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);
		if ($error = curl_error($connection)) 
			new ShoppError($error,'usps_connection',SHOPP_COMM_ERR);
		curl_close($connection);

		// echo '<!-- '. $buffer. ' -->';		
		// echo "<pre>".htmlentities($this->request)."</pre>";
		// echo "<pre>".htmlentities($buffer)."</pre>";
		
		$Response = new XMLdata($buffer);
		return $Response;
	}
	
}
?>