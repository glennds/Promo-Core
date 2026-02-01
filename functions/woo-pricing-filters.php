<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}
if (!function_exists('keuzeconcept_is_enabled') || !keuzeconcept_is_enabled('woo_pricing_filters')) {return;}

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////


/**
 * Normalize any numeric input to a dot-decimal string with fixed scale.
 */
function kc_decimal( $val, $scale = 6 ) {
    if ( $val === null || $val === '' ) return null;

    if ( function_exists( 'wc_format_decimal' ) ) {
        $norm = wc_format_decimal( $val, $scale );
    } else {
        $val  = preg_replace( '/[^\d\-,.]/', '', (string) $val );
        $val  = str_replace( ',', '.', $val );
        $norm = number_format( (float) $val, $scale, '.', '' );
    }
    return $norm;
}

/**
 * Render ONLY Min/Max inputs in Products list toolbar (no dropdowns).
 */
function kc_render_price_filters_admin( $post_type ) {
    if ( $post_type !== 'product' ) { return; }

    $get = function( $k ) {
        return isset( $_GET[ $k ] ) ? esc_attr( wp_unslash( $_GET[ $k ] ) ) : '';
    };

    $supplier_min = $get('supplier_price_min');
    $supplier_max = $get('supplier_price_max');
    $regular_min  = $get('regular_price_min');
    $regular_max  = $get('regular_price_max');
    ?>
    <div class="alignleft actions">
        <!-- Supplier Min/Max -->
        <label style="margin-right:6px;"><strong><?php esc_html_e('Supplier', 'your-textdomain'); ?></strong></label>
        <input type="number" step="0.01" name="supplier_price_min" placeholder="<?php esc_attr_e('Min', 'your-textdomain'); ?>"
               value="<?php echo $supplier_min; ?>" style="width:110px; margin-right:5px;" />
        <input type="number" step="0.01" name="supplier_price_max" placeholder="<?php esc_attr_e('Max', 'your-textdomain'); ?>"
               value="<?php echo $supplier_max; ?>" style="width:110px; margin-right:15px;" />

        <!-- Regular Min/Max -->
        <label style="margin-right:6px;"><strong><?php esc_html_e('Regular', 'your-textdomain'); ?></strong></label>
        <input type="number" step="0.01" name="regular_price_min" placeholder="<?php esc_attr_e('Min', 'your-textdomain'); ?>"
               value="<?php echo $regular_min; ?>" style="width:110px; margin-right:5px;" />
        <input type="number" step="0.01" name="regular_price_max" placeholder="<?php esc_attr_e('Max', 'your-textdomain'); ?>"
               value="<?php echo $regular_max; ?>" style="width:110px;" />
    </div>
    <?php
}
add_action( 'restrict_manage_posts', 'kc_render_price_filters_admin', 20 );

/**
 * Apply Min/Max filters to the main admin Products query (no dropdown logic).
 */
function kc_apply_price_filters_query( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) { return; }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ( ! $screen || $screen->id !== 'edit-product' ) { return; }

    // Existing meta_query (don’t clobber)
    $meta_query = $query->get('meta_query');
    if ( ! is_array( $meta_query ) ) { $meta_query = array(); }

    $filters = array( 'relation' => 'AND' );

    // Helpers
    $has_num = function( $k ) {
        if ( ! isset( $_GET[ $k ] ) ) return false;
        $raw = wp_unslash( $_GET[ $k ] );
        $raw = str_replace( ',', '.', $raw ); // tolerate comma decimals
        return $raw !== '' && is_numeric( $raw );
    };

    // ---------- Supplier (_cogs_total_value)
    $supplier_min = $has_num('supplier_price_min') ? kc_decimal( $_GET['supplier_price_min'] ) : null;
    $supplier_max = $has_num('supplier_price_max') ? kc_decimal( $_GET['supplier_price_max'] ) : null;

    if ( $supplier_min !== null && $supplier_max !== null ) {
        $filters[] = array(
            'key'     => '_cogs_total_value',
            'value'   => array( $supplier_min, $supplier_max ),
            'compare' => 'BETWEEN',
            'type'    => 'DECIMAL',
        );
    } elseif ( $supplier_min !== null ) {
        $filters[] = array(
            'key'     => '_cogs_total_value',
            'value'   => $supplier_min,
            'compare' => '>=',
            'type'    => 'DECIMAL',
        );
    } elseif ( $supplier_max !== null ) {
        $filters[] = array(
            'key'     => '_cogs_total_value',
            'value'   => $supplier_max,
            'compare' => '<=',
            'type'    => 'DECIMAL',
        );
    }

    // ---------- Regular (_regular_price)
    $regular_min = $has_num('regular_price_min') ? kc_decimal( $_GET['regular_price_min'] ) : null;
    $regular_max = $has_num('regular_price_max') ? kc_decimal( $_GET['regular_price_max'] ) : null;

    if ( $regular_min !== null && $regular_max !== null ) {
        $filters[] = array(
            'key'     => '_regular_price',
            'value'   => array( $regular_min, $regular_max ),
            'compare' => 'BETWEEN',
            'type'    => 'DECIMAL',
        );
    } elseif ( $regular_min !== null ) {
        $filters[] = array(
            'key'     => '_regular_price',
            'value'   => $regular_min,
            'compare' => '>=',
            'type'    => 'DECIMAL',
        );
    } elseif ( $regular_max !== null ) {
        $filters[] = array(
            'key'     => '_regular_price',
            'value'   => $regular_max,
            'compare' => '<=',
            'type'    => 'DECIMAL',
        );
    }

    // Apply if we added any clauses (beyond 'relation')
    if ( count( $filters ) > 1 ) {
        $meta_query[] = $filters; // merge; don’t overwrite other meta_query parts
        $query->set( 'meta_query', $meta_query );
    }
}
add_action( 'pre_get_posts', 'kc_apply_price_filters_query', 20 );
