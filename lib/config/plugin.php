<?php

return array(
    'name' => 'Заказ в один шаг',
    'description' => 'Оформление заказа в один шаг на одной странице',
    'vendor' => '985310',
    'version' => '2.0.0',
    'img' => 'img/onestep.png',
    'shop_settings' => true,
    'frontend' => true,
    'handlers' => array(
        'frontend_head' => 'frontendHead',
        'routing' => 'routing',
    ),
);
//EOF
