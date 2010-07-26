<?php
/**
 * ShipWire
 * 
 * Integrates ShipWire fulfillment services with Shopp
 * 
 * INSTALLATION INSTRUCTIONS
 * Upload ShipWire.php to your Shopp install under:
 * ./wp-content/plugins/shopp/shipping/
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, 9 December, 2009
 * @package shopp
 * @since 1.1 dev
 * @subpackage ShipWire
 * 
 * $Id$
 **/

class ShipWire extends ShippingFramework implements ShippingModule {

	var $url = 'https://www.shipwire.com/exec/';
	var $apis = array(
		"OrderListXML" => "FulfillmentServices.php",
		"RateRequestXML" => "RateServices.php",
		"InventoryUpdateXML" => "InventoryServices.php",
		"TrackingUpdateXML" => "TrackingServices.php"
	);
	var $type = "OrderListXML";

	// var $test = true;
	// var $dev = true;

	var $dimensions = true;
	var $xml = true;

	var $weight = 0;
	var $requiresauth = true;
	var $trackcycle = array();
	var $services = array();
	
	function __construct () {
		parent::__construct();
		
		$this->setup('email','password','trackcycle');
		
		$units = array("imperial" => "LBS","metric"=>"KGS");
		$this->settings['units'] = $units[$this->base['units']];
		if ($this->units == 'oz') $this->conversion = 0.0625;
		if ($this->units == 'g') $this->conversion = 0.001;

		$this->services = array(
			'OrderListXML' => __('Order Fulfillment','Shopp'),
			'RateRequestXML' => __('Shipping Rates','Shopp'),
			'InventoryUpdateXML' => __('Real-Time Inventory Updates','Shopp'),
			'TrackingUpdateXML' => __('Tracking Status Updates','Shopp')
		);
		
		$this->trackcycle = array(
			'daily' => __('Everyday','Shopp'),
			'twicedaily' => __('Twice a day','Shopp'),
			'hourly' => __('Every Hour','Shopp')
		);
		
		if (isset($this->rates[0])) $this->rate = $this->rates[0];
		
		add_action('init', array(&$this, 'ajax'),9);
		add_action('shipping_service_settings',array(&$this,'settings'));
		add_action('shopp_order_success',array(&$this,'order'));

		if (isset($this->settings['services'])) {
			if (in_array('InventoryUpdateXML',$this->settings['services']))
				add_filter('shopp_cartitem_stock',array(&$this,'sync'));

			if (in_array('TrackingUpdateXML',$this->settings['services']))
				wp_schedule_event(time(),$this->settings['trackcycle'],'shipwire_tracking_update');			
		}

	}
		
	function methods () {
		return array(__("ShipWire Service","Shopp"));
	}
		
