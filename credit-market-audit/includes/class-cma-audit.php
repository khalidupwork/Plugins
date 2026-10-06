<?php
/**
 * Audit orchestration.
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs an audit as a sequence of short steps so no single HTTP request
 * hits PHP / proxy timeouts (each PageSpeed run can take 10–40 seconds).
 */
class CMA_Audit {

	/**
	 * Step order. Each step requires the previous one to be done.
	 */
	const STEPS = array( 'start', 'mobile', 'desktop', 'finalize' );

	/**
	 * Category weights for the overall score.
	 */
	const WEIGHTS = array(
		'performance'    => 30,
		'seo'            => 30,
		'design'         => 20,
		'accessibility'  => 10,
		'best_practices' => 10,
	);

	/**
	 * Step 1: create the lead and run the on-page analysis.
	 *
	 * @param array $lead name, email, url, ip.
	 * @return array|WP_Error Audit row.
	 */
	public static function start( array $lead ) {
		$analysis = CMA_Analyzer::analyze( $lead['url'] );
		if ( is_wp_error( $analysis ) ) {
			return $analysis;
		}

		$audit = CMA_Repository::create( $lead );
		if ( ! $audit ) {
			return new WP_Error( 'cma_db', __( 'Could not save your request. Please try again.', 'credit-market-audit' ) );
		}

		$report = array(
			'page'       => $analysis['page'],
			'seo'        => $analysis['seo'],
			'design'     => $analysis['design'],
			'psi'        => array(),
			'steps_done' => array( 'start' ),
		);
		CMA_Repository::update(
			$audit['id'],
			array(
				'report' => $report,
				'status' => 'running',
			)
		);
		$audit['report'] = $report;

		return $audit;
	}

	/**
	 * Run one of the later steps.
	 *
	 * @param array  $audit Audit row.
	 * @param string $step  Step name.
	 * @return array|WP_Error Updated audit row.
	 */
	public static function run_step( array $audit, $step ) {
		$report = $audit['report'];
		$done   = isset( $report['steps_done'] ) ? (array) $report['steps_done'] : array();
		$index  = array_search( $step, self::STEPS, true );

		if ( false === $index || 0 === $index ) {
			return new WP_Error( 'cma_step', __( 'Invalid step.', 'credit-market-audit' ) );
		}
		// Idempotent: repeating a finished step just returns the current state.
		if ( in_array( $step, $done, true ) ) {
			return $audit;
		}
		if ( ! in_array( self::STEPS[ $index - 1 ], $done, true ) ) {
			return new WP_Error( 'cma_step', __( 'Audit steps were run out of order.', 'credit-market-audit' ) );
		}

		$url = ! empty( $report['page']['final_url'] ) ? $report['page']['final_url'] : $audit['url'];

		if ( 'mobile' === $step || 'desktop' === $step ) {
			$result = CMA_PageSpeed::run( $url, $step );
			if ( is_wp_error( $result ) ) {
				$result = array( 'error' => $result->get_error_message() );
			}
			$report['psi'][ $step ] = $result;
		}

		$fields = array();
		if ( 'finalize' === $step ) {
			$report                 = self::finalize( $report );
			$report['completed_at'] = current_time( 'mysql', true );
			$fields['status']       = 'complete';
			$fields['overall_score'] = $report['scores']['overall'];
		}

		$report['steps_done'][] = $step;
		$fields['report']       = $report;
		CMA_Repository::update( $audit['id'], $fields );

		$audit           = array_merge( $audit, $fields );
		$audit['report'] = $report;

		if ( 'finalize' === $step ) {
			$sent = CMA_Mailer::send_report( $audit );
			CMA_Mailer::notify_admin( $audit );
			if ( $sent ) {
				CMA_Repository::update( $audit['id'], array( 'email_sent' => 1 ) );
				$audit['email_sent'] = 1;
			}
		}

		return $audit;
	}

