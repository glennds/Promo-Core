<?php
defined('ABSPATH') || exit;

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////



if ( ! class_exists( 'Warehouse_Taxonomy' ) ) {
    final class Warehouse_Taxonomy {

        const TAXONOMY   = 'warehouse';
        const META_EMAIL = 'mcisoe_warehouse_email';
        const META_CUSTOM_TEXT = 'mcisoe_warehouse_custom_text';
        const META_DATA_TEXT   = 'mcisoe_warehouse_data_text';

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

            // Admin fields for warehouse meta.
            add_action( self::TAXONOMY . '_add_form_fields', [ $this, 'render_add_fields' ] );
            add_action( self::TAXONOMY . '_edit_form_fields', [ $this, 'render_edit_fields' ] );
            add_action( 'created_' . self::TAXONOMY, [ $this, 'save_meta_fields' ] );
            add_action( 'edited_' . self::TAXONOMY, [ $this, 'save_meta_fields' ] );
            add_action( 'admin_enqueue_scripts', [ $this, 'hide_default_taxonomy_fields' ] );

            // Columns on the taxonomy list table.
            add_filter( 'manage_edit-' . self::TAXONOMY . '_columns', [ $this, 'register_columns' ] );
            add_filter( 'manage_' . self::TAXONOMY . '_custom_column', [ $this, 'render_column' ], 10, 3 );

            // Product list filtering by warehouse in wp-admin.
            add_action( 'restrict_manage_posts', [ $this, 'add_warehouse_filter_dropdown' ] );
            add_filter( 'parse_query', [ $this, 'apply_warehouse_filter_query' ] );
        }

        public function register_taxonomy() {
            $labels = [
                'name'                       => __( 'Warehouses', 'warehouse-standalone' ),
                'singular_name'              => __( 'Warehouse', 'warehouse-standalone' ),
                'menu_name'                  => __( 'Warehouses', 'warehouse-standalone' ),
                'all_items'                  => __( 'All Warehouses', 'warehouse-standalone' ),
                'parent_item'                => __( 'Parent Warehouse', 'warehouse-standalone' ),
                'parent_item_colon'          => __( 'Parent Warehouse:', 'warehouse-standalone' ),
                'new_item_name'              => __( 'Name of new Warehouse', 'warehouse-standalone' ),
                'add_new_item'               => __( 'New Warehouse', 'warehouse-standalone' ),
                'edit_item'                  => __( 'Edit Warehouse', 'warehouse-standalone' ),
                'update_item'                => __( 'Update Warehouse', 'warehouse-standalone' ),
                'view_item'                  => __( 'View Warehouse', 'warehouse-standalone' ),
                'separate_items_with_commas' => __( 'Separate warehouses with commas', 'warehouse-standalone' ),
                'search_items'               => __( 'Search Warehouses', 'warehouse-standalone' ),
                'add_or_remove_items'        => __( 'Add or remove warehouses', 'warehouse-standalone' ),
                'choose_from_most_used'      => __( 'Choose from the most used warehouses', 'warehouse-standalone' ),
                'not_found'                  => __( 'No warehouses found', 'warehouse-standalone' ),
            ];

            $args = [
                'labels'            => $labels,
                'hierarchical'      => true,
                'rewrite'           => [ 'slug' => 'warehouse', 'with_front' => false ],
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
                <label for="mcisoe_warehouse_email"><?php esc_html_e( 'Warehouse Email', 'warehouse-standalone' ); ?></label>
                <input type="email" name="<?php echo esc_attr( self::META_EMAIL ); ?>" id="mcisoe_warehouse_email" value="" required />
                <p class="description"><?php esc_html_e( 'Email address of the warehouse.', 'warehouse-standalone' ); ?></p>
            </div>
            <?php wp_nonce_field( 'warehouse_standalone_meta', 'warehouse_standalone_meta' ); ?>
            <?php
        }

        public function render_edit_fields( $term ) {
            $email       = get_term_meta( $term->term_id, self::META_EMAIL, true );
            ?>
            <tr class="form-field term-email-wrap">
                <th scope="row"><label for="mcisoe_warehouse_email"><?php esc_html_e( 'Warehouse Email', 'warehouse-standalone' ); ?></label></th>
                <td>
                    <input type="email" name="<?php echo esc_attr( self::META_EMAIL ); ?>" id="mcisoe_warehouse_email" value="<?php echo esc_attr( $email ); ?>" required />
                    <p class="description"><?php esc_html_e( 'Email address of the warehouse.', 'warehouse-standalone' ); ?></p>
                </td>
            </tr>
            <?php wp_nonce_field( 'warehouse_standalone_meta', 'warehouse_standalone_meta' ); ?>
            <?php
        }

        public function save_meta_fields( $term_id ) {
            if ( ! isset( $_POST['warehouse_standalone_meta'] ) ) {
                return;
            }

            $nonce = sanitize_text_field( wp_unslash( $_POST['warehouse_standalone_meta'] ) );

            if ( ! wp_verify_nonce( $nonce, 'warehouse_standalone_meta' ) ) {
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
                'name'           => __( 'Name', 'warehouse-standalone' ),
                'warehouse_email' => __( 'Warehouse Email', 'warehouse-standalone' ),
            ];

            return $new_columns;
        }

        public function render_column( $content, $column, $term_id ) {
            switch ( $column ) {
                case 'warehouse_email':
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

        public function add_warehouse_filter_dropdown() {
            global $typenow;

            if ( 'product' !== $typenow || ! taxonomy_exists( self::TAXONOMY ) ) {
                return;
            }

            $taxonomy     = get_taxonomy( self::TAXONOMY );
            $selected     = isset( $_GET[ self::TAXONOMY ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::TAXONOMY ] ) ) : '';
            $placeholder  = sprintf( __( 'All %s', 'warehouse-standalone' ), $taxonomy->labels->name );

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

        public function apply_warehouse_filter_query( $query ) {
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

Warehouse_Taxonomy::instance();

/**
 * Helper to fetch warehouse meta in one call using legacy keys.
 *
 * @param int $term_id Warehouse term ID.
 * @return array
 */
if ( ! function_exists( 'warehouse_standalone_get_warehouse_meta' ) ) {
    function warehouse_standalone_get_warehouse_meta( $term_id ) {
        return [
            'email'       => get_term_meta( $term_id, Warehouse_Taxonomy::META_EMAIL, true ),
            'custom_text' => get_term_meta( $term_id, Warehouse_Taxonomy::META_CUSTOM_TEXT, true ),
            'data_text'   => get_term_meta( $term_id, Warehouse_Taxonomy::META_DATA_TEXT, true ),
        ];
    }
}
