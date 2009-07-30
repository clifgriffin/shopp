<?php if (SHOPP_WP27): ?>
	<div class="wrap shopp"> 
		<?php if (!empty($Shopp->Flow->Notice)): ?><div id="message" class="updated fade"><p><?php echo $Shopp->Flow->Notice; ?></p></div><?php endif; ?>

		<h2><?php _e('Customer Editor','Shopp'); ?></h2> 

		<div id="ajax-response"></div> 
		<form name="customer" id="customer" action="<?php echo add_query_arg('page',$this->Admin->customers,$Shopp->wpadminurl."admin.php"); ?>" method="post">
			<?php wp_nonce_field('shopp-save-customer'); ?>

			<div class="hidden"><input type="hidden" name="id" value="<?php echo $Customer->id; ?>" /></div>

			<div id="poststuff" class="metabox-holder has-right-sidebar">

				<div id="side-info-column" class="inner-sidebar">
				<?php
				do_action('submitpage_box');
				$side_meta_boxes = do_meta_boxes('admin_page_shopp-customers-edit', 'side', $Customer);
				?>
				</div>

				<div id="post-body" class="<?php echo $side_meta_boxes ? 'has-sidebar' : 'has-sidebar'; ?>">
				<div id="post-body-content" class="has-sidebar-content">
				<?php
				do_meta_boxes('admin_page_shopp-customers-edit', 'normal', $Customer);
				do_meta_boxes('admin_page_shopp-customers-edit', 'advanced', $Customer);
				wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
				?>

				</div>
				</div>

			</div> <!-- #poststuff -->
		</form>
	</div>

<?php else: ?>
	
<div class="wrap shopp">

	<h2><?php _e('Customer Editor','Shopp'); ?></h2>
	
	<form name="promotion" id="promotion" method="post" action="<?php echo add_query_arg('page',$Shopp->Flow->Admin->promotions,$Shopp->wpadminurl."admin.php"); ?>">
		<?php wp_nonce_field('shopp-save-promotion'); ?>

		<div class="hidden"><input type="hidden" name="id" value="<?php echo $Promotion->id; ?>" /></div>

		<table class="form-table"> 
			<tr class=" form-required"> 
				<th scope="row" valign="top"><label for="promotion-name"><?php _e('Description','Shopp'); ?></label></th> 
				<td><input type="text" name="name" value="<?php echo attribute_escape($Promotion->name); ?>" id="promotion-name" size="40" /><br /> 
	            <?php _e('The name is used to describe the promotion on order receipts.','Shopp'); ?></td>
			</tr>
			<tr class=" form-required"> 
				<th scope="row" valign="top"><label for="discount-status"><?php _e('Status','Shopp'); ?></label></th> 
				<td>
					<label for="discount-status"><input type="hidden" name="status" value="disabled" /><input type="checkbox" name="status" id="discount-status" value="enabled"<?php echo ($Promotion->status == "enabled")?' checked="checked"':''; ?> /> &nbsp;<?php _e('Enabled','Shopp'); ?></label>

					<p></p>
					
					<div id="start-position" class="calendar-wrap"><input type="text" name="starts[month]" id="starts-month" title="<?php _e('Month','Shopp'); ?>" size="3" value="<?php echo ($Promotion->starts>1)?date("n",$Promotion->starts):''; ?>" class="selectall" />/<input type="text" name="starts[date]" id="starts-date" title="<?php _e('Day','Shopp'); ?>" size="3" value="<?php echo ($Promotion->starts>1)?date("j",$Promotion->starts):''; ?>" class="selectall" />/<input type="text" name="starts[year]" id="starts-year" title="<?php _e('Year','Shopp'); ?>" size="5" value="<?php echo ($Promotion->starts>1)?date("Y",$Promotion->starts):''; ?>" class="selectall" /></div> &mdash; <div id="end-position" class="calendar-wrap"><input type="text" name="ends[month]" id="ends-month" title="<?php _e('Month','Shopp'); ?>" size="3" value="<?php echo ($Promotion->ends>1)?date("n",$Promotion->ends):''; ?>" class="selectall" />/<input type="text" name="ends[date]" id="ends-date" title="<?php _e('Day','Shopp'); ?>" size="3" value="<?php echo ($Promotion->ends>1)?date("j",$Promotion->ends):''; ?>" class="selectall" />/<input type="text" name="ends[year]" id="ends-year" title="<?php _e('Year','Shopp'); ?>" size="5" value="<?php echo ($Promotion->ends>1)?date("Y",$Promotion->ends):''; ?>" class="selectall" /></div>
					<br />
					<?php _e('Enter the date range this promotion will be in effect for.','Shopp'); ?>
					
	            </td>
			</tr>			
			<tr class=" form-required"> 
				<th scope="row" valign="top"><label for="discount-type"><?php _e('Discount Type','Shopp'); ?></label></th> 
				<td><select name="type" id="discount-type">
					<?php echo menuoptions($types,$Promotion->type,true); ?>
					</select><br />
	            <?php _e('Select how the discount will be applied.','Shopp'); ?></td>
			</tr>
			<tr id="discount-row" class=" form-required"> 
				<th scope="row" valign="top"><label for="discount-amount"><?php _e('Discount Amount','Shopp'); ?></label></th> 
				<td><input type="text" name="discount" id="discount-amount" value="<?php echo $Promotion->discount; ?>" size="10" /><br />
	            <?php _e('Enter the amount of this discount.','Shopp'); ?></td>
			</tr>
			<tr id="beyget-row" class=" form-required"> 
				<th scope="row" valign="top"><label for="discount-amount"><?php _e('Item Quantities','Shopp'); ?></label></th> 
				<td><?php _e('Buy','Shopp'); ?> <input type="text" name="buyqty" id="buy-x" value="<?php echo $Promotion->buyqty; ?>" size="5" /> <?php _e('Get','Shopp'); ?> <input type="text" name="getqty" id="get-y" value="<?php echo $Promotion->getqty; ?>" size="5" /><br />
	            <?php _e('Enter the number of items that must be purchased and how many will be gifted.','Shopp'); ?></td>
			</tr>
			
		</table>
		<br class="clear" />
		<?php
			$scope = '<select name="scope" id="promotion-scope">';
			$scope .= menuoptions($Promotion->_lists['scope'],$Promotion->scope);
			$scope .= '</select>';
	
			if (empty($Promotion->logic)) $Promotion->logic = "all";
	
			$logic = '<select name="search" class="small">';
			$logic .= menuoptions(array('any'=>__('any','Shopp'),'all' => __('all','Shopp')),$Promotion->logic,true);
			$logic .= '</select>';
		?>
		
		<h3><strong><?php printf(__('Apply discount to %s products where %s of these conditions are met','Shopp'),$scope,$logic); ?>:</strong></h3>
		
		<table class="form-table" id="rules"> 
		</table>
		<p class="submit"><input type="submit" class="button-primary" name="save" value="Save Changes" /></p>
	</form>
