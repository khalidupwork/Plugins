<?php
/**
 * Emails.
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends the report to the visitor and a lead notification to the site owner.
 */
class CMA_Mailer {

	/**
	 * Email the report summary (+ HTML attachment) to the visitor.
	 *
	 * @param array $audit Completed audit row.
	 * @return bool
	 */
	public static function send_report( array $audit ) {
		$settings = CMA_Settings::all();
		if ( empty( $settings['send_user_email'] ) || ! is_email( $audit['email'] ) ) {
			return false;
		}

		$host    = wp_parse_url( $audit['url'], PHP_URL_HOST );
		$subject = str_replace( array( '{domain}', '{score}' ), array( $host ? $host : $audit['url'], (string) $audit['overall_score'] ), $settings['email_subject'] );
		$body    = self::render_email( $audit, $settings );

		$attachments = array();
		$file        = '';
		if ( ! empty( $settings['attach_report'] ) ) {
			$file = self::write_attachment( $audit );
			if ( $file ) {
				$attachments[] = $file;
			}
		}

		$sent = self::send( $audit['email'], $subject, $body, $attachments, $settings );

		if ( $file && file_exists( $file ) ) {
			wp_delete_file( $file );
			@rmdir( dirname( $file ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}

		return $sent;
	}

	/**
	 * Notify the site owner about the new lead.
	 *
	 * @param array $audit Completed audit row.
	 * @return bool
	 */
	public static function notify_admin( array $audit ) {
		$settings = CMA_Settings::all();
		if ( empty( $settings['admin_notify'] ) || ! is_email( $settings['admin_email'] ) ) {
			return false;
		}

		/* translators: 1: website, 2: score */
		$subject = sprintf( __( 'New free audit lead: %1$s (score %2$d)', 'credit-market-audit' ), $audit['url'], (int) $audit['overall_score'] );
		$rows    = array(
			__( 'Name', 'credit-market-audit' )    => $audit['name'] ? $audit['name'] : '—',
			__( 'Email', 'credit-market-audit' )   => $audit['email'],
			__( 'Website', 'credit-market-audit' ) => $audit['url'],
			__( 'Score', 'credit-market-audit' )   => (int) $audit['overall_score'] . ' / 100',
		);

		$html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#0f172a">';
		$html .= '<h2 style="margin:0 0 12px">' . esc_html__( 'New website audit request', 'credit-market-audit' ) . '</h2><table cellpadding="6" style="border-collapse:collapse">';
		foreach ( $rows as $label => $value ) {
			$html .= '<tr><td style="color:#64748b">' . esc_html( $label ) . '</td><td><strong>' . esc_html( $value ) . '</strong></td></tr>';
		}
		$html .= '</table><p><a href="' . esc_url( CMA_Report::view_url( $audit['token'] ) ) . '">' . esc_html__( 'View full report', 'credit-market-audit' ) . '</a> · ';
		$html .= '<a href="' . esc_url( admin_url( 'admin.php?page=cma-leads' ) ) . '">' . esc_html__( 'All leads', 'credit-market-audit' ) . '</a></p></div>';

		$headers = array( 'Reply-To: ' . $audit['email'] );
		return self::send( $settings['admin_email'], $subject, $html, array(), $settings, $headers );
	}

	/**
	 * Render the visitor email body.
	 *
	 * @param array $audit    Audit row.
	 * @param array $settings Settings.
	 * @return string
	 */
	public static function render_email( array $audit, array $settings ) {
		$report = $audit['report'];
		ob_start();
		include CMA_Report::template( 'email.php' );
		return ob_get_clean();
	}

	/**
	 * wp_mail() wrapper with HTML content type and From header.
	 *
	 * @param string $to          Recipient.
	 * @param string $subject     Subject.
	 * @param string $html        HTML body.
	 * @param array  $attachments Files.
	 * @param array  $settings    Settings.
	 * @param array  $headers     Extra headers.
	 * @return bool
	 */
	private static function send( $to, $subject, $html, array $attachments, array $settings, array $headers = array() ) {
		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		if ( is_email( $settings['from_email'] ) ) {
			$name      = str_replace( array( '"', "\r", "\n" ), '', $settings['from_name'] );
			$headers[] = sprintf( 'From: "%s" <%s>', $name, $settings['from_email'] );
		}
		return (bool) wp_mail( $to, wp_specialchars_decode( $subject ), $html, $headers, $attachments );
	}

	/**
	 * Write the standalone report to a temp file for attaching.
	 *
	 * @param array $audit Audit row.
	 * @return string File path or empty string.
	 */
	private static function write_attachment( array $audit ) {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}
		// Unique, unguessable directory so the file name stays clean for the recipient.
		$dir = trailingslashit( $uploads['basedir'] ) . 'cma-tmp/' . wp_generate_password( 20, false );
		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		$file = $dir . '/' . CMA_Report::filename( $audit );
		$ok   = file_put_contents( $file, CMA_Report::render_document( $audit, 'download' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return $ok ? $file : '';
	}
}
