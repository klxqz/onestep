<?php

class shopOnestepPluginFrontendOnestepAction extends shopFrontendAction {

    public function execute() {

        $plugin = wa()->getPlugin('onestep');
        if (!$plugin->getSettings('status')) {
            throw new waException(_ws("Page not found"), 404);
        }
        if (shopOnestepHelper::getRouteSettings(null, 'status')) {
            $route_hash = null;
            $route_settings = shopOnestepHelper::getRouteSettings();
        } elseif (shopOnestepHelper::getRouteSettings(0, 'status')) {
            $route_hash = 0;
            $route_settings = shopOnestepHelper::getRouteSettings(0);
        } else {
            throw new waException(_ws("Page not found"), 404);
        }


        $cart_action = new shopFrontendCartAction();
        $cart_action->run();
        $this->checkCart();

        $checkout_action = new shopOnestepPluginFrontendCheckoutAction();
        $checkout_action->run();

        $this->view->assign('onestep_css_url', shopOnestepHelper::getRouteTemplateUrl('onestep_css', $route_hash));
        $this->view->assign('onestep_js_url', shopOnestepHelper::getRouteTemplateUrl('onestep_js', $route_hash));

        $checkout_template = shopOnestepHelper::getRouteTemplates($route_hash, 'checkout', false);
        $this->view->assign('checkout_path', $checkout_template['template_path']);

        $onestep_template = shopOnestepHelper::getRouteTemplates($route_hash, 'onestep', false);

        $this->view->assign('settings', $route_settings);
        $html = $this->view->fetch($onestep_template['template_path']);

        $this->getResponse()->setTitle($route_settings['page_title']);
        $this->view->assign('page', array(
            'id' => 'onestep',
            'title' => $route_settings['page_title'],
            'name' => $route_settings['page_title'],
            'content' => $html,
        ));
        $this->setThemeTemplate($route_settings['page_template']);
    }

    protected function checkCart() {
        $var_cart = $this->view->getVars('cart');
        $items = &$var_cart['items'];
        $errors = array();

        $cart = new shopCart();
        $code = $cart->getCode();
        $cart_model = new shopCartItemsModel();


        if (wa()->getSetting('ignore_stock_count')) {
            $check_count = false;
        } else {
            $check_count = true;
            if (wa()->getSetting('limit_main_stock') && waRequest::param('stock_id')) {
                $check_count = waRequest::param('stock_id');
            }
        }
        $not_available_items = $cart_model->getNotAvailableProducts($code, $check_count);
        foreach ($not_available_items as $row) {
            if ($row['sku_name']) {
                $row['name'] .= ' (' . $row['sku_name'] . ')';
            }
            if ($row['available']) {
                if ($row['count'] > 0) {
                    $errors[$row['id']] = sprintf(_w('Only %d pcs of %s are available, and you already have all of them in your shopping cart.'), $row['count'], $row['name']);
                } else {
                    $errors[$row['id']] = sprintf(_w('Oops! %s just went out of stock and is not available for purchase at the moment. We apologize for the inconvenience. Please remove this product from your shopping cart to proceed.'), $row['name']);
                }
            } else {
                $errors[$row['id']] = sprintf(_w('Oops! %s is not available for purchase at the moment. Please remove this product from your shopping cart to proceed.'), $row['name']);
            }
        }

        $type_ids = array();

        foreach ($items as $item_id => &$item) {
            if ($item['type'] == 'product') {
                $type_ids[] = $item['product']['type_id'];

                if (!$item['quantity'] && !isset($errors[$item_id])) {
                    $errors[$item_id] = _w('Oops! %s is not available for purchase at the moment. Please remove this product from your shopping cart to proceed.');
                }

                if (isset($errors[$item_id])) {
                    $item['error'] = $errors[$item_id];
                    if (strpos($item['error'], '%s') !== false) {
                        $item['error'] = sprintf($item['error'], $item['product']['name'] . ($item['sku_name'] ? ' (' . $item['sku_name'] . ')' : ''));
                    }
                }
            }
        }
        unset($item);
        $this->view->assign('cart', $var_cart);
    }

}
