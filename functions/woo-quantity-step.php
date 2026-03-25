<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'managepromo_is_enabled' ) || ! managepromo_is_enabled( 'woo_quantity_step' ) ) { return; }

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////


function mpc_qty_step_sanitize( $raw ): int {
    $step = absint( $raw );
    return ( $step > 0 ) ? $step : 1;
}

function mpc_qty_step_get_step( $product ): int {
    if ( is_numeric( $product ) ) {
        $product = wc_get_product( (int) $product );
    }

    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
        return 1;
    }

    $raw  = get_post_meta( $product->get_id(), '_mpc_qty_step', true );
    $step = absint( $raw );

    if ( $step < 1 && $product->is_type( 'variation' ) ) {
        $parent_id = $product->get_parent_id();
        if ( $parent_id ) {
            $parent_raw  = get_post_meta( $parent_id, '_mpc_qty_step', true );
            $parent_step = absint( $parent_raw );
            if ( $parent_step > 0 ) {
                $step = $parent_step;
            }
        }
    }

    if ( $step < 1 ) {
        $step = 1;
    }

    return $step;
}

function mpc_qty_step_get_limits( $product ): array {
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
        return [ 1, 0 ];
    }

    $min = (int) $product->get_min_purchase_quantity();
    if ( $min < 1 ) {
        $min = 1;
    }

    $max = (int) $product->get_max_purchase_quantity(); // 0 means no max

    return [ $min, $max ];
}

function mpc_qty_step_normalize_qty( $qty, int $step, $product ): int {
    $qty  = wc_stock_amount( $qty );
    $step = max( 1, absint( $step ) );

    if ( $step <= 1 ) {
        return (int) $qty;
    }

    [ $min, $max ] = mpc_qty_step_get_limits( $product );

    // Keep min value as-is; step is applied relative to min.
    $base = $min;

    // If stock max is lower than base, clamp base to max to respect stock rules.
    if ( $max > 0 && $base > $max ) {
        $base = $max;
    }

    if ( $qty < $base ) {
        $adjusted = $base;
    } else {
        $steps    = (int) round( ( $qty - $base ) / $step );
        $adjusted = $base + ( $steps * $step );
    }

    if ( $max > 0 && $adjusted > $max ) {
        $adjusted = $base + (int) ( floor( ( $max - $base ) / $step ) * $step );
        if ( $adjusted < $base ) {
            $adjusted = $max; // last-resort within stock
        }
    }

    return (int) $adjusted;
}


// Admin: add Quantity Step field to product inventory tab (simple + variable parent)
add_action( 'woocommerce_product_options_inventory_product_data', function () {
    global $post;
    if ( ! $post ) { return; }

    $current = get_post_meta( $post->ID, '_mpc_qty_step', true );
    if ( $current === '' || $current === null ) {
        $current = 1;
    }

    woocommerce_wp_text_input([
        'id'                => '_mpc_qty_step',
        'label'             => 'Quantity step',
        'type'              => 'number',
        'desc_tip'          => true,
        'description'       => 'Increase/decrease quantity in steps of X on the frontend.',
        'value'             => $current,
        'custom_attributes' => [
            'min'  => '1',
            'step' => '1',
        ],
    ]);
});

// Admin: save Quantity Step (simple + variable parent)
add_action( 'woocommerce_admin_process_product_object', function ( $product ) {
    $raw  = isset( $_POST['_mpc_qty_step'] ) ? wc_clean( wp_unslash( $_POST['_mpc_qty_step'] ) ) : '';
    $step = mpc_qty_step_sanitize( $raw );
    $product->update_meta_data( '_mpc_qty_step', $step );
});

// Admin: add Quantity Step to variations
add_action( 'woocommerce_variation_options_inventory', function ( $loop, $variation_data, $variation ) {
    $current = get_post_meta( $variation->ID, '_mpc_qty_step', true );
    if ( $current === '' || $current === null ) {
        $current = 1;
    }

    woocommerce_wp_text_input([
        'id'                => "mpc_qty_step[$loop]",
        'name'              => "mpc_qty_step[$loop]",
        'label'             => 'Quantity step',
        'type'              => 'number',
        'desc_tip'          => true,
        'description'       => 'Increase/decrease quantity in steps of X on the frontend.',
        'value'             => $current,
        'wrapper_class'     => 'form-row form-row-first',
        'custom_attributes' => [
            'min'  => '1',
            'step' => '1',
        ],
    ]);
}, 10, 3 );

// Admin: save Quantity Step for variations
add_action( 'woocommerce_save_product_variation', function ( $variation_id, $i ) {
    if ( ! isset( $_POST['mpc_qty_step'][ $i ] ) ) { return; }

    $raw  = wc_clean( wp_unslash( $_POST['mpc_qty_step'][ $i ] ) );
    $step = mpc_qty_step_sanitize( $raw );
    update_post_meta( $variation_id, '_mpc_qty_step', $step );
}, 10, 2 );


