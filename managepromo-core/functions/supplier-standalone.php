<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'managepromo_is_enabled' ) || ! managepromo_is_enabled( 'supplier_taxonomy_standalone' ) ) { return; }

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////


if ( ! class_exists( 'Supplier_Standalone' ) ) {
    final class Supplier_Standalone {

        const TAXONOMY   = 'supplier';
        const META_EMAIL = 'mcisoe_supplier_email';
        const META_CUSTOM_TEXT = 'mcisoe_supplier_custom_text';
        const META_DATA_TEXT   = 'mcisoe_supplier_data_text';

        private static $instance = null;

        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        private function __construct() {
            // Ensure the taxonomy is available on products (same slug/labels as the legacy plugin).
            add_action( 'init', [ $this, 'register_taxonomy' ], 11 );

            // Admin fields for supplier meta.
            add_action( self::TAXONOMY . '_add_form_fields', [ $this, 'render_add_fields' ] );
            add_action( self::TAXONOMY . '_edit_form_fields', [ $this, 'render_edit_fields' ] );
            add_action( 'created_' . self::TAXONOMY, [ $this, 'save_meta_fields' ] );
            add_action( 'edited_' . self::TAXONOMY, [ $this, 'save_meta_fields' ] );
            add_action( 'admin_enqueue_scripts', [ $this, 'hide_default_taxonomy_fields' ] );

            // Columns on the taxonomy list table.
            add_filter( 'manage_edit-' . self::TAXONOMY . '_columns', [ $this, 'register_columns' ] );
            add_filter( 'manage_' . self::TAXONOMY . '_custom_column', [ $this, 'render_column' ], 10, 3 );

            // Product list filtering by supplier in wp-admin.
            add_action( 'restrict_manage_posts', [ $this, 'add_supplier_filter_dropdown' ] );
            add_filter( 'parse_query', [ $this, 'apply_supplier_filter_query' ] );
        }

        public function register_taxonomy() {
            $labels = [
                'name'                       => __( 'Suppliers', 'supplier-standalone' ),
                'singular_name'              => __( 'Supplier', 'supplier-standalone' ),
                'menu_name'                  => __( 'Suppliers', 'supplier-standalone' ),
                'all_items'                  => __( 'All Suppliers', 'supplier-standalone' ),
                'parent_item'                => __( 'Parent Supplier', 'supplier-standalone' ),
                'parent_item_colon'          => __( 'Parent Supplier:', 'supplier-standalone' ),
                'new_item_name'              => __( 'Name of new Supplier', 'supplier-standalone' ),
                'add_new_item'               => __( 'New Supplier', 'supplier-standalone' ),
                'edit_item'                  => __( 'Edit Supplier', 'supplier-standalone' ),
                'update_item'                => __( 'Update Supplier', 'supplier-standalone' ),
                'view_item'                  => __( 'View Supplier', 'supplier-standalone' ),
                'separate_items_with_commas' => __( 'Separate suppliers with commas', 'supplier-standalone' ),
                'search_items'               => __( 'Search Suppliers', 'supplier-standalone' ),
                'add_or_remove_items'        => __( 'Add or remove suppliers', 'supplier-standalone' ),
                'choose_from_most_used'      => __( 'Choose from the most used suppliers', 'supplier-standalone' ),
                'not_found'                  => __( 'No suppliers found', 'supplier-standalone' ),
            ];

            $args = [
                'labels'            => $labels,
                'hierarchical'      => true,
                'rewrite'           => [ 'slug' => 'supplier', 'with_front' => false ],
                'public'            => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_nav_menus' => true,
                'show_tagcloud'     => true,
                'show_in_rest'      => true,
            ];

            // Avoid re-registering if another plugin already registered the taxonomy.
            if ( ! taxonomy_exists( self::TAXONOMY ) ) {
                register_taxonomy( self::TAXONOMY, [ 'product' ], $args );
            }

            // Ensure the taxonomy stays attached to WooCommerce products.
            register_taxonomy_for_object_type( self::TAXONOMY, 'product' );
        }

        public function render_add_fields() {
            ?>
            <div class="form-field term-email-wrap">
                <label for="mcisoe_supplier_email"><?php esc_html_e( 'Supplier Email', 'supplier-standalone' ); ?></label>
                <input type="email" name="<?php echo esc_attr( self::META_EMAIL ); ?>" id="mcisoe_supplier_email" value="" required />
                <p class="description"><?php esc_html_e( 'Email address of the supplier.', 'supplier-standalone' ); ?></p>
            </div>
            <?php wp_nonce_field( 'supplier_standalone_meta', 'supplier_standalone_meta' ); ?>
            <?php
        }

        public function render_edit_fields( $term ) {
            $email       = get_term_meta( $term->term_id, self::META_EMAIL, true );
            ?>
            <tr class="form-field term-email-wrap">
                <th scope="row"><label for="mcisoe_supplier_email"><?php esc_html_e( 'Supplier Email', 'supplier-standalone' ); ?></label></th>
                <td>
                    <input type="email" name="<?php echo esc_attr( self::META_EMAIL ); ?>" id="mcisoe_supplier_email" value="<?php echo esc_attr( $email ); ?>" required />
                    <p class="description"><?php esc_html_e( 'Email address of the supplier.', 'supplier-standalone' ); ?></p>
                </td>
            </tr>
            <?php wp_nonce_field( 'supplier_standalone_meta', 'supplier_standalone_meta' ); ?>
            <?php
        }

        public function save_meta_fields( $term_id ) {
            if ( ! isset( $_POST['supplier_standalone_meta'] ) ) {
                return;
            }

            $nonce = sanitize_text_field( wp_unslash( $_POST['supplier_standalone_meta'] ) );

            if ( ! wp_verify_nonce( $nonce, 'supplier_standalone_meta' ) ) {
                return;
            }

            $fields = [
                self::META_EMAIL       => 'sanitize_email',
                self::META_CUSTOM_TEXT => 'sanitize_textarea_field',
                self::META_DATA_TEXT   => 'sanitize_textarea_field',
            ];

            foreach ( $fields as $meta_key => $sanitizer ) {
                if ( isset( $_POST[ $meta_key ] ) ) {
                    $value = wp_unslash( $_POST[ $meta_key ] );
                    if ( is_callable( $sanitizer ) ) {
                        $value = call_user_func( $sanitizer, $value );
                    }

                    update_term_meta( $term_id, $meta_key, $value );
                }
            }
        }

        public function register_columns( $columns ) {
            $new_columns = [
                'cb'             => isset( $columns['cb'] ) ? $columns['cb'] : '',
                'name'           => __( 'Name', 'supplier-standalone' ),
                'supplier_email' => __( 'Supplier Email', 'supplier-standalone' ),
            ];

            return $new_columns;
        }

        public function render_column( $content, $column, $term_id ) {
            switch ( $column ) {
                case 'supplier_email':
                    $content = get_term_meta( $term_id, self::META_EMAIL, true );
                    break;
                default:
                    return $content;
            }

            return $content ? esc_html( $content ) : '&mdash;';
        }

        public function hide_default_taxonomy_fields() {
            if ( ! function_exists( 'get_current_screen' ) ) {
                return;
            }

            $screen = get_current_screen();
            if ( ! $screen || ( 'edit-' . self::TAXONOMY !== $screen->id && self::TAXONOMY !== $screen->taxonomy ) ) {
                return;
            }

            // Match the legacy plugin by hiding slug/parent/description rows.
            $css = '
            .term-slug-wrap,
            .term-parent-wrap,
            .term-description-wrap,
            #wpseo_meta {
                display: none !important;
            }';

            wp_add_inline_style( 'common', $css );
        }

        public function add_supplier_filter_dropdown() {
            global $typenow;

            if ( 'product' !== $typenow || ! taxonomy_exists( self::TAXONOMY ) ) {
                return;
            }

            $taxonomy     = get_taxonomy( self::TAXONOMY );
            $selected     = isset( $_GET[ self::TAXONOMY ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::TAXONOMY ] ) ) : '';
            $placeholder  = sprintf( __( 'All %s', 'supplier-standalone' ), $taxonomy->labels->name );

            wp_dropdown_categories(
                [
                    'show_option_all' => $placeholder,
                    'taxonomy'        => self::TAXONOMY,
                    'name'            => self::TAXONOMY,
                    'orderby'         => 'name',
                    'selected'        => $selected,
                    'hide_empty'      => false,
                    'hierarchical'    => true,
                    'value_field'     => 'slug', // Use slug so the query var is stable across sites.
                ]
            );
        }

        public function apply_supplier_filter_query( $query ) {
            global $pagenow;

            if ( 'edit.php' !== $pagenow || ! is_admin() ) {
                return $query;
            }

            if ( empty( $_GET['post_type'] ) || 'product' !== $_GET['post_type'] ) {
                return $query;
            }

            if ( ! empty( $_GET[ self::TAXONOMY ] ) && is_string( $_GET[ self::TAXONOMY ] ) ) {
                $query->query_vars[ self::TAXONOMY ] = sanitize_text_field( wp_unslash( $_GET[ self::TAXONOMY ] ) );
            }

            return $query;
        }
    }
}

Supplier_Standalone::instance();

/**
 * Helper to fetch supplier meta in one call using legacy keys.
 *
 * @param int $term_id Supplier term ID.
 * @return array
 */
if ( ! function_exists( 'supplier_standalone_get_supplier_meta' ) ) {
    function supplier_standalone_get_supplier_meta( $term_id ) {
        return [
            'email'       => get_term_meta( $term_id, Supplier_Standalone::META_EMAIL, true ),
            'custom_text' => get_term_meta( $term_id, Supplier_Standalone::META_CUSTOM_TEXT, true ),
            'data_text'   => get_term_meta( $term_id, Supplier_Standalone::META_DATA_TEXT, true ),
        ];
    }
}
