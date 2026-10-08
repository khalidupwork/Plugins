<?php
use PHPUnit\Framework\TestCase;

final class LicenseClientTest extends TestCase {
	private $pair;

	protected function setUp(): void {
		$this->pair                    = sodium_crypto_sign_keypair();
		$GLOBALS['tw_options']         = array();
	}

	private function client( array $keys = null ) {
		return new Talkwyn_License_Client(
			array(
				'plugin_file' => '/x/talkwyn-pro/talkwyn-pro.php',
				'public_keys' => $keys ?? array( base64_encode( sodium_crypto_sign_publickey( $this->pair ) ) ),
			)
		);
	}

	private function sign( array $data ) {
		return base64_encode( sodium_crypto_sign_detached( Talkwyn_License_Client::canonical_json( $data ), sodium_crypto_sign_secretkey( $this->pair ) ) );
	}

	public function test_valid_signature() {
		$data = array( 'status' => 'active', 'nonce' => 'abc', 'plan' => 'business', 'features' => array( 'pro' ) );
		$this->assertTrue( $this->client()->verify( $data, $this->sign( $data ) ) );
	}

	public function test_tampered_or_foreign_key_fails() {
		$data = array( 'status' => 'active', 'nonce' => 'abc' );
		$sig  = $this->sign( $data );
		$this->assertFalse( $this->client()->verify( array( 'status' => 'expired', 'nonce' => 'abc' ), $sig ) );
		$other = sodium_crypto_sign_keypair();
		$this->assertFalse( $this->client( array( base64_encode( sodium_crypto_sign_publickey( $other ) ) ) )->verify( $data, $sig ) );
		$this->assertFalse( $this->client()->verify( $data, 'not-base64' ) );
	}

	public function test_key_order_does_not_matter() {
		$this->assertSame( Talkwyn_License_Client::canonical_json( array( 'b' => 1, 'a' => array( 'd' => 2, 'c' => 3 ) ) ), Talkwyn_License_Client::canonical_json( array( 'a' => array( 'c' => 3, 'd' => 2 ), 'b' => 1 ) ) );
	}

	private function state( array $state ) {
		$GLOBALS['tw_options']['talkwyn_license_key']   = 'TALK-AAAA-BBBB-CCCC-DDDD';
		$GLOBALS['tw_options']['talkwyn_license_state'] = $state;
	}

	public function test_active_within_grace_period() {
		$this->state( array( 'status' => 'active', 'site_active' => true, 'last_check' => time() - 6 * DAY_IN_SECONDS ) );
		$this->assertTrue( $this->client()->is_pro() );
	}

	public function test_off_after_grace_period() {
		$this->state( array( 'status' => 'active', 'site_active' => true, 'last_check' => time() - 8 * DAY_IN_SECONDS ) );
		$this->assertFalse( $this->client()->is_pro() );
	}

	public function test_expired_and_site_deactivated() {
		$this->state( array( 'status' => 'active', 'site_active' => true, 'last_check' => time(), 'expires_at' => gmdate( 'c', time() - DAY_IN_SECONDS ) ) );
		$this->assertFalse( $this->client()->is_pro() );
		$this->state( array( 'status' => 'active', 'site_active' => false, 'last_check' => time() ) );
		$this->assertFalse( $this->client()->is_pro() );
	}

	public function test_trial_fields() {
		$this->state( array( 'status' => 'active', 'site_active' => true, 'last_check' => time(), 'is_trial' => true, 'trial_ends_at' => gmdate( 'c', time() + 3 * DAY_IN_SECONDS - 60 ) ) );
		$c = $this->client();
		$this->assertTrue( $c->is_trial() );
		$this->assertSame( 3, $c->trial_days_left() );
		$this->state( array( 'status' => 'expired', 'trial_ended' => true ) );
		$this->assertFalse( $this->client()->is_pro() );
		$this->assertTrue( $this->client()->trial_ended() );
	}
}
