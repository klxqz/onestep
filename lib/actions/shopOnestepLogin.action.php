<?php

class shopOnestepLoginAction extends shopLoginAction {

    protected function afterAuth() {
        $checkout_url = wa()->getRouteUrl('shop/frontend/onestep');
        $this->redirect($checkout_url);
    }

}
