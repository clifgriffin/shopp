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
var optionSets = new Array();
var pricingOptions = new Object();
var detailsidx = 1;
var menusidx = 1;
var optionsidx = 1;
var pricingidx = 1;
var uploader = false;
var changes = false;

function init () {
	window.onbeforeunload = function () { if (changes) return false; }	
	$('#product').change(function () { changes = true; });

	var basePrice = $(prices).get(0);
	if (basePrice && basePrice.context == "product") addPriceLine('#product-pricing',[],basePrice);
	else addPriceLine('#product-pricing',[]);

	if (specs) for (s in specs) addDetail(specs[s]);
	$('#addDetail').click(function() { addDetail(); });

	$('#variations-setting').click(variationsToggle);
	variationsToggle();
	loadVariations(options);
	
	$('#addVariationMenu').click(function() { addVariationOptionsMenu(); });
		
	categories();
	tags();
	quickSelects();
	
	// Initialize image uploader
	var swfu = new SWFUpload({
		flash_url : siteurl+'/wp-includes/js/swfupload/swfupload_f9.swf',
		upload_url: siteurl+'/wp-admin/admin-ajax.php?action=wp_ajax_shopp_add_image',
		post_params: {"product" : $('#image-product-id').val()},
		file_queue_limit : 1,
		file_size_limit : filesizeLimit+'b',
		file_types : "*.jpg;*.jpeg;*.png;*.gif",
		file_types_description : "Web-compatible Image Files",
		file_upload_limit : filesizeLimit,
		custom_settings : {
			targetHolder : false,
			progressBar : false,
			sorting : false
		},
		debug: false,

		swfupload_element_id : "swf-uploader",
		degraded_element_id : "browser-uploader",

		file_queued_handler : imageFileQueued,
		file_queue_error_handler : imageFileQueueError,
		file_dialog_complete_handler : imageFileDialogComplete,
		upload_start_handler : startImageUpload,
		upload_progress_handler : imageUploadProgress,
		upload_error_handler : imageUploadError,
		upload_success_handler : imageUploadSuccess,
		upload_complete_handler : imageUploadComplete,
		queue_complete_handler : imageQueueComplete
	});

	$("#add-product-image").click(function(){ swfu.selectFiles(); });

	if ($('#lightbox li').size() > 0) $('#lightbox').sortable({'opacity':0.8});
	$('#product-images ul li button.deleteButton').each(function () {
		enableDeleteButton(this);
	});

	// Initialize file uploader
	uploader = new SWFUpload({
		flash_url : siteurl+'/wp-includes/js/swfupload/swfupload_f9.swf',
		upload_url: siteurl+'/wp-admin/admin-ajax.php?action=wp_ajax_shopp_add_download',
		file_queue_limit : 1,
		file_size_limit : filesizeLimit+'b',
		file_types : "*.*",
		file_types_description : "All Files",
		file_upload_limit : filesizeLimit,
		custom_settings : {
			targetCell : false,
			targetLine : false,
			progressBar : false,
		},
		debug: false,

		file_queued_handler : fileQueued,
		file_queue_error_handler : fileQueueError,
		file_dialog_complete_handler : fileDialogComplete,
		upload_start_handler : startUpload,
		upload_progress_handler : uploadProgress,
		upload_error_handler : uploadError,
		upload_success_handler : uploadSuccess,
		upload_complete_handler : uploadComplete,
		queue_complete_handler : queueComplete
	});
	
}

