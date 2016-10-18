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

        $checkout_action = new shopOnestepPluginFrontendCheckoutAction();
        $checkout_action->run();

        $this->view->assign('onestep_css_url', shopOnestepHelper::getRouteTemplateUrl('onestep_css'));
        $this->view->assign('onestep_js_url', shopOnestepHelper::getRouteTemplateUrl('onestep_js'));

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

}
