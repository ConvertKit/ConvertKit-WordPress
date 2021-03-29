<?php
/**
 * ConvertKit TinyMCE class
 *
 * @since 1.5.0
 * @package ConvertKit
 * @author ConvertKit
 */

/**
 * Class ConvertKit_TinyMCE
 */
class ConvertKit_TinyMCE {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_head', array( $this, 'add_buttons' ), 11 );
	}

	/**
	 * Filters for adding CK button to TinyMCE editor.
	 *
	 * @since 1.5.0
	 */
	public function add_buttons() {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		add_filter( 'mce_external_plugins', array( $this, 'add_tinymce_plugin' ) );
		add_filter( 'mce_buttons', array( $this, 'register_mce_button' ) );

	}

	/**
	 * Register the external plugin
	 *
	 * @since 1.5.0
	 *
	 * @param array $plugins TinyMCE plugins.
	 *
	 * @return mixed
	 */
	public function add_tinymce_plugin( $plugins ) {
		$plugins['convertkit_button'] = CONVERTKIT_PLUGIN_URL . 'resources/backend/tinymce-buttons.js?' . time();

		return $plugins;
	}

	/**
	 * Register the external plugin.
	 *
	 * @since 1.5.0
	 *
	 * @param array $buttons TinyMCE buttons.
	 *
	 * @return array
	 */
	public function register_mce_button( $buttons ) {
		$buttons[] = 'convertkit_button';

		return $buttons;
	}

}

new ConvertKit_TinyMCE();
