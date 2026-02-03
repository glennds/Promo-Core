<?php
/**
 * Ensure WooCommerce activation steps run after NS Cloner creates a site.
 * File: wp-content/mu-plugins/nscloner-wc-post-activate.php
 */
if (!defined('ABSPATH')) exit;

add_action('ns_cloner_after_clone', 'ds_run_wc_activation_on_cloned_site', 20);
add_action('ns_cloner_site_created', 'ds_run_wc_activation_on_cloned_site', 20); // fallback if your NS Cloner fires this

function ds_run_wc_activation_on_cloned_site($process_or_site_id){
    // Get target blog ID from either hook signature
    $target_id = is_object($process_or_site_id) && !empty($process_or_site_id->target_id)
        ? (int) $process_or_site_id->target_id
        : (int) $process_or_site_id;

    if (!$target_id) return;

    switch_to_blog($target_id);

    if (class_exists('WC_Install')) {           // Only if WooCommerce is available on this site
        WC_Install::install();                  // Full activation routine (creates options, schedules, etc.)

        // Refresh role cache for this blog
        if (function_exists('wp_roles')) {wp_roles()->for_site($target_id);}

        // Clear object cache so the fresh wp_user_roles is used
        if (function_exists('wp_cache_flush')) {wp_cache_flush();}
    }

    restore_current_blog();
}
