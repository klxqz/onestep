<?php

$domains_settings = array();
if (class_exists('shopOnestep')) {
    shopOnestep::saveDomainsSettings($domains_settings);
}