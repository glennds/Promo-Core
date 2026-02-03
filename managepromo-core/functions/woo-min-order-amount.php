<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}
if (!function_exists('managepromo_is_enabled') || !managepromo_is_enabled('woo_min_order_amount')) {return;}

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////



// Add "Min. bestelhoeveelheid" number field to Product > General tab.
add_action('woocommerce_product_options_general_product_data', function () {
    woocommerce_wp_text_input([
        'id'                => 'min_order_amount',
        'label'             => 'Min. bestelhoeveelheid',
        'type'              => 'number',
        'desc_tip'          => true,
        'description'       => 'Minimum aantal stuks dat besteld moet worden voor dit product.',
        'placeholder'       => '1',
        'custom_attributes' => [
            'min'  => '1',
            'step' => '1',
        ],
    ]);
});

// Save "min_order_amount" meta on product save
add_action('woocommerce_admin_process_product_object', function ($product) {
    $raw = isset($_POST['min_order_amount']) ? wc_clean(wp_unslash($_POST['min_order_amount'])) : '';

    // Default to 1 when empty/missing
    if ($raw === '' || $raw === null) {
        $product->update_meta_data('min_order_amount', 1);
        return;
    }

    $value = max(1, (int) $raw);
    $product->update_meta_data('min_order_amount', $value);
});



// Translate min. order amount to frontend quantity input
add_filter('woocommerce_quantity_input_args', function (array $args, $product) {
    if (!is_product() || is_cart() || is_checkout()) {return $args;}    // Disable if not productpage

    $min_order_amount = (int) get_post_meta($product->get_id(), 'min_order_amount', true);
    $value = ($min_order_amount > 0) ? $min_order_amount : 1;

    
    $args['input_value'] = $value;      // Always set the initial quantity value

    return $args;
}, 10, 2);



// Check cart item quantities against each item's min. order amount.
add_action('woocommerce_after_checkout_validation', function ($data, $errors) {
    foreach (WC()->cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        if (!$product) {
            continue;
        }

        $product_id = $product->get_id();
        $min = (int) get_post_meta($product_id, 'min_order_amount', true);

        // If not set (or invalid), treat as 1
        if ($min < 1) {
            $min = 1;
        }

        $qty = (int) $cart_item['quantity'];

        if ($qty < $min) {
            $errors->add(
                'min_order_amount_' . $product_id,
                sprintf(
                    'Voor "%s" is de minimale bestelhoeveelheid %d. Pas de hoeveelheid aan en probeer opnieuw.',
                    $product->get_name(),
                    $min
                )
            );
        }
    }
}, 10, 2);
