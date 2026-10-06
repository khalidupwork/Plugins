<?php
/**
 * Leads list table.
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table of audit requests.
 */
class CMA_Leads_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'lead',
				'plural'   => 'leads',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox">',
			'email'         => __( 'Email', 'credit-market-audit' ),
			'name'          => __( 'Name', 'credit-market-audit' ),
			'url'           => __( 'Website', 'credit-market-audit' ),
			'overall_score' => __( 'Score', 'credit-market-audit' ),
			'status'        => __( 'Status', 'credit-market-audit' ),
			'email_sent'    => __( 'Emailed', 'credit-market-audit' ),
			'created_at'    => __( 'Date', 'credit-market-audit' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'email'         => array( 'email', false ),
			'url'           => array( 'url', false ),
			'overall_score' => array( 'overall_score', false ),
			'status'        => array( 'status', false ),
			'created_at'    => array( 'created_at', true ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array( 'delete' => __( 'Delete', 'credit-market-audit' ) );
	}

	/**
	 * Handle bulk + single delete.
	 */
	public function process_bulk_action() {
		if ( 'delete' !== $this->current_action() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nonces verified below.
		if ( isset( $_GET['lead'] ) && is_array( $_GET['lead'] ) ) {
			check_admin_referer( 'bulk-leads' );
			$ids = array_map( 'absint', wp_unslash( $_GET['lead'] ) );
		} elseif ( isset( $_GET['id'] ) ) {
			$id = absint( $_GET['id'] );
			check_admin_referer( 'cma_delete_' . $id );
			$ids = array( $id );
		} else {
			return;
		}
		// phpcs:enable

		CMA_Repository::delete( $ids );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Leads deleted.', 'credit-market-audit' ) . '</p></div>';
	}

	/**
	 * Load rows.
	 */
	public function prepare_items() {
		$per_page = 20;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$result = CMA_Repository::query(
			array(
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
				'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
				'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'created_at',
				'order'    => isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'desc',
			)
		);
		// phpcs:enable

		$this->items           = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'email' );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="lead[]" value="%d">', (int) $item['id'] );
	}

	/**
	 * Email column with row actions.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_email( $item ) {
		$actions = array();
		if ( 'complete' === $item['status'] ) {
			$actions['view']     = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( CMA_Report::view_url( $item['token'] ) ), esc_html__( 'View report', 'credit-market-audit' ) );
			$actions['download'] = sprintf( '<a href="%s">%s</a>', esc_url( CMA_Report::download_url( $item['token'] ) ), esc_html__( 'Download', 'credit-market-audit' ) );
			$actions['resend']   = sprintf( '<a href="%s">%s</a>', esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cma_resend&id=' . (int) $item['id'] ), 'cma_resend_' . (int) $item['id'] ) ), esc_html__( 'Resend email', 'credit-market-audit' ) );
		}
		$actions['delete'] = sprintf(
			'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
			esc_url( wp_nonce_url( admin_url( 'admin.php?page=cma-leads&action=delete&id=' . (int) $item['id'] ), 'cma_delete_' . (int) $item['id'] ) ),
			esc_js( __( 'Delete this lead?', 'credit-market-audit' ) ),
			esc_html__( 'Delete', 'credit-market-audit' )
		);

		return sprintf( '<strong><a href="mailto:%1$s">%2$s</a></strong>%3$s', esc_attr( $item['email'] ), esc_html( $item['email'] ), $this->row_actions( $actions ) );
	}

	/**
	 * Score column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_overall_score( $item ) {
		if ( null === $item['overall_score'] ) {
			return '—';
		}
		$score = (int) $item['overall_score'];
		return sprintf( '<span class="cma-score-badge" style="background:%s">%d</span>', esc_attr( CMA_Audit::color( $score ) ), $score );
	}

	/**
	 * URL column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_url( $item ) {
		return sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', esc_url( $item['url'] ), esc_html( $item['url'] ) );
	}

	/**
	 * Status column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_status( $item ) {
		$labels = array(
			'pending'  => __( 'Pending', 'credit-market-audit' ),
			'running'  => __( 'Incomplete', 'credit-market-audit' ),
			'complete' => __( 'Complete', 'credit-market-audit' ),
		);
		return esc_html( isset( $labels[ $item['status'] ] ) ? $labels[ $item['status'] ] : $item['status'] );
	}

	/**
	 * Emailed column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_email_sent( $item ) {
		return $item['email_sent'] ? '<span class="dashicons dashicons-yes" style="color:#16a34a"></span>' : '<span class="dashicons dashicons-minus" style="color:#94a3b8"></span>';
	}

	/**
	 * Date column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_created_at( $item ) {
		return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['created_at'] . ' UTC' ) ) );
	}

	/**
	 * Fallback column.
	 *
	 * @param array  $item        Row.
	 * @param string $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) && '' !== $item[ $column_name ] ? esc_html( $item[ $column_name ] ) : '—';
	}

	/**
	 * Empty state.
	 */
	public function no_items() {
		esc_html_e( 'No audits yet. Add the form to a page with the Elementor widget or the [credit_market_audit] shortcode.', 'credit-market-audit' );
	}
}
