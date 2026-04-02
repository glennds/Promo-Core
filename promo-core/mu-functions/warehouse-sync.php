<?php
defined('ABSPATH') || exit;

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////



if ( ! class_exists( 'MSO_Warehouse_Sync' ) ) {
    class MSO_Warehouse_Sync {
        /**
         * Keep the last run output for display.
         *
         * @var array
         */
        private $last_results = [];

        /**
         * Singleton.
         *
         * @var self|null
         */
        private static $instance = null;

        /**
         * Entry point.
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        private function __construct() {
            add_action( 'network_admin_menu', [ $this, 'register_menu' ] );
        }

        /**
         * Register the Network Admin page.
         */
        public function register_menu() {
            add_menu_page(
                __( 'Warehouse Sync', 'ds-warehouse-sync' ),
                __( 'Warehouse Sync', 'ds-warehouse-sync' ),
                'manage_network',
                'ds-warehouse-sync',
                [ $this, 'render_page' ],
                'dashicons-migrate',
                57
            );
        }

        /**
         * Render the Network Admin page and handle form submissions.
         */
        public function render_page() {
            if ( ! current_user_can( 'manage_network' ) ) {
                wp_die( __( 'You do not have permission to access this page.', 'ds-warehouse-sync' ) );
            }

            if ( ! is_multisite() ) {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'This plugin only works on multisite installs.', 'ds-warehouse-sync' ) . '</p></div>';
                return;
            }

            if ( isset( $_POST['mso_warehouse_sync_nonce'] ) ) {
                $this->handle_sync_request();
            }

            $this->render_form();
        }

        /**
         * Build a list of sites for the selects.
         *
         * @return array
         */
        private function get_sites_list() {
            $sites = get_sites( [ 'number' => 999 ] );
            $list  = [];

            foreach ( $sites as $site ) {
                $list[] = [
                    'blog_id' => (int) $site->blog_id,
                    'name'    => $site->blogname ?: $site->domain,
                    'path'    => $site->path,
                ];
            }

            return $list;
        }

        /**
         * Handle the form submission and perform the sync.
         */
        private function handle_sync_request() {
            check_admin_referer( 'mso_warehouse_sync_action', 'mso_warehouse_sync_nonce' );

            $source_site      = isset( $_POST['mso_source_site'] ) ? absint( $_POST['mso_source_site'] ) : 0;
            $target_sites_raw = isset( $_POST['mso_target_sites'] ) ? (array) $_POST['mso_target_sites'] : [];
            $identifier       = isset( $_POST['mso_identifier'] ) && in_array( $_POST['mso_identifier'], [ 'sku', 'title' ], true ) ? $_POST['mso_identifier'] : 'sku';
            $fallback_title   = ! empty( $_POST['mso_fallback_title'] );
            $replace_existing = ! empty( $_POST['mso_replace_existing'] );

            $target_sites = [];
            foreach ( $target_sites_raw as $blog_id ) {
                $bid = absint( $blog_id );
                if ( $bid ) {
                    $target_sites[] = $bid;
                }
            }

            if ( ! $source_site || empty( $target_sites ) ) {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Select a source site and at least one target site.', 'ds-warehouse-sync' ) . '</p></div>';
                return;
            }

            if ( in_array( $source_site, $target_sites, true ) ) {
                $target_sites = array_diff( $target_sites, [ $source_site ] );
            }

            if ( empty( $target_sites ) ) {
                echo '<div class="notice notice-warning"><p>' . esc_html__( 'Nothing to sync: target list is empty after removing the source site.', 'ds-warehouse-sync' ) . '</p></div>';
                return;
            }

            $this->last_results = [
                'source'  => $source_site,
                'targets' => [],
            ];

            $source_products = $this->build_source_product_map( $source_site, $identifier, $fallback_title );

            if ( empty( $source_products['products'] ) ) {
                echo '<div class="notice notice-warning"><p>' . esc_html__( 'No products with warehouse terms found on the source site.', 'ds-warehouse-sync' ) . '</p></div>';
                return;
            }

            foreach ( $target_sites as $target_site ) {
                $this->last_results['targets'][ $target_site ] = $this->sync_to_target(
                    $target_site,
                    $source_products['products'],
                    $identifier,
                    $fallback_title,
                    $replace_existing
                );
            }

            $this->render_results( $source_products['summary'] );
        }

        /**
         * Collect source products and their warehouse terms keyed by identifier.
         *
         * @param int    $source_site
         * @param string $identifier
         * @param bool   $fallback_title
         * @return array
         */
        private function build_source_product_map( $source_site, $identifier, $fallback_title ) {
            $products            = [];
            $duplicates          = 0;
            $missing_identifiers = 0;

            switch_to_blog( $source_site );

            $query = new WP_Query( [
                'post_type'      => 'product',
                'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ] );

            foreach ( $query->posts as $product_id ) {
                $title = get_the_title( $product_id );
                $sku   = get_post_meta( $product_id, '_sku', true );

                $identifier_value = ( 'title' === $identifier ) ? $title : $sku;
                $used_identifier  = $identifier;

                if ( 'sku' === $identifier && empty( $identifier_value ) && $fallback_title ) {
                    $identifier_value = $title;
                    $used_identifier  = 'title';
                }

                if ( empty( $identifier_value ) ) {
                    $missing_identifiers++;
                    continue;
                }

                $terms = get_the_terms( $product_id, 'warehouse' );
                if ( empty( $terms ) || is_wp_error( $terms ) ) {
                    continue;
                }

                $term_payload = [];
                foreach ( $terms as $term ) {
                    $term_meta = get_term_meta( $term->term_id );
                    $term_payload[ $term->slug ] = [
                        'name'        => $term->name,
                        'slug'        => $term->slug,
                        'description' => $term->description,
                        'meta'        => $term_meta,
                    ];
                }

                if ( isset( $products[ $identifier_value ] ) ) {
                    $duplicates++;
                    continue;
                }

                $products[ $identifier_value ] = [
                    'source_id'        => $product_id,
                    'title'            => $title,
                    'sku'              => $sku,
                    'identifier_used'  => $used_identifier,
                    'terms'            => $term_payload,
                ];
            }

            restore_current_blog();

            return [
                'products' => $products,
                'summary'  => [
                    'total_with_warehouses' => count( $products ),
                    'duplicates_skipped'   => $duplicates,
                    'missing_identifier'   => $missing_identifiers,
                ],
            ];
        }

        /**
         * Sync warehouse terms to a target site.
         *
         * @param int    $target_site
         * @param array  $products
         * @param string $identifier
         * @param bool   $fallback_title
         * @param bool   $replace_existing
         * @return array
         */
        private function sync_to_target( $target_site, $products, $identifier, $fallback_title, $replace_existing ) {
            $result = [
                'matched_products'  => 0,
                'links_written'     => 0,
                'missing_products'  => 0,
                'errors'            => [],
            ];

            switch_to_blog( $target_site );

            foreach ( $products as $identifier_value => $product ) {
                $target_product_id = $this->find_target_product_id( $identifier_value, $product, $identifier, $fallback_title );

                if ( ! $target_product_id ) {
                    $result['missing_products']++;
                    continue;
                }

                $synced = $this->write_warehouses( $target_product_id, $product['terms'], $replace_existing );

                if ( is_wp_error( $synced ) ) {
                    $result['errors'][] = $synced->get_error_message();
                    continue;
                }

                $result['matched_products']++;
                $result['links_written'] += $synced;
            }

            restore_current_blog();

            return $result;
        }

        /**
         * Find the target product ID using the selected identifier with fallback.
         *
         * @param string $identifier_value
         * @param array  $product
         * @param string $identifier
         * @param bool   $fallback_title
         * @return int
         */
        private function find_target_product_id( $identifier_value, $product, $identifier, $fallback_title ) {
            global $wpdb;

            $target_product_id = 0;

            if ( 'sku' === $identifier && ! empty( $product['sku'] ) && function_exists( 'wc_get_product_id_by_sku' ) ) {
                $target_product_id = wc_get_product_id_by_sku( $product['sku'] );
            }

            if ( ! $target_product_id && ( 'title' === $identifier || $fallback_title ) ) {
                $title_to_match    = ( 'title' === $identifier ) ? $identifier_value : $product['title'];
                $target_product_id = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'product' LIMIT 1",
                    $title_to_match
                ) );
            }

            return $target_product_id;
        }

        /**
         * Persist warehouse term links for a single product on the current site.
         *
         * @param int   $product_id
         * @param array $terms
         * @param bool  $replace_existing
         * @return int|WP_Error Number of written rows or WP_Error on failure.
         */
        private function write_warehouses( $product_id, $terms, $replace_existing ) {
            if ( ! taxonomy_exists( 'warehouse' ) ) {
                return new WP_Error( 'missing_taxonomy', __( 'Warehouse taxonomy does not exist on target site.', 'ds-warehouse-sync' ) );
            }

            $term_ids = [];

            foreach ( $terms as $term ) {
                $term_id = $this->get_or_create_term( $term );
                if ( is_wp_error( $term_id ) ) {
                    return $term_id;
                }
                $term_ids[] = $term_id;
            }

            if ( $replace_existing ) {
                $set = wp_set_object_terms( $product_id, $term_ids, 'warehouse' );
            } else {
                $existing = wp_get_object_terms( $product_id, 'warehouse', [ 'fields' => 'ids' ] );
                if ( is_wp_error( $existing ) ) {
                    return $existing;
                }
                $set = wp_set_object_terms( $product_id, array_unique( array_merge( $existing, $term_ids ) ), 'warehouse' );
            }

            if ( is_wp_error( $set ) ) {
                return $set;
            }

            return count( $term_ids );
        }

        /**
         * Get or create a warehouse term on current site and copy meta.
         *
         * @param array $term
         * @return int|WP_Error
         */
        private function get_or_create_term( $term ) {
            $existing = get_term_by( 'slug', $term['slug'], 'warehouse' );
            if ( $existing && ! is_wp_error( $existing ) ) {
                $this->maybe_update_term_meta( $existing->term_id, $term['meta'] );
                return (int) $existing->term_id;
            }

            $created = wp_insert_term(
                $term['name'],
                'warehouse',
                [
                    'slug'        => $term['slug'],
                    'description' => $term['description'],
                ]
            );

            if ( is_wp_error( $created ) ) {
                return $created;
            }

            $term_id = isset( $created['term_id'] ) ? (int) $created['term_id'] : 0;
            $this->maybe_update_term_meta( $term_id, $term['meta'] );

            return $term_id;
        }

        /**
         * Copy meta keys/values to target term.
         *
         * @param int   $term_id
         * @param array $meta
         */
        private function maybe_update_term_meta( $term_id, $meta ) {
            if ( empty( $meta ) || ! is_array( $meta ) ) {
                return;
            }

            foreach ( $meta as $meta_key => $values ) {
                if ( ! is_array( $values ) ) {
                    continue;
                }
                // Keep last value; most term meta keys are single values.
                $value = end( $values );
                if ( is_array( $value ) ) {
                    $value = maybe_serialize( $value );
                }
                update_term_meta( $term_id, $meta_key, $value );
            }
        }

        /**
         * Render the form UI.
         */
        private function render_form() {
            $sites = $this->get_sites_list();
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Warehouse Sync', 'ds-warehouse-sync' ); ?></h1>
                <p><?php esc_html_e( 'Copy warehouse taxonomy terms from a source subsite to one or more target subsites and attach them to matching products. Matching is done by SKU by default with an optional title fallback.', 'ds-warehouse-sync' ); ?></p>

                <form method="post">
                    <?php wp_nonce_field( 'mso_warehouse_sync_action', 'mso_warehouse_sync_nonce' ); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Source site', 'ds-warehouse-sync' ); ?></th>
                            <td>
                                <select name="mso_source_site" required>
                                    <option value=""><?php esc_html_e( 'Select source site', 'ds-warehouse-sync' ); ?></option>
                                    <?php foreach ( $sites as $site ) : ?>
                                        <option value="<?php echo esc_attr( $site['blog_id'] ); ?>">
                                            <?php echo esc_html( $site['name'] . ' (' . $site['path'] . ')' ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Target sites', 'ds-warehouse-sync' ); ?></th>
                            <td>
                                <select name="mso_target_sites[]" multiple size="8" required style="min-width: 280px;">
                                    <?php foreach ( $sites as $site ) : ?>
                                        <option value="<?php echo esc_attr( $site['blog_id'] ); ?>">
                                            <?php echo esc_html( $site['name'] . ' (' . $site['path'] . ')' ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple sites. The source site will be removed from the target list automatically.', 'ds-warehouse-sync' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Product identifier', 'ds-warehouse-sync' ); ?></th>
                            <td>
                                <label>
                                    <input type="radio" name="mso_identifier" value="title" checked>
                                    <?php esc_html_e( 'Productnaam (aanbevolen)', 'ds-warehouse-sync' ); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="mso_identifier" value="sku">
                                    <?php esc_html_e( 'SKU (NIET aanbevolen)', 'ds-warehouse-sync' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="mso_fallback_title" value="1">
                                    <?php esc_html_e( 'Als SKU leeg is, gebruik producttitel als fallback', 'ds-warehouse-sync' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Bestaande connecties bijwerken', 'ds-warehouse-sync' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="mso_replace_existing" value="1" checked>
                                    <?php esc_html_e( 'Replace existing warehouse terms on target products', 'ds-warehouse-sync' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Synchroniseren', 'ds-warehouse-sync' ); ?></button>
                    </p>
                </form>
            </div>
            <?php
        }

        /**
         * Render a summary of the last sync.
         *
         * @param array $source_summary
         */
        private function render_results( $source_summary ) {
            if ( empty( $this->last_results['targets'] ) ) {
                return;
            }

            echo '<div class="notice notice-success"><p>' . esc_html__( 'Warehouse sync finished.', 'ds-warehouse-sync' ) . '</p></div>';

            echo '<h2>' . esc_html__( 'Summary', 'ds-warehouse-sync' ) . '</h2>';
            echo '<p>' . sprintf(
                /* translators: 1: number of products, 2: duplicates, 3: missing identifier */
                esc_html__( '%1$d products with warehouses in source. %2$d duplicates skipped. %3$d without identifier skipped.', 'ds-warehouse-sync' ),
                (int) $source_summary['total_with_warehouses'],
                (int) $source_summary['duplicates_skipped'],
                (int) $source_summary['missing_identifier']
            ) . '</p>';

            echo '<table class="widefat striped" style="max-width: 900px;">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Target site', 'ds-warehouse-sync' ) . '</th>';
            echo '<th>' . esc_html__( 'Matched products', 'ds-warehouse-sync' ) . '</th>';
            echo '<th>' . esc_html__( 'Warehouse links written', 'ds-warehouse-sync' ) . '</th>';
            echo '<th>' . esc_html__( 'Products not found', 'ds-warehouse-sync' ) . '</th>';
            echo '<th>' . esc_html__( 'Errors', 'ds-warehouse-sync' ) . '</th>';
            echo '</tr></thead><tbody>';

            foreach ( $this->last_results['targets'] as $blog_id => $data ) {
                $site = get_blog_details( $blog_id );
                $name = $site ? $site->blogname . ' (' . $site->path . ')' : ( 'Blog ID ' . $blog_id );

                echo '<tr>';
                echo '<td>' . esc_html( $name ) . '</td>';
                echo '<td>' . esc_html( $data['matched_products'] ) . '</td>';
                echo '<td>' . esc_html( $data['links_written'] ) . '</td>';
                echo '<td>' . esc_html( $data['missing_products'] ) . '</td>';
                echo '<td>';
                if ( ! empty( $data['errors'] ) ) {
                    echo '<ul style="margin:0;">';
                    foreach ( $data['errors'] as $err ) {
                        echo '<li>' . esc_html( $err ) . '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '&mdash;';
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }
    }

    MSO_Warehouse_Sync::instance();
}
