<?php
/*
 * Plugin Name: Custom Functions for Keuzeconcept.com
 * Plugin URI: https://www.digishock.com/webdevelopment/
 * Description: Diverse functionaliteiten op maat gemaakt voor Keuzeconcept.com. Gebruik de ingebouwde instellingenpagina om de functies te beheren.
 * Version: 1.9.3
 * Requires at least: 6.8.2
 * Requires PHP: 8.2
 * Author: Digishock
 * Author URI: https://www.digishock.com/
 * License: GPL2+
 * Network: true
 * Update URI: #
 */

// Security check
defined('ABSPATH') || exit;

// Load must-have functions
require_once plugin_dir_path(__FILE__) . 'mu-functions/force-woocommerce-hooks.php';

// Load optional functions
if (keuzeconcept_is_enabled('disable_gutenberg'))                           {require_once plugin_dir_path(__FILE__) . 'functions/disable-gutenberg.php';}
if (keuzeconcept_is_enabled('site_logo'))                                   {require_once plugin_dir_path(__FILE__) . 'functions/add-sitelogo-setting.php';}
if (keuzeconcept_is_enabled('users_disable_email_bulkgen_exportimport'))    {require_once plugin_dir_path(__FILE__) . 'functions/users-disable-email-bulkgen-exportimport.php';}
if (keuzeconcept_is_enabled('users_restrict_login_to_subsite'))             {require_once plugin_dir_path(__FILE__) . 'functions/users-restrict-login-to-subsite.php';}
if (keuzeconcept_is_enabled('users_redirect_guests_to_login'))              {require_once plugin_dir_path(__FILE__) . 'functions/users-redirect-guests-to-login.php';}
if (keuzeconcept_is_enabled('users_profile_update_popup'))                  {require_once plugin_dir_path(__FILE__) . 'functions/users-profile-update-popup.php';}
if (keuzeconcept_is_enabled('users_mainsite_redirect'))                     {require_once plugin_dir_path(__FILE__) . 'functions/users-mainsite-redirect.php';}
if (keuzeconcept_is_enabled('woo_disable_downloads'))                       {require_once plugin_dir_path(__FILE__) . 'functions/woo-disable-downloadable-products.php';}
if (keuzeconcept_is_enabled('woo_giftpoints_currency'))                     {require_once plugin_dir_path(__FILE__) . 'functions/woo-giftpoints-currency.php';}
if (keuzeconcept_is_enabled('woo_accountpage_optimization'))                {require_once plugin_dir_path(__FILE__) . 'functions/woo-accountpage-optimization.php';}
if (keuzeconcept_is_enabled('woo_change_neworder_email'))                   {require_once plugin_dir_path(__FILE__) . 'functions/woo-change-admin-neworder-email.php';}
if (keuzeconcept_is_enabled('woo_pricing_filters'))                         {require_once plugin_dir_path(__FILE__) . 'functions/woo-pricing-filters.php';}
if (keuzeconcept_is_enabled('woo_limit_products_per_order'))                {require_once plugin_dir_path(__FILE__) . 'functions/woo-limit-products-per-order.php';}
if (keuzeconcept_is_enabled('woo_webshop_closure'))                         {require_once plugin_dir_path(__FILE__) . 'functions/woo-webshop-closure.php';}


// Load assets for functions
add_action('wp_enqueue_scripts', function() {
    if (
        function_exists('keuzeconcept_is_enabled') &&
        keuzeconcept_is_enabled('woo_account') &&
        is_account_page()) {
            wp_enqueue_style('keuzeconcept-custom-style',
                plugin_dir_url(__FILE__) . 'assets/woo-account-style.css', [],
                filemtime(plugin_dir_path(__FILE__) . 'assets/woo-account-style.css')
            );
        }
}, 100);


//
// Create admin menu page
add_action('admin_init', function() {
    register_setting('keuzeconcept_settings', 'keuzeconcept_function_toggles', [
    'type' => 'array',
    'sanitize_callback' => 'keuzeconcept_sanitize_toggle_options'
    ]);
});

function keuzeconcept_sanitize_toggle_options($input) {
    $defaults = [
        'disable_gutenberg' => 0,
        'site_logo' => 0,
        'users_disable_email_bulkgen_exportimport' => 0,
        'users_restrict_login_to_subsite' => 0,
        'users_redirect_guests_to_login' => 0,
        'users_profile_update_popup' => 0,
        'users_mainsite_redirect' => 0,
        'woo_disable_downloads' => 0,
        'woo_giftpoints_currency' => 0,
        'woo_accountpage_optimization' => 0,
        'woo_change_neworder_email' => 0,
        'woo_pricing_filters' => 0,
        'woo_limit_products_per_order' => 0,
        'woo_webshop_closure' => 0
    ];

    if (!is_array($input)) {$input = [];}   // Prevent null if function is toggled off    
    return array_merge($defaults, array_map('absint', $input)); // Fill empty value with 0
}

add_action('admin_menu', function() {
    add_submenu_page(
        'options-general.php',
        'Keuzeconcept Functies',
        'Keuzeconcept Functies',
        'manage_options',
        'keuzeconcept-functions',
        'keuzeconcept_functions_page'
    );
});

