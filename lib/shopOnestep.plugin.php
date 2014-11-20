<?php

class shopOnestepPlugin extends shopPlugin {

    protected static $steps = array();

    public function frontendCheckout($param) {
        if ($this->getSettings('status') && $param['step'] != 'success' && wa()->getRouting()->getCurrentUrl() != 'cart/') {
            $cart_url = wa()->getRouteUrl('shop/frontend/cart');
            wa()->getResponse()->redirect($cart_url);
        }
    }

    public static function display() {
        $app_settings_model = new waAppSettingsModel();
        $settings = $app_settings_model->get(array('shop', 'onestep'));
        if ($settings['status']) {
            $view = wa()->getView();
            if (self::checkCart($cart)) {
                $view->assign('cart', $cart);
            }
            self::checkout();

            $onestep_path = wa()->getDataPath('plugins/onestep/templates/onestep.html', false, 'shop', true);
            if (!file_exists($onestep_path)) {
                $onestep_path = wa()->getAppPath('plugins/onestep/templates/onestep.html', 'shop');
            }
            $checkout_path = wa()->getDataPath('plugins/onestep/templates/checkout.html', false, 'shop', true);
            if (!file_exists($checkout_path)) {
                $checkout_path = wa()->getAppPath('plugins/onestep/templates/checkout.html', 'shop');
            }
            $cart_js_path = wa()->getDataPath('plugins/onestep/js/cart.js', true, 'shop', true);
            $cart_js_url = wa()->getDataUrl('plugins/onestep/js/cart.js', true, 'shop');
            if (!file_exists($cart_js_path)) {
                $cart_js_path = wa()->getAppPath('plugins/onestep/js/cart.js', 'shop');
                $cart_js_url = wa()->getAppStaticUrl() . 'plugins/onestep/js/cart.js';
            }
            $view->assign('cart_js_url', $cart_js_url);
            $view->assign('checkout_path', $checkout_path);
            $view->assign('settings', $settings);
            $html = $view->fetch($onestep_path);
            return $html;
        }
    }

    public static function checkCart(&$cart = null) {
        $error = false;
        $cart = new shopCart();
        $code = $cart->getCode();
        $view = wa()->getView();
        if (!wa()->getSetting('ignore_stock_count')) {
            $cart_model = new shopCartItemsModel();
            $sku_model = new shopProductSkusModel();
            $product_model = new shopProductModel();

            $items = $cart->items(false);

            foreach ($items as &$item) {
                if (!isset($item['product_id'])) {
                    $sku = $sku_model->getById($item['sku_id']);
                    $product = $product_model->getById($sku['product_id']);
                } else {
                    $product = $product_model->getById($item['product_id']);
                    if (isset($item['sku_id'])) {
                        $sku = $sku_model->getById($item['sku_id']);
                    } else {
                        if (isset($item['features'])) {
                            $product_features_model = new shopProductFeaturesModel();
                            $sku_id = $product_features_model->getSkuByFeatures($product['id'], $item['features']);
                            if ($sku_id) {
                                $sku = $sku_model->getById($sku_id);
                            } else {
                                $sku = null;
                            }
                        } else {
                            $sku = $sku_model->getById($product['sku_id']);
                            if (!$sku['available']) {
                                $sku = $sku_model->getByField(array('product_id' => $product['id'], 'available' => 1));
                            }

                            if (!$sku) {
                                $item['error'] = _w('This product is not available for purchase');
                                $error = true;
                            }
                        }
                    }
                }

                $quantity = $item['quantity'];
                $c = $cart_model->countSku($code, $sku['id']);
                if ($sku['count'] !== null && $c + $quantity > $sku['count']) {
                    $quantity = $sku['count'] - $c;
                    $name = $product['name'] . ($sku['name'] ? ' (' . $sku['name'] . ')' : '');
                    if ($quantity < 0) {
                        $item['error'] = sprintf(_w('Only %d pcs of %s are available, and you already have all of them in your shopping cart.'), $sku['count'], $name);
                        $error = true;
                    }
                }
            }
            unset($item);
            foreach ($items as $item_id => $item) {
                $price = shop_currency($item['price'] * $item['quantity'], $item['currency'], null, false);
                if (isset($item['services'])) {
                    foreach ($item['services'] as $s) {
                        if (!empty($s['id'])) {
                            if (isset($s['variants'])) {
                                $price += shop_currency($s['variants'][$s['variant_id']]['price'] * $item['quantity'], $s['currency'], null, false);
                            } else {
                                $price += shop_currency($s['price'] * $item['quantity'], $s['currency'], null, false);
                            }
                        }
                    }
                }
                $items[$item_id]['full_price'] = $price;
            }


            $cart = array(
                'items' => $items,
                'total' => $cart->total(false),
                'count' => $cart->count()
            );
        }
        return $error;
    }

    public static function checkout() {
        $view = wa()->getView();
        $steps = wa()->getConfig()->getCheckoutSettings();

        $cart = new shopCart();
        if (!$cart->count()) {
            return false;
        }

        if (waRequest::method() == 'post') {
            if (waRequest::post('wa_auth_login')) {
                $login_action = new shopLoginAction();
                $login_action->run();
            } else {
                $error = false;
                foreach ($steps as $step_id => $step) {
                    $step_instance = self::getStep($step_id);
                    if (!$step_instance->execute()) {
                        $error = true;
                    }
                }


                if (waRequest::post('confirmation') && !$error && !self::checkCart()) {
                    if (self::createOrder()) {
                        wa()->getResponse()->redirect(wa()->getRouteUrl('/frontend/checkout', array('step' => 'success')));
                    }
                }
            }
        }
        $checkout_tpls = array();
        foreach ($steps as $step_id => $step) {
            $steps[$step_id]['content'] = self::getStep($step_id)->display();
            /**
             * @event frontend_checkout
             * @return array[string]string $return[%plugin_id%] html output
             */
            $event_params = array('step' => $step_id);
            $view->assign('frontend_checkout', wa()->event('frontend_checkout', $event_params));

            $step_tpl_path = wa()->getDataPath('plugins/onestep/templates/checkout.' . $step_id . '.html', false, 'shop', true);
            if (!file_exists($step_tpl_path)) {
                $step_tpl_path = wa()->getAppPath('plugins/onestep/templates/checkout.' . $step_id . '.html', 'shop');
            }

            $step_tpl = $view->fetch($step_tpl_path);
            $checkout_tpls[$step_id] = $step_tpl;
        }
        $view->assign('checkout_tpls', $checkout_tpls);
        $view->assign('checkout_steps', $steps);
    }

