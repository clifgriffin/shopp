<?php

class ProductsReport extends ShoppReportFramework implements ShoppReport {

	function query () {
		global $bbdb;
		extract($this->options, EXTR_SKIP);
		$data =& $this->data;


		$where = array();

		$tzoffset = date('Z')/3600;
		$where[] = "$starts < UNIX_TIMESTAMP(o.created)+$tzoffset";
		$where[] = "$ends > UNIX_TIMESTAMP(o.created)+$tzoffset";

		$where = join(" AND ",$where);

		$orderd = 'desc';
		if ( in_array( $order, array('asc','desc') ) ) $orderd = strtolower($order);

		$ordercols = 'orders';
		switch ($orderby) {
			case 'orders': $ordercols = 'orders'; break;
			case 'sold': $ordercols = 'sold'; break;
			case 'grossed': $ordercols = 'grossed'; break;
		}
		$ordercols = "$ordercols $orderd";

		$id = "o.product,' ',o.price";
		$purchased_table = DatabaseObject::tablename('purchased');
		$product_table = WPDatabaseObject::tablename(Product::$table);
		$summary_table = DatabaseObject::tablename(ProductSummary::$table);
		$price_table = DatabaseObject::tablename('price');
		$query = "SELECT CONCAT($id) AS id,
							UNIX_TIMESTAMP(o.created) as ts,
							CONCAT(p.post_title,' ',pr.label) AS product,
							COUNT(DISTINCT o.id) AS sold,
							COUNT(DISTINCT o.purchase) AS orders,
							SUM(o.total) AS grossed
					FROM $purchased_table AS o
					JOIN $summary_table AS s ON s.product=o.product
					JOIN $product_table AS p ON p.ID=o.product
					JOIN $price_table AS pr ON pr.id=o.price
					WHERE $where
					GROUP BY CONCAT($id) ORDER BY $ordercols";

		$data = DB::query($query,'array','index','id');
	}

	function setup () {
		extract($this->options, EXTR_SKIP);

		$this->Chart = new ShoppReportChart();
		$Chart = $this->Chart;
		$Chart->settings(array(
			'series' => array('bars' => array('show' => true,'lineWidth'=>0,'fill'=>true,'barWidth' => 0.75),'points'=>array('show'=>false)),
			'xaxis' => array('show' => false),
			'yaxis' => array('tickFormatter' => 'asMoney')
		));

		// Post processing for stats to fill in date ranges
		$data =& $this->data;
		$stats =& $this->report;

		$month = date('n',$starts); $day = date('j',$starts); $year = date('Y',$starts);

		$range = $this->range($starts,$ends,$op);
		$this->total = count($data);
		$stats = $data;

		$i = 0;
		foreach ($stats as $id => &$s) {
			$s->product = str_replace(__('Price & Delivery','Shopp'),'',$s->product);
			if ($i > 20) continue;
			$Chart->options['colors'][$i] = '#1C63A8'; // Set all bars to blue
			$this->chart($i++,$s->product,(int)$s->grossed);
		}

	}

	function chart ($series,$x,$y) {
		$Chart = $this->Chart;
		$Chart->data[$series]['label'] = $x;
		$Chart->data[$series]['data'][] = array($series,$y);
	}


	function filters () {
		ShoppReportFramework::rangefilter();
		ShoppReportFramework::filterbutton();
	}

	function columns () {
		ShoppUI::register_column_headers($this->screen, array(
			'product'=>__('Product','Shopp'),
			'numorders'=>__('# of Orders','Shopp'),
			'sold'=>__('Items Ordered','Shopp'),
			'grossed'=>__('Grossed','Shopp')
		) );
	}

	function sortcolumns () {
		return array(
			'numorders'=>'orders',
			'sold'=>'sold',
			'grossed'=>'grossed'
		);
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