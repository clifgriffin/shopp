<?php
/**
 * CAPostSellOnline
 * Uses the Canada Post Sell Online Webtools to get live shipping rates based on product weight
 * INSTALLATION INSTRUCTIONS: Upload USPSRates.php to 
 * your Shopp install under: .../wp-content/plugins/shopp/shipping/
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 26 February, 2009
 * @package shopp
 **/

require_once(SHOPP_PATH."/core/model/XMLdata.php");

class CAPostSellOnline {
	var $testurl = 'http://sellonline.canadapost.ca';
	var $url = 'http://sellonline.canadapost.ca';
	var $request = false;
	var $weight = 0;
	var $conversion = 1;
	var $Response = false;
	var $requiresauth = true;
	
	var $services = array(
		// Domestic Products
		"1010" => "Regular",
		"1020" => "Expedited",
		"1030" => "XpressPost",
		"1040" => "Priority Courier",
		
		// US Products
		"2005" => "Small Packets Surface USA",
		"2015" => "Small Packets Air USA",
		"2020" => "Expedited US Business Contract",
		"2025" => "Expedited US Commercial",
		"2030" => "XpressPost USA",
		"2040" => "Priority Worldwide USA",
		"2050" => "Priority Worldwide Pak USA",
		
		// International Products
		"3005" => "Small Packets Surface International",
		"3010" => "Surface International",
		"3015" => "Small Packets Air International",
		"3020" => "Air International",
		"3025" => "XpressPost International",
		"3040" => "Priority Worldwide International",
		"3050" => "Priority Worldwide Pak International"
	);
				
	function CAPostSellOnline () {
		global $Shopp;
		$this->settings = $Shopp->Settings->get('CAPostSellOnline');
		if (!isset($this->settings['merchantid'])) $this->settings['merchantid'] = '';
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
		if ($this->settings['country'] == "CA") // Require base of operations in Canada
			$ShipCalc->methods[get_class($this)] = __("Sell Online","Shopp");
	}
		
	function ui () {?>
		function CAPostSellOnline (methodid,table,rates) {
			table.addClass('services').empty();
			
			uniqueMethod(methodid,'<?php echo get_class($this); ?>');
			
			var services = <?php echo json_encode($this->settings['services']); ?>;
			var settings = '';
			settings += '<tr><td>';
			settings += '<input type="hidden" name="settings[shipping_rates]['+methodid+'][postcode-required]" value="true" />';

			settings += '<div class="multiple-select"><ul id="cpso-services">';

			settings += '<li><input type="checkbox" name="select-all" id="cpso-services-select-all" /><label for="cpso-services-select-all"><strong><?php _e('Select All','Shopp'); ?></strong></label>';

			var even = true;
			
			for (var service in services) {
				var checked = '';
				even = !even;
				for (var r in rates.services) 
					if (rates.services[r] == service) checked = ' checked="checked"';
				settings += '<li class="'+((even)?'even':'odd')+'"><input type="checkbox" name="settings[shipping_rates]['+methodid+'][services][]" value="'+service+'" id="cpso-service-'+service+'"'+checked+' /><label for="cpso-service-'+service+'">'+services[service]+'</label></li>';
			}

			settings += '</td>';
			
			settings += '<td>';
			settings += '<div><input type="text" name="settings[CAPostSellOnline][merchantid]" id="cpso_merchantid" value="<?php echo $this->settings['merchantid']; ?>" size="16" /><br /><label for="cpso_merchantid"><?php _e('Canada Post merchant ID','Shopp'); ?></label></div>';
			settings += '<div><input type="text" name="settings[CAPostSellOnline][postcode]" id="cpso_postcode" value="<?php echo $this->settings['postcode']; ?>" size="7" /><br /><label for="cpso_postcode"><?php _e('Your postal code','Shopp'); ?></label></div>';
				
			settings += '</td><td width="33%">&nbsp;</td>';
			settings += '</tr>';

			$(settings).appendTo(table);
			
			$('#usps-services-select-all').change(function () {
				if (this.checked) $('#usps-services input').attr('checked',true);
				else $('#usps-services input').attr('checked',false);
			});
			
			quickSelects();

		}

		methodHandlers.register('<?php echo get_class($this); ?>',CAPostSellOnline);

		<?php		
	}
	
