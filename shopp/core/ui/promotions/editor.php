	<div class="wrap shopp"> 
		<?php if (!empty($this->Notice)): ?><div id="message" class="updated fade"><p><?php echo $this->Notice; ?></p></div><?php endif; ?>

		<h2><?php _e('Promotion Editor','Shopp'); ?></h2> 

		<div id="ajax-response"></div> 
		<form name="promotion" id="promotion" action="<?php echo add_query_arg('page','shopp-promotions',admin_url('admin.php')); ?>" method="post">
			<?php wp_nonce_field('shopp-save-promotion'); ?>

			<div class="hidden"><input type="hidden" name="id" value="<?php echo $Promotion->id; ?>" /></div>

			<div id="poststuff" class="metabox-holder has-right-sidebar">

				<div id="side-info-column" class="inner-sidebar">
				<?php
				do_action('submitpage_box');
				$side_meta_boxes = do_meta_boxes('shopp_page_shopp-promotions', 'side', $Promotion);
				?>
				</div>

				<div id="post-body" class="<?php echo $side_meta_boxes ? 'has-sidebar' : 'has-sidebar'; ?>">
				<div id="post-body-content" class="has-sidebar-content">

					<div id="titlediv">
						<div id="titlewrap">
							<input name="name" id="title" type="text" value="<?php echo attribute_escape($Promotion->name); ?>" size="30" tabindex="1" autocomplete="off" />
						</div>
					</div>

				<?php
				do_meta_boxes('shopp_page_shopp-promotions', 'normal', $Promotion);
				do_meta_boxes('shopp_page_shopp-promotions', 'advanced', $Promotion);
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
helpurl = "<?php echo SHOPP_DOCS; ?>Running_Sales_%26_Promotions";


