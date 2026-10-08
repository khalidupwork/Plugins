<?php
/**
 * Smart search: embeddings stored in a custom table plus hybrid keyword scoring.
 *
 * Uses the embedding API of a provider the owner already has a key for
 * (OpenAI, Mistral or Google Gemini). Without one, keyword search is used.
 *
 * @package TalkwynPro
 */

namespace TalkwynPro;

defined( 'ABSPATH' ) || exit;

/**
 * Semantic and hybrid retrieval.
 */
final class Search {

	const CRON  = 'talkwyn_pro_embed';
	const BATCH = 48;

	/**
	 * Default embedding models.
	 *
	 * @var array<string, string>
	 */
	const MODELS = array(
		'openai'  => 'text-embedding-3-small',
		'mistral' => 'mistral-embed',
		'gemini'  => 'gemini-embedding-001',
	);

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'talkwyn_retrieve', array( self::class, 'retrieve' ), 10, 4 );
		add_filter( 'cron_schedules', array( self::class, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		add_action( self::CRON, array( self::class, 'cron' ) );
		add_action( 'talkwyn_scan_complete', array( self::class, 'soon' ) );
		add_action( 'talkwyn_post_indexed', array( self::class, 'soon' ) );
		add_action( 'talkwyn_pro_sources_changed', array( self::class, 'soon' ) );
		add_action( 'wp_ajax_talkwyn_pro_embed', array( self::class, 'ajax_batch' ) );
		add_action(
			'init',
			static function () {
				if ( self::provider() && ! wp_next_scheduled( self::CRON ) ) {
					wp_schedule_event( time() + 300, 'talkwyn_ten_minutes', self::CRON );
				}
			}
		);
	}

	/**
	 * Ten-minute schedule.
	 *
	 * @param array $s Schedules.
	 * @return array
	 */
	public static function schedules( array $s ): array {
		$s['talkwyn_ten_minutes'] = array(
			'interval' => 600,
			'display'  => 'Every 10 minutes (Talkwyn)',
		);
		return $s;
	}

	/**
	 * Embedding provider in use, or '' when none has a key.
	 */
	public static function provider(): string {
		$s  = \Talkwyn_Settings::all();
		$id = (string) ( $s['pro_embed_provider'] ?? '' );
		if ( '' === $id ) {
			return '';
		}
		return '' !== trim( (string) ( $s[ $id . '_key' ] ?? '' ) ) ? $id : '';
	}

	/**
	 * Embedding model in use.
	 */
	public static function model(): string {
		$id    = self::provider();
		$model = trim( (string) \Talkwyn_Settings::get( 'pro_embed_model', '' ) );
		return '' === $id ? '' : $id . ':' . ( '' !== $model ? $model : self::MODELS[ $id ] );
	}

	/**
	 * Run the next batch shortly.
	 */
	public static function soon(): void {
		if ( self::provider() ) {
			// WordPress drops duplicates scheduled within ten minutes.
			wp_schedule_single_event( time() + 30, self::CRON );
		}
	}

	/**
	 * Cron: embed new chunks, drop vectors of removed chunks.
	 */
	public static function cron(): void {
		self::purge();
		for ( $i = 0; $i < 3; $i++ ) {
			$r = self::embed_pending();
			if ( $r['done'] ) {
				break;
			}
		}
	}

	/**
	 * Admin AJAX batch (with progress).
	 */
	public static function ajax_batch(): void {
		check_ajax_referer( 'talkwyn_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'talkwyn-pro' ) ), 403 );
		}
		if ( ! self::provider() ) {
			wp_send_json_error( array( 'message' => __( 'Choose an embedding provider that has an API key first.', 'talkwyn-pro' ) ), 400 );
		}
		self::purge();
		$r = self::embed_pending();
		if ( '' !== $r['error'] ) {
			wp_send_json_error( array( 'message' => $r['error'] ), 400 );
		}
		wp_send_json_success( array_merge( $r, self::stats() ) );
	}

	/**
	 * Vector coverage.
	 *
	 * @return array{chunks:int,vectors:int}
	 */
	public static function stats(): array {
		global $wpdb;
		$t = \Talkwyn_DB::tables();
		$v = Installer::tables()['vectors'];
		return array(
			'chunks'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['chunks']}" ), // phpcs:ignore WordPress.DB
			'vectors' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$v} WHERE model = %s", self::model() ) ), // phpcs:ignore WordPress.DB
		);
	}

	/**
	 * Remove vectors whose chunk is gone.
	 */
	public static function purge(): void {
		global $wpdb;
		$t = \Talkwyn_DB::tables();
		$v = Installer::tables()['vectors'];
		$wpdb->query( "DELETE v FROM {$v} v LEFT JOIN {$t['chunks']} c ON c.id = v.chunk_id WHERE c.id IS NULL" ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Embed chunks that have no vector for the current model.
	 *
	 * @return array{embedded:int,done:bool,error:string}
	 */
	public static function embed_pending(): array {
		global $wpdb;
		$model = self::model();
		if ( '' === $model ) {
			return array(
				'embedded' => 0,
				'done'     => true,
				'error'    => '',
			);
		}
		$t    = \Talkwyn_DB::tables();
		$v    = Installer::tables()['vectors'];
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT c.id, c.title, c.chunk_text FROM {$t['chunks']} c LEFT JOIN {$v} v ON v.chunk_id = c.id AND v.model = %s WHERE v.chunk_id IS NULL ORDER BY c.id ASC LIMIT %d", $model, self::BATCH ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( ! $rows ) {
			return array(
				'embedded' => 0,
				'done'     => true,
				'error'    => '',
			);
		}
		$texts = array();
		foreach ( $rows as $row ) {
			$texts[] = \Talkwyn_Text::sub( trim( $row['title'] . "\n" . $row['chunk_text'] ), 0, 6000 );
		}
		try {
			$vectors = self::embed( $texts );
		} catch ( \Exception $e ) {
			return array(
				'embedded' => 0,
				'done'     => true,
				'error'    => $e->getMessage(),
			);
		}
		$count = 0;
		foreach ( $rows as $i => $row ) {
			if ( empty( $vectors[ $i ] ) ) {
				continue;
			}
			$vec = self::normalize( $vectors[ $i ] );
			$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$v,
				array(
					'chunk_id'    => (int) $row['id'],
					'model'       => $model,
					'dims'        => count( $vec ),
					'vec'         => self::pack( $vec ),
					'updated_gmt' => current_time( 'mysql', true ),
				),
				array( '%d', '%s', '%d', '%s', '%s' )
			);
			++$count;
		}
		return array(
			'embedded' => $count,
			'done'     => count( $rows ) < self::BATCH,
			'error'    => '',
		);
	}

	/**
	 * Call the embedding API.
	 *
	 * @param string[] $texts Texts.
	 * @return array<int, float[]>
	 * @throws \Exception On failure.
	 */
	public static function embed( array $texts ): array {
		$id    = self::provider();
		$s     = \Talkwyn_Settings::all();
		$model = substr( self::model(), strlen( $id ) + 1 );
		$key   = trim( (string) $s[ $id . '_key' ] );
		if ( 'gemini' === $id ) {
			$requests = array();
			foreach ( $texts as $text ) {
				$requests[] = array(
					'model'   => 'models/' . $model,
					'content' => array( 'parts' => array( array( 'text' => $text ) ) ),
				);
			}
			$res  = wp_remote_post(
				'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':batchEmbedContents',
				array(
					'timeout' => 30,
					'headers' => array(
						'Content-Type'   => 'application/json',
						'x-goog-api-key' => $key,
					),
					'body'    => wp_json_encode( array( 'requests' => $requests ) ),
				)
			);
			$data = \Talkwyn_Providers::json( $res );
			return array_map(
				static function ( $e ) {
					return array_map( 'floatval', (array) ( $e['values'] ?? array() ) );
				},
				(array) ( $data['embeddings'] ?? array() )
			);
		}
		$url  = 'openai' === $id ? 'https://api.openai.com/v1/embeddings' : 'https://api.mistral.ai/v1/embeddings';
		$res  = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model' => $model,
						'input' => array_values( $texts ),
					)
				),
			)
		);
		$data = \Talkwyn_Providers::json( $res );
		$out  = array();
		foreach ( (array) ( $data['data'] ?? array() ) as $row ) {
			$out[ (int) ( $row['index'] ?? count( $out ) ) ] = array_map( 'floatval', (array) ( $row['embedding'] ?? array() ) );
		}
		ksort( $out );
		return array_values( $out );
	}

	/**
	 * Filter for talkwyn_retrieve: hybrid ranking.
	 *
	 * @param array  $out     Keyword results.
	 * @param string $query   Query.
	 * @param int    $limit   Limit.
	 * @param array  $context Context.
	 * @return array
	 */
	public static function retrieve( $out, $query, $limit, $context ) {
		$model = self::model();
		if ( '' === $model || '' === trim( (string) $query ) ) {
			return $out;
		}
		$qv = self::query_vector( (string) $query, $model );
		if ( ! $qv ) {
			return $out;
		}
		$sims = self::nearest( $qv, $model, 16 );
		if ( ! $sims ) {
			return $out;
		}
		$rows = self::rows( array_keys( $sims ) );
		$w    = (float) \Talkwyn_Settings::get( 'pro_semantic_weight', 60 ) / 100;
		return self::hybrid( (array) $out, $sims, $rows, $w, (int) $limit );
	}

	/**
	 * Merge keyword and semantic results. Pure function, unit tested.
	 *
	 * @param array[]            $keyword Keyword rows (with id and score).
	 * @param array<int, float>  $sims    Chunk ID => cosine similarity.
	 * @param array<int, array>  $rows    Chunk rows by ID for semantic-only hits.
	 * @param float              $w       Semantic weight 0..1.
	 * @param int                $limit   Limit.
	 * @return array[]
	 */
	public static function hybrid( array $keyword, array $sims, array $rows, float $w, int $limit ): array {
		$max_kw = 0.0;
		foreach ( $keyword as $row ) {
			$max_kw = max( $max_kw, (float) ( $row['score'] ?? 0 ) );
		}
		$merged = array();
		foreach ( $keyword as $row ) {
			$id            = (int) $row['id'];
			$kw            = $max_kw > 0 ? (float) $row['score'] / $max_kw : 0.0;
			$sem           = (float) ( $sims[ $id ] ?? 0.0 );
			$row['hybrid'] = $w * $sem + ( 1 - $w ) * $kw;
			$merged[ $id ] = $row;
		}
		foreach ( $sims as $id => $sem ) {
			if ( isset( $merged[ $id ] ) || ! isset( $rows[ $id ] ) || $sem < 0.3 ) {
				continue;
			}
			$row           = $rows[ $id ];
			$row['score']  = 0;
			$row['hybrid'] = $w * $sem;
			$merged[ $id ] = $row;
		}
		uasort(
			$merged,
			static function ( $a, $b ) {
				return $b['hybrid'] <=> $a['hybrid'];
			}
		);
		$out        = array();
		$per_source = array();
		foreach ( $merged as $row ) {
			$key = (string) ( $row['source_key'] ?? $row['id'] );
			if ( ( $per_source[ $key ] ?? 0 ) >= 2 ) {
				continue;
			}
			$per_source[ $key ] = ( $per_source[ $key ] ?? 0 ) + 1;
			$out[]              = $row;
			if ( count( $out ) >= max( 1, $limit ) ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Query embedding, cached for a day.
	 *
	 * @param string $query Query.
	 * @param string $model Model key.
	 * @return float[]
	 */
	private static function query_vector( string $query, string $model ): array {
		$key    = 'talkwyn_pro_qv_' . md5( $model . '|' . $query );
		$cached = get_transient( $key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return self::unpack( $cached );
		}
		try {
			$vec = self::embed( array( \Talkwyn_Text::sub( $query, 0, 2000 ) ) );
		} catch ( \Exception $e ) {
			return array();
		}
		if ( empty( $vec[0] ) ) {
			return array();
		}
		$norm = self::normalize( $vec[0] );
		set_transient( $key, self::pack( $norm ), DAY_IN_SECONDS );
		return $norm;
	}

	/**
	 * Top-N chunks by cosine similarity.
	 *
	 * @param float[] $qv    Query vector (unit length).
	 * @param string  $model Model.
	 * @param int     $n     N.
	 * @return array<int, float>
	 */
	private static function nearest( array $qv, string $model, int $n ): array {
		global $wpdb;
		$v      = Installer::tables()['vectors'];
		$top    = array();
		$offset = 0;
		do {
			$batch = (array) $wpdb->get_results( $wpdb->prepare( "SELECT chunk_id, vec FROM {$v} WHERE model = %s AND dims = %d LIMIT %d OFFSET %d", $model, count( $qv ), 500, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB
			foreach ( $batch as $row ) {
				$top[ (int) $row['chunk_id'] ] = self::dot( $qv, self::unpack( (string) $row['vec'] ) );
			}
			arsort( $top );
			$top     = array_slice( $top, 0, $n, true );
			$offset += 500;
		} while ( count( $batch ) === 500 );
		return $top;
	}

	/**
	 * Chunk rows by ID.
	 *
	 * @param int[] $ids IDs.
	 * @return array<int, array>
	 */
	private static function rows( array $ids ): array {
		global $wpdb;
		if ( ! $ids ) {
			return array();
		}
		$t      = \Talkwyn_DB::tables();
		$holder = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows   = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, source_key, source_type, source_url, source_lang, title, chunk_text FROM {$t['chunks']} WHERE id IN ({$holder})", $ids ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$out    = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row['id'] ] = $row;
		}
		return $out;
	}

	/**
	 * Unit vector.
	 *
	 * @param float[] $v Vector.
	 * @return float[]
	 */
	public static function normalize( array $v ): array {
		$sum = 0.0;
		foreach ( $v as $x ) {
			$sum += $x * $x;
		}
		$len = sqrt( $sum );
		if ( $len <= 0 ) {
			return $v;
		}
		return array_map(
			static function ( $x ) use ( $len ) {
				return $x / $len;
			},
			$v
		);
	}

	/**
	 * Dot product.
	 *
	 * @param float[] $a A.
	 * @param float[] $b B.
	 */
	public static function dot( array $a, array $b ): float {
		$sum = 0.0;
		$n   = min( count( $a ), count( $b ) );
		for ( $i = 0; $i < $n; $i++ ) {
			$sum += $a[ $i ] * $b[ $i ];
		}
		return $sum;
	}

	/**
	 * Float32 binary.
	 *
	 * @param float[] $v Vector.
	 */
	public static function pack( array $v ): string {
		return pack( 'g*', ...array_values( $v ) );
	}

	/**
	 * Binary to floats.
	 *
	 * @param string $bin Binary.
	 * @return float[]
	 */
	public static function unpack( string $bin ): array {
		$v = unpack( 'g*', $bin );
		return is_array( $v ) ? array_values( $v ) : array();
	}
}
