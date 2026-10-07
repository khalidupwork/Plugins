<?php
/**
 * Trial rules (pure, no WordPress).
 *
 * @package TalkwynHub
 */

namespace TWH\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Eligibility, disposable email detection and reminder timing for the Pro trial.
 */
final class TrialPolicy {

	/**
	 * Common throwaway email domains. Editable in Hub settings.
	 */
	public const DEFAULT_DISPOSABLE = array(
		'mailinator.com',
		'guerrillamail.com',
		'guerrillamail.net',
		'sharklasers.com',
		'10minutemail.com',
		'10minutemail.net',
		'tempmail.com',
		'temp-mail.org',
		'tempmail.net',
		'throwawaymail.com',
		'yopmail.com',
		'yopmail.net',
		'getnada.com',
		'dispostable.com',
		'maildrop.cc',
		'trashmail.com',
		'fakeinbox.com',
		'mintemail.com',
		'mohmal.com',
		'emailondeck.com',
		'mailnesia.com',
		'spamgourmet.com',
		'tempinbox.com',
		'mytemp.email',
		'burnermail.io',
	);

	/**
	 * Lowercase, trimmed email. Gmail-style dots and +tags are removed for the
	 * "one trial per email" check, so name+1@gmail.com counts as name@gmail.com.
	 *
	 * @param string $email Email.
	 */
	public static function canonical_email( string $email ): string {
		$email = strtolower( trim( $email ) );
		if ( false === strpos( $email, '@' ) ) {
			return $email;
		}
		list( $local, $host ) = explode( '@', $email, 2 );
		$plus                 = strpos( $local, '+' );
		if ( false !== $plus ) {
			$local = substr( $local, 0, $plus );
		}
		if ( in_array( $host, array( 'gmail.com', 'googlemail.com' ), true ) ) {
			$local = str_replace( '.', '', $local );
			$host  = 'gmail.com';
		}
		return $local . '@' . $host;
	}

	/**
	 * Parse the editable disposable list (one domain per line or comma separated).
	 *
	 * @param string $raw Raw setting.
	 * @return string[]
	 */
	public static function parse_list( string $raw ): array {
		$items = preg_split( '/[\s,]+/', strtolower( $raw ) );
		$items = array_map(
			static function ( $d ) {
				return ltrim( trim( (string) $d ), '@.' );
			},
			is_array( $items ) ? $items : array()
		);
		return array_values( array_unique( array_filter( $items ) ) );
	}

	/**
	 * Whether the email's domain (or a parent domain) is on the disposable list.
	 *
	 * @param string   $email Email.
	 * @param string[] $list  Disposable domains.
	 */
	public static function is_disposable( string $email, array $list ): bool {
		$at = strrpos( $email, '@' );
		if ( false === $at ) {
			return false;
		}
		$host = strtolower( substr( $email, $at + 1 ) );
		foreach ( $list as $domain ) {
			if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Eligibility decision.
	 *
	 * @param string   $email        Email entered.
	 * @param string   $domain       Normalized site domain ('' if unknown).
	 * @param string   $used         Result of the reuse lookup: '', 'email' or 'domain'.
	 * @param string[] $disposable   Disposable domains.
	 * @param bool     $domain_is_dev Whether the domain is a local or staging host.
	 * @return string '' when eligible, otherwise an error code.
	 */
	public static function eligibility( string $email, string $domain, string $used, array $disposable, bool $domain_is_dev = false ): string {
		if ( ! preg_match( '/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', trim( $email ) ) ) {
			return 'invalid_email';
		}
		if ( self::is_disposable( $email, $disposable ) ) {
			return 'disposable_email';
		}
		// A trial is for a public site: needs a dotted host name, not localhost or a dev host.
		if ( '' === $domain || $domain_is_dev || false === strpos( $domain, '.' ) ) {
			return 'invalid_site';
		}
		if ( 'email' === $used ) {
			return 'email_used';
		}
		if ( 'domain' === $used ) {
			return 'domain_used';
		}
		return '';
	}

	/**
	 * Whole days left in a trial (rounded up, never negative).
	 *
	 * @param int $ends_at Trial end timestamp.
	 * @param int $now     Now.
	 */
	public static function days_left( int $ends_at, int $now ): int {
		if ( $ends_at <= $now ) {
			return 0;
		}
		return (int) ceil( ( $ends_at - $now ) / 86400 );
	}

	/**
	 * Which "days left" reminder is due now, if any.
	 *
	 * @param int   $ends_at   Trial end timestamp.
	 * @param int   $now       Now.
	 * @param int[] $thresholds Days-left thresholds, e.g. [5, 2].
	 * @param int[] $sent      Thresholds already sent.
	 * @return int|null The threshold to send, or null.
	 */
	public static function due_reminder( int $ends_at, int $now, array $thresholds, array $sent ): ?int {
		$left = self::days_left( $ends_at, $now );
		if ( 0 === $left ) {
			return null;
		}
		rsort( $thresholds );
		$due = null;
		foreach ( $thresholds as $t ) {
			// Send the smallest threshold reached that was not sent yet (skip stale bigger ones).
			if ( $left <= $t && ! in_array( $t, $sent, true ) ) {
				$due = $t;
			}
		}
		return $due;
	}
}