</div>
<?php endif; ?>

<div id="starts-calendar" class="calendar"></div>
<div id="ends-calendar" class="calendar"></div>

<script type="text/javascript">
helpurl = "<?php echo SHOPP_DOCS; ?>Editing_a_Customer";

var PWD_INDICATOR = "<?php _e('Strength indicator'); ?>";

var PWD_GOOD = "<?php _e('Good'); ?>";
var PWD_BAD = "<?php _e('Bad'); ?>";
var PWD_SHORT = "<?php _e('Short'); ?>";
var PWD_STRONG = "<?php _e('Strong'); ?>";

jQuery(document).ready( function() {

var $=jQuery.noConflict();

var wp26 = <?php echo (SHOPP_WP27)?'false':'true'; ?>;
var regions = <?php echo json_encode($regions); ?>;

if (!wp26) {
	postboxes.add_postbox_toggles('admin_page_shopp-customers-edit');
	// close postboxes that should be closed
	jQuery('.if-js-closed').removeClass('if-js-closed').addClass('closed');
}

updateStates('#billing-country','#billing-state-inputs');
updateStates('#shipping-country','#shipping-state-inputs');

function updateStates (country,state)  {
	var selector = $(state).find('select');
	var text = $(state).find('input');
	var label = $(state).find('label');

	function toggleStateInputs () {
		if ($(selector).children().length > 1) {
			$(selector).show().attr('disabled',false);
			$(text).hide().attr('disabled',true);
			$(label).attr('for',$(selector).attr('id'))
		} else {
			$(selector).hide().attr('disabled',true);
			$(text).show().attr('disabled',false).val('');
			$(label).attr('for',$(text).attr('id'))
		}
		
	}

	$(country).change(function() {
		if ($(selector).attr('type') == "text") return true;
		$(selector).empty().attr('disabled',true);
		$('<option></option>').val('').html('').appendTo(selector);
		if (regions[this.value]) {
			$.each(regions[this.value], function (value,label) {
				option = $('<option></option>').val(value).html(label).appendTo(selector);
			});
			$(selector).attr('disabled',false);
		}
		toggleStateInputs();
	});
	
	toggleStateInputs();
	
}

// Included from the WP 2.8 password strength meter
// Copyright by Automattic
$('#new-password').val('').keyup( check_pass_strength );

function check_pass_strength () {
	var pass = $('#new-password').val(), user = $('#email').val(), strength;

	$('#pass-strength-result').removeClass('short bad good strong');
	if ( ! pass ) {
		$('#pass-strength-result').html( PWD_INDICATOR );
		return;
	}

	strength = passwordStrength(pass, user);

	switch ( strength ) {
		case 2:
			$('#pass-strength-result').addClass('bad').html( PWD_BAD );
			break;
		case 3:
			$('#pass-strength-result').addClass('good').html( PWD_GOOD );
			break;
		case 4:
			$('#pass-strength-result').addClass('strong').html( PWD_STRONG );
			break;
		default:
			$('#pass-strength-result').addClass('short').html( PWD_SHORT );
	}
}

function passwordStrength(password,username) {
    var shortPass = 1, badPass = 2, goodPass = 3, strongPass = 4, symbolSize = 0, natLog, score;

	//password < 4
    if (password.length < 4 ) { return shortPass };

    //password == username
    if (password.toLowerCase()==username.toLowerCase()) return badPass;

	if (password.match(/[0-9]/)) symbolSize +=10;
	if (password.match(/[a-z]/)) symbolSize +=26;
	if (password.match(/[A-Z]/)) symbolSize +=26;
	if (password.match(/[^a-zA-Z0-9]/)) symbolSize +=31;

	natLog = Math.log( Math.pow(symbolSize,password.length) );
	score = natLog / Math.LN2;
	if (score < 40 )  return badPass
	if (score < 56 )  return goodPass
    return strongPass;
}

});

</script>