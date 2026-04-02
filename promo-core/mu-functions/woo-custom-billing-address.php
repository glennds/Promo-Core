<?php
defined('ABSPATH') || exit;

/**
 * Utkarsh's changes for PDF invoice. Can be removed probably...
 */



// function mpc_custom_billing_address_site_host(): string {
//     $host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
//     return is_string( $host ) ? strtolower( $host ) : '';
// }

// function mpc_custom_billing_address_enabled_hosts(): array {
//     $hosts = apply_filters( 'managepromo_custom_document_hosts', [
//         'elk.promotie.nl',
//         'elk.brandedshops.mijnnieuwe.site',
//     ] );

//     $hosts = array_filter( array_map( static function ( $host ): string {
//         return strtolower( trim( (string) $host ) );
//     }, (array) $hosts ) );

//     return array_values( array_unique( $hosts ) );
// }

// function mpc_custom_billing_address_is_elk_site(): bool {
//     return in_array( mpc_custom_billing_address_site_host(), mpc_custom_billing_address_enabled_hosts(), true );
// }

// function mpc_custom_billing_address_get_order_meta( WC_Order $order, string $meta_key ): string {
//     $candidates = [ $meta_key, '_' . ltrim( $meta_key, '_' ) ];

//     foreach ( array_unique( $candidates ) as $candidate ) {
//         $value = trim( (string) $order->get_meta( $candidate, true, 'edit' ) );
//         if ( $value !== '' ) {
//             return $value;
//         }
//     }

//     $user_id = (int) $order->get_user_id();
//     if ( $user_id > 0 ) {
//         foreach ( array_unique( $candidates ) as $candidate ) {
//             $value = trim( (string) get_user_meta( $user_id, ltrim( $candidate, '_' ), true ) );
//             if ( $value !== '' ) {
//                 return $value;
//             }
//         }
//     }

//     return '';
// }

// function mpc_custom_billing_address_get_reference( WC_Order $order ): string {
//     $meta_keys = [
//         'billing_kostenplaats_referentie',
//         'billing_kostenplaats_reference',
//         'billing_cost_reference',
//         'billing_reference',
//         'billing_referentie',
//         'billing_cost_center',
//         'billing_kostenplaats',
//     ];

//     foreach ( $meta_keys as $meta_key ) {
//         $value = mpc_custom_billing_address_get_order_meta( $order, $meta_key );
//         if ( $value !== '' ) {
//             return $value;
//         }
//     }

//     return '';
// }

// function mpc_custom_billing_address_get_customer_note( WC_Order $order ): string {
//     return trim( (string) $order->get_customer_note() );
// }

// function mpc_custom_documents_get_order( $order ): ?WC_Order {
//     if ( $order instanceof WC_Order ) {
//         return $order;
//     }

//     if ( is_numeric( $order ) ) {
//         $resolved = wc_get_order( (int) $order );
//         return $resolved instanceof WC_Order ? $resolved : null;
//     }

//     return null;
// }

// function mpc_custom_documents_get_address_lines( WC_Order $order, string $type ): array {
//     $is_shipping = $type === 'shipping';

//     $first_name = $is_shipping ? $order->get_shipping_first_name() : $order->get_billing_first_name();
//     $last_name  = $is_shipping ? $order->get_shipping_last_name() : $order->get_billing_last_name();
//     $company    = $is_shipping ? $order->get_shipping_company() : $order->get_billing_company();
//     $address_1  = $is_shipping ? $order->get_shipping_address_1() : $order->get_billing_address_1();
//     $address_2  = $is_shipping ? $order->get_shipping_address_2() : $order->get_billing_address_2();
//     $postcode   = $is_shipping ? $order->get_shipping_postcode() : $order->get_billing_postcode();
//     $city       = $is_shipping ? $order->get_shipping_city() : $order->get_billing_city();
//     $state      = $is_shipping ? $order->get_shipping_state() : $order->get_billing_state();
//     $country    = $is_shipping ? $order->get_shipping_country() : $order->get_billing_country();

//     $name_line = trim( $first_name . ' ' . $last_name );
//     $city_line = trim( implode( ' ', array_filter( [ $postcode, $city ] ) ) );

//     $country_label = $country !== '' && function_exists( 'WC' ) && WC()->countries
//         ? WC()->countries->countries[ $country ] ?? $country
//         : $country;

