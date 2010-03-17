/**
 * Nested Menu behaviors
 **/
function NestedMenu (i,target,dataname,defaultlabel,data,items,sortoptions) {
	var $=jQuery.noConflict();
	if (!sortoptions) sortoptions = {'axis':'y'};
	var _self = this;
	
	this.items = items;
	this.dataname = dataname;
	this.element = $('<li>').appendTo($(target).children('ul'));
	this.index = i;
	this.moveHandle = $('<div class="move"></div>').appendTo(this.element);
	this.sortorder = $('<input type="hidden" name="'+dataname+'-sortorder[]" value="'+i+'" />').appendTo(this.element);
	this.id = $('<input type="hidden" name="'+dataname+'['+i+'][id]" class="id" />').appendTo(this.element);
	this.label = $('<input type="text" name="'+dataname+'['+i+'][name]" class="label" />').appendTo(this.element);
	this.deleteButton = $('<button type="button" class="delete"><img src="'+uidir+'/icons/delete.png" alt="Delete" width="16" height="16" /></button>').appendTo(this.element);

	if (this.items) {
		if (items.type == "list") this.itemsElement = $('<ul></ul>').appendTo(items.target).hide();
		else this.itemsElement = $('<li></li>').appendTo(items.target).hide();
	}

	this.selected = function () {
		$(target).find('ul li').removeClass('selected');
		$(_self.element).addClass('selected');
		if (items) {
			$(items.target).children().hide();
			$(_self.itemsElement).show();	
		}
	}
	this.element
		.click(this.selected)
		.hover(function () { $(this).addClass('hover'); },
			   function () { $(this).removeClass('hover'); }
		);

	this.label.mouseup(function (e) { this.select(); }).focus(function () {
		$(this).keydown(function (e) {
			e.stopPropagation();
			if (e.keyCode == 13) $(this).blur().unbind('keydown');
		});
	});

	this.remove = function () {
		var deletes = $(target).find('input.deletes');
		if ($(_self.id).val() != "") // Only need db delete if an id exists
			deletes.val( (deletes.val() == "")?$(_self.id).val():deletes.val()+','+$(_self.id).val() );
		if (items) _self.itemsElement.remove();
		_self.element.remove();

	}
	this.deleteButton.click(this.remove);
	
	this.id.val(this.index);
	if (data && data.id) this.id.val(data.id);
	if (data && data.name) this.label.val(htmlentities(data.name));
	else this.label.val(defaultlabel+' '+this.index);

	// Enable sorting
	if (!$(target).children('ul').hasClass('ui-sortable'))
		$(target).children('ul').sortable(sortoptions);
	else $(target).children('ul').sortable('refresh');

}

function NestedMenuContent (i,target,dataname,data) {
	var $=jQuery.noConflict();
	var content = $('<textarea name="'+dataname+'['+i+'][value]" cols="40" rows="7"></textarea>').appendTo(target);
	if (data && data.value) content.val(htmlentities(data.value));
}

function NestedMenuOption (i,target,dataname,defaultlabel,data) {
	var $=jQuery.noConflict();
	
	var _self = this;
	
	this.index = $(target).contents().length;
	this.element = $('<li class="option"></li>').appendTo(target);
	this.moveHandle = $('<div class="move"></div>').appendTo(this.element);
	this.id = $('<input type="hidden" name="'+dataname+'['+i+'][options]['+this.index+'][id]" class="id" />').appendTo(this.element);
	this.label = $('<input type="text" name="'+dataname+'['+i+'][options]['+this.index+'][name]" class="label" />').appendTo(this.element);
	this.deleteButton = $('<button type="button" class="delete"><img src="'+uidir+'/icons/delete.png" alt="delete" width="16" height="16" /></button>').appendTo(this.element);

	this.element.hover(function () { $(this).addClass('hover'); },
					   function () { $(this).removeClass('hover'); });

	this.label.click(function () { this.select(); });
	this.label.focus(function () {
		$(this).keydown(function (e) {
			e.stopPropagation();
			if (e.keyCode == 13) $(this).blur().unbind('keydown');
		});
	});
	
	this.deleteButton.click(function () { $(_self.element).remove(); });

	this.id.val(this.index);
	if (data.id) this.id.val(data.id);
	if (data.name) this.label.val(htmlentities(data.name));
	if (!data.name) this.label.val(defaultlabel+' '+(this.index+1));
		
}


/**
 * Variations support
 **/
