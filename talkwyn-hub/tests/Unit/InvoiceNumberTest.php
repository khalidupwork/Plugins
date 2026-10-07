<?php
/**
 * @package TalkwynHub
 */

namespace TWH\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TWH\Woo\Invoices;

final class InvoiceNumberTest extends TestCase {

	public function test_pads_to_five_digits_with_prefix(): void {
		$this->assertSame( 'TW-00001', Invoices::format_number( 1, 'TW-' ) );
		$this->assertSame( 'TW-00042', Invoices::format_number( 42, 'TW-' ) );
	}

	public function test_grows_past_five_digits(): void {
		$this->assertSame( 'INV123456', Invoices::format_number( 123456, 'INV' ) );
	}

	public function test_never_formats_zero_or_negative(): void {
		$this->assertSame( '00001', Invoices::format_number( 0, '' ) );
		$this->assertSame( '00001', Invoices::format_number( -5, '' ) );
	}
}
