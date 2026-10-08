<?php
/**
 * Unicode-safe string helpers shared by the indexer, retriever and chat.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Text helpers. No WordPress calls, so they can be unit tested.
 */
class Talkwyn_Text {

	/**
	 * Lowercase plain text (tags removed).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function lower( $text ) {
		$text = trim( preg_replace( '/<[^>]*>/', ' ', (string) $text ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/**
	 * Unicode string length.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	public static function len( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text, 'UTF-8' ) : strlen( (string) $text );
	}

	/**
	 * Unicode substring.
	 *
	 * @param string   $text   Text.
	 * @param int      $start  Start.
	 * @param int|null $length Length.
	 * @return string
	 */
	public static function sub( $text, $start, $length = null ) {
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( (string) $text, $start, $length, 'UTF-8' );
		}
		return null === $length ? (string) substr( (string) $text, $start ) : (string) substr( (string) $text, $start, $length );
	}

	/**
	 * Last position of a needle.
	 *
	 * @param string $text   Text.
	 * @param string $needle Needle.
	 * @return int|false
	 */
	public static function rpos( $text, $needle ) {
		if ( function_exists( 'mb_strrpos' ) ) {
			return mb_strrpos( (string) $text, (string) $needle, 0, 'UTF-8' );
		}
		return strrpos( (string) $text, (string) $needle );
	}

	/**
	 * Whether a string contains another.
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle   Needle.
	 * @return bool
	 */
	public static function contains( $haystack, $needle ) {
		if ( '' === (string) $needle ) {
			return false;
		}
		return false !== strpos( (string) $haystack, (string) $needle );
	}

	/**
	 * Regex for scripts written without spaces between words (Chinese, Japanese, Korean, Thai).
	 *
	 * @return string
	 */
	public static function compact_class() {
		return '\x{3040}-\x{30ff}\x{3400}-\x{9fff}\x{ac00}-\x{d7af}\x{0e00}-\x{0e7f}';
	}

	/**
	 * Whether the text uses a compact script.
	 *
	 * @param string $text Text.
	 * @return bool
	 */
	public static function is_compact( $text ) {
		return (bool) preg_match( '/[' . self::compact_class() . ']/u', (string) $text );
	}

	/**
	 * Short n-grams for compact scripts so keyword search still finds matches.
	 *
	 * @param string $text Text.
	 * @param int    $max  Maximum grams.
	 * @return string[]
	 */
	public static function ngrams( $text, $max = 24 ) {
		$out = array();
		if ( ! preg_match_all( '/[' . self::compact_class() . ']+/u', (string) $text, $matches ) ) {
			return $out;
		}
		foreach ( $matches[0] as $sequence ) {
			$len = self::len( $sequence );
			if ( $len < 2 ) {
				continue;
			}
			foreach ( array( 3, 2 ) as $n ) {
				if ( $len < $n ) {
					continue;
				}
				for ( $i = 0; $i <= $len - $n; $i++ ) {
					$out[] = self::sub( $sequence, $i, $n );
					if ( count( $out ) >= $max ) {
						break 3;
					}
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Words of two or more letters, lowercased, as a lookup map.
	 *
	 * @param string $text Text.
	 * @return array<string, bool>
	 */
	public static function word_map( $text ) {
		preg_match_all( '/[\p{L}\p{N}\p{M}]{2,}/u', self::lower( $text ), $m );
		return array_fill_keys( $m[0], true );
	}

	/**
	 * Search tokens for a query: stop words removed, synonyms added, longest first.
	 *
	 * @param string $text Query.
	 * @param int    $max  Maximum tokens.
	 * @return string[]
	 */
	public static function tokens( $text, $max = 24 ) {
		$text = self::lower( $text );
		preg_match_all( '/[\p{L}\p{N}\p{M}]{2,}/u', $text, $m );
		$stop   = array(
			'the', 'and', 'for', 'that', 'this', 'with', 'from', 'your', 'you', 'are', 'was', 'were', 'have', 'has', 'can', 'what', 'how', 'who', 'when', 'where', 'which', 'our', 'about', 'more', 'please', 'site', 'website', 'is', 'do', 'does', 'it', 'of', 'to', 'in', 'on', 'an', 'me', 'my', 'we', 'us', 'be', 'or', 'if', 'at', 'by', 'any',
			'mein', 'hai', 'kia', 'kya', 'aur', 'mujhe', 'apka', 'apki', 'apke', 'aap', 'hum', 'hain', 'ho', 'kar', 'se', 'ka', 'ki', 'ko', 'ke',
			'de', 'het', 'een', 'en', 'van', 'voor', 'met', 'wat', 'hoe', 'wie', 'waar', 'welke',
			'le', 'la', 'les', 'un', 'une', 'et', 'des', 'pour', 'avec', 'que', 'quoi', 'comment', 'qui',
			'der', 'die', 'das', 'ein', 'eine', 'und', 'mit', 'für', 'wie', 'wer', 'wo',
			'el', 'los', 'las', 'una', 'con', 'para', 'como', 'quien', 'donde',
		);
		$tokens = array_values( array_diff( array_unique( $m[0] ), $stop ) );
		if ( self::is_compact( $text ) ) {
			$tokens = array_merge( self::ngrams( $text ), $tokens );
		}
		$synonyms = array(
			'contact'  => array( 'email', 'phone', 'call', 'whatsapp', 'address', 'reach', 'support' ),
			'email'    => array( 'contact', 'mail' ),
			'phone'    => array( 'contact', 'telephone', 'call', 'mobile' ),
			'address'  => array( 'contact', 'location' ),
			'service'  => array( 'services', 'offer' ),
			'services' => array( 'service', 'offer' ),
			'price'    => array( 'pricing', 'cost', 'fee', 'fees' ),
			'pricing'  => array( 'price', 'cost', 'fee', 'fees' ),
			'cost'     => array( 'price', 'pricing', 'fee' ),
			'book'     => array( 'booking', 'appointment', 'schedule' ),
			'booking'  => array( 'book', 'appointment', 'schedule' ),
			'hours'    => array( 'open', 'opening', 'timings' ),
			'shipping' => array( 'delivery', 'shipment' ),
			'delivery' => array( 'shipping' ),
			'refund'   => array( 'return', 'returns', 'refunds' ),
			'return'   => array( 'refund', 'returns' ),
		);
		$expanded = $tokens;
		foreach ( $tokens as $token ) {
			if ( isset( $synonyms[ $token ] ) ) {
				$expanded = array_merge( $expanded, $synonyms[ $token ] );
			}
		}
		$tokens = array_values( array_unique( $expanded ) );
		usort(
			$tokens,
			static function ( $a, $b ) {
				return self::len( $b ) <=> self::len( $a );
			}
		);
		return array_slice( $tokens, 0, $max );
	}

	/**
	 * Normalize whitespace.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function normalize( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/[\t ]+/u', ' ', $text );
		$text = preg_replace( '/\r\n|\r/', "\n", $text );
		$text = preg_replace( '/ *\n */', "\n", $text );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );
		return trim( (string) $text );
	}

	/**
	 * Split long text into overlapping chunks at sentence or line breaks.
	 *
	 * @param string $text    Text.
	 * @param int    $max     Maximum characters per chunk.
	 * @param int    $overlap Characters shared with the next chunk.
	 * @return string[]
	 */
	public static function chunk( $text, $max = 1400, $overlap = 220 ) {
		$text = trim( (string) $text );
		$len  = self::len( $text );
		if ( $len <= $max ) {
			return '' === $text ? array() : array( $text );
		}
		$chunks = array();
		$start  = 0;
		while ( $start < $len ) {
			$slice = self::sub( $text, $start, $max );
			if ( $start + $max < $len ) {
				$last = false;
				foreach ( array( "\n", '. ', '。', '！', '？', '؟', '۔' ) as $break ) {
					$pos = self::rpos( $slice, $break );
					if ( false !== $pos && ( false === $last || $pos > $last ) ) {
						$last = $pos;
					}
				}
				if ( false !== $last && $last > ( $max * 0.55 ) ) {
					$slice = self::sub( $slice, 0, $last + 1 );
				}
			}
			$slice_len = self::len( $slice );
			$slice     = trim( $slice );
			if ( '' !== $slice ) {
				$chunks[] = $slice;
			}
			if ( $start + $slice_len >= $len ) {
				break;
			}
			$start += max( 1, $slice_len - $overlap );
		}
		return array_values( array_unique( $chunks ) );
	}
}
