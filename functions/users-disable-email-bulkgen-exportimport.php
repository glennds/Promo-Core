<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}
if (!function_exists('keuzeconcept_is_enabled') || !keuzeconcept_is_enabled('users_disable_email_bulkgen_exportimport')) {return;}

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////



/**
 * -----------------------------------------------------------------------------
 * Keuzeconcept User Batch &#65533; Single page (Users &#8594; Bulk management)
 * - Generate random users
 * - Export current site's users (CSV)
 * - Import users (update by username; optional create)
 * -----------------------------------------------------------------------------
 */

class Keuzeconcept_User_Batch {
    const DUMMY_DOMAIN    = 'dummy.keuzeconcept.com';
    const META_PLAIN_PW   = 'kc_plain_password';
    const NONCE_GEN       = 'kc_gen_nonce';
    const NONCE_EXP       = 'kc_exp_nonce';
    const META_IDENTIFIER = 'kc_identifier';
    const META_WALLET     = 'kc_wallet_balance';

    /** Network Users: add "Identifier" column header */
    public function add_identifier_column_network( $cols ) {
        $cols['kc_identifier'] = 'Identifier';
        return $cols;
    }

    /** Network Users: render "Identifier" column cells */
    public function render_identifier_column_network( $val, $column_name, $user_id ) {
        if ( $column_name !== 'kc_identifier' ) return $val;
        $uid = is_object( $user_id ) && isset( $user_id->ID ) ? (int) $user_id->ID : (int) $user_id;
        $v   = get_user_meta( $uid, self::META_IDENTIFIER, true );
        return esc_html( (string) $v );
    }

    public function __construct() {
        // Network Admin &#8594; Users (all sites)

        add_action( 'admin_enqueue_scripts',              [ $this, 'enqueue_admin_assets' ] );
        add_action( 'admin_head-user-new.php',            [ $this, 'hide_email_fields' ] );
        add_action( 'admin_init',                         [ $this, 'alter_user_new_submission' ] );
        add_action( 'user_profile_update_errors',         [ $this, 'force_dummy_email_on_create' ], 10, 3 );

        add_filter( 'wp_send_new_user_notification_to_admin', '__return_false' );
        add_filter( 'wp_send_new_user_notification_to_user',  '__return_false' );
        add_filter( 'send_password_change_email',              '__return_false' );
        add_filter( 'send_email_change_email',                 '__return_false' );
        add_filter( 'allow_password_reset',                   [ $this, 'deny_password_reset' ], 10, 2 );
        add_filter( 'pre_wp_mail',                            [ $this, 'block_user_mail_to_dummy' ], 10, 2 );

        add_filter( 'woocommerce_account_menu_items',         [ $this, 'wc_hide_account_items' ] );
        add_action( 'template_redirect',                      [ $this, 'wc_block_edit_account_endpoint' ] );

        // Single admin page under Users
        add_action( 'admin_menu',                             [ $this, 'add_bulk_submenu' ] );

        // Bulk generate + Export POST handlers
        add_action( 'admin_post_kc_bulk_generate',            [ $this, 'handle_bulk_generate' ] );
        add_action( 'admin_post_kc_export_csv_site',          [ $this, 'handle_export_csv_site' ] );

        // Single-user create UI + save + validation
        add_action( 'user_new_form',                          [ $this, 'render_identifier_field' ] );
        add_action( 'user_profile_update_errors',             [ $this, 'validate_identifier_on_create' ], 15, 3 );
        add_action( 'user_register',                          [ $this, 'save_identifier_on_create' ] );

        // Site-level Users table column + filter
        add_filter( 'manage_users_columns',                   [ $this, 'add_identifier_column' ] );
        add_filter( 'manage_users_custom_column',             [ $this, 'render_identifier_column' ], 10, 3 );
        add_action( 'restrict_manage_users',                  [ $this, 'render_identifier_filter' ] );
        add_filter( 'pre_get_users',                          [ $this, 'apply_identifier_filter' ], 99 );
    }

    private static function is_subsite(): bool {
        return is_multisite() && ! is_network_admin();
    }

    public function enqueue_admin_assets( $hook ) {
        if ( ! self::is_subsite() ) return;
        if ( $hook !== 'user-new.php' ) return;

        wp_enqueue_script(
            'kc-user-gen',
            plugin_dir_url( __FILE__ ) . 'assets/user-gen.js',
            [ 'jquery' ],
            '1.0.1',
            true
        );
        wp_localize_script( 'kc-user-gen', 'KCUserGen', [
            'length'   => 10,
            'charset'  => $this->client_charset(), // A-Z,a-z,0-9 minus iIlLoO0
            'dummyDom' => self::DUMMY_DOMAIN,
        ] );
    }

    /* ----------------------------- Identifier UI ---------------------------- */

    public function render_identifier_field( $operation ) {
        if ( ! self::is_subsite() ) return;
        ?>
        <h3>Extra veld</h3>
        <table class="form-table">
            <tr>
                <th><label for="kc_identifier">Identifier</label></th>
                <td>
                    <input type="text" name="kc_identifier" id="kc_identifier" class="regular-text" required>
                    <p class="description">Vereist tekstveld voor interne identificatie.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function validate_identifier_on_create( $errors, $update, $user ) {
        if ( $update ) return;
        $idf = isset( $_POST['kc_identifier'] ) ? trim( (string) wp_unslash( $_POST['kc_identifier'] ) ) : '';
        if ( $idf === '' ) {
            $errors->add( 'kc_identifier_required', __( 'Identifier is required.', 'default' ) );
        }
    }

    public function save_identifier_on_create( $user_id ) {
        if ( ! self::is_subsite() ) return;
        if ( isset( $_POST['kc_identifier'] ) ) {
            update_user_meta( $user_id, self::META_IDENTIFIER, sanitize_text_field( wp_unslash( $_POST['kc_identifier'] ) ) );
        }
    }

    public function add_identifier_column( $cols ) {
        $cols['kc_identifier'] = 'Identifier';
        return $cols;
    }

    public function render_identifier_column( $val, $column_name, $user_id ) {
        if ( $column_name === 'kc_identifier' ) {
            $v = get_user_meta( $user_id, self::META_IDENTIFIER, true );
            return esc_html( (string) $v );
        }
        return $val;
    }

    public function render_identifier_filter( $which ) {
        if ( ! function_exists( 'get_current_screen' ) ) return;
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->id, [ 'users', 'users-network' ], true ) ) return;
        if ( $which === 'bottom' ) return;

