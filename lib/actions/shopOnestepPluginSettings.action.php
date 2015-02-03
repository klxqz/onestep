<?php

class shopOnestepPluginSettingsAction extends waViewAction {

    public function execute() {
        $app_settings_model = new waAppSettingsModel();
        
        $domain_routes = wa()->getRouting()->getByApp('shop');
        $domains_settings = shopOnestep::getDomainsSettings();
        $settings = $app_settings_model->get(shopOnestepPlugin::$plugin_id);
        $this->view->assign('domain_routes', $domain_routes);
        $this->view->assign('domains_settings', $domains_settings);
        $this->view->assign('settings', $settings);
    }

}
