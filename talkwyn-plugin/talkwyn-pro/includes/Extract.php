<?php
/**
 * Text extraction from PDF, DOCX, TXT and HTML.
 *
 * The PDF reader handles text-based PDFs (most exports from Word, Google Docs
 * and site builders). Scanned PDFs are images and need OCR first.
 *
 * @package TalkwynPro
 */

namespace TalkwynPro;

defined( 'ABSPATH' ) || exit;

/**
 * Extractors. Pure PHP, unit tested.
 */
final class Extract {

	/**
	 * Extract text from a file by extension.
	 *
	 * @param string $path File path.
	 * @return string
	 * @throws \Exception When the type is not supported.
	 */
	public static function file( string $path ): string {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$raw = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		switch ( $ext ) {
			case 'txt':
			case 'md':
			case 'csv':
				return self::plain( $raw );
			case 'docx':
				return self::docx( $path );
			case 'pdf':
				return self::pdf( $raw );
		}
		throw new \Exception( esc_html( 'Unsupported file type: ' . $ext ) );
	}

	/**
	 * Plain text in UTF-8.
	 *
	 * @param string $raw Raw.
	 */
	public static function plain( string $raw ): string {
		if ( 0 === strpos( $raw, "\xEF\xBB\xBF" ) ) {
			$raw = substr( $raw, 3 );
		}
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $raw, 'UTF-8' ) ) {
			$raw = (string) mb_convert_encoding( $raw, 'UTF-8', 'Windows-1252' );
		}
		return trim( str_replace( array( "\r\n", "\r" ), "\n", $raw ) );
	}

	/**
	 * DOCX: paragraphs from word/document.xml.
	 *
	 * @param string $path Path.
	 * @throws \Exception When unreadable.
	 */
	public static function docx( string $path ): string {
		if ( ! class_exists( '\ZipArchive' ) ) {
			throw new \Exception( 'The PHP zip extension is needed to read DOCX files.' );
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			throw new \Exception( 'Could not open the DOCX file.' );
		}
		$xml = (string) $zip->getFromName( 'word/document.xml' );
		$zip->close();
		if ( '' === $xml ) {
			throw new \Exception( 'The DOCX file has no text.' );
		}
		return self::docx_xml( $xml );
	}

	/**
	 * DOCX XML to text.
	 *
	 * @param string $xml document.xml.
	 */
	public static function docx_xml( string $xml ): string {
		$xml  = preg_replace( '#<w:tab\s*/>#', "\t", $xml );
		$xml  = preg_replace( '#<w:(br|cr)\s*/>#', "\n", $xml );
		$xml  = preg_replace( '#</w:p>#', "\n", $xml );
		$text = html_entity_decode( (string) preg_replace( '#<[^>]+>#', '', (string) $xml ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
		return trim( (string) preg_replace( "/\n{3,}/", "\n\n", $text ) );
	}

	/**
	 * PDF: decode content streams and read text operators.
	 *
	 * @param string $raw PDF bytes.
	 */
	public static function pdf( string $raw ): string {
		$out = array();
		if ( preg_match_all( '#<<(.*?)>>\s*stream\r?\n(.*?)\r?\n?endstream#s', $raw, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $stream ) {
				$dict = $stream[1];
				$data = $stream[2];
				if ( preg_match( '#/Subtype\s*/(Image|XML)|/Type\s*/(XObject|Metadata|XRef|ObjStm)#', $dict ) && ! preg_match( '#/Subtype\s*/Form#', $dict ) ) {
					continue;
				}
				if ( false !== strpos( $dict, '/FlateDecode' ) ) {
					$decoded = @gzuncompress( $data ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					if ( false === $decoded ) {
						$decoded = @gzinflate( substr( $data, 2 ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					}
					if ( false === $decoded ) {
						continue;
					}
					$data = $decoded;
				} elseif ( preg_match( '#/Filter#', $dict ) ) {
					continue;
				}
				$text = self::pdf_text( $data );
				if ( '' !== trim( $text ) ) {
					$out[] = $text;
				}
			}
		}
		$text = implode( "\n", $out );
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		return trim( (string) preg_replace( "/\n{3,}/", "\n\n", (string) $text ) );
	}

	/**
	 * Read text-showing operators from a content stream.
	 *
	 * @param string $content Stream content.
	 */
	public static function pdf_text( string $content ): string {
		if ( ! preg_match_all( '#BT(.*?)ET#s', $content, $blocks ) ) {
			return '';
		}
		$lines = array();
		foreach ( $blocks[1] as $block ) {
			$line = '';
			preg_match_all( '#(\[(?:[^\]\\\\]|\\\\.)*\]\s*TJ|\((?:[^()\\\\]|\\\\.|\((?:[^()\\\\]|\\\\.)*\))*\)\s*(?:Tj|\'|")|<[0-9A-Fa-f\s]+>\s*Tj|T\*|-?[\d.]+\s+-?[\d.]+\s+T[dD]|(?:-?[\d.]+\s+){6}Tm)#s', $block, $ops );
			foreach ( $ops[0] as $op ) {
				if ( preg_match( '#T[dD*]$|Tm$#', $op ) ) {
					if ( preg_match( '#^-?[\d.]+\s+(-?[\d.]+)\s+T[dD]$#', $op, $td ) && 0.0 === (float) $td[1] ) {
						continue;
					}
					$line .= "\n";
					continue;
				}
				if ( '[' === $op[0] ) {
					preg_match_all( '#\((?:[^()\\\\]|\\\\.)*\)|<[0-9A-Fa-f\s]*>|-?[\d.]+#s', $op, $parts );
					foreach ( $parts[0] as $part ) {
						if ( '(' === $part[0] ) {
							$line .= self::pdf_string( substr( $part, 1, -1 ) );
						} elseif ( '<' === $part[0] ) {
							$line .= self::pdf_hex( substr( $part, 1, -1 ) );
						} elseif ( (float) $part < -200 ) {
							$line .= ' ';
						}
					}
				} elseif ( '<' === $op[0] ) {
					$line .= self::pdf_hex( substr( $op, 1, strpos( $op, '>' ) - 1 ) );
				} else {
					$end   = strrpos( $op, ')' );
					$line .= self::pdf_string( substr( $op, 1, $end - 1 ) );
				}
			}
			$lines[] = trim( $line );
		}
		return implode( "\n", array_filter( $lines, 'strlen' ) );
	}

	/**
	 * Decode a PDF literal string.
	 *
	 * @param string $s Literal without the outer parentheses.
	 */
	public static function pdf_string( string $s ): string {
		$s = preg_replace_callback(
			'#\\\\([0-7]{1,3}|.)#s',
			static function ( $m ) {
				$map = array(
					'n'  => "\n",
					'r'  => '',
					't'  => "\t",
					'b'  => '',
					'f'  => '',
					'('  => '(',
					')'  => ')',
					'\\' => '\\',
				);
				if ( ctype_digit( $m[1] ) ) {
					return chr( octdec( $m[1] ) & 0xFF );
				}
				return $map[ $m[1] ] ?? $m[1];
			},
			$s
		);
		return self::to_utf8( (string) $s );
	}

	/**
	 * Decode a hex string (UTF-16 with BOM, or single bytes).
	 *
	 * @param string $hex Hex.
	 */
	public static function pdf_hex( string $hex ): string {
		$hex = preg_replace( '/\s+/', '', $hex );
		if ( strlen( $hex ) % 2 ) {
			$hex .= '0';
		}
		$bin = (string) hex2bin( $hex );
		return self::to_utf8( $bin );
	}

	/**
	 * Bytes to UTF-8 (UTF-16BE with BOM, or Latin-1).
	 *
	 * @param string $bin Bytes.
	 */
	private static function to_utf8( string $bin ): string {
		if ( 0 === strpos( $bin, "\xFE\xFF" ) && function_exists( 'mb_convert_encoding' ) ) {
			return (string) mb_convert_encoding( substr( $bin, 2 ), 'UTF-8', 'UTF-16BE' );
		}
		if ( function_exists( 'mb_check_encoding' ) && mb_check_encoding( $bin, 'UTF-8' ) ) {
			return $bin;
		}
		$out = function_exists( 'mb_convert_encoding' ) ? (string) mb_convert_encoding( $bin, 'UTF-8', 'ISO-8859-1' ) : $bin;
		// Drop control characters that come from glyph IDs without a text map.
		return (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $out );
	}

	/**
	 * Readable text from an HTML page (main content preferred).
	 *
	 * @param string $html HTML.
	 * @return array{title:string,text:string}
	 */
	public static function html( string $html ): array {
		$title = preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $m ) ? trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES, 'UTF-8' ) ) : '';
		$html  = preg_replace( '#<(script|style|noscript|svg|nav|header|footer|form|iframe|template)\b[^>]*>.*?</\1>#is', ' ', $html );
		if ( preg_match( '#<main\b[^>]*>(.*?)</main>#is', (string) $html, $m ) || preg_match( '#<article\b[^>]*>(.*?)</article>#is', (string) $html, $m ) ) {
			$html = $m[1];
		} elseif ( preg_match( '#<body\b[^>]*>(.*?)</body>#is', (string) $html, $m ) ) {
			$html = $m[1];
		}
		$html = preg_replace( '#</(p|div|li|h[1-6]|tr|section|br)>#i', "$0\n", (string) $html );
		$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		$text = preg_replace( '/ *\n */', "\n", (string) $text );
		return array(
			'title' => $title,
			'text'  => trim( (string) preg_replace( "/\n{3,}/", "\n\n", (string) $text ) ),
		);
	}

	/**
	 * URLs from a sitemap or sitemap index.
	 *
	 * @param string $xml XML.
	 * @return array{urls:string[],sitemaps:string[]}
	 */
	public static function sitemap( string $xml ): array {
		$urls     = array();
		$sitemaps = array();
		$is_index = false !== stripos( $xml, '<sitemapindex' );
		if ( preg_match_all( '#<loc>\s*(.*?)\s*</loc>#is', $xml, $m ) ) {
			foreach ( $m[1] as $loc ) {
				$loc = html_entity_decode( trim( preg_replace( '#^<!\[CDATA\[(.*)\]\]>$#s', '$1', $loc ) ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
				if ( preg_match( '#^https?://#i', $loc ) ) {
					if ( $is_index ) {
						$sitemaps[] = $loc;
					} else {
						$urls[] = $loc;
					}
				}
			}
		}
		return array(
			'urls'     => array_values( array_unique( $urls ) ),
			'sitemaps' => array_values( array_unique( $sitemaps ) ),
		);
	}
}
