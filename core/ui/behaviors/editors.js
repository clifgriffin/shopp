function NestedMenu (i,target,dataname,defaultlabel,data,items,sortoptions) {
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
	this.deleteButton = $('<button type="button" class="delete"><img src="'+rsrcdir+'/core/ui/icons/delete.png" alt="Delete" width="16" height="16" /></button>').appendTo(this.element);

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
	var content = $('<textarea name="'+dataname+'['+i+'][content]" cols="40" rows="7"></textarea>').appendTo(target);
	if (data && data.content) content.val(htmlentities(data.content));
}

function NestedMenuOption (i,target,dataname,defaultlabel,data) {
	
	var _self = this;
	
	this.index = $(target).contents().length;
	this.element = $('<li class="option"></li>').appendTo(target);
	this.moveHandle = $('<div class="move"></div>').appendTo(this.element);
	this.id = $('<input type="hidden" name="'+dataname+'['+i+'][options]['+this.index+'][id]" class="id" />').appendTo(this.element);
	this.label = $('<input type="text" name="'+dataname+'['+i+'][options]['+this.index+'][name]" class="label" />').appendTo(this.element);
	this.deleteButton = $('<button type="button" class="delete"><img src="'+rsrcdir+'/core/ui/icons/delete.png" alt="delete" width="16" height="16" /></button>').appendTo(this.element);

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

function addDetail (data) {
	var menus = $('#details-menu');
	var entries = $('#details-list');
	var id = detailsidx++;
	var menu = new NestedMenu(id,menus,'details','Detail Name',data,{target:entries});

	if (data && data.options) {
		var optionsmenu = $('<select name="details['+menu.index+'][content]"></select>').appendTo(menu.itemsElement);
		for (var i in data.options) $('<option>'+data.options[i]['name']+'</option>').appendTo(optionsmenu);		
		if (data && data.content) optionsmenu.val(htmlentities(data.content));	
	} else menu.item = new NestedMenuContent(menu.index,menu.itemsElement,'details',data);	
	
	if (!data || data.add) menu.add = $('<input type="hidden" name="details['+menu.index+'][new]" value="true" />').appendTo(menu.element);
	
}

function loadVariations (options,prices) {
	if (options) {
		if (options.variations) {
			for (key in options.variations) addVariationOptionsMenu(options.variations[key]);	
		} else {
			for (key in options) addVariationOptionsMenu(options[key]);
		}
		
		for (key in prices) {
			if (prices[key].context == "variation")
				addPriceLine('#variations-pricing',prices[key].options.split(","),prices[key]);
		}
		addVariationPrices();
	}
}

function addVariationOptionsMenu (data) {
	var menus = $('#variations-menu');
	var entries = $('#variations-list');
	var addOptionButton = $('#addVariationOption');
	var id = variationsidx;

	if (data && data.menuid) data = convertLegacyOptions(data);
	
	var menu = new NestedMenu(id,menus,'options','Option Menu',data,
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
		
	 	var option = new NestedMenuOption(menu.index,menu.itemsElement,'options','New Option',data);
		optionsidx++;

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
	if (data && data.options) for (var option in data.options) menu.addOption(data.options[option]);
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

// Reformat old options data structure to new structure
function convertLegacyOptions (olddata) {
	var data = new Array();
	
	data.id = olddata.menuid;
	data.name = olddata.menu;
	
	var i = 0;
	data.options = new Array();
	for (var o in olddata.id) {
		data.options[i] = new Object();
		data.options[i].id = olddata.id[o];
		data.options[i].name = olddata.label[o];
		i++;
	}
	
	return data;
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
					if (pricingOptions[newkey]) {
						pricingOptions[newkey].options = modOptions;
						pricingOptions[newkey].updateLabel();
						pricingOptions[newkey].updateKey();
					}
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

function orderOptions (menus,options) {
	var menuids = $(menus).find("ul li").not('.ui-sortable-helper').find('input.id');
	$(menuids).each(function (i,menuid) {
		if (menuid) $(optionMenus[$(menuid).val()].itemsElement).appendTo(options);
	});
	orderVariationPrices();
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
	var row = $('<tr id="row['+i+']"></tr>');
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
	$('<input type="hidden" name="price['+i+'][inventory]" value="off" />').prependTo(salepriceHeading);
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
	if (storage == "fs") {
		var filePathCell = $('<div></div>').prependTo(downloadCell).hide();
		var filePath = $('<input type="text" name="price['+i+'][downloadpath]" value="" title="Enter file path relative to: '+productspath+'">').appendTo(filePathCell);
		var filePathButton = $('<button type="button" class="button-secondary" tabindex="'+(i+1)+'14"><small>By File Path</small></button>').appendTo(uploadHeading).click(function () {
			filePathCell.slideToggle();
		});
		
	}
	
	var uploadHolder = $('<div id="flash-product-uploader-'+i+'"></div>').appendTo(uploadHeading);
	var uploadButton = $('<button type="button" class="button-secondary" tabindex="'+(i+1)+'13"><small>Upload&nbsp;File</small></button>').appendTo(uploadHeading);
	
	var uploader = new FileUploader($(uploadHolder).attr('id'),uploadButton,i,downloadFile);
			
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
		
	var interfaces = new Object();
	interfaces['All'] = new Array(priceHeading, priceCell, salepriceHeading, salepriceCell, shippingHeading, shippingCell, inventoryHeading, inventoryCell, downloadHeading, downloadCell, uploadHeading, donationSpacingCell);
	if (pricesPayload) {		
		interfaces['Shipped'] = new Array(priceHeading, priceCell, salepriceHeading, salepriceCell, shippingHeading, shippingCell, inventoryHeading, inventoryCell);
		interfaces['Download'] = new Array(priceHeading, priceCell, salepriceHeading, salepriceCell, downloadHeading, downloadCell, uploadHeading);
	} else {
		interfaces['Shipped'] = new Array(priceHeading, priceCell, shippingHeading, shippingCell);
		interfaces['Download'] = new Array(priceHeading, priceCell, donationSpacingCell);
	}
	interfaces['Donation'] = new Array(priceHeading, priceCell, donationSpacingCell);
	
	// Alter the interface depending on the type of price line
	type.change(function () {
		var ui = type.val();
		for (var e in interfaces['All']) $(interfaces['All'][e]).hide();
		priceLabel.html("Price");
		if (interfaces[ui])
			for (var e in interfaces[ui]) $(interfaces[ui][e]).show();
		if (type.val() == "Donation") {
			priceLabel.html("Amount");
			tax.attr('checked','true').change();
		}
	});
	
	// Optional input's checkbox toggle behavior
	salepriceToggle.change(function () {
		salepriceStatus.toggle();
		salepriceField.toggle();
	}).click(function () {
		if (this.checked) saleprice.focus().select();
	});

	shippingToggle.change(function () {
		shippingStatus.toggle();
		shippingFields.toggle();
	}).click(function () {
		if (this.checked) weight.focus().select();
	});
	
	inventoryToggle.change(function () {
		inventoryStatus.toggle();
		inventoryField.toggle();
	}).click(function () {
		if (this.checked) stock.focus().select();
	});
	
	price.change(function() { this.value = asMoney(this.value); }).change();
	saleprice.change(function() { this.value = asMoney(this.value); }).change();
	shippingfee.change(function() { this.value = asMoney(this.value); }).change();
	
	// Set the context for the db
	if (data && data.context) context.val(data.context);
	else context.val('product');
	
	// Set field values if we are rebuilding a priceline from 
	// database data
	if (data && data.label) {
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
			downloadFile.attr('class','file '+data.filedata.mimetype).html(data.filename+'<br /><small>'+readableFileSize(data.filesize)+'</small>').click(function () {
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

function readableFileSize (size) {
	var units = new Array("bytes","KB","MB","GB");
	var sized = size;
	if (sized == 0) return sized;
	var unit = 0;
	while (sized > 1000) {
		sized = sized/1024;
		unit++;
	}
	return sized.toFixed(2)+" "+units[unit];
}


/**
 * Image Uploads using SWFUpload or the jQuery plugin One Click Upload
 **/
function ImageUploads (params) {
	var swfu;
	
	var settings = {
		button_text: '<span class="button">Add New Image</span>',
		button_text_style: '.button { text-align: center; font-family:"Lucida Grande","Lucida Sans Unicode",Tahoma,Verdana,sans-serif; font-size: 9px; color: #333333; }',
		button_text_top_padding: 4,
		button_height: "24",
		button_width: "132",
		button_image_url: siteurl+'/wp-includes/images/upload.png',
		button_placeholder_id: "swf-uploader-button",
		upload_url : siteurl+'/wp-admin/admin-ajax.php?action=wp_ajax_shopp_add_image',
		flash_url : siteurl+'/wp-includes/js/swfupload/swfupload.swf',
		file_queue_limit : 1,
		file_size_limit : filesizeLimit+'b',
		file_types : "*.jpg;*.jpeg;*.png;*.gif",
		file_types_description : "Web-compatible Image Files",
		file_upload_limit : filesizeLimit,
		post_params : params,

		swfupload_element_id : "swf-uploader",
		degraded_element_id : "browser-uploader",

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
		debug: false
		
	}
	
	// Initialize image uploader
	if (swfu20) settings.flash_url = siteurl+'/wp-includes/js/swfupload/swfupload_f9.swf';
	swfu = new SWFUpload(settings);

	var browserImageUploader = $('#image-upload').upload({
		name: 'Filedata',
		action: siteurl+'/wp-admin/admin-ajax.php?action=wp_ajax_shopp_add_image',
		enctype: 'multipart/form-data',
		params: {},
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
				alert("There was an error communicating with the server.");
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
			var img = $('<img src="?shopp_image='+image.id+'" width="96" height="96" class="handle" />').appendTo(this.targetHolder).hide();
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
	});
	
	$(window).load(function() {
		if (!swfu.loaded && !swfu20) $('#product-images .swfupload').remove();
	});
	
	if (swfu20) $("#add-image").click(function(){ swfu.selectFiles(); });
	
	if ($('#lightbox li').size() > 0) $('#lightbox').sortable({'opacity':0.8});
	$('#lightbox li button.deleteButton').each(function () {
		enableDeleteButton(this);
	});

	function swfuLoaded () {
		if (swfu20 && flash.pv[0] == 10) {
			$('#browser-uploader').show();
			$('#swf-uploader').hide();
		}
		if (!swfu20) {
			$('#browser-uploader').hide();	
			$('#swf-uploader').hide();
		} 
		this.loaded = true;
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
		// console.log(error+": "+message);
	}

	function imageUploadSuccess (file, results) {
		var image = eval('('+results+')');
		if (image.error) {
			$(this.targetHolder).remove();
			alert(image.error);
			return true;
		}
	
		$(this.targetHolder).attr({'id':'image-'+image.src});
		$(this.sorting).val(image.src);
		var img = $('<img src="?shopp_image='+image.id+'" width="96" height="96" class="handle" />').appendTo(this.targetHolder).hide();
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
			if (confirm("Are you sure you want to delete this product image?")) {
				$('#deleteImages').val(($('#deleteImages').val() == "")?$(button).val():$('#deleteImages').val()+','+$(button).val());
				$(button).parent().fadeOut(500,function() {
					$(this).remove();
				});
			}
		});
	}

}

/**
 * File upload handlers for product download files using SWFupload
 **/
function FileUploader (button,defaultButton,linenum,updates) {
	var _self = this;

	_self.settings = {
		button_text: '<span class="button">Upload File</span>',
		button_text_style: '.button { text-align: center; font-family:"Lucida Grande","Lucida Sans Unicode",Tahoma,Verdana,sans-serif; font-size: 9px; color: #333333; }',
		button_text_top_padding: 4,
		button_height: "24",
		button_width: "132",
		button_image_url: siteurl+'/wp-includes/images/upload.png',
		button_placeholder_id: button,
		flash_url : siteurl+'/wp-includes/js/swfupload/swfupload.swf',
		upload_url : siteurl+'/wp-admin/admin-ajax.php?action=wp_ajax_shopp_add_download',
		file_queue_limit : 1,
		file_size_limit : filesizeLimit+'b',
		file_types : "*.*",
		file_types_description : "All Files",
		file_upload_limit : filesizeLimit,
				
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
			progressBar : false,
		},
		debug: false
		
	}
	
	// Initialize file uploader
	if (swfu20) _self.settings.flash_url = siteurl+'/wp-includes/js/swfupload/swfupload_f9.swf';
	_self.swfu = new SWFUpload(_self.settings);
	_self.swfu.targetCell = updates;
	_self.swfu.targetLine = linenum;
	if (swfu20) defaultButton.click(function() { _self.swfu.selectFiles(); });
	
	// Handle file uploads depending on whether the Flash uploader loads or not
	$(window).load(function() {
		if (!_self.swfu.loaded || (swfu20 && flash.pv[0] == 10)) {
			$(defaultButton).parent().parent().find('.swfupload').remove();
			
			// Browser-based AJAX uploads
			defaultButton.upload({
				name: 'Filedata',
				action: siteurl+'/wp-admin/admin-ajax.php?action=wp_ajax_shopp_add_download',
				enctype: 'multipart/form-data',
				params: {},
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
					// console.log(results);
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
		}	
	});
	
	
	function swfuLoaded () {
		if (swfu20 && flash.pv[0] == 10) {
			$(defaultButton).show();
		} else {
			$(defaultButton).hide();
		}
		this.loaded = true;
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
		// console.log(results);
		var filedata = eval('('+results+')');
		if (filedata.error) {
			$(this.targetHolder).html("No download file.")
			alert(filedata.error);
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
	
}

function SlugEditor (id,type) {
	
	var _self = this;
	
	this.edit_permalink = function () {
			var i, c = 0;
			var editor = $('#editable-slug');
			var revert_editor = editor.html();
			var real_slug = $('#slug_input');
			var revert_slug = real_slug.html();
			var buttons = $('#edit-slug-buttons');
			var revert_buttons = buttons.html();
			var full = $('#editable-slug-full').html();
		
			buttons.html('<button type="button" class="save button">Save</button> <button type="button" class="cancel button">Cancel</button>');
			buttons.children('.save').click(function() {
				var slug = editor.children('input').val();
				$.post(siteurl+'/wp-admin/admin-ajax.php?action=wp_ajax_shopp_edit_slug', 
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
	
	this.enable = function () {
		$('#edit-slug-buttons').children('.edit-slug').click(function () { _self.edit_permalink() });
		$('#editable-slug').click(function() { $('#edit-slug-buttons').children('.edit-slug').click(); });		
	}
	
	this.enable();

}

/* Centralized function for browser feature detection
	- Proprietary feature detection (conditional compiling) is used to detect Internet Explorer's features
	- User agent string detection is only used when no alternative is possible
	- Is executed directly for optimal performance
*/	
var flashua = function() {
	var UNDEF = "undefined",
		OBJECT = "object",
		SHOCKWAVE_FLASH = "Shockwave Flash",
		SHOCKWAVE_FLASH_AX = "ShockwaveFlash.ShockwaveFlash",
		FLASH_MIME_TYPE = "application/x-shockwave-flash",
		EXPRESS_INSTALL_ID = "SWFObjectExprInst",
		
		win = window,
		doc = document,
		nav = navigator,
		
		domLoadFnArr = [],
		regObjArr = [],
		objIdArr = [],
		listenersArr = [],
		script,
		timer = null,
		storedAltContent = null,
		storedAltContentId = null,
		isDomLoaded = false,
		isExpressInstallActive = false;

	var w3cdom = typeof doc.getElementById != UNDEF && typeof doc.getElementsByTagName != UNDEF && typeof doc.createElement != UNDEF,
		playerVersion = [0,0,0],
		d = null;
	if (typeof nav.plugins != UNDEF && typeof nav.plugins[SHOCKWAVE_FLASH] == OBJECT) {
		d = nav.plugins[SHOCKWAVE_FLASH].description;
		if (d && !(typeof nav.mimeTypes != UNDEF && nav.mimeTypes[FLASH_MIME_TYPE] && !nav.mimeTypes[FLASH_MIME_TYPE].enabledPlugin)) { // navigator.mimeTypes["application/x-shockwave-flash"].enabledPlugin indicates whether plug-ins are enabled or disabled in Safari 3+
			d = d.replace(/^.*\s+(\S+\s+\S+$)/, "$1");
			playerVersion[0] = parseInt(d.replace(/^(.*)\..*$/, "$1"), 10);
			playerVersion[1] = parseInt(d.replace(/^.*\.(.*)\s.*$/, "$1"), 10);
			playerVersion[2] = /r/.test(d) ? parseInt(d.replace(/^.*r(.*)$/, "$1"), 10) : 0;
		}
	}
	else if (typeof win.ActiveXObject != UNDEF) {
		var a = null, fp6Crash = false;
		try {
			a = new ActiveXObject(SHOCKWAVE_FLASH_AX + ".7");
		}
		catch(e) {
			try { 
				a = new ActiveXObject(SHOCKWAVE_FLASH_AX + ".6");
				playerVersion = [6,0,21];
				a.AllowScriptAccess = "always";	 // Introduced in fp6.0.47
			}
			catch(e) {
				if (playerVersion[0] == 6) {
					fp6Crash = true;
				}
			}
			if (!fp6Crash) {
				try {
					a = new ActiveXObject(SHOCKWAVE_FLASH_AX);
				}
				catch(e) {}
			}
		}
		if (!fp6Crash && a) { // a will return null when ActiveX is disabled
			try {
				d = a.GetVariable("$version");	// Will crash fp6.0.21/23/29
				if (d) {
					d = d.split(" ")[1].split(",");
					playerVersion = [parseInt(d[0], 10), parseInt(d[1], 10), parseInt(d[2], 10)];
				}
			}
			catch(e) {}
		}
	}
	var u = nav.userAgent.toLowerCase(),
		p = nav.platform.toLowerCase(),
		webkit = /webkit/.test(u) ? parseFloat(u.replace(/^.*webkit\/(\d+(\.\d+)?).*$/, "$1")) : false, // returns either the webkit version or false if not webkit
		ie = false,
		windows = p ? /win/.test(p) : /win/.test(u),
		mac = p ? /mac/.test(p) : /mac/.test(u);
	/*@cc_on
		ie = true;
		@if (@_win32)
			windows = true;
		@elif (@_mac)
			mac = true;
		@end
	@*/
	return { w3cdom:w3cdom, pv:playerVersion, webkit:webkit, ie:ie, win:windows, mac:mac };
};