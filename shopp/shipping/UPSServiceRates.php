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

require_once(SHOPP_PATH."/core/model/XMLdata.php");

class UPSServiceRates extends ShippingFramework implements ShippingModule {
	var $testurl = 'https://wwwcie.ups.com/ups.app/xml/Rate';
	var $liveurl = 'https://www.ups.com/ups.app/xml/Rate';
	var $request = false;
	var $weight = 0;
	var $conversion = 1;
	var $Response = false;
	
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
		$this->weight += ($Item->weight*$this->conversion) * $Item->quantity;
	}
	
	function calculate ($options,$Order) {
		if (empty($Order->Shipping->postcode)) {
			new ShoppError(__('A postal code for calculating shipping estimates and taxes is required before you can proceed to checkout.','Shopp','usps_postcode_required',SHOPP_ERR));
			return $options;
		}
		
		$this->request = $this->build(session_id(), $this->rate['name'], 
			$Order->Shipping->postcode, $Order->Shipping->country);
		
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
			$service = $rated['CHILDREN']['Service']['CHILDREN']['Code']['CONTENT'];
			$amount = $rated['CHILDREN']['TotalCharges']['CHILDREN']['MonetaryValue']['CONTENT'];
			if(floatval($amount) == 0) continue;
			$delivery = $rated['CHILDREN']['GuaranteedDaysToDelivery']['CONTENT'];
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
		
		return join("\n",$_);
	}  
	     
	function verify () {
		if (!$this->activated()) return;
		$this->weight = 1;
		$this->request = $this->build('1','Authentication test','10012','US');
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
		curl_setopt($connection, CURLOPT_TIMEOUT, 10); 
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
	
	function logo () {
		return 'iVBORw0KGgoAAAANSUhEUgAAACIAAAAoCAMAAAHbBegAAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAwBQTFRFelMaiWlGKhUBCgQB//e33ZIWVzUNvoQk/dWKyZpSWToS/+eZ0IkWglwn/+mM8/PzrpV02ahUmnVGuYk9dFMm6ahF/8Vj26VJsn4iypdH67tr6cmHxIIYiFkV1JUw67NXsXcaNyQMEggB/9uTrKaZ/btJ7Z0Zakki0Khk45oh/9qN+bM7NRkAm4116r1z+cx/yosjuJtm+tWR/9Z766Qr29ra/s13rHogtrOr/+mqs5h6/tKB7aU6yKh6/+ylx8bEjWMl/8pi5pgZ5J000bRsy5Q89KQe/++88aYq+7xS1sW0cksY/cRpp4dY8dqj/8NT4pUW8K1K7sJ32LyFl2Yn/7pDqHEblINmzLRqmGYXoHg29LJD//Ot8aIg4pcb66Q2RCIB/8Zd4+Pj+bVC7bNO7aIlx44y/8hZSykGWUEak3RT8bFSpXQh1tXU//XFvpdX+/v7gmE9/shsa0cPsayj6urq+M+J5JwtYD0TY0EV+sZ0rI5sfFo0/8xsmnpH9a86/+yweFYx3JUu6ZoYz6tkHRQJ/+229asv8qsy0JIsw4ou1o8dQisK6p4pkW5F2pUk8MZ8SjYYuqKL9q42w4km/8ppTisHyLOfppJv25kr77lhmntcjGo8/+qw/89wwYYr8as137Nhf148p5yGUS8JTjMMZkMZpJ6SaEccRCgH1ZAmoYNkb0wnzJ5b9rpc47tnSCYD8KU1on9W8q04Hg4APB0A/+Cbv5RL/+ef//OMnHlQcVAe051F0M/NzLWg9rRP5K1bnpSDwKeOwKyXx7SA3KA8Qi8U4ODg+NOOq6GEzY8o/8FQ4bZj4rVr27Jw7bhjnns/t4Uxr6J976o9uI9N/b9fsI1Ux4cnz61ooWkR6J4hnH9h/+Gfe1APvLm1pncrf1MRrnghrH0wvqqU3pkn558z+MJq76cuun4ex6Vkwaptz6Jf5qIv+sh3/8tyh2EyimU2jGc8kGw1o4Zqp41fqophkXlS3a9ckHxbuJ2AtXws+a0yc1Et/96HAAAA////ZaChQgAAAQB0Uk5T////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////AFP3ByUAAAT/SURBVHjaYvify2nxnyFCgG2LBIPLHAcPc4aXr11TzRn+b7n77z9AADH8F7j2nyGvcasSg3JF6D8Gx2nPWRkmb2FmBwgAMgDN/wBYgNxyRUupIYsARMNqpg0D/qUYANg7DQZAY/4FNwA1pJYC4CL+/gwA///HMP4nV///AgjIUsnfPoP/PwN3m/X+ELatPQw7gvtWZDBtYWa4PamQS11J8R8Do+82MyWHyGMMNX0ctcwzeR4yVMxy9N/TysDK4MqWUM/M/O8fw/+VTLeARn8DGjh/y7/Y//8BAojhv4U0yw7rZ9mizs7O5b0mvvwMEdzu7pavG9Ur2DISdIA2SDBE2P30qi5cUzFtK9MWJSXmf0DP9mlV922rNBbzD/13MS6Ax5zBpVBr13tlNrMQpeRT5XG7/5kztIi8+8hgtsTR4Z+2xL9/L5xyGAS8HTw9XXVUL5tU/LvJysN8i+H/0YwNG7ZueX5XCeS8fwb3gA77f7ETyAOCf5luy///B4kAQdKjODVdCBMggEAi/BaLDfO3X/myeC9YhP9STIyW2bpAG5AnUjtL/jPstGyz1DK7KvpLtmLJSsWVS6cw8DW4t50QWrOMa8kGkD/+sYNEfq5etKZiCcifSmCRYK9DWY3L2BLAHmUGikhNeizA2Mi2hlcRyOdqBYqsl+KYfWKChk+6qoNSrJOaEjvDQj0jRqMJr+cpXdQWufXvH1Dku96JRScmrK1VyqyfqNkNMllQSv+PvhTnVKXMBa2ZTs0gkVlV6s/OS6YrfeoAejagEigiKZYvJu+qOmfzk8ywHFZFdgZPNnHPFIa06AUGzEw8rLKtdQziGWxA98+f/I8ZHGIS9gx7j+psSNB54cEMCrV/rTzAENNNU2LaonALHIiKsQWgMCz5XfkPzGc+pQANw/+6HsX//rUayJjCQhUIDjppPjSFMAECDCxiWnLyiPTfv8bzxDz3B9rYbASBp3LCX1RywUosXvHFxDQ0WG7TsjbzWZcdGHjZJv6pc1PTKiDo14xNFVZhiODjtmtzb5t7R1nL7IBwyJoKtiUZGxIStgLDfAswMf2TYAcq2dHgnpfnZ7uJcVHNH/VlICVgFSAloAQHVsLiZzt9utVsRqCKSi6ggq0gaaA82N9gJTesvLz2/VzN2LhmRSUXOPVCpP+BAVCJy/pv58qkBT4LSZ/QWzbnbJFjur8oL9CC1vuazXHdD8BKzjDqKRt9zZpjtG1CxWuzWuFl91WjNytOfK7JU7w0UkYJpGRhIVDJpsKPQCVca83mOQCzYai2hIiHpoEiyCJzdoYb32ukOMqEGt+XaQWxrZ1X66DEzJW8eWbr7tQ41uaHrSAlLYKN583e9q3hrbI+v4RzqiNQiXryglNbgFESGcAadyyHnUFDXF2+qEiIa82zd/JLJBPTef8p7uloZ1Bc+u8fs0SAUwVQiWFKBa9nYqKjY6JjSobr6ejk0MkdwGhd5tHcHMCqJsts8IhBxd9hCVuFN4M3V4KOTleyduTKlVv+gQKFSXFl679/mbG6DP9nqDJsnbZkybQNwEBnX9D+gBkarOCgm+hWB0oei+u7dLZs3QqKuBcmqXfB6Q0StEr31SCl1P8C3TfxDFuYgeGuuLKHCaZCabdmwA9+RCIr1e1qv86l9A+Szf8xZ7K7yYQfR0p1EHBclz22+wFTK29/7ItwU4Q4khIQ4Fe5ELW8FFUMAGhZg3RNJeYTAAAAAElFTkSuQmCC';
	}
	
}
?>