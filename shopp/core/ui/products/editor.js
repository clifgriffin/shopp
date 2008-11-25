/**
 * editor.js
 * Product editor behaviors
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

$=jQuery.noConflict();

var productOptions = new Array();
var optionMenus = new Array();
var pricingOptions = new Object();
var detailsidx = 1;
var variationsidx = 1;
var optionsidx = 1;
var pricingidx = 1;
var fileUploader = false;
var changes = false;
var saving = false;
var flashUploader = false;
var pricesPayload = true;

function init () {
	window.onbeforeunload = function () { if (changes && !saving) return false; }	
	$('#product').change(function () { changes = true; });
	$('#product').submit(function() { saving = true; });

	if (specs) for (s in specs) addDetail(specs[s]);
	$('#addDetail').click(function() { addDetail(); });

	var basePrice = $(prices).get(0);
	if (basePrice && basePrice.context == "product") addPriceLine('#product-pricing',[],basePrice);
	else addPriceLine('#product-pricing',[]);

	$('#variations-setting').click(variationsToggle);
	variationsToggle();
	loadVariations(options);
	
	$('#addVariationMenu').click(function() { addVariationOptionsMenu(); });
		
	categories();
	tags();
	quickSelects();
		
	imageUploads = new ImageUploads();
	fileUploader = new FileUploads();	
}

function categories () {
	$('#new-category input, #new-category select').hide();
	
	// Add New Category button handler
	$('#add-new-category').click(function () {
		
		$('#new-category input, #new-category select').toggle();
		$('#new-category input').focus();

		// Add a new category
		var name = $('#new-category input').val();
		var parent = $('#new-category select').val();
		if (name != "") {
			$(this).addClass('updating');
			url = window.location.href.substr(0,window.location.href.indexOf('?'));
			$.getJSON(url+"/wp-admin/admin-ajax.php?action=wp_ajax_shopp_add_category&name="+name+"&parent="+parent,function(Category) {
				$('#add-new-category').removeClass('updating');
				addCategoryMenuItem(Category);
				addCategoryParentMenuOption(Category);

				// Reset the add new category inputs
				$('#new-category input').val('');
				$('#new-category select').each(function() { this.selectedIndex = 0; });
			});

		}
	});
	
	$('#category-menu input.category-toggle').change(function () {
		if (!this.checked) return true;
		
		// Build current list of spec labels
		var details = new Array();
		$('#details-menu').children().children().find('input.label').each(function(id,item) {
			details.push($(item).val());
		});
		
		var id = $(this).attr('id').substr($(this).attr('id').indexOf("-")+1);
		// Load category spec templates
		$.getJSON(siteurl+"/wp-admin/admin.php?lookup=spectemplate&cat="+id,function (speclist) {
			if (!speclist) return true;
			for (id in speclist) {
				if (details.toString().search(speclist[id]['name']) == -1) addDetail(speclist[id]);
			}
		});

		// Load category variation option templates
		$.getJSON(siteurl+"/wp-admin/admin.php?lookup=optionstemplate&cat="+id,function (options) {
			if (!options) return true;
			
			if (!$('#variations-setting').attr('checked')) {
				$('#variations-setting').click();
				variationsToggle();
			}
			
			var menus = $('#variations-menu input[type=text]');
			var templates = new Object();
			templates.variations = new Array();
			for (i in options.variations) {
				var exists = false;
				menus.each(function (id,label) {
					if (options.variations[i].menu == $(label).val()) exists = true;
				});
				if (!exists) {
					options.variations[i].id = new Array();
					templates.variations.push(options.variations[i]);
				}
			}
			loadVariations(templates);
		});

	});
	
	// Add to selection menu
	function  addCategoryMenuItem (c) {
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


	// Add this to new category drop-down menu
	function addCategoryParentMenuOption (c) {
		var name = $('#new-category input').val();
		var parent = $('#new-category select').val();

		parent = $('#new-category select');
		parentRel = $('#new-category select option:selected').attr('rel').split(',');
		children = new Array();
		insertionPoint = false;

		$('#new-category select').each(function() { 
			selected = this.selectedIndex;
			var hasChildren = false;
			for (var i = selected+1; i < this.options.length; i++) {
				var rel = $(this.options[i]).attr('rel').split(',');
				if (new Number(parentRel[1])+1 == rel[1] && !hasChildren) hasChildren = true;
				if (hasChildren && new Number(parentRel[1])+1 != rel[1]) hasChildren = false;
				if (hasChildren) children.push(this.options[i]);
			}
			if (selected == 0) children = this.options;
			if (selected > 0 && children.length == 0) insertionPoint = $(this.options[selected+1]);

		});

		$(children).each(function () {
			if (name < $(this).text() && $(this).val() != "0") {
				insertionPoint = this;
				return false;
			}
		});

		// Pad the label
		var label = name;
		for (i = 0; i < (new Number(parentRel[1])+1); i++) label = "&nbsp;&nbsp;&nbsp;"+label;			

		// Add our option
		if (!insertionPoint) var option = $('<option value="'+c.id+'" rel="'+parentRel[0]+','+(new Number(parentRel[1])+1)+'"></option>').html(label).appendTo(parent);
		else var option = $('<option value="'+c.id+'" rel="'+parentRel[0]+','+(new Number(parentRel[1])+1)+'"></option>').html(label).insertBefore(insertionPoint);
	}
	
}

function tags () {
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

