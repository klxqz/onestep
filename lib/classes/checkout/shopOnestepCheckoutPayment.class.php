<?php

class shopOnestepCheckoutPayment extends shopCheckoutPayment {

    public function display() {
        $plugin_model = new shopPluginModel();

        if (waRequest::param('payment_id') && is_array(waRequest::param('payment_id'))) {
            $methods = $plugin_model->getById(waRequest::param('payment_id'));
        } else {
            $methods = $plugin_model->listPlugins('payment');
        }

        $shipping = $this->getSessionData('shipping');
        if ($shipping) {
            $disabled = shopHelper::getDisabledMethods('payment', $shipping['id']);
        } else {
            $disabled = array();
        }

        $currencies = wa('shop')->getConfig()->getCurrencies();
        $selected = null;
        foreach ($methods as $key => $m) {
            $method_id = $m['id'];
            if (in_array($method_id, $disabled) || !$m['status']) {
                unset($methods[$key]);
                continue;
            }
            $plugin = shopPayment::getPlugin($m['plugin'], $m['id']);
            $plugin_info = $plugin->info($m['plugin']);
            $methods[$key]['icon'] = ifset($plugin_info['icon'], '');
            $custom_fields = $this->getCustomFields($method_id, $plugin);
            $custom_html = '';
            foreach ($custom_fields as $c) {
                $custom_html .= '<div class="wa-field">' . $c . '</div>';
            }
            $methods[$key]['custom_html'] = $custom_html;
            $allowed_currencies = $plugin->allowedCurrency();
            if ($allowed_currencies !== true) {
                $allowed_currencies = (array) $allowed_currencies;
                if (!array_intersect($allowed_currencies, array_keys($currencies))) {
                    $methods[$key]['error'] = sprintf(_w('Payment procedure cannot be processed because required currency %s is not defined in your store settings.'), implode(', ', $allowed_currencies));
                }
            }
            if (!$selected && empty($methods[$key]['error'])) {
                $selected = $method_id;
            }
        }



        $view = wa()->getView();
        $view->assign('checkout_payment_methods', $methods);

        $payment_id = $this->getSessionData('payment', $selected);
        if (!empty($methods[$payment_id])) {
            $view->assign('payment_id', $payment_id);
        } elseif (!empty($methods[$selected])) {
            $view->assign('payment_id', $selected);
        }

        $checkout_flow = new shopCheckoutFlowModel();
        $step_number = shopCheckout::getStepNumber('payment');
        // IF no errors 
        $checkout_flow->add(array(
            'step' => $step_number
        ));
        // ELSE
//        $checkout_flow->add(array(
//            'step' => $step_number,
//            'description' => ERROR MESSAGE HERE
//        ));
    }

}
