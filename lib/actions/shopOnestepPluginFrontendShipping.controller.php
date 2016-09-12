<?php

class shopOnestepPluginFrontendShippingController extends shopFrontendShippingController {

    public function execute() {
        $cart = new shopCart();
        $total = $cart->total();

        $shipping = new shopOnestepCheckoutShipping();
        $items = $shipping->getItems();


        if (waRequest::method() == 'post') {
            wa()->getStorage()->close();
            $shipping->execute();
        }

        $shipping_ids = waRequest::get('shipping_id', array(), waRequest::TYPE_ARRAY_INT);
        if (empty($shipping_ids)) {
            $shipping_ids = waRequest::post('shipping_ids', array(), waRequest::TYPE_ARRAY_INT);
        }

        if ($shipping_ids) {
            $address = $shipping->getAddress();
            wa()->getStorage()->close();
            $empty = true;
            foreach ($address as $v) {
                if ($v) {
                    $empty = false;
                    break;
                }
            }
            if ($empty) {
                $address = array();
            }
            if (!$address) {
                $config = wa('shop')->getConfig();
                /**
                 * @var shopConfig $config
                 */
                $settings = $config->getCheckoutSettings();
                if ($settings['contactinfo']['fields']['address']) {
                    foreach ($settings['contactinfo']['fields']['address']['fields'] as $k => $f) {
                        if (!empty($f['value'])) {
                            $address[$k] = $f['value'];
                        }
                    }
                }
            }


            foreach ($shipping_ids as $shipping_id) {
                $this->response[$shipping_id] = $this->getRates($shipping_id, $items, $address, $total);
            }
        }
    }

}
