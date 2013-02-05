<?php

class SalesReport extends ShoppReportFramework implements ShoppReport {

	var $periods = true;

	function setup () {
		$this->chartseries( __('Orders','Shopp'), array('column' => 'orders') );
	}

	function query () {
		extract($this->options, EXTR_SKIP);

		$where = array();
		$where[] = "$starts < UNIX_TIMESTAMP(o.created)";
		$where[] = "$ends > UNIX_TIMESTAMP(o.created)";
		$where = join(" AND ",$where);

		$id = $this->timecolumn('o.created');
		$orders_table = DatabaseObject::tablename('purchase');
		$purchased_table = DatabaseObject::tablename('purchased');
		return "SELECT CONCAT($id) AS id,
							UNIX_TIMESTAMP(o.created) as period,
							COUNT(DISTINCT p.id) AS items,
							COUNT(DISTINCT o.id) AS orders,
							SUM(o.subtotal)*(COUNT(DISTINCT o.id)/COUNT(*)) as subtotal,
							SUM(o.tax)*(COUNT(DISTINCT o.id)/COUNT(*)) as tax,
							SUM(o.freight)*(COUNT(DISTINCT o.id)/COUNT(*)) as shipping,
							SUM(o.discount)*(COUNT(DISTINCT o.id)/COUNT(*)) as discounts,
							SUM(o.total)*(COUNT(DISTINCT o.id)/COUNT(*)) as total,
							AVG(o.total)*(COUNT(DISTINCT o.id)/COUNT(*)) AS orderavg,
							AVG(p.unitprice)*(COUNT(DISTINCT o.id)/COUNT(*)) AS itemavg
					FROM $orders_table AS o
					LEFT OUTER JOIN $purchased_table AS p ON p.purchase=o.id
					WHERE $where
					GROUP BY CONCAT($id)";

	}

	function columns () {
		return array(
			'period'=>__('Period','Shopp'),
			'orders'=>__('Orders','Shopp'),
			'items'=>__('Items','Shopp'),
			'subtotal'=>__('Subtotal','Shopp'),
			'tax'=>__('Tax','Shopp'),
			'shipping'=>__('Shipping','Shopp'),
			'discounts'=>__('Discounts','Shopp'),
			'total'=>__('Total','Shopp'),
			'orderavg'=>__('Average Order','Shopp'),
			'itemavg'=>__('Average Items','Shopp')
		);
	}

	static function orders ($data) { return intval($data->orders); }

	static function items ($data) { return intval($data->items); }

	static function subtotal ($data) { return money($data->subtotal); }

	static function tax ($data) { return money($data->tax); }

	static function shipping ($data) { return money($data->shipping); }

	static function discounts ($data) { return money($data->discounts); }

	static function total ($data) { return money($data->total); }

	static function orderavg ($data) { return money($data->orderavg); }

	static function itemavg ($data) { return money($data->itemavg); }

}