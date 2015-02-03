<?php

class shopOnestepPluginBackendSaveController extends waJsonController {

    public function execute() {
        try {
            $app_settings_model = new waAppSettingsModel();
            $settings = waRequest::post('shop_onestep', array());
            $domains_settings = waRequest::post('domains_settings', array());
            $reset = waRequest::post('reset');
            foreach ($settings as $name => $value) {
                $app_settings_model->set(shopOnestepPlugin::$plugin_id, $name, $value);
            }
            if ($reset) {
                $domains_settings = array();
            }
            shopOnestep::saveDomainsSettings($domains_settings);


            $this->response['message'] = "Сохранено";
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }

}
