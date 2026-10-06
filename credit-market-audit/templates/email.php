<?php
/**
 * Visitor email template (table layout + inline styles for email clients).
 *
 * Override by copying to yourtheme/credit-market-audit/email.php.
 *
 * Available: $audit, $report, $settings.
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

$scores = isset( $report['scores'] ) ? $report['scores'] : array();
$brand  = CMA_Settings::brand();
$issues = CMA_Audit::issues_for_report( $report );
$accent = $brand['color'];
$quick  = 'full' !== $settings['report_mode'];
$name   = $audit['name'] ? $audit['name'] : __( 'there', 'credit-market-audit' );
$cats   = array(
	__( 'Performance', 'credit-market-audit' )    => isset( $scores['performance'] ) ? $scores['performance'] : null,
	__( 'SEO', 'credit-market-audit' )            => isset( $scores['seo'] ) ? $scores['seo'] : null,
	__( 'Design', 'credit-market-audit' )         => isset( $scores['design'] ) ? $scores['design'] : null,
	__( 'Accessibility', 'credit-market-audit' )  => isset( $scores['accessibility'] ) ? $scores['accessibility'] : null,
	__( 'Best Practices', 'credit-market-audit' ) => isset( $scores['best_practices'] ) ? $scores['best_practices'] : null,
);
$overall = isset( $scores['overall'] ) ? $scores['overall'] : null;
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
<tr><td align="center">
	<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
		<tr>
			<td style="background:#ffffff;border-top:6px solid <?php echo esc_attr( $accent ); ?>;padding:22px 28px 0;">
				<?php if ( ! empty( $brand['logo'] ) ) : ?>
					<img src="<?php echo esc_url( $brand['logo'] ); ?>" alt="<?php echo esc_attr( $brand['name'] ); ?>" style="max-height:50px;max-width:220px;display:block;">
				<?php else : ?>
					<div style="font-size:20px;font-weight:bold;color:<?php echo esc_attr( $brand['dark'] ); ?>;"><?php echo esc_html( $brand['name'] ); ?></div>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<td style="background:<?php echo esc_attr( $brand['dark'] ); ?>;padding:22px 28px;color:#ffffff;">
				<div style="font-size:22px;font-weight:bold;"><?php esc_html_e( 'Your Website Audit Report', 'credit-market-audit' ); ?></div>
				<div style="font-size:14px;opacity:.9;margin-top:4px;"><?php echo esc_html( $audit['url'] ); ?></div>
			</td>
		</tr>
		<tr>
			<td style="padding:28px;">
				<p style="margin:0 0 12px;font-size:15px;">
					<?php
					/* translators: %s: name */
					echo esc_html( sprintf( __( 'Hi %s,', 'credit-market-audit' ), $name ) );
					?>
				</p>
				<p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#334155;"><?php echo wp_kses_post( $settings['email_intro'] ); ?></p>

				<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:10px;margin-bottom:24px;">
					<tr>
						<td align="center" style="padding:20px;">
							<div style="font-size:13px;text-transform:uppercase;letter-spacing:1px;color:#64748b;"><?php esc_html_e( 'Overall score', 'credit-market-audit' ); ?></div>
							<div style="font-size:56px;font-weight:bold;line-height:1.1;color:<?php echo esc_attr( CMA_Audit::color( $overall ) ); ?>;"><?php echo null === $overall ? '–' : (int) $overall; ?><span style="font-size:20px;color:#94a3b8;">/100</span></div>
							<div style="font-size:16px;font-weight:bold;color:<?php echo esc_attr( CMA_Audit::color( $overall ) ); ?>;"><?php echo esc_html( CMA_Audit::grade( $overall ) ); ?></div>
						</td>
					</tr>
				</table>

				<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;border-collapse:collapse;">
					<?php foreach ( $cats as $label => $score ) : ?>
						<tr>
							<td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:15px;"><?php echo esc_html( $label ); ?></td>
							<td align="right" style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:15px;font-weight:bold;color:<?php echo esc_attr( CMA_Audit::color( $score ) ); ?>;"><?php echo null === $score ? '–' : (int) $score . ' / 100'; ?></td>
						</tr>
					<?php endforeach; ?>
				</table>

				<?php if ( $issues ) : ?>
					<div style="font-size:17px;font-weight:bold;margin-bottom:12px;"><?php echo esc_html( $quick ? __( 'Your quick wins', 'credit-market-audit' ) : __( 'Top priority fixes', 'credit-market-audit' ) ); ?></div>
					<?php foreach ( $issues as $i => $issue ) : ?>
						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:10px;border-left:4px solid <?php echo 'fail' === $issue['status'] ? '#ff4e42' : '#ffa400'; ?>;background:#f8fafc;">
							<tr><td style="padding:10px 14px;">
								<div style="font-size:14px;font-weight:bold;"><?php echo esc_html( ( $i + 1 ) . '. ' . $issue['label'] ); ?> <span style="font-weight:normal;color:#64748b;font-size:12px;">(<?php echo esc_html( $issue['section'] ); ?>)</span></div>
								<?php if ( $issue['recommendation'] ) : ?>
									<div style="font-size:13px;color:#475569;line-height:1.5;margin-top:4px;"><?php echo esc_html( $issue['recommendation'] ); ?></div>
								<?php endif; ?>
							</td></tr>
						</table>
					<?php endforeach; ?>
				<?php endif; ?>

				<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 8px;">
					<tr>
						<td style="background:<?php echo esc_attr( $accent ); ?>;border-radius:8px;">
							<a href="<?php echo esc_url( CMA_Report::view_url( $audit['token'] ) ); ?>" style="display:inline-block;padding:12px 22px;color:<?php echo esc_attr( $brand['on_color'] ); ?>;text-decoration:none;font-weight:bold;font-size:15px;"><?php esc_html_e( 'View your report online', 'credit-market-audit' ); ?></a>
						</td>
					</tr>
				</table>
				<p style="font-size:13px;color:#64748b;margin:0 0 24px;">
					<a href="<?php echo esc_url( CMA_Report::download_url( $audit['token'] ) ); ?>" style="color:<?php echo esc_attr( $accent ); ?>;"><?php echo esc_html( CMA_PDF::available() ? __( 'Download the PDF report', 'credit-market-audit' ) : __( 'Download the report', 'credit-market-audit' ) ); ?></a>
				</p>

				<?php if ( ! empty( $settings['cta_url'] ) && ! empty( $settings['cta_text'] ) ) : ?>
					<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:<?php echo esc_attr( $brand['dark'] ); ?>;border-radius:10px;">
						<tr><td style="padding:22px;color:#ffffff;">
							<div style="font-size:17px;font-weight:bold;margin-bottom:6px;"><?php esc_html_e( 'Want these fixed for you?', 'credit-market-audit' ); ?></div>
							<div style="font-size:14px;line-height:1.5;color:#cbd5e1;margin-bottom:14px;"><?php echo wp_kses_post( $settings['cta_message'] ); ?></div>
							<a href="<?php echo esc_url( $settings['cta_url'] ); ?>" style="display:inline-block;background:<?php echo esc_attr( $accent ); ?>;color:<?php echo esc_attr( $brand['on_color'] ); ?>;text-decoration:none;font-weight:bold;padding:10px 18px;border-radius:8px;font-size:14px;"><?php echo esc_html( $settings['cta_text'] ); ?></a>
						</td></tr>
					</table>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<td style="padding:16px 28px;background:#f8fafc;font-size:12px;color:#94a3b8;">
				<?php
				/* translators: %s: brand name */
				echo esc_html( sprintf( __( 'Sent by %s. You received this email because you requested a free website audit.', 'credit-market-audit' ), $brand['name'] ) );
				?>
			</td>
		</tr>
	</table>
</td></tr>
</table>
</body>
</html>
