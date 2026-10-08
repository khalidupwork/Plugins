<?php
use PHPUnit\Framework\TestCase;

final class MarkdownTest extends TestCase {
	public function test_basic_markdown() {
		$html = Talkwyn_Markdown::render( "**Hours**: 9 to 5\n\n- Mon\n- Tue\n\n1. One\n2. Two" );
		$this->assertSame( '<p><strong>Hours</strong>: 9 to 5</p><ul><li>Mon</li><li>Tue</li></ul><ol><li>One</li><li>Two</li></ol>', $html );
	}
	public function test_raw_html_is_escaped() {
		$html = Talkwyn_Markdown::render( '<script>alert(1)</script><img src=x onerror=alert(1)>' );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}
	public function test_javascript_links_are_dropped() {
		$html = Talkwyn_Markdown::render( '[click](javascript:alert(1)) and [ok](https://example.com/a?b=1&c=2)' );
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertStringContainsString( 'href="https://example.com/a?b=1&amp;c=2"', $html );
	}
	public function test_attribute_injection_in_url_is_escaped() {
		$html = Talkwyn_Markdown::render( '[x](https://e.com/"onmouseover="alert(1))' );
		$this->assertStringNotContainsString( '"onmouseover="', $html );
	}
	public function test_bare_urls_emails_and_code() {
		$html = Talkwyn_Markdown::render( 'Mail hi@example.com or see https://example.com. Use `<b>`.' );
		$this->assertStringContainsString( 'href="mailto:hi@example.com"', $html );
		$this->assertStringContainsString( 'href="https://example.com"', $html );
		$this->assertStringContainsString( '<code>&lt;b&gt;</code>', $html );
	}
	public function test_plain() {
		$this->assertSame( 'Read docs (https://x.test)', Talkwyn_Markdown::plain( 'Read [docs](https://x.test)' ) );
	}
}
