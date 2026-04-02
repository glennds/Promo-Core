<?php
defined('ABSPATH') || exit;

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////



function ds_min_order_amount_sanitize( $raw ): int {
    $value = absint( $raw );
    return $value > 0 ? $value : 1;
}

function ds_min_order_amount_get( $product ): int {
    if ( is_numeric( $product ) ) { $product = wc_get_product( (int) $product ); }
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) { return 1; }

    if ( $product->is_type( 'variation' ) ) {
        $parent_id = $product->get_parent_id();
        if ( $parent_id > 0 ) {
            return ds_min_order_amount_sanitize( get_post_meta( $parent_id, 'min_order_amount', true ) );
        }
    }

    return ds_min_order_amount_sanitize( get_post_meta( $product->get_id(), 'min_order_amount', true ) );
}

if ( ! function_exists( 'ds_print_product_type_visibility_script' ) ) {
    // WooCommerce does not always reveal custom fields reliably for extension product types.
    function ds_print_product_type_visibility_script() {
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

    add_action( 'admin_footer', 'ds_print_product_type_visibility_script', 50 );
}

if ( ! function_exists( 'ds_is_bundled_cart_item' ) ) {
    // Product Bundles identifies child cart items with the `bundled_by` relationship field.
    function ds_is_bundled_cart_item( array $cart_item ): bool {
        if ( function_exists( 'wc_pb_is_bundled_cart_item' ) ) {
            return wc_pb_is_bundled_cart_item( $cart_item, WC()->cart ? WC()->cart->get_cart() : false );
        }

        return ! empty( $cart_item['bundled_by'] );
    }
}

if ( ! function_exists( 'ds_get_cart_item_from_quantity_input_args' ) ) {
    // Cart quantity inputs expose the cart item key in their input name.
    function ds_get_cart_item_from_quantity_input_args( array $args ): array {
        if ( empty( $args['input_name'] ) || ! is_string( $args['input_name'] ) || ! WC()->cart ) { return []; }
        if ( ! preg_match( '/^cart\[([^\]]+)\]\[qty\]$/', $args['input_name'], $matches ) ) { return []; }

        $cart_item = WC()->cart->get_cart_item( wc_clean( $matches[1] ) );
        return is_array( $cart_item ) ? $cart_item : [];
    }
}

if ( ! function_exists( 'ds_get_builtin_min_purchase_quantity' ) ) {
    function ds_get_builtin_min_purchase_quantity( $product ): int {
        if ( is_numeric( $product ) ) { $product = wc_get_product( (int) $product ); }
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) { return 1; }

        $min = (int) $product->get_min_purchase_quantity();
        return $min > 0 ? $min : 1;
    }
}

if ( ! function_exists( 'ds_get_effective_min_quantity' ) ) {
    function ds_get_effective_min_quantity( $product, array $cart_item = [] ): int {
        if ( ! empty( $cart_item ) && ds_is_bundled_cart_item( $cart_item ) ) { return 1; }

        if ( is_numeric( $product ) ) { $product = wc_get_product( (int) $product ); }
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) { return 1; }

        $min = max( ds_get_builtin_min_purchase_quantity( $product ), ds_min_order_amount_get( $product ) );
        return $min > 0 ? $min : 1;
    }
}

function ds_min_order_amount_normalize_qty( $qty, $product, array $cart_item = [] ): int {
    $qty = max( 0, (int) wc_stock_amount( $qty ) );
    return max( ds_get_effective_min_quantity( $product, $cart_item ), $qty );
}

// Admin field: use the Inventory tab for simple, variable and bundle products.
add_action( 'woocommerce_product_options_inventory_product_data', function () {
    global $product_object;
    $product_id = ( $product_object && is_a( $product_object, 'WC_Product' ) ) ? $product_object->get_id() : 0;
    $current    = $product_id > 0 ? get_post_meta( $product_id, 'min_order_amount', true ) : '';

    woocommerce_wp_text_input(
        [
            'id'                => 'min_order_amount',
            'label'             => 'Min. bestelhoeveelheid',
            'type'              => 'number',
            'desc_tip'          => true,
            'description'       => 'Minimum aantal stuks dat besteld moet worden voor dit product.',
            'placeholder'       => '1',
            'value'             => '' !== $current && null !== $current ? $current : 1,
            'wrapper_class'     => 'show_if_simple show_if_variable show_if_bundle mpc-show-if-simple mpc-show-if-variable mpc-show-if-bundle',
            'custom_attributes' => [ 'min' => '1', 'step' => '1' ],
        ]
    );
} );

add_action( 'woocommerce_admin_process_product_object', function ( $product ) {
    if ( ! $product || ! is_a( $product, 'WC_Product' ) || ! isset( $_POST['min_order_amount'] ) ) { return; }
    $raw = wc_clean( wp_unslash( $_POST['min_order_amount'] ) );
    $product->update_meta_data( 'min_order_amount', ds_min_order_amount_sanitize( $raw ) );
} );

