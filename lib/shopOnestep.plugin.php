<?php

class shopOnestepPlugin extends shopPlugin {

    public static $templates = array(
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
        'onestep_css' => array(
            'name' => 'onestep.css',
            'tpl_path' => 'plugins/onestep/css/',
            'tpl_name' => 'onestep',
            'tpl_ext' => 'css',
            'public' => true
        ),
        'onestep_js' => array(
            'name' => 'onestep.js',
            'tpl_path' => 'plugins/onestep/js/',
            'tpl_name' => 'onestep',
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
    );

    public function saveSettings($settings = array()) {
        $route_hash = waRequest::post('route_hash');
        $route_settings = waRequest::post('route_settings');

        if ($routes = $this->getSettings('routes')) {
            $settings['routes'] = $routes;
        } else {
            $settings['routes'] = array();
        }
        $settings['routes'][$route_hash] = $route_settings;
        $settings['route_hash'] = $route_hash;
        parent::saveSettings($settings);


        $templates = waRequest::post('templates');
        foreach ($templates as $template_id => $template) {
            $s_template = self::$templates[$template_id];
            if (!empty($template['reset_tpl']) || waRequest::post('reset_tpl_all')) {
                $tpl_full_path = $s_template['tpl_path'] . $route_hash . '.' . $s_template['tpl_name'] . '.' . $s_template['tpl_ext'];
                $template_path = wa()->getDataPath($tpl_full_path, $s_template['public'], 'shop', true);
                @unlink($template_path);
            } else {
                $tpl_full_path = $s_template['tpl_path'] . $route_hash . '.' . $s_template['tpl_name'] . '.' . $s_template['tpl_ext'];
                $template_path = wa()->getDataPath($tpl_full_path, $s_template['public'], 'shop', true);
                if (!file_exists($template_path)) {
                    $tpl_full_path = $s_template['tpl_path'] . $s_template['tpl_name'] . '.' . $s_template['tpl_ext'];
                    $template_path = wa()->getAppPath($tpl_full_path, 'shop');
                }
                $content = file_get_contents($template_path);
                if (!empty($template['template']) && strcmp(str_replace("\r", "", $template['template']), str_replace("\r", "", $content)) != 0) {
                    $tpl_full_path = $s_template['tpl_path'] . $route_hash . '.' . $s_template['tpl_name'] . '.' . $s_template['tpl_ext'];
                    $template_path = wa()->getDataPath($tpl_full_path, $s_template['public'], 'shop', true);
                    $f = fopen($template_path, 'w');
                    if (!$f) {
                        throw new waException('Не удаётся сохранить шаблон. Проверьте права на запись ' . $template_path);
                    }
                    fwrite($f, $template['template']);
                    fclose($f);
                }
            }
        }
    }

    public function frontendHead($param) {
        if (!$this->getSettings('status')) {
            return false;
        }
        if (shopOnestepHelper::getRouteSettings(null, 'status')) {
            $route_settings = shopOnestepHelper::getRouteSettings();
        } elseif (shopOnestepHelper::getRouteSettings(0, 'status')) {
            $route_settings = shopOnestepHelper::getRouteSettings(0);
        } else {
            return false;
        }

        if (
                !(waRequest::isMobile() && !empty($route_settings['desktop_only'])) &&
                $this->getSettings('status') && $route_settings['status'] &&
                wa()->getRouting()->getCurrentUrl() != 'checkout/success/' &&
                wa()->getRouting()->getCurrentUrl() != 'checkout/error/' &&
                (((empty($route_settings['mode']) || $route_settings['mode'] != 'only_checkout') &&  wa()->getRouting()->getCurrentUrl() == 'cart/') || preg_match('@^checkout/@i', wa()->getRouting()->getCurrentUrl()))
        ) {
            $onestep_url = wa()->getRouteUrl('shop/frontend/onestep');
            wa()->getResponse()->redirect($onestep_url);
        }
    }

    public function routing($route = array()) {
        if (!$this->getSettings('status')) {
            return;
        }

        if (shopOnestepHelper::getRouteSettings(null, 'status')) {
            $route_settings = shopOnestepHelper::getRouteSettings();
        } elseif (shopOnestepHelper::getRouteSettings(0, 'status')) {
            $route_settings = shopOnestepHelper::getRouteSettings(0);
        } else {
            return;
        }

        $page_url = $route_settings['page_url'];
        $page_url = rtrim($page_url, '/') . "/";
        return array(
            $page_url => 'frontend/onestep',
            $page_url . 'save/' => 'frontend/cartSave',
            $page_url . 'delete/' => 'frontend/cartDelete',
            $page_url . 'add/' => 'frontend/cartAdd',
            $page_url . 'shipping/' => 'frontend/shipping',
        );
    }

}
