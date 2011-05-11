<?php

add_filter('shoppapi_purchase_address', array('ShoppPurchaseAPI', 'address'), 10, 3);
add_filter('shoppapi_purchase_card', array('ShoppPurchaseAPI', 'card'), 10, 3);
add_filter('shoppapi_purchase_cardtype', array('ShoppPurchaseAPI', 'card_type'), 10, 3);
add_filter('shoppapi_purchase_city', array('ShoppPurchaseAPI', 'city'), 10, 3);
add_filter('shoppapi_purchase_company', array('ShoppPurchaseAPI', 'company'), 10, 3);
add_filter('shoppapi_purchase_country', array('ShoppPurchaseAPI', 'country'), 10, 3);
add_filter('shoppapi_purchase_customer', array('ShoppPurchaseAPI', 'customer'), 10, 3);
add_filter('shoppapi_purchase_data', array('ShoppPurchaseAPI', 'data'), 10, 3);
add_filter('shoppapi_purchase_date', array('ShoppPurchaseAPI', 'date'), 10, 3);
add_filter('shoppapi_purchase_discount', array('ShoppPurchaseAPI', 'discount'), 10, 3);
add_filter('shoppapi_purchase_email', array('ShoppPurchaseAPI', 'email'), 10, 3);
add_filter('shoppapi_purchase_firstname', array('ShoppPurchaseAPI', 'first_name'), 10, 3);
add_filter('shoppapi_purchase_freight', array('ShoppPurchaseAPI', 'freight'), 10, 3);
add_filter('shoppapi_purchase_hasdata', array('ShoppPurchaseAPI', 'has_data'), 10, 3);
add_filter('shoppapi_purchase_hasitems', array('ShoppPurchaseAPI', 'has_items'), 10, 3);
add_filter('shoppapi_purchase_haspromo', array('ShoppPurchaseAPI', 'has_promo'), 10, 3);
add_filter('shoppapi_purchase_hasdiscount', array('ShoppPurchaseAPI', 'has_discount'), 10, 3);
add_filter('shoppapi_purchase_hasdownloads', array('ShoppPurchaseAPI', 'has_downloads'), 10, 3);
add_filter('shoppapi_purchase_hasfreight', array('ShoppPurchaseAPI', 'has_freight'), 10, 3);
add_filter('shoppapi_purchase_hastax', array('ShoppPurchaseAPI', 'has_tax'), 10, 3);
add_filter('shoppapi_purchase_id', array('ShoppPurchaseAPI', 'id'), 10, 3);
add_filter('shoppapi_purchase_itemaddons', array('ShoppPurchaseAPI', 'item_addons'), 10, 3);
add_filter('shoppapi_purchase_itemaddonslist', array('ShoppPurchaseAPI', 'item_addons_list'), 10, 3);
add_filter('shoppapi_purchase_itemdescription', array('ShoppPurchaseAPI', 'item_description'), 10, 3);
add_filter('shoppapi_purchase_itemdownload', array('ShoppPurchaseAPI', 'item_download'), 10, 3);
add_filter('shoppapi_purchase_itemhasaddons', array('ShoppPurchaseAPI', 'item_has_addons'), 10, 3);
add_filter('shoppapi_purchase_itemhasinputs', array('ShoppPurchaseAPI', 'item_has_inputs'), 10, 3);
add_filter('shoppapi_purchase_itemid', array('ShoppPurchaseAPI', 'item_id'), 10, 3);
add_filter('shoppapi_purchase_iteminput', array('ShoppPurchaseAPI', 'item_input'), 10, 3);
add_filter('shoppapi_purchase_iteminputs', array('ShoppPurchaseAPI', 'item_inputs'), 10, 3);
add_filter('shoppapi_purchase_iteminputslist', array('ShoppPurchaseAPI', 'item_inputs_list'), 10, 3);
add_filter('shoppapi_purchase_itemname', array('ShoppPurchaseAPI', 'item_name'), 10, 3);
add_filter('shoppapi_purchase_itemoptions', array('ShoppPurchaseAPI', 'item_options'), 10, 3);
add_filter('shoppapi_purchase_itemprice', array('ShoppPurchaseAPI', 'item_price'), 10, 3);
add_filter('shoppapi_purchase_itemproduct', array('ShoppPurchaseAPI', 'item_product'), 10, 3);
add_filter('shoppapi_purchase_itemquantity', array('ShoppPurchaseAPI', 'item_quantity'), 10, 3);
add_filter('shoppapi_purchase_itemsku', array('ShoppPurchaseAPI', 'item_sku'), 10, 3);
add_filter('shoppapi_purchase_itemtotal', array('ShoppPurchaseAPI', 'item_total'), 10, 3);
add_filter('shoppapi_purchase_itemunitprice', array('ShoppPurchaseAPI', 'item_unitprice'), 10, 3);
add_filter('shoppapi_purchase_items', array('ShoppPurchaseAPI', 'items'), 10, 3);
add_filter('shoppapi_purchase_lastname', array('ShoppPurchaseAPI', 'last_name'), 10, 3);
add_filter('shoppapi_purchase_notpaid', array('ShoppPurchaseAPI', 'not_paid'), 10, 3);
add_filter('shoppapi_purchase_orderdata', array('ShoppPurchaseAPI', 'order_data'), 10, 3);
add_filter('shoppapi_purchase_paid', array('ShoppPurchaseAPI', 'paid'), 10, 3);
add_filter('shoppapi_purchase_payment', array('ShoppPurchaseAPI', 'payment'), 10, 3);
add_filter('shoppapi_purchase_phone', array('ShoppPurchaseAPI', 'phone'), 10, 3);
add_filter('shoppapi_purchase_postcode', array('ShoppPurchaseAPI', 'post_code'), 10, 3);
add_filter('shoppapi_purchase_promolist', array('ShoppPurchaseAPI', 'promo_list'), 10, 3);
add_filter('shoppapi_purchase_receipt', array('ShoppPurchaseAPI', 'receipt'), 10, 3);
add_filter('shoppapi_purchase_shipaddress', array('ShoppPurchaseAPI', 'ship_address'), 10, 3);
add_filter('shoppapi_purchase_shipcity', array('ShoppPurchaseAPI', 'ship_city'), 10, 3);
add_filter('shoppapi_purchase_shipcountry', array('ShoppPurchaseAPI', 'ship_country'), 10, 3);
add_filter('shoppapi_purchase_shipmethod', array('ShoppPurchaseAPI', 'ship_method'), 10, 3);
add_filter('shoppapi_purchase_shippostcode', array('ShoppPurchaseAPI', 'ship_postcode'), 10, 3);
add_filter('shoppapi_purchase_shipstate', array('ShoppPurchaseAPI', 'ship_state'), 10, 3);
add_filter('shoppapi_purchase_shipxaddress', array('ShoppPurchaseAPI', 'ship_xaddress'), 10, 3);
add_filter('shoppapi_purchase_state', array('ShoppPurchaseAPI', 'state'), 10, 3);
add_filter('shoppapi_purchase_status', array('ShoppPurchaseAPI', 'status'), 10, 3);
add_filter('shoppapi_purchase_subtotal', array('ShoppPurchaseAPI', 'subtotal'), 10, 3);
add_filter('shoppapi_purchase_tax', array('ShoppPurchaseAPI', 'tax'), 10, 3);
add_filter('shoppapi_purchase_total', array('ShoppPurchaseAPI', 'total'), 10, 3);
add_filter('shoppapi_purchase_totalitems', array('ShoppPurchaseAPI', 'total_items'), 10, 3);
add_filter('shoppapi_purchase_txnid', array('ShoppPurchaseAPI', 'txnid'), 10, 3);
add_filter('shoppapi_purchase_transactionid', array('ShoppPurchaseAPI', 'txnid'), 10, 3);
add_filter('shoppapi_purchase_url', array('ShoppPurchaseAPI', 'url'), 10, 3);
add_filter('shoppapi_purchase_xaddress', array('ShoppPurchaseAPI', 'xaddress'), 10, 3);

