<?php

class shopOnestepPluginSettingsAction extends waViewAction {

    protected $templates = array(
        'onestep' => array('name' => 'Главный шаблон', 'tpl_path' => 'plugins/onestep/templates/onestep.html', 'public' => false),
        'checkout' => array('name' => 'Шаблон оформления заказа (checkout.html)', 'tpl_path' => 'plugins/onestep/templates/checkout.html', 'public' => false),
        'cart_js' => array('name' => 'cart.js', 'tpl_path' => 'plugins/onestep/js/cart.js', 'public' => true),
        
        'checkout.contactinfo' => array('name' => 'Шаблон оформления заказа - Контактная информация (checkout.contactinfo.html)', 'tpl_path' => 'plugins/onestep/templates/checkout.contactinfo.html', 'public' => false),
        'checkout.shipping' => array('name' => 'Шаблон оформления заказа - Доставка (checkout.shipping.html)', 'tpl_path' => 'plugins/onestep/templates/checkout.shipping.html', 'public' => false),
        'checkout.payment' => array('name' => 'Шаблон оформления заказа - Оплата (checkout.payment.html)', 'tpl_path' => 'plugins/onestep/templates/checkout.payment.html', 'public' => false),
        'checkout.confirmation' => array('name' => 'Шаблон оформления заказа - Подтверждение (checkout.confirmation.html)', 'tpl_path' => 'plugins/onestep/templates/checkout.confirmation.html', 'public' => false),  
    );
    protected $plugin_id = array('shop', 'onestep');

    public function execute() {
        $app_settings_model = new waAppSettingsModel();
        $settings = $app_settings_model->get($this->plugin_id);


        foreach ($this->templates as &$template) {
            $template['full_path'] = wa()->getDataPath($template['tpl_path'], $template['public'], 'shop', true);
            if (file_exists($template['full_path'])) {
                $template['change_tpl'] = true;
            } else {
                $template['full_path'] = wa()->getAppPath($template['tpl_path'], 'shop');
                $template['change_tpl'] = false;
            }
            $template['template'] = file_get_contents($template['full_path']);
        }
        unset($template);


        $this->view->assign('settings', $settings);
        $this->view->assign('templates', $this->templates);
    }

}
