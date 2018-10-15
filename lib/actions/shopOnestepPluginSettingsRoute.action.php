<?php

class shopOnestepPluginSettingsRouteAction extends waViewAction {

    public function execute() {
        $route_hash = waRequest::get('route_hash');
        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->set(array('shop', 'onestep'), 'route_hash', $route_hash);
        $view = wa()->getView();
        $view->assign(array(
            'route_hash' => $route_hash,
            'route_settings' => shopOnestepHelper::getRouteSettings($route_hash),
            'templates' => shopOnestepHelper::getRouteTemplates($route_hash),
        ));
    }

}
