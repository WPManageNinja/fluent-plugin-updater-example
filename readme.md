# Fluent Plugin Updater

A WordPress plugin updater and licensing system that handles automatic updates and license management for premium plugins.

## Installation

1. Copy the `updater` folder to your plugin
2. Include and initialize the licensing class:

```php
if (!class_exists('\FluentUpdater\FluentLicensing')) {
    require_once plugin_dir_path(__FILE__) . 'updater/FluentLicensing.php';
}

$instance = new \FluentUpdater\FluentLicensing();

$instance->register([
    'version'     => '1.0.0',
    'license_key' => 'your-license-key-here',
    'item_id'     => 114,
    'basename'    => plugin_basename(__FILE__),
    'api_url'     => 'https://your-api-domain.com/'
]);
```

## Configuration Parameters

The `register()` method accepts the following configuration parameters:

- `version` (required): Current version of your plugin
- `license_key` (optional): License key for the plugin
- `item_id` (required): Product ID from your licensing system
- `basename` (required): Plugin basename (e.g., 'your-plugin/your-plugin.php')
- `api_url` (required): Your licensing API endpoint URL
- `slug` (optional): Plugin slug (auto-generated from basename if not provided)
- `settings_key` (optional): Custom settings key for storing license data
- `license_key_callback` (optional): Callback function to retrieve license key

## Available Methods

### `register($config = [])`

Initializes the licensing system with the provided configuration. This method must be called before using any other methods.

**Parameters:**
- `$config` (array): Configuration array with licensing parameters

**Returns:** Instance of FluentLicensing class

**Example:**
```php
$instance->register([
    'version'     => '1.0.0',
    'license_key' => 'flpa3ce17926f2cb5c431691c318c9ea0e2',
    'item_id'     => 114,
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
$response = $instance->activate('flpa3ce17926f2cb5c431691c318c9ea0e2');

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

// Get remote status (checks with server)
$remoteStatus = $instance->getStatus(true);

if (is_wp_error($remoteStatus)) {
    echo 'Error: ' . $remoteStatus->get_error_message();
} else {
    echo 'License status: ' . $remoteStatus['status'];
    echo 'Expires: ' . $remoteStatus['expires'];
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
$instance = \FluentUpdater\FluentLicensing::getInstance();
```

## License Status Values

The license status can have the following values:

- `valid`: License is active and valid
- `invalid`: License is invalid or expired
- `unregistered`: No license is registered
- `error`: An error occurred during status check

## Error Handling

All methods that communicate with the licensing server may return a `WP_Error` object on failure. Always check for errors:

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
```

## API Endpoints

The licensing system communicates with your API server using the following endpoints:

- `activate_license`: Activates a license key
- `deactivate_license`: Deactivates a license
- `check_license`: Checks license status

All API requests include:
- `item_id`: Product ID
- `current_version`: Plugin version
- `url`: Site URL
- `license_key`: License key (when applicable)



