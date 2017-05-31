<?php
/**
 * ConvertKit PI class
 *
 * @package ConvertKit
 * @author ConvertKit
 */

/**
 * ConvertKit_API Class
 * Establishes API connection to ConvertKit App
 */
class ConvertKit_API {

	/**
	 * ConvertKit API Key
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * ConvertKit API Secret
	 *
	 * @var string
	 */
	protected $api_secret;

	/**
	 * Save debug data to log
	 *
	 * @var  string
	 */
	protected $debug;

	/**
	 * Version of ConvertKit API
	 *
	 * @var string
	 */
	protected $api_version = 'v3';

	/**
	 * ConvertKit API URL
	 *
	 * @var string
	 */
	protected $api_url_base = 'https://api.convertkit.com/';

	/**
	 * API resources
	 *
	 * @var array
	 */
	protected $resources = array();

	/**
	 * Additional markup
	 *
	 * @var array
	 */
	protected $markup = array();

	/**
	 * Constructor for ConvertKitAPI instance
	 *
	 * @param string $api_key ConvertKit API Key.
	 * @param string $api_secret ConvertKit API Secret.
	 * @param string $debug Save data to log.
	 */
	public function __construct( $api_key, $api_secret, $debug ) {
		$this->api_key = $api_key;
		$this->api_secret = $api_secret;
		$this->debug = $debug;
	}

	/**
	 * Gets a resource index
	 *
	 * GET /{$resource}/
	 *
	 * @param string $resource Resource type.
	 * @return object API response
	 */
	public function get_resources( $resource ) {

		if ( ! array_key_exists( $resource, $this->resources ) ) {

			if ( 'landing_pages' === $resource ) {
				$api_response = $this->_get_api_response( 'forms' );
			} else {
				$api_response = $this->_get_api_response( $resource );
			}

			if ( is_null( $api_response ) || is_wp_error( $api_response ) || isset( $api_response['error'] ) || isset( $api_response['error_message'] ) ) {
				$this->resources[ $resource ] = array(
					array(
						'id' => '-2',
						'name' => 'Error contacting API',
					),
				);
			} else {
				$_resource = array();

				if ( 'forms' === $resource ) {
					$response = isset( $api_response['forms'] ) ? $api_response['forms'] : array();
					foreach ( $response as $form ) {
						if ( isset( $form['archived'] ) && $form['archived'] ) {
							continue;
						}
						$_resource[] = $form;
					}
				} elseif ( 'landing_pages' === $resource ) {

					$response = isset( $api_response['forms'] ) ? $api_response['forms'] : array();
					foreach ( $response as $landing_page ) {
						if ( 'hosted' === $landing_page['type'] ) {
							if ( isset( $landing_page['archived'] ) && $landing_page['archived'] ) {
								continue;
							}
							$_resource[] = $landing_page;
						}
					}
				} elseif ( 'subscription_forms' === $resource ) {
					foreach ( $api_response as $mapping ) {
						if ( isset( $mapping['archived'] ) && $mapping['archived'] ) {
							continue;
						}
						$_resource[ $mapping['id'] ] = $mapping['form_id'];
					}
				}

				$this->resources[ $resource ] = $_resource;
			} // End if().
		} // End if().

		return $this->resources[ $resource ];
	}

	/**
	 * Adds a subscriber to a form.
	 *
	 * @param string $form_id Form ID.
	 * @param array  $options Array of user data.
	 */
	public function form_subscribe( $form_id, $options ) {
		$request = $this->api_version . sprintf( '/forms/%s/subscribe', $form_id );

		$args = array(
			'api_key' => $this->api_key,
			'email'   => $options['email'],
			'name'    => $options['name'],
		);

		$this->make_request( $request, 'POST', $args );
		return;
	}

	/**
	 * Remove subscription from a form
	 *
	 * @param array $options Array of user data.
	 */
	public function form_unsubscribe( $options ) {
		$request = $this->api_version . '/unsubscribe';

		$args = array(
			'api_secret' => $this->api_secret,
			'email'      => $options['email'],
		);

		$this->make_request( $request, 'PUT', $args );
		return;
	}

