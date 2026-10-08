<?php
/**
 * Lead capture: validation, spam protection, storage and email alerts.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Leads.
 */
class Talkwyn_Leads {

	/**
	 * Validate submitted lead fields. Pure function, unit tested.
	 *
	 * @param array $in Fields: name, email, phone, consent, website (honeypot).
	 * @param array $s  Settings.
	 * @return array{ok:bool,error:string,spam:bool}
	 */
	public static function validate( array $in, array $s ) {
		if ( '' !== trim( (string) ( $in['website'] ?? '' ) ) ) {
			return array(
				'ok'    => false,
				'error' => 'spam',
				'spam'  => true,
			);
		}
		$checks = array(
			array( ! empty( $s['lead_require_name'] ) && '' === trim( (string) ( $in['name'] ?? '' ) ), __( 'Please add your name.', 'talkwyn' ) ),
			array( ! empty( $s['lead_require_email'] ) && ! is_email( (string) ( $in['email'] ?? '' ) ), __( 'Please add a valid email address.', 'talkwyn' ) ),
			array( '' !== trim( (string) ( $in['email'] ?? '' ) ) && ! is_email( (string) ( $in['email'] ?? '' ) ), __( 'Please add a valid email address.', 'talkwyn' ) ),
			array( ! empty( $s['lead_require_phone'] ) && strlen( preg_replace( '/\D/', '', (string) ( $in['phone'] ?? '' ) ) ) < 6, __( 'Please add your phone number.', 'talkwyn' ) ),
			array( ! empty( $s['lead_consent_enabled'] ) && empty( $in['consent'] ), __( 'Please tick the consent box.', 'talkwyn' ) ),
			array( '' === trim( (string) ( $in['name'] ?? '' ) . ( $in['email'] ?? '' ) . ( $in['phone'] ?? '' ) ), __( 'Please add a way to reach you.', 'talkwyn' ) ),
		);
		foreach ( $checks as $check ) {
			if ( $check[0] ) {
				return array(
					'ok'    => false,
					'error' => $check[1],
					'spam'  => false,
				);
			}
		}
		return array(
			'ok'    => true,
			'error' => '',
			'spam'  => false,
		);
	}

	/**
	 * Verify a Cloudflare Turnstile token when the owner set a secret key.
	 *
	 * @param string $token Token from the widget.
	 * @param string $ip    Visitor IP.
	 * @return bool
	 */
	public static function turnstile_ok( $token, $ip ) {
		$secret = (string) Talkwyn_Settings::get( 'turnstile_secret', '' );
		if ( '' === $secret || '' === (string) Talkwyn_Settings::get( 'turnstile_site_key', '' ) ) {
			return true;
		}
		if ( '' === trim( (string) $token ) ) {
			return false;
		}
		$res = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret,
					'response' => (string) $token,
					'remoteip' => $ip,
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return false;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		return ! empty( $data['success'] );
	}

	/**
	 * Store a lead and send the email alert.
	 *
	 * @param array $lead name, email, phone, message, page_url, session_id, consent.
	 * @return int Lead ID, 0 on failure.
	 */
	public static function save( array $lead ) {
		global $wpdb;
		$t    = Talkwyn_DB::tables();
		$row  = array(
			'created_gmt' => current_time( 'mysql', true ),
			'session_id'  => (string) ( $lead['session_id'] ?? '' ),
			'name'        => sanitize_text_field( (string) ( $lead['name'] ?? '' ) ),
			'email'       => sanitize_email( (string) ( $lead['email'] ?? '' ) ),
			'phone'       => sanitize_text_field( (string) ( $lead['phone'] ?? '' ) ),
			'message'     => sanitize_textarea_field( (string) ( $lead['message'] ?? '' ) ),
			'page_url'    => esc_url_raw( (string) ( $lead['page_url'] ?? '' ) ),
			'consent'     => empty( $lead['consent'] ) ? 0 : 1,
			'status'      => 'new',
		);
		$ok = $wpdb->insert( $t['leads'], $row, array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $ok ) {
			return 0;
		}
		$id        = (int) $wpdb->insert_id;
		$row['id'] = $id;
		self::email_alert( $row );

		/**
		 * Fires after a lead was saved. Talkwyn Pro sends Slack and Telegram alerts here.
		 *
		 * @param int   $id  Lead ID.
		 * @param array $row Lead data.
		 */
		do_action( 'talkwyn_lead_saved', $id, $row );
		return $id;
	}

	/**
	 * Email the owner.
	 *
	 * @param array $row Lead.
	 * @return bool
	 */
	private static function email_alert( array $row ) {
		$to = sanitize_email( (string) Talkwyn_Settings::get( 'notification_email', '' ) );
		if ( '' === $to ) {
			return false;
		}
		/* translators: %s: site name */
		$subject = sprintf( __( '[%s] New lead from the chat', 'talkwyn' ), wp_strip_all_tags( get_bloginfo( 'name' ) ) );
		$lines   = array(
			/* translators: %d: lead reference number */
			sprintf( __( 'Reference: #%d', 'talkwyn' ), $row['id'] ),
			__( 'Name:', 'talkwyn' ) . ' ' . $row['name'],
			__( 'Email:', 'talkwyn' ) . ' ' . $row['email'],
			__( 'Phone:', 'talkwyn' ) . ' ' . $row['phone'],
			__( 'Page:', 'talkwyn' ) . ' ' . $row['page_url'],
			'',
			__( 'Question:', 'talkwyn' ),
			$row['message'],
			'',
			__( 'All leads:', 'talkwyn' ) . ' ' . admin_url( 'admin.php?page=talkwyn&tab=leads' ),
		);
		$headers = array();
		if ( is_email( $row['email'] ) ) {
			$headers[] = 'Reply-To: ' . ( '' !== $row['name'] ? str_replace( array( "\r", "\n", '<', '>' ), '', $row['name'] ) . ' ' : '' ) . '<' . $row['email'] . '>';
		}
		return (bool) wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
	}
}
