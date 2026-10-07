<?php
/**
 * @package TalkwynHub
 */

namespace TWH\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TWH\Domain\TrialPolicy;

final class TrialPolicyTest extends TestCase {

	private const DAY = 86400;

	public function test_canonical_email_strips_tags_and_gmail_dots(): void {
		$this->assertSame( 'janedoe@gmail.com', TrialPolicy::canonical_email( ' Jane.Doe+trial@GoogleMail.com ' ) );
		$this->assertSame( 'jane.doe@example.com', TrialPolicy::canonical_email( 'jane.doe+x@example.com' ) );
		$this->assertSame( 'no-at-sign', TrialPolicy::canonical_email( 'no-at-sign' ) );
	}

	public function test_parse_list_accepts_lines_and_commas(): void {
		$this->assertSame( array( 'a.com', 'b.net', 'c.org' ), TrialPolicy::parse_list( "A.com, b.net\n@c.org\n\na.com" ) );
	}

	public function test_disposable_matches_domain_and_subdomains(): void {
		$list = array( 'mailinator.com' );
		$this->assertTrue( TrialPolicy::is_disposable( 'x@mailinator.com', $list ) );
		$this->assertTrue( TrialPolicy::is_disposable( 'x@eu.mailinator.com', $list ) );
		$this->assertFalse( TrialPolicy::is_disposable( 'x@notmailinator.com', $list ) );
		$this->assertFalse( TrialPolicy::is_disposable( 'x@gmail.com', $list ) );
	}

	public function test_eligibility_order_of_checks(): void {
		$d = TrialPolicy::DEFAULT_DISPOSABLE;
		$this->assertSame( 'invalid_email', TrialPolicy::eligibility( 'not-an-email', 'a.com', '', $d ) );
		$this->assertSame( 'disposable_email', TrialPolicy::eligibility( 'x@yopmail.com', 'a.com', '', $d ) );
		$this->assertSame( 'invalid_site', TrialPolicy::eligibility( 'x@a.com', '', '', $d ) );
		$this->assertSame( 'invalid_site', TrialPolicy::eligibility( 'x@a.com', 'mysite.local', '', $d, true ) );
		$this->assertSame( 'invalid_site', TrialPolicy::eligibility( 'x@a.com', 'localhost', '', $d ) );
		$this->assertSame( 'email_used', TrialPolicy::eligibility( 'x@a.com', 'a.com', 'email', $d ) );
		$this->assertSame( 'domain_used', TrialPolicy::eligibility( 'x@a.com', 'a.com', 'domain', $d ) );
		$this->assertSame( '', TrialPolicy::eligibility( 'x@a.com', 'a.com', '', $d ) );
	}

	public function test_days_left_rounds_up_and_never_negative(): void {
		$now = 1_700_000_000;
		$this->assertSame( 15, TrialPolicy::days_left( $now + 15 * self::DAY, $now ) );
		$this->assertSame( 1, TrialPolicy::days_left( $now + 60, $now ) );
		$this->assertSame( 0, TrialPolicy::days_left( $now, $now ) );
		$this->assertSame( 0, TrialPolicy::days_left( $now - self::DAY, $now ) );
	}

	public function test_due_reminder_sends_each_threshold_once(): void {
		$now  = 1_700_000_000;
		$ends = $now + 5 * self::DAY;
		$this->assertSame( 5, TrialPolicy::due_reminder( $ends, $now, array( 5, 2 ), array() ) );
		$this->assertNull( TrialPolicy::due_reminder( $ends, $now, array( 5, 2 ), array( 5 ) ) );
		$this->assertNull( TrialPolicy::due_reminder( $now + 9 * self::DAY, $now, array( 5, 2 ), array() ) );
	}

	public function test_due_reminder_skips_stale_bigger_threshold(): void {
		$now = 1_700_000_000;
		// Cron missed the 5-day mark: only the 2-day reminder goes out.
		$this->assertSame( 2, TrialPolicy::due_reminder( $now + 2 * self::DAY, $now, array( 5, 2 ), array() ) );
	}

	public function test_no_reminder_after_expiry(): void {
		$now = 1_700_000_000;
		$this->assertNull( TrialPolicy::due_reminder( $now - 10, $now, array( 5, 2 ), array() ) );
	}
}
