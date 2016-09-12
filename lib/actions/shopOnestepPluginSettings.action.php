<?php

class shopOnestepPluginSettingsAction extends waViewAction {

    public function execute() {
        $this->view->assign(array(
            'templates' => shopOnestepPlugin::$templates,
            'settings' => wa()->getPlugin('onestep')->getSettings(),
            'route_hashs' => shopOnestepHelper::getRouteHashs(),
        ));
    }

}
