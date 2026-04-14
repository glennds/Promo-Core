<?php
/**
 * Order details table shown in emails.
 *
 * @package WooCommerce\Templates\Emails
 * @version 10.1.0
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

$email_improvements_enabled = FeaturesUtil::feature_is_enabled( 'email_improvements' );

if ( $email_improvements_enabled ) {
    add_filter( 'woocommerce_order_shipping_to_display_shipped_via', '__return_false' );
}

do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email );
?>
<h2 class="<?php echo esc_attr( $email_improvements_enabled ? 'email-order-detail-heading' : '' ); ?>">
    <?php
    if ( $email_improvements_enabled ) {
        echo wp_kses_post( __( 'Order summary', 'woocommerce' ) );
    }

    if ( $sent_to_admin ) {
        $before = '<a class="link" href="' . esc_url( $order->get_edit_order_url() ) . '">';
        $after  = '</a>';
    } else {
        $before = '';
        $after  = '';
    }

    if ( $email_improvements_enabled ) {
        echo '<br><span>';
    }

    $order_number_string = $email_improvements_enabled ? __( 'Order #%s', 'woocommerce' ) : __( '[Order #%s]', 'woocommerce' );

    echo wp_kses_post(
        $before . sprintf(
            $order_number_string . $after . ' (<time datetime="%s">%s</time>)',
            $order->get_order_number(),
            $order->get_date_created()->format( 'c' ),
            wc_format_datetime( $order->get_date_created() )
        )
    );

    if ( $email_improvements_enabled ) {
        echo '</span>';
    }
    ?>
</h2>

<div style="margin-bottom: <?php echo $email_improvements_enabled ? '24px' : '40px'; ?>;">
    <table class="td font-family <?php echo esc_attr( $email_improvements_enabled ? 'email-order-details' : '' ); ?>" cellspacing="0" cellpadding="6" style="width: 100%;" border="1">
        <thead>
            <tr>
                <th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
                <th class="td" scope="col" style="text-align:right;"><?php esc_html_e( 'Quantity', 'woocommerce' ); ?></th>
                <th class="td" scope="col" style="text-align:right;"><?php echo esc_html( ds_paynow_column_get_regular_price_label() ); ?></th>
                <th class="td" scope="col" style="text-align:right;"><?php echo esc_html( ds_paynow_column_get_pay_now_label() ); ?></th>
                <th class="td" scope="col" style="text-align:right;"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $image_size = $email_improvements_enabled ? 48 : 32;

            echo wc_get_email_order_items(
                $order,
                array(
                    'show_sku'      => $sent_to_admin,
                    'show_image'    => $email_improvements_enabled,
                    'image_size'    => array( $image_size, $image_size ),
                    'plain_text'    => $plain_text,
                    'sent_to_admin' => $sent_to_admin,
                )
            );
            ?>
        </tbody>
    </table>
</div>

<?php do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email ); ?>