// Generatie the page content
function keuzeconcept_functions_page() {
    $options = get_option('keuzeconcept_function_toggles', [
        'disable_gutenberg' => 0,
        'site_logo' => 0,
        'users_disable_email_bulkgen_exportimport' => 0,
        'users_restrict_login_to_subsite' => 0,
        'users_redirect_guests_to_login' => 0,
        'users_profile_update_popup' => 0,
        'users_mainsite_redirect' => 0,
        'woo_disable_downloads' => 0,
        'woo_giftpoints_currency' => 0,
        'woo_accountpage_optimization' => 0,
        'woo_change_neworder_email' => 0,
        'woo_pricing_filters' => 0,
        'woo_limit_products_per_order' => 0,
        'woo_webshop_closure' => 0
    ]);
    ?>
    <div class="wrap">
        <h1>Keuzeconcept Plugin Instellingen</h1>

        <form method="post" action="options.php">
            <?php
                settings_fields('keuzeconcept_settings');
                $options = get_option('keuzeconcept_function_toggles', [
                    'disable_gutenberg' => 0,
                    'site_logo' => 0,
                    'users_disable_email_bulkgen_exportimport' => 0,
                    'users_restrict_login_to_subsite' => 0,
                    'users_redirect_guests_to_login' => 0,
                    'users_profile_update_popup' => 0,
                    'users_mainsite_redirect' => 0,
                    'woo_disable_downloads' => 0,
                    'woo_giftpoints_currency' => 0,
                    'woo_accountpage_optimization' => 0,
                    'woo_change_neworder_email' => 0,
                    'woo_pricing_filters' => 0,
                    'woo_limit_products_per_order' => 0,
                    'woo_webshop_closure' => 0
                ]);
            ?>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col">Functie</th>
                        <th scope="col" style="width: 120px;">Actief</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="font-weight: 600">Algemeen - WebsiteNazorg.nl - Schakel Gutenberg uit</td>
                        <td>
                            <input type="hidden" name="keuzeconcept_function_toggles[disable_gutenberg]" value="0">
                            <label class="kc-toggle">
                                <input type="checkbox" name="keuzeconcept_function_toggles[disable_gutenberg]" value="1" <?php checked((int) ($options['disable_gutenberg'] ?? 0), 1); ?>>
                                <span class="kc-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">Algemeen - Site logo toevoegen aan instellingen</td>
                        <td>
                            <input type="hidden" name="keuzeconcept_function_toggles[site_logo]" value="0">
                            <label class="kc-toggle">
                                <input type="checkbox" name="keuzeconcept_function_toggles[site_logo]" value="1" <?php checked((int) ($options['site_logo'] ?? 0), 1); ?>>
                                <span class="kc-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">Users - Accountbeheer wijzigingen, bulk accountgeneratie en import/export van gebruikers/orders/klantdata.</td>
                        <td>
                            <input type="hidden" name="keuzeconcept_function_toggles[users_disable_email_bulkgen_exportimport]" value="0">
                            <label class="kc-toggle">
                                <input type="checkbox" name="keuzeconcept_function_toggles[users_disable_email_bulkgen_exportimport]" value="1" <?php checked((int) ($options['users_disable_email_bulkgen_exportimport'] ?? 0), 1); ?>>
                                <span class="kc-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">Users - Beperk gebruikers zodat ze alleen kunnen inloggen op de subsites waaraan ze gekoppeld zijn</td>
                        <td>
                            <input type="hidden" name="keuzeconcept_function_toggles[users_restrict_login_to_subsite]" value="0">
                            <label class="kc-toggle">
                                <input type="checkbox" name="keuzeconcept_function_toggles[users_restrict_login_to_subsite]" value="1" <?php checked((int) ($options['users_restrict_login_to_subsite'] ?? 0), 1); ?>>
                                <span class="kc-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">Users - Uitgelogde website bezoekers omleiden naar inlogpagina van subsite</td>
                        <td>
                            <input type="hidden" name="keuzeconcept_function_toggles[users_redirect_guests_to_login]" value="0">
                            <label class="kc-toggle">
                                <input type="checkbox" name="keuzeconcept_function_toggles[users_redirect_guests_to_login]" value="1" <?php checked((int) ($options['users_redirect_guests_to_login'] ?? 0), 1); ?>>
                                <span class="kc-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">Users - Toon een popup aan nieuwe gebruikers om contactgegevens in te vullen</td>
                        <td>
                            <input type="hidden" name="keuzeconcept_function_toggles[users_profile_update_popup]" value="0">
                            <label class="kc-toggle">
                                <input type="checkbox" name="keuzeconcept_function_toggles[users_profile_update_popup]" value="1" <?php checked((int) ($options['users_profile_update_popup'] ?? 0), 1); ?>>
                                <span class="kc-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">Users - Redirect gebruikers naar /inloggen/ als ze ingelogd zijn, klant zijn en homepage bezoeken (ALLEEN VOOR GEGEVENSBRON)</td>
                        <td>
                            <input type="hidden" name="keuzeconcept_function_toggles[users_mainsite_redirect]" value="0">
                            <label class="kc-toggle">
                                <input type="checkbox" name="keuzeconcept_function_toggles[users_mainsite_redirect]" value="1" <?php checked((int) ($options['users_mainsite_redirect'] ?? 0), 1); ?>>
                                <span class="kc-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">WooCommerce - Downloadbare producten uitschakelen</td>
                        <td>
                            <input type="hidden" name="keuzeconcept_function_toggles[woo_disable_downloads]" value="0">
                            <label class="kc-toggle">
                                <input type="checkbox" name="keuzeconcept_function_toggles[woo_disable_downloads]" value="1" <?php checked((int) ($options['woo_disable_downloads'] ?? 0), 1); ?>>
                                <span class="kc-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">WooCommerce - Verander WooCommerce valuta naar cadeaupunten</td>
                        <td>
                            <input type="hidden" name="keuzeconcept_function_toggles[woo_giftpoints_currency]" value="0">
                            <label class="kc-toggle">
                                <input type="checkbox" name="keuzeconcept_function_toggles[woo_giftpoints_currency]" value="1" <?php checked((int) ($options['woo_giftpoints_currency'] ?? 0), 1); ?>>
                                <span class="kc-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">WooCommerce - WooCommerce 'My Account' Optimalisatie</td>
                        <td>
                            <input type="hidden" name="keuzeconcept_function_toggles[woo_accountpage_optimization]" value="0">
                            <label class="kc-toggle">
                                <input type="checkbox" name="keuzeconcept_function_toggles[woo_accountpage_optimization]" value="1" <?php checked((int) ($options['woo_accountpage_optimization'] ?? 0), 1); ?>>
                                <span class="kc-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">WooCommerce - Wijzig het template voor de admin nieuwe order e-mail</td>
                        <td>
                            <input type="hidden" name="keuzeconcept_function_toggles[woo_change_neworder_email]" value="0">
                            <label class="kc-toggle">
                                <input type="checkbox" name="keuzeconcept_function_toggles[woo_change_neworder_email]" value="1" <?php checked((int) ($options['woo_change_neworder_email'] ?? 0), 1); ?>>
                                <span class="kc-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">WooCommerce - Extra weergavefilters op basis van Inkoopprijs & Reguliere Prijs in het backoffice productoverzicht</td>
                        <td>
                            <input type="hidden" name="keuzeconcept_function_toggles[woo_pricing_filters]" value="0">
                            <label class="kc-toggle">
                                <input type="checkbox" name="keuzeconcept_function_toggles[woo_pricing_filters]" value="1" <?php checked((int) ($options['woo_pricing_filters'] ?? 0), 1); ?>>
                                <span class="kc-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">WooCommerce - Beperk het aantal producten tot 1 artikel per order</td>
                        <td>
                            <input type="hidden" name="keuzeconcept_function_toggles[woo_limit_products_per_order]" value="0">
                            <label class="kc-toggle">
                                <input type="checkbox" name="keuzeconcept_function_toggles[woo_limit_products_per_order]" value="1" <?php checked((int) ($options['woo_limit_products_per_order'] ?? 0), 1); ?>>
                                <span class="kc-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">WooCommerce - Automatische sluitingsdatum van de webshop instellen</td>
                        <td>
                            <input type="hidden" name="keuzeconcept_function_toggles[woo_webshop_closure]" value="0">
                            <label class="kc-toggle">
                                <input type="checkbox" name="keuzeconcept_function_toggles[woo_webshop_closure]" value="1" <?php checked((int) ($options['woo_webshop_closure'] ?? 0), 1); ?>>
                                <span class="kc-slider"></span>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div style="display: flex; gap: 10px">
                <p class="submit"><button type="button" class="button" id="kc-disable-all">Alles uitschakelen</button></p>
				<p class="submit"><button type="button" class="button" id="kc-enable-all">Alles inschakelen</button></p>
            	<?php submit_button('Wijzigingen opslaan'); ?>
            </div>
        </form>
        <!-- Functionality for 'Enable/Disable all' buttons -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var enableBtn = document.getElementById('kc-enable-all');
                var disableBtn = document.getElementById('kc-disable-all');
                var form = enableBtn.closest('form');
                var checkboxes = form.querySelectorAll('input[type="checkbox"][name^="keuzeconcept_function_toggles"]');

                enableBtn.addEventListener('click', function() {
                    checkboxes.forEach(function(cb) { cb.checked = true; });
                });

                disableBtn.addEventListener('click', function() {
                    checkboxes.forEach(function(cb) { cb.checked = false; });
                });
            });
            </script>
    </div>
    <?php
}

// Load the page styles
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'settings_page_keuzeconcept-functions') {
        wp_enqueue_style('keuzeconcept-admin-style',
            plugin_dir_url(__FILE__) . 'assets/admin-style.css', [],
            filemtime(plugin_dir_path(__FILE__) . 'assets/admin-style.css')
        );}
});

// Allow (de)activating functions Helper
function keuzeconcept_is_enabled($key) {
    $options = get_option('keuzeconcept_function_toggles', []);
    return isset($options[$key]) && $options[$key] === 1;
}
