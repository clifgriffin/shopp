<?php
/**
 * Order Weight Tiers
 *
 * Provides shipping calculations based on order amount tiers
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, 27 April, 2008
 * @package shopp
 * @since 1.1 dev
 * @subpackage OrderWeight
 *
 * $Id$
 **/

class OrderWeight extends ShippingFramework implements ShippingModule {

	var $weight = 0;

	function init () {
		$this->weight = 0;
	}

	function methods () {
		return __('Order Weight Tiers','Shopp');
	}

	function calcitem ($id,$Item) {
		$this->weight += $Item->weight*$Item->quantity;
	}

	function calculate ($options,$Order) {

		foreach ($this->methods as $slug => $method) {

			$tiers = $this->tablerate($method['table']);
			if ($tiers === false) continue; // Skip methods that don't match at all

			$amount = 0;
			$tiers = array_reverse($tiers);
			foreach ($tiers as $tier) {
				extract($tier);
				$amount = floatvalue($rate);			// Capture the rate amount
				if ($this->weight >= $threshold) break;
			}

			$rate = array(
				'slug' => $slug,
				'name' => $method['label'],
				'amount' => $amount,
				'delivery' => false,
				'items' => false
			);

			$options[$slug] = new ShippingOption($rate);

		}

		return $options;
	}

	function settings () {
		$Settings = ShoppSettings();

		$this->ui->tablerates(0,array(
			'unit' => array(__('Weight','Shopp'),$Settings->get('weight_unit')),
			'table' => $this->settings['table']
		));

	}

} // end flatrates class

?>