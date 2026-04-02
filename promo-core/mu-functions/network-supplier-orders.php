<?php
defined('ABSPATH') || exit;

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////



if ( ! defined( 'NSO_VERSION' ) ) {
    define( 'NSO_VERSION', '2.0.2' );
}

if ( ! defined( 'NSO_PLUGIN_FILE' ) ) {
    define( 'NSO_PLUGIN_FILE', dirname( __FILE__, 2 ) . '/promo-core.php' );
}

if ( ! defined( 'NSO_PLUGIN_DIR' ) ) {
    define( 'NSO_PLUGIN_DIR', trailingslashit( dirname( NSO_PLUGIN_FILE ) ) );
}

if ( ! defined( 'NSO_PLUGIN_URL' ) ) {
    define( 'NSO_PLUGIN_URL', trailingslashit( plugins_url( '', NSO_PLUGIN_FILE ) ) );
}

if ( ! class_exists( 'Network_Supplier_Orders' ) ) {
    class Network_Supplier_Orders {
    
    private static $instance = null;
    private $attribute_column_pairs = 5;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('network_admin_menu', array($this, 'add_network_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_nso_export_orders', array($this, 'export_orders'));
        add_action('wp_ajax_nso_manual_export_email', array($this, 'manual_export_email'));
        add_action('wp_ajax_nso_save_supplier_settings', array($this, 'save_supplier_settings'));
        add_action('wp_ajax_nso_send_individual_supplier_email', array($this, 'send_individual_supplier_email'));
        add_action('wp_ajax_nso_save_email_schedule', array($this, 'save_email_schedule'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('init', array($this, 'ensure_cron_schedule'));
        
        // Hook into supplier creation to create folders
        add_action('created_supplier', array($this, 'create_supplier_folder'), 10, 2);
        
        // Schedule daily cron job
        add_action('nso_daily_supplier_export', array($this, 'daily_supplier_export'));

        // Deferred test email job
        add_action('nso_deferred_supplier_test_email', array($this, 'run_deferred_supplier_test_email'), 10, 1);
        
    }

    /**
     * Check if WP cron-based supplier emails are disabled (external cron in use)
     */
    private function is_wp_cron_disabled() {
        return (bool) get_site_option('nso_disable_wp_cron', false);
    }

    /**
     * Unschedule the plugin's daily cron event
     */
    private function unschedule_cron_job() {
        $timestamp = wp_next_scheduled('nso_daily_supplier_export');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'nso_daily_supplier_export');
        }
    }

    /**
     * Return all network sites (aggregate orders from every webshop)
     */
    private function get_main_sites() {
        // Fetch every site in the network; do not exclude archived/spam/deleted to avoid hiding valid shops.
        $sites = get_sites(array(
            'number'   => 0,
            'public'   => null,
            'archived' => null,
            'spam'     => null,
            'deleted'  => null,
        ));

        if (empty($sites)) {
            $fallback_id = function_exists('get_main_site_id') ? (int) get_main_site_id() : 0;

            if (!$fallback_id && function_exists('get_current_blog_id')) {
                $fallback_id = (int) get_current_blog_id();
            }

            if (!$fallback_id && function_exists('get_current_site')) {
                $fallback_id = (int) get_current_site()->blog_id;
            }

            if ($fallback_id) {
                $fallback_site = get_site($fallback_id);
                if ($fallback_site) {
                    $sites = array($fallback_site);
                }
            }
        }

        return $sites;
    }

    /**
     * Ensure the supplier taxonomy is registered for the current blog context.
     */
    private function ensure_supplier_taxonomy() {
        if ( taxonomy_exists( 'supplier' ) ) {
            return;
        }

        $labels = array(
            'name'                       => __( 'Suppliers', 'network-supplier-orders' ),
            'singular_name'              => __( 'Supplier', 'network-supplier-orders' ),
            'menu_name'                  => __( 'Suppliers', 'network-supplier-orders' ),
            'all_items'                  => __( 'All Suppliers', 'network-supplier-orders' ),
            'parent_item'                => __( 'Parent Supplier', 'network-supplier-orders' ),
            'parent_item_colon'          => __( 'Parent Supplier:', 'network-supplier-orders' ),
            'new_item_name'              => __( 'Name of new Supplier', 'network-supplier-orders' ),
            'add_new_item'               => __( 'New Supplier', 'network-supplier-orders' ),
            'edit_item'                  => __( 'Edit Supplier', 'network-supplier-orders' ),
            'update_item'                => __( 'Update Supplier', 'network-supplier-orders' ),
            'view_item'                  => __( 'View Supplier', 'network-supplier-orders' ),
            'separate_items_with_commas' => __( 'Separate suppliers with commas', 'network-supplier-orders' ),
            'search_items'               => __( 'Search Suppliers', 'network-supplier-orders' ),
            'add_or_remove_items'        => __( 'Add or remove suppliers', 'network-supplier-orders' ),
            'choose_from_most_used'      => __( 'Choose from the most used suppliers', 'network-supplier-orders' ),
            'not_found'                  => __( 'No suppliers found', 'network-supplier-orders' ),
        );

        $args = array(
            'labels'            => $labels,
            'hierarchical'      => true,
            'rewrite'           => array( 'slug' => 'supplier', 'with_front' => false ),
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud'     => true,
            'show_in_rest'      => true,
        );

        register_taxonomy( 'supplier', array( 'product' ), $args );
        register_taxonomy_for_object_type( 'supplier', 'product' );
    }
    
    public function add_network_menu() {
        add_menu_page(
            __('Network Orders', 'network-supplier-orders'),
            __('Network Orders', 'network-supplier-orders'),
            'manage_network',
            'nso-network-orders',
            array($this, 'network_orders_page'),
            'dashicons-cart',
            56
        );
        
        // Add settings submenu
        add_submenu_page(
            'nso-network-orders',
            __('Supplier Email Settings', 'network-supplier-orders'),
            __('Email Settings', 'network-supplier-orders'),
            'manage_network',
            'nso-supplier-settings',
            array($this, 'supplier_settings_page')
        );
        
        // Add time configuration submenu
        add_submenu_page(
            'nso-network-orders',
            __('Automated Email Time', 'network-supplier-orders'),
            __('Email Schedule', 'network-supplier-orders'),
            'manage_network',
            'nso-email-schedule',
            array($this, 'email_schedule_page')
        );
    }
    
    public function enqueue_scripts($hook) {
        if ( ! is_network_admin() ) {
            return;
        }

        $valid_hooks = array(
            'toplevel_page_nso-network-orders',
            'nso-network-orders_page_nso-supplier-settings',
            'nso-network-orders_page_nso-email-schedule',
        );

        if ( ! in_array( $hook, $valid_hooks, true ) ) {
            return;
        }

        wp_enqueue_style('nso-admin', NSO_PLUGIN_URL . 'assets/network-supplier-orders.css', array(), NSO_VERSION);
        wp_enqueue_script('nso-admin', NSO_PLUGIN_URL . 'assets/network-supplier-orders.js', array('jquery'), NSO_VERSION, true);

        wp_localize_script('nso-admin', 'nsoAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nso_export_nonce')
        ));
    }
    
    public function network_orders_page() {
        // Get filter parameters
        $supplier_filter = isset($_GET['supplier']) ? sanitize_text_field($_GET['supplier']) : '';
        $site_filter = isset($_GET['site']) ? intval($_GET['site']) : 0;
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        if ($date_from === '' && $date_to === '') {
            $now = current_time('timestamp');
            $date_to = date_i18n('Y-m-d', $now);
            $date_from = date_i18n('Y-m-d', $now - 29 * DAY_IN_SECONDS);
        }
        $per_page_options = array(50, 100, 200, 500);
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
        if (!in_array($per_page, $per_page_options, true)) {
            $per_page = 50;
        }
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        // Get only the main site
        $sites = $this->get_main_sites();
        
        // Get all suppliers from taxonomy (all sites)
        $all_suppliers = array();
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            $this->ensure_supplier_taxonomy();
            
            if (taxonomy_exists('supplier')) {
                $site_suppliers = get_terms(array(
                    'taxonomy' => 'supplier',
                    'hide_empty' => false,
                ));
                
                if (!is_wp_error($site_suppliers)) {
                    foreach ($site_suppliers as $supplier) {
                        if (!isset($all_suppliers[$supplier->slug])) {
                            $all_suppliers[$supplier->slug] = $supplier;
                        }
                    }
                }
            }
            
            restore_current_blog();
        }
        
        // Get order statuses
        $order_statuses = $this->get_order_statuses();
        
        // Get orders
        $orders = $this->get_all_orders($supplier_filter, $site_filter, $status_filter, $date_from, $date_to);
        $total_orders = count($orders);
        $total_pages = max(1, (int) ceil($total_orders / $per_page));
        if ($current_page > $total_pages) {
            $current_page = $total_pages;
        }
        $offset = ($current_page - 1) * $per_page;
        $paged_orders = array_slice($orders, $offset, $per_page);
        $pagination_args = array(
            'page' => 'nso-network-orders',
            'supplier' => $supplier_filter !== '' ? $supplier_filter : null,
            'site' => $site_filter ? $site_filter : null,
            'status' => $status_filter !== '' ? $status_filter : null,
            'date_from' => $date_from !== '' ? $date_from : null,
            'date_to' => $date_to !== '' ? $date_to : null,
            'per_page' => $per_page,
        );
        $pagination_args = array_filter($pagination_args, function($value) {
            return $value !== null && $value !== '';
        });
        $pagination_links = paginate_links(array(
            'base' => add_query_arg($pagination_args, network_admin_url('admin.php')) . '%_%',
            'format' => '&paged=%#%',
            'current' => $current_page,
            'total' => $total_pages,
            'type' => 'array',
            'prev_text' => __('Previous', 'network-supplier-orders'),
            'next_text' => __('Next', 'network-supplier-orders'),
        ));
        $display_start = $total_orders > 0 ? ($offset + 1) : 0;
        $display_end = $total_orders > 0 ? min($offset + $per_page, $total_orders) : 0;
        
        ?>
        <div class="wrap">
            <h1><?php _e('Network Orders by Supplier', 'network-supplier-orders'); ?></h1>
            
            <?php if (isset($_GET['debug'])): ?>
                <div class="notice notice-info">
                    <p><strong>Debug Info:</strong></p>
                    <ul>
                        <li>Suppliers found: <?php echo count($all_suppliers); ?></li>
                        <li>Orders found: <?php echo count($orders); ?></li>
                        <li>Sites: <?php echo count($sites); ?></li>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (empty($all_suppliers)): ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php _e('No suppliers found!', 'network-supplier-orders'); ?></strong><br>
                        <?php _e('Make sure the Supplier Order Email plugin is installed and suppliers are created.', 'network-supplier-orders'); ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <div class="nso-filters">
                <form method="get" action="<?php echo esc_url(network_admin_url('admin.php?page=nso-network-orders')); ?>">
                    <input type="hidden" name="page" value="nso-network-orders">
                    
                    <div class="nso-filter-row">
                        <div class="nso-filter-item">
                            <label for="supplier"><?php _e('Supplier:', 'network-supplier-orders'); ?></label>
                            <select name="supplier" id="supplier">
                                <option value=""><?php _e('All Suppliers', 'network-supplier-orders'); ?></option>
                                <?php foreach ($all_suppliers as $supplier): ?>
                                    <option value="<?php echo esc_attr($supplier->slug); ?>" <?php selected($supplier_filter, $supplier->slug); ?>>
                                        <?php echo esc_html($supplier->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="nso-filter-item">
                            <label for="site"><?php _e('Site:', 'network-supplier-orders'); ?></label>
                            <select name="site" id="site">
                                <option value="0"><?php _e('All Sites', 'network-supplier-orders'); ?></option>
                                <?php foreach ($sites as $site): ?>
                                    <option value="<?php echo $site->blog_id; ?>" <?php selected($site_filter, $site->blog_id); ?>>
                                        <?php 
                                        switch_to_blog($site->blog_id);
                                        echo esc_html(get_bloginfo('name'));
                                        restore_current_blog();
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="nso-filter-item">
                            <label for="status"><?php _e('Status:', 'network-supplier-orders'); ?></label>
                            <select name="status" id="status">
                                <option value=""><?php _e('All Statuses', 'network-supplier-orders'); ?></option>
                                <?php foreach ($order_statuses as $status_key => $status_label): ?>
                                    <option value="<?php echo esc_attr($status_key); ?>" <?php selected($status_filter, $status_key); ?>>
                                        <?php echo esc_html($status_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="nso-filter-item">
                            <label for="date_from"><?php _e('Date From:', 'network-supplier-orders'); ?></label>
                            <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
                        </div>
                        
                        <div class="nso-filter-item">
                            <label for="date_to"><?php _e('Date To:', 'network-supplier-orders'); ?></label>
                            <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
                        </div>
                        
                        <div class="nso-filter-item">
                            <label for="per_page"><?php _e('Orders per page:', 'network-supplier-orders'); ?></label>
                            <select name="per_page" id="per_page">
                                <?php foreach ($per_page_options as $option): ?>
                                    <option value="<?php echo esc_attr($option); ?>" <?php selected($per_page, $option); ?>>
                                        <?php echo esc_html($option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="nso-filter-actions">
                            <button type="submit" class="button button-primary"><?php _e('Filter', 'network-supplier-orders'); ?></button>
                            <a href="<?php echo network_admin_url('admin.php?page=nso-network-orders'); ?>" class="button"><?php _e('Reset', 'network-supplier-orders'); ?></a>
                        </div>
                    </div>
                </form>
                
                <div class="nso-export-actions">
                    <button id="nso-export-csv" class="button button-secondary"><?php _e('Export to CSV', 'network-supplier-orders'); ?></button>
                    <button id="nso-email-suppliers" class="button button-primary" style="margin-left: 10px;">
                        <span class="dashicons dashicons-email" style="margin-top: 3px;"></span>
                        <?php _e('Export & Email All Suppliers Now', 'network-supplier-orders'); ?>
                    </button>
                </div>
                
                <div id="nso-email-result" style="margin-top: 10px; display: none;"></div>
            </div>
            
            <div class="nso-cron-info" style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #ccd0d4; border-left: 4px solid #2271b1;">
                <h3 style="margin-top: 0;">
                    <span class="dashicons dashicons-clock" style="color: #2271b1;"></span>
                    <?php _e('Automated Export Schedule', 'network-supplier-orders'); ?>
                </h3>
                <p>
                    <?php 
                    $next_scheduled = wp_next_scheduled('nso_daily_supplier_export');
                    $email_time = get_site_option('nso_email_time', '09:00');
                    if ($next_scheduled) {
                        $timezone = new DateTimeZone('Europe/Amsterdam');
                        $next_run = new DateTime('@' . $next_scheduled);
                        $next_run->setTimezone($timezone);
                        printf(
                            __('Daily automated export is scheduled for <strong>%s (Netherlands Time)</strong>. Next run: <strong>%s</strong>', 'network-supplier-orders'),
                            $email_time,
                            $next_run->format('Y-m-d H:i:s')
                        );
                    } else {
                        _e('Daily automated export is <strong>NOT scheduled</strong>. Please deactivate and reactivate the plugin.', 'network-supplier-orders');
                    }
                    ?>
                </p>
                <p style="margin-bottom: 0;">
                    <em><?php _e('Each enabled supplier receives <strong>orders from the previous business day</strong> via email (Monday emails include Saturday & Sunday) with a CSV file attachment saved in:', 'network-supplier-orders'); ?>
                    <code><?php echo wp_upload_dir()['basedir']; ?>/supplier-exports/{supplier-email}/</code></em><br>
                    <em><?php _e('Manage supplier email preferences in', 'network-supplier-orders'); ?> <a href="<?php echo network_admin_url('admin.php?page=nso-supplier-settings'); ?>"><?php _e('Email Settings', 'network-supplier-orders'); ?></a> | 
                    <a href="<?php echo network_admin_url('admin.php?page=nso-email-schedule'); ?>"><?php _e('Change Email Time', 'network-supplier-orders'); ?></a></em>
                </p>
            </div>
            
            <div class="nso-stats">
                <div class="nso-stat-box">
                    <h3><?php echo $total_orders; ?></h3>
                    <p><?php _e('Total Orders', 'network-supplier-orders'); ?></p>
                </div>
                <div class="nso-stat-box">
                    <h3><?php echo $this->calculate_total_value($orders); ?></h3>
                    <p><?php _e('Total Value', 'network-supplier-orders'); ?></p>
                </div>
                <div class="nso-stat-box">
                    <h3><?php echo $this->count_unique_products($orders); ?></h3>
                    <p><?php _e('Unique Products', 'network-supplier-orders'); ?></p>
                </div>
            </div>
            
            <div class="nso-orders-table">
                <?php if (empty($orders)): ?>
                    <p><?php _e('No orders found matching your criteria.', 'network-supplier-orders'); ?></p>
                <?php else: ?>
                    <div class="tablenav top">
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php printf(__('Showing %1$d-%2$d of %3$d orders', 'network-supplier-orders'), $display_start, $display_end, $total_orders); ?>
                            </span>
                            <?php if (!empty($pagination_links)): ?>
                                <span class="pagination-links"><?php echo implode(' ', $pagination_links); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Order ID', 'network-supplier-orders'); ?></th>
                                <th><?php _e('Site', 'network-supplier-orders'); ?></th>
                                <th><?php _e('Date', 'network-supplier-orders'); ?></th>
                                <th><?php _e('Customer', 'network-supplier-orders'); ?></th>
                                <th><?php _e('Product', 'network-supplier-orders'); ?></th>
                                <th><?php _e('Supplier', 'network-supplier-orders'); ?></th>
                                <th><?php _e('Quantity', 'network-supplier-orders'); ?></th>
                                <th><?php _e('Total', 'network-supplier-orders'); ?></th>
                                <th><?php _e('Status', 'network-supplier-orders'); ?></th>
                                <th><?php _e('Actions', 'network-supplier-orders'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paged_orders as $order_data): ?>
                                <tr>
                                    <td><strong>#<?php echo esc_html($order_data['order_id']); ?></strong></td>
                                    <td><?php echo esc_html($order_data['site_name']); ?></td>
                                    <td><?php echo esc_html(date('Y-m-d H:i', strtotime($order_data['date']))); ?></td>
                                    <td>
                                        <?php echo esc_html($order_data['customer_name']); ?><br>
                                        <small><?php echo esc_html($order_data['customer_email']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo esc_html($order_data['product_name']); ?><br>
                                        <?php if ($order_data['product_sku']): ?>
                                            <small>SKU: <?php echo esc_html($order_data['product_sku']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($order_data['suppliers'])): ?>
                                            <?php foreach ($order_data['suppliers'] as $supplier): ?>
                                                <span class="nso-supplier-badge"><?php echo esc_html($supplier); ?></span><br>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="nso-no-supplier"><?php _e('No supplier', 'network-supplier-orders'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($order_data['quantity']); ?></td>
                                    <td><?php echo $order_data['total']; ?></td>
                                    <td><span class="order-status status-<?php echo esc_attr($order_data['status']); ?>"><?php echo esc_html($order_data['status_name']); ?></span></td>
                                    <td>
                                        <a href="<?php echo esc_url($order_data['order_url']); ?>" class="button button-small" target="_blank"><?php _e('View', 'network-supplier-orders'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php printf(__('Showing %1$d-%2$d of %3$d orders', 'network-supplier-orders'), $display_start, $display_end, $total_orders); ?>
                            </span>
                            <?php if (!empty($pagination_links)): ?>
                                <span class="pagination-links"><?php echo implode(' ', $pagination_links); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    private function get_all_orders($supplier_filter = '', $site_filter = 0, $status_filter = '', $date_from = '', $date_to = '') {
        $all_orders = array();
        $sites = $this->get_main_sites();
        
        foreach ($sites as $site) {
            if ($site_filter && $site->blog_id != $site_filter) {
                continue;
            }
            
            switch_to_blog($site->blog_id);
            
            if (!class_exists('WooCommerce')) {
                restore_current_blog();
                continue;
            }
            
            $args = array(
                'limit' => -1,
                'type' => 'shop_order',
                'return' => 'objects'
            );
            
            if ($status_filter) {
                $args['status'] = str_replace('wc-', '', $status_filter);
            }
            
            if ($date_from && $date_to) {
                $args['date_created'] = $date_from . '...' . $date_to . ' 23:59:59';
            } elseif ($date_from) {
                $args['date_created'] = '>=' . $date_from;
            } elseif ($date_to) {
                $args['date_created'] = '<=' . $date_to . ' 23:59:59';
            }
            
            $orders = wc_get_orders($args);
            
            foreach ($orders as $order) {
                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    $product = $item->get_product();
                    
                    if (!$product) {
                        continue;
                    }
                    
                    // Get suppliers from taxonomy
                    $product_suppliers = wp_get_post_terms($product_id, 'supplier', array('fields' => 'all'));
                    
                    // Filter by supplier if set
                    if ($supplier_filter) {
                        $has_supplier = false;
                        foreach ($product_suppliers as $ps) {
                            if ($ps->slug === $supplier_filter) {
                                $has_supplier = true;
                                break;
                            }
                        }
                        if (!$has_supplier) {
                            continue;
                        }
                    }
                    
                    // Get supplier names
                    $supplier_names = array();
                    foreach ($product_suppliers as $ps) {
                        $supplier_names[] = $ps->name;
                    }
                    
                    $customer_first_name = $order->get_billing_first_name();
                    $customer_last_name = $order->get_billing_last_name();
                    $address_components = $this->extract_order_address_components($order);
                    
                    $all_orders[] = array(
                        'order_id' => $order->get_id(),
                        'site_id' => $site->blog_id,
                        'site_name' => get_bloginfo('name'),
                        'date' => $order->get_date_created()->date('Y-m-d H:i:s'),
                        'customer_name' => trim($customer_first_name . ' ' . $customer_last_name),
                        'customer_first_name' => $customer_first_name,
                        'customer_last_name' => $customer_last_name,
                        'customer_email' => $order->get_billing_email(),
                        'customer_phone' => $order->get_billing_phone(),
                        'product_id' => $product_id,
                        'product_name' => $item->get_name(),
                        'product_sku' => $product->get_sku(),
                        'product_attributes' => $this->format_item_attributes($item),
                        'product_attribute_pairs' => $this->get_item_attribute_pairs($item),
                        'suppliers' => $supplier_names,
                        'quantity' => $item->get_quantity(),
                        'total' => wc_price($item->get_total()),
                        'total_raw' => $item->get_total(),
                        'status' => $order->get_status(),
                        'status_name' => wc_get_order_status_name($order->get_status()),
                        'order_url' => get_edit_post_link($order->get_id()),
                        'shipping_street' => $address_components['street'],
                        'shipping_address_line' => $address_components['address_line'],
                        'shipping_address_line_2' => $address_components['address_line_2'],
                        'shipping_zipcode' => $address_components['zipcode'],
                        'shipping_city' => $address_components['city'],
                        'shipping_country' => $address_components['country'],
                        'order_notes' => $order->get_customer_note()
                    );
                }
            }
            
            restore_current_blog();
        }
        
        usort($all_orders, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $all_orders;
    }
    
    /**
     * Get all orders by datetime (for reporting window filtering)
     */
    private function get_all_orders_by_datetime($supplier_filter = '', $datetime_from = '', $datetime_to = '') {
        $all_orders = array();
        $sites = $this->get_main_sites();
        
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            if (!class_exists('WooCommerce')) {
                restore_current_blog();
                continue;
            }
            
            $args = array(
                'limit' => -1,
                'type' => 'shop_order',
                'return' => 'objects'
            );
            
            // Add datetime filter
            if ($datetime_from && $datetime_to) {
                $args['date_created'] = $datetime_from . '...' . $datetime_to;
            }
            
            $orders = wc_get_orders($args);
            
            foreach ($orders as $order) {
                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    $product = $item->get_product();
                    
                    if (!$product) {
                        continue;
                    }
                    
                    // Get suppliers from taxonomy
                    $product_suppliers = wp_get_post_terms($product_id, 'supplier', array('fields' => 'all'));
                    
                    // Filter by supplier if set
                    if ($supplier_filter) {
                        $has_supplier = false;
                        foreach ($product_suppliers as $ps) {
                            if ($ps->slug === $supplier_filter) {
                                $has_supplier = true;
                                break;
                            }
                        }
                        if (!$has_supplier) {
                            continue;
                        }
                    }
                    
                    // Get supplier names
                    $supplier_names = array();
                    foreach ($product_suppliers as $ps) {
                        $supplier_names[] = $ps->name;
                    }
                    
                    $customer_first_name = $order->get_billing_first_name();
                    $customer_last_name = $order->get_billing_last_name();
                    $address_components = $this->extract_order_address_components($order);
                    
                    $all_orders[] = array(
                        'order_id' => $order->get_id(),
                        'site_id' => $site->blog_id,
                        'site_name' => get_bloginfo('name'),
                        'date' => $order->get_date_created()->date('Y-m-d H:i:s'),
                        'customer_name' => trim($customer_first_name . ' ' . $customer_last_name),
                        'customer_first_name' => $customer_first_name,
                        'customer_last_name' => $customer_last_name,
                        'customer_email' => $order->get_billing_email(),
                        'customer_phone' => $order->get_billing_phone(),
                        'product_id' => $product_id,
                        'product_name' => $item->get_name(),
                        'product_sku' => $product->get_sku(),
                        'product_attributes' => $this->format_item_attributes($item),
                        'product_attribute_pairs' => $this->get_item_attribute_pairs($item),
                        'suppliers' => $supplier_names,
                        'quantity' => $item->get_quantity(),
                        'total' => wc_price($item->get_total()),
                        'total_raw' => $item->get_total(),
                        'status' => $order->get_status(),
                        'status_name' => wc_get_order_status_name($order->get_status()),
                        'order_url' => get_edit_post_link($order->get_id()),
                        'shipping_street' => $address_components['street'],
                        'shipping_address_line' => $address_components['address_line'],
                        'shipping_address_line_2' => $address_components['address_line_2'],
                        'shipping_zipcode' => $address_components['zipcode'],
                        'shipping_city' => $address_components['city'],
                        'shipping_country' => $address_components['country'],
                        'order_notes' => $order->get_customer_note()
                    );
                }
            }
            
            restore_current_blog();
        }
        
        usort($all_orders, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $all_orders;
    }
    
    /**
     * Prepare structured address information for exports
     */
    private function extract_order_address_components($order) {
        $address1 = $order->get_shipping_address_1();
        $address2 = $order->get_shipping_address_2();
        $city = $order->get_shipping_city();
        $postcode = $order->get_shipping_postcode();
        $country = $order->get_shipping_country();
        
        // Fallback to billing details if shipping is empty
        if (!$address1 && !$address2 && !$city && !$postcode && !$country) {
            $address1 = $order->get_billing_address_1();
            $address2 = $order->get_billing_address_2();
            $city = $order->get_billing_city();
            $postcode = $order->get_billing_postcode();
            $country = $order->get_billing_country();
        }
        
        list($street, $house_number) = $this->split_street_and_number($address1);
        $full_address_line = trim($street . ' ' . $house_number);
        $full_address_line = $full_address_line !== '' ? $full_address_line : trim((string) $address1);
        
        return array(
            'street' => $street ?: $address1,
            'address_line' => $full_address_line,
            'address_line_2' => $address2,
            'zipcode' => $this->normalize_zipcode($postcode),
            'city' => $city,
            'country' => $country,
        );
    }
    
    /**
     * Split a street line into street name and house number/extra info
     */
    private function split_street_and_number($address_line) {
        $address_line = trim((string) $address_line);
        $street = $address_line;
        $number = '';
        
        if ($address_line === '') {
            return array('', '');
        }
        
        if (preg_match('/^(.+?)\s+(\d.*)$/u', $address_line, $matches)) {
            $street = trim($matches[1]);
            $number = trim($matches[2]);
        } elseif (preg_match('/^(\d+\S*)\s+(.+)$/u', $address_line, $matches)) {
            $street = trim($matches[2]);
            $number = trim($matches[1]);
        }
        
        return array($street, $number);
    }
    
    /**
     * Remove whitespace and normalize zipcode casing
     */
    private function normalize_zipcode($postcode) {
        if (!$postcode) {
            return '';
        }
        
        $clean = preg_replace('/\s+/', '', $postcode);
        return strtoupper($clean);
    }

    /**
     * Keep phone numbers as text in spreadsheet exports (preserve leading zeros)
     */
    private function format_phone_for_csv($phone) {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return '';
        }

        $safe_phone = str_replace('"', '""', $phone);
        return '="' . $safe_phone . '"';
    }

    /**
     * Convert order item attributes/meta to a readable string
     */
    private function format_item_attributes($item) {
        $pairs = $this->get_item_attribute_pairs($item);

        if (empty($pairs)) {
            return '';
        }

        $rendered = array();

        foreach ($pairs as $pair) {
            $label = isset($pair['label']) ? $pair['label'] : '';
            $value = isset($pair['value']) ? $pair['value'] : '';

            if ($label === '' && $value === '') {
                continue;
            }

            $rendered[] = $label !== '' ? ($label . ': ' . $value) : $value;
        }

        return implode('; ', $rendered);
    }

    /**
     * Return attribute pairs (label/value) for an order item
     */
    private function get_item_attribute_pairs($item) {
        if (!$item || !is_object($item) || !method_exists($item, 'get_formatted_meta_data')) {
            return array();
        }

        $pairs = array();
        $seen = array();

        $add_pair = function($label, $value) use (&$pairs, &$seen) {
            $label = trim((string) $label);
            $value = trim((string) $value);

            if ($label === '' && $value === '') {
                return;
            }

            $key = $label . '|' . $value;
            if (isset($seen[$key])) {
                return;
            }

            $seen[$key] = true;
            $pairs[] = array('label' => $label, 'value' => $value);
        };

        // Attributes from product/variation object
        if (method_exists($item, 'get_product')) {
            $product = $item->get_product();

            if ($product && method_exists($product, 'get_attributes')) {
                foreach ($product->get_attributes() as $name => $value) {
                    $label = $this->format_attribute_label($name);
                    $value_text = $this->format_attribute_value($name, $value);

                    if ($value_text === '') {
                        continue;
                    }

                    $add_pair($label, $value_text);
                }
            }
        }

        // Variation attributes stored on the order item (only if available)
        if (method_exists($item, 'get_variation_attributes')) {
            $variation_attributes = $item->get_variation_attributes();

            foreach ($variation_attributes as $name => $value) {
                $label = $this->format_attribute_label($name);
                $value_text = $this->format_attribute_value($name, $value);

                if ($value_text === '') {
                    continue;
                }

                $add_pair($label, $value_text);
            }
        }

        // Non-hidden item meta (e.g., add-ons)
        $meta_data = $item->get_formatted_meta_data('', true);

        foreach ($meta_data as $meta) {
            $key = isset($meta->key) ? (string) $meta->key : '';

            if ($key !== '' && substr($key, 0, 1) === '_') {
                continue;
            }

            $label = isset($meta->display_key) ? wp_strip_all_tags($meta->display_key) : '';
            $value = isset($meta->display_value) ? wp_strip_all_tags($meta->display_value) : '';

            if ($label === '' && $value === '') {
                continue;
            }

            $add_pair($label, $value);
        }

        return $pairs;
    }

    /**
     * Human readable attribute label
     */
    private function format_attribute_label($attribute_name) {
        $name = (string) $attribute_name;
        $name = str_replace('attribute_', '', $name);
        $name = str_replace('pa_', 'pa_', $name); // keep taxonomy prefix if present

        if (function_exists('wc_attribute_label')) {
            return wc_attribute_label($name);
        }

        return ucwords(str_replace(array('-', '_'), ' ', $name));
    }

    /**
     * Human readable attribute value, resolving taxonomy terms when possible
     */
    private function format_attribute_value($attribute_name, $value) {
        // WC_Product_Attribute or objects with get_options
        if (is_object($value) && method_exists($value, 'get_options')) {
            $value = $value->get_options();
        }

        // Arrays of values
        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        $name = (string) $attribute_name;
        $taxonomy = str_replace('attribute_', '', $name);

        if (taxonomy_exists($taxonomy)) {
            $term = get_term_by('slug', $value, $taxonomy);

            if ($term && !is_wp_error($term)) {
                return $term->name;
            }
        }

        return $value;
    }

    /**
     * Flatten attribute pairs into a fixed set of CSV columns (Attribute #/Value #)
     */
    private function flatten_attribute_pairs_for_csv($pairs) {
        $cells = array();
        $pairs = is_array($pairs) ? array_values($pairs) : array();

        for ($i = 0; $i < $this->attribute_column_pairs; $i++) {
            $label = isset($pairs[$i]['label']) ? $pairs[$i]['label'] : '';
            $value = isset($pairs[$i]['value']) ? $pairs[$i]['value'] : '';
            $cells[] = $label;
            $cells[] = $value;
        }

        return $cells;
    }
    
    private function get_order_statuses() {
        $sites = $this->get_main_sites();
        
        if (!empty($sites)) {
            switch_to_blog($sites[0]->blog_id);
            
            if (function_exists('wc_get_order_statuses')) {
                $statuses = wc_get_order_statuses();
                restore_current_blog();
                return $statuses;
            }
            
            restore_current_blog();
        }
        
        return array(
            'wc-pending' => 'Pending payment',
            'wc-processing' => 'Processing',
            'wc-on-hold' => 'On hold',
            'wc-completed' => 'Completed',
            'wc-cancelled' => 'Cancelled',
            'wc-refunded' => 'Refunded',
            'wc-failed' => 'Failed',
        );
    }
    
    private function calculate_total_value($orders) {
        $total = 0;
        foreach ($orders as $order) {
            $total += $order['total_raw'];
        }
        
        // Get currency from main site
        $sites = $this->get_main_sites();
        if (!empty($sites)) {
            switch_to_blog($sites[0]->blog_id);
            $formatted = wc_price($total);
            restore_current_blog();
            return $formatted;
        }
        
        return wc_price($total);
    }
    
    private function count_unique_products($orders) {
        $products = array();
        foreach ($orders as $order) {
            $products[$order['product_id']] = true;
        }
        return count($products);
    }
    
    public function export_orders() {
        check_ajax_referer('nso_export_nonce', 'nonce');
        
        if (!current_user_can('manage_network')) {
            wp_send_json_error('Permission denied');
        }
        
        $supplier = isset($_POST['supplier']) ? sanitize_text_field($_POST['supplier']) : '';
        $site = isset($_POST['site']) ? intval($_POST['site']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        
        $orders = $this->get_all_orders($supplier, $site, $status, $date_from, $date_to);
        
        $filename = 'network-orders-' . date('Y-m-d-His') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fwrite($output, "sep=;\n");
        
        fputcsv($output, array(
            'Order ID',
            'Site',
            'Date',
            'Voornaam',
            'Achternaam',
            'Customer Email',
            'Customer Phone',
            'Adress line',
            'Address line 2 (optional)',
            'Zipcode',
            'City',
            'Country',
            'Product Name',
            'Product SKU',
            'Product Attributes',
            // Attribute pairs (name/value) columns
            ...array_reduce(range(1, $this->attribute_column_pairs), function($carry, $i) {
                $carry[] = 'Attribute #' . $i;
                $carry[] = 'Value #' . $i;
                return $carry;
            }, array()),
            'Suppliers',
            'Quantity',
            'Total',
            'Status',
            'Order URL'
        ), ';');
        
        foreach ($orders as $order) {
            $attribute_pairs = isset($order['product_attribute_pairs']) ? $order['product_attribute_pairs'] : array();

            fputcsv($output, array(
                $order['order_id'],
                $order['site_name'],
                $order['date'],
                isset($order['customer_first_name']) ? $order['customer_first_name'] : '',
                isset($order['customer_last_name']) ? $order['customer_last_name'] : '',
                $order['customer_email'],
                isset($order['customer_phone']) ? $this->format_phone_for_csv($order['customer_phone']) : '',
                isset($order['shipping_address_line']) ? $order['shipping_address_line'] : '',
                isset($order['shipping_address_line_2']) ? $order['shipping_address_line_2'] : '',
                isset($order['shipping_zipcode']) ? $order['shipping_zipcode'] : '',
                isset($order['shipping_city']) ? $order['shipping_city'] : '',
                isset($order['shipping_country']) ? $order['shipping_country'] : '',
                $order['product_name'],
                $order['product_sku'],
                isset($order['product_attributes']) ? $order['product_attributes'] : '',
                ...$this->flatten_attribute_pairs_for_csv($attribute_pairs),
                implode(', ', $order['suppliers']),
                $order['quantity'],
                $order['total_raw'],
                $order['status_name'],
                $order['order_url']
            ), ';');
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Create supplier folder when new supplier is added
     */
    public function create_supplier_folder($term_id, $tt_id) {
        $term = get_term($term_id, 'supplier');
        
        if (!is_wp_error($term)) {
            // Get email from Supplier Order Email plugin meta key
            $supplier_email = get_term_meta($term_id, 'mcisoe_supplier_email', true);
            
            if (!$supplier_email) {
                // Use slug if no email
                $supplier_email = $term->slug;
            }
            
            $upload_dir = wp_upload_dir();
            $supplier_folder = $upload_dir['basedir'] . '/supplier-exports/' . sanitize_file_name($supplier_email);
            
            if (!file_exists($supplier_folder)) {
                wp_mkdir_p($supplier_folder);
                
                // Create .htaccess for security
                $htaccess = $supplier_folder . '/.htaccess';
                file_put_contents($htaccess, "Deny from all\n");
            }
        }
    }
    
    /**
     * Activate cron job
     */
    public function activate_cron() {
        if ($this->is_wp_cron_disabled()) {
            $this->unschedule_cron_job();
            return;
        }

        if (!wp_next_scheduled('nso_daily_supplier_export')) {
            $this->reschedule_cron_job();
        }
    }
    
    /**
     * Reschedule cron job with new time
     */
    private function reschedule_cron_job() {
        if ($this->is_wp_cron_disabled()) {
            $this->unschedule_cron_job();
            return;
        }

        // Remove existing schedule
        $timestamp = wp_next_scheduled('nso_daily_supplier_export');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'nso_daily_supplier_export');
        }
        
        // Get configured time (default 09:00)
        $email_time = get_site_option('nso_email_time', '09:00');
        
        // Schedule for configured time Netherlands time (CET/CEST)
        // Netherlands is UTC+1 (CET) or UTC+2 (CEST in summer)
        // We'll use Amsterdam timezone
        
        $timezone = new DateTimeZone('Europe/Amsterdam');
        $now = new DateTime('now', $timezone);
        $target = new DateTime('today ' . $email_time, $timezone);
        
        // If it's already past the scheduled time today, schedule for tomorrow
        if ($now > $target) {
            $target->modify('+1 day');
        }
        
        // Convert to UTC timestamp for WordPress
        $target->setTimezone(new DateTimeZone('UTC'));
        $timestamp = $target->getTimestamp();
        
        wp_schedule_event($timestamp, 'daily', 'nso_daily_supplier_export');
    }

    /**
     * Self-heal the cron schedule if it was removed/cleared on the live site
     */
    public function ensure_cron_schedule() {
        if ($this->is_wp_cron_disabled()) {
            return;
        }

        $check_flag = get_site_transient('nso_cron_check_recent');

        if ($check_flag) {
            return;
        }

        set_site_transient('nso_cron_check_recent', 1, HOUR_IN_SECONDS);

        if (!wp_next_scheduled('nso_daily_supplier_export')) {
            $this->log_event('Cron event missing; rescheduling daily supplier export.');
            $this->reschedule_cron_job();
        }
    }
    
    /**
     * Deactivate cron job
     */
    public function deactivate_cron() {
        $timestamp = wp_next_scheduled('nso_daily_supplier_export');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'nso_daily_supplier_export');
        }
    }
    
    /**
     * Daily supplier export and email
     */
    public function daily_supplier_export() {
        $this->export_and_email_suppliers(null, 'automated');
    }
    
    /**
     * Manual export and email trigger
     */
    public function manual_export_email() {
        check_ajax_referer('nso_export_nonce', 'nonce');
        
        if (!current_user_can('manage_network')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to   = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';

        $date_range = null;

        if (!empty($date_from) || !empty($date_to)) {
            $date_range = $this->build_manual_date_range($date_from, $date_to);

            if (empty($date_range['send'])) {
                $message = isset($date_range['message']) ? $date_range['message'] : __('Please provide a valid date range.', 'network-supplier-orders');
                wp_send_json_error(array('message' => $message));
            }
        }
        
        $result = $this->export_and_email_suppliers($date_range);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Export orders for each supplier and email them
     */
    private function export_and_email_suppliers($date_range = null, $run_context = 'manual') {
        $date_range = $date_range ?: $this->get_supplier_email_date_range();
        $lock_key = 'nso_email_export_lock';
        $lock_ttl = 15 * MINUTE_IN_SECONDS;
        $is_automated = ($run_context === 'automated');

        if ($is_automated) {
            $skip_info = $this->should_skip_automated_email_run($date_range);

            if ($skip_info['skip']) {
                return array(
                    'success' => true,
                    'suppliers_processed' => 0,
                    'emails_sent' => 0,
                    'emails_success' => array(),
                    'errors' => array(),
                    'message' => $skip_info['message'],
                );
            }
        }

        if (!$date_range['send']) {
            return array(
                'success' => true,
                'suppliers_processed' => 0,
                'emails_sent' => 0,
                'emails_success' => array(),
                'errors' => array(),
                'message' => $date_range['message'],
            );
        }

        if (get_site_transient($lock_key)) {
            return array(
                'success' => false,
                'suppliers_processed' => 0,
                'emails_sent' => 0,
                'emails_success' => array(),
                'errors' => array('Another supplier email run is already in progress.'),
                'message' => __('A supplier email job is already running. Please try again shortly.', 'network-supplier-orders'),
            );
        }

        set_site_transient($lock_key, 1, $lock_ttl);

        $sites = $this->get_main_sites();
        $all_suppliers = array();
        
        // Collect all unique suppliers from all sites
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            if (taxonomy_exists('supplier')) {
                $site_suppliers = get_terms(array(
                    'taxonomy' => 'supplier',
                    'hide_empty' => false,
                ));
                
                if (!is_wp_error($site_suppliers)) {
                    foreach ($site_suppliers as $supplier) {
                        if (!isset($all_suppliers[$supplier->slug])) {
                            $all_suppliers[$supplier->slug] = $supplier;
                        }
                    }
                }
            }
            
            restore_current_blog();
        }
        
        $results = array(
            'success' => true,
            'suppliers_processed' => 0,
            'emails_sent' => 0,
            'emails_success' => array(),
            'errors' => array(),
            'date_from' => $date_range['from'],
            'date_to' => $date_range['to'],
            'range_description' => $date_range['description'],
        );

        $range_label = isset($date_range['description']) ? $date_range['description'] : 'date window not set';
        $this->log_event(sprintf('Starting supplier email run for %s.', $range_label));

        try {
            foreach ($all_suppliers as $supplier) {
                $results['suppliers_processed']++;
                
                // Check if supplier email is enabled
                if (!$this->is_supplier_email_enabled($supplier->slug)) {
                    continue; // Skip this supplier - email disabled
                }
                
                // Get supplier email from Supplier Order Email plugin meta key
                $supplier_email = '';
                foreach ($sites as $site) {
                    switch_to_blog($site->blog_id);
                    // Use the correct meta key from Supplier Order Email plugin
                    $email = get_term_meta($supplier->term_id, 'mcisoe_supplier_email', true);
                    if ($email) {
                        $supplier_email = $email;
                        restore_current_blog();
                        break;
                    }
                    restore_current_blog();
                }
                
                if (!$supplier_email) {
                    $results['errors'][] = "No email found for supplier: {$supplier->name}";
                    continue;
                }
                
                // Get orders for this supplier within the configured reporting window
                $orders = $this->get_all_orders_by_datetime(
                    $supplier->slug,
                    $date_range['from'],
                    $date_range['to']
                );
                
                if (empty($orders)) {
                    // Don't treat empty orders as an error for enabled suppliers
                    continue;
                }
                
                // Create CSV file
                $file_result = $this->create_supplier_export_file($supplier, $supplier_email, $orders);
                
                if (!$file_result['success']) {
                    $results['errors'][] = $file_result['error'];
                    continue;
                }
                
                // Send email
                $email_result = $this->send_supplier_email(
                    $supplier,
                    $supplier_email,
                    $file_result['file_path'],
                    count($orders),
                    $date_range
                );
                
                if ($email_result) {
                    $results['emails_sent']++;
                    $results['emails_success'][] = array(
                        'supplier_name' => $supplier->name,
                        'supplier_email' => $supplier_email,
                        'order_count' => count($orders),
                        'file_name' => $file_result['filename'],
                        'range_description' => $date_range['description'],
                    );
                } else {
                    $results['errors'][] = "Failed to send email to: {$supplier->name} ({$supplier_email})";
                }
            }
        } catch (Throwable $e) {
            $results['success'] = false;
            $results['errors'][] = 'Unexpected error while emailing suppliers: ' . $e->getMessage();
            $this->log_event('Supplier email run failed: ' . $e->getMessage());
        } finally {
            delete_site_transient($lock_key);

            if ($is_automated) {
                $this->mark_automated_email_run($date_range, $results);
            }

            $this->log_event(sprintf(
                'Finished supplier email run (%d suppliers processed, %d emails sent, %d errors).',
                $results['suppliers_processed'],
                $results['emails_sent'],
                count($results['errors'])
            ));
        }

        return $results;
    }

    /**
     * Build a unique key for a given date window
     */
    private function build_date_window_key($date_range) {
        if (!is_array($date_range) || empty($date_range['from']) || empty($date_range['to'])) {
            return '';
        }

        return md5($date_range['from'] . '|' . $date_range['to']);
    }

    /**
     * Determine if the automated email for the current date window already ran
     */
    private function should_skip_automated_email_run($date_range) {
        $state = get_site_option('nso_last_automated_supplier_email', array());
        $current_key = $this->build_date_window_key($date_range);

        if (!$current_key || !is_array($state) || empty($state['window_key'])) {
            return array(
                'skip' => false,
                'message' => '',
            );
        }

        if ($state['window_key'] !== $current_key) {
            return array(
                'skip' => false,
                'message' => '',
            );
        }

        $ran_at = isset($state['ran_at']) ? $state['ran_at'] : '';
        $description = isset($date_range['description']) ? $date_range['description'] : '';

        $message = __('Automated supplier emails have already been sent for this date range.', 'network-supplier-orders');

        if ($description && $ran_at) {
            $message = sprintf(
                __('Automated supplier emails already sent for "%s" at %s.', 'network-supplier-orders'),
                $description,
                $ran_at
            );
        } elseif ($ran_at) {
            $message = sprintf(
                __('Automated supplier emails already sent at %s for this date range.', 'network-supplier-orders'),
                $ran_at
            );
        }

        $this->log_event('Skipped automated supplier email run because it already completed for this date range.');

        return array(
            'skip' => true,
            'message' => $message,
        );
    }

    /**
     * Store the last automated email run to avoid duplicate cron sends
     */
    private function mark_automated_email_run($date_range, $results) {
        $state = array(
            'window_key' => $this->build_date_window_key($date_range),
            'from' => isset($date_range['from']) ? $date_range['from'] : '',
            'to' => isset($date_range['to']) ? $date_range['to'] : '',
            'description' => isset($date_range['description']) ? $date_range['description'] : '',
            'ran_at' => current_time('mysql'),
            'emails_sent' => isset($results['emails_sent']) ? intval($results['emails_sent']) : 0,
            'suppliers_processed' => isset($results['suppliers_processed']) ? intval($results['suppliers_processed']) : 0,
        );

        if (!$state['window_key']) {
            return;
        }

        update_site_option('nso_last_automated_supplier_email', $state);
    }

    /**
     * Determine the reporting window for supplier emails
     */
    private function get_supplier_email_date_range() {
        $timezone = new DateTimeZone('Europe/Amsterdam');
        $now = new DateTime('now', $timezone);
        $day_of_week = (int) $now->format('N'); // 1 (Mon) - 7 (Sun)

        if ($day_of_week >= 6) {
            return array(
                'send' => false,
                'message' => __('Supplier emails are paused on Saturdays and Sundays.', 'network-supplier-orders'),
                'description' => '',
                'from' => '',
                'to' => '',
            );
        }

        // Catch up after weekend: Monday run should include Friday + weekend so nothing is lost.
        if ($day_of_week === 1) {
            $start = clone $now;
            $start->modify('-3 days'); // Friday
            $start->setTime(0, 0, 0);

            $end = clone $now;
            $end->modify('-1 day'); // Sunday
            $end->setTime(23, 59, 59);
        } else {
            $start = clone $now;
            $start->modify('-1 day');
            $start->setTime(0, 0, 0);

            $end = clone $now;
            $end->modify('-1 day');
            $end->setTime(23, 59, 59);
        }

        $description = sprintf(
            __('Orders from %s to %s', 'network-supplier-orders'),
            $start->format('l Y-m-d'),
            $end->format('l Y-m-d')
        );

        return array(
            'send' => true,
            'from' => $start->format('Y-m-d H:i:s'),
            'to' => $end->format('Y-m-d H:i:s'),
            'description' => $description,
        );
    }

    /**
     * Normalize manual date input to YYYY-MM-DD supporting common formats (YYYY-MM-DD, DD-MM-YYYY, DD/MM/YYYY)
     */
    private function normalize_manual_date_input($value) {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        // Already ISO format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // Support DD-MM-YYYY, DD/MM/YYYY, or MM-DD-YYYY, MM/DD/YYYY (disambiguate by value)
        if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $value, $m)) {
            $part1 = intval($m[1], 10);
            $part2 = intval($m[2], 10);
            $year = $m[3];

            // If the first part is clearly a day (greater than 12), treat as DD-MM.
            if ($part1 > 12 && $part2 <= 12) {
                return "{$year}-" . str_pad((string) $part2, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string) $part1, 2, '0', STR_PAD_LEFT);
            }

            // If the second part is clearly a day, treat as MM-DD.
            if ($part2 > 12 && $part1 <= 12) {
                return "{$year}-" . str_pad((string) $part1, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string) $part2, 2, '0', STR_PAD_LEFT);
            }

            // Default to DD-MM interpretation.
            return "{$year}-" . str_pad((string) $part2, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string) $part1, 2, '0', STR_PAD_LEFT);
        }

        return $value;
    }

    /**
     * Build a custom date range for manual email sends
     */
    private function build_manual_date_range($date_from, $date_to) {
        $date_from = trim((string) $date_from);
        $date_to = trim((string) $date_to);

        // Normalize date part while allowing optional time (HH:MM)
        $parse_datetime = function($input, $is_start) {
            $input = trim((string) $input);

            // Extract date and optional time
            if (preg_match('/^(\\d{4}-\\d{2}-\\d{2})(?:\\s+(\\d{1,2}):(\\d{2}))?$/', $input, $m)) {
                $date = $m[1];
                $hour = isset($m[2]) ? (int) $m[2] : ($is_start ? 0 : 23);
                $minute = isset($m[3]) ? (int) $m[3] : ($is_start ? 0 : 59);
                $second = $is_start ? 0 : 59;
                return [$date, sprintf('%02d:%02d:%02d', $hour, $minute, $second)];
            }

            // Fallback: normalize date-only formats
            $normalized_date = $this->normalize_manual_date_input($input);
            if ($normalized_date === '') {
                return ['', ''];
            }

            return [$normalized_date, $is_start ? '00:00:00' : '23:59:59'];
        };

        if ($date_from === '' && $date_to === '') {
            return array(
                'send' => false,
                'message' => __('Please select a start and end date for this email.', 'network-supplier-orders'),
            );
        }

        // If one side missing, mirror the other
        if ($date_from === '') {
            $date_from = $date_to;
        }

        if ($date_to === '') {
            $date_to = $date_from;
        }

        [$from_date, $from_time] = $parse_datetime($date_from, true);
        [$to_date, $to_time] = $parse_datetime($date_to, false);

        if ($from_date === '' || $to_date === '') {
            return array(
                'send' => false,
                'message' => __('Unable to read the selected dates. Please try again.', 'network-supplier-orders'),
            );
        }

        $timezone = new DateTimeZone('Europe/Amsterdam');
        $start = DateTime::createFromFormat('Y-m-d H:i:s', $from_date . ' ' . $from_time, $timezone);
        $end = DateTime::createFromFormat('Y-m-d H:i:s', $to_date . ' ' . $to_time, $timezone);

        if (!$start || !$end) {
            return array(
                'send' => false,
                'message' => __('Unable to read the selected dates. Please try again.', 'network-supplier-orders'),
            );
        }

        if ($start > $end) {
            return array(
                'send' => false,
                'message' => __('The start date cannot be after the end date.', 'network-supplier-orders'),
            );
        }

        $description = $start->format('Y-m-d') === $end->format('Y-m-d')
            ? sprintf(__('Orders for %s', 'network-supplier-orders'), $start->format('l Y-m-d'))
            : sprintf(
                __('Orders from %s to %s', 'network-supplier-orders'),
                $start->format('l Y-m-d'),
                $end->format('l Y-m-d')
            );

        return array(
            'send' => true,
            'from' => $start->format('Y-m-d H:i:s'),
            'to' => $end->format('Y-m-d H:i:s'),
            'description' => $description,
        );
    }
    
    /**
     * Create export file for supplier (CSV format)
     */
    private function create_supplier_export_file($supplier, $supplier_email, $orders) {
        $upload_dir = wp_upload_dir();
        $supplier_folder = $upload_dir['basedir'] . '/supplier-exports/' . sanitize_file_name($supplier_email);
        
        // Create folder if it doesn't exist
        if (!file_exists($supplier_folder)) {
            wp_mkdir_p($supplier_folder);
            file_put_contents($supplier_folder . '/.htaccess', "Deny from all\n");
        }
        
        // Generate filename with date - CSV format
        $filename = 'orders-' . sanitize_file_name($supplier->slug) . '-' . date('Y-m-d-His') . '.csv';
        $file_path = $supplier_folder . '/' . $filename;
        
        // Create CSV
        $file = fopen($file_path, 'w');
        
        if (!$file) {
            return array('success' => false, 'error' => "Could not create file for {$supplier->name}");
        }

        // Match the network export encoding so Excel opens emailed files the same way
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
        fwrite($file, "sep=;\n");
        
        // Add headers
        fputcsv($file, array(
            'Order ID',
            'Date',
            'Voornaam',
            'Achternaam',
            'Customer Email',
            'Customer Phone',
            'Adress line',
            'Address line 2 (optional)',
            'Zipcode',
            'City',
            'Country',
            'Product Name',
            'Product SKU',
            'Product Attributes',
            // Attribute pairs (name/value) columns
            ...array_reduce(range(1, $this->attribute_column_pairs), function($carry, $i) {
                $carry[] = 'Attribute #' . $i;
                $carry[] = 'Value #' . $i;
                return $carry;
            }, array()),
            'Quantity',
            'Total',
            'Status',
            'Order Notes'
        ), ';');
        
        // Add data
        foreach ($orders as $order) {
            $attribute_pairs = isset($order['product_attribute_pairs']) ? $order['product_attribute_pairs'] : array();

            fputcsv($file, array(
                $order['order_id'],
                $order['date'],
                isset($order['customer_first_name']) ? $order['customer_first_name'] : '',
                isset($order['customer_last_name']) ? $order['customer_last_name'] : '',
                $order['customer_email'],
                isset($order['customer_phone']) ? $this->format_phone_for_csv($order['customer_phone']) : '',
                isset($order['shipping_address_line']) ? $order['shipping_address_line'] : '',
                isset($order['shipping_address_line_2']) ? $order['shipping_address_line_2'] : '',
                isset($order['shipping_zipcode']) ? $order['shipping_zipcode'] : '',
                isset($order['shipping_city']) ? $order['shipping_city'] : '',
                isset($order['shipping_country']) ? $order['shipping_country'] : '',
                $order['product_name'],
                $order['product_sku'],
                isset($order['product_attributes']) ? $order['product_attributes'] : '',
                ...$this->flatten_attribute_pairs_for_csv($attribute_pairs),
                $order['quantity'],
                $order['total_raw'],
                $order['status_name'],
                isset($order['order_notes']) ? $order['order_notes'] : ''
            ), ';');
        }
        
        fclose($file);
        
        return array('success' => true, 'file_path' => $file_path, 'filename' => $filename);
    }
    
    /**
     * Supplier settings page
     */
    public function supplier_settings_page() {
        // Handle form submission
        if (isset($_POST['nso_save_settings']) && check_admin_referer('nso_supplier_settings')) {
            $enabled_suppliers = isset($_POST['enabled_suppliers']) ? $_POST['enabled_suppliers'] : array();
            update_site_option('nso_enabled_suppliers', $enabled_suppliers);
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'network-supplier-orders') . '</p></div>';
        }
        
        // Get all suppliers
        $sites = $this->get_main_sites();
        $all_suppliers = array();
        
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            if (taxonomy_exists('supplier')) {
                $site_suppliers = get_terms(array(
                    'taxonomy' => 'supplier',
                    'hide_empty' => false,
                ));
                
                if (!is_wp_error($site_suppliers)) {
                    foreach ($site_suppliers as $supplier) {
                        if (!isset($all_suppliers[$supplier->slug])) {
                            // Get supplier email
                            $supplier_email = get_term_meta($supplier->term_id, 'mcisoe_supplier_email', true);
                            $supplier->email = $supplier_email ? $supplier_email : 'No email set';
                            $all_suppliers[$supplier->slug] = $supplier;
                        }
                    }
                }
            }
            
            restore_current_blog();
        }
        
        // Get current enabled suppliers
        $enabled_suppliers = get_site_option('nso_enabled_suppliers', array());
        
        ?>
        <div class="wrap">
            <h1><?php _e('Supplier Email Settings', 'network-supplier-orders'); ?></h1>
            <p><?php _e('Enable or disable email notifications for each supplier. Only enabled suppliers will receive daily order exports.', 'network-supplier-orders'); ?></p>
            
            <div class="notice notice-info inline" style="margin: 15px 0;">
                <p>
                    <strong><?php _e('How it works:', 'network-supplier-orders'); ?></strong>
                </p>
                <ul style="margin-left: 20px; list-style: disc;">
                    <li><?php _e('Toggle ON: Supplier will receive automated daily emails at 9:00 PM (only if they have orders that day)', 'network-supplier-orders'); ?></li>
                    <li><?php _e('Toggle OFF: Supplier will NOT receive automated emails', 'network-supplier-orders'); ?></li>
                    <li><?php _e('Send Email Now: Manually send today\'s orders to any supplier immediately (works regardless of toggle setting)', 'network-supplier-orders'); ?></li>
                </ul>
            </div>
            
            <?php if (empty($all_suppliers)): ?>
                <div class="notice notice-warning">
                    <p><strong><?php _e('No suppliers found!', 'network-supplier-orders'); ?></strong></p>
                </div>
            <?php else: ?>
                <form method="post" action="">
                    <?php wp_nonce_field('nso_supplier_settings'); ?>
                    
                    <table class="wp-list-table widefat fixed striped nso-supplier-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;"><?php _e('Enabled', 'network-supplier-orders'); ?></th>
                                <th><?php _e('Supplier Name', 'network-supplier-orders'); ?></th>
                                <th><?php _e('Email', 'network-supplier-orders'); ?></th>
                                <th style="width: 260px;"><?php _e('Test Email', 'network-supplier-orders'); ?></th>
                                <th style="width: 320px;"><?php _e('Send Email Now', 'network-supplier-orders'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_suppliers as $supplier): ?>
                                <tr>
                                    <td class="nso-center-cell">
                                        <label class="nso-toggle-switch">
                                            <input 
                                                type="checkbox" 
                                                name="enabled_suppliers[]" 
                                                value="<?php echo esc_attr($supplier->slug); ?>"
                                                <?php checked(in_array($supplier->slug, $enabled_suppliers)); ?>
                                            >
                                            <span class="nso-toggle-slider"></span>
                                        </label>
                                    </td>
                                    <td><strong><?php echo esc_html($supplier->name); ?></strong></td>
                                    <td><?php echo esc_html($supplier->email); ?></td>
                                    <?php if ($supplier->email && $supplier->email !== 'No email set'): ?>
                                        <td class="nso-test-email-cell">
                                            <div class="nso-test-email-block">
                                                <span class="nso-field-label"><?php _e('Test email recipient', 'network-supplier-orders'); ?></span>
                                                <div class="nso-test-email-actions">
                                                    <input
                                                        type="email"
                                                        class="regular-text nso-test-email"
                                                        placeholder="<?php esc_attr_e('you@example.com', 'network-supplier-orders'); ?>"
                                                    >
                                                    <button
                                                        type="button"
                                                        class="button nso-send-test-email"
                                                        data-supplier-slug="<?php echo esc_attr($supplier->slug); ?>"
                                                        data-supplier-name="<?php echo esc_attr($supplier->name); ?>"
                                                    >
                                                        <span class="dashicons dashicons-admin-site-alt3"></span>
                                                        <?php _e('Send Test Email', 'network-supplier-orders'); ?>
                                                    </button>
                                                </div>
                                                <p class="nso-help-text"><?php _e('Preview the export in your inbox. Respects the optional date range.', 'network-supplier-orders'); ?></p>
                                            </div>
                                        </td>
                                        <td class="nso-send-now-cell">
                                            <div class="nso-date-inputs">
                                                <label>
                                                    <?php _e('From', 'network-supplier-orders'); ?>
                                                    <input 
                                                        type="date" 
                                                        class="nso-manual-range-from" 
                                                        name="manual_from_<?php echo esc_attr($supplier->slug); ?>"
                                                    >
                                                    <input
                                                        type="time"
                                                        class="nso-manual-range-from-time"
                                                        name="manual_from_time_<?php echo esc_attr($supplier->slug); ?>"
                                                        step="60"
                                                    >
                                                </label>
                                                <label>
                                                    <?php _e('To', 'network-supplier-orders'); ?>
                                                    <input 
                                                        type="date" 
                                                        class="nso-manual-range-to" 
                                                        name="manual_to_<?php echo esc_attr($supplier->slug); ?>"
                                                    >
                                                    <input
                                                        type="time"
                                                        class="nso-manual-range-to-time"
                                                        name="manual_to_time_<?php echo esc_attr($supplier->slug); ?>"
                                                        step="60"
                                                    >
                                                </label>
                                            </div>
                                            <div class="nso-send-button-row">
                                                <button 
                                                    type="button" 
                                                    class="button button-secondary nso-send-individual-email" 
                                                    data-supplier-slug="<?php echo esc_attr($supplier->slug); ?>"
                                                    data-supplier-name="<?php echo esc_attr($supplier->name); ?>"
                                                >
                                                    <span class="dashicons dashicons-email"></span>
                                                    <?php _e('Send Email Now', 'network-supplier-orders'); ?>
                                                </button>
                                                <span class="nso-email-status" data-supplier="<?php echo esc_attr($supplier->slug); ?>" style="display: none;"></span>
                                            </div>
                                            <div class="nso-manual-email-note">
                                                <?php _e('Leave dates empty to use the default previous-business-day window. Use the test email to receive the export yourself without emailing the supplier.', 'network-supplier-orders'); ?>
                                            </div>
                                        </td>
                                    <?php else: ?>
                                        <td class="nso-test-email-cell nso-disabled-cell">
                                            <span class="nso-no-email"><?php _e('No email configured', 'network-supplier-orders'); ?></span>
                                            <p class="nso-help-text"><?php _e('Add an email to enable testing for this supplier.', 'network-supplier-orders'); ?></p>
                                        </td>
                                        <td class="nso-send-now-cell nso-disabled-cell">
                                            <button type="button" class="button button-secondary" disabled>
                                                <span class="dashicons dashicons-email"></span>
                                                <?php _e('Send Email Now', 'network-supplier-orders'); ?>
                                            </button>
                                            <p class="nso-help-text"><?php _e('Set an email address first.', 'network-supplier-orders'); ?></p>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" name="nso_save_settings" class="button button-primary">
                            <?php _e('Save Settings', 'network-supplier-orders'); ?>
                        </button>
                    </p>
                </form>
            <?php endif; ?>
            
            <style>
                .nso-toggle-switch {
                    position: relative;
                    display: inline-block;
                    width: 50px;
                    height: 24px;
                }
                
                .nso-toggle-switch input {
                    opacity: 0;
                    width: 0;
                    height: 0;
                }
                
                .nso-toggle-slider {
                    position: absolute;
                    cursor: pointer;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: #ccc;
                    transition: .4s;
                    border-radius: 24px;
                }
                
                .nso-toggle-slider:before {
                    position: absolute;
                    content: "";
                    height: 18px;
                    width: 18px;
                    left: 3px;
                    bottom: 3px;
                    background-color: white;
                    transition: .4s;
                    border-radius: 50%;
                }
                
                .nso-toggle-switch input:checked + .nso-toggle-slider {
                    background-color: #2271b1;
                }
                
                .nso-toggle-switch input:checked + .nso-toggle-slider:before {
                    transform: translateX(26px);
                }

                .nso-supplier-table {
                    table-layout: fixed;
                }

                .nso-supplier-table th,
                .nso-supplier-table td {
                    vertical-align: top;
                }

                .nso-supplier-table th:nth-child(1) {
                    width: 70px;
                }

                .nso-supplier-table th:nth-child(4) {
                    width: 260px;
                }

                .nso-supplier-table th:nth-child(5) {
                    width: 320px;
                }

                .nso-center-cell {
                    text-align: center;
                }

                .nso-test-email-cell,
                .nso-send-now-cell {
                    padding-top: 14px;
                }

                .nso-test-email-block,
                .nso-send-now-cell {
                    display: flex;
                    flex-direction: column;
                    gap: 8px;
                }

                .nso-field-label {
                    font-weight: 600;
                    font-size: 12px;
                    color: #333;
                }

                .nso-test-email-actions {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    align-items: center;
                }

                .nso-test-email-actions .regular-text {
                    min-width: 200px;
                }

                .nso-date-inputs {
                    display: flex;
                    gap: 10px;
                    flex-wrap: wrap;
                }

                .nso-date-inputs label {
                    display: flex;
                    flex-direction: column;
                    font-size: 12px;
                    color: #555;
                    gap: 4px;
                }

                .nso-date-inputs input[type="date"] {
                    min-width: 140px;
                }

                .nso-date-inputs input[type="time"] {
                    min-width: 120px;
                }

                .nso-send-button-row {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    flex-wrap: wrap;
                }

                .nso-email-status {
                    display: none;
                    margin-left: 0;
                }

                .nso-manual-email-note,
                .nso-help-text {
                    font-size: 11px;
                    color: #555;
                    margin: 0;
                    line-height: 1.5;
                }

                .nso-disabled-cell {
                    background: #f6f7f9;
                    color: #7a7a7a;
                }

                .nso-disabled-cell .button[disabled] {
                    opacity: 0.6;
                }

                .nso-no-email {
                    font-weight: 600;
                    color: #d63638;
                }

                @media screen and (max-width: 1100px) {
                    .nso-supplier-table {
                        display: block;
                        overflow-x: auto;
                    }
                }
            </style>
        </div>
        <?php
    }
    
    /**
     * Email schedule configuration page
     */
    public function email_schedule_page() {
        // Handle form submission
        if (isset($_POST['nso_save_schedule']) && check_admin_referer('nso_email_schedule')) {
            $email_time = isset($_POST['email_time']) ? sanitize_text_field($_POST['email_time']) : '09:00';
            $disable_wp_cron = isset($_POST['disable_wp_cron']) ? true : false;
            
            // Validate time format (HH:MM)
            if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $email_time)) {
                update_site_option('nso_email_time', $email_time);
                update_site_option('nso_disable_wp_cron', $disable_wp_cron);
                
                if ($disable_wp_cron) {
                    $this->unschedule_cron_job();
                } else {
                    // Reschedule cron with new time
                    $this->reschedule_cron_job();
                }
                
                echo '<div class="notice notice-success"><p>' . __('Email schedule updated successfully!', 'network-supplier-orders') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __('Invalid time format. Please use HH:MM format (e.g., 09:00)', 'network-supplier-orders') . '</p></div>';
            }
        }
        
        $email_time = get_site_option('nso_email_time', '09:00');
        $next_scheduled = wp_next_scheduled('nso_daily_supplier_export');
        $cron_disabled = $this->is_wp_cron_disabled();
        $webhook_key = $this->get_webhook_key();
        $webhook_url = esc_url(add_query_arg(
            array('key' => $webhook_key),
            rest_url('nso/v1/email-suppliers')
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Automated Email Schedule', 'network-supplier-orders'); ?></h1>
            <p><?php _e('Configure the time when automated supplier emails will be sent daily (Netherlands Time - Europe/Amsterdam).', 'network-supplier-orders'); ?></p>
            
            <div class="notice notice-info inline" style="margin: 15px 0;">
                <p>
                    <strong><?php _e('How it works:', 'network-supplier-orders'); ?></strong>
                </p>
                <ul style="margin-left: 20px; list-style: disc;">
                    <li><?php _e('Set your preferred time for automated emails', 'network-supplier-orders'); ?></li>
                    <li><?php _e('Time is in Netherlands timezone (Europe/Amsterdam)', 'network-supplier-orders'); ?></li>
                    <li><?php _e('Emails run Monday through Friday at the selected time (Monday covers Saturday & Sunday orders; Tuesday-Friday cover the previous day)', 'network-supplier-orders'); ?></li>
                    <li><?php _e('Use 24-hour format (e.g., 09:00 for 9 AM, 21:00 for 9 PM)', 'network-supplier-orders'); ?></li>
                </ul>
            </div>
            
            <?php if ($cron_disabled): ?>
                <div class="notice notice-warning inline">
                    <p><strong><?php _e('WordPress cron is disabled for supplier emails. Only external/manual triggers will run emails.', 'network-supplier-orders'); ?></strong></p>
                </div>
            <?php elseif ($next_scheduled): ?>
                <div class="notice notice-success inline">
                    <p>
                        <?php 
                        $timezone = new DateTimeZone('Europe/Amsterdam');
                        $next_run = new DateTime('@' . $next_scheduled);
                        $next_run->setTimezone($timezone);
                        printf(
                            __('Next scheduled email: <strong>%s</strong>', 'network-supplier-orders'),
                            $next_run->format('l, F j, Y \a\t H:i:s')
                        );
                        ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning inline">
                    <p><strong><?php _e('No automated email scheduled! Please save the schedule below to activate it.', 'network-supplier-orders'); ?></strong></p>
                </div>
            <?php endif; ?>

            <div class="notice notice-info inline" style="margin: 15px 0;">
                <p>
                    <strong><?php _e('External trigger URL', 'network-supplier-orders'); ?></strong><br>
                    <?php printf(
                        __('Ping this URL (GET) to run the enabled supplier email job immediately: <code>%s</code>', 'network-supplier-orders'),
                        $webhook_url
                    ); ?>
                </p>
                <p style="margin-top: 8px;">
                    <?php _e('Optional query params: date_from=YYYY-MM-DD & date_to=YYYY-MM-DD to override the default date window. Keep this URL secret and rotate the key in the database if needed.', 'network-supplier-orders'); ?>
                </p>
            </div>
            
            <form method="post" action="" style="margin-top: 20px;">
                <?php wp_nonce_field('nso_email_schedule'); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="email_time"><?php _e('Email Time', 'network-supplier-orders'); ?></label>
                            </th>
                            <td>
                                <input 
                                    type="time" 
                                    id="email_time" 
                                    name="email_time" 
                                    value="<?php echo esc_attr($email_time); ?>" 
                                    class="regular-text"
                                    required
                                >
                                <p class="description">
                                    <?php _e('Time in Netherlands timezone (Europe/Amsterdam). Use 24-hour format.', 'network-supplier-orders'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="disable_wp_cron"><?php _e('Disable WP Cron Emails', 'network-supplier-orders'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input 
                                        type="checkbox" 
                                        id="disable_wp_cron" 
                                        name="disable_wp_cron" 
                                        value="1" 
                                        <?php checked($cron_disabled); ?>
                                    >
                                    <?php _e('I run supplier emails via an external cron/URL; do not schedule WordPress cron for this plugin.', 'network-supplier-orders'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('When enabled, the plugin will not schedule or run its daily WP cron job. Use the external URL above to trigger emails on your own schedule.', 'network-supplier-orders'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <button type="submit" name="nso_save_schedule" class="button button-primary">
                        <?php _e('Save Schedule & Reschedule Cron', 'network-supplier-orders'); ?>
                    </button>
                </p>
            </form>
            
            <hr>
            
            <h2><?php _e('Quick Time Examples', 'network-supplier-orders'); ?></h2>
            <table class="widefat" style="max-width: 600px;">
                <thead>
                    <tr>
                        <th><?php _e('Time', 'network-supplier-orders'); ?></th>
                        <th><?php _e('Description', 'network-supplier-orders'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>09:00</code></td>
                        <td><?php _e('9:00 AM (Default)', 'network-supplier-orders'); ?></td>
                    </tr>
                    <tr>
                        <td><code>08:00</code></td>
                        <td><?php _e('8:00 AM', 'network-supplier-orders'); ?></td>
                    </tr>
                    <tr>
                        <td><code>12:00</code></td>
                        <td><?php _e('12:00 PM (Noon)', 'network-supplier-orders'); ?></td>
                    </tr>
                    <tr>
                        <td><code>18:00</code></td>
                        <td><?php _e('6:00 PM', 'network-supplier-orders'); ?></td>
                    </tr>
                    <tr>
                        <td><code>21:00</code></td>
                        <td><?php _e('9:00 PM', 'network-supplier-orders'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Save supplier settings via AJAX
     */
    public function save_supplier_settings() {
        check_ajax_referer('nso_export_nonce', 'nonce');
        
        if (!current_user_can('manage_network')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $enabled_suppliers = isset($_POST['enabled_suppliers']) ? $_POST['enabled_suppliers'] : array();
        update_site_option('nso_enabled_suppliers', $enabled_suppliers);
        
        wp_send_json_success(array('message' => 'Settings saved successfully'));
    }
    
    /**
     * Save email schedule via AJAX
     */
    public function save_email_schedule() {
        check_ajax_referer('nso_export_nonce', 'nonce');
        
        if (!current_user_can('manage_network')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $email_time = isset($_POST['email_time']) ? sanitize_text_field($_POST['email_time']) : '09:00';
        $disable_wp_cron = isset($_POST['disable_wp_cron']) ? true : false;
        
        // Validate time format
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $email_time)) {
            wp_send_json_error(array('message' => 'Invalid time format'));
        }
        
        update_site_option('nso_email_time', $email_time);
        update_site_option('nso_disable_wp_cron', $disable_wp_cron);
        
        if ($disable_wp_cron) {
            $this->unschedule_cron_job();
        } else {
            // Reschedule cron with new time
            $this->reschedule_cron_job();
        }
        
        wp_send_json_success(array(
            'message' => $disable_wp_cron
                ? __('Schedule saved; WP cron emails disabled (use external trigger).', 'network-supplier-orders')
                : __('Schedule updated and cron rescheduled', 'network-supplier-orders')
        ));
    }

    /**
     * Build the reporting window for individual email requests
     */
    private function build_individual_email_date_range($date_from, $date_to, $time_from, $time_to) {
        $date_from_input = $date_from;
        $date_to_input = $date_to;

        if ($time_from && $date_from) {
            $date_from_input = $date_from . ' ' . $time_from;
        }

        if ($time_to && $date_to) {
            $date_to_input = $date_to . ' ' . $time_to;
        }

        $use_custom_range = !empty($date_from_input) || !empty($date_to_input);

        return $use_custom_range
            ? $this->build_manual_date_range($date_from_input, $date_to_input)
            : $this->get_supplier_email_date_range();
    }

    /**
     * Find a supplier term by slug across the network
     */
    private function find_supplier_by_slug($supplier_slug) {
        $supplier_slug = trim((string) $supplier_slug);

        if ($supplier_slug === '') {
            return array(null, '');
        }

        $sites = $this->get_main_sites();

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            if (taxonomy_exists('supplier')) {
                $terms = get_terms(array(
                    'taxonomy' => 'supplier',
                    'slug' => $supplier_slug,
                    'hide_empty' => false,
                ));

                if (!is_wp_error($terms) && !empty($terms)) {
                    $supplier = $terms[0];
                    $supplier_email = get_term_meta($supplier->term_id, 'mcisoe_supplier_email', true);
                    restore_current_blog();
                    return array($supplier, $supplier_email);
                }
            }

            restore_current_blog();
        }

        return array(null, '');
    }

    /**
     * Process and send an individual supplier email (test or real)
     */
    private function process_individual_supplier_email($supplier_slug, $date_range, $recipient_email = '', $is_test = false) {
        $supplier_slug = trim((string) $supplier_slug);

        if ($supplier_slug === '') {
            return array('success' => false, 'message' => __('Supplier slug is required', 'network-supplier-orders'));
        }

        if ($is_test && !is_email($recipient_email)) {
            return array('success' => false, 'message' => __('Provide a valid test email address.', 'network-supplier-orders'));
        }

        if (!is_array($date_range) || empty($date_range['send'])) {
            $message = is_array($date_range) && !empty($date_range['message'])
                ? $date_range['message']
                : __('Please provide a valid date range.', 'network-supplier-orders');
            return array('success' => false, 'message' => $message);
        }

        list($supplier, $supplier_email) = $this->find_supplier_by_slug($supplier_slug);

        if (!$supplier) {
            return array('success' => false, 'message' => __('Supplier not found', 'network-supplier-orders'));
        }

        if (!$supplier_email && !$is_test) {
            return array('success' => false, 'message' => __('No email found for this supplier', 'network-supplier-orders'));
        }

        $range_description = isset($date_range['description']) ? $date_range['description'] : '';

        $orders = $this->get_all_orders_by_datetime(
            $supplier->slug,
            $date_range['from'],
            $date_range['to']
        );

        if (empty($orders) && !$is_test) {
            return array(
                'success' => false,
                'message' => sprintf(
                    __('No orders found for %s for this supplier', 'network-supplier-orders'),
                    $range_description
                )
            );
        }

        $export_email = $supplier_email ? $supplier_email : $supplier->slug;
        $file_result = $this->create_supplier_export_file($supplier, $export_email, $orders);

        if (!$file_result['success']) {
            return array('success' => false, 'message' => $file_result['error']);
        }

        $recipient = $is_test ? $recipient_email : $supplier_email;

        $email_result = $this->send_supplier_email(
            $supplier,
            $recipient,
            $file_result['file_path'],
            count($orders),
            $date_range,
            $is_test
        );

        if (!$email_result) {
            return array('success' => false, 'message' => __('Failed to send email', 'network-supplier-orders'));
        }

        $recipient_label = $is_test
            ? sprintf(__('Test email to %s (supplier %s)', 'network-supplier-orders'), $recipient, $supplier->name)
            : sprintf(__('%s (%s)', 'network-supplier-orders'), $supplier->name, $recipient);

        return array(
            'success' => true,
            'message' => sprintf(
                __('Email sent successfully to %s with %d order(s) covering %s', 'network-supplier-orders'),
                $recipient_label,
                count($orders),
                $range_description
            ),
            'supplier_name' => $supplier->name,
            'supplier_email' => $recipient,
            'order_count' => count($orders),
            'file_name' => $file_result['filename'],
            'range_description' => $range_description
        );
    }

    /**
     * Delay (minutes) before running queued test emails
     */
    private function get_test_email_delay_minutes() {
        $minutes = (int) apply_filters('nso_test_email_delay_minutes', 15);
        return $minutes > 0 ? $minutes : 15;
    }

    /**
     * Queue a deferred test email job
     */
    private function queue_test_email_job($supplier_slug, $test_email, $date_range) {
        $delay_minutes = $this->get_test_email_delay_minutes();
        $delay_seconds = $delay_minutes * MINUTE_IN_SECONDS;

        $payload = array(
            'supplier_slug' => $supplier_slug,
            'test_email' => $test_email,
            'date_range' => $date_range,
        );

        $args = array($payload);
        $scheduled = true;
        if (!wp_next_scheduled('nso_deferred_supplier_test_email', $args)) {
            $scheduled = wp_schedule_single_event(time() + $delay_seconds, 'nso_deferred_supplier_test_email', $args);
        }

        if (!$scheduled) {
            return array(
                'success' => false,
                'message' => __('Unable to queue the test email. Please try again.', 'network-supplier-orders')
            );
        }

        return array(
            'success' => true,
            'queued' => true,
            'delay_minutes' => $delay_minutes,
            'message' => sprintf(
                __('Test email queued. The export is being generated and will be sent in about %d minutes.', 'network-supplier-orders'),
                $delay_minutes
            ),
            'range_description' => isset($date_range['description']) ? $date_range['description'] : ''
        );
    }

    /**
     * Prep runtime for long-running background tasks
     */
    private function prepare_long_task() {
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
    }

    /**
     * Run deferred test email jobs via WP-Cron
     */
    public function run_deferred_supplier_test_email($payload) {
        if (!is_array($payload)) {
            $this->log_event('Deferred test email payload missing or invalid.');
            return;
        }

        $supplier_slug = isset($payload['supplier_slug']) ? sanitize_text_field($payload['supplier_slug']) : '';
        $test_email = isset($payload['test_email']) ? sanitize_email($payload['test_email']) : '';
        $date_range = isset($payload['date_range']) && is_array($payload['date_range']) ? $payload['date_range'] : array();

        if ($supplier_slug === '' || $test_email === '' || !is_email($test_email)) {
            $this->log_event('Deferred test email skipped due to missing supplier or invalid email.');
            return;
        }

        if (empty($date_range['send'])) {
            $message = isset($date_range['message']) ? $date_range['message'] : 'Date range invalid for deferred test email.';
            $this->log_event('Deferred test email skipped: ' . $message);
            return;
        }

        $this->prepare_long_task();

        $result = $this->process_individual_supplier_email($supplier_slug, $date_range, $test_email, true);

        if (empty($result['success'])) {
            $error_message = isset($result['message']) ? $result['message'] : 'Unknown error while sending deferred test email.';
            $this->log_event('Deferred test email failed: ' . $error_message);
            return;
        }

        $this->log_event(sprintf(
            'Deferred test email sent to %s for supplier %s.',
            $test_email,
            $supplier_slug
        ));
    }
    
    /**
     * Send email to individual supplier
     */
    public function send_individual_supplier_email() {
        check_ajax_referer('nso_export_nonce', 'nonce');
        
        if (!current_user_can('manage_network')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $supplier_slug = isset($_POST['supplier_slug']) ? sanitize_text_field($_POST['supplier_slug']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $time_from = isset($_POST['date_from_time']) ? sanitize_text_field($_POST['date_from_time']) : '';
        $time_to = isset($_POST['date_to_time']) ? sanitize_text_field($_POST['date_to_time']) : '';
        $test_email = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : '';
        $use_test_email = !empty($test_email);
        
        if (empty($supplier_slug)) {
            wp_send_json_error(array('message' => 'Supplier slug is required'));
        }

        if ($use_test_email && !is_email($test_email)) {
            wp_send_json_error(array('message' => __('Provide a valid test email address.', 'network-supplier-orders')));
        }

        $date_range = $this->build_individual_email_date_range($date_from, $date_to, $time_from, $time_to);

        if (empty($date_range['send'])) {
            $message = isset($date_range['message']) ? $date_range['message'] : __('Please provide a valid date range.', 'network-supplier-orders');
            wp_send_json_error(array('message' => $message));
        }

        try {
            if ($use_test_email) {
                list($supplier) = $this->find_supplier_by_slug($supplier_slug);

                if (!$supplier) {
                    wp_send_json_error(array('message' => __('Supplier not found', 'network-supplier-orders')));
                }

                $queue_result = $this->queue_test_email_job($supplier_slug, $test_email, $date_range);

                if (empty($queue_result['success'])) {
                    $message = isset($queue_result['message']) ? $queue_result['message'] : __('Unable to queue test email.', 'network-supplier-orders');
                    wp_send_json_error(array('message' => $message));
                }

                $queue_result['supplier_name'] = $supplier->name;
                $queue_result['supplier_email'] = $test_email;
                $queue_result['order_count'] = 0;

                wp_send_json_success($queue_result);
            }

            $result = $this->process_individual_supplier_email($supplier_slug, $date_range);

            if (!empty($result['success'])) {
                wp_send_json_success($result);
            }

            $message = isset($result['message']) ? $result['message'] : __('Failed to send email', 'network-supplier-orders');
            wp_send_json_error(array('message' => $message));
        } catch (Throwable $e) {
            error_log('[NSO] send_individual_supplier_email error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Unexpected error: ' . $e->getMessage()));
        }
    }
    
    /**
     * Check if supplier has email enabled
     */
    private function is_supplier_email_enabled($supplier_slug) {
        $enabled_suppliers = get_site_option('nso_enabled_suppliers', array());
        return in_array($supplier_slug, $enabled_suppliers);
    }

    /**
     * REST API route to trigger supplier emails from an external URL
     */
    public function register_rest_routes() {
        register_rest_route('nso/v1', '/email-suppliers', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_trigger_supplier_emails'),
            'permission_callback' => array($this, 'rest_permission_callback'),
            'args' => array(
                'key' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'date_from' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'date_to' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }

    /**
     * Authenticate REST requests using a site-wide secret key
     */
    public function rest_permission_callback($request) {
        $provided_key = sanitize_text_field($request->get_param('key'));
        $expected_key = $this->get_webhook_key();

        if (!$provided_key || !$expected_key || !hash_equals($expected_key, $provided_key)) {
            return new WP_Error(
                'nso_invalid_key',
                __('Invalid or missing key for supplier email webhook.', 'network-supplier-orders'),
                array('status' => 401)
            );
        }

        return true;
    }

    /**
     * Handle REST request to trigger supplier emails
     */
    public function rest_trigger_supplier_emails($request) {
        $date_from = $request->get_param('date_from');
        $date_to = $request->get_param('date_to');
        $use_custom_range = !empty($date_from) || !empty($date_to);

        $date_range = $use_custom_range
            ? $this->build_manual_date_range($date_from, $date_to)
            : $this->get_supplier_email_date_range();

        if (!$date_range['send']) {
            return rest_ensure_response(array(
                'success' => true,
                'suppliers_processed' => 0,
                'emails_sent' => 0,
                'emails_success' => array(),
                'errors' => array(),
                'message' => $date_range['message'],
            ));
        }

        $run_context = $use_custom_range ? 'manual' : 'automated';

        $result = $this->export_and_email_suppliers($date_range, $run_context);

        return rest_ensure_response($result);
    }

    /**
     * Create or fetch a persistent webhook key for external triggers
     */
    private function get_webhook_key() {
        $key = get_site_option('nso_email_webhook_key', '');

        if (!$key) {
            $key = wp_generate_password(32, false, false);
            update_site_option('nso_email_webhook_key', $key);
            $this->log_event('Generated new webhook key for supplier email trigger.');
        }

        return $key;
    }

    /**
     * Write a lightweight log line to error_log and to the supplier exports folder
     */
    private function log_event($message) {
        if (!$message) {
            return;
        }

        $timestamp = current_time('mysql');
        $line = sprintf('[NSO %s] %s', $timestamp, $message);

        if (function_exists('error_log')) {
            error_log($line);
        }

        $upload_dir = wp_upload_dir();
        $log_dir = trailingslashit($upload_dir['basedir']) . 'supplier-exports';
        $log_file = $log_dir . '/nso-email.log';

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        file_put_contents($log_file, $line . PHP_EOL, FILE_APPEND);
    }
    
    /**
     * Send email to supplier with export file
     */
    private function send_supplier_email($supplier, $supplier_email, $file_path, $order_count, $date_range = array(), $is_test = false) {
        $range_label = '';

        if (!empty($date_range['description'])) {
            $range_label = $date_range['description'];
        } elseif (!empty($date_range['from']) && !empty($date_range['to'])) {
            $range_label = sprintf('%s - %s', $date_range['from'], $date_range['to']);
        } else {
            $range_label = __('the latest reporting window', 'network-supplier-orders');
        }

        $subject_prefix = $is_test ? '[TEST] ' : '';
        $subject = sprintf(
            __('%sNew Orders Export - %s - %s', 'network-supplier-orders'),
            $subject_prefix,
            $supplier->name,
            date('Y-m-d')
        );
        
        $message = sprintf(
            __("Hello %s,\n\nPlease find attached the export of %d order(s) for your products.\nReporting window: %s\nSent on: %s at %s (Netherlands Time)\n%s\n\nThe order details are in the attached CSV file.\n\nBest regards,\nYour Store", 'network-supplier-orders'),
            $supplier->name,
            $order_count,
            $range_label,
            date('Y-m-d'),
            date('H:i'),
            $is_test ? __("This email was sent in TEST mode to validate delivery.", 'network-supplier-orders') : ''
        );
        
        // HTML version
        $html_message = sprintf(
            '<html><body style="font-family: Arial, sans-serif;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #2271b1; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">New Orders Export</h2>
                <p>Hello <strong>%s</strong>,</p>
                <p>Please find attached the export of <strong>%d order(s)</strong> for your products.</p>
                <table style="border-collapse: collapse; margin: 20px 0; width: 100%%; background: #f9f9f9; border: 1px solid #ddd;">
                    <tr>
                        <td style="padding: 12px; font-weight: bold; border-bottom: 1px solid #ddd; width: 40%%;">Date:</td>
                        <td style="padding: 12px; border-bottom: 1px solid #ddd;">%s</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; font-weight: bold; border-bottom: 1px solid #ddd;">Time:</td>
                        <td style="padding: 12px; border-bottom: 1px solid #ddd;">%s (Netherlands Time)</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; font-weight: bold; border-bottom: 1px solid #ddd;">Reporting window:</td>
                        <td style="padding: 12px; border-bottom: 1px solid #ddd;">%s</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; font-weight: bold; border-bottom: 1px solid #ddd;">Total Orders:</td>
                        <td style="padding: 12px; border-bottom: 1px solid #ddd;">%d</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; font-weight: bold;">File Format:</td>
                        <td style="padding: 12px;">CSV</td>
                    </tr>
                </table>
                %s
                <p style="background: #e7f3ff; padding: 15px; border-left: 4px solid #2271b1; margin: 20px 0;">
                    <strong>Attachment:</strong> The order details are in the attached CSV file which can be opened with Microsoft Excel, Google Sheets, or any spreadsheet application.
                </p>
                <p>Best regards,<br><strong>Your Store</strong></p>
                <hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">
                <p style="font-size: 12px; color: #666;">This is an automated email sent from your supplier order management system.</p>
            </div>
            </body></html>',
            $supplier->name,
            $order_count,
            date('Y-m-d'),
            date('H:i'),
            $range_label,
            $order_count,
            $is_test
                ? '<p style="margin: 10px 0; padding: 12px; background: #fff8e5; border: 1px solid #f0c36d; color: #7a5a00;">'
                    . __('TEST MODE: This email was sent to validate delivery and may not be sent to the supplier.', 'network-supplier-orders')
                    . '</p>'
                : ''
        );
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>'
        );
        
        $attachments = array($file_path);
        
        return wp_mail($supplier_email, $subject, $html_message, $headers, $attachments);
    }
}
}

// Initialize
if ( class_exists( 'Network_Supplier_Orders' ) ) {
    Network_Supplier_Orders::get_instance();
}
