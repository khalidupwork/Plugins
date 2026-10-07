<?php
/**
 * @package TalkwynHub
 */

namespace TWH\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TWH\Domain\ActivationPolicy;

final class ActivationPolicyTest extends TestCase {

	private static function row( int $id, string $instance, string $domain, bool $dev = false, ?string $deactivated = null ): array {
		return array(
			'id'                => $id,
			'instance_id'       => $instance,
			'domain_normalized' => $domain,
			'is_dev_site'       => $dev ? '1' : '0',
			'deactivated_at'    => $deactivated,
		);
	}

	public function test_first_activation_creates(): void {
		$d = ActivationPolicy::decide( 1, array(), 'inst-1', 'a.com', false );
		$this->assertSame( ActivationPolicy::ACTION_CREATE, $d['action'] );
	}

	public function test_limit_reached_denies_new_site(): void {
		$rows = array( self::row( 1, 'inst-1', 'a.com' ) );
		$d    = ActivationPolicy::decide( 1, $rows, 'inst-2', 'b.com', false );
		$this->assertSame( ActivationPolicy::ACTION_DENY, $d['action'] );
	}

	public function test_reactivating_same_instance_reuses_row_even_at_limit(): void {
		$rows = array( self::row( 7, 'inst-1', 'a.com' ) );
		$d    = ActivationPolicy::decide( 1, $rows, 'inst-1', 'a.com', false );
		$this->assertSame( ActivationPolicy::ACTION_REUSE, $d['action'] );
		$this->assertSame( 7, $d['activation_id'] );
	}

	public function test_same_domain_new_instance_reuses_row(): void {
		$rows = array( self::row( 7, 'inst-old', 'a.com' ) );
		$d    = ActivationPolicy::decide( 1, $rows, 'inst-new', 'a.com', false );
		$this->assertSame( ActivationPolicy::ACTION_REUSE, $d['action'] );
		$this->assertSame( 7, $d['activation_id'] );
	}

	public function test_instance_match_wins_over_domain_match(): void {
		$rows = array(
			self::row( 1, 'inst-a', 'shared.com' ),
			self::row( 2, 'inst-b', 'other.com' ),
		);
		$d = ActivationPolicy::decide( 5, $rows, 'inst-b', 'shared.com', false );
		$this->assertSame( 2, $d['activation_id'] );
	}

	public function test_dev_sites_do_not_count_and_are_always_allowed(): void {
		$rows = array(
			self::row( 1, 'inst-1', 'a.com' ),
			self::row( 2, 'inst-2', 'a.local', true ),
			self::row( 3, 'inst-3', 'staging.a.com', true ),
		);
		$this->assertSame( 1, ActivationPolicy::count_used( $rows ) );
		$this->assertSame( ActivationPolicy::ACTION_CREATE, ActivationPolicy::decide( 1, $rows, 'inst-4', 'b.test', true )['action'] );
		$this->assertSame( ActivationPolicy::ACTION_DENY, ActivationPolicy::decide( 1, $rows, 'inst-5', 'b.com', false )['action'] );
	}

	public function test_deactivated_rows_are_ignored(): void {
		$rows = array( self::row( 1, 'inst-1', 'a.com', false, '2026-01-01 00:00:00' ) );
		$this->assertSame( 0, ActivationPolicy::count_used( $rows ) );
		$d = ActivationPolicy::decide( 1, $rows, 'inst-1', 'a.com', false );
		$this->assertSame( ActivationPolicy::ACTION_CREATE, $d['action'], 'A deactivated row is not re-used; a new one is created.' );
	}

	public function test_unlimited(): void {
		$rows = array();
		for ( $i = 1; $i <= 200; $i++ ) {
			$rows[] = self::row( $i, 'inst-' . $i, 'site' . $i . '.com' );
		}
		$this->assertSame( ActivationPolicy::ACTION_CREATE, ActivationPolicy::decide( 0, $rows, 'inst-new', 'new.com', false )['action'] );
	}

	public function test_business_plan_five_sites(): void {
		$rows = array();
		for ( $i = 1; $i <= 4; $i++ ) {
			$rows[] = self::row( $i, 'inst-' . $i, 'site' . $i . '.com' );
		}
		$this->assertSame( ActivationPolicy::ACTION_CREATE, ActivationPolicy::decide( 5, $rows, 'inst-5', 'site5.com', false )['action'] );
		$rows[] = self::row( 5, 'inst-5', 'site5.com' );
		$this->assertSame( ActivationPolicy::ACTION_DENY, ActivationPolicy::decide( 5, $rows, 'inst-6', 'site6.com', false )['action'] );
		$this->assertSame( ActivationPolicy::ACTION_REUSE, ActivationPolicy::decide( 5, $rows, 'inst-3', 'site3.com', false )['action'] );
	}

	public function test_moving_dev_row_to_production_needs_free_slot(): void {
		$rows = array(
			self::row( 1, 'inst-prod', 'a.com' ),
			self::row( 2, 'inst-dev', 'a.local', true ),
		);
		// Same instance moves from a.local to b.com: needs a slot, limit 1 is used.
		$this->assertSame( ActivationPolicy::ACTION_DENY, ActivationPolicy::decide( 1, $rows, 'inst-dev', 'b.com', false )['action'] );
		// With limit 2 it is re-used.
		$d = ActivationPolicy::decide( 2, $rows, 'inst-dev', 'b.com', false );
		$this->assertSame( ActivationPolicy::ACTION_REUSE, $d['action'] );
		$this->assertSame( 2, $d['activation_id'] );
	}
}
