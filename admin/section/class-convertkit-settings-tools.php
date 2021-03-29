<?php

/**
 * ConvertKit Tools Settings class
 *
 * @package ConvertKit
 * @author ConvertKit
 */

/**
 * Class ConvertKit_Settings_Tools
 */
class ConvertKit_Settings_Tools extends ConvertKit_Settings_Base {

	/**
	 * We are hiding the submit button.
	 *
	 * @var bool
	 */
	protected $show_submit = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings_key = '_wp_convertkit_integration_tools_settings';
		$this->name         = 'tools';
		$this->title        = __( 'Tools', 'convertkit' );
		$this->tab_text     = __( 'Tools', 'convertkit' );

		parent::__construct();
	}

	/**
	 * Register and add settings.
	 */
	public function register_fields() {
		add_settings_field(
			'log',
			'Log',
			array( $this, 'view_log' ),
			$this->settings_key,
			$this->name
		);
	}

	/**
	 * Prints help info for this section.
	 */
	public function print_section_info() {
		?>
		<p><?php esc_html_e( 'Tools to help you manage ConvertKit on your site.', 'convertkit' ); ?></p>
		<?php
	}

	/**
	 * Renders the section.
	 */
	public function render() {
		settings_fields( $this->settings_key );
		$this->view_log();
		$this->view_server_info();
	}

	/**
	 * View the Log.
	 */
	public function view_log() {
		// Only try to get file contents if the file exists; otherwise default to empty string.
		$log_file = trailingslashit( CONVERTKIT_PLUGIN_PATH ) . 'log.txt';
		$log      = file_exists( $log_file ) ? file_get_contents( $log_file ) : '';

		?>
		<div class="metabox-holder">
			<div class="postbox ck-debug-log">
				<h3><span><?php esc_html_e( 'Debug Log', 'convertkit' ); ?></span></h3>
				<div class="inside">
					<p><?php _e( 'Use this tool to help debug ConvertKit plugin functionality.', 'convertkit' ); ?></p>
					<textarea readonly="readonly" class="large-text monospace" rows="15" name="convertkit-debug-log-contents"><?php echo esc_textarea( $log ); ?></textarea>
					<p class="submit">
						<?php
						submit_button(
							__( 'Copy Log', 'convertkit' ),
							'primary',
							'convertkit-copy-debug-log',
							false,
							array(
								'onclick' => "this.form['convertkit-debug-log-contents'].focus();this.form['convertkit-debug-log-contents'].select();document.execCommand('copy');return false;",
							)
						);
						submit_button(
							__( 'Clear Log', 'convertkit' ),
							'secondary ck-inline-button',
							'convertkit-clear-debug-log',
							false
						);
						?>
					</p>
					<p><?php _e( 'Log file', 'convertkit' ); ?>: <code><?php echo $log_file; ?></code></p>
				</div><!-- .inside -->
			</div><!-- .postbox -->
		</div><!-- .metabox-holder -->
		<?php
	}

	/**
	 * View server info.
	 */
	public function view_server_info() {
		?>
		<div class="metabox-holder">
			<div class="postbox ck-debug-log">
				<h3><span><?php esc_html_e( 'System Info', 'convertkit' ); ?></span></h3>
				<div class="inside">
					<p><?php _e( 'Use this tool to send system info to support when necessary.', 'convertkit' ); ?></p>
						<textarea readonly="readonly" onclick="this.focus(); this.select()" id="system-info-textarea" class="large-text monospace" rows="15" name="convertkit-sysinfo"><?php echo $this->get_system_info(); ?></textarea>
						<p class="submit">
							<input type="hidden" name="convertkit-action" value="download_sysinfo" />
							<?php
							submit_button(
								__( 'Copy System Info', 'convertkit' ),
								'primary',
								'convertkit-copy-system-info',
								false,
								array(
									'onclick' => "this.form['convertkit-sysinfo'].focus();this.form['convertkit-sysinfo'].select();document.execCommand('copy');return false;",
								)
							);
							?>
						</p>
				</div><!-- .inside -->
			</div><!-- .postbox -->
		</div><!-- .metabox-holder -->
		<?php
	}

	/**
	 * Sanitizes the settings.
	 * We are also using this to clear the Log file.
	 *
	 * @param  array $settings The settings fields submitted.
	 * @return array           Sanitized settings.
	 */
	public function sanitize_settings( $settings ) {

		$log_file = trailingslashit( CONVERTKIT_PLUGIN_PATH ) . 'log.txt';

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_REQUEST['convertkit-clear-debug-log'] ) ) {

			$handle = fopen( $log_file, 'w' );
			fclose( $handle );

			wp_safe_redirect( admin_url( 'options-general.php?page=_wp_convertkit_settings&tab=tools' ) );
			exit;

		}
	}

	/**
	 * Get system info
	 *
	 * Adapted from Easy Digital Downloads
	 *
	 * @since       2.0
	 * @global      object $wpdb Used to query the database using the WordPress Database API
	 * @return      string $return A string containing the info to output
	 */
	function get_system_info() {
		global $wpdb;

		if ( ! class_exists( 'Browser' ) ) {
			require_once CONVERTKIT_PLUGIN_PATH . '/lib/browser.php';
		}

		$browser = new Browser();

		// Get theme info.
		$theme_data   = wp_get_theme();
		$theme        = $theme_data->Name . ' ' . $theme_data->Version; // phpcs:ignore -- Okay use of non-snake_case variable/property names.
		$parent_theme = $theme_data->Template; // phpcs:ignore -- Okay use of non-snake_case variable/property names.

		if ( ! empty( $parent_theme ) ) {
			$parent_theme_data = wp_get_theme( $parent_theme );
			$parent_theme      = $parent_theme_data->Name . ' ' . $parent_theme_data->Version; // phpcs:ignore -- Okay use of non-snake_case variable/property names.
		}

		// Try to identify the hosting provider.
		$host = $this->get_host();

		$return = '### Begin System Info ###' . "\n\n";

		// Start with the basics.
		$return .= '-- Site Info' . "\n\n";
		$return .= 'Site URL:                 ' . site_url() . "\n";
		$return .= 'Home URL:                 ' . home_url() . "\n";
		$return .= 'Multisite:                ' . ( is_multisite() ? 'Yes' : 'No' ) . "\n";

		$return = apply_filters( 'convertkit_sysinfo_after_site_info', $return );

		// Can we determine the site's host?
		if ( $host ) {
			$return .= "\n" . '-- Hosting Provider' . "\n\n";
			$return .= 'Host:                     ' . $host . "\n";

			$return = apply_filters( 'convertkit_sysinfo_after_host_info', $return );
		}

		// The local users' browser information, handled by the Browser class.
		$return .= "\n" . '-- User Browser' . "\n\n";
		$return .= $browser;

		$return = apply_filters( 'convertkit_sysinfo_after_user_browser', $return );
		$locale = get_locale();

		// WordPress configuration.
		$return .= "\n" . '-- WordPress Configuration' . "\n\n";
		$return .= 'Version:                  ' . get_bloginfo( 'version' ) . "\n";
		$return .= 'Language:                 ' . ( ! empty( $locale ) ? $locale : 'en_US' ) . "\n";
		$return .= 'Permalink Structure:      ' . ( get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : 'Default' ) . "\n";
		$return .= 'Active Theme:             ' . $theme . "\n";
		if ( $parent_theme !== $theme ) {
			$return .= 'Parent Theme:             ' . $parent_theme . "\n";
		}
		$return .= 'Show On Front:            ' . get_option( 'show_on_front' ) . "\n";

		// Only show page specs if frontpage is set to 'page'.
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$front_page_id = get_option( 'page_on_front' );
			$blog_page_id  = get_option( 'page_for_posts' );

			$return .= 'Page On Front:            ' . ( 0 !== $front_page_id ? get_the_title( $front_page_id ) . ' (#' . $front_page_id . ')' : 'Unset' ) . "\n";
			$return .= 'Page For Posts:           ' . ( 0 !== $blog_page_id ? get_the_title( $blog_page_id ) . ' (#' . $blog_page_id . ')' : 'Unset' ) . "\n";
		}

		$return .= 'ABSPATH:                  ' . ABSPATH . "\n";

		// Make sure wp_remote_post() is working.
		$request['cmd'] = '_notify-validate';

		$params = array(
			'sslverify'  => false,
			'timeout'    => 60,
			'user-agent' => convertkit_wp_get_user_agent(),
			'body'       => $request,
		);

		$response = wp_remote_post( 'https://www.paypal.com/cgi-bin/webscr', $params );

		if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) {
			$WP_REMOTE_POST = 'wp_remote_post() works'; // phpcs:ignore -- Okay lack of snake_case in variable name.
		} else {
			$WP_REMOTE_POST = 'wp_remote_post() does not work'; // phpcs:ignore -- Okay lack of snake_case in variable name.
		}

		$return .= 'Remote Post:              ' . $WP_REMOTE_POST . "\n"; // phpcs:ignore -- Okay lack of snake_case in variable name.
		$return .= 'Table Prefix:             ' . 'Length: ' . strlen( $wpdb->prefix ) . '   Status: ' . ( strlen( $wpdb->prefix ) > 16 ? 'ERROR: Too long' : 'Acceptable' ) . "\n";
		$return .= 'WP_DEBUG:                 ' . ( defined( 'WP_DEBUG' ) ? WP_DEBUG ? 'Enabled' : 'Disabled' : 'Not set' ) . "\n";
		$return .= 'Memory Limit:             ' . WP_MEMORY_LIMIT . "\n";
		$return .= 'Registered Post Stati:    ' . implode( ', ', get_post_stati() ) . "\n";

		$return = apply_filters( 'convertkit_sysinfo_after_wordpress_config', $return );

		// ConvertKit configuration.
		$return .= "\n" . '-- ConvertKit Configuration' . "\n\n";
		$return .= 'Version:                  ' . CONVERTKIT_PLUGIN_VERSION . "\n";

		// @TODO add info on form settings, incl. integrations, etc.

		$return = apply_filters( 'convertkit_sysinfo_after_convertkit_config', $return );

		// Get plugins that have an update.
		$updates = get_plugin_updates();

		// Must-use plugins.
		// NOTE: MU plugins can't show updates!
		$muplugins = get_mu_plugins();

		if ( count( $muplugins ) > 0 ) {
			$return .= "\n" . '-- Must-Use Plugins' . "\n\n";

			foreach ( $muplugins as $plugin => $plugin_data ) {
				$return .= $plugin_data['Name'] . ': ' . $plugin_data['Version'] . "\n";
			}

			$return = apply_filters( 'convertkit_sysinfo_after_wordpress_mu_plugins', $return );
		}

		// WordPress active plugins.
		$return .= "\n" . '-- WordPress Active Plugins' . "\n\n";

		$plugins        = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );

		foreach ( $plugins as $plugin_path => $plugin ) {
			if ( ! in_array( $plugin_path, $active_plugins, true ) ) {
				continue;
			}

			$update  = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[ $plugin_path ]->update->new_version . ')' : '';
			$return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
		}

		$return = apply_filters( 'convertkit_sysinfo_after_wordpress_plugins', $return );

		// WordPress inactive plugins.
		$return .= "\n" . '-- WordPress Inactive Plugins' . "\n\n";

		foreach ( $plugins as $plugin_path => $plugin ) {
			if ( in_array( $plugin_path, $active_plugins, true ) ) {
				continue;
			}

			$update  = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[ $plugin_path ]->update->new_version . ')' : '';
			$return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
		}

		$return = apply_filters( 'convertkit_sysinfo_after_wordpress_plugins_inactive', $return );

		if ( is_multisite() ) {
			// WordPress Multisite active plugins.
			$return .= "\n" . '-- Network Active Plugins' . "\n\n";

			$plugins        = wp_get_active_network_plugins();
			$active_plugins = get_site_option( 'active_sitewide_plugins', array() );

			foreach ( $plugins as $plugin_path ) {
				$plugin_base = plugin_basename( $plugin_path );

				if ( ! array_key_exists( $plugin_base, $active_plugins ) ) {
					continue;
				}

				$update  = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[ $plugin_path ]->update->new_version . ')' : '';
				$plugin  = get_plugin_data( $plugin_path );
				$return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
			}

			$return = apply_filters( 'convertkit_sysinfo_after_wordpress_ms_plugins', $return );
		}

		// Server configuration (really just versioning).
		$return .= "\n" . '-- Webserver Configuration' . "\n\n";
		$return .= 'PHP Version:              ' . PHP_VERSION . "\n";
		$return .= 'MySQL Version:            ' . $wpdb->db_version() . "\n";
		$return .= 'Webserver Info:           ' . $_SERVER['SERVER_SOFTWARE'] . "\n";

		$return = apply_filters( 'convertkit_sysinfo_after_webserver_config', $return );

		// PHP configs... now we're getting to the important stuff.
		$return .= "\n" . '-- PHP Configuration' . "\n\n";
		$return .= 'Memory Limit:             ' . ini_get( 'memory_limit' ) . "\n";
		$return .= 'Upload Max Size:          ' . ini_get( 'upload_max_filesize' ) . "\n";
		$return .= 'Post Max Size:            ' . ini_get( 'post_max_size' ) . "\n";
		$return .= 'Upload Max Filesize:      ' . ini_get( 'upload_max_filesize' ) . "\n";
		$return .= 'Time Limit:               ' . ini_get( 'max_execution_time' ) . "\n";
		$return .= 'Max Input Vars:           ' . ini_get( 'max_input_vars' ) . "\n";
		$return .= 'Display Errors:           ' . ( ini_get( 'display_errors' ) ? 'On (' . ini_get( 'display_errors' ) . ')' : 'N/A' ) . "\n";
		$return .= 'PHP Arg Separator:        ' . ini_get( 'arg_separator.output' ) . "\n";

		$return = apply_filters( 'convertkit_sysinfo_after_php_config', $return );

		$curl_version_info = function_exists( 'curl_version' ) ? curl_version() : array(
			'version'     => 'Unknown; cURL not avaible',
			'ssl_version' => 'Unknown; cURL not avaible',
		);

		// PHP extensions and such.
		$return .= "\n" . '-- PHP Extensions' . "\n\n";
		$return .= 'cURL:                     ' . ( function_exists( 'curl_init' ) ? 'Supported' : 'Not Supported' ) . "\n";
		$return .= 'cURL version:             ' . $curl_version_info['version'] . "\n";
		$return .= 'ssl version:              ' . $curl_version_info['ssl_version'] . "\n";
		$return .= 'fsockopen:                ' . ( function_exists( 'fsockopen' ) ? 'Supported' : 'Not Supported' ) . "\n";
		$return .= 'SOAP Client:              ' . ( class_exists( 'SoapClient' ) ? 'Installed' : 'Not Installed' ) . "\n";
		$return .= 'Suhosin:                  ' . ( extension_loaded( 'suhosin' ) ? 'Installed' : 'Not Installed' ) . "\n";

		$return = apply_filters( 'convertkit_sysinfo_after_php_ext', $return );

		// Session stuff.
		$return .= "\n" . '-- Session Configuration' . "\n\n";

		// The rest of this is only relevant is session is enabled.
		if ( isset( $_SESSION ) ) {
			$return .= 'Session Name:             ' . esc_html( ini_get( 'session.name' ) ) . "\n";
			$return .= 'Cookie Path:              ' . esc_html( ini_get( 'session.cookie_path' ) ) . "\n";
			$return .= 'Save Path:                ' . esc_html( ini_get( 'session.save_path' ) ) . "\n";
			$return .= 'Use Cookies:              ' . ( ini_get( 'session.use_cookies' ) ? 'On' : 'Off' ) . "\n";
			$return .= 'Use Only Cookies:         ' . ( ini_get( 'session.use_only_cookies' ) ? 'On' : 'Off' ) . "\n";
		}

		$return = apply_filters( 'convertkit_sysinfo_after_session_config', $return );

		$return .= "\n" . '### End System Info ###';

		return $return;
	}

	/**
	 * Get user host.
	 *
	 * Adapted from Easy Digital Downloads
	 *
	 * Returns the webhost this site is using if possible
	 *
	 * @since 1.6.4
	 * @return mixed string $host if detected, false otherwise
	 */
	function get_host() {
		$host = false;

		if ( defined( 'WPE_APIKEY' ) ) {
			$host = 'WP Engine';
		} elseif ( defined( 'PAGELYBIN' ) ) {
			$host = 'Pagely';
		} elseif ( DB_HOST === 'localhost:/tmp/mysql5.sock' ) {
			$host = 'ICDSoft';
		} elseif ( DB_HOST === 'mysqlv5' ) {
			$host = 'NetworkSolutions';
		} elseif ( strpos( DB_HOST, 'ipagemysql.com' ) !== false ) {
			$host = 'iPage';
		} elseif ( strpos( DB_HOST, 'ipowermysql.com' ) !== false ) {
			$host = 'IPower';
		} elseif ( strpos( DB_HOST, '.gridserver.com' ) !== false ) {
			$host = 'MediaTemple Grid';
		} elseif ( strpos( DB_HOST, '.pair.com' ) !== false ) {
			$host = 'pair Networks';
		} elseif ( strpos( DB_HOST, '.stabletransit.com' ) !== false ) {
			$host = 'Rackspace Cloud';
		} elseif ( strpos( DB_HOST, '.sysfix.eu' ) !== false ) {
			$host = 'SysFix.eu Power Hosting';
		} elseif ( strpos( $_SERVER['SERVER_NAME'], 'Flywheel' ) !== false ) {
			$host = 'Flywheel';
		} else {
			// Adding a general fallback for data gathering.
			$host = 'DBH: ' . DB_HOST . ', SRV: ' . $_SERVER['SERVER_NAME'];
		}

		return $host;
	}
}