function categories () {
	$('#new-category input, #new-category select').hide();
	
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
		if ($(this).val() == $(this).attr('title')) {
			$(this).val('');
			$(this).toggleClass('form-input-tip');
		}
			
	});
	
	$('#newtags').blur(function () {
		if ($(this).val() == '') {
			$(this).val($(this).attr('title'));
			$(this).toggleClass('form-input-tip');
		}
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

function addDetail (data) {
	var menu = $('#details-menu');
	var entries = $('#details-list');
	var i = detailsidx;
	
	var e = $('<li>').appendTo($(menu).children('ul'));
	var moveHandle = $('<div class="move"></div>').appendTo(e);
	var detailsorder = $('<input type="hidden" name="detailsorder[]" value="'+i+'" />').appendTo(e);
	var specId = $('<input type="hidden" name="details['+i+'][id]" />').appendTo(e);
	var label = $('<input type="text" name="details['+i+'][name]" class="label" tabindex="9" />').appendTo(e);
	var deleteButton = $('<button type="button" class="delete"><img src="'+rsrcdir+'/core/ui/icons/delete.png" alt="delete" width="16" height="16" /></button>').appendTo(e);

	var detailEntry = $('<li></li>').appendTo(entries).hide();
	var content = $('<textarea name="details['+i+'][content]" cols="40" rows="7" tabindex="9"></textarea>').appendTo(detailEntry);

	e.click(function () {
		var details = $(menu).children().children();
		$(details).removeClass('selected');
		e.addClass('selected');
		$(entries).children().hide();
		detailEntry.show();
	});
	
	e.hover(function () { e.addClass('hover'); },
			function () { e.removeClass('hover'); });

	label.mouseup(function (e) {
		this.select();
	});
	label.contents = content;
	label.focus(function () {
		$(this).keydown(function (e) {
			e.stopPropagation();
			if (e.keyCode == 13) {
				$(this).blur();
				$(this).unbind('keydown');
			}
		});
	});

	deleteButton.click(function () {
		var deletes = $('#deletedSpecs');
		if (specId.val() != "") { // Only need db delete if an id exists
			if (deletes.val() == "") deletes.val(specId.val());
			else deletes.val(deletes.val()+","+specId.val());
		}
		detailEntry.remove();
		e.remove();
	});
	
	if (data) {
		specId.val(data.id);
		label.val(htmlentities(data.name));
		content.val(htmlentities(data.content));
	} else {
		label.val("Detail Name "+i);		
	}
	detailsidx++;
	e.parent().sortable({'axis':'y'});
	
}


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

function loadVariations (options) {
	if (options && options.variations) {
		for (key in options.variations) {
			addVariationOptionsMenu(options.variations[key]);	
		}

		$(prices).each(function(index,price) {
			if (price.context == "variation") addPriceLine('#variations-pricing',price.options.split(","),price);
		});
		addVariationPrices();
	}
}

function addVariationOptionsMenu (data) {
	addOptionMenu(
		'variation',			// Type of option
		'#variations-menu', 	// Menus container element
		'#variations-list',		// Option lists container element
		'#addVariationOption',	// Add option button element
		'#variations-pricing',	// Pricing container
		'variations',			// Fieldname
		data					// Data
	);
}

function addOptionMenu (type,menu,lists,addoption,pricing,fieldname,data) {
	var i = $(menu+" > ul li").length;

	var e = $('<li>').appendTo($(menu).children('ul'));
	var moveHandle = $('<div class="move"></div>').appendTo(e);
	var menuId = $('<input type="hidden" name="options['+fieldname+']['+i+'][menuid]" id="menuid-'+i+'" value="'+menusidx+'" class="id" />').appendTo(e);
	var label = $('<input type="text" name="options['+fieldname+']['+i+'][menu]" id="'+fieldname+'-menu-'+i+'" tabindex="13" />').appendTo(e);
	var deleteButton = $('<button type="button" class="delete"><img src="'+rsrcdir+'/core/ui/icons/delete.png" alt="delete" width="16" height="16" /></button>').appendTo(e);

	var options = $('<ul></ul>').appendTo(lists).hide();
	optionSets[menusidx++] = options;
	
	e.addOption = function (evt,id,label) {
		var j = $(options).contents().length;
		var init = false;
		
		if (!id) {
			init = true;
 			id = optionsidx;
		} else if (id > optionsidx) optionsidx = id;

		var option = $('<li></li>').appendTo(options);
		var optionMove = $('<div class="move"></div>').appendTo(option);
		var optionId = $('<input type="hidden" name="options['+fieldname+']['+i+'][id][]" value="'+id+'" class="id" />').appendTo(option);
		var optionLabel = $('<input type="text" name="options['+fieldname+']['+i+'][label][]" tabindex="13" />').appendTo(option);
		var optionDelete = $('<button type="button" class="delete"><img src="'+rsrcdir+'/core/ui/icons/delete.png" alt="delete" width="16" height="16" /></button>').appendTo(option);
		productOptions[id] = optionLabel;
		optionsidx++;
		
		option.hover(function () {
			option.addClass('hover');
		},function () {
			option.removeClass('hover');
		});
		
		if (label) optionLabel.val(htmlentities(label));
		else optionLabel.val('New Option '+(j+1));
		
		optionLabel.click(function () { this.select(); });
		optionLabel.focus(function () {
			$(this).keydown(function (e) {
				e.stopPropagation();
				if (e.keyCode == 13) {
					$(this).blur();
					$(this).unbind('keydown');
				}
			});
		});
		if (type == "variation") {

			optionLabel.blur(function () { updateVariationLabels(); });

			optionDelete.click(function () {
				if (options.children().length == 1) {
					deleteVariationPrices([optionId.val()],true);
					options.remove();
					e.remove();
				} else deleteVariationPrices([optionId.val()]);
				option.remove();
			});

			options.sortable({'axis':'y','update':function(){orderVariationPrices()}});
			
			if (!init) addVariationPrices(optionId.val());
			else addVariationPrices();
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
	label.focus(function () {
		$(this).keydown(function (e) {
			e.stopPropagation();
			if (e.keyCode == 13) {
				$(this).blur();
				$(this).unbind('keydown');
			}
		});
	});

	deleteButton.click(function () {
		var deletedOptions = new Array();
		$(options).children('li').not('.ui-sortable-helper').children("input.id").each(function (i,id) {
			deletedOptions.push($(id).val());
		});
		if (type == "variation") deleteVariationPrices(deletedOptions,true);
		options.remove();
		e.remove();
	
	});

	if (data) {
		label.val(htmlentities(data.menu));
		if (data.id.length > 0) {
			$(data.id).each(function (key,entry) {
				e.addOption(null,data.id[key],data.label[key]);
			});
		} else if (data.label.length > 0) {
			$(data.label).each(function (key,entry) {
				e.addOption(null,data.id[key],data.label[key]);
			});
		}

	} else {
		label.val('New Option Menu '+(i+1));
		e.addOption();
		e.addOption();
	}
	e.parent().sortable({'axis':'y','update':function(){orderOptions(menu,lists)}});

}

/**
 * buildVariations()
 * Creates an array of all possible combinations of the product variation options */
function buildVariations () {
	var combos = new Array();							// Final list of possible variations
	var optionSets = $('#variations-list ul');			// Reference to the DOM-stored option set
	var totalSets = optionSets.length;					// Total number of sets
	var lastOptionSet = totalSets-1;					// Reference to the index of the last set
	var address = new Array(optionSets.length);			// Helper to reference a specific option in a specific set
	var totalOptions = new Array(optionSets.length);	// Reference list of total options of a set
	var totalVariations = 0;							// The total variations possible
	
	// Identify total options in each set and calculate total permutations
	optionSets.each(function (id,set) {
		totalOptions[id] = $(set).children().length;	// Save the total options for this set
		
		// Calculate the total possibilities (options * options * options...)
		if (totalVariations == 0) totalVariations = $(set).children().length;
		else totalVariations = totalVariations * $(set).children().length;
		address[id] = 0;								// initialize our address helper list
	});

	// Build variation labels for each possible permutation
	for (var i = 0; i < totalVariations; i++) {
		
		// Grab the label for the currently addressed option in each set
		// and add it to this variation permutation
		for (var setid = 0; setid < optionSets.length; setid++) {
			var fields = $(optionSets[setid]).children("li").not('.ui-sortable-helper').children("input.id");
			if (!combos[i]) combos[i] = [$(fields[address[setid]]).val()];
			else combos[i].push($(fields[address[setid]]).val());
		}
		
		// Figure out what is the next combination by trying to increment
		// the last option set address. If the last address exceeds the total options, 
		// reset it to 0 and increment the previous option set address 
		// (and if that exceeds its total, reset and so on)
		if (++address[lastOptionSet] >= totalOptions[lastOptionSet]) {
			for (var index = lastOptionSet; index > -1; index--) {
				if (address[index] < totalOptions[index]) continue;
				address[index] = 0;
				if (index-1 > -1) address[(index-1)]++;
			}
		}
		
	}

	return combos;
}

function addVariationPrices (data) {
	if (!data) {
		var updated = buildVariations();
		var variationPricing = $('#variations-pricing');
		var pricelines = $(variationPricing).children();

		$(updated).each(function(id,options) {
			var key = xorkey(options);
			var preKey = xorkey(options.slice(0,options.length-1));
			if (preKey == "") preKey = -1;

			if (!pricingOptions[key]) {
				if (pricingOptions[preKey]) {
					pricingOptions[key] = pricingOptions[preKey];
					delete pricingOptions[preKey];
					pricingOptions[key].options = options;
					pricingOptions[key].updateKey();
					pricingOptions[key].updateLabel();
				} else {
					if (pricelines.length == 0) {
						addPriceLine('#variations-pricing',options,{context:'variation'});
					} else {
						addPriceLine(pricingOptions[ xorkey(updated[(id-1)]) ].row,options,{context:'variation'},'after');
					}
				}
			}
		});		
	}
}

function deleteVariationPrices (optionids,reduce) {
	var updated = buildVariations();
		
	$(updated).each(function(id,options) {
		var key = xorkey(options);

		for (var i = 0; i < optionids.length; i++)  {
			if (options.indexOf(optionids[i]) != -1) {

				var modOptions = new Array();
				$(options).each(function(index,option) {
					if (option != optionids[i]) modOptions.push(option);
				});
				var newkey = xorkey(modOptions);
			
				if (reduce && !pricingOptions[newkey]) {
					pricingOptions[newkey] = pricingOptions[key];
					delete pricingOptions[key];
					pricingOptions[newkey].options = modOptions;
					pricingOptions[newkey].updateLabel();
					pricingOptions[newkey].updateKey();
				} else {
					if (pricingOptions[key]) {
						
						// Mark priceline for removal from db
						var dbPriceId = $('#id\\['+pricingOptions[key].id+'\\]').val();
						if ($('#deletePrices').val() == "") $('#deletePrices').val(dbPriceId);
						else $('#deletePrices').val($('#deletePrices').val()+","+dbPriceId);

						// Remove the priceline row from the ui/dom
						pricingOptions[key].row.remove();
						delete pricingOptions[key];
					}
				}
			
			}
		}

	});

}


function updateVariationLabels () {
	var updated = buildVariations();
	$(updated).each(function(id,options) {
		var key = xorkey(options);
		if (pricingOptions[key]) pricingOptions[key].updateLabel();
	});
}

function orderOptions (menus,lists) {
	var menus = $(menus+" ul li").not('.ui-sortable-helper').children('input.id');
	$(menus).each(function (id,menu) {
		optionSets[$(menu).val()].appendTo(lists);
	});
	if (lists == "#variations-list") orderVariationPrices();
}

function orderVariationPrices () {
	var updated = buildVariations();
	
	$(updated).each(function (id,options) {
		var key = xorkey(options);
		if (key > 0 && pricingOptions[key]) {
			pricingOptions[key].row.appendTo('#variations-pricing');
			pricingOptions[key].options = options;
			pricingOptions[key].updateLabel();
		}
			
	});
	
}

function addPriceLine (target,options,data,attachment) {
	
	// Give this entry a unique runtime id
	var i = pricingidx;
	
	// Build the interface
	var row = $('<tr id="row['+i+']"></tr>').addClass('form-field');
	if (attachment == "after") row.insertAfter(target);
	else if (attachment == "before") row.insertBefore(target);
	else row.appendTo(target);

	var heading = $('<th class="pricing-label"></th>').appendTo(row);
	var labelText = $('<label for="label['+i+']"></label>').appendTo(heading);
		
	var label = $('<input type="hidden" name="price['+i+'][label]" id="label['+i+']" />').appendTo(heading);
	label.change(function () { labelText.text($(this).val()); });
	
	var myid = $('<input type="hidden" name="price['+i+'][id]" id="id['+i+']" />').appendTo(heading);
	var productid = $('<input type="hidden" name="price['+i+'][product]" id="product['+i+']" />').appendTo(heading);
	var context = $('<input type="hidden" name="price['+i+'][context]" />').appendTo(heading);
	var optionkey = $('<input type="hidden" name="price['+i+'][optionkey]" />').appendTo(heading);
	var optionids = $('<input type="hidden" name="price['+i+'][options]" />').appendTo(heading);
	var sortorder = $('<input type="hidden" name="sortorder[]" value="'+i+'" />').appendTo(heading);

	var dataCell = $('<td/>').appendTo(row);
	
	var pricingTable = $('<table/>').addClass('pricing-table').appendTo(dataCell);

	var headingsRow = $('<tr/>').appendTo(pricingTable);	
	var inputsRow = $('<tr/>').appendTo(pricingTable);

	var typeHeading = $('<th><label for="">Type</label></th>').appendTo(headingsRow);
	var typeCell = $('<td></td>').appendTo(inputsRow);
	var type = $('<select name="price['+i+'][type]" id="type['+i+']" tabindex="'+(i+1)+'02"></select>').appendTo(typeCell);
	$(priceTypes).each(function (t,name) {
		var typeOption = $('<option>'+name+'</option>').appendTo(type);
	});
	
	var priceHeading = $('<th></th>').appendTo(headingsRow);
	var priceLabel = $('<label for="price['+i+']">Price</label>').appendTo(priceHeading);
	var priceCell = $('<td/>').appendTo(inputsRow);
	var price = $('<input type="text" name="price['+i+'][price]" id="price['+i+']" value="0" size="10" class="selectall right" tabindex="'+(i+1)+'03" />').appendTo(priceCell);
	$('<br />').appendTo(priceCell);
	$('<input type="hidden" name="price['+i+'][tax]" tabindex="'+(i+1)+'04" value="on" />').appendTo(priceCell);
	var tax = $('<input type="checkbox" name="price['+i+'][tax]" id="tax['+i+']" tabindex="'+(i+1)+'04" value="off" />').appendTo(priceCell);
	var taxLabel = $('<label for="tax['+i+']"> Not Taxable</label><br />').appendTo(priceCell);

	var salepriceHeading = $('<th><label for="sale['+i+']"> Sale Price</label></th>').appendTo(headingsRow);
	var salepriceToggle = $('<input type="checkbox" name="price['+i+'][sale]" id="sale['+i+']" tabindex="'+(i+1)+'05" />').prependTo(salepriceHeading);
	$('<input type="hidden" name="price['+i+'][sale]" value="off" />').prependTo(salepriceHeading);
	
	var salepriceCell = $('<td/>').appendTo(inputsRow);
	var salepriceStatus = $('<span id="test['+i+']">Not on Sale</span>').addClass('status').appendTo(salepriceCell);
	var salepriceField = $('<span/>').addClass('fields').appendTo(salepriceCell).hide();
	var saleprice = $('<input type="text" name="price['+i+'][saleprice]" id="saleprice['+i+']" size="10" class="selectall right" tabindex="'+(i+1)+'06" />').appendTo(salepriceField);
	
	var donationSpacingCell = $('<td rowspan="2" width="58%" />').appendTo(headingsRow);
	
	var shippingHeading = $('<th><label for="shipping-'+i+'"> Shipping</label></th>').appendTo(headingsRow);
	var shippingToggle = $('<input type="checkbox" name="price['+i+'][shipping]" id="shipping-'+i+'" tabindex="'+(i+1)+'07" />').prependTo(shippingHeading);
	$('<input type="hidden" name="price['+i+'][shipping]" value="off" />').prependTo(shippingHeading);
	
	var shippingCell = $('<td/>').appendTo(inputsRow);
	var shippingStatus = $('<span>Free Shipping</span>').addClass('status').appendTo(shippingCell);
	var shippingFields = $('<span/>').addClass('fields').appendTo(shippingCell).hide();
	var weight = $('<input type="text" name="price['+i+'][weight]" id="weight['+i+']" size="8" class="selectall right" tabindex="'+(i+1)+'08" />').appendTo(shippingFields);
	var shippingWeightLabel = $('<label for="weight['+i+']" title="Weight"> Weight'+((weightUnit)?' ('+weightUnit+')':'')+'</label><br />').appendTo(shippingFields);
	var shippingfee = $('<input type="text" name="price['+i+'][shipfee]" id="shipfee['+i+']" size="8" class="selectall right" tabindex="'+(i+1)+'08" />').appendTo(shippingFields);
	var shippingFeeLabel = $('<label for="shipfee['+i+']" title="Additional shipping fee calculated per quantity ordered (for handling costs, etc)"> Handling Fee</label><br />').appendTo(shippingFields);
	
	var inventoryHeading = $('<th><label for="inventory['+i+']"> Inventory</label></th>').appendTo(headingsRow);
	var inventoryToggle = $('<input type="checkbox" name="price['+i+'][inventory]" id="inventory['+i+']" tabindex="'+(i+1)+'10" />').prependTo(inventoryHeading);
	var inventoryCell = $('<td/>').appendTo(inputsRow);
	var inventoryStatus = $('<span>Not Tracked</span>').addClass('status').appendTo(inventoryCell);
	var inventoryField = $('<span/>').addClass('fields').appendTo(inventoryCell).hide();
	var stock = $('<input type="text" name="price['+i+'][stock]" id="stock['+i+']" size="8" class="selectall right" tabindex="'+(i+1)+'11" />').appendTo(inventoryField);
	var inventoryLabel =$('<label for="stock['+i+']"> In Stock</label>').appendTo(inventoryField);
	var inventoryBr = $('<br/>').appendTo(inventoryField);
	var sku = $('<input type="text" name="price['+i+'][sku]" id="sku['+i+']" size="8" title="Enter a unique tracking number for this product option." class="selectall" tabindex="'+(i+1)+'12" />').appendTo(inventoryField);
	var skuLabel =$('<label for="sku['+i+']" title="Stock Keeping Unit"> SKU</label>').appendTo(inventoryField);
		
	var downloadHeading = $('<th><label for="download['+i+']">Product Download</label></th>').appendTo(headingsRow);
	var downloadCell = $('<td width="31%" />').appendTo(inputsRow);
	var downloadFile = $('<div></div>').html('No product download.').appendTo(downloadCell);

	var uploadHeading = $('<td rowspan="2" class="controls" width="75" />').appendTo(headingsRow);
	var uploadButton = $('<button type="button" class="button-secondary" tabindex="'+(i+1)+'13"><small>Upload File</small></button>').appendTo(uploadHeading);
	uploadButton.click(function () { uploader.targetCell = downloadFile; uploader.targetLine = i; uploader.selectFiles(); });
	
	// Build an object to reference and control/update this entry
	var Pricing = new Object();
	Pricing.id = pricingidx;
	Pricing.options = options;
	Pricing.data = data;
	Pricing.row = row;
	Pricing.label = label;
	Pricing.disable = function () { type.val('N/A').change(); }
	Pricing.updateKey = function () { optionkey.val(xorkey(this.options)); }
	Pricing.updateLabel = function () {
		var string = "";
		var ids = "";
		if (this.options) {
			string = "";
			$(this.options).each(function(index,id) {
				if (string == "") string = $(productOptions[id]).val();
				else string += ", "+$(productOptions[id]).val();
				if (ids == "") ids = id;
				else ids += ","+id;
			});
		}
		if (string == "") string = "Price & Delivery";
		this.label.val(htmlentities(string)).change();
		optionids.val(ids);
	}
	Pricing.updateKey();
	Pricing.updateLabel();
	
	// Utility function to hide all of the optional fields
	var hideAllFields = function () {
		priceHeading.hide();
		priceCell.hide()
		salepriceHeading.hide();
		salepriceCell.hide();
		shippingHeading.hide();
		shippingCell.hide();
		inventoryHeading.hide();
		inventoryCell.hide();
		downloadHeading.hide();
		downloadCell.hide();
		uploadHeading.hide();
		donationSpacingCell.hide();
		priceLabel.html("Price");
	}
	
	// Alter the interface depending on the type of price line
	type.change(function () {
		hideAllFields();
		if (type.val() == "Shipped") {
			priceHeading.show();
			priceCell.show();
			salepriceHeading.show();
			salepriceCell.show();
			shippingHeading.show();
			shippingCell.show();
			inventoryHeading.show();
			inventoryCell.show();
		}
		if (type.val() == "Download") {
			priceHeading.show();
			priceCell.show();
			salepriceHeading.show();
			salepriceCell.show();
			downloadHeading.show();
			downloadCell.show();
			uploadHeading.show();
		}
		if (type.val() == "Donation") {
			priceHeading.show();
			priceCell.show();
			priceLabel.html("Amount");
			donationSpacingCell.show();
			tax.attr('checked','true').change();
		}
		
	});
	
	// Optional input's checkbox toggle behavior
	salepriceToggle.change(function () {
		salepriceStatus.toggle();
		salepriceField.toggle();
	});

	salepriceToggle.click(function () {
		if (this.checked) {
			saleprice.focus();
			saleprice.select();
		}
	});

	shippingToggle.change(function () {
		shippingStatus.toggle();
		shippingFields.toggle();
	});
	
	shippingToggle.click(function () {
		if (this.checked) {
			weight.focus();
			weight.select();
		}
	});
	
	inventoryToggle.change(function () {
		inventoryStatus.toggle();
		inventoryField.toggle();
	});
	
	inventoryToggle.click(function () {
		if (this.checked) {
			stock.focus();
			stock.select();
		}
	});
	
	// Auto-format prices to a money format
	// TODO: Need to handle currency formatting
	price.change(function() { this.value = asMoney(this.value); }).change();
	saleprice.change(function() { this.value = asMoney(this.value); }).change();
	shippingfee.change(function() { this.value = asMoney(this.value); }).change();
	
	// Set the context for the db
	if (data && data.context) context.val(data.context);
	else context.val('product');
	
	// Set field values if we are rebuilding a priceline from 
	// database data
	if (data && data.id) {
		label.val(htmlentities(data.label)).change();
		type.val(data.type);
		myid.val(data.id);
		
		productid.val(data.product);
		sku.val(data.sku);
		price.val(asMoney(data.price));

		if (data.sale == "on") salepriceToggle.attr('checked','true').change();
		if (data.shipping == "on") shippingToggle.attr('checked','true').change();
		if (data.inventory == "on") inventoryToggle.attr('checked','true').change();

		saleprice.val(asMoney(data.saleprice));
		shippingfee.val(asMoney(data.shipfee));
		weight.val(data.weight);
		stock.val(data.stock);
		
		if (data.download) {
			if (data.filedata.mimetype)	data.filedata.mimetype = data.filedata.mimetype.replace(/\//gi," ");
			downloadFile.attr('class','file '+data.filedata.mimetype).html(data.filename+'<br /><small>'+Math.round((data.filesize/1024)*10)/10+' KB</small>').click(function () {
				window.location.href = siteurl+"/wp-admin/admin.php?page=shopp/lookup&download="+data.download;
			});

		}
		
		if (data.tax == "off") tax.attr('checked','true');
	} else {
		if (type.val() == "Shipped") shippingToggle.attr('checked','true').change();
	}

	// Improve usability for quick data entry by
	// causing fields to automatically select all
	// contents when focused/activated
	quickSelects(row);
	
	// Initialize the interface by triggering the
	// priceline type change behavior 
	type.change();
	
	// Store the price line reference object
	if (options) pricingOptions[xorkey(options)] = Pricing;	
	$('#prices').val(pricingidx++);
	
	return row;
}

// Magic key generator
function xorkey (ids) {
	for (var key=0,i=0; i < ids.length; i++) 
		key = key ^ (ids[i]*101);
	return key;
}

function variationsToggle () {
	if ($('#variations-setting').attr('checked')) {
		pricingOptions[0].disable();
		$('#product-pricing').hide();
		$('#variations').show();
	} else {
		$('#variations').hide();
		$('#product-pricing').show();
	}
}


/**
 * SWFUpload Image Uploading events
 **/

function imageFileQueued (file) {}

function imageFileQueueError (file, error, message) {
	if (error == SWFUpload.QUEUE_ERROR.QUEUE_LIMIT_EXCEEDED) {
		alert("You selected too many files to upload at one time. " + (message === 0 ? "You have reached the upload limit." : "You may upload " + (message > 1 ? "up to " + message + " files." : "only one file.")));
		return;
	}

}

function imageFileDialogComplete (selected, queued) {
	try {
		this.startUpload();
	} catch (ex) {
		this.debug(ex);
	}
}

function startImageUpload (file) {
	var cell = $('<li id="image-uploading"></li>').appendTo($('#lightbox'));
	var sorting = $('<input type="hidden" name="images[]" value="" />').appendTo(cell);
	var progress = $('<div class="progress"></div>').appendTo(cell);
	var bar = $('<div class="bar"></div>').appendTo(progress);
	var art = $('<div class="gloss"></div>').appendTo(progress);

	this.targetHolder = cell;
	this.progressBar = bar;
	this.sorting = sorting;
	return true;
}

function imageUploadProgress (file, loaded, total) {
	var progress = Math.ceil((loaded/total)*76);
	$(this.progressBar).animate({'width':progress+'px'},100);
}

function imageUploadError (file, error, message) {
	// console.log(error+": "+message);
}

function imageUploadSuccess (file, results) {
	var image = eval('('+results+')');
	$(this.targetHolder).attr({'id':'image-'+image.src});
	$(this.sorting).val(image.src);
	var img = $('<img src="'+siteurl+'/wp-admin/admin.php?page=shopp/lookup&id='+image.id+'" width="96" height="96" class="handle" />').appendTo(this.targetHolder).hide();
	var deleteButton = $('<button type="button" name="deleteImage" value="'+image.src+'" title="Delete product image&hellip;" class="deleteButton"></button>').appendTo($(this.targetHolder)).hide();
	var deleteIcon = $('<img src="'+rsrcdir+'/core/ui/icons/delete.png" alt="-" width="16" height="16" />').appendTo(deleteButton);
	
	$(this.progressBar).animate({'width':'76px'},250,function () { 
		$(this).parent().fadeOut(500,function() {
			$(this).remove(); 
			$(img).fadeIn('500');
			enableDeleteButton(deleteButton);
		});
	});
}

function imageUploadComplete (file) {
	if ($('#lightbox li').size() > 1) $('#lightbox').sortable('refresh');
	else $('#lightbox').sortable();
}

function imageQueueComplete (uploads) {}

function enableDeleteButton (button) {
	$(button).hide();

	$(button).parent().hover(function() {
		$(button).show();
	},function () {
		$(button).hide();
	});
	
	$(button).click(function() {
		if (confirm("Are you sure you want to delete this product image?")) {
			$('#deleteImages').val(($('#deleteImages').val() == "")?$(button).val():$('#deleteImages').val()+','+$(button).val());
			$(button).parent().fadeOut(500,function() {
				$(this).remove();
			});
		}
	});
}

/**
 * SWFUpload pricing option upload file event handlers
 **/
function fileQueued (file) {

}

function fileQueueError (file, error, message) {
	if (error == SWFUpload.QUEUE_ERROR.QUEUE_LIMIT_EXCEEDED) {
		alert("You selected too many files to upload at one time. " + (message === 0 ? "You have reached the upload limit." : "You may upload " + (message > 1 ? "up to " + message + " files." : "only one file.")));
		return;
	}

}

function fileDialogComplete (selected, queued) {
	try {
		this.startUpload();
	} catch (ex) {
		this.debug(ex);
	}
}

function startUpload (file) {
	this.targetCell.attr('class','').html('');
	var progress = $('<div class="progress"></div>').appendTo(this.targetCell);
	var bar = $('<div class="bar"></div>').appendTo(progress);
	var art = $('<div class="gloss"></div>').appendTo(progress);
	
	this.progressBar = bar;
	
	return true;
}

function uploadProgress (file, loaded, total) {
	var progress = Math.ceil((loaded/total)*76);
	$(this.progressBar).animate({'width':progress+'px'},100);
}

function uploadError (file, error, message) { }

function uploadSuccess (file, results) {
	var filedata = eval('('+results+')');
	var targetCell = this.targetCell;
	var i = this.targetLine;
	// console.log(filedata.id);
	filedata.type = filedata.type.replace(/\//gi," ");
	$(this.progressBar).animate({'width':'76px'},250,function () { 
		$(this).parent().fadeOut(500,function() {
			$(this).remove(); 
			$(targetCell).attr('class','file '+filedata.type).html(filedata.name+'<br /><small>'+Math.round((filedata.size/1024)*10)/10+' KB</small><input type="hidden" name="price['+i+'][download]" value="'+filedata.id+'" />');
		});
	});
}

function uploadComplete (file) {}

function queueComplete (uploads) {}