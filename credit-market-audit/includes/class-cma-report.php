<?php
/**
 * Report rendering, public view and download endpoints.
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the audit report as HTML (in-page, standalone page, or downloadable file).
 */
class CMA_Report {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ) );
	}

	/**
	 * Public URL of the online report.
	 *
	 * @param string $token Token.
	 * @param array  $args  Extra query args.
	 * @return string
	 */
	public static function view_url( $token, array $args = array() ) {
		return add_query_arg( array_merge( array( 'cma_report' => $token ), $args ), home_url( '/' ) );
	}

	/**
	 * Download URL of the report file.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	public static function download_url( $token ) {
		return self::view_url( $token, array( 'cma_download' => 1 ) );
	}

	/**
	 * File name for downloads / attachments.
	 *
	 * @param array $audit Audit row.
	 * @return string
	 */
	public static function filename( array $audit ) {
		$host = wp_parse_url( $audit['url'], PHP_URL_HOST );
		$host = $host ? preg_replace( '/^www\./', '', $host ) : 'website';
		return sanitize_file_name( sprintf( 'website-audit-%s-%s.html', $host, gmdate( 'Y-m-d', strtotime( $audit['created_at'] ) ) ) );
	}

	/**
	 * Serve ?cma_report=<token> as a standalone page or file download.
	 */
	public static function maybe_serve() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public, token-protected read-only view.
		if ( empty( $_GET['cma_report'] ) ) {
			return;
		}
		$token = sanitize_text_field( wp_unslash( $_GET['cma_report'] ) );
		$audit = CMA_Repository::get_by_token( $token );

		if ( ! $audit || 'complete' !== $audit['status'] ) {
			status_header( 404 );
			nocache_headers();
			wp_die( esc_html__( 'This report could not be found or is still being generated.', 'credit-market-audit' ), esc_html__( 'Report not found', 'credit-market-audit' ), array( 'response' => 404 ) );
		}

		$download = ! empty( $_GET['cma_download'] );
		$print    = ! empty( $_GET['cma_print'] );
		// phpcs:enable

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );

		if ( $download ) {
			header( 'Content-Disposition: attachment; filename="' . self::filename( $audit ) . '"' );
		}

		// Full document; all dynamic values are escaped inside the template.
		echo self::render_document( $audit, $download ? 'download' : ( $print ? 'print' : 'view' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Report body (used inside a page and inside the standalone document).
	 *
	 * @param array $audit Audit row.
	 * @return string
	 */
	public static function render( array $audit ) {
		$settings = CMA_Settings::all();
		$report   = $audit['report'];

		ob_start();
		include self::template( 'report.php' );
		return ob_get_clean();
	}

	/**
	 * Full standalone HTML document with inline CSS.
	 *
	 * @param array  $audit   Audit row.
	 * @param string $context view|print|download|email.
	 * @return string
	 */
	public static function render_document( array $audit, $context = 'view' ) {
		$css      = (string) file_get_contents( CMA_PATH . 'assets/css/cma-report.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$color    = CMA_Settings::get( 'brand_color' );
		$host     = wp_parse_url( $audit['url'], PHP_URL_HOST );
		$title    = sprintf( '%s – %s', __( 'Website Audit Report', 'credit-market-audit' ), $host ? $host : $audit['url'] );
		$body     = self::render( $audit );
		$toolbar  = in_array( $context, array( 'view', 'print' ), true );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="<?php echo esc_attr( get_option( 'blog_charset' ) ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $title ); ?></title>
<style>
:root{--cma-accent:<?php echo esc_html( $color ); ?>;}
body{margin:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;}
.cma-standalone-wrap{max-width:1000px;margin:0 auto;padding:24px 16px 48px;}
.cma-toolbar{display:flex;gap:10px;justify-content:flex-end;margin-bottom:16px;flex-wrap:wrap;}
.cma-toolbar a,.cma-toolbar button{background:var(--cma-accent);color:#fff;border:0;border-radius:8px;padding:10px 16px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;font-family:inherit;}
.cma-toolbar .cma-secondary{background:#fff;color:#0f172a;border:1px solid #cbd5e1;}
@media print{body{background:#fff}.cma-toolbar{display:none!important}.cma-standalone-wrap{padding:0;max-width:none}}
<?php echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static plugin stylesheet. ?>
</style>
</head>
<body>
<div class="cma-standalone-wrap">
		<?php if ( $toolbar ) : ?>
	<div class="cma-toolbar">
		<button type="button" onclick="window.print()"><?php esc_html_e( 'Save as PDF / Print', 'credit-market-audit' ); ?></button>
		<a class="cma-secondary" href="<?php echo esc_url( self::download_url( $audit['token'] ) ); ?>"><?php esc_html_e( 'Download report', 'credit-market-audit' ); ?></a>
	</div>
		<?php endif; ?>
		<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in template. ?>
</div>
		<?php if ( 'print' === $context ) : ?>
<script>window.addEventListener('load',function(){setTimeout(function(){window.print();},400);});</script>
		<?php endif; ?>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Locate a template, allowing theme overrides in /credit-market-audit/.
	 *
	 * @param string $name File name.
	 * @return string
	 */
	public static function template( $name ) {
		$override = locate_template( 'credit-market-audit/' . $name );
		return $override ? $override : CMA_PATH . 'templates/' . $name;
	}

	/**
	 * SVG circular gauge.
	 *
	 * @param int|null $score Score 0-100.
	 * @param int      $size  Pixel size.
	 * @return string
	 */
	public static function gauge( $score, $size = 120 ) {
		$r      = 54;
		$c      = 2 * M_PI * $r;
		$pct    = null === $score ? 0 : max( 0, min( 100, (int) $score ) );
		$offset = $c * ( 1 - $pct / 100 );
		$color  = CMA_Audit::color( $score );
		$label  = null === $score ? '–' : (string) (int) $score;

		return sprintf(
			'<svg class="cma-gauge" width="%1$d" height="%1$d" viewBox="0 0 120 120" role="img" aria-label="%2$s"><circle cx="60" cy="60" r="%3$d" fill="none" stroke="#e2e8f0" stroke-width="10"/><circle cx="60" cy="60" r="%3$d" fill="none" stroke="%4$s" stroke-width="10" stroke-linecap="round" stroke-dasharray="%5$.2f" stroke-dashoffset="%6$.2f" transform="rotate(-90 60 60)"/><text x="60" y="60" text-anchor="middle" dominant-baseline="central" font-size="32" font-weight="700" fill="%4$s" font-family="Arial,Helvetica,sans-serif">%7$s</text></svg>',
			(int) $size,
			esc_attr( null === $score ? __( 'Score not available', 'credit-market-audit' ) : sprintf( '%d / 100', $pct ) ),
			$r,
			esc_attr( $color ),
			$c,
			$offset,
			esc_html( $label )
		);
	}

	/**
	 * Status icon markup.
	 *
	 * @param string $status pass|warning|fail.
	 * @return string
	 */
	public static function status_icon( $status ) {
		$map = array(
			'pass'    => array( '✓', __( 'Passed', 'credit-market-audit' ) ),
			'warning' => array( '!', __( 'Warning', 'credit-market-audit' ) ),
			'fail'    => array( '✕', __( 'Failed', 'credit-market-audit' ) ),
		);
		$def = isset( $map[ $status ] ) ? $map[ $status ] : $map['warning'];
		return sprintf( '<span class="cma-status cma-status--%1$s" title="%3$s" aria-label="%3$s">%2$s</span>', esc_attr( $status ), esc_html( $def[0] ), esc_attr( $def[1] ) );
	}
}
