<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}
if (!function_exists('managepromo_is_enabled') || !managepromo_is_enabled('users_mainsite_redirect')) {return;}

// If user is logged in, has customer role and visits homepage on main site: redirect to /inloggen/
add_action('template_redirect', function () {
    if (!is_user_logged_in()) {return;}

    $main_site_id = 1;
    if (function_exists('get_main_site_id')) {
        $main_site_id = (int) get_main_site_id();
    }
    if (function_exists('is_multisite') && is_multisite()) {
        if ((int) get_current_blog_id() !== $main_site_id) {return;}
    }

    $user = wp_get_current_user();
    if (!in_array('customer', (array) $user->roles, true)) {return;}
    if (is_front_page() || is_home()) {
        $login_url = function_exists('get_site_url')
            ? get_site_url($main_site_id, '/inloggen/')
            : home_url('/inloggen/');
        wp_redirect($login_url);
        exit;
    }
});
