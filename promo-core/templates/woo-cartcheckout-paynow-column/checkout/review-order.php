<?php
/**
 * Review order table
 *
 * @package WooCommerce\Templates
 * @version 5.2.0
 */

defined( 'ABSPATH' ) || exit;
?>
<table class="shop_table woocommerce-checkout-review-order-table">
    <thead>
        <tr>
            <th class="product-name"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
            <th class="product-regular-price"><?php echo esc_html( ds_paynow_column_get_regular_price_label() ); ?></th>
            <th class="product-pay-now"><?php echo esc_html( ds_paynow_column_get_pay_now_label() ); ?></th>
            <th class="product-total"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        do_action( 'woocommerce_review_order_before_cart_contents' );

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

            if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 || ! apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
                continue;
            }
            ?>
            <tr class="<?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
                <td class="product-name">
                    <?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) ) . '&nbsp;'; ?>
                    <?php echo apply_filters( 'woocommerce_checkout_cart_item_quantity', ' <strong class="product-quantity">' . sprintf( '&times;&nbsp;%s', $cart_item['quantity'] ) . '</strong>', $cart_item, $cart_item_key ); ?>
                    <?php echo wc_get_formatted_cart_item_data( $cart_item ); ?>
                </td>
                <td class="product-regular-price">
                    <?php echo wp_kses_post( ds_paynow_column_get_cart_regular_price_html( $cart_item ) ); ?>
                </td>
                <td class="product-pay-now">
                    <?php echo wp_kses_post( ds_paynow_column_get_cart_pay_now_price_html( $cart_item ) ); ?>
                </td>
                <td class="product-total">
                    <?php echo wp_kses_post( ds_paynow_column_get_cart_regular_subtotal_html( $cart_item ) ); ?>
                </td>
            </tr>
            <?php
        }

        do_action( 'woocommerce_review_order_after_cart_contents' );
        ?>
    </tbody>
    <tfoot>
        <tr class="cart-subtotal">
            <th colspan="3"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
            <td><?php echo wp_kses_post( ds_paynow_column_get_cart_regular_subtotal_total_html() ); ?></td>
        </tr>

        <?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
            <tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
                <th colspan="3"><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
                <td><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
            </tr>
        <?php endforeach; ?>

        <?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
            <?php do_action( 'woocommerce_review_order_before_shipping' ); ?>
            <?php echo wp_kses_post( ds_paynow_column_get_checkout_shipping_rows_html() ); ?>
            <?php do_action( 'woocommerce_review_order_after_shipping' ); ?>
        <?php endif; ?>

        <?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
            <tr class="fee">
                <th colspan="3"><?php echo esc_html( $fee->name ); ?></th>
                <td><?php wc_cart_totals_fee_html( $fee ); ?></td>
            </tr>
        <?php endforeach; ?>

        <?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
            <?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
                <?php foreach ( ds_paynow_column_get_cart_regular_tax_totals() as $tax ) : ?>
                    <tr class="<?php echo esc_attr( $tax['class_name'] ); ?>">
                        <th colspan="3"><?php echo esc_html( $tax['label'] ); ?></th>
                        <td data-title="<?php echo esc_attr( $tax['label'] ); ?>"><?php echo wp_kses_post( $tax['formatted_amount'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr class="tax-total">
                    <th colspan="3"><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></th>
                    <td data-title="<?php echo esc_attr( WC()->countries->tax_or_vat() ); ?>"><?php echo wp_kses_post( ds_paynow_column_get_cart_regular_tax_total_html() ); ?></td>
                </tr>
            <?php endif; ?>
        <?php endif; ?>

        <?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

        <tr class="order-total">
            <th colspan="3"><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
            <td data-title="<?php esc_attr_e( 'Total', 'woocommerce' ); ?>"><?php echo wp_kses_post( ds_paynow_column_get_cart_regular_total_html() ); ?></td>
        </tr>

        <tr class="pay-now-total cart-subtotal">
            <th colspan="3"><?php echo esc_html( ds_paynow_column_get_pay_now_label() ); ?></th>
            <td data-title="<?php echo esc_attr( ds_paynow_column_get_pay_now_label() ); ?>"><?php echo wp_kses_post( ds_paynow_column_get_cart_pay_now_total_html() ); ?></td>
        </tr>

        <?php do_action( 'woocommerce_review_order_after_order_total' ); ?>
    </tfoot>
</table>
