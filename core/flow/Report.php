<?php
/**
 * Report
 *
 * Flow controller for report interfaces
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, June 2012
 * @package shopp
 * @subpackage shopp
 **/

/**
 * Report
 *
 * @package shopp
 * @since 1.3
 * @author Jonathan Davis
 **/
class Report extends AdminController {

	var $screen = 'shopp_page_shopp-reports';
	var $records = array();
	var $count = false;

	private $view = 'dashboard';

	private $defaults = array();	// Default request options
	private $options = array();		// Processed options
	private $Report = false;

	/**
	 * Service constructor
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function __construct () {
		parent::__construct();

		$this->reports = array(
			'sales' => array( 'class' => 'SalesReport', 'name' => __('Sales Report','Shopp'), 'label' => __('Sales','Shopp') ),
			'tax' => array( 'class' => 'TaxReport', 'name' => __('Tax Report','Shopp'), 'label' => __('Taxes','Shopp') ),
			'shipping' => array( 'class' => 'ShippingReport', 'name' => __('Shipping Report','Shopp'), 'label' => __('Shipping','Shopp') ),
			'products' => array( 'class' => 'ProductsReport', 'name' => __('Products Report','Shopp'), 'label' => __('Products','Shopp') ),
		);

		$this->defaults = array(
			'start' => date('n/j/Y',mktime(0,0,0,11,1)),
			'end' => date('n/j/Y',mktime(23,59,59)),
			'range' => '',
			'scale' => 'day',
			'op' => 'day',
			'report' => 'overall',
			'per_page' => 100
		);

		add_action('load-'.$this->screen,array($this,'request'));
		add_action('load-'.$this->screen,array($this,'loader'));

		shopp_enqueue_script('calendar');
		shopp_enqueue_script('flot');
		shopp_enqueue_script('flot-grow');
		shopp_enqueue_script('reports');

		do_action('shopp_order_admin_scripts');
	}

	function request () {

		$today = mktime(23,59,59);

		$this->options = array_merge($this->defaults,$_GET);
		$options =& $this->options;

		if (!empty($options['start'])) {
			$startdate = $options['start'];
			list($sm,$sd,$sy) = explode("/",$startdate);
			$options['starts'] = mktime(0,0,0,$sm,$sd,$sy);
			date('F j Y',$options['starts']);
		}

		if (!empty($options['end'])) {
			$enddate = $options['end'];
			list($em,$ed,$ey) = explode("/",$enddate);
			$options['ends'] = mktime(23,59,59,$em,$ed,$ey);
			if ($options['ends'] > $today) $options['ends'] = $today;
		}

		$daterange = $options['ends'] - $options['starts'];

		$options['op'] = $options['scale'];

		if ($daterange <= 86400) {
			$options['scale'] = 'day';
			$options['op'] = 'hour';
		}

		$options['op'] = strtolower($options['op']);

		$options['daterange'] = $daterange;
		$options['screen'] = $this->screen;

	}

	/**
	 * Handles orders list loading
	 *
	 * @author Jonathan Davis
	 * @since 1.2.1
	 *
	 * @return void
	 **/
	function loader () {
		if ( ! current_user_can('shopp_financials') ) return;
		extract($options);

		$reports = $this->reports;

		// Load the report
		$report = isset($_GET['report']) ? $_GET['report'] : 'sales';
		if ( ! file_exists(SHOPP_ADMIN_PATH."/reports/$report.php") ) wp_die("The requested report does not exist.");

		require(SHOPP_ADMIN_PATH."/reports/$report.php");
		$ReportClass = $reports[$report]['class'];
		$Report = new $ReportClass($this->options);
		$Report->query();
		$Report->setup();
		$this->Report = $Report;

		$num_pages = ceil($Report->total / $per_page );
		$ListTable = ShoppUI::table_set_pagination ($this->screen, $Report->total, $num_pages, $per_page );

	}

