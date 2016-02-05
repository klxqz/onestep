<?php

class shopOnestepPluginFrontendOnestepAction extends shopFrontendAction {

    protected static $steps = array();

    public function execute() {

        $domain_settings = shopOnestep::getDomainSettings();
        $templates = $domain_settings['templates'];

        $this->getResponse()->setTitle($domain_settings['page_title']);
        $this->cart();
        $this->checkout($templates);


        if ($templates['cart_js']['change_tpl']) {
            $cart_js_url = wa()->getDataUrl($templates['cart_js']['tpl_full_path'], true, 'shop');
        } else {
            $cart_js_url = wa()->getAppStaticUrl() . $templates['cart_js']['tpl_full_path'];
        }

        $this->view->assign('checkout_path', $templates['checkout']['template_path']);
        $this->view->assign('cart_js_url', $cart_js_url);
        $this->view->assign('settings', $domain_settings);


        $this->setTemplate($templates['onestep']['template_path']);
    }

    public function cart() {
        $this->getResponse()->addHeader("Cache-Control", "no-store, no-cache, must-revalidate");
        $this->getResponse()->addHeader("Expires", date("r"));

        if (waRequest::method() == 'post') {
            $data = wa()->getStorage()->get('shop/checkout', array());
            if ($coupon_code = waRequest::post('coupon_code')) {
                $data['coupon_code'] = $coupon_code;
            } elseif (isset($data['coupon_code'])) {
                unset($data['coupon_code']);
            }

            if (($use = waRequest::post('use_affiliate')) !== null) {
                if ($use) {
                    $data['use_affiliate'] = 1;
                } elseif (isset($data['use_affiliate'])) {
                    unset($data['use_affiliate']);
                }
            }

            wa()->getStorage()->set('shop/checkout', $data);
            wa()->getStorage()->remove('shop/cart');
        }

        $cart = new shopCart();
        $code = $cart->getCode();

        $errors = array();
        $cart_model = new shopCartItemsModel();
        //$items = $cart_model->where('code= ?', $code)->order('parent_id')->fetchAll('id');
        $items = $cart->items(false);

        $total = $cart->total(false);
        $order = array(
            'currency' => wa()->getConfig()->getCurrency(false),
            'total' => $total,
            'items' => $items
        );
        $discount_description = '';
        $order['discount'] = $discount = shopDiscounts::calculate($order, false, $discount_description);
        $order['total'] = $total = $total - $order['discount'];

        $saved_quantity = $cart_model->select('id,quantity')->where("type='product' AND code = s:code", array('code' => $code))->fetchAll('id');
        $quantity = waRequest::post('quantity', array());

        foreach ($quantity as $id => $q) {
            if (isset($saved_quantity[$id]) && ($q != $saved_quantity[$id])) {
                $cart->setQuantity($id, $q);
            }
        }

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
        foreach ($items as $row) {
            if (!$row['quantity'] && !isset($errors[$row['id']])) {
                $errors[$row['id']] = null;
            }
        }

        $product_ids = $sku_ids = $service_ids = $type_ids = array();
        foreach ($items as $item) {
            $product_ids[] = $item['product_id'];
            $sku_ids[] = $item['sku_id'];
        }

        $product_ids = array_unique($product_ids);
        $sku_ids = array_unique($sku_ids);

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

        $type_ids = array_unique($type_ids);

        // get available services for all types of products
        $type_services_model = new shopTypeServicesModel();
        $rows = $type_services_model->getByField('type_id', $type_ids, true);
        $type_services = array();
        foreach ($rows as $row) {
            $service_ids[$row['service_id']] = $row['service_id'];
            $type_services[$row['type_id']][$row['service_id']] = true;
        }

        // get services for products and skus, part 1
        $product_services_model = new shopProductServicesModel();
        $rows = $product_services_model->getByProducts($product_ids);
        foreach ($rows as $i => $row) {
            if ($row['sku_id'] && !in_array($row['sku_id'], $sku_ids)) {
                unset($rows[$i]);
                continue;
            }
            $service_ids[$row['service_id']] = $row['service_id'];
        }

        $service_ids = array_unique(array_values($service_ids));

        // Get services
        $service_model = new shopServiceModel();
        $services = $service_model->getByField('id', $service_ids, 'id');
        shopRounding::roundServices($services);

        // get services for products and skus, part 2
        $product_services = $sku_services = array();
        shopRounding::roundServiceVariants($rows, $services);
        foreach ($rows as $row) {
            if (!$row['sku_id']) {
                $product_services[$row['product_id']][$row['service_id']]['variants'][$row['service_variant_id']] = $row;
            }
            if ($row['sku_id']) {
                $sku_services[$row['sku_id']][$row['service_id']]['variants'][$row['service_variant_id']] = $row;
            }
        }

        // Get service variants
        $variant_model = new shopServiceVariantsModel();
        $rows = $variant_model->getByField('service_id', $service_ids, true);
        shopRounding::roundServiceVariants($rows, $services);
        foreach ($rows as $row) {
            $services[$row['service_id']]['variants'][$row['id']] = $row;
            unset($services[$row['service_id']]['variants'][$row['id']]['id']);
        }

        // When assigning services into cart items, we don't want service ids there
        foreach ($services as &$s) {
            unset($s['id']);
        }
        unset($s);


        // Assign service and product data into cart items
        foreach ($items as $item_id => $item) {
            if ($item['type'] == 'product') {
                $p = $item['product'];
                $item_services = array();
                // services from type settings
                if (isset($type_services[$p['type_id']])) {
                    foreach ($type_services[$p['type_id']] as $service_id => &$s) {
                        $item_services[$service_id] = $services[$service_id];
                    }
                }
                // services from product settings
                if (isset($product_services[$item['product_id']])) {
                    foreach ($product_services[$item['product_id']] as $service_id => $s) {
                        if (!isset($s['status']) || $s['status']) {
                            if (!isset($item_services[$service_id])) {
                                $item_services[$service_id] = $services[$service_id];
                            }
                            // update variants
                            foreach ($s['variants'] as $variant_id => $v) {
                                if ($v['status']) {
                                    if ($v['price'] !== null) {
                                        $item_services[$service_id]['variants'][$variant_id]['price'] = $v['price'];
                                    }
                                } else {
                                    unset($item_services[$service_id]['variants'][$variant_id]);
                                }
                            }
                        } elseif (isset($item_services[$service_id])) {
                            // remove disabled service
                            unset($item_services[$service_id]);
                        }
                    }
                }
                // services from sku settings
                if (isset($sku_services[$item['sku_id']])) {
                    foreach ($sku_services[$item['sku_id']] as $service_id => $s) {
                        if (!isset($s['status']) || $s['status']) {
                            // update variants
                            foreach ($s['variants'] as $variant_id => $v) {
                                if ($v['status']) {
                                    if ($v['price'] !== null) {
                                        $item_services[$service_id]['variants'][$variant_id]['price'] = $v['price'];
                                    }
                                } else {
                                    unset($item_services[$service_id]['variants'][$variant_id]);
                                }
                            }
                        } elseif (isset($item_services[$service_id])) {
                            // remove disabled service
                            unset($item_services[$service_id]);
                        }
                    }
                }
                foreach ($item_services as $s_id => &$s) {
                    if (!$s['variants']) {
                        unset($item_services[$s_id]);
                        continue;
                    }

                    if ($s['currency'] == '%') {
                        foreach ($s['variants'] as $v_id => $v) {
                            $s['variants'][$v_id]['price'] = $v['price'] * $item['price'] / 100;
                        }
                        $s['currency'] = $item['currency'];
                    }

                    if (count($s['variants']) == 1) {
                        $v = reset($s['variants']);
                        $s['price'] = $v['price'];
                        unset($s['variants']);
                    }
                }
                unset($s);
                uasort($item_services, array('shopServiceModel', 'sortServices'));

                $items[$item_id]['services'] = $item_services;
            } else {
                $items[$item['parent_id']]['services'][$item['service_id']]['id'] = $item['id'];
                if (isset($item['service_variant_id'])) {
                    $items[$item['parent_id']]['services'][$item['service_id']]['variant_id'] = $item['service_variant_id'];
                }
                unset($items[$item_id]);
            }
        }

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

        $data = wa()->getStorage()->get('shop/checkout');
        $this->view->assign('cart', array(
            'items' => $items,
            'total' => $total,
            'count' => $cart->count()
        ));

        $this->view->assign('coupon_code', isset($data['coupon_code']) ? $data['coupon_code'] : '');
        if (!empty($data['coupon_code']) && !empty($order['params']['coupon_discount'])) {
            $this->view->assign('coupon_discount', $order['params']['coupon_discount']);
        }
        if (shopAffiliate::isEnabled()) {
            $affiliate_bonus = $affiliate_discount = 0;
            if ($this->getUser()->isAuth()) {
                $customer_model = new shopCustomerModel();
                $customer = $customer_model->getById($this->getUser()->getId());
                $affiliate_bonus = $customer ? round($customer['affiliate_bonus'], 2) : 0;
            }
            $this->view->assign('affiliate_bonus', $affiliate_bonus);

            $use = !empty($data['use_affiliate']);
            $this->view->assign('use_affiliate', $use);
            $usage_percent = (float) wa()->getSetting('affiliate_usage_percent', 0, 'shop');
            $this->view->assign('affiliate_percent', $usage_percent);
            $affiliate_discount = self::getAffiliateDiscount($affiliate_bonus, $order);
            if ($use) {
                $discount -= $affiliate_discount;
                $this->view->assign('used_affiliate_bonus', $order['params']['affiliate_bonus']);
            }
            $this->view->assign('affiliate_discount', $affiliate_discount);

            $add_affiliate_bonus = shopAffiliate::calculateBonus($order);
            $this->view->assign('add_affiliate_bonus', round($add_affiliate_bonus, 2));
        }
        $this->view->assign('discount', $discount);

        /**
         * @event frontend_cart
         * @return array[string]string $return[%plugin_id%] html output
         */
        $this->view->assign('frontend_cart', wa()->event('frontend_cart'));

        $this->getResponse()->setTitle(_w('Cart'));

        $checkout_flow = new shopCheckoutFlowModel();
        $checkout_flow->add(array(
            'code' => $code,
            'step' => 0,
            'description' => null /* TODO: Error message here if exists */
        ));
    }

