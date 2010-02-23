<div class="wrap shopp"> 
	<?php if (!empty($this->Notice)): ?><div id="message" class="updated fade"><p><?php echo $this->Notice; ?></p></div><?php endif; ?>

	<h2><?php _e('Category Editor','Shopp'); ?></h2> 

	<div id="ajax-response"></div> 
	<form name="category" id="category" action="<?php echo admin_url('admin.php'); ?>" method="post">
		<?php wp_nonce_field('shopp-save-category'); ?>

		<div id="poststuff" class="metabox-holder has-right-sidebar">

			<div id="side-info-column" class="inner-sidebar">

			<?php
			do_action('submitpage_box');
			$side_meta_boxes = do_meta_boxes('shopp_page_shopp-products', 'side', $Category);
			?>
			</div>

			<div id="post-body" class="<?php echo $side_meta_boxes ? 'has-sidebar' : 'has-sidebar'; ?>">
			<div id="post-body-content" class="has-sidebar-content">

				<div id="titlediv">
					<div id="titlewrap">
						<input name="name" id="title" type="text" value="<?php echo attribute_escape($Category->name); ?>" size="30" tabindex="1" autocomplete="off" />
					</div>
					<div class="inside">
						<?php if (SHOPP_PERMALINKS && !empty($Category->id)): ?>
						<div id="edit-slug-box"><strong><?php _e('Permalink','Shopp'); ?>:</strong>
						<span id="sample-permalink"><?php echo $permalink; ?><span id="editable-slug" title="<?php _e('Click to edit this part of the permalink','Shopp'); ?>"><?php echo attribute_escape($Category->slug); ?></span><span id="editable-slug-full"><?php echo attribute_escape($Category->slug); ?></span>/</span>
						<span id="edit-slug-buttons"><button type="button" class="edit-slug button">Edit</button></span>
						</div>
						<?php endif; ?>
					</div>
				</div>
				<div id="<?php echo user_can_richedit() ? 'postdivrich' : 'postdiv'; ?>" class="postarea">
				<?php the_editor($Category->description,'content','Description', false); ?>
				<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
				</div>
				
			<?php
			do_meta_boxes('shopp_page_shopp-products', 'normal', $Category);
			do_meta_boxes('shopp_page_shopp-products', 'advanced', $Category);
			?>

			</div>
			</div>
				
		</div> <!-- #poststuff -->
	</form>
</div>

<script type="text/javascript">
helpurl = "<?php echo SHOPP_DOCS; ?>Editing_a_Category";

var flashuploader = <?php echo ($uploader == 'flash' && !(false !== strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'mac') && apache_mod_loaded('mod_security')))?'true':'false'; ?>;
var category = <?php echo (!empty($Category->id))?$Category->id:'false'; ?>;
var details = <?php echo json_encode($Category->specs) ?>;
var priceranges = <?php echo json_encode($Category->priceranges) ?>;
var options = <?php echo json_encode($Category->options) ?>;
var prices = <?php echo json_encode($Category->prices) ?>;
var rsrcdir = '<?php echo SHOPP_PLUGINURI; ?>';
var siteurl = '<?php echo $Shopp->siteurl; ?>';
var adminurl = '<?php echo $Shopp->wpadminurl; ?>';
var ajaxurl = adminurl+'admin-ajax.php';
var addcategory_url = '<?php echo wp_nonce_url($Shopp->wpadminurl."admin-ajax.php", "shopp-ajax_add_category"); ?>';
var editslug_url = '<?php echo wp_nonce_url($Shopp->wpadminurl."admin-ajax.php", "shopp-ajax_edit_slug"); ?>';
var fileverify_url = '<?php echo wp_nonce_url($Shopp->wpadminurl."admin-ajax.php", "shopp-ajax_verify_file"); ?>';
var manager_page = '<?php echo $this->Admin->pagename('categories'); ?>';
var editor_page = '<?php echo $this->Admin->pagename('categories'); ?>';
var request = <?php echo json_encode(stripslashes_deep($_GET)); ?>;
var workflow = {'continue':editor_page, 'close':manager_page, 'new':editor_page, 'next':editor_page, 'previous':editor_page};
var worklist = <?php echo json_encode($this->categories(true)); ?>;
var filesizeLimit = <?php echo wp_max_upload_size(); ?>;
var priceTypes = <?php echo json_encode($priceTypes) ?>;
var weightUnit = '<?php echo $this->Settings->get('weight_unit'); ?>';
var storage = '<?php echo $this->Settings->get('product_storage'); ?>';
<?php chdir(WP_CONTENT_DIR); // realpath needs for relative paths ?>
var productspath = '<?php echo trailingslashit(sanitize_path(realpath($this->Settings->get('products_path')))); ?>';
var imageupload_debug = <?php echo (defined('SHOPP_IMAGEUPLOAD_DEBUG') && SHOPP_IMAGEUPLOAD_DEBUG)?'true':'false'; ?>;
var fileupload_debug = <?php echo (defined('SHOPP_FILEUPLOAD_DEBUG') && SHOPP_FILEUPLOAD_DEBUG)?'true':'false'; ?>;

