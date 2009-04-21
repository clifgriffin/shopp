<?php
function save_meta_box ($Promotion) {
?>

<div id="misc-publishing-actions">
	<label for="discount-status"><input type="hidden" name="status" value="disabled" /><input type="checkbox" name="status" id="discount-status" value="enabled"<?php echo ($Promotion->status == "enabled")?' checked="checked"':''; ?> /> &nbsp;<?php _e('Enabled','Shopp'); ?></label>

	<p></p>
	
	<div id="start-position" class="calendar-wrap"><input type="text" name="starts[month]" id="starts-month" size="3" value="<?php echo ($Promotion->starts>1)?date("n",$Promotion->starts):''; ?>" class="selectall" />/<input type="text" name="starts[date]" id="starts-date" size="3"  value="<?php echo ($Promotion->starts>1)?date("j",$Promotion->starts):''; ?>" class="selectall" />/<input type="text" name="starts[year]" id="starts-year" size="5" value="<?php echo ($Promotion->starts>1)?date("Y",$Promotion->starts):''; ?>" class="selectall" /></div>
	<p><?php _e('Start promotion on this date.','Shopp'); ?></p>
	
	<div id="end-position" class="calendar-wrap"><input type="text" name="ends[month]" id="ends-month" size="3" value="<?php echo ($Promotion->ends>1)?date("n",$Promotion->ends):''; ?>" class="selectall" />/<input type="text" name="ends[date]" id="ends-date" size="3" value="<?php echo ($Promotion->ends>1)?date("j",$Promotion->ends):''; ?>" class="selectall" />/<input type="text" name="ends[year]" id="ends-year" size="5" value="<?php echo ($Promotion->ends>1)?date("Y",$Promotion->ends):''; ?>" class="selectall" /></div>
	<p><?php _e('End the promotion on this date.','Shopp'); ?></p>
</div>

<div id="major-publishing-actions">
	<input type="submit" class="button-primary" name="save" value="<?php _e('Save Promotion','Shopp'); ?>" />
</div>
<?php
}
add_meta_box('save-promotion', __('Save','Shopp'), 'save_meta_box', 'admin_page_shopp-promotions-edit', 'side', 'core');

function discount_meta_box ($Promotion) {
	$types = array(
		'Percentage Off' => __('Percentage Off','Shopp'),
		'Amount Off' => __('Amount Off','Shopp'),
		'Free Shipping' => __('Free Shipping','Shopp'),
		'Buy X Get Y Free' => __('Buy X Get Y Free','Shopp')			
	);
	
?>
<p>
<select name="type" id="discount-type">
	<?php echo menuoptions($types,$Promotion->type,true); ?>
</select>
<span id="discount-row"> 
	&mdash;
	<input type="text" name="discount" id="discount-amount" value="<?php echo $Promotion->discount; ?>" size="10" class="selectall" />
</span>
<span id="beyget-row"> 
	&mdash;
	&nbsp;<?php _e('Buy','Shopp'); ?> <input type="text" name="buyqty" id="buy-x" value="<?php echo $Promotion->buyqty; ?>" size="5" class="selectall" /> <?php _e('Get','Shopp'); ?> <input type="text" name="getqty" id="get-y" value="<?php echo $Promotion->getqty; ?>" size="5" class="selectall" />
</span></p>
<p><?php _e('Select the discount type and amount.','Shopp'); ?></p>

<?php
}
add_meta_box('promotion-discount', __('Discount','Shopp'), 'discount_meta_box', 'admin_page_shopp-promotions-edit', 'normal', 'core');

function rules_meta_box ($Promotion) {
	$scope = '<select name="scope" id="promotion-scope">';
	$scope .= menuoptions($Promotion->_lists['scope'],$Promotion->scope);
	$scope .= '</select>';
	
	if (empty($Promotion->logic)) $Promotion->logic = "all";
	
	$logic = '<select name="search" class="small">';
	$logic .= menuoptions(array('any'=>__('any','Shopp'),'all' => __('all','Shopp')),$Promotion->logic,true);
	$logic .= '</select>';

?>
<p><strong><?php printf(__('Apply discount to %s products where %s of these conditions are met','Shopp'),$scope,$logic); ?>:</strong></p>
<table class="form-table" id="rules"></table>
<?php
}
add_meta_box('promotion-rules', __('Conditions','Shopp'), 'rules_meta_box', 'admin_page_shopp-promotions-edit', 'normal', 'core');

do_action('do_meta_boxes', 'admin_page_shopp-promotions-edit', 'normal', $Promotion);
do_action('do_meta_boxes', 'admin_page_shopp-promotions-edit', 'advanced', $Promotion);
do_action('do_meta_boxes', 'admin_page_shopp-promotions-edit', 'side', $Promotion);
?>
