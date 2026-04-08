<?php
defined('ABSPATH') || exit;
/**
 * Force new multisite subsites to use first-level subdomains on promotie.nl
 * instead of subdomains on admin.promotie.nl.
 *
 * Example:
 *   requested/generated: subsite.admin.promotie.nl
 *   becomes:             subsite.promotie.nl
 */



if (! is_multisite()) {return;}


// Rewrite subdomain on site creation to remove the excess '.admin' subdomain, before the new site is fully initialized
add_action('wp_initialize_site', function ($new_site) {
	global $wpdb;

	if (empty($new_site->blog_id) || empty($new_site->domain)) {
		return;
	}

	$blog_id    = (int) $new_site->blog_id;
	$old_domain = (string) $new_site->domain;
	$path       = ! empty($new_site->path) ? (string) $new_site->path : '/';

	$suffix = '.admin.promotie.nl';

	if (substr($old_domain, -strlen($suffix)) !== $suffix) {
		return;
	}

	$subsite = substr($old_domain, 0, -strlen($suffix));

	if ($subsite === '' || strpos($subsite, '.') !== false) {
		return;
	}

	$new_domain = $subsite . '.promotie.nl';
	$new_url    = 'https://' . $new_domain;

	$wpdb->update(
		$wpdb->blogs,
		['domain' => $new_domain],
		['blog_id' => $blog_id],
		['%s'],
		['%d']
	);

	switch_to_blog($blog_id);

	update_option('home', $new_url);
	update_option('siteurl', $new_url);

	restore_current_blog();

	clean_blog_cache($blog_id);
	refresh_blog_details($blog_id);

	error_log('Promo multisite rewrite after init: ' . $old_domain . ' => ' . $new_domain . ' (blog_id ' . $blog_id . ')');
}, 100, 1);


// Rewrite any remaining occurrences of the old subdomain in the admin interface, for example in the site list and site switcher
add_action('admin_print_footer_scripts', function () {
	if (! is_network_admin()) {
		return;
	}

	$network_domain = 'admin.promotie.nl';
	$public_domain  = 'promotie.nl';
	$admin_suffix   = '.' . $network_domain;
	$public_suffix  = '.' . $public_domain;
	?>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const fromSuffix = <?php echo wp_json_encode($admin_suffix); ?>;
			const toSuffix   = <?php echo wp_json_encode($public_suffix); ?>;

			const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
			const textNodes = [];

			while (walker.nextNode()) {
				textNodes.push(walker.currentNode);
			}

			textNodes.forEach(function (node) {
				if (node.textContent && node.textContent.indexOf(fromSuffix) !== -1) {
					node.textContent = node.textContent.split(fromSuffix).join(toSuffix);
				}
			});
		});
	</script>
	<?php
});
