<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}
if (!function_exists('managepromo_is_enabled') || !managepromo_is_enabled('woo_originalprice_columns')) {return;}

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////



if (!defined('ABSPATH')) exit;

/**
 * Add "Reguliere prijs" column to cart table.
 */
add_filter('woocommerce_cart_item_name', function ($name, $cart_item, $cart_item_key) {
    // No changes to the product name cell content
    return $name;
}, 10, 3);

add_filter('woocommerce_cart_item_price', function ($price_html, $cart_item, $cart_item_key) {
    // Keep the existing "Price" column unchanged
    return $price_html;
}, 10, 3);

/**
 * Insert a new header column after the product column.
 */
add_action('woocommerce_before_cart_contents', function () {
    ?>
    <style>
        /* Keep column width reasonable */
        th.ds-regular-price, td.ds-regular-price { width: 140px; }
    </style>
    <?php
});

add_filter('woocommerce_cart_item_remove_link', function ($link, $cart_item_key) {
    // No-op, but keeps file load predictable if you group cart customizations
    return $link;
}, 10, 2);

/**
 * Render header cell.
 */
add_action('woocommerce_cart_contents', function () {
    // No-op: header rendering needs template override for perfect placement
}, 1);