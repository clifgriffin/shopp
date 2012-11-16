<?php
/**
 * Order Amount Tiers
 *
 * Provides shipping calculations based on order amount ranges
 *
 * @author Jonathan Davis
 * @version 1.2
 * @copyright Ingenesis Limited, 27 April, 2008
 * @package shopp
 * @since 1.2
 * @subpackage OrderAmount
 *
 * $Id$
 **/

class OrderAmount extends ShippingFramework implements ShippingModule {

	function init () { /* Not implemented */ }
	function calcitem ($id,$Item) { /* Not implemented */ }

	function methods () {
		return __('Order Amount Tiers','Shopp');
	}

	function calculate (&$options,$Order) {

		foreach ($this->methods as $slug => $method) {

			$tiers = $this->tablerate($method['table']);
			if ($tiers === false) continue; // Skip methods that don't match at all

			$amount = 0;
			$tiers = array_reverse($tiers);
			foreach ($tiers as $tier) {
				extract($tier);
				$amount = floatvalue($rate);			// Capture the rate amount
				if (floatvalue($Order->Cart->Totals->subtotal) >= floatvalue($threshold)) break;
			}

			$rate = array(
				'slug' => $slug,
				'name' => $method['label'],
				'amount' => $amount,
				'delivery' => $this->delivery($method),
				'items' => false
			);

			$options[$slug] = new ShippingOption($rate);

		}

		return $options;
	}

	function settings () {

		$this->ui->tablerates(0,array(
			'unit' => array(__('Order Amount','Shopp')),
			'table' => $this->settings['table'],
			'threshold_class' => 'money',
			'rate_class' => 'money'
		));

	}

} // end flatrates class

?>