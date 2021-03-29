<?php
/**
 * ConvertKit API class
 *
 * @package ConvertKit
 * @author ConvertKit
 */

use oldmine\RelativeToAbsoluteUrl\RelativeToAbsoluteUrl;
use KubAT\PhpSimple\HtmlDomParser;

/**
 * ConvertKit_API Class
 * Establishes API connection to ConvertKit App.
 */
class ConvertKit_API {

	/**
	 * ConvertKit API Key.
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * ConvertKit API Secret.
	 *
	 * @var string
	 */
	protected $api_secret;

	/**
	 * Save debug data to log.
	 *
	 * @var  string
	 */
	protected $debug;

	/**
	 * Version of ConvertKit API.
	 *
	 * @var string
	 */
	protected $api_version = 'v3';

	/**
	 * ConvertKit API URL.
	 *
	 * @var string
	 */
	protected $api_url_base = 'https://api.convertkit.com/';

	/**
	 * API resources.
	 *
	 * @var array
	 */
	protected $resources = array();

	/**
	 * Additional markup.
	 *
	 * @var array
	 */
	protected $markup = array();

	/**
	 * Constructor for ConvertKitAPI instance.
	 *
	 * @param string $api_key ConvertKit API Key.
	 * @param string $api_secret ConvertKit API Secret.
	 * @param string $debug Save data to log.
	 */
	public function __construct( $api_key, $api_secret, $debug ) {
		$this->api_key    = $api_key;
		$this->api_secret = $api_secret;
		$this->debug      = $debug;
	}

	/**
	 * Adds a tag to a subscriber.
	 *
	 * @param int   $tag Tag ID.
	 * @param array $options Array of user data.
	 * @return object
	 */
	public function add_tag( $tag, $options ) {
		$request = $this->api_version . sprintf( '/tags/%s/subscribe', $tag );

		$args = array(
			'api_key' => $this->api_key,
			'email'   => $options['email'],
		);

		return $this->make_request( $request, 'POST', $args );
	}

	/**
	 * Update resources in local options table.
	 *
	 * @param string $api_key API key.
	 * @param string $api_secret API secret.
	 * @return boolean
	 */
	public function update_resources( $api_key, $api_secret ) {

		$this->api_key = $api_key;

		$forms         = array();
		$landing_pages = array();
		$tags          = array();

		WP_ConvertKit::log( 'Updating resource with API key: ' . $api_key );

		// Forms and Landing Pages.
		$api_response = $this->_get_api_response( 'forms' );
		WP_ConvertKit::log( 'API Response: ' . print_r( $api_response, true ) );

		if ( is_null( $api_response )
			|| is_wp_error( $api_response )
			|| isset( $api_response['error'] )
			|| isset( $api_response['error_message'] )
		) {
			$error_message  = isset( $api_response['error'] ) ? $api_response['error'] : 'unknown error';
			$error_message .= is_wp_error( $api_response ) ? $api_response->get_error_message() : '';

			WP_ConvertKit::log( 'Error contacting API: ' . $error_message );

			$forms[0] = array(
				'id'   => '-2',
				'name' => 'Error contacting API',
			);

			$update_forms         = $this->maybe_update_option( 'convertkit_forms', $forms );
			$update_landing_pages = $this->maybe_update_option( 'convertkit_landing_pages', $landing_pages );
			$update_tags          = $this->maybe_update_option( 'convertkit_tags', $tags );

		} else {

			$response = isset( $api_response['forms'] ) ? $api_response['forms'] : array();
			foreach ( $response as $form ) {
				if ( isset( $form['archived'] ) && $form['archived'] ) {
					continue;
				}

				if ( 'hosted' === $form['type'] ) {
					$landing_pages[ $form['id'] ] = $form;
				} else {
					$forms[ $form['id'] ] = $form;
				}
			}
			$update_forms         = $this->maybe_update_option( 'convertkit_forms', $forms );
			$update_landing_pages = $this->maybe_update_option( 'convertkit_landing_pages', $landing_pages );

			// Tags.
			$api_response = $this->_get_api_response( 'tags' );
			$response     = isset( $api_response['tags'] ) ? $api_response['tags'] : array();

			foreach ( $response as $tag ) {
				$tags[] = $tag;
			}

			$update_tags = $this->maybe_update_option( 'convertkit_tags', $tags );

			$this->update_account_name( $api_secret );
		}

		return $update_forms && $update_landing_pages && $update_tags;
	}

	/**
	 * Update ConvertKit account name.
	 *
	 * @param string $api_secret API secret.
	 *
	 * @return bool
	 */
	public function update_account_name( $api_secret ) {
		$response = $this->_get_api_response( 'account', $api_secret );

		return $this->maybe_update_option( 'convertkit_account_name', $response['name'] );
	}

