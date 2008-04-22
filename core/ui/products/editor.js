/**
 * editor.js
 * Product editor behaviors
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

$j=jQuery.noConflict();

var pricingOptions = new Array();
var uploader = false;

var init = function () {
	$j('#addProductOption').click(function() {
		addProductOption();
		quickSelects();
		$j("#pricing").tableDnD({onDragClass:'dragging'});
	});
	
	if ($j('#brand-menu').val() == "new") $j('#brand-menu').hide();
	else $j('#brand').hide();
	
	$j('#brand-menu').change(function () {
		if (this.value == "new") {
			$j(this).hide();
			$j('#brand').val('').show().focus();
		} else $j('#brand').val($j(this).val());
	});

	$j('#new-category input, #new-category select').hide();

	$j('#add-new-category').click(function () {
		$j('#new-category input, #new-category select').toggle();
		$j('#new-category input').focus();
		
		// Add a new category
		var name = $j('#new-category input').val();
		var parent = $j('#new-category select').val();
		if (name != "") {
			url = window.location.href.substr(0,window.location.href.indexOf('?'));
			$j.getJSON(url+"?shopp=add-category&name="+name+"&parent="+parent,function(Category) {
				addCategoryMenuItem(Category);
				addCategoryParentMenuOption(Category);

				// Reset the add new category inputs
				$j('#new-category input').val('');
				$j('#new-category select').each(function() { this.selectedIndex = 0; });
			});
			
		}
	});

	if (prices && prices.length > 0) for(i = 0; i < prices.length; i++) addProductOption(prices[i]);
	else addProductOption();

	quickSelects();
	$j('#pricing').tableDnD({onDragClass:'dragging'});
	if ($j('#lightbox li').size() > 0) $j('#lightbox').sortable();
	
	// Initialize image uploader
	var swfu = new SWFUpload({
		flash_url : siteurl+'/wp-includes/js/swfupload/swfupload_f9.swf',
		upload_url: siteurl+'/wp-admin/admin.php?shopp=add-image',
		post_params: {"product" : $j('#image-product-id').val()},
		file_queue_limit : 1,
		file_size_limit : filesizeLimit+'b',
		file_types : "*.jpg;*.gif",
		file_types_description : "Web-compatible Image Files",
		file_upload_limit : filesizeLimit,
		custom_settings : {
			targetHolder : false,
			progressBar : false,
			sorting : false
		},
		debug: false,

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
	
	$j("#add-product-image").click(function(){ swfu.selectFiles(); });
	
	$j('#product-images ul li button.deleteButton').each(function () {
		enableDeleteButton(this);
	});
	
	
	// Initialize image uploader
	uploader = new SWFUpload({
		flash_url : siteurl+'/wp-includes/js/swfupload/swfupload_f9.swf',
		upload_url: siteurl+'/wp-admin/admin.php?shopp=add-download',
		file_queue_limit : 1,
		file_size_limit : filesizeLimit+'b',
		file_types : "*.*",
		file_types_description : "All Files",
		file_upload_limit : filesizeLimit,
		custom_settings : {
			targetCell : false,
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


// Add to selection menu
var addCategoryMenuItem = function (c) {
	var parent = false;
	var name = $j('#new-category input').val();
	var parentid = $j('#new-category select').val();

	// Determine where to add on the tree (trunk, branch, leaf)
	if (parentid > 0) {
		if ($j('#category-element-'+parentid+' ~ li > ul').size() > 0)
			parent = $j('#category-element-'+parentid+' ~ li > ul');
		else {
			var ulparent = $j('#category-element-'+parentid);
			var liparent = $j('<li></li>').insertAfter(ulparent);
			parent = $j('<ul></ul>').appendTo(liparent);
		}
	} else parent = $j('#category-menu > ul');
	
	// Figure out where to insert our item amongst siblings (leaves)
	var insertionPoint = false;
	parent.children().each(function() {
		var label = $j(this).children('label').text();
		if (label && name < label) {
			insertionPoint = this;
			return false;
		}
	});
	
	// Add the category selector
	if (!insertionPoint) var li = $j('<li id="category-element-'+c.id+'"></li>').appendTo(parent);
	else var li = $j('<li id="category-element-'+c.id+'"></li>').insertBefore(insertionPoint);
	var checkbox = $j('<input type="checkbox" name="categories[]" value="'+c.id+'" id="category-'+c.id+'" checked="checked" />').appendTo(li);
	var label = $j('<label for="category-'+c.id+'"></label>').html(name).appendTo(li);
}


// Add this to new category drop-down menu
var addCategoryParentMenuOption = function (c) {
	var name = $j('#new-category input').val();
	var parent = $j('#new-category select').val();

	parent = $j('#new-category select');
	parentRel = $j('#new-category select option:selected').attr('rel').split(',');
	children = new Array();
	insertionPoint = false;

	$j('#new-category select').each(function() { 
		selected = this.selectedIndex;
		var hasChildren = false;
		for (var i = selected+1; i < this.options.length; i++) {
			var rel = $j(this.options[i]).attr('rel').split(',');
			if (new Number(parentRel[1])+1 == rel[1] && !hasChildren) hasChildren = true;
			if (hasChildren && new Number(parentRel[1])+1 != rel[1]) hasChildren = false;
			if (hasChildren) children.push(this.options[i]);
			
		}
		if (selected == 0) children = this.options;
		if (selected > 0 && children.length == 0) insertionPoint = $j(this.options[selected+1]);
		
	});
	
	$j(children).each(function () {
		if (name < $j(this).text() && $j(this).val() != "0") {
			insertionPoint = this;
			return false;
		} 
	});
		
	// Pad the label
	var label = name;
	for (i = 0; i < (new Number(parentRel[1])+1); i++) label = "&nbsp;&nbsp;&nbsp;"+label;			
	
	// Add our option
	if (!insertionPoint) var option = $j('<option value="'+c.id+'" rel="'+parentRel[0]+','+(new Number(parentRel[1])+1)+'"></option>').html(label).appendTo(parent);
	else var option = $j('<option value="'+c.id+'" rel="'+parentRel[0]+','+(new Number(parentRel[1])+1)+'"></option>').html(label).insertBefore(insertionPoint);
}


var addProductOption = function (p) {
	
	i = pricingOptions.length;
	var row = $j('<tr id="row['+i+']"></tr>').addClass('form-field').appendTo('#pricing');
	var heading = $j('<th class="pricing-label"><label for="label['+i+']">Product Option</label></th>').appendTo(row);
	var label = $j('<input type="text" name="price['+i+'][label]" value="Option '+(i+1)+'" id="label['+i+']" size="16" title="Enter a name for this product option (used when showing product options)" class="selectall" tabindex="'+(i+1)+'00" />').appendTo(heading);
	var grouping = $j('<input type="text" name="price['+i+'][grouping]" value="Option Group" id="grouping['+i+']" size="16" class="selectall" tabindex="'+(i+1)+'01" />').appendTo(heading);
	
	if (optionGroups.length > 0) {
		var groupingMenu = $j('<select name="price['+i+'][groupingmenu]" id="groupingmenu['+i+']" tabindex="'+(i+1)+'01"></select>').appendTo(heading);
		$j(optionGroups).each(function(g,option) {
			$j('<option value="'+option+'">'+option+'</option>').appendTo(groupingMenu);
		});
		$j('<option value="create-new-grouping">New Option Set&hellip;</option>').appendTo(groupingMenu);
		
		groupingMenu.change(function () {
			if (groupingMenu.val() == "create-new-grouping") {
				groupingMenu.hide();
				grouping.show();
			} else grouping.val(groupingMenu.val());
			if (groupingMenu.val() == "") {
				console.log("Selected empty group name.");
				grouping.val('');
			}
		});
		grouping.hide();
	}
	
	if (i == 0) {
		label.hide();
		grouping.hide();
		groupingMenu.hide();
	} else if (i == 1) {
		$j('#label\\[0\\]').show();
		if (groupingMenu) $j('#groupingmenu\\[0\\]').show();
		else $j('#grouping\\[0\\]');
	}
		
	var myid = $j('<input type="hidden" name="price['+i+'][id]" id="id['+i+']" />').appendTo(heading);
	var productid = $j('<input type="hidden" name="price['+i+'][product]" id="product['+i+']" />').appendTo(heading);
	var sortorder = $j('<input type="hidden" name="sortorder[]" value="'+i+'" />').appendTo(heading);

	var dataCell = $j('<td/>').appendTo(row);
	var deleteButton = $j('<button id="deleteButton['+i+']" class="deleteButton" type="button" title="Delete product option&hellip;" tabindex="'+(i+1)+'99"></button>').appendTo(dataCell).hide();
	var deleteIcon = $j('<img src="'+rsrcdir+'/core/ui/icons/delete.png" width="16" height="16" title="Delete product option&hellip;" />').appendTo(deleteButton);

	var pricingTable = $j('<table/>').addClass('pricing-table').appendTo(dataCell);

	var headingsRow = $j('<tr/>').appendTo(pricingTable);	
	var inputsRow = $j('<tr/>').appendTo(pricingTable);
	
	var typeHeading = $j('<th><label for="sku['+i+']" title="Stock Keeping Unit">SKU</label> &amp; <label for="type['+i+']" title="Type of product">Type</label></th>').appendTo(headingsRow);
	var skutypeCell = $j('<td/>').appendTo(inputsRow);
	var sku = $j('<input type="text" name="price['+i+'][sku]" id="sku['+i+']" size="10" title="Enter a unique tracking number for this product option." class="selectall" tabindex="'+(i+1)+'01" />').appendTo(skutypeCell);
	$j('<br />').appendTo(skutypeCell);
	var type = $j('<select name="price['+i+'][type]" id="type['+i+']" tabindex="'+(i+1)+'02"></select>').appendTo(skutypeCell);
	$j(optionTypes).each(function (t,name) {
		var option = $j('<option>'+name+'</option>').appendTo(type);
	});

	var priceHeading = $j('<th></th>').appendTo(headingsRow);
	var priceLabel = $j('<label for="price['+i+']">Price</label>').appendTo(priceHeading);
	var priceCell = $j('<td/>').appendTo(inputsRow);
	var price = $j('<input type="text" name="price['+i+'][price]" id="price['+i+']" value="0" size="10" class="selectall right" tabindex="'+(i+1)+'03" />').appendTo(priceCell);
	$j('<br />').appendTo(priceCell);
	var tax = $j('<input type="checkbox" name="price['+i+'][tax]" id="tax['+i+']" tabindex="'+(i+1)+'04" />').appendTo(priceCell);
	var taxLabel = $j('<label for="tax['+i+']"> Not Taxable</label><br />').appendTo(priceCell);

	var salepriceHeading = $j('<th><label for="sale['+i+']"> Sale Price</label></th>').appendTo(headingsRow);
	var salepriceToggle = $j('<input type="checkbox" name="price['+i+'][sale]" id="sale['+i+']" tabindex="'+(i+1)+'05" />').prependTo(salepriceHeading);
	var salepriceCell = $j('<td/>').appendTo(inputsRow);
	var salepriceStatus = $j('<span id="test['+i+']">Not on Sale</span>').addClass('status').appendTo(salepriceCell);
	var salepriceField = $j('<span/>').addClass('fields').appendTo(salepriceCell).hide();
	var saleprice = $j('<input type="text" name="price['+i+'][saleprice]" id="saleprice['+i+']" size="10" class="selectall right" tabindex="'+(i+1)+'06" />').appendTo(salepriceField);
	
	var donationSpacingCell = $j('<td rowspan="2" width="58%" />').appendTo(headingsRow);
	
	var shippingHeading = $j('<th><label for="shipping['+i+']"> Shipping</label></th>').appendTo(headingsRow);
	var shippingToggle = $j('<input type="checkbox" name="price['+i+'][shipping]" id="shipping['+i+']" tabindex="'+(i+1)+'07" />').prependTo(shippingHeading);
	var shippingCell = $j('<td/>').appendTo(inputsRow);
	var shippingStatus = $j('<span>Shipping Disabled</span>').addClass('status').appendTo(shippingCell);
	var shippingFields = $j('<span/>').addClass('fields').appendTo(shippingCell).hide();
	var shippingDom = $j('<input type="text" name="price['+i+'][domship]" id="domship['+i+']" size="8" class="selectall right" tabindex="'+(i+1)+'08" />').appendTo(shippingFields);
	var shippingDomLabel = $j('<label for="domship['+i+']" title="Domestic"> Dom</label><br />').appendTo(shippingFields);
	var shippingIntl = $j('<input type="text" name="price['+i+'][intlship]" id="intlship['+i+']" size="8" class="selectall right" tabindex="'+(i+1)+'09" />').appendTo(shippingFields);
	var shippingIntlLabel = $j('<label for="intlship['+i+']" title="International"> Int\'l</label>').appendTo(shippingFields);

	var inventoryHeading = $j('<th><label for="inventory['+i+']"> Inventory</label></th>').appendTo(headingsRow);
	var inventoryToggle = $j('<input type="checkbox" name="price['+i+'][inventory]" id="inventory['+i+']" tabindex="'+(i+1)+'10" />').prependTo(inventoryHeading);
	var inventoryCell = $j('<td/>').appendTo(inputsRow);
	var inventoryStatus = $j('<span>Not Tracked</span>').addClass('status').appendTo(inventoryCell);
	var inventoryField = $j('<span/>').addClass('fields').appendTo(inventoryCell).hide();
	var stock = $j('<input type="text" name="price['+i+'][stock]" id="stock['+i+']" size="8" class="selectall" tabindex="'+(i+1)+'11" />').appendTo(inventoryField);
	var inventoryBr = $j('<br/>').appendTo(inventoryField);
	var inventoryLabel =$j('<label for="stock['+i+']">Qty in stock</label>').appendTo(inventoryField);
		
	var downloadHeading = $j('<th><label for="download['+i+']">Product Download</label></th>').appendTo(headingsRow);
	var downloadCell = $j('<td width="31%" />').appendTo(inputsRow);
	var downloadFile = $j('<span>No product download.</span>').appendTo(downloadCell);

	var uploadHeading = $j('<td rowspan="2" class="controls" width="75" />').appendTo(headingsRow);
	var uploadButton = $j('<button type="button" class="button-secondary" tabindex="'+(i+1)+'12"><small>Upload File</small></button>').appendTo(uploadHeading);
	
	uploadButton.click(function () { uploader.targetCell = downloadFile; uploader.selectFiles(); });
	
	var rowBG = row.css("background-color");
	var deletingBG = "#ffebe8";
	
	row.hover(function () {
			deleteButton.show();
		}, function () {
			deleteButton.hide();
	});
	
	deleteButton.hover (function () {
			row.animate({backgroundColor:deletingBG},250);
		},function() {
			row.animate({backgroundColor:rowBG},250);		
	});
	
	deleteButton.click(function () {
		if (pricingOptions.length > 1) {
			if (confirm("Are you sure you want to delete this product option?")) {
				row.remove();
				pricingOptions.splice(i,1);
				$j('#options').val(pricingOptions.length);
				$j('#deletePrices').val(($j('#deletePrices').val() == "")?myid.val():$j('#deletePrices').val()+','+myid.val());
			}
		}
	});
	var hideAllFields = function () {
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
	
	type.change(function () {
		hideAllFields();
		if (type.val() == "Shipped") {
			salepriceHeading.show();
			salepriceCell.show();
			shippingHeading.show();
			shippingCell.show();
			inventoryHeading.show();
			inventoryCell.show();
		}
		if (type.val() == "Download") {
			salepriceHeading.show();
			salepriceCell.show();
			downloadHeading.show();
			downloadCell.show();
			uploadHeading.show();
		}
		if (type.val() == "Donation") {
			priceLabel.html("Amount");
			donationSpacingCell.show();
			tax.each(function() { this.checked = true; }).change();
		}
		
	});
	
	salepriceToggle.change(function (e) {
		salepriceStatus.toggle();
		salepriceField.toggle();
	});

	shippingToggle.change(function () {
		shippingStatus.toggle();
		shippingFields.toggle();
	});
	
	inventoryToggle.change(function () {
		inventoryStatus.toggle();
		inventoryField.toggle();
	});
	
	price.change(function() { this.value = asMoney(this.value); }).change();
	saleprice.change(function() { this.value = asMoney(this.value); }).change();
	shippingDom.change(function() { this.value = asMoney(this.value); }).change();
	shippingIntl.change(function() { this.value = asMoney(this.value); }).change();
	
	if (p) {
		label.val(p.label);
		grouping.val(p.grouping);
		if (groupingMenu) groupingMenu.val(p.grouping);
		myid.val(p.id);
		productid.val(p.product);
		sku.val(p.sku);
		type.val(p.type);
		price.val(asMoney(p.price));

		if (p.sale == "on") salepriceToggle.each(function() { this.checked = true; }).change();
		if (p.shipping == "on") shippingToggle.each(function() { this.checked = true; }).change();
		if (p.inventory == "on") inventoryToggle.each(function() { this.checked = true; }).change();

		saleprice.val(asMoney(p.saleprice));
		shippingDom.val(asMoney(p.domship));
		shippingIntl.val(asMoney(p.intlship));
		stock.val(p.stock);

		if (p.tax == "off") tax.each(function() { this.checked = true; });
	}
	type.change();
	label.focus();
	
	pricingOptions.push(row);
	$j('#options').val(pricingOptions.length);

}


/**
 * SWFUpload Image Uploading events
 **/

