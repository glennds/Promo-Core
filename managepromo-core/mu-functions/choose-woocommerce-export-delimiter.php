<?php
if (!defined('ABSPATH')) {
    exit;
}

// Add delimiter field to WooCommerce product export form.
add_filter('woocommerce_product_exporter_formatting_callbacks', function ($callbacks) {return $callbacks;});

// Add custom field to the product export form.
add_action('woocommerce_product_exporter_additional_fields', function () {
    ?>
    <tr>
        <th scope="row"><label for="ds_export_delimiter">Scheidingsteken</label></th>
        <td>
            <input type="text" id="ds_export_delimiter" name="ds_export_delimiter" value=";" placeholder=";" class="regular-text" maxlength="1"/>
            <p class="description">Scheidingsteken voor het CSV-bestand (bijv. ; of ,)</p>
        </td>
    </tr>
    <?php
});

// Use the chosen delimiter for the export.
add_filter('woocommerce_csv_export_delimiter', function ($delimiter) {
    if (!empty($_REQUEST['ds_export_delimiter'])) {
        $custom = trim((string) $_REQUEST['ds_export_delimiter']);
        if ($custom !== '') {
            return $custom[0];
        }
    }

    return ';'; // Default fallback
});