function loadVariations (options,prices) {
	if (!options) return;
	var $=jQuery.noConflict();
	$.each(options,function (key,option) { 
		addVariationOptionsMenu(option); 
	});

	$.each(prices,function (key,price) { 
		if (this.context == "variation")
			Pricelines.add(this.options.split(","),this,'#variations-pricing');
	});
	Pricelines.updateVariationsUI();
	
	$.each(options,function (key,option) { 
		$.each(option.options,function(i,data) {
			if (data.linked == "on") Pricelines.linkVariations(data.id);
		});
	});
}

function addVariationOptionsMenu (data) {
	var $=jQuery.noConflict();
	var menus = $('#variations-menu');
	var entries = $('#variations-list');
	var addOptionButton = $('#addVariationOption');
	var linkOptionVariations = $('#linkOptionVariations');
	var id = variationsidx;
	
	var menu = new NestedMenu(id,menus,'options',OPTION_MENU_DEFAULT,data,
		{target:entries,type:'list'},
		{'axis':'y','update':function() { orderOptions(menus,entries) }}
	);
	
	menu.addOption = function (data) {
		var init = false;
		if (!data) data = new Object();

		if (!data.id) {
			init = true;
 			data.id = optionsidx;
		} else if (data.id > optionsidx) optionsidx = data.id;
		
	 	var option = new NestedMenuOption(menu.index,menu.itemsElement,'options',NEW_OPTION_DEFAULT,data);
		optionsidx++;

		option.linkIcon = $('<img src="'+uidir+'/icons/linked.png" alt="linked" width="16" height="16" class="link" />').appendTo(option.moveHandle);
		option.linked = $('<input type="hidden" name="options['+menu.index+'][options]['+option.index+'][linked]" class="linked" />').appendTo(option.element);
		option.linked.change(function () {
			if ($(this).val() == "off")	option.linkIcon.addClass('invisible');
			if ($(this).val() == "on") option.linkIcon.removeClass('invisible');
		});
		if (data.linked) option.linked.val(data.linked).change();
		else option.linked.val('off').change();

		option.selected = function () {
			if (option.element.hasClass('selected')) {
				entries.find('ul li').removeClass('selected');
				selectedMenuOption = false;
			} else {
				entries.find('ul li').removeClass('selected');
				$(option.element).addClass('selected');
				selectedMenuOption = option;
			}
			linkOptionVariations.change();
		}
		option.element.click(option.selected);

		productOptions[option.id.val()] = option.label;
		option.label.blur(function() { updateVariationLabels(); });
		option.deleteButton.unbind('click');
	
		option.deleteButton.click(function () {
			if (menu.itemsElement.children().length == 1)
				deleteVariationPrices([option.id.val()],true);
			else deleteVariationPrices([option.id.val()]);
			option.element.remove();
		});
	
		if (!init) addVariationPrices(option.id.val());
		else addVariationPrices();
		
		menu.items.push(option);
	}

	menu.items = new Array();
	if (data && data.options) $.each(data.options,function () { menu.addOption(this) });
	else {
		menu.addOption();
		menu.addOption();
	}
	menu.itemsElement.sortable({'axis':'y','update':function(){ orderVariationPrices() }});

	menu.element.unbind('click',menu.click);
	menu.element.click(function () {
		menu.selected();
		$(addOptionButton).unbind('click').click(menu.addOption);
	});
	optionMenus[variationsidx++] = menu;
	
	menu.deleteButton.unbind('click');
	menu.deleteButton.click(function () {
		var deletedOptions = new Array();
		$(menu.itemsElement).find('li').not('.ui-sortable-helper').find('input.id').each(function (i,id) {
			deletedOptions.push($(id).val());
		});
		deleteVariationPrices(deletedOptions,true);
		menu.remove();
	});
	
}

/**
 * buildVariations()
 * Creates an array of all possible combinations of the product variation options */
function buildVariations () {
	var $=jQuery.noConflict();
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
	var $=jQuery.noConflict();
	if (!data) {
		var updated = buildVariations();
		var variationPricing = $('#variations-pricing');
		var variationPricelines = $(variationPricing).children();
		var added = false;

		$(updated).each(function(id,options) {
			var key = xorkey(options);
			var preKey = xorkey(options.slice(0,options.length-1));
			if (preKey == "") preKey = -1;

			if (!Pricelines.row[key]) {
				if (Pricelines.row[preKey]) {
					Pricelines.row[key] = Pricelines.row[preKey];
					delete Pricelines.row[preKey];
					Pricelines.row[key].setOptions(options);
				} else {
					if (variationPricelines.length == 0) { // Append new row
						Pricelines.add(options,{context:'variation'},'#variations-pricing');
					} else { // Add after previous variation
						Pricelines.add(options,{context:'variation'},Pricelines.row[ xorkey(updated[(id-1)]) ].row,'after');
					}
					added = true;
				}
			}
		});
		if (added) Pricelines.updateVariationsUI();
	}
}

