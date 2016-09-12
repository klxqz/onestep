<?php

try {
    $files = array(
        'plugins/onestep/js/cart.js',
        'plugins/onestep/lib/classes/shopOnestep.class.php',
        'plugins/onestep/lib/classes/shopOnestepCheckout.class.php',
        'plugins/onestep/lib/classes/shopOnestepCheckoutConfirmation.class.php',
        'plugins/onestep/lib/classes/shopOnestepCheckoutContactinfo.class.php',
        'plugins/onestep/lib/classes/shopOnestepCheckoutPayment.class.php',
        'plugins/onestep/lib/classes/shopOnestepCheckoutShipping.class.php',
        'plugins/onestep/lib/actions/shopOnestepPluginBackendSave.controller.php',
        'plugins/onestep/lib/actions/shopOnestepPluginFrontendAdd.controller.php',
        'plugins/onestep/lib/actions/shopOnestepPluginFrontendCheck.controller.php',
        'plugins/onestep/lib/actions/shopOnestepPluginFrontendDelete.controller.php',
        'plugins/onestep/lib/actions/shopOnestepPluginFrontendSave.controller.php',
    );

    foreach ($files as $file) {
        waFiles::delete(wa()->getAppPath($file, 'shop'), true);
    }
} catch (Exception $e) {
    
}