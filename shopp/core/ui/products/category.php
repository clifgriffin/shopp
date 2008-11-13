<div class="wrap shopp">
	<h2><?php _e('Category Editor','Shopp'); ?></h2>
	<?php include("navigation.php"); ?>
	<br class="clear" />
	
	<form name="category" id="category" method="post" action="<?php echo admin_url("admin.php?page=".$this->Admin->products."&categories=list"); ?>">
		<?php wp_nonce_field('shopp-save-category'); ?>
		
		<table class="form-table"> 
			<tr class="form-required"> 
				<th scope="row" valign="top"><label for="category_name"><?php _e('Category Name','Shopp'); ?></label></th> 
				<td><input type="text" name="name" value="<?php echo $Category->name; ?>" id="category_name" size="40" /><br /> 
	            <?php _e('The name is used to identify the category in your catalog.','Shopp'); ?></td>
			</tr>
			<tr class="form-required"> 
				<th scope="row" valign="top"><label for="slug"><?php _e('Category Slug','Shopp'); ?></label></th> 
				<td><input type="text" name="slug" value="<?php echo $Category->slug; ?>" id="category_slug" size="40" /><br /> 
	            <?php _e('The name is used to identify the category in your catalog.','Shopp'); ?></td>
			</tr>
			<tr class="form-required"> 
				<th scope="row" valign="top"><label for="category_parent"><?php _e('Category Parent','Shopp'); ?></label></th> 
				<td><select name="parent" id="category_parent"><?php echo $categories_menu; ?></select><br /> 
	            <?php _e('Categories, unlike tags, can be or have nested sub-categories.','Shopp'); ?></td>
			</tr>
			<tr class="form-required"> 
				<th scope="row" valign="top"><label for="category_description"><?php _e('Description','Shopp'); ?></label></th> 
				<td><textarea name="description" id="category_description" rows="5" cols="50" style="width: 97%;"><?php echo $Category->description; ?></textarea><br /> 
	            <?php _e('The description is not prominent by default, however some themes may show it.','Shopp'); ?></td>
		</tr>
		<tr class="">
			<th><label><?php _e('Details Template','Shopp'); ?></label>
				<div id="new-detail">
				<button type="button" id="addDetail" class="button-secondary"><small><?php _e('Add Detail','Shopp'); ?></small></button>
				</div>
			</th>
			<td>
				<ul class="details multipane">
					<li><input type="hidden" name="deletedSpecs" id="deletedSpecs" value="" />
						<div id="details-menu" class="multiple-select options"><ul></ul></div></li>
				</ul>
				<div class="clear"></div>
				<?php _e('Create a predefined set of details for products in this category.','Shopp'); ?>
			</td>
		</tr>
		<tr>
			<th><?php _e('Option Menu Templates','Shopp'); ?></th>
			<td>
				<?php _e('Create a predefined set of variation options for products in this category.','Shopp'); ?><br />
				<ul class="multipane">
					<li><div id="variations-menu" class="multiple-select options menu"><ul></ul></div>
					<div class="controls">
						<button type="button" id="addVariationMenu" class="button-secondary"><img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/add.png" alt="-" width="16" height="16" /><small> <?php _e('Add Option Menu','Shopp'); ?></small></button>
					</div>
				</li>
				
				<li>
					<div id="variations-list" class="multiple-select options"></div><br />
					<div class="controls right">
					<button type="button" id="addVariationOption" class="button-secondary"><img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/add.png" alt="-" width="16" height="16" /><small> <?php _e('Add Option','Shopp'); ?></small></button>
					</div>
				</li>
				</ul>
				
			</td>
		</tr>		</table>
		<p class="submit"><input type="submit" class="button" name="save" value="<?php _e('Save Changes','Shopp'); ?>" /></p>
	</form>
</div>

<script type="text/javascript">

var details = <?php echo json_encode($Category->specs) ?>;
var options = <?php echo json_encode($Category->options) ?>;
var categoryOptions = new Array();
var optionSets = new Array();
var optionsidx = 1;
var detailsidx = 1;
var menusidx = 1;
var rsrcdir = '<?php echo SHOPP_PLUGINURI; ?>';

$=jQuery.noConflict();


$(window).ready(function () {
	if (details) for (s in details) addDetail(details[s]);
	$('#addDetail').click(function() { addDetail(); });	
	$('#addVariationMenu').click(function() { addVariationOptionsMenu(); });

	if (options && options.variations) {
		for (key in options.variations) {
			addVariationOptionsMenu(options.variations[key]);	
		}
	}
	
});

