<?php

class shopOnestepCheckoutShipping extends shopCheckoutShipping {

    public function execute() {
        if ($shipping_id = waRequest::post('shipping_id', null, waRequest::TYPE_INT)) {

            if ($data = waRequest::post('customer_' . $shipping_id)) {

                try {
                    $plugins = $this->getPlugins($shipping_id);
                    if (!$plugins) {
                        return false;
                    }
                    $plugin_info = reset($plugins);
                    if (empty($plugin_info['available'])) {
                        return false;
                    }
                    $plugin = shopShipping::getPlugin($plugin_info['plugin'], $plugin_info['id']);
                } catch (waException $ex) {
                    switch ($ex->getCode()) {
                        case 404:
                            $this->assign('error', _w('Shipping option is not defined. Please return to the shipping option checkout step to continue.'));
                            break;
                        default:
                            $this->assign('error', $ex->getMessage());
                            break;
                    }
                    return false;
                }
                $form = $this->getAddressForm($shipping_id, $plugin, null, array(), true);
                /* убираем проверку валидации формы, чтобы введенная информация могла сохраниться.
                  if (!$form->isValid()) {
                  return false;
                  } */

                $contact = $this->getContact();

                if ($data && is_array($data)) {
                    foreach ($data as $field => $value) {
                        if (is_array($value) && ($old = $contact->get($field))) {
                            if (isset($old[0]['data'])) {
                                foreach ($old[0]['data'] as $k => $v) {
                                    if (!isset($value[$k])) {
                                        $value[$k] = $v;
                                    }
                                }
                            }
                        }
                        $contact->set($field, $value);
                    }
                    if (wa()->getUser()->isAuth()) {
                        $contact->save();
                    } else {
                        $this->setSessionData('contact', $contact);
                    }
                }
            }

            $rates = waRequest::post('rate_id');

            $rate_id = isset($rates[$shipping_id]) ? $rates[$shipping_id] : null;
            $rate = $this->getRate($shipping_id, $rate_id);
            if (is_string($rate)) {
                $this->assign('error', $rate);
                $rate = false;
            } elseif ($rate['rate'] !== null) {
                if ($this->isFreeShipping()) {
                    $rate['rate'] = 0;
                }
            }

            $shipping = array(
                'id' => $shipping_id,
                'rate_id' => $rate_id,
                'rate' => $rate ? $rate['rate'] : 0,
                'name' => $rate ? $rate['name'] : '',
                'plugin' => $rate ? $rate['plugin'] : '',
            );
            $this->setSessionData('shipping', $shipping);
            if (!$rate) {
                return false;
            }

            if ($comment = waRequest::post('comment')) {
                $this->setSessionData('comment', $comment);
            }

            if ($shipping_params = waRequest::post('shipping_' . $shipping_id)) {
                $params = $this->getSessionData('params', array());
                $params['shipping'] = $shipping_params;
                $this->setSessionData('params', $params);
            }

            if (!isset($rate['rate']) && isset($rate['comment'])) {
                return false;
            }

            return true;
        } else {
            return false;
        }
    }

    private function getExtendedCheckoutSettings() {
        static $settings = null;
        if ($settings === null) {
            $settings = self::getCheckoutSettings();
            if (!isset($settings['contactinfo']) ||
                    (
                    !isset($settings['contactinfo']['fields']['address.shipping']) && !isset($settings['contactinfo']['fields']['address'])
                    )
            ) {
                $config = wa('shop')->getConfig();
                /**
                 * @var shopConfig $config
                 */
                $settings = $config->getCheckoutSettings(true);
            }
        }
        return $settings;
    }

