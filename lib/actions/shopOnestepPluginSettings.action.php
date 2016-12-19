<?php

class shopOnestepPluginSettingsAction extends waViewAction {

    public function execute() {
        $this->view->assign(array(
            'templates' => shopOnestepPlugin::$templates,
            'plugin' => wa()->getPlugin('onestep'),
            'route_hashs' => shopOnestepHelper::getRouteHashs(),
        ));
    }

}
