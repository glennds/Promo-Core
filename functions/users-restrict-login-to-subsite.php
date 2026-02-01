<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}
if (!function_exists('keuzeconcept_is_enabled') || !keuzeconcept_is_enabled('users_restrict_login_to_subsite')) {return;}

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////


function restrict_user_login_to_blog( $user, $username, $password ) {
    // Check if the user exists
    if ( ! empty( $user ) && ! is_wp_error( $user ) ) {
        // Get the list of all blogs the user is a member of
        $users_blogs = get_blogs_of_user( $user->ID );

        // Get the current blog ID
        $current_blog_id = get_current_blog_id();

        // Check if the current blog ID is in the user's blogs list
        $is_member_of_current_blog = false;
        foreach ( $users_blogs as $blog_id => $blog_role ) {
            if ( $blog_id == $current_blog_id ) {
                $is_member_of_current_blog = true;
                break;
            }
        }

        // If the user is not a member of the current blog, return an error
        if ( ! $is_member_of_current_blog ) {
            return new WP_Error( 'access_denied', __( 'U heeft geen toegang tot deze site.' ) );
        }
    }

    return $user;
}
add_filter( 'authenticate', 'restrict_user_login_to_blog', 99, 3 );

function redirect_if_not_on_correct_blog() {
    // Check if the user is logged in
    if ( is_user_logged_in() && ! is_super_admin() ) {
        $current_user = wp_get_current_user();
        $users_blogs = get_blogs_of_user( $current_user->ID );

        // Get the current blog ID
        $current_blog_id = get_current_blog_id();

        // Check if the current blog ID is in the user's blogs list
        $is_member_of_current_blog = false;
        foreach ( $users_blogs as $blog_id => $blog_role ) {
            if ( $blog_id == $current_blog_id ) {
                $is_member_of_current_blog = true;
                break;
            }
        }

        // If the user is not a member of the current blog, redirect them
        if ( ! $is_member_of_current_blog ) {
            // Get the first blog ID the user is a member of
            $first_blog_id = key($users_blogs);
            $first_blog_url = get_blog_details( $first_blog_id )->siteurl;

            // Redirect the user
            wp_redirect( $first_blog_url );
            exit;
        }
    }
}
add_action( 'template_redirect', 'redirect_if_not_on_correct_blog' );
