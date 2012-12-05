<div class="wrap shopp">
	<div class="icon32"></div>
	<h2><?php echo $report_title; ?></h2>

	<?php do_action('shopp_admin_notices'); ?>

	<form action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" id="orders" method="get">
	<?php include("navigation.php"); ?>
	<div>
		<input type="hidden" name="page" value="<?php echo $page; ?>" />
		<input type="hidden" name="report" value="<?php echo $report; ?>" />
		<input type="hidden" name="scale" value="<?php echo $scale; ?>" />
	</div>
	<div class="clear"></div>

	<div class="tablenav">
		<div class="alignleft actions inline">
			<div class="filtering">
				<?php do_action('shopp_report_filter_controls'); ?>
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
jQuery(document).ready( function() {
	var pagenow = 'toplevel_page_shopp-reports';
	columns.init(pagenow);
});

</script>