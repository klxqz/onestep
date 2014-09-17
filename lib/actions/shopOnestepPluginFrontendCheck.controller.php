<?php

class shopOnestepPluginFrontendCheckController extends waJsonController {

    public function execute() {
        
        $this->response['check'] = $this->checkOrder();
    }

    public function checkOrder() {
        $app_settings_model = new waAppSettingsModel();
        $settings = $app_settings_model->get(array('shop', 'onestep'));
        
        $cart = new shopCart();
        $def_currency = wa('shop')->getConfig()->getCurrency(true);
        $cur_currency = wa('shop')->getConfig()->getCurrency(false);

        $total = $cart->total(true);
        $total = shop_currency($total, $cur_currency, $def_currency, false);
        $min_sum = $settings['min_sum'];

        if ($total < $min_sum) {
            return false;
        }
        return true;
    }

}
