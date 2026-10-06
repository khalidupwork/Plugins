<?php
/**
 * PDF report template (rendered by Dompdf: tables + simple CSS only, no flex/grid).
 *
 * Override by copying to yourtheme/credit-market-audit/pdf.php.
 *
 * Available: $audit, $report, $settings, $brand (name, logo, color, dark, on_color), $logo (data URI or '').
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

$scores    = isset( $report['scores'] ) ? $report['scores'] : array();
$overall   = isset( $scores['overall'] ) ? $scores['overall'] : null;
$mobile    = CMA_Audit::psi( $report, 'mobile' );
$desktop   = CMA_Audit::psi( $report, 'desktop' );
$issues    = CMA_Audit::issues_for_report( $report );
$strengths = CMA_Audit::strengths( $report, 8 );
$total     = CMA_Audit::issue_count( $report );
$quick     = 'full' !== $settings['report_mode'];
$host      = wp_parse_url( $audit['url'], PHP_URL_HOST );
$host      = $host ? preg_replace( '/^www\./', '', $host ) : $audit['url'];
$date      = isset( $report['completed_at'] ) ? $report['completed_at'] : $audit['created_at'];
$accent    = $brand['color'];
$dark      = $brand['dark'];

$cards = array(
	__( 'Speed', 'credit-market-audit' )          => isset( $scores['performance'] ) ? $scores['performance'] : null,
	__( 'SEO', 'credit-market-audit' )            => isset( $scores['seo'] ) ? $scores['seo'] : null,
	__( 'Design', 'credit-market-audit' )         => isset( $scores['design'] ) ? $scores['design'] : null,
	__( 'Accessibility', 'credit-market-audit' )  => isset( $scores['accessibility'] ) ? $scores['accessibility'] : null,
	__( 'Best Practices', 'credit-market-audit' ) => isset( $scores['best_practices'] ) ? $scores['best_practices'] : null,
);

$effort_labels = array(
	'easy'   => __( 'Easy fix', 'credit-market-audit' ),
	'medium' => __( 'Quick fix', 'credit-market-audit' ),
	'hard'   => __( 'Larger task', 'credit-market-audit' ),
);

$score_text = static function ( $score ) {
	return null === $score ? '–' : (string) (int) $score;
};
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?php echo esc_html( sprintf( '%s – %s', __( 'Website Audit Report', 'credit-market-audit' ), $host ) ); ?></title>
<style>
	@page { margin: 30px 0 46px 0; }
	* { box-sizing: border-box; }
	body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5px; color: #1e293b; line-height: 1.45; margin: 0; }
	.wrap { padding: 0 38px; }
	table { border-collapse: collapse; width: 100%; }
	td { vertical-align: top; }
	h1, h2, h3, h4 { margin: 0; color: <?php echo esc_html( $dark ); ?>; }

	.topbar { margin-top: -30px; height: 8px; background: <?php echo esc_html( $accent ); ?>; }
	.header td { vertical-align: middle; padding: 22px 0 18px; }
	.logo { max-height: 54px; max-width: 220px; }
	.brand-name { font-size: 18px; font-weight: bold; color: <?php echo esc_html( $dark ); ?>; }
	.doc-title { text-align: right; }
	.doc-title h1 { font-size: 19px; }
	.doc-title .site { font-size: 12px; font-weight: bold; color: <?php echo esc_html( $accent ); ?>; }
	.doc-title .date { font-size: 9px; color: #64748b; }

	.hero { background: <?php echo esc_html( $dark ); ?>; color: #fff; border-radius: 10px; }
	.hero td { vertical-align: middle; padding: 20px 22px; }
	.big-score { width: 108px; height: 82px; padding-top: 26px; border-radius: 54px; background: #fff; text-align: center; }
	.big-score .num { font-size: 38px; font-weight: bold; line-height: 1; }
	.big-score .of { font-size: 8.5px; color: #64748b; margin-top: 3px; }
	.hero .label { text-transform: uppercase; font-size: 8.5px; letter-spacing: 1px; color: #cbd5e1; }
	.hero h2 { color: #fff; font-size: 22px; margin: 2px 0 6px; }
	.hero p { margin: 0; color: #e2e8f0; font-size: 10.5px; }

	.cards { margin-top: 14px; }
	.cards td { width: 20%; text-align: center; padding: 0 4px; }
	.card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 4px 9px; }
	.dot { display: inline-block; width: 44px; height: 31px; padding-top: 13px; border-radius: 22px; color: #fff; font-weight: bold; font-size: 16px; line-height: 1; text-align: center; }
	.card .name { margin-top: 6px; font-weight: bold; font-size: 10px; color: <?php echo esc_html( $dark ); ?>; }

	.section { margin-top: 22px; }
	.section-title { font-size: 15px; padding-bottom: 6px; border-bottom: 2px solid <?php echo esc_html( $accent ); ?>; margin-bottom: 10px; }
	.section-intro { color: #475569; margin: -4px 0 10px; }

	.issue { border: 1px solid #e2e8f0; border-left: 4px solid #f59e0b; border-radius: 6px; margin-bottom: 8px; page-break-inside: avoid; }
	.issue.fail { border-left-color: #ef4444; }
	.issue td { padding: 9px 12px; }
	.issue .n { width: 26px; padding-right: 0; }
	.issue .n span { display: inline-block; width: 20px; height: 15px; padding-top: 5px; border-radius: 10px; background: <?php echo esc_html( $accent ); ?>; color: <?php echo esc_html( $brand['on_color'] ); ?>; text-align: center; font-weight: bold; line-height: 1; font-size: 10px; }
	.issue .t { font-weight: bold; font-size: 11.5px; color: <?php echo esc_html( $dark ); ?>; }
	.issue .r { color: #475569; margin-top: 3px; }
	.tag { display: inline-block; font-size: 8px; font-weight: bold; padding: 2px 7px; border-radius: 8px; background: #ecfdf5; color: #047857; margin-left: 6px; }
	.tag.sec { background: #f1f5f9; color: #475569; }

	.speed td.col { width: 50%; padding-right: 10px; }
	.speed td.col + td.col { padding-right: 0; padding-left: 10px; }
	.box { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; }
	.box h4 { font-size: 12px; margin-bottom: 4px; }
	.metric td { padding: 4px 0; border-bottom: 1px solid #f1f5f9; }
	.metric td.v { text-align: right; font-weight: bold; }
	.shots { margin-top: 12px; }
	.shots td { text-align: center; vertical-align: bottom; padding: 0 6px; }
	.shots img { border: 1px solid #e2e8f0; border-radius: 6px; }
	.shots .cap { font-size: 8px; color: #94a3b8; margin-top: 3px; }

	.good td { width: 50%; padding: 3px 6px 3px 0; }
	.check { color: #16a34a; font-weight: bold; }

	.cta { margin-top: 22px; background: <?php echo esc_html( $accent ); ?>; color: <?php echo esc_html( $brand['on_color'] ); ?>; border-radius: 10px; page-break-inside: avoid; }
	.cta td { padding: 18px 22px; vertical-align: middle; }
	.cta h3 { color: <?php echo esc_html( $brand['on_color'] ); ?>; font-size: 15px; margin-bottom: 4px; }
	.cta a.btn { display: inline-block; background: <?php echo esc_html( $dark ); ?>; color: #fff; text-decoration: none; font-weight: bold; padding: 9px 16px; border-radius: 6px; }
	.note { margin-top: 12px; font-size: 9px; color: #64748b; }
	.footer { position: fixed; bottom: -32px; left: 38px; right: 38px; font-size: 7.5px; color: #94a3b8; }
</style>
</head>
<body>
<div class="footer">
	<?php echo esc_html( $brand['name'] ); ?> · <?php echo esc_html( preg_replace( '#^https?://#', '', home_url() ) ); ?>
</div>

<div class="topbar"></div>
<div class="wrap">

	<table class="header">
		<tr>
			<td>
				<?php if ( $logo ) : ?>
					<img class="logo" src="<?php echo esc_attr( $logo ); ?>" alt="<?php echo esc_attr( $brand['name'] ); ?>">
				<?php else : ?>
					<span class="brand-name"><?php echo esc_html( $brand['name'] ); ?></span>
				<?php endif; ?>
			</td>
			<td class="doc-title">
				<h1><?php esc_html_e( 'Website Audit Report', 'credit-market-audit' ); ?></h1>
				<div class="site"><?php echo esc_html( $host ); ?></div>
				<div class="date"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $date . ' UTC' ) ) ); ?></div>
			</td>
		</tr>
	</table>

	<table class="hero">
		<tr>
			<td style="width: 150px;">
				<div class="big-score">
					<div class="num" style="color: <?php echo esc_attr( CMA_Audit::color( $overall ) ); ?>;"><?php echo esc_html( $score_text( $overall ) ); ?></div>
					<div class="of"><?php esc_html_e( 'out of 100', 'credit-market-audit' ); ?></div>
				</div>
			</td>
			<td>
				<div class="label"><?php esc_html_e( 'Overall score', 'credit-market-audit' ); ?></div>
				<h2><?php echo esc_html( CMA_Audit::grade( $overall ) ); ?></h2>
				<p>
					<?php
					if ( $quick && $issues ) {
						/* translators: %d: number of quick wins */
						echo esc_html( sprintf( _n( 'We found %d quick win that can improve your website without a redesign.', 'We found %d quick wins that can improve your website without a redesign.', count( $issues ), 'credit-market-audit' ), count( $issues ) ) );
					} elseif ( null !== $overall && $overall >= 90 ) {
						esc_html_e( 'Great job! Your website is in excellent shape.', 'credit-market-audit' );
					} else {
						esc_html_e( 'Here is a snapshot of how your website performs for speed, SEO and design.', 'credit-market-audit' );
					}
					?>
				</p>
			</td>
		</tr>
	</table>

	<table class="cards">
		<tr>
			<?php foreach ( $cards as $label => $score ) : ?>
				<td>
					<div class="card">
						<span class="dot" style="background: <?php echo esc_attr( CMA_Audit::color( $score ) ); ?>;"><?php echo esc_html( $score_text( $score ) ); ?></span>
						<div class="name"><?php echo esc_html( $label ); ?></div>
					</div>
				</td>
			<?php endforeach; ?>
		</tr>
	</table>

	<?php if ( $issues ) : ?>
		<div class="section">
			<h3 class="section-title">
				<?php
				echo esc_html(
					$quick
						/* translators: %d: number of items */
						? sprintf( __( 'Your top %d quick wins', 'credit-market-audit' ), count( $issues ) )
						: __( 'Top priority fixes', 'credit-market-audit' )
				);
				?>
			</h3>
			<?php if ( $quick ) : ?>
				<p class="section-intro"><?php esc_html_e( 'Small, high-impact improvements that can be done quickly — no redesign needed.', 'credit-market-audit' ); ?></p>
			<?php endif; ?>
			<?php foreach ( $issues as $i => $issue ) : ?>
				<table class="issue <?php echo esc_attr( $issue['status'] ); ?>">
					<tr>
						<td class="n"><span><?php echo (int) $i + 1; ?></span></td>
						<td>
							<div class="t">
								<?php echo esc_html( $issue['label'] ); ?>
								<span class="tag"><?php echo esc_html( isset( $effort_labels[ $issue['effort'] ] ) ? $effort_labels[ $issue['effort'] ] : '' ); ?></span>
								<span class="tag sec"><?php echo esc_html( $issue['section'] ); ?></span>
							</div>
							<?php if ( $issue['recommendation'] ) : ?>
								<div class="r"><?php echo esc_html( $issue['recommendation'] ); ?></div>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $mobile || $desktop ) : ?>
		<div class="section">
			<div style="page-break-inside: avoid;">
			<h3 class="section-title"><?php esc_html_e( 'Speed snapshot (Google PageSpeed)', 'credit-market-audit' ); ?></h3>
			<table class="speed">
				<tr>
					<?php
					foreach ( array(
						__( 'Mobile', 'credit-market-audit' )  => $mobile,
						__( 'Desktop', 'credit-market-audit' ) => $desktop,
					) as $label => $psi ) :
						?>
						<td class="col">
							<div class="box">
								<table>
									<tr>
										<td style="width: 52px; vertical-align: middle;">
											<span class="dot" style="background: <?php echo esc_attr( CMA_Audit::color( $psi ? $psi['scores']['performance'] : null ) ); ?>;"><?php echo esc_html( $score_text( $psi ? $psi['scores']['performance'] : null ) ); ?></span>
										</td>
										<td style="vertical-align: middle;"><h4><?php echo esc_html( $label ); ?></h4></td>
									</tr>
								</table>
								<?php if ( $psi ) : ?>
									<table class="metric">
										<?php foreach ( array( 'largest-contentful-paint', 'total-blocking-time', 'cumulative-layout-shift' ) as $mid ) : ?>
											<?php
											if ( empty( $psi['metrics'][ $mid ] ) ) {
												continue;
											}
											$metric = $psi['metrics'][ $mid ];
											$mscore = null === $metric['score'] ? null : (int) round( $metric['score'] * 100 );
											?>
											<tr>
												<td><?php echo esc_html( $metric['label'] ); ?></td>
												<td class="v" style="color: <?php echo esc_attr( CMA_Audit::color( $mscore ) ); ?>;"><?php echo esc_html( $metric['value'] ); ?></td>
											</tr>
										<?php endforeach; ?>
									</table>
								<?php else : ?>
									<div class="note"><?php esc_html_e( 'Not available.', 'credit-market-audit' ); ?></div>
								<?php endif; ?>
							</div>
						</td>
					<?php endforeach; ?>
				</tr>
			</table>
			</div>
			<?php if ( ( $desktop && $desktop['screenshot'] ) || ( $mobile && $mobile['screenshot'] ) ) : ?>
				<table class="shots">
					<tr>
						<td style="width: 25%;"></td>
						<?php if ( $desktop && $desktop['screenshot'] ) : ?>
							<td style="width: 340px;">
								<img src="<?php echo esc_attr( $desktop['screenshot'] ); ?>" style="width: 320px;" alt="">
								<div class="cap"><?php esc_html_e( 'Desktop', 'credit-market-audit' ); ?></div>
							</td>
						<?php endif; ?>
						<?php if ( $mobile && $mobile['screenshot'] ) : ?>
							<td style="width: 120px;">
								<img src="<?php echo esc_attr( $mobile['screenshot'] ); ?>" style="width: 100px;" alt="">
								<div class="cap"><?php esc_html_e( 'Mobile', 'credit-market-audit' ); ?></div>
							</td>
						<?php endif; ?>
						<td style="width: 25%;"></td>
					</tr>
				</table>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $strengths ) : ?>
		<div class="section" style="page-break-inside: avoid;">
			<h3 class="section-title"><?php esc_html_e( 'What is already working well', 'credit-market-audit' ); ?></h3>
			<table class="good">
				<?php foreach ( array_chunk( $strengths, 2 ) as $pair ) : ?>
					<tr>
						<?php foreach ( $pair as $label ) : ?>
							<td><span class="check">✓</span> <?php echo esc_html( $label ); ?></td>
						<?php endforeach; ?>
						<?php if ( 1 === count( $pair ) ) : ?>
							<td></td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $settings['cta_url'] ) && ! empty( $settings['cta_text'] ) ) : ?>
		<table class="cta">
			<tr>
				<td>
					<h3><?php esc_html_e( 'Want these fixed for you?', 'credit-market-audit' ); ?></h3>
					<div><?php echo esc_html( wp_strip_all_tags( $settings['cta_message'] ) ); ?></div>
				</td>
				<td style="width: 190px; text-align: right;">
					<a class="btn" href="<?php echo esc_url( $settings['cta_url'] ); ?>"><?php echo esc_html( $settings['cta_text'] ); ?></a>
				</td>
			</tr>
		</table>
	<?php endif; ?>

	<?php if ( $quick && $total > count( $issues ) ) : ?>
		<p class="note">
			<?php
			/* translators: %s: brand name */
			echo esc_html( sprintf( __( 'This free audit focuses on the quickest improvements. Contact %s for a complete, in-depth review.', 'credit-market-audit' ), $brand['name'] ) );
			?>
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $settings['report_footer'] ) ) : ?>
		<div class="note"><?php echo wp_kses_post( wpautop( $settings['report_footer'] ) ); ?></div>
	<?php endif; ?>
	<p class="note"><?php esc_html_e( 'Speed data provided by Google PageSpeed Insights.', 'credit-market-audit' ); ?></p>
</div>
</body>
</html>
