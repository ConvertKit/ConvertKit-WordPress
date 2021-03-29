<?php
/**
 * Plugin Name: ConvertKit
 * Plugin URI: https://convertkit.com/
 * Description: Quickly and easily integrate ConvertKit forms into your site.
 * Version: 1.9.4
 * Author: ConvertKit
 * Author URI: https://convertkit.com/
 * Text Domain: convertkit
 *
 * @package convertkit-wordpress
 */

if ( class_exists( 'WP_ConvertKit' ) ) {
	return;
}

define( 'CONVERTKIT_PLUGIN_FILE', plugin_basename( __FILE__ ) );
define( 'CONVERTKIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CONVERTKIT_PLUGIN_PATH', __DIR__ );
define( 'CONVERTKIT_PLUGIN_VERSION', '1.9.4' );

require_once CONVERTKIT_PLUGIN_PATH . '/vendor/autoload.php';

require_once CONVERTKIT_PLUGIN_PATH . '/includes/functions.php';
require_once CONVERTKIT_PLUGIN_PATH . '/includes/class-wp-convertkit.php';
require_once CONVERTKIT_PLUGIN_PATH . '/includes/class-convertkit-api.php';
require_once CONVERTKIT_PLUGIN_PATH . '/includes/class-ck-widget-form.php';
require_once CONVERTKIT_PLUGIN_PATH . '/includes/class-convertkit-custom-content.php';
require_once CONVERTKIT_PLUGIN_PATH . '/includes/integration/class-convertkit-wishlist-integration.php';
require_once CONVERTKIT_PLUGIN_PATH . '/includes/integration/class-convertkit-contactform7-integration.php';

if ( is_admin() ) {
	require_once CONVERTKIT_PLUGIN_PATH . '/admin/class-convertkit-settings.php';
	require_once CONVERTKIT_PLUGIN_PATH . '/admin/class-multi-value-field-table.php';
	require_once CONVERTKIT_PLUGIN_PATH . '/admin/class-convertkit-tinymce.php';
	require_once CONVERTKIT_PLUGIN_PATH . '/admin/section/class-convertkit-settings-base.php';
	require_once CONVERTKIT_PLUGIN_PATH . '/admin/section/class-convertkit-settings-general.php';
	require_once CONVERTKIT_PLUGIN_PATH . '/admin/section/class-convertkit-settings-tools.php';
	require_once CONVERTKIT_PLUGIN_PATH . '/admin/section/class-convertkit-settings-wishlist.php';
	require_once CONVERTKIT_PLUGIN_PATH . '/admin/section/class-convertkit-settings-contactform7.php';

	$convertkit_settings = new ConvertKit_Settings();
}

WP_ConvertKit::init();
