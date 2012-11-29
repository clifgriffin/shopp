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

	private $ranges = array();		// Stat range menu
	private $scales = array();		// Stat time scales
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

		$this->ranges = array(
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

		$this->scales = array(
			__('Day','HelpDesk'),
			__('Week','HelpDesk'),
			__('Month','HelpDesk'),
			__('Year','HelpDesk')
		);

		$this->reports = array(
			'sales' => array('SalesReport',__('Sales Report','Shopp')),
			'products' => array('ProductsReport',__('Products Report','Shopp')),
		);

		$this->defaults = array(
			'start' => date('n/j/Y',mktime(0,0,0,11,1)),
			'end' => date('n/j/Y',mktime(23,59,59)),
			'range' => '',
			'scale' => 'Day',
			'op' => 'day',
			'report' => 'overall',
			'per_page' => 100
		);

		add_action('load-'.$this->screen,array($this,'request'));
		add_action('load-'.$this->screen,array($this,'loader'));

		shopp_enqueue_script('calendar');

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
			$options['scale'] = 'Day';
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
		$report = isset($_GET['report']) ? $_GET['report'] : 'products';
		if ( ! file_exists(SHOPP_ADMIN_PATH."/reports/$report.php") ) wp_die("The requested report does not exist.");

		require(SHOPP_ADMIN_PATH."/reports/$report.php");
		$ReportClass = $reports[$report][0];
		$Report = new $ReportClass($this->options);
		$Report->query();
		$Report->parse();
		$Report->init();
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

		$this->report();

	}

	function report () {

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
			'enddate' => ''
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
		include(SHOPP_ADMIN_PATH."/reports/reports.php");
	}


} // end class Report


interface ShoppReport {
	public function query();
	public function parse();
	public function table();

}

class ShoppReportFramework {
	var $screen = false;
	var $options = array();
	var $data = array();
	var $report = array();
	var $chart = array();
	var $chartop = array(
		'grid' => array('hoverable' => true),
		'series' => array('lines' => array('show' => true),'points'=>array('show' => true)),
		'xaxis' => array(
			'mode' => 'time',
			'timeformat' => '%m/%d/%y',
			'tickSize' => array(1,'day'),
			'twelveHourClock' => true
		),
		// 'colors' => array('#A6CEE3', '#1F78B4', '#B2DF8A', '#33A02C', '#FB9A99', '#E31A1C', '#FDBF6F', '#FF7F00', '#CAB2D6', '#6A3D9A', '#FFFF99')
		// 'colors' => array('#A6CEE3','#1F78B4','#B2DF8A','#33A02C','#FB9A99','#E31A1C','#FDBF6F','#FF7F00','#CAB2D6','#6A3D9A')
		// 'colors' => array('#008116','#005a67','#629c00','#a80c00','#a85100','#8a0046','#740066')

	);

	var $daterange = false;

	function __construct ($request = array()) {
		$this->options = $request;
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

	function chartaxis ($options,$range,$scale='day') {
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

		return $options;
	}

	function chart ($report,$ts,$value) {
		$tzoffset = date('Z');

		if (isset($this->chart[$report]))
			$this->chart[$report]['data'][] = array(($ts+$tzoffset)*1000,$value);
	}

		function table () {
			if (empty($this->report)) return;
	?>
			<table class="widefat" cellspacing="0">
				<thead>
				<tr><?php ShoppUI::print_column_headers($this->screen); ?></tr>
				</thead>
				<tfoot>
				<tr><?php ShoppUI::print_column_headers($this->screen,false); ?></tr>
				</tfoot>
			<?php if (count($this->report) >  0): ?>
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
				<tbody><tr><td colspan="6"><?php _e('No report data available.','Shopp'); ?></td></tr></tbody>
			<?php endif; ?>
			</table>

	<?php
		}

}