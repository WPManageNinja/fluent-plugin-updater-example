# Fluent Plugin Updater

A WordPress plugin updater and licensing system that handles automatic updates and license management for premium
plugins.

## Installation

1. Copy the `updater` folder to your plugin
2. Include and initialize the licensing class:
3. Change The Namespaces `FluentUpdater` with `YourPluginNameSpace` and paths as per your plugin structure

Please make sure you change the namespace `YourPluginNameSpace` to your plugin's namespace and update the paths accordingly.

```php
if (!class_exists('\YourPluginNameSpace\FluentLicensing')) {
    require_once plugin_dir_path(__FILE__) . 'updater/FluentLicensing.php';
}

$instance = new \YourPluginNameSpace\FluentLicensing();

$instance->register([
    'version'     => '1.0.0', // Current version of your plugin
    'item_id'     => "product_id", // Product ID from FluentCart
    'basename'    => plugin_basename(__FILE__), // Plugin basename (e.g., 'your-plugin/your-plugin.php')
    'api_url'     => 'https://your-api-domain.com/' // Your WordPress URL where you have fluent-cart installed
]);
```

## Configuration Parameters

The `register()` method accepts the following configuration parameters:

- `version` (required): Current version of your plugin
- `license_key` (optional): License key for the plugin (If you want to manage license keys on your own way)
- `item_id` (required): Product ID from FluentCart
- `basename` (required): Plugin basename (e.g., 'your-plugin/your-plugin.php')
- `api_url` (required): Your licensing API endpoint URL. Normally your WordPress site URL where you have FluentCart
  installed
- `slug` (optional): Plugin slug (auto-generated from basename if not provided)
- `settings_key` (optional): Custom settings key for storing license data
- `license_key_callback` (optional): Callback function to retrieve license key

## Available Methods

### `register($config = [])`

Initializes the licensing system with the provided configuration. This method must be called before using any other
methods.

**Parameters:**

- `$config` (array): Configuration array with licensing parameters

**Returns:** Instance of FluentLicensing class

**Example:**

```php
$instance->register([
    'version'     => '1.0.0',
    'license_key' => 'your-license-key-here',
    'item_id'     => "product_id",
    'basename'    => plugin_basename(__FILE__),
    'api_url'     => 'https://fluentcart.test/'
]);
```

### `activate($licenseKey = '')`

Activates a license key by sending an activation request to the licensing server.

**Parameters:**

- `$licenseKey` (string): The license key to activate

**Returns:**

- Array with license data on success
- WP_Error object on failure

**Example:**

```php

$instance = \YourPluginNameSpace\FluentLicensing::getInstance();
$response = $instance->activate('your-license-key-here');

if (is_wp_error($response)) {
    // Handle error
    echo $response->get_error_message();
} else {
    // License activated successfully
    echo 'License activated: ' . $response['status'];
}
```

### `deactivate()`

Deactivates the current license by sending a deactivation request to the licensing server.

**Parameters:** None

**Returns:**

- Array with deactivation response on success
- WP_Error object on failure

**Example:**

```php
$response = $instance->deactivate();

if (is_wp_error($response)) {
    // Handle error
    echo $response->get_error_message();
} else {
    // License deactivated successfully
    echo 'License deactivated';
}
```

### `getStatus($remoteFetch = false)`

Retrieves the current license status. Can fetch from local storage or remote server.

**Parameters:**

- `$remoteFetch` (boolean): Whether to fetch status from remote server (default: false)

**Returns:**

- Array with license status information
- WP_Error object on failure

**Example:**

```php
// Get local status
$localStatus = $instance->getStatus();

// available properties:
[
      'license_key'     => $licenseKey,
      'status'          => // valid / invalid / disabled / unregistered / error,
      'variation_id'    => 123, // Price ID from FluentCart
      'variation_title' => '5 Sites License', // Price title from FluentCart
      'expires'         => '2026-12-31 23:24:50', // Expiration date in Y-m-d format or 'lifetime' for lifetime licenses
      'activation_hash' => 'UNIQUE_HASH' // Activation hash if available
]

// Get remote status (checks with server)
$remoteStatus = $instance->getStatus(true);

// This will rerun all the values from $instance->getStatus() but with some additional properties:
// 'is_expired' => 'yes' if expiring or expired otherwise empty
// 'renewal_url' => 'https://your-site.com/renewal-link' //
// 'error_type' => 'disabled' / 'key_mismatch' / 'validation_error' / 'invalid_license' / 'invalid_activation' / '' // Error type if any
// 'message' => 'Error message if any' 

if (is_wp_error($remoteStatus)) {
    echo 'Error: ' . $remoteStatus->get_error_message();
} else {
    echo 'License status: ' . $remoteStatus['status']; // valid / invalid / disabled / unregistered / error
    echo 'Expires: ' . $remoteStatus['expires'];
    
    echo 'Is Expiring / Expired: '. $remoteStatus['is_expired']; // yes if expiring or expired otherwise empty
    echo 'Renewal URL: '. $remoteStatus['renewal_url']; // Renewal URL if available
    echo 'Error Type: '. $remoteStatus['error_type']; // Error type if any possible values: disabled / key_mismatch / validation_error / invalid_license / invalid_activation / 
    echo 'Error Message: '. $remoteStatus['message']; // Error message if any
}
```

