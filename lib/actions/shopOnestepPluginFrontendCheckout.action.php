<?php

class shopOnestepPluginFrontendCheckoutAction extends shopFrontendCheckoutAction {

    public function execute() {
        if (shopOnestepHelper::getRouteSettings(null, 'status')) {
            $route_hash = null;
            $route_settings = shopOnestepHelper::getRouteSettings();
        } elseif (shopOnestepHelper::getRouteSettings(0, 'status')) {
            $route_hash = 0;
            $route_settings = shopOnestepHelper::getRouteSettings(0);
        } else {
            throw new waException(_ws("Page not found"), 404);
        }

        $templates = shopOnestepHelper::getRouteTemplates($route_hash, null, false);

        $steps = wa()->getConfig()->getCheckoutSettings();

        $cart = new shopCart();
        if (!$cart->count()) {
            return false;
        }

        if (waRequest::method() == 'post' && !waRequest::post('apply_coupon_code')) {
            if (waRequest::post('wa_auth_login')) {
                $login_action = new shopOnestepLoginAction();
                $login_action->run();
            } else {
                $error = false;
                foreach ($steps as $step_id => $step) {
                    $step_instance = $this->getStep($step_id);
                    if (!$step_instance->execute()) {
                        $error = true;
                    }
                }

                if (waRequest::post('confirmation') && !$error) {
                    if (!$this->checkCart($r_cart)) {
                        if ($order_id = $this->createOrder()) {
                            wa()->getStorage()->set('shop/success_order_id', $order_id);
                            wa()->getResponse()->redirect(wa()->getRouteUrl('/frontend/checkout', array('step' => 'success')));
                        }
                    } else {
                        $this->view->assign('cart', $r_cart);
                    }
                }
            }
        }
        $this->view->assign('checkout_steps', $steps);
        $checkout_tpls = array();
        foreach ($steps as $step_id => $step) {
            $step = $this->getStep($step_id);
            $steps[$step_id]['content'] = $step->display();
            /**
             * @event frontend_checkout
             * @return array[string]string $return[%plugin_id%] html output
             */
            $event_params = array('step' => $step_id);
            $this->view->assign('frontend_checkout', wa()->event('frontend_checkout', $event_params));
            
            $step_tpl_path = $templates['checkout.' . $step_id]['template_path'];

            $step_tpl = $this->view->fetch($step_tpl_path);
            $checkout_tpls[$step_id] = $step_tpl;
        }
        $this->view->assign('checkout_tpls', $checkout_tpls);
    }

    protected function getStep($step_id) {
        if (!isset(self::$steps[$step_id])) {
            $class_name = 'shopOnestepCheckout' . ucfirst($step_id);
            self::$steps[$step_id] = new $class_name();
        }
        return self::$steps[$step_id];
    }

    public function checkCart(&$cart = null) {
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

    protected function createOrder(&$errors = array()) {
        $order_id = parent::createOrder($errors);
        if ($order_id) {
            $this->setSessionData('sendSpamAlert', 0);
            wa()->getStorage()->set('shop/checkout_code', '');
        }
        return $order_id;
    }

    protected function setSessionData($key, $value) {
        $data = wa()->getStorage()->get('shop/checkout', array());
        if ($value === null) {
            if (isset($data[$key])) {
                unset($data[$key]);
            }
        } else {
            $data[$key] = $value;
        }
        wa()->getStorage()->set('shop/checkout', $data);
    }

}
