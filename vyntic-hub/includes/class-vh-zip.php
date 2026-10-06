<?php
/**
 * Reads a plugin zip: folder name, plugin headers and readme.txt data.
 *
 * @package VynticHub
 */

defined( 'ABSPATH' ) || exit;

class VH_Zip {

	/**
	 * @return array|WP_Error
	 */
	public static function inspect( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'vh_zip', __( 'The PHP zip extension is missing on this server.', 'vyntic-hub' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'vh_zip', __( 'This file is not a valid zip.', 'vyntic-hub' ) );
		}

		$folders = array();
		$files   = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = (string) $zip->getNameIndex( $i );
			if ( '' === $name || 0 === strpos( $name, '__MACOSX/' ) || false !== strpos( $name, '..' ) ) {
				continue;
			}
			$parts = explode( '/', $name );
			if ( count( $parts ) < 2 ) {
				$zip->close();
				return new WP_Error( 'vh_zip', __( 'Files must be inside one plugin folder (zip the folder, not its contents).', 'vyntic-hub' ) );
			}
			$folders[ $parts[0] ] = true;
			$files[]              = $name;
		}
		if ( 1 !== count( $folders ) ) {
			$zip->close();
			return new WP_Error( 'vh_zip', __( 'The zip must contain exactly one plugin folder.', 'vyntic-hub' ) );
		}
		$folder = (string) key( $folders );

		// Main plugin file: a top-level PHP file with a "Plugin Name:" header.
		$headers = null;
		foreach ( $files as $name ) {
			if ( ! preg_match( '#^' . preg_quote( $folder, '#' ) . '/[^/]+\.php$#', $name ) ) {
				continue;
			}
			$data = self::headers( (string) $zip->getFromName( $name ), array(
				'name'         => 'Plugin Name',
				'version'      => 'Version',
				'requires'     => 'Requires at least',
				'requires_php' => 'Requires PHP',
				'description'  => 'Description',
				'tested'       => 'Tested up to',
			) );
			if ( '' !== $data['name'] ) {
				$headers         = $data;
				$headers['file'] = basename( $name );
				break;
			}
		}
		if ( ! $headers ) {
			$zip->close();
			return new WP_Error( 'vh_zip', __( 'No main plugin file (with a "Plugin Name:" header) was found.', 'vyntic-hub' ) );
		}
		if ( '' === $headers['version'] ) {
			$zip->close();
			return new WP_Error( 'vh_zip', __( 'The main plugin file has no "Version:" header.', 'vyntic-hub' ) );
		}

		$readme    = (string) $zip->getFromName( $folder . '/readme.txt' );
		$zip->close();
		$changelog = '';
		if ( '' !== $readme ) {
			$extra = self::headers( $readme, array(
				'tested'       => 'Tested up to',
				'requires'     => 'Requires at least',
				'requires_php' => 'Requires PHP',
			) );
			foreach ( $extra as $key => $value ) {
				if ( '' === $headers[ $key ] && '' !== $value ) {
					$headers[ $key ] = $value;
				}
			}
			$changelog = self::readme_changelog( $readme, $headers['version'] );
		}

		return array(
			'folder'       => $folder,
			'file'         => $headers['file'],
			'name'         => $headers['name'],
			'version'      => $headers['version'],
			'requires'     => $headers['requires'],
			'requires_php' => $headers['requires_php'],
			'tested'       => $headers['tested'],
			'description'  => $headers['description'],
			'changelog'    => $changelog,
			'size'         => (int) @filesize( $path ), // phpcs:ignore
		);
	}

	/**
	 * Same idea as get_file_data(), but on a string.
	 */
	private static function headers( $contents, array $wanted ) {
		$contents = substr( str_replace( "\r", "\n", $contents ), 0, 8192 );
		$out      = array();
		foreach ( $wanted as $key => $label ) {
			$out[ $key ] = '';
			if ( preg_match( '/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote( $label, '/' ) . ':(.*)$/mi', $contents, $m ) ) {
				$out[ $key ] = trim( preg_replace( '/\s*(?:\*\/|\?>).*/', '', $m[1] ) );
			}
		}
		return $out;
	}

	/**
	 * The "= x.y.z =" block of a readme changelog for one version.
	 */
	private static function readme_changelog( $readme, $version ) {
		if ( ! preg_match( '/==\s*Changelog\s*==(.*?)(?:\n==[^=]|\z)/is', $readme, $m ) ) {
			return '';
		}
		if ( preg_match( '/=\s*' . preg_quote( $version, '/' ) . '\s*=(.*?)(?:\n=\s*[^=\n]+=|\z)/s', $m[1], $v ) ) {
			return trim( $v[1] );
		}
		return '';
	}
}