### `getCurrentLicenseKey()`

Retrieves the currently stored license key.

**Parameters:** None

**Returns:** String containing the current license key or empty string if not set

**Example:**

```php
$licenseKey = $instance->getCurrentLicenseKey();
echo 'Current license key: ' . $licenseKey;
```

### `getInstance()`

Gets the singleton instance of the FluentLicensing class.

**Parameters:** None

**Returns:** Instance of FluentLicensing class

**Example:**

```php
$instance = \YourPluginNameSpace\FluentLicensing::getInstance();
```

## License Status Values

The license status can have the following values:

- `valid`: License is active and valid
- `invalid`: License is invalid or expired
- `disabled`: License is disabled due to refund or by admin
- `unregistered`: No license is registered
- `error`: An error occurred during status check

## Error Handling

All methods that communicate with the licensing server may return a `WP_Error` object on failure. Always check for
errors:

```php
$response = $instance->activate('your-license-key');

if (is_wp_error($response)) {
    // Handle the error
    $error_message = $response->get_error_message();
    $error_code = $response->get_error_code();
    
    // Log or display the error
    error_log("License activation failed: $error_message");
} else {
    // Handle success
    echo "License activated successfully";
}
```

## Complete Usage Example

```php
<?php
/*
Plugin Name: Your Plugin Name
Description: Your plugin description
Version: 1.0.0
*/

if (defined('YOUR_PLUGIN_PATH')) {
    return;
}

add_action('init', function () {
    if (!class_exists('\YourPluginNameSpace\FluentLicensing')) {
        require_once plugin_dir_path(__FILE__) . 'updater/FluentLicensing.php';
    }
    $instance = new \YourPluginNameSpace\FluentLicensing();
    // Register the licensing system
    $instance->register([
        'version'     => '1.0.0',
        'license_key' => 'your-license-key-here',
        'item_id'     => "product_id",
        'basename'    => plugin_basename(__FILE__),
        'api_url'     => 'https://your-api-domain.com/'
    ]);
    
    // get instance from anywhere your plugin
    
    $instance = \YourPluginNameSpace\FluentLicensing::getInstance();

    // Example: Activate a license
    // $response = $instance->activate('your-license-key');
    
    // Example: Check license status from local DB
    // $status = $instance->getStatus();
    
    // Example: Check license status from  remote server
    // $status = $instance->getStatus(true);
    
    // Example: Deactivate license
    // $response = $instance->deactivate();
});
```

## API Endpoints

The licensing system communicates with your API server using the following endpoints:

**URLS:**

- Activate License: `https://your-fluentcart-shop.com/?fluent-cart=activate_license`
- Deactivate License: `https://your-fluentcart-shop.com/?fluent-cart=deactivate_license`
- Check Status: `https://your-fluentcart-shop.com/?fluent-cart=check_license`
- License version: `https://your-fluentcart-shop.com/?fluent-cart=get_license_version`
- License version with package download and info: `https://your-fluentcart-shop.com/?fluent-cart=get_license_version`


You need to add the following parameters in the request body for each endpoint:

- `item_id`: Product ID
- `current_version`: Plugin version
- `site_url`: Site URL
- `license_key`: License key (when applicable)



## Built in Settings Page
If you want to use the built-in settings page to manage license keys, you can enable it by adding the following line
after registering the licensing system:

```php

require_once plugin_dir_path(__FILE__) . 'updater/LicensingSettings.php';

$liecnseSettings = (new \YourPluginNameSpace\LicenseSettings())
        ->register($licenseInstance) // pass the instance of FluentLicensing
        ->setConfig([
            'menu_title'      => 'Awesome License',
            'title'           => 'License Settings',
            'license_key'     => 'License Key',
            'action_renderer' => 'your_plugin/plign_license_form', // optional, if you want to render content with your own with do_action('fluent_licenseing_render_{action_renderer}')
            'purchase_url'    => 'https://yourstore.com/pricing?utm_source=plugin_updater&utm_medium=plugin_updater&utm_campaign=fluent_plugin_updater_example', // your product purchase URL
            'account_url'     => 'https://yourstore.com/account', // Account URL where user can manage their license keys
            'plugin_name'     => 'Awesome Addon', // Your plugin name
        ]);

// If you want to render the settings page in WordPress Admin Panel. Use this:
$liecnseSettings->addPage([
            'type' => 'options', // possible values: options / submenu / menu
            'parent_slug' => 'tools.php' // if type is submenu then parent slug is required
        ])

// OR: If you want to render the license form in your own settings page's content, you can use the following action:
do_action('fluent_licenseing_render_your_plugin/plign_license_form');
```
