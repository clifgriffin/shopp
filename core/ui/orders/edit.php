<div class="wrap shopp">

	<div class="icon32"></div>
	<?php if ( ShoppPurchase()->id > 0 ): ?>
		<h2><?php Shopp::_e('Order #%d', (int)$Purchase->id); ?> <a href="<?php echo esc_url(add_query_arg(array('page'=> $this->page(), 'id' => 'new'), admin_url('admin.php'))); ?>" class="add-new-h2"><?php Shopp::_e('Add New'); ?></a> </h2>
	<?php else: ?>
		<h2><?php Shopp::_e('New Order'); ?></h2>
	<?php endif; ?>

	<?php $this->notices(); ?>

	<?php include $this->ui('navigation.php'); ?>
	<br class="clear" />

	<?php
		$totalsedit = isset($_GET['edit']) && 'totals' == $_GET['edit'];
		$columns = get_column_headers($this->screen);
		$hidden = get_hidden_columns($this->screen);
		$colspan = count($columns);
	?>
	<div id="order">
			<div class="title">
				<div id="titlewrap">
					<span class="date"><?php echo Shopp::_d(get_option('date_format'), $Purchase->created); ?> <small><?php echo date(get_option('time_format'),$Purchase->created); ?></small>

					<div class="alignright">

						<?php if ($Purchase->shipped): ?>
						<div class="stamp shipped<?php if ( $Purchase->isvoid() ) echo ' void'; ?>"><div class="type"><?php _e('Shipped','Shopp'); ?></div><div class="ing">&nbsp;</div></div>
						<?php endif; ?>

						<?php if ( $Purchase->ispaid() && ! $Purchase->isvoid() ): ?>
						<div class="stamp paid"><div class="type"><?php _e('Paid','Shopp'); ?></div><div class="ing">&nbsp;</div></div>
						<?php elseif ($Purchase->isvoid()): ?>
						<div class="stamp void"><div class="type"><?php _e('Void','Shopp'); ?></div><div class="ing">&nbsp;</div></div>
						<?php endif; ?>

					</div>

				</div>
			</div>

		<div id="poststuff" class="poststuff">
			<div class="meta-boxes">

				<div id="topside" class="third-column first-third-column box-stretch">
					<?php do_meta_boxes($this->screen, 'topside', $Purchase); ?>
				</div>
				<div id="topic" class="third-column  box-stretch">
					<?php do_meta_boxes($this->screen, 'topic', $Purchase); ?>
				</div>
				<div id="topsider" class="third-column  box-stretch">
					<?php do_meta_boxes($this->screen, 'topsider', $Purchase); ?>
				</div>

			</div>

			<?php include $this->ui('editor.php'); ?>

			<?php if ( 'new' == $_GET['id'] ): ?>
			<div class="meta-boxes">

				<div id="underside" class="third-column first-third-column  box-stretch">
					<?php do_meta_boxes($this->screen, 'underside', $Purchase); ?>
				</div>
				<div id="underic" class="third-column  box-stretch">
					<?php do_meta_boxes($this->screen, 'underic', $Purchase); ?>
				</div>
				<div id="undersider" class="third-column  box-stretch">
					<?php do_meta_boxes($this->screen, 'undersider', $Purchase); ?>
				</div>

				<div id="management">
					<?php do_meta_boxes($this->screen, 'normal', $Purchase); ?>
				</div>
			</div>
			<?php else: ?>
			<div class="meta-boxes">

				<div id="column-one" class="column left-column">
					<?php do_meta_boxes($this->screen, 'side', $Purchase); ?>
				</div>
				<div id="main-column">
					<div id="column-two" class="column right-column">
						<?php do_meta_boxes($this->screen, 'normal', $Purchase); ?>
					</div>
				</div>
				<br class="clear" />
			</div>
			<?php endif; ?>

			<?php wp_nonce_field('shopp-save-order'); ?>
			<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false); ?>
			<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false); ?>
			</div>
	</div> <!-- #order -->

</div>

<iframe id="print-receipt" name="receipt" src="<?php echo wp_nonce_url(admin_url('admin-ajax.php').'?action=shopp_order_receipt&amp;id='.$Purchase->id,'wp_ajax_shopp_order_receipt'); ?>" width="400" height="100" class="invisible"></iframe>

<script type="text/javascript">
/* <![CDATA[ */
var carriers   = <?php echo json_encode($carriers_json); ?>,
	noteurl    = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'), 'wp_ajax_shopp_order_note_message'); ?>',
	producturl = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'), 'wp_ajax_shopp_select_product'); ?>',
	addressurl = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'), 'wp_ajax_shopp_lookup_addresses'); ?>';

jQuery(document).ready(function($) {

<?php do_action('shopp_order_admin_script', $Purchase); ?>

});
/* ]]> */
</script>