function deleteVariationPrices (optionids,reduce) {
	var $=jQuery.noConflict();
	var updated = buildVariations();
	var reduced = false;
	$(updated).each(function(id,options) {
		var key = xorkey(options);

		for (var i = 0; i < optionids.length; i++)  {
			if (options.indexOf(optionids[i]) != -1) {

				var modOptions = new Array();
				$(options).each(function(index,option) {
					if (option != optionids[i]) modOptions.push(option);
				});
				var newkey = xorkey(modOptions);
			
				if (reduce && !Pricelines.row[newkey]) {
					if (newkey != 0) Pricelines.row[newkey] = Pricelines.row[key];
					else Pricelines.row[key].row.remove();
					delete Pricelines.row[key];
					
					if (Pricelines.row[newkey]) {
						Pricelines.row[newkey].setOptions(modOptions);
						reduced = true;
					}
				} else {
					if (Pricelines.row[key]) {
						// Mark priceline for removal from db
						var dbPriceId = $('#priceid-'+Pricelines.row[key].id).val();
						if ($('#deletePrices').val() == "") $('#deletePrices').val(dbPriceId);
						else $('#deletePrices').val($('#deletePrices').val()+","+dbPriceId);

						// Remove the priceline row from the ui/dom
						Pricelines.remove(key);
					}
				}
			
			}
		}

	});
	
	if (reduced) updateVariationsUI();

}

function optionMenuExists (label) {
	var $=jQuery.noConflict();
	if (!label) return false;
	var found = false;
	$.each(optionMenus,function (id,menu) {
		if (menu && $(menu.label).val() == label) return (found = id);
	});
	if (optionMenus[found]) return optionMenus[found];
	return found;
}

function optionMenuItemExists (menu,label) {
	var $=jQuery.noConflict();
	if (!menu || !menu.items || !label) return false;
	var found = false;
	$.each(menu.items,function (id,item) {
		if (item && $(item.label).val() == label) return (found = true);
	});
	return found;
}

function updateVariationLabels () {
	var $=jQuery.noConflict();
	var updated = buildVariations();
	$(updated).each(function(id,options) {
		var key = xorkey(options);
		if (Pricelines.row[key]) Pricelines.row[key].updateLabel();
	});
}

function orderOptions (menus,options) {
	var $=jQuery.noConflict();
	var menuids = $(menus).find("ul li").not('.ui-sortable-helper').find('input.id');
	$(menuids).each(function (i,menuid) {
		if (menuid) $(optionMenus[$(menuid).val()].itemsElement).appendTo(options);
	});
	orderVariationPrices();
}

function orderVariationPrices () {
	var $=jQuery.noConflict();
	var updated = buildVariations();

	$(updated).each(function (id,options) {
		var key = xorkey(options);
		if (key > 0 && Pricelines.row[key])
			Pricelines.reorderVariation(key,options);
	});

	Pricelines.updateVariationsUI("tabs");
}

// Magic key generator
function xorkey (ids) {
	for (var key=0,i=0; i < ids.length; i++) 
		key = key ^ (ids[i]*7001);
	return key;
}

function variationsToggle () {
	var $=jQuery.noConflict();
	if ($('#variations-setting').attr('checked')) {
		if (Pricelines.row[0]) Pricelines.row[0].disable();
		$('#product-pricing').hide();
		$('#variations').show();
	} else {
		$('#variations').hide();
		$('#product-pricing').show();
	}
}

function addonsToggle () {
	var $=jQuery.noConflict();
	if ($('#addons-setting').attr('checked')) $('#addons').show();
	else $('#addons').hide();
}


function clearLinkedIcons () {
	jQuery('#variations-list input.linked').val('off').change();
}

