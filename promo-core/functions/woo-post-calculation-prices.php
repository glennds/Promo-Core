<?php
defined('ABSPATH') || exit;

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////



// Add 'Prijs op nacalculatie' checkbox on Product > General tab.
add_action('woocommerce_product_options_general_product_data', function () {
    woocommerce_wp_checkbox([
        'id'          => '_post_calculation_price',
        'label'       => 'Prijs op nacalculatie',
        'description' => '',
    ]);
});

// Save "_post_calculation_price" meta on product save (Checked = yes // Unchecked = no).
add_action('woocommerce_admin_process_product_object', function ($product) {
    $is_checked = !empty($_POST['_post_calculation_price']);
    $product->update_meta_data('_post_calculation_price', $is_checked ? 'yes' : 'no');
});



// Check if checkbox is enabled
function ds_is_post_calculation_price($product): bool {
    // Normalize product id for variations
    $product_id = $product ? $product->get_id() : 0;
    if (!$product_id) return false;

    // Checkbox saved as 'yes' (or meta exists); treat any truth as enabled
    $val = get_post_meta($product_id, '_post_calculation_price', true);
    return ($val === 'yes' || $val === '1' || $val === 1 || $val === true);
}

// Make WC treat post-calculation products as price 0 for all calculations (cart/checkout/orders/stats).
function ds_postcalc_zero_price($price, $product) {
    if (is_admin() && !wp_doing_ajax()) {return $price;} // Don't interfere with admin editing screens
    if ($product && ds_is_post_calculation_price($product)) {return 0;}

    return $price;
}

// Apply to all relevant price getters
add_filter('woocommerce_product_get_price', 'ds_postcalc_zero_price', 9999, 2);
add_filter('woocommerce_product_get_regular_price', 'ds_postcalc_zero_price', 9999, 2);
add_filter('woocommerce_product_get_sale_price', 'ds_postcalc_zero_price', 9999, 2);
add_filter('woocommerce_product_variation_get_price', 'ds_postcalc_zero_price', 9999, 2);
add_filter('woocommerce_product_variation_get_regular_price', 'ds_postcalc_zero_price', 9999, 2);
add_filter('woocommerce_product_variation_get_sale_price', 'ds_postcalc_zero_price', 9999, 2);

// Set price fields to 0 if checkbox is enabled.
add_action('woocommerce_admin_process_product_object', function ($product) {
    // Always persist the checkbox state
    $product->update_meta_data('_post_calculation_price', !empty($_POST['_post_calculation_price']) ? 'yes' : 'no');

    if (empty($_POST['_post_calculation_price'])) {return;}

    // Explicitly force all price-related values to 0
    $product->set_regular_price('0');
    $product->set_sale_price('0');
    $product->set_price('0');

    // Also force raw meta to 0 for consistency (exports, direct meta reads, stats)
    $product->update_meta_data('_regular_price', '0');
    $product->update_meta_data('_sale_price', '0');
    $product->update_meta_data('_price', '0');
});

add_action('admin_footer-post.php', 'ds_postcalc_disable_price_inputs_live');
add_action('admin_footer-post-new.php', 'ds_postcalc_disable_price_inputs_live');

add_action('admin_footer-post.php', 'ds_postcalc_disable_price_inputs_live');
add_action('admin_footer-post-new.php', 'ds_postcalc_disable_price_inputs_live');

// Disable price fields when checkbox gets ticked
function ds_postcalc_disable_price_inputs_live(): void {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'product') return;
    ?>
    <script>
    (function () {
        // Enable/disable price inputs based on the post-calculation checkbox
        function togglePriceInputs() {
            var cb = document.getElementById('_post_calculation_price');
            if (!cb) return;

            var disabled = cb.checked;
            var regular  = document.getElementById('_regular_price');
            var sale     = document.getElementById('_sale_price');

            if (regular) regular.disabled = disabled;
            if (sale)    sale.disabled    = disabled;
        }

        // Initial state + live updates
        document.addEventListener('DOMContentLoaded', togglePriceInputs);
        document.addEventListener('change', function (e) {
            if (e.target && e.target.id === '_post_calculation_price') {
                togglePriceInputs();
            }
        });
    })();
    </script>
    <?php
}



