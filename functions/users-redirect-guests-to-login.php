<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}
if (!function_exists('keuzeconcept_is_enabled') || !keuzeconcept_is_enabled('users_redirect_guests_to_login')) {return;}

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////


// Redirect guests to login page
add_action( 'template_redirect', function() {
    if ( is_user_logged_in() ) return;

    // Skip for admin, login, register, lost password
    if ( is_admin() || is_page( array( 'inloggen', 'wp-login.php', 'register', 'lostpassword' ) ) ) return;

    // Current subsite path
    $subsite = trim( get_blog_details()->path, '/' );

    // Build redirect URL
    $login_url = home_url( '/inloggen/' );

    wp_safe_redirect( $login_url );
    exit;
});
