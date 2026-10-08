<?php
use PHPUnit\Framework\TestCase;

final class LeadsTest extends TestCase {
	private $s = array( 'lead_require_name' => 1, 'lead_require_email' => 1, 'lead_require_phone' => 0, 'lead_consent_enabled' => 1 );

	public function test_valid_lead() {
		$this->assertTrue( Talkwyn_Leads::validate( array( 'name' => 'Sara', 'email' => 'sara@example.com', 'consent' => 1, 'elapsed' => 9000 ), $this->s )['ok'] );
	}
	public function test_honeypot_is_spam() {
		$r = Talkwyn_Leads::validate( array( 'name' => 'Bot', 'email' => 'b@example.com', 'consent' => 1, 'website' => 'x' ), $this->s );
		$this->assertTrue( $r['spam'] );
	}
	public function test_consent_and_email_required() {
		$this->assertFalse( Talkwyn_Leads::validate( array( 'name' => 'Sara', 'email' => 'sara@example.com', 'elapsed' => 9000 ), $this->s )['ok'] );
		$this->assertFalse( Talkwyn_Leads::validate( array( 'name' => 'Sara', 'email' => 'nope', 'consent' => 1, 'elapsed' => 9000 ), $this->s )['ok'] );
	}

	public function test_time_trap() {
		$this->assertTrue( Talkwyn_Leads::validate( array( 'name' => 'Sara', 'email' => 'sara@example.com', 'consent' => 1, 'elapsed' => 400 ), $this->s )['spam'] );
		$this->assertTrue( Talkwyn_Leads::validate( array( 'name' => 'Sara', 'email' => 'sara@example.com', 'consent' => 1 ), $this->s )['spam'] );
	}
}
