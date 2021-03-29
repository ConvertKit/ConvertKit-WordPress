<?php
/**
 * ConvertKit Settings class
 *
 * @package ConvertKit
 * @author ConvertKit
 */

/**
 * Class ConvertKit_Settings
 */
class ConvertKit_Settings {

	/**
	 * ConvertKit API instance.
	 *
	 * @var ConvertKit_API
	 */
	public $api;

	/**
	 * Settings sections.
	 *
	 * @var array
	 */
	public $sections = array();

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	public $settings_key = WP_ConvertKit::SETTINGS_PAGE_SLUG;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$general_options = get_option( $this->settings_key );
		$api_key         = $general_options && array_key_exists( 'api_key', $general_options ) ? $general_options['api_key'] : null;
		$api_secret      = $general_options && array_key_exists( 'api_secret', $general_options ) ? $general_options['api_secret'] : null;
		$debug           = $general_options && array_key_exists( 'debug', $general_options ) ? $general_options['debug'] : null;
		$this->api       = new ConvertKit_API( $api_key, $api_secret, $debug );

		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_sections' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// AJAX callback for TinyMCE button to get list of tags.
		add_action( 'wp_ajax_convertkit_get_tags', array( $this, 'get_tags' ) );

		// Function to output.
		add_action( 'admin_footer', array( $this, 'add_tags_footer' ) );

		// Category default forms.
		add_action( 'edit_category_form_fields', array( $this, 'category_form_fields' ), 20 );
		add_action( 'edited_category', array( $this, 'save_category_fields' ), 20 );

