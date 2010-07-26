<?php
/**
 * UPS Service Rates
 * 
 * Uses UPS Online Tools to get live shipping rates based on product weight
 * 
 * INSTALLATION INSTRUCTIONS
 * Upload UPSServiceRates.php to your Shopp install under:
 * ./wp-content/plugins/shopp/shipping/
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, 3 January, 2009
 * @package shopp
 * @since 1.1 dev
 * @subpackage UPSServiceRates
 * 
 * $Id$
 **/

class UPSServiceRates extends ShippingFramework implements ShippingModule {

	var $url = 'https://www.ups.com/ups.app/xml/Rate';
	var $weight = 0;

	var $dimensions = true;
	var $xml = true;


	/* Test URL */
	// var $url = 'https://wwwcie.ups.com/ups.app/xml/Rate';
	
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
	
	function __construct () {
		parent::__construct();
		$this->setup('license','postcode','userid','password');
		
		$units = array("imperial" => "LBS","metric"=>"KGS");
		$this->settings['units'] = $units[$this->base['units']];
		if ($this->units == 'oz') $this->conversion = 0.0625;
		if ($this->units == 'g') $this->conversion = 0.001;

		// Select service options using base country
		if (array_key_exists($this->base['country'],$this->services)) 
			$services = $this->services[$this->base['country']];
		else $services = $this->worldwide;
		
		// Build the service list
		$this->settings['services'] = array();
		foreach ($services as $code) 
			$this->settings['services'][$code] = $this->codes[$code];
		
		if (isset($this->rates[0])) $this->rate = $this->rates[0];
		
		add_action('shipping_service_settings',array(&$this,'settings'));
	}
	
	function methods () {
		return array(__("UPS Service Rates","Shopp"));
	}
		
	function ui () {?>
		function UPSServiceRates (methodid,table,rates) {
			table.addClass('services').empty();
			
			if (!uniqueMethod(methodid,'<?php echo get_class($this); ?>')) return;
			
			var services = <?php echo json_encode($this->settings['services']); ?>;
			var settings = '';
			settings += '<tr><td>';
			settings += '<style type="text/css">#shipping-rates th.upsservicerates { background: url(data:image/png;base64,<?php echo $this->logo(); ?>) no-repeat 20px 20px; }</style>';
			settings += '<input type="hidden" name="settings[shipping_rates]['+methodid+'][postcode-required]" value="true" />';

			settings += '<div class="multiple-select"><ul id="ups-services">';

			settings += '<li><input type="checkbox" name="select-all" id="ups-services-select-all" /><label for="ups-services-select-all"><strong><?php echo addslashes(__('Select All','Shopp')); ?></strong></label>';

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
			settings += '<div><input type="text" name="settings[UPSServiceRates][license]" id="upsrates_license" value="<?php echo $this->settings['license']; ?>" size="16" /><br /><label for="upsrates_license"><?php echo addslashes(__('UPS Access License Number','Shopp')); ?></label></div>';
			settings += '<div><input type="text" name="settings[UPSServiceRates][postcode]" id="upsrates_postcode" value="<?php echo $this->settings['postcode']; ?>" size="7" /><br /><label for="upsrates_postcode"><?php echo addslashes(__('Your postal code','Shopp')); ?></label></div>';
				
			settings += '</td>';
			settings += '<td>';
			settings += '<div><input type="text" name="settings[UPSServiceRates][userid]" id="upsrates_userid" value="<?php echo $this->settings['userid']; ?>" size="16" /><br /><label for="upsrates_userid"><?php echo addslashes(__('UPS User ID','Shopp')); ?></label></div>';
			settings += '<div><input type="password" name="settings[UPSServiceRates][password]" id="upsrates_password" value="<?php echo $this->settings['password']; ?>" size="16" /><br /><label for="upsrates_password"><?php echo addslashes(__('UPS password','Shopp')); ?></label></div>';
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
	
	function init () {
		$this->weight = 0;
	}

	function calcitem ($id,$Item) {
		$this->weight += $Item->weight * $Item->quantity;
	}
	
	function calculate ($options,$Order) {
		if (empty($Order->Shipping->postcode)) {
			new ShoppError(__('A postal code for calculating shipping estimates and taxes is required before you can proceed to checkout.','Shopp','usps_postcode_required',SHOPP_ERR));
			return $options;
		}
		
		$request = $this->build(session_id(), $this->rate['name'], 
			$Order->Shipping->postcode, $Order->Shipping->country);
		
		$Response = $this->send($request);
		
		if (!$Response) return false;
		if ($Response->tag('Error')) {
			new ShoppError($Response->content('ErrorDescription'),'ups_rate_error',SHOPP_ADDON_ERR);
			return false;
		}

		$estimate = false;
		if (!$RatedShipment = $Response->tag('RatedShipment')) return false;
		while ($rated = $RatedShipment->each()) {
			$service = $rated->content('Service > Code');
			$amount = $rated->content('TotalCharges > MonetaryValue:first');
			if(floatval($amount) == 0) continue;
			$delivery = $rated->content('GuaranteedDaysToDelivery');
			if (empty($delivery)) $delivery = "1d-5d";
			else $delivery .= "d";
			if (is_array($this->rate['services']) && in_array($service,$this->rate['services'])) {
				$rate = array();
				$rate['name'] = $this->codes[$service];
				$rate['amount'] = $amount;
				$rate['delivery'] = $delivery;
				$options[$rate['name']] = new ShippingOption($rate);
			}
		}
		return $options;
	}
	
	function build ($cart,$description,$postcode,$country) {

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
		$_[] = '<PickupType><Code>03</Code></PickupType>';
		$_[] = '<Shipment>';
			$_[] = '<Description>'.$description.'</Description>';
			$_[] = '<Shipper>';
				$_[] = '<Address>';
					$_[] = '<PostalCode>'.$this->settings['postcode'].'</PostalCode>';
					$_[] = '<CountryCode>'.$this->base['country'].'</CountryCode>';
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
					$_[] = '<Weight>'.number_format(($this->weight < 1)?1:$this->weight,1,'.','').'</Weight>';
				$_[] = '</PackageWeight>   ';
			$_[] = '</Package>';
		$_[] = '</Shipment>';
		$_[] = '</RatingServiceSelectionRequest>';
		
		return join("\n",apply_filters('shopp_ups_request',$_));
	}  
	     
	function verify () {
		if (!$this->activated()) return;
		$this->weight = 1;
		$request = $this->build('1','Authentication test','10012','US');
		$Response = $this->send($request);
		if ($Response->tag('Error')) new ShoppError($Response->content('ErrorDescription'),'ups_verify_auth',SHOPP_ADDON_ERR);
	}   
	
	function send ($data) {  
		$response = parent::send($data,$this->url);
		return new xmlQuery($response);
	}
	
	function logo () {
		return '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';
	}
	
}
?>