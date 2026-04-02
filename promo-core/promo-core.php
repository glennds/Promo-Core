<?php
/*
 * Plugin Name: Promo Core
 * Plugin URI: https://www.digishock.com/webdevelopment/
 * Description: Diverse functionaliteiten op maat gemaakt voor Promotie.nl - Gebruik de ingebouwde instellingenpagina's om de functies te beheren.
 * Version: beta-1.5
 * Requires at least: 6.8.2
 * Requires PHP: 8.2
 * Author: Digishock
 * Author URI: https://www.digishock.com/
 * License: GNU-GPL3
 * Network: true
 * Update URI: #
 */

// Security check
defined('ABSPATH') || exit;



// --- //
// Load must-use functions from /mu-functions/. These functions can't be disabled via the settings page.
require_once plugin_dir_path(__FILE__) . 'mu-functions/cleanup-breakdance.php';
require_once plugin_dir_path(__FILE__) . 'mu-functions/force-woocommerce-hooks.php';
require_once plugin_dir_path(__FILE__) . 'mu-functions/improve-multisite-passwordreset.php';
require_once plugin_dir_path(__FILE__) . 'mu-functions/network-order-management.php';
require_once plugin_dir_path(__FILE__) . 'mu-functions/warehouse-sync.php';
require_once plugin_dir_path(__FILE__) . 'mu-functions/warehouse-taxonomy.php';
require_once plugin_dir_path(__FILE__) . 'mu-functions/woo-custom-billing-address.php';



// --- //
// Check if optional function is enabled before loading the file, based on the 'dscore_require_optional' list below this function.
function dscore_require_optional($key, $relative_path, $label = null) {
    if (!dscore_is_enabled($key)) {return;}

    $file = plugin_dir_path(__FILE__) . ltrim($relative_path, '/');
    if (is_readable($file)) {
        require_once $file;
        return;
    }

    $label = $label ?: $key;
    $message = sprintf(
        'Promo Core: missing file for "%s" (%s). Please reinstall or reupload the plugin.',
        $label,
        $relative_path
    );

    if (is_admin()) {
        $hook = function_exists('is_network_admin') && is_network_admin()
            ? 'network_admin_notices'
            : 'admin_notices';
        add_action($hook, function () use ($message) {
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        });
    }

    error_log($message);
}

// Only load /functions/ files if corresponding key is enabled in settings page.
function dscore_is_enabled($key) {
    $options = get_option('ds_functiontoggles', []);
    if (!is_array($options)) {$options = [];}
    return isset($options[$key]) && $options[$key] === 1;
}

// Load optional functions, ONLY if enabled in the wp-admin settings page
dscore_require_optional('general_sitelogo_field',            'functions/general-sitelogo-field.php',            'Site logo');
dscore_require_optional('general_disable_gutenberg',         'functions/general-disable-gutenberg.php',         'Disable Gutenberg');
dscore_require_optional('woo_disable_downloads',             'functions/woo-disable-downloads.php',             'WooCommerce disable downloads');

dscore_require_optional('woo_min_order_amount',              'functions/woo-min-order-amount.php',              'WooCommerce min order amount');
dscore_require_optional('woo_quantity_step',                 'functions/woo-quantity-step.php',                 'WooCommerce quantity step');
dscore_require_optional('woo_post_calculation_prices',       'functions/woo-post-calculation-prices.php',       'WooCommerce post-calculation prices');
dscore_require_optional('woo_trackntrace_field',             'functions/woo-trackntrace-field.php',             'WooCommerce Track & Trace field');
dscore_require_optional('woo_pricing_filters',               'functions/woo-pricing-filters.php',               'WooCommerce pricing filters');
dscore_require_optional('woo_variable_product_flag',         'functions/woo-variable-product-flag.php',         'WooCommerce variable product flag');
dscore_require_optional('woo_email_product_attributes',      'functions/woo-email-product-attributes.php',      'WooCommerce email product attributes');
dscore_require_optional('woo_webshop_closure',               'functions/woo-webshop-closure.php',               'WooCommerce webshop closure');

