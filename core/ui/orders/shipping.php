<?php
	$this->purchase($Purchase);
	$editor = $this->editor();
	$data = $this->data();
	$this->json($data);

	if ( $this->editing() ):
?>
	<form action="<?php echo $this->url(); ?>" method="post" id="shipping-address-editor">
	<?php echo ShoppUI::template($editor, $data); ?>
	</form>
<?php
	return;
	endif;
?>
<form action="<?php echo $this->url(); ?>" method="post" id="shipping-address-editor"></form>
<div class="display">
	<form action="<?php echo $this->url(); ?>" method="post">
	<input type="submit" id="edit-shipping-address" name="edit-shipping-address" value="<?php Shopp::_e('Edit'); ?>" class="button-secondary button-edit" />
	</form>

	<address><big><?php echo esc_html($Purchase->shipname); ?></big><br />
	<?php echo esc_html($Purchase->shipaddress); ?><br />
	<?php if ( ! empty($Purchase->shipxaddress) ) echo esc_html($Purchase->shipxaddress) . "<br />"; ?>
	<?php echo esc_html("{$Purchase->shipcity}" . ( ! empty($Purchase->shipstate) ?', ' : '' ) . " {$Purchase->shipstate} {$Purchase->shippostcode}") ?><br />
	<?php echo $targets[ $Purchase->shipcountry ]; ?></address>
</div>