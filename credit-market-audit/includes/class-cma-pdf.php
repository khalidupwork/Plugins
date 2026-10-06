<?php
/**
 * PDF rendering with the bundled Dompdf library.
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns an audit into a branded PDF (templates/pdf.php).
 */
class CMA_PDF {

	/**
	 * Is Dompdf available?
	 *
	 * @return bool
	 */
	public static function available() {
		if ( class_exists( '\Dompdf\Dompdf' ) ) {
			return true;
		}
		$autoload = CMA_PATH . 'vendor/autoload.php';
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		}
		return class_exists( '\Dompdf\Dompdf' );
	}

	/**
	 * PDF bytes for an audit.
	 *
	 * @param array $audit Completed audit row.
	 * @return string|WP_Error
	 */
	public static function render( array $audit ) {
		if ( ! self::available() ) {
			return new WP_Error( 'cma_no_pdf', __( 'PDF library is not available.', 'credit-market-audit' ) );
		}

		$html = self::html( $audit );

		$tmp = self::temp_dir();
		try {
			$options = new \Dompdf\Options();
			$options->set( 'isRemoteEnabled', false );
			$options->set( 'isPhpEnabled', false );
			$options->set( 'isHtml5ParserEnabled', true );
			$options->set( 'defaultFont', 'DejaVu Sans' );
			$options->set( 'defaultPaperSize', 'a4' );
			$options->set( 'dpi', 96 );
			if ( $tmp ) {
				$options->set( 'tempDir', $tmp );
				$options->set( 'fontCache', $tmp );
			}
			$options->set( 'chroot', array( CMA_PATH ) );

			$dompdf = new \Dompdf\Dompdf( $options );
			$dompdf->loadHtml( $html, 'UTF-8' );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();
			self::page_numbers( $dompdf );

			return (string) $dompdf->output();
		} catch ( \Throwable $e ) {
			return new WP_Error( 'cma_pdf_failed', $e->getMessage() );
		}
	}

	/**
	 * HTML fed to Dompdf.
	 *
	 * @param array $audit Audit row.
	 * @return string
	 */
	public static function html( array $audit ) {
		$report   = $audit['report'];
		$settings = CMA_Settings::all();
		$brand    = CMA_Settings::brand();
		$logo     = $brand['logo'] ? self::data_uri( $brand['logo'] ) : '';

		ob_start();
		include CMA_Report::template( 'pdf.php' );
		return ob_get_clean();
	}

	/**
	 * Footer "Page X of Y".
	 *
	 * @param \Dompdf\Dompdf $dompdf Instance.
	 */
	private static function page_numbers( $dompdf ) {
		$canvas = $dompdf->getCanvas();
		$font   = $dompdf->getFontMetrics()->getFont( 'DejaVu Sans' );
		if ( ! $font ) {
			return;
		}
		/* translators: Dompdf placeholders, keep {PAGE_NUM} and {PAGE_COUNT}. */
		$text = __( 'Page {PAGE_NUM} of {PAGE_COUNT}', 'credit-market-audit' );
		$canvas->page_text( $canvas->get_width() - 110, $canvas->get_height() - 28, $text, $font, 7.5, array( 0.58, 0.64, 0.72 ) );
	}

	/**
	 * Embed an image as a data URI (remote loading is disabled in Dompdf for safety).
	 *
	 * @param string $url Image URL.
	 * @return string Empty on failure.
	 */
	public static function data_uri( $url ) {
		if ( 0 === strpos( $url, 'data:image/' ) ) {
			return $url;
		}

		$body    = '';
		$uploads = wp_upload_dir();
		$path    = '';
		if ( ! empty( $uploads['baseurl'] ) && 0 === strpos( set_url_scheme( $url, 'http' ), set_url_scheme( $uploads['baseurl'], 'http' ) ) ) {
			$path = $uploads['basedir'] . substr( set_url_scheme( $url, 'http' ), strlen( set_url_scheme( $uploads['baseurl'], 'http' ) ) );
		}
		$real = $path ? realpath( strtok( $path, '?' ) ) : false;
		if ( $real && 0 === strpos( $real, realpath( $uploads['basedir'] ) ) && is_readable( $real ) ) {
			$body = (string) file_get_contents( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		} else {
			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'             => 10,
					'limit_response_size' => 2 * MB_IN_BYTES,
				)
			);
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$body = (string) wp_remote_retrieve_body( $response );
			}
		}

		if ( '' === $body ) {
			return '';
		}

		$mime = self::sniff_mime( $body );
		return $mime ? 'data:' . $mime . ';base64,' . base64_encode( $body ) : ''; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Detect image type from bytes.
	 *
	 * @param string $bytes Image bytes.
	 * @return string
	 */
	private static function sniff_mime( $bytes ) {
		if ( 0 === strpos( $bytes, "\x89PNG" ) ) {
			return 'image/png';
		}
		if ( 0 === strpos( $bytes, "\xFF\xD8" ) ) {
			return 'image/jpeg';
		}
		if ( 0 === strpos( $bytes, 'GIF8' ) ) {
			return 'image/gif';
		}
		if ( 0 === strpos( $bytes, 'RIFF' ) && 'WEBP' === substr( $bytes, 8, 4 ) ) {
			// Dompdf can't decode WebP everywhere; convert with GD when possible.
			return function_exists( 'imagecreatefromwebp' ) ? 'image/webp' : '';
		}
		if ( false !== stripos( substr( $bytes, 0, 500 ), '<svg' ) ) {
			return 'image/svg+xml';
		}
		return '';
	}

	/**
	 * Writable temp / font-cache dir inside uploads.
	 *
	 * @return string
	 */
	private static function temp_dir() {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}
		$dir = trailingslashit( $uploads['basedir'] ) . 'cma-tmp/dompdf';
		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', '<?php // Silence.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return $dir;
	}
}