function linkVariationsButton () {
	var $=jQuery.noConflict();
	if (selectedMenuOption) {
		if (selectedMenuOption.linked.val() == 'off') {
			// If all are linked, unlink everything first
			if (Pricelines.allLinked()) {
				clearLinkedIcons();
				Pricelines.unlinkAll();
			}
			selectedMenuOption.linked.val('on').change();
			Pricelines.linkVariations(selectedMenuOption.id.val());
		} else {
			selectedMenuOption.linked.val('off').change();
			Pricelines.unlinkVariations(selectedMenuOption.id.val());	
		}
	} else {
		// Nothing selected, link/unlink all
		clearLinkedIcons();
		if (Pricelines.allLinked()) {
			Pricelines.unlinkAll();
		} else Pricelines.linkAll();
	}
	$(this).change();
}

function linkVariationsButtonLabel () {
	var $=jQuery.noConflict();
	if (selectedMenuOption) {
		if (selectedMenuOption.linked.val() == 'on') $(this).find('small').html(' '+UNLINK_VARIATIONS);
		else $(this).find('small').html(' '+LINK_VARIATIONS);
	} else {
		if (Pricelines.allLinked()) $(this).find('small').html(' '+UNLINK_ALL_VARIATIONS);
		else $(this).find('small').html(' '+LINK_ALL_VARIATIONS);
	}
	
}

/**
 * Addons support
 **/
function loadAddons (addons,prices) {
	var $=jQuery.noConflict();
	if (!addons) return;

	$.each(addons,function (key,addon) {
		newAddonGroup(addon); 
	});

	// $.each(prices,function (key,price) { 
	// 	console.log(price.options);
	// 	if (price.context == "addon")
	// 		Pricelines.add(price.options.split(","),this,'#addon-pricing');
	// });
	Pricelines.updateVariationsUI();
}

function newAddonGroup (data) {
	var $=jQuery.noConflict();
	var menus = $('#addon-menu');
	var entries = $('#addon-list');
	var addOptionButton = $('#addAddonOption');
	var id = addon_group_idx;
	
	var menu = new NestedMenu(id,menus,'addons',ADDON_GROUP_DEFAULT,data,
		{target:entries,type:'list'},
		{'axis':'y','update':function() { orderAddonGroups() }}
	);
	
	menu.itemsElement.attr('id','addon-group-'+id);
	menu.pricegroup = $('<div id="addon-pricegroup-'+id+'" />').appendTo('#addon-pricing');
	menu.pricegroupLabel = $('<label />').html('<h4>'+menu.label.val()+'</h4>').prependTo(menu.pricegroup);
	menu.updatePriceLabel = function () {
		menu.pricegroupLabel.html('<h4>'+menu.label.val()+'</h4>');
	}
	menu.label.blur(menu.updatePriceLabel);
	
	menu.addOption = function (data) {
		var init = false;
		if (!data) data = new Object();

		if (!data.id) {
			init = true;
 			data.id = addonsidx;
		} else if (data.id > addonsidx) addonsidx = data.id;
		
	 	var option = new NestedMenuOption(menu.index,menu.itemsElement,'addons',NEW_OPTION_DEFAULT,data);
		addonsidx++;
		var optionid = option.id.val();
		option.selected = function () {
			if (option.element.hasClass('selected')) {
				entries.find('ul li').removeClass('selected');
				selectedMenuOption = false;
			} else {
				entries.find('ul li').removeClass('selected');
				$(option.element).addClass('selected');
				selectedMenuOption = option;
			}
		}
		option.element.click(option.selected);

		productAddons[optionid] = option.label;
		option.label.blur(function() { Pricelines.row[optionid].updateLabel(); });
		option.deleteButton.unbind('click');
	
		option.deleteButton.click(function () {
			Pricelines.row[option.id.val()].row.remove();
			option.element.remove();
		});

		Pricelines.add(option.id.val(),{context:'addon'},menu.pricegroup);
		
		menu.items.push(option);
	}

	menu.items = new Array();
	if (data && data.options) $.each(data.options,function () { menu.addOption(this) });
	else {
		menu.addOption();
		menu.addOption();
	}
	menu.itemsElement.sortable({'axis':'y','update':function(){ orderAddonPrices(menu.index) }});

	menu.element.unbind('click',menu.click);
	menu.element.click(function () {
		menu.selected();
		$(addOptionButton).unbind('click').click(menu.addOption);
	});
	addonGroups[addon_group_idx++] = menu;
	
	menu.deleteButton.unbind('click');
	menu.deleteButton.click(function () {
		var options = $('#addon-list #addon-group-'+menu.index+' li').not('.ui-sortable-helper').find('input.id');
		$(options).each(function (id,option) {
			Pricelines.row[$(option).val()].row.remove();
		});
		menu.pricegroup.remove();
		menu.remove();
	});
	
}

