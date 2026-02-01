<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}
if (!function_exists('managepromo_is_enabled') || !managepromo_is_enabled('woo_webshop_closure')) {return;}

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////



final class DS_Webshop_Closure {
    const OPTION_KEY = 'ds_webshop_closure';

    public function __construct() {
        // Admin UI
        add_action('admin_menu', [$this, 'add_admin_page'], 49);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_css']);

        // Front-end redirect logic
        add_action('template_redirect', [$this, 'maybe_redirect_blocked_urls'], 1);

        // Admin bar badge
        add_action('admin_bar_menu', [$this, 'admin_bar_status_badge'], 100);
    }

    /** Get merged settings with defaults */
    public static function get_settings(): array {
        $defaults = [
            'date'               => '', // Y-m-d
            'time'               => '', // H:i
            'redirect_page_id'   => 0,
            'blocked_urls'       => "", // newline separated paths
            'admin_bypass'       => 1,  // default allow admin bypass
        ];
        $opts = get_option(self::OPTION_KEY, []);
        return wp_parse_args(is_array($opts) ? $opts : [], $defaults);
    }

    /** ================== Admin Page ================== */
    public function add_admin_page() {
        // Parent 'woocommerce' puts this submenu under WooCommerce (near "Home")
        add_submenu_page(
            'managepromo',
            'Webshop sluiten',
            'Webshop sluiten',
            'manage_options',
            'ds-close-webshop',
            [$this, 'render_page'],
            1
        );
    }

    public function register_settings() {
        register_setting(self::OPTION_KEY, self::OPTION_KEY, [$this, 'sanitize']);
        add_settings_section('ds_wc_closure_main', '', '__return_false', self::OPTION_KEY);

        add_settings_field('ds_wc_closure_date', __('Closure Date', 'ds-wc-closure'), function () {
            $o = self::get_settings(); ?>
            <input type="date" name="<?php echo esc_attr(self::OPTION_KEY); ?>[date]" value="<?php echo esc_attr($o['date']); ?>" />
        <?php }, self::OPTION_KEY, 'ds_wc_closure_main');

        add_settings_field('ds_wc_closure_time', __('Closure Time', 'ds-wc-closure'), function () {
            $o = self::get_settings(); ?>
            <input type="time" name="<?php echo esc_attr(self::OPTION_KEY); ?>[time]" value="<?php echo esc_attr($o['time']); ?>" />
            <p class="description"><?php esc_html_e('Uses your site timezone (Settings &#8594; General).', 'ds-wc-closure'); ?></p>
        <?php }, self::OPTION_KEY, 'ds_wc_closure_main');

        add_settings_field('ds_wc_closure_redirect', __('Redirect Page', 'ds-wc-closure'), function () {
            $o = self::get_settings();
            wp_dropdown_pages([
                'name'              => self::OPTION_KEY . '[redirect_page_id]',
                'selected'          => (int) $o['redirect_page_id'],
                'show_option_none'  => __('— Select a page —', 'ds-wc-closure'),
            ]);
        }, self::OPTION_KEY, 'ds_wc_closure_main');

        add_settings_field('ds_wc_closure_urls', __('Blocked URLs', 'ds-wc-closure'), function () {
            $o   = self::get_settings();
            $val = (string) $o['blocked_urls']; ?>
            <textarea name="<?php echo esc_attr(self::OPTION_KEY); ?>[blocked_urls]" rows="7" class="large-text code" placeholder="/shop/
/product-category/
/categorie/alle-producten/">
                <?php echo esc_textarea($val); ?>
            </textarea>
            <p class="description">
                <?php esc_html_e('One per line. Enter paths like "/shop" or "/product-category/shoes".', 'ds-wc-closure'); ?>
                <?php esc_html_e('Matches the exact path and any children (e.g., "/shop/page/2") automatically. Works even if your site runs under a subdirectory like "/demo2025".', 'ds-wc-closure'); ?>
            </p>
        <?php }, self::OPTION_KEY, 'ds_wc_closure_main');

        add_settings_field('ds_wc_closure_admin_bypass', __('Admin Bypass', 'ds-wc-closure'), function () {
            $o = self::get_settings(); ?>
            <label>
                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[admin_bypass]" value="1" <?php checked((int)$o['admin_bypass'], 1); ?>/>
                <?php esc_html_e('Allow administrators to bypass redirects (for testing).', 'ds-wc-closure'); ?>
            </label>
        <?php }, self::OPTION_KEY, 'ds_wc_closure_main');
    }

