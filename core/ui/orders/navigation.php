<ul class="subsubsub">
	<li><a href="<?php echo esc_url(add_query_arg(array_merge($_GET,array('status'=>null,'id'=>null)),admin_url('admin.php'))); ?>"><?php _e('All Orders','Shopp'); ?></a></li>
	<?php
		$counts = $this->status_counts();
		if (!empty($counts)) foreach($counts as $id => $state): ?>
		<li>| <a href="<?php echo esc_url(add_query_arg(array_merge($_GET,array('status'=>$id,'id'=>null)),admin_url('admin.php'))); ?>"><?php echo $state->label; ?></a> (<?php echo $state->total; ?>)</li>
	<?php endforeach; ?>
</ul>