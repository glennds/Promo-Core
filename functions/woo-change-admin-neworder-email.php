<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}
if (!function_exists('keuzeconcept_is_enabled') || !keuzeconcept_is_enabled('woo_change_neworder_email')) {return;}

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////


// Helper: is this the HTML admin "New Order" email?
function kc_is_admin_new_order_html( $sent_to_admin, $plain_text, $email ) : bool {
    return (
        $sent_to_admin
        && ! $plain_text
        && ( $email instanceof WC_Email )
        && $email->id === 'new_order'
    );
}



// 1. Before Woo prints default table, unhook ONLY for admin new_order and schedule a restore at end of the same hook run
add_action( 'woocommerce_email_order_details', function( $order, $sent_to_admin, $plain_text, $email ) {
    if ( kc_is_admin_new_order_html( $sent_to_admin, $plain_text, $email ) ) {
        // Remove default table for THIS email render.
        remove_action( 'woocommerce_email_order_details', array( WC()->mailer(), 'order_details' ), 10 );

        // Ensure it's restored for any subsequent emails built in this request.
        add_action( 'woocommerce_email_order_details', 'kc_restore_default_email_table', 1000, 4 );
    }
}, 1, 4 );



// 2. Our custom table for admin new_order (HTML only).
add_action( 'woocommerce_email_order_details', function( $order, $sent_to_admin, $plain_text, $email ) {
    if ( ! kc_is_admin_new_order_html( $sent_to_admin, $plain_text, $email ) ) {
        return;
    }

    $items    = $order->get_items();
    $currency = $order->get_currency();

    echo '<h2>' . esc_html__( 'Besteloverzicht', 'woocommerce' ) . '</h2>';

    ?>
    <table class="td" cellspacing="0" cellpadding="6" style="width:100%; margin-bottom:40px">
        <thead>
            <tr style="background:#e3ddd6;">
                <th style="text-align:left"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
                <th style="text-align:center"><?php esc_html_e( 'Aantal', 'woocommerce' ); ?></th>
                <th style="text-align:right"><?php esc_html_e( 'Verkoopprijs', 'woocommerce' ); ?></th>
                <th style="text-align:right"><?php esc_html_e( 'Prijs', 'woocommerce' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php
        foreach ( $items as $item ) {
            $product = $item->get_product();
            if ( ! $product ) { continue; }

            $qty               = (int) $item->get_quantity();
            $line_total        = (float) $item->get_total(); // line total (excl. tax)
            $unit_actual_price = $qty > 0 ? $line_total / $qty : 0.0;

            $regular_price = (float) $product->get_regular_price(); // Regular price can be string; cast to float for math

            echo '<tr>';
            echo '<td style="border-bottom:1px solid #e3ddd666; font-size:14px; text-align:left;">' . esc_html( $item->get_name() ) . '</td>';
            echo '<td style="border-bottom:1px solid #e3ddd666; font-size:14px; text-align:center;">' . esc_html( $qty ) . '</td>';
            echo '<td style="border-bottom:1px solid #e3ddd666; font-size:14px; text-align:right;">' . wc_price( $regular_price, array( 'currency' => $currency ) ) . '</td>';
            echo '<td style="border-bottom:1px solid #e3ddd666; font-size:14px; text-align:right;">' . wc_price( $unit_actual_price, array( 'currency' => $currency ) ) . '</td>';
            echo '</tr>';
        }
        ?>
        </tbody>
        <?php
        $regular_price_subtotal = 0.0;
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( ! $product ) { continue; }
            $regular_price_subtotal += (float) $product->get_regular_price() * (int) $item->get_quantity();
        }
        ?>
        <tfoot>
            <tr>
                <td colspan="2" style="padding-top:40px; text-align:left;"><?php esc_html_e( 'Subtotaal:', 'woocommerce' ); ?></td>
                <td style="padding-top:40px; text-align:right;"><?php echo wc_price( $regular_price_subtotal, array( 'currency' => $currency ) ); ?></td>
                <td style="padding-top:40px; text-align:right;"><?php echo wc_price( (float) $order->get_subtotal(), array( 'currency' => $currency ) ); ?></td>
            </tr>
            <tr>
                <th colspan="2" style="text-align:left;"><?php esc_html_e( 'Totaal:', 'woocommerce' ); ?></th>
                <td style="text-align:right;">-</td>
                <td style="text-align:right;"><?php echo wc_price( (float) $order->get_total(), array( 'currency' => $currency ) ); ?></td>
            </tr>
        </tfoot>
    </table>
    <?php
}, 5, 4 );



// 3. Restore Woo’s default table for any other emails in the same request.
function kc_restore_default_email_table( $order, $sent_to_admin, $plain_text, $email ) {
    add_action( 'woocommerce_email_order_details', array( WC()->mailer(), 'order_details' ), 10, 4 );
    remove_action( 'woocommerce_email_order_details', 'kc_restore_default_email_table', 1000 ); // Remove restore hook so it doesn't keep firing.
}
