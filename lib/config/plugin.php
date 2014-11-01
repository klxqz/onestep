<?php

return array(
    'name' => 'Заказ в один шаг',
    'description' => 'Оформление заказа в один шаг на одной странице',
    'vendor' => '985310',
    'version' => '1.0.1',
    'img' => 'img/onestep.png',
    'shop_settings' => true,
    'frontend' => true,
    'handlers' => array(
        'frontend_checkout' => 'frontendCheckout'
    ),
);
//EOF
