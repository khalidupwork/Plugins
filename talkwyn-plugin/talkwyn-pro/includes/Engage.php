<?php
/**
 * Proactive messages and business hours.
 *
 * @package TalkwynPro
 */

namespace TalkwynPro;

defined( 'ABSPATH' ) || exit;

/**
 * Engagement.
 */
final class Engage {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'talkwyn_widget_config', array( self::class, 'config' ) );
		add_filter( 'talkwyn_pre_reply', array( self::class, 'away_reply' ), 1, 2 );
		add_filter( 'talkwyn_reply', array( self::class, 'away_lead' ), 10, 2 );
		add_filter(
			'talkwyn_translatable_keys',
			static function ( $keys ) {
				return array_merge( (array) $keys, array( 'pro_away_label', 'pro_away_message' ) );
			}
		);
	}

	/**
	 * Whether the business is open at a time. Pure, unit tested.
	 *
	 * @param array              $hours Day => open, from, until.
	 * @param \DateTimeInterface $now   Time in the site time zone.
	 */
	public static function is_open( array $hours, \DateTimeInterface $now ): bool {
		$day = strtolower( $now->format( 'D' ) );
		$row = $hours[ $day ] ?? null;
		if ( ! is_array( $row ) || empty( $row['open'] ) ) {
			return false;
		}
		$t     = $now->format( 'H:i' );
		$from  = (string) ( $row['from'] ?? '00:00' );
		$until = (string) ( $row['until'] ?? '23:59' );
		if ( $from <= $until ) {
			return $t >= $from && $t < $until;
		}
		// Overnight hours, for example 22:00 to 02:00.
		return $t >= $from || $t < $until;
	}

	/**
	 * Away right now (business hours on and closed).
	 */
	public static function away(): bool {
		$s = \Talkwyn_Settings::all();
		if ( empty( $s['pro_hours_enabled'] ) ) {
			return false;
		}
		return ! self::is_open( (array) $s['pro_hours'], new \DateTimeImmutable( 'now', wp_timezone() ) );
	}

	/**
	 * Widget configuration.
	 *
	 * @param array $config Config.
	 * @return array
	 */
	public static function config( array $config ): array {
		$s                       = \Talkwyn_Settings::all();
		$config['pro']           = $config['pro'] ?? array();
		$config['pro']['rules']  = array_values( (array) ( $s['pro_proactive'] ?? array() ) );
		$config['pro']['away']   = self::away();
		$config['pro']['awayLabel'] = \Talkwyn_I18n::translate( 'pro_away_label', (string) $s['pro_away_label'] );
		return $config;
	}

	/**
	 * Away and lead-only: answer with the away message.
	 *
	 * @param array|null $preset Preset.
	 * @param array      $ctx    Context.
	 * @return array|null
	 */
	public static function away_reply( $preset, $ctx ) {
		if ( is_array( $preset ) || ! \Talkwyn_Settings::get( 'pro_away_lead_only' ) || ! self::away() ) {
			return $preset;
		}
		$flags = \Talkwyn_History::flags( (string) $ctx['session'] );
		if ( ! empty( $flags['lead_submitted'] ) ) {
			return $preset;
		}
		return array(
			'text'     => \Talkwyn_I18n::translate( 'pro_away_message', (string) \Talkwyn_Settings::get( 'pro_away_message' ) ),
			'provider' => 'away',
		);
	}

	/**
	 * Away and lead-only: show the lead form right away.
	 *
	 * @param array $data Reply data.
	 * @param array $ctx  Context.
	 * @return array
	 */
	public static function away_lead( $data, $ctx ) {
		if ( 'away' !== ( $data['provider'] ?? '' ) ) {
			return $data;
		}
		$session = (string) $ctx['session'];
		$flags   = \Talkwyn_History::flags( $session );
		if ( ! empty( $flags['lead_form'] ) || ! \Talkwyn_Settings::get( 'lead_enabled' ) ) {
			return $data;
		}
		\Talkwyn_History::set_flag( $session, 'lead_offered', 1 );
		\Talkwyn_History::set_flag( $session, 'lead_form', 1 );
		\Talkwyn_History::add(
			$session,
			array(
				array(
					'role'    => 'assistant',
					'content' => '',
					'kind'    => 'lead_form',
				),
			)
		);
		$data['lead_offer']  = true;
		$data['lead_direct'] = true;
		return $data;
	}
}