/**
 * Provides shopp('purchase') theme API functionality
 *
 * @author Jonathan Davis, John Dillick
 * @since 1.2
 *
 **/
class ShoppPurchaseAPI {
	function address ($result, $options, $O) { return esc_html($O->address); }

	function card ($result, $options, $O) { return (!empty($O->card))?sprintf("%'X16d",$O->card):''; }

	function card_type ($result, $options, $O) { return $O->cardtype; }

	function city ($result, $options, $O) { return esc_html($O->city); }

	function company ($result, $options, $O) { return esc_html($O->company); }

	function country ($result, $options, $O) {
		global $Shopp;
		$countries = $Shopp->Settings->get('target_markets');
		return $countries[$O->country];
	}

	function customer ($result, $options, $O) { return $O->customer; }

	function data ($result, $options, $O) {
		if (!is_array($O->data)) return false;
		$data = current($O->data);
		$name = key($O->data);
		if (isset($options['name'])) return esc_html($name);
		return esc_html($data);
	}

	function date ($result, $options, $O) {
		if (empty($options['format'])) $options['format'] = get_option('date_format').' '.get_option('time_format');
		return _d($options['format'],((is_int($O->created))?$O->created:mktimestamp($O->created)));
	}

	function discount ($result, $options, $O) { return money($O->discount); }