dscore_require_optional('users_redirect_guests_to_login',    'functions/users-redirect-guests-to-login.php',    'Users redirect guests to login');
dscore_require_optional('users_restrict_login_to_subsite',   'functions/users-restrict-login-to-subsite.php',   'Users restrict login to subsite');
dscore_require_optional('users_bulkgen_exportimport',        'functions/users-bulkgen-exportimport.php',        'Users bulk generation import/export');

// dscore_require_optional('warehouse_taxonomy_standalone',     'functions/warehouse-standalone.php',              'Warehouse taxonomy standalone');
// dscore_require_optional('warehouse_sync_multisite',          'functions/warehouse-sync.php',                    'Warehouse sync multisite');
// dscore_require_optional('network_warehouse_orders',          'functions/network-warehouse-orders.php',          'Network warehouse orders');



// --- //
// Create admin menu page
add_action('admin_init', function() {
    register_setting('dscore_settings', 'ds_functiontoggles', [
    'type' => 'array',
    'sanitize_callback' => 'dscore_sanitize_toggle_options'
    ]);
});

// Sanitize input from settings page, ensure all values are set and either 0 or 1
function dscore_sanitize_toggle_options($input) {
    $defaults = [
        'general_sitelogo_field'                    => 0,
        'general_disable_gutenberg'                 => 0,
        'woo_disable_downloads'                     => 0,

        'woo_min_order_amount'                      => 0,
        'woo_quantity_step'                         => 0,
        'woo_post_calculation_prices'               => 0,
        'woo_trackntrace_field'                     => 0,
        'woo_pricing_filters'                       => 0,
        'woo_variable_product_flag'                 => 0,
        'woo_email_product_attributes'              => 0,
        'woo_webshop_closure'                       => 0,

        'users_redirect_guests_to_login'            => 0,
        'users_restrict_login_to_subsite'           => 0,
        'users_bulkgen_exportimport'                => 0

        // 'warehouse_taxonomy_standalone'              => 0,
        // 'warehouse_sync_multisite'                   => 0,
        // 'network_warehouse_orders'                   => 1,
    ];

    if (!is_array($input)) {$input = [];}                           // Prevent null if function is toggled off
    $options = array_merge($defaults, array_map('absint', $input)); // Fill empty value with 0
    return $options;
}