	/**
	 * Attempt to store updated forms, tags, or landing pages retrieved from the ConvertKit API.
	 * If they match what is already stored, WordPress's built in update_option() function will return
	 * false, which is unhelpful. So, if it returns false, we check if what we tried to store matches what is
	 * already stored, and if so, we return true.
	 *
	 * This way, we know if there was a failure or not.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $option_value Option value to (maybe) set.
	 *
	 * @return bool true if option was updated or if no update was needed, false if failure
	 */
	public function maybe_update_option( $option_name, $option_value ) {
		$result = update_option( $option_name, $option_value );

		if ( ! $result ) {
			$old    = get_option( $option_name, $option_value );
			$result = $old === $option_value ? true : false;
		}

		return $result;
	}

	/**
	 * Gets a resource index.
	 *
	 * GET /{$resource}/
	 *
	 * @param string $resource Resource type.
	 * @return object API response.
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
						'id'   => '-2',
						'name' => 'Error contacting API',
					),
				);
			} else {
				$_resource = array();

				if ( 'forms' === $resource ) {
					$response = isset( $api_response['forms'] ) ? $api_response['forms'] : array();
					$forms    = array();

					foreach ( $response as $form ) {

						if ( isset( $form['archived'] ) && $form['archived'] ) {
							continue;
						}

						$_resource[]          = $form;
						$forms[ $form['id'] ] = $form;
					}

					update_option( 'convertkit_forms', $forms );

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
					update_option( 'convertkit_landing_pages', $_resource );
				} elseif ( 'subscription_forms' === $resource ) {
					foreach ( $api_response as $mapping ) {
						if ( isset( $mapping['archived'] ) && $mapping['archived'] ) {
							continue;
						}
						$_resource[ $mapping['id'] ] = $mapping['form_id'];
					}
					update_option( 'convertkit_subscription_forms', $_resource );
				} elseif ( 'tags' === $resource ) {
					$response = isset( $api_response['tags'] ) ? $api_response['tags'] : array();
					foreach ( $response as $tag ) {
						$_resource[] = $tag;
					}
					update_option( 'convertkit_tags', $_resource );
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
	}

	/**
	 * Remove subscription from a form.
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
	}

	/**
	 * Get the ConvertKit subscriber ID associated with email address if it exists.
	 * Return 0 if subscriber not found.
	 *
	 * @param string $email_address Subscriber email address.
	 *
	 * @return int $subscriber_id
	 */
	public function get_subscriber_id( $email_address ) {

		$url = add_query_arg(
			array(
				'api_secret'    => WP_ConvertKit::get_api_secret(),
				'email_address' => $email_address,
				'status'        => 'all',
			),
			'https://api.convertkit.com/v3/subscribers'
		);

		WP_ConvertKit::log( 'get_subscriber_id for: ' . $email_address );

		$result = $this->get_resource( $url );

		if ( is_wp_error( $result ) ) {
			WP_ConvertKit::log( 'Error getting resource for: ' . $url . '. Error: ' . $result->get_error_messages() );
			return 0;
		}

		$subs        = json_decode( $result );
		$subscribers = is_array( $subs->subscribers ) ? $subs->subscribers : array();

		if ( $subscribers ) {
			$subscriber = array_pop( $subscribers );

			WP_ConvertKit::log( 'Found ' . count( $subscribers ) . ' subscribers' );
			WP_ConvertKit::log( 'ID (' . $subscriber->id . ') ' . $subscriber->email_address );

			return $subscriber->id;
		}

		WP_ConvertKit::log( 'Subscriber not found with email ' . $email_address );

		// Subscriber not found.
		return 0;
	}

	/**
	 * Get subscriber by ID.
	 *
	 * @param int $subscriber_id Subscriber ID.
	 *
	 * @return int
	 */
	public function get_subscriber( $subscriber_id ) {
		$url = add_query_arg(
			array(
				'api_secret' => WP_ConvertKit::get_api_secret(),
			),
			'https://api.convertkit.com/v3/subscribers/' . $subscriber_id
		);

		WP_ConvertKit::log( 'get_subscriber info for id: ' . $subscriber_id );

		$result = $this->get_resource( $url );

		if ( is_wp_error( $result ) ) {
			WP_ConvertKit::log( 'Error getting resource for: ' . $url . '. Error: ' . $result->get_error_messages() );
			return 0;
		}

		$result = json_decode( $result );

		if ( isset( $result->subscriber ) ) {
			$subscriber = $result->subscriber;

			WP_ConvertKit::log( 'Found: (' . $subscriber->id . ') ' . $subscriber->email_address );

			return $subscriber;
		}

		// Subscriber not found.
		return 0;
	}

