<?php

class shopOnestepCheckoutContactinfo extends shopCheckoutContactinfo {

    public function execute() {
        $contact = $this->getContact();
        if (!$contact) {
            $contact = new waContact();
        }
        $this->form = shopHelper::getCustomerForm(null, false, true);
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

        return parent::execute();
    }

    protected function sendSpamAlert() {
        if ($this->setSessionData('sendSpamAlert')) {
            return;
        }
        parent::sendSpamAlert();
        $this->setSessionData('sendSpamAlert', 1);
    }

}
