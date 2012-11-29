<?php

class ProductsReport extends ShoppReportFramework implements ShoppReport {
	var $chart = array(
		0 => array('label' => 'Orders','color'=> 'rgb(4,138,191)','data' => array()),
	);

	function query () {
		global $bbdb;
		extract($this->options, EXTR_SKIP);
		$data =& $this->data;

		$where = array();

		$where[] = "$starts < UNIX_TIMESTAMP(o.created)";
		$where[] = "$ends > UNIX_TIMESTAMP(o.created)";

		$where = join(" AND ",$where);
		// $id = $this->timecolumn('o.created');
		$id = "o.product,' ',o.price";
		$orders_table = DatabaseObject::tablename('purchase');
		$purchased_table = DatabaseObject::tablename('purchased');
		$product_table = WPDatabaseObject::tablename(Product::$table);
		$summary_table = DatabaseObject::tablename(ProductSummary::$table);
		$price_table = DatabaseObject::tablename('price');
		$query = "SELECT CONCAT($id) AS id,
							UNIX_TIMESTAMP(o.created) as ts,
							CONCAT(p.post_title,' ',pr.label) AS product,
							COUNT(DISTINCT o.id) AS orders,
							s.sold AS sold,
							s.grossed AS grossed
					FROM $purchased_table AS o
					LEFT OUTER JOIN $summary_table AS s ON s.product=o.product
					LEFT OUTER JOIN $product_table AS p ON p.ID=o.product
					LEFT OUTER JOIN $price_table AS pr ON pr.product=o.product
					WHERE $where
					GROUP BY CONCAT($id) ORDER BY orders DESC";

		$data = DB::query($query,'array','index','id');
	}

	function parse () {
		extract($this->options, EXTR_SKIP);

		$this->screen = $screen;

		// Post processing for stats to fill in date ranges
		$data =& $this->data;
		$stats =& $this->report;
		$chart =& $this->chart;
		$xaxis =& $this->chartop['xaxis'];

		$month = date('n',$starts); $day = date('j',$starts); $year = date('Y',$starts);

		$range = $this->range($starts,$ends,$op);
		$this->total = count($data);
		$xaxis = $this->chartaxis($xaxis,$range,$op);
		$stats = $data;

		foreach ($stats as $id => &$s) {
			$s->product = str_replace(__('Price & Delivery','Shopp'),'',$s->product);
		}

	}

	function init () {
		register_column_headers($this->options['screen'], array(
			'product'=>__('Product','Shopp'),
			'numorders'=>__('# of Orders','Shopp'),
			'sold'=>__('Items Ordered','Shopp'),
			'grossed'=>__('Grossed','Shopp')
		) );
	}

	function period ($data,$column,$coltitle) {
		switch (strtolower($this->options['op'])) {
			case 'hour': echo date('ga',$data->ts); break;
			case 'day': echo date('l, F j, Y',$data->ts); break;
			case 'week': echo $this->weekrange($data->ts); break;
			case 'month': echo date('F Y',$data->ts); break;
			case 'year': echo date('Y',$data->ts); break;
			default: echo $data->ts; break;
		}
	}

	function product ($data) { echo $data->product; }

	function numorders ($data) { echo $data->orders; }

	function sold ($data) { echo $data->sold; }

	function grossed ($data) { echo money($data->grossed); }

	function tax ($data) { echo money($data->tax); }

	function shipping ($data) { echo money($data->shipping); }

	function discounts ($data) { echo money($data->discounts); }

	function total ($data) { echo money($data->total); }

	function orderavg ($data) { echo money($data->avgorder); }

	function itemavg ($data) { echo money($data->avgitem); }

}