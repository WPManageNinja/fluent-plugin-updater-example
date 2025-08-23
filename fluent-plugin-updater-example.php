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
        require_once plugin_dir_path(__FILE__) . 'updater/LicenseSettings.php';
    }

    $fluentLicensing = (new \FluentUpdater\FluentLicensing())->register([
        'version'  => '1.7.72', // your current plugin version. Change this on every new release
        'item_id'  => 7560872, // The Product ID from your store
        'basename' => plugin_basename(__FILE__), // shoudld be like: your-plugin_name/your-plugin-name.php
        'api_url'  => 'https://manageninja.lab/' // your store URL where you sell the plugin or theme
    ]);

    (new \FluentUpdater\LicenseSettings())
        ->register($fluentLicensing)
        ->setConfig([
            'menu_title'      => 'Awesome License',
            'title'           => 'License Settings',
            'license_key'     => 'License Key',
            'action_renderer' => plugin_basename(__FILE__), // optional, if you want to render content with your own with do_action('fluent_licenseing_render_{action_renderer}')
            'purchase_url'    => 'https://yourstore.com/pricing?utm_source=plugin_updater&utm_medium=plugin_updater&utm_campaign=fluent_plugin_updater_example', // your product purchase URL
            'account_url'     => 'https://yourstore.com/account',
            'plugin_name'     => 'Awesome Addon',
        ])
        ->addPage([
            'type' => 'options'
        ]);

});
