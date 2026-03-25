<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'managepromo_is_enabled' ) || ! managepromo_is_enabled( 'woo_quantity_step' ) ) { return; }

function mpc_qty_step_sanitize( $raw ): int {
    $step = absint( $raw );
    return $step > 0 ? $step : 1;
}

if ( ! function_exists( 'mpc_get_grouped_request_parent_product' ) ) {
    // Shared with the min-order feature so grouped children can follow the parent rules.
    function mpc_get_grouped_request_parent_product() {
        static $resolved = false;
        static $product  = null;

        if ( $resolved ) { return $product; }
        $resolved = true;

        $parent_id = isset( $_REQUEST['add-to-cart'] ) ? absint( wp_unslash( $_REQUEST['add-to-cart'] ) ) : 0;
        if ( $parent_id < 1 || ! isset( $_REQUEST['mpc_grouped_quantity'] ) ) { return null; }

        $candidate = wc_get_product( $parent_id );
        if ( ! $candidate || ! $candidate->is_type( 'grouped' ) ) { return null; }

        $product = $candidate;
        return $product;
    }
}

if ( ! function_exists( 'mpc_get_quantity_rules_product' ) ) {
    function mpc_get_quantity_rules_product( $product, array $cart_item = [] ) {
        if ( is_numeric( $product ) ) { $product = wc_get_product( (int) $product ); }
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) { return null; }

        $grouped_parent_id = isset( $cart_item['mpc_grouped_parent_id'] ) ? absint( $cart_item['mpc_grouped_parent_id'] ) : 0;
        if ( $grouped_parent_id > 0 ) {
            $grouped_parent = wc_get_product( $grouped_parent_id );
            if ( $grouped_parent && $grouped_parent->is_type( 'grouped' ) ) { return $grouped_parent; }
        }

        $request_parent = mpc_get_grouped_request_parent_product();
        if ( ! $request_parent ) { return $product; }

        $children          = array_map( 'absint', $request_parent->get_children() );
        $product_id        = $product->get_id();
        $product_parent_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : 0;

        if ( in_array( $product_id, $children, true ) || ( $product_parent_id > 0 && in_array( $product_parent_id, $children, true ) ) ) {
            return $request_parent;
        }

        return $product;
    }
}

if ( ! function_exists( 'mpc_get_builtin_min_purchase_quantity' ) ) {
    function mpc_get_builtin_min_purchase_quantity( $product ): int {
        if ( is_numeric( $product ) ) { $product = wc_get_product( (int) $product ); }
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) { return 1; }

        $min = (int) $product->get_min_purchase_quantity();
        return $min > 0 ? $min : 1;
    }
}

if ( ! function_exists( 'mpc_get_effective_min_quantity' ) ) {
    function mpc_get_effective_min_quantity( $product, array $cart_item = [] ): int {
        $rules_product = mpc_get_quantity_rules_product( $product, $cart_item );
        if ( ! $rules_product || ! is_a( $rules_product, 'WC_Product' ) ) { return 1; }
        return mpc_get_builtin_min_purchase_quantity( $rules_product );
    }
}

function mpc_qty_step_get_step( $product, array $cart_item = [] ): int {
    $product = mpc_get_quantity_rules_product( $product, $cart_item );
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) { return 1; }

    $step = absint( get_post_meta( $product->get_id(), '_mpc_qty_step', true ) );
    if ( $step < 1 && $product->is_type( 'variation' ) ) {
        $parent_id = $product->get_parent_id();
        if ( $parent_id > 0 ) { $step = absint( get_post_meta( $parent_id, '_mpc_qty_step', true ) ); }
    }

    return $step > 0 ? $step : 1;
}

function mpc_qty_step_get_max( $product, array $cart_item = [] ): int {
    $rules_product = mpc_get_quantity_rules_product( $product, $cart_item );
    return ( $rules_product && is_a( $rules_product, 'WC_Product' ) ) ? (int) $rules_product->get_max_purchase_quantity() : 0;
}

