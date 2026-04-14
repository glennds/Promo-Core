<?php
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'ds_paynow_column_get_locale' ) ) {
    function ds_paynow_column_get_locale(): string {
        if ( function_exists( 'determine_locale' ) ) {
            return (string) determine_locale();
        }

        return (string) get_locale();
    }
}

if ( ! function_exists( 'ds_paynow_column_get_regular_price_label' ) ) {
    function ds_paynow_column_get_regular_price_label(): string {
        return (string) apply_filters( 'ds_paynow_column_regular_price_label', __( 'Price', 'woocommerce' ) );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_pay_now_label' ) ) {
    function ds_paynow_column_get_pay_now_label(): string {
        $locale = strtolower( ds_paynow_column_get_locale() );
        $label  = 0 === strpos( $locale, 'nl' ) ? 'Betaal nu' : 'Pay now';

        return (string) apply_filters( 'ds_paynow_column_pay_now_label', $label, $locale );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_template_root' ) ) {
    function ds_paynow_column_get_template_root(): string {
        return dirname( __DIR__ ) . '/templates/woo-cartcheckout-paynow-column/';
    }
}

if ( ! function_exists( 'ds_paynow_column_get_postcalc_label' ) ) {
    function ds_paynow_column_get_postcalc_label(): string {
        if ( function_exists( 'ds_postcalc_label' ) ) {
            return (string) ds_postcalc_label();
        }

        return 'Op nacalculatie';
    }
}

if ( ! function_exists( 'ds_paynow_column_is_postcalc_product' ) ) {
    function ds_paynow_column_is_postcalc_product( $product ): bool {
        return $product && function_exists( 'ds_is_post_calculation_price' ) && ds_is_post_calculation_price( $product );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_product_regular_price_raw' ) ) {
    function ds_paynow_column_get_product_regular_price_raw( $product ): float {
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return 0.0;
        }

        $product_id    = $product->get_id();
        $regular_price = $product_id ? get_post_meta( $product_id, '_regular_price', true ) : '';

        if ( '' === $regular_price || null === $regular_price ) {
            if ( $product->is_type( 'variation' ) ) {
                $parent_product = wc_get_product( $product->get_parent_id() );

                if ( $parent_product ) {
                    $regular_price = get_post_meta( $parent_product->get_id(), '_regular_price', true );
                }
            }
        }

        if ( '' === $regular_price || null === $regular_price ) {
            $regular_price = $product_id ? get_post_meta( $product_id, '_price', true ) : '';
        }

        if ( '' === $regular_price || null === $regular_price ) {
            $regular_price = $product->get_regular_price( 'edit' );
        }

        if ( '' === $regular_price || null === $regular_price ) {
            $regular_price = $product->get_price( 'edit' );
        }

        return (float) wc_format_decimal( $regular_price );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_product_active_price_raw' ) ) {
    function ds_paynow_column_get_product_active_price_raw( $product ): float {
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return 0.0;
        }

        $price = $product->get_price( 'edit' );

        if ( '' === $price || null === $price ) {
            $price = $product->get_price();
        }

        return (float) wc_format_decimal( $price );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_cart_item_quantity' ) ) {
    function ds_paynow_column_get_cart_item_quantity( array $cart_item ): int {
        return max( 1, (int) ( $cart_item['quantity'] ?? 1 ) );
    }
}

if ( ! function_exists( 'ds_paynow_column_format_cart_price_html' ) ) {
    function ds_paynow_column_format_cart_price_html( $product, float $price, int $quantity = 1 ): string {
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return '';
        }

        $display_price = wc_get_price_to_display(
            $product,
            array(
                'price' => $price,
                'qty'   => $quantity,
            )
        );

        return wc_price( $display_price );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_cart_regular_price_html' ) ) {
    function ds_paynow_column_get_cart_regular_price_html( array $cart_item ): string {
        $product = $cart_item['data'] ?? null;

        if ( ds_paynow_column_is_postcalc_product( $product ) ) {
            return ds_paynow_column_get_postcalc_label();
        }

        return ds_paynow_column_format_cart_price_html(
            $product,
            ds_paynow_column_get_product_regular_price_raw( $product )
        );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_cart_pay_now_price_html' ) ) {
    function ds_paynow_column_get_cart_pay_now_price_html( array $cart_item ): string {
        $product = $cart_item['data'] ?? null;

        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return '';
        }

        if ( WC()->cart ) {
            return WC()->cart->get_product_price( $product );
        }

        return ds_paynow_column_format_cart_price_html(
            $product,
            ds_paynow_column_get_product_active_price_raw( $product )
        );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_cart_regular_subtotal_html' ) ) {
    function ds_paynow_column_get_cart_regular_subtotal_html( array $cart_item ): string {
        $product  = $cart_item['data'] ?? null;
        $quantity = ds_paynow_column_get_cart_item_quantity( $cart_item );

        if ( ds_paynow_column_is_postcalc_product( $product ) ) {
            return ds_paynow_column_get_postcalc_label();
        }

        return ds_paynow_column_format_cart_price_html(
            $product,
            ds_paynow_column_get_product_regular_price_raw( $product ),
            $quantity
        );
    }
}

if ( ! function_exists( 'ds_paynow_column_format_order_price_html' ) ) {
    function ds_paynow_column_format_order_price_html( $order, float $amount ): string {
        if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
            return wc_price( $amount );
        }

        return wc_price(
            $amount,
            array(
                'currency' => $order->get_currency(),
            )
        );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_order_item_regular_unit_price_raw' ) ) {
    function ds_paynow_column_get_order_item_regular_unit_price_raw( $item ): float {
        if ( ! $item || ! is_a( $item, 'WC_Order_Item_Product' ) ) {
            return 0.0;
        }

        $stored = $item->get_meta( '_ds_regular_unit_price', true );

        if ( '' !== $stored && null !== $stored ) {
            return (float) wc_format_decimal( $stored );
        }

        return ds_paynow_column_get_product_regular_price_raw( $item->get_product() );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_order_item_regular_subtotal_raw' ) ) {
    function ds_paynow_column_get_order_item_regular_subtotal_raw( $item ): float {
        if ( ! $item || ! is_a( $item, 'WC_Order_Item_Product' ) ) {
            return 0.0;
        }

        $stored = $item->get_meta( '_ds_regular_line_subtotal', true );

        if ( '' !== $stored && null !== $stored ) {
            return (float) wc_format_decimal( $stored );
        }

        return ds_paynow_column_get_order_item_regular_unit_price_raw( $item ) * max( 1, (int) $item->get_quantity() );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_order_item_pay_now_unit_raw' ) ) {
    function ds_paynow_column_get_order_item_pay_now_unit_raw( $item ): float {
        if ( ! $item || ! is_a( $item, 'WC_Order_Item_Product' ) ) {
            return 0.0;
        }

        $quantity = max( 1, (int) $item->get_quantity() );
        $subtotal = (float) $item->get_subtotal();

        if ( 0.0 === $subtotal ) {
            $subtotal = (float) $item->get_total();
        }

        return $subtotal / $quantity;
    }
}

if ( ! function_exists( 'ds_paynow_column_get_order_item_regular_price_html' ) ) {
    function ds_paynow_column_get_order_item_regular_price_html( $item, $order ): string {
        if ( ds_paynow_column_is_postcalc_product( $item->get_product() ) ) {
            return ds_paynow_column_get_postcalc_label();
        }

        return ds_paynow_column_format_order_price_html(
            $order,
            ds_paynow_column_get_order_item_regular_unit_price_raw( $item )
        );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_order_item_pay_now_price_html' ) ) {
    function ds_paynow_column_get_order_item_pay_now_price_html( $item, $order ): string {
        return ds_paynow_column_format_order_price_html(
            $order,
            ds_paynow_column_get_order_item_pay_now_unit_raw( $item )
        );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_order_item_regular_subtotal_html' ) ) {
    function ds_paynow_column_get_order_item_regular_subtotal_html( $item, $order ): string {
        return ds_paynow_column_format_order_price_html(
            $order,
            ds_paynow_column_get_order_item_regular_subtotal_raw( $item )
        );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_checkout_shipping_rows_html' ) ) {
    function ds_paynow_column_get_checkout_shipping_rows_html(): string {
        ob_start();
        wc_cart_totals_shipping_html();
        $shipping_rows = (string) ob_get_clean();

        return str_replace( '<th', '<th colspan="3"', $shipping_rows );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_cart_regular_subtotal_raw' ) ) {
    function ds_paynow_column_get_cart_regular_subtotal_raw(): float {
        if ( ! WC()->cart ) {
            return 0.0;
        }

        $subtotal = 0.0;

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $quantity = ds_paynow_column_get_cart_item_quantity( $cart_item );
            $product  = $cart_item['data'] ?? null;
            $subtotal += ds_paynow_column_get_product_regular_price_raw( $product ) * $quantity;
        }

        return (float) $subtotal;
    }
}

if ( ! function_exists( 'ds_paynow_column_get_cart_shipping_total_raw' ) ) {
    function ds_paynow_column_get_cart_shipping_total_raw(): float {
        if ( ! WC()->cart ) {
            return 0.0;
        }

        $shipping_total = (float) WC()->cart->get_shipping_total();

        if ( WC()->cart->display_prices_including_tax() ) {
            $shipping_total += (float) WC()->cart->get_shipping_tax();
        }

        return $shipping_total;
    }
}

if ( ! function_exists( 'ds_paynow_column_get_cart_regular_tax_totals' ) ) {
    function ds_paynow_column_get_cart_regular_tax_totals(): array {
        if ( ! WC()->cart || ! wc_tax_enabled() ) {
            return array();
        }

        $tax_rows           = array();
        $prices_include_tax = wc_prices_include_tax();

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product = $cart_item['data'] ?? null;

            if ( ! $product || ! is_a( $product, 'WC_Product' ) || ! $product->is_taxable() ) {
                continue;
            }

            $quantity    = ds_paynow_column_get_cart_item_quantity( $cart_item );
            $line_amount = ds_paynow_column_get_product_regular_price_raw( $product ) * $quantity;
            $tax_rates   = WC_Tax::get_rates( $product->get_tax_class() );
            $line_taxes  = WC_Tax::calc_tax( $line_amount, $tax_rates, $prices_include_tax );

            foreach ( $line_taxes as $rate_id => $amount ) {
                $rate_id = (int) $rate_id;

                if ( ! isset( $tax_rows[ $rate_id ] ) ) {
                    $tax_rows[ $rate_id ] = array(
                        'rate_id'          => $rate_id,
                        'label'            => WC_Tax::get_rate_label( $rate_id ),
                        'amount'           => 0.0,
                        'class_name'       => 'tax-rate tax-rate-' . sanitize_title( (string) $rate_id ),
                    );
                }

                $tax_rows[ $rate_id ]['amount'] += (float) $amount;
            }
        }

        foreach ( $tax_rows as $rate_id => $tax_row ) {
            $tax_rows[ $rate_id ]['formatted_amount'] = ds_paynow_column_format_cart_total_html( (float) $tax_row['amount'] );
        }

        return array_values( $tax_rows );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_cart_regular_tax_total_raw' ) ) {
    function ds_paynow_column_get_cart_regular_tax_total_raw(): float {
        $tax_total = 0.0;

        foreach ( ds_paynow_column_get_cart_regular_tax_totals() as $tax_row ) {
            $tax_total += (float) ( $tax_row['amount'] ?? 0.0 );
        }

        return $tax_total;
    }
}

if ( ! function_exists( 'ds_paynow_column_get_cart_regular_tax_total_html' ) ) {
    function ds_paynow_column_get_cart_regular_tax_total_html(): string {
        return ds_paynow_column_format_cart_total_html( ds_paynow_column_get_cart_regular_tax_total_raw() );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_cart_pay_now_total_raw' ) ) {
    function ds_paynow_column_get_cart_pay_now_total_raw(): float {
        if ( ! WC()->cart ) {
            return 0.0;
        }

        return (float) WC()->cart->get_total( 'edit' );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_cart_regular_total_raw' ) ) {
    function ds_paynow_column_get_cart_regular_total_raw(): float {
        if ( ! WC()->cart ) {
            return 0.0;
        }

        $regular_total = ds_paynow_column_get_cart_regular_subtotal_raw();
        $regular_total += ds_paynow_column_get_cart_shipping_total_raw();
        $regular_total += (float) WC()->cart->get_fee_total();
        $regular_total += ds_paynow_column_get_cart_regular_tax_total_raw();

        return (float) $regular_total;
    }
}

if ( ! function_exists( 'ds_paynow_column_get_cart_display_currency' ) ) {
    function ds_paynow_column_get_cart_display_currency(): string {
        return function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
    }
}

if ( ! function_exists( 'ds_paynow_column_format_cart_total_html' ) ) {
    function ds_paynow_column_format_cart_total_html( float $amount ): string {
        return wc_price(
            $amount,
            array(
                'currency' => ds_paynow_column_get_cart_display_currency(),
            )
        );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_cart_regular_subtotal_total_html' ) ) {
    function ds_paynow_column_get_cart_regular_subtotal_total_html(): string {
        return ds_paynow_column_format_cart_total_html( ds_paynow_column_get_cart_regular_subtotal_raw() );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_cart_regular_total_html' ) ) {
    function ds_paynow_column_get_cart_regular_total_html(): string {
        return ds_paynow_column_format_cart_total_html( ds_paynow_column_get_cart_regular_total_raw() );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_cart_pay_now_total_html' ) ) {
    function ds_paynow_column_get_cart_pay_now_total_html(): string {
        return ds_paynow_column_format_cart_total_html( ds_paynow_column_get_cart_pay_now_total_raw() );
    }
}

if ( ! function_exists( 'ds_paynow_column_get_cart_table_payload' ) ) {
    function ds_paynow_column_get_cart_table_payload(): array {
        if ( ! WC()->cart ) {
            return array();
        }

        $items = array();

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $items[ $cart_item_key ] = array(
                'regular_price_html' => ds_paynow_column_get_cart_regular_price_html( $cart_item ),
                'pay_now_price_html' => ds_paynow_column_get_cart_pay_now_price_html( $cart_item ),
                'subtotal_html'      => ds_paynow_column_get_cart_regular_subtotal_html( $cart_item ),
            );
        }

        return array(
            'regularLabel'             => ds_paynow_column_get_regular_price_label(),
            'payNowLabel'              => ds_paynow_column_get_pay_now_label(),
            'subtotalLabel'            => __( 'Subtotal', 'woocommerce' ),
            'regularSubtotalTotalHtml' => ds_paynow_column_get_cart_regular_subtotal_total_html(),
            'regularTaxRows'           => ds_paynow_column_get_cart_regular_tax_totals(),
            'regularTotalHtml'         => ds_paynow_column_get_cart_regular_total_html(),
            'payNowTotalHtml'          => ds_paynow_column_get_cart_pay_now_total_html(),
            'items'                    => $items,
        );
    }
}

add_action(
    'woocommerce_checkout_create_order_line_item',
    function ( $item, $cart_item_key, $values, $order ) {
        if ( ! $item || ! is_a( $item, 'WC_Order_Item_Product' ) ) {
            return;
        }

        $product = isset( $values['data'] ) && is_a( $values['data'], 'WC_Product' )
            ? $values['data']
            : $item->get_product();

        $quantity         = max( 1, (int) ( $values['quantity'] ?? $item->get_quantity() ) );
        $regular_unit     = ds_paynow_column_get_product_regular_price_raw( $product );
        $regular_subtotal = $regular_unit * $quantity;

        $item->add_meta_data( '_ds_regular_unit_price', wc_format_decimal( $regular_unit ), true );
        $item->add_meta_data( '_ds_regular_line_subtotal', wc_format_decimal( $regular_subtotal ), true );
    },
    10,
    4
);

add_filter(
    'woocommerce_hidden_order_itemmeta',
    function ( $hidden_meta ): array {
        $hidden_meta[] = '_ds_regular_unit_price';
        $hidden_meta[] = '_ds_regular_line_subtotal';

        return array_values( array_unique( $hidden_meta ) );
    }
);

add_filter(
    'woocommerce_locate_template',
    function ( $template, $template_name, $template_path ) {
        $supported_templates = array(
            'cart/cart.php',
            'cart/cart-totals.php',
            'checkout/review-order.php',
            'order/order-details.php',
            'order/order-details-item.php',
            'emails/email-order-details.php',
            'emails/email-order-items.php',
            'emails/plain/email-order-items.php',
        );

        if ( ! in_array( $template_name, $supported_templates, true ) ) {
            return $template;
        }

        $custom_template = ds_paynow_column_get_template_root() . $template_name;

        if ( is_readable( $custom_template ) ) {
            return $custom_template;
        }

        return $template;
    },
    20,
    3
);

add_action(
    'wp_enqueue_scripts',
    function () {
        if ( is_admin() || ! function_exists( 'is_cart' ) || ! is_cart() || ! WC()->cart ) {
            return;
        }

        $payload = wp_json_encode( ds_paynow_column_get_cart_table_payload() );

        if ( ! $payload ) {
            return;
        }

        $script = <<<JS
(function () {
    var data = {$payload};
    if (!data || !data.items) { return; }

    function getCartItemKey(row) {
        var qtyInput = row.querySelector('input[name^="cart["][name*="][qty]"]');
        if (!qtyInput) { return ''; }
        var match = qtyInput.name.match(/^cart\\[([^\\]]+)\\]\\[qty\\]$/);
        return match ? match[1] : '';
    }

    function ensureCell(row, selector, beforeSelector) {
        var cell = row.querySelector(selector);
        if (cell) { return cell; }

        cell = document.createElement('td');
        cell.className = selector.replace(/^[.#]/, '');

        var before = row.querySelector(beforeSelector);
        if (before && before.parentNode === row) {
            row.insertBefore(cell, before);
        } else {
            row.appendChild(cell);
        }

        return cell;
    }

    function ensureHeader(table, selector, text, beforeSelector) {
        var headRow = table.querySelector('thead tr');
        if (!headRow) { return; }

        var th = headRow.querySelector(selector);
        if (!th) {
            th = document.createElement('th');
            th.className = selector.replace(/^[.#]/, '');
            var before = headRow.querySelector(beforeSelector);
            if (before && before.parentNode === headRow) {
                headRow.insertBefore(th, before);
            } else {
                headRow.appendChild(th);
            }
        }

        th.textContent = text;
    }

    function applyToTable(table) {
        ensureHeader(table, '.product-regular-price', data.regularLabel, '.product-price');
        ensureHeader(table, '.product-price', data.payNowLabel, '.product-quantity, .product-subtotal');

        var rows = table.querySelectorAll('tbody tr');
        rows.forEach(function (row) {
            if (!row.querySelector('input[name^="cart["][name*="][qty]"]')) { return; }

            var key = getCartItemKey(row);
            if (!key || !data.items[key]) { return; }

            var item = data.items[key];
            var regularCell = ensureCell(row, '.product-regular-price', '.product-price');
            var payNowCell  = ensureCell(row, '.product-price', '.product-quantity, .product-subtotal');
            var subtotalCell = row.querySelector('.product-subtotal');

            if (regularCell) {
                regularCell.setAttribute('data-title', data.regularLabel);
                regularCell.innerHTML = item.regular_price_html;
            }

            if (payNowCell) {
                payNowCell.setAttribute('data-title', data.payNowLabel);
                payNowCell.innerHTML = item.pay_now_price_html;
            }

            if (subtotalCell) {
                subtotalCell.setAttribute('data-title', data.subtotalLabel);
                subtotalCell.innerHTML = item.subtotal_html;
            }
        });
    }

    function ensureTotalsRow(table, selector, label, html, afterSelector) {
        var row = table.querySelector(selector);
        if (!row) {
            row = document.createElement('tr');
            row.className = selector.replace(/^[.#]/, '') + ' cart-subtotal';

            var th = document.createElement('th');
            var td = document.createElement('td');
            row.appendChild(th);
            row.appendChild(td);

            var after = table.querySelector(afterSelector);
            if (after && after.parentNode) {
                after.parentNode.insertBefore(row, after.nextSibling);
            } else {
                var tbody = table.querySelector('tbody') || table;
                tbody.appendChild(row);
            }
        }

        var heading = row.querySelector('th') || row.children[0];
        var value = row.querySelector('td') || row.children[1];

        row.classList.add('cart-subtotal');

        if (heading) { heading.textContent = label; }
        if (value) {
            value.setAttribute('data-title', label);
            value.innerHTML = html;
        }
    }

    function syncTaxRows(table, rows) {
        table.querySelectorAll('.tax-rate, .tax-total').forEach(function (row) {
            row.remove();
        });

        if (!rows || !rows.length) { return; }

        var insertionPoint = table.querySelector('.order-total') || table.querySelector('.pay-now-total');
        rows.forEach(function (taxRow) {
            var row = document.createElement('tr');
            row.className = taxRow.class_name || 'tax-rate';

            var th = document.createElement('th');
            var td = document.createElement('td');

            th.textContent = taxRow.label || '';
            td.setAttribute('data-title', taxRow.label || '');
            td.innerHTML = taxRow.formatted_amount || '';

            row.appendChild(th);
            row.appendChild(td);

            if (insertionPoint && insertionPoint.parentNode) {
                insertionPoint.parentNode.insertBefore(row, insertionPoint);
            } else {
                var tbody = table.querySelector('tbody') || table;
                tbody.appendChild(row);
            }
        });
    }

    function applyToTotals(table) {
        var subtotalRow = table.querySelector('.cart-subtotal');
        var totalRow = table.querySelector('.order-total');

        if (subtotalRow) {
            var subtotalValue = subtotalRow.querySelector('td');
            if (subtotalValue) { subtotalValue.innerHTML = data.regularSubtotalTotalHtml; }
        }

        if (totalRow) {
            var totalValue = totalRow.querySelector('td');
            if (totalValue) { totalValue.innerHTML = data.regularTotalHtml; }
        }

        syncTaxRows(table, data.regularTaxRows || []);
        ensureTotalsRow(table, '.pay-now-total', data.payNowLabel, data.payNowTotalHtml, '.order-total');
    }

    function apply() {
        document.querySelectorAll('table.shop_table.cart, table.woocommerce-cart-form__contents').forEach(applyToTable);
        document.querySelectorAll('.cart_totals table.shop_table, .cart_totals table').forEach(applyToTotals);
    }

    document.addEventListener('DOMContentLoaded', apply);
    window.addEventListener('load', apply);

    if (window.jQuery) {
        window.jQuery(document.body).on('updated_cart_totals updated_wc_div', apply);
    }
})();
JS;

        wp_register_script( 'ds-paynow-cart-fallback', '', array(), null, true );
        wp_enqueue_script( 'ds-paynow-cart-fallback' );
        wp_add_inline_script( 'ds-paynow-cart-fallback', $script );
    }
);
