<?php
/**
 * @package TalkwynHub
 */

namespace TWH\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TWH\Domain\ReferralPolicy;

final class ReferralPolicyTest extends TestCase {

	private const DAY = 86400;

	public function test_no_attribution_without_click_or_coupon(): void {
		$this->assertNull( ReferralPolicy::attribute( null, null ) );
	}

	public function test_cookie_only(): void {
		$this->assertSame(
			array( 'partner_id' => 7, 'source' => 'cookie' ),
			ReferralPolicy::attribute( array( 'partner_id' => 7, 'at' => 100 ), null )
		);
	}

	public function test_coupon_at_checkout_beats_older_click(): void {
		$this->assertSame(
			array( 'partner_id' => 9, 'source' => 'coupon' ),
			ReferralPolicy::attribute( array( 'partner_id' => 7, 'at' => 100 ), array( 'partner_id' => 9, 'at' => 200 ) )
		);
	}

	public function test_last_click_wins(): void {
		$this->assertSame(
			array( 'partner_id' => 7, 'source' => 'cookie' ),
			ReferralPolicy::attribute( array( 'partner_id' => 7, 'at' => 300 ), array( 'partner_id' => 9, 'at' => 200 ) )
		);
	}

	public function test_click_window(): void {
		$now = 1_700_000_000;
		$this->assertTrue( ReferralPolicy::click_valid( $now - 59 * self::DAY, $now, 60 ) );
		$this->assertFalse( ReferralPolicy::click_valid( $now - 61 * self::DAY, $now, 60 ) );
		$this->assertFalse( ReferralPolicy::click_valid( $now + 10, $now, 60 ) );
		$this->assertFalse( ReferralPolicy::click_valid( 0, $now, 60 ) );
	}

	public function test_self_referral_detection(): void {
		$partner = array( 'user_id' => 5, 'email' => 'Sam.Lee@gmail.com', 'fingerprint' => 'fp1' );
		$this->assertTrue( ReferralPolicy::is_self_referral( $partner, array( 'user_id' => 5, 'email' => 'other@x.com', 'fingerprint' => '' ) ) );
		$this->assertTrue( ReferralPolicy::is_self_referral( $partner, array( 'user_id' => 0, 'email' => 'samlee+buy@gmail.com', 'fingerprint' => '' ) ) );
		$this->assertTrue( ReferralPolicy::is_self_referral( $partner, array( 'user_id' => 0, 'email' => 'x@y.com', 'fingerprint' => 'fp1' ) ) );
		$this->assertFalse( ReferralPolicy::is_self_referral( $partner, array( 'user_id' => 0, 'email' => 'x@y.com', 'fingerprint' => '' ) ) );
	}

	public function test_rates(): void {
		$this->assertSame( 20.0, ReferralPolicy::rate( 'new', 20, 0, null ) );
		$this->assertSame( 30.0, ReferralPolicy::rate( 'new', 20, 0, 30.0 ) );
		$this->assertSame( 0.0, ReferralPolicy::rate( 'renewal', 20, 0, 30.0 ) );
		$this->assertSame( 10.0, ReferralPolicy::rate( 'renewal', 20, 10, null ) );
		$this->assertSame( 100.0, ReferralPolicy::rate( 'new', 20, 0, 150.0 ) );
	}

	public function test_commission_rounds_to_cents(): void {
		$this->assertSame( 11.8, ReferralPolicy::commission( 59.0, 20 ) );
		$this->assertSame( 3.33, ReferralPolicy::commission( 9.99, 33.33 ) );
		$this->assertSame( 0.0, ReferralPolicy::commission( -10.0, 20 ) );
	}

	public function test_approval_after_refund_window(): void {
		$now = 1_700_000_000;
		$this->assertFalse( ReferralPolicy::approvable( $now - 29 * self::DAY, $now, 30 ) );
		$this->assertTrue( ReferralPolicy::approvable( $now - 30 * self::DAY, $now, 30 ) );
	}

	public function test_refunds(): void {
		$this->assertSame( 'rejected', ReferralPolicy::after_refund( 'pending', 59.0, 59.0 ) );
		$this->assertSame( 'rejected', ReferralPolicy::after_refund( 'approved', 59.0, 59.0 ) );
		$this->assertSame( 'pending', ReferralPolicy::after_refund( 'pending', 59.0, 20.0 ) );
		$this->assertSame( 'paid', ReferralPolicy::after_refund( 'paid', 59.0, 59.0 ) );
	}

	public function test_payout_threshold(): void {
		$this->assertFalse( ReferralPolicy::can_pay( 49.99, 50 ) );
		$this->assertTrue( ReferralPolicy::can_pay( 50.0, 50 ) );
		$this->assertFalse( ReferralPolicy::can_pay( 0.0, 0 ) );
	}

	public function test_sanitize_code(): void {
		$this->assertSame( 'sam-lee', ReferralPolicy::sanitize_code( '  Sam Lee!! ' ) );
		$this->assertSame( '', ReferralPolicy::sanitize_code( 'ab' ) );
		$this->assertSame( 32, strlen( ReferralPolicy::sanitize_code( str_repeat( 'a', 50 ) ) ) );
	}
}