// Warning/Error Dialogs
var DELETE_IMAGE_WARNING = "<?php _e('Are you sure you want to delete this category image?','Shopp'); ?>";
var SERVER_COMM_ERROR = "<?php _e('There was an error communicating with the server.','Shopp'); ?>";

// Translatable dynamic interface labels
var NEW_DETAIL_DEFAULT = "<?php _e('Detail Name','Shopp'); ?>";
var NEW_OPTION_DEFAULT = "<?php _e('New Option','Shopp'); ?>";
var FACETED_DISABLED = "<?php _e('Faceted menu disabled','Shopp'); ?>";
var FACETED_AUTO = "<?php _e('Build faceted menu automatically','Shopp'); ?>";
var FACETED_RANGES = "<?php _e('Build as custom number ranges','Shopp'); ?>";
var FACETED_CUSTOM = "<?php _e('Build from preset options','Shopp'); ?>";
var ADD_IMAGE_BUTTON_TEXT = "<?php _e('Add New Image','Shopp'); ?>";
var SAVE_BUTTON_TEXT = "<?php _e('Save','Shopp'); ?>";
var CANCEL_BUTTON_TEXT = "<?php _e('Cancel','Shopp'); ?>";
var OPTION_MENU_DEFAULT = "<?php _e('Option Menu','Shopp'); ?>";
var NEW_OPTION_DEFAULT = "<?php _e('New Option','Shopp'); ?>";

var UPLOAD_FILE_BUTTON_TEXT = "<?php _e('Upload&nbsp;File','Shopp'); ?>";
var TYPE_LABEL = "<?php _e('Type','Shopp'); ?>";
var PRICE_LABEL = "<?php _e('Price','Shopp'); ?>";
var AMOUNT_LABEL = "<?php _e('Amount','Shopp'); ?>";
var SALE_PRICE_LABEL = "<?php _e('Sale Price','Shopp'); ?>";
var NOT_ON_SALE_TEXT = "<?php _e('Not on Sale','Shopp'); ?>";
var NOTAX_LABEL = "<?php _e('Not Taxed','Shopp'); ?>";
var SHIPPING_LABEL = "<?php _e('Shipping','Shopp'); ?>";
var FREE_SHIPPING_TEXT = "<?php _e('Free Shipping','Shopp'); ?>";
var WEIGHT_LABEL = "<?php _e('Weight','Shopp'); ?>";
var SHIPFEE_LABEL = "<?php _e('Handling Fee','Shopp'); ?>";
var INVENTORY_LABEL = "<?php _e('Inventory','Shopp'); ?>";
var NOT_TRACKED_TEXT = "<?php _e('Not Tracked','Shopp'); ?>";
var IN_STOCK_LABEL = "<?php _e('In Stock','Shopp'); ?>";
var SKU_LABEL = "<?php _e('SKU','Shopp'); ?>";
var SKU_LABEL_HELP = "<?php _e('Stock Keeping Unit','Shopp'); ?>";
var DONATIONS_VAR_LABEL = "<?php _e('Accept variable amounts','Shopp'); ?>";
var DONATIONS_MIN_LABEL = "<?php _e('Amount required as minimum','Shopp'); ?>";
var PRODUCT_DOWNLOAD_LABEL = "<?php _e('Product Download','Shopp'); ?>";
var NO_PRODUCT_DOWNLOAD_TEXT = "<?php _e('No product download.','Shopp'); ?>";
var NO_DOWNLOAD = "<?php _e('No download file.','Shopp'); ?>";
var UNKNOWN_UPLOAD_ERROR = "<?php _e('An unknown error occurred. The upload could not be saved.','Shopp'); ?>";
var DEFAULT_PRICELINE_LABEL = "<?php _e('Price & Delivery','Shopp'); ?>";
var FILE_NOT_FOUND_TEXT = "<?php _e('The file you specified could not be found.','Shopp'); ?>";
var FILE_NOT_READ_TEXT = "<?php _e('The file you specified is not readable and cannot be used.','Shopp'); ?>";
var FILE_ISDIR_TEXT = "<?php _e('The file you specified is a directory and cannot be used.','Shopp'); ?>";

var productOptions = new Array();
var optionMenus = new Array();
var Pricelines = false;
var detailsidx = 1;
var variationsidx = 1;
var optionsidx = 1;
var pricingidx = 1;
var pricelevelsidx = 1;
var fileUploader = false;
var changes = false;
var saving = false;
var flashUploader = false;
var pricesPayload = false;

