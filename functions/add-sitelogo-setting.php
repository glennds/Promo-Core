<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}
if (!function_exists('keuzeconcept_is_enabled') || !keuzeconcept_is_enabled('site_logo')) {return;}

//////////////////////////////////
// Function contents start HERE //
//////////////////////////////////


// Register setting
add_action('admin_init', function() {
    register_setting('general', 'site_logo', [
        'type' => 'integer',
        'description' => 'Afbeelding ID van het site logo',
        'sanitize_callback' => 'absint',
        'show_in_rest' => true, // Voor Breakdance dynamic data
    ]);

    add_settings_field(
        'site_logo',
        'Site logo',
        'render_site_logo_setting_field',
        'general'
    );
});

// Render upload veld
function render_site_logo_setting_field() {
    $image_id = get_option('site_logo');
    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';

    ?>
    <div style="margin-bottom: 10px;">
        <img id="site-logo-preview" src="<?php echo esc_url($image_url); ?>" style="max-height:60px; <?php echo $image_url ? '' : 'display:none;'; ?>">
    </div>
    <input type="hidden" id="site-logo-input" name="site_logo" value="<?php echo esc_attr($image_id); ?>">
    <button type="button" class="button" id="upload-site-logo-button">Upload / Kies afbeelding</button>
    <button type="button" class="button" id="remove-site-logo-button">Verwijder</button>

    <script>
    jQuery(document).ready(function($) {
        let frame;

        $('#upload-site-logo-button').on('click', function(e) {
            e.preventDefault();

            // Als frame al bestaat, open opnieuw
            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: 'Selecteer of upload site logo',
                button: { text: 'Gebruik dit logo' },
                multiple: false
            });

            // Zorg dat de select listener niet dubbel wordt toegevoegd
            frame.on('open', function() {
                frame.off('select'); // Verwijder eventuele oude select listeners
                frame.on('select', function() {
                    const attachment = frame.state().get('selection').first().toJSON();
                    $('#site-logo-input').val(attachment.id);
                    $('#site-logo-preview').attr('src', attachment.url).show();
                });
            });

            frame.open();
        });

        $('#remove-site-logo-button').on('click', function() {
            $('#site-logo-input').val('');
            $('#site-logo-preview').hide();
        });
    });
    </script>
    <?php
}