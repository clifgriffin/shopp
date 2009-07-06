var currencyFormat = <?php echo json_encode($base_operations['currency']['format']); ?>;
var tb_pathToImage = "<?php echo force_ssl(WP_PLUGIN_URL); ?>/shopp/core/ui/icons/loading.gif";
var CHECKOUT_REQUIRED_FIELD = "<?php _e('Your %s is required.','Shopp'); ?>";
var CHECKOUT_INVALID_EMAIL = "<?php _e('The e-mail address you provided does not appear to be a valid address.','Shopp'); ?>";
var CHECKOUT_MIN_LENGTH = "<?php _e('The %s you entered is too short. It must be at least %d characters long.','Shopp'); ?>";
var CHECKOUT_PASSWORD_MISMATCH = "<?php _e('The passwords you entered do not match. They must match in order to confirm you are correctly entering the password you want to use.','Shopp'); ?>";