function orderAddonGroups () {
	var $=jQuery.noConflict();
	var menuids = $('#addon-menu ul li').not('.ui-sortable-helper').find('input.id');
	$(menuids).each(function (i,menuid) {
		var menu = addonGroups[$(menuid).val()];
		menu.pricegroup.appendTo('#addon-pricing');
	});
}

function orderAddonPrices (index) {
	var $=jQuery.noConflict();
	var menu = addonGroups[index];
	var options = $('#addon-list #addon-group-'+menu.index+' li').not('.ui-sortable-helper').find('input.id');
	$(options).each(function (id,option) {
		Pricelines.reorderAddon($(option).val(),menu.pricegroup);
	});
}

function readableFileSize (size) {
	var units = new Array("bytes","KB","MB","GB");
	var sized = size*1;
	if (sized == 0) return sized;
	var unit = 0;
	while (sized > 1000) {
		sized = sized/1024;
		unit++;
	}
	return sized.toFixed(2)+" "+units[unit];
}

function unsavedChanges () {
	var mce = typeof(tinyMCE) != 'undefined' ? tinyMCE.activeEditor : false, title, content;

	if ( mce && !mce.isHidden() ) {
		if ( mce.isDirty() )
			return UNSAVED_CHANGES_WARNING;
	}
	if (changes && !saving) return UNSAVED_CHANGES_WARNING;	
}

/**
 * Add a product spec/detail
 **/
function addDetail (data) {
	var $=jQuery.noConflict();
	var menus = $('#details-menu');
	var entries = $('#details-list');
	var id = detailsidx++;
	var menu = new NestedMenu(id,menus,'details','Detail Name',data,{target:entries});

	if (data && data.options) {
		var optionsmenu = $('<select name="details['+menu.index+'][value]"></select>').appendTo(menu.itemsElement);
		for (var i in data.options) $('<option>'+data.options[i]['name']+'</option>').appendTo(optionsmenu);		
		if (data && data.value) optionsmenu.val(htmlentities(data.value));	
	} else menu.item = new NestedMenuContent(menu.index,menu.itemsElement,'details',data);	
	
	if (!data || data.add) menu.add = $('<input type="hidden" name="details['+menu.index+'][new]" value="true" />').appendTo(menu.element);
}

/**
 * Image Uploads using SWFUpload or the jQuery plugin One Click Upload
 **/