// Add top-level and sub-item menu pages in wp-admin
add_action('admin_menu', function () {

    $icon_svg = 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -95.5 512 512">
            <path fill="currentColor" d="m249.7 0.1q-0.1 70-0.1 140-48.8-0.6-97.6-1.2c-19.5-0.2-40.5 0.1-56.1 11.8-10.2 7.7-16.8 19.8-19.2 32.4-2.5 12.7-1.1 25.8 2.4 38.2 3.2 11.3 8.5 22.7 18.1 29.4 11.1 7.9 25.7 8.3 39.3 8.3q56.4 0 112.7 0.1 0 30.2-0.1 60.4c0 0.2-87.5 1.2-95.5 1.3-30.3 0.4-64.6 0.6-92.5-13.1-25.2-12.5-42.9-35.9-52.2-62.2-14.3-40.1-13-93.1 15.9-126.9 19.1-22.4 43.4-34.5 72.7-38.1 26.8-3.4 54-0.6 81.1-1.1q0.4-39.7 0.8-79.4z"/>
            <path fill="currentColor" d="m412.6 78.8c25.1 0 75.2 0.2 75.2 0.2h9.9v58.3c0 0-83.8-0.4-121.3-0.4-3.9 0-6.5 0.4-10.2 0.4-12 0-16.8 4.9-16.6 14.6 0.2 10 7.6 17.8 17.7 18 14.5 0.3 29 0.2 43.5 0.2 16.7-0.1 33.3 0 49.7 3.3 28 5.7 50.2 33.7 51.3 62.5 1 25-3.6 47.2-22.3 65.5-13.4 13.1-29.3 19.3-47.4 19.4-49.6 0.4-160.2 0.3-160.2 0.3v-63.3c0 0 96.2 0.8 139.1 0.8 7.9 0 15.3-0.6 17.2-10.7 2.4-12.5-1.3-17.4-12.3-20.9-6.8-2.1-13.7-2.1-20.6-2.1-21.2 0-42.4 0-63.5-0.1-32.5-0.3-64.7-35.4-63.9-68 0.5-20.1 5.5-37.9 16.8-54.5 10.4-15.4 24.6-22.6 42.7-23.3 4.2-0.2 8.4-0.2 12.7-0.2q31.2 0 62.5 0z"/>
            <path fill="currentColor" d="m248.5 201.2c-0.3 20.1-15.8 34.7-36.4 34.3-19.4-0.3-35.2-16.9-34.9-36.6 0.2-19.4 16.8-35.1 36.6-34.7 21 0.5 35.1 15.4 34.7 37z"/>
        </svg>'
    );

    add_menu_page(                  // Top-level menu-item
        'Promo Core',               // Page title (browser tab)
        'Digishock',                // Menu title (sidebar nav)
        'manage_options',           // Capability (admins only)
        'dscore',                   // Menu slug
        'promocore_features',       // Callback to content of first submenu-item on clicking toplevel menu-item
        $icon_svg,                  // Icon
        9999                        // Menu position
    );

    add_submenu_page(               // First submenu-item, enable/disable features
        'dscore',                   // Parent slug
        'Functies',                 // Page title (browser tab)
        'Functies',                 // Menu title (sidebar nav)
        'manage_options',           // Capability (admins only)
        'promocore',                // Slug for this item
        'promocore_features',       // Function to call for page contents
        0
    );
});



