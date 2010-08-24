<?php
/**
 * Canada Post
 * 
 * Uses the Canada Post Sell Online Webtools to get live shipping rates based on product weight
 * 
 * INSTALLATION INSTRUCTIONS
 * Upload USPSRates.php to your Shopp install under:  
 * ./wp-content/plugins/shopp/shipping/
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, 26 February, 2009
 * @package shopp
 * @since 1.1 dev
 * @subpackage CanadaPost
 * 
 * $Id$
 **/

class CanadaPost extends ShippingFramework implements ShippingModule {

	var $url = 'http://sellonline.canadapost.ca';

	var $xml = true;	// Requires XML parser
	var $postcode = true;

	var $weight = 0;
	var $maxweight = 30; // 30 kg
	
	/* Test URL */
	// var $url = 'http://sellonline.canadapost.ca';
	
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
				
	function __construct () {
		parent::__construct();

		$this->setup('merchantid','postcode');
	

		// Select service options using base country
		if (array_key_exists($this->base['country'],$this->services)) 
			$services = $this->services[$this->base['country']];
		
		// Build the service list
		$this->settings['services'] = $this->services;
		
		if (isset($this->rates[0])) $this->rate = $this->rates[0];
		
		add_action('shipping_service_settings',array(&$this,'settings'));
		add_action('shopp_verify_shipping_services',array(&$this,'verify'));
	}
		
	function methods () {
		// Require base of operations in Canada
		// if ($this->settings['country'] != "CA") return array(); 
		return array(__("Canada Post","Shopp"));
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
			
			$('#cpso-services-select-all').change(function () {
				if (this.checked) $('#cpso-services input').attr('checked',true);
				else $('#cpso-services input').attr('checked',false);
			});
			
			quickSelects();

		}

		methodHandlers.register('<?php echo $this->module; ?>',CanadaPost);

		<?php		
	}
	
	function init () {
		$this->weight = 0;
	}

	function calcitem ($id,$Item) {
		$this->weight += ($Item->weight*$this->conversion) * $Item->quantity;
	}
	
	function calculate ($options,$Order) {

		$request = $this->build($Order->Cart->shipped, $this->rate['name'], 
			$Order->Shipping->postcode, $Order->Shipping->country);
		
		$Response = $this->send($request);
		if (!$Response) return false;
		if ($Response->tag('error')) {
			new ShoppError($Response->content('statusMessage'),'cpc_rate_error',SHOPP_TRXN_ERR);
			return false;
		}

		$estimate = false;
		$Postage = $Response->tag('product');
		while ($rated = $Postage->each()) {
			$service = $rated->attr(false,'id'); //['ATTRS']['id'];
			$amount = $rated->content('rate'); //['CHILDREN']['rate']['CONTENT'];
			$delivery = "1d-5d";
			if ($deliveryDate = $rated->content('deliveryDate') !== false)  //['CHILDREN']['deliveryDate']['CONTENT'])
				$delivery = $this->delivery($deliveryDate);
			if (is_array($this->rate['services']) && in_array($service,$this->rate['services'])) {
				$rate['name'] = $this->services[$service];
				$rate['amount'] = $amount;
				$rate['delivery'] = $delivery;
				$options[$rate['name']] = new ShippingOption($rate);
			}
		}
		return $options;
	}
	
	function delivery ($date) {
		list($year,$month,$day) = sscanf($date,"%4d-%2d-%2d");
		$days = ceil((mktime(9,0,0,$month,$day,$year) - mktime())/86400);
		return $days.'d';
	}
	
	function build ($items,$description,$postcode,$country) {
		
		$_ = array('<?xml version="1.0"?>');
		$_[] = '<eparcel>';
			$_[] = '<language>en</language>';
			$_[] = '<ratesAndServicesRequest>';
				$_[] = '<merchantCPCID> '.$this->settings['merchantid'].' </merchantCPCID>';
				$_[] = '<fromPostalCode> '.$this->settings['postcode'].' </fromPostalCode>';
				$_[] = '<lineItems>';
				foreach ((array)$items as $id => $Item) {
					$_[] = '<item>';
					$_[] = '<quantity>'.(empty($Item->quantity)?1:$Item->quantity).'</quantity>';
					$_[] = '<weight>'.(empty($Item->weight)?1:convert_unit($Item->weight,'kg')).'</weight>';
					$_[] = '<length>'.(empty($Item->length)?1:convert_unit($Item->length,'cm')).'</length>';
					$_[] = '<width>'.(empty($Item->width)?1:convert_unit($Item->width,'cm')).'</width>';
					$_[] = '<height>'.(empty($Item->height)?1:convert_unit($Item->height,'cm')).'</height>';
					$_[] = '<description>Box '.($id+1).'</description>';
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
		return "XMLRequest=".(join("\n",apply_filters('shopp_capost_request',$_)));
	}  

	function verify () {
		if (!$this->activated()) return;
		$request = $this->build('1','Authentication test',1,'M1P1C0','CA');
		$Response = $this->send($request);
		if ($Response->tag('error')) new ShoppError($Response->content('statusMessage'),'cpc_verify_auth',SHOPP_ADDON_ERR);
	}   
	     	
	function send ($data) {
		// echo "<pre>";
		// ob_start();
		// print_r($data);
		// $content = ob_get_contents();
		// ob_end_clean();
		// echo htmlentities($data);
		// echo "</pre>";
		// exit();
		
		$response = parent::send($data,$this->url,'30000');
		return new xmlQuery($response);
		   
		// global $Shopp;
		// $connection = curl_init();
		// curl_setopt($connection,CURLOPT_URL,$this->url.":30000");
		// curl_setopt($connection, CURLOPT_PORT, 30000); // alternative port not used in some libcurl builds
		// curl_setopt($connection, CURLOPT_FOLLOWLOCATION,0); 
		// curl_setopt($connection, CURLOPT_POST, 1); 
		// curl_setopt($connection, CURLOPT_POSTFIELDS, $this->request); 
		// curl_setopt($connection, CURLOPT_TIMEOUT, 10); 
		// curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		// curl_setopt($connection, CURLOPT_REFERER, "http://".$_SERVER['SERVER_NAME']); 
		// curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		// $buffer = curl_exec($connection);
		// if ($error = curl_error($connection)) 
		// 	new ShoppError($error,'cpc_connection',SHOPP_COMM_ERR);
		// curl_close($connection);

		// echo '<!-- '. $buffer. ' -->';		
		// echo "<pre>REQUEST\n".htmlentities($this->request).BR.BR."</pre>";
		// echo "<pre>RESPONSE\n".htmlentities($buffer)."</pre>";
		// exit();

		// $Response = new XMLdata($buffer);
		// return $Response;
	}
	
	function logo () {
		return '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';
	}
	
}
?>