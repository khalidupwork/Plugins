<?php
/**
 * Local WebP conversion (GD or Imagick) – on upload and in bulk.
 * Files are stored next to the original as "photo.jpg.webp".
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

class VSO_WebP {

	const META = '_vso_webp';

	public static function init() {
		if ( VSO_Settings::enabled( 'webp' ) ) {
			add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'on_generate_metadata' ), 20, 2 );
		}
		add_action( 'delete_attachment', array( __CLASS__, 'delete_webp' ) );
	}

	public static function supported() {
		static $ok = null;
		if ( null === $ok ) {
			$ok = ( function_exists( 'imagewebp' ) && function_exists( 'imagecreatefromjpeg' ) )
				|| ( class_exists( 'Imagick' ) && in_array( 'WEBP', (array) Imagick::queryFormats( 'WEBP' ), true ) );
		}
		return $ok;
	}

	public static function on_generate_metadata( $metadata, $attachment_id ) {
		self::convert_attachment( $attachment_id, $metadata );
		return $metadata;
	}

	/**
	 * Converts the original and every generated size of an attachment.
	 *
	 * @return int Bytes saved.
	 */
	public static function convert_attachment( $attachment_id, $metadata = null ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! is_file( $file ) || ! preg_match( '#\.(jpe?g|png)$#i', $file ) ) {
			update_post_meta( $attachment_id, self::META, 'skip' );
			return 0;
		}
		if ( null === $metadata ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
		}

		$files = array( $file );
		$dir   = dirname( $file );
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) ) {
					$files[] = $dir . '/' . $size['file'];
				}
			}
		}
		if ( ! empty( $metadata['original_image'] ) ) {
			$files[] = $dir . '/' . $metadata['original_image'];
		}

		$saved = 0;
		foreach ( array_unique( $files ) as $path ) {
			$saved += self::convert_file( $path );
		}
		update_post_meta( $attachment_id, self::META, max( 0, $saved ) );
		return $saved;
	}

	/**
	 * Creates "$path.webp". Returns bytes saved (0 if skipped).
	 */
	public static function convert_file( $path ) {
		if ( ! is_file( $path ) || is_file( $path . '.webp' ) || ! self::supported() ) {
			return 0;
		}
		$quality = (int) VSO_Settings::get( 'webp_quality', 80 );
		$target  = $path . '.webp';
		$ok      = false;

		if ( class_exists( 'Imagick' ) && in_array( 'WEBP', (array) Imagick::queryFormats( 'WEBP' ), true ) ) {
			try {
				$im = new Imagick( $path );
				$im->setImageFormat( 'webp' );
				$im->setImageCompressionQuality( $quality );
				$im->setOption( 'webp:method', '6' );
				$im->stripImage();
				$ok = $im->writeImage( $target );
				$im->clear();
			} catch ( Exception $e ) {
				$ok = false;
			}
		}

		if ( ! $ok && function_exists( 'imagewebp' ) ) {
			$info = @getimagesize( $path ); // phpcs:ignore
			if ( ! $info || $info[0] * $info[1] > 40000000 ) {
				return 0; // Unreadable or too large for GD memory.
			}
			$img = null;
			if ( IMAGETYPE_JPEG === $info[2] ) {
				$img = @imagecreatefromjpeg( $path ); // phpcs:ignore
			} elseif ( IMAGETYPE_PNG === $info[2] ) {
				$img = @imagecreatefrompng( $path ); // phpcs:ignore
				if ( $img ) {
					if ( function_exists( 'imagepalettetotruecolor' ) ) {
						imagepalettetotruecolor( $img );
					}
					imagealphablending( $img, true );
					imagesavealpha( $img, true );
				}
			}
			if ( $img ) {
				$ok = @imagewebp( $img, $target, $quality ); // phpcs:ignore
				imagedestroy( $img );
			}
		}

		if ( ! $ok || ! is_file( $target ) ) {
			@unlink( $target ); // phpcs:ignore
			return 0;
		}

		$before = (int) filesize( $path );
		$after  = (int) filesize( $target );
		if ( $after <= 0 || $after >= $before ) {
			// WebP not smaller – keep serving the original.
			@unlink( $target ); // phpcs:ignore
			return 0;
		}
		return $before - $after;
	}

	public static function delete_webp( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return;
		}
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$dir      = dirname( $file );
		$files    = array( $file );
		if ( ! empty( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) ) {
					$files[] = $dir . '/' . $size['file'];
				}
			}
		}
		if ( ! empty( $metadata['original_image'] ) ) {
			$files[] = $dir . '/' . $metadata['original_image'];
		}
		foreach ( $files as $path ) {
			if ( is_file( $path . '.webp' ) ) {
				@unlink( $path . '.webp' ); // phpcs:ignore
			}
		}
	}

	/**
	 * Converts the next batch of images; used by the bulk tool.
	 */
	public static function bulk_batch( $limit = 5 ) {
		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => array( 'image/jpeg', 'image/png' ),
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'meta_query'     => array( // phpcs:ignore
					array(
						'key'     => self::META,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		$saved = 0;
		foreach ( $ids as $id ) {
			$saved += self::convert_attachment( $id );
		}
		$stats          = self::stats();
		$stats['saved'] = $saved;
		$stats['done']  = empty( $ids ) || $stats['remaining'] <= 0;
		return $stats;
	}

	public static function stats() {
		global $wpdb;
		$total     = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type IN ('image/jpeg','image/png')" ); // phpcs:ignore
		$converted = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = 'attachment' AND p.post_mime_type IN ('image/jpeg','image/png')", self::META ) ); // phpcs:ignore
		$bytes     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META ) ); // phpcs:ignore
		return array(
			'total'     => $total,
			'converted' => $converted,
			'remaining' => max( 0, $total - $converted ),
			'bytes'     => $bytes,
		);
	}

	/**
	 * Forgets conversion state so the bulk tool can run again (files are kept).
	 */
	public static function reset() {
		delete_post_meta_by_key( self::META );
	}
}
