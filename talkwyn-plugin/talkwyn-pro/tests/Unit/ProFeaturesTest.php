<?php
use PHPUnit\Framework\TestCase;
use TalkwynPro\Alerts;
use TalkwynPro\Engage;
use TalkwynPro\Extract;
use TalkwynPro\Knowledge;
use TalkwynPro\Search;
use TalkwynPro\Stream;
use TalkwynPro\Woo;

final class ProFeaturesTest extends TestCase {

	public function test_hybrid_merges_keyword_and_meaning() {
		$keyword = array(
			array( 'id' => 1, 'score' => 30, 'source_key' => 'post:1', 'title' => 'Pricing' ),
			array( 'id' => 2, 'score' => 10, 'source_key' => 'post:2', 'title' => 'Blog' ),
		);
		$sims    = array( 3 => 0.82, 2 => 0.75, 1 => 0.2, 4 => 0.1 );
		$rows    = array( 3 => array( 'id' => 3, 'source_key' => 'post:3', 'title' => 'Refunds' ), 4 => array( 'id' => 4, 'source_key' => 'post:4' ) );
		$out     = Search::hybrid( $keyword, $sims, $rows, 0.6, 3 );
		// 2: 0.6*0.75 + 0.4*(10/30) = 0.58; 1: 0.6*0.2 + 0.4 = 0.52; 3: 0.6*0.82 = 0.49.
		$this->assertSame( array( 2, 1, 3 ), array_column( $out, 'id' ) );
		$this->assertNotContains( 4, array_column( $out, 'id' ), 'Weak semantic-only hits are dropped' );
	}

	public function test_vectors_round_trip() {
		$v = Search::normalize( array( 3.0, 4.0 ) );
		$this->assertEqualsWithDelta( 1.0, Search::dot( $v, $v ), 1e-6 );
		$this->assertEqualsWithDelta( 0.6, Search::unpack( Search::pack( $v ) )[0], 1e-6 );
	}

	public function test_custom_answer_matching() {
		$this->assertSame( 1.0, Knowledge::match_score( 'Do you offer refunds?', 'do you offer refunds' ) );
		$this->assertGreaterThanOrEqual( Knowledge::MATCH_MIN, Knowledge::match_score( 'Can I get my money back', 'Do you offer refunds?', 'money back' ) );
		$this->assertLessThan( Knowledge::MATCH_MIN, Knowledge::match_score( 'How do I install it?', 'Do you offer refunds?' ) );
	}

	public function test_docx_xml() {
		$xml = '<w:document><w:body><w:p><w:r><w:t>Opening hours</w:t></w:r></w:p><w:p><w:r><w:t>Mon</w:t></w:r><w:tab/><w:r><w:t>9 &amp; 5</w:t></w:r></w:p></w:body></w:document>';
		$this->assertSame( "Opening hours\nMon\t9 & 5", Extract::docx_xml( $xml ) );
	}

	public function test_pdf_text_operators() {
		$stream = "BT /F1 12 Tf 72 712 Td (Refunds within 14 days) Tj 0 -14 Td [(Ship) -300 (ping is free)] TJ ET";
		$pdf    = "%PDF-1.4\n1 0 obj << /Length " . strlen( $stream ) . " >> stream\n" . $stream . "\nendstream endobj";
		$text   = Extract::pdf( $pdf );
		$this->assertStringContainsString( 'Refunds within 14 days', $text );
		$this->assertStringContainsString( 'Ship ping is free', $text );
	}

	public function test_pdf_flate_stream_and_escapes() {
		$stream = 'BT (Price \(USD\): 49) Tj ET';
		$data   = gzcompress( $stream );
		$pdf    = "1 0 obj << /Filter /FlateDecode /Length " . strlen( $data ) . " >> stream\n" . $data . "\nendstream";
		$this->assertSame( 'Price (USD): 49', Extract::pdf( $pdf ) );
	}

	public function test_sitemap() {
		$xml = '<?xml version="1.0"?><urlset><url><loc>https://x.test/a/</loc></url><url><loc>https://x.test/b/?q=1&amp;r=2</loc></url></urlset>';
		$this->assertSame( array( 'https://x.test/a/', 'https://x.test/b/?q=1&r=2' ), Extract::sitemap( $xml )['urls'] );
		$idx = '<sitemapindex><sitemap><loc>https://x.test/post-sitemap.xml</loc></sitemap></sitemapindex>';
		$this->assertSame( array( 'https://x.test/post-sitemap.xml' ), Extract::sitemap( $idx )['sitemaps'] );
	}

	public function test_order_message_parsing() {
		$p = Woo::parse_order_message( 'Where is my order #1042? Email sara@example.com' );
		$this->assertSame( array( true, 1042, 'sara@example.com' ), array( $p['intent'], $p['number'], $p['email'] ) );
		$p = Woo::parse_order_message( 'Do you sell 100 piece sets?' );
		$this->assertFalse( $p['intent'] );
		$p = Woo::parse_order_message( 'bestelling 2001 status' );
		$this->assertSame( 2001, $p['number'] );
	}

	public function test_business_hours() {
		$hours = array(
			'mon' => array( 'open' => 1, 'from' => '09:00', 'until' => '17:00' ),
			'sat' => array( 'open' => 1, 'from' => '22:00', 'until' => '02:00' ),
			'sun' => array( 'open' => 0, 'from' => '09:00', 'until' => '17:00' ),
		);
		$tz    = new DateTimeZone( 'UTC' );
		$this->assertTrue( Engage::is_open( $hours, new DateTimeImmutable( '2026-10-12 10:30', $tz ) ) );
		$this->assertFalse( Engage::is_open( $hours, new DateTimeImmutable( '2026-10-12 17:00', $tz ) ) );
		$this->assertTrue( Engage::is_open( $hours, new DateTimeImmutable( '2026-10-10 23:30', $tz ) ) );
		$this->assertFalse( Engage::is_open( $hours, new DateTimeImmutable( '2026-10-11 12:00', $tz ) ) );
	}

	public function test_stream_line_parsing() {
		$this->assertSame( 'Hel', Stream::parse_line( 'data: {"choices":[{"delta":{"content":"Hel"}}]}', 'openai' ) );
		$this->assertNull( Stream::parse_line( 'data: [DONE]', 'openai' ) );
		$this->assertSame( 'lo', Stream::parse_line( 'data: {"type":"content_block_delta","delta":{"type":"text_delta","text":"lo"}}', 'anthropic' ) );
		$this->assertNull( Stream::parse_line( 'event: ping', 'anthropic' ) );
		$this->assertSame( 'Answer', Stream::visible( '<think>plan</think>Answer' ) );
		$this->assertSame( '', Stream::visible( '<think>still thinking' ) );
	}

	public function test_alert_text() {
		$t = Alerts::text( array( 'name' => 'Sara', 'email' => 'sara@example.com', 'phone' => '' ), 'Clinic' );
		$this->assertSame( "New lead on Clinic\nName: Sara\nEmail: sara@example.com", $t );
	}
}
