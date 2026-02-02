<?php
if (!defined('ABSPATH')) exit;

// Remove Breakdance "Builder Access" block only on 'Add New User'-screen
add_action('admin_footer-user-new.php', function () {
    ?>
    <script>
    (function () {
        
        // Find the closest unique selector
        var select = document.getElementById('breakdance-builder-access');
        if (!select) return;

        // Trace the parent table closest to unique selector
        var table = select.closest('table.form-table');
        if (!table) return;

        // Remove the preceding <h3> thatclosest to unique select
        var h3 = table.previousElementSibling;
        while (h3 && h3.tagName !== 'H3') {
            h3 = h3.previousElementSibling;
        }

        if (h3) h3.remove();
        table.remove();
    })();
    </script>
    <?php
});
