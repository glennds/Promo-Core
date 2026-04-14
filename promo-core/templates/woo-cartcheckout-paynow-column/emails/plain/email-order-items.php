<?php
/**
 * Email Order Items (plain)
 *
 * @package WooCommerce\Templates\Emails\Plain
 * @version 9.8.0
 */

defined( 'ABSPATH' ) || exit;

foreach ( $items as $item_id => $item ) :
    if ( ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) {
        continue;
    }

    $product       = $item->get_product();
    $sku           = '';
    $purchase_note = '';

    if ( is_object( $product ) ) {
        $sku           = $product->get_sku();
        $purchase_note = $product->get_purchase_note();
    }

    $product_name = wp_strip_all_tags( apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, false ) );
    $quantity     = wp_strip_all_tags( (string) apply_filters( 'woocommerce_email_order_item_quantity', $item->get_quantity(), $item ) );

    echo $product_name;

    if ( $show_sku && $sku ) {
        echo ' (#' . $sku . ')';
    }

    echo ' x ' . $quantity . "\n";
    echo ds_paynow_column_get_regular_price_label() . ': ' . wp_strip_all_tags( ds_paynow_column_get_order_item_regular_price_html( $item, $order ) ) . ' | ';
    echo ds_paynow_column_get_pay_now_label() . ': ' . wp_strip_all_tags( ds_paynow_column_get_order_item_pay_now_price_html( $item, $order ) ) . ' | ';
    echo esc_html__( 'Subtotal', 'woocommerce' ) . ': ' . wp_strip_all_tags( ds_paynow_column_get_order_item_regular_subtotal_html( $item, $order ) ) . "\n";

    do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $order, $plain_text );

    echo strip_tags(
        wc_display_item_meta(
            $item,
            array(
                'before'    => "\n- ",
                'separator' => "\n- ",
                'after'     => '',
                'echo'      => false,
                'autop'     => false,
            )
        )
    );

    do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $order, $plain_text );

    if ( $show_purchase_note && $purchase_note ) {
        echo "\n" . wp_strip_all_tags( do_shortcode( $purchase_note ) );
    }

    echo "\n\n";
endforeach;