		if ( WP_DEBUG ) {
			add_action( 'show_user_profile', array( $this, 'add_customer_meta_fields' ) );
			add_action( 'edit_user_profile', array( $this, 'add_customer_meta_fields' ) );
		}
	}

	/**
	 * Enqueue Scripts in Admin.
	 *
	 * @param string $hook Admin hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'settings_page__wp_convertkit_settings' === $hook ) {
			wp_enqueue_script( 'ck-admin-js', plugins_url( '../resources/backend/wp-convertkit.js', __FILE__ ), array( 'jquery' ), CONVERTKIT_PLUGIN_VERSION, true );

			wp_localize_script(
				'ck-admin-js',
				'ck_admin',
				array(
					'option_none' => __( 'None', 'convertkit' ),
				)
			);
		}
	}

	/**
	 * Add the options page
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'ConvertKit', 'convertkit' ),
			__( 'ConvertKit', 'convertkit' ),
			'manage_options',
			$this->settings_key,
			array( $this, 'display_settings_page' )
		);

		add_action( 'admin_print_styles', array( $this, 'admin_styles' ) );
	}

	/**
	 * Options page callback
	 */
	public function display_settings_page() {
		if ( isset( $_GET['tab'] ) ) { // WPCS: CSRF ok.
			$active_section = sanitize_text_field( wp_unslash( $_GET['tab'] ) ); // WPCS: CSRF ok.
		} else {
			$active_section = $this->sections[0]->name;
		}
		?>
		<div class="wrap convertkit-settings-wrap">
			<?php
			if ( count( $this->sections ) > 1 ) {
				$this->display_section_nav( $active_section );
			} else {
				?>
				<h2><?php esc_html_e( 'ConvertKit', 'convertkit' ); ?></h2>
				<?php
			}
			?>

			<form method="post" action="options.php">
				<?php
				foreach ( $this->sections as $section ) :
					if ( $active_section === $section->name ) :
						$section->render();
					endif;
				endforeach;

				// Check for Multibyte string PHP extension.
				if ( ! extension_loaded( 'mbstring' ) ) {
					?>
					<div class="inline notice notice-warning">
						<p>
							<strong>
								<?php
									printf(
										/* Translators: %s is link to mbstring PHP documentation website. */
										__( 'Notice: Your server does not support the %s function - this is required for better character encoding. Please contact your webhost to have it installed.', 'convertkit' ),
										'<a href="https://php.net/manual/en/mbstring.installation.php">mbstring</a>'
									);
								?>
							</strong>
						</p>
					</div>
					<?php
				}
				?>
				<p class="description">
				<?php
					printf(
						'If you need help setting up the plugin please refer to the %s plugin documentation.</a>',
						'<a href="https://help.convertkit.com/en/articles/2502591-the-convertkit-wordpress-plugin" target="_blank">'
					);
				?>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Queue up the admin styles
	 */
	public function admin_styles() {
		wp_enqueue_style( 'wp-convertkit-admin' );
	}

	/**
	 * Render a tab for each section
	 *
	 * @param string $active_section The currently active section.
	 */
	public function display_section_nav( $active_section ) {
		?>
		<h1><?php esc_html_e( 'ConvertKit', 'convertkit' ); ?></h1>
		<h2 class="nav-tab-wrapper">
		<?php
		foreach ( $this->sections as $section ) :
			printf(
				'<a href="?page=%s&tab=%s" class="nav-tab right %s">%s</a>',
				esc_html( $this->settings_key ),
				esc_html( $section->name ),
				$active_section === $section->name ? 'nav-tab-active' : '',
				esc_html( $section->tab_text )
			);
		endforeach;
		?>
		</h2>
		<?php
	}

	/**
	 * Adds a section to be displayed
	 *
	 * @param string $section A section class name.
	 */
	public function register_section( $section ) {
		$section_instance = new $section();

		if ( $section_instance->is_registerable ) {
			array_push( $this->sections, $section_instance );
		}
	}

	/**
	 * Register each section
	 */
	public function register_sections() {
		wp_register_style( 'wp-convertkit-admin', plugins_url( '../resources/backend/wp-convertkit.css', __FILE__ ) );
		$this->register_section( 'ConvertKit_Settings_General' );
		$this->register_section( 'ConvertKit_Settings_Tools' );
		$this->register_section( 'ConvertKit_Settings_Wishlist' );
		$this->register_section( 'ConvertKit_Settings_ContactForm7' );
	}

	/**
	 * Ajax callback to return formatted list of available tags
	 *
	 * Since 1.5.0
	 */
	public function get_tags() {
		check_ajax_referer( 'convertkit-tinymce', 'security' );

		$tags   = get_option( 'convertkit_tags' );
		$values = array();
		if ( is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				$values[] = array(
					'value' => $tag['id'],
					'text'  => $tag['name'],
				);
			}
		}
		wp_send_json( $values );
	}

	/**
	 * Add tags to the footer
	 *
	 * @since 1.5.0
	 */
	public function add_tags_footer() {

		// Create nonce.
		global $pagenow;

		if ( 'admin.php' !== $pagenow ) {
			$nonce = wp_create_nonce( 'convertkit-tinymce' );
			?>
			<script type="text/javascript">
				jQuery( document ).ready( function( $ ) {
					var data = {
						'action'	: 'convertkit_get_tags', // wp ajax action
						'security'	: '<?php echo $nonce; ?>' // nonce value created earlier
					};
					jQuery.post( ajaxurl, data, function( response ) {
						if( response === '-1' ){
							console.log( 'error convertkit_get_tags' );
						} else {
							if ( typeof( tinyMCE ) != 'undefined' ) {
								if (tinyMCE.activeEditor != null) {
									tinyMCE.activeEditor.settings.ckTags = response;
									console.log('added tags');
								}
							}
						}
					});
				});
			</script>
			<?php
		}
	}

	/**
	 * Show customer's tags.
	 *
	 * @param WP_User $user WordPress uer object.
	 */
	public function add_customer_meta_fields( $user ) {
		$tags = get_user_meta( $user->ID, 'convertkit_tags', true );
		?>
		<h2><?php esc_attr_e( 'ConvertKit Tags', 'convertkit' ); ?></h2>
		<table class="form-table" id="<?php echo esc_attr( 'fieldset-convertkit' ); ?>">
			<tr>
				<th><label for="tags"><?php esc_attr_e( 'Tags', 'convertkit' ); ?></label></th>
				<td>
				<textarea id="tags" name="tags" disabled="disabled">
				<?php
				if ( empty( $tags ) ) {
					esc_html_e( 'No ConvertKit Tags assigned to this user.', 'convertkit' );
				} else {
					$tags = json_decode( $tags );
					foreach ( $tags as $key => $tag ) {
						echo $tag . ' (' . $key . ')' . "\n";
					}
				}
				?>
				</textarea>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Display the ConvertKit forms dropdown.
	 *
	 * @since 1.5.3
	 *
	 * @param WP_Term $tag The WP term.
	 */
	public function category_form_fields( $tag ) {

		$forms        = $this->get_forms();
		$default_form = get_term_meta( $tag->term_id, 'ck_default_form', true );

		echo '<tr class="form-field"><th scope="row"><label for="description">ConvertKit Form</label></th><td>';

		// Check for error in response.
		if ( isset( $forms[0]['id'] ) && '-2' === $forms[0]['id'] ) {
			$html = '<p class="error">' . __( 'Error connecting to API. Please verify your site can connect to <code>https://api.convertkit.com</code>', 'convertkit' ) . '</p>';
		} else {
			$html  = '<select id="ck_default_form" name="ck_default_form">';
			$html .= '<option value="default">' . __( 'None', 'convertkit' ) . '</option>';

			foreach ( $forms as $form ) {
				$html .= sprintf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $form['id'] ),
					selected( $default_form, $form['id'], false ),
					esc_html( $form['name'] )
				);
			}

			$html .= '</select>';
		}

		if ( empty( $forms ) ) {
			$html .= '<p class="description">' . __( 'There are no forms setup in your account. You can go <a href="https://app.convertkit.com/landing_pages/new" target="_blank">here</a> to create one.', 'convertkit' ) . '</p>';
		}

		$html .= '<p class="description">' . __( 'This form will be automatically added to posts in this category.', 'convertkit' ) . '</p></td></tr>';

		echo $html; // WPCS: XSS ok.

	}

	/**
	 * Set the default ConvertKit form for
	 *
	 * @param int $tag_id Tag ID.
	 */
	public function save_category_fields( $tag_id ) {
		$ck_default_form = isset( $_POST['ck_default_form'] ) ? intval( $_POST['ck_default_form'] ) : 0;

		if ( $ck_default_form ) {
			update_term_meta( $tag_id, 'ck_default_form', $ck_default_form );
		} else {
			update_term_meta( $tag_id, 'ck_default_form', 'default' );
		}
	}

	/**
	 * Returns our list of ConvertKit forms.
	 * Fetches new forms if stored transient is older than 2 minutes
	 *
	 * @return bool|mixed|void
	 */
	public function get_forms() {
		$forms = get_transient( 'convertkit_forms' );

		if ( false === $forms ) {
			$this->api->update_resources( $this->api_key, $this->api_secret );

			$forms = get_option( 'convertkit_forms' );

			set_transient( 'convertkit_forms', $forms, 2 * MINUTE_IN_SECONDS );
		}

		return $forms;
	}

}