        $current = isset( $_GET['kc_identifier'] )
            ? sanitize_text_field( (string) wp_unslash( $_GET['kc_identifier'] ) )
            : '';

        echo '<label class="screen-reader-text" for="kc_identifier">Identifier</label>';
        echo '<input type="text" name="kc_identifier" id="kc_identifier" value="' . esc_attr( $current ) . '" placeholder="Filter Identifier" class="regular-text" style="margin-left:8px;" />';
        submit_button( __( 'Filter' ), 'secondary', 'filter_action', false );
    }

    public function apply_identifier_filter( $query ) {
        if ( ! is_admin() ) return;

        if ( function_exists( 'get_current_screen' ) ) {
            $screen = get_current_screen();
            if ( $screen && ! in_array( $screen->id, [ 'users', 'users-network' ], true ) ) return;
        }

        $raw = isset( $_REQUEST['kc_identifier'] ) ? (string) wp_unslash( $_REQUEST['kc_identifier'] ) : '';
        $idf = trim( sanitize_text_field( $raw ) );
        if ( $idf === '' ) return;

        global $wpdb;

        $like = '%' . $wpdb->esc_like( $idf ) . '%';
        $ids  = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT user_id
                 FROM {$wpdb->usermeta}
                 WHERE meta_key = %s
                   AND meta_value LIKE %s",
                self::META_IDENTIFIER,
                $like
            )
        );

        if ( self::is_subsite() && ! empty( $ids ) ) {
            $blog_id = get_current_blog_id();
            $cap_key = $wpdb->get_blog_prefix( $blog_id ) . 'capabilities';
            $site_member_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT user_id
                     FROM {$wpdb->usermeta}
                     WHERE meta_key = %s",
                    $cap_key
                )
            );
            $ids = array_values( array_intersect( array_map( 'intval', $ids ), array_map( 'intval', $site_member_ids ) ) );
        }

        if ( empty( $ids ) ) {
            $query->set( 'include', [ 0 ] );
        } else {
            $query->set( 'include', $ids );
        }

        $query->set( 'kc_identifier', $idf );
        $query->set( 'cache_results', false );
        $query->set( 'update_user_meta_cache', false );
        $query->set( 'update_site_meta_cache', false );
    }

    /* ------------------------- Hide email on add user ----------------------- */

    public function hide_email_fields() {
        if ( ! self::is_subsite() ) return; ?>
        <style>
            tr.user-email-wrap, #email, label[for="email"] {display:none!important;}
            #send_user_notification, label[for="send_user_notification"]{display:none!important;}
            p.noconfirmation, #noconfirmation, label[for="noconfirmation"]{display:none!important;}
        </style>
        <script>
            document.addEventListener("DOMContentLoaded",function(){
                ['#send_user_notification','#noconfirmation'].forEach(function(sel){
                    document.querySelectorAll(sel).forEach(function(el){
                        var row = el.closest('tr') || el.closest('p') || el.parentElement;
                        if(row) row.style.display = 'none';
                    });
                });
                document.querySelectorAll('p.noconfirmation').forEach(function(p){ p.style.display='none'; });
            });
        </script>
        <?php
    }

    public function alter_user_new_submission() {
        if ( ! self::is_subsite() ) return;
        if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'createuser' ) return;
        if ( ! current_user_can( 'create_users' ) ) return;

        $u = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ), true ) : '';
        $p = isset( $_POST['pass1'] ) ? (string) $_POST['pass1'] : '';

        if ( $u === '' && isset( $_POST['kc_user_login'] ) ) {
            $u = sanitize_user( wp_unslash( $_POST['kc_user_login'] ), true );
            $_POST['user_login'] = $u;
        }
        if ( $p === '' && isset( $_POST['kc_pass1'] ) ) {
            $p = (string) $_POST['kc_pass1'];
            $_POST['pass1'] = $_POST['pass2'] = $p;
        }

        if ( $u ) {
            $_POST['email'] = $u . '@' . self::DUMMY_DOMAIN;
        }

        add_action( 'user_register', function( $user_id ) use ( $p ) {
            if ( $p ) update_user_meta( $user_id, Keuzeconcept_User_Batch::META_PLAIN_PW, $p );
        } );
    }

    public function force_dummy_email_on_create( $errors, $update, $user ) {
        if ( $update ) return;
        if ( empty( $user->user_login ) ) return;
        $user->user_email = $user->user_login . '@' . self::DUMMY_DOMAIN;
    }

    public function deny_password_reset( $allow, $user_id ) {
        if ( user_can( $user_id, 'manage_options' ) ) return true;
        return false;
    }

    public function block_user_mail_to_dummy( $short_circuit, $atts ) {
        if ( ! empty( $atts['to'] ) && is_string( $atts['to'] ) && str_contains( $atts['to'], '@' . self::DUMMY_DOMAIN ) ) {
            return true;
        }
        return $short_circuit;
    }

    public function wc_hide_account_items( $items ) {
        unset( $items['edit-account'], $items['edit-address'], $items['payment-methods'], $items['lost-password'] );
        return $items;
    }

    public function wc_block_edit_account_endpoint() {
        if ( function_exists( 'is_account_page' ) && is_account_page() ) {
            $endpoint = isset( $GLOBALS['wp']->query_vars ) ? $GLOBALS['wp']->query_vars : [];
            foreach ( [ 'edit-account', 'edit-address', 'payment-methods', 'lost-password' ] as $b ) {
                if ( isset( $endpoint[ $b ] ) ) wp_die( 'Accountbeheer is uitgeschakeld.' );
            }
        }
    }

    /* --------------------- Admin page (Users &#8594; Bulk management) ------------- */

    public function add_bulk_submenu() {
        if ( ! self::is_subsite() ) return;
        add_users_page(
            'Bulk management',
            'Bulk management',
            'create_users',
            'kc-bulk-accounts',
            [ $this, 'render_bulk_page' ]
        );
    }

    public function render_bulk_page() {
        if ( ! current_user_can( 'create_users' ) ) { wp_die( 'Geen rechten.' ); }

        // Handle Import on the same page
        $report = null;
        if ( isset( $_POST['kc_import_csv_site'] ) ) {
            check_admin_referer( 'kc_bulk_import_site' );
            $report = kc_import_update_users_from_csv(); // returns details
        }

        $roles = wp_roles()->get_names();
        ?>
        <div class="wrap">
            <h1>Bulk accounts aanmaken / exporteren</h1>

            <div class="card">
                <h2>Formulier 1: Genereer nieuwe accounts</h2>
                <p><small>
                    Gebruikersnaam/wachtwoord worden willekeurig gegenereerd.<br>
                    Lengte: <strong>10</strong> tekens. Alleen letters en cijfers. Uitgesloten: <code>i I l L o O 0</code>.
                </small></p>

                <form style="margin:20px 0" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( self::NONCE_GEN ); ?>
                    <input type="hidden" name="action" value="kc_bulk_generate">

                    <table>
                        <tr>
                            <th style="width:150px; text-align:left"><label for="kc_qty">Aantal accounts</label></th>
                            <td><input type="number" id="kc_qty" name="kc_qty" min="1" max="5000" value="50" required></td>
                        </tr>
                        <tr>
                            <th style="width:150px; text-align:left"><label for="kc_role">Rol</label></th>
                            <td>
                                <select id="kc_role" name="kc_role">
                                    <?php
                                    $default_role = function_exists( 'wc' ) ? 'customer' : get_option( 'default_role', 'subscriber' );
                                    foreach ( $roles as $role_key => $label ) {
                                        printf(
                                            '<option value="%s"%s>%s</option>',
                                            esc_attr( $role_key ),
                                            selected( $role_key, $default_role, false ),
                                            esc_html( $label )
                                        );
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th style="width:150px; text-align:left"><label for="kc_identifiers">Identifier</label></th>
                            <td><input type="text" id="kc_identifiers" name="kc_identifiers" placeholder="Bijv. BATCH-2025-LOCATIE1" required></td>
                        </tr>
                    </table>

                    <button style="margin-top:20px" type="submit" class="button button-primary">Genereren</button>
                </form>
            </div>

            <div class="card">
                <h2>Formulier 2: Exporteer bestaande gebruikers</h2>
                <p><small>Kolommen: username, password, role, site_id, user_id, identifier, created (incl. e-mail/naam en WooCommerce orderdetails indien aanwezig).</small></p>

                <form style="margin:20px 0" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'kc_bulk_export_site' ); ?>
                    <input type="hidden" name="action" value="kc_export_csv_site">

                    <table>
                        <tr>
                            <th style="width:150px; text-align:left"><label>Rol</label></th>
                            <td>
                                <select name="kc_role">
                                    <option value="__all">Alle rollen</option>
                                    <?php foreach ( $roles as $role_key => $label ) {
                                        printf( '<option value="%s">%s</option>', esc_attr( $role_key ), esc_html( $label ) );
                                    } ?>
                                </select>
                            </td>
                        </tr>
                    </table>

                    <button style="margin-top:20px" type="submit" class="button">Exporteren (CSV)</button>
                </form>
            </div>

            <div class="card">
                <h2>Formulier 3: Import Users CSV (Update by Username)</h2>
                <p><small>Toegestane kolommen: <code>username</code> (verplicht). Alle overige velden (bijv. <code>password</code>, <code>user_email</code>, <code>first_name</code>, <code>last_name</code>, <code>role</code>, <code>wallet_balance</code>) zijn optioneel. Ontbrekende of ongeldige e-mailadressen krijgen automatisch een placeholder. Vult bestaande gebruikers bij; optioneel nieuwe aanmaken.</small></p>

                <form style="margin:20px 0" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'kc_bulk_import_site' ); ?>
                    <input type="file" name="kc_import_file" accept=".csv" required />

                    <?php
                    // Roles dropdown
                    $roles         = wp_roles()->get_names();
                    $default_role  = function_exists( 'wc' ) ? 'customer' : get_option( 'default_role', 'subscriber' );
                    // Keep safe: ensure default exists
                    if ( ! isset( $roles[ $default_role ] ) ) {
                        $default_role = 'subscriber';
                    }
                    ?>
                    <div style="margin-top:12px;">
                        <label for="kc_import_role_default"><strong>Standaard rol voor nieuwe/geüpdatete gebruikers</strong></label><br>
                        <select id="kc_import_role_default" name="kc_import_role_default" style="min-width:240px;">
                            <?php foreach ( $roles as $role_key => $label ) : ?>
                                <option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( $role_key, $default_role ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description" style="max-width:640px;">
                            De CSV kan ook een kolom <code>role</code> bevatten (bijv. <code>customer</code>, <code>subscriber</code>, <code>shop_manager</code>).
                            Wanneer aanwezig en geldig, heeft de CSV-rol voorrang. Anders wordt deze standaard rol gebruikt.
                        </p>
                    </div>

                    <p style="margin:8px 0;">
                        <label>
                            <input type="checkbox" name="kc_import_update_role" value="1">
                            Update rol voor <em>bestaande</em> gebruikers (op deze site)
                        </label>
                    </p>

                    <p style="margin-top:8px;">
                        <label><input type="checkbox" name="kc_create_missing" value="1"> Maak gebruiker aan als deze niet bestaat</label>
                    </p>

                    <p><button class="button button-primary" name="kc_import_csv_site" value="1">Run Import</button></p>
                </form>

            </div>

            <?php if ( is_array( $report ) && ! empty( $report['rows'] ) ) : ?>
                <div class="card" style="margin-top:24px;">
                    <h2>Importresultaten</h2>
                    <p><strong><?php
                        printf(
                            'Created: %d | Updated: %d | Skipped: %d | Errors: %d',
                            $report['totals']['created'],
                            $report['totals']['updated'],
                            $report['totals']['skipped'],
                            $report['totals']['errors']
                        );
                    ?></strong></p>
                    <table class="widefat striped" style="max-width:980px;">
                        <thead><tr>
                            <th>Lijn</th>
                            <th>Username</th>
                            <th>Actie</th>
                            <th>Bericht</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ( $report['rows'] as $r ) : ?>
                            <tr>
                                <td><?php echo (int) $r['line']; ?></td>
                                <td><?php echo esc_html( $r['username'] ); ?></td>
                                <td><?php echo esc_html( $r['action'] ); ?></td>
                                <td><?php echo esc_html( $r['message'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
        <?php
    }

    /* ---------------------- Actions (Generate / Export) --------------------- */

    public function handle_bulk_generate() {
        if ( ! current_user_can( 'create_users' ) ) wp_die( 'Geen rechten.' );
        check_admin_referer( self::NONCE_GEN );

        $qty  = max( 1, min( 5000, intval( $_POST['kc_qty'] ?? 0 ) ) );
        $role = sanitize_key( $_POST['kc_role'] ?? '' );

        if ( empty( $role ) ) {
            $role = function_exists( 'wc' ) ? 'customer' : get_option( 'default_role', 'subscriber' );
        } else {
            $editable_roles = array_keys( get_editable_roles() );
            if ( ! in_array( $role, $editable_roles, true ) ) {
                $role = function_exists( 'wc' ) ? 'customer' : get_option( 'default_role', 'subscriber' );
            }
        }

        $raw_ids     = isset( $_POST['kc_identifiers'] ) ? (string) wp_unslash( $_POST['kc_identifiers'] ) : '';
        $identifiers = array_values( array_filter( array_map( 'trim', preg_split( '/\R+/', $raw_ids ) ), 'strlen' ) );
        $identifiers = array_map( 'sanitize_text_field', $identifiers );

        if ( count( $identifiers ) === 0 ) {
            wp_die( 'Voer minstens &#65533;&#65533;n identifier in.' );
        }
        if ( count( $identifiers ) === 1 ) {
            $identifiers = array_fill( 0, $qty, $identifiers[0] );
        } elseif ( count( $identifiers ) !== $qty ) {
            wp_die( sprintf(
                'Aantal identifiers (%d) moet 1 (batch) of gelijk aan aantal accounts (%d) zijn.',
                count( $identifiers ),
                $qty
            ) );
        }

        $blog_id       = get_current_blog_id();
        $created       = [];
        $used_passwords = $this->collect_existing_plain_passwords( $blog_id );

        for ( $i = 0; $i < $qty; $i++ ) {
            $username = $this->unique_username();
            $password = $this->unique_password( $used_passwords );
            $email    = $username . '@' . self::DUMMY_DOMAIN;
            $ident    = $identifiers[ $i ];

            $user_id = username_exists( $username );
            if ( ! $user_id ) {
                $user_id = wp_insert_user( [
                    'user_login'   => $username,
                    'user_pass'    => $password,
                    'user_email'   => $email,
                    'display_name' => $username,
                    'role'         => $role,
                ] );
            }

            if ( is_wp_error( $user_id ) ) {
                $created[] = [
                    'username'   => $username,
                    'password'   => '',
                    'role'       => $role,
                    'site_id'    => $blog_id,
                    'user_id'    => 'ERROR: ' . $user_id->get_error_message(),
                    'identifier' => $ident,
                    'created'    => current_time( 'mysql' ),
                ];
                continue;
            }

            if ( is_multisite() ) {
                add_user_to_blog( $blog_id, $user_id, $role );
            }

            update_user_meta( $user_id, self::META_PLAIN_PW, $password );
            update_user_meta( $user_id, self::META_IDENTIFIER, $ident );

            $created[] = [
                'username'   => $username,
                'password'   => $password,
                'role'       => $role,
                'site_id'    => $blog_id,
                'user_id'    => $user_id,
                'identifier' => $ident,
                'created'    => current_time( 'mysql' ),
            ];

            $used_passwords[ $password ] = true;
        }

        $this->output_csv_download(
            'generated-users-site' . $blog_id . '-' . date( 'Ymd-His' ) . '.csv',
            [ 'username', 'password', 'role', 'site_id', 'user_id', 'identifier', 'created' ],
            $created
        );
        exit;
    }

    public function handle_export_csv_site() {
        if ( ! current_user_can( 'list_users' ) ) wp_die( __( 'You do not have permission.', 'kc' ) );
        check_admin_referer( 'kc_bulk_export_site' );

        $role = sanitize_key( $_POST['kc_role'] ?? '__all' );
        kc_export_for_blog( get_current_blog_id(), $role ); // streams & exits
    }

    /* ------------------------------- Utils ---------------------------------- */

    private function client_charset(): string {
        $upper  = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        $lower  = 'abcdefghjkmnpqrstuvwxyz';
        $digits = '123456789';
        return $upper . $lower . $digits;
    }

    private function random_string( int $length ): string {
        $chars = $this->client_charset();
        $bytes = random_bytes( $length );
        $out   = '';
        $max   = strlen( $chars ) - 1;
        for ( $i = 0; $i < $length; $i++ ) {
            $out .= $chars[ ord( $bytes[ $i ] ) % ( $max + 1 ) ];
        }
        return $out;
    }

    private function unique_username(): string {
        do { $u = $this->random_string( 10 ); } while ( username_exists( $u ) );
        return $u;
    }

    private function unique_password( array $used_pw_map ): string {
        do { $p = $this->random_string( 10 ); } while ( isset( $used_pw_map[ $p ] ) );
        return $p;
    }

    private function collect_existing_plain_passwords( int $blog_id ): array {
        $map   = [];
        $users = get_users( [ 'blog_id' => $blog_id, 'fields' => [ 'ID' ] ] );
        foreach ( $users as $u ) {
            $pw = get_user_meta( $u->ID, self::META_PLAIN_PW, true );
            if ( $pw ) $map[ $pw ] = true;
        }
        return $map;
    }

    private function role_of_user_on_current_blog( int $user_id ): string {
        $user  = new WP_User( $user_id );
        $roles = is_array( $user->roles ) ? $user->roles : [];
        return $roles ? implode( '|', $roles ) : '';
    }

    private function output_csv_download( string $filename, array $headers, array $rows ): void {
        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        echo "\xEF\xBB\xBF";
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, $headers );
        foreach ( $rows as $r ) {
            $line = [];
            foreach ( $headers as $h ) { $line[] = $r[ $h ] ?? ''; }
            fputcsv( $out, $line );
        }
        fclose( $out );
    }
}
new Keuzeconcept_User_Batch();

