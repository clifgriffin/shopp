<div class="wrap shopp">
	<div class="icon32"></div>
	<h2><?php _e('Reports','Shopp'); ?></h2>

	<?php do_action('shopp_admin_notices'); ?>

	<form action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" id="orders" method="get">
	<?php include("navigation.php"); ?>
	<div>
		<input type="hidden" name="page" value="<?php echo $page; ?>" />
	</div>
	<div class="clear"></div>

	<div class="tablenav">
		<div class="alignleft actions inline">
			<div class="filtering">
				<select name="range" id="range">
					<?php echo menuoptions($ranges,$range,true); ?>
				</select>
				<div id="dates" class="hide-if-js">
					<div id="start-position" class="calendar-wrap"><input type="text" id="start" name="start" value="<?php echo $startdate; ?>" size="10" class="search-input selectall" /></div>
					<small>to</small>
					<div id="end-position" class="calendar-wrap"><input type="text" id="end" name="end" value="<?php echo $enddate; ?>" size="10" class="search-input selectall" /></div>
				</div>
				<button type="submit" id="filter-button" name="filter" value="order" class="button-secondary"><?php _e('Filter','Shopp'); ?></button>
				<button type="submit" id="scale-button-hour" name="scale" value="hour" class="button-secondary"><?php _e('Hour','Shopp'); ?></button>
				<button type="submit" id="scale-button-day" name="scale" value="day" class="button-secondary"><?php _e('Day','Shopp'); ?></button>
				<button type="submit" id="scale-button-week" name="scale" value="week" class="button-secondary"><?php _e('Week','Shopp'); ?></button>
				<button type="submit" id="scale-button-month" name="scale" value="month" class="button-secondary"><?php _e('Month','Shopp'); ?></button>
			</div>
		</div>

		<?php $ListTable->pagination('top'); ?>

		<div class="clear"></div>
	</div>
	<div class="clear"></div>

	<?php $Report->table(); ?>

	</form>

	<div class="tablenav">

		<?php $ListTable->pagination('bottom'); ?>

		<div class="clear"></div>
	</div>
</div>

<script type="text/javascript">
var lastexport = new Date(<?php echo date("Y,(n-1),j",shopp_setting('purchaselog_lastexport')); ?>);

jQuery(document).ready( function() {
var $=jqnc();

pagenow = 'toplevel_page_shopp-orders';
columns.init(pagenow);

function formatDate (e) {
	if (this.value == "") match = false;
	if (this.value.match(/^(\d{6,8})/))
		match = this.value.match(/(\d{1,2}?)(\d{1,2})(\d{4,4})$/);
	else if (this.value.match(/^(\d{1,2}.{1}\d{1,2}.{1}\d{4})/))
		match = this.value.match(/^(\d{1,2}).{1}(\d{1,2}).{1}(\d{4})/);
	if (match) {
		date = new Date(match[3],(match[1]-1),match[2]);
		$(this).val((date.getMonth()+1)+"/"+date.getDate()+"/"+date.getFullYear());
		range.val('custom');
	}
}

var range = $('#range'),
	start = $('#start').change(formatDate),
	StartCalendar = $('<div id="start-calendar" class="calendar"></div>').appendTo('#wpwrap').PopupCalendar({
		scheduling:false,
		input:start
	}).bind('calendarSelect',function () {
		range.val('custom');
	}),
	end = $('#end').change(formatDate),
	EndCalendar = $('<div id="end-calendar" class="calendar"></div>').appendTo('#wpwrap').PopupCalendar({
		scheduling:true,
		input:end,
		scheduleAfter:StartCalendar
	}).bind('calendarSelect',function () {
		range.val('custom');
	});

range.change(function () {
	if (this.selectedIndex == 0) {
		start.val(''); end.val('');
		$('#dates').hide();
		return;
	} else $('#dates').show();
	var today = new Date(),
		startdate = new Date(today.getFullYear(),today.getMonth(),today.getDate()),
		enddate = new Date(today.getFullYear(),today.getMonth(),today.getDate());
	today = new Date(today.getFullYear(),today.getMonth(),today.getDate());

	switch($(this).val()) {
		case 'week':
			startdate.setDate(today.getDate()-today.getDay());
			enddate = new Date(startdate.getFullYear(),startdate.getMonth(),startdate.getDate()+6);
			break;
		case 'month':
			startdate.setDate(1);
			enddate = new Date(startdate.getFullYear(),startdate.getMonth()+1,0);
			break;
		case 'quarter':
			quarter = Math.floor(today.getMonth()/3);
			startdate = new Date(today.getFullYear(),today.getMonth()-(today.getMonth()%3),1);
			enddate = new Date(today.getFullYear(),startdate.getMonth()+3,0);
			break;
		case 'year':
			startdate = new Date(today.getFullYear(),0,1);
			enddate = new Date(today.getFullYear()+1,0,0);
			break;
		case 'yesterday':
			startdate.setDate(today.getDate()-1);
			enddate.setDate(today.getDate()-1);
			break;
		case 'lastweek':
			startdate.setDate(today.getDate()-today.getDay()-7);
			enddate.setDate((today.getDate()-today.getDay()+6)-7);
			break;
		case 'last30':
			startdate.setDate(today.getDate()-30);
			enddate.setDate(today.getDate());
			break;
		case 'last90':
			startdate.setDate(today.getDate()-90);
			enddate.setDate(today.getDate());
			break;
		case 'lastmonth':
			startdate = new Date(today.getFullYear(),today.getMonth()-1,1);
			enddate = new Date(today.getFullYear(),today.getMonth(),0);
			break;
		case 'lastquarter':
			startdate = new Date(today.getFullYear(),(today.getMonth()-(today.getMonth()%3))-3,1);
			enddate = new Date(today.getFullYear(),startdate.getMonth()+3,0);
			break;
		case 'lastyear':
			startdate = new Date(today.getFullYear()-1,0,1);
			enddate = new Date(today.getFullYear(),0,0);
			break;
		case 'lastexport':
			startdate = lastexport;
			enddate = today;
			break;
		case 'custom': return; break;
	}
	StartCalendar.select(startdate);
	EndCalendar.select(enddate);
}).change();

$('#selectall_columns').change(function () {
	if ($(this).attr('checked')) $('#export-columns input').not(this).attr('checked',true);
	else $('#export-columns input').not(this).attr('checked',false);
});
$('input.current-page').unbind('mouseup.select').bind('mouseup.select',function () { this.select(); });

});

</script>