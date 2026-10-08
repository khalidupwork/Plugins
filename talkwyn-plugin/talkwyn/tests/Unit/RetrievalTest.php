<?php
use PHPUnit\Framework\TestCase;

final class RetrievalTest extends TestCase {

	private function row( $id, $title, $text, $url = '', $lang = 'en' ) {
		return array( 'id' => $id, 'title' => $title, 'chunk_text' => $text, 'source_url' => $url, 'source_key' => 'post:' . $id, 'source_type' => 'page', 'source_lang' => $lang );
	}

	public function test_title_match_beats_newer_body_only_match() {
		$rows   = array(
			$this->row( 1, 'Shipping and returns', 'We ship in 2 days. Returns within 30 days.' ),
			$this->row( 9, 'Blog news', 'Our team went to a conference about shipping logistics.' ),
		);
		$tokens = Talkwyn_Text::tokens( 'How long does shipping take?' );
		$out    = Talkwyn_Retriever::rank( $rows, $tokens, '', '', 2 );
		$this->assertSame( 1, $out[0]['id'] );
	}

	public function test_whole_words_only_for_latin_scripts() {
		$rows = array( $this->row( 1, 'Pricing', 'Plans start at 10 dollars.' ) );
		$this->assertSame( array(), Talkwyn_Retriever::rank( $rows, array( 'pric' ), '', '', 3 ) );
	}

	public function test_old_relevant_chunk_is_not_lost_among_many_newer_matches() {
		$rows = array( $this->row( 1, 'Refund policy', 'Refund within 14 days of purchase.' ) );
		for ( $i = 2; $i < 60; $i++ ) {
			$rows[] = $this->row( $i, 'Post ' . $i, 'A post that mentions policy once.' );
		}
		$out = Talkwyn_Retriever::rank( $rows, Talkwyn_Text::tokens( 'refund policy' ), '', '', 3 );
		$this->assertSame( 1, $out[0]['id'] );
	}

	public function test_language_and_current_page_boost() {
		$rows = array(
			$this->row( 1, 'Contacto', 'Llámenos al teléfono de contacto.', 'https://x.test/es/contacto/', 'es' ),
			$this->row( 2, 'Contact', 'Call our contact phone.', 'https://x.test/contact/', 'en' ),
		);
		$out  = Talkwyn_Retriever::rank( $rows, array( 'contacto', 'contact' ), '', 'es-ES', 2 );
		$this->assertSame( 1, $out[0]['id'] );
	}

	public function test_compact_scripts_use_ngrams() {
		$rows = array( $this->row( 1, '配送について', '注文から三日で配送します。', '', 'ja' ) );
		$out  = Talkwyn_Retriever::rank( $rows, Talkwyn_Text::tokens( '配送はいつですか' ), '', '', 3 );
		$this->assertCount( 1, $out );
	}

	public function test_at_most_two_chunks_per_source() {
		$rows = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$r               = $this->row( $i, 'Pricing', 'Pricing details part ' . $i );
			$r['source_key'] = 'post:7';
			$rows[]          = $r;
		}
		$this->assertCount( 2, Talkwyn_Retriever::rank( $rows, array( 'pricing' ), '', '', 6 ) );
	}
}
