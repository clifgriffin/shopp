/**
 * editor.js
 * Product editor behaviors
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @subpackage product-editor
 **/

var Pricelines = false;
var productOptions = new Array();
var optionMenus = new Array();
var selectedMenuOption = false;
var detailsidx = 1;
var variationsidx = 1;
var optionsidx = 1;
var pricingidx = 1;
var fileUploader = false;
var changes = false;
var saving = false;
var flashUploader = false;
var pricesPayload = true;

jQuery(document).ready(function() {
	var $=jQuery.noConflict();

	postboxes.add_postbox_toggles('shopp_page_shopp-products');
	// close postboxes that should be closed
	jQuery('.if-js-closed').removeClass('if-js-closed').addClass('closed');
	
	if (!product) $('#title').focus();

	var editslug = new SlugEditor(product,'product');
	
	if (specs) $.each(specs,function () { addDetail(this) });
	$('#addDetail').click(function() { addDetail(); });
	
	Pricelines = new Pricelines();
	var basePrice = $(prices).get(0);
	if (basePrice && basePrice.context == "product") Pricelines.add(false,basePrice,'#product-pricing');
	else Pricelines.add(false,false,'#product-pricing');
	
	$('#variations-setting').click(variationsToggle);
	variationsToggle();
	loadVariations(options,prices);
	
	$('#addVariationMenu').click(function() { addVariationOptionsMenu(); });
	$('#linkOptionVariations').click(linkVariationsButton).change(linkVariationsButtonLabel);
	
	categories();
	tags();
	quickSelects();
	updateWorkflow();
	
	imageUploads = new ImageUploads($('#image-product-id').val(),'product');
	window.onbeforeunload = unsavedChanges;
	$('#product').change(function () { changes = true; }).submit(function() {
		this.action = this.action+"?"+$.param(request);
		saving = true;
		return true;
	});
	
});

function updateWorkflow () {
	var $=jQuery.noConflict();
	$('#workflow').change(function () {
		setting = $(this).val();
		request.page = workflow[setting];
		request.id = product;
		if (!request.id) request.id = "new";
		if (setting == "new") request.next = setting;
		
		// Find previous product
		if (setting == "previous") {
			$.each(worklist,function (i,entry) {
				if (entry.id == product) {
					if (worklist[i-1]) request.next = worklist[i-1].id;
					else request.page = workflow['close'];
					return true;
				}
			});
		}
		
		// Find next product
		if (setting == "next") {
			$.each(worklist,function (i,entry) {
				if (entry.id == product) {
					if (worklist[i+1]) request.next = worklist[i+1].id;
					else request.page = workflow['close'];
					return true;
				}
			});
		}
		
	}).change();
}

