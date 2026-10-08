<?php
use PHPUnit\Framework\TestCase;

final class ContrastTest extends TestCase {
	public function test_ratio_black_white_is_21() {
		$this->assertEqualsWithDelta( 21.0, Talkwyn_Contrast::ratio( '#000000', '#FFFFFF' ), 0.01 );
	}
	public function test_brand_red_uses_white_text() {
		$this->assertSame( '#FFFFFF', Talkwyn_Contrast::text_on( '#D7263D' ) );
		$this->assertTrue( Talkwyn_Contrast::is_readable( '#D7263D' ) );
	}
	public function test_light_colour_uses_ink_text() {
		$this->assertSame( Talkwyn_Contrast::INK, Talkwyn_Contrast::text_on( '#FFE066' ) );
	}
	public function test_mid_grey_is_flagged() {
		$this->assertFalse( Talkwyn_Contrast::is_readable( '#797979' ) );
	}
	public function test_short_hex_and_invalid() {
		$this->assertSame( array( 255, 255, 255 ), Talkwyn_Contrast::rgb( '#fff' ) );
		$this->assertNull( Talkwyn_Contrast::rgb( 'red' ) );
	}
}