function addDetail (data) {
	var menu = $('#details-menu');
	var i = detailsidx;
	
	var e = $('<li>').appendTo($(menu).children('ul'));
	var moveHandle = $('<div class="move"></div>').appendTo(e);
	var detailsorder = $('<input type="hidden" name="specOrder[]" value="'+i+'" />').appendTo(e);
	var specId = $('<input type="hidden" name="specs['+i+'][id]" />').appendTo(e);
	var label = $('<input type="text" name="specs['+i+'][name]" />').appendTo(e);
	var deleteButton = $('<button type="button" class="delete"><img src="'+rsrcdir+'/core/ui/icons/delete.png" alt="delete" width="16" height="16" /></button>').appendTo(e);

	e.click(function () {
		var details = $(menu).children().children();
		$(details).removeClass('selected');
		e.addClass('selected');
	});
	
	e.hover(function () { e.addClass('hover'); },
			function () { e.removeClass('hover'); });

	label.mouseup(function (e) { this.select(); });

	deleteButton.click(function () { e.remove(); });
	
	if (data) {
		specId.val(data.id);
		label.val(data.name);		
	} else {
		specId.val(detailsidx);
		label.val("Detail Name "+i);
	}

	detailsidx++;
	e.parent().sortable({'axis':'y'});
	
}

function addVariationOptionsMenu (data) {
	addOptionMenu(
		'variation',			// Type of option
		'#variations-menu', 	// Menus container element
		'#variations-list',		// Option lists container element
		'#addVariationOption',	// Add option button element
		'variations',			// Fieldname
		data					// Data
	);
}
function addOptionMenu (type,menu,lists,addoption,fieldname,data) {
	var i = $(menu+" > ul li").length;

	var e = $('<li>').appendTo($(menu).children('ul'));
	var moveHandle = $('<div class="move"></div>').appendTo(e);
	var menuId = $('<input type="hidden" name="options['+fieldname+']['+i+'][menuid]" id="menuid-'+i+'" value="'+menusidx+'" class="id" />').appendTo(e);
	var label = $('<input type="text" name="options['+fieldname+']['+i+'][menu]" id="'+fieldname+'-menu-'+i+'" />').appendTo(e);
	var deleteButton = $('<button type="button" class="delete"><img src="'+rsrcdir+'/core/ui/icons/delete.png" alt="delete" width="16" height="16" /></button>').appendTo(e);

	var options = $('<ul></ul>').appendTo(lists).hide();
	optionSets[menusidx++] = options;
	
	e.addOption = function (evt,id,label) {
		var j = $(options).contents().length;
		
		if (!id) id = optionsidx;
		else if (id > optionsidx) optionsidx = id;

		var option = $('<li></li>').appendTo(options);
		var optionMove = $('<div class="move"></div>').appendTo(option);
		var optionId = $('<input type="hidden" name="options['+fieldname+']['+i+'][id][]" value="'+id+'" class="id" />').appendTo(option);
		var optionLabel = $('<input type="text" name="options['+fieldname+']['+i+'][label][]" />').appendTo(option);
		var optionDelete = $('<button type="button" class="delete"><img src="'+rsrcdir+'/core/ui/icons/delete.png" alt="delete" width="16" height="16" /></button>').appendTo(option);
		categoryOptions[id] = optionLabel;
		optionsidx++;
		
		option.hover(function () {
			option.addClass('hover');
		},function () {
			option.removeClass('hover');
		});
		
		if (label) optionLabel.val(label);
		else optionLabel.val('New Option '+(j+1));
		
		optionLabel.click(function () { this.select(); });
		if (type == "variation") {

			optionDelete.click(function () {
				if (options.children().length == 1) {
					options.remove();
					e.remove();
				}
				option.remove();
			});

			options.sortable({'axis':'y'});
		}		
	}
	
	e.click(function () {
		var opt = options;
		$(menu+" > ul li").each(function(id,entry) {
			$(entry).removeClass('selected');
		});
		e.addClass('selected');
		$(addoption).unbind('click',e.addoption);
		$(addoption).click(e.addOption);
		
		$(lists).children('ul').each(function (id,optionMenu) {
			$(optionMenu).hide();
		});
		
		options.show();
	});

	e.hover(function () { e.addClass('hover'); },
			function () { e.removeClass('hover'); });

	label.mouseup(function (e) {
		this.select();
	});

	deleteButton.click(function () {
		var deletedOptions = new Array();
		$(options).children('li').not('.ui-sortable-helper').children("input.id").each(function (i,id) {
			deletedOptions.push($(id).val());
		});
		options.remove();
		e.remove();
	
	});

	if (data) {
		label.val(data.menu);
		if (data.id) {
			$(data.id).each(function (key,entry) {
				e.addOption(null,data.id[key],data.label[key]);
			});
		}
	} else {
		label.val('New Option Menu '+(i+1));
		e.addOption();
		e.addOption();
	}
	e.parent().sortable({'axis':'y'});

}


</script>