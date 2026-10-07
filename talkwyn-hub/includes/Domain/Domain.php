<?php
/**
 * Site URL normalization and dev-site detection.
 *
 * @package TalkwynHub
 */

namespace TWH\Domain;

/**
 * Pure helpers; the only WordPress dependency is the optional filter.
 */
final class Domain {

	/**
	 * Default dev/staging host patterns. `*` matches one or more characters.
	 *
	 * @var string[]
	 */
	public const DEFAULT_DEV_PATTERNS = array(
		'localhost',
		'*.localhost',
		'*.local',
		'*.test',
		'*.dev',
		'*.example',
		'*.invalid',
		'staging.*',
		'dev.*',
		'*.wpengine.com',
		'*.kinsta.cloud',
		'*.flywheelsites.com',
		'*.instawp.xyz',
	);

	/**
	 * Normalize a site URL to "host" or "host/path".
	 *
	 * Lowercases, strips scheme, port, `www.`, query, fragment and trailing slashes.
	 * Returns an empty string for input that has no usable host.
	 *
	 * @param string $url Site URL.
	 */
	public static function normalize( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $url ) ) {
			$url = 'http://' . ltrim( $url, '/' );
		}
		$parts = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure helper, runs outside WP in tests.
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}

		$host = strtolower( rtrim( $parts['host'], '.' ) );
		$host = trim( $host, '[]' ); // IPv6 literal.
		if ( function_exists( 'idn_to_ascii' ) && preg_match( '/[^\x20-\x7e]/', $host ) ) {
			$ascii = idn_to_ascii( $host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );
			if ( is_string( $ascii ) && '' !== $ascii ) {
				$host = $ascii;
			}
		}
		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}
		if ( '' === $host || ! preg_match( '/^[a-z0-9.:_-]+$/', $host ) ) {
			return '';
		}

		$path = isset( $parts['path'] ) ? strtolower( trim( $parts['path'], '/' ) ) : '';
		$path = (string) preg_replace( '#/+#', '/', $path );
		$path = (string) preg_replace( '#(^|/)(index\.php|wp-admin.*)$#', '', $path );
		$path = trim( $path, '/' );

		return '' === $path ? $host : $host . '/' . $path;
	}

	/**
	 * Host part of a normalized domain.
	 *
	 * @param string $normalized Normalized domain.
	 */
	public static function host( string $normalized ): string {
		$pos = strpos( $normalized, '/' );
		return false === $pos ? $normalized : substr( $normalized, 0, $pos );
	}

	/**
	 * Whether the normalized domain is a dev/staging site.
	 *
	 * @param string        $normalized Normalized domain.
	 * @param string[]|null $patterns   Patterns; defaults (filtered) when null.
	 */
	public static function is_dev( string $normalized, ?array $patterns = null ): bool {
		$host = self::host( $normalized );
		if ( '' === $host ) {
			return false;
		}
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return true;
		}
		if ( null === $patterns ) {
			$patterns = self::DEFAULT_DEV_PATTERNS;
		}
		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter the dev/staging host patterns. `*` matches one or more characters.
			 *
			 * @param string[] $patterns Patterns.
			 * @param string   $host     Host being checked.
			 */
			$patterns = (array) apply_filters( 'twh_dev_domain_patterns', $patterns, $host );
		}
		foreach ( $patterns as $pattern ) {
			if ( self::matches( $host, (string) $pattern ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Glob-like match of a host against a pattern.
	 *
	 * `*` matches one or more characters. A trailing `.*` (as in `staging.*`)
	 * must match a full domain with at least one dot, so `staging.*` matches
	 * staging.example.com but not the real domains staging.com or dev.to.
	 *
	 * @param string $host    Host.
	 * @param string $pattern Pattern.
	 */
	public static function matches( string $host, string $pattern ): bool {
		$pattern = strtolower( trim( $pattern ) );
		if ( '' === $pattern ) {
			return false;
		}
		$tail = '';
		if ( '.*' === substr( $pattern, -2 ) ) {
			$pattern = substr( $pattern, 0, -2 );
			$tail    = '\.[^.]+\..+';
		}
		$regex = '/^' . str_replace( '\*', '.+', preg_quote( $pattern, '/' ) ) . $tail . '$/';
		return 1 === preg_match( $regex, $host );
	}

	/**
	 * Parse a textarea/comma list of patterns.
	 *
	 * @param string $text Raw text.
	 * @return string[]
	 */
	public static function parse_patterns( string $text ): array {
		$items = preg_split( '/[\s,]+/', strtolower( $text ) );
		$items = array_filter( array_map( 'trim', (array) $items ) );
		return array_values( array_unique( $items ) );
	}
}
