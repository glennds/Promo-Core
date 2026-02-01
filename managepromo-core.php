<?php
/*
 * Plugin Name: ManagePromo Core
 * Plugin URI: https://www.digishock.com/webdevelopment/
 * Description: Diverse functionaliteiten op maat gemaakt voor Promotie.nl — Gebruik de ingebouwde instellingenpagina's om de functies te beheren.
 * Version: 1.0.1
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

// Load optional functions
if (managepromo_is_enabled('disable_gutenberg'))                           {require_once plugin_dir_path(__FILE__) . 'functions/disable-gutenberg.php';}
if (managepromo_is_enabled('site_logo'))                                   {require_once plugin_dir_path(__FILE__) . 'functions/add-sitelogo-setting.php';}
if (managepromo_is_enabled('users_disable_email_bulkgen_exportimport'))    {require_once plugin_dir_path(__FILE__) . 'functions/users-disable-email-bulkgen-exportimport.php';}
if (managepromo_is_enabled('users_restrict_login_to_subsite'))             {require_once plugin_dir_path(__FILE__) . 'functions/users-restrict-login-to-subsite.php';}
if (managepromo_is_enabled('users_redirect_guests_to_login'))              {require_once plugin_dir_path(__FILE__) . 'functions/users-redirect-guests-to-login.php';}
if (managepromo_is_enabled('users_profile_update_popup'))                  {require_once plugin_dir_path(__FILE__) . 'functions/users-profile-update-popup.php';}
if (managepromo_is_enabled('users_mainsite_redirect'))                     {require_once plugin_dir_path(__FILE__) . 'functions/users-mainsite-redirect.php';}
if (managepromo_is_enabled('woo_disable_downloads'))                       {require_once plugin_dir_path(__FILE__) . 'functions/woo-disable-downloads.php';}
if (managepromo_is_enabled('woo_giftpoints_currency'))                     {require_once plugin_dir_path(__FILE__) . 'functions/woo-giftpoints-currency.php';}
if (managepromo_is_enabled('woo_accountpage_optimization'))                {require_once plugin_dir_path(__FILE__) . 'functions/woo-accountpage-optimization.php';}
if (managepromo_is_enabled('woo_change_neworder_email'))                   {require_once plugin_dir_path(__FILE__) . 'functions/woo-change-admin-neworder-email.php';}
if (managepromo_is_enabled('woo_pricing_filters'))                         {require_once plugin_dir_path(__FILE__) . 'functions/woo-pricing-filters.php';}
if (managepromo_is_enabled('woo_limit_products_per_order'))                {require_once plugin_dir_path(__FILE__) . 'functions/woo-limit-products-per-order.php';}
if (managepromo_is_enabled('woo_webshop_closure'))                         {require_once plugin_dir_path(__FILE__) . 'functions/woo-webshop-closure.php';}


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

    if (!is_array($input)) {$input = [];}                           // Prevent null if function is toggled off    
    return array_merge($defaults, array_map('absint', $input));     // Fill empty value with 0
}

add_action('admin_menu', function () {
    $menu_slug = 'managepromo';
    $capability = 'manage_options';

    add_menu_page(                      // Add top-level menu-item
        'ManagePromo',                  // Page title
        'ManagePromo',                  // Menu title
        $capability,                    // Capability (admins)
        $menu_slug,                     // Menu slug
        'managepromo_features',         // Callback to first submenu-item on clicking toplevel menu-item
        'dashicons-admin-generic',      // Icon
        9999                            // Menu position
    );

    add_submenu_page(                   // First submenu-item, enable/disable features
        $menu_slug,
        'Functies',
        'Functies',
        $capability,
        $menu_slug,
        'managepromo_features'
    );
});



//
// Generate the page content
function managepromo_features() {
    
    if (!current_user_can('manage_options')) {
        wp_die(__('Je hebt geen toegang tot deze pagina.'));
    }

    $options = get_option('ds_functiontoggles', [
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
                        <td style="font-weight: 600">VERBETEREN - Automatische sluitingsdatum instellen</td>
                        <td>
                            <input type="hidden" name="ds_functiontoggles[woo_webshop_closure]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="ds_functiontoggles[woo_webshop_closure]" value="1" <?php checked((int) ($options['woo_webshop_closure'] ?? 0), 1); ?>>
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
                            <input type="hidden" name="managepromo_function_toggles[users_redirect_guests_to_login]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="managepromo_function_toggles[users_redirect_guests_to_login]" value="1" <?php checked((int) ($options['users_redirect_guests_to_login'] ?? 0), 1); ?>>
                                <span class="ds-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">Beperk inloggen voor klant tot alleen gekoppelde subsites</td>
                        <td>
                            <input type="hidden" name="managepromo_function_toggles[users_restrict_login_to_subsite]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="managepromo_function_toggles[users_restrict_login_to_subsite]" value="1" <?php checked((int) ($options['users_restrict_login_to_subsite'] ?? 0), 1); ?>>
                                <span class="ds-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">Accountbeheer wijzigingen, bulk accountgeneratie en import/export van gebruikers/orders/klantdata.</td>
                        <td>
                            <input type="hidden" name="managepromo_function_toggles[users_disable_email_bulkgen_exportimport]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="managepromo_function_toggles[users_disable_email_bulkgen_exportimport]" value="1" <?php checked((int) ($options['users_disable_email_bulkgen_exportimport'] ?? 0), 1); ?>>
                                <span class="ds-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600">VERBETEREN - Redirect gebruikers naar /inloggen/ als ze ingelogd zijn, klant zijn en homepage bezoeken (ALLEEN VOOR GEGEVENSBRON)</td>
                        <td>
                            <input type="hidden" name="managepromo_function_toggles[users_mainsite_redirect]" value="0">
                            <label class="ds-toggle">
                                <input type="checkbox" name="managepromo_function_toggles[users_mainsite_redirect]" value="1" <?php checked((int) ($options['users_mainsite_redirect'] ?? 0), 1); ?>>
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
