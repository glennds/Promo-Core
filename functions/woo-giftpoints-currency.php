<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}
if (!function_exists('keuzeconcept_is_enabled') || !keuzeconcept_is_enabled('woo_giftpoints_currency')) {return;}

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////


// 1. Voeg "Giftpoints" toe als aangepaste valuta
add_filter('woocommerce_currencies', function($currencies) {
    $currencies['GIFT'] = __('Giftpoints', 'woocommerce');
    return $currencies;
});

// 2. Voeg het &#127873;-icoon toe als valuta-symbool voor Giftpoints
add_filter('woocommerce_currency_symbol', function($currency_symbol, $currency) {
    if ('GIFT' === $currency) {
        return '&#127873;';
    }
    return $currency_symbol;
}, 10, 2);

// 3. Forceer WooCommerce om altijd europrijzen te gebruiken op de achtergrond
add_filter('woocommerce_currency', function($currency) {
    if (function_exists('keuzeconcept_is_enabled') && keuzeconcept_is_enabled('woo_giftpoints_currency')) {
        return 'GIFT';
    }
    return $currency;
});
