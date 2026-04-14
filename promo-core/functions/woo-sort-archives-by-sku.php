<?php
defined('ABSPATH') || exit;

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////

/**
 * Add SKU sorting options to the WooCommerce catalog order dropdowns.
 */
function ds_add_sku_catalog_orderby_options( $sortby ) {
    $sortby['sku_asc']  = 'Sorteer op SKU (oplopend)';
    $sortby['sku_desc'] = 'Sorteer op SKU (aflopend)';

    return $sortby;
}
add_filter( 'woocommerce_catalog_orderby', 'ds_add_sku_catalog_orderby_options' );
add_filter( 'woocommerce_default_catalog_orderby_options', 'ds_add_sku_catalog_orderby_options' );

/**
 * Apply SKU sorting when one of the custom orderby options is selected.
 */
function ds_apply_sku_catalog_ordering_args( $args, $orderby, $order ) {
    if ( 'sku_asc' !== $orderby && 'sku_desc' !== $orderby ) {
        return $args;
    }

    $args['orderby']  = 'meta_value';
    $args['order']    = 'sku_desc' === $orderby ? 'DESC' : 'ASC';
    $args['meta_key'] = '_sku';

    return $args;
}
add_filter( 'woocommerce_get_catalog_ordering_args', 'ds_apply_sku_catalog_ordering_args', 20, 3 );


