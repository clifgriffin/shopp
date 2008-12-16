<div class="wrap shopp">
	<h2><?php _e('Promotion Editor','Shopp'); ?></h2>
	
	<form name="promotion" id="promotion" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
		<?php wp_nonce_field('shopp-save-promotion'); ?>
		
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
					
					<div class="calendar-wrap"><div id="starts-calendar" class="calendar"></div><input type="text" name="starts[month]" id="starts-month" size="3" value="<?php echo ($Promotion->starts>1)?date("n",$Promotion->starts):''; ?>" />/<input type="text" name="starts[date]" id="starts-date" size="3"  value="<?php echo ($Promotion->starts>1)?date("j",$Promotion->starts):''; ?>" />/<input type="text" name="starts[year]" id="starts-year" size="5" value="<?php echo ($Promotion->starts>1)?date("Y",$Promotion->starts):''; ?>" /></div> &mdash; <div class="calendar-wrap"><div id="ends-calendar" class="calendar"></div><input type="text" name="ends[month]" id="ends-month" size="3" value="<?php echo ($Promotion->ends>1)?date("n",$Promotion->ends):''; ?>" />/<input type="text" name="ends[date]" id="ends-date" size="3" value="<?php echo ($Promotion->ends>1)?date("j",$Promotion->ends):''; ?>" />/<input type="text" name="ends[year]" id="ends-year" size="5" value="<?php echo ($Promotion->ends>1)?date("Y",$Promotion->ends):''; ?>" /></div>
					<p><?php _e('Enter the date range this promotion will be in effect for.','Shopp'); ?></p>
					
	            </td>
			</tr>			
			<tr class=" form-required"> 
				<th scope="row" valign="top"><label for="promotion-scope"><?php _e('Applied To','Shopp'); ?></label></th> 
				<td><select name="scope" id="promotion-scope">
					<?php echo menuoptions($Promotion->_lists['scope'],$Promotion->scope); ?>
					</select><br />
	            <?php _e('Apply the discount to individual catalog items, or to an entire order.','Shopp'); ?></td>
			</tr>
			<tr class=" form-required"> 
				<th scope="row" valign="top"><label for="discount-type"><?php _e('Discount Type','Shopp'); ?></label></th> 
				<td><select name="type" id="discount-type">
					<?php echo menuoptions($Promotion->_lists['type'],$Promotion->type); ?>
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
		<h3><?php _e('For products where','Shopp'); ?> <select name="search" class="small">
			<?php 
				if (empty($Promotion->logic)) $Promotion->logic = "all";
				echo menuoptions(array('any','all'),$Promotion->logic); 
			?>
			</select> <?php _e('of these conditions are met:','Shopp'); ?></h3>
		<table class="form-table" id="rules"> 
		</table>
		<p class="submit"><input type="submit" class="button-primary" name="save" value="Save Changes" /></p>
	</form>
</div>

<script type="text/javascript">
helpurl = "<?php echo SHOPP_DOCS; ?>Running_Sales_%26_Promotions";

$=jQuery.noConflict();

var currencyFormat = <?php $base = $this->Settings->get('base_operations'); echo json_encode($base['currency']['format']); ?>;
var rules = <?php echo json_encode($Promotion->rules); ?>;
var ruleidx = 1;
var StartsCalendar = new PopupCalendar($('#starts-calendar'));
StartsCalendar.render();
var EndsCalendar = new PopupCalendar($('#ends-calendar'));
EndsCalendar.render();


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
	"Item name":{"logic":["boolean","fuzzy"],"value":"text"},
	"Item quantity":{"logic":["boolean","amount"],"value":"text"},
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
			$('<option></option>').html(label).val(label).attr('rel','order').appendTo(properties);
		
	} else {
		for (var label in product_conditions)
			$('<option></option>').html(label).val(label).attr('rel','product').appendTo(properties);
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
		field = $('<input type="text" name="rules['+i+'][value]" />').appendTo(value);
		if (type == "price") field.change(function () { this.value = asMoney(this.value); });
	}
	
	// Generate logic operation menu
	properties.change(function () {
		operation.empty();

		if ($(this.options[this.selectedIndex]).attr('rel') == "product") var conditions = product_conditions[$(this).val()];
		if ($(this.options[this.selectedIndex]).attr('rel') == "order") var conditions = order_conditions[$(this).val()];

		if (conditions['logic'].length > 0) {
			operation.show();
			for (var l in conditions['logic']) {
				var lop = conditions['logic'][l];
					for (var op in logic[lop])
						$('<option></option>').html(logic[lop][op]).val(logic[lop][op]).appendTo(operation);
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
	if (type == "Buy X Get Y Free") $('#beyget-row').show();
	
	$('#discount-amount').change(function () {
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
				$('<option></option>').html(label).val(label).attr('rel','order').appendTo($(menu));
		} else {
			for (var label in product_conditions)
				$('<option></option>').html(label).val(label).attr('rel','product').appendTo($(menu));
		}
	});

});

$('#starts-calendar').hide();
$('#starts-month').click(function (e) {
	$('#ends-calendar').hide();
	$('#starts-calendar').toggle();
	$(StartsCalendar).change(function () {
		$('#starts-month').val(StartsCalendar.selection.getMonth()+1);
		$('#starts-date').val(StartsCalendar.selection.getDate());
		$('#starts-year').val(StartsCalendar.selection.getFullYear());
	});
});

$('#ends-calendar').hide();
$('#ends-month').click(function (e) {
	$('#starts-calendar').hide();
	$('#ends-calendar').toggle();
	$(EndsCalendar).change(function () {
		$('#ends-month').val(EndsCalendar.selection.getMonth()+1);
		$('#ends-date').val(EndsCalendar.selection.getDate());
		$('#ends-year').val(EndsCalendar.selection.getFullYear());
	});
});

</script>