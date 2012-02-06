<?php
/**
* ShoppCartThemeAPI - Provided theme api tags.
*
* @version 1.0
* @since 1.2
* @package shopp
* @subpackage ShoppCartThemeAPI
*
**/

/**
 * Provides shopp('cart') theme api functionality
 *
 * @author Jonathan Davis, John Dillick
 * @since 1.2
 *
 **/
class ShoppCartThemeAPI implements ShoppAPI {
	static $register = array(
		'_cart',
		'discount' => 'discount',
		'discounts' => 'discounts',
		'downloaditems' => 'download_items',
		'emptybutton' => 'empty_button',
		'function' => 'cart_function',
		'hasdiscount' => 'has_discount',
		'hasdownloads' => 'has_downloads',
		'hasitems' => 'has_items',
		'haspromos' => 'has_promos',
		'hasshipcosts' => 'has_ship_costs',
		'hasshipped' => 'has_shipped',
		'hasshippingmethods' => 'has_shipping_methods',
		'hastaxes' => 'has_taxes',
		'items' => 'items',
		'lastitem' => 'last_item',
		'needsshipped' => 'needs_shipped',
		'needsshippingestimates' => 'needs_shipping_estimates',
		'promocode' => 'promocode',
		'promos' => 'promos',
		'promosavailable' => 'promos_available',
		'promodiscount' => 'promo_discount',
		'promoname' => 'promo_name',
		'referer' => 'referrer',
		'referrer' => 'referrer',
		'shipping' => 'shipping',
		'shippingestimates' => 'shipping_estimates',
		'shippeditems' => 'shipped_items',
		'sidecart' => 'sidecart',
		'subtotal' => 'subtotal',
		'tax' => 'tax',
		'total' => 'total',
		'totalitems' => 'total_items',
		'totalquantity' => 'total_quantity',
		'totalpromos' => 'total_promos',
		'updatebutton' => 'update_button',
		'url' => 'url'
	);

	static function _apicontext () { return 'cart'; }

	/**
	 * _setobject - returns the global context object used in the shopp('cart') call
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 **/
	static function _setobject ($Object, $object) {
		if ( is_object($Object) && is_a($Object, 'Order') && isset($Object->Cart) && 'cart' == strtolower($object) )
			return $Object->Cart;
		else if ( strtolower($object) != 'cart' ) return $Object; // not mine, do nothing

		$Order =& ShoppOrder();
		return $Order->Cart;
	}

	static function _cart ($result, $options, $property, $O) {
		// Passthru for non-monetary results
		$monetary = array('discount','subtotal','shipping','tax','total');
		if (!in_array($property,$monetary) || !is_numeric($result)) return $result;

		// @deprecated currency parameter
		if (isset($options['currency'])) $options['money'] = $options['currency'];
		// @deprecated wrapper parameter
		if (isset($options['wrapper'])) $options['wrap'] = $options['wrapper'];

		$defaults = array(
			'wrap' => 'on',
			'money' => 'on',
			'number' => false,
		);
		$options = array_merge($defaults,$options);
		extract($options);


		if ( str_true($number) ) return $result;
		if ( str_true($money)  ) $result = money($result);
		if ( str_true($wrap)   ) return '<span class="shopp-cart cart-'.strtolower($property).'">'.$result.'</span>';

		return $result;
	}

	static function discount ($result, $options, $O) { return $O->Totals->discount; }

	static function discounts ($result, $options, $O) {
		if (!isset($O->_promo_looping)) {
			reset($O->discounts);
			$O->_promo_looping = true;
		} else next($O->discounts);

		$discount = current($O->discounts);
		while ($discount && empty($discount->applied) && !$discount->freeshipping)
			$discount = next($O->discounts);

		if (current($O->discounts)) return true;
		else {
			unset($O->_promo_looping);
			reset($O->discounts);
			return false;
		}
	}

	static function download_items ($result, $options, $O) {
		if (!isset($O->_downloads_loop)) {
			reset($O->downloads);
			$O->_downloads_loop = true;
		} else next($O->downloads);

		if (current($O->downloads)) return true;
		else {
			unset($O->_downloads_loop);
			reset($O->downloads);
			return false;
		}
	}

	static function empty_button ($result, $options, $O) {
		$submit_attrs = array('title','value','disabled','tabindex','accesskey','class');
		if (!isset($options['value'])) $options['value'] = __('Empty Cart','Shopp');
		return '<input type="submit" name="empty" id="empty-button" '.inputattrs($options,$submit_attrs).' />';
	}

