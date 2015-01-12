<div id="major-publishing-actions">
	<input type="hidden" name="id" value="<?php echo $Category->id; ?>" />
	<select name="settings[workflow]" id="workflow">
	<?php echo $workflows; ?>
	</select>
	<input type="submit" class="button-primary" name="save" value="<?php Shopp::_e('Update'); ?>" />
</div>