/* -----------------------------------------------------------------------------
 * Constants / helpers used outside the class
 * -------------------------------------------------------------------------- */
if ( ! defined( 'KC_IDENTIFIER_META_KEY' ) ) {
    define( 'KC_IDENTIFIER_META_KEY', 'kc_identifier' );
}

/* -----------------------------------------------------------------------------
 * Export helpers
 * -------------------------------------------------------------------------- */

function kc_export_for_blog( $blog_id, $role = '__all' ) {
    @set_time_limit( 0 );

    $filename = 'users_export_site-' . (int) $blog_id . '_' . date( 'Ymd_His' ) . '.csv';
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=' . $filename );

    $out = fopen( 'php://output', 'w' );
    kc_write_csv_header( $out );

    switch_to_blog( $blog_id );
    kc_stream_users_for_current_blog( $out, $blog_id, $role );
    restore_current_blog();

    fclose( $out );
    exit;
}

function kc_write_csv_header( $out ) {
    fputcsv( $out, [
        'username','password','role','site_id','user_id','identifier','created',
        'wallet_balance', // TeraWallet
        'user_email','first_name','last_name','total_orders',
        'order_id','order_date','status',
        'billing_address1','billing_address2','billing_postcode','billing_city','billing_state','billing_country',
        'item_sku','item_name','item_qty',
    ] );
}