	/**
	 * admin
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function admin () {
		if ( ! current_user_can('shopp_financials') )
			wp_die(__('You do not have sufficient permissions to access this page.','Shopp'));

		global $Shopp;

		$defaults = array(
			'page' => false,
			'update' => false,
			'newstatus' => false,
			'paged' => 1,
			'per_page' => 20,
			'status' => false,
			's' => '',
			'range' => '',
			'startdate' => '',
			'enddate' => '',
			'report' => 'sales',
			'scale' => 'day'
		);

		$args = array_merge($defaults,$_GET);
		extract($args, EXTR_SKIP);

		$s = stripslashes($s);

		$statusLabels = shopp_setting('order_status');
		if (empty($statusLabels)) $statusLabels = array('');
		$txnstatus_labels = Lookup::txnstatus_labels();

		$Purchase = new Purchase();

		$Orders = $this->orders;
		$ordercount = $this->ordercount;
		$num_pages = ceil($ordercount->total / $per_page);

		$ListTable = ShoppUI::table_set_pagination ($this->screen, $ordercount->total, $num_pages, $per_page );

		$ranges = array(
			'all' => __('Show All Orders','Shopp'),
			'today' => __('Today','Shopp'),
			'week' => __('This Week','Shopp'),
			'month' => __('This Month','Shopp'),
			'quarter' => __('This Quarter','Shopp'),
			'year' => __('This Year','Shopp'),
			'yesterday' => __('Yesterday','Shopp'),
			'lastweek' => __('Last Week','Shopp'),
			'last30' => __('Last 30 Days','Shopp'),
			'last90' => __('Last 3 Months','Shopp'),
			'lastmonth' => __('Last Month','Shopp'),
			'lastquarter' => __('Last Quarter','Shopp'),
			'lastyear' => __('Last Year','Shopp'),
			'lastexport' => __('Last Export','Shopp'),
			'custom' => __('Custom Dates','Shopp')
			);

		$exports = array(
			'tab' => __('Tab-separated.txt','Shopp'),
			'csv' => __('Comma-separated.csv','Shopp'),
			'xls' => __('Microsoft&reg; Excel.xls','Shopp'),
			'iif' => __('Intuit&reg; QuickBooks.iif','Shopp')
			);

		$formatPref = shopp_setting('purchaselog_format');
		if (!$formatPref) $formatPref = 'tab';

		$columns = array_merge(Purchase::exportcolumns(),Purchased::exportcolumns());
		$selected = shopp_setting('purchaselog_columns');
		if (empty($selected)) $selected = array_keys($columns);

		$Report = $this->Report;
		$report_title = isset($this->reports[ $report ])? $this->reports[ $report ]['name'] : __('Report','Shopp');
		include(SHOPP_ADMIN_PATH."/reports/reports.php");
	}

} // end class Report

interface ShoppReport {
	public function query();
	public function setup();
	public function table();
}

class ShoppReportFramework {
	var $screen = false;		// The current WP screen
	var $Chart = false;			// The report chart (if any)

	var $options = array();		// Options for the report
	var $data = array();		// The raw data from the query
	var $report = array();		// The processed report data
	var $daterange = false;

	function __construct ($request = array()) {
		$this->options = $request;

		$this->screen = $this->options['screen'];

		add_action('shopp_report_filter_controls',array($this,'filters'));
		add_action("manage_{$this->screen}_columns",array($this,'columns'));
		add_action("manage_{$this->screen}_sortable_columns",array($this,'sortcolumns'));
	}

	function timecolumn ($column) {
		$tzoffset = date('Z')/3600;
		$column = "CONVERT_TZ($column,'+00:00','".($tzoffset>=0?'+':'-')."$tzoffset:00')";
		switch (strtolower($this->options['op'])) {
			case 'hour':	$_ = "HOUR($column)"; break;
			case 'week':	$_ = "WEEK($column,3),' ',YEAR($column)"; break;
			case 'month':	$_ = "MONTH($column),' ',YEAR($column)"; break;
			case 'year':	$_ = "YEAR($column)"; break;
			default:		$_ = "DAY($column),' ',MONTH($column),' ',YEAR($column)";
		}
		return $_;
	}

	function weekrange ($ts) {
		$weekday = date('w',$ts);
		$startweek = $ts-($weekday*86400);
		$endweek = $startweek+(6*86400);

		return sprintf('%s - %s',date('F j',$startweek),date('F j Y',$endweek));
	}

	function range ($starts,$ends,$scale='day') {
		$oneday = 86400;
		$years = date('Y',$ends)-date('Y',$starts);
		switch (strtolower($scale)) {
			case 'week':
				// Find the timestamp for the first day of the start date's week
				$startweekday = date('w',$starts);
				$startweekdate = $starts-($startweekday*86400);

				// Find the timestamp for the last day of the end date's' week
				$endweekday = date('w',$ends);
				$endweekdate = $ends+((6-$endweekday)*86400);

				$starts_week = (int)date('W',$startweekdate);
				$ends_week =  (int)date('W',$endweekdate);
				if ($starts_week > $ends_week) $starts_week -= 52;
				return ($years*52)+$ends_week - $starts_week;
			case 'month':
				$starts_month = date('n',$starts);
				$ends_month = date('n',$ends);
				if ($starts_month > $ends_month) $starts_month -= 12;
				return (12*$years)+$ends_month-$starts_month+1;
			case 'year': return $years+1;
			case 'hour': return 24; break;
			default:
			case 'day': return ceil(($ends-$starts)/$oneday);
		}
	}

	function columns () { ShoppUI::register_column_headers($this->screen,array()); }

	function sortcolumns () { return array(); }

	function table () {
		if ( $this->Chart ) $this->Chart->render();
	?>
			<table class="widefat" cellspacing="0">
				<thead>
				<tr><?php ShoppUI::print_column_headers($this->screen); ?></tr>
				</thead>
				<tfoot>
				<tr><?php ShoppUI::print_column_headers($this->screen,false); ?></tr>
				</tfoot>
			<?php if ( false !== $this->report && count($this->report) > 0 ): ?>
				<tbody id="report" class="list stats">
				<?php
				$columns = get_column_headers($this->screen);
				$hidden = get_hidden_columns($this->screen);

				$even = false;
				$count = 0;
				foreach ($this->report as $i => $data):
					if ($count++ > $this->options['per_page']) break;
				?>
					<tr<?php if (!$even) echo " class='alternate'"; $even = !$even; ?>>
				<?php

					foreach ($columns as $column => $column_title) {
						$classes = array($column,"column-$column");
						if ( in_array($column,$hidden) ) $classes[] = 'hidden';

						if ( method_exists($this,$column)): ?>
							<td class="<?php echo esc_attr(join(' ',$classes)); ?>"><?php call_user_func(array($this,$column),$data,$column,$column_title); ?></td>
						<?php else: ?>
							<td class="<?php echo esc_attr(join(' ',$classes)); ?>">
							<?php do_action( 'shopp_manage_report_custom_column', $column, $column_title, $data );	?>
							</td>
						<?php endif;
				} /* $columns */
				?>
				</tr>
				<?php endforeach; /* $Products */ ?>
				</tbody>
			<?php else: ?>
				<tbody><tr><td colspan="<?php echo count(get_column_headers($this->screen)); ?>"><?php _e('No report data available.','Shopp'); ?></td></tr></tbody>
			<?php endif; ?>
			</table>
	<?php
	}

	function filters () {
		self::rangefilter();
		self::scalefilter();
		self::filterbutton();
	}

	protected static function rangefilter () { ?>
		<select name="range" id="range">
			<?php
				$start = $_GET['start'];
				$end = $_GET['end'];
				$range = isset($_GET['range']) ? $_GET['range'] : 'all';
				$ranges = array(
					'today' => __('Today','HelpDesk'),
					'week' => __('This Week','HelpDesk'),
					'month' => __('This Month','HelpDesk'),
					'year' => __('This Year','HelpDesk'),
					'quarter' => __('This Quarter','HelpDesk'),
					'yesterday' => __('Yesterday','HelpDesk'),
					'lastweek' => __('Last Week','HelpDesk'),
					'last30' => __('Last 30 Days','HelpDesk'),
					'last90' => __('Last 3 Months','HelpDesk'),
					'lastmonth' => __('Last Month','HelpDesk'),
					'lastquarter' => __('Last Quarter','HelpDesk'),
					'lastyear' => __('Last Year','HelpDesk'),
					'custom' => __('Custom Dates','HelpDesk')
				);
				echo menuoptions($ranges,$range,true);
			?>
		</select>
		<div id="dates" class="hide-if-js">
			<div id="start-position" class="calendar-wrap"><input type="text" id="start" name="start" value="<?php echo esc_attr($start); ?>" size="10" class="search-input selectall" /></div>
			<small>to</small>
			<div id="end-position" class="calendar-wrap"><input type="text" id="end" name="end" value="<?php echo esc_attr($end); ?>" size="10" class="search-input selectall" /></div>
		</div>
<?php
	}

	protected static function scalefilter () { ?>

		<select name="scale" id="scale">
		<?php
		$scale = isset($_GET['scale']) ? $_GET['scale'] : 'day';
		$scales = array(
			'hour' => __('By Hour','Shopp'),
			'day' => __('By Day','Shopp'),
			'week' => __('By Week','Shopp'),
			'month' => __('By Month','Shopp')
		);

		echo menuoptions($scales,$scale,true);
		?>
		</select>

<?php
	}

	protected static function filterbutton () {
		?><button type="submit" id="filter-button" name="filter" value="order" class="button-secondary"><?php _e('Filter','Shopp'); ?></button><?php
	}

	protected function chart ($report,$x,$y) {
		$this->Chart->data($report,$x,$y);
	}

} // End class ShoppReportFramework

