<ul class="subsubsub">
	<?php foreach($subs as $name => $sub): ?>
	<li><?php echo ($name != "all")?"| ":""; ?><a href="<?php echo esc_url(add_query_arg(array_merge($_GET,array('f'=>$sub['request'])),admin_url('admin.php'))); ?>"><?php echo $sub['label']; ?></a> (<?php echo $sub['total']; ?>)</li>
	<?php endforeach; ?>
</ul>