    protected function createOrder() {
        $checkout_data = wa()->getStorage()->get('shop/checkout');

        $contact = wa()->getUser()->isAuth() ? wa()->getUser() : $checkout_data['contact'];
        $cart = new shopCart();
        $items = $cart->items(false);
        // remove id from item
        foreach ($items as &$item) {
            unset($item['id']);
            unset($item['parent_id']);
        }
        unset($item);

        $order = array(
            'contact' => $contact,
            'items' => $items,
            'total' => $cart->total(false),
            'params' => isset($checkout_data['params']) ? $checkout_data['params'] : array(),
        );

        $order['discount'] = shopDiscounts::apply($order);

        if (isset($checkout_data['shipping'])) {
            $order['params']['shipping_id'] = $checkout_data['shipping']['id'];
            $order['params']['shipping_rate_id'] = $checkout_data['shipping']['rate_id'];
            $shipping_step = new shopCheckoutShipping();
            $rate = $shipping_step->getRate($order['params']['shipping_id'], $order['params']['shipping_rate_id']);
            $order['params']['shipping_plugin'] = $rate['plugin'];
            $order['params']['shipping_name'] = $rate['name'];
            if (isset($rate['est_delivery'])) {
                $order['params']['shipping_est_delivery'] = $rate['est_delivery'];
            }
            if (!isset($order['shipping'])) {
                $order['shipping'] = $rate['rate'];
            }
            if (!empty($order['params']['shipping'])) {
                foreach ($order['params']['shipping'] as $k => $v) {
                    $order['params']['shipping_params_' . $k] = $v;
                }
                unset($order['params']['shipping']);
            }
        } else {
            $order['shipping'] = 0;
        }

        if (isset($checkout_data['payment'])) {
            $order['params']['payment_id'] = $checkout_data['payment'];
            $plugin_model = new shopPluginModel();
            $plugin_info = $plugin_model->getById($checkout_data['payment']);
            $order['params']['payment_name'] = $plugin_info['name'];
            $order['params']['payment_plugin'] = $plugin_info['plugin'];
            if (!empty($order['params']['payment'])) {
                foreach ($order['params']['payment'] as $k => $v) {
                    $order['params']['payment_params_' . $k] = $v;
                }
                unset($order['params']['payment']);
            }
        }

        if ($skock_id = waRequest::post('stock_id')) {
            $order['params']['stock_id'] = $skock_id;
        }

        $routing_url = wa()->getRouting()->getRootUrl();
        $order['params']['storefront'] = wa()->getConfig()->getDomain() . ($routing_url ? '/' . $routing_url : '');

        if ($ref = wa()->getStorage()->get('shop/referer')) {
            $order['params']['referer'] = $ref;
            $ref_parts = parse_url($ref);
            $order['params']['referer_host'] = $ref_parts['host'];
            // try get search keywords
            if (!empty($ref_parts['query'])) {
                $search_engines = array(
                    'text' => 'yandex\.|rambler\.',
                    'q' => 'bing\.com|mail\.|google\.',
                    's' => 'nigma\.ru',
                    'p' => 'yahoo\.com'
                );
                $q_var = false;
                foreach ($search_engines as $q => $pattern) {
                    if (preg_match('/(' . $pattern . ')/si', $ref_parts['host'])) {
                        $q_var = $q;
                        break;
                    }
                }
                // default query var name
                if (!$q_var) {
                    $q_var = 'q';
                }
                parse_str($ref_parts['query'], $query);
                if (!empty($query[$q_var])) {
                    $order['params']['keyword'] = $query[$q_var];
                }
            }
        }

        $order['params']['ip'] = waRequest::getIp();
        $order['params']['user_agent'] = waRequest::getUserAgent();

        foreach (array('shipping', 'billing') as $ext) {
            $address = $contact->getFirst('address.' . $ext);
            if ($address) {
                foreach ($address['data'] as $k => $v) {
                    $order['params'][$ext . '_address.' . $k] = $v;
                }
            }
        }

        if (isset($checkout_data['comment'])) {
            $order['comment'] = $checkout_data['comment'];
        }

        $workflow = new shopWorkflow();
        if ($order_id = $workflow->getActionById('create')->run($order)) {

            $step_number = shopCheckout::getStepNumber();
            $checkout_flow = new shopCheckoutFlowModel();
            $checkout_flow->add(array(
                'step' => $step_number
            ));

            $cart->clear();
            wa()->getStorage()->remove('shop/checkout');
            wa()->getStorage()->set('shop/order_id', $order_id);

            return true;
        }
    }

    protected static function getStep($step_id) {
        if (!isset(self::$steps[$step_id])) {
            $class_name = 'shopOnestepCheckout' . ucfirst($step_id);
            self::$steps[$step_id] = new $class_name();
        }
        return self::$steps[$step_id];
    }

}
