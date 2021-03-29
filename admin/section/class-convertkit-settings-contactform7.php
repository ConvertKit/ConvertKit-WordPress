<?php
/**
 * ConvertKit Contact Form 7 Settings class
 *
 * @package ConvertKit
 * @author ConvertKit
 */

/**
 * Class ConvertKit_Settings_ContactForm7
 *
 * @since 1.4.4
 */
class ConvertKit_Settings_ContactForm7 extends ConvertKit_Settings_Base {

	/**
	 * Contact Form 7 forms
	 *
	 * @var array
	 */
	private $forms;

	/**
	 * Constructor
	 */
	public function __construct() {

		if ( ! defined( 'WPCF7_VERSION' ) ) {
			$this->is_registerable = false;
			return;
		}

		$this->settings_key = '_wp_convertkit_integration_contactform7_settings';
		$this->name         = 'contactform7';
		$this->title        = 'Contact Form 7 Integration Settings';
		$this->tab_text     = 'Contact Form 7';

		$this->get_cf7_forms();

		parent::__construct();
	}

	/**
	 * Gets available forms from CF7
	 */
	public function get_cf7_forms() {

		$forms = array();

		$args = array(
			'orderby'        => 'ID',
			'posts_per_page' => '100',
			'order'          => 'ASC',
			'post_type'      => 'wpcf7_contact_form',
		);

		$result = new WP_Query( $args );

		foreach ( $result->posts as $post ) {
			$forms[] = array(
				'id'   => $post->ID,
				'name' => $post->post_title,
			);
		}

		wp_reset_postdata();
		$this->forms = $forms;
	}

	/**
	 * Register and add settings
	 */
	public function register_fields() {

		$forms = $this->get_forms();

		foreach ( $this->forms as $form ) {

			add_settings_field(
				sprintf( '%s_title', $form['id'] ),
				'Contact Form 7 Form',
				array( $this, 'cf7_title_callback' ),
				$this->settings_key,
				$this->name,
				array(
					'cf7_form_id'   => $form['id'],
					'cf7_form_name' => $form['name'],
					'sortable'      => true,
				)
			);

			add_settings_field(
				sprintf( '%s_form', $form['id'] ),
				'ConvertKit Form',
				array( $this, 'cf7_form_callback' ),
				$this->settings_key,
				$this->name,
				array(
					'cf7_form_id' => $form['id'],
					'forms'       => $forms,
					'sortable'    => false,
				)
			);

			add_settings_field(
				sprintf( '%s_email', $form['id'] ),
				'CF7 Email Field',
				array( $this, 'cf7_email_callback' ),
				$this->settings_key,
				$this->name,
				array(
					'cf7_email_id' => 'your-email',
					'sortable'     => false,
				)
			);

			add_settings_field(
				sprintf( '%s_name', $form['id'] ),
				'CF7 Name Field',
				array( $this, 'cf7_name_callback' ),
				$this->settings_key,
				$this->name,
				array(
					'cf7_name_id' => 'your-name',
					'sortable'    => false,
				)
			);

		}
	}

	/**
	 * Prints help info for this section.
	 */
	public function print_section_info() {
		?>
		<p><?php esc_html_e( 'ConvertKit seamlessly integrates with Contact Form 7 to let you add subscribers using Contact Form 7 forms.', 'convertkit' ); ?></p>

		<p>
		<?php
			printf( 'The Contact Form 7 form must have <code>text*</code> fields named <code>your-name</code> and <code>your-email</code>. ' );
			esc_html_e( 'These fields will be sent to ConvertKit for the subscription.', 'convertkit' );
		?>
		</p>

		<?php
	}

	/**
	 * Render the settings table. Designed to mimic WP's do_settings_fields.
	 */
	public function do_settings_table() {
		global $wp_settings_fields;

		$table   = new Multi_Value_Field_Table;
		$columns = array();
		$rows    = array();
		$fields  = $wp_settings_fields[ $this->settings_key ][ $this->name ];

		foreach ( $fields as $field ) {
			list( $cf7_form_id, $field_type ) = explode( '_', $field['id'] );

			if ( ! in_array( $field_type, $columns, true ) ) {
				$table->add_column( $field_type, $field['title'], $field['args']['sortable'] );
				array_push( $columns, $field_type );
			}

			if ( ! isset( $rows[ $cf7_form_id ] ) ) {
				$rows[ $cf7_form_id ] = array();
			}

			$rows[ $cf7_form_id ][ $field_type ] = call_user_func( $field['callback'], $field['args'] );
		}

		foreach ( $rows as $row ) {
			$table->add_item( $row );
		}

		$table->prepare_items();
		$table->display();
	}

	/**
	 * Renders the section
	 *
	 * Called from ConvertKitSettings::display_settings_page()
	 *
	 * @return void
	 */
	public function render() {
		global $wp_settings_sections;

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
	 * Display form title in first column
	 *
	 * @param  array $args Name argument.
	 * @return string
	 */
	public function cf7_title_callback( $args ) {
		return $args['cf7_form_name'];
	}

	/**
	 * Display CF7 to CK mapping
	 *
	 * @param  array $args Settings to display.
	 * @return string $html
	 */
	public function cf7_form_callback( $args ) {
		$cf7_form_id = $args['cf7_form_id'];
		$forms       = $args['forms'];

		$html  = sprintf( '<select id="%1$s_%2$s" class="widefat" name="%1$s[%2$s]">', $this->settings_key, $cf7_form_id );
		$html .= '<option value="default">' . esc_html__( 'None', 'convertkit' ) . '</option>';

		foreach ( $forms as $form ) {
			$selected = '';
			if ( isset( $this->options[ $cf7_form_id ] ) ) {
				$selected = selected( $this->options[ $cf7_form_id ], $form['id'], false );
			}
			$html .=
				'<option value="' .
				esc_attr( $form['id'] ) . '" ' .
				$selected . '>' .
				esc_html( $form['name'] ) . '</option>';
		}

		$html .= '</select>';

		return $html;
	}


	/**
	 * Display email in first column
	 *
	 * @param array $args Email setting.
	 * @return string
	 */
	public function cf7_email_callback( $args ) {
		return $args['cf7_email_id'];
	}

	/**
	 * Display form title in first column
	 *
	 * @param array $args Name setting.
	 * @return string
	 */
	public function cf7_name_callback( $args ) {
		return $args['cf7_name_id'];
	}

	/**
	 * Sanitizes the settings
	 *
	 * @param  array $input Values to be sanitized.
	 * @return array sanitized settings
	 */
	public function sanitize_settings( $input ) {
		// Settings page can be paginated; combine input with existing options.
		// If saved options are not an array, start fresh. Fixes rogue saved options.
		$output = is_array( $this->options ) ? $this->options : array();

		foreach ( $input as $key => $value ) {
			$output[ $key ] = sanitize_text_field( $value );
		}
		$sanitize_hook = 'sanitize' . $this->settings_key;

		return apply_filters( $sanitize_hook, $output, $input );
	}
}
