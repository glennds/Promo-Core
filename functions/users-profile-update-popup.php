<?php
if ( ! defined('ABSPATH') ) exit;

// Feature flag (keep your existing switch)
if ( function_exists('keuzeconcept_is_enabled') && ! keuzeconcept_is_enabled('users_profile_update_popup') ) {
    return;
}

/** Core updater used by both hooks and AJAX */
function kc_profile_sync_apply(array $data, int $user_id): void {
    $first = isset($data['first']) ? sanitize_text_field($data['first']) : '';
    $last  = isset($data['last'])  ? sanitize_text_field($data['last'])  : '';
    $email = isset($data['email']) ? sanitize_email($data['email'])      : '';

    // Names -> WP + Woo meta
    if ($first !== '') {
        update_user_meta($user_id, 'first_name', $first);
        update_user_meta($user_id, 'billing_first_name',  $first);
        update_user_meta($user_id, 'shipping_first_name', $first);
    }
    if ($last !== '') {
        update_user_meta($user_id, 'last_name', $last);
        update_user_meta($user_id, 'billing_last_name',  $last);
        update_user_meta($user_id, 'shipping_last_name', $last);
    }
    if ($first !== '' || $last !== '') {
        $display = trim($first . ' ' . $last);
        if ($display !== '') {
            wp_update_user(['ID' => $user_id, 'display_name' => $display]);
        }
    }

    // Email -> Woo billing + WP account (unique)
    if ($email !== '' && is_email($email)) {
        update_user_meta($user_id, 'billing_email', $email);

        $existing = email_exists($email);
        if ( ! $existing || intval($existing) === $user_id ) {
            $res = wp_update_user(['ID' => $user_id, 'user_email' => $email]);
            if (is_wp_error($res)) {
                error_log('[KC Profile Sync] user_email update error: '.$res->get_error_message());
            }
        } else {
            // Another account uses this email; keep billing_email updated but skip user_email
            error_log(sprintf('[KC Profile Sync] user_email not changed; "%s" belongs to user %d.', $email, $existing));
        }

        // Update current Woo customer/session so checkout prefills immediately
        if ( function_exists('wc') && wc()->customer ) {
            try {
                wc()->customer->set_billing_email($email);
                if ( method_exists(wc()->customer, 'set_email') ) {
                    wc()->customer->set_email($email);
                }
                wc()->customer->save();
            } catch (Throwable $e) {
                error_log('[KC Profile Sync] WC()->customer save failed: '.$e->getMessage());
            }
        } elseif ( class_exists('WC_Customer') ) {
            try {
                $customer = new WC_Customer($user_id);
                if ( method_exists($customer, 'set_billing_email') ) $customer->set_billing_email($email);
                if ( method_exists($customer, 'set_email') )         $customer->set_email($email);
                $customer->save();
            } catch (Throwable $e) {
                error_log('[KC Profile Sync] WC_Customer save failed: '.$e->getMessage());
            }
        }
    }
}

/** Path A: when Breakdance stores a response post (CPT: breakdance_form_res) */
add_action('save_post_breakdance_form_res', function($post_id, $post, $update){
    if ( wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) ) return;
    if ( empty($_POST['fields']) || ! is_array($_POST['fields']) ) return;

    $user_id = get_current_user_id();
    if ( ! $user_id ) return;

    $fields = wp_unslash($_POST['fields']);
    $data = [
        'first' => isset($fields['your-firstname']) ? $fields['your-firstname'] : '',
        'last'  => isset($fields['your-lastname'])  ? $fields['your-lastname']  : '',
        'email' => isset($fields['your-email'])     ? $fields['your-email']     : '',
    ];
    kc_profile_sync_apply($data, $user_id);
}, 10, 3);

/** Path B: explicit AJAX endpoint we’ll call from JS on success (guaranteed even if no CPT is saved) */
add_action('wp_ajax_kc_breakdance_profile_sync', function () {
    if ( ! is_user_logged_in() ) wp_send_json_error(['msg'=>'not_logged_in'], 401);
    check_ajax_referer('kc_profile_sync');

    $user_id = get_current_user_id();
    $data = [
        'first' => isset($_POST['first']) ? wp_unslash($_POST['first']) : '',
        'last'  => isset($_POST['last'])  ? wp_unslash($_POST['last'])  : '',
        'email' => isset($_POST['email']) ? wp_unslash($_POST['email']) : '',
    ];
    kc_profile_sync_apply($data, $user_id);
    wp_send_json_success(['ok'=>true]);
});

/** Provide ajax_url + nonce for the front-end snippet */
add_action('wp_footer', function () {
    if ( ! is_user_logged_in() ) return;
    $nonce = wp_create_nonce('kc_profile_sync');
    $ajax  = admin_url('admin-ajax.php');
    ?>
    <script>
    (function(){
        var ajax  = "<?php echo esc_js($ajax); ?>";
        var nonce = "<?php echo esc_js($nonce); ?>";
        window.kcps = {ajax_url: ajax, nonce: nonce};

        function kcpsSend(form) {
            if (!ajax || !nonce || !form) return;
            // Only target forms that contain our contact fields
            if (!form.querySelector('[name="fields[your-firstname]"], [name="fields[your-lastname]"], [name="fields[your-email]"]')) return;

            var first = (form.querySelector('[name="fields[your-firstname]"]') || {}).value || '';
            var last  = (form.querySelector('[name="fields[your-lastname]"]')  || {}).value || '';
            var email = (form.querySelector('[name="fields[your-email]"]')     || {}).value || '';
            first = first.trim();
            last  = last.trim();
            email = email.trim();

            if (!first && !last && !email) return;

            var body = new URLSearchParams({
                action: 'kc_breakdance_profile_sync',
                _ajax_nonce: nonce,
                first: first,
                last: last,
                email: email
            });

            fetch(ajax, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: body.toString()
            }).catch(function(err){
                console.warn('[KC Profile Sync] AJAX failed', err);
            });
        }

        document.addEventListener('submit', function(evt){
            var form = evt.target.closest('form.breakdance-form');
            if (!form) return;
            kcpsSend(form);
        }, true);
    })();
    </script>
    <?php
}, 99);