// --- //
// Generate the page content
function promocore_features() {

    if (!current_user_can('manage_options')) {wp_die(__('You are not allowed to access this page.'));}

    // Get the current options, with defaults for any missing values to prevent undefined index notices
    $options = get_option('ds_functiontoggles', [
        'general_sitelogo_field'                    => 0,
        'general_disable_gutenberg'                 => 0,
        'woo_disable_downloads'                     => 0,

        'woo_min_order_amount'                      => 0,
        'woo_quantity_step'                         => 0,
        'woo_post_calculation_prices'               => 0,
        'woo_trackntrace_field'                     => 0,
        'woo_pricing_filters'                       => 0,
        'woo_variable_product_flag'                 => 0,
        'woo_email_product_attributes'              => 0,
        'woo_webshop_closure'                       => 0,

        'users_redirect_guests_to_login'            => 0,
        'users_restrict_login_to_subsite'           => 0,
        'users_bulkgen_exportimport'                => 0

        // 'warehouse_taxonomy_standalone'              => 0,
        // 'warehouse_sync_multisite'                   => 0,
        // 'network_warehouse_orders'                   => 1,
    ]);

    ?>
    <div class="wrap">
        <h1>Promo Core Functies</h1>

        <form method="post" action="options.php">
            <?php settings_fields('dscore_settings'); ?>

            <table class="ds-optionstable wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><h3>General & Optimisation</h3></th>
                        <th scope="col" style="width: 120px;"><h3>Status</h3></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Add site logo field in <b>Settings » General</b></td>
                        <td>
                            <input type="hidden" value="0" name="ds_functiontoggles[site_logo]">
                            <label class="ds-toggle"><input name="ds_functiontoggles[site_logo]" <?php checked((int) ($options['site_logo'] ?? 0), 1); ?> type="checkbox" value="1"><span class="ds-slider"></span></label>
                        </td>
                    </tr>
                    <tr>
                        <td>WebsiteNazorg.nl - Disable Gutenberg</td>
                        <td>
                            <input type="hidden" value="0" name="ds_functiontoggles[disable_gutenberg]">
                            <label class="ds-toggle"><input name="ds_functiontoggles[disable_gutenberg]" <?php checked((int) ($options['disable_gutenberg'] ?? 0), 1); ?> type="checkbox" value="1"><span class="ds-slider"></span></label>
                        </td>
                    </tr>
                    <tr>
                        <td>Disable downloadable products</td>
                        <td>
                            <input type="hidden" value="0" name="ds_functiontoggles[woo_disable_downloads]">
                            <label class="ds-toggle"><input name="ds_functiontoggles[woo_disable_downloads]" <?php checked((int) ($options['woo_disable_downloads'] ?? 0), 1); ?> type="checkbox" value="1"><span class="ds-slider"></span></label>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table class="ds-optionstable wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><h3>WooCommerce</h3></th>
                        <th scope="col" style="width: 120px;"><h3>Status</h3></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Minimal order quantity</td>
                        <td>
                            <input type="hidden" value="0" name="ds_functiontoggles[woo_min_order_amount]">
                            <label class="ds-toggle"><input name="ds_functiontoggles[woo_min_order_amount]" <?php checked((int) ($options['woo_min_order_amount'] ?? 0), 1); ?> type="checkbox" value="1"><span class="ds-slider"></span></label>
                        </td>
                    </tr>
                    <tr>
                        <td>Configure quantity increment step</td>
                        <td>
                            <input type="hidden" value="0" name="ds_functiontoggles[woo_quantity_step]">
                            <label class="ds-toggle"><input name="ds_functiontoggles[woo_quantity_step]" <?php checked((int) ($options['woo_quantity_step'] ?? 0), 1); ?> type="checkbox" value="1"><span class="ds-slider"></span></label>
                        </td>
                    </tr>
                    <tr>
                        <td>Allow post-calculation prices (disable regular price fields)</td>
                        <td>
                            <input type="hidden" value="0" name="ds_functiontoggles[woo_post_calculation_prices]">
                            <label class="ds-toggle"><input name="ds_functiontoggles[woo_post_calculation_prices]" <?php checked((int) ($options['woo_post_calculation_prices'] ?? 0), 1); ?> type="checkbox" value="1"><span class="ds-slider"></span></label>
                        </td>
                    </tr>
                    <tr>
                        <td>Add Track & Trace field to orders</td>
                        <td>
                            <input type="hidden" value="0" name="ds_functiontoggles[woo_trackntrace_field]">
                            <label class="ds-toggle"><input name="ds_functiontoggles[woo_trackntrace_field]" <?php checked((int) ($options['woo_trackntrace_field'] ?? 0), 1); ?> type="checkbox" value="1"><span class="ds-slider"></span></label>
                        </td>
                    </tr>
                    <tr>
                        <td>Filter by Purchase/Regular Price (min/max) within products overview</td>
                        <td>
                            <input type="hidden" value="0" name="ds_functiontoggles[woo_pricing_filters]">
                            <label class="ds-toggle"><input name="ds_functiontoggles[woo_pricing_filters]" <?php checked((int) ($options['woo_pricing_filters'] ?? 0), 1); ?> type="checkbox" value="1"><span class="ds-slider"></span></label>
                        </td>
                    </tr>
                    <tr>
                        <td>Add <b>_ds_isvariable</b> meta to variable products</td>
                        <td>
                            <input type="hidden" value="0" name="ds_functiontoggles[woo_variable_product_flag]">
                            <label class="ds-toggle"><input name="ds_functiontoggles[woo_variable_product_flag]" <?php checked((int) ($options['woo_variable_product_flag'] ?? 0), 1); ?> type="checkbox" value="1"><span class="ds-slider"></span></label>
                        </td>
                    </tr>
                    <tr>
                        <td>Show product attributes in order e-mails</td>
                        <td>
                            <input type="hidden" value="0" name="ds_functiontoggles[woo_email_product_attributes]">
                            <label class="ds-toggle"><input name="ds_functiontoggles[woo_email_product_attributes]" <?php checked((int) ($options['woo_email_product_attributes'] ?? 0), 1); ?> type="checkbox" value="1"><span class="ds-slider"></span></label>
                        </td>
                    </tr>
                    <tr>
                        <td>Configure automatic webshop closure date</td>
                        <td>
                            <input type="hidden" value="0" name="ds_functiontoggles[woo_webshop_closure]">
                            <label class="ds-toggle"><input name="ds_functiontoggles[woo_webshop_closure]" <?php checked((int) ($options['woo_webshop_closure'] ?? 0), 1); ?> type="checkbox" value="1"><span class="ds-slider"></span></label>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table class="ds-optionstable wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><h3>User management</h3></th>
                        <th scope="col" style="width: 120px;"><h3>Status</h3></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Redirect users to subsite login page if not signed in</td>
                        <td>
                            <input type="hidden" value="0" name="ds_functiontoggles[users_redirect_guests_to_login]">
                            <label class="ds-toggle"><input name="ds_functiontoggles[users_redirect_guests_to_login]" <?php checked((int) ($options['users_redirect_guests_to_login'] ?? 0), 1); ?> type="checkbox" value="1"><span class="ds-slider"></span></label>
                        </td>
                    </tr>
                    <tr>
                        <td>Limit login only to subsite(s) that user is associated with</td>
                        <td>
                            <input type="hidden" value="0" name="ds_functiontoggles[users_restrict_login_to_subsite]">
                            <label class="ds-toggle"><input name="ds_functiontoggles[users_restrict_login_to_subsite]" <?php checked((int) ($options['users_restrict_login_to_subsite'] ?? 0), 1); ?> type="checkbox" value="1"><span class="ds-slider"></span></label>
                        </td>
                    </tr>
                    <tr>
                        <td>Bulk user generation & import/export of users including order data</td>
                        <td>
                            <input type="hidden" value="0" name="ds_functiontoggles[users_bulkgen_exportimport]">
                            <label class="ds-toggle"><input name="ds_functiontoggles[users_bulkgen_exportimport]" <?php checked((int) ($options['users_bulkgen_exportimport'] ?? 0), 1); ?> type="checkbox" value="1"><span class="ds-slider"></span></label>
                        </td>
                    </tr>
                </tbody>
            </table>            


            <div style="display: flex; gap: 10px">
                <p class="submit"><button type="button" class="button" id="ds-disable-all">Alles uitschakelen</button></p>
				<p class="submit"><button type="button" class="button" id="ds-enable-all">Alles inschakelen</button></p>
            	<?php submit_button('Wijzigingen opslaan'); ?>
            </div>
        </form>


        <!-- Functionality for 'Enable/Disable all' buttons -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var enableBtn = document.getElementById('ds-enable-all');
                var disableBtn = document.getElementById('ds-disable-all');
                var form = enableBtn.closest('form');
                var checkboxes = form.querySelectorAll('input[type="checkbox"][name^="ds_functiontoggles"]');

                enableBtn.addEventListener('click', function() {
                    checkboxes.forEach(function(cb) {
                        if (cb.dataset.forceOn === '1' || cb.disabled) { return; }
                        cb.checked = true;
                    });
                });
                disableBtn.addEventListener('click', function() {
                    checkboxes.forEach(function(cb) {
                        if (cb.dataset.forceOn === '1' || cb.disabled) { return; }
                        cb.checked = false;
                    });
                });
            });
            </script>
    </div>
    <?php
}


// Load the page styles
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_dscore') {return;} // Only load on this admin page
    wp_enqueue_style(
        'admin-style',
        plugin_dir_url(__FILE__) . 'assets/admin-style.css',
        [],
        filemtime(plugin_dir_path(__FILE__) . 'assets/admin-style.css')
    );
});