	static function cart_function ($result, $options, $O) {
		return '<div class="hidden"><input type="hidden" id="cart-action" name="cart" value="true" /></div><input type="submit" name="update" id="hidden-update" />';
	}

	static function has_discount ($result, $options, $O) { return ($O->Totals->discount > 0); }

	static function has_downloads ($result, $options, $O) { return $O->downloads(); }

	static function has_items ($result, $options, $O) { return (count($O->contents) > 0); }

	static function has_promos ($result, $options, $O) { return (count($O->discounts) > 0);  }

	static function has_ship_costs ($result, $options, $O) { return ($O->Totals->shipping > 0); }

	static function has_shipped ($result, $options, $O) { return $O->shipped();	}

	static function has_shipping_methods ($result, $options, $O) {
		return apply_filters('shopp_shipping_hasestimates',
							( shopp_setting_enabled('shipping') && !empty($O->shipping) ),
							$O->shipping
		);
	}

	static function has_taxes ($result, $options, $O) { return ($O->Totals->tax > 0); }

	static function items ($result, $options, $O) {
		if (!isset($O->_item_loop)) {
			reset($O->contents);
			$O->_item_loop = true;
		} else next($O->contents);

		if (current($O->contents)) return true;
		else {
			unset($O->_item_loop);
			reset($O->contents);
			return false;
		}
	}

	static function last_item ($result, $options, $O) { return $O->contents[$O->added]; }

	static function needs_shipped ($result, $options, $O) { return (!empty($O->shipped)); }

	static function needs_shipping_estimates ($result, $options, $O) {
		// Shipping must be enabled, without free shipping and shipped items must be present in the cart
		return ( shopp_setting_enabled('shipping') && !( $O->freeship && empty($O->shipped) ) );
	}

	static function promocode ($result, $options, $O) {
		global $Shopp;

		$submit_attrs = array('title','value','disabled','tabindex','accesskey','class');
		// Skip if no promotions exist
		if (!$Shopp->Promotions->available()) return false;
		// Skip if the promo limit has been reached
		if (shopp_setting('promo_limit') > 0 &&
			count($O->discounts) >= shopp_setting('promo_limit')) return false;
		if (!isset($options['value'])) $options['value'] = __("Apply Promo Code","Shopp");
		$result = '<ul><li>';
		$ShoppErrors = ShoppErrors();
		if ($ShoppErrors->exist()) {
			$result .= '<p class="error">';
			$errors = $ShoppErrors->source('CartDiscounts');
			foreach ((array)$errors as $error) if (!empty($error)) $result .= $error->message(true,false);
			$result .= '</p>';
		}

		$result .= '<span><input type="text" id="promocode" name="promocode" value="" size="10" /></span>';
		$result .= '<span><input type="submit" id="apply-code" name="update" '.inputattrs($options,$submit_attrs).' /></span>';
		$result .= '</li></ul>';
		return $result;
	}

	static function promo_discount ($result, $options, $O) {
		$discount = current($O->discounts);
		if ($discount->applied == 0 && empty($discount->items) && !isset($O->freeshipping)) return false;
		if (!isset($options['label'])) $options['label'] = ' '.__('Off!','Shopp');
		else $options['label'] = ' '.$options['label'];
		$string = false;
		if (!empty($options['before'])) $string = $options['before'];

		switch($discount->type) {
			case "Free Shipping": $string .= money((float)$discount->freeshipping).$options['label']; break;
			case "Percentage Off": $string .= percentage((float)$discount->discount,array('precision' => 0)).$options['label']; break;
			case "Amount Off": $string .= money((float)$discount->discount).$options['label']; break;
			case "Buy X Get Y Free": return sprintf(__('Buy %s get %s free','Shopp'),$discount->buyqty,$discount->getqty); break;
		}
		if (!empty($options['after'])) $string .= $options['after'];

		return $string;
	}

	static function promo_name ($result, $options, $O) {
		$discount = current($O->discounts);
		if ($discount->applied == 0 && empty($discount->items) && !isset($O->freeshipping)) return false;
		return $discount->name;
	}

	static function promos ($result, $options, $O) {
		if (!isset($O->_promo_looping)) {
			reset($O->discounts);
			$O->_promo_looping = true;
		} else next($O->discounts);

		$discount = current($O->discounts);
		while ($discount && empty($discount->applied) && !$discount->freeshipping)
			$discount = next($O->discounts);

		if (current($O->discounts)) return true;
		else {
			unset($O->_promo_looping);
			reset($O->discounts);
			return false;
		}
	}