// Frontend inputs always get the effective minimum, including variation refreshes.
add_filter( 'woocommerce_quantity_input_args', function ( array $args, $product ) {
    if ( ( is_admin() && ! wp_doing_ajax() ) || ! $product || ! is_a( $product, 'WC_Product' ) ) { return $args; }

    $cart_item = ds_get_cart_item_from_quantity_input_args( $args );
    if ( $cart_item && ds_is_bundled_cart_item( $cart_item ) ) { return $args; }

    $min                 = ds_get_effective_min_quantity( $product, $cart_item );
    $args['min_value']   = $min;
    $args['input_value'] = isset( $args['input_value'] ) && (int) $args['input_value'] >= $min ? $args['input_value'] : $min;

    if ( empty( $args['custom_attributes'] ) || ! is_array( $args['custom_attributes'] ) ) { $args['custom_attributes'] = []; }
    $args['custom_attributes']['data-mpc-min'] = (string) $min;

    return $args;
}, 10, 2 );

add_filter( 'woocommerce_available_variation', function ( $data, $product, $variation ) {
    $data['min_qty'] = ds_get_effective_min_quantity( $variation );
    return $data;
}, 10, 3 );


// When quantity-step is off, minimum quantity still needs to be enforced server-side.
add_filter( 'woocommerce_add_to_cart_validation', function ( $passed, $product_id, $quantity, $variation_id = 0 ) {
    $product = wc_get_product( $variation_id ? $variation_id : $product_id );
    if ( ! $product ) { return $passed; }

    $adjusted = ds_min_order_amount_normalize_qty( $quantity, $product );
    if ( (int) $adjusted !== (int) $quantity ) {
        if ( isset( $_REQUEST['quantity'] ) && ! is_array( $_REQUEST['quantity'] ) ) { $_REQUEST['quantity'] = $adjusted; }
        if ( isset( $_POST['quantity'] ) && ! is_array( $_POST['quantity'] ) ) { $_POST['quantity'] = $adjusted; }
        wc_add_notice( sprintf( 'Hoeveelheid aangepast naar %d vanwege de minimale bestelhoeveelheid.', $adjusted ), 'notice' );
    }

    return $passed;
}, 10, 4 );

add_filter( 'woocommerce_add_to_cart_quantity', function ( $qty, $product_id ) {
    $variation_id = isset( $_REQUEST['variation_id'] ) ? absint( wp_unslash( $_REQUEST['variation_id'] ) ) : 0;
    $product      = wc_get_product( $variation_id ? $variation_id : $product_id );
    return $product ? ds_min_order_amount_normalize_qty( $qty, $product ) : $qty;
}, 10, 2 );

add_action( 'woocommerce_after_cart_item_quantity_update', function ( $cart_item_key, $quantity, $old_quantity, $cart ) {
    if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) { return; }

    $item = $cart->get_cart_item( $cart_item_key );
    if ( ! $item || empty( $item['data'] ) || ! is_a( $item['data'], 'WC_Product' ) || ds_is_bundled_cart_item( $item ) ) { return; }

    $adjusted = ds_min_order_amount_normalize_qty( $quantity, $item['data'], $item );
    if ( (int) $adjusted !== (int) $quantity ) {
        $cart->set_quantity( $cart_item_key, $adjusted, true );
        wc_add_notice( sprintf( 'Hoeveelheid aangepast naar %d vanwege de minimale bestelhoeveelheid.', $adjusted ), 'notice' );
    }
}, 10, 4 );

add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
    if ( ! WC()->cart ) { return; }

    foreach ( WC()->cart->get_cart() as $cart_item ) {
        if ( ds_is_bundled_cart_item( $cart_item ) ) { continue; }

        $product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) { continue; }

        $min = ds_get_effective_min_quantity( $product, $cart_item );
        $qty = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0;

        if ( $qty < $min ) {
            $errors->add(
                'min_order_amount_' . $product->get_id(),
                sprintf( 'Voor "%s" is de minimale bestelhoeveelheid %d. Pas de hoeveelheid aan en probeer opnieuw.', $product->get_name(), $min )
            );
        }
    }
}, 10, 2 );

add_action( 'wp_enqueue_scripts', function () {
    if ( is_admin() || ! ( is_product() || is_cart() || is_checkout() ) ) { return; }

    $rel_path = '../assets/mpc-quantity-step.js';
    $file     = plugin_dir_path( __FILE__ ) . $rel_path;
    $url      = plugin_dir_url( __FILE__ ) . $rel_path;

    wp_enqueue_script( 'mpc-quantity-step', $url, [], file_exists( $file ) ? filemtime( $file ) : '1.0.0', true );
}, 20 );
