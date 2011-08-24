<?php
/**
 * Item Rates
 *
 * Provides flat rates per item
 *
 * @author Jonathan Davis
 * @version 1.2
 * @copyright Ingenesis Limited, June 14, 2011
 * @package shopp
 * @since 1.2 dev
 * @subpackage ItemRates
 *
 **/

class ItemRates extends ShippingFramework implements ShippingModule {

	function methods () {
		return __('Flat Item Rates','Shopp');
	}

	function init () {
		$rate['items'] = array();
	}

	function calcitem ($id,$Item) {
		foreach ($this->methods as $slug => &$method) {
			$amount = $this->tablerate($method['table']);
			if ($amount === false) continue; // Skip methods that don't match at all
			$method['items'] = $Item->quantity * $amount;
		}
	}

	function calculate ($options,$Order) {

		foreach ($this->methods as $slug => $method) {

			$amount = $this->tablerate($method['table']);
			if ($amount === false) continue; // Skip methods that don't match at all
			$rate = array(
				'slug' => $slug,
				'name' => $method['label'],
				'amount' => $amount,
				'delivery' => false,
				'items' => $method['items']
			);

			$options[$slug] = new ShippingOption($rate);

		}

		return $options;
	}

	function settings () {

		$this->ui->flatrates(0,array(
			'table' => $this->settings['table']
		));

	}

} // END class OrderRates
?>