<?php
/**
* ShoppPurchaseThemeAPI - Provided theme api tags.
*
* @version 1.0
* @since 1.2
* @package shopp
* @subpackage ShoppPurchaseThemeAPI
*
**/

/**
 * Provides shopp('purchase') theme API functionality
 *
 * @author Jonathan Davis, John Dillick
 * @since 1.2
 *
 **/
class ShoppPurchaseThemeAPI implements ShoppAPI {
	static $context = 'Purchase';
	static $register = array(
		'address' => 'address',
		'card' => 'card',
		'cardtype' => 'card_type',
		'city' => 'city',
		'company' => 'company',
		'country' => 'country',
		'customer' => 'customer',
		'data' => 'data',
		'date' => 'date',
		'discount' => 'discount',
		'email' => 'email',
		'emailfrom' => 'email_from',
		'emailsubject' => 'email_subject',
		'emailto' => 'email_to',
		'emailevent' => 'email_event',
		'emailnote' => 'email_note',
		'firstname' => 'first_name',
		'freight' => 'freight',
		'hasdata' => 'has_data',
		'hasitems' => 'has_items',
		'haspromo' => 'has_promo',
		'hasdiscount' => 'has_discount',
		'hasdownloads' => 'has_downloads',
		'hasfreight' => 'has_freight',
		'hastax' => 'has_tax',
		'id' => 'id',
		'itemaddons' => 'item_addons',
		'itemaddonslist' => 'item_addons_list',
		'itemdescription' => 'item_description',
		'itemdownload' => 'item_download',
		'itemhasaddons' => 'item_has_addons',
		'itemhasinputs' => 'item_has_inputs',
		'itemid' => 'item_id',
		'iteminput' => 'item_input',
		'iteminputs' => 'item_inputs',
		'iteminputslist' => 'item_inputs_list',
		'itemname' => 'item_name',
		'itemoptions' => 'item_options',
		'itemprice' => 'item_price',
		'itemproduct' => 'item_product',
		'itemquantity' => 'item_quantity',
		'itemsku' => 'item_sku',
		'itemtotal' => 'item_total',
		'itemunitprice' => 'item_unit_price',
		'itemtype' => 'item_type',
		'items' => 'items',
		'lastname' => 'last_name',
		'notpaid' => 'not_paid',
		'orderdata' => 'order_data',
		'paid' => 'paid',
		'payment' => 'payment',
		'paymethod' => 'paymethod',
		'phone' => 'phone',
		'postcode' => 'postcode',
		'promolist' => 'promo_list',
		'gateway' => 'gateway',
		'receipt' => 'receipt',
		'shipaddress' => 'ship_address',
		'shipcity' => 'ship_city',
		'shipcountry' => 'ship_country',
		'shipmethod' => 'ship_method',
		'shippostcode' => 'ship_postcode',
		'shipstate' => 'ship_state',
		'shipxaddress' => 'ship_xaddress',
		'state' => 'state',
		'status' => 'status',
		'subtotal' => 'subtotal',
		'tax' => 'tax',
		'total' => 'total',
		'totalitems' => 'total_items',
		'txnid' => 'txnid',
		'transactionid' => 'txnid',
		'url' => 'url',
		'xaddress' => 'xaddress'
	);

	static function _apicontext () { return 'purchase'; }

	/**
	 * _setobject - returns the global context object used in the shopp('purchase') call
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 **/
	static function _setobject ($Object, $object) {
		if ( is_object($Object) && is_a($Object, 'Purchase') ) return $Object;

		if ( strtolower($object) != 'purchase' ) return $Object; // not mine, do nothing
		else {
			return ShoppPurchase();
		}
	}

	function address ($result, $options, $O) { return esc_html($O->address); }

	function card ($result, $options, $O) { return (!empty($O->card))?sprintf("%'X16d",$O->card):''; }

	function card_type ($result, $options, $O) { return $O->cardtype; }

	function city ($result, $options, $O) { return esc_html($O->city); }

	function company ($result, $options, $O) { return esc_html($O->company); }

	function country ($result, $options, $O) {
		global $Shopp;
		$countries = shopp_setting('target_markets');
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

	// email_* tags are for email headers. The trailing PHP_EOL is to account for PHP ticket #21891
	// where trailing newlines are removed, despite the PHP docs saying they will be included.

	function email_from ($result, $options, $O) { return ($O->message['from'].PHP_EOL); }

	function email_to ($result, $options, $O) { return ($O->message['to'].PHP_EOL); }

	function email_subject ($result, $options, $O) { return ($O->message['subject'].PHP_EOL); }

	function email_event ($result, $options, $O) {
		if (!isset($O->message['event'])) return '';
		extract($options);

		$Event = $O->message['event'];
		if (isset($Event->$name))
			return esc_html($Event->$name);
		return '';
	}

	function email_note ($result, $options, $O) { return esc_html($O->message['note']); }

	function first_name ($result, $options, $O) { return esc_html($O->firstname); }

	function freight ($result, $options, $O) { return money($O->freight); }

	function gateway ($result, $options, $O) { return $O->gateway; }

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
		// if (isset($options['onsale'])) return $addon->value->sale;
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

	function item_type ($result, $options, $O) {
		$item = current($O->purchased);
		return $item->type;
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

	function not_paid ($result, $options, $O) { return ('captured' != $O->txnstatus); }

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

	function paid ($result, $options, $O) { return ('captured' == $O->txnstatus); }

	function payment ($result, $options, $O) {
		$labels = Lookup::txnstatus_labels();
		return isset($labels[$O->txnstatus])?$labels[$O->txnstatus]:$O->txnstatus;
	}

	function paymethod ($result, $options, $O) { return $O->paymethod; }

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

		if (isset($options['template']))
			$template = locate_shopp_template($options['template']);

		if ('' != $template) return $O->receipt($template);

		return $O->receipt();
	}

	function ship_address ($result, $options, $O) { return esc_html($O->shipaddress); }

	function ship_city ($result, $options, $O) { return esc_html($O->shipcity); }

	function ship_country ($result, $options, $O) {
		global $Shopp;
		$countries = shopp_setting('target_markets');
		return $countries[$O->shipcountry];
	}

	function ship_method ($result, $options, $O) { return esc_html($O->shipoption); }

	function ship_postcode ($result, $options, $O) { return esc_html($O->shippostcode); }

	function ship_state ($result, $options, $O) {
		$state = esc_html($O->shipstate);
		if (strlen($O->state > 2)) return $state;
		$regions = Lookup::country_zones();

		if (isset($regions[$O->country])) {
			$states = $regions[$O->country];
			if (isset($states[$O->shipstate]))
				return esc_html($states[$O->shipstate]);
		}

		return $state;
	}

	function ship_xaddress ($result, $options, $O) { return esc_html($O->shipxaddress); }

	function state ($result, $options, $O) {
		$state = esc_html($O->state);
		if (strlen($O->state > 2)) return $state;
		$regions = Lookup::country_zones();

		if (isset($regions[$O->country])) {
			$states = $regions[$O->country];
			if (isset($states[$O->state]))
				return esc_html($states[$O->state]);
		}

		return $state;
	}

	function status ($result, $options, $O) {
		global $Shopp;
		$labels = shopp_setting('order_status');
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