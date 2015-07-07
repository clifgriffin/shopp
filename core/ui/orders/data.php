<table>
<?php
	foreach ( $Purchase->data as $name => $data ):
		if ( empty($data) ) continue;
		$classname = 'shopp_orderui_orderdata_' . sanitize_title_with_dashes($name);
		ob_start();
?>
	<tr class="<?php echo $classname; ?>">
		<th><?php ShoppAdminOrderDataBox::name($name); ?></th>
		<td><?php ShoppAdminOrderDataBox::data($name, $data); ?></td>
	</tr>
<?php
		echo apply_filters('shopp_orderui_orderdata_' . sanitize_title_with_dashes($name), ob_get_clean());
	endforeach; ?>
</table>
