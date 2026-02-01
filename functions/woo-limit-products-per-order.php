<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}
if (!function_exists('managepromo_is_enabled') || !managepromo_is_enabled('woo_limit_products_per_order')) {return;}

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////


// Only allow one product per cart
add_filter( 'woocommerce_add_to_cart_validation', 'one_product_per_order', 10, 3 );
function one_product_per_order( $passed, $product_id, $quantity ) {
    // If there's already something in the cart, prevent adding another
    if ( WC()->cart->get_cart_contents_count() >= 1 ) {
        wc_add_notice( 'Sorry, maar je kan slechts 1 artikel bestellen per order.', 'error' );
        return false;
    }
    return $passed;
}