function kc_stream_users_for_current_blog( $out, $blog_id, $role = '__all' ) {
    $has_wc = function_exists( 'wc_get_orders' );

    $pw_meta_key = ( class_exists( 'Keuzeconcept_User_Batch' ) )
        ? Keuzeconcept_User_Batch::META_PLAIN_PW
        : 'kc_plain_password';

    $args = [
        'blog_id' => $blog_id,
        'fields'  => [ 'ID', 'user_login', 'user_email', 'user_registered' ],
        'number'  => -1,
    ];
    if ( $role && $role !== '__all' ) {
        $args['role'] = $role;
    }

    $users = get_users( $args );

    foreach ( $users as $u ) {
        $user_id    = (int) $u->ID;
        $username   = $u->user_login;
        $identifier = get_user_meta( $user_id, KC_IDENTIFIER_META_KEY, true );
        $created    = $u->user_registered ?: '';
        $first_name = get_user_meta( $user_id, 'first_name', true );
        $last_name  = get_user_meta( $user_id, 'last_name', true );
        $role_str   = kc_first_user_role_for_blog( $user_id, $blog_id );

        $plain_pw   = (string) get_user_meta( $user_id, $pw_meta_key, true );

        // Wallet balance (TeraWallet)
        $wallet = kc_get_wallet_balance( $user_id ); // normalized "123.45" or ""

        $total_orders = 0;
        $orders       = [];

        if ( $has_wc ) {
            $orders = wc_get_orders( [
                'customer_id' => $user_id,
                'limit'       => -1,
                'orderby'     => 'date',
                'order'       => 'DESC',
            ] );
            $total_orders = is_array( $orders ) ? count( $orders ) : 0;
        }

        if ( ! $has_wc || $total_orders === 0 ) {
            fputcsv( $out, [
                $username, $plain_pw, $role_str, $blog_id, $user_id, $identifier, $created,
                $wallet,
                $u->user_email, $first_name, $last_name, 0,
                '','','','','','','','','','',
            ] );
            continue;
        }

        foreach ( $orders as $order ) {
            $items = $order->get_items( 'line_item' );
            $skus = $names = $qtys = [];

            foreach ( $items as $item ) {
                $product = $item->get_product();
                $skus[]  = $product ? $product->get_sku() : '';
                $names[] = $item->get_name();
                $qtys[]  = (string) $item->get_quantity();
            }

            fputcsv( $out, [
                $username, $plain_pw, $role_str, $blog_id, $user_id, $identifier, $created,
                $wallet,
                $u->user_email, $first_name, $last_name, $total_orders,
                $order->get_id(),
                $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i:s' ) : '',
                $order->get_status(),
                $order->get_billing_address_1(),
                $order->get_billing_address_2(),
                $order->get_billing_postcode(),
                $order->get_billing_city(),
                $order->get_billing_state(),
                $order->get_billing_country(),
                implode( ';', array_map( 'kc_sanitize_join_val', $skus ) ),
                implode( ';', array_map( 'kc_sanitize_join_val', $names ) ),
                implode( ';', array_map( 'kc_sanitize_join_val', $qtys ) ),
            ] );
        }
    }
}


