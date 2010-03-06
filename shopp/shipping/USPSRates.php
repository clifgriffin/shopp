<?php
/**
 * USPS Rates
 * 
 * Uses USPS Webtools to get live shipping rates based on product weight
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
 * @subpackage USPSRates
 * 
 * $Id$
 **/

require_once(SHOPP_PATH."/core/model/XMLdata.php");

class USPSRates extends ShippingFramework implements ShippingModule {

	var $testurl = 'http://testing.shippingapis.com/ShippingAPITest.dll';
	var $liveurl = 'http://production.shippingapis.com/ShippingAPI.dll';
	var $request = false;
	var $weight = 0;
	var $conversion = 1;
	var $Response = false;
	var $postcode = true;
	
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
				
	function __construct () {
		parent::__construct();

		$this->setup('userid','postcode');

		// Select service options using base country
		if (array_key_exists($this->base['country'],$this->services)) 
			$services = $this->services[$this->base['country']];
		
		// Build the service list
		$this->settings['services'] = $this->services;
		if (isset($this->rates[0])) $this->rate = $this->rates[0];

		add_action('shipping_service_settings',array(&$this,'settings'));
		add_action('shopp_verify_shipping_services',array(&$this,'verify'));
	}
	
	function init () {
		$this->weight = 0;
	}
	
	function calcitem ($id,$Item) {
		$this->weight += (($Item->weight*$this->conversion) * $Item->quantity);
	}
	
	function methods () {
		if ($this->base['country'] != "US") return array(); // Require base of operations in USA
		return array(__("USPS Rates","Shopp"));
	}
		
	function ui () {?>
		function USPSRates (methodid,table,rates) {
			table.addClass('services').empty();
			
			if (!uniqueMethod(methodid,'<?php echo get_class($this); ?>')) return;
			
			var services = <?php echo json_encode($this->settings['services']); ?>;
			var settings = '';
			settings += '<tr><td>';
			settings += '<style type="text/css">#shipping-rates th.uspsrates { background: url(data:image/png;base64,<?php echo $this->logo(); ?>) no-repeat 20px 25px; }</style>';
			settings += '<input type="hidden" name="settings[shipping_rates]['+methodid+'][postcode-required]" value="true" />';

			settings += '<div class="multiple-select"><ul id="usps-services">';

			settings += '<li><input type="checkbox" name="select-all" id="usps-services-select-all" /><label for="usps-services-select-all"><strong><?php echo addslashes(__('Select All','Shopp')); ?></strong></label>';

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
			new ShoppError($this->Response->getElementContent('Description'),'usps_rate_error',SHOPP_TRXN_ERR);
			return false;
		}

		$estimate = false;
		$type = "domestic";
		$estimates = $this->Response->getElement('Postage');
		if (empty($estimates)) {
			$estimates = $this->Response->getElement('Service');
			if (!empty($estimates)) $type = "intl";
		}
	
		if (!is_array($estimates)) return false;
		foreach ($estimates as $rated) {
			$delivery = "5d-7d";
			if ($type == "domestic") {
				$service = substr($type,0,1).$rated['ATTRS']['CLASSID'];
				$amount = $rated['CHILDREN']['Rate']['CONTENT'];
				$delivery = false;	
			}
			
			if ($type == "intl") {
				$service = substr($type,0,1).$rated['ATTRS']['ID'];
				$amount = $rated['CHILDREN']['Postage']['CONTENT'];
				if (isset($rated['CHILDREN']['SvcCommitments']['CONTENT']))
					$delivery = $this->delivery($rated['CHILDREN']['SvcCommitments']['CONTENT']);

			}

			if (is_array($this->rate['services']) && in_array($service,$this->rate['services'])) {
				$rate = array();
				$rate['name'] = $this->services[$service];
				$rate['amount'] = $amount;
				$rate['delivery'] = $delivery;
				$options[$rate['name']] = new ShippingOption($rate);
			}
		}
		return $options;
	}
	
