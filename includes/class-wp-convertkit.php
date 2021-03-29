<?php

/**
 * Class WP_ConvertKit
 */
class WP_ConvertKit {

	const POST_META_KEY = '_wp_convertkit_post_meta';

	const SETTINGS_NAME = '_wp_convertkit_settings';

	const SETTINGS_PAGE_SLUG = '_wp_convertkit_settings';

	/**
	 * ConvertKit_API instance.
	 *
	 * @var ConvertKit_API
	 */
	private static $api;

	/**
	 * Data caching length.
	 *
	 * @var int
	 */
	private static $cache_period = 0;

	/**
	 * Default meta values relating to forms and landing pages on posts.
	 *
	 * @var null
	 */
	private static $meta_defaults = null;

	/**
	 * Default plugin settings.
	 *
	 * @var array
	 */
	private static $settings_defaults = array(
		'api_key'      => '',
		'api_secret'   => '',
		'default_form' => 0,
		'debug'        => false,
		'no_scripts'   => false,
	);

	/**
	 * Forms markup.
	 *
	 * @var array
	 */
	private static $forms_markup = array();

	/**
	 * Landing pages markup.
	 *
	 * @var array
	 */
	private static $landing_pages_markup = array();

	/**
	 * Forms version.
	 *
	 * @var string
	 */
	private static $forms_version = '6';

	/**
	 * Initialize the class.
	 */
	public static function init() {
		self::add_actions();
		self::add_filters();
		self::register_shortcodes();

		self::$cache_period = MINUTE_IN_SECONDS * 10;

		self::_api_connect();
	}