// Round to the nearest step, then clamp back up to the configured minimum.
function mpc_qty_step_normalize_qty( $qty, int $step, $product, array $cart_item = [] ): int {
    $qty  = max( 0, (int) wc_stock_amount( $qty ) );
    $step = max( 1, absint( $step ) );
    $min  = mpc_get_effective_min_quantity( $product, $cart_item );
    $max  = mpc_qty_step_get_max( $product, $cart_item );

    if ( $qty <= $min ) {
        $adjusted = $min;
    } elseif ( $step <= 1 ) {
        $adjusted = $qty;
    } else {
        $adjusted = (int) ( round( $qty / $step ) * $step );
        if ( $adjusted < $min ) { $adjusted = $min; }
    }

    if ( $max > 0 && $adjusted > $max ) {
        $adjusted = $step > 1 ? (int) ( floor( $max / $step ) * $step ) : $max;
        if ( $adjusted < $min ) { $adjusted = $max >= $min ? $min : $max; }
    }

    return max( 1, (int) $adjusted );
}

if ( ! managepromo_is_enabled( 'woo_min_order_amount' ) ) {
    function mpc_grouped_qty_render_master_input() {
        if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
        global $product;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) || ! $product->is_type( 'grouped' ) ) { return; }

        $min = mpc_get_effective_min_quantity( $product );
        $max = (int) $product->get_max_purchase_quantity();

        echo '<div class="mpc-grouped-master-quantity">';
        echo '<label for="mpc-grouped-quantity">' . esc_html__( 'Quantity', 'woocommerce' ) . '</label>';
        woocommerce_quantity_input(
            [
                'input_id'    => 'mpc-grouped-quantity',
                'input_name'  => 'mpc_grouped_quantity',
                'input_value' => $min,
                'min_value'   => $min,
                'max_value'   => $max,
            ],
            $product,
            true
        );
        echo '</div>';
    }

    function mpc_grouped_qty_replace_child_quantity_column( $value, $grouped_product_child ) {
        if ( ! is_product() ) { return $value; }
        global $product;

        if (
            ! $product || ! is_a( $product, 'WC_Product' ) || ! $product->is_type( 'grouped' ) ||
            ! $grouped_product_child || ! is_a( $grouped_product_child, 'WC_Product' )
        ) {
            return $value;
        }

        if ( ! $grouped_product_child->is_purchasable() || ! $grouped_product_child->is_in_stock() ) { return ''; }

        return sprintf(
            '<input type="hidden" class="mpc-grouped-child-qty" name="quantity[%1$d]" value="1" data-product-id="%1$d" />',
            absint( $grouped_product_child->get_id() )
        );
    }

    function mpc_grouped_qty_apply_request_quantities() {
        $grouped_parent = mpc_get_grouped_request_parent_product();
        if ( ! $grouped_parent ) { return; }

        $raw_qty = isset( $_REQUEST['mpc_grouped_quantity'] ) ? wc_clean( wp_unslash( $_REQUEST['mpc_grouped_quantity'] ) ) : '';
        if ( '' === $raw_qty ) { return; }

        $quantity          = mpc_qty_step_normalize_qty( $raw_qty, mpc_qty_step_get_step( $grouped_parent ), $grouped_parent );
        $posted_quantities = isset( $_REQUEST['quantity'] ) && is_array( $_REQUEST['quantity'] ) ? wp_unslash( $_REQUEST['quantity'] ) : [];
        $normalized        = [];

        foreach ( $grouped_parent->get_children() as $child_id ) {
            $child = wc_get_product( $child_id );
            if ( ! $child || ! $child->is_purchasable() || ! $child->is_in_stock() ) { continue; }
            $normalized[ $child_id ] = $quantity;
        }

        $_REQUEST['quantity'] = array_merge( $posted_quantities, $normalized );
        $_POST['quantity']    = $_REQUEST['quantity'];
    }

    function mpc_grouped_qty_attach_cart_item_parent( $cart_item_data, $product_id, $variation_id ) {
        $grouped_parent = mpc_get_grouped_request_parent_product();
        if ( ! $grouped_parent ) { return $cart_item_data; }

        $children = array_map( 'absint', $grouped_parent->get_children() );
        if ( in_array( absint( $product_id ), $children, true ) || ( $variation_id > 0 && in_array( absint( $variation_id ), $children, true ) ) ) {
            $cart_item_data['mpc_grouped_parent_id'] = $grouped_parent->get_id();
        }

        return $cart_item_data;
    }

    add_action( 'woocommerce_before_add_to_cart_button', 'mpc_grouped_qty_render_master_input', 5 );
    add_filter( 'woocommerce_grouped_product_list_column_quantity', 'mpc_grouped_qty_replace_child_quantity_column', 10, 2 );
    add_action( 'wp_loaded', 'mpc_grouped_qty_apply_request_quantities', 5 );
    add_filter( 'woocommerce_add_cart_item_data', 'mpc_grouped_qty_attach_cart_item_parent', 10, 3 );
}

