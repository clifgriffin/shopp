<?php
/**
 * Dashboard.php
 *
 * Admin Dashboard controller
 *
 * @author Jonathan Davis
 * @copyright Ingenesis Limited, June 2013
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @version 1.0
 * @since 1.3
 **/

defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit; // Prevent direct access

// @todo Move widgets to self contained classes and add a registration process to ShoppAdminDashboard
class ShoppAdminDashboard {

	/**
	 * Initializes the Shopp dashboard widgets
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	public static function init () {

		$dashboard = shopp_setting('dashboard');

		if ( ! ( current_user_can('shopp_financials') && Shopp::str_true($dashboard) ) ) return false;

		wp_add_dashboard_widget('dashboard_shopp_stats', Shopp::__('Sales Stats'), array(__CLASS__, 'stats_widget'),
			array('all_link' => '', 'feed_link' => '', 'width' => 'half', 'height' => 'single')
		);

		wp_add_dashboard_widget('dashboard_shopp_orders', Shopp::__('Recent Orders'), array(__CLASS__, 'orders_widget'),
			array('all_link' => 'admin.php?page=' . ShoppAdmin()->pagename('orders'), 'feed_link' => '', 'width' => 'half', 'height' => 'single')
		);

		if ( shopp_setting_enabled('inventory') ) {
			wp_add_dashboard_widget('dashboard_shopp_inventory', Shopp::__('Inventory Monitor'), array(__CLASS__, 'inventory_widget'),
				array('all_link' => 'admin.php?page=' . ShoppAdmin()->pagename('products'), 'feed_link' => '', 'width' => 'half', 'height' => 'single')
			);
		}

		add_action('admin_print_styles-index.php', array(__CLASS__, 'styles'));

	}

	/**
	 * Loads the Shopp admin CSS on the WordPress dashboard for widget styles
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	public static function styles () {
		wp_enqueue_style('shopp.dashboard', SHOPP_ADMIN_URI . '/styles/dashboard.css', array(), ShoppVersion::cache(), 'screen');
	}

	/**
	 * Dashboard Widgets
	 */
	/**
	 * Renders the order stats widget
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	public static function stats_widget ( $args = false ) {

		$ranges = array(
			'today'	        => Shopp::__('Today'),
			'week'          => Shopp::__('This Week'),
			'month'         => Shopp::__('This Month'),
			'quarter'       => Shopp::__('This Quarter'),
			'year'          => Shopp::__('This Year'),
			'yesterday'     => Shopp::__('Yesterday'),
			'lastweek'      => Shopp::__('Last Week'),
			'last30'        => Shopp::__('Last 30 Days'),
			'last90'        => Shopp::__('Last 3 Months'),
			'lastmonth'     => Shopp::__('Last Month'),
			'lastquarter'   => Shopp::__('Last Quarter'),
			'lastyear'      => Shopp::__('Last Year'),
		);

		$defaults = array(
			'before_widget' => '',
			'before_title'  => '',
			'widget_name'   => '',
			'after_title'   => '',
			'after_widget'  => '',
			'range'         => isset($_GET['shopp-stats-range']) ? $_GET['shopp-stats-range'] : ''
		);
		$args = array_merge($defaults, (array) $args);
		extract( $args, EXTR_SKIP );

		if ( ! $range || !isset($ranges[ strtolower($range) ]) ) $range = 'last30';
		$purchasetable = ShoppDatabaseObject::tablename(ShoppPurchase::$table);

		$now      = current_time('timestamp');
		// $offset = get_option( 'gmt_offset' ) * 3600;
		$daytimes = 86400;
		$day      = date('j', $now);
		$month    = date('n', $now);
		$year     = date('Y', $now);
		$end      = $now;

		list($weekstart, $weekend) = array_values(get_weekstartend(current_time('mysql')));
		switch ( $range ) {
			case 'today':       $start = mktime(0, 0, 0, $month, $day, $year); break;
			case 'week':        $start = $weekstart; $end = $weekend; break;
			case 'month':       $start = mktime(0, 0, 0, $month, 1, $year); break;
			case 'quarter':     $start = mktime(0, 0, 0, $month - (3 - ($month % 3)), 1, $year); break;
			case 'year':        $start = mktime(0, 0, 0, 1, 1, $year); break;
			case 'yesterday':   $start = mktime(0, 0, 0, $month, $day - 1, $year); $end = mktime(23, 59, 59, $month, $day - 1, $year); break;
			case 'lastweek':    $start = $weekstart - (7 * $daytimes); $end = $weekstart - 1; break;
			case 'last7':       $start = $now - (7 * $daytimes); break;
			case 'last30':      $start = $now - (30 * $daytimes); break;
			case 'last90':      $start = $now - (90 * $daytimes); break;
			case 'lastmonth':   $start = mktime(0, 0, 0, $month-1, 1, $year); $end = mktime(0, 0, 0, $month, 0, $year); break;
			case 'lastquarter': $start = mktime(0, 0, 0, ($month - (3 - ($month % 3))) - 3, 1, $year); $end = mktime(23, 59, 59, date('n', $start) + 3, 0, $year); break;
			case 'lastyear':    $start = mktime(0, 0, 0, $month, 1, $year-1); $end = mktime(23, 59, 59, 1, 0, $year); break;
		}

		// Include authorizations, captures and old 1.1 tranaction status CHARGED in sales data
		$salestatus = array("'authed'", "'captured'", "'CHARGED'");

		$txnstatus = "txnstatus IN (" . join(',', $salestatus) . ")";
		$daterange = "created BETWEEN '" . sDB::mkdatetime($start) . "' AND '" . sDB::mkdatetime($end) . "'";

		$query = "SELECT count(id) AS orders,
						SUM(total) AS sales,
						AVG(total) AS average,
		 				SUM(IF($daterange,1,0)) AS wkorders,
						SUM(IF($daterange,total,0)) AS wksales,
						AVG(IF($daterange,total,null)) AS wkavg
 					FROM $purchasetable 
 					WHERE $txnstatus";

		$cached = get_transient('shopp_dashboard_stats_' . $range);
		if ( empty($cached) ) {

			$results = sDB::query($query);

			$RecentBestsellers = new BestsellerProducts(array('range' => array($start, $end), 'show' => 5));
			$RecentBestsellers->load(array('pagination' => false));
			$RecentBestsellers->maxsold = 0;
			foreach ( $RecentBestsellers as $product ) $RecentBestsellers->maxsold = max($RecentBestsellers->maxsold, $product->sold);

			$LifeBestsellers = new BestsellerProducts(array('show' => 5));
			$LifeBestsellers->load(array('pagination' => false));
			$LifeBestsellers->maxsold = 0;
			foreach ($LifeBestsellers as $product) $LifeBestsellers->maxsold = max($LifeBestsellers->maxsold, $product->sold);

			set_transient('shopp_dashboard_stats_' . $range, array($results, $RecentBestsellers, $LifeBestsellers), 300);

		} else list($results, $RecentBestsellers, $LifeBestsellers) = $cached;


		echo $before_widget;

		echo $before_title;
		echo $widget_name;
		echo $after_title;

		$orderscreen   = add_query_arg('page', ShoppAdmin()->pagename('orders'), admin_url('admin.php'));
		$productscreen = add_query_arg(array('page' => ShoppAdmin()->pagename('products')), admin_url('admin.php'));

		?>
		<div class="table"><table>
		<tr><th colspan="2"><form action="<?php echo admin_url('index.php'); ?>">
			<select name="shopp-stats-range" id="shopp-stats-range">
				<?php echo menuoptions($ranges,$range,true); ?>
			</select>
			<button type="submit" id="filter-button" name="filter" value="order" class="button-secondary hide-if-js"><?php Shopp::_e('Filter'); ?></button>
		</form>
		</th><th colspan="2"><?php Shopp::_e('Lifetime'); ?></th></tr>

		<tbody>
		<tr><td class="amount"><a href="<?php echo esc_url($orderscreen); ?>"><?php echo (int)$results->wkorders; ?></a></td><td class="label"><?php echo _n('Order', 'Orders', (int)$results->wkorders, 'Shopp'); ?></td>
		<td class="amount"><a href="<?php echo esc_url($orderscreen); ?>"><?php echo (int)$results->orders; ?></a></td><td class="label"><?php echo _n('Order', 'Orders', (int)$results->orders, 'Shopp'); ?></td></tr>

		<tr><td class="amount"><a href="<?php echo esc_url($orderscreen); ?>"><?php echo money($results->wksales); ?></a></td><td class="label"><?php Shopp::_e('Sales'); ?></td>
		<td class="amount"><a href="<?php echo esc_url($orderscreen); ?>"><?php echo money($results->sales); ?></a></td><td class="label"><?php Shopp::_e('Sales'); ?></td></tr>

		<tr><td class="amount"><a href="<?php echo esc_url($orderscreen); ?>"><?php echo money($results->wkavg); ?></a></td><td class="label"><?php Shopp::_e('Average Order'); ?></td>
		<td class="amount"><a href="<?php echo esc_url($orderscreen); ?>"><?php echo money($results->average); ?></a></td><td class="label"><?php Shopp::_e('Average Order'); ?></td></tr>

		<?php if (!empty($RecentBestsellers->products) || !empty($LifeBestsellers->products)): ?>
		<tr>
			<th colspan="2"><?php Shopp::_e('Bestsellers %s', $ranges[ $range ]); ?></th>
			<th colspan="2"><?php Shopp::_e('Lifetime Bestsellers', $ranges[ $range ]); ?></th>
		</tr>
		<?php
			reset($RecentBestsellers);
			reset($LifeBestsellers);
			$firstrun = true;
			while ( true ):
				list($recentid, $recent)     = each($RecentBestsellers->products);
				list($lifetimeid, $lifetime) = each($LifeBestsellers->products);
				if ( ! $recent && ! $lifetime ) break;
			?>
			<tr>
				<?php if (empty($RecentBestsellers->products) && $firstrun) echo '<td colspan="2" rowspan="5">' . Shopp::__('None') . '</td>'; ?>
				<?php if ( ! empty($recent->id) ): ?>
				<td class="salesgraph">
					<div class="bar" style="width:<?php echo ($recent->sold/$RecentBestsellers->maxsold)*100; ?>%;"><?php echo $recent->sold; ?></div>
				</td>
				<td>
				<a href="<?php echo esc_url(add_query_arg('view','bestselling',$productscreen)); ?>"><?php echo esc_html($recent->name); ?></a>
				</td>
				<?php endif; ?>
				<?php if (empty($LifeBestsellers->products) && $firstrun) echo '<td colspan="2" rowspan="5">' . Shopp::__('None') . '</td>'; ?>
				<?php if (!empty($lifetime->id)): ?>
				<td class="salesgraph">
					<div class="bar" style="width:<?php echo ($lifetime->sold/$LifeBestsellers->maxsold)*100; ?>%;"><?php echo $lifetime->sold; ?></div>
				</td>
				<td>
				<a href="<?php echo esc_url(add_query_arg('view','bestselling',$productscreen)); ?>"><?php echo esc_html($lifetime->name); ?></a>
				</td>
				<?php endif; ?>
			</tr>
		<?php $firstrun = false; endwhile; ?>
		<?php endif; ?>
		</tbody></table></div>
		<script type="text/javascript">
		jQuery(document).ready(function($){$('#shopp-stats-range').change(function(){$(this).parents('form').submit();});});
		</script>
		<?php
		echo $after_widget;

	}

	/**
	 * Renders the recent orders dashboard widget
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	public static function orders_widget ( $args = false ) {

		$defaults = array(
			'before_widget' => '',
			'before_title'  => '',
			'widget_name'   => '',
			'after_title'   => '',
			'after_widget'  => ''
		);
		$args = array_merge($defaults, (array) $args);
		extract( $args, EXTR_SKIP );
		$statusLabels = shopp_setting('order_status');

		echo $before_widget;

		echo $before_title;
		echo $widget_name;
		echo $after_title;

		$purchasetable = ShoppDatabaseObject::tablename(ShoppPurchase::$table);
		$purchasedtable = ShoppDatabaseObject::tablename(Purchased::$table);
		$txnlabels = Lookup::txnstatus_labels();

		if ( ! ( $Orders = get_transient('shopp_dashboard_orders') ) ) {
			$Orders = sDB::query("SELECT p.*,count(*) as items FROM (SELECT * FROM $purchasetable WHERE txnstatus != 'purchased' AND txnstatus != 'invoiced' ORDER BY created DESC LIMIT 6) AS p LEFT JOIN $purchasedtable AS i ON i.purchase=p.id GROUP BY p.id ORDER BY p.id DESC", 'array');
			set_transient('shopp_dashboard_orders', $Orders, 90); // Keep for the next 1 minute
		}

		if ( ! empty($Orders) ) {

			echo  '<table class="widefat">'
				. '<thead>'
				. '	<tr>'
				. '		<th scope="col">' . __('Name') . '</th>'
				. '		<th scope="col">' . Shopp::__('Date') . '</th>'
				. '		<th scope="col" class="num">' . Shopp::__('Items') . '</th>'
				. '		<th scope="col" class="num">' . Shopp::__('Total') . '</th>'
				. '		<th scope="col" class="num">' . Shopp::__('Status') . '</th>'
				. '	</tr>'
				. '</thead>'
				. '	<tbody id="orders" class="list orders">';

			$even = false;
			foreach ( $Orders as $Order ) {
				$classes = array();
				if ( $even = !$even ) $classes[] = 'alternate';

				$txnstatus = isset($txnlabels[ $Order->txnstatus ]) ?
					$txnlabels[ $Order->txnstatus ] : $Order->txnstatus;
				$status = isset($statusLabels[ $Order->status ]) ?
					$statusLabels[ $Order->status ] : $Order->status;
				$contact = '' == $Order->firstname . $Order->lastname ?
					'(no contact name)' : $Order->firstname . ' ' . $Order->lastname;
				$url = add_query_arg(array('page' => ShoppAdmin()->pagename('orders'), 'id' => $Order->id), admin_url('admin.php'));
				$classes[] = strtolower(preg_replace('/[^\w]/', '_', $Order->txnstatus));

				echo  '<tr class="' . join(' ',$classes) . '">'
					. '	<td><a class="row-title" href="' . $url . '" title="View &quot;Order '.$Order->id.'&quot;">'.((empty($Order->firstname) && empty($Order->lastname))?'(no contact name)':$Order->firstname.' '.$Order->lastname).'</a></td>'
					. '	<td>'.date("Y/m/d", mktimestamp($Order->created)) . '</td>'
					. '	<td class="num items">' . $Order->items . '</td>'
					. '	<td class="num total">' . money($Order->total) . '</td>'
					. '	<td class="num status">' . $statusLabels[ $Order->status ] . '</td>'
					. '</tr>';
			}

			echo '</tbody></table>';

		} else {
			echo '<p>' . Shopp::__('No orders, yet.') . '</p>';
		}

		echo $after_widget;

	}

	/**
	 * Renders the bestselling products dashboard widget
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	public static function inventory_widget ( $args = false ) {

		$warnings = array(
			'none'      => Shopp::__('OK'),
			'warning'   => Shopp::__('warning'),
			'critical'  => Shopp::__('critical'),
			'backorder' => Shopp::__('backorder')
		);

		$defaults = array(
			'before_widget' => '',
			'before_title'  => '',
			'widget_name'   => '',
			'after_title'   => '',
			'after_widget'  => ''
		);

		$args = array_merge($defaults, (array) $args);
		extract( $args, EXTR_SKIP );

		$pt = ShoppDatabaseObject::tablename(ShoppPrice::$table);
		$setting = ( shopp_setting('lowstock_level') );

		$where = array();

		$where[] = "pt.stock < pt.stocked AND pt.stock/pt.stocked < $setting";
		$where[] = "(pt.context='product' OR pt.context='variation') AND pt.type != 'N/A'";

		$loading = array(
			'columns'    => "pt.id AS stockid,IF(pt.context='variation',CONCAT(p.post_title,': ',pt.label),p.post_title) AS post_title,pt.sku AS sku,pt.stock,pt.stocked",
			'joins'      => array($pt => "LEFT JOIN $pt AS pt ON p.ID=pt.product"),
			'where'      => $where,
			'groupby'    => 'pt.id',
			'orderby'    => '(pt.stock/pt.stocked) ASC',
			'published'  => false,
			'pagination' => false,
			'limit'      => 25
		);

		$Collection = new ProductCollection();
		$Collection->load($loading);

		$productscreen = add_query_arg(array('page' => ShoppAdmin()->pagename('products')), admin_url('admin.php'));

		echo $before_widget;

		echo $before_title;
		echo $widget_name;
		echo $after_title;

		?>
		<table><tbody>
			<?php if ( empty($Collection->products) ) : ?>
				<tr><td colspan="4"><?php Shopp::_e('No low stock products found.'); ?></td></tr>
			<?php else: ?> 
				<?php foreach ( $Collection->products as $product ): $product->lowstock($product->stock, $product->stocked); ?>
				<tr>
					<td class="amount"><?php echo abs($product->stock); ?></td>
					<td><span class="stock lowstock <?php echo $product->lowstock; ?>"><?php echo $warnings[ $product->lowstock ]; ?></span></td>
					<td><a href="<?php echo esc_url(add_query_arg('id',$product->id,$productscreen)); ?>"><?php echo $product->name; ?></a></td>
					<td><a href="<?php echo esc_url(add_query_arg('view','inventory',$productscreen)); ?>"><?php echo $product->sku; ?></a></td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody></table>

		<?php
		echo $after_widget;

	}

}