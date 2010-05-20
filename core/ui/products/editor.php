<div class="wrap shopp"> 
	<?php if (!empty($Shopp->Notice)): ?><div id="message" class="updated fade"><p><?php echo $Shopp->Notice; ?></p></div><?php endif; ?>

	<div class="icon32"></div>
	<h2><?php _e('Product Editor','Shopp'); ?></h2> 

	<div id="ajax-response"></div> 
	<form name="product" id="product" action="<?php echo admin_url('admin.php'); ?>" method="post">
		<?php wp_nonce_field('shopp-save-product'); ?>
		<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
		<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>

		<div id="poststuff" class="metabox-holder has-right-sidebar">

			<div id="side-info-column" class="inner-sidebar">
			<?php
			do_action('submitpage_box');
			$side_meta_boxes = do_meta_boxes('shopp_page_shopp-products', 'side', $Product);
			?>
			</div>

			<div id="post-body" class="<?php echo $side_meta_boxes ? 'has-sidebar' : 'has-sidebar'; ?>">
			<div id="post-body-content" class="has-sidebar-content">

				<div id="titlediv">
					<div id="titlewrap">
						<input name="name" id="title" type="text" value="<?php echo esc_attr($Product->name); ?>" size="30" tabindex="1" autocomplete="off" />
					</div>
					<div class="inside">
						<?php if (SHOPP_PERMALINKS && !empty($Product->id)): ?>
							<div id="edit-slug-box"><strong><?php _e('Permalink','Shopp'); ?>:</strong>
							<span id="sample-permalink"><?php echo $permalink; ?><span id="editable-slug" title="<?php _e('Click to edit this part of the permalink','Shopp'); ?>"><?php echo esc_attr($Product->slug); ?></span><span id="editable-slug-full"><?php echo esc_attr($Product->slug); ?></span>/</span>
							<span id="edit-slug-buttons"><button type="button" class="edit-slug button"><?php _e('Edit','Shopp'); ?></button><?php if ($Product->published == "on"): ?><button id="view-product" type="button" class="view button"><?php _e('View','Shopp'); ?></button><?php endif; ?></span>
							</div>
						<?php else: ?>
							<?php if (!empty($Product->id)): ?>
							<div id="edit-slug-box"><strong><?php _e('Product ID','Shopp'); ?>:</strong>
							<span id="editable-slug"><?php echo $Product->id; ?></span>
							</div>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
				<div id="<?php echo user_can_richedit() ? 'postdivrich' : 'postdiv'; ?>" class="postarea">
				<?php the_editor($Product->description,'content','Description', false); ?>
				</div>
				
			<?php
			do_meta_boxes('shopp_page_shopp-products', 'normal', $Product);
			do_meta_boxes('shopp_page_shopp-products', 'advanced', $Product);
			?>

			</div>
			</div>
			
		</div> <!-- #poststuff -->
	</form>
</div>

<script type="text/javascript">
<!--
var flashuploader = <?php echo ($uploader == 'flash' && !(false !== strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'mac') && apache_mod_loaded('mod_security')))?'true':'false'; ?>;
var product = <?php echo (!empty($Product->id))?$Product->id:'false'; ?>;
var prices = <?php echo json_encode($Product->prices) ?>;
var specs = <?php echo json_encode($Product->specs) ?>;
var options = <?php echo json_encode($Product->options) ?>;
var priceTypes = <?php echo json_encode($priceTypes) ?>;
var shiprates = <?php echo json_encode($shiprates); ?>;
var buttonrsrc = '<?php echo includes_url('images/upload.png'); ?>';
var uidir = '<?php echo SHOPP_ADMIN_URI; ?>';
var siteurl = '<?php echo $Shopp->siteurl; ?>';
var canonurl = '<?php echo $Shopp->canonuri; ?>';
var adminurl = '<?php echo $Shopp->wpadminurl; ?>';
var ajaxurl = adminurl+'admin-ajax.php';
var sugg_url = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'), "wp_ajax_shopp_storage_suggestions"); ?>';
var spectemp_url = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'), "wp_ajax_shopp_spec_template"); ?>';
var opttemp_url = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'), "wp_ajax_shopp_options_template"); ?>';
var catmenu_url = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'), "wp_ajax_shopp_category_menu"); ?>';
var addcategory_url = '<?php echo wp_nonce_url($Shopp->wpadminurl."admin-ajax.php", "wp_ajax_shopp_add_category"); ?>';
var editslug_url = '<?php echo wp_nonce_url($Shopp->wpadminurl."admin-ajax.php", "wp_ajax_shopp_edit_slug"); ?>';
var fileverify_url = '<?php echo wp_nonce_url($Shopp->wpadminurl."admin-ajax.php", "wp_ajax_shopp_verify_file"); ?>';
var fileimport_url = '<?php echo wp_nonce_url($Shopp->wpadminurl."admin-ajax.php", "wp_ajax_shopp_import_file"); ?>';
var fileimportp_url = '<?php echo wp_nonce_url($Shopp->wpadminurl."admin-ajax.php", "wp_ajax_shopp_import_file_progress"); ?>';
var imageul_url = '<?php echo wp_nonce_url($Shopp->wpadminurl."admin-ajax.php", "wp_ajax_shopp_upload_image"); ?>';
var manager_page = '<?php echo $this->Admin->pagename('products'); ?>';
var editor_page = '<?php echo $this->Admin->pagename('products'); ?>';
var request = <?php echo json_encode(stripslashes_deep($_GET)); ?>;
var workflow = {'continue':editor_page, 'close':manager_page, 'new':editor_page, 'next':editor_page, 'previous':editor_page};
var worklist = <?php echo json_encode($this->products(true)); ?>;
var filesizeLimit = <?php echo wp_max_upload_size(); ?>;
var weightUnit = '<?php echo $this->Settings->get('weight_unit'); ?>';
var dimensionUnit = '<?php echo $this->Settings->get('dimension_unit'); ?>';
var storage = '<?php echo $this->Settings->get('product_storage'); ?>';
<?php chdir(WP_CONTENT_DIR); // realpath needs for relative paths ?>
var productspath = '<?php echo trailingslashit(sanitize_path(realpath($this->Settings->get('products_path')))); ?>';
var imageupload_debug = <?php echo (defined('SHOPP_IMAGEUPLOAD_DEBUG') && SHOPP_IMAGEUPLOAD_DEBUG)?'true':'false'; ?>;
var fileupload_debug = <?php echo (defined('SHOPP_FILEUPLOAD_DEBUG') && SHOPP_FILEUPLOAD_DEBUG)?'true':'false'; ?>;
var dimensionsRequired = <?php echo $Shopp->Shipping->dimensions?'true':'false'; ?>;