// Admin fields: all base product types in General, variations inside the variation panel.
add_action( 'woocommerce_product_options_general_product_data', function () {
    global $product_object;
    $product_id = ( $product_object && is_a( $product_object, 'WC_Product' ) ) ? $product_object->get_id() : 0;
    $current    = $product_id > 0 ? get_post_meta( $product_id, '_mpc_qty_step', true ) : '';

    woocommerce_wp_text_input(
        [
            'id'                => '_mpc_qty_step',
            'label'             => 'Quantity step',
            'type'              => 'number',
            'desc_tip'          => true,
            'description'       => 'Increase/decrease quantity in steps of X on the frontend.',
            'value'             => '' !== $current && null !== $current ? $current : 1,
            'wrapper_class'     => 'show_if_simple show_if_variable show_if_grouped',
            'custom_attributes' => [ 'min' => '1', 'step' => '1' ],
        ]
    );
} );

add_action( 'woocommerce_admin_process_product_object', function ( $product ) {
    if ( ! $product || ! is_a( $product, 'WC_Product' ) || ! isset( $_POST['_mpc_qty_step'] ) ) { return; }
    $product->update_meta_data( '_mpc_qty_step', mpc_qty_step_sanitize( wc_clean( wp_unslash( $_POST['_mpc_qty_step'] ) ) ) );
} );

add_action( 'woocommerce_variation_options_pricing', function ( $loop, $variation_data, $variation ) {
    $current = get_post_meta( $variation->ID, '_mpc_qty_step', true );

    woocommerce_wp_text_input(
        [
            'id'                => "mpc_qty_step[$loop]",
            'name'              => "mpc_qty_step[$loop]",
            'label'             => 'Quantity step',
            'type'              => 'number',
            'desc_tip'          => true,
            'description'       => 'Increase/decrease quantity in steps of X on the frontend.',
            'value'             => '' !== $current && null !== $current ? $current : 1,
            'wrapper_class'     => 'form-row form-row-last',
            'custom_attributes' => [ 'min' => '1', 'step' => '1' ],
        ]
    );
}, 10, 3 );

add_action( 'woocommerce_save_product_variation', function ( $variation_id, $i ) {
    if ( ! isset( $_POST['mpc_qty_step'][ $i ] ) ) { return; }
    update_post_meta( $variation_id, '_mpc_qty_step', mpc_qty_step_sanitize( wc_clean( wp_unslash( $_POST['mpc_qty_step'][ $i ] ) ) ) );
}, 10, 2 );