jQuery(document).ready( function() {
var $=jQuery.noConflict();

var currencyFormat = <?php $base = $this->Settings->get('base_operations'); echo json_encode($base['currency']['format']); ?>;
var rules = <?php echo json_encode($Promotion->rules); ?>;
var ruleidx = 1;
var promotion = <?php echo (!empty($Promotion->id))?$Promotion->id:'false'; ?>;
var StartsCalendar = new PopupCalendar($('#starts-calendar'));
StartsCalendar.render();
var EndsCalendar = new PopupCalendar($('#ends-calendar'));
EndsCalendar.render();

var RULES_LANG = {
	"Name":"<?php _e('Name','Shopp'); ?>",
	"Category":"<?php _e('Category','Shopp'); ?>",
	"Variation":"<?php _e('Variation','Shopp'); ?>",
	"Price":"<?php _e('Price','Shopp'); ?>",
	"Sale price":"<?php _e('Sale price','Shopp'); ?>",
	"Type":"<?php _e('Type','Shopp'); ?>",
	"In stock":"<?php _e('In stock','Shopp'); ?>",

	"Any item name":"<?php _e('Any item name','Shopp'); ?>",
	"Any item amount":"<?php _e('Any item amount','Shopp'); ?>",
	"Any item quantity":"<?php _e('Any item quantity','Shopp'); ?>",
	"Total quantity":"<?php _e('Total quantity','Shopp'); ?>",
	"Shipping amount":"<?php _e('Shipping amount','Shopp'); ?>",
	"Subtotal amount":"<?php _e('Subtotal amount','Shopp'); ?>",
	"Promo code":"<?php _e('Promo code','Shopp'); ?>",
	
	"Is equal to":"<?php _e('Is equal to','Shopp'); ?>",
	"Is not equal to":"<?php _e('Is not equal to','Shopp'); ?>",
	"Contains":"<?php _e('Contains','Shopp'); ?>",
	"Does not contain":"<?php _e('Does not contain','Shopp'); ?>",
	"Begins with":"<?php _e('Begins with','Shopp'); ?>",
	"Ends with":"<?php _e('Ends with','Shopp'); ?>",
	"Is greater than":"<?php _e('Is greater than','Shopp'); ?>",
	"Is greater than or equal to":"<?php _e('Is greater than or equal to','Shopp'); ?>",
	"Is less than":"<?php _e('Is less than','Shopp'); ?>",
	"Is less than or equal to":"<?php _e('Is less than or equal to','Shopp'); ?>"
	
}

var product_conditions = {
	"Name":{"logic":["boolean","fuzzy"],"value":"text"},
	"Category":{"logic":["boolean","fuzzy"],"value":"text"},
	"Variation":{"logic":["boolean","fuzzy"],"value":"text"},
	"Price":{"logic":["boolean","amount"],"value":"price"},
	"Sale price":{"logic":["boolean","amount"],"value":"price"},
	"Type":{"logic":["boolean"],"value":"text"},
	"In stock":{"logic":["boolean","amount"],"value":"text"}
}

var order_conditions = {
	"Any item name":{"logic":["boolean","fuzzy"],"value":"text"},
	"Any item quantity":{"logic":["boolean","amount"],"value":"text"},
	"Any item amount":{"logic":["boolean","amount"],"value":"price"},
	"Total quantity":{"logic":["boolean","amount"],"value":"text"},
	"Shipping amount":{"logic":["boolean","amount"],"value":"price"},
	"Subtotal amount":{"logic":["boolean","amount"],"value":"price"},
	"Promo code":{"logic":["boolean"],"value":"text"}
}

var logic = {
	"boolean":["Is equal to","Is not equal to"],
	"fuzzy":["Contains","Does not contain","Begins with","Ends with"],
	"amount":["Is greater than","Is greater than or equal to","Is less than","Is less than or equal to"]
}

function add_condition (rule,location) {
	
	var i = ruleidx;
	
	if (!location) var row = $('<tr></tr>').appendTo('#rules');
	else var row = $('<tr></tr>').insertAfter(location);
	
	var cell = $('<td></td>').appendTo(row);
	var deleteButton = $('<button type="button" class="delete"></button>').html('<img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/delete.png" alt="Delete" width="16" height="16" />').appendTo(cell);

	var properties = $('<select name="rules['+i+'][property]" class="ruleprops"></select>').appendTo(cell);

	if ($('#promotion-scope').val() == "Order") {
		for (var label in order_conditions)
			$('<option></option>').html(RULES_LANG[label]).val(label).attr('rel','order').appendTo(properties);
		
	} else {
		for (var label in product_conditions)
			$('<option></option>').html(RULES_LANG[label]).val(label).attr('rel','product').appendTo(properties);
	}

	var operation = $('<select name="rules['+i+'][logic]" ></select>').appendTo(cell);
	var value = $('<span></span>').appendTo(cell);
	
	var addspan = $('<span></span>').appendTo(cell);
	var addButton = $('<button type="button" class="add"></button>').html('<img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/add.png" alt="Add" width="16" height="16" />').appendTo(addspan);

	addButton.click(function () { add_condition(false,row); });
	
	deleteButton.click(function () { $(row).remove(); });
	
	cell.hover(function () {
		deleteButton.css('visibility','visible');
	},function () {
		deleteButton.css('visibility','hidden');
	});

	var valuefield = function (type) {
		value.empty();
		field = $('<input type="text" name="rules['+i+'][value]" class="selectall" />').appendTo(value);
		if (type == "price") field.change(function () { this.value = asMoney(this.value); });
	}
	
	// Generate logic operation menu
	properties.change(function () {
		operation.empty();

		if ($(this.options[this.selectedIndex]).attr('rel') == "product") var conditions = product_conditions[$(this).val()];
		if ($(this.options[this.selectedIndex]).attr('rel') == "order") var conditions = order_conditions[$(this).val()];

		if (conditions['logic'].length > 0) {
			operation.show();
			for (var l = 0; l < conditions['logic'].length; l++) {
				var lop = conditions['logic'][l];
				if (!lop) break;
				for (var op = 0; op < logic[lop].length; op++) 
					$('<option></option>').html(RULES_LANG[logic[lop][op]]).val(logic[lop][op]).appendTo(operation);
			}
		} else operation.hide();
		
		valuefield(conditions['value']);
	}).change();
	
	// Load up existing conditional rule
	if (rule) {
		properties.val(rule.property).change();
		operation.val(rule.logic);
		if (field) field.val(rule.value);
		
	}
	
	ruleidx++;
	
}

$('#discount-type').change(function () {
	$('#discount-row').hide();
	$('#beyget-row').hide();
	var type = $(this).val();
	
	if (type == "Percentage Off" || type == "Amount Off") $('#discount-row').show();
	if (type == "Buy X Get Y Free") {
		$('#beyget-row').show();
		$('#promotion-scope').val('Order').change();
		$('#promotion-scope option').eq(0).attr('disabled',true);
	} else {
		$('#promotion-scope option').eq(0).attr('disabled',false);
	}
	
	$('#discount-amount').unbind('change').change(function () {
		if (type == "Percentage Off") this.value = asPercent(this.value);
		if (type == "Amount Off") this.value = asMoney(this.value);
	}).change();
	
}).change();

if (rules) for (var r in rules) add_condition(rules[r]);
else add_condition();

$('#promotion-scope').change(function () {
	var scope = $(this).val();
	var menus = $('#rules select.ruleprops');
	$(menus).empty();
	$(menus).each(function (id,menu) {
		if (scope == "Order") {
			for (var label in order_conditions)
				$('<option></option>').html(RULES_LANG[label]).val(label).attr('rel','order').appendTo($(menu));
		} else {
			for (var label in product_conditions)
				$('<option></option>').html(RULES_LANG[label]).val(label).attr('rel','product').appendTo($(menu));
		}
	});

});



var scpos = $('#start-position').offset();
$('#starts-calendar').hide()
	.css({left:scpos.left,
		   top:scpos.top+$('#start-position input:first').height()});
$('#starts-month').click(function (e) {
	$('#ends-calendar').hide();
	$('#starts-calendar').toggle();
	$(StartsCalendar).change(function () {
		$('#starts-month').val(StartsCalendar.selection.getMonth()+1);
		$('#starts-date').val(StartsCalendar.selection.getDate());
		$('#starts-year').val(StartsCalendar.selection.getFullYear());
	});
});

var ecpos = $('#end-position').offset();
$('#ends-calendar').hide()
	.css({left:ecpos.left,
		   top:ecpos.top+$('#end-position input:first').height()});
		
$('#ends-month').click(function (e) {
	$('#starts-calendar').hide();
	$('#ends-calendar').toggle();
	$(EndsCalendar).change(function () {
		$('#ends-month').val(EndsCalendar.selection.getMonth()+1);
		$('#ends-date').val(EndsCalendar.selection.getDate());
		$('#ends-year').val(EndsCalendar.selection.getFullYear());
	});
});

postboxes.add_postbox_toggles('shopp_page_shopp-promotions');
// close postboxes that should be closed
$('.if-js-closed').removeClass('if-js-closed').addClass('closed');

if (!promotion) $('#title').focus();

});

</script>