//     $state_label = '';
//     if ( $country !== '' && $state !== '' && function_exists( 'WC' ) && WC()->countries ) {
//         $states = WC()->countries->get_states( $country );
//         $state_label = $states[ $state ] ?? $state;
//     } else {
//         $state_label = $state;
//     }

//     $lines = [
//         $company,
//         $name_line,
//         $address_1,
//         $address_2,
//         $city_line,
//         $state_label,
//         $country_label,
//     ];

//     if ( ! $is_shipping ) {
//         $company_entity = mpc_custom_billing_address_get_order_meta( $order, 'billing_company_entity' );
//         $company_branch = mpc_custom_billing_address_get_order_meta( $order, 'billing_company_branch' );
//         $reference      = mpc_custom_billing_address_get_reference( $order );

//         if ( $company_entity !== '' ) {
//             array_unshift( $lines, $company_entity );
//         }

//         if ( $company_branch !== '' ) {
//             array_splice( $lines, 1, 0, [ $company_branch ] );
//         }

//         if ( $reference !== '' ) {
//             $lines[] = sprintf(
//                 '%s: %s',
//                 __( 'Kostenplaats / Referentie', 'managepromo-core' ),
//                 $reference
//             );
//         }
//     }

//     return array_values( array_filter( array_map( 'trim', array_map( 'strval', $lines ) ), 'strlen' ) );
// }

// function mpc_custom_documents_order_has_shipping_address( WC_Order $order ): bool {
//     $fields = [
//         $order->get_shipping_first_name(),
//         $order->get_shipping_last_name(),
//         $order->get_shipping_company(),
//         $order->get_shipping_address_1(),
//         $order->get_shipping_address_2(),
//         $order->get_shipping_postcode(),
//         $order->get_shipping_city(),
//         $order->get_shipping_state(),
//         $order->get_shipping_country(),
//     ];

//     foreach ( $fields as $value ) {
//         if ( trim( (string) $value ) !== '' ) {
//             return true;
//         }
//     }

//     return false;
// }

// function mpc_custom_documents_get_store_lines(): array {
//     $country_state = get_option( 'woocommerce_default_country', '' );
//     $country       = '';
//     $state         = '';

//     if ( is_string( $country_state ) && strpos( $country_state, ':' ) !== false ) {
//         [ $country, $state ] = array_pad( explode( ':', $country_state, 2 ), 2, '' );
//     } else {
//         $country = (string) $country_state;
//     }

//     $country_label = $country !== '' && function_exists( 'WC' ) && WC()->countries
//         ? WC()->countries->countries[ $country ] ?? $country
//         : $country;

//     $state_label = '';
//     if ( $country !== '' && $state !== '' && function_exists( 'WC' ) && WC()->countries ) {
//         $states = WC()->countries->get_states( $country );
//         $state_label = $states[ $state ] ?? $state;
//     } else {
//         $state_label = $state;
//     }

//     $city_line = trim(
//         implode(
//             ' ',
//             array_filter(
//                 [
//                     get_option( 'woocommerce_store_postcode', '' ),
//                     get_option( 'woocommerce_store_city', '' ),
//                 ]
//             )
//         )
//     );

//     return array_values( array_filter( array_map( 'trim', [
//         get_bloginfo( 'name' ),
//         get_option( 'woocommerce_store_address', '' ),
//         get_option( 'woocommerce_store_address_2', '' ),
//         $city_line,
//         $state_label,
//         $country_label,
//     ] ), 'strlen' ) );
// }

// function mpc_custom_documents_get_invoice_view_url( WC_Order $order ): string {
//     return add_query_arg(
//         [
//             'managepromo_invoice' => 1,
//             'order_id'            => $order->get_id(),
//             'key'                 => $order->get_order_key(),
//         ],
//         home_url( '/' )
//     );
// }

// function mpc_custom_documents_user_can_view_invoice( WC_Order $order ): bool {
//     if ( current_user_can( 'edit_shop_orders' ) ) {
//         return true;
//     }

//     $provided_key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
//     if ( $provided_key === '' || ! hash_equals( (string) $order->get_order_key(), $provided_key ) ) {
//         return false;
//     }

//     $order_user_id = (int) $order->get_user_id();
//     if ( $order_user_id > 0 && is_user_logged_in() ) {
//         return $order_user_id === (int) get_current_user_id();
//     }

//     return true;
// }

