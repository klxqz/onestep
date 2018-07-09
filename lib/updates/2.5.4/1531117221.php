<?php

try {
    $files = array(
        'plugins/onestep/img/wholesale.png',
        'plugins/onestep/img/phmask.png',
    );

    foreach ($files as $file) {
        waFiles::delete(wa()->getAppPath($file, 'shop'), true);
    }
} catch (Exception $e) {
    
}