<?php
/**
 * @package TalkwynHub
 */

namespace TWH\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TWH\Domain\KeyGenerator;

final class KeyGeneratorTest extends TestCase {

	public function test_format(): void {
		$key = KeyGenerator::generate();
		$this->assertMatchesRegularExpression( '/^TALK-[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $key );
		$this->assertTrue( KeyGenerator::is_valid_format( $key ) );
		$this->assertSame( 24, strlen( $key ) );
	}

	public function test_alphabet_has_no_ambiguous_characters(): void {
		$this->assertSame( 32, strlen( KeyGenerator::ALPHABET ) );
		foreach ( array( '0', 'O', '1', 'I' ) as $char ) {
			$this->assertStringNotContainsString( $char, KeyGenerator::ALPHABET );
		}
		$all = '';
		for ( $i = 0; $i < 500; $i++ ) {
			$all .= substr( KeyGenerator::generate(), 5 );
		}
		$this->assertDoesNotMatchRegularExpression( '/[01OI]/', str_replace( '-', '', $all ) );
	}

	public function test_keys_are_unique_and_use_whole_alphabet(): void {
		$keys = array();
		$seen = array();
		for ( $i = 0; $i < 2000; $i++ ) {
			$key          = KeyGenerator::generate();
			$keys[ $key ] = true;
			foreach ( str_split( str_replace( '-', '', substr( $key, 5 ) ) ) as $c ) {
				$seen[ $c ] = true;
			}
		}
		$this->assertCount( 2000, $keys );
		$this->assertCount( 32, $seen, 'Every alphabet character should appear in 32k random characters.' );
	}

	public function test_normalize_and_hash_are_case_and_space_insensitive(): void {
		$key = KeyGenerator::generate();
		$messy = '  ' . strtolower( substr( $key, 0, 10 ) ) . " \t" . substr( $key, 10 ) . "\n";
		$this->assertSame( $key, KeyGenerator::normalize( $messy ) );
		$this->assertSame( KeyGenerator::hash( $key ), KeyGenerator::hash( $messy ) );
		$this->assertSame( 64, strlen( KeyGenerator::hash( $key ) ) );
	}

	public function test_last4_and_mask(): void {
		$this->assertSame( 'WXYZ', KeyGenerator::last4( 'TALK-ABCD-EFGH-JKLM-WXYZ' ) );
		$this->assertSame( 'TALK-****-****-****-WXYZ', KeyGenerator::mask( 'WXYZ' ) );
	}

	/**
	 * @dataProvider invalid_keys
	 */
	public function test_rejects_invalid_formats( string $key ): void {
		$this->assertFalse( KeyGenerator::is_valid_format( $key ) );
	}

	public static function invalid_keys(): array {
		return array(
			'empty'          => array( '' ),
			'wrong prefix'   => array( 'TALX-ABCD-EFGH-JKLM-NPQR' ),
			'ambiguous zero' => array( 'TALK-AB0D-EFGH-JKLM-NPQR' ),
			'ambiguous O'    => array( 'TALK-ABOD-EFGH-JKLM-NPQR' ),
			'ambiguous 1'    => array( 'TALK-AB1D-EFGH-JKLM-NPQR' ),
			'ambiguous I'    => array( 'TALK-ABID-EFGH-JKLM-NPQR' ),
			'too short'      => array( 'TALK-ABCD-EFGH-JKLM' ),
			'too long'       => array( 'TALK-ABCD-EFGH-JKLM-NPQR-STUV' ),
			'lowercase'      => array( 'talk-abcd-efgh-jkLM-NPQR' ),
			'trailing junk'  => array( 'TALK-ABCD-EFGH-JKLM-NPQR ' ),
		);
	}
}
