<?php 
	global $Shopp;
	$setting_pages = array_filter(array_keys($Shopp->Flow->Admin->Pages),array(&$Shopp->Flow->Admin,'get_settings_pages'));
?>
<ul class="subsubsub">
	<?php $i = 0; foreach ($setting_pages as $screen): ?>
		<li><a href="?page=<?php echo $screen; ?>"<?php if ($_GET['page'] == $screen) echo ' class="current"'; ?>><?php 
			echo $Shopp->Flow->Admin->Pages[$screen]->label;
		?></a><?php if (count($setting_pages)-1!=$i++): ?> | <?php endif; ?></li>
	<?php endforeach; ?>
</ul>
<br class="clear" />