var imageFileQueued = function (file) {

}

var imageFileQueueError = function (file, error, message) {
	if (error == SWFUpload.QUEUE_ERROR.QUEUE_LIMIT_EXCEEDED) {
		alert("You selected too many files to upload at one time. " + (message === 0 ? "You have reached the upload limit." : "You may upload " + (message > 1 ? "up to " + message + " files." : "only one file.")));
		return;
	}

}

var imageFileDialogComplete = function (selected, queued) {
	try {
		this.startUpload();
	} catch (ex) {
		this.debug(ex);
	}
}

var startImageUpload = function (file) {
	var cell = $j('<li id="image-uploading"></li>').appendTo($j('#lightbox'));
	var sorting = $j('<input type="hidden" name="images[]" value="" />').appendTo(cell);
	var progress = $j('<div class="progress"></div>').appendTo(cell);
	var bar = $j('<div class="bar"></div>').appendTo(progress);
	var art = $j('<div class="gloss"></div>').appendTo(progress);

	this.targetHolder = cell;
	this.progressBar = bar;
	this.sorting = sorting;
	return true;
}

var imageUploadProgress = function (file, loaded, total) {
	var progress = Math((loaded/total)*100).round();
	$j(this.progressBar).animate({'width':progress+'px'},100);
}

var imageUploadError = function (file, error, message) {

}

