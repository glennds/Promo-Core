<?php
defined('ABSPATH') || exit;

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////



if ( class_exists( 'DS_IsVariable_Flag' ) ) { return; }

if ( ! class_exists( 'MPC_DS_IsVariable_Flag' ) ) {
    class MPC_DS_IsVariable_Flag {
        const META_KEY   = '_ds_isvariable';
        const META_VALUE = 'true';

        public static function init(): void {
            if ( class_exists( 'DS_IsVariable_Flag' ) ) { return; }
            add_action( 'save_post_product', [ __CLASS__, 'on_save_product' ], 20, 3 );
            add_action( 'woocommerce_product_options_general_product_data', [ __CLASS__, 'render_admin_field' ] );
        }

        private static function woo_available(): bool {
            return function_exists( 'wc_get_product' ) && class_exists( 'WC_Product' );
        }

        public static function on_save_product( int $post_id, \WP_Post $post, bool $update ): void {
            if ( ! self::woo_available() ) { return; }
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
            if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }
            if ( $post->post_status === 'trash' ) { return; }

            $product = wc_get_product( $post_id );
            if ( ! $product ) { return; }

            $is_variable = $product->is_type( 'variable' );

            if ( $is_variable ) {
                update_post_meta( $post_id, self::META_KEY, self::META_VALUE );
            } else {
                delete_post_meta( $post_id, self::META_KEY );
            }
        }

        public static function render_admin_field(): void {
            global $post;

            if ( ! self::woo_available() || ! $post || $post->post_type !== 'product' ) { return; }

            $value   = get_post_meta( $post->ID, self::META_KEY, true );
            $checked = ( $value === self::META_VALUE ) ? 'yes' : 'no';

            echo '<div class="options_group">';

            woocommerce_wp_checkbox( [
                'id'                => self::META_KEY,
                'label'             => __( 'DS Is Variable', 'ds' ),
                'description'       => __( 'Auto-detected. Checked means this product is a Variable product and meta _ds_isvariable=true is set.', 'ds' ),
                'desc_tip'          => true,
                'value'             => $checked,
                'custom_attributes' => [
                    'disabled' => 'disabled',
                ],
            ] );

            echo '<input type="hidden" name="' . esc_attr( self::META_KEY ) . '" value="' . esc_attr( $checked ) . '" />';

            echo '</div>';
        }
    }

    add_action( 'plugins_loaded', [ 'MPC_DS_IsVariable_Flag', 'init' ] );
}
