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

		add_action('load-'.$this->screen,array($this,'load'));

		shopp_enqueue_script('calendar');
		shopp_enqueue_script('reports');

		do_action('shopp_order_admin_scripts');
	}

	static function reports () {
		return apply_filters('shopp_reports',array(
			'sales' => array( 'class' => 'SalesReport', 'name' => __('Sales Report','Shopp'), 'label' => __('Sales','Shopp') ),
			'tax' => array( 'class' => 'TaxReport', 'name' => __('Tax Report','Shopp'), 'label' => __('Taxes','Shopp') ),
			'shipping' => array( 'class' => 'ShippingReport', 'name' => __('Shipping Report','Shopp'), 'label' => __('Shipping','Shopp') ),
			'products' => array( 'class' => 'ProductsReport', 'name' => __('Products Report','Shopp'), 'label' => __('Products','Shopp') ),
			'locations' => array( 'class' => 'LocationsReport', 'name' => __('Locations Report','Shopp'), 'label' => __('Locations','Shopp') ),
		));
	}

	function request () {
		$defaults = array(
			'start' => date('n/j/Y',mktime(0,0,0,11,1)),
			'end' => date('n/j/Y',mktime(23,59,59)),
			'range' => '',
			'scale' => 'day',
			'op' => 'day',
			'report' => 'sales',
			'paged' => 1,
			'per_page' => 100,
			'num_pages' => 1
		);

		$today = mktime(23,59,59);

		$options = wp_parse_args($_GET,$defaults);

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

		if ( $daterange <= 86400 ) $_GET['scale'] = $options['scale'] = 'hour';

		$options['daterange'] = $daterange;
		$options['screen'] = $this->screen;

		return $options;
	}

	/**
	 * Handles orders list loading
	 *
	 * @author Jonathan Davis
	 * @since 1.2.1
	 *
	 * @return void
	 **/
	function load () {
		if ( ! current_user_can('shopp_financials') ) return;
		$this->options = self::request();
		extract($this->options,EXTR_SKIP);

		$reports = self::reports();

		// Load the report
		$report = isset($_GET['report']) ? $_GET['report'] : 'sales';
		if ( ! file_exists(SHOPP_ADMIN_PATH."/reports/$report.php") ) wp_die("The requested report does not exist.");
		require(SHOPP_ADMIN_PATH."/reports/$report.php");

		$ReportClass = $reports[$report]['class'];
		$Report = new $ReportClass($this->options);
		$this->Report = $Report;
		$Report->query();
		$Report->setup();

		// Reset pagination
		$_GET['paged'] = $this->options['paged'] = min($paged,$Report->pagination());

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

		extract($this->options, EXTR_SKIP);

		$Report = $this->Report;
		$ListTable = ShoppUI::table_set_pagination ($this->screen, $Report->total, $Report->pages, $per_page );

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
			'custom' => __('Custom Dates','Shopp')
			);

		$exports = array(
			'tab' => __('Tab-separated.txt','Shopp'),
			'csv' => __('Comma-separated.csv','Shopp'),
			'xls' => __('Microsoft&reg; Excel.xls','Shopp'),
			);

		$format = shopp_setting('report_format');
		if ( ! $format ) $format = 'tab';

		$columns = array_merge(Purchase::exportcolumns(),Purchased::exportcolumns());
		$selected = shopp_setting('purchaselog_columns');
		if (empty($selected)) $selected = array_keys($columns);

		$reports = self::reports();

		$report_title = isset($reports[ $report ])? $reports[ $report ]['name'] : __('Report','Shopp');
		include(SHOPP_ADMIN_PATH."/reports/reports.php");
	}

} // end class Report

interface ShoppReport {
	public function query();
	public function setup();
	public function table();
}

abstract class ShoppReportFramework {
	var $screen = false;		// The current WP screen
	var $Chart = false;			// The report chart (if any)

	var $options = array();		// Options for the report
	var $data = array();		// The raw data from the query
	var $report = array();		// The processed report data
	var $total = 0;				// Total number of records in the report
	var $pages = 1;				// Number of pages for the report
	var $daterange = false;

	function __construct ($request = array()) {
		$this->options = $request;
		$this->screen = $this->options['screen'];

		add_action('shopp_report_filter_controls',array($this,'filters'));
		add_action("manage_{$this->screen}_columns",array($this,'screencolumns'));
		add_action("manage_{$this->screen}_sortable_columns",array($this,'sortcolumns'));
	}

