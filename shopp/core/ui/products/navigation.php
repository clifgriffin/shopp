<ul class="subsubsub">
	<?php foreach($subs as $nav => $sub): 
		if ($nav=="inventory" and $sub['total'] == "0") continue; 
		$filter = isset($sub['request'])?array('f'=>$sub['request']):array('f'=>null);
	?>
	<li><?php echo ($nav != "all")?"| ":""; ?><a href="<?php echo esc_url(add_query_arg(array_merge($_GET,$filter),admin_url('admin.php'))); ?>"><?php echo $sub['label']; ?></a> (<?php echo $sub['total']; ?>)</li>
	<?php endforeach; ?>
</ul>
