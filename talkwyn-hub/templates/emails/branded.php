<?php
/**
 * Branded HTML email: white body, Plum header with the logo as PNG, Ink text, one Plum button.
 * Table layout and inline styles for email clients. Override by copying to
 * yourtheme/talkwyn-hub/emails/branded.php.
 *
 * @package TalkwynHub
 * @var array<string, mixed> $args heading, body (HTML), keys (string[]), button, url, footer, logo.
 */

defined( 'ABSPATH' ) || exit;

$twh_keys = (array) $args['keys'];
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?php echo esc_html( (string) $args['heading'] ); ?></title>
</head>
<body style="margin:0;padding:0;background:#F7F3F3;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F3F3;">
	<tr>
		<td align="center" style="padding:24px 12px;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border:1px solid #EEE7E8;border-radius:16px;overflow:hidden;">
				<tr>
					<td style="background:#1A0F12;padding:24px 32px;">
						<img src="<?php echo esc_url( (string) $args['logo'] ); ?>" width="180" height="44" alt="Talkwyn" style="display:block;border:0;width:180px;height:auto;">
					</td>
				</tr>
				<tr>
					<td style="padding:32px;font-family:Figtree,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#1A0F12;">
						<?php if ( '' !== (string) $args['heading'] ) : ?>
							<h1 style="margin:0 0 16px;font-size:24px;line-height:1.25;font-weight:800;color:#1A0F12;"><?php echo esc_html( (string) $args['heading'] ); ?></h1>
						<?php endif; ?>
						<div style="color:#1A0F12;"><?php echo wp_kses_post( (string) $args['body'] ); ?></div>
						<?php if ( $twh_keys ) : ?>
							<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;background:#FFF1F2;border-radius:12px;">
								<tr>
									<td style="padding:18px 20px;">
										<p style="margin:0 0 8px;font-size:13px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#6B5E61;"><?php echo esc_html( _n( 'Your license key', 'Your license keys', count( $twh_keys ), 'talkwyn-hub' ) ); ?></p>
										<?php foreach ( $twh_keys as $twh_key ) : ?>
											<p style="margin:6px 0;font-family:'JetBrains Mono',Menlo,Consolas,monospace;font-size:18px;letter-spacing:1px;font-weight:600;color:#1A0F12;"><?php echo esc_html( (string) $twh_key ); ?></p>
										<?php endforeach; ?>
									</td>
								</tr>
							</table>
						<?php endif; ?>
						<?php if ( '' !== (string) $args['url'] && '' !== (string) $args['button'] ) : ?>
							<table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px 0 8px;">
								<tr>
									<td style="border-radius:999px;background:#D7263D;">
										<a href="<?php echo esc_url( (string) $args['url'] ); ?>" style="display:inline-block;padding:14px 28px;font-family:Figtree,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;font-weight:600;color:#FFFFFF;text-decoration:none;border-radius:999px;"><?php echo esc_html( (string) $args['button'] ); ?></a>
									</td>
								</tr>
							</table>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td style="padding:20px 32px;border-top:1px solid #EEE7E8;font-family:Figtree,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13px;line-height:1.5;color:#6B5E61;">
						<?php echo esc_html( (string) $args['footer'] ); ?> · <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:#D7263D;"><?php echo esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>