	function ui () {
		global $Shopp; ?>
		function ShipWire (methodid,table,rates) {
			table.addClass('services').empty();
			
			if (!uniqueMethod(methodid,'<?php echo get_class($this); ?>')) return;
			
			var shipwire_ajax = '<?php echo wp_nonce_url($Shopp->wpadminurl."admin-ajax.php","shopp-wp_ajax_shipwire_sync"); ?>';
			
			var services = <?php echo json_encode($this->services); ?>;
			
			
			var settings = '';
			settings += '<tr><td>';
			settings += '<style type="text/css">#shipping-rates th.shipwire { background: url(data:image/png;base64,<?php echo $this->logo(); ?>) no-repeat 20px 25px; }</style>';
			settings += '<div class="multiple-select"><ul id="shipwire-services">';
			
			settings += '<li><input type="checkbox" name="select-all" id="shipwire-services-select-all" /><label for="shipwire-services-select-all"><strong><?php echo addslashes(__('Select All','Shopp')); ?></strong></label>';
			
			var even = true;
			
			for (var service in services) {
				var checked = '';
				even = !even;
				for (var r in rates.services) 
					if (rates.services[r] == service) checked = ' checked="checked"';
					if (service == "OrderListXML") checked = ' checked="checked"';
				settings += '<li class="'+((even)?'even':'odd')+'"><input type="checkbox" name="settings[shipping_rates]['+methodid+'][services][]" value="'+service+'" id="shipwire-service-'+service+'"'+checked+''+(service=="OrderListXML"?' disabled="disabled"':'')+' /><label for="shipwire-service-'+service+'">'+services[service]+'</label></li>';
			}

			settings += '</td>';
			
			settings += '<td>';
			settings += '<input type="hidden" id="shipwire-postcode-required" name="settings[shipping_rates]['+methodid+'][postcode-required]" value="true" />';
			settings += '<div><input type="text" name="settings[ShipWire][email]" id="shipwire_email" value="<?php echo $this->settings['email']; ?>" size="16" class="selectall" /><br /><label for="shipwire_email"><?php echo addslashes(__('ShipWire Email','Shopp')); ?></label></div>';
			settings += '<div><input type="password" name="settings[ShipWire][password]" id="shipwire_password" value="<?php echo $this->settings['password']; ?>" size="16"  class="selectall" /><br /><label for="shipwire_password"><?php echo addslashes(__('ShipWire password','Shopp')); ?></label></div>';
			settings += '</td>';

			settings += '<td width="40%">';
			settings += '<div><button name="settings[ShipWire][syncstock]" id="shipwire_syncstock" class="button"><?php _e('Update Inventory','Shopp'); ?></button><br /><label for="shipwire_syncstock" id="shipwire_syncstock_status"><br /></label></div>';
			settings += '<div><select name="seetings[ShipWire][trackcycle]" id="shipwire_trackcycle"><?php echo menuoptions($this->trackcycle,$this->settings['trackcycle'],true); ?></select><br /><label for="shipwire_trackcycle"><?php echo addslashes(__('Shipment tracking updates','Shopp')); ?></label></div>';
			settings += '</td>';


			settings += '</tr>';

			$(settings).appendTo(table);
			
			$(table).addClass('shipwire');
			
			$('#shipwire-service-RateRequestXML').change(function () {
				if (this.checked) $('#shipwire-postcode-required').val('true');
				else $('#shipwire-postcode-required').val('false');
			});
						
			$('#shipwire-services-select-all').change(function () {
				if (this.checked) $('#shipwire-services input[disabled!=true]').attr('checked',true);
				else $('#shipwire-services input[disabled!=true]').attr('checked',false);
			});
			
			$('#shipwire_syncstock').click(function () {
				var button = $(this);
				var original_label = button.html();
				button.html('<img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/updating.gif" width="16" height="16" />&nbsp;Updating...').attr('disabled',true);
				$.ajax({
					type:"POST",
					url:shipwire_ajax+'&action=wp_ajax_shopp_shipwire_sync',
					timeout:10000,
					dataType:"json",
					success:function (data) {
						button.html(original_label).attr('disabled',false);
						if (data == 0) status = "No updates required";
						else status = data[0]+' of '+data[1]+' updated';
						$('#shipwire_syncstock_status').hide().html(status).fadeIn(500);
					},
					error:function (data) { 
						button.html(original_label).attr('disabled',false);
					}
				});
				return false;
			});

			quickSelects();

		}

		methodHandlers.register('<?php echo get_class($this); ?>',ShipWire);

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
			new ShoppError(__('A postal code for calculating shipping estimates and taxes is required before you can proceed to checkout.','Shopp','shipwire_postcode_required',SHOPP_ERR));
			return $options;
		}
		
		$request = $this->build(session_id(), $$this->rate['name'], 
			$Order->Shipping->postcode, $Order->Shipping->country);
		
		$Response = $this->send();
		if (!$Response) return false;
		
		if ($Response->getElement('Error')) {
			new ShoppError($Response->getElementContent('ErrorDescription'),'shipwire_rate_error',SHOPP_ADDON_ERR);
			return false;
		}

		$estimate = false;
		$Quotes = $Response->getElement('Quote');
		if (!is_array($Quotes)) return false;
		foreach ($Quotes as $Quote) {
			$service = $Quote['CHILDREN']['Service']['CONTENT'];
			$amount = $Quote['CHILDREN']['Cost']['CONTENT'];
			if(floatval($amount) == 0) continue;
			$DeliveryEstimate = false;
			$delivery = $Quote['CHILDREN']['DeliveryEstimate']['CHILDREN'];
			$MinDelivery = $delivery['Minimum']['CONTENT'].substr($delivery['Minimum']['ATTRS']['units'],0,1);
			$MaxDelivery = $delivery['Maximum']['CONTENT'].substr($delivery['Maximum']['ATTRS']['units'],0,1);
			$DeliveryEstimate = "$MinDelivery-$MaxDelivery";
			if (empty($DeliveryEstimate)) $DeliveryEstimate = "1d-5d";
			$rate = array();
			$rate['name'] = $service;
			$rate['amount'] = $amount;
			$rate['delivery'] = $DeliveryEstimate;
			$options[$rate['name']] = new ShippingOption($rate);
		}
		