	/**
	 * Add WP actions.
	 */
	private static function add_actions() {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
		if ( is_admin() ) {
			add_action( 'add_meta_boxes_page', array( __CLASS__, 'add_meta_boxes' ) );
			add_action( 'add_meta_boxes_post', array( __CLASS__, 'add_meta_boxes' ) );
			add_action( 'add_meta_boxes_product', array( __CLASS__, 'add_meta_boxes' ) );
		} else {
			add_action( 'template_redirect', array( __CLASS__, 'append_custom_post_type_forms' ) );
			add_action( 'template_redirect', array( __CLASS__, 'page_takeover' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		}

		add_action( 'widgets_init', array( __CLASS__, 'ck_register_widgets' ) );

		add_action( 'save_post', array( __CLASS__, 'save_post_meta' ), 10, 2 );

		add_action( 'init', array( __CLASS__, 'upgrade' ), 10 );
	}

	/**
	 * Load plugin textdomain.
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'convertkit', false, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Add WP Filters.
	 */
	private static function add_filters() {
		if ( ! is_admin() ) {
			add_filter( 'the_content', array( __CLASS__, 'append_form' ) );
		}

		add_filter( 'plugin_action_links_' . CONVERTKIT_PLUGIN_FILE, array( __CLASS__, 'add_settings_page_link' ) );
	}

	/**
	 * Register ConvertKit shortcodes.
	 */
	private static function register_shortcodes() {
		add_shortcode( 'convertkit', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Plugin action links callback.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array
	 */
	public static function add_settings_page_link( $links ) {
		$settings_link = sprintf( '<a href="%s">%s</a>', self::_get_settings_page_link(), __( 'Settings', 'convertkit' ) );

		return array_merge( array( 'settings' => $settings_link ), $links );
	}

	/**
	 * Add Meta Boxes callback
	 *
	 * @param WP_Post $post The current post.
	 */
	public static function add_meta_boxes( $post ) {
		add_meta_box( 'wp-convertkit-meta-box', __( 'ConvertKit', 'convertkit' ), array( __CLASS__, 'display_meta_box' ), $post->post_type, 'normal' );
	}

	/**
	 * Metabox callback
	 *
	 * @param WP_Post $post The current post.
	 */
	public static function display_meta_box( $post ) {

		$forms         = get_option( 'convertkit_forms' );
		$landing_pages = get_option( 'convertkit_landing_pages' );
		$tags          = get_option( 'convertkit_tags' );

		/**
		 * Alphabetize.
		 */
		usort(
			$forms,
			function( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		usort(
			$landing_pages,
			function( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		usort(
			$tags,
			function( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		$meta          = self::_get_meta( $post->ID );
		$settings_link = self::_get_settings_page_link();

		include( CONVERTKIT_PLUGIN_PATH . '/views/backend/meta-boxes/meta-box.php' );
	}

	/**
	 * Save post meta callback
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post The post.
	 */
	public static function save_post_meta( $post_id, $post ) {
		if ( wp_is_post_autosave( $post_id )
			|| wp_is_post_revision( $post_id )
			|| ! isset( $_POST['wp-convertkit-save-meta-nonce'] ) // WPCS input var okay.
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp-convertkit-save-meta-nonce'] ) ), 'wp-convertkit-save-meta' ) ) { // WPCS input var okay.
			return;
		}

		if ( isset( $_POST['wp-convertkit'] ) ) { // WPCS input var okay.

			$form = '';

			if ( isset( $_POST['wp-convertkit']['form'] ) ) { // WPCS input var okay.
				$form = sanitize_text_field( wp_unslash( $_POST['wp-convertkit']['form'] ) ); // WPCS input var okay.
			}

			$landing_page = '';

			if ( isset( $_POST['wp-convertkit']['landing_page'] ) ) { // WPCS input var okay.
				$landing_page = sanitize_text_field( wp_unslash( $_POST['wp-convertkit']['landing_page'] ) ); // WPCS input var okay.
			}

			$tag = '';

			if ( isset( $_POST['wp-convertkit']['tag'] ) ) { // WPCS input var okay.
				$tag = sanitize_text_field( wp_unslash( $_POST['wp-convertkit']['tag'] ) ); // WPCS input var okay.
			}

			$meta = array(
				'form'         => $form,
				'landing_page' => $landing_page,
				'tag'          => $tag,
			);

			update_post_meta( $post_id, self::POST_META_KEY, $meta );
		}
	}

	/**
	 * Page/Post display callback
	 * In the future, other post types that use the_content can be handled here with conditional cases
	 *
	 * @param string $content The post content.
	 * @return string
	 */
	public static function append_form( $content ) {

		if ( is_singular( array( 'post' ) ) || is_page() ) {
			$content .= self::get_form_for_post( get_the_ID(), 'default_form' );
		}

		return $content;
	}

	/**
	 * Handle custom post types that don't reliably use the_content
	 */
	public static function append_custom_post_type_forms() {
		add_action( 'woocommerce_after_single_product_summary', array( __CLASS__, 'append_woocommerce_product_form' ) );
	}

	/**
	 * Append form to WooCommerce products
	 */
	public static function append_woocommerce_product_form() {
		$form = self::get_form_for_post( get_the_ID(), 'product_form' );
		echo $form;
	}

	/**
	 * Get the ConvertKit form set for the given post.
	 *
	 * @param integer $post_id WordPress post ID.
	 * @param string  $settings_key The settings key used for the post type on the ConvertKit plugin settings page. Usually of the form {post_type_name}_form.
	 *
	 * @return string
	 */
	public static function get_form_for_post( $post_id, $settings_key ) {
		$form       = '';
		$attributes = self::_get_meta( $post_id );

		// Get post/page form setting.
		// If post form id is 0, then it's "None", and we need to stop here completely.
		if ( isset( $attributes['form'] ) && ( 0 <= $attributes['form'] ) ) {
			$form_id = $attributes['form'];
		} else {
			// Get category form.
			$form_id = self::get_category_form( $post_id );

			if ( 0 === $form_id || 'default' === $form_id ) {
				// Get default form for post type.
				if ( '-1' === $attributes['form'] ) {
					$form_id = self::_get_settings( $settings_key );
				}
			}
		}

		if ( 0 < $form_id && ! is_array( $form_id ) ) {

			$forms = get_option( 'convertkit_forms' );

			if ( isset( $forms[ $form_id ]['uid'] ) ) {
				// New form.
				$tag  = '<script async data-uid="' . $forms[ $form_id ]['uid'] . '" src="' . $forms[ $form_id ]['embed_js'] . '"></script>';
				$form = $tag;
			} else {
				// Old form.
				$url = add_query_arg(
					array(
						'api_key' => self::_get_settings( 'api_key' ),
						'v'       => self::$forms_version,
					),
					'https://forms.convertkit.com/' . $form_id . '.html'
				);

				$form_markup = self::$api->get_resource( $url );
				$form        = $form_markup;
			}
		}

		return $form;
	}

	/**
	 * Replace page content if a landing_page is set
	 */
	public static function page_takeover() {
		$queried_object = get_queried_object();
		if ( isset( $queried_object->post_type )
			&& 'page' === $queried_object->post_type ) {

			$landing_page_id = self::_get_meta( $queried_object->ID, 'landing_page' );

			if ( '0' === $landing_page_id || empty( $landing_page_id ) ) {
				// Set to None.
				return;
			}

			if ( strstr( $landing_page_id, 'http' ) ) {

				// Old landing page.
				$landing_page = self::$api->get_resource( $landing_page_id );

				if ( ! empty( $landing_page ) ) {
					$script       = WP_ConvertKit::get_ck_script();
					$script      .= '</head>';
					$landing_page = str_replace( '</head>', $script, $landing_page );

					echo $landing_page; // WPCS: XSS ok.
					exit;
				}
			} else {

				// New landing page.
				$landing_pages = get_option( 'convertkit_landing_pages' );
				$landing_page  = self::$api->get_resource( $landing_pages[ $landing_page_id ]['embed_url'] );

				if ( ! empty( $landing_page ) ) {
					$script       = WP_ConvertKit::get_ck_script( true );
					$script      .= '</head>';
					$landing_page = str_replace( '</head>', $script, $landing_page );

					echo $landing_page; // WPCS: XSS ok.
					exit;
				}
			}
		}
	}

	/**
	 * Generate HTML for including ConvertKit JS in a landing page.
	 *
	 * During the landing page takeover the normal WordPress enqueue routine is not run so we need to add these
	 * javascript if we want subscribers tagged correctly.
	 *
	 * @since 1.5.2
	 * @param bool $include_jquery Whether to explicitly load jQuery.
	 * @return string
	 */
	public static function get_ck_script( $include_jquery = false ) {
		$script = '';

		if ( $include_jquery ) {
			wp_enqueue_script( 'jquery' );
		}

		$script .= "<script type='text/javascript' src='" . CONVERTKIT_PLUGIN_URL . "resources/frontend/jquery.cookie.min.js?ver=1.4.0'></script>";
		$script .= "<script type='text/javascript' src='" . CONVERTKIT_PLUGIN_URL . 'resources/frontend/wp-convertkit.js?ver=' . CONVERTKIT_PLUGIN_VERSION . "'></script>";
		$script .= "<script type='text/javascript'>/* <![CDATA[ */var ck_data = {\"ajaxurl\":\"" . admin_url( 'admin-ajax.php' ) . '"};/* ]]> */</script>';

		return $script;
	}

	/**
	 * Enqueue scripts
	 */
	public static function enqueue_scripts() {

		// Only check for tags if we're on a singular post; otherwise, no tags.
		$has_tag = is_singular() ? self::post_has_tag( get_post() ) : false;

		// Only load scripts if no scripts setting is not checked.
		$no_scripts = self::_get_settings( 'no_scripts' );

		if ( ! $no_scripts ) {
			wp_register_script(
				'jquery-cookie',
				CONVERTKIT_PLUGIN_URL . 'resources/frontend/jquery.cookie.min.js',
				array( 'jquery' ),
				'1.4.0'
			);

			wp_register_script(
				'convertkit-js',
				CONVERTKIT_PLUGIN_URL . 'resources/frontend/wp-convertkit.js',
				array( 'jquery-cookie' ),
				CONVERTKIT_PLUGIN_VERSION
			);

			wp_localize_script(
				'convertkit-js',
				'ck_data',
				array(
					'ajaxurl'      => admin_url( 'admin-ajax.php' ),
					'post_has_tag' => $has_tag,
				)
			);

			wp_enqueue_script( 'convertkit-js' );
		}
	}

	/**
	 * Register widget.
	 */
	public static function ck_register_widgets() {
		register_widget( 'CK_Widget_Form' );
	}

	/**
	 * Shortcode callback
	 *
	 * @param array $attributes Shortcode attributes.
	 * @param null  $content The shortcode content.
	 * @return string
	 */
	public static function shortcode( $attributes, $content = null ) {

		if ( isset( $attributes['id'] ) ) {
			$form_id = $attributes['id'];
			$forms   = get_option( 'convertkit_forms' );

			if ( isset( $forms[ $form_id ]['uid'] ) ) {
				// New form.
				$form_markup = '<script async data-uid="' . $forms[ $form_id ]['uid'] . '" src="' . $forms[ $form_id ]['embed_js'] . '"></script>';
				return apply_filters( 'wp_convertkit_get_form_embed', $form_markup, $attributes );
			} else {
				// Old form.
				$url = add_query_arg(
					array(
						'api_key' => self::_get_settings( 'api_key' ),
						'v'       => self::$forms_version,
					),
					'https://forms.convertkit.com/' . $form_id . '.html'
				);
			}
		} elseif ( isset( $attributes['form'] ) ) {
			$form_id = $attributes['form'];
			$forms   = ( 'convertkit_forms' );

			if ( isset( $forms[ $form_id ]['uid'] ) ) {
				// New form.
				$form_markup = '<script async data-uid="' . $forms[ $form_id ]['uid'] . '" src="' . $forms[ $form_id ]['embed_js'] . '"></script>';
				return apply_filters( 'wp_convertkit_get_form_embed', $form_markup, $attributes );
			} else {
				$url = add_query_arg(
					array(
						'k' => self::_get_settings( 'api_key' ),
						'v' => '2',
					),
					'https://api.convertkit.com/forms/' . $form_id . '/embed'
				);
			}
		} else {
			$form_id = self::_get_settings( 'default_form' );
			$url     = add_query_arg(
				array(
					'api_key' => self::_get_settings( 'api_key' ),
					'v'       => self::$forms_version,
				),
				'https://forms.convertkit.com/' . $form_id . '.html'
			);
		}

		if ( 0 < $form_id ) {
			$form_markup = self::$api->get_resource( $url );
		} else {
			$form_markup = '';
		}

		return apply_filters( 'wp_convertkit_get_form_embed', $form_markup, $attributes );
	}

	/**
	 * Retrieve meta.
	 *
	 * @return array|null
	 */
	private static function _get_meta_defaults() {
		if ( is_null( self::$meta_defaults ) ) {
			self::$meta_defaults = array(
				'form'         => '-1',
				'landing_page' => '',
				'tag'          => '',
			);
		}

		return self::$meta_defaults;
	}

	/**
	 * Get selected post meta.
	 *
	 * @param int  $post_id Post ID.
	 * @param null $meta_key Key string to get.
	 *
	 * @return array|bool
	 */
	private static function _get_meta( $post_id, $meta_key = null ) {
		$post_id = empty( $post_id ) ? get_the_ID() : $post_id;

		$meta          = get_post_meta( $post_id, self::POST_META_KEY, true );
		$meta_defaults = self::_get_meta_defaults();

		if ( empty( $meta ) ) {
			$meta = $meta_defaults;

			$old_value = intval( get_post_meta( $post_id, '_convertkit_convertkit_form', true ) );

			if ( 0 !== $old_value ) {
				$meta['form'] = $old_value;
			}
		}

		$meta = shortcode_atts( $meta_defaults, $meta );

		return is_null( $meta_key ) ? $meta : ( isset( $meta[ $meta_key ] ) ? $meta[ $meta_key ] : false );
	}

	/**
	 * Get plugin settings.
	 *
	 * @param null $settings_key Specific settings instance key.
	 *
	 * @return mixed|null
	 */
	public static function _get_settings( $settings_key = null ) {
		$settings = get_option( self::SETTINGS_NAME, self::$settings_defaults );

		return is_null( $settings_key ) ? $settings : ( isset( $settings[ $settings_key ] ) ? $settings[ $settings_key ] : null );
	}

	/**
	 * Get instance of ConvertKitAPI.
	 */
	private static function _api_connect() {
		$api_key    = self::_get_settings( 'api_key' );
		$api_secret = self::_get_settings( 'api_secret' );
		$debug      = self::_get_settings( 'debug' );

		self::$api = new ConvertKit_API( $api_key, $api_secret, $debug );
	}

	/**
	 * Get instance of the CK API
	 *
	 * @return ConvertKit_API
	 */
	public static function get_api() {
		return self::$api;
	}

	/**
	 * Get ConvertKit API key
	 *
	 * @return mixed|null|void
	 */
	public static function get_api_key() {
		return self::_get_settings( 'api_key' );
	}

	/**
	 * Get ConvertKit API secret
	 *
	 * @return mixed|null|void
	 */
	public static function get_api_secret() {
		return self::_get_settings( 'api_secret' );
	}

	/**
	 * Get forms version.
	 *
	 * @return string
	 */
	public static function get_forms_version() {
		return self::$forms_version;
	}

	/**
	 * Get link to the plugin settings page
	 *
	 * @param array $query_args Args to add to URL.
	 * @return string
	 */
	private static function _get_settings_page_link( $query_args = array() ) {
		$query_args = array(
			'page' => self::SETTINGS_PAGE_SLUG,
		) + $query_args;

		return add_query_arg( $query_args, admin_url( 'options-general.php' ) );
	}

	/**
	 * Get default form for the post's categories.
	 * If there are multiple set the last one found will be returned.
	 *
	 * @param int $id WordPress post ID.
	 * @return int $form_id
	 */
	private static function get_category_form( $id ) {
		$categories = wp_get_post_categories( $id );

		$form_id = 0;
		foreach ( $categories as $category ) {

			$id = get_term_meta( $category, 'ck_default_form', true );
			if ( $id ) {
				$form_id = $id;
			}
		}

		return $form_id;
	}

	/**
	 * Output data to log file
	 *
	 * @param string $message String to add to log.
	 */
	static function log( $message ) {

		if ( 'on' === self::_get_settings( 'debug' ) ) {
			$dir    = CONVERTKIT_PLUGIN_PATH;
			$handle = fopen( trailingslashit( $dir ) . 'log.txt', 'a' );

			if ( $handle ) {
				$time = date_i18n( 'm-d-Y @ H:i:s -' );

				fwrite( $handle, $time . ' ' . $message . "\n" );
				fclose( $handle );
			}
		}
	}

	/**
	 * Run version specific upgrade.
	 */
	public static function upgrade() {

		$current_version               = get_option( 'convertkit_version' );
		$convertkit_user_history_table = get_option( 'convertkit_user_history_table' );

		if ( ! $current_version ) {
			// Run 1.4.1 upgrade.
			$settings = self::_get_settings();

			if ( isset( $settings['api_key'] ) ) {
				// Get all posts and pages to track what has been updated.
				$posts = get_option( '_wp_convertkit_upgrade_posts' );

				if ( ! $posts ) {

					$args = array(
						'post_type' => array( 'post', 'page' ),
						'fields'    => 'ids',
					);

					$result = new WP_Query( $args );
					if ( ! is_wp_error( $result ) ) {
						$posts = $result->posts;
						update_option( '_wp_convertkit_upgrade_posts', $posts );
					}
				}

				// Get form mappings.
				$mappings = self::$api->get_resources( 'subscription_forms' );

				// 1. Update global form. Set 'api_version' so this is only done once.
				if ( ! isset( $settings['api_version'] ) ) {
					$old_form_id              = $settings['default_form'];
					$settings['default_form'] = isset( $mappings[ $old_form_id ] ) ? $mappings[ $old_form_id ] : 0;
					$settings['api_version']  = 'v3';
					update_option( self::SETTINGS_NAME, $settings );
				}

				// 2. Scan posts/pages for _wp_convertkit_post_meta and update IDs
				// Scan content for shortcode and update
				// Remove page_id from posts array after page is updated.
				foreach ( $posts as $key => $post_id ) {
					$post_settings = get_post_meta( $post_id, '_wp_convertkit_post_meta', true );

					if ( isset( $post_settings['form'] ) && ( 0 < $post_settings['form'] ) ) {
						$post_settings['form'] = isset( $mappings[ $post_settings['form'] ] ) ? $mappings[ $post_settings['form'] ] : 0;
					}
					if ( isset( $post_settings['landing_page'] ) && ( 0 < $post_settings['landing_page'] ) ) {
						$post_settings['landing_page'] = isset( $mappings[ $post_settings['landing_page'] ] ) ? $mappings[ $post_settings['landing_page'] ] : 0;
					}
					update_post_meta( $post_id, '_wp_convertkit_post_meta', $post_settings );
					unset( $posts[ $key ] );
					update_option( '_wp_convertkit_upgrade_posts', $posts );
				}

				// Done scanning posts, upgrade complete.
				if ( empty( $posts ) ) {
					update_option( 'convertkit_version', CONVERTKIT_PLUGIN_VERSION );
					delete_option( '_wp_convertkit_upgrade_posts' );
				}
			} else {
				update_option( 'convertkit_version', CONVERTKIT_PLUGIN_VERSION );
			} // End if().
		} elseif ( version_compare( $current_version, '1.5.0', '<' ) || ! $convertkit_user_history_table ) {

			// Create User History table.
			ConvertKit_Custom_Content::create_table();
			update_option( 'convertkit_version', CONVERTKIT_PLUGIN_VERSION );

		} elseif ( version_compare( $current_version, '1.6.1', '<' ) ) {
			// Refresh the forms meta to get new forms builder settings.
			$api_key    = self::_get_settings( 'api_key' );
			$api_secret = self::_get_settings( 'api_secret' );

			if ( ! empty( $api_key ) && ! empty( $api_secret ) ) {
				self::$api->update_resources( $api_key, $api_secret );
			}

			update_option( 'convertkit_version', CONVERTKIT_PLUGIN_VERSION );
		}// End if().
	}


	/**
	 * If post has a tag assigned to it.
	 *
	 * @param WP_Post $post The post's WP_Post object.
	 *
	 * @return boolean
	 */
	public static function post_has_tag( $post ) {
		$meta = get_post_meta( $post->ID, '_wp_convertkit_post_meta', true );

		if ( isset( $meta['tag'] ) ) {
			return ( 0 === $meta['tag'] ) ? false : true;
		}
		return false;
	}
}
