<?php
/**
 * Australia Post
 * 
 * Uses the Australia Post eDeliver service to get live shipping rates based on product dimensions & weight
 * 
 * INSTALLATION INSTRUCTIONS
 * Upload AusPost.php to your Shopp install under:  
 * ./wp-content/plugins/shopp/shipping/
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, July, 2010
 * @package shopp
 * @since 1.1b2
 * @subpackage AusPost
 * 
 **/

class AusPost extends ShippingFramework implements ShippingModule {

	var $url = 'http://drc.edeliver.com.au/ratecalc.asp';

	var $postcode = true;
	var $dimensions = true;

	var $weight = 0;
	
	var $services = array(
		'STANDARD' => 'Standard', 
		'EXPRESS' => 'Express',
		'AIR' => 'Air',
		'SEA' => 'Sea'
	);
	
	var $domestic = array('STANDARD','EXPRESS');
	var $intl = array('AIR','SEA');
	var $insurance = 1.1;
	var $insured = false;
	var $singular = true; // module can only be loaded once
				
	function __construct () {
		parent::__construct();

		$this->setup('postcode');
	

		// Select service options using base country
		if (array_key_exists($this->base['country'],$this->services)) 
			$services = $this->services[$this->base['country']];
		
		// Build the service list
		$this->settings['services'] = $this->services;
		
		if ($this->singular && is_array($this->rates) && !empty($this->rates))  $this->rate = reset($this->rates); // TODO: remove after 1.1.3
		
		add_action('shipping_service_settings',array(&$this,'settings'));
		add_action('shopp_verify_shipping_services',array(&$this,'verify'));
	}
		
	function methods () {
		// Require base of operations in Canada
		// if ($this->settings['country'] != "AU") return array(); 
		return array(__("Australia Post","Shopp"));
	}
		
	function ui () {?>
		function AusPost (methodid,table,rates) {
			table.addClass('services').empty();
			
			if (!uniqueMethod(methodid,'<?php echo get_class($this); ?>')) return;
			
			var services = <?php echo json_encode($this->settings['services']); ?>;
			var settings = '';
			settings += '<tr><td>';
			settings += '<style type="text/css">#shipping-rates th.auspost { background: url(data:image/png;base64,<?php echo $this->logo(); ?>) no-repeat 20px 25px; }</style>';

			settings += '<div class="multiple-select"><ul id="auspost-services">';

			settings += '<li><input type="checkbox" name="select-all" id="auspost-services-select-all" /><label for="auspost-services-select-all"><strong><?php echo addslashes(__('Select All','Shopp')); ?></strong></label>';

			var even = true;
			
			for (var service in services) {
				var checked = '';
				even = !even;
				for (var r in rates.services) 
					if (rates.services[r] == service) checked = ' checked="checked"';
				settings += '<li class="'+((even)?'even':'odd')+'"><input type="checkbox" name="settings[shipping_rates]['+methodid+'][services][]" value="'+service+'" id="auspost-service-'+service+'"'+checked+' /><label for="auspost-service-'+service+'">'+services[service]+'</label></li>';
			}

			settings += '</td>';
			
			settings += '<td>';
			settings += '<div><input type="text" name="settings[AusPost][postcode]" id="auspost_postcode" value="<?php echo $this->settings['postcode']; ?>" size="7" /><br /><label for="auspost_postcode"><?php echo addslashes(__('Your postal code','Shopp')); ?></label></div>';
				
			settings += '</td><td width="33%">&nbsp;</td>';
			settings += '</tr>';

			$(settings).appendTo(table);
			
			$('#auspost-services-select-all').change(function () {
				if (this.checked) $('#auspost-services input').attr('checked',true);
				else $('#auspost-services input').attr('checked',false);
			});
			
			quickSelects();

		}

		methodHandlers.register('<?php echo $this->module; ?>',AusPost);

		<?php		
	}
	
	function init () {
		$this->weight = 0;
		$this->length = 0;
		$this->height = 0;
		$this->width = 0;
	}

	function calcitem ($id,$Item) {
		$this->weight += $Item->weight * $Item->quantity;
		$this->length = max($this->length,$Item->length);
		$this->width = max($this->width,$Item->width);
		$this->height += ($Item->height) * $Item->quantity;
	}
	
	function calculate ($options,$Order) {
		if (empty($Order->Shipping->postcode)) {
			new ShoppError(__('A postal code for calculating shipping estimates and taxes is required before you can proceed to checkout.','Shopp','auspost_postcode_required',SHOPP_ERR));
			return $options;
		}

		// Domestic or international?
		if (strtoupper($Order->Shipping->country) == "AU") $available = $this->domestic;
		else $available = $this->intl;

		$enabled = array_keys($this->settings['services']);
		$services = array_intersect($available,$enabled);
		
		foreach ($services as $service) {
			$request = $this->build($service, count($Order->Cart->shipped), $Order->Shipping->postcode, $Order->Shipping->country);
			$Response = $this->send($request);
			if ($Response->err_msg != "OK") {
				new ShoppError($Response->err_msg,'auspost_verify_auth',SHOPP_ADDON_ERR);
				return $options;	
			}
			$rate['name'] = $this->services[$service];
			$rate['amount'] = $Response->charge;
			$rate['delivery'] = $Response->days.'d';
			$options[$rate['name']] = new ShippingOption($rate);
		}
		return $options;
	}
	
	function delivery ($date) {
		list($year,$month,$day) = sscanf($date,"%4d-%2d-%2d");
		$days = ceil((mktime(9,0,0,$month,$day,$year) - mktime())/86400);
		return $days.'d';
	}
	
	function build ($service,$quantity,$postcode,$country) {
		$_ = array();
		$_['Pickup_Postcode'] = $this->settings['postcode'];
		$_['Destination_Postcode'] = $postcode;
		$_['Country'] = $country;
		$_['Service_Type'] = $service;
		$_['Weight'] = convert_unit($this->weight,'g');		// Weight in grams
		$_['Length'] = convert_unit($this->length,'mm'); 	// Dimensions are
		$_['Width'] = convert_unit($this->width,'mm');		// measured in
		$_['Height'] = convert_unit($this->height,'mm');	// millimeters
		$_['Quantity'] = $quantity;
		return $this->encode($_);
	}  

	function verify () {
		if (!$this->activated()) return;
		$this->weight = 10;
		$this->length = 10;
		$this->width = 10;
		$this->height = 10;
		$request = $this->build('STANDARD',1,'3015','AU');
		$Response = $this->send($request);
		if ($Response->err_msg != "OK") new ShoppError($Response->err_msg,'auspost_verify_auth',SHOPP_ADDON_ERR);
	}   
	     	
	function send ($data) {
		$response = parent::send($data,$this->url);
		$pairs = explode("\n",trim($response));
		$_ = new stdClass();
		foreach ($pairs as $set) {
			list($key, $value) = explode("=",$set);
			$_->$key = $value;
		}
		return $_;
	}
	
	function logo () {
		return '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';
	}
	
}
?>