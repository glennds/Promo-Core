<?php
defined('ABSPATH') || exit;

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////



// Redirect guests to login page
add_action( 'template_redirect', function() {
    if ( is_user_logged_in() ) return;

    // Skip for admin and public authentication / password-reset pages
    if ( is_admin() || is_page( array(
        'inloggen',
        'login',
        'forgot-password',
        'password-reset',
        'wachtwoord-vergeten',
        'wachtwoord-reset',
        'wp-login.php',
        'register',
        'lostpassword',
    ) ) ) return;

    // Current subsite path
    $subsite = trim( get_blog_details()->path, '/' );

    // Build redirect URL
    $login_url = home_url( '/inloggen/' );

    wp_safe_redirect( $login_url );
    exit;
});
