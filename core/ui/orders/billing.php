<?php $this->purchase($Purchase); ?>
<script id="address-editor" type="text/x-jquery-tmpl">
<?php
	echo $editor = $this->editor();
	$data = $this->data();
	$this->json($data);
?>
</script>

<?php if ( $this->editing() ): ?>
	<form action="<?php echo $this->url(); ?>" method="post" id="billing-address-editor">
	<?php echo ShoppUI::template($editor, $data); ?>
	</form>
<?php return; endif; ?>

<form action="<?php echo $this->url(); ?>" method="post" id="billing-address-editor"></form>
<div class="display">
<form action="<?php echo $this->url(); ?>" method="post">
	<input type="hidden" id="edit-billing-address-data" value="<?php echo esc_attr(json_encode($data)); ?>" />
	<input type="submit" id="edit-billing-address" name="edit-billing-address" value="<?php _e('Edit','Shopp'); ?>" class="button-secondary button-edit" />
</form>

<address>
<big><?php echo esc_html("{$Purchase->firstname} {$Purchase->lastname}"); ?></big><br />
<?php echo ! empty($Purchase->company) ? esc_html($Purchase->company) . '<br />' : ''; ?>
<?php echo esc_html($Purchase->address); ?><br />
<?php if ( ! empty($Purchase->xaddress) ) echo esc_html($Purchase->xaddress) . '<br />'; ?>
<?php echo esc_html("{$Purchase->city}" . ( ! empty($Purchase->shipstate) ? ', ' : '') . " {$Purchase->state} {$Purchase->postcode}") ?><br />
<?php echo $targets[ $Purchase->country ]; ?>
</address>
<?php if ( ! empty($Customer->info) && is_array($Customer->info) ): ?>
	<ul>
		<?php foreach ( $Customer->info as $name => $value ): ?>
		<li><strong><?php echo esc_html($name); ?>:</strong> <?php echo esc_html($value); ?></li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
</div>