var imageUploadSuccess = function (file, results) {
	var image = eval('('+results+')');
	$j(this.targetHolder).attr({'id':'image-'+image.src});
	$j(this.sorting).val(image.src);
	var img = $j('<img src="'+siteurl+'/wp-admin/admin.php?lookup=asset&id='+image.id+'" width="128" height="96" />').appendTo(this.targetHolder).hide();
	var deleteButton = $j('<button type="button" name="deleteImage" value="'+image.src+'" title="Delete product image&hellip;" class="deleteButton"></button>').appendTo($j(this.targetHolder)).hide();
	var deleteIcon = $j('<img src="'+rsrcdir+'/core/ui/icons/delete.png" alt="-" width="16" height="16" />').appendTo(deleteButton);
	
	$j(this.progressBar).animate({'width':'100px'},250,function () { 
		$j(this).parent().fadeOut(500,function() {
			$j(this).remove(); 
			$j(img).fadeIn('500');
			enableDeleteButton(deleteButton);
		});
	});
}

var imageUploadComplete = function (file) {
	if ($j('#lightbox li').size() > 1) $j('#lightbox').sortable('refresh');
	else $j('#lightbox').sortable();
}

var imageQueueComplete = function (uploads) {

}

var enableDeleteButton = function (button) {
	$j(button).hide();

	$j(button).parent().hover(function() {
		$j(button).show();
	},function () {
		$j(button).hide();
	});
	
	$j(button).click(function() {
		if (confirm("Are you sure you want to delete this product image?")) {
			$j('#deleteImages').val(($j('#deleteImages').val() == "")?$j(button).val():$j('#deleteImages').val()+','+$j(button).val());
			$j(button).parent().fadeOut(500,function() {
				$j(this).remove();
			});
		}
	});
}

