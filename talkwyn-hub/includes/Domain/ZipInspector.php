<?php
/**
 * Reads plugin headers from a release ZIP.
 *
 * @package TalkwynHub
 */

namespace TWH\Domain;

/**
 * Finds the main plugin file (a .php file at the ZIP root or one folder deep
 * that contains a "Plugin Name:" header) and parses its headers.
 */
final class ZipInspector {

	/**
	 * Inspect a ZIP file.
	 *
	 * @param string $zip_path Path to the ZIP.
	 * @return array{folder: string, file: string, name: string, version: string, requires_wp: string, requires_php: string, tested_wp: string}|null
	 * @throws \RuntimeException When the zip extension is missing.
	 */
	public static function inspect( string $zip_path ): ?array {
		if ( ! class_exists( \ZipArchive::class ) ) {
			throw new \RuntimeException( 'The PHP zip extension is required.' );
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			return null;
		}

		$result = null;
		$readme = '';
		$count  = $zip->numFiles; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive API.
		for ( $i = 0; $i < $count; $i++ ) {
			$name = (string) $zip->getNameIndex( $i );
			if ( false !== strpos( $name, '..' ) || 0 === strpos( $name, '/' ) || 0 === strpos( $name, '__MACOSX' ) ) {
				continue;
			}
			$depth = substr_count( rtrim( $name, '/' ), '/' );
			if ( $depth > 1 ) {
				continue;
			}
			if ( preg_match( '#^(?:[^/]+/)?readme\.txt$#i', $name ) ) {
				$readme = (string) $zip->getFromIndex( $i, 16384 );
				continue;
			}
			if ( null !== $result || ! preg_match( '/\.php$/i', $name ) ) {
				continue;
			}
			$head    = (string) $zip->getFromIndex( $i, 8192 );
			$headers = self::parse_headers( $head );
			if ( '' !== $headers['name'] ) {
				$result = array_merge(
					$headers,
					array(
						'folder' => $depth ? (string) strstr( $name, '/', true ) : '',
						'file'   => $name,
					)
				);
			}
		}
		$zip->close();

		if ( null !== $result && '' !== $readme ) {
			$readme_headers = self::parse_headers( $readme );
			if ( '' === $result['tested_wp'] ) {
				$result['tested_wp'] = $readme_headers['tested_wp'];
			}
			if ( '' === $result['requires_wp'] ) {
				$result['requires_wp'] = $readme_headers['requires_wp'];
			}
			if ( '' === $result['requires_php'] ) {
				$result['requires_php'] = $readme_headers['requires_php'];
			}
		}
		return $result;
	}

	/**
	 * Parse WordPress-style headers from file contents.
	 *
	 * @param string $contents Contents.
	 * @return array{name: string, version: string, requires_wp: string, requires_php: string, tested_wp: string}
	 */
	public static function parse_headers( string $contents ): array {
		$contents = str_replace( "\r", "\n", $contents );
		$map      = array(
			'name'         => 'Plugin Name',
			'version'      => 'Version',
			'requires_wp'  => 'Requires at least',
			'requires_php' => 'Requires PHP',
			'tested_wp'    => 'Tested up to',
		);
		$out      = array();
		foreach ( $map as $key => $label ) {
			if ( preg_match( '/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote( $label, '/' ) . ':(.*)$/mi', $contents, $m ) ) {
				$out[ $key ] = trim( (string) preg_replace( '/\s*(?:\*\/|\?>).*/', '', $m[1] ) );
			} else {
				$out[ $key ] = '';
			}
		}
		return $out;
	}
}
