<div><span>
<select name="type" id="discount-type">
	<?php echo $types_menu ?>
</select></span>
<span id="discount-row">
	&mdash;
	<input type="text" name="discount" id="discount-amount" value="<?php echo $Promotion->discount; ?>" size="10" class="selectall" />
</span>
<span id="bogof-row">
	&mdash;
	&nbsp;<?php _e('Buy','Shopp'); ?> <input type="text" name="buyqty" id="buy-x" value="<?php echo $Promotion->buyqty; ?>" size="5" class="selectall" /> <?php _e('Get','Shopp'); ?> <input type="text" name="getqty" id="get-y" value="<?php echo $Promotion->getqty; ?>" size="5" class="selectall" />
</span></div>
<p><?php _e('Select the discount type and amount.','Shopp'); ?></p>