    public function sanitize($input) {
        $out = self::get_settings();

        $out['date'] = isset($input['date']) ? preg_replace('~[^0-9\-]~', '', (string)$input['date']) : '';
        $out['time'] = isset($input['time']) ? preg_replace('~[^0-9:\.]~', '', (string)$input['time']) : '';
        $out['redirect_page_id'] = isset($input['redirect_page_id']) ? (int)$input['redirect_page_id'] : 0;

        // Normalize blocked URLs: trim, unique, keep non-empty
        $blocked = [];
        if (isset($input['blocked_urls'])) {
            $lines = preg_split('/\R+/', (string)$input['blocked_urls']);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') $blocked[$line] = true;
            }
        }
        $out['blocked_urls'] = implode("\n", array_keys($blocked));

        $out['admin_bypass'] = isset($input['admin_bypass']) ? 1 : 0;

        return $out;
    }

    public function render_page() {
        if ( ! current_user_can('manage_woocommerce') ) {
            wp_die(__('You do not have permission to access this page.', 'ds-wc-closure'));
        }

        $status = $this->get_status();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Webshop Closure', 'ds-wc-closure'); ?></h1>

            <div class="card" style="max-width:920px;">
                <h2 class="title"><?php esc_html_e('Schedule & Redirect', 'ds-wc-closure'); ?></h2>

                <p>
                    <strong><?php esc_html_e('Status:', 'ds-wc-closure'); ?></strong>
                    <?php echo wp_kses_post($status['label_html']); ?>
                </p>

                <form method="post" action="options.php">
                    <?php
                    settings_fields(self::OPTION_KEY);
                    do_settings_sections(self::OPTION_KEY);
                    submit_button(__('Save settings', 'ds-wc-closure'));
                    ?>
                </form>
            </div>

            <div class="card" style="max-width:920px;">
                <h2 class="title"><?php esc_html_e('How it works', 'ds-wc-closure'); ?></h2>
                <ol>
                    <li><?php esc_html_e('Pick a closure Date & Time (site timezone).', 'ds-wc-closure'); ?></li>
                    <li><?php esc_html_e('Choose a Page to redirect visitors to.', 'ds-wc-closure'); ?></li>
                    <li><?php esc_html_e('List the shop/category paths (one per line) that should be blocked after closure. Children are matched automatically.', 'ds-wc-closure'); ?></li>
                    <li><?php esc_html_e('Once the time passes, requests to those paths will be redirected.', 'ds-wc-closure'); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }

    public function enqueue_admin_css($hook) {
        if (isset($_GET['page']) && $_GET['page'] === 'ds-wc-closure') {
            // Using core .card styles; no extra CSS needed
        }
    }

    /** ================== Business Logic ================== */

    private function closure_datetime(): ?DateTimeInterface {
        $o   = self::get_settings();
        $d   = trim((string)$o['date']);
        $t   = trim((string)$o['time']);
        if ($d === '' || $t === '') return null;

        try {
            $tz  = wp_timezone();
            $dt  = new DateTimeImmutable("$d $t", $tz);
            return $dt;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function is_closed(): bool {
        $dt = $this->closure_datetime();
        if ( ! $dt ) return false;
        $now = current_datetime(); // WP timezone-aware DateTimeImmutable
        return $now >= $dt;
    }

    public function get_status(): array {
        $closed = $this->is_closed();
        $dt     = $this->closure_datetime();

        if ( $closed ) {
            return [
                'state'      => 'closed',
                'label_html' => '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#dc2626;color:#fff;font-weight:600;">Closed</span>',
            ];
        }

        if ( $dt ) {
            $human = human_time_diff( $dt->getTimestamp(), current_datetime()->getTimestamp() );
            return [
                'state'      => 'scheduled',
                'label_html' => '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#2563eb;color:#fff;font-weight:600;">Open • Closes in ' . esc_html($human) . '</span>',
            ];
        }

        return [
            'state'      => 'open',
            'label_html' => '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#16a34a;color:#fff;font-weight:600;">Open</span>',
        ];
    }

    /** Return the path component of home_url() with leading slash (e.g., '/demo2025') or '' if root */
    private function home_path_prefix(): string {
        $home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
        $home_path = '/' . ltrim( $home_path, '/' );  // ensure leading slash
        $home_path = rtrim( $home_path, '/' );        // no trailing slash
        return ($home_path === '/') ? '' : $home_path;
    }

    /** Base-path aware, prefix-style matcher. Matches exact path and any children. */
    private function is_request_blocked(array $patterns): bool {
        if (empty($patterns)) return false;

        // Raw request path, e.g. '/demo2025/categorie/alle-producten/page/2/'
        $req_uri     = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $request_path = (string) wp_parse_url($req_uri, PHP_URL_PATH);
        if ($request_path === '') $request_path = '/';

        // Also compute a version with base stripped (so '/categorie' can match under '/demo2025')
        $base = $this->home_path_prefix(); // '' or '/demo2025'
        $request_path_nobase = $request_path;
        if ($base && strpos($request_path_nobase, $base . '/') === 0) {
            $request_path_nobase = substr($request_path_nobase, strlen($base));
            if ($request_path_nobase === '') $request_path_nobase = '/';
        }

        foreach ($patterns as $raw) {
            $p = trim($raw);
            if ($p === '') continue;

            // Accept full URLs or paths; normalize to path
            if (preg_match('~^https?://~i', $p)) {
                $p = (string) wp_parse_url($p, PHP_URL_PATH);
            }
            if ($p === '') continue;

            // Ensure pattern starts with a slash
            if ($p[0] !== '/') $p = '/' . $p;

            // Build two comparable versions of the pattern:
            // 1) As entered
            // 2) With the site's base prefix prepended (so '/categorie' matches '/demo2025/categorie')
            $pat_with_base = $base ? rtrim($base, '/') . $p : $p;

            // Prefix-style regex: exact path OR any child (handles optional trailing slash)
            $quoted1 = preg_quote(rtrim($p, '/'), '~');
            $regex1  = '~^' . $quoted1 . '(?:/.*)?/?$~i';

            $quoted2 = preg_quote(rtrim($pat_with_base, '/'), '~');
            $regex2  = '~^' . $quoted2 . '(?:/.*)?/?$~i';

            if (preg_match($regex1, $request_path) || preg_match($regex2, $request_path)) {
                return true;
            }
            // Also check the "no-base" variant
            if (preg_match($regex1, $request_path_nobase)) {
                return true;
            }
        }
        return false;
    }

    /** Redirect if closed and path is blocked */
    public function maybe_redirect_blocked_urls() {
        // Only front-end
        if ( is_admin() || ( defined('REST_REQUEST') && REST_REQUEST ) ) return;
        if ( defined('DOING_AJAX') && DOING_AJAX ) return;

        $o = self::get_settings();

        // Admin bypass?
        if ( ! empty($o['admin_bypass']) && current_user_can('manage_woocommerce') ) {
            return;
        }

        // Not closed yet?
        if ( ! $this->is_closed() ) return;

        $target_id = (int) $o['redirect_page_id'];
        if ( $target_id <= 0 ) return;

        $target_url = get_permalink($target_id);
        if ( ! $target_url ) return;

        // Prevent redirect loop
        if ( is_page($target_id) ) return;

        // Collect patterns from settings
        $patterns = array_filter(array_map('trim', preg_split('/\R+/', (string)$o['blocked_urls'])));
        if ( empty($patterns) ) return;

        if ( $this->is_request_blocked($patterns) ) {
            wp_redirect($target_url, 302);
            exit;
        }
    }

    /** ================== Admin Bar Badge ================== */
    public function admin_bar_status_badge($wp_admin_bar) {
        if ( ! current_user_can('manage_woocommerce') ) return;

        $status = $this->get_status();
        $dot = [
            'open'      => '#16a34a',
            'scheduled' => '#2563eb',
            'closed'    => '#dc2626',
        ][$status['state']] ?? '#6b7280';

        $html = '<span style="display:inline-flex;align-items:center;gap:6px;">
            <span style="width:8px;height:8px;border-radius:999px;background:' . esc_attr($dot) . ';display:inline-block;"></span>
            <span>' . esc_html( wp_strip_all_tags( $status['state'] === 'scheduled' ? 'Open • ' . strip_tags($status['label_html']) : strip_tags($status['label_html']) ) ) . '</span>
        </span>';

        $wp_admin_bar->add_node([
            'id'    => 'ds-wc-closure-status',
            'title' => $html,
            'href'  => admin_url('admin.php?page=ds-close-webshop'),
            'meta'  => ['title' => 'Webshop Closure'],
        ]);
    }
}

new DS_Webshop_Closure();
