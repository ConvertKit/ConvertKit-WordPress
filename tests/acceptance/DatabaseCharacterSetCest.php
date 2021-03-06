<?php

/**
 * Class SignInCest
 */
class DatabaseCharacterSetCest {
	/**
	 * @param AcceptanceTester $I
	 *
	 * @throws \Codeception\Exception\ModuleException
	 */
	public function _before( AcceptanceTester $I ) {

		$this->ck_api_key    = getenv( 'CK_API_KEY' );
		$this->ck_api_secret = getenv( 'CK_API_SECRET' );

		$I->loginAsAdmin();
	}

	// tests

	/**
	 * @param AcceptanceTester $I
	 */
	public function testSettingsPage( AcceptanceTester $I ) {

		$charset = $this->_optionValueCharacterSet();
		$this->_debug($charset);

		$this->_makeDatabaseUtf8();

		$charset = $this->_optionValueCharacterSet();
		$this->_debug($charset);


		$charset = $this->_optionValueCharacterSet();
		$this->_debug($charset);

		$I->amOnPage( '/wp-admin/options-general.php?page=_wp_convertkit_settings' );



//		$I->fillField( "#api_key", $this->ck_api_key );
//		$I->fillField( "#api_secret", $this->ck_api_secret );
//		$I->seeElement( 'option', [ 'value' => 'default' ] );
//		$I->seeElement( 'option', [ 'value' => '820085' ] );
//
//		$I->click( '[href="?page=_wp_convertkit_settings&amp;tab=contactform7"]' );
//		$I->see( 'ConvertKit Form' );
//		$I->seeElement( 'option', [ 'value' => 'default' ] );
//		$I->seeElement( 'option', [ 'value' => '820085' ] );
//
//		$I->selectOption( 'form select[id=_wp_convertkit_integration_contactform7_settings_5]', 'Clean form' );
//		$I->click( '#submit' );
//
//		$I->seeOptionIsSelected( 'form select[id=_wp_convertkit_integration_contactform7_settings_5]', 'Clean form' );
	}

	/**
	 * @return bool|false|int
	 */
	public function _makeDatabaseUtf8() {
		global $wpdb;
		$table = $wpdb->prefix . 'options';
		return $wpdb->query( "ALTER TABLE $table CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci" );
	}

	/**
	 * @return bool|false|int
	 */
	public function _makeDatabaseUtf8mb4() {
		global $wpdb;
		$table = $wpdb->prefix . 'options';
		return $wpdb->query( "ALTER TABLE $table CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" );
	}

	public function _optionValueCharacterSet() {
		global $wpdb;

		$charset = $wpdb->get_results( "SELECT CHARACTER_SET_NAME FROM information_schema.columns WHERE TABLE_SCHEMA = 'local' AND TABLE_NAME = 'wp_options' AND COLUMN_NAME = 'option_value'");

		return $charset;
	}

	public function _debug( $string = '' ) {
		codecept_debug('####################################');
		codecept_debug('####################################');
		codecept_debug('');

		codecept_debug($string);

		codecept_debug('');
		codecept_debug('####################################');
		codecept_debug('####################################');
	}

}