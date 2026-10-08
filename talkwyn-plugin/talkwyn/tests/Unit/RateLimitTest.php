<?php
use PHPUnit\Framework\TestCase;

final class RateLimitTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['tw_transients'] = array();
	}
	public function test_limit_per_session_and_ip() {
		$server = array( 'REMOTE_ADDR' => '203.0.113.5' );
		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertTrue( Talkwyn_Rate_Limiter::allow_chat( 'sess-a', 5, false, $server ) );
		}
		$this->assertFalse( Talkwyn_Rate_Limiter::allow_chat( 'sess-a', 5, false, $server ) );
	}
	public function test_new_sessions_cannot_dodge_the_ip_cap() {
		$server  = array( 'REMOTE_ADDR' => '203.0.113.6' );
		$allowed = 0;
		for ( $i = 0; $i < 40; $i++ ) {
			$allowed += Talkwyn_Rate_Limiter::allow_chat( 'sess-' . $i, 5, false, $server ) ? 1 : 0;
		}
		$this->assertSame( 15, $allowed );
	}
	public function test_proxy_headers_only_when_trusted() {
		$server = array( 'REMOTE_ADDR' => '10.0.0.1', 'HTTP_CF_CONNECTING_IP' => '198.51.100.7' );
		$this->assertSame( '10.0.0.1', Talkwyn_Rate_Limiter::client_ip( false, $server ) );
		$this->assertSame( '198.51.100.7', Talkwyn_Rate_Limiter::client_ip( true, $server ) );
		$this->assertSame( '10.0.0.1', Talkwyn_Rate_Limiter::client_ip( true, array( 'REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => 'not-an-ip' ) ) );
	}
}
