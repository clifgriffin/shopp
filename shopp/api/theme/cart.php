<?php
add_filter('shoppapi_cart', array('ShoppCartAPI', '_cart'), 10, 4); // default filter

add_filter('shoppapi_cart_url', array('ShoppCartAPI', 'url'), 10, 3);
add_filter('shoppapi_cart_referrer', array('ShoppCartAPI', 'referrer'), 10, 3);
add_filter('shoppapi_cart_referer', array('ShoppCartAPI', 'referrer'), 10, 3);
add_filter('shoppapi_cart_hasitems', array('ShoppCartAPI', 'hasitems'), 10, 3);
add_filter('shoppapi_cart_totalitems', array('ShoppCartAPI', 'totalitems'), 10, 3);
add_filter('shoppapi_cart_items', array('ShoppCartAPI', 'items'), 10, 3);
add_filter('shoppapi_cart_hasshipped', array('ShoppCartAPI', 'hasshipped'), 10, 3);
add_filter('shoppapi_cart_shippeditems', array('ShoppCartAPI', 'shippeditems'), 10, 3);
add_filter('shoppapi_cart_hasdownloads', array('ShoppCartAPI', 'hasdownloads'), 10, 3);
add_filter('shoppapi_cart_downloaditems', array('ShoppCartAPI', 'downloaditems'), 10, 3);
add_filter('shoppapi_cart_lastitem', array('ShoppCartAPI', 'lastitem'), 10, 3);
add_filter('shoppapi_cart_totalpromos', array('ShoppCartAPI', 'totalpromos'), 10, 3);
add_filter('shoppapi_cart_haspromos', array('ShoppCartAPI', 'haspromos'), 10, 3);
add_filter('shoppapi_cart_discounts', array('ShoppCartAPI', 'discounts'), 10, 3);
add_filter('shoppapi_cart_promos', array('ShoppCartAPI', 'promos'), 10, 3);
add_filter('shoppapi_cart_promoname', array('ShoppCartAPI', 'promoname'), 10, 3);
add_filter('shoppapi_cart_promodiscount', array('ShoppCartAPI', 'promodiscount'), 10, 3);
add_filter('shoppapi_cart_function', array('ShoppCartAPI', 'cartfunction'), 10, 3);
add_filter('shoppapi_cart_emptybutton', array('ShoppCartAPI', 'emptybutton'), 10, 3);
add_filter('shoppapi_cart_updatebutton', array('ShoppCartAPI', 'updatebutton'), 10, 3);
add_filter('shoppapi_cart_sidecart', array('ShoppCartAPI', 'sidecart'), 10, 3);
add_filter('shoppapi_cart_hasdiscount', array('ShoppCartAPI', 'hasdiscount'), 10, 3);
add_filter('shoppapi_cart_discount', array('ShoppCartAPI', 'discount'), 10, 3);
add_filter('shoppapi_cart_promosavailable', array('ShoppCartAPI', 'promosavailable'), 10, 3);
add_filter('shoppapi_cart_promocode', array('ShoppCartAPI', 'promocode'), 10, 3);
add_filter('shoppapi_cart_hasshippingmethods', array('ShoppCartAPI', 'hasshippingmethods'), 10, 3);
add_filter('shoppapi_cart_needsshipped', array('ShoppCartAPI', 'needsshipped'), 10, 3);
add_filter('shoppapi_cart_hasshipcosts', array('ShoppCartAPI', 'hasshipcosts'), 10, 3);
add_filter('shoppapi_cart_needsshippingestimates', array('ShoppCartAPI', 'needsshippingestimates'), 10, 3);
add_filter('shoppapi_cart_shippingestimates', array('ShoppCartAPI', 'shippingestimates'), 10, 3);
add_filter('shoppapi_cart_subtotal', array('ShoppCartAPI', 'subtotal'), 10, 3);
add_filter('shoppapi_cart_shipping', array('ShoppCartAPI', 'shipping'), 10, 3);
add_filter('shoppapi_cart_hastaxes', array('ShoppCartAPI', 'hastaxes'), 10, 3);
add_filter('shoppapi_cart_tax', array('ShoppCartAPI', 'tax'), 10, 3);
add_filter('shoppapi_cart_total', array('ShoppCartAPI', 'total'), 10, 3);

/**
 * Provides shopp('cart') theme api functionality
 *
 * @author Jonathan Davis, John Dillic
 * @since 1.2
 *
 **/
class ShoppCartAPI {
	function _cart ($result, $options, $property, $obj) {
		if (isset($options['currency']) && !value_is_true($options['currency'])) return $result;
		if (is_numeric($result)) {
			if (isset($options['wrapper']) && !value_is_true($options['wrapper'])) return money($result);
			return '<span class="shopp_cart_'.$property.'">'.money($result).'</span>';
		}
		return $result;
	}

	function discount ($result, $options, $obj) { return money($obj->Totals->discount); }

