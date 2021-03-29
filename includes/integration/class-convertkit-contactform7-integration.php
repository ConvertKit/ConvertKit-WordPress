<?php
/**
 * Contact Form 7 Integration
 *
 * @package ConvertKit
 * @author ConvertKit
 */

require_once CONVERTKIT_PLUGIN_PATH . '/includes/class-convertkit-api.php';

/**
 * Class ConvertKit_ContactForm7_Integration
 */
class ConvertKit_ContactForm7_Integration {

	/**
	 * ConvertKit API instance.
	 *
	 * @var ConvertKit_API
	 */
	protected $api;

	/**
	 * CF7 settings from ConvertKit options.
	 *
	 * @var mixed|void
	 */
	protected $options;

	/**
	 * Constructor
	 */
	public function __construct() {
		$general_options = get_option( '_wp_convertkit_settings' );
		$this->options   = get_option( '_wp_convertkit_integration_wishlistmember_settings' );
		$api_key         = $general_options && array_key_exists( 'api_key', $general_options ) ? $general_options['api_key'] : null;
		$api_secret      = $general_options && array_key_exists( 'api_secret', $general_options ) ? $general_options['api_secret'] : null;
		$debug           = $general_options && array_key_exists( 'debug', $general_options ) ? $general_options['debug'] : null;
		$this->api       = new ConvertKit_API( $api_key, $api_secret, $debug );

		add_action( 'wpcf7_submit', array( $this, 'handle_wpcf7_submit' ), 10, 2 );
	}

	/**
	 * Handle checking submitted CF7 forms for a CK form mapping.
	 *
	 * If a mapping is found and options exist then the form submitter is subscribed.
	 *
	 * @param WPCF7_ContactForm $contact_form The contact form object.
	 * @param array             $result Resulting form submission to check.
	 *
	 * @return void
	 */
	public function handle_wpcf7_submit( $contact_form, $result ) {

		if ( $result['demo_mode'] ) {
			return;
		}

		if ( 'mail_sent' === $result['status'] ) {

			$mapping = get_option( '_wp_convertkit_integration_contactform7_settings' );

			if ( is_array( $mapping ) ) {
				if ( isset( $mapping[ $contact_form->id() ] ) && 'default' !== $mapping[ $contact_form->id() ] ) {
					$submission = WPCF7_Submission::get_instance();

					if ( $submission ) {
						$posted_data = $submission->get_posted_data();

						$name  = $posted_data['your-name'];
						$email = $posted_data['your-email'];

						if ( ! empty( $email ) ) {
							$this->api->form_subscribe(
								$mapping[ $contact_form->id() ],
								array(
									'email' => $email,
									'name'  => $name,
								)
							);
						}
					}
				}
			}
		}
	}

}

new ConvertKit_ContactForm7_Integration();
