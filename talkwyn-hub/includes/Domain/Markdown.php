<?php
/**
 * Minimal, safe Markdown to HTML converter for changelogs.
 *
 * @package TalkwynHub
 */

namespace TWH\Domain;

/**
 * Supports headings, unordered/ordered lists, paragraphs, **bold**, *italic*,
 * `code` and [links](https://...). All text is HTML-escaped first, so the
 * output only contains tags this class produces.
 */
final class Markdown {

	/**
	 * Convert Markdown to HTML.
	 *
	 * @param string $markdown Markdown.
	 */
	public static function to_html( string $markdown ): string {
		$lines = preg_split( '/\r\n|\r|\n/', $markdown );
		$html  = array();
		$list  = null;
		$para  = array();

		$flush_para = static function () use ( &$para, &$html ): void {
			if ( $para ) {
				$html[] = '<p>' . implode( ' ', $para ) . '</p>';
				$para   = array();
			}
		};
		$close_list = static function () use ( &$list, &$html ): void {
			if ( null !== $list ) {
				$html[] = '</' . $list . '>';
				$list   = null;
			}
		};

		foreach ( (array) $lines as $line ) {
			$trimmed = trim( (string) $line );
			if ( '' === $trimmed ) {
				$flush_para();
				$close_list();
				continue;
			}
			if ( preg_match( '/^(#{1,6})\s+(.+)$/', $trimmed, $m ) ) {
				$flush_para();
				$close_list();
				$level  = min( 6, strlen( $m[1] ) + 2 ); // "#" becomes h3, keeping modal headings small.
				$html[] = '<h' . $level . '>' . self::inline( $m[2] ) . '</h' . $level . '>';
				continue;
			}
			$type = null;
			if ( preg_match( '/^[-*+]\s+(.+)$/', $trimmed, $m ) ) {
				$type = 'ul';
			} elseif ( preg_match( '/^\d+[.)]\s+(.+)$/', $trimmed, $m ) ) {
				$type = 'ol';
			}
			if ( null !== $type ) {
				$flush_para();
				if ( $list !== $type ) {
					$close_list();
					$html[] = '<' . $type . '>';
					$list   = $type;
				}
				$html[] = '<li>' . self::inline( $m[1] ) . '</li>';
				continue;
			}
			$close_list();
			$para[] = self::inline( $trimmed );
		}
		$flush_para();
		$close_list();

		return implode( "\n", $html );
	}

	/**
	 * Inline formatting on an escaped string.
	 *
	 * @param string $text Raw text.
	 */
	private static function inline( string $text ): string {
		$text = htmlspecialchars( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Protect code spans first.
		$codes = array();
		$text  = (string) preg_replace_callback(
			'/`([^`]+)`/',
			static function ( array $m ) use ( &$codes ): string {
				$codes[] = '<code>' . $m[1] . '</code>';
				return "\x01" . ( count( $codes ) - 1 ) . "\x01";
			},
			$text
		);

		$text = (string) preg_replace_callback(
			'/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
			static function ( array $m ): string {
				return '<a href="' . $m[2] . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
			},
			$text
		);
		$text = (string) preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
		$text = (string) preg_replace( '/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/', '<em>$1</em>', $text );

		return (string) preg_replace_callback(
			"/\x01(\d+)\x01/",
			static function ( array $m ) use ( $codes ): string {
				return $codes[ (int) $m[1] ];
			},
			$text
		);
	}
}