function categories () {
	var $=jQuery.noConflict();
	
	$('#new-category').hide();
	
	// Add New Category button handler
	$('#new-category-button').click(function () {
		$('#new-category').toggle();
		$('#new-category input').focus();
		$(this).toggle();
	});

	$('#add-new-category').click(function () {

		// Add a new category
		var name = $('#new-category input').val();
		var parent = $('#new-category select').val();
		if (name != "") {
			$('#new-category').hide();
			$('#new-category-button').show();

			$(this).addClass('updating');
			$.getJSON(addcategory_url+"&action=shopp_add_category&name="+name+"&parent="+parent,
				function(Category) {
				$('#add-new-category').removeClass('updating');
				addCategoryMenuItem(Category);

				// Update the parent category menu selector
				$.get(ajaxurl+'?action=shopp_category_menu',false,function (menu) {
					var defaultOption = $('#new-category select option').eq(0).clone();
					$('#new-category select').empty().html(menu);
					defaultOption.prependTo('#new-category select');
					$('#new-category select').attr('selectedIndex',0);
				},'html');

				// Reset the add new category inputs
				$('#new-category input').val('');
			});

		}
	});
	
	// Handles toggling a category on/off when the category is pre-existing
	$('#category-menu input.category-toggle').change(function () {
		if (!this.checked) return true;
		
		// Build current list of spec labels
		var details = new Array();
		$('#details-menu').children().children().find('input.label').each(function(id,item) {
			details.push($(item).val());
		});
		
		var id = $(this).attr('id').substr($(this).attr('id').indexOf("-")+1);
		// Load category spec templates
		$.getJSON(ajaxurl+'?action=shopp_spec_template&category='+id,function (speclist) {
			if (!speclist) return true;
			for (id in speclist) {
				speclist[id].add = true;
				if (details.toString().search(speclist[id]['name']) == -1) addDetail(speclist[id]);
			}
		});

		// Load category variation option templates
		$.getJSON(ajaxurl+'?action=shopp_options_template&category='+id,function (template) {
			if (!template) return true;
			if (!template.options) return true;
			
			if (!$('#variations-setting').attr('checked')) {
				$('#variations-setting').click();
				variationsToggle();
			}

			if (optionMenus.length > 0) {
				$.each(template.options,function (tid,tmenu) {
					if (menu = optionMenuExists(tmenu.name)) {
						var added = false;
						$.each(tmenu.options,function (i,option) {
							if (!optionMenuItemExists(menu,option.name)) {
								menu.addOption(option);
								added = true;
							}
						});
						if (added) addVariationPrices();
					} else {
						addVariationOptionsMenu(tmenu);
						addVariationPrices();
					}
					
				});
			} else loadVariations(template.options,template.prices);

		});
	});
		
	// Add to selection menu
	function  addCategoryMenuItem (c) {
		var $=jQuery.noConflict();
		
		var parent = false;
		var name = $('#new-category input').val();
		var parentid = $('#new-category select').val();

		// Determine where to add on the tree (trunk, branch, leaf)
		if (parentid > 0) {
			if ($('#category-element-'+parentid+' ~ li > ul').size() > 0)
				parent = $('#category-element-'+parentid+' ~ li > ul');
			else {
				var ulparent = $('#category-element-'+parentid);
				var liparent = $('<li></li>').insertAfter(ulparent);
				parent = $('<ul></ul>').appendTo(liparent);
			}
		} else parent = $('#category-menu > ul');

		// Figure out where to insert our item amongst siblings (leaves)
		var insertionPoint = false;
		parent.children().each(function() {
			var label = $(this).children('label').text();
			if (label && name < label) {
				insertionPoint = this;
				return false;
			}
		});

		// Add the category selector
		if (!insertionPoint) var li = $('<li id="category-element-'+c.id+'"></li>').appendTo(parent);
		else var li = $('<li id="category-element-'+c.id+'"></li>').insertBefore(insertionPoint);
		var checkbox = $('<input type="checkbox" name="categories[]" value="'+c.id+'" id="category-'+c.id+'" checked="checked" />').appendTo(li);
		var label = $('<label for="category-'+c.id+'"></label>').html(name).appendTo(li);
	}
	
}

function tags () {
	var $=jQuery.noConflict();
	
	function updateTagList () {
		$('#tagchecklist').empty();
		var tags = $('#tags').val().split(',');
		if (tags[0].length > 0) {
			$(tags).each(function (id,tag) {
				var entry = $('<span></span>').html(tag).appendTo('#tagchecklist');
				var deleteButton = $('<a></a>').html('X').addClass('ntdelbutton').prependTo(entry);
				deleteButton.click(function () {
					var tags = $('#tags').val();
					tags = tags.replace(new RegExp('(^'+tag+',?|,'+tag+'\\b)'),'');
					$('#tags').val(tags);
					updateTagList();
				});
			});
		}
	}
	
	$('#newtags').focus(function () {
		if ($(this).val() == $(this).attr('title')) 
			$(this).val('').toggleClass('form-input-tip');
	});
	
	$('#newtags').blur(function () {
		if ($(this).val() == '') 
			$(this).val($(this).attr('title')).toggleClass('form-input-tip');
	});
	
	$('#add-tags').click(function () {
		if ($('#newtags').val() == $('#newtags').attr('title')) return true;
		newtags = $('#newtags').val().split(',');
		
		$(newtags).each(function(id,tag) { 
			var tags = $('#tags').val();
			tag = $.trim(tag);
			if (tags == '') $('#tags').val(tag);
			else if (tags != tag && tags.indexOf(tag+',') == -1 && tags.indexOf(','+tag) == -1) 
				$('#tags').val(tags+','+tag);
		});
		updateTagList();
		$('#newtags').val('').blur();
	});
	
	updateTagList();
	
}
