<!-- <ul class="subsubsub right">
	<li><a href="?page=shopp/customers"><?php _e('Customers','Shopp'); ?></a></li>
</ul> -->
<ul class="subsubsub">
	<li><a href="<?php echo esc_url(add_query_arg(array_merge($_GET,array('status'=>null,'id'=>null)),admin_url('admin.php'))); ?>"><?php _e('All Orders','Shopp'); ?></a></li>
	<?php 
		$StatusCounts = $this->status_counts();
		if (!empty($statusLabels)) foreach($statusLabels as $id => $label): ?>
		<li>| <a href="<?php echo esc_url(add_query_arg(array_merge($_GET,array('status'=>$id,'id'=>null)),admin_url('admin.php'))); ?>"><?php echo $label; ?></a> (<?php echo $StatusCounts[$id]; ?>)</li>
	<?php endforeach; ?>
</ul>
