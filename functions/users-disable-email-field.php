<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}
if (!managepromo_is_enabled('users_disable_email_field')) {return;}

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////



// Build a dummy email prefab based on <username>@dummy<domain>.
function managepromo_dummy_email_for_login(string $user_login): string {
    $login = sanitize_user($user_login, true);

    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    $host = strtolower(preg_replace('/[^a-z0-9\.\-]/i', '', $host));
    if ($host === '') {$host = 'localhost';}

    return $login . '@dummy' . $host;
}



// Ensure dummy email is unique in WP users table.
function managepromo_unique_dummy_email(string $base_email, string $user_login): string {
    if ( ! email_exists($base_email) ) {return $base_email;}

    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    $host = strtolower(preg_replace('/[^a-z0-9\.\-]/i', '', $host));
    if ($host === '') {$host = 'localhost';}

    $login = sanitize_user($user_login, true);
    for ($i = 2; $i < 500; $i++) {
        $candidate = $login . '-' . $i . '@dummy' . $host;
        if ( ! email_exists($candidate) ) {return $candidate;}
    }
    
    return $login . '-' . wp_generate_password(6, false) . '@dummy' . $host;
}



// When saving, Fill email field with dummy email prefab before adding to WP users table.
add_filter('wp_pre_insert_user_data', function (array $data, $update, $user_id, $userdata) {
    if (empty($data['user_login'])) {return $data;}

    if (empty($data['user_email'])) {
        $base = managepromo_dummy_email_for_login((string) $data['user_login']);
        $data['user_email'] = managepromo_unique_dummy_email($base, (string) $data['user_login']);
    }

    return $data;
}, 10, 4);



// Remove "email required" validation in wp-login.php registration flow.
add_filter('registration_errors', function (WP_Error $errors, $sanitized_user_login, $user_email) {
    $errors->remove('empty_email');
    return $errors;
}, 10, 3);

// Remove "email required" validation in wp-admin user create/edit flow.
add_action('user_profile_update_errors', function (WP_Error $errors, $update, $user) {
    $errors->remove('empty_email');
}, 10, 3);

// Hide e-mail field on registration and make non-required
add_action('admin_footer-user-new.php', function () {
    ?>
    <script>
    (function () {
        var row = document.getElementById('email')?.closest('tr');
        if (row) {row.style.display = 'none';}
    })();
    </script>
    <?php
});

// Remove confirmation email checkboxes
add_action('admin_enqueue_scripts', function ($hook) {
    // Only run on wp-admin/user-new.php
    if ($hook !== 'user-new.php') {
        return;
    }

    add_action('admin_print_footer_scripts', function () {
        ?>
        <script>
        (function () {
            // Remove the "Send the new user an email about their account" checkbox row from the DOM
            var el = document.getElementById('send_user_notification') || document.querySelector('input[name="send_user_notification"]');
            if (!el) return;

            var row = el.closest('tr') || el.closest('.form-field') || el.closest('p') || el.parentElement;
            if (row) row.remove();
        })();
        </script>
        <?php
    });
});