		return $options;
	}
	
	function build ($cart,$description,$postcode,$country) {
		$this->type = "RateRequestXML";
		
		$_ = array('<?xml version="1.0" encoding="utf-8"?>');
		$_[] = '<!DOCTYPE RateRequest SYSTEM "http://www.shipwire.com/exec/download/RateRequest.dtd">';
		$_[] = '<RateRequest>';
			$_[] = '<EmailAddress>'.$this->settings['email'].'</EmailAddress>';
			$_[] = '<Password>'.$this->settings['password'].'</Password>';
			$_[] = '<Server>'.($this->test?'Test':'Production').'</Server>';
			$_[] = '<Order id="">';
				$_[] = '<Warehouse>0</Warehouse>';
				$_[] = '<AddressInfo type="ship">';
					$_[] = '<Address1>321 Foo bar lane</Address1>';
					$_[] = '<Address2>Apartment #2</Address2>';
					$_[] = '<City>Nowhere</City>';
					$_[] = '<State>CA</State>';
					$_[] = '<Country>'.$country.'</Country>';
					$_[] = '<Zip>'.$postcode.'</Zip>';
				$_[] = '</AddressInfo>';
				$_[] = '<Item num="0">';
					$_[] = '<Code>12345</Code>';
					$_[] = '<Quantity>1</Quantity>';
				$_[] = '</Item>';
			$_[] = '</Order>';
		$_[] = '</RateRequest>';	

		return $this->type.'='.urlencode(join("\n",apply_filters('shopp_shipwire_rate_request',$_)));
	}  
	
	function verify () {         
		if (!$this->activated()) return;
		$this->weight = 1;
		$this->request = $this->build('1','Authentication test','10012','US');
		$Response = $this->send();
		if ($Response->getElementContent('Status') == "Error") new ShoppError($Response->getElementContent('ErrorMessage'),'shipwire_verify_auth',SHOPP_ADDON_ERR);
	}   
	
	function sync ($stock=0,$Item=false) {
		$db =& DB::get();
		$product = "";
		if (!empty($Item->sku) && $Item->inventory == "on") $product = $Item->sku;
		$this->type = "InventoryUpdateXML";

		$_ = array('<?xml version="1.0" encoding="UTF-8"?>');
		$_[] = '<!DOCTYPE InventoryStatus SYSTEM "http://www.shipwire.com/exec/download/InventoryUpdate.dtd">';
		$_[] = '<InventoryUpdate>';
		$_[] = '<EmailAddress>'.$this->settings['email'].'</EmailAddress>';
		$_[] = '<Password>'.$this->settings['password'].'</Password>';
		$_[] = '<Server>'.($this->test?'Test':'Production').'</Server>';
		$_[] = '<ProductCode>'.$product.'</ProductCode>';
		$_[] = '</InventoryUpdate>';
		
		$this->request = $this->type.'='.urlencode(join("\n",apply_filters('shopp_shipwire_inventory_update',$_)));
		$Response = $this->send();

		if ($Response->getElementContent('Status') == "Error") new ShoppError($Response->getElementContent('ErrorMessage'),'shipwire_sync_error',SHOPP_ADDON_ERR);
	
		$Products = $Response->getElement('Product');
		$pricetable = DatabaseObject::tablename(Price::$table);
		if (!is_array($Products)) return 0;
		
		$updates = 0;
		if (isset($Products['ATTRS'])) $Products = array($Products);
		foreach ($Products as $entry) {
			$product = $entry['ATTRS'];
			if ($Item && $Item->sku == $product['code']) $stock = $product['quantity'];
			if ($db->query("UPDATE $pricetable SET stock='{$product['quantity']}' WHERE sku='{$product['code']}'")) $updates++;
		}

		if ($Item) return $stock;
		return array($updates,(int)$Response->getElementContent('TotalProducts'));
	}
	
	function tracking () {
		$db =& DB::get();
		$this->type = "TrackingUpdateXML";
		$_ = array('<?xml version="1.0" encoding="UTF-8"?>');
		$_[] = '<!DOCTYPE TrackingStatus SYSTEM "http://www.shipwire.com/exec/download/TrackingUpdate.dtd">';
		$_[] = '<TrackingUpdate>';
		$_[] = '<EmailAddress>'.$this->settings['email'].'</EmailAddress>';
		$_[] = '<Password>'.$this->settings['password'].'</Password>';
		$_[] = '<Server>'.($this->test?'Test':'Production').'</Server>';
		$_[] = '<Bookmark>3</Bookmark>';
		$_[] = '</TrackingUpdate>';

		$this->request = $this->type.'='.urlencode(join("\n",apply_filters('shopp_shipwire_tracking_update',$_)));
		$Response = $this->send();

		if ($Response->getElementContent('Status') == "Error") new ShoppError($Response->getElementContent('ErrorMessage'),'shipwire_sync_error',SHOPP_ADDON_ERR);

		$purchasetable = DatabaseObject::tablename(Purchase::$table);

		$orders = $Response->getElement('Order');
		if (isset($orders['ATTRS'])) $orders = array($orders);
		if (!is_array($orders)) return false;
		foreach ($orders as $entry) {
			$order = $entry['ATTRS'];
			if ($db->query("UPDATE $purchasetable SET shiptrack='{$order['trackingNumber']}' WHERE id='{$order['id']}'")) $updates++;
		}
		new ShoppError(sprintf(__('Processed %s updates to ShipWire shipment tracking.','Shopp'),$updates),'shipwire_tracking_updates',SHOPP_ADDON_ERR);
		
	}
	
	function order ($Purchase) {
		$this->type = "OrderListXML";

		$_ = array('<?xml version="1.0" encoding="UTF-8"?>');
		$_[] = '<!DOCTYPE OrderList SYSTEM "http://www.shipwire.com/exec/download/OrderList.dtd">';
		$_[] = '<OrderList>';
			$_[] = '<EmailAddress>'.$this->settings['email'].'</EmailAddress>';
			$_[] = '<Password>'.$this->settings['password'].'</Password>';
			$_[] = '<Server>'.($this->test?'Test':'Production').'</Server>';
			$_[] = '<Referer>4411</Referer>';
			$_[] = '<Order id="'.$Purchase->id.'">';
				$_[] = '<Warehouse>00</Warehouse>';
				$_[] = '<AddressInfo type="ship">';
					$_[] = '<Name>';
						$_[] = '<Full>'.$Purchase->firstname.' '.$Purchase->lastname.'</Full>';
					$_[] = '</Name>';
					$_[] = '<Address1>'.$Purchase->shipaddress.'</Address1>';
					if (!empty($Purchase->shipxaddress))
						$_[] = '<Address2>'.$Purchase->shipxaddress.'</Address2>';
					$_[] = '<City>'.$Purchase->shipcity.'</City>';
					$_[] = '<State>'.$Purchase->shipstate.'</State>';
					$_[] = '<Country>'.$Purchase->shipcountry.'</Country>';
					$_[] = '<Zip>'.$Purchase->shippostcode.'</Zip>';
					$_[] = '<Phone>'.$Purchase->phone.'</Phone>';
					$_[] = '<Email>'.$Purchase->email.'</Email>';
				$_[] = '</AddressInfo>';
				$_[] = '<Shipping>'.$Purchase->shipmethod.'</Shipping>';
				foreach ($Purchase->purchased as $id => $Item) {
					$_[] = '<Item num="'.$id.'">';
						$_[] = '<Code>'.$Item->sku.'</Code>';
						$_[] = '<Quantity>'.$Item->quantity.'</Quantity>';
						$_[] = '<Description>'.$Item->description.'</Description>';
						$_[] = '<Length>1</Length>';
						$_[] = '<Width>1</Width>';
						$_[] = '<Height>1</Height>';
						$_[] = '<Weight>'.$Item->weight.'</Weight>';
						$_[] = '<DeclaredValue>'.$Item->total.'</DeclaredValue>';
					$_[] = '</Item>';
				}
			$_[] = '</Order>';
		$_[] = '</OrderList>';
		// new ShoppError(_object_r($_),'shipwire_order',SHOPP_DEBUG_ERR);
		
		$this->request = $this->type.'='.urlencode(join("\n",apply_filters('shopp_shipwire_order_list',$_)));
		$Response = $this->send();

		if ($Response->getElementContent('Status') == "Error") new ShoppError($Response->getElementContent('ErrorMessage'),'shipwire_orderlist_error',SHOPP_ADDON_ERR);
		
	}
	
	function send () {   
		global $Shopp;

		if ($this->type == "RateRequestXML" && $this->dev) {
			return new XMLdata('<?xml version="1.0" encoding="UTF-8"?>
			<!DOCTYPE RateResponse SYSTEM "http://www.shipwire.com/exec/download/RateResponse.dtd">
			<RateResponse>
			<Status>OK</Status>

			<Order sequence="1">

			<Quotes>

			<Quote method="GD">
			<Warehouse>Reno (Pick/Pack Saver)</Warehouse>
			<Service>UPS Ground</Service>
			<Cost currency="USD">7.73</Cost>

			<DeliveryEstimate>
			<Minimum units="days">1</Minimum>
			<Maximum units="days">5</Maximum>
			</DeliveryEstimate>
			</Quote>

			<Quote method="2D">
			<Warehouse>Reno (Pick/Pack Saver)</Warehouse>
			<Service>UPS Second Day Air</Service>
			<Cost currency="USD">13.64</Cost>

			<DeliveryEstimate>
			<Minimum units="days">2</Minimum>
			<Maximum units="days">2</Maximum>
			</DeliveryEstimate>
			</Quote>

			<Quote method="1D">
			<Warehouse>Reno (Pick/Pack Saver)</Warehouse>
			<Service>USPS Express Mail</Service>
			<Cost currency="USD">25.25</Cost>

			<DeliveryEstimate>
			<Minimum units="days">1</Minimum>
			<Maximum units="days">1</Maximum>
			</DeliveryEstimate>
			</Quote>
			</Quotes>
			</Order>
			</RateResponse>');
		}

		if ($this->type == "InventoryUpdateXML" && $this->dev) {
			sleep(1);
			return new XMLdata('<InventoryUpdateResponse><Status>Test</Status>
			    <Product code="GD802-024" quantity="14"/>
			    <Product code="GD201-500" quantity="32"/>
			    <TotalProducts>2</TotalProducts>
			    </InventoryUpdateResponse>');
		}

		if ($this->type == "TrackingUpdateXML" && $this->dev) {
			return new XMLdata('<TrackingUpdateResponse>
			<Status>Test</Status>
			<TotalOrders>0</TotalOrders>
			<TotalShippedOrders>0</TotalShippedOrders>
			<Bookmark>2006-04-28 20:35:45</Bookmark>
			</TrackingUpdateResponse>');
			// return new XMLdata('<TrackingUpdateResponse>
			// <Status>Test</Status>
			// <Order id="2986" shipped="YES" trackingNumber="1ZW682E90326614239" shipper="UPS GD" handling="1.00" shipping="13.66" total="14.66"/>
			// <Order id="2987" shipped="YES" trackingNumber="1ZW682E90326795080" shipper="UPS GD" handling="1.50" shipping="9.37" total="10.87"/>
			// <Order id="2988" shipped="NO" trackingNumber="" shipper="UPS GD" handling="" shipping="" total=""/>
			// <TotalOrders>3</TotalOrders>
			// <TotalShippedOrders>2</TotalShippedOrders>
			// <Bookmark>2006-04-28 20:35:45</Bookmark>
			// </TrackingUpdateResponse>');
		}

		$connection = curl_init();
		curl_setopt($connection, CURLOPT_URL,$this->url.$this->apis[$this->type]);
		curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0); 
		curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0); 
		curl_setopt($connection, CURLOPT_VERBOSE, 1); 
		curl_setopt($connection, CURLOPT_FOLLOWLOCATION,0); 
		curl_setopt($connection, CURLOPT_HTTPHEADER, array("Content-type","application/x-www-form-urlencoded"));
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $this->request); 
		curl_setopt($connection, CURLOPT_TIMEOUT, 10); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_REFERER, "https://".$_SERVER['SERVER_NAME']); 
		
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);
		if ($error = curl_error($connection)) 
			new ShoppError($error,'shipwire_connection',SHOPP_COMM_ERR);
		curl_close($connection);

		// echo "<pre>".htmlentities($this->request)."</pre>";
		// echo "<pre>".htmlentities($buffer)."</pre>";

		new ShoppError($buffer,'shipwire_response',SHOPP_DEBUG_ERR);
		$Response = new XMLdata($buffer);
		return $Response;
	}
	
	function ajax () {
		if ((!is_user_logged_in() || !current_user_can('manage_options') || !current_user_can('shopp_settings_shipping'))) return;
		if (empty($_GET['action']) || $_GET['action'] != 'wp_ajax_shopp_shipwire_sync') return;
		
		$Updates = $this->sync();

		$Errors = &$ShoppErrors;
		if (!empty($Errors) && $Error = $Errors->get('shipwire_sync_error')) die("ERROR: $Error->message");
		echo json_encode($Updates);
		exit();
	}
	
	function logo () {
		return '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';
	}
}
?>