// Warning/Error Dialogs
var DELETE_IMAGE_WARNING = "<?php _e('Are you sure you want to delete this product image?','Shopp'); ?>";
var SERVER_COMM_ERROR = "<?php _e('There was an error communicating with the server.','Shopp'); ?>";

// Dynamic interface label translations
var LINK_ALL_VARIATIONS = "<?php _e('Link All Variations','Shopp'); ?>";
var UNLINK_ALL_VARIATIONS = "<?php _e('Unlink All Variations','Shopp'); ?>";
var LINK_VARIATIONS = "<?php _e('Link Variations','Shopp'); ?>";
var UNLINK_VARIATIONS = "<?php _e('Unlink Variations','Shopp'); ?>";
var ADD_IMAGE_BUTTON_TEXT = "<?php _e('Add New Image','Shopp'); ?>";
var UPLOAD_FILE_BUTTON_TEXT = "<?php _e('Upload&nbsp;File','Shopp'); ?>";
var SELECT_FILE_BUTTON_TEXT = "<?php _e('Select File','Shopp'); ?>";
var SAVE_BUTTON_TEXT = "<?php _e('Save','Shopp'); ?>";
var CANCEL_BUTTON_TEXT = "<?php _e('Cancel','Shopp'); ?>";
var TYPE_LABEL = "<?php _e('Type','Shopp'); ?>";
var PRICE_LABEL = "<?php _e('Price','Shopp'); ?>";
var AMOUNT_LABEL = "<?php _e('Amount','Shopp'); ?>";
var SALE_PRICE_LABEL = "<?php _e('Sale Price','Shopp'); ?>";
var NOT_ON_SALE_TEXT = "<?php _e('Not on Sale','Shopp'); ?>";
var NOTAX_LABEL = "<?php _e('Not Taxed','Shopp'); ?>";
var SHIPPING_LABEL = "<?php _e('Shipping','Shopp'); ?>";
var FREE_SHIPPING_TEXT = "<?php _e('Free Shipping','Shopp'); ?>";
var WEIGHT_LABEL = "<?php _e('Weight','Shopp'); ?>";
var LENGTH_LABEL = "<?php _e('Length','Shopp'); ?>";
var WIDTH_LABEL = "<?php _e('Width','Shopp'); ?>";
var HEIGHT_LABEL = "<?php _e('Height','Shopp'); ?>";
var DIMENSIONAL_WEIGHT_LABEL = "<?php _e('3D Weight','Shopp'); ?>";
var SHIPFEE_LABEL = "<?php _e('Handling Fee','Shopp'); ?>";
var SHIPFEE_XTRA = "<?php _e('Amount added to shipping costs for each unit ordered (for handling costs, etc)','Shopp'); ?>";
var INVENTORY_LABEL = "<?php _e('Inventory','Shopp'); ?>";
var NOT_TRACKED_TEXT = "<?php _e('Not Tracked','Shopp'); ?>";
var IN_STOCK_LABEL = "<?php _e('In Stock','Shopp'); ?>";
var OPTION_MENU_DEFAULT = "<?php _e('Option Menu','Shopp'); ?>";
var NEW_OPTION_DEFAULT = "<?php _e('New Option','Shopp'); ?>";
var ADDON_GROUP_DEFAULT = "<?php _e('Add-on Group','Shopp'); ?>";
var SKU_LABEL = "<?php _e('SKU','Shopp'); ?>";
var SKU_LABEL_HELP = "<?php _e('Stock Keeping Unit','Shopp'); ?>";
var SKU_XTRA = "<?php _e('Enter a unique stock keeping unit identification code.','Shopp'); ?>";
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
var IMAGE_DETAILS_TEXT = "<?php _e('Image Details','Shopp'); ?>";
//-->
</script>