	/**
	 * Add PageSpeed-based design checks and compute scores.
	 *
	 * @param array $report Report.
	 * @return array
	 */
	private static function finalize( array $report ) {
		$mobile  = self::psi( $report, 'mobile' );
		$desktop = self::psi( $report, 'desktop' );

		if ( $mobile ) {
			$report['design'] = array_merge( $report['design'], self::psi_design_checks( $mobile ) );
		}

		$own_seo = CMA_Analyzer::score( $report['seo'] );
		$psi_seo = $mobile ? $mobile['scores']['seo'] : null;
		$seo     = null === $psi_seo ? $own_seo : (int) round( $own_seo * 0.7 + $psi_seo * 0.3 );

		$scores = array(
			'performance'         => $mobile ? $mobile['scores']['performance'] : null,
			'performance_desktop' => $desktop ? $desktop['scores']['performance'] : null,
			'seo'                 => $seo,
			'design'              => CMA_Analyzer::score( $report['design'] ),
			'accessibility'       => $mobile ? $mobile['scores']['accessibility'] : ( $desktop ? $desktop['scores']['accessibility'] : null ),
			'best_practices'      => $mobile ? $mobile['scores']['best-practices'] : ( $desktop ? $desktop['scores']['best-practices'] : null ),
		);

		// Fall back to desktop performance if the mobile run failed.
		if ( null === $scores['performance'] && null !== $scores['performance_desktop'] ) {
			$scores['performance'] = $scores['performance_desktop'];
		}

		$sum    = 0;
		$weight = 0;
		foreach ( self::WEIGHTS as $key => $w ) {
			if ( null !== $scores[ $key ] ) {
				$sum    += $scores[ $key ] * $w;
				$weight += $w;
			}
		}
		$scores['overall'] = $weight ? (int) round( $sum / $weight ) : 0;

		$report['scores'] = $scores;
		return $report;
	}

	/**
	 * Successful PSI result for a strategy, or null.
	 *
	 * @param array  $report   Report.
	 * @param string $strategy mobile|desktop.
	 * @return array|null
	 */
	public static function psi( array $report, $strategy ) {
		if ( empty( $report['psi'][ $strategy ] ) || ! empty( $report['psi'][ $strategy ]['error'] ) ) {
			return null;
		}
		return $report['psi'][ $strategy ];
	}

	/**
	 * Design checks derived from Lighthouse (mobile run).
	 *
	 * @param array $psi Normalized PSI data.
	 * @return array[]
	 */
	private static function psi_design_checks( array $psi ) {
		$checks = array();
		$flags  = isset( $psi['audit_flags'] ) ? $psi['audit_flags'] : array();

		$map = array(
			'color-contrast' => array(
				__( 'Colour contrast', 'credit-market-audit' ),
				__( 'Text has enough contrast against its background.', 'credit-market-audit' ),
				__( 'Some text does not have enough contrast with its background.', 'credit-market-audit' ),
				__( 'Increase contrast between text and background colours (WCAG ratio 4.5:1) so content is readable for everyone, including on phones outdoors.', 'credit-market-audit' ),
				3,
			),
			'font-size'      => array(
				__( 'Readable font sizes on mobile', 'credit-market-audit' ),
				__( 'Text is legible on mobile devices.', 'credit-market-audit' ),
				__( 'Some text is too small to read on mobile.', 'credit-market-audit' ),
				__( 'Use a base font size of at least 16px on mobile so visitors don\'t need to pinch-zoom.', 'credit-market-audit' ),
				3,
			),
			'target-size'    => array(
				__( 'Tap targets size', 'credit-market-audit' ),
				__( 'Buttons and links are large enough to tap.', 'credit-market-audit' ),
				__( 'Some buttons or links are too small or too close together.', 'credit-market-audit' ),
				__( 'Make buttons and links at least 48×48px with enough spacing so they are easy to tap on phones.', 'credit-market-audit' ),
				2,
			),
		);

		foreach ( $map as $id => $def ) {
			if ( ! isset( $flags[ $id ] ) || null === $flags[ $id ] ) {
				continue;
			}
			$pass     = $flags[ $id ] >= 0.9;
			$checks[] = CMA_Analyzer::check( 'psi_' . $id, $def[0], $pass ? 'pass' : 'fail', $pass ? $def[1] : $def[2], $def[3], $def[4] );
		}

		if ( isset( $psi['metrics']['cumulative-layout-shift'] ) ) {
			$cls      = $psi['metrics']['cumulative-layout-shift'];
			$score    = null === $cls['score'] ? 1 : $cls['score'];
			$checks[] = CMA_Analyzer::check(
				'psi_cls',
				__( 'Visual stability (layout shift)', 'credit-market-audit' ),
				$score >= 0.9 ? 'pass' : ( $score >= 0.5 ? 'warning' : 'fail' ),
				/* translators: %s: CLS value */
				sprintf( __( 'Cumulative Layout Shift: %s', 'credit-market-audit' ), $cls['value'] ),
				__( 'Elements move while the page loads. Reserve space for images, ads and embeds, and avoid inserting content above existing content.', 'credit-market-audit' ),
				3
			);
		}

		return $checks;
	}

