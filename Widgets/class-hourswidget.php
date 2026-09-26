<?php
/**
 * Working Hours widget.
 *
 * Context-aware: it only renders content when the front page being viewed is
 * a single business listing, and then shows that business's weekly hours
 * together with a tri-state open / closed / no-hours status badge.
 *
 * @package Zeko_ZEKO_BUSINESS
 **/

namespace ZBE\Widgets;

use ZBE\Core\Plans;
use ZBE\Core\Services;
use ZBE\Frontend\PublicController;

defined( 'ABSPATH' ) || exit;

/** Class HoursWidget. */
class HoursWidget extends \WP_Widget {

	/**
	 * Construct.
	 */
	public function __construct() {
		parent::__construct(
			'zbe_hours_widget',
			__( 'Zeko Business Hours', 'zeko-business' ),
			array(
				'description' => __( 'Show a business\'s working hours and open/closed status on its listing.', 'zeko-business' ),
			)
		);
	}

	/**
	 * Register.
	 */
	public static function register(): void {
		register_widget( __CLASS__ );
	}

	/**
	 * Widget.
	 *
	 * @param mixed $args Args.
	 * @param mixed $instance Instance.
	 */
	public function widget( $args, $instance ) {
		$business = $this->current_business();

		if ( null === $business ) {
			return;
		}

		$hours_mode = (string) ( $business->hours_mode ?: '' );
		$hours      = Services::hours()->get_for_business( (int) $business->id );
		$status     = PublicController::hours_status( $hours, $hours_mode );

		if ( ! in_array( $hours_mode, array( '', 'always', 'closed', 'none' ), true ) ) {
			$hours_mode = '';
		}

		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Business Hours', 'zeko-business' );
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		$day_names = array(
			__( 'Sunday', 'zeko-business' ),
			__( 'Monday', 'zeko-business' ),
			__( 'Tuesday', 'zeko-business' ),
			__( 'Wednesday', 'zeko-business' ),
			__( 'Thursday', 'zeko-business' ),
			__( 'Friday', 'zeko-business' ),
			__( 'Saturday', 'zeko-business' ),
		);

		$by_day = array();
		foreach ( $hours as $h ) {
			$by_day[ (int) $h->day_of_week ] = $h;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
		echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput

		if ( 'always' === $hours_mode ) {
			echo '<span class="zbp-open-badge zbp-open-badge--open">' . esc_html__( 'Open 24/7', 'zeko-business' ) . '</span>';
			echo '<p>' . esc_html__( 'This business is open around the clock.', 'zeko-business' ) . '</p>';
		} elseif ( 'closed' === $hours_mode ) {
			echo '<span class="zbp-open-badge zbp-open-badge--closed">' . esc_html__( 'Permanently closed', 'zeko-business' ) . '</span>';
		} elseif ( 'none' === $hours_mode || empty( $by_day ) ) {
			echo '<span class="zbp-open-badge zbp-open-badge--no_hours">' . esc_html__( 'No hours listed', 'zeko-business' ) . '</span>';
		} else {
			if ( \ZBE\Core\Plans::allows( (string) ( $business->plan ?: 'free' ), 'hours' ) ) {
				echo '<span class="zbp-open-badge zbp-open-badge--' . esc_attr( $status ) . '">'
					. ( 'open' === $status ? esc_html__( 'Open now', 'zeko-business' ) : esc_html__( 'Closed now', 'zeko-business' ) )
					. '</span>';
			}

			echo '<table class="zbp-hours-table zbp-widget-hours">';
			foreach ( $day_names as $i => $day ) {
				$h = $by_day[ $i ] ?? null;
				echo '<tr>';
				echo '<td class="zbp-hours-table__day">' . esc_html( $day ) . '</td>';
				echo '<td class="zbp-hours-table__time">';
				if ( $h && ! empty( $h->is_closed ) ) {
					echo '<span class="zbp-hours-table__closed">' . esc_html__( 'Closed', 'zeko-business' ) . '</span>';
				} elseif ( $h && ! empty( $h->open_time ) && ! empty( $h->close_time ) ) {
					echo esc_html( date_i18n( 'g:i A', strtotime( $h->open_time ) ) . ' — ' . date_i18n( 'g:i A', strtotime( $h->close_time ) ) );
				} else {
					echo '—';
				}
				echo '</td>';
				echo '</tr>';
			}
			echo '</table>';
		}

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Form.
	 *
	 * @param mixed $instance Instance.
	 */
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'zeko-business' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<?php
	}

	/**
	 * Update.
	 *
	 * @param mixed $new_instance New instance.
	 * @param mixed $old_instance Old instance.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = sanitize_text_field( (string) ( $new_instance['title'] ?? '' ) );

		return $instance;
	}

	/**
	 * Resolve the business currently being viewed (a single business listing),
	 * or null when the widget is not rendered in that context.
	 */
	private function current_business(): ?object {
		$object = get_queried_object();

		if ( ! $object instanceof \WP_Post || 'zeko_business' !== $object->post_type ) {
			return null;
		}

		return Services::businesses()->get_by_post_id( (int) $object->ID );
	}
}