    public function getAddressForm($method_id, waShipping $plugin, $config, $contact_address, $address_form) {
        if ($config === null) {
            $config = $this->getExtendedCheckoutSettings();
        }
        $config_address = isset($config['contactinfo']['fields']['address.shipping']) ?
                $config['contactinfo']['fields']['address.shipping'] :
                (isset($config['contactinfo']['fields']['address']) ? $config['contactinfo']['fields']['address'] : array());

        $address_fields = $plugin->requestedAddressFields();
        $disabled_only = $address_fields === array() ? false : true;
        if ($address_fields === false || $address_fields === null) {
            return false;
        }
        foreach ($address_fields as $f) {
            if ($f !== false) {
                $disabled_only = false;
                break;
            }
        }
        $address = array();
        if ($disabled_only) {
            $allowed = $plugin->allowedAddress();
            if (count($allowed) == 1) {
                $one = true;
                if (!isset($config_address['fields'])) {
                    $fields = array();
                    $address_field = waContactFields::get('address');
                    foreach ($address_field->getFields() as $f) {
                        /**
                         * @var waContactAddressField $f
                         */
                        $fields[$f->getId()] = array();
                    }
                } else {
                    $fields = $config_address['fields'];
                }
                foreach ($allowed[0] as $k => $v) {
                    if (is_array($v)) {
                        $one = false;
                        break;
                    } else {
                        $fields[$k]['hidden'] = 1;
                        $fields[$k]['value'] = $v;
                    }
                }
                foreach ($address_fields as $k => $v) {
                    if ($v === false && isset($fields[$k])) {
                        unset($fields[$k]);
                    }
                }
                if ($one) {
                    $address = $config_address;
                    $address['fields'] = $fields;
                }
            }
        } else {
            $union = false;
            if (isset($config_address['fields'])) {
                $fields = $config_address['fields'];
                if ($address_fields) {

                    foreach ($fields as $f_id => $f) {
                        if (isset($address_fields[$f_id])) {
                            if (is_array($address_fields[$f_id])) {
                                foreach ($address_fields[$f_id] as $k => $v) {
                                    $fields[$f_id][$k] = $v;
                                }
                            } elseif ($address_fields[$f_id] === false) {
                                unset($fields[$f_id]);
                                unset($address_fields[$f_id]);
                            }
                        } elseif (!$union) {
                            unset($fields[$f_id]);
                        }
                    }
                    foreach ($address_fields as $f_id => $f) {
                        if (!isset($fields[$f_id])) {
                            $fields[$f_id] = $f === false ? array() : $f;
                        }
                    }
                }
                $address_fields = $fields;
            }
            if ($address_fields) {
                $address = array('fields' => $address_fields);
            }
        }

        if (!$address_form && !empty($address['fields'])) {
            foreach ($address['fields'] as $k => $v) {
                if (empty($contact_address[$k])) {
                    $address_form = true;
                    break;
                }
            }
        }

        if ($address_form || ifset($config, 'shipping', 'prompt_type', null) == 2) {
            if (ifset($config, 'shipping', 'prompt_type', null) == 1) {
                #show only cost type fields
                if (!empty($address['fields'])) {
                    foreach ($address['fields'] as $k => $v) {
                        if (empty($v['cost'])) {
                            unset($address['fields'][$k]);
                        }
                    }
                    if (!$address['fields']) {
                        return null;
                    }
                } else {
                    $empty = true;
                    foreach ($address_fields as $f) {
                        if (!empty($f['cost'])) {
                            $empty = false;
                            break;
                        }
                    }
                    if ($empty) {
                        return null;
                    }
                }
            }

            #attempt to sort address fields
            if (!empty($address['fields']) && !empty($config_address['fields'])) {
                $sort = array_flip(array_keys($config_address['fields']));
                $code = ' $map = ' . var_export($sort, true) . ';';
                $code .= ' return ifset($map[$a],0)-ifset($map[$b],0);';

                $compare = wa_lambda('$a, $b', $code);
                uksort($address['fields'], $compare);
            }
            /* Добавляем определение адреса - начало */
            if (shopOnestepHelper::getRouteSettings(null, 'status')) {
                $route_hash = null;
                $route_settings = shopOnestepHelper::getRouteSettings();
            } elseif (shopOnestepHelper::getRouteSettings(0, 'status')) {
                $route_hash = 0;
                $route_settings = shopOnestepHelper::getRouteSettings(0);
            }

            if (
                    !empty($route_settings['sxgeo']) && (
                    !empty($address['fields']['city']) ||
                    !empty($address['fields']['region']) ||
                    !empty($address['fields']['country'])
                    )
            ) {
                $autoload = waAutoload::getInstance();
                $autoload->add('SxGeo', "wa-apps/shop/plugins/onestep/lib/vendors/SxGeo.php");
                $SxGeo = new SxGeo(wa()->getAppPath('plugins/onestep/lib/vendors/SxGeoCity.dat', 'shop'));
                $ip = waRequest::getIp();
                $info = $SxGeo->getCityFull($ip);

                if (!empty($info['city']['name_ru']) && !empty($address['fields']['city'])) {
                    $address['fields']['city']['value'] = $info['city']['name_ru'];
                }
                if (!empty($info['region']['name_ru']) && !empty($address['fields']['region'])) {
                    $address['fields']['region']['value'] = $info['region']['name_ru'];
                }
                if (!empty($info['country']['name_ru']) && !empty($address['fields']['country'])) {
                    $country_model = new waCountryModel();
                    if ($country = $country_model->getByField('iso2letter', $info['country']['iso'])) {
                        $address['fields']['country']['value'] = $country['iso3letter'];
                    }
                }
            }
            /* Добавляем определение адреса - конец */

            return waContactForm::loadConfig(array('address.shipping' => $address), array('namespace' => 'customer_' . $method_id));
        } else {
            return null;
        }
    }

