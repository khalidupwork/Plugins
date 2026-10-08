<?php
/**
 * Local knowledge retrieval.
 *
 * Candidates are ranked inside MySQL before the limit (FULLTEXT relevance plus a
 * keyword score), then re-ranked in PHP with whole-word, title, URL, current page
 * and language signals. Chinese, Japanese, Korean and Thai use Unicode n-grams.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retriever.
 */
class Talkwyn_Retriever {

	const CANDIDATES = 60;

	/**
	 * Search the knowledge base.
	 *
	 * @param string $query   Visitor question (optionally with the previous question).
	 * @param int    $limit   Chunks to return.
	 * @param array  $context page_url, page_lang.
	 * @return array[]
	 */
	public static function search( $query, $limit = 6, array $context = array() ) {
		$tokens = Talkwyn_Text::tokens( $query );
		$rows   = $tokens ? self::candidates( $query, $tokens ) : array();
		$out    = self::rank( $rows, $tokens, (string) ( $context['page_url'] ?? '' ), (string) ( $context['page_lang'] ?? '' ), $limit );

		/**
		 * Filters retrieved knowledge. Talkwyn Pro uses this for smart search
		 * and custom answers.
		 *
		 * @param array  $out     Ranked chunks (id, title, chunk_text, source_url, score...).
		 * @param string $query   Search query.
		 * @param int    $limit   Limit.
		 * @param array  $context page_url, page_lang.
		 */
		return (array) apply_filters( 'talkwyn_retrieve', $out, $query, $limit, $context );
	}