// Create 'Op nacalculatie' label
function ds_postcalc_label(): string {return 'Op nacalculatie';}

// Replace prices with label 'Op nacalculatie' for: HTML outputs (i.e. /wp-admin/ products table).
add_filter('woocommerce_get_price_html', function ($price_html, $product) {
    if ($product && ds_is_post_calculation_price($product)) {return ds_postcalc_label();}
    return $price_html;
}, 9999, 2);

// Replace prices with label 'Op nacalculatie' for: cart items.
add_filter('woocommerce_cart_item_price', function ($price_html, $cart_item, $cart_item_key) {
    $product = $cart_item['data'] ?? null;
    if ($product && ds_is_post_calculation_price($product)) {return ds_postcalc_label();}
    return $price_html;
}, 9999, 3);

// Replace prices with label 'Op nacalculatie' for: cart totals.
add_filter('woocommerce_cart_item_subtotal', function ($subtotal_html, $cart_item, $cart_item_key) {
    $product = $cart_item['data'] ?? null;
    if ($product && ds_is_post_calculation_price($product)) {return ds_postcalc_label();}
    return $subtotal_html;
}, 9999, 3);

// Replace prices with label 'Op nacalculatie' for: Thankyou page, emails, account page orders overview.
add_filter('woocommerce_order_formatted_line_subtotal', function ($subtotal, $item, $order) {
    $product = is_callable([$item, 'get_product']) ? $item->get_product() : null; // Item can be product line; get product safely
    if ($product && ds_is_post_calculation_price($product)) {return ds_postcalc_label();}
    return $subtotal;
}, 9999, 3);

// Create [ds_postcalc_or_regular_price] shortcode for front-end productpage.
add_shortcode('ds_postcalc_or_regular_price', function ($atts) {
    $product_id = get_the_ID();
    if (!$product_id) return '';

    $flag = get_post_meta($product_id, '_post_calculation_price', true);
    $is_postcalc = ($flag === 'yes' || $flag === '1' || $flag === 1 || $flag === true);

    if ($is_postcalc) {
        return 'Op nacalculatie';
    }

    // Use stored regular price meta (not filtered runtime getters)
    $regular = get_post_meta($product_id, '_regular_price', true);
    if ($regular === '' || $regular === null) return '';

    return wc_price((float) $regular);
});

// Replace prices with label 'Op nacalculatie' for: /wp-admin/ single order overview.
add_action('admin_enqueue_scripts', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'shop_order') return;

    $order_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    $postcalc_product_ids = [];

    foreach ($order->get_items('line_item') as $item) {
        $product = $item->get_product();
        if (!$product) continue;

        $flag = get_post_meta($product->get_id(), '_post_calculation_price', true);
        $is_postcalc = ($flag === 'yes' || $flag === '1' || $flag === 1 || $flag === true);

        if ($is_postcalc) {$postcalc_product_ids[] = (int) $product->get_id();}
    }

    if (!$postcalc_product_ids) return;

    $json = wp_json_encode(array_values(array_unique($postcalc_product_ids)));

    wp_add_inline_script('jquery-core', "
        (function($){
            var postcalcIds = new Set($json);

            function apply(){
                $('#order_line_items tr.item').each(function(){
                    var \$row = $(this);
                    var pid =
                        \$row.find('input[name*=\"[product_id]\"]').first().val() ||
                        \$row.find('input.product_id').first().val() ||
                        '';
                    pid = parseInt(pid, 10);
                    if (!pid || !postcalcIds.has(pid)) return;

                    \$row.find('td.line_cost .view, td.line_cost .display_meta').first().text('Op nacalculatie');
                    \$row.find('td.line_subtotal .view, td.line_subtotal .display_meta').first().text('Op nacalculatie');
                    \$row.find('td.line_cost .amount, td.line_subtotal .amount, td.line_cost .woocommerce-Price-amount, td.line_subtotal .woocommerce-Price-amount')
                        .text('Op nacalculatie');
                });
            }

            $(document).ready(function(){
                apply();
                // Re-apply if WC re-renders order items
                $(document).on('wc_order_items_reloaded', apply);
                setTimeout(apply, 200);
            });
        })(jQuery);
    ");
});