	function pagination () {
		extract($this->options,EXTR_SKIP);
		$this->pages = ceil($this->total / $per_page);
		$_GET['paged'] = $this->options['paged'] = min($paged,$this->pages);
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

	function weekrange ( $ts, $formats=array('F j','F j Y') ) {
		$weekday = date('w',$ts);
		$startweek = $ts-($weekday*86400);
		$endweek = $startweek+(6*86400);

		return sprintf('%s - %s',date($formats[0],$startweek),date($formats[1],$endweek));
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

	function columns () { return array(); }

	function screencolumns () { ShoppUI::register_column_headers($this->screen,$this->columns()); }

	function sortcolumns () { return array(); }

	function value ($value) {
		echo $value;
	}

	static function period ($data,$column,$title,$options) {
		switch (strtolower($options['scale'])) {
			case 'hour': echo date('ga',$data->period); break;
			case 'day': echo date('l, F j, Y',$data->period); break;
			case 'week': echo $this->weekrange($data->period); break;
			case 'month': echo date('F Y',$data->period); break;
			case 'year': echo date('Y',$data->period); break;
			default: echo $data->period; break;
		}
	}

	static function export_period ($data,$column,$title,$options) {
		$date_format = get_option('date_format');
		$time_format = get_option('time_format');
		$datetime = "$date_format $time_format";

		switch (strtolower($options['scale'])) {
			case 'day': echo date($date_format,$data->period); break;
			case 'week': echo $this->weekrange($data->period,array($format,$format)); break;
			default: echo date($datetime,$data->period); break;
		}
	}

	function table () {
		extract($this->options,EXTR_SKIP);
		if ( $this->Chart ) $this->Chart->render();

		// Get only the records for this page
		$beginning = (int)($paged-1)*$per_page;
		$report = array_slice($this->report,$beginning,$beginning+$per_page,true);
		unset($this->report); // Free memory

	?>
			<table class="widefat" cellspacing="0">
				<thead>
				<tr><?php ShoppUI::print_column_headers($this->screen); ?></tr>
				</thead>
				<tfoot>
				<tr><?php ShoppUI::print_column_headers($this->screen,false); ?></tr>
				</tfoot>
			<?php if ( false !== $report && count($report) > 0 ): ?>
				<tbody id="report" class="list stats">
				<?php
				$columns = get_column_headers($this->screen);
				$hidden = get_hidden_columns($this->screen);

				$even = false;
				$records = 0;
				while (list($id,$data) = each($report)):
					if ($records++ > $per_page) break;
				?>
					<tr<?php if (!$even) echo " class='alternate'"; $even = !$even; ?>>
				<?php

					foreach ($columns as $column => $column_title) {
						$classes = array($column,"column-$column");
						if ( in_array($column,$hidden) ) $classes[] = 'hidden';

						if ( method_exists(get_class($this),$column)): ?>
							<td class="<?php echo esc_attr(join(' ',$classes)); ?>"><?php echo call_user_func(array(get_class($this),$column),$data,$column,$column_title,$this->options); ?></td>
						<?php else: ?>
							<td class="<?php echo esc_attr(join(' ',$classes)); ?>">
							<?php do_action( 'shopp_manage_report_custom_column', $column, $column_title, $data );	?>
							</td>
						<?php endif;
				} /* $columns */
				?>
				</tr>
				<?php endwhile; /* records */ ?>
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

	function __construct () {
		shopp_enqueue_script('flot');
		shopp_enqueue_script('flot-grow');
	}

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
			'grow' => array(				// Enables grow animation
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

		<div id="chart" class="flot"></div>
		<div id="chart-legend"></div>
<?php
	}

} // End class ShoppReportChart

abstract class ShoppReportExportFramework {

	var $ReportClass = '';
	var $columns = array();
	var $headings = true;
	var $data = false;

	var $recordstart = true;
	var $content_type = "text/plain";
	var $extension = "txt";
	var $set = 0;
	var $limit = 1024;

	function __construct ( $Report ) {

		$this->ReportClass = get_class($Report);
		$this->options = $Report->options;

		$Report->query();
		$Report->setup();

		$this->columns = $Report->columns();
		$this->data = $Report->report;
		$this->records = $Report->total;

		$report = $this->options['report'];

		$settings = shopp_setting("{$report}_report_export");

		$this->headings = str_true($settings['headers']);
		$this->selected = $settings['columns'];

	}

	// function query () {	}

	/**
	 * Generates the output for the exported report
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return void Description...
	 **/
	function output () {
		if ( empty($this->data) ) shopp_redirect(add_query_arg(array_merge($_GET,array('src' => null)),admin_url('admin.php')));

		$sitename = get_bloginfo('name');
		$report = $this->options['report'];
		$reports = Report::reports();
		$name = $reports[$report]['name'];

		header("Content-type: $this->content_type; charset=UTF-8");
		header("Content-Disposition: attachment; filename=\"$sitename $name.$this->extension\"");
		header("Content-Description: Delivered by WordPress/Shopp ".SHOPP_VERSION);
		header("Cache-Control: maxage=1");
		header("Pragma: public");

		$this->begin();
		if ($this->headings) $this->heading();
		$this->records();
		$this->end();
	}

	/**
	 * Outputs the beginning of file marker (BOF)
	 *
	 * Can be used to include a byte order marker (BOM) that sets the endianess of the data
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return void
	 **/
	function begin () { }

	/**
	 * Outputs the column headers when enabled
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return void
	 **/
	function heading () {
		foreach ($this->selected as $name)
			$this->export($this->columns[$name]);
		$this->record();
	}

	/**
	 * Outputs each of the record parts
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return void
	 **/
	function records () {
		$options = array('scale' => $this->scale);
		// @todo Add batch export to reduce memory footprint and add scalability to report exports
		// while (!empty($this->data)) {
		foreach ($this->data as $key => $record) {
			foreach ($this->selected as $column) {
				$title = $this->columns[$column];
				$columns = get_object_vars($record);
				$value = isset($columns[ $column ]) ? ShoppReportExportFramework::parse( $columns[ $column ] ) : false;
				if ( method_exists($this->ReportClass,"export_$column") )
					$value = call_user_func(array($this->ReportClass,"export_$column"),$record,$column,$title,$this->options);
				elseif ( method_exists($this->ReportClass,$column) )
					$value = call_user_func(array($this->ReportClass,$column),$record,$column,$title,$this->options);
				$this->export($value);
			}
			$this->record();
		}
		// 	$this->set++;
		// 	$this->query();
		// }
	}

	/**
	 * Parses column data and normalizes non-standard data
	 *
	 * Non-standard data refers to binary or serialized object strings
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param mixed $column A record value of any type
	 * @return string The normalized string column data
	 **/
	static function parse ( $column ) {
		if (preg_match("/^[sibNaO](?:\:.+?\{.*\}$|\:.+;$|;$)/",$column)) {
			$list = unserialize($column);
			$column = "";
			foreach ($list as $name => $value)
				$column .= (empty($column)?"":";")."$name:$value";
		}
		return $column;
	}

	/**
	 * Outputs the end of file marker (EOF)
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return void
	 **/
	function end () { }

	/**
	 * Outputs each individual value in a record
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return void
	 **/
	function export ($value) {
		echo ($this->recordstart?"":"\t").$value;
		$this->recordstart = false;
	}

	/**
	 * Outputs the end of record marker (EOR)
	 *
	 * @author Jonathan Davis
	 * @since
	 *
	 * @return void Description...
	 **/
	function record () {
		echo "\n";
		$this->recordstart = true;
	}

} // End class ShoppReportExportFramework

class ShoppReportTabExport extends ShoppReportExportFramework {

	function __construct( $Report ) {
		parent::__construct( $Report );
		$this->output();
	}

}

class ShoppReportCSVExport extends ShoppReportExportFramework {
	function __construct ($Report) {
		parent::__construct($Report);
		$this->content_type = "text/csv";
		$this->extension = "csv";
		$this->output();
	}

	function export ($value) {
		$value = str_replace('"','""',$value);
		if (preg_match('/^\s|[,"\n\r]|\s$/',$value)) $value = '"'.$value.'"';
		echo ($this->recordstart?"":",").$value;
		$this->recordstart = false;
	}

}

class ShoppReportXLSExport extends ShoppReportExportFramework {
	function __construct ($Report) {
		parent::__construct($Report);
		$this->content_type = "application/vnd.ms-excel";
		$this->extension = "xls";
		$this->c = 0; $this->r = 0;
		$this->output();
	}

	function begin () {
		echo pack("ssssss", 0x809, 0x8, 0x0, 0x10, 0x0, 0x0);
	}

	function end () {
		echo pack("ss", 0x0A, 0x00);
	}

	function export ($value) {
		if (preg_match('/^[\d\.]+$/',$value)) {
		 	echo pack("sssss", 0x203, 14, $this->r, $this->c, 0x0);
			echo pack("d", $value);
		} else {
			$l = strlen($value);
			echo pack("ssssss", 0x204, 8+$l, $this->r, $this->c, 0x0, $l);
			echo $value;
		}
		$this->c++;
	}

	function record () {
		$this->c = 0;
		$this->r++;
	}
}

