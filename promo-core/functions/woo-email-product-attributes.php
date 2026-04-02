<?php
defined('ABSPATH') || exit;

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////



if ( ! class_exists( 'WCEPA_Email_Product_Attributes' ) ) {
    class WCEPA_Email_Product_Attributes {
        public function __construct() {
            add_filter( 'woocommerce_email_order_items_args', [ $this, 'enable_item_meta_in_emails' ] );
            add_filter( 'woocommerce_email_order_item_meta', [ $this, 'append_attributes_to_email' ], 10, 3 );
        }

        /**
         * Force item meta (attributes) to be included when WooCommerce renders email items.
         */
        public function enable_item_meta_in_emails( $args ) {
            $args['show_meta'] = true;
            return $args;
        }

        /**
         * Append a consolidated attributes string under each line item in order emails.
         */
        public function append_attributes_to_email( $html, $item, $args ) {
            $attributes = $this->get_item_attributes( $item );

            if ( $attributes === '' ) {
                return $html;
            }

            if ( ! empty( $args['plain_text'] ) ) {
                $html .= "\n" . __( 'Attributes', 'wc-email-product-attributes' ) . ': ' . $attributes . "\n";
            } else {
                $html .= sprintf(
                    '<div class="wcepa-item-attributes"><strong>%s</strong> %s</div>',
                    esc_html__( 'Attributes:', 'wc-email-product-attributes' ),
                    esc_html( $attributes )
                );
            }

            return $html;
        }

        /**
         * Build a readable list of attributes/variation details for an order item.
         */
        private function get_item_attributes( $item ) {
            if ( ! $item || ! is_object( $item ) || ! method_exists( $item, 'get_formatted_meta_data' ) ) {
                return '';
            }

            $meta_data = $item->get_formatted_meta_data( '', true );

            if ( empty( $meta_data ) ) {
                return '';
            }

            $attributes = [];

            foreach ( $meta_data as $meta ) {
                $key = isset( $meta->key ) ? (string) $meta->key : '';

                // Skip hidden/system meta keys that start with an underscore.
                if ( $key !== '' && substr( $key, 0, 1 ) === '_' ) {
                    continue;
                }

                $label = isset( $meta->display_key ) ? wp_strip_all_tags( $meta->display_key ) : '';
                $value = isset( $meta->display_value ) ? wp_strip_all_tags( $meta->display_value ) : '';

                if ( $label === '' && $value === '' ) {
                    continue;
                }

                $attributes[] = $label !== '' ? ( $label . ': ' . $value ) : $value;
            }

            return implode( ', ', $attributes );
        }
    }

    new WCEPA_Email_Product_Attributes();
}
