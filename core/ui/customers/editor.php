	<div class="wrap shopp"> 
		<?php if (!empty($Shopp->Flow->Notice)): ?><div id="message" class="updated fade"><p><?php echo $Shopp->Flow->Notice; ?></p></div><?php endif; ?>

		<div class="icon32"></div>
		<h2><?php _e('Customer Editor','Shopp'); ?></h2> 

		<div id="ajax-response"></div> 
		<form name="customer" id="customer" action="<?php echo add_query_arg('page',$this->Admin->pagename('customers'),admin_url('admin.php')); ?>" method="post">
			<?php wp_nonce_field('shopp-save-customer'); ?>

			<div class="hidden"><input type="hidden" name="id" value="<?php echo $Customer->id; ?>" /></div>

			<div id="poststuff" class="metabox-holder has-right-sidebar">

				<div id="side-info-column" class="inner-sidebar">
				<?php
				do_action('submitpage_box');
				$side_meta_boxes = do_meta_boxes('shopp_page_shopp-customers', 'side', $Customer);
				?>
				</div>

				<div id="post-body" class="<?php echo $side_meta_boxes ? 'has-sidebar' : 'has-sidebar'; ?>">
				<div id="post-body-content" class="has-sidebar-content">
				<?php
				do_meta_boxes('shopp_page_shopp-customers', 'normal', $Customer);
				do_meta_boxes('shopp_page_shopp-customers', 'advanced', $Customer);
				wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
				?>

				</div>
				</div>

			</div> <!-- #poststuff -->
		</form>
	</div>

<div id="starts-calendar" class="calendar"></div>
<div id="ends-calendar" class="calendar"></div>

<script type="text/javascript">
/* <![CDATA[ */

var PWD_INDICATOR = "<?php _e('Strength indicator','Shopp'); ?>",
	PWD_GOOD = "<?php _e('Good','Shopp'); ?>",
	PWD_BAD = "<?php _e('Bad','Shopp'); ?>",
	PWD_SHORT = "<?php _e('Short','Shopp'); ?>",
	PWD_STRONG = "<?php _e('Strong','Shopp'); ?>";

jQuery(document).ready( function() {

var $=jqnc(),
	regions = <?php echo json_encode($regions); ?>;

postboxes.add_postbox_toggles('shopp_page_shopp-customers');
// close postboxes that should be closed
$('.if-js-closed').removeClass('if-js-closed').addClass('closed');

$('.postbox a.help').click(function () {
	$(this).colorbox({iframe:true,open:true,innerWidth:768,innerHeight:480,scrolling:false});
	return false;
});

$('#username').click(function () {
	var url = $(this).attr('rel');
	if (url) document.location.href = url;
});

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
			$(text).show().attr('disabled',false);
			$(label).attr('for',$(text).attr('id'))
		}
		
	}

	$(country).change(function() {
		if ($(selector).children().length > 1) $(text).val('');
		if ($(selector).attr('type') == "text") return true;
		$(selector).empty().attr('disabled',true);
		$('<option><\/option>').val('').html('').appendTo(selector);
		if (regions[this.value]) {
			$.each(regions[this.value], function (value,label) {
				option = $('<option><\/option>').val(value).html(label).appendTo(selector);
			});
			$(selector).attr('disabled',false);
		}
		toggleStateInputs();
	});
	
	toggleStateInputs();
	
}

// Included from the WP 2.8 password strength meter
// Copyright by WordPress.org
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
/* ]]> */
</script>