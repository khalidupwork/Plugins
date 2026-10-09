<?php
/**
 * Admin dashboard with KPIs and plain-SVG charts.
 *
 * @package TalkwynHub
 */

namespace TWH\Admin;

use TWH\Install\Schema;
use TWH\Repository\Activations;
use TWH\Repository\Events;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- custom tables; only internal table names are concatenated, values always use placeholders.

/**
 * Dashboard screen.
 */
final class Dashboard {

	/**
	 * Gather stats (cached for 10 minutes).
	 *
	 * @return array<string, mixed>
	 */
	public static function stats(): array {
		$cached = get_transient( 'twh_dashboard_stats' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$l     = Schema::table( 'licenses' );
		$e     = Schema::table( 'events' );
		$now   = gmdate( 'Y-m-d H:i:s' );
		$month = gmdate( 'Y-m-01 00:00:00' );
		$in30  = gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS );
		$ago   = gmdate( 'Y-m-d H:i:s', time() - 365 * DAY_IN_SECONDS );
		$since = gmdate( 'Y-m-01 00:00:00', strtotime( '-11 months', strtotime( gmdate( 'Y-m-01' ) ) ) );

		$active   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$l} WHERE status = 'active' AND (expires_at IS NULL OR expires_at > %s)", $now ) );
		$new      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$l} WHERE created_at >= %s", $month ) );
		$expiring = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$l} WHERE status = 'active' AND expires_at > %s AND expires_at <= %s", $now, $in30 ) );
		$expired  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$l} WHERE status = 'expired'" );

		// Renewal rate over the last 12 months: renewals / (renewals + licenses that expired and were not renewed).
		$renewals     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT license_id) FROM {$e} WHERE type = 'renewal' AND created_at >= %s", $ago ) );
		$lapsed       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$l} WHERE status = 'expired' AND expires_at >= %s", $ago ) );
		$renewal_rate = ( $renewals + $lapsed ) > 0 ? round( 100 * $renewals / ( $renewals + $lapsed ), 1 ) : null;

		$new_by_month = array();
		$rows         = (array) $wpdb->get_results( $wpdb->prepare( "SELECT DATE_FORMAT(created_at, '%%Y-%%m') AS m, COUNT(*) AS c FROM {$l} WHERE created_at >= %s GROUP BY m", $since ), ARRAY_A );
		foreach ( $rows as $row ) {
			$new_by_month[ (string) $row['m'] ] = (int) $row['c'];
		}
		$revenue = Events::amounts_by_month( array( 'purchase', 'renewal', 'upgrade', 'refund', 'chargeback' ), $since );

		$months = array();
		for ( $i = 11; $i >= 0; $i-- ) {
			$months[] = gmdate( 'Y-m', strtotime( "-{$i} months", strtotime( gmdate( 'Y-m-01' ) ) ) );
		}
		$series_new = array();
		$series_rev = array();
		foreach ( $months as $m ) {
			$series_new[ $m ] = $new_by_month[ $m ] ?? 0;
			$series_rev[ $m ] = round( $revenue[ $m ] ?? 0.0, 2 );
		}

		$stats = array(
			'active'        => $active,
			'new_month'     => $new,
			'expiring_30'   => $expiring,
			'expired'       => $expired,
			'renewal_rate'  => $renewal_rate,
			'revenue_month' => $series_rev[ gmdate( 'Y-m' ) ] ?? 0.0,
			'revenue_12m'   => array_sum( $series_rev ),
			'active_sites'  => Activations::count_active_sites(),
			'versions'      => Activations::version_distribution(),
			'series_new'    => $series_new,
			'series_rev'    => $series_rev,
		);
		set_transient( 'twh_dashboard_stats', $stats, 10 * MINUTE_IN_SECONDS );
		return $stats;
	}

	/**
	 * Render.
	 */
	public static function render(): void {
		if ( ! current_user_can( Admin::cap() ) ) {
			return;
		}
		$s     = self::stats();
		$money = static function ( float $v ): string {
			return function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $v ) ) : number_format_i18n( $v, 2 );
		};
		$cards = array(
			array( __( 'Active licenses', 'talkwyn-hub' ), number_format_i18n( $s['active'] ), admin_url( 'admin.php?page=twh-licenses&status=active' ) ),
			array( __( 'New this month', 'talkwyn-hub' ), number_format_i18n( $s['new_month'] ), '' ),
			array( __( 'Expiring in 30 days', 'talkwyn-hub' ), number_format_i18n( $s['expiring_30'] ), '' ),
			array( __( 'Expired, not renewed', 'talkwyn-hub' ), number_format_i18n( $s['expired'] ), admin_url( 'admin.php?page=twh-licenses&status=expired' ) ),
			array( __( 'Renewal rate (12 mo)', 'talkwyn-hub' ), null === $s['renewal_rate'] ? 'n/a' : $s['renewal_rate'] . '%', '' ),
			array( __( 'Revenue this month', 'talkwyn-hub' ), $money( (float) $s['revenue_month'] ), '' ),
			array( __( 'Revenue (12 months)', 'talkwyn-hub' ), $money( (float) $s['revenue_12m'] ), '' ),
			array( __( 'Active sites', 'talkwyn-hub' ), number_format_i18n( $s['active_sites'] ), '' ),
		);
		?>
		<div class="wrap twh-wrap">
			<h1><?php esc_html_e( 'Dashboard', 'talkwyn-hub' ); ?></h1>
			<div class="twh-cards">
				<?php foreach ( $cards as $card ) : ?>
					<div class="twh-stat">
						<div class="twh-stat__label"><?php echo esc_html( $card[0] ); ?></div>
						<div class="twh-stat__value">
							<?php if ( $card[2] ) : ?>
								<a href="<?php echo esc_url( $card[2] ); ?>"><?php echo esc_html( $card[1] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $card[1] ); ?>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php $twh_trial = \TWH\Repository\Licenses::trial_stats(); ?>
			<h2><?php esc_html_e( 'Trials', 'talkwyn-hub' ); ?></h2>
			<div class="twh-cards">
				<div class="twh-stat"><div class="twh-stat__label"><?php esc_html_e( 'Trials started', 'talkwyn-hub' ); ?></div><div class="twh-stat__value"><?php echo esc_html( number_format_i18n( $twh_trial['started'] ) ); ?></div></div>
				<div class="twh-stat"><div class="twh-stat__label"><?php esc_html_e( 'Trials active', 'talkwyn-hub' ); ?></div><div class="twh-stat__value"><?php echo esc_html( number_format_i18n( $twh_trial['active'] ) ); ?></div></div>
				<div class="twh-stat"><div class="twh-stat__label"><?php esc_html_e( 'Trial to paid', 'talkwyn-hub' ); ?></div><div class="twh-stat__value"><?php echo esc_html( $twh_trial['converted'] + $twh_trial['ended'] > 0 ? $twh_trial['rate'] . '%' : 'n/a' ); ?></div></div>
				<div class="twh-stat"><div class="twh-stat__label"><?php esc_html_e( 'Average days to convert', 'talkwyn-hub' ); ?></div><div class="twh-stat__value"><?php echo esc_html( $twh_trial['converted'] > 0 ? number_format_i18n( $twh_trial['avg_days'], 1 ) : 'n/a' ); ?></div></div>
			</div>
			<p class="description"><?php esc_html_e( 'Conversion rate counts finished trials only: converted divided by converted plus ended.', 'talkwyn-hub' ); ?></p>
			<div class="twh-charts">
				<div class="twh-panel">
					<h2><?php esc_html_e( 'New licenses per month', 'talkwyn-hub' ); ?></h2>
					<?php echo self::bar_chart( $s['series_new'], '#D7263D' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
				</div>
				<div class="twh-panel">
					<h2><?php esc_html_e( 'Net revenue per month (mapped products)', 'talkwyn-hub' ); ?></h2>
					<?php echo self::bar_chart( $s['series_rev'], '#1A0F12' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
				</div>
				<div class="twh-panel">
					<h2><?php esc_html_e( 'Plugin versions on active sites', 'talkwyn-hub' ); ?></h2>
					<?php echo self::hbar_chart( $s['versions'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
				</div>
			</div>
			<p class="description"><?php esc_html_e( 'Figures are cached for 10 minutes. Revenue is net of refunds and chargebacks and counts orders processed by Talkwyn Hub.', 'talkwyn-hub' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Vertical bar chart (SVG).
	 *
	 * @param array<string, float|int> $series Label => value.
	 * @param string                   $color  Bar color.
	 */
	public static function bar_chart( array $series, string $color ): string {
		$w    = 600;
		$h    = 220;
		$pad  = 28;
		$n    = max( 1, count( $series ) );
		$max  = $series ? max( 1, max( array_map( 'abs', array_values( $series ) ) ) ) : 1;
		$slot = ( $w - 2 * $pad ) / $n;
		$bar  = $slot * 0.6;
		$out  = '<svg class="twh-chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" preserveAspectRatio="xMidYMid meet">';
		$out .= '<line x1="' . $pad . '" y1="' . ( $h - $pad ) . '" x2="' . ( $w - $pad ) . '" y2="' . ( $h - $pad ) . '" stroke="#dcdcde"/>';
		$i    = 0;
		foreach ( $series as $label => $value ) {
			$bh   = max( 0, ( $h - 2 * $pad - 14 ) * max( 0, (float) $value ) / $max );
			$x    = $pad + $i * $slot + ( $slot - $bar ) / 2;
			$y    = $h - $pad - $bh;
			$out .= '<rect x="' . round( $x, 1 ) . '" y="' . round( $y, 1 ) . '" width="' . round( $bar, 1 ) . '" height="' . round( $bh, 1 ) . '" rx="2" fill="' . esc_attr( $color ) . '"><title>' . esc_html( $label . ': ' . $value ) . '</title></rect>';
			if ( (float) $value > 0 ) {
				$out .= '<text x="' . round( $x + $bar / 2, 1 ) . '" y="' . round( $y - 4, 1 ) . '" font-size="10" text-anchor="middle" fill="#50575e">' . esc_html( (string) ( is_float( $value ) ? round( $value ) : $value ) ) . '</text>';
			}
			$out .= '<text x="' . round( $x + $bar / 2, 1 ) . '" y="' . ( $h - $pad + 14 ) . '" font-size="10" text-anchor="middle" fill="#50575e">' . esc_html( substr( (string) $label, 5 ) ) . '</text>';
			++$i;
		}
		return $out . '</svg>';
	}

	/**
	 * Horizontal bar chart (SVG).
	 *
	 * @param array<string, int> $series Label => value.
	 */
	public static function hbar_chart( array $series ): string {
		if ( ! $series ) {
			return '<p class="description">' . esc_html__( 'No active sites yet.', 'talkwyn-hub' ) . '</p>';
		}
		$w   = 600;
		$row = 22;
		$h   = $row * count( $series ) + 10;
		$max = max( 1, max( $series ) );
		$lw  = 90;
		$out = '<svg class="twh-chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img">';
		$i   = 0;
		foreach ( $series as $label => $value ) {
			$y    = 5 + $i * $row;
			$bw   = ( $w - $lw - 60 ) * $value / $max;
			$out .= '<text x="' . ( $lw - 8 ) . '" y="' . ( $y + 14 ) . '" font-size="11" text-anchor="end" fill="#50575e">' . esc_html( (string) $label ) . '</text>';
			$out .= '<rect x="' . $lw . '" y="' . $y . '" width="' . round( $bw, 1 ) . '" height="' . ( $row - 6 ) . '" rx="2" fill="#8c5ad8"/>';
			$out .= '<text x="' . round( $lw + $bw + 6, 1 ) . '" y="' . ( $y + 14 ) . '" font-size="11" fill="#50575e">' . esc_html( number_format_i18n( $value ) ) . '</text>';
			++$i;
		}
		return $out . '</svg>';
	}
}
