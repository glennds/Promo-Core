<?php
defined('ABSPATH') || exit;

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////



function mpc_qty_step_sanitize( $raw ): int {
    $step = absint( $raw );
    return $step > 0 ? $step : 1;
}

if ( ! function_exists( 'mpc_print_product_type_visibility_script' ) ) {
    // WooCommerce does not always reveal custom fields reliably for extension product types.
    function mpc_print_product_type_visibility_script() {
        if ( ! is_admin() ) { return; }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'product' !== $screen->post_type ) { return; }
        ?>
        <script>
            jQuery(function ($) {
                function toggleMpcProductFields() {
                    var type = $("#product-type").val() || "";
                    $(".mpc-show-if-simple, .mpc-show-if-variable, .mpc-show-if-bundle").hide();
                    if (type === "simple") { $(".mpc-show-if-simple").show(); }
                    if (type === "variable") { $(".mpc-show-if-variable").show(); }
                    if (type === "bundle") { $(".mpc-show-if-bundle").show(); }
                }

                $(document.body).on("woocommerce-product-type-change", toggleMpcProductFields);
                $("#product-type").on("change", toggleMpcProductFields);
                toggleMpcProductFields();
            });
        </script>
        <?php
    }

    add_action( 'admin_footer', 'mpc_print_product_type_visibility_script', 50 );
}

if ( ! function_exists( 'mpc_is_bundled_cart_item' ) ) {
    // Product Bundles identifies child cart items with the `bundled_by` relationship field.
    function mpc_is_bundled_cart_item( array $cart_item ): bool {
        if ( function_exists( 'wc_pb_is_bundled_cart_item' ) ) {
            return wc_pb_is_bundled_cart_item( $cart_item, WC()->cart ? WC()->cart->get_cart() : false );
        }

        return ! empty( $cart_item['bundled_by'] );
    }
}

if ( ! function_exists( 'mpc_get_cart_item_from_quantity_input_args' ) ) {
    // Cart quantity inputs expose the cart item key in their input name.
    function mpc_get_cart_item_from_quantity_input_args( array $args ): array {
        if ( empty( $args['input_name'] ) || ! is_string( $args['input_name'] ) || ! WC()->cart ) { return []; }
        if ( ! preg_match( '/^cart\[([^\]]+)\]\[qty\]$/', $args['input_name'], $matches ) ) { return []; }

        $cart_item = WC()->cart->get_cart_item( wc_clean( $matches[1] ) );
        return is_array( $cart_item ) ? $cart_item : [];
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
        if ( $cart_item && mpc_is_bundled_cart_item( $cart_item ) ) { return 1; }
        return mpc_get_builtin_min_purchase_quantity( $product );
    }
}

function mpc_qty_step_get_step( $product, array $cart_item = [] ): int {
    if ( $cart_item && mpc_is_bundled_cart_item( $cart_item ) ) { return 1; }
    if ( is_numeric( $product ) ) { $product = wc_get_product( (int) $product ); }
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) { return 1; }

    if ( $product->is_type( 'variation' ) ) {
        $parent_id = $product->get_parent_id();
        $step      = $parent_id > 0 ? absint( get_post_meta( $parent_id, '_mpc_qty_step', true ) ) : 0;
        return $step > 0 ? $step : 1;
    }

    $step = absint( get_post_meta( $product->get_id(), '_mpc_qty_step', true ) );
    return $step > 0 ? $step : 1;
}

function mpc_qty_step_get_max( $product, array $cart_item = [] ): int {
    if ( $cart_item && mpc_is_bundled_cart_item( $cart_item ) ) { return 0; }
    if ( is_numeric( $product ) ) { $product = wc_get_product( (int) $product ); }
    return ( $product && is_a( $product, 'WC_Product' ) ) ? (int) $product->get_max_purchase_quantity() : 0;
}

// Round to the nearest step, then clamp back to the allowed minimum and maximum.
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

// Admin field: use the Inventory tab for simple, variable, and bundle products.
add_action( 'woocommerce_product_options_inventory_product_data', function () {
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
            'placeholder'       => '1',
            'value'             => '' !== $current && null !== $current ? $current : 1,
            'wrapper_class'     => 'show_if_simple show_if_variable show_if_bundle mpc-show-if-simple mpc-show-if-variable mpc-show-if-bundle',
            'custom_attributes' => [ 'min' => '1', 'step' => '1' ],
        ]
    );
} );

add_action( 'woocommerce_admin_process_product_object', function ( $product ) {
    if ( ! $product || ! is_a( $product, 'WC_Product' ) || ! isset( $_POST['_mpc_qty_step'] ) ) { return; }
    $product->update_meta_data( '_mpc_qty_step', mpc_qty_step_sanitize( wc_clean( wp_unslash( $_POST['_mpc_qty_step'] ) ) ) );
} );

// Frontend and cart inputs carry both min and step so JS can normalize live typing.
add_filter( 'woocommerce_quantity_input_args', function ( $args, $product ) {
    if ( ( is_admin() && ! wp_doing_ajax() ) || ! $product || ! is_a( $product, 'WC_Product' ) ) { return $args; }

    $cart_item = mpc_get_cart_item_from_quantity_input_args( $args );
    if ( $cart_item && mpc_is_bundled_cart_item( $cart_item ) ) { return $args; }

    $step = max( 1, mpc_qty_step_get_step( $product, $cart_item ) );
    $min  = mpc_get_effective_min_quantity( $product, $cart_item );

    $args['min_value']   = $min;
    $args['input_value'] = isset( $args['input_value'] ) && (int) $args['input_value'] >= $min ? $args['input_value'] : $min;

    if ( empty( $args['custom_attributes'] ) || ! is_array( $args['custom_attributes'] ) ) { $args['custom_attributes'] = []; }
    $args['custom_attributes']['data-mpc-min']  = (string) $min;
    $args['custom_attributes']['data-mpc-step'] = (string) $step;
    $args['custom_attributes']['data-step']     = (string) $step;
    $args['custom_attributes']['data-qty-step'] = (string) $step;
    $args['step']                               = (string) $step;

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
    if ( ! $item || empty( $item['data'] ) || ! is_a( $item['data'], 'WC_Product' ) || mpc_is_bundled_cart_item( $item ) ) { return; }

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
