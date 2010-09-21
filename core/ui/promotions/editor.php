	<div class="wrap shopp"> 
		<?php if (!empty($this->Notice)): ?><div id="message" class="updated fade"><p><?php echo $this->Notice; ?></p></div><?php endif; ?>

		<div class="icon32"></div>
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
							<input name="name" id="title" type="text" value="<?php echo esc_attr($Promotion->name); ?>" size="30" tabindex="1" autocomplete="off" />
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

jQuery(document).ready( function() {
var $=jqnc(),
	currencyFormat = <?php $base = $this->Settings->get('base_operations'); echo json_encode($base['currency']['format']); ?>,
	rules = <?php echo json_encode($Promotion->rules); ?>,
	ruleidx = 1,
	itemidx = 1,
	promotion = <?php echo (!empty($Promotion->id))?$Promotion->id:'false'; ?>,
	loading = true,
	SCOPEPROP_LANG = {
		"Catalog":"<?php _e('price','Shopp'); ?>",
		"Cart":"<?php _e('subtotal','Shopp'); ?>",
		"Cart Item":"<?php _e('total, where:','Shopp'); ?>"
	},
	TARGET_LANG = {
		"Catalog":"<?php _e('product','Shopp'); ?>",
		"Cart":"<?php _e('cart','Shopp'); ?>",
		"Cart Item":"<?php _e('cart','Shopp'); ?>"
	},
	RULES_LANG = {
		"Name":"<?php _e('Name','Shopp'); ?>",
		"Category":"<?php _e('Category','Shopp'); ?>",
		"Variation":"<?php _e('Variation','Shopp'); ?>",
		"Price":"<?php _e('Price','Shopp'); ?>",
		"Sale price":"<?php _e('Sale price','Shopp'); ?>",
		"Type":"<?php _e('Type','Shopp'); ?>",
		"In stock":"<?php _e('In stock','Shopp'); ?>",

		"Tag name":"<?php _e('Tag name','Shopp'); ?>",
		"Unit price":"<?php _e('Unit price','Shopp'); ?>",
		"Total price":"<?php _e('Total price','Shopp'); ?>",
		"Input name":"<?php _e('Input name','Shopp'); ?>",
		"Input value":"<?php _e('Input value','Shopp'); ?>",
		"Quantity":"<?php _e('Quantity','Shopp'); ?>",

		"Any item name":"<?php _e('Any item name','Shopp'); ?>",
		"Any item amount":"<?php _e('Any item amount','Shopp'); ?>",
		"Any item quantity":"<?php _e('Any item quantity','Shopp'); ?>",
		"Total quantity":"<?php _e('Total quantity','Shopp'); ?>",
		"Shipping amount":"<?php _e('Shipping amount','Shopp'); ?>",
		"Subtotal amount":"<?php _e('Subtotal amount','Shopp'); ?>",
		"Discount amount":"<?php _e('Discount amount','Shopp'); ?>",

		"Customer type":"<?php _e('Customer type','Shopp'); ?>",
		"Ship-to country":"<?php _e('Ship-to country','Shopp'); ?>",

		"Promo code":"<?php _e('Promo code','Shopp'); ?>",
		"Promo use count":"<?php _e('Promo use count','Shopp'); ?>",
	
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
	
	},
	conditions = {
		"Catalog":{
			"Name":{"logic":["boolean","fuzzy"],"value":"text"},
			"Category":{"logic":["boolean","fuzzy"],"value":"text"},
			"Variation":{"logic":["boolean","fuzzy"],"value":"text"},
			"Price":{"logic":["boolean","amount"],"value":"price"},
			"Sale price":{"logic":["boolean","amount"],"value":"price"},
			"Type":{"logic":["boolean"],"value":"text"},
			"In stock":{"logic":["boolean","amount"],"value":"text"}
		},
		"Cart":{
			"Any item name":{"logic":["boolean","fuzzy"],"value":"text"},
			"Any item quantity":{"logic":["boolean","amount"],"value":"text"},
			"Any item amount":{"logic":["boolean","amount"],"value":"price"},
			"Total quantity":{"logic":["boolean","amount"],"value":"text"},
			"Shipping amount":{"logic":["boolean","amount"],"value":"price"},
			"Subtotal amount":{"logic":["boolean","amount"],"value":"price"},
			"Discount amount":{"logic":["boolean","amount"],"value":"price"},
			"Customer type":{"logic":["boolean"],"value":"text"},
			"Ship-to country":{"logic":["boolean"],"value":"text"},
			"Promo use count":{"logic":["boolean","amount"],"value":"text"},
			"Promo code":{"logic":["boolean"],"value":"text"}
		},
		"Cart Item":{
			"Any item name":{"logic":["boolean","fuzzy"],"value":"text"},
			"Any item quantity":{"logic":["boolean","amount"],"value":"text"},
			"Any item amount":{"logic":["boolean","amount"],"value":"price"},
			"Total quantity":{"logic":["boolean","amount"],"value":"text"},
			"Shipping amount":{"logic":["boolean","amount"],"value":"price"},
			"Subtotal amount":{"logic":["boolean","amount"],"value":"price"},
			"Discount amount":{"logic":["boolean","amount"],"value":"price"},
			"Customer type":{"logic":["boolean"],"value":"text"},
			"Ship-to country":{"logic":["boolean","fuzzy"],"value":"text"},
			"Promo use count":{"logic":["boolean","amount"],"value":"text"},
			"Promo code":{"logic":["boolean"],"value":"text"}
		},
		"Cart Item Target":{
			"Name":{"logic":["boolean","fuzzy"],"value":"text"},
			"Category":{"logic":["boolean","fuzzy"],"value":"text"},
			"Tag name":{"logic":["boolean","fuzzy"],"value":"text"},
			"Variation":{"logic":["boolean","fuzzy"],"value":"text"},
			"Input name":{"logic":["boolean","fuzzy"],"value":"text"},
			"Input value":{"logic":["boolean","fuzzy"],"value":"text"},
			"Quantity":{"logic":["boolean","amount"],"value":"text"},
			"Unit price":{"logic":["boolean","amount"],"value":"price"},
			"Total price":{"logic":["boolean","amount"],"value":"price"},
			"Discount amount":{"logic":["boolean","amount"],"value":"price"}
		}
	},
	logic = {
		"boolean":["Is equal to","Is not equal to"],
		"fuzzy":["Contains","Does not contain","Begins with","Ends with"],
		"amount":["Is greater than","Is greater than or equal to","Is less than","Is less than or equal to"]
	},
	Conditional = function (type,settings,location) {
		var target = $('#promotion-target').val(),
			row = false, i = false;

		if (!type) type = 'condition';
	
		if (type == "cartitem") {
			i = itemidx;
			if (!location) row = $('<tr />').appendTo('#cartitem');
			else row = $('<tr></tr>').insertAfter(location);
		} else {
			i = ruleidx;
			if (!location) row = $('<tr />').appendTo('#rules');
			else row = $('<tr></tr>').insertAfter(location);
		}

		var cell = $('<td></td>').appendTo(row);
		var deleteButton = $('<button type="button" class="delete"></button>').html('<img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/delete.png" alt="<?php _e('Delete','Shopp'); ?>" width="16" height="16" />').appendTo(cell).click(function () { if (i > 1) $(row).remove(); }).attr('opacity',0);

		var properties_name = (type=='cartitem')?'rules[item]['+i+'][property]':'rules['+i+'][property]';
		var properties = $('<select name="'+properties_name+'" class="ruleprops"></select>').appendTo(cell);
	
		if (type == "cartitem") target = "Cart Item Target";
		if (conditions[target])
			for (var label in conditions[target])
				$('<option></option>').html(RULES_LANG[label]).val(label).attr('rel',target).appendTo(properties);

		var operation_name = (type=='cartitem')?'rules[item]['+i+'][logic]':'rules['+i+'][logic]';
		var operation = $('<select name="'+operation_name+'" ></select>').appendTo(cell);
		var value = $('<span></span>').appendTo(cell);
	
		var addspan = $('<span></span>').appendTo(cell);
		$('<button type="button" class="add"></button>').html('<img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/add.png" alt="<?php _e('Add','Shopp'); ?>" width="16" height="16" />').appendTo(addspan).click(function () { new Conditional(type,false,row); });
	
		cell.hover(function () {
			if (i > 1) deleteButton.css({'opacity':100,'visibility':'visible'});
		},function () {
			deleteButton.animate({'opacity':0});
		});

		var valuefield = function (fieldtype) {
			value.empty();
			var name = (type=='cartitem')?'rules[item]['+i+'][value]':'rules['+i+'][value]';
			field = $('<input type="text" name="'+name+'" class="selectall" />').appendTo(value);
			if (fieldtype == "price") field.change(function () { this.value = asMoney(this.value); });
		}
	
		// Generate logic operation menu
		properties.change(function () {
			operation.empty();
			if (!$(this).val()) this.selectedIndex = 0;
			var property = $(this).val();
			var c = false;
			if (conditions[$(this).find(':selected').attr('rel')]);
				c = conditions[$(this).find(':selected').attr('rel')][property];

			if (c['logic'].length > 0) {
				operation.show();
				for (var l = 0; l < c['logic'].length; l++) {
					var lop = c['logic'][l];
					if (!lop) break;
					for (var op = 0; op < logic[lop].length; op++) 
						$('<option></option>').html(RULES_LANG[logic[lop][op]]).val(logic[lop][op]).appendTo(operation);
				}
			} else operation.hide();
		
			valuefield(c['value']);
		}).change();
		
		// Load up existing conditional rule
		if (settings) {
			properties.val(settings.property).change();
			operation.val(settings.logic);
			if (field) field.val(settings.value);
		}
	
		if (type == "cartitem") itemidx++;
		else ruleidx++;
	};
	
$('.postbox a.help').click(function () {
	$(this).colorbox({iframe:true,open:true,innerWidth:768,innerHeight:480,scrolling:false});
	return false;
});
	

$('#discount-type').change(function () {
	$('#discount-row').hide();
	$('#beyget-row').hide();
	var type = $(this).val();
	
	if (type == "Percentage Off" || type == "Amount Off") $('#discount-row').show();
	if (type == "Buy X Get Y Free") {
		$('#beyget-row').show();
		$('#promotion-target').val('Cart Item').change();
		$('#promotion-target option:lt(2)').attr('disabled',true);
	} else {
		$('#promotion-target option:lt(2)').attr('disabled',false);
	}
	
	$('#discount-amount').unbind('change').change(function () {
		var value = this.value;
		if (loading) {
			value = new Number(this.value);
			loading = !loading;
		}
		if (type == "Percentage Off") this.value = asPercent(value);
		if (type == "Amount Off") this.value = asMoney(value);
	}).change();
	
}).change();

$('#promotion-target').change(function () {
	var target = $(this).val();
	var menus = $('#rules select.ruleprops');
	$('#target-property').html(SCOPEPROP_LANG[target]);
	$('#rule-target').html(TARGET_LANG[target]);
	$(menus).empty().each(function (id,menu) {
		for (var label in conditions[target])
			$('<option></option>').html(RULES_LANG[label]).val(label).attr('rel',target).appendTo($(menu));
	});
	if (target == "Cart Item") {
		if (rules['item']) for (var r in rules['item']) new Conditional('cartitem',rules['item'][r]);
		else new Conditional('cartitem');
	} else $('#cartitem').empty();

}).change();


if (rules) {
	for (var r in rules) if (r != 'item') new Conditional('condition',rules[r]);
} else new Conditional();

$('#starts-calendar').PopupCalendar({
	m_input:$('#starts-month'),
	d_input:$('#starts-date'),
	y_input:$('#starts-year')
}).bind('show',function () {
	$('#ends-calendar').hide();
});

$('#ends-calendar').PopupCalendar({
	m_input:$('#ends-month'),
	d_input:$('#ends-date'),
	y_input:$('#ends-year')
}).bind('show',function () {
	$('#starts-calendar').hide();
});

postboxes.add_postbox_toggles('shopp_page_shopp-promotions');
// close postboxes that should be closed
$('.if-js-closed').removeClass('if-js-closed').addClass('closed');

if (!promotion) $('#title').focus();

});

</script>