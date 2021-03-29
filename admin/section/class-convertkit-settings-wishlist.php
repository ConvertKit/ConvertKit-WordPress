<?php
/**
 * ConvertKit WishList Member Settings class
 *
 * @package ConvertKit
 * @author ConvertKit
 */

/**
 * Class ConvertKit_Setting_Wishlist
 */
class ConvertKit_Settings_Wishlist extends ConvertKit_Settings_Base {

	/**
	 * WLM levels
	 *
	 * @var array
	 */
	private $wlm_levels;

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( ! function_exists( 'wlmapi_get_levels' ) ) {
			$this->is_registerable = false;
			return;
		}

		$this->settings_key = '_wp_convertkit_integration_wishlistmember_settings';
		$this->name         = 'wishlist-member';
		$this->title        = 'WishList Member Integration Settings';
		$this->tab_text     = 'WishList Member';
		$this->wlm_levels   = $this->get_wlm_levels();

		parent::__construct();
	}

	/**
	 * Gets membership levels from WishList Member API
	 *
	 * @return array Membership levels
	 */
	public function get_wlm_levels() {
		$wlm_get_levels = wlmapi_get_levels();

		if ( 1 === $wlm_get_levels['success'] ) {
			$this->wlm_levels = $wlm_get_levels['levels']['level'];
			return $this->wlm_levels;
		} else {
			return array();
		}
	}

	/**
	 * Register and add settings
	 */
	public function register_fields() {

		$forms = $this->get_forms();
		$tags  = get_option( 'convertkit_tags' );

		foreach ( $this->wlm_levels as $wlm_level ) {
			add_settings_field(
				sprintf( '%s_title', $wlm_level['id'] ),
				__( 'WishList Membership Level', 'convertkit' ),
				array( $this, 'wlm_title_callback' ),
				$this->settings_key,
				$this->name,
				array(
					'wlm_level_id'   => $wlm_level['id'],
					'wlm_level_name' => $wlm_level['name'],
					'sortable'       => true,
				)
			);

			add_settings_field(
				sprintf( '%s_form', $wlm_level['id'] ),
				__( 'ConvertKit Form', 'convertkit' ),
				array( $this, 'wlm_level_callback' ),
				$this->settings_key,
				$this->name,
				array(
					'wlm_level_id' => $wlm_level['id'],
					'forms'        => $forms,
				)
			);

			add_settings_field(
				sprintf( '%s_unsubscribe', $wlm_level['id'] ),
				__( 'Unsubscribe Action', 'convertkit' ),
				array( $this, 'wlm_unsubscribe_callback' ),
				$this->settings_key,
				$this->name,
				array(
					'wlm_level_id' => $wlm_level['id'],
					'tags'         => $tags,
				)
			);
		} // End foreach().
	}

	/**
	 * Prints help info for this section
	 */
	public function print_section_info() {
		?>
			<p><?php esc_html_e( ' ConvertKit seamlessly integrates with WishList Member to let you capture all of your WishList Membership registrations within your ConvertKit forms.', 'convertkit' ); ?></p>
		<?php
	}

	/**
	 * Render the settings table. Designed to mimic WP's do_settings_fields
	 */
	public function do_settings_table() {
		global $wp_settings_fields;

		$table   = new Multi_Value_Field_Table;
		$columns = array();
		$rows    = array();
		$fields  = $wp_settings_fields[ $this->settings_key ][ $this->name ];

		foreach ( $fields as $field ) {
			list( $wlm_level_id, $field_type ) = explode( '_', $field['id'] );

			if ( ! in_array( $field_type, $columns, true ) ) {
				$table->add_column( $field_type, $field['title'], $field['args']['sortable'] );
				array_push( $columns, $field_type );
			}

			if ( ! isset( $rows[ $wlm_level_id ] ) ) {
				$rows[ $wlm_level_id ] = array();
			}

			$rows[ $wlm_level_id ][ $field_type ] = call_user_func( $field['callback'], $field['args'] );
		}

		foreach ( $rows as $row ) {
			$table->add_item( $row );
		}

		$table->prepare_items();
		$table->display();
	}

	/**
	 * Renders the section
	 */
	public function render() {
		global $wp_settings_sections, $wp_settings_fields;

		if ( ! isset( $wp_settings_sections[ $this->settings_key ] ) ) {
			return;
		}

		foreach ( $wp_settings_sections[ $this->settings_key ] as $section ) {
			if ( $section['title'] ) {
				echo '<h3>' . esc_html( $section['title'] ) . '</h3>';
			}
			if ( $section['callback'] ) {
				call_user_func( $section['callback'], $section );
			}

			$this->do_settings_table();
			settings_fields( $this->settings_key );
			submit_button();
		}
	}

	/**
	 * Title for WishList Membership Level
	 *
	 * @param array $arguments Arguments from add_settings_field().
	 * @return string WishList Membership Level title.
	 */
	public function wlm_title_callback( $arguments ) {
		return $arguments['wlm_level_name'];
	}

	/**
	 * CK Form select for WishList Membership Level.
	 *
	 * @param array $arguments Arguments from add_settings_field().
	 * @return string Select element.
	 */
	public function wlm_level_callback( $arguments ) {
		$wlm_level_id = $arguments['wlm_level_id'];
		$forms        = $arguments['forms'];

		$html  = sprintf( '<select id="%1$s_%2$s_form" name="%1$s[%2$s_form]">', $this->settings_key, $wlm_level_id );
		$html .= '<option value="default">' . __( 'None', 'convertkit' ) . '</option>';

		foreach ( $forms as $form ) {
			$html .= sprintf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $form['id'] ),
				selected( $this->options[ $wlm_level_id . '_form' ], $form['id'], false ),
				esc_html( $form['name'] )
			);
		}

		$html .= '</select>';

		return $html;
	}

	/**
	 * Action to take when customer membership lapses
	 *
	 * @param  array $arguments Arguments from add_settings_field().
	 * @return string Checkbox and label.
	 */
	public function wlm_unsubscribe_callback( $arguments ) {
		$wlm_level_id = $arguments['wlm_level_id'];
		$tags         = $arguments['tags'];

		$html  = sprintf( '<select id="%1$s_%2$s_form" name="%1$s[%2$s_unsubscribe]">', $this->settings_key, $wlm_level_id );
		$html .= '<option value="0">' . __( 'None', 'convertkit' ) . '</option>';

		foreach ( $tags as $tag ) {
			$html .= sprintf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $tag['id'] ),
				selected( $this->options[ $wlm_level_id . '_unsubscribe' ], $tag['id'], false ),
				esc_html( 'Tag: ' . $tag['name'] )
			);
		}

		$html .= '<option value="unsubscribe">' . __( 'Unsubscribe from all', 'convertkit' ) . '</option>';
		$html .= '</select>';

		return $html;
	}

	/**
	 * Sanitizes the settings
	 *
	 * @param  array $input The settings fields submitted.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		// Settings page can be paginated; combine input with existing options.
		// If saved options are not an array, start fresh. Fixes rogue saved options.
		$output = is_array( $this->options ) ? $this->options : array();

		foreach ( $input as $key => $value ) {
			$output[ $key ] = sanitize_text_field( $value );
		}

		$sanitize_filter = 'sanitize' . $this->settings_key;

		return apply_filters( $sanitize_filter, $output, $input );
	}
}
