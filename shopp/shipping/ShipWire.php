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

require_once(SHOPP_PATH."/core/model/XMLdata.php");

class ShipWire extends ShippingFramework implements ShippingModule {
	var $url = 'https://www.shipwire.com/exec/';
	var $apis = array(
		"OrderListXML" => "FulfillmentServices.php",
		"RateRequestXML" => "RateServices.php",
		"InventoryUpdateXML" => "InventoryServices.php",
		"TrackingUpdateXML" => "TrackingServices.php"
	);
	var $type = "OrderListXML";
	var $request = false;
	var $test = true;
	var $dev = true;
	var $weight = 0;
	var $conversion = 1;
	var $Response = false;
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
 		$this->weight += ($Item->weight * $this->conversion) * $Item->quantity;
	}
	
	function calculate ($options,$Order) {
		if (empty($Order->Shipping->postcode)) {
			new ShoppError(__('A postal code for calculating shipping estimates and taxes is required before you can proceed to checkout.','Shopp','shipwire_postcode_required',SHOPP_ERR));
			return $options;
		}
		
		$this->request = $this->build(session_id(), $$this->rate['name'], 
			$Order->Shipping->postcode, $Order->Shipping->country);
		
		$this->Response = $this->send();
		if (!$this->Response) return false;
		
		if ($this->Response->getElement('Error')) {
			new ShoppError($this->Response->getElementContent('ErrorDescription'),'shipwire_rate_error',SHOPP_ADDON_ERR);
			return false;
		}

		$estimate = false;
		$Quotes = $this->Response->getElement('Quote');
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

		return $this->type.'='.urlencode(join("\n",$_));
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
		
		$this->request = $this->type.'='.urlencode(join("\n",$_));
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

		$this->request = $this->type.'='.urlencode(join("\n",$_));
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
		
		$this->request = $this->type.'='.urlencode(join("\n",$_));
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
		if ((!is_user_logged_in() || !current_user_can('manage_options'))) return;
		if (empty($_GET['action']) || $_GET['action'] != 'wp_ajax_shopp_shipwire_sync') return;
		
		$Updates = $this->sync();

		$Errors = &$ShoppErrors;
		if (!empty($Errors) && $Error = $Errors->get('shipwire_sync_error')) die("ERROR: $Error->message");
		echo json_encode($Updates);
		exit();
	}
	
	function logo () {
		return 'iVBORw0KGgoAAAANSUhEUgAAAIIAAAAeCAMAAAGgfgZhAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAwBQTFRFwsLCTk5OkpKSSUlJs8KD/MuU/YwNMjIy/dSmo6OjRUVFoMB//vXr8fToi4uLkKVJzNas95Ie+qlNUlJSkadMqrp06Ojo/Pz894oN8fHx1Nrh+fr1mKtV4e7bysrKdHR0g4ODoLJk94wS/vHjjqRG2+HmhZw25ubmkqhNxcbFqqqqrb16YGBgnrFhxM+dQUJBOTk5nbBe1NTUi6FAlqpSra2t/7xs9Pfu7e3t/eXH/v799vjxhp45xtGhPT09ampqzc3Nv7+///nxydSmiaA+LC0t2uHCsLCwfZYq5urV3t7e95If4OfNmq5b0dq01t69u7u7jaNEZmVl/du0nJyc+Pj4+4UD9PT00dHR+rx1tLS07fHhfHx895AaJCUk94UG1d26jqVI6e7cVlZW+vr6mq5a+Y4W+Pz5oLNmjKJC//v18PPn2dnZWVlZ6OzZ/enStsSI4eHh9vb2usiN/Pz6+rJeVFRU3uXJpLZqlKlQ/u3amKxX6PPmuMaLWlpaiJ88obRn94IBUFBQgpoypbds9noAnK9dvcqU/MB8lqtVXFxc7/LkvMqP//z46uvr4+jQtM+e3OPFp7hvmKxWgJkv/f38ztXdYmJiu8iQn7JkorRomJiYlapT/pATmq1Y8/Xrk6hP94cJ/vjv+JgqoLNjlalRv8yWU1RUX19fXl5e/v7+U1NT///////+/v/+S0xL//z3TExM//37g5s0T1BP+/z5//7+ma1ZSEhI//38+/v4Q0RDW1tblapR//786+/f9ezZ5OnSscB+9/nyVVZVNzc3oKCg8/f6lpaWj6RH+aJA3+vV5PDgSUpMR0dHS0tLcHV6//v4wMyZ/YkF7PTp5uvXT09PXFxb7u/w/YYH4+Pk8fT3yc/VQEBAq6urOzs7W1xb7/L0UVFRm69c/v7/t7e4Pz8/6PHkzdeuPDw8//zzpqam3NzcV1dXhJs0+fn5qKio/vDf1tbXma5Yma5amq1b2+LE3eTH1Ny5+JouHR0dVFZXVVVVn7Jjl6tU////ldVeZgAAAQB0Uk5T////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////AFP3ByUAAArESURBVHjaYvjv/5/3PwQsCgCRDP8X/P/79///fyz3VuisXPE2dxVDy/9//1zU+P9vDhJ+/2zLMa0Qhv9rQEr//YcDgABi+H/ofzSUzaEJEtD9/7/N5u/fv2UR/4v+BxnoMPBbffsvUs9vs5tdmOv1/yZHBohqK7gZAAHE8H9OZcHf/76XQGb8DGtlWzh3oQSbEtvla2JwFf/n/OX///d/hYyAYfbf/4apXJxdK9S7LFb8X/neYqWYPBfD/8mBMlPUNv//Z/7vKv/Jk//+y6/+L7z7P7P9UYP//8uOlv1n+I8E/v3HAgACiOH/uoyJ///PRxVdU/VfDqZgzV/bySpWwED6n1+advNm2MK5Emxs8Wx3NsIUWP/N/v+/WDdj5v85s/hn/c34v/K/TpDcit369nt0/uu8tWP4P0kx479IItArf0v+sgCpFZJ1fCsku54yMv5foRm+kuGf1X/+ln/zgS4EwlVAYnVT6PXz/1f/Xy35fzWnqSODx+RMkFyCyf5/q/7Nmzzl/2p2ueVTpwIV/P8ft/GaK9Cfa2Fu1xbB6k0EqHfBogAggIAK/KuBMbiEHxizgbqT0eV7F+WzdpT+VJKIXyihJOjd+GTPCi10E7L//u3+/7/fd/v8q391rX1mFQNFTz+X/X+lRvb/BO4z/9dIqd7+/3+aqiqIkOorzPlv4eD43z35/3+53K7XYEe6ZPxdEugJ1Mf/N4Pf+fHfr+usdf8m/K/8+1fbz/Bv4n/mlff+P1uxUv7/s5X6/xlX6PzXX7kil9HLYAXXqv/nVzBCvFkr0qbrI+1r+ALoE0Pj/wsM+f+/MKwu+f9/e9vS/+f2PH2U65gbcH7P7v9aXW/D1bseMP3/H/VgT25u7p495xiuTpap+P/f+d9k/8f/gAF1eM4/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';
	}
}
?>