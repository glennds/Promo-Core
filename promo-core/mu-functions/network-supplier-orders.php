<?php
defined('ABSPATH') || exit;

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////



// --- //
// Allow password reset for all users, specifically users with 'customer' role
add_filter('allow_password_reset', function ($allow, $user_id) {
	$user = get_userdata($user_id);

	if (!$user instanceof WP_User) {return $allow;} // If user doesn't exist, return default behavior
	if (in_array('customer', (array) $user->roles, true)) {return true;} // Explicitly check for 'customer' role

	return true;
}, 9999, 2);


// --- //
// Determine the blog where password reset was requested and use that blog's name & URL in the reset email, instead of main site details.
function promocore_password_reset_normalize_host( string $host ): string {
    $host = strtolower( trim( $host ) );
    $host = preg_replace( '/:\d+$/', '', $host );
    return rtrim( $host, '.' );
}

function promocore_password_reset_site_candidates_from_path( string $path ): array {
    $path      = '/' . ltrim( $path, '/' );
    $segments  = array_values( array_filter( explode( '/', trim( $path, '/' ) ), 'strlen' ) );
    $candidates = [];

    for ( $i = count( $segments ); $i >= 0; $i-- ) {
        if ( $i === 0 ) {
            $candidates[] = '/';
            continue;
        }

        $candidates[] = '/' . implode( '/', array_slice( $segments, 0, $i ) ) . '/';
    }

    return array_values( array_unique( $candidates ) );
}

function promocore_password_reset_blog_id_from_url( string $url ): int {
    $host = (string) wp_parse_url( $url, PHP_URL_HOST );
    if ( $host === '' ) {
        return 0;
    }

    $host = promocore_password_reset_normalize_host( $host );
    $path = (string) wp_parse_url( $url, PHP_URL_PATH );
    if ( $path === '' ) {
        $path = '/';
    }

    $hosts = [ $host ];
    if ( str_starts_with( $host, 'www.' ) ) {
        $hosts[] = substr( $host, 4 );
    } else {
        $hosts[] = 'www.' . $host;
    }

    foreach ( array_unique( $hosts ) as $candidate_host ) {
        foreach ( promocore_password_reset_site_candidates_from_path( $path ) as $candidate_path ) {
            $blog_id = (int) get_blog_id_from_url( $candidate_host, $candidate_path );
            if ( $blog_id > 0 ) {
                return $blog_id;
            }
        }
    }

    return 0;
}

function promocore_password_reset_target_blog_id(): int {
    static $resolved_blog_id = null;

    if ( $resolved_blog_id !== null ) {
        return $resolved_blog_id;
    }

    $resolved_blog_id = get_current_blog_id();

    if ( ! is_multisite() ) {
        return $resolved_blog_id;
    }

    $scheme = is_ssl() ? 'https' : 'http';
    $current_url = '';
    if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
        $current_url = $scheme . '://' . wp_unslash( $_SERVER['HTTP_HOST'] ) . ( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' );
    }

    $candidates = array_filter( [
        wp_get_referer(),
        isset( $_POST['_wp_http_referer'] ) ? wp_unslash( $_POST['_wp_http_referer'] ) : '',
        isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '',
        $current_url,
    ] );

    foreach ( $candidates as $candidate_url ) {
        $blog_id = promocore_password_reset_blog_id_from_url( (string) $candidate_url );
        if ( $blog_id > 0 ) {
            $resolved_blog_id = $blog_id;
            break;
        }
    }

    return $resolved_blog_id;
}

function promocore_password_reset_login_url( int $blog_id, array $query_args = [] ): string {
    if ( $blog_id > 0 && $blog_id !== get_current_blog_id() ) {
        switch_to_blog( $blog_id );
        $base_url = site_url( 'wp-login.php', 'login' );
        $locale   = get_locale();
        restore_current_blog();
    } else {
        $base_url = site_url( 'wp-login.php', 'login' );
        $locale   = get_locale();
    }

    if ( $locale !== '' && ! isset( $query_args['wp_lang'] ) ) {
        $query_args['wp_lang'] = $locale;
    }

    if ( ! empty( $query_args ) ) {
        $base_url = add_query_arg( $query_args, $base_url );
    }

    return $base_url;
}

function promocore_password_reset_blog_name( int $blog_id ): string {
    if ( $blog_id > 0 && $blog_id !== get_current_blog_id() ) {
        switch_to_blog( $blog_id );
        $blog_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
        restore_current_blog();
        return $blog_name;
    }

    return wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
}

add_filter( 'lostpassword_url', function ( $lostpassword_url, $redirect ) {
    if ( ! is_multisite() ) {
        return $lostpassword_url;
    }

    $blog_id = promocore_password_reset_target_blog_id();
    $url     = promocore_password_reset_login_url( $blog_id, [ 'action' => 'lostpassword' ] );

    if ( ! empty( $redirect ) ) {
        $url = add_query_arg( 'redirect_to', $redirect, $url );
    }

    return $url;
}, 10, 2 );

add_filter( 'retrieve_password_title', function ( $title ) {
    $blog_name = promocore_password_reset_blog_name( promocore_password_reset_target_blog_id() );

    if ( $blog_name === '' ) {
        return $title;
    }

    return sprintf( __( '[%s] Password Reset' ), $blog_name );
} );

add_filter( 'retrieve_password_message', function ( $message, $key, $user_login, $user_data ) {
    $blog_id   = promocore_password_reset_target_blog_id();
    $blog_name = promocore_password_reset_blog_name( $blog_id );
    $reset_url = promocore_password_reset_login_url( $blog_id, [
        'login'  => $user_login,
        'key'    => $key,
        'action' => 'rp',
    ] );

    $message = __( 'Someone has requested a password reset for the following account:' ) . "\r\n\r\n";

    if ( is_multisite() ) {
        $message .= sprintf( __( 'Site Name: %s' ), $blog_name ) . "\r\n\r\n";
    }

    $message .= sprintf( __( 'Username: %s' ), $user_login ) . "\r\n\r\n";
    $message .= __( 'If this was a mistake, ignore this email and nothing will happen.' ) . "\r\n\r\n";
    $message .= __( 'To reset your password, visit the following address:' ) . "\r\n\r\n";
    $message .= esc_url_raw( $reset_url ) . "\r\n";

    if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $message .= "\r\n" . sprintf(
            __( 'This password reset request originated from the IP address %s.' ),
            sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
        ) . "\r\n";
    }

    return $message;
}, 10, 4 );
