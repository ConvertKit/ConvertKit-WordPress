<?php
/**
 * ConvertKit Form Widget class
 *
 * @package ConvertKit
 * @author ConvertKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form Widget.
 *
 * @author   ConvertKit
 * @category Widgets
 * @version  1.0.0
 * @extends  WP_Widget
 */
class CK_Widget_Form extends WP_Widget {

	/**
	 * CSS class.
	 *
	 * @var string
	 */
	public $widget_cssclass;

	/**
	 * Widget description.
	 *
	 * @var string
	 */
	public $widget_description;

	/**
	 * Widget ID.
	 *
	 * @var string
	 */
	public $widget_id;

	/**
	 * Widget name.
	 *
	 * @var string
	 */
	public $widget_name;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	public $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->widget_cssclass    = 'convertkit widget_convertkit_form';
		$this->widget_description = __( 'Display a ConvertKit form.', 'convertkit' );
		$this->widget_id          = 'convertkit_form';
		$this->widget_name        = __( 'ConvertKit Form', 'convertkit' );

		$forms = get_option( 'convertkit_forms', array() );

		$this->settings = array(
			'title' => array(
				'type'  => 'text',
				'std'   => __( 'ConvertKit Form', 'convertkit' ),
				'label' => __( 'Title', 'convertkit' ),
			),
			'form'  => array(
				'type'    => 'select',
				'std'     => '',
				'label'   => __( 'Form', 'convertkit' ),
				'options' => $forms,
			),
		);

		$widget_ops = array(
			'classname'                   => $this->widget_cssclass,
			'description'                 => $this->widget_description,
			'customize_selective_refresh' => true,
		);

