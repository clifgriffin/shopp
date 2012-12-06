<?php

class SalesReport extends ShoppReportFramework implements ShoppReport {

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

	function query () {
		extract($this->options, EXTR_SKIP);
		$data =& $this->data;

		$where = array();

		$where[] = "$starts < UNIX_TIMESTAMP(o.created)";
		$where[] = "$ends > UNIX_TIMESTAMP(o.created)";

		$where = join(" AND ",$where);
		$id = $this->timecolumn('o.created');
		$orders_table = DatabaseObject::tablename('purchase');
		$purchased_table = DatabaseObject::tablename('purchased');
		$query = "SELECT CONCAT($id) AS id,
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
		$data = DB::query($query,'array','index','id');
	}

	function setup () {
		extract($this->options, EXTR_SKIP);

		$this->Chart = new ShoppReportChart();
		$Chart = $this->Chart;
		$Chart->series(0,__('Orders','Shopp'));

		// Post processing for stats to fill in date ranges
		$data =& $this->data;
		$stats =& $this->report;

		$month = date('n',$starts); $day = date('j',$starts); $year = date('Y',$starts);

		$range = $this->range($starts,$ends,$scale);
		$Chart->timeaxis('xaxis',$range,$scale);

		$this->total = $range;
		$stats = array_fill(0,$range,true);

		foreach ($stats as $i => &$s) {
			$s = new StdClass();

			$index = $i;
			switch (strtolower($scale)) {
				case 'hour': $ts = mktime($i,0,0,$month,$day,$year); break;
				case 'week':
					$ts = mktime(0,0,0,$month,$day+($i*7),$year);
					$index = sprintf('%s %s',(int)date('W',$ts),date('Y',$ts));
					break;
				case 'month':
					$ts = mktime(0,0,0,$month+$i,1,$year);
					$index = sprintf('%s %s',date('n',$ts),date('Y',$ts));
					break;
				case 'year':
					$ts = mktime(0,0,0,1,1,$year+$i);
					$index = sprintf('%s',date('Y',$ts));
					break;
				default:
					$ts = mktime(0,0,0,$month,$day+$i,$year);
					$index = sprintf('%s %s %s',date('j',$ts),date('n',$ts),date('Y',$ts));
					break;
			}

			if (isset($data[$index])) {
				$props = get_object_vars($data[$index]);
				foreach ( $props as $property => $value)
					$s->$property = $value;
			}

			$s->period = $ts;

			$Chart->data(0,$s->period,(int)$s->orders);
		}

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