	/**
	 * Get a list of the tags for a subscriber.
	 *
	 * @param int $subscriber_id Subscriber ID.
	 *
	 * @return array $subscriber_tags Array of tags for customer with key of tag_id
	 */
	public function get_subscriber_tags( $subscriber_id ) {

		$url = add_query_arg(
			array(
				'api_key' => WP_ConvertKit::get_api_key(),
			),
			'https://api.convertkit.com/v3/subscribers/' . $subscriber_id . '/tags'
		);

		WP_ConvertKit::log( 'get_subscriber_tags for: ' . $subscriber_id );

		$result = $this->get_resource( $url );

		if ( is_wp_error( $result ) ) {
			WP_ConvertKit::log( 'Error getting resource for: ' . $url . '. Error: ' . $result->get_error_messages() );
			return array();
		}

		$result = json_decode( $result );
		$tags   = isset( $result->tags ) ? $result->tags : array();

		if ( empty( $tags ) ) {
			WP_ConvertKit::log( 'No tags found for customer.' );
		} else {
			WP_ConvertKit::log( 'Found ' . count( $tags ) . 'tags for subscriber' );
			$tags = wp_list_pluck( $tags, 'name', 'id' );
		}

		return $tags;

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
			WP_ConvertKit::log( 'API Request (get_resource): ' . $url );

			$response = wp_remote_get(
				$url,
				array(
					'timeout'         => 10,
					'Accept-Encoding' => 'gzip',
					'user-agent'      => convertkit_wp_get_user_agent(),
				)
			);

			if ( ! is_wp_error( $response ) ) {

				/**
				 * Maybe inflate response body.
				 *
				 * @see https://wordpress.stackexchange.com/questions/10088/how-do-i-troubleshoot-responses-with-wp-http-api
				 */
				$inflate = @gzinflate( $response['body'] ); // phpcs:ignore -- @todo Determine better way to do this without suppressing errors and notices.

				if ( $inflate ) {
					$response['body'] = $inflate;
				}

				$body = wp_remote_retrieve_body( $response );

				/**
				 * Parse HTML.
				 *
				 * @var \simple_html_dom\simple_html_dom $html
				 */
				$html = HtmlDomParser::str_get_html( $body );

				foreach ( $html->find( 'a, link' ) as $element ) {
					if ( isset( $element->href ) && strpos( $element->href, 'fonts.googleapis.com' ) === false ) {
						$element->href = RelativeToAbsoluteUrl::urlToAbsolute( $url, $element->href );
					}
				}

				foreach ( $html->find( 'img, script' ) as $element ) {
					if ( isset( $element->src ) ) {
						$element->src = RelativeToAbsoluteUrl::urlToAbsolute( $url, $element->src );
					}
				}

				foreach ( $html->find( 'form' ) as $element ) {
					if ( isset( $element->action ) ) {
						$element->action = RelativeToAbsoluteUrl::urlToAbsolute( $url, $element->action );
					} else {
						$element->action = $url;
					}
				}

				// Check `status_code` for 200, otherwise log error.
				if ( 200 === $response['response']['code'] ) {
					$resource             = $html->save();
					$this->markup[ $url ] = $resource;
				} else {
					WP_ConvertKit::log( 'Status Code (' . $response['response']['code'] . ') for URL (' . $url . '): ' . $html->save() );
				}
			} else {
				WP_ConvertKit::log( sprintf( 'API Response was WP_Error (get_resource): Code: %1$s Message: %2$s', $response->get_error_code(), $response->get_error_message() ) );
			}
		}

		return $resource;
	}

	/**
	 * Do a remote request.
	 *
	 * @param string      $path Part of URL.
	 * @param string|null $api_secret Optional, default null. API secret.
	 *
	 * @return array
	 */
	private function _get_api_response( $path = '', $api_secret = null ) {

		$args = array(
			'api_key' => $this->api_key,
		);

		if ( $api_secret ) {
			$args['api_secret'] = $api_secret;
		}

		$api_path = $this->api_url_base . $this->api_version;
		$url      = add_query_arg( $args, path_join( $api_path, $path ) );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'         => 10,
				'Accept-Encoding' => 'gzip',
				'user-agent'      => convertkit_wp_get_user_agent(),
			)
		);

		if ( is_wp_error( $response ) ) {
			WP_ConvertKit::log( 'Error: ' . $response->get_error_message() );

			return array(
				'error' => $response->get_error_message(),
			);
		} else {

			/**
			 * Maybe inflate response body.
			 *
			 * @see https://wordpress.stackexchange.com/questions/10088/how-do-i-troubleshoot-responses-with-wp-http-api
			 */
			$inflate = @gzinflate( $response['body'] ); // phpcs:ignore -- @todo Determine a way to keep this functionality without suppressing errors and notices.

			if ( $inflate ) {
				$response['body'] = $inflate;
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
		}

		return $data;
	}

	/**
	 * Make a request to the ConvertKit API.
	 *
	 * @param string $request Request string.
	 * @param string $method HTTP Method.
	 * @param array  $args Request arguments.
	 */
	private function make_request( $request, $method, $args = array() ) {

		WP_ConvertKit::log( 'API Request (make_request): ' . $request . ' Args: ' . wp_json_encode( $args ) );

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
			WP_ConvertKit::log( 'API Response (make_request): WPError: ' . $result->get_error_message() );
		} elseif ( isset( $result['response']['code'] ) && '200' === $result['response']['code'] ) {
			if ( isset( $result['body'] ) ) {
				WP_ConvertKit::log( 'API Response (make_request): ' . $result['body'] );
			} else {
				WP_ConvertKit::log( 'API Response (make_request): Response code 200, but body is not set.' );
			}
		} else {
			WP_ConvertKit::log( 'API Response (make_request): Result code: ' . $result['response']['code'] . ' ' . $result['response']['message'] );
		}

	}

}
