<?php
/**
 * @package TalkwynHub
 */

namespace TWH\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TWH\Domain\DownloadToken;

final class DownloadTokenTest extends TestCase {

	private const SECRET = 'test-secret-0123456789abcdef0123';
	private const NOW    = 1791331200;

	public function test_valid_token(): void {
		$token  = DownloadToken::create( 42, 7, self::SECRET, self::NOW );
		$claims = DownloadToken::validate( $token, self::SECRET, self::NOW + 60 );
		$this->assertSame( 42, $claims['license_id'] );
		$this->assertSame( 7, $claims['release_id'] );
		$this->assertSame( self::NOW + 600, $claims['expires'] );
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $token, 'URL-safe.' );
	}

	public function test_expires_after_ten_minutes(): void {
		$token = DownloadToken::create( 42, 7, self::SECRET, self::NOW );
		$this->assertNotNull( DownloadToken::validate( $token, self::SECRET, self::NOW + 600 ) );
		$this->assertNull( DownloadToken::validate( $token, self::SECRET, self::NOW + 601 ) );
	}

	public function test_wrong_secret_fails(): void {
		$token = DownloadToken::create( 42, 7, self::SECRET, self::NOW );
		$this->assertNull( DownloadToken::validate( $token, 'other-secret', self::NOW ) );
	}

	public function test_tampered_payload_fails(): void {
		$token         = DownloadToken::create( 42, 7, self::SECRET, self::NOW );
		list( $b, $m ) = explode( '.', $token );
		$payload       = json_decode( DownloadToken::b64url_decode( $b ), true );
		$payload['r']  = 8; // Try another release.
		$forged        = DownloadToken::b64url_encode( json_encode( $payload ) ) . '.' . $m;
		$this->assertNull( DownloadToken::validate( $forged, self::SECRET, self::NOW ) );
	}

	public function test_wrong_purpose_fails_even_if_signed(): void {
		$body  = DownloadToken::b64url_encode( json_encode( array( 'p' => 'other', 'l' => 1, 'r' => 1, 'e' => self::NOW + 600, 'n' => 'x' ) ) );
		$token = $body . '.' . DownloadToken::b64url_encode( hash_hmac( 'sha256', $body, self::SECRET, true ) );
		$this->assertNull( DownloadToken::validate( $token, self::SECRET, self::NOW ) );
	}

	public function test_type_juggling_rejected(): void {
		$body  = DownloadToken::b64url_encode( json_encode( array( 'p' => 'dl', 'l' => '1', 'r' => 1, 'e' => self::NOW + 600, 'n' => 'x' ) ) );
		$token = $body . '.' . DownloadToken::b64url_encode( hash_hmac( 'sha256', $body, self::SECRET, true ) );
		$this->assertNull( DownloadToken::validate( $token, self::SECRET, self::NOW ) );
	}

	/**
	 * @dataProvider malformed
	 */
	public function test_malformed( string $token ): void {
		$this->assertNull( DownloadToken::validate( $token, self::SECRET, self::NOW ) );
	}

	public static function malformed(): array {
		return array(
			array( '' ),
			array( 'abc' ),
			array( 'a.b.c' ),
			array( '.' ),
			array( str_repeat( 'a', 600 ) . '.b' ),
		);
	}

	public function test_tokens_are_unique(): void {
		$this->assertNotSame( DownloadToken::create( 1, 1, self::SECRET, self::NOW ), DownloadToken::create( 1, 1, self::SECRET, self::NOW ) );
	}
}
