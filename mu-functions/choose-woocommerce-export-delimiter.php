<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inject "Scheidingsteken" field into WooCommerce Product Export form.
 * Uses the native WooCommerce key: "delimiter".
 */
add_action('admin_footer', function () {
    // Only on WooCommerce product exporter page
    if (
        empty($_GET['post_type']) || $_GET['post_type'] !== 'product' ||
        empty($_GET['page']) || $_GET['page'] !== 'product_exporter'
    ) {
        return;
    }
    ?>
    <script>
    (function () {
        var form = document.querySelector('form.woocommerce-exporter');
        if (!form) return;
        if (document.getElementById('delimiter')) return; // Prevent double insert
        var tbody = form.querySelector('table.form-table tbody');
        if (!tbody) return;

        var tr = document.createElement('tr');
        tr.innerHTML =
            '<th scope="row">' +
                '<label for="delimiter">Scheidingsteken</label>' +
            '</th>' +
            '<td>' +
                '<input type="text" id="delimiter" name="delimiter" placeholder=";" class="regular-text" maxlength="1" />' +
            '</td>';

        tbody.insertBefore(tr, tbody.firstChild); // Insert at top of the form

        // Default value if empty
        var input = tr.querySelector('#delimiter');
        if (input && !input.value) {input.value = ';';}
    })();
    </script>
    <?php
});

add_filter('woocommerce_product_export_delimiter', function ($delimiter) {
    // Read from request (GET or POST) --- DOESNT WORK
    // if (isset($_REQUEST['delimiter'])) {
    //     $raw = trim((string) wp_unslash($_REQUEST['delimiter']));
    //     if ($raw !== '') {
    //         return $raw[0]; // always use first character
    //     }
    // }

    return ';'; // Default fallback
});