/* -----------------------------------------------------------------------------
 * Import (update by username; optional create)
 * -------------------------------------------------------------------------- */

function kc_import_update_users_from_csv() {
    $report = [
        'rows'   => [],
        'totals' => [ 'updated' => 0, 'created' => 0, 'skipped' => 0, 'errors' => 0 ],
    ];

    $create_if_missing     = ! empty( $_POST['kc_create_missing'] );
    $update_role_existing  = ! empty( $_POST['kc_import_update_role'] );

    // Roles map + keys
    $editable_roles_map = get_editable_roles();
    $editable_roles     = is_array( $editable_roles_map ) ? array_keys( $editable_roles_map ) : [];

    // Resolve default role from the form via the helper (accepts labels/aliases too)
    $default_role_raw = sanitize_text_field( $_POST['kc_import_role_default'] ?? '' );
    $default_role     = kc_resolve_role_key( $default_role_raw, $editable_roles_map );
    if ( $default_role === '' ) {
        $fallback     = function_exists( 'wc' ) ? 'customer' : get_option( 'default_role', 'subscriber' );
        $default_role = kc_resolve_role_key( $fallback, $editable_roles_map );
        if ( $default_role === '' ) $default_role = 'subscriber';
    }

    // --- File checks
    if ( empty( $_FILES['kc_import_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['kc_import_file']['tmp_name'] ) ) {
        $msg = __( 'No file uploaded or invalid upload.', 'kc' );
        kc_admin_notice( $msg, 'error' );
        kc_admin_notice_net( $msg, 'error' );
        return $report;
    }

    $fh = fopen( $_FILES['kc_import_file']['tmp_name'], 'r' );
    if ( ! $fh ) {
        $msg = __( 'Unable to open uploaded file.', 'kc' );
        kc_admin_notice( $msg, 'error' );
        kc_admin_notice_net( $msg, 'error' );
        return $report;
    }

    // --- Detect delimiter: , ; or TAB
    $firstLine = '';
    while ( ($firstLine = fgets($fh)) !== false ) {
        if ( trim($firstLine) !== '' ) { break; }
    }
    if ( $firstLine === false ) {
        fclose($fh);
        $msg = __( 'CSV appears empty.', 'kc' );
        kc_admin_notice( $msg, 'error' );
        kc_admin_notice_net( $msg, 'error' );
        return $report;
    }
    rewind($fh);
    $delims   = [',', ';', "\t"];
    $bestDelim= ',';
    $bestHits = -1;
    foreach ( $delims as $d ) {
        $hits = substr_count($firstLine, $d);
        if ( $hits > $bestHits ) { $bestHits = $hits; $bestDelim = $d; }
    }

    // --- Header
    $header = fgetcsv( $fh, 0, $bestDelim );
    if ( ! $header || ! is_array( $header ) ) {
        fclose( $fh );
        $msg = sprintf( __( 'CSV header not readable (delimiter: %s).', 'kc' ), $bestDelim === "\t" ? 'TAB' : $bestDelim );
        kc_admin_notice( $msg, 'error' );
        kc_admin_notice_net( $msg, 'error' );
        return $report;
    }

    $strip_bom = static function ( $s ) {
        $s = (string) $s;
        if ( str_starts_with( $s, "\xEF\xBB\xBF" ) ) {
            return substr( $s, 3 );
        }
        if ( str_starts_with( $s, "\xFF\xFE" ) || str_starts_with( $s, "\xFE\xFF" ) ) {
            // UTF-16 BOM found; fgetcsv on UTF-16 is unreliable, but at least strip the BOM.
            return '';
        }
        return $s;
    };

    $norm = function( $s ) use ( $strip_bom ) {
        $s = $strip_bom( $s );
        $s = strtolower( trim( (string) $s ) );
        $s = str_replace( [ ' ', '-' ], '_', $s );
        return $s;
    };
    $map = [];
    foreach ( $header as $i => $col ) { $map[ $norm( $col ) ] = $i; }

    // Column resolver with aliases
    $get_col = function( array $candidates ) use ( $map ) {
        foreach ( $candidates as $c ) {
            if ( isset( $map[$c] ) ) return $map[$c];
        }
        return null;
    };

    $idx_username = $get_col( [ 'username', 'user_login', 'login', 'user' ] );
    if ( $idx_username === null ) {
        fclose( $fh );
        $msg = __( 'Missing required column: username (aliases: user_login, login, user).', 'kc' );
        kc_admin_notice( $msg, 'error' );
        kc_admin_notice_net( $msg, 'error' );
        return $report;
    }

    $idx_pass    = $get_col( [ 'password', 'user_pass' ] );
    $idx_mail    = $get_col( [ 'user_email', 'email', 'mail' ] );
    $idx_fn      = $get_col( [ 'first_name', 'user_first_name', 'firstname' ] );
    $idx_ln      = $get_col( [ 'last_name', 'user_last_name', 'lastname', 'surname' ] );
    $idx_wallet  = $get_col( [ 'wallet_balance', 'wallet', 'walletamount', 'wallet_balance_target' ] );
    $idx_role    = $get_col( [ 'role', 'user_role', 'wp_role' ] );

    $read_row = function() use ( $fh, $bestDelim ) { return fgetcsv( $fh, 0, $bestDelim ); };

    // --- Rows
    $line = 1; // header
    while ( ( $row = $read_row() ) !== false ) {
        $line++;
        $username = trim( (string) ( $row[ $idx_username ] ?? '' ) );

        if ( $username === '' ) {
            $report['rows'][] = [ 'line' => $line, 'username' => '', 'action' => 'SKIPPED', 'message' => 'Empty username' ];
            $report['totals']['skipped']++;
            continue;
        }

        // Read & sanitize row values (everything optional except username)
        $email_raw      = ( $idx_mail !== null ) ? (string) ( $row[ $idx_mail ] ?? '' ) : '';
        $email          = sanitize_email( $email_raw );
        $email_provided = trim( $email_raw ) !== '';
        $email_valid    = $email !== '' && is_email( $email );
        $email_note     = '';

        $first    = ( $idx_fn     !== null ) ? sanitize_text_field( (string) ( $row[ $idx_fn ]     ?? '' ) ) : '';
        $last     = ( $idx_ln     !== null ) ? sanitize_text_field( (string) ( $row[ $idx_ln ]     ?? '' ) ) : '';
        $pass_in  = ( $idx_pass   !== null ) ? (string)             ( $row[ $idx_pass ]   ?? '' ) : '';
        $role_raw = ( $idx_role   !== null ) ? (string)             ( $row[ $idx_role ]   ?? '' ) : '';

        // Resolve per-row role via helper; fallback to default
        $role_key    = kc_resolve_role_key( $role_raw, $editable_roles_map );
        $role_to_use = $role_key !== '' ? $role_key : $default_role;

        $wallet_in_raw = ( $idx_wallet !== null ) ? (string) ( $row[ $idx_wallet ] ?? '' ) : '';
        $wallet_in     = kc_sanitize_amount( $wallet_in_raw );

        $existing = get_user_by( 'login', $username );

        if ( $existing ) {
            $user_id = $existing->ID;

            // Basic profile updates
            $userdata = [ 'ID' => $user_id ];
            if ( $pass_in !== '' ) {
                $userdata['user_pass']  = $pass_in;
            }
            if ( $email_valid ) {
                $userdata['user_email'] = $email;
            } elseif ( $email_provided ) {
                $email_note = ' (email skipped: invalid format)';
            }

            if ( count( $userdata ) > 1 ) {
                $res = wp_update_user( $userdata );
                if ( is_wp_error( $res ) ) {
                    $report['rows'][] = [ 'line' => $line, 'username' => $username, 'action' => 'ERROR', 'message' => $res->get_error_message() ];
                    $report['totals']['errors']++;
                    continue;
                }
            }

            if ( $first !== '' ) update_user_meta( $user_id, 'first_name', $first );
            if ( $last  !== '' ) update_user_meta( $user_id, 'last_name',  $last );

            // Role update (this site)
            if ( $update_role_existing || ($idx_role !== null && $role_raw !== '') ) {
                if ( is_multisite() && ! is_user_member_of_blog( $user_id, get_current_blog_id() ) ) {
                    add_user_to_blog( get_current_blog_id(), $user_id, $role_to_use );
                } else {
                    $wp_user = new WP_User( $user_id );
                    $wp_user->set_role( $role_to_use ); // replaces all roles
                }
            }

            // Wallet target
            if ( $wallet_in !== '' ) {
                kc_set_wallet_balance( $user_id, $wallet_in, sprintf( 'Import set balance to %s', $wallet_in ) );
            }

            // Optional note if role value fell back
            $role_note = ($idx_role !== null && $role_raw !== '' && $role_key === '') ? " (role '".sanitize_text_field($role_raw)."' not recognized &#8594; used default '".$role_to_use."')" : '';

            $report['rows'][] = [ 'line' => $line, 'username' => $username, 'action' => 'UPDATED', 'message' => 'User updated' . $role_note . $email_note ];
            $report['totals']['updated']++;
            continue;
        }

        // Create new user?
        if ( ! $create_if_missing ) {
            $report['rows'][] = [ 'line' => $line, 'username' => $username, 'action' => 'SKIPPED', 'message' => 'User not found' ];
            $report['totals']['skipped']++;
            continue;
        }

        $fallback_domain = class_exists( 'Keuzeconcept_User_Batch' ) ? Keuzeconcept_User_Batch::DUMMY_DOMAIN : 'example.invalid';

        // Email is optional for creation; use safe fallback when empty/invalid.
        if ( ! $email_valid ) {
            $email_base      = sanitize_key( $username );
            if ( $email_base === '' ) {
                $email_base = 'imported';
            }
            $email       = $email_base . '@' . $fallback_domain;
            $email_valid = is_email( $email );

            if ( $email_provided ) {
                $email_note = ' (email replaced with fallback)';
            }
        }

        // Absolute guard: never block creation on email formatting.
        if ( ! $email_valid ) {
            $email       = 'imported@' . $fallback_domain;
            $email_valid = true;
        }

        $password = ( $pass_in !== '' ) ? $pass_in : wp_generate_password( 12 );

        // Create user with role
        $userdata = [
            'user_login'   => $username,
            'user_pass'    => $password,
            'user_email'   => $email,
            'display_name' => $username,
            'role'         => $role_to_use,
        ];
        $new_id = wp_insert_user( $userdata );

        if ( is_wp_error( $new_id ) ) {
            $report['rows'][] = [ 'line' => $line, 'username' => $username, 'action' => 'ERROR', 'message' => $new_id->get_error_message() ];
            $report['totals']['errors']++;
            continue;
        }

        if ( is_multisite() ) {
            add_user_to_blog( get_current_blog_id(), $new_id, $role_to_use );
        }

        if ( $first !== '' ) update_user_meta( $new_id, 'first_name', $first );
        if ( $last  !== '' ) update_user_meta( $new_id, 'last_name',  $last );

        if ( $wallet_in !== '' ) {
            kc_set_wallet_balance( $new_id, $wallet_in, sprintf( 'Import set balance to %s', $wallet_in ) );
        }

        // Optional note if role value fell back
        $role_note = ($idx_role !== null && $role_raw !== '' && $role_key === '') ? " (role '".sanitize_text_field($role_raw)."' not recognized &#8594; used default '".$role_to_use."')" : '';

        $report['rows'][] = [ 'line' => $line, 'username' => $username, 'action' => 'CREATED', 'message' => 'User created' . $role_note ];
        $report['totals']['created']++;
    }

    fclose( $fh );

    $msg = sprintf(
        'Import complete. Created: %d, Updated: %d, Skipped: %d, Errors: %d',
        $report['totals']['created'],
        $report['totals']['updated'],
        $report['totals']['skipped'],
        $report['totals']['errors']
    );
    kc_admin_notice( $msg, $report['totals']['errors'] ? 'error' : 'success' );
    kc_admin_notice_net( $msg, $report['totals']['errors'] ? 'error' : 'success' );

    return $report;
}



/* -----------------------------------------------------------------------------
 * Misc helpers
 * -------------------------------------------------------------------------- */

function kc_sanitize_join_val( $val ) {
    $val = (string) $val;
    $val = str_replace( [ ";\r\n", ";\n", ";\r", ";" ], "/", $val );
    return trim( $val );
}
function kc_sanitize_amount( $val ) {
    if ( $val === null ) return '';
    $s = trim( (string) $val );
    if ( $s === '' ) return '';

    if ( function_exists( 'wc_format_decimal' ) ) {
        $norm = wc_format_decimal( $s, 2 );
        if ( $norm !== '' && $norm !== null ) {
            // wc_format_decimal can return numeric|false|string; unify to string with 2 decimals
            return number_format( (float) $norm, 2, '.', '' );
        }
    }

    // fallback: pull last number out if needed, then normalize
    if ( ! is_numeric( $s ) ) {
        $s = kc_extract_last_number( $s );
    }
    if ( $s === '' || ! is_numeric( $s ) ) return '';
    return number_format( (float) $s, 2, '.', '' );
}



function kc_first_user_role_for_blog( $user_id, $blog_id ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) return '';
    $roles = (array) $user->roles;
    return $roles ? array_values( $roles )[0] : '';
}

function kc_admin_notice( $msg, $type = 'success' ) {
    if ( is_network_admin() ) return;

    $html = sprintf(
        '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
        esc_attr( $type === 'error' ? 'error' : 'success' ),
        esc_html( $msg )
    );

    // If admin_notices already ran (when rendering the same request), output immediately.
    if ( did_action( 'admin_notices' ) || did_action( 'all_admin_notices' ) ) {
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return;
    }

    add_action( 'admin_notices', function () use ( $html ) {
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } );
}

function kc_admin_notice_net( $msg, $type = 'success' ) {
    if ( ! is_network_admin() ) return;

    $html = sprintf(
        '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
        esc_attr( $type === 'error' ? 'error' : 'success' ),
        esc_html( $msg )
    );

    if ( did_action( 'network_admin_notices' ) || did_action( 'all_admin_notices' ) ) {
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return;
    }

    add_action( 'network_admin_notices', function () use ( $html ) {
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } );
}


/**
 * Get current wallet balance using TeraWallet, with safe fallbacks.
 */
function kc_get_wallet_balance( $user_id ) {
    $user_id = (int) $user_id;

    // Prefer TeraWallet API
    if ( function_exists( 'woo_wallet' ) && isset( woo_wallet()->wallet ) ) {
        $wallet = woo_wallet()->wallet;
        if ( method_exists( $wallet, 'get_wallet_balance' ) ) {
            $raw = $wallet->get_wallet_balance( $user_id ); // may be formatted
            if ( is_numeric( $raw ) ) {
                return kc_sanitize_amount( $raw );
            }
            $num = kc_extract_last_number( $raw );
            return kc_sanitize_amount( $num );
        }
    }

    // Fallback: meta cache some versions keep
    $meta_bal = get_user_meta( $user_id, '_current_woo_wallet_balance', true );
    if ( is_numeric( $meta_bal ) ) {
        return kc_sanitize_amount( $meta_bal );
    }
    $num = kc_extract_last_number( $meta_bal );
    return kc_sanitize_amount( $num );
}


/**
 * Set wallet balance by creating a credit/debit transaction via TeraWallet.
 * If TeraWallet not available, last-resort fallback updates meta (not recommended).
 */
function kc_set_wallet_balance( $user_id, $target_amount, $note = 'Bulk import adjustment' ) {
    $user_id = (int) $user_id;
    $target  = (float) kc_sanitize_amount( $target_amount );
    $current = (float) kc_get_wallet_balance( $user_id );
    $delta   = round( $target - $current, 2 );

    if ( abs( $delta ) < 0.01 ) {
        return true; // nothing to do
    }

    if ( function_exists( 'woo_wallet' ) && isset( woo_wallet()->wallet ) ) {
        $wallet = woo_wallet()->wallet;

        if ( $delta > 0 && method_exists( $wallet, 'credit' ) ) {
            $wallet->credit( $user_id, $delta, $note );
            return true;
        } elseif ( $delta < 0 && method_exists( $wallet, 'debit' ) ) {
            $wallet->debit( $user_id, abs( $delta ), $note );
            return true;
        }

        // Fallback to actions if methods are not exposed
        if ( $delta > 0 ) {
            do_action( 'woo_wallet_credit', $user_id, $delta, $note );
            return true;
        } else {
            do_action( 'woo_wallet_debit',  $user_id, abs( $delta ), $note );
            return true;
        }
    }

    // Last resort (not ideal): write cached balance meta
    update_user_meta( $user_id, '_current_woo_wallet_balance', number_format( $target, 2, '.', '' ) );
    return true;
}


/**
 * Extract the last numeric token (e.g., "10" from "&#127873; 10", or "1,234.50" from "&#8377; 1,234.50").
 * Returns '' if not found.
 */
function kc_extract_last_number( $s ) {
    if ( $s === null ) return '';
    $s = (string) $s;
    $s = wp_strip_all_tags( $s );
    $s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

    if ( preg_match_all( '/-?\d{1,3}(?:([.,])\d{3})*(?:\1\d{3})*(?:[.,]\d+)?|-?\d+(?:[.,]\d+)?/', $s, $m ) && ! empty( $m[0] ) ) {
        $last = trim( end( $m[0] ) );
        $has_dot   = strrpos( $last, '.' );
        $has_comma = strrpos( $last, ',' );

        if ( $has_dot !== false && $has_comma !== false ) {
            $dec_pos  = max( $has_dot, $has_comma );
            $int_part = preg_replace( '/[^\d]/', '', substr( $last, 0, $dec_pos ) );
            $frac_part= preg_replace( '/[^\d]/', '', substr( $last, $dec_pos + 1 ) );
            $last = $int_part . '.' . $frac_part;
        } else {
            $last = str_replace( ',', '.', $last );
            $last = preg_replace( '/[^0-9.\-]/', '', $last );
        }
        if ( is_numeric( $last ) ) return $last;
    }
    return '';
}

/**
 * Resolve a CSV role value to a valid WP role key.
 * Accepts labels ("Shop Manager"), aliases ("admin"), hyphens/spaces, and some nl_NL terms.
 * Returns role key (e.g. 'shop_manager') or '' if no match.
 */
function kc_resolve_role_key( $input, array $editable_roles_map ) {
    $in = trim( (string) $input );
    if ( $in === '' ) return '';

    // Build lookup: key &#8594; key, label &#8594; key, plus aliases
    $lut = [];

    // editable_roles_map: [ 'subscriber' => [ 'name' => 'Subscriber', ... ], ... ]
    foreach ( $editable_roles_map as $key => $def ) {
        $lut[ strtolower( $key ) ] = $key; // exact keys
        if ( ! empty( $def['name'] ) ) {
            $lut[ strtolower( (string) $def['name'] ) ] = $key; // human label
        }
    }

    // Common aliases / localizations (extend as you like)
    $aliases = [
        'admin'         => 'administrator',
        'beheerder'     => 'administrator',
        'editor'        => 'editor',
        'auteur'        => 'author',
        'author'        => 'author',
        'contributor'   => 'contributor',
        'abonnee'       => 'subscriber',
        'subscriber'    => 'subscriber',
        'klant'         => 'customer',
        'customer'      => 'customer',
        'shop manager'  => 'shop_manager',
        'shop-manager'  => 'shop_manager',
        'shop_manager'  => 'shop_manager',
    ];
    foreach ( $aliases as $k => $v ) {
        $lut[ strtolower( $k ) ] = $v;
    }

    $norms = [];
    $norms[] = strtolower( $in );
    $norms[] = strtolower( str_replace( ['-', ' '], '_', $in ) ); // “Shop Manager” &#8594; “shop_manager”
    $norms[] = strtolower( str_replace( ['_', ' '], '-', $in ) ); // “shop_manager” &#8594; “shop-manager”
    $norms[] = strtolower( str_replace( ['-', '_'], ' ', $in ) ); // “shop-manager” &#8594; “shop manager”

    foreach ( array_unique( $norms ) as $candidate ) {
        if ( isset( $lut[ $candidate ] ) ) {
            return $lut[ $candidate ];
        }
    }
    return '';
}
