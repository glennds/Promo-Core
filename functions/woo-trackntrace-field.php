<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'managepromo_is_enabled' ) || ! managepromo_is_enabled( 'woo_trackntrace_field' ) ) { return; }

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////

// Returns the public order meta key used by admin, REST API and webhooks.
function mpc_trackntrace_meta_key(): string {
    return 'trackntrace';
}

// Sanitizes the tracking URL while still allowing an empty value.
function mpc_trackntrace_sanitize_value( $value ): string {
    $value = is_string( $value ) ? trim( $value ) : '';
    return $value === '' ? '' : esc_url_raw( $value );
}

// Reads the current Track & Trace value from an order object or order ID.
function mpc_trackntrace_get_value( $order ): string {
    if ( is_numeric( $order ) ) {
        $order = wc_get_order( (int) $order );
    }

    return ( $order && is_a( $order, 'WC_Order' ) )
        ? (string) $order->get_meta( mpc_trackntrace_meta_key(), true, 'edit' )
        : '';
}

// Stores the sanitized value on the order, or removes the meta when empty.
function mpc_trackntrace_save_value( WC_Order $order, $value, ?string $meta_key = null ): void {
    $meta_key = $meta_key ?: mpc_trackntrace_meta_key();
    $value    = mpc_trackntrace_sanitize_value( $value );

    if ( $value === '' ) {
        $order->delete_meta_data( $meta_key );
        return;
    }

    $order->update_meta_data( $meta_key, $value );
}

// Adds the field to WooCommerce's shipping section so it uses the native edit toggle UI.
function mpc_trackntrace_add_admin_field( array $fields, $order, $context ): array {
    unset( $context );

    $field = [
        'label'           => __( 'Track & Trace', 'managepromo-core' ),
        'id'              => mpc_trackntrace_meta_key(),
        'type'            => 'url',
        'show'            => true,
        'wrapper_class'   => 'form-field-wide',
        'placeholder'     => 'https://',
        'description'     => __( 'Tracking link for this order.', 'managepromo-core' ),
        'desc_tip'        => true,
        'value'           => mpc_trackntrace_get_value( $order ),
        'update_callback' => 'mpc_trackntrace_update_order_meta',
    ];

    $updated = [];

    foreach ( $fields as $key => $existing_field ) {
        $updated[ $key ] = $existing_field;
        if ( 'phone' === $key ) {
            $updated['trackntrace'] = $field;
        }
    }

    if ( ! isset( $updated['trackntrace'] ) ) {
        $updated['trackntrace'] = $field;
    }

    return $updated;
}

// Saves the field when the admin order form is submitted.
function mpc_trackntrace_update_order_meta( $field_id, $value, $order ): void {
    if ( $order && is_a( $order, 'WC_Order' ) ) {
        mpc_trackntrace_save_value( $order, $value, $field_id );
    }
}

// Re-sanitizes programmatic and REST-driven updates before WooCommerce saves the order.
function mpc_trackntrace_normalize_before_order_save( $order ): void {
    if ( $order && is_a( $order, 'WC_Order' ) ) {
        mpc_trackntrace_save_value( $order, mpc_trackntrace_get_value( $order ) );
    }
}

// Registers the meta so it is available in the WordPress REST API schema.
function mpc_trackntrace_register_meta(): void {
    register_post_meta( 'shop_order', mpc_trackntrace_meta_key(), [
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'mpc_trackntrace_sanitize_value',
        'auth_callback'     => static function () {
            return current_user_can( 'edit_shop_orders' );
        },
    ] );
}

add_filter( 'woocommerce_admin_shipping_fields', 'mpc_trackntrace_add_admin_field', 10, 3 );
add_action( 'woocommerce_before_order_object_save', 'mpc_trackntrace_normalize_before_order_save' );
add_action( 'init', 'mpc_trackntrace_register_meta' );
