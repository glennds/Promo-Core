<?php
defined('ABSPATH') || exit;

/**
 * Remove bulky Breakdance Builder access settings from user management screens
 */



add_action('admin_footer-user-new.php', function () {
    ?>
    <script>
    (function () {
        function removeBreakdanceBuilderAccessSection() {

            // Find the Breakdance permission select by its stable name attribute
            var select = document.querySelector('select[name="breakdance_permission"]');
            if (!select) return false;

            // Remove the closest settings table that contains this select
            var table = select.closest('table.form-table');
            if (!table) return false;

            // Remove the nearest preceding H3 that says "Breakdance"
            var h3 = table.previousElementSibling;
            while (h3) {
                if (h3.tagName === 'H3' && (h3.textContent || '').trim() === 'Breakdance') {
                    h3.remove();
                    break;
                }
                h3 = h3.previousElementSibling;
            }

            table.remove();
            return true;
        }

        // Run once now
        removeBreakdanceBuilderAccessSection();

        // Keep it removed even if the page re-renders parts of the form
        var observer = new MutationObserver(function () {
            removeBreakdanceBuilderAccessSection();
        });

        observer.observe(document.documentElement, { childList: true, subtree: true });
    })();
    </script>
    <?php
});
