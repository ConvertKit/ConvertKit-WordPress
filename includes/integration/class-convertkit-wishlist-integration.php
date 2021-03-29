<?php
/**
 * WishList Member Integration
 *
 * @package ConvertKit
 * @author ConvertKit
 */

/**
 * API class require.
 */
require_once CONVERTKIT_PLUGIN_PATH . '/includes/class-convertkit-api.php';

/**
 * Class ConvertKit_Wishlist_Integration
 */
class ConvertKit_Wishlist_Integration {

	/**
	 * API instance
	 *
	 * @var ConvertKit_API
	 */
	protected $api;

	/**
	 * Integration options
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

		// When a user has levels added or registers check for a mapping to a ConvertKit form.
		add_action( 'wishlistmember_add_user_levels', array( $this, 'add_user_levels' ), 10, 2 );

		// When a user has levels removed check for a mapping to a ConvertKit tag, or if the subscriber should be removed from ConvertKit.
		add_action( 'wishlistmember_remove_user_levels', array( $this, 'remove_user_levels' ), 10, 2 );
	}

	/**
	 * Callback function for wishlistmember_add_user_levels action
	 *
	 * @param string $member_id ID for member that has just had levels added.
	 * @param array  $levels Levels to which member was added.
	 */
	public function add_user_levels( $member_id, $levels ) {
		$member = $this->get_member( $member_id );

		foreach ( $levels as $wlm_level_id ) {
			if ( ! isset( $this->options[ $wlm_level_id . '_form' ] ) ) {
				continue;
			}

			$this->member_resource_subscribe(
				$member,
				$this->options[ $wlm_level_id . '_form' ]
			);
		}
	}

	/**
	 * Note: Form level unsubscribe is not available in v3 of the API.
	 *
	 * Callback function for wishlistmember_remove_user_levels action
	 *
	 * @param  string $member_id ID for member that has just had levels removed.
	 * @param  array  $levels Levels from which member was removed.
	 */
	public function remove_user_levels( $member_id, $levels ) {

		$member = $this->get_member( $member_id );

		foreach ( $levels as $wlm_level_id ) {
			// Get the mapping if it is set.
			$unsubscribe = ( isset( $this->options[ $wlm_level_id . '_unsubscribe' ] ) ) ? $this->options[ $wlm_level_id . '_unsubscribe' ] : 0;

			if ( $unsubscribe && 'unsubscribe' === $unsubscribe ) {
				// If mapping is set to "Unsubscribe from all".
				$this->member_resource_unsubscribe( $member );
			} elseif ( $unsubscribe ) {
				// If mapping is a positive integer then tag customer.
				$this->member_tag( $member, $this->options[ $wlm_level_id . '_unsubscribe' ] );
			}
		}

	}

	/**
	 * Subscribes a member to a ConvertKit resource
	 *
	 * @param  array  $member  UserInfo from WishList Member.
	 * @param  string $form_id ConvertKit form id.
	 * @return object Response object from API
	 */
	public function member_resource_subscribe( $member, $form_id ) {

		// Check for temp email.
		if ( preg_match( '/temp_[a-f0-9]{32}/', $member['user_email'] ) ) {
			$email = $member['wlm_origemail'];
		} else {
			$email = $member['user_email'];
		}

		// Note Wishlist Member combines first and last name into 'display_name'.
		return $this->api->form_subscribe(
			$form_id,
			array(
				'email' => $email,
				'name'  => $member['display_name'],
			)
		);
	}

	/**
	 * Unsubscribes a member from a ConvertKit resource
	 *
	 * @param array $member UserInfo from WishList Member.
	 *
	 * @return object Response object from API
	 */
	public function member_resource_unsubscribe( $member ) {
		return $this->api->form_unsubscribe(
			array(
				'email' => $member['user_email'],
			)
		);
	}

	/**
	 * Tag a member
	 *
	 * @param  array  $member  UserInfo from WishList Member.
	 * @param  string $tag     ConvertKit Tag ID.
	 *
	 * @return object          Response object from API.
	 */
	public function member_tag( $member, $tag ) {
		return $this->api->add_tag(
			$tag,
			array(
				'email' => $member['user_email'],
			)
		);
	}

	/**
	 * Gets a WLM member using the wlmapi functions
	 *
	 * @param  string $id The member id.
	 * @return array The member fields from the API request
	 */
	public function get_member( $id ) {
		if ( ! function_exists( 'wlmapi_get_member' ) ) {
			return false;
		}

		$wlm_get_member = wlmapi_get_member( $id );

		if ( 0 === $wlm_get_member['success'] ) {
			return false;
		}

		return $wlm_get_member['member'][0]['UserInfo'];
	}

}

new ConvertKit_Wishlist_Integration();