		parent::__construct( $this->widget_id, $this->widget_name, $widget_ops );

	}

	/**
	 * Output the html at the start of a widget.
	 *
	 * @param array $args Widget arguments.
	 * @param array $instance Widget settings.
	 */
	public function widget_start( $args, $instance ) {
		echo $args['before_widget'];
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );
		if ( $title ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}
	}

	/**
	 * Output the html at the end of a widget.
	 *
	 * @param array $args After widget setting.
	 */
	public function widget_end( $args ) {
		echo $args['after_widget'];
	}

	/**
	 * Output widget.
	 *
	 * @see WP_Widget
	 *
	 * @param array $args Widget arguments.
	 * @param array $instance Widget settings.
	 */
	public function widget( $args, $instance ) {

		if ( ! isset( $instance['form'] ) ) {
			return;
		}

		$api     = WP_ConvertKit::get_api();
		$form_id = $instance['form'];
		$forms   = get_option( 'convertkit_forms' );

		if ( isset( $forms[ $form_id ]['uid'] ) ) {
			// New form.
			$this->widget_start( $args, $instance );
			$tag = '<script async data-uid="' . $forms[ $form_id ]['uid'] . '" src="' . $forms[ $form_id ]['embed_js'] . '"></script>';
			echo $tag;
			$this->widget_end( $args );
		} else {
			// Old form.
			$url = add_query_arg(
				array(
					'api_key' => WP_ConvertKit::get_api_key(),
					'v'       => WP_ConvertKit::get_forms_version(),
				),
				'https://forms.convertkit.com/' . $form_id . '.html'
			);

			$form_markup = $api->get_resource( $url );

			if ( $api && ! is_wp_error( $api ) ) {
				ob_start();

				$this->widget_start( $args, $instance );
				echo $form_markup;
				$this->widget_end( $args );

				$content = ob_get_clean();

				echo $content;
			}
		}
	}

	/**
	 * Outputs the settings update form.
	 *
	 * @param array $instance Widget settings.
	 * @return null
	 */
	public function form( $instance ) {

		if ( empty( $this->settings ) ) {
			return;
		}

		foreach ( $this->settings as $key => $setting ) {

			$class = isset( $setting['class'] ) ? $setting['class'] : '';
			$value = isset( $instance[ $key ] ) ? $instance[ $key ] : $setting['std'];

			switch ( $setting['type'] ) {

				case 'text':
					?>
					<p>
						<label for="<?php echo $this->get_field_id( $key ); ?>"><?php echo $setting['label']; ?></label>
						<input class="widefat <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo $this->get_field_name( $key ); ?>" type="text" value="<?php echo esc_attr( $value ); ?>" />
					</p>
					<?php
					break;

				case 'number':
					?>
					<p>
						<label for="<?php echo $this->get_field_id( $key ); ?>"><?php echo $setting['label']; ?></label>
						<input class="widefat <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo $this->get_field_name( $key ); ?>" type="number" step="<?php echo esc_attr( $setting['step'] ); ?>" min="<?php echo esc_attr( $setting['min'] ); ?>" max="<?php echo esc_attr( $setting['max'] ); ?>" value="<?php echo esc_attr( $value ); ?>" />
					</p>
					<?php
					break;

				case 'select':
					if ( empty( $setting['options'] ) ) {
						$query_args        = array( 'page' => WP_ConvertKit::SETTINGS_PAGE_SLUG );
						$settings_page_url = add_query_arg( $query_args, admin_url( 'options-general.php' ) );
						?>
						<p><?php echo __( 'No forms were returned from ConvertKit.', 'convertkit' ); ?></p>
						<?php /* translators: 1: settings page url */ ?>
						<p><?php echo sprintf( __( 'Please check the <a href="%s">settings</a>.', 'convertkit' ), $settings_page_url ); ?></p>
						<?php
					} else {
						?>
						<p>
							<label
								for="<?php echo $this->get_field_id( $key ); ?>"><?php echo $setting['label']; ?></label>
							<select class="widefat <?php echo esc_attr( $class ); ?>"
							id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"
							name="<?php echo $this->get_field_name( $key ); ?>">
								<?php foreach ( $setting['options'] as $option_key => $option_value ) : ?>
									<option
										value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $option_key, $value ); ?>><?php echo esc_html( $option_value['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<?php
					}
					break;

				case 'textarea':
					?>
					<p>
						<label for="<?php echo $this->get_field_id( $key ); ?>"><?php echo $setting['label']; ?></label>
						<textarea class="widefat <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo $this->get_field_name( $key ); ?>" cols="20" rows="3"><?php echo esc_textarea( $value ); ?></textarea>
						<?php if ( isset( $setting['desc'] ) ) : ?>
							<small><?php echo esc_html( $setting['desc'] ); ?></small>
						<?php endif; ?>
					</p>
					<?php
					break;

				case 'checkbox':
					?>
					<p>
						<input class="checkbox <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" type="checkbox" value="1" <?php checked( $value, 1 ); ?> />
						<label for="<?php echo $this->get_field_id( $key ); ?>"><?php echo $setting['label']; ?></label>
					</p>
					<?php
					break;
			}
		}
	}

	/**
	 * Updates a particular instance of a widget.
	 *
	 * @see    WP_Widget->update
	 * @param  array $new_instance Updated widget settings.
	 * @param  array $old_instance Original widget settings.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {

		$instance = $old_instance;

		if ( empty( $this->settings ) ) {
			return $instance;
		}

		// Loop settings and get values to save.
		foreach ( $this->settings as $key => $setting ) {
			if ( ! isset( $setting['type'] ) ) {
				continue;
			}

			// Format the value based on settings type.
			switch ( $setting['type'] ) {
				case 'number':
					$instance[ $key ] = absint( $new_instance[ $key ] );

					if ( isset( $setting['min'] ) && '' !== $setting['min'] ) {
						$instance[ $key ] = max( $instance[ $key ], $setting['min'] );
					}

					if ( isset( $setting['max'] ) && '' !== $setting['max'] ) {
						$instance[ $key ] = min( $instance[ $key ], $setting['max'] );
					}
					break;
				case 'textarea':
					$instance[ $key ] = wp_kses( trim( wp_unslash( $new_instance[ $key ] ) ), wp_kses_allowed_html( 'post' ) );
					break;
				case 'checkbox':
					$instance[ $key ] = empty( $new_instance[ $key ] ) ? 0 : 1;
					break;
				default:
					$instance[ $key ] = sanitize_text_field( $new_instance[ $key ] );
					break;
			}
		}

		return $instance;
	}

}