	function calculate (&$Cart,$fees,$rate,$column) {
		if (empty($Cart->data->Order->Shipping->postcode)) return false;
		$ShipCosts = &$Cart->data->ShipCosts;
		$weight = 0;
		foreach($Cart->shipped as $Item) $weight += (($Item->weight*$this->conversion) * $Item->quantity);

		$this->request = $this->build($Cart, $rate['name'], $weight, 
			$Cart->data->Order->Shipping->postcode, $Cart->data->Order->Shipping->country);
		
		$this->Response = $this->send();
		if (!$this->Response) return false;
		if ($this->Response->getElement('error')) {
			new ShoppError($this->Response->getElementContent('statusMessage'),'cpc_rate_error',SHOPP_TRXN_ERR);
			return false;
		}

		$estimate = false;
		$Postage = $this->Response->getElement('product');
		
		if (!is_array($Postage)) return false;
		foreach ($Postage as $rated) {
			$ServiceCode = $rated['ATTRS']['id'];
			$TotalCharges = $rated['CHILDREN']['rate']['CONTENT'];
			$DeliveryEstimate = "1d-5d";
			if (isset($rated['CHILDREN']['deliveryDate']['CONTENT'])) 
				$DeliveryEstimate = $this->delivery_estimate($rated['CHILDREN']['deliveryDate']['CONTENT']);
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

	function delivery_estimate ($date) {
		list($year,$month,$day) = sscanf($date,"%4d-%2d-%2d");
		$days = ceil((mktime(9,0,0,$month,$day,$year) - mktime())/86400);
		return $days.'d';
	}
	
	function build (&$cart,$description,$weight,$postcode,$country) {
		
		$_ = array('<?xml version="1.0"?>');
		$_[] = '<eparcel>';
			$_[] = '<language>en</language>';
			$_[] = '<ratesAndServicesRequest>';
				$_[] = '<merchantCPCID> '.$this->settings['merchantid'].' </merchantCPCID>';
				$_[] = '<fromPostalCode> '.$this->settings['postcode'].' </fromPostalCode>';
				$_[] = '<lineItems>';
					$_[] = '<item>';
					if (isset($cart->contents)) {
						foreach ($cart->contents as $item) {
							$weight = $item->weight > 0?number_format($item->weight,3):1;
							if ($this->settings['units'] == "g")
								$weight = $weight/1000;
							$_[] = '<quantity>'.$item->quantity.'</quantity>';
							$_[] = '<weight>'.$weight.'</weight>';
							$_[] = '<length>50</length>';
							$_[] = '<width>50</width>';
							$_[] = '<height>50</height>';
							$_[] = '<description>'.htmlentities($item->name).'</description>';
							$_[] = '<readyToShip/>';
						}
					} else {
						$weight = $weight > 0?number_format($item->weight,3):1;
						$_[] = '<quantity>1</quantity>';
						$_[] = '<weight>'.$weight.'</weight>';
						$_[] = '<length>1</length>';
						$_[] = '<width>1</width>';
						$_[] = '<height>1</height>';
						$_[] = '<description>'.htmlentities($description).'</description>';
						$_[] = '<readyToShip/>';
					}
					$_[] = '</item>';
				$_[] = '</lineItems>';
				
				// $_[] = '<city>'.'</city>';
				$_[] = '<provOrState> '.' </provOrState>';
				$_[] = '<country>'.$country.'</country>';
				$_[] = '<postalCode>'.$postcode.'</postalCode>';
		
			$_[] = '</ratesAndServicesRequest>';
		$_[] = '</eparcel>';

		return join("\n",$_);
	}  

	function verifyauth () {         
		$this->request = $this->build('1','Authentication test',1,'M1P1C0','CA');
		$Response = $this->send();
		if ($Response->getElement('error')) new ShoppError($Response->getElementContent('statusMessage'),'cpc_verify_auth',SHOPP_ADDON_ERR);
	}   
	     	
	function send () {   
		global $Shopp;
		$connection = curl_init();
		curl_setopt($connection,CURLOPT_URL,$this->url);
		curl_setopt($connection, CURLOPT_PORT, 30000); 
		curl_setopt($connection, CURLOPT_FOLLOWLOCATION,0); 
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $this->request); 
		curl_setopt($connection, CURLOPT_TIMEOUT, 60); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_REFERER, "http://".$_SERVER['SERVER_NAME']); 
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);
		if ($error = curl_error($connection)) 
			new ShoppError($error,'cpc_connection',SHOPP_COMM_ERR);
		curl_close($connection);

		// echo '<!-- '. $buffer. ' -->';		
		// echo "<pre>REQUEST\n".htmlentities($this->request).BR.BR."</pre>";
		// echo "<pre>RESPONSE\n".htmlentities($buffer)."</pre>";
		
		$Response = new XMLdata($buffer);
		return $Response;
	}
	
}
?>