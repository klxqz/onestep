<?php

class shopNoveltiesPluginFrontendNoveltiesAction extends shopFrontendAction
{
    protected $plugin_id = array('shop', 'novelties');
    
    public function execute()
    {
        $app_settings_model = new waAppSettingsModel();
        $status = $app_settings_model->get($this->plugin_id, 'status');
        
        if(!$status) {
            throw new waException(_ws("Page not found"),404);
        }
        
        $days = $app_settings_model->get($this->plugin_id, 'days');
        $collection = new shopNoveltiesProductsCollection();
        $collection->noveltiesFilter($days);
        $this->setCollection($collection);

        $page_title = $app_settings_model->get($this->plugin_id, 'page_title');
        wa()->getResponse()->setTitle($page_title);
        $this->view->assign('frontend_search', wa()->event('frontend_search'));
        $this->setThemeTemplate('search.html');
    }
}