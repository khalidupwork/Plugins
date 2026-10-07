<?php
/**
 * @package TalkwynHub
 */

namespace TWH\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TWH\Domain\ExpiryCalculator as E;

final class ExpiryCalculatorTest extends TestCase {

	private const NOW = 1791331200; // 2026-10-07 00:00:00 UTC.
	private const DAY = 86400;

	public function test_initial(): void {
		$this->assertSame( self::NOW + 365 * self::DAY, E::initial( self::NOW, 365 ) );
		$this->assertNull( E::initial( self::NOW, 0 ), 'Duration 0 is lifetime.' );
	}

	public function test_renew_early_extends_from_current_expiry(): void {
		$expiry = self::NOW + 30 * self::DAY;
		$this->assertSame( $expiry + 365 * self::DAY, E::renew( self::NOW, $expiry, 365 ) );
	}

	public function test_renew_late_extends_from_now(): void {
		$expiry = self::NOW - 40 * self::DAY;
		$this->assertSame( self::NOW + 365 * self::DAY, E::renew( self::NOW, $expiry, 365 ) );
	}

	public function test_renew_exactly_at_expiry(): void {
		$this->assertSame( self::NOW + 365 * self::DAY, E::renew( self::NOW, self::NOW, 365 ) );
	}

	public function test_renew_lifetime_stays_lifetime(): void {
		$this->assertNull( E::renew( self::NOW, null, 365 ) );
		$this->assertNull( E::renew( self::NOW, self::NOW + self::DAY, 0 ) );
	}

	public function test_is_expired(): void {
		$this->assertFalse( E::is_expired( null, self::NOW ) );
		$this->assertFalse( E::is_expired( self::NOW + 1, self::NOW ) );
		$this->assertTrue( E::is_expired( self::NOW, self::NOW ) );
		$this->assertTrue( E::is_expired( self::NOW - 1, self::NOW ) );
	}

	public function test_days_left(): void {
		$this->assertNull( E::days_left( null, self::NOW ) );
		$this->assertSame( 30, E::days_left( self::NOW + 30 * self::DAY, self::NOW ) );
		$this->assertSame( 1, E::days_left( self::NOW + 3600, self::NOW ) );
		$this->assertSame( 0, E::days_left( self::NOW - self::DAY, self::NOW ) );
	}

	public function test_upgrade_price_is_prorated(): void {
		// Personal 49 → Business 99, half the year left: (99 - 49) * 0.5 = 25.
		$expiry = self::NOW + (int) ( 182.5 * self::DAY );
		$this->assertSame( 25.0, E::upgrade_price( 49.0, 99.0, $expiry, 365, self::NOW ) );
		// Full year left: full difference.
		$this->assertSame( 50.0, E::upgrade_price( 49.0, 99.0, self::NOW + 365 * self::DAY, 365, self::NOW ) );
		// Expired: nothing to prorate.
		$this->assertSame( 0.0, E::upgrade_price( 49.0, 99.0, self::NOW - self::DAY, 365, self::NOW ) );
		// Lifetime: full difference.
		$this->assertSame( 150.0, E::upgrade_price( 249.0, 399.0, null, 0, self::NOW ) );
		// Downgrade never yields a negative price.
		$this->assertSame( 0.0, E::upgrade_price( 99.0, 49.0, self::NOW + 100 * self::DAY, 365, self::NOW ) );
		// Expiry beyond one term (e.g. renewed early) is capped at the full difference.
		$this->assertSame( 50.0, E::upgrade_price( 49.0, 99.0, self::NOW + 500 * self::DAY, 365, self::NOW ) );
	}

	public function test_due_reminder(): void {
		$days = array( 30, 7 );
		$this->assertNull( E::due_reminder( self::NOW + 45 * self::DAY, self::NOW, $days, array() ) );
		$this->assertSame( 30, E::due_reminder( self::NOW + 29 * self::DAY, self::NOW, $days, array() ) );
		$this->assertNull( E::due_reminder( self::NOW + 29 * self::DAY, self::NOW, $days, array( 30 ) ) );
		$this->assertSame( 7, E::due_reminder( self::NOW + 6 * self::DAY, self::NOW, $days, array( 30 ) ) );
		// Cron missed the 30-day window: send the 7-day one, never a stale 30-day one.
		$this->assertSame( 7, E::due_reminder( self::NOW + 5 * self::DAY, self::NOW, $days, array() ) );
		$this->assertNull( E::due_reminder( self::NOW + 5 * self::DAY, self::NOW, $days, array( 30, 7 ) ) );
		$this->assertNull( E::due_reminder( self::NOW - 1, self::NOW, $days, array() ), 'Already expired.' );
		$this->assertNull( E::due_reminder( null, self::NOW, $days, array() ), 'Lifetime.' );
	}
}