	function discounts ($result, $options, $obj) {
		if (!isset($obj->_promo_looping)) {
			reset($obj->discounts);
			$obj->_promo_looping = true;
		} else next($obj->discounts);

		$discount = current($obj->discounts);
		while ($discount && empty($discount->applied) && !$discount->freeshipping)
			$discount = next($obj->discounts);

		if (current($obj->discounts)) return true;
		else {
			unset($obj->_promo_looping);
			reset($obj->discounts);
			return false;
		}
	}

	function downloaditems ($result, $options, $obj) {
		if (!isset($obj->_downloads_loop)) {
			reset($obj->downloads);
			$obj->_downloads_loop = true;
		} else next($obj->downloads);

		if (current($obj->downloads)) return true;
		else {
			unset($obj->_downloads_loop);
			reset($obj->downloads);
			return false;
		}
	}

	function emptybutton ($result, $options, $obj) {
		$submit_attrs = array('title','value','disabled','tabindex','accesskey','class');
		if (!isset($options['value'])) $options['value'] = __('Empty Cart','Shopp');
		return '<input type="submit" name="empty" id="empty-button" '.inputattrs($options,$submit_attrs).' />';
	}

	function cartfunction ($result, $options, $obj) {
		$result = '<div class="hidden"><input type="hidden" id="cart-action" name="cart" value="true" /></div><input type="submit" name="update" id="hidden-update" />';

		$Errors = &ShoppErrors();
		if (!$Errors->exist(SHOPP_STOCK_ERR)) return $result;

		ob_start();
		include(SHOPP_TEMPLATES."/errors.php");
		$errors = ob_get_contents();
		ob_end_clean();
		return $result.$errors;
	}

	function hasdiscount ($result, $options, $obj) { return ($obj->Totals->discount > 0); }

	function hasdownloads ($result, $options, $obj) { return $obj->downloads(); }

	function hasitems ($result, $options, $obj) { return (count($obj->contents) > 0); }

	function haspromos ($result, $options, $obj) { return (count($obj->discounts) > 0);  }

	function hasshipcosts ($result, $options, $obj) { return ($obj->Totals->shipping > 0); }

	function hasshipped ($result, $options, $obj) { return $obj->shipped();	}

	function hasshippingmethods ($result, $options, $obj) {
		return apply_filters(
					'shopp_shipping_hasestimates',
					(!empty($obj->shipping) && !$obj->noshipping),
					$obj->shipping
				);
	}

	function hastaxes ($result, $options, $obj) { return ($obj->Totals->tax > 0); }

	function items ($result, $options, $obj) {
		if (!isset($obj->_item_loop)) {
			reset($obj->contents);
			$obj->_item_loop = true;
		} else next($obj->contents);

		if (current($obj->contents)) return true;
		else {
			unset($obj->_item_loop);
			reset($obj->contents);
			return false;
		}
	}

	function lastitem ($result, $options, $obj) { return $obj->contents[$obj->added]; }

	function needsshipped ($result, $options, $obj) { return (!empty($obj->shipped)); }

	function needsshippingestimates ($result, $options, $obj) {
		global $Shopp;
		$markets = $Shopp->Settings->get('target_markets');
		return (!empty($obj->shipped) && !$obj->noshipping && ($obj->showpostcode || count($markets) > 1));
	}

	function promocode ($result, $options, $obj) {
		global $Shopp;
		$submit_attrs = array('title','value','disabled','tabindex','accesskey','class');
		// Skip if no promotions exist
		if (!$Shopp->Promotions->available()) return false;
		// Skip if the promo limit has been reached
		if ($Shopp->Settings->get('promo_limit') > 0 &&
			count($obj->discounts) >= $Shopp->Settings->get('promo_limit')) return false;
		if (!isset($options['value'])) $options['value'] = __("Apply Promo Code","Shopp");
		$result = '<ul><li>';

		if ($Shopp->Errors->exist()) {
			$result .= '<p class="error">';
			$errors = $Shopp->Errors->source('CartDiscounts');
			foreach ((array)$errors as $error) if (!empty($error)) $result .= $error->message(true,false);
			$result .= '</p>';
		}

		$result .= '<span><input type="text" id="promocode" name="promocode" value="" size="10" /></span>';
		$result .= '<span><input type="submit" id="apply-code" name="update" '.inputattrs($options,$submit_attrs).' /></span>';
		$result .= '</li></ul>';
		return $result;
	}

	function promodiscount ($result, $options, $obj) {
		$discount = current($obj->discounts);
		if ($discount->applied == 0 && empty($discount->items) && !isset($obj->freeshipping)) return false;
		if (!isset($options['label'])) $options['label'] = ' '.__('Off!','Shopp');
		else $options['label'] = ' '.$options['label'];
		$string = false;
		if (!empty($options['before'])) $string = $options['before'];

		switch($discount->type) {
			case "Free Shipping": $string .= money($discount->freeshipping).$options['label']; break;
			case "Percentage Off": $string .= percentage($discount->discount,array('precision' => 0)).$options['label']; break;
			case "Amount Off": $string .= money($discount->discount).$options['label']; break;
			case "Buy X Get Y Free": return sprintf(__('Buy %s get %s free','Shopp'),$discount->buyqty,$discount->getqty); break;
		}
		if (!empty($options['after'])) $string .= $options['after'];

		return $string;
	}

