<?php
/**
 * Google PageSpeed Insights API client.
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs PSI v5 and normalises the response into a compact array that is safe to store.
 */
class CMA_PageSpeed {

	const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

	/**
	 * Lighthouse metric audits shown in the report.
	 */
	const METRICS = array(
		'first-contentful-paint'   => 'First Contentful Paint',
		'largest-contentful-paint' => 'Largest Contentful Paint',
		'total-blocking-time'      => 'Total Blocking Time',
		'cumulative-layout-shift'  => 'Cumulative Layout Shift',
		'speed-index'              => 'Speed Index',
	);

	/**
	 * Run a PSI test.
	 *
	 * @param string $url      Page URL.
	 * @param string $strategy mobile|desktop.
	 * @return array|WP_Error
	 */
	public static function run( $url, $strategy = 'mobile' ) {
		$query = array(
			'url'      => $url,
			'strategy' => 'desktop' === $strategy ? 'desktop' : 'mobile',
			'locale'   => 'en',
		);
		$key   = CMA_Settings::get( 'psi_api_key' );
		if ( $key ) {
			$query['key'] = $key;
		}

		// PSI expects the `category` parameter repeated, which add_query_arg() can't express.
		$request_url = self::ENDPOINT . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		foreach ( array( 'performance', 'accessibility', 'best-practices', 'seo' ) as $category ) {
			$request_url .= '&category=' . rawurlencode( $category );
		}

		$response = wp_remote_get(
			$request_url,
			array(
				'timeout' => 90,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $body ) || empty( $body['lighthouseResult'] ) ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : sprintf( 'PageSpeed API returned HTTP %d', $code );
			return new WP_Error( 'cma_psi_failed', $message );
		}

		return self::normalize( $body );
	}

	/**
	 * Reduce a PSI response to the parts the report uses.
	 *
	 * @param array $body Decoded PSI JSON.
	 * @return array
	 */
	public static function normalize( array $body ) {
		$lh     = $body['lighthouseResult'];
		$audits = isset( $lh['audits'] ) ? $lh['audits'] : array();
		$cats   = isset( $lh['categories'] ) ? $lh['categories'] : array();

		$scores = array();
		foreach ( array( 'performance', 'accessibility', 'best-practices', 'seo' ) as $id ) {
			$scores[ $id ] = isset( $cats[ $id ]['score'] ) && null !== $cats[ $id ]['score'] ? (int) round( $cats[ $id ]['score'] * 100 ) : null;
		}

		$metrics = array();
		foreach ( self::METRICS as $id => $label ) {
			if ( ! isset( $audits[ $id ] ) ) {
				continue;
			}
			$metrics[ $id ] = array(
				'label' => $label,
				'value' => isset( $audits[ $id ]['displayValue'] ) ? $audits[ $id ]['displayValue'] : '',
				'score' => isset( $audits[ $id ]['score'] ) ? (float) $audits[ $id ]['score'] : null,
			);
		}

		$screenshot = '';
		if ( isset( $audits['final-screenshot']['details']['data'] ) && 0 === strpos( $audits['final-screenshot']['details']['data'], 'data:image/' ) ) {
			$screenshot = $audits['final-screenshot']['details']['data'];
		}

		return array(
			'scores'        => $scores,
			'metrics'       => $metrics,
			'opportunities' => self::failing_audits( $cats, $audits, 'performance', 8 ),
			'accessibility' => self::failing_audits( $cats, $audits, 'accessibility', 8 ),
			'field'         => isset( $body['loadingExperience']['overall_category'] ) ? $body['loadingExperience']['overall_category'] : '',
			'screenshot'    => $screenshot,
			'audit_flags'   => array(
				'color-contrast' => self::audit_score( $audits, 'color-contrast' ),
				'font-size'      => self::audit_score( $audits, 'font-size' ),
				'target-size'    => self::audit_score( $audits, 'target-size' ),
			),
			'fetched_at'    => isset( $lh['fetchTime'] ) ? $lh['fetchTime'] : '',
		);
	}

	/**
	 * Score of a single audit, or null when absent / not applicable.
	 *
	 * @param array  $audits Audits.
	 * @param string $id     Audit id.
	 * @return float|null
	 */
	private static function audit_score( array $audits, $id ) {
		if ( ! isset( $audits[ $id ] ) || ! isset( $audits[ $id ]['score'] ) ) {
			return null;
		}
		if ( isset( $audits[ $id ]['scoreDisplayMode'] ) && in_array( $audits[ $id ]['scoreDisplayMode'], array( 'notApplicable', 'manual', 'informative' ), true ) ) {
			return null;
		}
		return (float) $audits[ $id ]['score'];
	}

	/**
	 * Audits in a category that didn't pass, ordered by estimated savings then score.
	 *
	 * @param array  $cats     Categories.
	 * @param array  $audits   Audits.
	 * @param string $category Category id.
	 * @param int    $limit    Max items.
	 * @return array
	 */
	private static function failing_audits( array $cats, array $audits, $category, $limit ) {
		if ( empty( $cats[ $category ]['auditRefs'] ) ) {
			return array();
		}

		$items = array();
		foreach ( $cats[ $category ]['auditRefs'] as $ref ) {
			$id = $ref['id'];
			// Metric audits are shown separately.
			if ( isset( $ref['group'] ) && 'metrics' === $ref['group'] ) {
				continue;
			}
			if ( ! isset( $audits[ $id ] ) ) {
				continue;
			}
			$audit = $audits[ $id ];
			$mode  = isset( $audit['scoreDisplayMode'] ) ? $audit['scoreDisplayMode'] : '';
			if ( ! isset( $audit['score'] ) || null === $audit['score'] || in_array( $mode, array( 'notApplicable', 'manual', 'informative', 'error' ), true ) ) {
				continue;
			}
			if ( $audit['score'] >= 0.9 ) {
				continue;
			}

			$savings = 0;
			if ( isset( $audit['details']['overallSavingsMs'] ) ) {
				$savings = (float) $audit['details']['overallSavingsMs'];
			} elseif ( isset( $audit['metricSavings'] ) && is_array( $audit['metricSavings'] ) ) {
				$savings = (float) array_sum( array_map( 'floatval', $audit['metricSavings'] ) );
			}

			$items[] = array(
				'id'          => $id,
				'title'       => isset( $audit['title'] ) ? $audit['title'] : $id,
				'description' => self::clean_description( isset( $audit['description'] ) ? $audit['description'] : '' ),
				'display'     => isset( $audit['displayValue'] ) ? $audit['displayValue'] : '',
				'score'       => (float) $audit['score'],
				'savings'     => $savings,
			);
		}

		usort(
			$items,
			static function ( $a, $b ) {
				if ( $a['savings'] !== $b['savings'] ) {
					return $b['savings'] <=> $a['savings'];
				}
				return $a['score'] <=> $b['score'];
			}
		);

		return array_slice( $items, 0, $limit );
	}

	/**
	 * Strip Lighthouse markdown links and trailing "Learn more" text.
	 *
	 * @param string $text Description.
	 * @return string
	 */
	private static function clean_description( $text ) {
		$text = preg_replace( '/\[([^\]]+)\]\([^)]+\)/', '$1', $text );
		$text = preg_replace( '/\s*Learn (more|how)[^.]*\.?\s*$/i', '', $text );
		$text = str_replace( '`', '', $text );
		return trim( wp_strip_all_tags( $text ) );
	}
}