	static function promos_available ($result, $options, $O) {
		global $Shopp;
		if (!$Shopp->Promotions->available()) return false;
		// Skip if the promo limit has been reached
		if (shopp_setting('promo_limit') > 0 &&
			count($O->discounts) >= shopp_setting('promo_limit')) return false;
		return true;
	}

	static function referrer ($result, $options, $O) {
		$Shopping = ShoppShopping();
		$referrer = $Shopping->data->referrer;
		if (!$referrer) $referrer = shopp('catalog','url','return=1');
		return $referrer;
	}

	static function shipped_items ($result, $options, $O) {
		if (!isset($O->_shipped_loop)) {
			reset($O->shipped);
			$O->_shipped_loop = true;
		} else next($O->shipped);

		if (current($O->shipped)) return true;
		else {
			unset($O->_shipped_loop);
			reset($O->shipped);
			return false;
		}
	}

	static function shipping ($result, $options, $O) {
		if (empty($O->shipped)) return "";
		if (isset($options['label'])) {
			$options['currency'] = "false";
			if ($O->freeshipping) {
				$result = shopp_setting('free_shipping_text');
				if (empty($result)) $result = __('Free Shipping!','Shopp');
			}

			else $result = $options['label'];
		} else {
			if ($O->Totals->shipping === null)
				return __("Enter Postal Code","Shopp");
			elseif ($O->Totals->shipping === false)
				return __("Not Available","Shopp");
			else $result = $O->Totals->shipping;
		}
		return $result;
	}

	static function shipping_estimates ($result, $options, $O) {
		$defaults = array(
			'postcode' => true,
			'class' => 'ship-estimates'
		);
		$options = array_merge($defaults,$options);
		extract($options);

		if (empty($O->shipped)) return '';

		$base = shopp_setting('base_operations');
		$markets = shopp_setting('target_markets');
		$Shipping = ShoppOrder()->Shipping;

		if (empty($markets)) return '';

		foreach ($markets as $iso => $country) $countries[$iso] = $country;
		if (!empty($Shipping->country)) $selected = $Shipping->country;
		else $selected = $base['country'];
		$postcode = (str_true($postcode) || $O->showpostcode);

		$_[] = '<div class="'.$class.'">';
		if (count($countries) > 1) {
			$_[] = '<span>';
			$_[] = '<select name="shipping[country]" id="shipping-country">';
			$_[] = menuoptions($countries,$selected,true);
			$_[] = '</select>';
			$_[] = '</span>';
		} else $_[] = '<input type="hidden" name="shipping[country]" id="shipping-country" value="'.key($markets).'" />';
		if ($postcode) {
			$_[] = '<span>';
			$_[] = '<input type="text" name="shipping[postcode]" id="shipping-postcode" size="6" value="'.$Shipping->postcode.'" />&nbsp;';
			$_[] = '</span>';
			$_[] = shopp('cart','get-update-button',array('value' => __('Estimate Shipping & Taxes','Shopp')));
		}

		$_[] = '</div>';

		return join('',$_);
	}

	static function sidecart ($result, $options, $O) {
		if (!shopp_setting_enabled('shopping_cart')) return '';
		ob_start();
		locate_shopp_template(array('sidecart.php'),true);
		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}

	static function subtotal ($result, $options, $O) { return $O->Totals->subtotal; }

	static function tax ($result, $options, $O) {
		$defaults = array(
			'label' => false,
		);
		$options = array_merge($defaults,$options);
		extract($options);

		if (!empty($label)) return $label;

		return $O->Totals->tax;

	 }

	static function total ($result, $options, $O) { return $O->Totals->total; }

	static function total_items ($result, $options, $O) {
	 	return count($O->contents);
	}

	static function total_promos ($result, $options, $O) { return count($O->discounts); }

	static function total_quantity ($result, $options, $O) {
	 	return $O->Totals->quantity;
	}

	static function update_button ($result, $options, $O) {
		$submit_attrs = array('title','value','disabled','tabindex','accesskey','class');
		if (!isset($options['value'])) $options['value'] = __('Update Subtotal','Shopp');
		if (isset($options['class'])) $options['class'] .= " update-button";
		else $options['class'] = "update-button";
		return '<input type="submit" name="update"'.inputattrs($options,$submit_attrs).' />';
	}

	static function url ($result, $options, $O) {
			return shoppurl(false,'cart');
	}
}

?>