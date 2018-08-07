<?php

class shopOnestepCheckoutShipping extends shopCheckoutShipping {

    public function display() {
        parent::display();
        $view = wa()->getView();
        $checkout_shipping_methods = $view->getVars('checkout_shipping_methods');
        $shipping = $view->getVars('shipping');

        if (empty($checkout_shipping_methods[$shipping['id']])) {
            $default_method = '';
            foreach ($checkout_shipping_methods as $m) {
                if (empty($m['error'])) {
                    $default_method = $m['id'];
                    break;
                }
            }
            $view->assign('shipping', array('id' => $default_method, 'rate_id' => ''));
        }
    }

}
