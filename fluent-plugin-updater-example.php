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

    $instance = new \FluentUpdater\FluentLicensing();

    // Register the licensing system
    $instance->register([
        'version'     => '1.0.0',
        'license_key' => 'your-license-key-here',
        'item_id'     => 114,
        'basename'    => plugin_basename(__FILE__),
        'api_url'     => 'https://your-api-domain.com/'
    ]);

    // Example: Activate a license
    // $response = $instance->activate('your-license-key');
    
    // Example: Check license status
    // $status = $instance->getStatus(true);
    
    // Example: Deactivate license
    // $response = $instance->deactivate();
});
