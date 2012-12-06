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
							UNIX_TIMESTAMP(o.created) as period,
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
		return array(
			'product'=>__('Product','Shopp'),
			'orders'=>__('Orders','Shopp'),
			'sold'=>__('Items','Shopp'),
			'grossed'=>__('Grossed','Shopp')
		);
	}

	function sortcolumns () {
		return array(
			'orders'=>'orders',
			'sold'=>'sold',
			'grossed'=>'grossed'
		);
	}

	static function product ($data) { return $data->product; }

	static function orders ($data) { return intval($data->orders); }

	static function sold ($data) { return intval($data->sold); }

	static function grossed ($data) { return money($data->grossed); }

}