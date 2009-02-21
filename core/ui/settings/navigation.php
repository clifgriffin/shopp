<ul class="subsubsub">
	<li><a href="?page=<?php echo $this->Admin->settings ?>&amp;edit=general"<?php if (empty($_GET['edit']) || $_GET['edit']=="general") echo ' class="current"'; ?>><?php _e('General','Shopp'); ?></a> |</li>
	<li><a href="?page=<?php echo $this->Admin->settings ?>&amp;edit=checkout"<?php if ($_GET['edit']=="checkout") echo ' class="current"'; ?>><?php _e('Checkout','Shopp'); ?></a> |</li>
	<li><a href="?page=<?php echo $this->Admin->settings ?>&amp;edit=payments"<?php if ($_GET['edit']=="payments") echo ' class="current"'; ?>><?php _e('Payments','Shopp'); ?></a> |</li>
	<li><a href="?page=<?php echo $this->Admin->settings ?>&amp;edit=shipping"<?php if ($_GET['edit']=="shipping") echo ' class="current"'; ?>><?php _e('Shipping','Shopp'); ?></a> |</li>
	<li><a href="?page=<?php echo $this->Admin->settings ?>&amp;edit=taxes"<?php if ($_GET['edit']=="taxes") echo ' class="current"'; ?>><?php _e('Taxes','Shopp'); ?></a> |</li>
	<li><a href="?page=<?php echo $this->Admin->settings ?>&amp;edit=presentation"<?php if ($_GET['edit']=="presentation") echo ' class="current"'; ?>><?php _e('Presentation','Shopp'); ?></a> |</li>
	<li><a href="?page=<?php echo $this->Admin->settings ?>&amp;edit=system"<?php if ($_GET['edit']=="system") echo ' class="current"'; ?>><?php _e('System','Shopp'); ?></a> |</li>
	<li><a href="?page=<?php echo $this->Admin->settings ?>&amp;edit=update"<?php if ($_GET['edit']=="update") echo ' class="current"'; ?>><?php _e('Update','Shopp'); ?></a></li>
</ul>
<br class="clear" />