	function email ($result, $options, $O) { return esc_html($O->email); }

	function first_name ($result, $options, $O) { return esc_html($O->firstname); }

	function freight ($result, $options, $O) { return money($O->freight); }

	function has_data ($result, $options, $O) { return (is_array($O->data) && count($O->data) > 0); }

	function has_discount ($result, $options, $O) { return ($O->discount > 0); }

	function has_downloads ($result, $options, $O) { return ($O->downloads); }

	function has_freight ($result, $options, $O) { return (!empty($O->shipmethod) || $O->freight > 0); }

	function has_items ($result, $options, $O) {
		if (empty($O->purchased)) $O->load_purchased();
		return (count($O->purchased) > 0);
	}

	function has_promo ($result, $options, $O) {
		if (empty($options['name'])) return false;
		return (in_array($options['name'],$O->promos));
	}

	function has_tax ($result, $options, $O) { return ($O->tax > 0)?true:false; }

	function id ($result, $options, $O) { return $O->id; }

	function item_addons ($result, $options, $O) {
		$item = current($O->purchased);
		if (!isset($O->_itemaddons_loop)) {
			reset($item->addons->meta);
			$O->_itemaddons_loop = true;
		} else next($item->addons->meta);

		if (current($item->addons->meta) !== false) return true;
		else {
			unset($O->_itemaddons_loop);
			return false;
		}
		// @todo Do we need this somewhere?
		// $item = current($O->purchased);
		// $addon = current($item->addons->meta);
		// if (isset($options['id'])) return esc_html($addon->id);
		// if (isset($options['name'])) return esc_html($addon->name);
		// if (isset($options['label'])) return esc_html($addon->name);
		// if (isset($options['type'])) return esc_html($addon->value->type);
		// if (isset($options['onsale'])) return $addon->value->onsale;
		// if (isset($options['inventory'])) return $addon->value->inventory;
		// if (isset($options['sku'])) return esc_html($addon->value->sku);
		// if (isset($options['unitprice'])) return money($addon->value->unitprice);
		// return money($addon->value->unitprice);
	}

