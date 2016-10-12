<?php

class shopOnestepCheckoutShipping extends shopCheckoutShipping {

    public function execute() {
        if ($shipping_id = waRequest::post('shipping_id')) {

            if ($data = waRequest::post('customer_' . $shipping_id)) {
                $contact = $this->getContact();
                if (!$contact) {
                    $contact = new waContact();
                }
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
                $rate = false;
            }
            $this->setSessionData('shipping', array(
                'id' => $shipping_id,
                'rate_id' => $rate_id,
                'name' => $rate ? $rate['name'] : '',
                'plugin' => $rate ? $rate['plugin'] : ''
            ));

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

    public function getAddressForm($method_id, waShipping $plugin, $config, $contact_address, $address_form) {
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
            if (isset($config_address['fields'])) {
                $fields = $config_address['fields'];
                if ($address_fields) {
                    foreach ($fields as $f_id => $f) {
                        if (!empty($address_fields[$f_id]) && is_array($address_fields[$f_id])) {
                            foreach ($address_fields[$f_id] as $k => $v) {
                                $fields[$f_id][$k] = $v;
                            }
                        } else {
                            unset($fields[$f_id]);
                        }
                    }
                    foreach ($address_fields as $f_id => $f) {
                        if (!isset($fields[$f_id])) {
                            $fields[$f_id] = $f;
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
                }
            }
        }
        if ($address_form) {
            if (!empty($config['shipping']['prompt_type'])) {
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

            $settings = wa('shop')->getConfig()->getCheckoutSettings();
            if (!empty($settings['contactinfo']['fields']['address.shipping']['fields'])) {
                foreach ($settings['contactinfo']['fields']['address.shipping']['fields'] as $field => $field_data) {
                    unset($address['fields'][$field]);
                }
            }


            if (empty($address['fields'])) {
                return null;
            }


            return waContactForm::loadConfig(array('address.shipping' => $address), array('namespace' => 'customer_' . $method_id));
        } else {
            return null;
        }
    }

}
