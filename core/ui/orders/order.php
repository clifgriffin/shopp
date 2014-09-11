<div class="wrap shopp">

	<div class="icon32"></div>
	<?php if ( ShoppPurchase()->id > 0 ): ?>
		<h2><?php Shopp::_e('Order #%d', (int)$Purchase->id); ?> <a href="<?php echo esc_url(add_query_arg(array('page'=> $this->page(), 'id' => 'new'), admin_url('admin.php'))); ?>" class="add-new-h2"><?php Shopp::_e('Add New'); ?></a> </h2>
	<?php else: ?>
		<h2><?php Shopp::_e('New Order'); ?></h2>
	<?php endif; ?>

	<?php $this->notices(); ?>

	<?php include $this->ui('navigation.php'); ?>
	<br class="clear" />

				<?php
		$totalsedit = isset($_GET['edit']) && 'totals' == $_GET['edit'];

				$columns = get_column_headers($this->screen);
				$hidden = get_hidden_columns($this->screen);
		$colspan = count($columns);

			?>
	<script id="item-editor-ui" type="text/x-jquery-tmpl">
			<?php
		ob_start();
				foreach ($columns as $column => $column_title) {
			$classes = array($column, "column-$column edit");
					if ( in_array($column, $hidden) ) $classes[] = 'hidden';

			switch ( $column ) {
						case 'cb':
							?>
								<th scope='row' class='check-column'></th>
							<?php
							break;
						case 'items':
							?>
								<td class="<?php echo esc_attr(join(' ',$classes)); ?>">
						<select class="select-product edit" name="product" placeholder="<?php Shopp::_e('Search for a product&hellip;'); ?>"></select>
						<input type="text" name="itemname" value="${itemname}" size="40" id="edit-item" tabindex="1" /><button class="shoppui-th-list choose-product" title="<?php Shopp::__('Select product&hellip;'); ?>" tabindex="2"><?php Shopp::__('Select product&hellip;'); ?></button>
						<input type="hidden" name="id" value="${id}" />
								<div class="controls">
								<input type="hidden" name="lineid" value="${lineid}"/>
						<input type="submit" name="cancel-edit-item" value="<?php _e('Cancel','Shopp'); ?>" class="button-secondary" tabindex="6" />
								</div>
								</td>
							<?php
							break;
						case 'qty':
							$classes[] = 'num';
							?>
						<td class="<?php echo esc_attr(join(' ',$classes)); ?>"><input type="text" name="quantity" value="${quantity}" size="5" class="selectall" id="edit-qty" tabindex="3" /></td>
							<?php
							break;
						case 'price':
							$classes[] = 'money';
							?>
						<td class="<?php echo esc_attr(join(' ',$classes)); ?>"><input type="text" name="unitprice" value="${unitprice}" size="10" class="selectall money" id="edit-unitprice" tabindex="4" /></td>
							<?php
							break;
						case 'total':
							$classes[] = 'money';
							?>
							<td class="<?php echo esc_attr(join(' ',$classes)); ?>">
						<input type="text" name="total" value="${totalprice}" size="10" class="money focus-edit" id="edit-total" />
								<div class="controls">
						<input type="submit" name="save-item" value="<?php _e('Save Changes','Shopp'); ?>" class="button-primary alignright" tabindex="5" />
								</div>
							</td>
							<?php
							break;
						default:
							?>
								<td class="<?php echo esc_attr(join(' ',$classes)); ?>"></td>
							<?php
							break;
					}
				}
		$itemeditor = ob_get_clean();
		echo $itemeditor;
				?>
			</script>

	<script id="total-editor-ui" type="text/x-jquery-tmpl">
	<?php
		$colspan = count($columns) - 1;
		ob_start();
	?>
	<tr class="total-editor ${type}">
		<td scope="row" colspan="<?php echo $colspan; ?>" class="label">
			<button type="button" class="delete"><span class="shoppui-minus"><span class="hidden"><?php Shopp::_e('Remove'); ?></span></span></button>
			<input type="text" name="${type}[labels][]" value="" placeholder="${placeholder}" size="20" class="selectall labeling">
		</td>
		<td class="money">
		<input type="text" name="${type}[]" value="${amount}" size="7" class="money selectall ${type}">
		<button type="button" class="add" data-label="${placeholder}" value="${type}" ><span class="shoppui-plus"><span class="hidden"><?php Shopp::_e('Add'); ?></span></span></button>
		</td>
	</tr>
	<?php
		$totaleditor = ob_get_clean();
		echo $totaleditor;
	?>
	</script>

	<div id="order">
		<form action="<?php echo ShoppAdminController::url( array('id' => ( $Purchase->id > 0 ? $Purchase->id : 'new' )) ); ?>" method="post" id="order-updates">
			<div class="title">
				<div id="titlewrap">
					<span class="date"><?php echo Shopp::_d(get_option('date_format'), $Purchase->created); ?> <small><?php echo date(get_option('time_format'),$Purchase->created); ?></small>

					<div class="alignright">

						<?php if ($Purchase->shipped): ?>
						<div class="stamp shipped<?php if ( $Purchase->isvoid() ) echo ' void'; ?>"><div class="type"><?php _e('Shipped','Shopp'); ?></div><div class="ing">&nbsp;</div></div>
						<?php endif; ?>

						<?php if ( $Purchase->ispaid() && ! $Purchase->isvoid() ): ?>
						<div class="stamp paid"><div class="type"><?php _e('Paid','Shopp'); ?></div><div class="ing">&nbsp;</div></div>
						<?php elseif ($Purchase->isvoid()): ?>
						<div class="stamp void"><div class="type"><?php _e('Void','Shopp'); ?></div><div class="ing">&nbsp;</div></div>
						<?php endif; ?>

					</div>

				</div>
			</div>

			<table class="widefat" cellspacing="0">

				<thead>
					<tr><?php ShoppUI::print_column_headers($this->screen); ?></tr>
				</thead>

				<tfoot id="order-totals"<?php if ( 'new' == $_GET['id'] ) echo ' class="order-editing"'; ?>>
				<tr class="subtotal">
					<td scope="col" class="add"><select class="add-product" name="product" placeholder="<?php Shopp::_e('Search to add a product&hellip;'); ?>"></select></td>
					<td scope="row" colspan="<?php echo $colspan - 1; ?>" class="label"><?php _e('Subtotal','Shopp'); ?></td>
					<td class="money" data-value="<?php echo $Purchase->subtotal; ?>"><?php echo money($Purchase->subtotal); ?></td>
				</tr>
				<tr class="fee<?php if ( floatval($Purchase->fees) == 0.0 ) echo ' empty'; ?>">
					<td scope="row" colspan="<?php echo $colspan; ?>" class="label"><?php _e('Fees','Shopp'); ?></td>
					<td class="money"><input type="text" id="fee-total" name="fee[]" value="<?php echo money($Purchase->fees); ?>" size="7" class="money selectall">
						<button type="button" class="add" data-label="<?php Shopp::_e('Fee Label&hellip;'); ?>" value="fee"><span class="shoppui-plus"><span class="hidden"><?php Shopp::_e('Add'); ?></span></span></button></td>
				</tr>
				<?php
					if ( count($Purchase->discounts) > 1 ) {
						foreach ($Purchase->discounts as $PurchaseDiscount ) {
							$data = array(
								'${type}'  => 'discount',
								'${label}' => $PurchaseDiscount->name,
								'${amount}' => $PurchaseDiscount->amount
							);
							echo ShoppUI::template($totaleditor, $data);
						}
					}
				?>
				<tr class="discount<?php if ( floatval($Purchase->discount) == 0.0 ) echo ' empty'; ?>">
					<td scope="row" colspan="<?php echo $colspan; ?>" class="label"><?php _e('Discounts','Shopp'); ?></td>
					<td class="money"><input type="text" id="discount-total" name="discount[]" value="<?php echo money($Purchase->discount); ?>" size="7" class="money selectall">
						<button type="button" class="add" data-label="<?php Shopp::_e('Discount Label&hellip;'); ?>" value="discount"><span class="shoppui-plus"><span class="hidden"><?php Shopp::_e('Add'); ?></span></span></button>
						<?php if ( $Purchase->discounts() ): ?>
						<ul class="promos">
						<?php foreach ( $Purchase->discounts as $id => $Discount ): ?>
							<li><small><a href="<?php echo esc_url( add_query_arg(array('page' => $this->Admin->pagename('discounts'), 'id' => $id), admin_url('admin.php'))); ?>"><?php echo esc_html($Discount->name); ?></a><?php if ( isset($Discount->code) ) echo " - " . esc_html($Discount->code); ?></small></li>
						<?php endforeach; ?>
						</ul>
						<?php endif; ?>

						</td>
				</tr>
				<tr class="shipping<?php if ( floatval($Purchase->freight) == 0.0 && empty($Purchase->shipoption) ) echo ' empty'; ?>">
					<td scope="row" colspan="<?php echo $colspan; ?>" class="label shipping">
						<span class="method"><?php echo apply_filters('shopp_order_manager_shipping_method',$Purchase->shipoption); ?></span> <?php _e('Shipping','Shopp'); ?></td>
					<td class="money"><input type="text" id="shipping-total" name="shipping[]" value="<?php echo money($Purchase->freight); ?>"size="7" class="money selectall shipping">
						<button type="button" class="add" data-label="<?php Shopp::_e('Shipping Label&hellip;'); ?>" value="shipping"><span class="shoppui-plus"><span class="hidden"><?php Shopp::_e('Add'); ?></span></span></button></td>
				</tr>
				<?php
					if ( count($Purchase->taxes) > 1 ) {
						foreach ( $Purchase->taxes as $PurchaseTax ) {
							$data = array(
								'${type}'  => 'tax',
								'${placeholder}' => Shopp::__('Tax Label&hellip;'),
								'${label}' => $PurchaseTax->name,
								'${amount}' => $PurchaseTax->amount
							);
							echo ShoppUI::template($totaleditor, $data);
						}
					}
				?>
				<tr class="tax<?php if ( floatval($Purchase->tax) == 0.0 ) echo ' empty'; ?>">
					<td scope="row" colspan="<?php echo $colspan; ?>" class="label"><?php _e('Tax','Shopp'); ?></td>
					<td class="money"><input type="text" id="tax-total" name="tax[]" value="<?php echo money($Purchase->tax); ?>" size="7" class="money selectall tax"><button class="shoppui-calculator" id="calculate-tax" title="<?php Shopp::__('Calculate Tax&hellip;'); ?>" tabindex="2"><?php Shopp::__('Calculate Tax&hellip;'); ?></button>
					<button type="button" class="add" data-label="<?php Shopp::_e('Tax Label&hellip;'); ?>" value="tax"><span class="shoppui-plus"><span class="hidden"><?php Shopp::_e('Add'); ?></span></span></button>
				</td>
				</tr>
				<tr class="total">
					<td scope="row" colspan="<?php echo $colspan; ?>" class="label"><?php _e('Total','Shopp'); ?></td>
					<td class="money"><input type="text" id="order-total" name="total" value="<?php echo money($Purchase->total); ?>" size="7" class="money selectall">
						<input type="submit" id="save-totals" name="save-totals" value="<?php Shopp::_e('Save'); ?>" class="button-primary"></td>
				</tr>

				</tfoot>

				<tbody id="order-items" class="list items">
				<?php if ( count($Purchase->purchased) > 0 ): ?>
					<?php
					$columns = get_column_headers($this->screen);
					$hidden = get_hidden_columns($this->screen);

					$even = false;
					foreach ($Purchase->purchased as $id => $Item):
						$itemname = $Item->name . ( ! empty($Item->optionlabel) ?" ($Item->optionlabel)" : '');
						$taxrate = round($Item->unittax/$Item->unitprice,4);
						$editing = isset($_GET['editline']) && (int)$_GET['editline'] == $id;
						$rowclasses = array("lineitem-$id");
						if ( $editing ) $rowclasses[] = 'editing';
						if ( ! $even ) $rowclasses[] = 'alternate';
						$even = ! $even;
					?>
						<tr class="<?php echo esc_attr(join(' ', $rowclasses)); ?>">
					<?php
						if ( $editing ) {
							$data = array(
								'${lineid}'     => (int)$_GET['editline'],
								'${itemname}'   => $itemname,
								'${quantity}'   => $Item->quantity,
								'${unitprice}'  => money($Item->unitprice),
								'${totalprice}' => money($Item->total)
							);
							echo ShoppUI::template($itemeditor, $data);
						} else {

							foreach ($columns as $column => $column_title) {
								$classes = array($column, "column-$column");
								if ( in_array($column, $hidden) ) $classes[] = 'hidden';

								ob_start();
								switch ( $column ) {
									case 'items':
									ShoppProduct( new ShoppProduct($Item->product) ); // @todo Find a way to make this more efficient by loading product slugs with load_purchased()?
									$viewurl = shopp('product.get-url');
									$editurl = ShoppAdminController::url( array('id' => $Purchase->id, 'editline'=> $id) );
									$rmvurl = ShoppAdminController::url( array('id' => $Purchase->id, 'rmvline'=> $id) );
									$producturl = add_query_arg( array('page' => 'shopp-products', 'id' => $Item->product), admin_url('admin.php') );
										?>
											<td class="<?php echo esc_attr(join(' ',$classes)); ?>">
												<a href="<?php echo $producturl; ?>">
	                                                <?php
	                                                $Product = new ShoppProduct($Item->product);
	                                                $Product->load_data( array('images') );
	                                                $Image = reset($Product->images);
	                                                $image_id = apply_filters('shopp_order_item_image_id', $Image->id, $Item, $Product);

	                                                if ( ! empty($Image) ) { ?>
	                                                    <img src="?siid=<?php echo $image_id ?>&amp;<?php echo $Image->resizing(38, 0, 1) ?>" width="38" height="38" class="alignleft" />
	                                                <?php
	                                                }
	                                                echo apply_filters('shopp_purchased_item_name', $itemname); ?>
	                                            </a>
												<div class="row-actions">
													<span class='edit'><a href="<?php echo $editurl; ?>" title="<?php _e('Edit','Shopp'); ?> &quot;<?php echo esc_attr($Item->name); ?>&quot;"><?php _e('Edit','Shopp'); ?></a> | </span>
													<span class='delete'><a href="<?php echo $rmvurl; ?>" title="<?php echo esc_attr(sprintf(__('Remove %s from the order','Shopp'), "&quot;$Item->name&quot;")); ?>" class="delete"><?php _e('Remove','Shopp'); ?></a> | </span>
													<span class='view'><a href="<?php echo $viewurl;  ?>" title="<?php _e('View','Shopp'); ?> &quot;<?php echo esc_attr($Item->name); ?>&quot;" target="_blank"><?php _e('View','Shopp'); ?></a></span>
												</div>

												<?php if ( (is_array($Item->data) && ! empty($Item->data))  || ! empty($Item->sku) || (! empty($Item->addons) && 'no' != $Item->addons) ): ?>
												<ul>
												<?php if (!empty($Item->sku)): ?><li><small><?php _e('SKU','Shopp'); ?>: <strong><?php echo $Item->sku; ?></strong></small></li><?php endif; ?>

												<?php if ( isset($Item->addons) && isset($Item->addons->meta) ): ?>
													<?php foreach ( (array)$Item->addons->meta as $id => $addon ):
														if ( "inclusive" != $Purchase->taxing )
															$addonprice = $addon->value->unitprice + ( $addon->value->unitprice * $taxrate );
														else $addonprice = $addon->value->unitprice;

														?>
														<li><small><?php echo apply_filters('shopp_purchased_addon_name', $addon->name); ?><?php if ( ! empty($addon->value->sku) ) echo apply_filters('shopp_purchased_addon_sku',' [SKU: ' . $addon->value->sku . ']'); ?>: <strong><?php echo apply_filters('shopp_purchased_addon_unitprice', money($addonprice)); ?></strong></small></li>
													<?php endforeach; ?>
												<?php endif; ?>
												<?php foreach ( (array)$Item->data as $name => $value ): ?>
													<li><small><?php echo apply_filters('shopp_purchased_data_name', $name); ?>: <strong><?php echo apply_filters('shopp_purchased_data_value', $value); ?></strong></small></li>
												<?php endforeach; ?>
												<?php endif; ?>
												<?php do_action_ref_array('shopp_after_purchased_data', array($Item, $Purchase)); ?>
												</ul>
											</td>
										<?php
										break;

									case 'qty':
										$classes[] = 'num';
										?>
											<td class="<?php echo esc_attr(join(' ', $classes)); ?>"><?php echo $Item->quantity; ?></td>
										<?php
										break;

									case 'price':
									$classes[] = 'money';
										?>
											<td class="<?php echo esc_attr(join(' ', $classes)); ?>"><?php echo money($Item->unitprice); ?></td>
										<?php
										break;

									case 'total':
										$classes[] = 'money';
										?>
											<td class="<?php echo esc_attr(join(' ', $classes)); ?>"><?php echo money($Item->total); ?></td>
										<?php
										break;

									default:
										?>
											<td class="<?php echo esc_attr(join(' ', $classes)); ?>">
											<?php do_action( 'shopp_manage_order_' . sanitize_key($column) .'_column_data', $column, $Product, $Item, $Purchase ); ?>
											</td>
										<?php
										break;
								}
								$output = ob_get_contents();
								ob_end_clean();
								echo apply_filters('shopp_manage_order_' . $column . '_column', $output);
							}
						}
					?>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
			</form>


			<div id="poststuff" class="poststuff">

			<div class="meta-boxes">

				<div id="column-one" class="column left-column">
					<?php do_meta_boxes('toplevel_page_shopp-orders', 'side', $Purchase); ?>
				</div>
				<div id="main-column">
					<div id="column-two" class="column right-column">
						<?php do_meta_boxes('toplevel_page_shopp-orders', 'normal', $Purchase); ?>
					</div>
				</div>
				<br class="clear" />
			</div>

			<?php wp_nonce_field('shopp-save-order'); ?>
			<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
			<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>
			</div>
	</div> <!-- #order -->

</div>

<iframe id="print-receipt" name="receipt" src="<?php echo wp_nonce_url(admin_url('admin-ajax.php').'?action=shopp_order_receipt&amp;id='.$Purchase->id,'wp_ajax_shopp_order_receipt'); ?>" width="400" height="100" class="invisible"></iframe>

<script type="text/javascript">
/* <![CDATA[ */
var carriers   = <?php echo json_encode($carriers_json); ?>,
	noteurl    = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'), 'wp_ajax_shopp_order_note_message'); ?>',
	producturl = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'), 'wp_ajax_shopp_select_product'); ?>',
	addressurl = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'), 'wp_ajax_shopp_lookup_addresses'); ?>';

jQuery(document).ready(function($) {

<?php do_action('shopp_order_admin_script', $Purchase); ?>

});
/* ]]> */
</script>