class ShoppReportChart {
	var $data = array();
	var $chart = array();

	var $options = array(
		'series' => array(
			'lines' => array('show' => true,'fill'=>true,'lineWidth'=>3),
			'points' => array('show' => true),
			'shadowSize' => 0
		),
		'xaxis' => array(
			'color' => '#545454',
			'tickColor' => '#fff',
			'position' => 'top',
			'mode' => 'time',
			'timeformat' => '%m/%d/%y',
			'tickSize' => array(1,'day'),
			'twelveHourClock' => true
		),
		'yaxis' => array(
			'position' => 'right',
			'autoscaleMargin' => 0.02,
		),
		'legend' => array(
			'show' => false
		),
		'grid' => array(
			'show' => true,
			'hoverable' => true,
			'borderWidth' => 0,
			'borderColor' => '#000',
			'minBorderMargin' => 10,
			'labelMargin' => 10,
			'markingsColor' => '#f7f7f7'
         ),
		// Solarized Color Palette
		'colors' => array('#618C03','#1C63A8','#1F756B','#896204','#cb4b16','#A90007','#A9195F','#4B4B9A'),
	);

	function settings ($options) {
		foreach ($options as $setting => $settings)
			$this->options[$setting] = wp_parse_args($settings,$this->options[$setting]);
	}

