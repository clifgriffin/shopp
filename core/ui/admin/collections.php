<div class="shopp-collections-menu-item taxonomydiv" id="shopp-collections-menu-item">
	<div id="tabs-panel-shopp-collections" class="tabs-panel tabs-panel-active">

		<ul class="categorychecklist form-no-clear">

		<?php
			$collections = $Shopp->Collections;
			foreach ( (array) $collections as $slug => $CollectionClass ):
				$menu = get_class_property($CollectionClass, '_menu');
				if ( ! $menu ) continue;
				$Collection = new $CollectionClass();
				$Collection->smart();
				$navmenu_placeholder = 0 > $navmenu_placeholder ? $navmenu_placeholder - 1 : -1;
		?>
			<li>
				<label class="menu-item-title">
				<input type="checkbox" name="menu-item[<?php echo $navmenu_placeholder; ?>][menu-item-shopp-collection]" value="<?php echo $slug; ?>" class="menu-item-checkbox" /> <?php
					echo esc_html( $Collection->name );
				?></label>
				<input type="hidden" class="menu-item-db-id" name="menu-item[<?php echo $navmenu_placeholder; ?>][menu-item-db-id]" value="0" />
				<input type="hidden" class="menu-item-object-id" name="menu-item[<?php echo $navmenu_placeholder; ?>][menu-item-object-id]" value="<?php echo $slug; ?>" />
				<input type="hidden" class="menu-item-object" name="menu-item[<?php echo $navmenu_placeholder; ?>][menu-item-object]" value="<?php echo $slug; ?>" />
				<input type="hidden" class="menu-item-parent-id" name="menu-item[<?php echo $navmenu_placeholder; ?>][menu-item-parent-id]" value="0">
				<input type="hidden" class="menu-item-type" name="menu-item[<?php echo $navmenu_placeholder; ?>][menu-item-type]" value="<?php echo SmartCollection::$taxon; ?>" />
				<input type="hidden" class="menu-item-title" name="menu-item[<?php echo $navmenu_placeholder; ?>][menu-item-title]" value="<?php echo $Collection->name; ?>" />

			</li>
		<?php endforeach; ?>
		<?php
			// Promo Collections
			$select = sDB::select(array(
				'table'   => ShoppDatabaseObject::tablename(ShoppPromo::$table),
				'columns' => 'SQL_CALC_FOUND_ROWS id,name',
				'where'   => array("target='Catalog'","status='enabled'"),
				'orderby' => 'created DESC'
			));

			$Promotions = sDB::query($select, 'array');
			foreach ( (array) $Promotions as $promo ):
				$slug = sanitize_title_with_dashes($promo->name);
		?>
			<li>
				<label class="menu-item-title">
				<input type="checkbox" name="menu-item[<?php echo $navmenu_placeholder; ?>][menu-item-shopp-collection]" value="<?php echo $slug; ?>" class="menu-item-checkbox" /> <?php
					echo esc_html( $promo->name );
				?></label>
				<input type="hidden" class="menu-item-db-id" name="menu-item[<?php echo $navmenu_placeholder; ?>][menu-item-db-id]" value="0" />
				<input type="hidden" class="menu-item-object-id" name="menu-item[<?php echo $navmenu_placeholder; ?>][menu-item-object-id]" value="<?php echo $slug; ?>" />
				<input type="hidden" class="menu-item-object" name="menu-item[<?php echo $navmenu_placeholder; ?>][menu-item-object]" value="<?php echo $slug; ?>" />
				<input type="hidden" class="menu-item-parent-id" name="menu-item[<?php echo $navmenu_placeholder; ?>][menu-item-parent-id]" value="0">
				<input type="hidden" class="menu-item-type" name="menu-item[<?php echo $navmenu_placeholder; ?>][menu-item-type]" value="<?php echo SmartCollection::$taxon; ?>" />
				<input type="hidden" class="menu-item-title" name="menu-item[<?php echo $navmenu_placeholder; ?>][menu-item-title]" value="<?php echo $promo->name; ?>" />

			</li>
		<?php endforeach; ?>
		</ul>

	</div>

	<p class="button-controls">
		<span class="list-controls">
			<a href="<?php echo esc_url($selecturl); ?>#shopp-collections-menu-item" class="select-all"><?php _e('Select All'); ?></a>
		</span>

		<span class="add-to-menu">
			<span class="spinner"></span>
			<input type="submit"<?php disabled( $navmenu_selected, 0 ); ?> class="button-secondary submit-add-to-menu" value="<?php esc_attr_e('Add to Menu'); ?>" name="add-shopp-menu-item" id="submit-shopp-collections-menu-item" />
		</span>
	</p>

</div><!-- /.customlinkdiv -->