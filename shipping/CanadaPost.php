<?php
/**
 * Canada Post
 * Uses the Canada Post Sell Online Webtools to get live shipping rates based on product weight
 * INSTALLATION INSTRUCTIONS: Upload USPSRates.php to 
 * your Shopp install under: .../wp-content/plugins/shopp/shipping/
 *
 * @author Jonathan Davis
 * @version 1.0.1
 * @copyright Ingenesis Limited, 26 February, 2009
 * @package shopp
 * 
 * $Id$
 **/

require_once(SHOPP_PATH."/core/model/XMLdata.php");

class CanadaPost {
	var $testurl = 'http://sellonline.canadapost.ca';
	var $url = 'http://sellonline.canadapost.ca';
	var $request = false;
	var $weight = 0;
	var $conversion = 1;
	var $Response = false;
	var $requiresauth = true;
	var $maxweight = 30; // 30 kg
	
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
				
	function CanadaPost () {
		global $Shopp;
		$this->settings = $Shopp->Settings->get('CanadaPost');
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
			$ShipCalc->methods[get_class($this)] = __("Canada Post","Shopp");
	}
		
	function ui () {?>
		function CanadaPost (methodid,table,rates) {
			table.addClass('services').empty();
			
			if (!uniqueMethod(methodid,'<?php echo get_class($this); ?>')) return;
			
			var services = <?php echo json_encode($this->settings['services']); ?>;
			var settings = '';
			settings += '<tr><td>';
			settings += '<style type="text/css">#shipping-rates th.canadapost { background: url(data:image/png;base64,<?php echo $this->logo(); ?>) no-repeat 20px 25px; }</style>';
			settings += '<input type="hidden" name="settings[shipping_rates]['+methodid+'][postcode-required]" value="true" />';

			settings += '<div class="multiple-select"><ul id="cpso-services">';

			settings += '<li><input type="checkbox" name="select-all" id="cpso-services-select-all" /><label for="cpso-services-select-all"><strong><?php echo addslashes(__('Select All','Shopp')); ?></strong></label>';

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
			settings += '<div><input type="text" name="settings[CanadaPost][merchantid]" id="cpso_merchantid" value="<?php echo $this->settings['merchantid']; ?>" size="16" /><br /><label for="cpso_merchantid"><?php echo addslashes(__('Canada Post merchant ID','Shopp')); ?></label></div>';
			settings += '<div><input type="text" name="settings[CanadaPost][postcode]" id="cpso_postcode" value="<?php echo $this->settings['postcode']; ?>" size="7" /><br /><label for="cpso_postcode"><?php echo addslashes(__('Your postal code','Shopp')); ?></label></div>';
				
			settings += '</td><td width="33%">&nbsp;</td>';
			settings += '</tr>';

			$(settings).appendTo(table);
			
			$('#usps-services-select-all').change(function () {
				if (this.checked) $('#usps-services input').attr('checked',true);
				else $('#usps-services input').attr('checked',false);
			});
			
			quickSelects();

		}

		methodHandlers.register('<?php echo get_class($this); ?>',CanadaPost);

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
	
	function build ($cart,$description,$weight,$postcode,$country) {
		
		$_ = array('<?xml version="1.0"?>');
		$_[] = '<eparcel>';
			$_[] = '<language>en</language>';
			$_[] = '<ratesAndServicesRequest>';
				$_[] = '<merchantCPCID> '.$this->settings['merchantid'].' </merchantCPCID>';
				$_[] = '<fromPostalCode> '.$this->settings['postcode'].' </fromPostalCode>';
				$_[] = '<lineItems>';
					if (is_array($cart->contents) && !empty($cart->contents)) {
						$items = array();
						$itemid = 0;
						$items[$itemid] = array('weight'=>0);
						foreach ($cart->contents as $product) {
							$weight = ($product->weight*$product->quantity) > 0 ? 
								($product->weight*$product->quantity):1;
							if ($this->settings['units'] == "g")
								$weight = $weight/1000;
							if ($items[$itemid]['weight'] + $weight > $this->maxweight) {
								$items[$itemid++] = array('weight'=>$weight);
							} else $items[$itemid]['weight'] += $weight;
						}
						foreach ($items as $id => $item) {
								$_[] = '<item>';
								$_[] = '<quantity>1</quantity>';
								$_[] = '<weight>'.number_format($item['weight'],3,'.','').'</weight>';
								$_[] = '<length>1</length>';
								$_[] = '<width>1</width>';
								$_[] = '<height>1</height>';
								$_[] = '<description>Box '.($id+1).'</description>';
								$_[] = '<readyToShip/>';
								$_[] = '</item>';
						}						
					} else {
						$weight = ($weight*$quantity) > 0?number_format(($weight*$quantity),3,'.',''):1;
						$_[] = '<item>';
						$_[] = '<quantity>1</quantity>';
						$_[] = '<weight>'.$weight.'</weight>';
						$_[] = '<length>1</length>';
						$_[] = '<width>1</width>';
						$_[] = '<height>1</height>';
						$_[] = '<description>'.htmlentities($description).'</description>';
						$_[] = '<readyToShip/>';
						$_[] = '</item>';
					}
				$_[] = '</lineItems>';
				
				// $_[] = '<city>'.'</city>';
				$_[] = '<provOrState> '.' </provOrState>';
				$_[] = '<country>'.$country.'</country>';
				$_[] = '<postalCode>'.$postcode.'</postalCode>';
		
			$_[] = '</ratesAndServicesRequest>';
		$_[] = '</eparcel>';
		// echo "<pre>"; print_r($_); echo "</pre>";
		// exit();
		return "XMLRequest=".urlencode(join("\n",$_));
	}  

	function verifyauth () {         
		$this->request = $this->build('1','Authentication test',1,'M1P1C0','CA');
		$Response = $this->send();
		if ($Response->getElement('error')) new ShoppError($Response->getElementContent('statusMessage'),'cpc_verify_auth',SHOPP_ADDON_ERR);
	}   
	     	
	function send () {   
		global $Shopp;
		$connection = curl_init();
		curl_setopt($connection,CURLOPT_URL,$this->url.":30000");
		curl_setopt($connection, CURLOPT_PORT, 30000); // alternative port not used in some libcurl builds
		curl_setopt($connection, CURLOPT_FOLLOWLOCATION,0); 
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $this->request); 
		curl_setopt($connection, CURLOPT_TIMEOUT, 10); 
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
		// exit();

		$Response = new XMLdata($buffer);
		return $Response;
	}
	
	function logo () {
		return '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';
	}
	
}
?>