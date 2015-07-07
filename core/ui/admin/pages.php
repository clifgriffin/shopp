		<div class="shopp-pages-menu-item taxonomydiv" id="shopp-pages-menu-item">
			<div id="tabs-panel-shopp-pages" class="tabs-panel tabs-panel-active">

				<ul class="categorychecklist form-no-clear">

				<?php
					foreach ( ShoppPages() as $name => $Page ):
						$navmenu_placeholder = 0 > $navmenu_placeholder ? $navmenu_placeholder - 1 : -1;
				?>
					<li>
						<label class="menu-item-title">
						<input type="checkbox" name="menu-item[<?php esc_html_e( $navmenu_placeholder ) ?>][menu-item-shopp-page]" value="<?php esc_attr_e( $Page->slug() ) ?>" class="menu-item-checkbox" /> <?php
							echo esc_html( $Page->title() );
						?></label>
						<input type="hidden" class="menu-item-db-id" name="menu-item[<?php esc_attr_e( $navmenu_placeholder ) ?>][menu-item-db-id]" value="0" />
						<input type="hidden" class="menu-item-object-id" name="menu-item[<?php esc_attr_e( $navmenu_placeholder ) ?>][menu-item-object-id]" value="<?php esc_attr_e( $name ) ?>" />
						<input type="hidden" class="menu-item-object" name="menu-item[<?php esc_attr_e( $navmenu_placeholder ) ?>][menu-item-object]" value="<?php esc_attr_e( $name ) ?>" />
						<input type="hidden" class="menu-item-parent-id" name="menu-item[<?php esc_attr_e( $navmenu_placeholder ) ?>][menu-item-parent-id]" value="0">
						<input type="hidden" class="menu-item-type" name="menu-item[<?php esc_attr_e( $navmenu_placeholder ) ?>][menu-item-type]" value="<?php esc_attr_e( ShoppPages::QUERYVAR ) ?>" />
						<input type="hidden" class="menu-item-title" name="menu-item[<?php esc_attr_e( $navmenu_placeholder ) ?>][menu-item-title]" value="<?php esc_attr_e( $Page->title() ) ?>" />

					</li>
				<?php endforeach; ?>
				</ul>

			</div>

			<p class="button-controls">
				<span class="list-controls">
					<a href="<?php echo $selecturl; ?>#shopp-pages-menu-item" class="select-all"><?php _e('Select All'); ?></a>
				</span>

				<span class="add-to-menu">
					<span class="spinner"></span>
					<input type="submit"<?php disabled( $navmenu_selected, 0 ); ?> class="button-secondary submit-add-to-menu" value="<?php esc_attr_e('Add to Menu'); ?>" name="add-shopp-menu-item" id="submit-shopp-pages-menu-item" />
				</span>
			</p>

		</div><!-- /.customlinkdiv -->