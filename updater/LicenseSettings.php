<?php

namespace FluentUpdater;

class LicenseSettings
{

    private static $instance;

    private $licensing = null;

    private $menuArgs = [];

    private $config = [];

    public function register($licensing, $config = [])
    {
        if (self::$instance) {
            return self::$instance; // Return existing instance if already set.
        }

        if (!$licensing) {
            try {
                $licensing = FluentLicensing::getInstance();
            } catch (\Exception $e) {
                return new self(); // Return empty instance if FluentLicensing is not available.
            }
        }

        $this->licensing = $licensing;

        if (!$this->config) {
            $defaultLabels = [
                'menu_title'      => 'License Settings',
                'page_title'      => 'License Settings',
                'title'           => 'License Settings',
                'description'     => 'Manage your license settings for the plugin.',
                'license_key'     => 'License Key',
                'purchase_url'    => '',
                'account_url'     => '',
                'plugin_name'     => '',
                'action_renderer' => ''
            ];

            $this->config = wp_parse_args($config, $defaultLabels);
        }

        $ajaxPrefix = 'wp_ajax_' . $this->licensing->getConfig('slug') . '_license';

        add_action($ajaxPrefix . '_activate', array($this, 'handleLicenseActivateAjax'));
        add_action($ajaxPrefix . '_deactivate', array($this, 'handleLicenseDeactivateAjax'));
        add_action($ajaxPrefix . '_status', array($this, 'handleLicenseStatusAjax'));

        if (!empty($this->config['action_renderer'])) {
            add_action('fluent_licenseing_render_' . $this->config['action_renderer'], array($this, 'renderLicensingContent'));
        }

        self::$instance = $this; // Set the instance for future use.

        return self::$instance;
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            return new self();
        }

