<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Admin;

use ZBE\Core\Services;

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/** Class AdminBusinessTable. */
class AdminBusinessTable extends \WP_List_Table {
	/**
	 * Construct.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'business',
				'plural'   => 'businesses',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 */
	public function get_columns(): array {
		return array(
			'cb'           => '<input type="checkbox" />',
			'title'        => __( 'Name', 'zeko-business' ),
			'zbp_status'   => __( 'Status', 'zeko-business' ),
			'zbp_email'    => __( 'Email', 'zeko-business' ),
			'zbp_city'     => __( 'City', 'zeko-business' ),
			'zbp_rating'   => __( 'Rating', 'zeko-business' ),
			'zbp_plan'     => __( 'Plan', 'zeko-business' ),
			'zbp_views'    => __( 'Views', 'zeko-business' ),
			'date_created' => __( 'Date', 'zeko-business' ),
		);
	}

	/**
	 * Sortable columns.
	 */
	public function get_sortable_columns(): array {
		return array(
			'title'        => array( 'name', false ),
			'zbp_rating'   => array( 'avg_rating', false ),
			'zbp_views'    => array( 'view_count', false ),
			'date_created' => array( 'date_created', true ),
		);
	}

	/**
	 * Collect filters.
	 */
	private function collect_filters(): array {
		$args = array();

		if ( ! empty( $_REQUEST['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table search filter.
			$args['search'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table search filter.
		}

		if ( ! empty( $_REQUEST['zbp_status_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table status filter.
			$status = sanitize_key( wp_unslash( $_REQUEST['zbp_status_filter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table status filter.
			if ( in_array( $status, array( 'active', 'pending', 'inactive', 'suspended' ), true ) ) {
				$args['status'] = $status;
			}
		}

		return $args;
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items(): void {
		$per_page = 20;
		$current  = $this->get_pagenum();

		$allowed_orderby = array( 'name', 'avg_rating', 'view_count', 'date_created' );
		$orderby         = 'date_created';
		$order           = 'DESC';

		if ( ! empty( $_REQUEST['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table sort parameter.
			$ob = sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table sort parameter.
			if ( in_array( $ob, $allowed_orderby, true ) ) {
				$orderby = $ob;
			}
		}

		if ( ! empty( $_REQUEST['order'] ) && 'ASC' === strtoupper( sanitize_key( wp_unslash( $_REQUEST['order'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table sort direction.
			$order = 'ASC';
		}

		$repo        = Services::businesses();
		$total       = $repo->count( $this->collect_filters() );
		$this->items = $repo->find(
			array_merge(
				$this->collect_filters(),
				array(
					'orderby'  => $orderby,
					'order'    => $order,
					'per_page' => $per_page,
					'page'     => $current,
				)
			)
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	/**
	 * Column cb.
	 *
	 * @param object $item Item.
	 */
	public function column_cb( object $item ): string {
		return sprintf( '<input type="checkbox" name="post[]" value="%d" />', (int) $item->post_id );
	}

	/**
	 * Column title.
	 *
	 * @param object $item Item.
	 */
	public function column_title( object $item ): string {
		$url  = get_edit_post_link( (int) $item->post_id );
		$edit = $url ? sprintf( ' <a href="%s">%s</a>', esc_url( $url ), esc_html( $item->name ) ) : esc_html( $item->name );
		return '<strong>' . $edit . '</strong>';
	}

	/**
	 * Column zbp status.
	 *
	 * @param object $item Item.
	 */
	public function column_zbp_status( object $item ): string {
		return sprintf( '<span class="zbp-badge zbp-badge--%s">%s</span>', esc_attr( $item->status ), esc_html( ucfirst( $item->status ) ) );
	}

	/**
	 * Column zbp email.
	 *
	 * @param object $item Item.
	 */
	public function column_zbp_email( object $item ): string {
		return esc_html( $item->email ?: '—' );
	}

	/**
	 * Column zbp city.
	 *
	 * @param object $item Item.
	 */
	public function column_zbp_city( object $item ): string {
		return esc_html( $item->city ?: '—' );
	}

	/**
	 * Column zbp rating.
	 *
	 * @param object $item Item.
	 */
	public function column_zbp_rating( object $item ): string {
		$rating = (float) $item->avg_rating;
		$full   = (int) floor( $rating );
		return esc_html( str_repeat( '★', $full ) . str_repeat( '☆', max( 0, 5 - $full ) ) ) . ' ' . esc_html( number_format( $rating, 1 ) );
	}

	/**
	 * Column zbp plan.
	 *
	 * @param object $item Item.
	 */
	public function column_zbp_plan( object $item ): string {
		return '<span class="zbp-badge zbp-badge--plan">' . esc_html( ucfirst( $item->plan ) ) . '</span>';
	}

	/**
	 * Column zbp views.
	 *
	 * @param object $item Item.
	 */
	public function column_zbp_views( object $item ): string {
		return esc_html( number_format( (int) $item->view_count ) );
	}

	/**
	 * Column date created.
	 *
	 * @param object $item Item.
	 */
	public function column_date_created( object $item ): string {
		return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->date_created ) ) );
	}

	/**
	 * Extra tablenav.
	 *
	 * @param string $which Which.
	 */
	protected function extra_tablenav( string $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$counts = Services::businesses()->count_by_status();

		echo '<div class="alignleft actions">';
		echo '<select name="zbp_status_filter">';
		echo '<option value="">' . esc_html__( 'All Statuses', 'zeko-business' ) . '</option>';
		foreach ( array( 'active', 'pending', 'inactive', 'suspended' ) as $s ) {
			$sel = ( ! empty( $_REQUEST['zbp_status_filter'] ) && $_REQUEST['zbp_status_filter'] === $s ) ? ' selected' : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table status filter; values compared against a fixed whitelist.
			printf(
				'<option value="%s"%s>%s (%d)</option>',
				esc_attr( $s ),
				esc_attr( $sel ),
				esc_html( ucfirst( $s ) ),
				(int) ( $counts[ $s ] ?? 0 )
			);
		}
		echo '</select>';
		submit_button( __( 'Filter', 'zeko-business' ), '', 'filter_action', false );
		echo '</div>';
	}
}