function ImageUploads (id,type) {
	(function($) {
	var swfu;

	var settings = {
		button_text: '<span class="button">'+ADD_IMAGE_BUTTON_TEXT+'</span>',
		button_text_style: '.button { text-align: center; font-family:"Lucida Grande","Lucida Sans Unicode",Tahoma,Verdana,sans-serif; font-size: 9px; color: #333333; }',
		button_text_top_padding: 3,
		button_height: "22",
		button_width: "100",
		button_image_url: uidir+'/icons/buttons.png',
		button_placeholder_id: "swf-uploader-button",
		upload_url : ajaxurl,
		flash_url : uidir+'/behaviors/swfupload/swfupload.swf',
		file_queue_limit : 1,
		file_size_limit : filesizeLimit+'b',
		file_types : "*.jpg;*.jpeg;*.png;*.gif",
		file_types_description : "Web-compatible Image Files",
		file_upload_limit : filesizeLimit,
		post_params : {
			action:'shopp_upload_image',
			parent:id,
			type:type
		},

		swfupload_loaded_handler : swfuLoaded,
		file_queued_handler : imageFileQueued,
		file_queue_error_handler : imageFileQueueError,
		file_dialog_complete_handler : imageFileDialogComplete,
		upload_start_handler : startImageUpload,
		upload_progress_handler : imageUploadProgress,
		upload_error_handler : imageUploadError,
		upload_success_handler : imageUploadSuccess,
		upload_complete_handler : imageUploadComplete,
		queue_complete_handler : imageQueueComplete,

		custom_settings : {
			loaded: false,
			targetHolder : false,
			progressBar : false,
			sorting : false
			
		},
		debug: imageupload_debug
		
	}
	
	// Initialize image uploader
	
	if (flashuploader)
		swfu = new SWFUpload(settings);

	var browserImageUploader = $('#image-upload').upload({
		name: 'Filedata',
		action: ajaxurl,
		enctype: 'multipart/form-data',
		params: {
			action:'shopp_upload_image',
			type:type
		},
		autoSubmit: true,
		onSubmit: function() {
			var cell = $('<li id="image-uploading"></li>').appendTo($('#lightbox'));
			var sorting = $('<input type="hidden" name="images[]" value="" />').appendTo(cell);
			var progress = $('<div class="progress"></div>').appendTo(cell);
			var bar = $('<div class="bar"></div>').appendTo(progress);
			var art = $('<div class="gloss"></div>').appendTo(progress);
	
			this.targetHolder = cell;
			this.progressBar = bar;
			this.sorting = sorting;
		},
		onComplete: function(results) {
			if (results == "") {
				$(this.targetHolder).remove();
				alert(SERVER_COMM_ERROR);
				return true;
			}
			var image = eval('('+results+')');
			if (image.error) {
				$(this.targetHolder).remove();
				alert(image.error);
				return true;
			}
			$(this.targetHolder).attr({'id':'image-'+image.src});
			$(this.sorting).val(image.src);
			var img = $('<img src="?siid='+image.id+'" width="96" height="96" class="handle" />').appendTo(this.targetHolder).hide();
			var deleteButton = $('<button type="button" name="deleteImage" value="'+image.src+'" title="Delete product image&hellip;" class="deleteButton"></button>').appendTo($(this.targetHolder)).hide();
			var deleteIcon = $('<img src="'+uidir+'/icons/delete.png" alt="-" width="16" height="16" />').appendTo(deleteButton);
	
			$(this.progressBar).animate({'width':'76px'},250,function () { 
				$(this).parent().fadeOut(500,function() {
					$(this).remove(); 
					$(img).fadeIn('500');
					enableDeleteButton(deleteButton);
				});
			});
		}
	});
	
	$(document).load(function() {
		if (!swfu.loaded) $('#product-images .swfupload').remove();
	});
		
	if ($('#lightbox li').size() > 0) $('#lightbox').sortable({'opacity':0.8});
	$('#lightbox li').each(function () {
		$(this).dblclick(function () {
			var id = $(this).attr('id')+"-details";
			$.fn.colorbox({'title':IMAGE_DETAILS_TEXT,'innerWidth':'340','innerHeight':'110','inline':true,'href':'#'+id});
		});
		enableDeleteButton($(this).find('button.deleteButton'));
	});

	function swfuLoaded () {
		$('#browser-uploader').hide();	
		swfu.loaded = true;
	}

	function imageFileQueued (file) {}

	function imageFileQueueError (file, error, message) {

		if (error == SWFUpload.QUEUE_ERROR.QUEUE_LIMIT_EXCEEDED) {
			alert("You selected too many files to upload at one time. " + (message === 0 ? "You have reached the upload limit." : "You may upload " + (message > 1 ? "up to " + message + " files." : "only one file.")));
			return;
		} else alert(message);
	
	}

	function imageFileDialogComplete (selected, queued) {
		try {
			this.startUpload();
		} catch (ex) {
			this.debug(ex);
		}
	}

	function startImageUpload (file) {
		var cell = $('<li class="image uploading"></li>').appendTo($('#lightbox'));
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
		//console.log(error+": "+message);
	}

	function imageUploadSuccess (file, results) {
		var image = eval('('+results+')');
		if (!image.id) {
			$(this.targetHolder).remove();
			if (image.error) alert(image.error);
			else alert(UNKNOWN_UPLOAD_ERROR);
			return true;
		}
	
		$(this.targetHolder).attr({'id':'image-'+image.id});
		$(this.sorting).val(image.src);
		var img = $('<img src="?siid='+image.id+'" width="96" height="96" class="handle" />').appendTo(this.targetHolder).hide();
		var deleteButton = $('<button type="button" name="deleteImage" value="'+image.id+'" title="Delete product image&hellip;" class="deleteButton"></button>').appendTo($(this.targetHolder)).hide();
		var deleteIcon = $('<img src="'+uidir+'/icons/delete.png" alt="-" width="16" height="16" />').appendTo(deleteButton);
	
		$(this.progressBar).animate({'width':'76px'},250,function () { 
			$(this).parent().fadeOut(500,function() {
				$(this).remove(); 
				$(img).fadeIn('500');
				enableDeleteButton(deleteButton);
			});
		});
	}

	function imageUploadComplete (file) {}

	function imageQueueComplete (uploads) {
		if ($('#lightbox li').size() > 1) $('#lightbox').sortable('refresh');
		else $('#lightbox').sortable();
	}

	function enableDeleteButton (button) {
		$(button).hide();

		$(button).parent().hover(function() {
			$(button).show();
		},function () {
			$(button).hide();
		});
	
		$(button).click(function() {
			if (confirm(DELETE_IMAGE_WARNING)) {
				$('#deleteImages').val(($('#deleteImages').val() == "")?$(button).val():$('#deleteImages').val()+','+$(button).val());
				$(button).parent().fadeOut(500,function() {
					$(this).remove();
				});
			}
		});
	}
	})(jQuery)

}

