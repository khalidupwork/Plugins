<?php
/**
 * @package TalkwynHub
 */

namespace TWH\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TWH\Domain\Domain;

final class DomainTest extends TestCase {

	/**
	 * @dataProvider urls
	 */
	public function test_normalize( string $url, string $expected ): void {
		$this->assertSame( $expected, Domain::normalize( $url ) );
	}

	public static function urls(): array {
		return array(
			'plain'               => array( 'https://example.com', 'example.com' ),
			'www stripped'        => array( 'https://www.Example.COM/', 'example.com' ),
			'no scheme'           => array( 'example.com', 'example.com' ),
			'port stripped'       => array( 'http://example.com:8080/', 'example.com' ),
			'query and fragment'  => array( 'https://example.com/?a=1#x', 'example.com' ),
			'subdirectory'        => array( 'https://example.com/Shop/', 'example.com/shop' ),
			'double slashes'      => array( 'https://example.com//blog//', 'example.com/blog' ),
			'wp-admin stripped'   => array( 'https://example.com/wp-admin/options.php', 'example.com' ),
			'index.php stripped'  => array( 'https://example.com/index.php', 'example.com' ),
			'trailing dot'        => array( 'https://example.com./', 'example.com' ),
			'subdomain kept'      => array( 'https://shop.example.com', 'shop.example.com' ),
			'ipv4'                => array( 'http://192.168.1.10/site', '192.168.1.10/site' ),
			'ipv6'                => array( 'http://[::1]:8080/', '::1' ),
			'empty'               => array( '', '' ),
			'garbage'             => array( 'http://', '' ),
			'invalid characters'  => array( 'http://exa mple.com', '' ),
		);
	}

	/**
	 * @dataProvider dev_hosts
	 */
	public function test_dev_detection( string $domain, bool $is_dev ): void {
		$this->assertSame( $is_dev, Domain::is_dev( $domain ) );
	}

	public static function dev_hosts(): array {
		return array(
			array( 'localhost', true ),
			array( 'localhost/wp', true ),
			array( 'mysite.local', true ),
			array( 'mysite.test', true ),
			array( 'mysite.dev', true ),
			array( 'mysite.example', true ),
			array( 'staging.mysite.com', true ),
			array( 'dev.mysite.com', true ),
			array( 'mysite.wpengine.com', true ),
			array( 'env-123.kinsta.cloud', true ),
			array( 'mysite.flywheelsites.com', true ),
			array( 'abc.instawp.xyz', true ),
			array( '127.0.0.1', true ),
			array( '10.0.0.5/shop', true ),
			array( '::1', true ),
			array( 'example.com', false ),
			array( 'shop.example.com', false ),
			array( 'mylocal.com', false ),
			array( 'developer.com', false ),
			array( 'staging.com', false ),
			array( 'dev.to', false ),
			array( 'staging.mysite.co.uk', true ),
			array( 'wpengine.com', false ),
			array( 'testsite.co.uk', false ),
			array( 'example.com/local', false ),
			array( '', false ),
		);
	}

	public function test_custom_patterns(): void {
		$this->assertTrue( Domain::is_dev( 'qa.client.com', array( 'qa.*' ) ) );
		$this->assertFalse( Domain::is_dev( 'mysite.local', array( 'qa.*' ) ) );
		$this->assertTrue( Domain::is_dev( '8.8.8.8', array() ), 'IP addresses are always dev.' );
	}

	public function test_matches(): void {
		$this->assertTrue( Domain::matches( 'a.b.local', '*.local' ) );
		$this->assertFalse( Domain::matches( 'local', '*.local' ) );
		$this->assertFalse( Domain::matches( 'mysite.local.com', '*.local' ) );
		$this->assertTrue( Domain::matches( 'staging.x.com', 'STAGING.*' ) );
		$this->assertFalse( Domain::matches( 'staging.com', 'staging.*' ), 'Trailing .* needs a full domain.' );
		$this->assertFalse( Domain::matches( 'example.com', '' ) );
		$this->assertFalse( Domain::matches( 'exampleXcom', 'example.com' ), 'Dots are literal.' );
	}

	public function test_parse_patterns(): void {
		$this->assertSame( array( '*.local', 'staging.*', 'qa.x.com' ), Domain::parse_patterns( "*.local\n STAGING.* , qa.x.com\n\n*.local" ) );
	}
}