	function item_addons_list ($result, $options, $O) {
		$item = current($O->purchased);
		if (empty($item->addons)) return false;
		$defaults = array(
			'prices' => "on",
			'download' => __('Download','Shopp'),
			'before' => '',
			'after' => '',
			'classes' => '',
			'excludes' => ''
		);
		$options = array_merge($defaults,$options);
		extract($options);

		$class = !empty($classes)?' class="'.join(' ',explode(',',$classes)).'"':'';
		$taxrate = 0;
		if ($item->unitprice > 0)
			$taxrate = round($item->unittax/$item->unitprice,4);

		$result = $before.'<ul'.$class.'>';
		foreach ($item->addons->meta as $id => $addon) {
			if (in_array($addon->name,$excludes)) continue;
			if ($O->taxing == "inclusive")
				$price = $addon->value->unitprice+($addon->value->unitprice*$taxrate);
			else $price = $addon->value->unitprice;

			$link = false;
			if (isset($addon->value->download) && isset($addon->value->dkey)) {
				$dkey = $addon->value->dkey;
				$request = SHOPP_PRETTYURLS?"download/$dkey":array('s_dl'=>$dkey);
				$url = shoppurl($request,'catalog');
				$link = '<br /><a href="'.$url.'">'.$download.'</a>';
			}

			$pricing = value_is_true($prices)?" (".money($price).")":"";
			$result .= '<li>'.esc_html($addon->name.$pricing).$link.'</li>';
		}
		$result .= '</ul>'.$after;
		return $result;
	}

	function item_description ($result, $options, $O) {
		$item = current($O->purchased);
		return $item->description;
	}

	function item_download ($result, $options, $O) {
		$item = current($O->purchased);
		if (empty($item->download)) return "";
		if (!isset($options['label'])) $options['label'] = __('Download','Shopp');
		$classes = "";
		if (isset($options['class'])) $classes = ' class="'.$options['class'].'"';
		$request = SHOPP_PRETTYURLS?
			"download/$item->dkey":
			array('src'=>'download','s_dl'=>$item->dkey);
		$url = shoppurl($request,'catalog');
		return '<a href="'.$url.'"'.$classes.'>'.$options['label'].'</a>';
	}

	function item_has_addons ($result, $options, $O) {
		$item = current($O->purchased);
		return (count($item->addons) > 0);
	}

	function item_has_inputs ($result, $options, $O) {
		$item = current($O->purchased);
		return (count($item->data) > 0);
	}

	function item_id ($result, $options, $O) {
		$item = current($O->purchased);
		return $item->id;
	}

	function item_input ($result, $options, $O) {
		$item = current($O->purchased);
		$data = current($item->data);
		$name = key($item->data);
		if (isset($options['name'])) return esc_html($name);
		return esc_html($data);
	}

	function item_inputs ($result, $options, $O) {
		$item = current($O->purchased);
		if (!isset($O->_iteminputs_loop)) {
			reset($item->data);
			$O->_iteminputs_loop = true;
		} else next($item->data);

		if (current($item->data) !== false) return true;
		else {
			unset($O->_iteminputs_loop);
			return false;
		}
	}

	function item_inputs_list ($result, $options, $O) {
		$item = current($O->purchased);
		if (empty($item->data)) return false;
		$before = ""; $after = ""; $classes = ""; $excludes = array();
		if (!empty($options['class'])) $classes = ' class="'.$options['class'].'"';
		if (!empty($options['exclude'])) $excludes = explode(",",$options['exclude']);
		if (!empty($options['before'])) $before = $options['before'];
		if (!empty($options['after'])) $after = $options['after'];

		$result .= $before.'<ul'.$classes.'>';
		foreach ($item->data as $name => $data) {
			if (in_array($name,$excludes)) continue;
			$result .= '<li><strong>'.esc_html($name).'</strong>: '.esc_html($data).'</li>';
		}
		$result .= '</ul>'.$after;
		return $result;
	}

	function item_name ($result, $options, $O) {
		$item = current($O->purchased);
		return $item->name;
	}

	function item_options ($result, $options, $O) {
		if (!isset($options['after'])) $options['after'] = "";
		$item = current($O->purchased);
		return (!empty($item->optionlabel))?$options['before'].$item->optionlabel.$options['after']:'';
	}

	function item_price ($result, $options, $O) {
		$item = current($O->purchased);
		return $item->price;
	}

	function item_product ($result, $options, $O) {
		$item = current($O->purchased);
		return $item->product;
	}

	function item_quantity ($result, $options, $O) {
		$item = current($O->purchased);
		return $item->quantity;
	}

