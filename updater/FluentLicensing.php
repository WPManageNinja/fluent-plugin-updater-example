<?php

namespace FluentUpdater;

class FluentLicensing
{
    private static $instance;

    private $config = [];

    public $settingsKey = '';

    public function register($config = [])
    {
        if (self::$instance) {
            return self::$instance; // Return existing instance if already set.
        }

        if (empty($config['basename']) || empty($config['version']) || empty($config['api_url'])) {
            throw new \Exception('Invalid configuration provided for FluentLicensing. Please provide basename, version, and api_url.');
        }

        $this->config = $config;
        $baseName = isset($config['basename']) ? $config['basename'] : plugin_basename(__FILE__);

        $slug = isset($config['slug']) ? $config['slug'] : explode('/', $baseName)[0];
        $this->config['slug'] = (string)$slug;

        $this->settingsKey = isset($config['settings_key']) ? $config['settings_key'] : '__' . $this->config['slug'] . '_sl_info';

        $config = $this->config;

        if (empty($config['license_key']) && empty($config['license_key_callback'])) {
            $config['license_key_callback'] = function () {
                return $this->getCurrentLicenseKey();
            };
        }

        if (!class_exists('\\' . __NAMESPACE__ . '\PluginUpdater')) {
            require_once __DIR__ . '/PluginUpdater.php';
        }

        // Initialize the updater with the provided configuration.
        new PluginUpdater($config);

        self::$instance = $this; // Set the instance for future use.
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            throw new \Exception('Licensing is not registered. Please call register() method first.');
        }

        return self::$instance; // Return the singleton instance.
    }

    public function activate($licenseKey = '')
    {
        if (!$licenseKey) {
            return new \WP_Error('license_key_missing', 'License key is required for activation.');
        }

        $response = $this->apiRequest('activate_license', [
            'license_key' => $licenseKey,
        ]);

        if (is_wp_error($response)) {
            return $response; // Return the error response if there is an error.
        }

        $saveData = [
            'license_key'     => $licenseKey,
            'status'          => $response['status'] ?? 'valid',
            'variation_id'    => $response['variation_id'] ?? '',
            'expires'         => $response['expiration_date'] ?? '',
            'activation_hash' => $response['activation_hash'] ?? ''
        ];

        // Save the license data to the database.
        update_option($this->settingsKey, $saveData, false);

        return $saveData; // Return the saved data.
    }

    public function deactivate()
    {
        return $this->apiRequest('deactivate_license', [
            'license_key' => $this->getCurrentLicenseKey()
        ]);
    }

    public function getStatus($remoteFetch = false)
    {
        $currentLicense = get_option($this->settingsKey, []);
        if (!$currentLicense || !is_array($currentLicense) || empty($currentLicense['license_key'])) {
            $currentLicense = [
                'license_key'  => '',
                'status'       => 'unregistered',
                'variation_id' => '',
                'expires'      => ''
            ];
        }

        if (!$remoteFetch) {
            return $currentLicense; // Return the current license status without fetching from the API.
        }

        $remoteStatus = $this->apiRequest('check_license', [
            'license_key'     => $currentLicense['license_key'],
            'activation_hash' => $currentLicense['activation_hash'],
            'item_id'         => $this->config['item_id'],
            'site_url'             => home_url()
        ]);

        if (is_wp_error($remoteStatus)) {
            return $remoteStatus; // Return the error response if there is an error.
        }

        $status = isset($remoteStatus['status']) ? $remoteStatus['status'] : 'unregistered';
        $errorType = isset($remoteStatus['error_type']) ? $remoteStatus['error_type'] : null;

        if (!empty($currentLicense['status'])) {
            $currentLicense['status'] = $status;
            if (!empty($remoteStatus['expiration_date'])) {
                $currentLicense['expires'] = sanitize_text_field($currentLicense['expires']);
            }

            if (!empty($remoteStatus['variation_id'])) {
                $currentLicense['variation_id'] = sanitize_text_field($remoteStatus['variation_id']);
            }

            update_option($this->settingsKey, $currentLicense, false); // Save the updated license status.
        } else {
            $currentLicense['status'] = 'error';
        }

        if ($errorType) {
            return new \WP_Error($errorType, 'License check failed: ' . $remoteStatus['message'], $remoteStatus);
        }

        return $currentLicense;
    }

    public function getCurrentLicenseKey()
    {
        $status = $this->getStatus();
        return isset($status['license_key']) ? $status['license_key'] : ''; // Return the current license key.
    }

    private function apiRequest($action, $data = [])
    {
        $url = $this->config['api_url'];
        $fullUrl = add_query_arg(array(
            'fluent-cart' => $action,
        ), $url);

        $defaults = [
            'item_id'         => $this->config['item_id'],
            'current_version' => $this->config['version'],
            'site_url'        => home_url(),
        ];

        $payload = wp_parse_args($data, $defaults);

        // send the post request to the API.
        $response = wp_remote_post($fullUrl, array(
            'timeout'   => 15,
            'body'      => $payload,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            return $response; // Return the error response if there is an error.
        }

        if (200 !== wp_remote_retrieve_response_code($response)) {
            $errorData = wp_remote_retrieve_body($response);
            if (!empty($errorData)) {
                $decodedData = json_decode($errorData, true);
                if ($decodedData) {
                    $errorData = $decodedData;
                }
            }
            return new \WP_Error('api_error', 'API request failed with status code: ' . wp_remote_retrieve_response_code($response), $errorData);
        }

        $responseData = json_decode(wp_remote_retrieve_body($response), true); // Return the decoded response body.

        if ($responseData) {
            return $responseData;
        }

        return new \WP_Error('api_error', 'API request returned an empty or not JSON response.', []);
    }
}