// function mpc_custom_documents_render_invoice_page(): void {
//     if ( ! isset( $_GET['managepromo_invoice'] ) ) {
//         return;
//     }

//     $order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
//     $order    = wc_get_order( $order_id );

//     if ( ! $order instanceof WC_Order || ! mpc_custom_documents_user_can_view_invoice( $order ) ) {
//         wp_die( esc_html__( 'You are not allowed to view this invoice.', 'managepromo-core' ), '', [ 'response' => 403 ] );
//     }

//     nocache_headers();
//     status_header( 200 );
//     header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );

//     $template = dirname( __DIR__ ) . '/templates/managepromo/invoice.php';
//     if ( is_readable( $template ) ) {
//         $invoice_order = $order;
//         include $template;
//         exit;
//     }

//     wp_die( esc_html__( 'Invoice template not found.', 'managepromo-core' ) );
// }

// function mpc_custom_documents_add_my_account_invoice_action( array $actions, WC_Order $order ): array {
//     if ( ! mpc_custom_billing_address_is_elk_site() || isset( $actions['invoice'] ) ) {
//         return $actions;
//     }

//     $actions['invoice'] = [
//         'url'  => mpc_custom_documents_get_invoice_view_url( $order ),
//         'name' => __( 'View invoice', 'managepromo-core' ),
//     ];

//     return $actions;
// }

// function mpc_custom_documents_add_admin_order_list_action( array $actions, WC_Order $order ): array {
//     if ( ! mpc_custom_billing_address_is_elk_site() ) {
//         return $actions;
//     }

//     $actions['managepromo_view_invoice'] = [
//         'url'    => mpc_custom_documents_get_invoice_view_url( $order ),
//         'name'   => __( 'View invoice', 'managepromo-core' ),
//         'action' => 'managepromo-view-invoice',
//     ];

//     return $actions;
// }

// function mpc_custom_documents_register_order_action( array $actions ): array {
//     if ( ! mpc_custom_billing_address_is_elk_site() ) {
//         return $actions;
//     }

//     $actions['managepromo_send_invoice_email'] = __( 'Send invoice email', 'managepromo-core' );

//     return $actions;
// }

// function mpc_custom_documents_get_invoice_email_subject( WC_Order $order ): string {
//     return sprintf(
//         __( 'Invoice for order %s', 'managepromo-core' ),
//         $order->get_order_number()
//     );
// }

// function mpc_custom_documents_get_invoice_email_body( WC_Order $order ): string {
//     $customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
//     if ( $customer_name === '' ) {
//         $customer_name = __( 'customer', 'managepromo-core' );
//     }

//     $invoice_url = esc_url( mpc_custom_documents_get_invoice_view_url( $order ) );

//     return wpautop(
//         sprintf(
//             /* translators: 1: customer name, 2: order number, 3: invoice url */
//             __(
//                 'Hello %1$s,%2$sYou can view your invoice for order %2$s#%3$s here:%2$s%4$s',
//                 'managepromo-core'
//             ),
//             esc_html( $customer_name ),
//             "\n\n",
//             esc_html( $order->get_order_number() ),
//             $invoice_url
//         )
//     );
// }

// function mpc_custom_documents_trigger_invoice_email( WC_Order $order ): bool {
//     $recipient = $order->get_billing_email();
//     if ( ! is_email( $recipient ) ) {
//         return false;
//     }

//     $sent = wp_mail(
//         $recipient,
//         mpc_custom_documents_get_invoice_email_subject( $order ),
//         mpc_custom_documents_get_invoice_email_body( $order ),
//         [ 'Content-Type: text/html; charset=UTF-8' ]
//     );

//     if ( $sent ) {
//         $order->add_order_note( __( 'Invoice email sent manually.', 'managepromo-core' ) );
//     }

//     return (bool) $sent;
// }

// function mpc_custom_documents_handle_order_action_send_invoice( WC_Order $order ): void {
//     mpc_custom_documents_trigger_invoice_email( $order );
// }

// function mpc_custom_documents_add_invoice_meta_box(): void {
//     if ( ! mpc_custom_billing_address_is_elk_site() ) {
//         return;
//     }

//     $screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

//     add_meta_box(
//         'managepromo-invoice-actions',
//         __( 'Invoice', 'managepromo-core' ),
//         'mpc_custom_documents_render_invoice_meta_box',
//         $screen,
//         'side',
//         'default'
//     );
// }

