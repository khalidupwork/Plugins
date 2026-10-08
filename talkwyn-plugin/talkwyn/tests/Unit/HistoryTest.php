<?php
use PHPUnit\Framework\TestCase;

final class HistoryTest extends TestCase {
	public function test_history_survives_requests_and_is_capped() {
		$s = 'a1b2c3d4-e5f6-4a7b-8c9d-0123456789ab';
		Talkwyn_History::clear( $s );
		for ( $i = 0; $i < 30; $i++ ) {
			Talkwyn_History::add( $s, array( array( 'role' => 'user', 'content' => 'q' . $i ), array( 'role' => 'assistant', 'content' => 'a' . $i ) ) );
		}
		$all = Talkwyn_History::get( $s );
		$this->assertCount( Talkwyn_History::MAX_ENTRIES, $all );
		$this->assertSame( 'a29', end( $all )['content'] );
		$model = Talkwyn_History::for_model( $s, 4 );
		$this->assertSame( array( 'q28', 'a28', 'q29', 'a29' ), array_column( $model, 'content' ) );
	}
	public function test_events_are_not_sent_to_the_model() {
		$s = 'b1b2c3d4-e5f6-4a7b-8c9d-0123456789ab';
		Talkwyn_History::add( $s, array( array( 'role' => 'user', 'content' => 'Yes, contact me', 'kind' => 'event' ), array( 'role' => 'user', 'content' => 'real question' ) ) );
		$this->assertSame( array( 'real question' ), array_column( Talkwyn_History::for_model( $s, 8 ), 'content' ) );
	}
	public function test_invalid_session_is_rejected() {
		$this->assertSame( '', Talkwyn_History::clean_session( '../etc/passwd' ) );
		Talkwyn_History::add( 'bad', array( array( 'role' => 'user', 'content' => 'x' ) ) );
		$this->assertSame( array(), Talkwyn_History::get( 'bad' ) );
	}
	public function test_flags() {
		$s = 'c1b2c3d4-e5f6-4a7b-8c9d-0123456789ab';
		Talkwyn_History::set_flag( $s, 'lead_declined', 1 );
		$this->assertSame( 1, Talkwyn_History::flags( $s )['lead_declined'] );
	}
}
