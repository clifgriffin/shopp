<div class="wrap shopp">
	<div class="icon32"></div>
	<h2><?php _e('Image Settings','Shopp'); ?> <a href="<?php echo esc_url( add_query_arg(array('page'=>$this->Admin->pagename('settings-images'),'id'=>'new'),admin_url('admin.php'))); ?>" class="button add-new"><?php _e('Add New','Shopp'); ?></a></h2>

	<form action="<?php echo esc_url($this->url); ?>" id="images" method="post">
	<div>
		<?php wp_nonce_field('shopp-settings-images'); ?>
		<input type="hidden" name="page" value="<?php echo $page; ?>" />
	</div>

	<br class="clear" />

	<script id="editor" type="text/x-jquery-tmpl">
	<?php ob_start(); ?>
	<tr class="inline-edit-row ${classnames}" id="edit-image-setting-${id}">
		<td>
		<fieldset><input type="hidden" name="id" value="${id}" /><label><input type="text" name="name" value="${name}" /><br /><?php _e('Name','Shopp'); ?></label></fieldset>
		<p class="submit">
		<a href="<?php echo $this->url; ?>" class="button-secondary cancel"><?php _e('Cancel','Shopp'); ?></a>
		</p>
		</td>
		<td class="dimensions column-dimensions">
		<fieldset><span><label><input type="text" name="width" value="${width}" size="4" class="selectall" /> &times;<br /><?php _e('Width','Shopp'); ?></label></span>
		<span><label><input type="text" name="height" value="${height}" size="4" class="selectall" /><br /><?php _e('Height','Shopp'); ?></label></span></fieldset>
		</td>
		<td class="fit column-fit">
		<label>
		<select name="fit" class="fit-menu">
		<?php foreach ($fit_menu as $index => $option): ?>
		<option value="<?php echo $index; ?>"${select_fit_<?php echo $index; ?>}><?php echo $option; ?></option>
		<?php endforeach; ?>
		</select><br /><?php _e('Fit','Shopp'); ?></label>
		</td>
		<td class="quality column-quality">
		<fieldset><label><select name="quality" class="quality-menu">
		<?php foreach ($quality_menu as $index => $option): ?>
		<option value="<?php echo $index; ?>"${select_quality_<?php echo $index; ?>}><?php echo $option; ?></option>
		<?php endforeach; ?>
		</select><br /><?php _e('Quality','Shopp'); ?></label></fieldset>
		</td>
		<td class="sharpen column-sharpen">
		<fieldset><label><input type="text" name="sharpen" value="${sharpen}" size="5" class="percentage selectall" /><br /><?php _e('Sharpen','Shopp'); ?></label></fieldset>
		<p class="submit">
		<input type="submit" class="button-primary" name="save" value="<?php _e('Save Changes','Shopp'); ?>" />
		</p>
		</td>
	</tr>
	<?php $editor = ob_get_contents(); ob_end_clean(); echo $editor; ?>
	</script>

	<table class="widefat" cellspacing="0">
		<thead>
		<tr><?php print_column_headers('shopp_page_shopp-settings-images'); ?></tr>
		</thead>
		<tfoot>
		<tr><?php print_column_headers('shopp_page_shopp-settings-images',false); ?></tr>
		</tfoot>
	<?php if (count($settings) > 0): ?>
		<tbody id="image-setting-table" class="list">
		<?php
			$hidden = get_hidden_columns('shopp_page_shopp-settings-pages');

			$even = false;


			if ('new' == $edit) {
				$editor = preg_replace('/\${\w+}/','',$editor);
				echo str_replace(array_keys($template_data),$template_data,$editor);
			}

			foreach ($settings as $setting):
				$editurl = add_query_arg(array('id'=>$setting->id),$this->url);
				$deleteurl = add_query_arg(array('delete'=>$setting->id),$this->url);

				$classes = array();
				if (!$even) $classes[] = 'alternate'; $even = !$even;

				if ($edit == $setting->id) {
					$template_data = array(
						'${id}' => $setting->id,
						'${name}' => $setting->name,
						'${width}' => $setting->width,
						'${height}' => $setting->height,
						'${sharpen}' => $setting->sharpen,
						'${select_fit_'.$setting->fit.'}' => ' selected="selected"',
						'${select_quality_'.$setting->quality.'}' => ' selected="selected"'
					);

					$editor = str_replace(array_keys($template_data),$template_data,$editor);
					$editor = preg_replace('/\${\w+}/','',$editor);
					echo $editor;
					continue;
				}

			?>
		<tr class="<?php echo join(' ',$classes); ?>" id="image-setting-<?php echo $setting->id; ?>">
			<td class="title column-title"><a class="row-title" href="<?php echo $editurl; ?>" title="<?php _e('Edit','Shopp'); ?> &quot;<?php echo esc_attr($setting->name); ?>&quot;" class="edit"><?php echo esc_html($setting->name); ?></a>
				<div class="row-actions">
					<span class='edit'><a href="<?php echo esc_url($editurl); ?>" title="<?php _e('Edit','Shopp'); ?> &quot;<?php echo esc_attr($setting->name); ?>&quot;" class="edit"><?php _e('Edit','Shopp'); ?></a> | </span><span class='delete'><a href="<?php echo esc_url($deleteurl); ?>" title="<?php _e('Delete','Shopp'); ?> &quot;<?php echo esc_attr($setting->name); ?>&quot;" class="delete"><?php _e('Delete','Shopp'); ?></a></span>
				</div>
			</td>
			<td class="dimensions column-dimensions"><?php echo esc_html("$setting->width &times; $setting->height"); ?></td>
			<td class="scaling column-scaling"><?php echo esc_html($fit_menu[$setting->fit]); ?></td>
			<td class="quality column-quality"><?php echo esc_html($quality_menu[$setting->quality]); ?></td>
			<td class="sharpen column-sharpen"><?php echo esc_html("$setting->sharpen%"); ?></td>

		</tr>
		<?php endforeach; ?>
		</tbody>
	<?php else: ?>
		<tbody><tr><td colspan="6"><?php _e('No predefined image settings available, yet.','Shopp'); ?></td></tr></tbody>
	<?php endif; ?>
	</table>

	</form>
</div>
<script type="text/javascript">
/* <![CDATA[ */
var images = <?php echo json_encode($json_settings); ?>;
/* ]]> */
</script>