    /* Переопределяем данный метод т.к. в более ранних версиях он отсутствует */

    protected function isFreeShipping() {
        $is_free = false;

        $coupon_code = $this->getSessionData('coupon_code');
        if (!empty($coupon_code)) {
            empty($cm) && ($cm = new shopCouponModel());
            $coupon = $cm->getByField('code', $coupon_code);
            if ($coupon && $coupon['type'] == '$FS') {
                $is_free = true;
            }
        }
        return $is_free;
    }

    /* три метода, которые в более ранних версиях shop-script отсутствуют */

    protected function getPlugins($id = null) {
        $options = array();

        # filter enabled at frontend plugins
        $shipping_id = waRequest::param('shipping_id');
        if ($shipping_id && is_array($shipping_id)) {
            $options['id'] = $shipping_id;
        }

        if ($id) {
            if (empty($options['id'])) {
                $options['id'] = $id;
            } elseif (in_array($id, $options['id'])) {
                $options['id'] = $id;
            } else {
                return array();
            }
        }

        # filter applicable shipping plugins
        if ($payment_id = $this->getSessionData('payment')) {
            if (self::getStepNumber($this->step_id) > self::getStepNumber('payment')) {
                $options[shopPluginModel::TYPE_PAYMENT] = $payment_id;
            }
        }

        return $this->plugin_model->listPlugins(shopPluginModel::TYPE_SHIPPING, $options);
    }

    public function __get($name) {
        static $instances = array();
        $value = null;
        if (!isset($instances[$name])) {
            switch ($name) {
                case 'cart':
                    $instances[$name] = new shopCart();
                    break;
                case 'plugin_model':
                    $instances[$name] = new shopPluginModel();
                    break;
            }
        }
        return isset($instances[$name]) ? $instances[$name] : null;
    }

    protected static function getCheckoutSettings($name = null) {
        static $settings;
        if (!$settings) {
            $config = wa('shop')->getConfig();
            /**
             * @var shopConfig $config
             */
            $settings = $config->getCheckoutSettings();
        }

        return $name ? ifset($settings[$name]) : $settings;
    }

}