	/**
	 * How much work each check takes to fix. Only easy/medium items are shown
	 * in the "quick" report so prospects see a short, achievable list.
	 */
	const EFFORT = array(
		// SEO.
		'title'            => 'easy',
		'meta_description' => 'easy',
		'h1'               => 'easy',
		'headings'         => 'easy',
		'image_alt'        => 'easy',
		'canonical'        => 'easy',
		'indexable'        => 'easy',
		'lang'             => 'easy',
		'social'           => 'easy',
		'schema'           => 'easy',
		'robots_txt'       => 'easy',
		'sitemap'          => 'easy',
		'compression'      => 'easy',
		'links'            => 'medium',
		'https'            => 'medium',
		'mixed_content'    => 'medium',
		'content'          => 'hard',
		'response_time'    => 'hard',
		// Design.
		'favicon'          => 'easy',
		'mobile_branding'  => 'easy',
		'image_formats'    => 'easy',
		'image_dimensions' => 'easy',
		'conversion'       => 'easy',
		'fonts'            => 'medium',
		'psi_color-contrast' => 'medium',
		'psi_font-size'    => 'medium',
		'psi_target-size'  => 'medium',
		'viewport'         => 'hard',
		'deprecated_html'  => 'hard',
		'inline_styles'    => 'hard',
		'assets'           => 'hard',
		'psi_cls'          => 'hard',
	);

	/**
	 * Lighthouse audits that are usually fixed in minutes (often with a plugin / setting).
	 */
	const EASY_SPEED_AUDITS = array(
		'uses-webp-images',
		'modern-image-formats',
		'uses-optimized-images',
		'uses-responsive-images',
		'offscreen-images',
		'uses-text-compression',
		'unminified-css',
		'unminified-javascript',
		'uses-long-cache-ttl',
		'efficient-animated-content',
		'image-delivery-insight',
		'cache-insight',
	);

	/**
	 * Short list of problems that are quick to fix, ordered by impact.
	 *
	 * @param array $report Report.
	 * @param int   $limit  Max items.
	 * @return array[] { label, recommendation, status, section, effort }
	 */
	public static function quick_wins( array $report, $limit = 5 ) {
		$items = array();
		foreach ( array( 'seo' => 'SEO', 'design' => __( 'Design', 'credit-market-audit' ) ) as $key => $section ) {
			foreach ( isset( $report[ $key ] ) ? $report[ $key ] : array() as $check ) {
				$effort = isset( self::EFFORT[ $check['id'] ] ) ? self::EFFORT[ $check['id'] ] : 'medium';
				if ( 'pass' === $check['status'] || 'hard' === $effort ) {
					continue;
				}
				$items[] = array(
					'label'          => $check['label'],
					'recommendation' => $check['recommendation'],
					'status'         => $check['status'],
					'section'        => $section,
					'effort'         => $effort,
					'rank'           => ( 'fail' === $check['status'] ? 20 : 0 ) + ( 'easy' === $effort ? 10 : 0 ) + (int) $check['weight'],
				);
			}
		}

		// At most two easy speed fixes so the list isn't dominated by technical items.
		$psi = self::psi( $report, 'mobile' );
		$psi = $psi ? $psi : self::psi( $report, 'desktop' );
		if ( $psi ) {
			$added = 0;
			foreach ( $psi['opportunities'] as $op ) {
				if ( $added >= 2 || ! in_array( $op['id'], self::EASY_SPEED_AUDITS, true ) ) {
					continue;
				}
				$items[] = array(
					'label'          => $op['title'],
					'recommendation' => $op['description'],
					'status'         => $op['score'] < 0.5 ? 'fail' : 'warning',
					'section'        => __( 'Speed', 'credit-market-audit' ),
					'effort'         => 'easy',
					'rank'           => ( $op['score'] < 0.5 ? 20 : 0 ) + 10 + 3,
				);
				++$added;
			}
		}

		usort(
			$items,
			static function ( $a, $b ) {
				return $b['rank'] <=> $a['rank'];
			}
		);

		return array_slice( $items, 0, max( 1, (int) $limit ) );
	}

