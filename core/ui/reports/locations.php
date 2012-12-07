<?php

class LocationsReport extends ShoppReportFramework implements ShoppReport {

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

		$id = "o.country";
		$orders_table = DatabaseObject::tablename('purchase');
		$purchased_table = DatabaseObject::tablename('purchased');
		$product_table = WPDatabaseObject::tablename(Product::$table);
		$summary_table = DatabaseObject::tablename(ProductSummary::$table);
		$price_table = DatabaseObject::tablename('price');
		$query = "SELECT CONCAT($id) AS id,
							o.country AS country,
							COUNT(DISTINCT o.id) AS orders,
							COUNT(DISTINCT p.id) AS items,
							SUM(o.total) AS grossed
					FROM $orders_table AS o
					JOIN $purchased_table AS p ON p.purchase=o.id
					WHERE $where
					GROUP BY CONCAT($id) ORDER BY $ordercols";

		$data = DB::query($query,'array','index','id');
	}

	function setup () {

		shopp_enqueue_script('jvectormap');
		shopp_enqueue_script('worldmap');

		extract($this->options, EXTR_SKIP);

		// Post processing for stats to fill in date ranges
		$this->report = $this->data;
		$this->data = array();

		$month = date('n',$starts); $day = date('j',$starts); $year = date('Y',$starts);

		foreach ($this->report as $i => $data)
			$this->data[$data->country] = $data->grossed;

		$this->total = count($this->report);

	}

	function table () { ?>
		<div id="map"></div>
		<script type="text/javascript">
		var d = <?php echo json_encode($this->data); ?>;
		</script>
<?php
		parent::table();
	}

	function filters () {
		ShoppReportFramework::rangefilter();
		ShoppReportFramework::filterbutton();
	}

	function columns () {
		return array(
			'country'=>__('Country','Shopp'),
			'orders'=>__('Orders','Shopp'),
			'items'=>__('Items','Shopp'),
			'grossed'=>__('Grossed','Shopp')
		);
	}

	function sortcolumns () {
		return array(
			'orders'=>__('Orders','Shopp'),
			'items'=>__('Items','Shopp'),
			'grossed'=>__('Grossed','Shopp')
		);
	}

	static function country ($data) { $countries = Lookup::countries(); return $countries[$data->country]['name']; }

	static function orders ($data) { return intval($data->orders); }

	static function items ($data) { return intval($data->items); }

	static function grossed ($data) { return money($data->grossed); }

}