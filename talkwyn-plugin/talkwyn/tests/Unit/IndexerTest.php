<?php
use PHPUnit\Framework\TestCase;

final class IndexerTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpdb']->deleted = array();
		Talkwyn_Settings::flush();
	}

	public function test_password_and_unpublished_posts_are_not_indexable() {
		$this->assertTrue( Talkwyn_Indexer::is_indexable( new WP_Post() ) );
		$this->assertFalse( Talkwyn_Indexer::is_indexable( new WP_Post( array( 'post_password' => 'secret' ) ) ) );
		$this->assertFalse( Talkwyn_Indexer::is_indexable( new WP_Post( array( 'post_status' => 'draft' ) ) ) );
		$this->assertFalse( Talkwyn_Indexer::is_indexable( new WP_Post( array( 'post_type' => 'attachment' ) ) ) );
	}

	public function test_unpublish_removes_chunks() {
		Talkwyn_Indexer::on_transition( 'draft', 'publish', new WP_Post( array( 'ID' => 42 ) ) );
		Talkwyn_Indexer::on_transition( 'private', 'publish', new WP_Post( array( 'ID' => 43 ) ) );
		$keys = array_map( static function ( $d ) { return $d[1]['source_key']; }, $GLOBALS['wpdb']->deleted );
		$this->assertSame( array( 'post:42', 'post:43' ), $keys );
	}

	public function test_publish_transition_does_not_remove() {
		Talkwyn_Indexer::on_transition( 'publish', 'draft', new WP_Post( array( 'ID' => 44 ) ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->deleted );
	}

	public function test_trash_and_delete_remove() {
		Talkwyn_Indexer::remove_post( 50 );
		$this->assertSame( 'post:50', $GLOBALS['wpdb']->deleted[0][1]['source_key'] );
	}

	public function test_custom_field_allow_list() {
		$allow = Talkwyn_Indexer::allowlist( "spec_*, warranty\nopening_hours" );
		$this->assertTrue( Talkwyn_Indexer::allowed_key( 'spec_weight', $allow ) );
		$this->assertTrue( Talkwyn_Indexer::allowed_key( 'warranty', $allow ) );
		$this->assertFalse( Talkwyn_Indexer::allowed_key( 'internal_notes', $allow ) );
		$this->assertFalse( Talkwyn_Indexer::allowed_key( 'warranty_cost', $allow ) );
	}
}
