<?php

class shopOnestep {

    public static function getRouteHash() {
        $domain = wa()->getRouting()->getDomain(null, true);
        $route = wa()->getRouting()->getRoute();
        return md5($domain . '/' . $route['url']);
    }

    public static function getDomainsSettings() {

        $cache = new waSerializeCache('shopOnestepPlugin');

        if ($cache && $cache->isCached()) {
            $domains_settings = $cache->get();
        } else {
            $app_settings_model = new waAppSettingsModel();
            $routing = wa()->getRouting();
            $domains_routes = $routing->getByApp('shop');
            $app_settings_model->get(shopOnestepPlugin::$plugin_id, 'domains_settings');
            $domains_settings = json_decode($app_settings_model->get(shopOnestepPlugin::$plugin_id, 'domains_settings'), true);

            if (empty($domains_settings)) {
                $domains_settings = array();
            }

            foreach ($domains_routes as $domain => $routes) {
                foreach ($routes as $route) {
                    $domain_route = md5($domain . '/' . $route['url']);
                    if (empty($domains_settings[$domain_route])) {
                        $domains_settings[$domain_route] = shopOnestepPlugin::$default_settings;
                    }
                    foreach (shopOnestepPlugin::$default_settings['templates'] as $tpl_name => $tpl) {
                        $domains_settings[$domain_route]['templates'][$tpl_name] = $tpl;

                        $tpl_full_path = $tpl['tpl_path'] . $domain_route . '_' . $tpl['tpl_name'] . '.' . $tpl['tpl_ext'];
                        $domains_settings[$domain_route]['templates'][$tpl_name]['tpl_full_path'] = $tpl_full_path;
                        $template_path = wa()->getDataPath($tpl_full_path, $tpl['public'], 'shop', true);


                        if (file_exists($template_path)) {
                            $domains_settings[$domain_route]['templates'][$tpl_name]['template_path'] = $template_path;
                            $domains_settings[$domain_route]['templates'][$tpl_name]['template'] = file_get_contents($template_path);
                            $domains_settings[$domain_route]['templates'][$tpl_name]['change_tpl'] = 1;
                        } else {
                            $domains_settings[$domain_route]['templates'][$tpl_name]['tpl_full_path'] = $tpl['tpl_path'] . $tpl['tpl_name'] . '.' . $tpl['tpl_ext'];
                            $template_path = wa()->getAppPath($tpl['tpl_path'] . $tpl['tpl_name'] . '.' . $tpl['tpl_ext'], 'shop');
                            $domains_settings[$domain_route]['templates'][$tpl_name]['template_path'] = $template_path;
                            $domains_settings[$domain_route]['templates'][$tpl_name]['template'] = file_get_contents($template_path);
                            $domains_settings[$domain_route]['templates'][$tpl_name]['change_tpl'] = 0;
                        }
                    }
                }

                if ($domains_settings && $cache) {
                    $cache->set($domains_settings);
                }
            }
        }

        return $domains_settings;
    }

    public static function saveDomainsSettings($domains_settings) {


        $app_settings_model = new waAppSettingsModel();
        $routing = wa()->getRouting();
        $domains_routes = $routing->getByApp('shop');

        foreach ($domains_routes as $domain => $routes) {
            foreach ($routes as $route) {
                $domain_route = md5($domain . '/' . $route['url']);

                foreach (shopOnestepPlugin::$default_settings['templates'] as $id => $template) {
                    $tpl_full_path = $template['tpl_path'] . $domain_route . '_' . $template['tpl_name'] . '.' . $template['tpl_ext'];
                    $template_path = wa()->getDataPath($tpl_full_path, $template['public'], 'shop', true);

                    @unlink($template_path);
                    if (empty($domains_settings[$domain_route]['templates'][$id]['reset_tpl'])) {
                        $source_path = wa()->getAppPath($template['tpl_path'] . $template['tpl_name'] . '.' . $template['tpl_ext'], 'shop');
                        $source_content = file_get_contents($source_path);


                        if (!isset($domains_settings[$domain_route]['templates'][$id]['template'])) {
                            continue;
                        }

                        $post_template = $domains_settings[$domain_route]['templates'][$id]['template'];
                        if (preg_replace('/\s/', '', $source_content) != preg_replace('/\s/', '', $post_template)) {
                            $f = fopen($template_path, 'w');
                            if (!$f) {
                                throw new waException('Не удаётся сохранить шаблон. Проверьте права на запись ' . $template_path);
                            }
                            fwrite($f, $post_template);
                            fclose($f);
                        }
                    }
                }
                unset($domains_settings[$domain_route]['templates']);
            }
        }

        $app_settings_model->set(shopOnestepPlugin::$plugin_id, 'domains_settings', json_encode($domains_settings));
        $cache = new waSerializeCache('shopOnestepPlugin');
        if ($cache && $cache->isCached()) {
            $cache->delete();
        }
    }

    public static function getDomainSettings() {
        $domains_settings = self::getDomainsSettings();
        $hash = self::getRouteHash();
        if (!empty($domains_settings[$hash])) {
            return $domains_settings[$hash];
        } else {
            return false;
        }
    }

}
