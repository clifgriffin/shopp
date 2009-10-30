<?php
/**
 * USPSRates
 * Uses USPS Webtools to get live shipping rates based on product weight
 * INSTALLATION INSTRUCTIONS: Upload USPSRates.php to 
 * your Shopp install under: .../wp-content/plugins/shopp/shipping/
 *
 * @author Jonathan Davis
 * @version 1.0.3
 * @copyright Ingenesis Limited, 26 February, 2009
 * @package shopp
 * 
 * $Id$
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
		"d0" => "First-Class",
		"d1" => "Priority Mail",
		"d2" => "Express Mail Hold for Pickup",
		"d3" => "Express Mail PO to Addressee",
		"d4" => "Parcel Post",
		"d5" => "Bound Printed Matter",
		"d6" => "Media Mail",
		"d7" => "Library",
		"d12" => "First-Class Postcard Stamped",
		"d13" => "Express Mail Flat-Rate Envelope",
		"d16" => "Priority Mail Flat-Rate Envelope",
		"d17" => "Priority Mail Flat-Rate Boxes",
		"d18" => "Priority Mail Keys and IDs",
		"d19" => "First-Class Keys and IDs",
		"d22" => "Priority Mail Flat-Rate Large Box",
		"d23" => "Express Mail Sunday/Holiday",
		"d25" => "Express Mail Flat-Rate Envelope Sunday/Holiday",
		"d27" => "Express Mail Flat-Rate Envelope Hold for Pickup",
		"d28" => "Priority Mail Small Flat-Rate Box",
		"i1" => "Express Mail International",
		"i2" => "Priority Mail International",
		"i4" => "Global Express Guaranteed",
		"i5" => "Global Express Guaranteed Document used",
		"i6" => "Global Express Guaranteed Non-Document Rectangular",
		"i7" => "Global Express Guaranteed Non-Document Non-Rectangular",
		"i8" => "Priority Mail International Flat Rate Envelope",
		"i9" => "Priority Mail International Flat Rate Box",
		"i10" => "Express Mail International Flat Rate Envelope",
		"i11" => "Priority Mail International Large Flat Rate Box",
		"i12" => "Global Express Guaranteed Envelope",
		"i13" => "First Class Mail International Letters",
		"i14" => "First Class Mail International Flats",
		"i15" => "First Class Mail International Package",
		"i16" => "Priority Mail International Small Flat-Rate Box",
		"i21" => "International PostCards"
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
		if ($this->settings['country'] == "US") // Require base of operations in USA
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

			settings += '<li><input type="checkbox" name="select-all" id="usps-services-select-all" /><label for="ups-services-select-all"><strong><?php echo addslashes(__('Select All','Shopp')); ?></strong></label>';

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
			settings += '<div><input type="text" name="settings[USPSRates][userid]" id="uspsrates_userid" value="<?php echo $this->settings['userid']; ?>" size="16" /><br /><label for="uspsrates_userid"><?php echo addslashes(__('USPS User ID','Shopp')); ?></label></div>';
			settings += '<div><input type="text" name="settings[USPSRates][postcode]" id="upsrates_postcode" value="<?php echo $this->settings['postcode']; ?>" size="7" /><br /><label for="upsrates_postcode"><?php echo addslashes(__('Your postal code','Shopp')); ?></label></div>';
				
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
		$type = "domestic";
		$Estimates = $this->Response->getElement('Postage');
		if (empty($Estimates)) {
			$Estimates = $this->Response->getElement('Service');
			if (!empty($Estimates)) $type = "intl";
		}
	
		if (!is_array($Estimates)) return false;
		foreach ($Estimates as $rated) {
			$DeliveryEstimate = "5d-7d";
			if ($type == "domestic") {
				$ServiceCode = substr($type,0,1).$rated['ATTRS']['CLASSID'];
				$TotalCharges = $rated['CHILDREN']['Rate']['CONTENT'];	
			}
			
			if ($type == "intl") {
				$ServiceCode = substr($type,0,1).$rated['ATTRS']['ID'];
				$TotalCharges = $rated['CHILDREN']['Postage']['CONTENT'];
				if (isset($rated['CHILDREN']['SvcCommitments']['CONTENT']))
					$DeliveryEstimate = $this->delivery_estimate($rated['CHILDREN']['SvcCommitments']['CONTENT']);

			}

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
		if ($this->settings['units'] == "oz"){
			$pounds = floor($weight / 16);
			$ounces = $weight % 16;
		}
		else{ 
			list($pounds,$ounces) = explode(".",$weight);
			$ounces = ceil(($weight-$pounds)*16);
		}

		$type = "RateV3"; // Domestic shipping rates
		if ($country != $this->settings['country']) {
			global $Shopp;
			$type = "IntlRate";	
			$countries = $Shopp->Settings->get('countries');
		}
		
		$_ = array('API='.$type.'&XML=<?xml version="1.0" encoding="utf-8"?>');
		$_[] = '<'.$type.'Request USERID="'.$this->settings['userid'].'">';
			$_[] = '<Package ID="1ST">';
				if ($type == "IntlRate") {
					$_[] = '<Pounds>'.$pounds.'</Pounds>';
					$_[] = '<Ounces>'.$ounces.'</Ounces>';
					$_[] = '<Machinable>TRUE</Machinable>';
					$_[] = '<MailType>Package</MailType>';
					$_[] = '<Country>'.$countries[$country]['name'].'</Country>';
				} else {
					$_[] = '<Service>ALL</Service>';
					$_[] = '<ZipOrigination>'.$this->settings['postcode'].'</ZipOrigination>';
					$_[] = '<ZipDestination>'.$postcode.'</ZipDestination>';
					$_[] = '<Pounds>'.$pounds.'</Pounds>';
					$_[] = '<Ounces>'.$ounces.'</Ounces>';
					$_[] = '<Size>REGULAR</Size>';
					$_[] = '<Machinable>TRUE</Machinable>';
				}
			$_[] = '</Package>';
		$_[] = '</'.$type.'Request>';

		return join("\n",$_);
	} 
	
	function delivery_estimate ($timeframe) {
		list($start,$end) = sscanf($timeframe,"%d - %d Days");
		$days = $start.'d'.(!empty($end)?'-'.$end.'d':'');
		if (empty($start)) $days = "5d-15d";
		return $days;
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
		curl_setopt($connection, CURLOPT_TIMEOUT, 10); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_REFERER, "http://".$_SERVER['SERVER_NAME']); 
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);
		if ($error = curl_error($connection)) 
			new ShoppError($error,'usps_connection',SHOPP_COMM_ERR);
		curl_close($connection);

		// echo '<!-- '. $buffer. ' -->';		
		// echo "<pre>REQUEST\n".htmlentities($this->request).BR.BR."</pre>";
		// echo "<pre>RESPONSE\n".htmlentities($buffer)."</pre>";
		// exit();
		
		$Response = new XMLdata($buffer);
		return $Response;
	}
	
}
?>