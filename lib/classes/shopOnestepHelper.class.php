<?php

class shopOnestepHelper {

    public static function getRouteTemplateUrl($template_id, $route_hash = null) {
        if ($route_hash === null) {
            $route_hash = self::getCurrentRouteHash();
        }
        $template = self::getRouteTemplate($route_hash, $template_id, false);

        $template_url = '';

        if ($template['tpl_ext'] == 'css') {
            $css_content = file_get_contents($template['template_path']);
            $css_content = str_replace('{$wa_url}', wa()->getRootUrl(), $css_content);
            $tpl_full_path = $template['tpl_path'] . 'tmp' . $route_hash . '.' . $template['tpl_name'] . '.' . $template['tpl_ext'];
            $template_path = wa()->getDataPath($tpl_full_path, $template['public'], 'shop', true);
            $f = fopen($template_path, 'w');
            if (!$f) {
                throw new waException('Не удаётся сохранить шаблон. Проверьте права на запись ' . $template_path);
            }
            fwrite($f, $css_content);
            fclose($f);
            $template_url = wa()->getDataUrl($tpl_full_path, true, 'shop');
        } elseif ($template['tpl_ext'] == 'js') {
            if ($template['change_tpl']) {
                $template_url = wa()->getDataUrl($template['tpl_full_path'], true, 'shop');
            } else {
                $template_url = wa()->getAppStaticUrl() . $template['tpl_full_path'];
            }
        }

        return $template_url;
    }

    public static function getRouteTemplates($route_hash = null, $template_id = null, $read = true) {
        if ($route_hash === null) {
            $route_hash = self::getCurrentRouteHash();
        }
        if ($template_id) {
            return self::getRouteTemplate($route_hash, $template_id, $read);
        } else {
            $templates = array();
            foreach (shopOnestepPlugin::$templates as $template_id => $template) {
                $templates[$template_id] = self::getRouteTemplate($route_hash, $template_id, $read);
            }
            return $templates;
        }
    }

    protected static function getRouteTemplate($route_hash = null, $template_id, $read = true) {
        if (empty(shopOnestepPlugin::$templates[$template_id])) {
            return false;
        }
        if ($route_hash === null) {
            $route_hash = self::getCurrentRouteHash();
        }

        $template = shopOnestepPlugin::$templates[$template_id];

        $tpl_full_path = $template['tpl_path'] . $route_hash . '.' . $template['tpl_name'] . '.' . $template['tpl_ext'];
        $template_path = wa()->getDataPath($tpl_full_path, $template['public'], 'shop', true);
        if (file_exists($template_path)) {
            $template['change_tpl'] = 1;
        } else {
            $tpl_full_path = $template['tpl_path'] . $template['tpl_name'] . '.' . $template['tpl_ext'];
            $template_path = wa()->getAppPath($tpl_full_path, 'shop');
            $template['change_tpl'] = 0;
        }
        $template['tpl_full_path'] = $tpl_full_path;
        $template['template_path'] = $template_path;
        if ($read) {
            $template['template'] = file_get_contents($template_path);
        }
        return $template;
    }

    public static function getRouteSettings($route = null, $setting = null) {
        if ($route === null) {
            $route = self::getCurrentRouteHash();
        }
        $routes = wa()->getPlugin('onestep')->getSettings('routes');
        if (!empty($routes[$route])) {
            $route_settings = $routes[$route];
        } else {
            $route_settings = array();
        }

        if (!$setting) {
            return $route_settings;
        } elseif (!empty($route_settings[$setting])) {
            return $route_settings[$setting];
        } else {
            return null;
        }
    }

    public static function getCurrentRouteHash() {
        $domain = wa()->getRouting()->getDomain(null, true);
        $route = wa()->getRouting()->getRoute();
        return md5($domain . '/' . $route['url']);
    }

    public static function getRouteHashs() {
        $route_hashs = array();
        $routing = wa()->getRouting();
        $domain_routes = $routing->getByApp('shop');
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $route) {
                $route_url = $domain . '/' . $route['url'];
                $route_hashs[$route_url] = md5($route_url);
            }
        }
        return $route_hashs;
    }

}