    public static function getAffiliateDiscount($affiliate_bonus, $order) {
        $data = wa()->getStorage()->get('shop/checkout');
        $use = !empty($data['use_affiliate']);
        $usage_percent = (float) wa()->getSetting('affiliate_usage_percent', 0, 'shop');
        if (!$usage_percent) {
            $usage_percent = 100;
        }
        $affiliate_discount = 0;
        if ($use) {
            $affiliate_discount = shop_currency(shopAffiliate::convertBonus($order['params']['affiliate_bonus']), wa('shop')->getConfig()->getCurrency(true), null, false);
            if ($usage_percent) {
                $affiliate_discount = min($affiliate_discount, ($order['total'] + $affiliate_discount) * $usage_percent / 100.0);
            }
        } elseif ($affiliate_bonus) {
            $affiliate_discount = shop_currency(shopAffiliate::convertBonus($affiliate_bonus), wa('shop')->getConfig()->getCurrency(true), null, false);
            if ($usage_percent) {
                $affiliate_discount = min($affiliate_discount, $order['total'] * $usage_percent / 100.0);
            }
        }
        return $affiliate_discount;
    }

    public function checkout($templates) {
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
                    if ($order_id = self::createOrder()) {
                        wa()->getStorage()->set('shop/success_order_id', $order_id);
                        wa()->getResponse()->redirect(wa()->getRouteUrl('/frontend/checkout', array('step' => 'success')));
                    }
                }
            }
            $view->assign('submit', true);
        }
        $checkout_tpls = array();
        foreach ($steps as $step_id => $step) {
            $step = self::getStep($step_id);
            $step->initDefault();
            $steps[$step_id]['content'] = $step->display();
            /**
             * @event frontend_checkout
             * @return array[string]string $return[%plugin_id%] html output
             */
            $event_params = array('step' => $step_id);
            $view->assign('frontend_checkout', wa()->event('frontend_checkout', $event_params));

            $step_tpl_path = $templates['checkout.' . $step_id]['template_path'];

            $step_tpl = $view->fetch($step_tpl_path);
            $checkout_tpls[$step_id] = $step_tpl;
        }
        $view->assign('checkout_tpls', $checkout_tpls);
        $view->assign('checkout_steps', $steps);
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

    protected function createOrder() {
        $checkout_data = wa()->getStorage()->get('shop/checkout');

        if (wa()->getUser()->isAuth()) {
            $contact = wa()->getUser();
        } else if (!empty($checkout_data['contact']) && $checkout_data['contact'] instanceof waContact) {
            $contact = $checkout_data['contact'];
        } else {
            $contact = new waContact();
        }

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

        $order['discount_description'] = null;
        $order['discount'] = shopDiscounts::apply($order, $order['discount_description']);

        if (isset($checkout_data['shipping'])) {
            $order['params']['shipping_id'] = $checkout_data['shipping']['id'];
            $order['params']['shipping_rate_id'] = $checkout_data['shipping']['rate_id'];
            $shipping_step = new shopOnestepCheckoutShipping();
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

        if (( $ref = waRequest::cookie('referer'))) {
            $order['params']['referer'] = $ref;
            $ref_parts = @parse_url($ref);
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

        if (( $utm = waRequest::cookie('utm'))) {
            $utm = json_decode($utm, true);
            if ($utm && is_array($utm)) {
                foreach ($utm as $k => $v) {
                    $order['params']['utm_' . $k] = $v;
                }
            }
        }

        if (( $landing = waRequest::cookie('landing')) && ( $landing = @parse_url($landing))) {
            if (!empty($landing['query'])) {
                @parse_str($landing['query'], $arr);
                if (!empty($arr['gclid']) && !empty($order['params']['referer_host']) && strpos($order['params']['referer_host'], 'google') !== false) {
                    $order['params']['referer_host'] .= ' (cpc)';
                    $order['params']['cpc'] = 1;
                } else if (!empty($arr['_openstat']) && !empty($order['params']['referer_host']) && strpos($order['params']['referer_host'], 'yandex') !== false) {
                    $order['params']['referer_host'] .= ' (cpc)';
                    $order['params']['openstat'] = $arr['_openstat'];
                    $order['params']['cpc'] = 1;
                }
            }

            $order['params']['landing'] = $landing['path'];
        }

        // A/B tests
        $abtest_variants_model = new shopAbtestVariantsModel();
        foreach (waRequest::cookie() as $k => $v) {
            if (substr($k, 0, 5) == 'waabt') {
                $variant_id = $v;
                $abtest_id = substr($k, 5);
                if (wa_is_int($abtest_id) && wa_is_int($variant_id)) {
                    $row = $abtest_variants_model->getById($variant_id);
                    if ($row && $row['abtest_id'] == $abtest_id) {
                        $order['params']['abt' . $abtest_id] = $variant_id;
                    }
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

            $step_number = shopOnestepCheckout::getStepNumber();
            $checkout_flow = new shopCheckoutFlowModel();
            $checkout_flow->add(array(
                'step' => $step_number
            ));

            $cart->clear();
            wa()->getStorage()->remove('shop/checkout');
            wa()->getStorage()->set('shop/order_id', $order_id);

            return $order_id;
        } else {
            return false;
        }
    }

    protected function getStep($step_id) {
        if (!isset(self::$steps[$step_id])) {
            $class_name = 'shopOnestepCheckout' . ucfirst($step_id);
            self::$steps[$step_id] = new $class_name();
        }
        return self::$steps[$step_id];
    }

}