	/**
	 * Candidate rows ranked in SQL.
	 *
	 * @param string   $query  Query.
	 * @param string[] $tokens Tokens.
	 * @return array[]
	 */
	private static function candidates( $query, array $tokens ) {
		global $wpdb;
		$t    = Talkwyn_DB::tables();
		$rows = array();

		$words = array_values(
			array_filter(
				$tokens,
				static function ( $token ) {
					return ! Talkwyn_Text::is_compact( $token ) && Talkwyn_Text::len( $token ) >= 3;
				}
			)
		);
		if ( $words && get_option( 'talkwyn_fulltext' ) ) {
			$against = implode( ' ', $words );
			$found   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT id, source_key, source_type, source_url, source_lang, title, chunk_text,
						MATCH(title, chunk_text) AGAINST (%s IN NATURAL LANGUAGE MODE) AS ft
					FROM {$t['chunks']}
					WHERE MATCH(title, chunk_text) AGAINST (%s IN NATURAL LANGUAGE MODE)
					ORDER BY ft DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$against,
					$against,
					self::CANDIDATES
				),
				ARRAY_A
			);
			foreach ( (array) $found as $row ) {
				$rows[ (int) $row['id'] ] = $row;
			}
		}

		// Keyword score computed in SQL so the best rows survive the limit.
		$score  = array();
		$where  = array();
		$params = array();
		foreach ( array_slice( $tokens, 0, 12 ) as $token ) {
			$like     = '%' . $wpdb->esc_like( $token ) . '%';
			$score[]  = '(title LIKE %s) * 8 + (chunk_text LIKE %s) * 2 + (source_url LIKE %s) * 3';
			$where[]  = '(title LIKE %s OR chunk_text LIKE %s OR source_url LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		$params = array_merge( $params, $params, array( self::CANDIDATES ) );
		$sql    = "SELECT id, source_key, source_type, source_url, source_lang, title, chunk_text, (" . implode( ' + ', $score ) . ") AS kw
			FROM {$t['chunks']} WHERE " . implode( ' OR ', $where ) . ' ORDER BY kw DESC, id DESC LIMIT %d';
		$found  = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $found as $row ) {
			$id = (int) $row['id'];
			if ( isset( $rows[ $id ] ) ) {
				$rows[ $id ]['kw'] = $row['kw'];
			} else {
				$rows[ $id ] = $row;
			}
		}
		return array_values( $rows );
	}

	/**
	 * Rank candidate rows. Pure function, unit tested.
	 *
	 * @param array[]  $rows      Rows (id, title, chunk_text, source_url, source_key, source_type, source_lang, ft?).
	 * @param string[] $tokens    Query tokens.
	 * @param string   $page_url  Current page URL.
	 * @param string   $page_lang Current page language.
	 * @param int      $limit     Limit.
	 * @return array[]
	 */
	public static function rank( array $rows, array $tokens, $page_url = '', $page_lang = '', $limit = 6 ) {
		$max_ft = 0.0;
		foreach ( $rows as $row ) {
			$max_ft = max( $max_ft, (float) ( $row['ft'] ?? 0 ) );
		}
		$ranked = array();
		foreach ( $rows as $row ) {
			$score = self::score( $row, $tokens, $page_url, $page_lang );
			if ( $score <= 0 ) {
				continue;
			}
			if ( $max_ft > 0 && ! empty( $row['ft'] ) ) {
				$score += 6 * ( (float) $row['ft'] / $max_ft );
			}
			$row['score'] = round( $score, 3 );
			$ranked[]     = $row;
		}
		usort(
			$ranked,
			static function ( $a, $b ) {
				if ( $a['score'] === $b['score'] ) {
					return (int) $b['id'] <=> (int) $a['id'];
				}
				return $b['score'] <=> $a['score'];
			}
		);
		$out        = array();
		$per_source = array();
		foreach ( $ranked as $row ) {
			$key = ! empty( $row['source_key'] ) ? $row['source_key'] : 'row:' . $row['id'];
			if ( ( $per_source[ $key ] ?? 0 ) >= 2 ) {
				continue;
			}
			$per_source[ $key ] = ( $per_source[ $key ] ?? 0 ) + 1;
			$out[]              = $row;
			if ( count( $out ) >= max( 1, (int) $limit ) ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Score one row. Latin tokens must match whole words; compact scripts match substrings.
	 *
	 * @param array    $row       Row.
	 * @param string[] $tokens    Tokens.
	 * @param string   $page_url  Current page.
	 * @param string   $page_lang Page language.
	 * @return float
	 */
	public static function score( array $row, array $tokens, $page_url = '', $page_lang = '' ) {
		$title   = Talkwyn_Text::lower( $row['title'] ?? '' );
		$body    = Talkwyn_Text::lower( $row['chunk_text'] ?? '' );
		$url     = Talkwyn_Text::lower( rawurldecode( (string) ( $row['source_url'] ?? '' ) ) );
		$t_words = Talkwyn_Text::word_map( $title );
		$b_words = Talkwyn_Text::word_map( $body );
		$u_words = Talkwyn_Text::word_map( $url );
		$score   = 0.0;
		$matched = 0;
		foreach ( $tokens as $token ) {
			$hit = false;
			if ( Talkwyn_Text::is_compact( $token ) ) {
				if ( Talkwyn_Text::contains( $title, $token ) ) {
					$score += 7;
					$hit    = true;
				}
				if ( Talkwyn_Text::contains( $body, $token ) ) {
					$score += 2;
					$hit    = true;
				}
			} else {
				if ( isset( $t_words[ $token ] ) ) {
					$score += 8;
					$hit    = true;
				}
				if ( isset( $b_words[ $token ] ) ) {
					$score += 2 + min( 3, substr_count( $body, $token ) - 1 ) * 0.5;
					$hit    = true;
				}
				if ( isset( $u_words[ $token ] ) ) {
					$score += 3;
					$hit    = true;
				}
			}
			if ( $hit ) {
				++$matched;
			}
		}
		if ( $matched < 1 ) {
			return 0.0;
		}
		// Reward rows that match more of the question.
		$score += 4 * ( $matched / max( 1, count( $tokens ) ) );
		if ( in_array( (string) ( $row['source_type'] ?? '' ), array( 'page', 'post', 'product' ), true ) ) {
			$score += 1;
		}
		if ( '' !== $page_url && ! empty( $row['source_url'] ) && rtrim( $row['source_url'], '/' ) === rtrim( $page_url, '/' ) ) {
			$score += 4;
		}
		$pl = strtolower( (string) preg_replace( '/[-_].*$/', '', (string) $page_lang ) );
		$rl = strtolower( (string) preg_replace( '/[-_].*$/', '', (string) ( $row['source_lang'] ?? '' ) ) );
		if ( '' !== $pl && '' !== $rl && $pl === $rl ) {
			$score += 6;
		}
		return $score;
	}
}