        return self::$instance; // Return the singleton instance.
    }

    public function setConfig($config = [])
    {
        $this->config = wp_parse_args($config, $this->config);
        return $this;
    }

    public function handleLicenseActivateAjax()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json([
                'message' => 'Sorry! You do not have permission to perform this action.',
            ], 422);
        }

        $nonce = isset($_POST['_nonce']) ? sanitize_text_field($_POST['_nonce']) : '';
        $licenseKey = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';

        if (!wp_verify_nonce($nonce, 'fct_license_nonce')) {
            wp_send_json([
                'message' => 'Invalid nonce. Please try again.',
            ], 422);
        }

        if (!$licenseKey) {
            wp_send_json([
                'message' => 'Please provide a valid license key.',
            ], 422);
        }

        $currentLicense = $this->licensing->getStatus();

        if ($currentLicense['status'] === 'active' && $currentLicense['license_key'] === $licenseKey) {
            wp_send_json([
                'message' => 'This license key is already active.',
            ], 200);
        }

        $activated = $this->licensing->activate($licenseKey);

        if (is_wp_error($activated)) {
            wp_send_json([
                'message' => $activated->get_error_message(),
                'status'  => 'api_error'
            ], 422);
        }

        if ($activated['status'] !== 'valid') {
            wp_send_json([
                'message' => 'License activation failed. Please check your license key.',
                'status'  => $activated['status']
            ], 422);
        }

        return wp_send_json([
            'message' => 'License activated successfully.',
            'status'  => 'active'
        ], 200);
    }

    public function handleLicenseDeactivateAjax()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json([
                'message' => 'Sorry! You do not have permission to perform this action.',
            ], 422);
        }

        $nonce = isset($_POST['_nonce']) ? sanitize_text_field($_POST['_nonce']) : '';

        if (!wp_verify_nonce($nonce, 'fct_license_nonce')) {
            wp_send_json([
                'message' => 'Invalid nonce. Please try again.',
            ], 422);
        }

        $deactivated = $this->licensing->deactivate();

        wp_send_json([
            'message'            => 'License deactivated successfully.',
            'remote_deactivated' => !is_wp_error($deactivated),
        ]);
    }

    public function handleLicenseStatusAjax()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json([
                'message' => 'Sorry! You do not have permission to perform this action.',
            ], 422);
        }

        $nonce = isset($_POST['_nonce']) ? sanitize_text_field($_POST['_nonce']) : '';

        if (!wp_verify_nonce($nonce, 'fct_license_nonce')) {
            wp_send_json([
                'message' => 'Invalid nonce. Please try again.',
            ], 422);
        }

        $status = $this->licensing->getStatus(true);

        if (is_wp_error($status)) {
            wp_send_json([
                'error_notice' => $status->get_error_message(),
            ]);
        }

        $message = '';
        if (!empty($status['is_expired'])) {
            $message = '<p>Your license has expired. Please renew your license to continue receiving updates and support.</p>';
            if (!empty($status['renewal_url'])) {
                $message .= '<p><a href="' . esc_url($status['renewal_url']) . '" target="_blank" class="button button-primary fct_renew_url_btn">Renew License</a></p>';
            }
        } else if (!empty($status['error_type']) && $status['error_type'] === 'disabled') {
            $message = $status['message'] ?? '<p>Your license has been disabled. Please contact support for assistance.</p>';
        }

        unset($status['license_key']);

        wp_send_json([
            'error_notice' => $message,
            'remote_data'  => $status,
        ]);
    }

    public function addPage($args)
    {
        if (!$this->licensing) {
            return;
        }

        $this->menuArgs = wp_parse_args($args, [
            'type'        => 'submenu', // Can be: menu, options, submenu.
            'page_title'  => $this->config['page_title'] ?? '',
            'menu_title'  => $this->config['menu_title'] ?? '',
            'capability'  => 'manage_options',
            'parent_slug' => 'tools.php',
            'menu_slug'   => $this->licensing->getConfig('slug') . '-manage-license',
            'menu_icon'   => '',
            'position'    => 999
        ]);

        add_action('admin_menu', array($this, 'createMenuPage'), 999);

        return $this;
    }

    public function createMenuPage()
    {
        switch ($this->menuArgs['type']) {
            case 'menu':
                $this->createTopeLevelMenuPage();
                break;
            case 'submenu':
                $this->createSubMenuPage();
                break;
            case 'options':
                $this->createOptionsPage();
                break;
        }
    }

    private function createTopeLevelMenuPage()
    {
        add_menu_page(
            $this->menuArgs['page_title'],
            $this->menuArgs['menu_title'],
            $this->menuArgs['capability'],
            $this->menuArgs['menu_slug'],
            array($this, 'renderLicensingContent'),
            $this->menuArgs['menu_icon'] ?? 'dashicons-admin-generic',
            $this->menuArgs['position'] ?? 100
        );
    }

    private function createOptionsPage()
    {
        if (function_exists('add_options_page')) {
            add_options_page(
                $this->menuArgs['page_title'],
                $this->menuArgs['menu_title'],
                $this->menuArgs['capability'],
                $this->menuArgs['menu_slug'],
                array($this, 'renderLicensingContent')
            );
        }
    }

    private function createSubMenuPage()
    {
        add_submenu_page(
            $this->menuArgs['parent_slug'],
            $this->menuArgs['page_title'],
            $this->menuArgs['menu_title'],
            $this->menuArgs['capability'],
            $this->menuArgs['menu_slug'],
            array($this, 'renderLicensingContent'),
            $this->menuArgs['position'] ?? 10
        );
    }

    public function renderLicensingContent()
    {
        if (!$this->licensing) {
            echo '<div class="fct_error"><p>Licensing instance is not available.</p></div>';
            return;
        }

        $licenseStatus = $this->licensing->getStatus();
        $purchaseUrl = $this->config['purchase_url'] ?? '';
        ?>

        <div class="fct_licensing_wrap">
            <div class="fct_licensing_header">
                <h1><?php echo esc_html($this->config['title']); ?></h1>
                <?php if ($this->config['account_url']): ?>
                    <a rel="noopener" target="_blank" href="<?php echo esc_url($this->config['account_url']); ?>">Account</a>
                <?php endif; ?>
            </div>

            <div id="fct_license_body" class="fct_licensing_body">
                <?php if ($licenseStatus['status'] === 'valid'): ?>
                    <h2>Your License is Active</h2>
                    <p>Thank you for activating your license for <?php echo esc_html($this->config['plugin_name']); ?>
                        .</p>
                    <p><strong>Status:</strong> <?php echo esc_html(ucfirst($licenseStatus['status'])); ?></p>
                    <?php if ($licenseStatus['expires'] !== 'lifetime'): ?>
                        <p><strong>Expires On:</strong> <?php echo esc_html($licenseStatus['expires']); ?></p>
                    <?php else: ?>
                        <p><strong>Expires On:</strong> Never</p>
                    <?php endif; ?>
                    <p><a id="fct_deactivate_license" href="#">Deactivate License</a></p>

                    <div id="fct_error_wrapper"></div>

                <?php else: ?>
                    <h2>Please Provide the License key of <?php echo esc_html($this->config['plugin_name']); ?></h2>
                    <div class="fct_licensing_form">
                        <input type="text" name="fct_license_key"
                               value="<?php echo esc_attr($licenseStatus['license_key']); ?>"
                               placeholder="Your License Key"/>
                        <button id="license_key_submit" class="button button-primary">Activate License</button>
                    </div>

                    <div id="fct_error_wrapper"></div>

                    <?php if ($purchaseUrl): ?>
                        <div class="fct_purchase_wrap">
                            <p>
                                Don't have a license? <a rel="noopener" href="<?php echo esc_url($purchaseUrl); ?>"
                                                         target="_blank">
                                    Purchase License
                                </a>
                            </p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>


                <div class="fct_loader_item">
                    <svg fill="hsl(228, 97%, 42%)" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="4" cy="12" r="3">
                            <animate id="spinner_qFRN" begin="0;spinner_OcgL.end+0.25s" attributeName="cy"
                                     calcMode="spline" dur="0.6s" values="12;6;12"
                                     keySplines=".33,.66,.66,1;.33,0,.66,.33"/>
                        </circle>
                        <circle cx="12" cy="12" r="3">
                            <animate begin="spinner_qFRN.begin+0.1s" attributeName="cy" calcMode="spline" dur="0.6s"
                                     values="12;6;12" keySplines=".33,.66,.66,1;.33,0,.66,.33"/>
                        </circle>
                        <circle cx="20" cy="12" r="3">
                            <animate id="spinner_OcgL" begin="spinner_qFRN.begin+0.2s" attributeName="cy"
                                     calcMode="spline" dur="0.6s" values="12;6;12"
                                     keySplines=".33,.66,.66,1;.33,0,.66,.33"/>
                        </circle>
                    </svg>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {

                var licenseBody = document.getElementById('fct_license_body');

                function setError(errorMessage, isHtml = false) {
                    var errorWrapper = document.getElementById('fct_error_wrapper');
                    if (!errorMessage) {
                        if (errorWrapper) {
                            errorWrapper.innerHTML = '';
                        }
                        return;
                    }
                    if (errorWrapper) {
                        // create dom for security
                        var errorDiv = document.createElement('div');
                        errorDiv.className = 'fct_error_notice';

                        if (isHtml) {
                            errorDiv.innerHTML = errorMessage;
                        } else {
                            errorDiv.textContent = errorMessage;
                        }

                        errorWrapper.innerHTML = '';
                        errorWrapper.appendChild(errorDiv);
                    } else {
                        alert(errorMessage);
                    }
                }

                function sendAjaxRequest(action, data) {

                    // add class loader
                    licenseBody && licenseBody.classList.add('fct_loading');

                    setError(''); // Clear previous errors

                    var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
                    var _nonce = '<?php echo esc_js(wp_create_nonce('fct_license_nonce')); ?>';

                    data.action = '<?php echo $this->licensing->getConfig('slug'); ?>_license_' + action;
                    data._nonce = _nonce;

                    return new Promise((resolve, reject) => {
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', ajaxUrl, true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onload = function () {
                            console.log(xhr.responseText);
                            if (xhr.status >= 200 && xhr.status < 300) {
                                resolve(JSON.parse(xhr.responseText));
                            } else {
                                // Handle errors and send the response json
                                reject(JSON.parse(xhr.responseText));
                            }
                        };
                        xhr.onerror = function () {
                            reject({
                                message: 'Network error occurred while processing the request.'
                            });
                        };
                        xhr.send(new URLSearchParams(data).toString());

                        // remove class loader on ajax complete
                        xhr.onloadend = function () {
                            licenseBody && licenseBody.classList.remove('fct_loading');
                        }
                    });
                }

                function activateLicense(licenseKey) {
                    if (!licenseKey) {
                        alert('Please enter a valid license key.');
                        return;
                    }

                    sendAjaxRequest('activate', {license_key: licenseKey})
                        .then(response => {
                            location.reload(); // Reload the page to reflect changes
                        })
                        .catch(error => {
                            setError(error.message || 'An error occurred while activating the license.');
                        });
                }

                <?php if($licenseStatus['status'] !== 'unregistered'): ?>
                sendAjaxRequest('status', {})
                    .then(response => {
                        if (response.error_notice) {
                            setError(response.error_notice, true);
                        }
                    });
                <?php endif; ?>

                var activateBtn = document.getElementById('license_key_submit');
                if (activateBtn) {
                    activateBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        var licenseKey = document.querySelector('input[name="fct_license_key"]').value.trim();
                        activateLicense(licenseKey);
                    });
                }

                var deactivateBtn = document.getElementById('fct_deactivate_license');
                if (deactivateBtn) {
                    deactivateBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        sendAjaxRequest('deactivate', {})
                            .then(response => {
                                console.log(response);
                                window.location.reload();
                            })
                            .catch(error => {
                                window.location.reload();
                            });
                    });
                }

                document.querySelectorAll('.update-nag, .notice, #wpbody-content > .updated, #wpbody-content > .error').forEach(element => element.remove());
            });
        </script>

        <style>
            .fct_licensing_wrap {
                position: relative;
                max-width: 600px;
                margin: 30px auto;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            .fct_loader_item {
                display: none;
            }

            .fct_loading .fct_loader_item {
                display: block;
                position: absolute;
                right: 10px;
                bottom: 0px;
            }

            .fct_loader_item svg {
                fill: #686a6b;
                width: 40px;
            }

            .fct_error_notice {
                color: #ff4e16;
                margin-top: 20px;
                font-size: 15px;
            }

            .fct_error_notice p {
                font-size: 15px;
            }

            .fct_purchase_wrap {
                margin-top: 20px;
                display: block;
                overflow: hidden;
            }

            .fct_licensing_header {
                background: #f7fafc;
                padding: 15px 20px;
                border-bottom: 1px solid #ddd;
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
            }

            .fct_licensing_header a {
                text-decoration: none;
                background: #0073aa;
                color: #fff;
                padding: 2px 12px;
                border-radius: 4px;
            }

            .fct_licensing_header h1 {
                margin: 0;
                font-size: 20px;
                padding: 0;
            }

            .fct_licensing_body {
                padding: 30px 20px;
            }

            .fct_licensing_wrap h2 {
                margin-top: 0;
                font-size: 18px;
                margin-bottom: 10px;
            }

            .fct_licensing_form {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 15px;
                flex-wrap: wrap;
            }

            .fct_licensing_form input {
                width: 100%;
                padding: 6px 10px;
            }
        </style>

        <?php
    }
}
