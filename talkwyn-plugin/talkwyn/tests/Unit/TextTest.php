<?php
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase {
	public function test_chunks_overlap_and_cover_text() {
		$text   = '';
		for ( $i = 0; $i < 200; $i++ ) {
			$text .= 'Sentence ' . $i . ' about pricing and plans. ';
		}
		$chunks = Talkwyn_Text::chunk( $text, 500, 80 );
		$this->assertGreaterThan( 10, count( $chunks ) );
		foreach ( $chunks as $c ) {
			$this->assertLessThanOrEqual( 500, Talkwyn_Text::len( $c ) );
		}
	}
	public function test_short_text_single_chunk() {
		$this->assertSame( array( 'Hi' ), Talkwyn_Text::chunk( 'Hi' ) );
		$this->assertSame( array(), Talkwyn_Text::chunk( '  ' ) );
	}
	public function test_settings_sanitize_skips_unknown_and_arrays() {
		$out = Talkwyn_Settings::sanitize( array( 'bot_name' => '<b>Ava</b>', 'evil' => 'x', 'chat_width' => 5000, 'brand_color' => 'nope', 'welcome_message' => array( 'x' ) ), array( 'enabled' ) );
		$this->assertSame( 'Ava', $out['bot_name'] );
		$this->assertArrayNotHasKey( 'evil', $out );
		$this->assertSame( 720, $out['chat_width'] );
		$this->assertSame( '#D7263D', $out['brand_color'] );
		$this->assertSame( 0, $out['enabled'] );
		$this->assertArrayNotHasKey( 'welcome_message', $out );
	}
}
