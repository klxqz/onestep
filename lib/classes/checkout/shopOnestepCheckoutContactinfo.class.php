<?php

class shopOnestepCheckoutContactinfo extends shopCheckoutContactinfo {

    protected function getCustomerForm($id = null, $ensure_address = false, $checkout = false) {
        $contact = null;
        if ($id) {
            if (is_numeric($id)) {
                $contact = new waContact($id);
            } elseif ($id instanceof waContact) {
                $contact = $id;
            }
            $contact && $contact->getName(); // make sure contact exists; throws exception otherwise
        }

        $settings = wa('shop')->getConfig()->getCheckoutSettings();
        if (!isset($settings['contactinfo'])) {
            $settings = wa('shop')->getConfig()->getCheckoutSettings(true);
        }

        $fields_config = ifset($settings['contactinfo']['fields'], array());
        $address_config = ifset($fields_config['address'], array());
        unset($fields_config['address']);

        if ($ensure_address && !isset($fields_config['address.billing']) && !isset($fields_config['address.shipping'])) {
            // In customer center, we want to show address field even if completely disabled in settings.
            $fields_config['address'] = $address_config;
        } else {
            if (wa()->getEnv() == 'backend') {
                // Tweaks for backend order editor.
                // We want shipping address to show even if disabled in settings.
                // !!! Why is that?.. No idea. Legacy code.
                if (!isset($fields_config['address.shipping'])) {
                    $fields_config['address.shipping'] = $address_config;
                }
                // When an existing contact has address specified, we want to show all the data fields
                if ($contact) {
                    foreach (array('address.shipping', 'address.billing') as $addr_field_id) {
                        if (!empty($fields_config[$addr_field_id])) {
                            $address = $contact->getFirst($addr_field_id);
                            if ($address && !empty($address['data'])) {
                                foreach ($address['data'] as $subfield => $v) {
                                    if (!isset($fields_config[$addr_field_id]['fields'][$subfield])) {
                                        $fields_config[$addr_field_id]['fields'][$subfield] = array();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        foreach (array('address', 'address.shipping', 'address.billing') as $fid) {
            if (isset($fields_config[$fid]) && !empty($fields_config[$fid]['fields']['country'])) {
                if (!isset($fields_config[$fid]['fields']['country']['value'])) {
                    $fields_config[$fid]['fields']['country']['value'] = wa('shop')->getConfig()->getGeneralSettings('country');
                }
            }
        }

        if (shopOnestepHelper::getRouteSettings(null, 'status')) {
            $route_hash = null;
            $route_settings = shopOnestepHelper::getRouteSettings();
        } elseif (shopOnestepHelper::getRouteSettings(0, 'status')) {
            $route_hash = 0;
            $route_settings = shopOnestepHelper::getRouteSettings(0);
        }

        if (
                !empty($route_settings['sxgeo']) && (
                !empty($fields_config['address.shipping']['fields']['city']) ||
                !empty($fields_config['address.shipping']['fields']['region']) ||
                !empty($fields_config['address.shipping']['fields']['country'])
                )
        ) {
            $autoload = waAutoload::getInstance();
            $autoload->add('SxGeo', "wa-apps/shop/plugins/onestep/lib/vendors/SxGeo.php");
            $SxGeo = new SxGeo(wa()->getAppPath('plugins/onestep/lib/vendors/SxGeoCity.dat', 'shop'));
            $ip = waRequest::getIp();
            $info = $SxGeo->getCityFull($ip);

            if (!empty($info['city']['name_ru']) && !empty($fields_config['address.shipping']['fields']['city'])) {
                $fields_config['address.shipping']['fields']['city']['value'] = $info['city']['name_ru'];
            }
            if (!empty($info['region']['name_ru']) && !empty($fields_config['address.shipping']['fields']['region'])) {
                $fields_config['address.shipping']['fields']['region']['value'] = $info['region']['name_ru'];
            }
            if (!empty($info['country']['name_ru']) && !empty($fields_config['address.shipping']['fields']['country'])) {
                $country_model = new waCountryModel();
                if ($country = $country_model->getByField('iso2letter', $info['country']['iso'])) {
                    $fields_config['address.shipping']['fields']['country']['value'] = $country['iso3letter'];
                }
            }
        }

        if ($checkout && !wa()->getUser()->isAuth()) {
            $form = shopOnestepContactForm::loadConfig(
                            $fields_config, array(
                        'namespace' => 'customer'
                            )
            );
        } else {
            $form = waContactForm::loadConfig(
                            $fields_config, array(
                        'namespace' => 'customer'
                            )
            );
        }
        $contact && $form->setValue($contact);
        return $form;
    }

    public function display() {
        $this->form = $this->getCustomerForm(null, false, true);

        parent::display();
    }

    protected function sendSpamAlert() {
        if ($this->getSessionData('sendSpamAlert')) {
            return;
        }
        parent::sendSpamAlert();
        $this->setSessionData('sendSpamAlert', 1);
    }

    protected function updateContact() {
        $contact = $this->getContact();
        if (!$contact) {
            $contact = new waContact();
        }
        $this->form = $this->getCustomerForm(null, false, true);
        $data = waRequest::post('customer');
        if ($data && is_array($data)) {
            // When both shipping and billing addresses are enabled,
            // there's an option to only edit one address copy.
            if (waRequest::request('billing_matches_shipping') && $this->form->fields('address.shipping') && $this->form->fields('address.billing') && !empty($data['address.shipping'])) {
                $data['address.billing'] = $data['address.shipping'];
            }

            foreach ($data as $field => $value) {
                $contact->set($field, $value);
            }

            if (wa()->getUser()->isAuth()) {
                $contact->save();
            } else {
                $this->setSessionData('contact', $contact);
            }
        }
    }

    public function execute() {
        $this->updateContact();
        $contact = $this->getContact();
        if (!$contact) {
            $contact = new waContact();
        }

        $this->form = $this->getCustomerForm(null, false, true);

        // Do not validate required subfields of billing address
        // when billing is set up to match shipping address.
        if (waRequest::request('billing_matches_shipping') && $this->form->fields('address.shipping') && $this->form->fields('address.billing')) {
            $subfields = $this->form->fields('address.billing')->getParameter('fields');
            foreach ($subfields as $i => $sf) {
                if ($sf->isRequired()) {
                    $subfields[$i] = clone $sf;
                    $subfields[$i]->setParameter('required', false);
                }
            }
            $this->form->fields('address.billing')->setParameter('fields', $subfields);
        }

        if (!wa()->getUser()->isAuth() && ($this->form instanceof shopContactForm)) {
            if (!$this->form->isValidAntispam()) {
                $errors = $this->form->errors();
                if (!empty($errors['spam'])) {
                    $this->sendSpamAlert();
                    wa()->getView()->assign('errors', array(
                        'all' => $errors['spam']
                    ));
                }
            }
        }
        if (!$this->form->isValid($contact)) {
            return false;
        }
        if (wa('shop')->getSetting('checkout_antispam') && !wa()->getUser()->isAuth()) {
            $this->setSessionData('antispam', true);
        }

        $data = waRequest::post('customer');
        if ($data && is_array($data)) {
            // When both shipping and billing addresses are enabled,
            // there's an option to only edit one address copy.
            if (waRequest::request('billing_matches_shipping') && $this->form->fields('address.shipping') && $this->form->fields('address.billing') && !empty($data['address.shipping'])) {
                $data['address.billing'] = $data['address.shipping'];
            }

            foreach ($data as $field => $value) {
                $contact->set($field, $value);
            }
        }

        if (($shipping = $this->getSessionData('shipping')) && !waRequest::post('ignore_shipping_error')) {
            $shipping_step = new shopCheckoutShipping();
            $rate_id = isset($shipping['rate_id']) ? $shipping['rate_id'] : null;
            $rate = $shipping_step->getRate($shipping['id'], $rate_id, $contact);
            if (!$rate || is_string($rate)) {
                // remove selected shipping method
                $this->setSessionData('shipping', null);
                /*
                  $errors = array();
                  $errors['all'] = sprintf(_w('We cannot ship to the specified address via %s.'), $shipping['name']);
                  if ($rate) {
                  $errors['all'] .= '<br> <strong>'.$rate.'</strong><br>';
                  }
                  $errors['all'] .= '<br> '._w('Please double-check the address above, or return to the shipping step and select another shipping option.');
                  $errors['all'] .= '<input type="hidden" name="ignore_shipping_error" value="1">';
                  wa()->getView()->assign('errors', $errors);
                  return false;
                 */
            }
        }

        if (wa()->getUser()->isAuth()) {
            $contact->save();
        } else {
            $errors = array();
            if (waRequest::post('create_user')) {
                $login = waRequest::post('login');
                if (!$login) {
                    $errors['email'][] = _ws('Required');
                }
                if (!waRequest::post('password')) {
                    $errors['password'] = _ws('Required');
                }
                $email_validator = new waEmailValidator();
                if (!$email_validator->isValid($login)) {
                    $errors['email'] = $email_validator->getErrors();
                }
                if (!$errors) {
                    $contact_model = new waContactModel();
                    if ($contact_model->getByEmail($login, true)) {
                        $errors['email'][] = _w('Email already registered');
                    }
                }
                if (!$errors) {
                    $contact->set('email', $login);
                    $contact->set('password', waRequest::post('password'));
                } else {
                    if (isset($errors['email'])) {
                        $errors['email'] = implode(', ', $errors['email']);
                    }
                    wa()->getView()->assign('errors', $errors);
                    return false;
                }
            }
            $this->setSessionData('contact', $contact);
        }

        if ($comment = waRequest::post('comment')) {
            $this->setSessionData('comment', $comment);
        }

        $agreed = waRequest::request('service_agreement');
        if (!$agreed && null !== $agreed) {
            wa()->getView()->assign('errors', array(
                'service_agreement' => _w('Please confirm your agreement'),
            ));
            return false;
        }

        return true;
    }

}
