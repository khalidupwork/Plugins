<?php
use PHPUnit\Framework\TestCase;

final class ProvidersTest extends TestCase {
	private $calls = array();

	protected function setUp(): void {
		$GLOBALS['tw_transients'] = array();
		$this->calls              = array();
	}

	private function registry( array $behaviour ) {
		$out = array();
		foreach ( $behaviour as $id => $b ) {
			$out[ $id ] = array(
				'ready' => static function () use ( $b ) {
					return 'nokey' !== $b;
				},
				'call'  => function () use ( $id, $b ) {
					$this->calls[] = $id;
					if ( 'fail' === $b ) {
						throw new Exception( 'quota' );
					}
					return 'ok' === $b ? 'Answer from ' . $id : '';
				},
			);
		}
		return $out;
	}

	public function test_falls_back_to_next_provider() {
		$r = Talkwyn_Providers::generate( array(), array( 'provider_order' => 'a,b,c' ), $this->registry( array( 'a' => 'fail', 'b' => 'empty', 'c' => 'ok' ) ) );
		$this->assertSame( 'c', $r['provider'] );
		$this->assertSame( array( 'a', 'b', 'c' ), $this->calls );
		$this->assertStringContainsString( 'a: quota', $r['error'] );
	}

	public function test_remembers_last_good_provider() {
		$reg = $this->registry( array( 'a' => 'fail', 'b' => 'ok' ) );
		Talkwyn_Providers::generate( array(), array( 'provider_order' => 'a,b' ), $reg );
		$this->calls = array();
		Talkwyn_Providers::generate( array(), array( 'provider_order' => 'a,b' ), $reg );
		$this->assertSame( array( 'b' ), $this->calls );
	}

	public function test_skips_providers_without_keys() {
		$r = Talkwyn_Providers::generate( array(), array( 'provider_order' => 'a,b' ), $this->registry( array( 'a' => 'nokey', 'b' => 'ok' ) ) );
		$this->assertSame( array( 'b' ), $this->calls );
		$this->assertSame( 'b', $r['provider'] );
	}

	public function test_all_fail_returns_empty() {
		$r = Talkwyn_Providers::generate( array(), array( 'provider_order' => 'a' ), $this->registry( array( 'a' => 'fail' ) ) );
		$this->assertSame( '', $r['text'] );
	}
}
