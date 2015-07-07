<div id="misc-publishing-actions">
	<div class="misc-pub-section misc-pub-section-last">

	<label for="discount-status"><input type="hidden" name="status" value="disabled" /><input type="checkbox" name="status" id="discount-status" value="enabled"<?php echo ($Promotion->status == "enabled")?' checked="checked"':''; ?> /> &nbsp;<?php _e('Enabled','Shopp'); ?></label>
	</div>

	<div class="misc-pub-section misc-pub-section-last">

	<div id="start-position" class="calendar-wrap"><?php
		$dateorder = Shopp::date_format_order(true);
		$previous = false;
		foreach ($dateorder as $type => $format):
			if ($previous == "s" && $type[0] == "s") continue;
	 		if ("month" == $type): ?><input type="text" name="starts[month]" id="starts-month" title="<?php _e('Month','Shopp'); ?>" size="3" maxlength="2" value="<?php echo ($Promotion->starts>1)?date("n",$Promotion->starts):''; ?>" class="selectall" /><?php elseif ("day" == $type): ?><input type="text" name="starts[date]" id="starts-date" title="<?php _e('Day','Shopp'); ?>" size="3" maxlength="2" value="<?php echo ($Promotion->starts>1)?date("j",$Promotion->starts):''; ?>" class="selectall" /><?php elseif ("year" == $type): ?><input type="text" name="starts[year]" id="starts-year" title="<?php _e('Year','Shopp'); ?>" size="5" maxlength="4" value="<?php echo ($Promotion->starts>1)?date("Y",$Promotion->starts):''; ?>" class="selectall" /><?php elseif ($type[0] == "s"): echo "/"; endif; $previous = $type[0];  endforeach; ?></div>
	<p><?php _e('Start promotion on this date.','Shopp'); ?></p>

	<div id="end-position" class="calendar-wrap"><?php
		$previous = false;
		foreach ($dateorder as $type => $format):
			if ($previous == "s" && $type[0] == "s") continue;
			if ("month" == $type): ?><input type="text" name="ends[month]" id="ends-month" title="<?php _e('Month','Shopp'); ?>" size="3" maxlength="2" value="<?php echo ($Promotion->ends>1)?date("n",$Promotion->ends):''; ?>" class="selectall" /><?php elseif ("day" == $type): ?><input type="text" name="ends[date]" id="ends-date" title="<?php _e('Day','Shopp'); ?>" size="3" maxlength="2" value="<?php echo ($Promotion->ends>1)?date("j",$Promotion->ends):''; ?>" class="selectall" /><?php elseif ("year" == $type): ?><input type="text" name="ends[year]" id="ends-year" title="<?php _e('Year','Shopp'); ?>" size="5" maxlength="4" value="<?php echo ($Promotion->ends>1)?date("Y",$Promotion->ends):''; ?>" class="selectall" /><?php elseif ($type[0] == "s"): echo "/"; endif; $previous = $type[0];  endforeach; ?></div>
	<p><?php _e('End the promotion on this date.','Shopp'); ?></p>

	</div>

</div>

<div id="major-publishing-actions">
	<input type="submit" class="button-primary" name="save" value="<?php _e('Save','Shopp'); ?>" />
</div>