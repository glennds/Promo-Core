<?php
defined('ABSPATH') || exit;

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////



add_action( 'admin_head', 'dnz_hide_downloadable_option_in_admin' );

function dnz_hide_downloadable_option_in_admin() {
    $screen = get_current_screen();
    if ( isset( $screen->id ) && $screen->id === 'product' ) {
        echo '<style>
            .options_group .form-field._downloadable_field,
            .options_group ._downloadable.checkbox {
                display: none !important;
            }
        </style>';
    }
}