	function items ($result, $options, $O) {
		if (!isset($O->_items_loop)) {
			reset($O->purchased);
			$O->_items_loop = true;
		} else next($O->purchased);

		if (current($O->purchased) !== false) return true;
		else {
			unset($O->_items_loop);
			return false;
		}
	}

	function item_sku ($result, $options, $O) {
		$item = current($O->purchased);
		return $item->sku;
	}

	function item_total ($result, $options, $O) {
		$item = current($O->purchased);
		$amount = $item->total+($O->taxing == 'inclusive'?$item->unittax*$item->quantity:0);
		return money($amount);
	}

	function item_unit_price ($result, $options, $O) {
		$item = current($O->purchased);
		$amount = $item->unitprice+($O->taxing == 'inclusive'?$item->unittax:0);
		return money($amount);
	}

	function last_name ($result, $options, $O) { return esc_html($O->lastname); }

	function not_paid ($result, $options, $O) { return ($O->txnstatus != "CHARGED"); }

	function order_data ($result, $options, $O) {
		if (!isset($O->_data_loop)) {
			reset($O->data);
			$O->_data_loop = true;
		} else next($O->data);

		if (current($O->data) !== false) return true;
		else {
			unset($O->_data_loop);
			return false;
		}
	}

	function paid ($result, $options, $O) { return ($O->txnstatus == "CHARGED"); }

	function payment ($result, $options, $O) {
		$labels = Lookup::payment_status_labels();
		return isset($labels[$O->txnstatus])?$labels[$O->txnstatus]:$O->txnstatus;
	}

	function phone ($result, $options, $O) { return esc_html($O->phone); }

	function postcode ($result, $options, $O) { return esc_html($O->postcode); }

	function promo_list ($result, $options, $O) {
		$output = "";
		if (!empty($O->promos)) {
			$output .= '<ul>';
			foreach ($O->promos as $promo)
				$output .= '<li>'.$promo.'</li>';
			$output .= '</ul>';
		}
		return $output;
	}

	function receipt ($result, $options, $O) {
		// Skip the receipt processing when sending order notifications in admin without the receipt
		if (defined('WP_ADMIN') && isset($_POST['receipt']) && $_POST['receipt'] == "no") return;
		if (isset($options['template']) && is_readable(SHOPP_TEMPLATES."/".$options['template']))
			return $O->receipt($template);
		else return $O->receipt();
	}

	function ship_address ($result, $options, $O) { return esc_html($O->shipaddress); }

	function ship_city ($result, $options, $O) { return esc_html($O->shipcity); }

	function ship_country ($result, $options, $O) {
		global $Shopp;
		$countries = $Shopp->Settings->get('target_markets');
		return $countries[$O->shipcountry];
	}

	function ship_method ($result, $options, $O) { return esc_html($O->shipmethod); }

	function ship_postcode ($result, $options, $O) { return esc_html($O->shippostcode); }

	function ship_state ($result, $options, $O) {
		if (strlen($O->shipstate > 2)) return esc_html($O->shipstate);
		$regions = Lookup::country_zones();
		$states = $regions[$O->country];
		return $states[$O->shipstate];
	}

	function ship_xaddress ($result, $options, $O) { return esc_html($O->shipxaddress); }

	function state ($result, $options, $O) {
		if (strlen($O->state > 2)) return esc_html($O->state);
		$regions = Lookup::country_zones();
		$states = $regions[$O->country];
		return $states[$O->state];
	}

	function status ($result, $options, $O) {
		global $Shopp;
		$labels = $Shopp->Settings->get('order_status');
		if (empty($labels)) $labels = array('');
		return $labels[$O->status];
	}

	function subtotal ($result, $options, $O) { return money($O->subtotal); }

	function tax ($result, $options, $O) { return money($O->tax); }

	function total ($result, $options, $O) { return money($O->total); }

	function total_items ($result, $options, $O) { return count($O->purchased); }

	function txnid ($result, $options, $O) { return $O->txnid; }

	function url ($result, $options, $O) { return shoppurl(false,'account'); }

	function xaddress ($result, $options, $O) { return esc_html($O->xaddress); }

}

?>