// Frontend inputs carry both min and step so JS can normalize live typing.
add_filter( 'woocommerce_quantity_input_args', function ( $args, $product ) {
    if ( ( is_admin() && ! wp_doing_ajax() ) || ! $product || ! is_a( $product, 'WC_Product' ) ) { return $args; }

    $step = max( 1, mpc_qty_step_get_step( $product ) );
    $min  = mpc_get_effective_min_quantity( $product );

    $args['min_value']   = $min;
    $args['input_value'] = isset( $args['input_value'] ) && (int) $args['input_value'] >= $min ? $args['input_value'] : $min;

    if ( empty( $args['custom_attributes'] ) || ! is_array( $args['custom_attributes'] ) ) { $args['custom_attributes'] = []; }
    $args['custom_attributes']['data-mpc-min']  = (string) $min;
    $args['custom_attributes']['data-mpc-step'] = (string) $step;
    $args['custom_attributes']['data-step']     = (string) $step;
    $args['custom_attributes']['data-qty-step'] = (string) $step;

    if ( $step > 1 ) { $args['step'] = 'any'; }
    return $args;
}, 10, 2 );

add_filter( 'woocommerce_available_variation', function ( $data, $product, $variation ) {
    $data['step']    = mpc_qty_step_get_step( $variation );
    $data['min_qty'] = mpc_get_effective_min_quantity( $variation );
    return $data;
}, 10, 3 );

add_filter( 'woocommerce_add_to_cart_validation', function ( $passed, $product_id, $quantity, $variation_id = 0 ) {
    $product = wc_get_product( $variation_id ? $variation_id : $product_id );
    if ( ! $product ) { return $passed; }

    $adjusted = mpc_qty_step_normalize_qty( $quantity, mpc_qty_step_get_step( $product ), $product );
    if ( (int) $adjusted !== (int) $quantity ) {
        if ( isset( $_REQUEST['quantity'] ) && ! is_array( $_REQUEST['quantity'] ) ) { $_REQUEST['quantity'] = $adjusted; }
        if ( isset( $_POST['quantity'] ) && ! is_array( $_POST['quantity'] ) ) { $_POST['quantity'] = $adjusted; }
        wc_add_notice( sprintf( 'Hoeveelheid aangepast naar %d om aan de ingestelde bestelhoeveelheid te voldoen.', $adjusted ), 'notice' );
    }

    return $passed;
}, 10, 4 );

add_filter( 'woocommerce_add_to_cart_quantity', function ( $qty, $product_id ) {
    $variation_id = isset( $_REQUEST['variation_id'] ) ? absint( wp_unslash( $_REQUEST['variation_id'] ) ) : 0;
    $product      = wc_get_product( $variation_id ? $variation_id : $product_id );
    return $product ? mpc_qty_step_normalize_qty( $qty, mpc_qty_step_get_step( $product ), $product ) : $qty;
}, 10, 2 );

add_action( 'woocommerce_after_cart_item_quantity_update', function ( $cart_item_key, $quantity, $old_quantity, $cart ) {
    if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) { return; }

    $item = $cart->get_cart_item( $cart_item_key );
    if ( ! $item || empty( $item['data'] ) || ! is_a( $item['data'], 'WC_Product' ) ) { return; }

    $adjusted = mpc_qty_step_normalize_qty( $quantity, mpc_qty_step_get_step( $item['data'], $item ), $item['data'], $item );
    if ( (int) $adjusted !== (int) $quantity ) {
        $cart->set_quantity( $cart_item_key, $adjusted, true );
        wc_add_notice( sprintf( 'Hoeveelheid aangepast naar %d om aan de ingestelde bestelhoeveelheid te voldoen.', $adjusted ), 'notice' );
    }
}, 10, 4 );

add_action( 'wp_enqueue_scripts', function () {
    if ( is_admin() || ! ( is_product() || is_cart() || is_checkout() ) ) { return; }

    $rel_path = '../assets/mpc-quantity-step.js';
    $file     = plugin_dir_path( __FILE__ ) . $rel_path;
    $url      = plugin_dir_url( __FILE__ ) . $rel_path;

    wp_enqueue_script( 'mpc-quantity-step', $url, [], file_exists( $file ) ? filemtime( $file ) : '1.0.0', true );
}, 20 );