jQuery(document).ready(function () {	
	var $=jQuery.noConflict();
	var editslug = new SlugEditor(category,'category');
	var imageUploads = new ImageUploads($('#image-category-id').val(),'category');
	
	updateWorkflow();
	$('#category').submit(function () {
		this.action = this.action+"?"+$.param(request);
		return true;
	});
		
	$('#templates, #details-template, #details-facetedmenu, #variations-template, #variations-pricing, #price-ranges, #facetedmenus-setting').hide();
	
	$('#spectemplates-setting').change(function () {
		if (this.checked) $('#templates, #details-template, #facetedmenus-setting').show();
		else $('#details-template, #facetedmenus-setting').hide();
		if (!$('#spectemplates-setting').attr('checked') && !$('#variations-setting').attr('checked'))
			$('#templates').hide();
	}).change();

	$('#faceted-setting').change(function () {
		if (this.checked) {
			$('#details-menu').removeClass('options').addClass('menu');
			$('#details-facetedmenu, #price-ranges').show();
		} else {
			$('#details-menu').removeClass('menu').addClass('options');
			$('#details-facetedmenu, #price-ranges').hide();
		}
	}).change();
	
	$('#variations-setting').change(function () {
		if (this.checked) $('#templates, #variations-template, #variations-pricing').show();
		else $('#variations-template, #variations-pricing').hide();
		if (!$('#spectemplates-setting').attr('checked') && !$('#variations-setting').attr('checked'))
			$('#templates').hide();
	}).change();
	
	Pricelines = new Pricelines();
	if (details) for (s in details) addDetail(details[s]);
	$('#addPriceLevel').click(function() { addPriceLevel(); });	
	$('#addDetail').click(function() { addDetail(); });	
	$('#addVariationMenu').click(function() { addVariationOptionsMenu(); });
	
	$('#pricerange-facetedmenu').change(function () {
		if ($(this).val() == "custom") $('#pricerange-menu, #addPriceLevel').show();
		else $('#pricerange-menu, #addPriceLevel').hide();
	}).change();

	if (priceranges) for (key in priceranges) addPriceLevel(priceranges[key]);	
	if (options) loadVariations(options,prices);
	
	if (!category) $('#title').focus();
	
	function addPriceLevel (data) {
		var menus = $('#pricerange-menu');
		var id = pricelevelsidx++;
		var menu = new NestedMenu(id,menus,'priceranges','',data,false,
			{'axis':'y','scroll':false});
		$(menu.label).change(function (){ this.value = asMoney(this.value); }).change();
	}
	
	postboxes.add_postbox_toggles('shopp_page_shopp-categories');
	// close postboxes that should be closed
	$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
		
	function addDetail (data) {
		var menus = $('#details-menu');
		var entries = $('#details-list');
		var addOptionButton = $('#addDetailOption');
		var id = detailsidx;

		var menu = new NestedMenu(
				id,menus,
				'specs',
				NEW_DETAIL_DEFAULT,
				data,
				{target:entries,type:'list'}
		);

		menu.items = new Array();
		menu.addOption = function (data) {
		 	var option = new NestedMenuOption(menu.index,menu.itemsElement,menu.dataname,NEW_OPTION_DEFAULT,data,true);
			menu.items.push(option);
		}

		var facetedSetting = $('<li class="setting"></li>').appendTo(menu.itemsElement);
		var facetedMenu = $('<select name="specs['+menu.index+'][facetedmenu]"></select>').appendTo(facetedSetting);
		$('<option value="disabled">'+FACETED_DISABLED+'</option>').appendTo(facetedMenu);
		$('<option value="auto">'+FACETED_AUTO+'</option>').appendTo(facetedMenu);
		$('<option value="ranges">'+FACETED_RANGES+'</option>').appendTo(facetedMenu);
		$('<option value="custom">'+FACETED_CUSTOM+'</option>').appendTo(facetedMenu);
		
		if (data && data.facetedmenu) facetedMenu.val(data.facetedmenu);
		
		facetedMenu.change(function () {
			if ($(this).val() == "disabled" || $(this).val() == "auto")  {
				$(addOptionButton).hide();
				$(menu.itemsElement).find('li.option').hide();
			} else {
				$(addOptionButton).show();
				$(menu.itemsElement).find('li.option').show();
			}
		}).change();
		
		// Load up existing options
		if (data && data.options) {
			for (var i in data.options) menu.addOption(data.options[i]);
		}
		
		
		$(menu.itemsElement).sortable({'axis':'y','items':'li.option','scroll':false});
		
		menu.element.unbind('click',menu.click);
		menu.element.click(function () {
			menu.selected();
			$(addOptionButton).unbind('click').click(menu.addOption);
			$(facetedMenu).change();
		});

		detailsidx++;
	}
	
	function updateWorkflow () {
		$('#workflow').change(function () {
			setting = $(this).val();
			request.page = workflow[setting];
			request.id = category;
			if (!request.id) request.id = "new";
			if (setting == "new") request.next = setting;

			// Find previous category
			if (setting == "previous") {
				$.each(worklist,function (i,entry) {
					if (entry.id == category) {
						if (worklist[i-1]) request.next = worklist[i-1].id;
						else request.page = workflow['close'];
						return true;
					}
				});
			}

			// Find next category
			if (setting == "next") {
				$.each(worklist,function (i,entry) {
					if (entry.id == category) {
						if (worklist[i+1]) request.next = worklist[i+1].id;
						else request.page = workflow['close'];
						return true;
					}
				});
			}
		}).change();
	}
	
});


</script>