	function build ($cart,$description,$postcode,$country) {
		$weight = number_format($this->weight,3,'.','');
		if ($this->units == "oz"){
			$pounds = floor($weight / 16);
			$ounces = $weight % 16;
		}
		else{ 
			list($pounds,$ounces) = explode(".",$weight);
			$ounces = ceil(($weight-$pounds)*16);
		}

		$type = "RateV3"; // Domestic shipping rates
		if ($country != $this->base['country']) {
			global $Shopp;
			$type = "IntlRate";	
			$countries = Lookup::country_zones();
			if ($country == "GB") $country = $countries[$country]['name'].' (Great Britain)';
			else $country = $countries[$country]['name'];
		}
		
		$_ = array('API='.$type.'&XML=<?xml version="1.0" encoding="utf-8"?>');
		$_[] = '<'.$type.'Request USERID="'.$this->settings['userid'].'">';
			$_[] = '<Package ID="1ST">';
				if ($type == "IntlRate") {
					$_[] = '<Pounds>'.$pounds.'</Pounds>';
					$_[] = '<Ounces>'.$ounces.'</Ounces>';
					$_[] = '<Machinable>TRUE</Machinable>';
					$_[] = '<MailType>Package</MailType>';
					$_[] = '<Country>'.$country.'</Country>';
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

		return join("\n",apply_filters('shopp_usps_request',$_));
	} 
	
	function delivery ($timeframe) {
		list($start,$end) = sscanf($timeframe,"%d - %d Days");
		$days = $start.'d'.(!empty($end)?'-'.$end.'d':'');
		if (empty($start)) $days = "5d-15d";
		return $days;
	}

	function verify () {         
		if (!$this->activated()) return;
		$this->weight = 1;
		$this->request = $this->build('1','Authentication test','10022','US');
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
	
	function logo () {
		return 'iVBORw0KGgoAAAANSUhEUgAAALIAAAAeCAMAAAHIjYRZAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAwBQTFRF6OjolpaWJCQkKioqkJCQ5OTkVlZWjo6OYmJixMTEDFGF2NjYMmuYcnJytMradXV18PDwdJu5CwsL5e3y8vb4jKzEZWVmvr6+zMzMLS0t/urs9vj7hISE5ubm0NDQo73R2tramZmZyMjIeXl5RUVFFhYW7Ozs3NzcBAQE4uLi6urqAEZ+KWWT/ejqQUFB1NTUXV1dysrKXIqtaWlpAEJ74urwqampxsbGbW1tgICAVYOpOTk5iIiIRHmhoaGhhKbBUlJSlbLJoKCgwsLCNjY2q6ur+vz9mbbMtLS0zs7OAER9pqam0tLSHR0dt7e3AEqBurq61tfXuLi4OnGcPT09+fv8G16PnJycwMDAUFBQvLy8ra2tWVlZIWGRsLCw3efu7u7ufHx8pKSkGRkZTk5O/P3+C0+DSkpKsrKyapKzrq6ufn5+ioqKytnkf6K+vNDeZZCyElaJbZW1MDAwMjIyTExMwdPgeZ67BU2CFFiJADx3rsXW1uLq7PL2ERER6O/0R0dHz93nF1uM7vP3Hl2N2+XtgKO/+fr7zNvmGluMAEh/PXWe6/D1L2iWEFSH4OnvSXyjHVuMA0qBqMDUX4yuNW+bAEmAT4GnGlaJ/v7+9aGr9Z+q/f39e6K+np6e/Pz8/+zv/////+3v+fn59aCq+vr69vb29fX1/enr+/v73t7e+Pj48/Pz4eHh/f7+AEN8/+zu39/f/ers9/f34ODg9PT08vLy/+vu9aCr9qGsAEuB/v//n5+f3+jvb5e2jIyM4+vx7/T37PH1oLrPnp+fAkd/aZS0/P39EEyCCE6DlJSUX42v5eXlgoOD19fX5yhA9Jmk725/A0yC4+PjBEqBx9fjcpi36+vr/efqyNfj0dHRUYGn+tLXo6Oj/ezu8PT3WIerX2Bgm7fN0d7owNLfcJq55yU9O3SdPnKc+vr7e6C++LK7p6enaJOz7FJlE1iK9vb3AkmA2OPsw9XieKC8e6G9P3ag//7+7PH2QXig9PX13d3d6C5FAEyCAAAA////G3OM6QAAAQB0Uk5T////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////AFP3ByUAAA0lSURBVHjaYvjvHgwErcXp/4tLig0WP2RWuMvMzPyf4X9XV+XJ//89/2uw/v9frPD/XM7///8dgOJ///r/9/qPDhj+ez3++/fv/2QQ5x8QW//TWA1kAAQQw/9ekPlAI0KYEyWZ/zMXF5eU/F/NYNpVWfn/738WoEqJ/8v+R/5X4FADmjLtr0h8bSyG4X//awPNzvkn8I9JRf//v+R///8x/PcFCCAGiKwIjxfI5v8N/2/ZzOFO/s/4X2ktUAHIFQLs1kCqznCVHMiFfWBl///ziWr/JwRA/vy7/v9/HSD14/8/vf8ckjNtlnkrBQr93/J/prKaKqeKufHD//85/ynwcjIwiP43gYH/M///P6daqgF0ALPvPyGN/0L/AjWsVR2ADpH8/6/un9B/gABi+C89gScYCoDK4/7v1wIGEPM8Zh9Jlf//mZeWSG6Zwyz5n5nZEhQRYFDt7w9yj8H/f4z/khn/M/3/JwESjlsGNLY0cel/bwmQf0Gx1tVXWdk343+eICgI/sn+AwWE0H9+kLTQfw2X/xoq4Pipg3gRGDP/m6XfEg4PN6DiXTcn/v/P85ew4m9AxZn/hb1AaUVAQuifft0/Z/F/EuA4+edsY65mLRTz/5/QOTtuZk6G9X/zwFHz9+/R/0z//nP//3du/39LG3AykrX85/s/gXfVQqBX/v9PbmCwP1wFDWc+sGH//mukz/x/VkPpHzCgrf8JnfrvC/SmMVOykpABQABSque1ySCIhuilhJAQkBokpS2krqaYS4j2ByEKYk0ak1v8jSWCQqEgCRYU9OZBPHgQZOFjQxANgeil7aUKURBE8GRvQsFD0Yv0L2jm+eb7rHh3DrvD7O7bt29n5o90OHL/6ddSdfjXHvOHIgZILt3g+dipvsCbCNt2umAt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';
	}
	
}
?>