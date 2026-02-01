<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}
if (!function_exists('keuzeconcept_is_enabled') || !keuzeconcept_is_enabled('woo_accountpage_optimization')) {return;}

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////



// Bypass "Accountbeheer is uitgeschakeld." op adres-endpoint
add_filter('wp_die_handler', function ($handler) {
    return function ($message, $title = '', $args = []) use ($handler) {
        if (function_exists('is_account_page') && is_account_page()) {
            if (function_exists('is_wc_endpoint_url') && (is_wc_endpoint_url('edit-address') || is_wc_endpoint_url('adres-wijzigen'))) {
                return; // negeer die; laat pagina doorrenderen
            }
        }
        return call_user_func($handler, $message, $title, $args);
    };
}, 0);



// Register endpoint and add the query var for the wishlist
add_action('init', function () {add_rewrite_endpoint('verlanglijst', EP_ROOT | EP_PAGES);});
add_filter('woocommerce_get_query_vars', function ($vars) {$vars['verlanglijst'] = 'verlanglijst';  return $vars;});

// Add wishlist tab in WooCommerce Account page
add_filter('woocommerce_account_menu_items', function ($items) {
    $new = [];
    foreach ($items as $key => $label) {
        if ($key === 'woo-wallet') {
            $new['verlanglijst'] = __('Verlanglijst', 'your-textdomain');
        }
        $new[$key] = $label;
    }
    return $new;
});

// Add the wishlist plugin shortcode as content
add_action('woocommerce_account_verlanglijst_endpoint', function () {
    if (shortcode_exists('yith_wcwl_wishlist')) {
        echo do_shortcode('[yith_wcwl_wishlist]');
    } else {echo '<p>' . esc_html__('Geen verlanglijst beschikbaar.', 'your-textdomain') . '</p>';}
});

// Flush rewrites on activation
if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, function () {
        add_rewrite_endpoint('verlanglijst', EP_ROOT | EP_PAGES);
        flush_rewrite_rules();
    });
}


// Esnure WooCommerce recognizes the custom slugs
add_filter('woocommerce_get_query_vars', function ($vars) {
    $vars['orders']          = 'orders';
    $vars['edit-address']    = 'adres-wijzigen';
    $vars['customer-logout'] = 'logout';
    return $vars;
}, 0);

// Find TerraWallet key from slug
function kc_wallet_key_from_slug() {
    if (!function_exists('WC') || !WC()->query) return null;
    $qv = WC()->query->get_query_vars();           // ['edit-address'=>'adres-wijzigen', ...]
    $key = array_search('tegoed', $qv, true);      // verwacht bv. 'woo-wallet'
    if ($key) return $key;
    foreach (array_keys($qv) as $k) if (strpos($k, 'wallet') !== false) return $k;
    return null;
}

// 2) Cleanup, reorder and rename WooCommerce account menu items
add_filter('woocommerce_account_menu_items', function ($items) {
    $wallet = kc_wallet_key_from_slug();

    // Ensure items exist
    if ($wallet && !isset($items[$wallet])) $items[$wallet] = __('Mijn tegoed', 'your-textdomain');
    $items['verlanglijst']    = $items['verlanglijst'] ?? __('Verlanglijst', 'your-textdomain');
    $items['orders']          = $items['orders'] ?? __('Orders', 'your-textdomain');
    $items['edit-address']    = __('Gegevens wijzigen', 'your-textdomain');
    $items['customer-logout'] = $items['customer-logout'] ?? __('Uitloggen', 'woocommerce');

    // Change order
    $new = [];
    if ($wallet)    $new[$wallet]                   = __('Mijn tegoed', 'your-textdomain');  // 1
                    $new['verlanglijst']            = __('Verlanglijst', 'your-textdomain');                      // 2
                    $new['orders']                  = __('Orders', 'your-textdomain');                            // 3
                    $new['edit-address']            = __('Gegevens wijzigen', 'your-textdomain');                 // 4
                    $new['customer-logout']         = $items['customer-logout'];                                  // 5
    return $new;
}, 9999);


// Redirect dashboard to Wallet tab
add_action( 'template_redirect', 'redirect_my_account_dashboard_to_wallet' );
function redirect_my_account_dashboard_to_wallet() {
    if ( is_account_page() && empty( WC()->query->get_current_endpoint() ) ) {
        wp_safe_redirect( wc_get_account_endpoint_url( 'woo-wallet' ) );
        exit;
    }
}

add_action('wp_enqueue_scripts', function () {
    if (function_exists('is_account_page') && is_account_page()) {
        wp_enqueue_style(
            'kc-woo-account-style',
            plugin_dir_url(__DIR__) . 'assets/woo-account-style.css'
        );
    }
});
