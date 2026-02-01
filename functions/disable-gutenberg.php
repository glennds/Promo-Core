<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}
if (!function_exists('managepromo_is_enabled') || !managepromo_is_enabled('disable_gutenberg')) {return;}

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////


// Disable the Gutenberg block editor requests
if (version_compare($GLOBALS['wp_version'], '5.0-beta', '>')) {	
	add_filter('use_block_editor_for_post_type', '__return_false', 10);
} else {
	add_filter('gutenberg_can_edit_post_type', '__return_false', 10);
}

// Offload the block library scripts
add_action( 'wp_print_styles', 'wps_deregister_styles', 100 );
function wps_deregister_styles() {
    wp_dequeue_style( 'wp-block-library' );
}
