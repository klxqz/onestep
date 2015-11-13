<?php

class shopOnestepPlugin extends shopPlugin {

    protected static $steps = array();
    public static $plugin_id = array('shop', 'onestep');
    public static $default_settings = array(
        'status' => 1,
        'page_url' => 'onestep/',
        'page_title' => 'Корзина',
        'desktop_only' => 0,
        'min_sum' => 0,
        'validate' => 1,
        'templates' => array(
            'onestep' => array(
                'name' => 'Главный шаблон',
                'tpl_path' => 'plugins/onestep/templates/',
                'tpl_name' => 'onestep',
                'tpl_ext' => 'html',
                'public' => false,
            ),
            'checkout' => array(
                'name' => 'Шаблон оформления заказа (checkout.html)',
                'tpl_path' => 'plugins/onestep/templates/',
                'tpl_name' => 'checkout',
                'tpl_ext' => 'html',
                'public' => false
            ),
            'cart_js' => array(
                'name' => 'cart.js',
                'tpl_path' => 'plugins/onestep/js/',
                'tpl_name' => 'cart',
                'tpl_ext' => 'js',
                'public' => true
            ),
            'checkout.contactinfo' => array(
                'name' => 'Шаблон оформления заказа - Контактная информация (checkout.contactinfo.html)',
                'tpl_path' => 'plugins/onestep/templates/',
                'tpl_name' => 'checkout.contactinfo',
                'tpl_ext' => 'html',
                'public' => false
            ),
            'checkout.shipping' => array(
                'name' => 'Шаблон оформления заказа - Доставка (checkout.shipping.html)',
                'tpl_path' => 'plugins/onestep/templates/',
                'tpl_name' => 'checkout.shipping',
                'tpl_ext' => 'html',
                'public' => false
            ),
            'checkout.payment' => array(
                'name' => 'Шаблон оформления заказа - Оплата (checkout.payment.html)',
                'tpl_path' => 'plugins/onestep/templates/',
                'tpl_name' => 'checkout.payment',
                'tpl_ext' => 'html',
                'public' => false
            ),
            'checkout.confirmation' => array(
                'name' => 'Шаблон оформления заказа - Подтверждение (checkout.confirmation.html)',
                'tpl_path' => 'plugins/onestep/templates/',
                'tpl_name' => 'checkout.confirmation',
                'tpl_ext' => 'html',
                'public' => false
            ),
        )
    );

    public function frontendHead($param) {
        $domain_settings = shopOnestep::getDomainSettings();

        if (
                !(waRequest::isMobile() && $domain_settings['desktop_only']) &&
                $this->getSettings('status') && $domain_settings['status'] &&
                wa()->getRouting()->getCurrentUrl() != 'checkout/success/' &&
                wa()->getRouting()->getCurrentUrl() != 'checkout/error/' &&
                (wa()->getRouting()->getCurrentUrl() == 'cart/' || preg_match('@^checkout/@i', wa()->getRouting()->getCurrentUrl()))
        ) {
            $onestep_url = wa()->getRouteUrl('shop/frontend/onestep');
            wa()->getResponse()->redirect($onestep_url);
        }
    }

    public function routing($route = array()) {
        $domain_settings = shopOnestep::getDomainSettings();

        $page_url = $domain_settings['page_url'];
        $page_url = rtrim($page_url, '/') . "/";
        return array(
            $page_url => 'frontend/onestep',
            'onestepcheck/' => 'frontend/check',
            $page_url . 'save/' => 'frontend/save',
            $page_url . 'delete/' => 'frontend/delete',
            $page_url . 'add/' => 'frontend/add',
        );
    }

    public static function display() {
        return false;
    }

}
