<ul class="subsubsub">
	<?php foreach($subs as $nav => $sub): if ($nav=="inventory" and $sub['total'] == "0") continue; ?>
	<li><?php echo ($nav != "all")?"| ":""; ?><a href="<?php echo esc_url(add_query_arg(array_merge($_GET,array('f'=>$sub['request'])),admin_url('admin.php'))); ?>"><?php echo $sub['label']; ?></a> (<?php echo $sub['total']; ?>)</li>
	<?php endforeach; ?>
</ul>
