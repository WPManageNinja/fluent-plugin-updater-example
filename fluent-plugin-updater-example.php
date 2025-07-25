<?php

/*
Plugin Name: Fluent Plugin Updater Example
Description: Plugin to demonstrate Fluent Plugin Updater functionality.
Plugin URI:   https://fluentcart.com
Version: 1.0.0
Author:       FluentCart
Author URI:   https://fluentcart.com
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  fluent-plugin-updater-example
Domain Path:  /language
*/
if (defined('FLUENT_PLUGIN_UPDATER_EXAMPLE_PLUGIN_PATH')) {
    return;
}

add_action('init', function () {

    if (!class_exists('\FluentUpdater\FluentLicensing')) {
        require_once plugin_dir_path(__FILE__) . 'updater/FluentLicensing.php';
    }

    (new \FluentUpdater\FluentLicensing())->register([
        'version'     => '1.7.72',
        'license_key' => '5bfc9711543a610d61bd98493140580e',
        'item_id'     => 7551031,
        'basename'    => plugin_basename(__FILE__), // shoudld be like: your-plugin_name/your-plugin-name.php
        'api_url'     => 'https://wpmanageninja.lab/'
    ]);

});
