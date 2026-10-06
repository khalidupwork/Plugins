<?php
/**
 * Audit report template.
 *
 * Override by copying to yourtheme/credit-market-audit/report.php.
 *
 * Available: $audit (row), $report (decoded report), $settings (plugin settings).
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

$scores  = isset( $report['scores'] ) ? $report['scores'] : array();
$mobile  = CMA_Audit::psi( $report, 'mobile' );
$desktop = CMA_Audit::psi( $report, 'desktop' );
$issues  = CMA_Audit::top_issues( $report, 6 );
$overall = isset( $scores['overall'] ) ? $scores['overall'] : null;
$date    = isset( $report['completed_at'] ) ? $report['completed_at'] : $audit['created_at'];

$cards = array(
	array( __( 'Performance', 'credit-market-audit' ), isset( $scores['performance'] ) ? $scores['performance'] : null, __( 'Mobile speed (Google PageSpeed)', 'credit-market-audit' ) ),
	array( __( 'SEO', 'credit-market-audit' ), isset( $scores['seo'] ) ? $scores['seo'] : null, __( 'On-page search optimisation', 'credit-market-audit' ) ),
	array( __( 'Design & UX', 'credit-market-audit' ), isset( $scores['design'] ) ? $scores['design'] : null, __( 'Mobile-friendliness & visual quality', 'credit-market-audit' ) ),
	array( __( 'Accessibility', 'credit-market-audit' ), isset( $scores['accessibility'] ) ? $scores['accessibility'] : null, __( 'Usable by everyone', 'credit-market-audit' ) ),
	array( __( 'Best Practices', 'credit-market-audit' ), isset( $scores['best_practices'] ) ? $scores['best_practices'] : null, __( 'Security & modern standards', 'credit-market-audit' ) ),
);

$sections = array(
	'seo'    => array( __( 'SEO Check', 'credit-market-audit' ), __( 'How well search engines like Google can find, understand and rank your website.', 'credit-market-audit' ) ),
	'design' => array( __( 'Design & User Experience', 'credit-market-audit' ), __( 'How your website looks and feels for visitors, especially on mobile phones.', 'credit-market-audit' ) ),
);
?>
<div class="cma-report" style="--cma-brand: <?php echo esc_attr( $settings['brand_color'] ); ?>;">

	<header class="cma-report__header">
		<div class="cma-report__brand">
			<?php if ( ! empty( $settings['brand_logo'] ) ) : ?>
				<img src="<?php echo esc_url( $settings['brand_logo'] ); ?>" alt="<?php echo esc_attr( $settings['brand_name'] ); ?>" class="cma-report__logo">
			<?php else : ?>
				<strong class="cma-report__brand-name"><?php echo esc_html( $settings['brand_name'] ); ?></strong>
			<?php endif; ?>
		</div>
		<div class="cma-report__meta">
			<h2 class="cma-report__title"><?php esc_html_e( 'Website Audit Report', 'credit-market-audit' ); ?></h2>
			<p class="cma-report__url"><?php echo esc_html( $audit['url'] ); ?></p>
			<p class="cma-report__date"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $date . ' UTC' ) ) ); ?></p>
		</div>
	</header>

	<section class="cma-report__overall">
		<div class="cma-report__overall-gauge">
			<?php echo CMA_Report::gauge( $overall, 150 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<div class="cma-report__overall-text">
			<span class="cma-report__eyebrow"><?php esc_html_e( 'Overall score', 'credit-market-audit' ); ?></span>
			<h3 style="color: <?php echo esc_attr( CMA_Audit::color( $overall ) ); ?>;"><?php echo esc_html( CMA_Audit::grade( $overall ) ); ?></h3>
			<p>
				<?php
				if ( null !== $overall && $overall >= 90 ) {
					esc_html_e( 'Great job! Your website is in excellent shape. Fix the few remaining items below to stay ahead of competitors.', 'credit-market-audit' );
				} elseif ( null !== $overall && $overall >= 50 ) {
					esc_html_e( 'Your website has a solid base but there are clear opportunities to load faster, rank higher and convert more visitors.', 'credit-market-audit' );
				} else {
					esc_html_e( 'Your website has important issues that are likely costing you visitors, rankings and leads. Start with the priority fixes below.', 'credit-market-audit' );
				}
				?>
			</p>
		</div>
	</section>

	<section class="cma-report__cards">
		<?php foreach ( $cards as $card ) : ?>
			<div class="cma-card">
				<?php echo CMA_Report::gauge( $card[1], 84 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<h4><?php echo esc_html( $card[0] ); ?></h4>
				<p><?php echo esc_html( $card[2] ); ?></p>
			</div>
		<?php endforeach; ?>
	</section>

	<?php if ( $issues ) : ?>
		<section class="cma-report__section">
			<h3 class="cma-report__section-title"><?php esc_html_e( 'Top priority fixes', 'credit-market-audit' ); ?></h3>
			<ol class="cma-issues">
				<?php foreach ( $issues as $issue ) : ?>
					<li class="cma-issue cma-issue--<?php echo esc_attr( $issue['status'] ); ?>">
						<div class="cma-issue__head">
							<?php echo CMA_Report::status_icon( $issue['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<strong><?php echo esc_html( $issue['label'] ); ?></strong>
							<span class="cma-tag"><?php echo esc_html( $issue['section'] ); ?></span>
						</div>
						<?php if ( $issue['recommendation'] ) : ?>
							<p><?php echo esc_html( $issue['recommendation'] ); ?></p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
	<?php endif; ?>

	<section class="cma-report__section">
		<h3 class="cma-report__section-title"><?php esc_html_e( 'Speed (Google PageSpeed Insights)', 'credit-market-audit' ); ?></h3>
		<?php if ( ! $mobile && ! $desktop ) : ?>
			<p class="cma-note">
				<?php esc_html_e( 'Google PageSpeed data could not be retrieved for this website right now.', 'credit-market-audit' ); ?>
				<?php if ( ! empty( $report['psi']['mobile']['error'] ) ) : ?>
					<br><small><?php echo esc_html( $report['psi']['mobile']['error'] ); ?></small>
				<?php endif; ?>
			</p>
		<?php else : ?>
			<div class="cma-speed">
				<?php
				foreach ( array(
					'mobile'  => array( __( 'Mobile', 'credit-market-audit' ), $mobile ),
					'desktop' => array( __( 'Desktop', 'credit-market-audit' ), $desktop ),
				) as $strategy => $data ) :
					list( $label, $psi ) = $data;
					?>
					<div class="cma-speed__col">
						<div class="cma-speed__head">
							<?php echo CMA_Report::gauge( $psi ? $psi['scores']['performance'] : null, 72 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<div>
								<h4><?php echo esc_html( $label ); ?></h4>
								<?php if ( $psi && ! empty( $psi['field'] ) ) : ?>
									<small>
										<?php
										/* translators: %s: FAST/AVERAGE/SLOW */
										echo esc_html( sprintf( __( 'Real-user experience: %s', 'credit-market-audit' ), ucfirst( strtolower( $psi['field'] ) ) ) );
										?>
									</small>
								<?php endif; ?>
							</div>
						</div>
						<?php if ( $psi ) : ?>
							<table class="cma-metrics">
								<tbody>
									<?php foreach ( $psi['metrics'] as $metric ) : ?>
										<?php $mscore = null === $metric['score'] ? null : (int) round( $metric['score'] * 100 ); ?>
										<tr>
											<td><span class="cma-dot" style="background: <?php echo esc_attr( CMA_Audit::color( $mscore ) ); ?>;"></span><?php echo esc_html( $metric['label'] ); ?></td>
											<td class="cma-metrics__value"><?php echo esc_html( $metric['value'] ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php else : ?>
							<p class="cma-note"><?php echo esc_html( ! empty( $report['psi'][ $strategy ]['error'] ) ? $report['psi'][ $strategy ]['error'] : __( 'Not available.', 'credit-market-audit' ) ); ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( ( $mobile && $mobile['screenshot'] ) || ( $desktop && $desktop['screenshot'] ) ) : ?>
				<div class="cma-shots">
					<?php if ( $desktop && $desktop['screenshot'] ) : ?>
						<figure class="cma-shot cma-shot--desktop">
							<img src="<?php echo esc_attr( $desktop['screenshot'] ); ?>" alt="<?php esc_attr_e( 'Desktop screenshot', 'credit-market-audit' ); ?>">
							<figcaption><?php esc_html_e( 'Desktop', 'credit-market-audit' ); ?></figcaption>
						</figure>
					<?php endif; ?>
					<?php if ( $mobile && $mobile['screenshot'] ) : ?>
						<figure class="cma-shot cma-shot--mobile">
							<img src="<?php echo esc_attr( $mobile['screenshot'] ); ?>" alt="<?php esc_attr_e( 'Mobile screenshot', 'credit-market-audit' ); ?>">
							<figcaption><?php esc_html_e( 'Mobile', 'credit-market-audit' ); ?></figcaption>
						</figure>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php $opportunities = $mobile ? $mobile['opportunities'] : ( $desktop ? $desktop['opportunities'] : array() ); ?>
			<?php if ( $opportunities ) : ?>
				<h4 class="cma-report__sub"><?php esc_html_e( 'Speed improvement opportunities', 'credit-market-audit' ); ?></h4>
				<ul class="cma-checks">
					<?php foreach ( $opportunities as $op ) : ?>
						<li class="cma-check">
							<?php echo CMA_Report::status_icon( $op['score'] < 0.5 ? 'fail' : 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<div class="cma-check__body">
								<div class="cma-check__label">
									<?php echo esc_html( $op['title'] ); ?>
									<?php if ( $op['display'] ) : ?>
										<span class="cma-check__value-inline"><?php echo esc_html( $op['display'] ); ?></span>
									<?php endif; ?>
								</div>
								<?php if ( $op['description'] ) : ?>
									<div class="cma-check__rec"><?php echo esc_html( $op['description'] ); ?></div>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>
	</section>

	<?php foreach ( $sections as $key => $section ) : ?>
		<?php
		$checks = isset( $report[ $key ] ) ? $report[ $key ] : array();
		$passed = count(
			array_filter(
				$checks,
				static function ( $c ) {
					return 'pass' === $c['status'];
				}
			)
		);
		?>
		<section class="cma-report__section">
			<div class="cma-report__section-head">
				<div>
					<h3 class="cma-report__section-title"><?php echo esc_html( $section[0] ); ?></h3>
					<p class="cma-report__section-desc"><?php echo esc_html( $section[1] ); ?></p>
				</div>
				<span class="cma-pill">
					<?php
					/* translators: 1: passed checks, 2: total checks */
					echo esc_html( sprintf( __( '%1$d / %2$d passed', 'credit-market-audit' ), $passed, count( $checks ) ) );
					?>
				</span>
			</div>
			<ul class="cma-checks">
				<?php foreach ( $checks as $check ) : ?>
					<li class="cma-check cma-check--<?php echo esc_attr( $check['status'] ); ?>">
						<?php echo CMA_Report::status_icon( $check['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<div class="cma-check__body">
							<div class="cma-check__label"><?php echo esc_html( $check['label'] ); ?></div>
							<div class="cma-check__value"><?php echo esc_html( $check['value'] ); ?></div>
							<?php if ( $check['recommendation'] ) : ?>
								<div class="cma-check__rec"><strong><?php esc_html_e( 'How to fix:', 'credit-market-audit' ); ?></strong> <?php echo esc_html( $check['recommendation'] ); ?></div>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endforeach; ?>

	<?php $a11y = $mobile ? $mobile['accessibility'] : ( $desktop ? $desktop['accessibility'] : array() ); ?>
	<?php if ( $a11y ) : ?>
		<section class="cma-report__section">
			<h3 class="cma-report__section-title"><?php esc_html_e( 'Accessibility issues', 'credit-market-audit' ); ?></h3>
			<ul class="cma-checks">
				<?php foreach ( $a11y as $item ) : ?>
					<li class="cma-check cma-check--fail">
						<?php echo CMA_Report::status_icon( 'fail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<div class="cma-check__body">
							<div class="cma-check__label"><?php echo esc_html( $item['title'] ); ?></div>
							<?php if ( $item['description'] ) : ?>
								<div class="cma-check__rec"><?php echo esc_html( $item['description'] ); ?></div>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $settings['cta_url'] ) && ! empty( $settings['cta_text'] ) ) : ?>
		<section class="cma-cta">
			<div>
				<h3><?php esc_html_e( 'Need help fixing these issues?', 'credit-market-audit' ); ?></h3>
				<p><?php echo wp_kses_post( $settings['cta_message'] ); ?></p>
			</div>
			<a class="cma-cta__btn" href="<?php echo esc_url( $settings['cta_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $settings['cta_text'] ); ?></a>
		</section>
	<?php endif; ?>

	<footer class="cma-report__footer">
		<?php if ( ! empty( $settings['report_footer'] ) ) : ?>
			<div><?php echo wp_kses_post( wpautop( $settings['report_footer'] ) ); ?></div>
		<?php endif; ?>
		<p>
			<?php
			/* translators: %s: brand name */
			echo esc_html( sprintf( __( 'Report generated by %s. Speed data provided by Google PageSpeed Insights.', 'credit-market-audit' ), $settings['brand_name'] ) );
			?>
		</p>
	</footer>
</div>
