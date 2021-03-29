<?php

/**
 * Class ConvertKit_Custom_Content
 *
 * @since 1.5.0
 */
class ConvertKit_Custom_Content {

	/**
	 * Name of the database table to store user visits.
	 *
	 * @var string
	 */
	public $table = 'convertkit_user_history';

	/**
	 * Name of the cookie to store user visits.
	 *
	 * @var string
	 */
	public $cookie;

	/**
	 * Version of the visits database.
	 *
	 * @var string
	 */
	public $version = '1.0.0';

	/**
	 * How long the cookie will last.
	 *
	 * @var int
	 */
	public $cookie_life;

	/**
	 * Custom content settings.
	 *
	 * @var mixed|void
	 */
	protected $options;

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->options = get_option( '_wp_convertkit_integration_custom_content_settings' );

		$this->table       = 'convertkit_user_history';
		$this->cookie      = 'convertkit_history';
		$this->cookie_life = 21 * DAY_IN_SECONDS;

		$this->add_actions();
		$this->register_shortcodes();
	}

	/**
	 * Add actions related to user history.
	 */
	public function add_actions() {

		add_action( 'wp_ajax_nopriv_ck_add_user_visit', array( $this, 'maybe_tag_subscriber_ajax' ) );
		add_action( 'wp_ajax_ck_add_user_visit', array( $this, 'maybe_tag_subscriber_ajax' ) );
		add_action( 'wp_ajax_nopriv_ck_get_subscriber', array( $this, 'get_subscriber' ) );
		add_action( 'wp_ajax_ck_get_subscriber', array( $this, 'get_subscriber' ) );

		if ( ! is_admin() ) {
			add_action( 'the_post', array( $this, 'maybe_tag_subscriber' ), 50 );
		}

	}

	/**
	 * This method is called by a javascript ajax call when a ck_subscriber_id was passed via the query args.
	 *
	 * @since 1.5.5
	 */
	public function maybe_tag_subscriber_ajax() {

		// Get post_id from URL.
		$url     = isset( $_POST['url'] ) ? sanitize_text_field( $_POST['url'] ) : '';
		$post_id = url_to_postid( $url );
		$post    = get_post( $post_id );

		// Set cookie.
		$subscriber_id               = isset( $_POST['subscriber_id'] ) ? sanitize_text_field( $_POST['subscriber_id'] ) : 0;
		$_COOKIE['ck_subscriber_id'] = absint( $subscriber_id );

		if ( isset( $post ) ) {
			$message = ConvertKit_Custom_Content::maybe_tag_subscriber( $post );

			if ( $message ) {
				echo json_encode( $message );
				exit;
			}
		}

		echo json_encode(
			array(
				'subscriber_id' => $subscriber_id,
			)
		);
		exit;
	}

	/**
	 * Get ck_subscriber_id with email via AJAX.
	 */
	public function get_subscriber() {

		$email = isset( $_POST['email'] ) ? sanitize_text_field( $_POST['email'] ) : 0;
		$api   = WP_ConvertKit::get_api();

		WP_ConvertKit::log( 'Trying to get subscriber_id with email: ' . $email );

		$subscriber_id = $api->get_subscriber_id( $email );

		WP_ConvertKit::log( sprintf( 'In get_subscriber. email: %1$s, subscriber_id: %2$s', $email, $subscriber_id ) );

		echo json_encode(
			array(
				'subscriber_id' => $subscriber_id,
			)
		);

		exit;
	}

	/**
	 * Register shortcode.
	 */
	public function register_shortcodes() {
		add_shortcode( 'convertkit_content', array( $this, 'shortcode' ) );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array  $attributes Shortcode attributes.
	 * @param string $content Shortcode content.
	 *
	 * @return mixed|void
	 */
	public static function shortcode( $attributes, $content ) {

		// The 'tag' attribute is required.
		if ( isset( $attributes['tag'] ) ) {
			$tags = array();
			$tag  = $attributes['tag'];
			$api  = WP_ConvertKit::get_api();

			if ( isset( $_COOKIE['ck_subscriber_id'] ) ) {

				WP_ConvertKit::log( 'shortcode: cookie found, calling API' );

				// Get cookie and check API for customer tags.
				$subscriber_id = absint( $_COOKIE['ck_subscriber_id'] );

				if ( $subscriber_id ) {
					$tags = $api->get_subscriber_tags( $subscriber_id );
				}
			} elseif ( isset( $_GET['ck_subscriber_id'] ) ) {

				WP_ConvertKit::log( 'shortcode: URL param found, calling API' );

				// Get cookie and check API for customer tags.
				$subscriber_id = absint( $_GET['ck_subscriber_id'] );

				if ( $subscriber_id ) {
					$tags = $api->get_subscriber_tags( $subscriber_id );
				}
			}

			if ( isset( $tags[ $tag ] ) ) {
				return apply_filters( 'wp_convertkit_shortcode_custom_content', $content, $attributes );
			}
		}
		return null;
	}

	/**
	 * If the user views page with a cookie 'ck_subscriber_id' then check if tags need to be applied based on visit.
	 *
	 * @see https://app.convertkit.com/account/edit#email_settings
	 *
	 * @param WP_Post $post WP_Post object for the post.
	 *
	 * @return string|boolean
	 */
	public static function maybe_tag_subscriber( $post ) {

		if ( isset( $_COOKIE['ck_subscriber_id'] ) && absint( $_COOKIE['ck_subscriber_id'] ) ) {
			$subscriber_id = absint( $_COOKIE['ck_subscriber_id'] );
			$api           = WP_ConvertKit::get_api();
			$meta          = get_post_meta( $post->ID, '_wp_convertkit_post_meta', true );
			$tag           = isset( $meta['tag'] ) ? $meta['tag'] : 0;

			// Get subscriber's email to add tag with.
			$subscriber = $api->get_subscriber( $subscriber_id );

			if ( $subscriber ) {
				// Tag subscriber.
				$args = array(
					'email' => $subscriber->email_address,
				);

				if ( $tag ) {
					$api->add_tag( $tag, $args );
					WP_ConvertKit::log( 'Tagging ' . $subscriber->email_address . ' (' . $subscriber_id . ')' . ' with tag (' . $tag . ')' );
				} else {
					WP_ConvertKit::log( 'post_id (' . $post->ID . ') post does not have tags defined.' );
				}
			} else {

				http_response_code( 404 );

				unset( $_COOKIE['ck_subscriber_id'] );

				return 'Subscriber with ID ' . $subscriber_id . ' not found';
			}
		}

		return false;

	}

	/**
	 * Runs after the customer has been tagged and subscriber_id has been retrieved
	 *
	 * @param string  $user_login User loging name.
	 * @param WP_User $user WP_User object of the user.
	 */
	public function login_action( $user_login, $user ) {

		$user_email = $user->user_email;
		$api        = WP_ConvertKit::get_api();

		WP_ConvertKit::log( '----login_action for user: ' . $user->ID );

		// Get subscriber id from email and cookie.
		$subscriber_id = $api->get_subscriber_id( $user_email );

		if ( $subscriber_id ) {
			update_user_meta( $user->ID, 'convertkit_subscriber_id', $subscriber_id );

			setcookie( 'convertkit_subscriber', $subscriber_id, time() + ( 21 * DAY_IN_SECONDS ), '/' );

			// Get tags and add to user meta.
			$tags = $api->get_subscriber_tags( $subscriber_id );
			update_user_meta( $user->ID, 'convertkit_tags', json_encode( $tags ) );
		}

		if ( isset( $_COOKIE['ck_visit'] ) ) {
			$user_cookie = sanitize_text_field( $_COOKIE['ck_visit'] );
			$this->associate_history_with_user( $user_cookie, $subscriber_id, $user->ID );
		}

		if ( $subscriber_id ) {
			$this->process_history( $subscriber_id, $user->ID, $user->user_email );
		}

		$tags = $api->get_subscriber_tags( $subscriber_id );

		update_user_meta( $user->ID, 'convertkit_tags', json_encode( $tags ) );
	}

	/**
	 * Associates history of viewing custom content with a specified user.
	 *
	 * @param string $cookie Visitory cookie name.
	 * @param int    $subscriber_id Subscriber ID.
	 * @param int    $user_id User ID.
	 */
	public function associate_history_with_user( $cookie, $subscriber_id = 0, $user_id = 0 ) {

		if ( $user_id ) {
			$this->update( 'user_id', strval( $user_id ), 'visitor_cookie', $cookie );
		}

		if ( $subscriber_id ) {
			$this->update( 'subscriber_id', strval( $subscriber_id ), 'visitor_cookie', $cookie );
		}
	}

	/**
	 * Remove rows in the convertkit_user_history table older than the Expire setting.
	 */
	public function remove_expired_rows() {
		$expire_months = isset( $this->options['expire'] ) ? absint( $this->options['expire'] ) : 4;
		$expire_range  = apply_filters( 'convertkit_user_history_expire', $expire_months );
		$expire_date   = date( 'Y-m-d H:i:s', strtotime( '-' . $expire_range . ' months' ) ); // phpcs:ignore -- Okay use of date() function.
		$rows          = $this->delete( 'date', $expire_date, '<' );
	}

	/**
	 * Get IP of visitor.
	 *
	 * @see https://stackoverflow.com/questions/13646690/how-to-get-real-ip-from-visitor
	 *
	 * @return mixed
	 */
	public function get_user_ip() {
		$client  = @$_SERVER['HTTP_CLIENT_IP']; // phpcs:ignore -- @todo Determine better way to get IP without suppressing errors.
		$forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];  // phpcs:ignore -- @todo Determine better way to get IP without suppressing errors.
		$remote  = $_SERVER['REMOTE_ADDR'];

		if ( filter_var( $client, FILTER_VALIDATE_IP ) ) {
			$ip = $client;
		} elseif ( filter_var( $forward, FILTER_VALIDATE_IP ) ) {
			$ip = $forward;
		} else {
			$ip = $remote;
		}

		return $ip;
	}

	/**
	 * Process the rows collected in the history table
	 *
	 * @param int    $subscriber_id Subscriber ID.
	 * @param int    $user_id User ID.
	 * @param string $user_email User email address.
	 */
	public function process_history( $subscriber_id = 0, $user_id = 0, $user_email ) {

		// @todo This needs to work with batch processing for larger user history.
		$user_rows = array();
		$sub_rows  = array();

		// Get all rows.
		if ( $user_id ) {
			$user_rows = $this->get( 'user_id', $user_id, '=' );
		}

		if ( $subscriber_id ) {
			$sub_rows = $this->get( 'subscriber_id', $subscriber_id, '=' );
		}

		// Get unique URLs visited.
		$visits = array_merge( $user_rows, $sub_rows );
		$urls   = wp_list_pluck( $visits, 'url' );
		$urls   = array_unique( $urls );

		// Get post IDs.
		$post_ids = $this->get_post_ids_from_url( $urls );

		// For each matching post_id tag customer.
		$api  = WP_ConvertKit::get_api(); // @todo Remove when there is a general logger for plugin.
		$args = array(
			'email' => $user_email,
		);

		foreach ( $post_ids as $post_id ) {
			$meta = get_post_meta( $post_id, '_wp_convertkit_post_meta', true );
			$tag  = isset( $meta['tag'] ) ? $meta['tag'] : 0;

			if ( $tag ) {
				$api->add_tag( $tag, $args );
				WP_ConvertKit::log( 'tagging user (' . $user_id . ')' . ' with tag (' . $tag . ')' );
			} else {
				WP_ConvertKit::log( 'post_id (' . $post_id . ') not found in user history' );
			}
		}

		// Delete all rows.
		$this->delete( 'user_id', $user_id, '=' );
		$this->delete( 'subscriber_id', $subscriber_id, '=' );
	}

	/**
	 * Get post_id from a URL.
	 *
	 * @param array $urls Array of WordPress post URLs.
	 * @return array
	 */
	public function get_post_ids_from_url( $urls ) {
		$ids = array();

		foreach ( $urls as $url ) {
			$post_id = url_to_postid( $url );
			if ( $post_id ) {
				$ids[] = $post_id;
			}
		}

		return $ids;
	}

	/**
	 * Database Helper functions
	 */

	/**
	 * Insert table row.
	 *
	 * @param mixed $data Data describing visitor viewing custom content.
	 */
	private function insert( $data ) {
		global $wpdb;

		do_action( 'ck_data_before_insert', $data );

		$table_columns = array(
			'visitor_cookie' => '%s',
			'user_id'        => '%d',
			'url'            => '%s',
			'ip'             => '%s',
			'date'           => '%s',
		);

		$wpdb->insert( $wpdb->prefix . $this->table, $data, $table_columns );

		do_action( 'ck_data_after_insert', $data );
	}

	/**
	 * Get rows from the database.
	 *
	 * @param string $field Database field.
	 * @param mixed  $value Database value.
	 * @param string $operator Database query operator.
	 *
	 * @return false|int
	 */
	private function get( $field, $value, $operator ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'convertkit_user_history';
		$sql        = 'SELECT * from ' . $table_name . ' WHERE ' . $field . ' ' . $operator . ' \'' . $value . '\'';
		$rows       = $wpdb->get_results( $sql ); // phpcs:ignore -- @todo Replace with query that uses $wpdb->prepare().

		return $rows;
	}

	/**
	 * Update table row.
	 *
	 * @param string $column DB query column.
	 * @param string $value DB query value.
	 * @param string $compare DB query comparison operator.
	 * @param string $compare_value DB query comparison value.
	 *
	 * @return int
	 */
	private function update( $column, $value, $compare, $compare_value ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'convertkit_user_history';

		$data = array(
			$column => $value,
		);

		$where = array(
			$compare => $compare_value,
		);

		$rows = $wpdb->update( $table_name, $data, $where );

		return $rows;
	}

	/**
	 * Delete table row.
	 *
	 * @param string $column DB query column.
	 * @param string $value DB query value.
	 * @param string $operator DB query operator.
	 *
	 * @return false|int
	 */
	private function delete( $column, $value, $operator ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'convertkit_user_history';
		$sql        = 'DELETE from ' . $table_name . ' WHERE ' . $column . ' ' . $operator . ' \'' . $value . '\'';
		$rows       = $wpdb->query( $sql ); // phpcs:ignore -- @todo Replace with query that uses $wpdb->prepare().

		return $rows;
	}

	/**
	 * Creates the table to track visits.
	 *
	 * @access public
	 *
	 * @see dbDelta()
	 */
	static function create_table() {
		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$table_name = $wpdb->prefix . 'convertkit_user_history';

		$sql = 'CREATE TABLE ' . $table_name . ' (
			visit_id bigint(20) NOT NULL AUTO_INCREMENT,
			visitor_cookie mediumtext NOT NULL,
			user_id bigint(20) NOT NULL,
			subscriber_id bigint(20) NOT NULL,
			url mediumtext NOT NULL,
			ip tinytext NOT NULL,
			date datetime NOT NULL,
			PRIMARY KEY  (visit_id)
			) CHARACTER SET utf8 COLLATE utf8_general_ci;';

		dbDelta( $sql );

		update_option( 'convertkit_user_history_table', '1.0.0' );
	}

}

new ConvertKit_Custom_Content();
