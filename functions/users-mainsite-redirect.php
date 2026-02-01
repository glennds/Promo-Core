<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}
if (!function_exists('keuzeconcept_is_enabled') || !keuzeconcept_is_enabled('users_mainsite_redirect')) {return;}

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////


// If user is logged in, has customer role and visits homepage: redirect to /inloggen/
add_action('template_redirect', function () {
    if (!is_user_logged_in()) {return;}
    $user = wp_get_current_user();
    if (!in_array('customer', (array) $user->roles, true)) {return;}
    if (is_front_page() || is_home()) {
        wp_redirect(home_url('/inloggen/'));
        exit;
    }
});