	function promoname ($result, $options, $obj) {
		$discount = current($obj->discounts);
		if ($discount->applied == 0 && empty($discount->items) && !isset($obj->freeshipping)) return false;
		return $discount->name;
	}

	function promos ($result, $options, $obj) {}

	function promosavailable ($result, $options, $obj) {
		global $Shopp;
		if (!$Shopp->Promotions->available()) return false;
		// Skip if the promo limit has been reached
		if ($Shopp->Settings->get('promo_limit') > 0 &&
			count($obj->discounts) >= $Shopp->Settings->get('promo_limit')) return false;
		return true;
	}

	function referrer ($result, $options, $obj) {
		global $Shopp;
		$referrer = $Shopp->Shopping->data->referrer;
		if (!$referrer) $referrer = shopp('catalog','url','return=1');
		return $referrer;
	}

	function shippeditems ($result, $options, $obj) {
		if (!isset($obj->_shipped_loop)) {
			reset($obj->shipped);
			$obj->_shipped_loop = true;
		} else next($obj->shipped);

		if (current($obj->shipped)) return true;
		else {
			unset($obj->_shipped_loop);
			reset($obj->shipped);
			return false;
		}
	}

	function shipping ($result, $options, $obj) {
		global $Shopp;
		if (empty($obj->shipped)) return "";
		if (isset($options['label'])) {
			$options['currency'] = "false";
			if ($obj->freeshipping) {
				$result = $Shopp->Settings->get('free_shipping_text');
				if (empty($result)) $result = __('Free Shipping!','Shopp');
			}

			else $result = $options['label'];
		} else {
			if ($obj->Totals->shipping === null)
				return __("Enter Postal Code","Shopp");
			elseif ($obj->Totals->shipping === false)
				return __("Not Available","Shopp");
			else $result = $obj->Totals->shipping;
		}
		return $result;
	}

	function shippingestimates ($result, $options, $obj) {
		global $Shopp;
		if (empty($obj->shipped)) return "";
		$base = $Shopp->Settings->get('base_operations');
		$markets = $Shopp->Settings->get('target_markets');
		$Shipping = &$Shopp->Order->Shipping;
		if (empty($markets)) return "";
		foreach ($markets as $iso => $country) $countries[$iso] = $country;
		if (!empty($Shipping->country)) $selected = $Shipping->country;
		else $selected = $base['country'];
		$postcode = false;
		$result .= '<ul><li>';
		if ((isset($options['postcode']) && value_is_true($options['postcode'])) || $obj->showpostcode) {
			$postcode = true;
			$result .= '<span>';
			$result .= '<input type="text" name="shipping[postcode]" id="shipping-postcode" size="6" value="'.$Shipping->postcode.'" />&nbsp;';
			$result .= '</span>';
		}
		if (count($countries) > 1) {
			$result .= '<span>';
			$result .= '<select name="shipping[country]" id="shipping-country">';
			$result .= menuoptions($countries,$selected,true);
			$result .= '</select>';
			$result .= '</span>';
		} else $result .= '<input type="hidden" name="shipping[country]" id="shipping-country" value="'.key($markets).'" />';
		if ($postcode) {
			$result .= '</li><li>';
			$result .= shopp('cart','update-button',array('value' => __('Estimate Shipping & Taxes','Shopp'),'return'=>'1'));
		}

		$result .= '</li></ul>';
		return $result;
	}

	function sidecart ($result, $options, $obj) {
		ob_start();
		include(SHOPP_TEMPLATES."/sidecart.php");
		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}

	function subtotal ($result, $options, $obj) { return $obj->Totals->subtotal; }

	function tax ($result, $options, $obj) {
		if ($obj->Totals->tax > 0) {
			if (isset($options['label'])) {
				$options['currency'] = "false";
				$result = $options['label'];
			} else $result = $obj->Totals->tax;
		} else $options['currency'] = "false";
		return $result;
	}

	function total ($result, $options, $obj) { return $obj->Totals->total; }

	function totalitems ($result, $options, $obj) {
	 	return $obj->Totals->quantity;
	}

	function totalpromos ($result, $options, $obj) { return count($obj->discounts); }

	function updatebutton ($result, $options, $obj) {
		$submit_attrs = array('title','value','disabled','tabindex','accesskey','class');
		if (!isset($options['value'])) $options['value'] = __('Update Subtotal','Shopp');
		if (isset($options['class'])) $options['class'] .= " update-button";
		else $options['class'] = "update-button";
		return '<input type="submit" name="update"'.inputattrs($options,$submit_attrs).' />';
	}

	function url ($result, $options, $obj) {
			return shoppurl(false,'cart');
	}
}

?>