	function timeaxis ($axis,$range,$scale='day') {
		if ( ! isset($this->options[ $axis ])) return;

		$options = array();
		switch (strtolower($scale)) {
			case 'hour':
				$options['timeformat'] = '%h%p';
				$options['tickSize'] = array(2,'hour');
				break;
			case 'day':
				$tickscale = ceil($range / 10);
				$options['tickSize'] = array($tickscale,'day');
				$options['timeformat'] = '%b %d';
				break;
			case 'week':
				$tickscale = ceil($range/10)*7;
				$options['tickSize'] = array($tickscale,'day');
				$options['minTickSize'] = array(7,'day');
				$options['timeformat'] = '%b %d';
				break;
			case 'month':
				$tickscale = ceil($range / 10);
				$options['tickSize'] = array($tickscale,'month');
				$options['timeformat'] = '%b %y';
				break;
			case 'year':
				$options['tickSize'] = array(12,'month');
				$options['minTickSize'] = array(12,'month');
				$options['timeformat'] = '%y';
				break;
		}

		$this->options[ $axis ] = wp_parse_args($options,$this->options[ $axis ]);
	}

	function series ($id,$label) {
		$this->data[$id] = array(
			'label' => $label,
			'data' => array(),
			'grow' => array(
				'active' => true,
				'stepMode' => 'linear',
				'stepDelay' => false,
				'steps' => 25,
				'stepDirection' => 'up'
			)
		);
	}

	function data ($series,$x,$y) {
		$tzoffset = date('Z');

		if ( isset($this->data[$series]) )
			$this->data[$series]['data'][] = array(($x+$tzoffset)*1000,$y);


		$this->datapoints = max( $this->datapoints, count($this->data[$series]['data']) );
	}

	function render () {
		if (count($this->data[0]['data']) > 75) $this->options['series']['points'] = false; ?>
		<script type="text/javascript">
		var d = <?php echo json_encode($this->data); ?>,
			co = <?php echo json_encode($this->options); ?>;
		</script>

		<div id="chart"></div>
		<div id="chart-legend"></div>
<?php
	}

} // End class ShoppReportChart