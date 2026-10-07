<?php
/**
 * Licenses list table.
 *
 * @package TalkwynHub
 */

namespace TWH\Admin;

use TWH\Domain\KeyGenerator;
use TWH\LicenseService;
use TWH\Repository\Licenses;
use TWH\Repository\Products;
use TWH\Support\Time;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table for licenses.
 */
final class LicensesTable extends \WP_List_Table {

	/**
	 * Product names by id.
	 *
	 * @var array<int, string>
	 */
	private array $products = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'license',
				'plural'   => 'licenses',
				'ajax'     => false,
			)
		);
		foreach ( Products::all() as $p ) {
			$this->products[ (int) $p['id'] ] = (string) $p['name'];
		}
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'key'        => __( 'License', 'talkwyn-hub' ),
			'customer'   => __( 'Customer', 'talkwyn-hub' ),
			'product'    => __( 'Product / plan', 'talkwyn-hub' ),
			'status'     => __( 'Status', 'talkwyn-hub' ),
			'sites'      => __( 'Sites', 'talkwyn-hub' ),
			'expires_at' => __( 'Expires', 'talkwyn-hub' ),
			'created_at' => __( 'Created', 'talkwyn-hub' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	protected function get_sortable_columns() {
		return array(
			'key'        => array( 'id', true ),
			'status'     => array( 'status', false ),
			'expires_at' => array( 'expires_at', false ),
			'created_at' => array( 'created_at', false ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return array(
			'bulk_suspend'    => __( 'Suspend', 'talkwyn-hub' ),
			'bulk_reactivate' => __( 'Reactivate', 'talkwyn-hub' ),
			'bulk_revoke'     => __( 'Revoke', 'talkwyn-hub' ),
			'bulk_export'     => __( 'Export to CSV', 'talkwyn-hub' ),
		);
	}

	/**
	 * Load items.
	 */
	public function prepare_items(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$per_page = 25;
		$result   = Licenses::search(
			array(
				'search'     => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
				'status'     => isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '',
				'plan'       => isset( $_REQUEST['plan'] ) ? sanitize_key( wp_unslash( $_REQUEST['plan'] ) ) : '',
				'product_id' => isset( $_REQUEST['product_id'] ) ? absint( $_REQUEST['product_id'] ) : 0,
				'orderby'    => isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'id',
				'order'      => isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc',
				'per_page'   => $per_page,
				'page'       => $this->get_pagenum(),
			)
		);
		// phpcs:enable
		$this->items           = $result['rows'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Filters above the table.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status  = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';
		$plan    = isset( $_REQUEST['plan'] ) ? sanitize_key( wp_unslash( $_REQUEST['plan'] ) ) : '';
		$product = isset( $_REQUEST['product_id'] ) ? absint( $_REQUEST['product_id'] ) : 0;
		// phpcs:enable
		echo '<div class="alignleft actions">';
		echo '<select name="status"><option value="">' . esc_html__( 'All statuses', 'talkwyn-hub' ) . '</option>';
		foreach ( Licenses::STATUSES as $s ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $s ), selected( $status, $s, false ), esc_html( LicenseService::status_label( $s ) ) );
		}
		echo '</select> <select name="plan"><option value="">' . esc_html__( 'All plans', 'talkwyn-hub' ) . '</option>';
		foreach ( Licenses::plans() as $p ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $p ), selected( $plan, $p, false ), esc_html( LicenseService::plan_label( $p ) ) );
		}
		echo '</select> <select name="product_id"><option value="0">' . esc_html__( 'All products', 'talkwyn-hub' ) . '</option>';
		foreach ( $this->products as $id => $name ) {
			printf( '<option value="%d" %s>%s</option>', (int) $id, selected( $product, $id, false ), esc_html( $name ) );
		}
		echo '</select> ';
		submit_button( __( 'Filter', 'talkwyn-hub' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Checkbox.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	protected function column_cb( $item ) {
		return '<input type="checkbox" name="license_ids[]" value="' . (int) $item['id'] . '" />';
	}

	/**
	 * Key column.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	protected function column_key( $item ) {
		$url = admin_url( 'admin.php?page=twh-licenses&action=edit&license=' . (int) $item['id'] );
		return sprintf(
			'<strong><a href="%s"><code>%s</code></a></strong> <span class="twh-muted">#%d</span>',
			esc_url( $url ),
			esc_html( KeyGenerator::mask( (string) $item['key_last4'] ) ),
			(int) $item['id']
		) . $this->row_actions( array( 'edit' => '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Manage', 'talkwyn-hub' ) . '</a>' ) );
	}

	/**
	 * Customer column.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	protected function column_customer( $item ) {
		$out = '';
		if ( (int) $item['customer_id'] ) {
			$user = get_userdata( (int) $item['customer_id'] );
			if ( $user ) {
				$out = '<a href="' . esc_url( get_edit_user_link( $user->ID ) ) . '">' . esc_html( $user->user_email ) . '</a>';
			}
		}
		if ( '' === $out ) {
			$out = esc_html( (string) $item['customer_email'] );
		}
		if ( (int) $item['order_id'] ) {
			$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $item['order_id'] ) : null;
			$link  = $order ? $order->get_edit_order_url() : '';
			$out  .= '<br><small>' . ( $link ? '<a href="' . esc_url( $link ) . '">' : '' ) . esc_html( sprintf( /* translators: %d: order id */ __( 'Order #%d', 'talkwyn-hub' ), (int) $item['order_id'] ) ) . ( $link ? '</a>' : '' ) . '</small>';
		}
		return $out;
	}

	/**
	 * Product column.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	protected function column_product( $item ) {
		return esc_html( $this->products[ (int) $item['product_id'] ] ?? '?' ) . '<br><small>' . esc_html( LicenseService::plan_label( (string) $item['plan_slug'] ) ) . '</small>';
	}

	/**
	 * Status column.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	protected function column_status( $item ) {
		$status = Licenses::effective_status( $item );
		$out    = '<span class="twh-badge twh-badge--' . esc_attr( $status ) . '">' . esc_html( LicenseService::status_label( $status ) ) . '</span>';
		if ( ! empty( $item['is_trial'] ) ) {
			$out .= ' <span class="twh-badge twh-badge--dev">' . esc_html__( 'Trial', 'talkwyn-hub' ) . '</span>';
		} elseif ( ! empty( $item['converted_at'] ) ) {
			$out .= ' <span class="twh-badge twh-badge--lifetime">' . esc_html__( 'From trial', 'talkwyn-hub' ) . '</span>';
		}
		return $out;
	}

	/**
	 * Sites column.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	protected function column_sites( $item ) {
		return esc_html( (int) ( $item['sites_used'] ?? 0 ) . ' / ' . LicenseService::limit_label( (int) $item['activation_limit'] ) );
	}

	/**
	 * Date columns.
	 *
	 * @param array<string, mixed> $item        Row.
	 * @param string               $column_name Column.
	 */
	protected function column_default( $item, $column_name ) {
		if ( 'expires_at' === $column_name ) {
			return esc_html( Time::human( $item['expires_at'], __( 'Lifetime', 'talkwyn-hub' ) ) );
		}
		if ( 'created_at' === $column_name ) {
			return esc_html( Time::human( $item['created_at'] ) );
		}
		return '';
	}

	/**
	 * Empty message.
	 */
	public function no_items() {
		esc_html_e( 'No licenses found.', 'talkwyn-hub' );
	}
}
