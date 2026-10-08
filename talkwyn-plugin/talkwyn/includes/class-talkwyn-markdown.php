<?php
/**
 * Safe Markdown rendering for bot replies.
 *
 * Everything is escaped first, then a small set of Markdown is turned into an
 * allow-listed set of tags: p, br, strong, em, code, ul, ol, li, a (http, https,
 * mailto, tel only). Raw HTML from the model is never output.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Markdown to safe HTML. Pure PHP, unit tested.
 */
class Talkwyn_Markdown {

	/**
	 * Render Markdown to safe HTML.
	 *
	 * @param string $text Markdown text from the AI provider.
	 * @return string
	 */
	public static function render( $text ) {
		$text  = str_replace( array( "\r\n", "\r" ), "\n", trim( (string) $text ) );
		$lines = explode( "\n", $text );
		$html  = array();
		$para  = array();
		$list  = null;
		$items = array();

		$flush_para = static function () use ( &$para, &$html ) {
			if ( $para ) {
				$html[] = '<p>' . implode( '<br>', array_map( array( __CLASS__, 'inline' ), $para ) ) . '</p>';
				$para   = array();
			}
		};
		$flush_list = static function () use ( &$list, &$items, &$html ) {
			if ( $list && $items ) {
				$html[] = '<' . $list . '>' . implode( '', array_map( static function ( $item ) {
					return '<li>' . self::inline( $item ) . '</li>';
				}, $items ) ) . '</' . $list . '>';
			}
			$list  = null;
			$items = array();
		};

		foreach ( $lines as $line ) {
			$trim = trim( $line );
			if ( '' === $trim ) {
				$flush_para();
				$flush_list();
				continue;
			}
			if ( preg_match( '/^(?:[-*+•])\s+(.+)$/u', $trim, $m ) ) {
				$flush_para();
				if ( 'ul' !== $list ) {
					$flush_list();
					$list = 'ul';
				}
				$items[] = $m[1];
				continue;
			}
			if ( preg_match( '/^\d{1,3}[.)]\s+(.+)$/u', $trim, $m ) ) {
				$flush_para();
				if ( 'ol' !== $list ) {
					$flush_list();
					$list = 'ol';
				}
				$items[] = $m[1];
				continue;
			}
			$flush_list();
			if ( preg_match( '/^#{1,6}\s+(.+)$/u', $trim, $m ) ) {
				$flush_para();
				$html[] = '<p><strong>' . self::inline( rtrim( $m[1], '# ' ) ) . '</strong></p>';
				continue;
			}
			if ( preg_match( '/^(?:-{3,}|\*{3,}|_{3,})$/', $trim ) ) {
				$flush_para();
				continue;
			}
			$para[] = preg_replace( '/^>\s?/', '', $trim );
		}
		$flush_para();
		$flush_list();
		return implode( '', $html );
	}

	/**
	 * Inline Markdown: code, links, bold, italic, bare URLs.
	 *
	 * @param string $text Line.
	 * @return string
	 */
	public static function inline( $text ) {
		$tokens = array();
		$stash  = static function ( $html ) use ( &$tokens ) {
			$key            = "\x1A" . count( $tokens ) . "\x1A";
			$tokens[ $key ] = $html;
			return $key;
		};

		// Inline code first, so its contents stay literal.
		$text = preg_replace_callback(
			'/`([^`\n]+)`/u',
			static function ( $m ) use ( $stash ) {
				return $stash( '<code>' . self::esc( $m[1] ) . '</code>' );
			},
			(string) $text
		);

		// [label](url).
		$text = preg_replace_callback(
			'/\[([^\]\n]{1,200})\]\(\s*([^)\s]{1,2000})(?:\s+"[^"]*")?\s*\)/u',
			static function ( $m ) use ( $stash ) {
				$url = self::safe_url( $m[2] );
				if ( '' === $url ) {
					return $m[1];
				}
				return $stash( self::link( $url, self::emphasis( self::esc( $m[1] ) ) ) );
			},
			$text
		);

		// Bare URLs and email addresses.
		$text = preg_replace_callback(
			'~(?<![\w/@])(https?://[^\s<>"\x1A]+[^\s<>"\x1A.,;:!?)\]])~u',
			static function ( $m ) use ( $stash ) {
				$url = self::safe_url( $m[1] );
				return '' === $url ? $m[1] : $stash( self::link( $url, self::esc( $m[1] ) ) );
			},
			$text
		);
		$text = preg_replace_callback(
			'/(?<![\w.\/:@])([A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,})(?![\w@])/u',
			static function ( $m ) use ( $stash ) {
				return $stash( self::link( 'mailto:' . $m[1], self::esc( $m[1] ) ) );
			},
			$text
		);

		$text = self::emphasis( self::esc( $text ) );
		return strtr( $text, $tokens );
	}

	/**
	 * Bold and italic on escaped text.
	 *
	 * @param string $text Escaped text.
	 * @return string
	 */
	private static function emphasis( $text ) {
		$text = preg_replace( '/\*\*(?=\S)(.+?)(?<=\S)\*\*/u', '<strong>$1</strong>', $text );
		$text = preg_replace( '/__(?=\S)(.+?)(?<=\S)__/u', '<strong>$1</strong>', $text );
		$text = preg_replace( '/(?<![\*\w])\*(?=\S)([^*\n]+?)(?<=\S)\*(?![\*\w])/u', '<em>$1</em>', $text );
		return $text;
	}

	/**
	 * Link markup.
	 *
	 * @param string $url   Safe URL.
	 * @param string $label Escaped label.
	 * @return string
	 */
	private static function link( $url, $label ) {
		$external = 0 === strpos( $url, 'http' );
		return '<a href="' . self::esc( $url ) . '"' . ( $external ? ' target="_blank" rel="noopener nofollow"' : '' ) . '>' . $label . '</a>';
	}

	/**
	 * Only http, https, mailto and tel URLs are allowed.
	 *
	 * @param string $url URL.
	 * @return string Empty when unsafe.
	 */
	public static function safe_url( $url ) {
		$url = trim( html_entity_decode( (string) $url, ENT_QUOTES, 'UTF-8' ) );
		$url = preg_replace( '/[\x00-\x20\x7F]/', '', $url );
		if ( preg_match( '#^(https?://[^\s]+|mailto:[^\s@]+@[^\s]+|tel:\+?[0-9().\- ]{3,})$#i', $url ) ) {
			return $url;
		}
		return '';
	}

	/**
	 * HTML escape.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function esc( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false );
	}

	/**
	 * Plain text version (for copy and logs).
	 *
	 * @param string $text Markdown.
	 * @return string
	 */
	public static function plain( $text ) {
		$text = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/u', '$1 ($2)', (string) $text );
		$text = preg_replace( '/(\*\*|__|`)/u', '', $text );
		$text = preg_replace( '/^#{1,6}\s+/mu', '', $text );
		return trim( $text );
	}
}
