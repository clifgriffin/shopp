Content-type: text/html; charset=utf-8
From: <?php shopp('purchase','email-from'); ?>
To: <?php shopp('purchase','email-to'); ?>
Subject: <?php shopp('purchase','email-subject'); ?>

<html>

<div id="header">
<h1><?php bloginfo('name'); ?></h1>
<h2><?php _e('Order','Shopp'); ?> <?php shopp('purchase','id'); ?></h2>
</div>
<div id="body">

<?php shopp('purchase','receipt'); ?>

<?php if (shopp('purchase','notpaid') && shopp('checkout','get-offline-instructions')): ?>
    <p><?php shopp('checkout','offline-instructions'); ?></p>
<?php endif; ?>

</div>

</html>