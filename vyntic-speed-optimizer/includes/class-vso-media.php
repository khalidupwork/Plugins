<?php
/**
 * Images & iframes: lazy loading, LCP priority, missing dimensions, WebP, YouTube facade.
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

class VSO_Media {

	const SKIP_CLASSES = array( 'no-lazy', 'skip-lazy', 'data-no-lazy', 'data-skip-lazy', 'vso-no-lazy', 'custom-logo', 'site-logo' );

	/** @var bool */
	private $youtube_used = false;

	/** @var int */
	private $img_index = 0;

	/** @var bool The main (LCP) image already got high priority. */
	private $lcp_done = false;

	public function process( $html ) {
		$webp = VSO_Settings::enabled( 'webp' ) && VSO_WebP::supported();

		if ( VSO_Settings::enabled( 'youtube_facade' ) ) {
			$html = $this->youtube_facade( $html );
		}

		$html = preg_replace_callback(
			'#<img\b[^>]*>#i',
			function ( $m ) {
				return $this->img( $m[0] );
			},
			$html
		);

		if ( VSO_Settings::enabled( 'lazy_iframes' ) ) {
			$html = preg_replace_callback(
				'#<iframe\b[^>]*>#i',
				static function ( $m ) {
					$tag = $m[0];
					if ( VSO_Utils::has_attr( $tag, 'loading' ) || VSO_Utils::matches_any( $tag, self::SKIP_CLASSES ) || ! VSO_Utils::attr( $tag, 'src' ) ) {
						return $tag;
					}
					return preg_replace( '#^<iframe\b#i', '<iframe loading="lazy"', $tag );
				},
				$html
			);
		}

		if ( $webp ) {
			$html = $this->rewrite_webp( $html );
		}

		return $html;
	}

	public function youtube_used() {
		return $this->youtube_used;
	}

	private function img( $tag ) {
		$index = $this->img_index++;
		$src   = (string) VSO_Utils::attr( $tag, 'src' );
		if ( '' === $src || 0 === strpos( $src, 'data:' ) ) {
			return $tag;
		}

		if ( VSO_Settings::enabled( 'add_dimensions' ) && ( ! VSO_Utils::has_attr( $tag, 'width' ) || ! VSO_Utils::has_attr( $tag, 'height' ) ) ) {
			$tag = $this->add_dimensions( $tag, $src );
		}

		$skip_count = (int) VSO_Settings::get( 'lazy_skip' );
		$excluded   = VSO_Utils::matches_any( $tag, array_merge( self::SKIP_CLASSES, VSO_Settings::lines( 'lazy_exclude' ) ) );
		$above_fold = $index < $skip_count;

		if ( $above_fold || $excluded ) {
			// The first real content image near the top is the likely LCP element:
			// never lazy, fetched first. Logos and icons are skipped, and a
			// "high" priority someone else put on a logo is removed.
			if ( VSO_Settings::enabled( 'lcp_priority' ) && $above_fold ) {
				if ( self::is_small_or_logo( $tag ) ) {
					if ( 'high' === strtolower( (string) VSO_Utils::attr( $tag, 'fetchpriority' ) ) ) {
						$tag = VSO_Utils::remove_attr( $tag, 'fetchpriority' );
					}
				} elseif ( ! $this->lcp_done ) {
					$this->lcp_done = true;
					$tag            = VSO_Utils::remove_attr( $tag, 'fetchpriority' );
					$tag            = VSO_Utils::remove_attr( $tag, 'decoding' );
					$tag            = preg_replace( '#^<img\b#i', '<img fetchpriority="high"', $tag );
				}
			}
			if ( $above_fold && 'lazy' === strtolower( (string) VSO_Utils::attr( $tag, 'loading' ) ) ) {
				$tag = VSO_Utils::remove_attr( $tag, 'loading' );
			}
			return $tag;
		}

		if ( VSO_Settings::enabled( 'lazy_images' ) && ! VSO_Utils::has_attr( $tag, 'loading' ) ) {
			$tag = preg_replace( '#^<img\b#i', '<img loading="lazy"', $tag );
		}
		if ( ! VSO_Utils::has_attr( $tag, 'decoding' ) ) {
			$tag = preg_replace( '#^<img\b#i', '<img decoding="async"', $tag );
		}
		return $tag;
	}

	/**
	 * Logos, icons, avatars and small images are never the LCP element.
	 */
	private static function is_small_or_logo( $tag ) {
		$w = (int) VSO_Utils::attr( $tag, 'width' );
		$h = (int) VSO_Utils::attr( $tag, 'height' );
		if ( ( $w > 0 && $w < 300 ) || ( $h > 0 && $h < 150 ) ) {
			return true;
		}
		$text = VSO_Utils::attr( $tag, 'class' ) . ' ' . VSO_Utils::attr( $tag, 'src' ) . ' ' . VSO_Utils::attr( $tag, 'alt' );
		return VSO_Utils::matches_any( $text, array( 'logo', 'favicon', 'icon', 'avatar', 'gravatar', 'emoji', 'badge' ) );
	}

	private function add_dimensions( $tag, $src ) {
		$size = $this->image_size( $src );
		if ( ! $size ) {
			return $tag;
		}
		list( $w, $h ) = $size;
		$has_w = VSO_Utils::attr( $tag, 'width' );
		$has_h = VSO_Utils::attr( $tag, 'height' );

		if ( $has_w && ! $has_h && (int) $has_w > 0 ) {
			$h = (int) round( $h * ( (int) $has_w / $w ) );
			$w = (int) $has_w;
		} elseif ( $has_h && ! $has_w && (int) $has_h > 0 ) {
			$w = (int) round( $w * ( (int) $has_h / $h ) );
			$h = (int) $has_h;
		}
		if ( ! $has_w ) {
			$tag = preg_replace( '#^<img\b#i', '<img width="' . (int) $w . '"', $tag );
		}
		if ( ! $has_h ) {
			$tag = preg_replace( '#^<img\b#i', '<img height="' . (int) $h . '"', $tag );
		}
		return $tag;
	}

	private function image_size( $src ) {
		static $memo = array();
		if ( isset( $memo[ $src ] ) ) {
			return $memo[ $src ];
		}
		$memo[ $src ] = false;
		$path         = VSO_Utils::url_to_path( $src );
		if ( ! $path ) {
			return false;
		}
		if ( preg_match( '#\.svg$#i', $path ) ) {
			$svg = (string) @file_get_contents( $path, false, null, 0, 2048 ); // phpcs:ignore
			if ( preg_match( '#viewBox=["\'][\d.\-]+[\s,]+[\d.\-]+[\s,]+([\d.]+)[\s,]+([\d.]+)#i', $svg, $m ) && (float) $m[1] > 0 ) {
				$memo[ $src ] = array( (int) round( (float) $m[1] ), (int) round( (float) $m[2] ) );
			}
			return $memo[ $src ];
		}
		$info = @getimagesize( $path ); // phpcs:ignore
		if ( $info && $info[0] > 0 && $info[1] > 0 ) {
			$memo[ $src ] = array( (int) $info[0], (int) $info[1] );
		}
		return $memo[ $src ];
	}

	/**
	 * Points every local JPG/PNG/GIF URL at its .webp sibling when one exists.
	 */
	private function rewrite_webp( $html ) {
		$upload = wp_get_upload_dir();
		$base   = preg_replace( '#^https?:#i', '', $upload['baseurl'] );
		$quoted = preg_quote( $base, '#' );
		$rel    = preg_quote( (string) wp_parse_url( $upload['baseurl'], PHP_URL_PATH ), '#' );

		return preg_replace_callback(
			'#((?:https?:)?' . $quoted . '|(?<=["\'\s(,])' . $rel . ')(/[^"\'\s()<>,]+?\.(?:jpe?g|png|gif))(?=[\s"\'),?])#i',
			static function ( $m ) use ( $upload ) {
				$file = $upload['basedir'] . rawurldecode( $m[2] );
				if ( false === strpos( $file, '..' ) && is_file( $file . '.webp' ) ) {
					return $m[0] . '.webp';
				}
				return $m[0];
			},
			$html
		);
	}

	/**
	 * Replaces YouTube iframes with a lightweight thumbnail that loads the player on click.
	 */
	private function youtube_facade( $html ) {
		return preg_replace_callback(
			'#<iframe\b[^>]*\ssrc=["\']((?:https?:)?//(?:www\.)?(?:youtube\.com|youtube-nocookie\.com)/embed/([a-zA-Z0-9_-]{6,})[^"\']*)["\'][^>]*>\s*</iframe>#i',
			function ( $m ) {
				$tag = $m[0];
				if ( VSO_Utils::matches_any( $tag, self::SKIP_CLASSES ) ) {
					return $tag;
				}
				$this->youtube_used = true;
				$id    = $m[2];
				$title = VSO_Utils::attr( $tag, 'title' );
				$w     = (int) VSO_Utils::attr( $tag, 'width' );
				$h     = (int) VSO_Utils::attr( $tag, 'height' );
				$ratio = ( $w > 0 && $h > 0 ) ? round( $h / $w * 100, 3 ) : 56.25;
				$src   = html_entity_decode( $m[1], ENT_QUOTES );
				if ( 0 === strpos( $src, '//' ) ) {
					$src = 'https:' . $src;
				}
				return '<div class="vso-yt" data-src="' . esc_attr( $src ) . '" data-title="' . esc_attr( (string) $title ) . '" data-class="' . esc_attr( (string) VSO_Utils::attr( $tag, 'class' ) ) . '" role="button" tabindex="0" aria-label="' . esc_attr( $title ? $title : __( 'Play video', 'vyntic-speed-optimizer' ) ) . '" style="padding-bottom:' . $ratio . '%' . ( $w > 0 ? ';max-width:' . $w . 'px' : '' ) . '">'
					. '<img src="https://i.ytimg.com/vi/' . esc_attr( $id ) . '/hqdefault.jpg" alt="' . esc_attr( (string) $title ) . '" loading="lazy" decoding="async" width="480" height="360">'
					. '<span class="vso-yt-play" aria-hidden="true"></span></div>';
			},
			$html
		);
	}

	public static function youtube_assets() {
		$css = '.vso-yt{position:relative;width:100%;height:0;overflow:hidden;cursor:pointer;background:#000}'
			. '.vso-yt img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;margin:0}'
			. '.vso-yt-play{position:absolute;left:50%;top:50%;width:68px;height:48px;margin:-24px 0 0 -34px;background:#f00;border-radius:12px;opacity:.9;transition:opacity .2s}'
			. '.vso-yt-play:after{content:"";position:absolute;left:27px;top:14px;border-style:solid;border-width:10px 0 10px 18px;border-color:transparent transparent transparent #fff}'
			. '.vso-yt:hover .vso-yt-play{opacity:1}';
		$js  = VSO_JS::compact( (string) @file_get_contents( VSO_PATH . 'assets/js/vso-youtube.js' ) ); // phpcs:ignore
		return '<style id="vso-yt-css">' . $css . '</style><script data-vso-nodelay id="vso-yt-js">' . $js . '</script>';
	}
}
