<?php
/**
 * Cart totals
 *
 * @package WooCommerce\Templates
 * @version 7.9.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="cart_totals <?php echo WC()->customer->has_calculated_shipping() ? 'calculated_shipping' : ''; ?>">

    <?php do_action( 'woocommerce_before_cart_totals' ); ?>

    <h2><?php esc_html_e( 'Cart totals', 'woocommerce' ); ?></h2>

    <table cellspacing="0" class="shop_table shop_table_responsive">

        <tr class="cart-subtotal">
            <th><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
            <td data-title="<?php esc_attr_e( 'Subtotal', 'woocommerce' ); ?>"><?php echo wp_kses_post( ds_paynow_column_get_cart_regular_subtotal_total_html() ); ?></td>
        </tr>

        <?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
            <tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
                <th><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
                <td data-title="<?php echo esc_attr( wc_cart_totals_coupon_label( $coupon, false ) ); ?>"><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
            </tr>
        <?php endforeach; ?>

        <?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
            <?php do_action( 'woocommerce_cart_totals_before_shipping' ); ?>
            <?php wc_cart_totals_shipping_html(); ?>
            <?php do_action( 'woocommerce_cart_totals_after_shipping' ); ?>
        <?php elseif ( WC()->cart->needs_shipping() && 'yes' === get_option( 'woocommerce_enable_shipping_calc' ) ) : ?>
            <tr class="shipping">
                <th><?php esc_html_e( 'Shipping', 'woocommerce' ); ?></th>
                <td data-title="<?php esc_attr_e( 'Shipping', 'woocommerce' ); ?>"><?php woocommerce_shipping_calculator(); ?></td>
            </tr>
        <?php endif; ?>

        <?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
            <tr class="fee">
                <th><?php echo esc_html( $fee->name ); ?></th>
                <td data-title="<?php echo esc_attr( $fee->name ); ?>"><?php wc_cart_totals_fee_html( $fee ); ?></td>
            </tr>
        <?php endforeach; ?>

        <?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
            <?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
                <?php foreach ( ds_paynow_column_get_cart_regular_tax_totals() as $tax ) : ?>
                    <tr class="<?php echo esc_attr( $tax['class_name'] ); ?>">
                        <th><?php echo esc_html( $tax['label'] ); ?></th>
                        <td data-title="<?php echo esc_attr( $tax['label'] ); ?>"><?php echo wp_kses_post( $tax['formatted_amount'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr class="tax-total">
                    <th><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></th>
                    <td data-title="<?php echo esc_attr( WC()->countries->tax_or_vat() ); ?>"><?php echo wp_kses_post( ds_paynow_column_get_cart_regular_tax_total_html() ); ?></td>
                </tr>
            <?php endif; ?>
        <?php endif; ?>

        <?php do_action( 'woocommerce_cart_totals_before_order_total' ); ?>

        <tr class="order-total">
            <th><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
            <td data-title="<?php esc_attr_e( 'Total', 'woocommerce' ); ?>"><?php echo wp_kses_post( ds_paynow_column_get_cart_regular_total_html() ); ?></td>
        </tr>

        <tr class="pay-now-total cart-subtotal">
            <th><?php echo esc_html( ds_paynow_column_get_pay_now_label() ); ?></th>
            <td data-title="<?php echo esc_attr( ds_paynow_column_get_pay_now_label() ); ?>"><?php echo wp_kses_post( ds_paynow_column_get_cart_pay_now_total_html() ); ?></td>
        </tr>

        <?php do_action( 'woocommerce_cart_totals_after_order_total' ); ?>

    </table>

    <div class="wc-proceed-to-checkout">
        <?php do_action( 'woocommerce_proceed_to_checkout' ); ?>
    </div>

    <?php do_action( 'woocommerce_after_cart_totals' ); ?>

</div>