/**
 * SWFUpload pricing option upload file event handlers
 **/
var fileQueued = function (file) {

}

var fileQueueError = function (file, error, message) {
	if (error == SWFUpload.QUEUE_ERROR.QUEUE_LIMIT_EXCEEDED) {
		alert("You selected too many files to upload at one time. " + (message === 0 ? "You have reached the upload limit." : "You may upload " + (message > 1 ? "up to " + message + " files." : "only one file.")));
		return;
	}

}

var fileDialogComplete = function (selected, queued) {
	try {
		this.startUpload();
	} catch (ex) {
		this.debug(ex);
	}
}

var startUpload = function (file) {
	this.targetCell.html('');
	var progress = $j('<div class="progress"></div>').appendTo(this.targetCell);
	var bar = $j('<div class="bar"></div>').appendTo(progress);
	var art = $j('<div class="gloss"></div>').appendTo(progress);
	
	this.progressBar = bar;
	
	return true;
}

var uploadProgress = function (file, loaded, total) {
	var progress = Math((loaded/total)*100).round();
	$j(this.progressBar).animate({'width':progress+'px'},100);
}

var uploadError = function (file, error, message) {

}

var uploadSuccess = function (file, results) {
	var filedata = eval('('+results+')');
	var targetCell = this.targetCell;
	filedata.type = filedata.type.replace(/\//gi," ");
	$j(this.progressBar).animate({'width':'100px'},250,function () { 
		$j(this).parent().fadeOut(500,function() {
			$j(this).remove(); 
			$j(targetCell).html('<div class="file '+filedata.type+'">'+filedata.name+'<br /><small>'+Math.round((filedata.size/1024)*10)/10+' KB</small></div>');
			
		});
	});
	
	
}

var uploadComplete = function (file) {
}

var queueComplete = function (uploads) {

}