	/**
	 * Labels of checks that passed (shown as "what's working well").
	 *
	 * @param array $report Report.
	 * @param int   $limit  Max items.
	 * @return string[]
	 */
	public static function strengths( array $report, $limit = 8 ) {
		$out = array();
		foreach ( array( 'seo', 'design' ) as $key ) {
			foreach ( isset( $report[ $key ] ) ? $report[ $key ] : array() as $check ) {
				if ( 'pass' === $check['status'] ) {
					$out[ $check['weight'] * 100 + count( $out ) ] = $check['label'];
				}
			}
		}
		krsort( $out );
		return array_slice( array_values( $out ), 0, $limit );
	}

	/**
	 * Total number of problems found (for "N more items in a full audit").
	 *
	 * @param array $report Report.
	 * @return int
	 */
	public static function issue_count( array $report ) {
		$n = 0;
		foreach ( array( 'seo', 'design' ) as $key ) {
			foreach ( isset( $report[ $key ] ) ? $report[ $key ] : array() as $check ) {
				if ( 'pass' !== $check['status'] ) {
					++$n;
				}
			}
		}
		return $n;
	}

	/**
	 * Issues to show for the configured report mode.
	 *
	 * @param array $report Report.
	 * @return array[]
	 */
	public static function issues_for_report( array $report ) {
		$limit = (int) CMA_Settings::get( 'max_issues', 5 );
		return 'full' === CMA_Settings::get( 'report_mode' ) ? self::top_issues( $report, $limit ) : self::quick_wins( $report, $limit );
	}

	/**
	 * Highest priority problems across the report.
	 *
	 * @param array $report Report.
	 * @param int   $limit  Max items.
	 * @return array[] { label, recommendation, status, section }
	 */
	public static function top_issues( array $report, $limit = 6 ) {
		$issues = array();
		foreach ( array( 'seo' => 'SEO', 'design' => __( 'Design', 'credit-market-audit' ) ) as $key => $section ) {
			foreach ( isset( $report[ $key ] ) ? $report[ $key ] : array() as $check ) {
				if ( 'pass' === $check['status'] ) {
					continue;
				}
				$issues[] = array(
					'label'          => $check['label'],
					'recommendation' => $check['recommendation'],
					'status'         => $check['status'],
					'section'        => $section,
					'effort'         => isset( self::EFFORT[ $check['id'] ] ) ? self::EFFORT[ $check['id'] ] : 'medium',
					'rank'           => ( 'fail' === $check['status'] ? 10 : 0 ) + (int) $check['weight'],
				);
			}
		}

		$mobile = self::psi( $report, 'mobile' );
		if ( $mobile ) {
			foreach ( array_slice( $mobile['opportunities'], 0, 3 ) as $op ) {
				$issues[] = array(
					'label'          => $op['title'],
					'recommendation' => $op['description'],
					'status'         => $op['score'] < 0.5 ? 'fail' : 'warning',
					'section'        => __( 'Speed', 'credit-market-audit' ),
					'effort'         => in_array( $op['id'], self::EASY_SPEED_AUDITS, true ) ? 'easy' : 'medium',
					'rank'           => ( $op['score'] < 0.5 ? 10 : 0 ) + 3,
				);
			}
		}

		usort(
			$issues,
			static function ( $a, $b ) {
				return $b['rank'] <=> $a['rank'];
			}
		);

		return array_slice( $issues, 0, $limit );
	}

	/**
	 * Verbal grade for a score.
	 *
	 * @param int|null $score Score.
	 * @return string
	 */
	public static function grade( $score ) {
		if ( null === $score ) {
			return __( 'Not available', 'credit-market-audit' );
		}
		if ( $score >= 90 ) {
			return __( 'Excellent', 'credit-market-audit' );
		}
		if ( $score >= 70 ) {
			return __( 'Good', 'credit-market-audit' );
		}
		if ( $score >= 50 ) {
			return __( 'Needs improvement', 'credit-market-audit' );
		}
		return __( 'Poor', 'credit-market-audit' );
	}

	/**
	 * Colour for a score (green / orange / red).
	 *
	 * @param int|null $score Score.
	 * @return string
	 */
	public static function color( $score ) {
		if ( null === $score ) {
			return '#94a3b8';
		}
		if ( $score >= 90 ) {
			return '#0cce6b';
		}
		if ( $score >= 50 ) {
			return '#ffa400';
		}
		return '#ff4e42';
	}
}
