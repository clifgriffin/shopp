<div class="wrap shopp">
	<div class="icon32"></div>
	<h2><?php _e('Categories','Shopp'); ?> <a href="<?php echo esc_url(add_query_arg(array_merge(stripslashes_deep($_GET),array('page'=>$this->Admin->pagename('categories'),'id'=>'new')),admin_url('admin.php'))); ?>" class="button add-new"><?php _e('New Category','Shopp'); ?></a></h2>

	<?php do_action('shopp_admin_notice'); ?>

	<form action="<?php echo esc_url($url); ?>" id="categories" method="get">
	<?php include('navigation.php'); ?>
	<div>
		<input type="hidden" name="page" value="<?php echo $this->Admin->pagename('categories'); ?>" />
	</div>

	<p id="post-search" class="search-box">
		<input type="text" id="categories-search-input" class="search-input" name="s" value="<?php echo esc_attr(stripslashes($s)); ?>" />
		<input type="submit" value="<?php _e('Search Categories','Shopp'); ?>" class="button" />
	</p>

	<div class="tablenav">
		<div class="alignleft actions">
			<button type="submit" id="delete-button" name="deleting" value="category" class="button-secondary"><?php _e('Delete','Shopp'); ?></button>
		</div>

		<?php $ListTable->pagination( 'top' ); ?>
		<div class="clear"></div>
	</div>
	<div class="clear"></div>

	<table class="widefat" cellspacing="0">
		<thead>
		<tr><?php ShoppUI::print_column_headers('shopp_page_shopp-categories'); ?></tr>
		</thead>
		<tfoot>
		<tr><?php ShoppUI::print_column_headers('shopp_page_shopp-categories',false); ?></tr>
		</tfoot>
	<?php if (sizeof($Categories) > 0): ?>
		<tbody id="categories-table" class="list categories">
		<?php
		$hidden = array();
		$hidden = get_hidden_columns('shopp_page_shopp-categories');

		$even = false;
		foreach ($Categories as $Category):

		$editurl = esc_url(add_query_arg(array_merge($_GET,
			array('page'=>$this->Admin->pagename('categories'),
					'id'=>$Category->id)),
					admin_url('admin.php')));

		$deleteurl = esc_url(add_query_arg(array_merge($_GET,
			array('page'=>$this->Admin->pagename('categories'),
					'delete[]'=>$Category->id,
					'deleting'=>'category')),
					admin_url('admin.php')));

		$CategoryName = empty($Category->name)?'('.__('no category name','Shopp').')':$Category->name;

		?>
		<tr<?php if (!$even) echo " class='alternate'"; $even = !$even; ?>>
			<th scope='row' class='check-column'><input type='checkbox' name='delete[]' value='<?php echo $Category->id; ?>' /></th>
			<td><a class='row-title' href='<?php echo $editurl; ?>' title='<?php _e('Edit','Shopp'); ?> &quot;<?php echo esc_attr($CategoryName); ?>&quot;'><?php echo str_repeat("&#8212; ",$Category->level); echo esc_html($CategoryName); ?></a>
				<div class="row-actions">
					<span class='edit'><a href="<?php echo $editurl; ?>" title="<?php _e('Edit','Shopp'); ?> &quot;<?php echo esc_attr($CategoryName); ?>&quot;"><?php _e('Edit','Shopp'); ?></a> | </span>
					<span class='delete'><a class='submitdelete' title='<?php _e('Delete','Shopp'); ?> &quot;<?php echo esc_attr($CategoryName); ?>&quot;' href="<?php echo $deleteurl; ?>" rel="<?php echo $Category->id; ?>"><?php _e('Delete','Shopp'); ?></a> | </span>
					<span class='view'><a href="<?php echo shoppurl( '' == get_option('permalink_structure') ? array('s_cat'=>$Category->id) : "category/$Category->slug" ); ?>" title="<?php _e('View','Shopp'); ?> &quot;<?php echo esc_attr($CategoryName); ?>&quot;" rel="permalink" target="_blank"><?php _e('View','Shopp'); ?></a></span>
				</div>
			</td>
			<td class="slug column-slug<?php echo in_array('links',$hidden)?' hidden':''; ?>"><?php echo $Category->slug; ?></td>
			<td width="5%" class="num products column-products<?php echo in_array('links',$hidden)?' hidden':''; ?>"><?php echo $Category->count; ?></td>
			<td width="5%" class="num templates column-templates<?php echo in_array('templates',$hidden)?' hidden':''; ?>">
				<div class="checkbox"><?php if (isset($Category->spectemplates) && 'on' == $Category->spectemplates): ?><div class="checked">&nbsp;</div><?php else: ?>&nbsp;<?php endif; ?></div>
			</td>
			<td width="5%" class="num menus column-menus<?php echo in_array('menus',$hidden)?' hidden':''; ?>">
				<div class="checkbox"><?php if (isset($Category->facetedmenus) && 'on' == $Category->facetedmenus): ?><div class="checked">&nbsp;</div><?php else: ?>&nbsp;<?php endif; ?></div>
			</td>
		</tr>
		<?php endforeach; ?>
		</tbody>
	<?php else: ?>
		<tbody><tr><td colspan="6"><?php _e('No categories found.','Shopp'); ?></td></tr></tbody>
	<?php endif; ?>
	</table>
	</form>
	<div class="tablenav">
		<?php $ListTable->pagination( 'bottom' ); ?>
		<div class="clear"></div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready( function() {

	var $ = jQuery.noConflict();

	$('#selectall').change( function() {
		$('#categories-table th input').each( function () {
			if (this.checked) this.checked = false;
			else this.checked = true;
		});
	});

	$('a.submitdelete').click(function () {
		if (confirm("<?php _e('You are about to delete this category!\n \'Cancel\' to stop, \'OK\' to delete.','Shopp'); ?>")) {
			$('<input type="hidden" name="delete[]" />').val($(this).attr('rel')).appendTo('#categories');
			$('<input type="hidden" name="deleting" />').val('category').appendTo('#categories');
			$('#categories').submit();
			return false;
		} else return false;
	});

	$('#delete-button').click(function() {
		if (confirm("<?php echo addslashes(__('Are you sure you want to delete the selected categories?','Shopp')); ?>")) {
			$('<input type="hidden" name="categories" value="list" />').appendTo($('#categories'));
			return true;
		} else return false;
	});

	pagenow = 'shopp_page_shopp-categories';
	columns.init(pagenow);

});
</script>