// Frontend: quantity input attributes
add_filter( 'woocommerce_quantity_input_args', function ( $args, $product ) {
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) { return $args; }

    $step = mpc_qty_step_get_step( $product );
    if ( $step <= 1 ) { return $args; }

    $args['step'] = $step;

    $min = isset( $args['min_value'] ) ? (int) $args['min_value'] : 1;
    $max = isset( $args['max_value'] ) ? (int) $args['max_value'] : 0;
    if ( $max > 0 && $min > $max ) { $min = $max; }

    $input_value = isset( $args['input_value'] ) ? (int) $args['input_value'] : 0;
    // Ensure min aligns with initial quantity so HTML5 validation accepts the first value.
    if ( $input_value > $min ) {
        $min = $input_value;
    }

    $args['min_value'] = $min;
    $current_value = isset( $args['input_value'] ) ? (int) $args['input_value'] : 0;
    if ( $current_value < $min ) {
        $args['input_value'] = $min; // auto-fill minimum order quantity
    }

    if ( empty( $args['custom_attributes'] ) || ! is_array( $args['custom_attributes'] ) ) {
        $args['custom_attributes'] = [];
    }
    $args['custom_attributes']['data-mpc-step'] = (string) $step;
    $args['custom_attributes']['data-step']     = (string) $step;
    $args['custom_attributes']['data-qty-step'] = (string) $step;

    return $args;
}, 10, 2 );

// Frontend: ensure variation data carries step/min so JS updates on selection
add_filter( 'woocommerce_available_variation', function ( $data, $product, $variation ) {
    $step = mpc_qty_step_get_step( $variation );
    if ( $step <= 1 ) { return $data; }

    $data['step'] = $step;
    return $data;
}, 10, 3 );


// Validation: add-to-cart (round to nearest valid step)
add_filter( 'woocommerce_add_to_cart_validation', function ( $passed, $product_id, $quantity, $variation_id = 0 ) {
    $target_id = $variation_id ? $variation_id : $product_id;
    $product   = wc_get_product( $target_id );
    if ( ! $product ) { return $passed; }

    $step = mpc_qty_step_get_step( $product );
    if ( $step <= 1 ) { return $passed; }

    $adjusted = mpc_qty_step_normalize_qty( $quantity, $step, $product );

    if ( (int) $adjusted !== (int) $quantity ) {
        if ( isset( $_REQUEST['quantity'] ) ) { $_REQUEST['quantity'] = $adjusted; }
        if ( isset( $_POST['quantity'] ) ) { $_POST['quantity'] = $adjusted; }
        wc_add_notice( sprintf( 'Quantity adjusted to %d (step size %d).', $adjusted, $step ), 'notice' );
    }

    return $passed;
}, 10, 4 );

// Add-to-cart quantity filter (ensure adjusted quantity is used)
add_filter( 'woocommerce_add_to_cart_quantity', function ( $qty, $product_id ) {
    $variation_id = isset( $_REQUEST['variation_id'] ) ? (int) $_REQUEST['variation_id'] : 0;
    $target_id    = $variation_id ? $variation_id : $product_id;
    $product      = wc_get_product( $target_id );
    if ( ! $product ) { return $qty; }

    $step = mpc_qty_step_get_step( $product );
    if ( $step <= 1 ) { return $qty; }

    return mpc_qty_step_normalize_qty( $qty, $step, $product );
}, 10, 2 );

// Cart update: enforce step when quantities are updated
add_action( 'woocommerce_after_cart_item_quantity_update', function ( $cart_item_key, $quantity, $old_quantity, $cart ) {
    if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) { return; }

    $item = $cart->get_cart_item( $cart_item_key );
    if ( ! $item || empty( $item['data'] ) ) { return; }

    $product = $item['data'];
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) { return; }

    $step = mpc_qty_step_get_step( $product );
    if ( $step <= 1 ) { return; }

    $adjusted = mpc_qty_step_normalize_qty( $quantity, $step, $product );

    if ( (int) $adjusted !== (int) $quantity ) {
        $cart->set_quantity( $cart_item_key, $adjusted, true );
        wc_add_notice( sprintf( 'Quantity adjusted to %d (step size %d).', $adjusted, $step ), 'notice' );
    }
}, 10, 4 );

// Frontend: enqueue JS for +/- buttons
add_action( 'wp_enqueue_scripts', function () {
    if ( is_admin() ) { return; }
    if ( ! ( is_product() || is_cart() || is_checkout() ) ) { return; }

    $rel_path = '../assets/mpc-quantity-step.js';
    $file     = plugin_dir_path( __FILE__ ) . $rel_path;
    $url      = plugin_dir_url( __FILE__ ) . $rel_path;

    wp_enqueue_script(
        'mpc-quantity-step',
        $url,
        [],
        file_exists( $file ) ? filemtime( $file ) : '1.0.0',
        true
    );
}, 20 );