// function mpc_custom_documents_render_invoice_meta_box( $post_or_order ): void {
//     $order = null;

//     if ( $post_or_order instanceof WP_Post ) {
//         $order = wc_get_order( $post_or_order->ID );
//     } elseif ( $post_or_order instanceof WC_Order ) {
//         $order = $post_or_order;
//     } elseif ( is_object( $post_or_order ) && method_exists( $post_or_order, 'get_id' ) ) {
//         $order = wc_get_order( $post_or_order->get_id() );
//     }

//     if ( ! $order instanceof WC_Order ) {
//         echo '<p>' . esc_html__( 'Invoice actions are unavailable for this order.', 'managepromo-core' ) . '</p>';
//         return;
//     }

//     $view_url = mpc_custom_documents_get_invoice_view_url( $order );
//     $send_url = wp_nonce_url(
//         add_query_arg(
//             [
//                 'action'   => 'mpc_send_invoice_email',
//                 'order_id' => $order->get_id(),
//             ],
//             admin_url( 'admin-post.php' )
//         ),
//         'mpc_send_invoice_email_' . $order->get_id()
//     );

//     echo '<p><a class="button button-primary" href="' . esc_url( $view_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View invoice', 'managepromo-core' ) . '</a></p>';
//     echo '<p><a class="button" href="' . esc_url( $send_url ) . '">' . esc_html__( 'Send invoice email', 'managepromo-core' ) . '</a></p>';
//     echo '<p class="description">' . esc_html__( 'This uses the built-in ManagePromo invoice page and sends the customer a direct invoice link.', 'managepromo-core' ) . '</p>';
// }

// function mpc_custom_documents_admin_post_send_invoice_email(): void {
//     if ( ! current_user_can( 'edit_shop_orders' ) ) {
//         wp_die( esc_html__( 'You do not have permission to send invoices.', 'managepromo-core' ) );
//     }

//     $order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
//     if ( $order_id < 1 ) {
//         wp_die( esc_html__( 'Missing order ID.', 'managepromo-core' ) );
//     }

//     check_admin_referer( 'mpc_send_invoice_email_' . $order_id );

//     $order = wc_get_order( $order_id );
//     if ( ! $order instanceof WC_Order ) {
//         wp_die( esc_html__( 'Invalid order.', 'managepromo-core' ) );
//     }

//     $sent = mpc_custom_documents_trigger_invoice_email( $order );

//     $redirect_url = wp_get_referer();
//     if ( ! $redirect_url ) {
//         $redirect_url = admin_url( 'post.php?post=' . $order_id . '&action=edit' );
//     }

//     wp_safe_redirect( add_query_arg( 'mpc_invoice_email_sent', $sent ? '1' : '0', $redirect_url ) );
//     exit;
// }

// function mpc_custom_documents_render_admin_notice(): void {
//     if ( ! isset( $_GET['mpc_invoice_email_sent'] ) ) {
//         return;
//     }

//     $sent    = wp_unslash( $_GET['mpc_invoice_email_sent'] ) === '1';
//     $class   = $sent ? 'notice notice-success' : 'notice notice-error';
//     $message = $sent
//         ? __( 'Invoice email sent.', 'managepromo-core' )
//         : __( 'Invoice email could not be sent.', 'managepromo-core' );

//     echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
// }

// if ( mpc_custom_billing_address_is_elk_site() ) {
//     add_action( 'template_redirect', 'mpc_custom_documents_render_invoice_page', 1 );
//     add_filter( 'woocommerce_my_account_my_orders_actions', 'mpc_custom_documents_add_my_account_invoice_action', 10, 2 );
//     add_filter( 'woocommerce_admin_order_actions', 'mpc_custom_documents_add_admin_order_list_action', 10, 2 );
//     add_filter( 'woocommerce_order_actions', 'mpc_custom_documents_register_order_action' );
//     add_action( 'woocommerce_order_action_managepromo_send_invoice_email', 'mpc_custom_documents_handle_order_action_send_invoice' );
//     add_action( 'add_meta_boxes', 'mpc_custom_documents_add_invoice_meta_box' );
//     add_action( 'admin_post_mpc_send_invoice_email', 'mpc_custom_documents_admin_post_send_invoice_email' );
//     add_action( 'admin_notices', 'mpc_custom_documents_render_admin_notice' );
// }
