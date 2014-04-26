<?php
/**
 * cart.php
 *
 * ShoppCartThemeAPI provides shopp('cart') Theme API tags
 *
 * @api
 * @copyright Ingenesis Limited, 2012-2013
 * @package shopp
 * @since 1.2
 * @version 1.3
 **/

defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit; // Prevent direct access

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
		'applycode' => 'applycode',
		'applygiftcard' => 'applygiftcard',
		'discount' => 'discount',
		'discountapplied' => 'discount_applied',
		'discountname' => 'discount_name',
		'discountremove' => 'discount_remove',
		'discounts' => 'discounts',
		'discountsavailable' => 'discounts_available',
		'downloaditems' => 'download_items',
		'emptybutton' => 'empty_button',
		'function' => 'cart_function',
		'hasdiscount' => 'has_discount',
		'hasdiscounts' => 'has_discounts',
		'hasdownloads' => 'has_downloads',
		'hasitems' => 'has_items',
		'hasshipcosts' => 'has_ship_costs',
		'hasshipped' => 'has_shipped',
		'hasshippingmethods' => 'has_shipping_methods',
		'hastaxes' => 'has_taxes',
		'items' => 'items',
		'lastitem' => 'last_item',
		'needsshipped' => 'needs_shipped',
		'needsshippingestimates' => 'needs_shipping_estimates',
		'referer' => 'referrer',
		'referrer' => 'referrer',
		'shipping' => 'shipping',
		'shippingestimates' => 'shipping_estimates',
		'shippeditems' => 'shipped_items',
		'sidecart' => 'sidecart',
		'subtotal' => 'subtotal',
		'tax' => 'tax',
		'total' => 'total',
		'totaldiscounts' => 'total_discounts',
		'totalitems' => 'total_items',
		'totalquantity' => 'total_quantity',
		'updatebutton' => 'update_button',
		'url' => 'url',
		'hassavings' => 'has_savings',
		'savings' => 'savings',

		/* Deprecated tag names - do not use */
		'haspromos' => 'has_discounts',
		'promocode' => 'applycode',
		'promos' => 'discounts',
		'promosavailable' => 'discounts_available',
		'promodiscount' => 'discount_applied',
		'promoname' => 'discount_name',
		'totalpromos' => 'total_discounts',
	);

	public static function _apicontext () {
		return 'cart';
	}

	/**
	 * _setobject - returns the global context object used in the shopp('cart') call
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 **/
	public static function _setobject ( $Object, $object ) {
		if ( is_object($Object) && is_a($Object, 'ShoppOrder') && isset($Object->Cart) && 'cart' == strtolower($object) )
			return $Object->Cart;
		else if ( strtolower($object) != 'cart' ) return $Object; // not mine, do nothing

		$Order = ShoppOrder();
		return $Order->Cart;
	}

	public static function _cart ( $result, $options, $property, $O) {
		// Passthru for non-monetary results
		$monetary = array('discount', 'subtotal', 'shipping', 'tax', 'total');
		if ( ! in_array($property, $monetary) || ! is_numeric($result) ) return $result;

		// @deprecated currency parameter
		if ( isset($options['currency']) ) $options['money'] = $options['currency'];
		// @deprecated wrapper parameter
		if ( isset($options['wrapper']) ) $options['wrap'] = $options['wrapper'];

		$defaults = array(
			'wrap' => 'on',
			'money' => 'on',
			'number' => false,
		);
		$options = array_merge($defaults, $options);
		extract($options);

		if ( Shopp::str_true($number) ) return $result;
		if ( Shopp::str_true($money)  ) $result = money( roundprice($result) );
		if ( Shopp::str_true($wrap)   ) return '<span class="shopp-cart cart-' . strtolower($property) . '">' . $result . '</span>';

		return $result;
	}

	public static function applycode ( $result, $options, $O ) {

		$submit_attrs = array( 'title', 'value', 'disabled', 'tabindex', 'accesskey', 'class', 'autocomplete', 'placeholder', 'required' );

		// Skip if discounts are not available
		if ( ! self::discounts_available(false, false, $O) ) return false;

		if ( ! isset($options['value']) ) $options['value'] = __('Apply Discount', 'Shopp');

		$result = '<div class="applycode">';

		$defaults = array(
			'before' => '<p class="error">',
			'after' => '</p>'
		);
		$options = array_merge($defaults, $options);
		extract($options);

		$Errors = ShoppErrorStorefrontNotices();
		if ( $Errors->exist() ) {
			while ( $Errors->exist() )
				$result .=  $before . $Errors->message() . $after;
		}

		$result .= '<span><input type="text" id="discount-code" name="discount" value="" size="10" /></span>';
		$result .= '<span><input type="submit" id="apply-code" name="update" ' . inputattrs($options, $submit_attrs) . ' /></span>';
		$result .= '</div>';
		return $result;
	}

	public static function applygiftcard ( $result, $options, $O ) {

		$submit_attrs = array( 'title', 'value', 'disabled', 'tabindex', 'accesskey', 'class', 'autocomplete', 'placeholder', 'required' );

		if ( ! isset($options['value']) ) $options['value'] = Shopp::__('Add Gift Card');

		$result = '<div class="apply-giftcard">';

		$defaults = array(
			'before' => '<p class="error">',
			'after' => '</p>'
		);
		$options = array_merge($defaults, $options);
		extract($options);

		$Errors = ShoppErrorStorefrontNotices();
		if ( $Errors->exist() ) {
			while ( $Errors->exist() )
				$result .=  $before . $Errors->message() . $after;
		}

		$result .= '<span><input type="text" id="giftcard" name="credit" value="" size="20" /></span>';
		$result .= '<span><input type="submit" id="apply-giftcard" name="giftcard" ' . inputattrs($options, $submit_attrs) . ' /></span>';
		$result .= '</div>';
		return $result;
	}

	public static function discount ( $result, $options, $O ) {
		return abs($O->total('discount'));
	}

	public static function discount_applied ( $result, $options, $O ) {
		$Discount = ShoppOrder()->Discounts->current();
		if ( ! $Discount->applies() ) return false;

		$defaults = array(
			'label' => __('%s off', 'Shopp'),
			'creditlabel' => __('%s applied', 'Shopp'),
			'before' => '',
			'after' => '',
			'remove' => 'on'
		);
		$options = array_merge($defaults, $options);
		extract($options, EXTR_SKIP);

		if ( false === strpos($label, '%s') )
			$label = "%s $label";

		$string = $before;

		switch ( $Discount->type() ) {
			case ShoppOrderDiscount::SHIP_FREE:		$string .= Shopp::esc_html__( 'Free Shipping!' ); break;
			case ShoppOrderDiscount::PERCENT_OFF:	$string .= sprintf(esc_html($label), percentage($Discount->discount(), array('precision' => 0))); break;
			case ShoppOrderDiscount::AMOUNT_OFF:	$string .= sprintf(esc_html($label), money($Discount->discount())); break;
			case ShoppOrderDiscount::CREDIT:		$string .= sprintf(esc_html($creditlabel), money($Discount->amount())); break;
			case ShoppOrderDiscount::BOGOF:			list($buy, $get) = $Discount->discount(); $string .= ucfirst(strtolower(Shopp::esc_html__('Buy %s Get %s Free', $buy, $get))); break;
		}

		if ( Shopp::str_true($remove) )
			$string .= '&nbsp;' . self::discount_remove('', $options, $O);

		$string .= $after;

		return $string;
	}

	public static function discount_name ( $result, $options, $O ) {
		$Discount = ShoppOrder()->Discounts->current();
		if ( ! $Discount->applies() ) return false;
		return $Discount->name();
	}

	public static function discount_remove ( $result, $options, $O ) {
		$Discount = ShoppOrder()->Discounts->current();
		if ( ! $Discount->applies() ) return false;

		return '<a href="' . Shopp::url(array('removecode' => $Discount->id()), 'cart') . '" class="shoppui-remove-sign"><span class="hidden">' . Shopp::esc_html__('Remove Discount') . '</span></a>';
	}

	public static function discounts ( $result, $options, $O ) {

		$O = ShoppOrder()->Discounts;
		if ( ! isset($O->_looping) ) {
			$O->rewind();
			$O->_looping = true;
		} else $O->next();

		if ( $O->valid() ) return true;
		else {
			unset($O->_looping);
			$O->rewind();
			return false;
		}

	}

	public static function discounts_available ( $result, $options, $O ) {
		// Discounts are not available if there are no configured discounts loaded (Promotions)
		if ( ! ShoppOrder()->Promotions->available() ) return false;

		// Discounts are not available if the discount limit has been reached
		if ( shopp_setting('promo_limit') > 0 && ShoppOrder()->Discounts->count() >= shopp_setting('promo_limit') ) return false;
		return true;
	}

	public static function download_items ( $result, $options, $O ) {
		if ( ! isset($O->_downloads_loop) ) {
			reset($O->downloads);
			$O->_downloads_loop = true;
		} else next($O->downloads);

		if ( current($O->downloads) ) return true;
		else {
			unset($O->_downloads_loop);
			reset($O->downloads);
			return false;
		}
	}

	public static function empty_button ( $result, $options, $O ) {
		$submit_attrs = array( 'title', 'value', 'disabled', 'tabindex', 'accesskey', 'class', 'autocomplete', 'placeholder', 'required' );
		if ( ! isset($options['value']) ) $options['value'] = __('Empty Cart', 'Shopp');
		return '<input type="submit" name="empty" id="empty-button" ' . inputattrs($options,$submit_attrs) . ' />';
	}

	public static function cart_function ( $result, $options, $O ) {
		return '<div class="hidden"><input type="hidden" id="cart-action" name="cart" value="true" /></div><input type="submit" name="update" id="hidden-update" />';
	}

	public static function has_discount ( $result, $options, $O ) {
		return ( abs($O->total('discount')) > 0 );
	}

	public static function has_discounts ( $result, $options, $O ) {
		$Discounts = ShoppOrder()->Discounts;
		$Discounts->rewind();
		return ($Discounts->count() > 0);
	}

	public static function has_downloads ( $result, $options, $O ) {
		reset($O->downloads);
		return $O->downloads();
	}

	public static function has_items ( $result, $options, $O ) {
		$O->rewind();
		return $O->count() > 0;
	}

	public static function has_ship_costs ( $result, $options, $O ) {
		return ($O->total('shipping') > 0);
	}

	public static function has_shipped ( $result, $options, $O ) {
		reset($O->shipped);
		return $O->shipped();
	}

	public static function has_shipping_methods ( $result, $options, $O ) {
		return ShoppShippingThemeAPI::has_options( $result, $options, $O );
	}

	public static function has_taxes ( $result, $options, $O ) {
		return ($O->total('tax') > 0);
	}

	public static function items ( $result, $options, $O ) {
		if ( ! isset($O->_item_loop) ) {
			$O->rewind();
			$O->_item_loop = true;
		} else $O->next();

		if ( $O->valid() ) return true;
		else {
			unset($O->_item_loop);
			$O->rewind();
			return false;
		}
	}

	public static function last_item ( $result, $options, $O ) {
		return $O->added();
	}

	public static function needs_shipped ( $result, $options, $O ) {
		return ( ! empty($O->shipped) );
	}

	public static function needs_shipping_estimates ( $result, $options, $O ) {
		// Shipping must be enabled, without free shipping and shipped items must be present in the cart
		return ( shopp_setting_enabled('shipping') && ! ShoppOrder()->Shiprates->free() && ! empty($O->shipped) );
	}

	public static function referrer ( $result, $options, $O ) {
		$Shopping = ShoppShopping();
		$referrer = $Shopping->data->referrer;
		if ( ! $referrer ) $referrer = shopp('catalog', 'url', 'return=1');
		return $referrer;
	}

	public static function shipped_items ( $result, $options, $O ) {
		if ( ! isset($O->_shipped_loop) ) {
			reset($O->shipped);
			$O->_shipped_loop = true;
		} else next($O->shipped);

		if ( current($O->shipped) ) return true;
		else {
			unset($O->_shipped_loop);
			reset($O->shipped);
			return false;
		}
	}

	public static function shipping ( $result, $options, $O ) {
		if ( empty($O->shipped) ) return "";
		if ( isset($options['label']) ) {
			$options['currency'] = "false";
			if ( ShoppOrder()->Shiprates->free() ) {
				$result = shopp_setting('free_shipping_text');
				if ( empty($result) ) $result = Shopp::__('Free Shipping!');
			}

			else $result = $options['label'];
		} else {

			$shipping = $O->total('shipping');
			if ( isset($options['id']) ) {
				$Entry = $O->Totals->entry('shipping', $options['id']);
				if ( ! $Entry ) $shipping = false;
				else $shipping = $Entry->amount();
			}

			if ( false === $shipping )
				return Shopp::__('Enter Postal Code');
			elseif ( false === $shipping )
				return Shopp::__('Not Available');
			else $result = (float) $shipping;

		}
		return $result;
	}

	public static function shipping_estimates ( $result, $options, $O ) {
		$defaults = array(
			'postcode' => true,
			'class' => 'ship-estimates'
		);
		$options = array_merge($defaults, $options);
		extract($options);

		if ( empty($O->shipped) ) return '';

		$base = shopp_setting('base_operations');
		$markets = shopp_setting('target_markets');
		$Shipping = ShoppOrder()->Shipping;

		if ( empty($markets) ) return '';

		foreach ($markets as $iso => $country) $countries[$iso] = $country;
		if ( ! empty($Shipping->country) ) $selected = $Shipping->country;
		else $selected = $base['country'];
		$postcode = ( Shopp::str_true($postcode) || $O->showpostcode );

		$button = isset($button) ? esc_attr($button) : __('Estimate Shipping & Taxes', 'Shopp');

		$_ = '<div class="' . $class . '">';
		if ( count($countries) > 1 ) {
			$_ .= '<span>';
			$_ .= '<select name="shipping[country]" id="shipping-country">';
			$_ .= menuoptions($countries, $selected, true);
			$_ .= '</select>';
			$_ .= '</span>';
		} else {
			$_ .= '<input type="hidden" name="shipping[country]" id="shipping-country" value="' . key($markets) . '" />';
		}
		if ( $postcode ) {
			$_ .= '<span>';
			$_ .= '<input type="text" name="shipping[postcode]" id="shipping-postcode" size="6" value="' . $Shipping->postcode . '"' . inputattrs($options) . ' />&nbsp;';
			$_ .= '</span>';
			$_ .= shopp('cart','get-update-button', array('value' => $button));
		}

		return $_ . '</div>';
	}

	public static function sidecart ( $result, $options, $O ) {
		if ( ! shopp_setting_enabled('shopping_cart') ) return '';

		ob_start();
		locate_shopp_template(array('sidecart.php'), true);
		return ob_get_clean();

	}

	public static function subtotal ( $result, $options, $O ) {
		$defaults = array(
			'taxes' => 'on'
		);
		$options = array_merge($defaults, $options);
		extract($options, EXTR_SKIP);

		$subtotal = $O->total('order');

		// Handle no-tax option for tax inclusive storefronts
		if ( ! Shopp::str_true($taxes) && shopp_setting_enabled('tax_inclusive') ) {
			$tax = $O->Totals->entry('tax', 'Tax');
			if ( is_a($tax, 'OrderAmountItemTax') )
				$subtotal -= $tax->amount();
		}

		return (float)$subtotal;
	}

	public static function tax ( $result, $options, $O ) {
		$defaults = array(
			'label' => false,
			'id' => false
		);
		$options = array_merge($defaults, $options);
		extract($options);

		if ( ! empty($label) ) return $label;

		$tax = (float) $O->total('tax');
		if ( ! empty($id) ) {
			$Entry = $O->Totals->entry('tax', $id);
			if ( empty($Entry) ) return false;
			$tax = (float) $Entry->amount();
		}

		return $tax;

	 }

	public static function total ( $result, $options, $O ) {
		return (float)$O->total();
	}

	public static function total_items ( $result, $options, $O ) {
	 	return (int)$O->count();
	}

	public static function total_discounts ( $result, $options, $O ) {
		return (int)ShoppOrder()->Discounts->count();
	}

	public static function total_quantity ( $result, $options, $O ) {
	 	return (int)$O->total('quantity');
	}

	public static function update_button ( $result, $options, $O ) {
		$submit_attrs = array( 'title', 'value', 'disabled', 'tabindex', 'accesskey', 'class', 'autocomplete', 'placeholder', 'required' );
		if ( ! isset($options['value']) ) $options['value'] = __('Update Subtotal', 'Shopp');
		if ( isset($options['class']) ) $options['class'] .= ' update-button';
		else $options['class'] = 'update-button';
		return '<input type="submit" name="update"' . inputattrs($options, $submit_attrs) . ' />';
	}

	public static function url ( $result, $options, $O ) {
		return Shopp::url(false, 'cart');
	}

	// Check if any of the items in the cart are on sale
	public static function has_savings ( $result, $options, $O ) {
		// loop thru cart looking for $Item->sale == "on" or "1" etc
		foreach( $O as $item ) {
			if ( str_true( $item->sale ) ) return true;
		}

		return false;
	}

	// Total discount of each item PLUS any Promotional Catalog discounts
	public static function savings ( $result, $options, $O ) {
		$total = 0;

		foreach( $O as $item ){
			$total += $item->option->price * $item->quantity;
		}

		return $total - ( $O->total('order') + $O->total('discount') );
	}

}