jQuery.fn.FileChooser = function (line,status) {
	var $ = jQuery.noConflict();

	fileUploads.updateLine(line,status);

	var downloadpath = $('#downloadpath-'+line);
	var fileimport = $('#import-url').change(function () {
		var fi = $(this);
		fi.removeClass('warning').addClass('verifying');
		$.ajax({url:fileverify_url+'&action=shopp_verify_file',
				type:"POST",
				data:'url='+fi.val(),
				timeout:10000,
				dataType:'text',
				success:function (results) {
					fi.attr('class','fileimport');
					if (results == "OK") { fi.addClass('ok'); return; }
					if (results == "NULL") fi.attr('title',FILE_NOT_FOUND_TEXT);
					if (results == "ISDIR") fi.attr('title',FILE_ISDIR_TEXT);
					if (results == "READ") fi.attr('title',FILE_NOT_READ_TEXT);
					fi.addClass("warning");
				}
		});
		
	});

	$(this).colorbox({'title':'File Selector','innerWidth':'350','innerHeight':'140','inline':true,'href':'#chooser'});

}


/**
 * File upload handlers for product download files using SWFupload
 **/
function FileUploader (button,defaultButton) {
	var _self = this;
	(function($) {

	_self.settings = {
		button_text: '<span class="button">'+UPLOAD_FILE_BUTTON_TEXT+'</span>',
		button_text_style: '.button { text-align: center; font-family:"Lucida Grande","Lucida Sans Unicode",Tahoma,Verdana,sans-serif; font-size: 9px; color: #333333; }',
		button_text_top_padding: 3,
		button_height: "22",
		button_width: "100",
		button_image_url: uidir+'/icons/buttons.png',
		button_placeholder_id: button,
		button_action: SWFUpload.BUTTON_ACTION.SELECT_FILE,
		flash_url : uidir+'/behaviors/swfupload/swfupload.swf',
		upload_url : ajaxurl,
		file_queue_limit : 1,
		file_size_limit : filesizeLimit+'b',
		file_types : "*.*",
		file_types_description : "All Files",
		file_upload_limit : filesizeLimit,
		post_params : {
			action:'shopp_upload_file'
		},
				
		swfupload_loaded_handler : swfuLoaded,
		file_queue_error_handler : fileQueueError,
		file_dialog_complete_handler : fileDialogComplete,
		upload_start_handler : startUpload,
		upload_progress_handler : uploadProgress,
		upload_error_handler : uploadError,
		upload_success_handler : uploadSuccess,
		upload_complete_handler : uploadComplete,
		
		custom_settings : {
			loaded : false,
			targetCell : false,
			targetLine : false,
			progressBar : false
		},
		debug: fileupload_debug
		
	}
	
	// Initialize file uploader
	
	if (flashuploader)
		_self.swfu = new SWFUpload(_self.settings);
	
	// Browser-based AJAX uploads
	defaultButton.upload({
		name: 'Filedata',
		action: ajaxurl,
		enctype: 'multipart/form-data',
		params: {
			action:'shopp_upload_file'
		},
		autoSubmit: true,
		onSubmit: function() {
			updates.attr('class','').html('');
			var progress = $('<div class="progress"></div>').appendTo(updates);
			var bar = $('<div class="bar"></div>').appendTo(progress);
			var art = $('<div class="gloss"></div>').appendTo(progress);

			this.targetHolder = updates;
			this.progressBar = bar;
		},
		onComplete: function(results) {
			var filedata = eval('('+results+')');
			if (filedata.error) {
				$(this.targetHolder).html("No download file.");
				alert(filedata.error);
				return true;
			}
			var targetHolder = this.targetHolder;
			filedata.type = filedata.type.replace(/\//gi," ");
			$(this.progressBar).animate({'width':'76px'},250,function () { 
				$(this).parent().fadeOut(500,function() {
					$(targetHolder).attr('class','file '+filedata.type).html(filedata.name+'<br /><small>'+readableFileSize(filedata.size)+'</small><input type="hidden" name="price['+linenum+'][download]" value="'+filedata.id+'" />');
					$(this).remove(); 
				});
			});
		}
	});

	$(_self).load(function () {
		if (!_self.swfu.loaded) $(defaultButton).parent().parent().find('.swfupload').remove();
	});
	
	function swfuLoaded () {
		$(defaultButton).hide();
		this.loaded = true;
	}
	
	_self.updateLine = function (line,status) {
		_self.swfu.targetLine = line;
		_self.swfu.targetCell = status;
	}
		
	function fileQueueError (file, error, message) {
		if (error == SWFUpload.QUEUE_ERROR.QUEUE_LIMIT_EXCEEDED) {
			alert("You selected too many files to upload at one time. " + (message === 0 ? "You have reached the upload limit." : "You may upload " + (message > 1 ? "up to " + message + " files." : "only one file.")));
			return;
		} else {
			alert(message);
		}

	}
	
	function fileDialogComplete (selected, queued) {
		$.fn.colorbox.hide();

		if (!selected) return;
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
		if (!filedata.id && !filedata.name) {
			$(this.targetHolder).html(NO_DOWNLOAD);
			if (filedata.error) alert(filedata.error);
			else alert(UNKNOWN_UPLOAD_ERROR);
			return true;
		}

		var targetCell = this.targetCell;
		var i = this.targetLine;
		filedata.type = filedata.type.replace(/\//gi," ");
		$(this.progressBar).animate({'width':'76px'},250,function () { 
			$(this).parent().fadeOut(500,function() {
				$(this).remove(); 
				$(targetCell).attr('class','file '+filedata.type).html(filedata.name+'<br /><small>'+readableFileSize(filedata.size)+'</small><input type="hidden" name="price['+i+'][download]" value="'+filedata.id+'" />');
			});
		});
	}

	function uploadComplete (file) {}
	})(jQuery)
	
}

function SlugEditor (id,type) {
	var _self = this;
	(function($) {
	_self.edit_permalink = function () {
			var i, c = 0;
			var editor = $('#editable-slug');
			var revert_editor = editor.html();
			var real_slug = $('#slug_input');
			var revert_slug = real_slug.html();
			var buttons = $('#edit-slug-buttons');
			var revert_buttons = buttons.html();
			var full = $('#editable-slug-full').html();
		
			buttons.html('<button type="button" class="save button">'+SAVE_BUTTON_TEXT+'</button> <button type="button" class="cancel button">'+CANCEL_BUTTON_TEXT+'</button>');
			buttons.children('.save').click(function() {
				var slug = editor.children('input').val();
				$.post(editslug_url+'&action=shopp_edit_slug', 
					{ 'id':id, 'type':type, 'slug':slug },
					function (data) {
						editor.html(revert_editor);
						buttons.html(revert_buttons);
						if (data != -1) {
							editor.html(data);
							$('#editable-slug-full').html(data);
							real_slug.val(data);
						}
						_self.enable();
					},'text');
			});
			$('#edit-slug-buttons .cancel').click(function() {
				editor.html(revert_editor);
				buttons.html(revert_buttons);
				real_slug.attr('value', revert_slug);
				_self.enable();
			});
			
			for(i=0; i < full.length; ++i) if ('%' == full.charAt(i)) c++;
			slug_value = (c > full.length/4)? '' : full;
			
			editor.html('<input type="text" id="new-post-slug" value="'+slug_value+'" />').children('input').keypress(function(e) {
				// on enter, just save the new slug, don't save the post
				var key = e.which;
				if (key == 13 || key == 27) e.preventDefault();
				if (13 == key) buttons.children('.save').click();
				if (27 == key) buttons.children('.cancel').click();
				real_slug.val(this.value)
			}).focus();

	}
	
	_self.enable = function () {
		
		$('#edit-slug-buttons').children('.edit-slug').click(function () { _self.edit_permalink(); });
		$('#edit-slug-buttons').children('.view').click(function () { document.location.href=canonurl+$('#editable-slug-full').html(); });
		$('#editable-slug').click(function() { $('#edit-slug-buttons').children('.edit-slug').click(); });		
	}

	
	_self.enable();
	})(jQuery)
}