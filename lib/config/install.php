<?php

$plugin_id = array('shop', 'onestep');
$app_settings_model = new waAppSettingsModel();
$app_settings_model->set($plugin_id, 'min_sum', '0');