	/**
	 * Get markup from ConvertKit for the provided $url
	 *
	 * @param string $url URL of API action.
	 * @return string
	 */
	public function get_resource( $url ) {
		$resource = '';

		if ( ! empty( $url ) && isset( $this->markup[ $url ] ) ) {
			$resource = $this->markup[ $url ];
		} elseif ( ! empty( $url ) ) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 10,
				)
			);

			if ( ! is_wp_error( $response ) ) {
				if ( ! function_exists( 'str_get_html' ) ) {
					require_once( dirname( __FILE__ ) . '/../vendor/simple-html-dom/simple-html-dom.php' );
				}

				if ( ! function_exists( 'url_to_absolute' ) ) {
					require_once( dirname( __FILE__ ) . '/../vendor/url-to-absolute/url-to-absolute.php' );
				}

				$body = wp_remote_retrieve_body( $response );
				$html = str_get_html( $body );
				foreach ( $html->find( 'a, link' ) as $element ) {
					if ( isset( $element->href ) ) {
						$element->href = url_to_absolute( $url, $element->href );
					}
				}

				foreach ( $html->find( 'img, script' ) as $element ) {
					if ( isset( $element->src ) ) {
						$element->src = url_to_absolute( $url, $element->src );
					}
				}

				foreach ( $html->find( 'form' ) as $element ) {
					if ( isset( $element->action ) ) {
						$element->action = url_to_absolute( $url, $element->action );
					} else {
						$element->action = $url;
					}
				}

				// Check `status_code` for 200, otherwise log error.
				if ( '200' === $response['response']['code'] ) {
					$resource = $html->save();
					$this->markup[ $url ] = $resource;
				} else {
					$this->log( 'Status Code (' . $response['response']['code'] . ') for URL (' . $url . '): ' . $html->save() );
				}
			} // End if().
		} // End if().

		return $resource;
	}

	/**
	 * Do a remote request.
	 *
	 * @param string $path Part of URL.
	 * @return array
	 */
	private function _get_api_response( $path = '' ) {

		$args = array(
			'api_key' => $this->api_key,
			);
		$api_path = $this->api_url_base . $this->api_version;
		$url = add_query_arg( $args, path_join( $api_path, $path ) );

		$this->log( 'API Request (_get_api_response): ' . $url );

		$data = get_transient( 'convertkit_get_api_response' );

		if ( ! $data ) {

			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 10,
					'sslverify' => false,
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->log( 'Error: ' . $response->get_error_message() );

				return array(
					'error' => $response->get_error_message(),
				);
			} else {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
			}

			set_transient( 'convertkit_get_api_response', $data, 300 );

			$this->log( 'API Response (_get_api_response): ' . wp_json_encode( $data ) );
		} else {
			$this->log( 'Transient Response (_get_api_response)' );
		}

		return $data;
	}

	/**
	 * Make a request to the ConvertKit API
	 *
	 * @param string $request Request string.
	 * @param string $method HTTP Method.
	 * @param array  $args Request arguments.
	 * @return object Response object
	 */
	private function make_request( $request, $method, $args = array() ) {

		$this->log( 'API Request (make_request): ' . $request . ' Args: ' . wp_json_encode( $args ) );

		$url = $this->api_url_base . $request;

		$headers = array(
			'Content-Type' => 'application/json; charset=utf-8',
		);

		$settings = array(
			'headers' => $headers,
			'method'  => $method,
			'body'    => wp_json_encode( $args ),
		);

		$result = wp_remote_request( $url, $settings );

		if ( is_wp_error( $result ) ) {
			$this->log( 'API Response (make_request): WPError: ' . $result->get_error_message() );
		} elseif ( isset( $result['response']['code'] ) && '200' === $result['response']['code'] ) {
			if ( isset( $result['body'] ) ) {
				$this->log( 'API Response (make_request): ' . $result['body'] );
			} else {
				$this->log( 'API Response (make_request): Response code 200, but body is not set.' );
			}
		} else {
			$this->log( 'API Response (make_request): Result code: ' . $result['response']['code'] . ' ' . $result['response']['message'] );
		}

	}

	/**
	 * Output data to log file
	 *
	 * @param string $message String to add to log.
	 */
	public function log( $message ) {

		if ( 'on' === $this->debug ) {
			require_once( ABSPATH . '/wp-admin/includes/file.php' );
			WP_Filesystem();
			global $wp_filesystem;

			$dir = dirname( __FILE__ );
			$time   = date_i18n( 'm-d-Y @ H:i:s -' );
			$file = trailingslashit( $dir ) . 'log.txt';

			$wp_filesystem->put_contents(
				$file,
				$time . ' ' . $message . "\n",
				FS_CHMOD_FILE
			);
		}

	}

}
