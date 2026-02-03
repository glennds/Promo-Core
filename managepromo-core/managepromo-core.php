<?php
/*
 * Plugin Name: ManagePromo Core
 * Plugin URI: https://www.digishock.com/webdevelopment/
 * Description: Diverse functionaliteiten op maat gemaakt voor Promotie.nl — Gebruik de ingebouwde instellingenpagina's om de functies te beheren.
 * Version: beta-1.3.0
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

// Load must-have functions
require_once plugin_dir_path(__FILE__) . 'mu-functions/force-woocommerce-hooks.php';
require_once plugin_dir_path(__FILE__) . 'mu-functions/choose-woocommerce-export-delimiter.php';
require_once plugin_dir_path(__FILE__) . 'mu-functions/cleanup-breakdance.php';

// Load optional functions
if (managepromo_is_enabled('site_logo'))                                    {require_once plugin_dir_path(__FILE__) . 'functions/add-sitelogo-setting.php';}
if (managepromo_is_enabled('woo_change_neworder_email'))                    {require_once plugin_dir_path(__FILE__) . 'functions/woo-change-admin-neworder-email.php';}
if (managepromo_is_enabled('woo_pricing_filters'))                          {require_once plugin_dir_path(__FILE__) . 'functions/woo-pricing-filters.php';}
if (managepromo_is_enabled('woo_webshop_closure'))                          {require_once plugin_dir_path(__FILE__) . 'functions/woo-webshop-closure.php';}
if (managepromo_is_enabled('woo_min_order_amount'))                         {require_once plugin_dir_path(__FILE__) . 'functions/woo-min-order-amount.php';}
if (managepromo_is_enabled('woo_post_calculation_prices'))                  {require_once plugin_dir_path(__FILE__) . 'functions/woo-post-calculation-prices.php';}
if (managepromo_is_enabled('woo_originalprice_columns'))                    {require_once plugin_dir_path(__FILE__) . 'functions/woo-originalprice-columns.php';}
if (managepromo_is_enabled('users_redirect_guests_to_login'))               {require_once plugin_dir_path(__FILE__) . 'functions/users-redirect-guests-to-login.php';}
if (managepromo_is_enabled('users_restrict_login_to_subsite'))              {require_once plugin_dir_path(__FILE__) . 'functions/users-restrict-login-to-subsite.php';}
if (managepromo_is_enabled('users_disable_email_field'))                    {require_once plugin_dir_path(__FILE__) . 'functions/users-disable-email-field.php';}
if (managepromo_is_enabled('users_disable_email_bulkgen_exportimport'))     {require_once plugin_dir_path(__FILE__) . 'functions/users-disable-email-bulkgen-exportimport.php';}
if (managepromo_is_enabled('users_mainsite_redirect'))                      {require_once plugin_dir_path(__FILE__) . 'functions/users-mainsite-redirect.php';}
if (managepromo_is_enabled('disable_gutenberg'))                            {require_once plugin_dir_path(__FILE__) . 'functions/disable-gutenberg.php';}
if (managepromo_is_enabled('woo_disable_downloads'))                        {require_once plugin_dir_path(__FILE__) . 'functions/woo-disable-downloads.php';}


// Load assets for functions
add_action('wp_enqueue_scripts', function() {
    if (
        function_exists('managepromo_is_enabled') &&
        managepromo_is_enabled('woo_account') &&
        is_account_page()) {
            wp_enqueue_style('managepromo-custom-style',
                plugin_dir_url(__FILE__) . 'assets/woo-account-style.css', [],
                filemtime(plugin_dir_path(__FILE__) . 'assets/woo-account-style.css')
            );
        }
}, 100);


//
// Create admin menu page
add_action('admin_init', function() {
    register_setting('managepromo_settings', 'ds_functiontoggles', [
    'type' => 'array',
    'sanitize_callback' => 'managepromo_sanitize_toggle_options'
    ]);
});

function managepromo_sanitize_toggle_options($input) {
    $defaults = [
        'site_logo'                                 => 0,
        'woo_change_neworder_email'                 => 0,
        'woo_pricing_filters'                       => 0,
        'woo_webshop_closure'                       => 0,
        'woo_min_order_amount'                      => 0,
        'woo_post_calculation_prices'               => 0,
        'woo_originalprice_columns'                 => 0,
        'users_redirect_guests_to_login'            => 0,
        'users_restrict_login_to_subsite'           => 0,
        'users_disable_email_field'                 => 0,
        'users_disable_email_bulkgen_exportimport'  => 0,
        'users_mainsite_redirect'                   => 0,
        'disable_gutenberg'                         => 0,
        'woo_disable_downloads'                     => 0
    ];

    if (!is_array($input)) {$input = [];}                           // Prevent null if function is toggled off    
    return array_merge($defaults, array_map('absint', $input));     // Fill empty value with 0
}

add_action('admin_menu', function () {

    $icon_svg = 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -95.5 512 512">
            <path fill="currentColor" d="m249.7 0.1q-0.1 70-0.1 140-48.8-0.6-97.6-1.2c-19.5-0.2-40.5 0.1-56.1 11.8-10.2 7.7-16.8 19.8-19.2 32.4-2.5 12.7-1.1 25.8 2.4 38.2 3.2 11.3 8.5 22.7 18.1 29.4 11.1 7.9 25.7 8.3 39.3 8.3q56.4 0 112.7 0.1 0 30.2-0.1 60.4c0 0.2-87.5 1.2-95.5 1.3-30.3 0.4-64.6 0.6-92.5-13.1-25.2-12.5-42.9-35.9-52.2-62.2-14.3-40.1-13-93.1 15.9-126.9 19.1-22.4 43.4-34.5 72.7-38.1 26.8-3.4 54-0.6 81.1-1.1q0.4-39.7 0.8-79.4z"/>
            <path fill="currentColor" d="m412.6 78.8c25.1 0 75.2 0.2 75.2 0.2h9.9v58.3c0 0-83.8-0.4-121.3-0.4-3.9 0-6.5 0.4-10.2 0.4-12 0-16.8 4.9-16.6 14.6 0.2 10 7.6 17.8 17.7 18 14.5 0.3 29 0.2 43.5 0.2 16.7-0.1 33.3 0 49.7 3.3 28 5.7 50.2 33.7 51.3 62.5 1 25-3.6 47.2-22.3 65.5-13.4 13.1-29.3 19.3-47.4 19.4-49.6 0.4-160.2 0.3-160.2 0.3v-63.3c0 0 96.2 0.8 139.1 0.8 7.9 0 15.3-0.6 17.2-10.7 2.4-12.5-1.3-17.4-12.3-20.9-6.8-2.1-13.7-2.1-20.6-2.1-21.2 0-42.4 0-63.5-0.1-32.5-0.3-64.7-35.4-63.9-68 0.5-20.1 5.5-37.9 16.8-54.5 10.4-15.4 24.6-22.6 42.7-23.3 4.2-0.2 8.4-0.2 12.7-0.2q31.2 0 62.5 0z"/>
            <path fill="currentColor" d="m248.5 201.2c-0.3 20.1-15.8 34.7-36.4 34.3-19.4-0.3-35.2-16.9-34.9-36.6 0.2-19.4 16.8-35.1 36.6-34.7 21 0.5 35.1 15.4 34.7 37z"/>
        </svg>'
    );

    add_menu_page(                  // Add top-level menu-item
        'ManagePromo',              // Page title
        'Digishock',                // Menu title
        'manage_options',           // Capability (admins)
        'managepromo',              // Menu slug
        'managepromo_features',     // Callback to first submenu-item on clicking toplevel menu-item
        $icon_svg,                  // Icon
        9999                        // Menu position
    );

    add_submenu_page(               // First submenu-item, enable/disable features
        'managepromo',
        'Functies',
        'Functies',
        'manage_options',
        'managepromo',
        'managepromo_features',
        0
    );

});



//
// Generate the page content
function managepromo_features() {
    
    if (!current_user_can('manage_options')) {
        wp_die(__('Je hebt geen toegang tot deze pagina.'));
    }

    $options = get_option('ds_functiontoggles', [
        'site_logo'                                 => 0,
        'woo_change_neworder_email'                 => 0,
        'woo_pricing_filters'                       => 0,
        'woo_webshop_closure'                       => 0,
        'woo_min_order_amount'                      => 0,
        'woo_post_calculation_prices'               => 0,
        'woo_originalprice_columns'                 => 0,
        'users_redirect_guests_to_login'            => 0,
        'users_restrict_login_to_subsite'           => 0,
        'users_disable_email_field'                 => 0,
        'users_disable_email_bulkgen_exportimport'  => 0,
        'users_mainsite_redirect'                   => 0,
        'disable_gutenberg'                         => 0,
        'woo_disable_downloads'                     => 0
    ]);
    ?>
    <div class="wrap">
        <h1>ManagePromo Functies</h1>

        <form method="post" action="options.php">
            <?php settings_fields('managepromo_settings'); ?>

            <table class="ds-optionstable wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><h3>Functionaliteiten - Algemeen</h3></th>
                        <th scope="col" style="width: 120px;"><h3>Status</h3></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="font-weight: 600">Site logo toevoegen aan instellingen</td>
                        <td>
                            <input type="hidden" name="ds_functiontoggles[site_logo]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="ds_functiontoggles[site_logo]" value="1" <?php checked((int) ($options['site_logo'] ?? 0), 1); ?>>
                                <span class="ds-slider"></span>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table class="ds-optionstable wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><h3>Functionaliteiten - WooCommerce</h3></th>
                        <th scope="col" style="width: 120px;"><h3>Status</h3></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="font-weight: 600">VERBETEREN - Wijzig het template voor de admin nieuwe order e-mail</td>
                        <td>
                            <input type="hidden" name="ds_functiontoggles[woo_change_neworder_email]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="ds_functiontoggles[woo_change_neworder_email]" value="1" <?php checked((int) ($options['woo_change_neworder_email'] ?? 0), 1); ?>>
                                <span class="ds-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">Productoverzicht weergavefilters op basis van Inkoopprijs & Reguliere Prijs</td>
                        <td>
                            <input type="hidden" name="ds_functiontoggles[woo_pricing_filters]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="ds_functiontoggles[woo_pricing_filters]" value="1" <?php checked((int) ($options['woo_pricing_filters'] ?? 0), 1); ?>>
                                <span class="ds-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">Automatische sluitingsdatum instellen</td>
                        <td>
                            <input type="hidden" name="ds_functiontoggles[woo_webshop_closure]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="ds_functiontoggles[woo_webshop_closure]" value="1" <?php checked((int) ($options['woo_webshop_closure'] ?? 0), 1); ?>>
                                <span class="ds-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">Minimale afname per order instellen op producten</td>
                        <td>
                            <input type="hidden" name="ds_functiontoggles[woo_min_order_amount]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="ds_functiontoggles[woo_min_order_amount]" value="1" <?php checked((int) ($options['woo_min_order_amount'] ?? 0), 1); ?>>
                                <span class="ds-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">Prijs 'op nacalculatie' mogelijk maken</td>
                        <td>
                            <input type="hidden" name="ds_functiontoggles[woo_post_calculation_prices]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="ds_functiontoggles[woo_post_calculation_prices]" value="1" <?php checked((int) ($options['woo_post_calculation_prices'] ?? 0), 1); ?>>
                                <span class="ds-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">Voeg een 'Originele Prijs' kolom toe aan cart, checkout, thankyou & email</td>
                        <td>
                            <input type="hidden" name="ds_functiontoggles[woo_originalprice_columns]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="ds_functiontoggles[woo_originalprice_columns]" value="1" <?php checked((int) ($options['woo_originalprice_columns'] ?? 0), 1); ?>>
                                <span class="ds-slider"></span>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table class="ds-optionstable wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><h3>Functionaliteiten - Gebruikersbeheer</h3></th>
                        <th scope="col" style="width: 120px;"><h3>Status</h3></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="font-weight: 600">Uitgelogde website bezoekers omleiden naar inlogpagina van subsite</td>
                        <td>
                            <input type="hidden" name="ds_functiontoggles[users_redirect_guests_to_login]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="ds_functiontoggles[users_redirect_guests_to_login]" value="1" <?php checked((int) ($options['users_redirect_guests_to_login'] ?? 0), 1); ?>>
                                <span class="ds-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">Beperk inloggen voor klant tot alleen gekoppelde subsites</td>
                        <td>
                            <input type="hidden" name="ds_functiontoggles[users_restrict_login_to_subsite]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="ds_functiontoggles[users_restrict_login_to_subsite]" value="1" <?php checked((int) ($options['users_restrict_login_to_subsite'] ?? 0), 1); ?>>
                                <span class="ds-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">Registratie e-mails en e-mailveld bij aanmaken van nieuwe gebruiker uitschakelen</td>
                        <td>
                            <input type="hidden" name="ds_functiontoggles[users_disable_email_field]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="ds_functiontoggles[users_disable_email_field]" value="1" <?php checked((int) ($options['users_disable_email_field'] ?? 0), 1); ?>>
                                <span class="ds-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">Accountbeheer wijzigingen, bulk accountgeneratie en import/export van gebruikers/orders/klantdata.</td>
                        <td>
                            <input type="hidden" name="ds_functiontoggles[users_disable_email_bulkgen_exportimport]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="ds_functiontoggles[users_disable_email_bulkgen_exportimport]" value="1" <?php checked((int) ($options['users_disable_email_bulkgen_exportimport'] ?? 0), 1); ?>>
                                <span class="ds-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">VERBETEREN - Redirect gebruikers naar /inloggen/ als ze ingelogd zijn, klant zijn en homepage bezoeken (ALLEEN VOOR GEGEVENSBRON)</td>
                        <td>
                            <input type="hidden" name="ds_functiontoggles[users_mainsite_redirect]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="ds_functiontoggles[users_mainsite_redirect]" value="1" <?php checked((int) ($options['users_mainsite_redirect'] ?? 0), 1); ?>>
                                <span class="ds-slider"></span>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table class="ds-optionstable wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><h3>Functionaliteiten - Optimalisatie</h3></th>
                        <th scope="col" style="width: 120px;"><h3>Status</h3></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="font-weight: 600">WebsiteNazorg.nl - Schakel Gutenberg uit</td>
                        <td>
                            <input type="hidden" name="ds_functiontoggles[disable_gutenberg]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="ds_functiontoggles[disable_gutenberg]" value="1" <?php checked((int) ($options['disable_gutenberg'] ?? 0), 1); ?>>
                                <span class="ds-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">Downloadbare producten uitschakelen</td>
                        <td>
                            <input type="hidden" name="ds_functiontoggles[woo_disable_downloads]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="ds_functiontoggles[woo_disable_downloads]" value="1" <?php checked((int) ($options['woo_disable_downloads'] ?? 0), 1); ?>>
                                <span class="ds-slider"></span>
                            </label>
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

                enableBtn.addEventListener('click', function() {checkboxes.forEach(function(cb) { cb.checked = true; });});
                disableBtn.addEventListener('click', function() {checkboxes.forEach(function(cb) { cb.checked = false; });});
            });
            </script>
    </div>
    <?php
}



// Load the page styles
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_managepromo') {return;} // Only load on this admin page
    wp_enqueue_style(
        'admin-style',
        plugin_dir_url(__FILE__) . 'assets/admin-style.css',
        [],
        filemtime(plugin_dir_path(__FILE__) . 'assets/admin-style.css')
    );
});

// Allow (de)activating functions Helper
function managepromo_is_enabled($key) {
    $options = get_option('ds_functiontoggles', []);
    return isset($options[$key]) && $options[$key] === 1;
}
