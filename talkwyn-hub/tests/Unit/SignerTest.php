<?php
/**
 * @package TalkwynHub
 */

namespace TWH\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TWH\Domain\Signer;

final class SignerTest extends TestCase {

	private static array $pair;

	public static function setUpBeforeClass(): void {
		self::$pair = Signer::generate_keypair();
	}

	private static function data(): array {
		return array(
			'status'           => 'active',
			'plan'             => 'business',
			'expires_at'       => '2027-10-07T00:00:00Z',
			'activations_used' => 2,
			'activation_limit' => 5,
			'is_dev_site'      => false,
			'features'         => array( 'pro', 'white_label' ),
			'server_time'      => 1791331200,
			'nonce'            => 'abc123def456',
			'url'              => 'https://talkwyn.com/wp-json/x?y=ü',
		);
	}

	public function test_sign_and_verify(): void {
		$sig = Signer::sign( self::data(), self::$pair['secret'] );
		$this->assertSame( 64, strlen( base64_decode( $sig ) ) );
		$this->assertTrue( Signer::verify( self::data(), $sig, self::$pair['public'] ) );
	}

	public function test_tampered_data_fails(): void {
		$sig          = Signer::sign( self::data(), self::$pair['secret'] );
		$data         = self::data();
		$data['plan'] = 'agency';
		$this->assertFalse( Signer::verify( $data, $sig, self::$pair['public'] ) );
		$data           = self::data();
		$data['server_time']++;
		$this->assertFalse( Signer::verify( $data, $sig, self::$pair['public'] ) );
	}

	public function test_wrong_key_fails(): void {
		$sig   = Signer::sign( self::data(), self::$pair['secret'] );
		$other = Signer::generate_keypair();
		$this->assertFalse( Signer::verify( self::data(), $sig, $other['public'] ) );
	}

	public function test_garbage_signature_or_key_fails_safely(): void {
		$this->assertFalse( Signer::verify( self::data(), 'not-base64!!', self::$pair['public'] ) );
		$this->assertFalse( Signer::verify( self::data(), base64_encode( 'short' ), self::$pair['public'] ) );
		$this->assertFalse( Signer::verify( self::data(), Signer::sign( self::data(), self::$pair['secret'] ), base64_encode( 'short' ) ) );
	}

	public function test_canonical_json_is_key_order_independent(): void {
		$a = array( 'b' => 1, 'a' => array( 'y' => true, 'x' => null ), 'list' => array( 3, 1, 2 ) );
		$b = array( 'list' => array( 3, 1, 2 ), 'a' => array( 'x' => null, 'y' => true ), 'b' => 1 );
		$this->assertSame( Signer::canonical_json( $a ), Signer::canonical_json( $b ) );
		$this->assertSame( '{"a":{"x":null,"y":true},"b":1,"list":[3,1,2]}', Signer::canonical_json( $a ) );
	}

	public function test_canonical_json_unescaped_and_objects(): void {
		$this->assertSame( '{"u":"https://x.y/ä"}', Signer::canonical_json( array( 'u' => 'https://x.y/ä' ) ) );
		// Empty objects and empty arrays canonicalize identically (both decode to []).
		$this->assertSame( Signer::canonical_json( array( 'icons' => new \stdClass() ) ), Signer::canonical_json( array( 'icons' => array() ) ) );
	}

	public function test_signature_survives_json_transport(): void {
		$data = self::data();
		$sig  = Signer::sign( $data, self::$pair['secret'] );
		// What the client sees after json_encode (server) + json_decode (client).
		$wire    = json_encode( array( 'data' => $data, 'signature' => $sig ) );
		$decoded = json_decode( $wire, true );
		$this->assertTrue( Signer::verify( $decoded['data'], $decoded['signature'], self::$pair['public'] ) );
	}

	public function test_public_from_secret(): void {
		$this->assertSame( self::$pair['public'], Signer::public_from_secret( self::$pair['secret'] ) );
	}

	public function test_client_sdk_verifies_hub_signature(): void {
		$data = self::data();
		$sig  = Signer::sign( $data, self::$pair['secret'] );

		$this->assertSame( Signer::canonical_json( $data ), \Talkwyn_License_Client::canonical_json( $data ) );

		$client = ( new \ReflectionClass( \Talkwyn_License_Client::class ) )->newInstanceWithoutConstructor();
		$prop   = new \ReflectionProperty( \Talkwyn_License_Client::class, 'cfg' );
		$prop->setAccessible( true );
		$other = Signer::generate_keypair();
		$prop->setValue( $client, array( 'public_keys' => array( 'old' => $other['public'], 'new' => self::$pair['public'] ) ) );

		$decoded = json_decode( json_encode( $data ), true );
		$this->assertTrue( $client->verify( $decoded, $sig ), 'Any trusted key may verify (rotation).' );
		$decoded['status'] = 'expired';
		$this->assertFalse( $client->verify( $decoded, $sig ) );
	}
}
