<?php
/**
 * @package TalkwynHub
 */

namespace TWH\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TWH\Domain\Crypto;
use TWH\Domain\Markdown;
use TWH\Domain\ZipInspector;

final class CryptoAndMarkdownTest extends TestCase {

	public function test_encrypt_decrypt_roundtrip(): void {
		$c   = new Crypto( 'a-very-long-master-secret-for-tests-only' );
		$enc = $c->encrypt( 'TALK-ABCD-EFGH-JKLM-NPQR' );
		$this->assertStringNotContainsString( 'TALK', $enc );
		$this->assertSame( 'TALK-ABCD-EFGH-JKLM-NPQR', $c->decrypt( $enc ) );
		$this->assertNotSame( $enc, $c->encrypt( 'TALK-ABCD-EFGH-JKLM-NPQR' ), 'Random nonce per encryption.' );
	}

	public function test_decrypt_with_wrong_secret_or_tampered_data_returns_null(): void {
		$enc = ( new Crypto( 'secret-one-secret-one-secret-one' ) )->encrypt( 'hello' );
		$this->assertNull( ( new Crypto( 'secret-two-secret-two-secret-two' ) )->decrypt( $enc ) );
		$raw     = base64_decode( $enc );
		$raw[30] = chr( ord( $raw[30] ) ^ 1 );
		$this->assertNull( ( new Crypto( 'secret-one-secret-one-secret-one' ) )->decrypt( base64_encode( $raw ) ) );
		$this->assertNull( ( new Crypto( 'x' ) )->decrypt( 'not base64 !!' ) );
	}

	public function test_subkeys_differ_per_context(): void {
		$c = new Crypto( 'master' );
		$this->assertNotSame( $c->subkey( 'enc' ), $c->subkey( 'ip' ) );
		$this->assertSame( 32, strlen( $c->subkey( 'enc' ) ) );
		$this->assertSame( $c->hmac( '1.2.3.4', 'ip' ), ( new Crypto( 'master' ) )->hmac( '1.2.3.4', 'ip' ) );
	}

	public function test_markdown_escapes_html(): void {
		$html = Markdown::to_html( "## 1.2.0\n- New: **bold** and *italic* and `<code>`\n- Fix: <script>alert(1)</script>\n\nSee [docs](https://talkwyn.com/docs) and [x](javascript:alert(1))" );
		$this->assertStringContainsString( '<h4>1.2.0</h4>', $html );
		$this->assertStringContainsString( '<li>New: <strong>bold</strong> and <em>italic</em> and <code>&lt;code&gt;</code></li>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '<a href="https://talkwyn.com/docs"', $html );
		$this->assertStringNotContainsString( 'href="javascript', $html );
	}

	public function test_zip_inspector_reads_plugin_header(): void {
		if ( ! class_exists( \ZipArchive::class ) ) {
			$this->markTestSkipped( 'zip extension missing' );
		}
		$path = tempnam( sys_get_temp_dir(), 'twh' ) . '.zip';
		$zip  = new \ZipArchive();
		$zip->open( $path, \ZipArchive::CREATE );
		$zip->addFromString( 'talkwyn/talkwyn.php', "<?php\n/**\n * Plugin Name: Talkwyn\n * Version: 1.2.3\n * Requires at least: 6.4\n * Requires PHP: 8.0\n * WC requires at least: 8.0\n */\n" );
		$zip->addFromString( 'talkwyn/readme.txt', "=== Talkwyn ===\nTested up to: 6.7\nStable tag: 1.2.3\n" );
		$zip->addFromString( 'talkwyn/includes/other.php', "<?php\n// Plugin Name: Not me\n" );
		$zip->close();

		$info = ZipInspector::inspect( $path );
		unlink( $path );
		$this->assertSame( 'Talkwyn', $info['name'] );
		$this->assertSame( '1.2.3', $info['version'] );
		$this->assertSame( '6.4', $info['requires_wp'] );
		$this->assertSame( '8.0', $info['requires_php'] );
		$this->assertSame( '6.7', $info['tested_wp'] );
		$this->assertSame( 'talkwyn', $info['folder'] );
	}

	public function test_zip_inspector_rejects_non_plugin(): void {
		$path = tempnam( sys_get_temp_dir(), 'twh' ) . '.zip';
		$zip  = new \ZipArchive();
		$zip->open( $path, \ZipArchive::CREATE );
		$zip->addFromString( 'readme.md', 'hello' );
		$zip->close();
		$this->assertNull( ZipInspector::inspect( $path ) );
		unlink( $path );
		$this->assertNull( ZipInspector::inspect( __FILE__ ), 'Not a ZIP.' );
	}
}
