<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Integration;

use ZBE\Core\Services;
use ZBE\Integration\Adapters\JobsAdapter;
use ZBE\Integration\Adapters\ShopAdapter;
use ZBE\Integration\Adapters\QAAdapter;
use ZBE\Integration\Adapters\LearnAdapter;
use ZBE\Integration\Adapters\FreelanceAdapter;
use ZBE\Integration\Adapters\MentorAdapter;
use ZBE\Integration\Adapters\RewardsAdapter;

defined( 'ABSPATH' ) || exit;

/** Class Ecosystem. */
class Ecosystem {
	/**
	 * Initialized.
	 *
	 * @var bool Initialized.
	 */
	private static bool $initialized = false;

	/**
	 * Jobs.
	 *
	 * @var JobsAdapter Jobs.
	 */
	private JobsAdapter $jobs;
	/**
	 * Shop.
	 *
	 * @var ShopAdapter Shop.
	 */
	private ShopAdapter $shop;
	/**
	 * Qa.
	 *
	 * @var QAAdapter Qa.
	 */
	private QAAdapter $qa;
	/**
	 * Learn.
	 *
	 * @var LearnAdapter Learn.
	 */
	private LearnAdapter $learn;
	/**
	 * Freelance.
	 *
	 * @var FreelanceAdapter Freelance.
	 */
	private FreelanceAdapter $freelance;
	/**
	 * Mentor.
	 *
	 * @var MentorAdapter Mentor.
	 */
	private MentorAdapter $mentor;
	/**
	 * Rewards.
	 *
	 * @var RewardsAdapter Rewards.
	 */
	private RewardsAdapter $rewards;

	/**
	 * Init.
	 */
	public function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		$this->jobs      = new JobsAdapter();
		$this->shop      = new ShopAdapter();
		$this->qa        = new QAAdapter();
		$this->learn     = new LearnAdapter();
		$this->freelance = new FreelanceAdapter();
		$this->mentor    = new MentorAdapter();
		$this->rewards   = new RewardsAdapter();

		add_filter( 'zeko_dashboard_tabs', array( $this, 'register_dashboard_tab' ) );
		add_action( 'zeko_dashboard_tab_content_businesses', array( $this, 'render_dashboard_tab' ) );
		add_filter( 'zeko_nav_items', array( $this, 'add_nav_items' ) );
		add_action( 'zeko_register_notification_sources', array( $this, 'register_notifications' ) );
		add_filter( 'zeko_activity_feed_items', array( $this, 'add_activity_items' ), 10, 2 );
		add_filter( 'zeko_theme_profile_stats', array( $this, 'add_profile_stats' ), 10, 2 );
		add_action( 'zeko_profile_view_sections', array( $this, 'render_profile_businesses' ) );
		add_action( 'zbe_business_tabs', array( $this, 'add_integration_tabs' ), 20 );
		add_action( 'zbe_business_tab_content', array( $this, 'render_integration_tabs' ), 20, 2 );
		add_filter( 'zeko_global_search', array( $this, 'add_to_global_search' ), 10, 2 );

		Services::mention_service()->register_hooks();
	}

	/**
	 * Dashboard tab.
	 *
	 * @param array $tabs Tabs.
	 */
	public function register_dashboard_tab( array $tabs ): array {
		$tabs['businesses'] = array(
			'title'    => __( 'Businesses', 'zeko-business' ),
			'icon'     => 'dashicons-building',
			'priority' => 30,
		);
		return $tabs;
	}

	/**
	 * Render dashboard tab.
	 */
	public function render_dashboard_tab(): void {
		$user_id    = get_current_user_id();
		$businesses = Services::businesses()->get_by_owner( $user_id, 20 );

		if ( empty( $businesses ) ) {
			echo '<div class="zbp-empty-state"><p>' . esc_html__( "You don't have any businesses yet.", 'zeko-business' ) . '</p></div>';
			echo '<p><a href="' . esc_url( \ZBE\Plugin::page_url( 'submit' ) ) . '" class="zbp-btn zbp-btn--primary">' . esc_html__( 'Create a Business', 'zeko-business' ) . '</a></p>';
			return;
		}

		echo '<div class="zbp-portal-stat-cards">';
		echo '<div class="zbp-portal-stat-card"><div class="zbp-portal-stat-card__number">' . esc_html( count( $businesses ) ) . '</div><div class="zbp-portal-stat-card__label">' . esc_html__( 'My Businesses', 'zeko-business' ) . '</div></div>';

		$total_views = 0;
		foreach ( $businesses as $b ) {
			$total_views += (int) $b->view_count;
		}
		echo '<div class="zbp-portal-stat-card"><div class="zbp-portal-stat-card__number">' . esc_html( number_format( $total_views ) ) . '</div><div class="zbp-portal-stat-card__label">' . esc_html__( 'Total views', 'zeko-business' ) . '</div></div>';
		echo '</div>';

		echo '<h3>' . esc_html__( 'This Week', 'zeko-business' ) . '</h3>';
		echo '<div class="zbp-portal-stat-cards" style="margin-bottom:1.5rem">';
		foreach ( $businesses as $biz ) {
			$views_week = Services::analytics_repo()->daily_totals_for_business( (int) $biz->id, 7, 'view' );
			$views_sum  = array_sum( $views_week );
			$reviews    = (int) $biz->review_count;
			$followers  = Services::followers()->count( (int) $biz->id );
			$biz_url    = home_url( '/businesses/' . $biz->slug . '/' );
			?>
			<div class="zbp-portal-stat-card">
				<div class="zbp-portal-stat-card__number"><?php echo esc_html( (int) $views_sum ); ?></div>
				<div class="zbp-portal-stat-card__label">
					<a href="<?php echo esc_url( $biz_url ); ?>" style="color:var(--color-primary);text-decoration:none;font-weight:600"><?php echo esc_html( $biz->name ); ?></a>
					<span style="display:block;margin-top:0.25rem;font-size:0.75rem">
						<?php
						printf(
							/* translators: 1: number of views. 2: number of reviews. 3: number of followers */
							esc_html__( '%1$s views 7d · %2$s reviews · %3$s followers', 'zeko-business' ),
							esc_html( number_format( $views_sum ) ),
							esc_html( number_format( $reviews ) ),
							esc_html( number_format( $followers ) )
						);
						?>
					</span>
				</div>
			</div>
			<?php
		}
		echo '</div>';

		$latest_reviews = Services::reviews()->get_recent( 5 );
		if ( ! empty( $latest_reviews ) ) {
			echo '<h3>' . esc_html__( 'Latest Reviews', 'zeko-business' ) . '</h3>';
			echo '<div style="margin-bottom:1.5rem">';
			foreach ( $latest_reviews as $rev ) {
				$biz      = Services::businesses()->get( (int) $rev->business_id );
				$biz_name = $biz ? $biz->name : '';
				$user     = get_userdata( (int) $rev->user_id );
				$author   = $user ? $user->display_name : __( 'A customer', 'zeko-business' );
				$stars    = str_repeat( '★', (int) $rev->rating ) . str_repeat( '☆', 5 - (int) $rev->rating );
				?>
				<div style="padding:0.5rem 0;border-bottom:1px solid var(--color-border)">
					<span style="font-weight:600"><?php echo esc_html( $author ); ?></span>
					<span style="color:#f59e0b"><?php echo esc_html( $stars ); ?></span>
					<?php if ( $biz_name ) : ?>
						<span style="color:var(--color-text-light);font-size:0.8125rem">
							<?php
							printf(
								/* translators: %s: formatted date */
								esc_html__( 'on %s', 'zeko-business' ),
								'<a href="' . esc_url( home_url( '/businesses/' . $biz->slug . '/' ) ) . '">' . esc_html( $biz_name ) . '</a>'
							);
							?>
						</span>
					<?php endif; ?>
					<?php if ( ! empty( $rev->content ) ) : ?>
						<p style="margin:0.25rem 0 0;font-size:0.875rem;color:var(--color-text-light)"><?php echo esc_html( wp_trim_words( $rev->content, 20 ) ); ?></p>
					<?php endif; ?>
				</div>
				<?php
			}
			echo '</div>';
		}

		echo '<p><a href="' . esc_url( \ZBE\Plugin::page_url( 'portal' ) ) . '" class="zbp-btn zbp-btn--primary">' . esc_html__( 'Open Business Portal', 'zeko-business' ) . '</a></p>';
	}

	/**
	 * Add nav items.
	 *
	 * @param array $items Items.
	 */
	public function add_nav_items( array $items ): array {
		$pages = get_option( 'zbe_pages', array() );

		$url = static function ( string $key, string $fallback ) use ( $pages ): string {
			if ( ! empty( $pages[ $key ] ) ) {
				$permalink = get_permalink( (int) $pages[ $key ] );
				if ( $permalink ) {
					return $permalink;
				}
			}
			return home_url( '/' . $fallback . '/' );
		};

		$items['primary'][] = array(
			'title'    => __( 'Businesses', 'zeko-business' ),
			'url'      => $url( 'directory', 'business-directory' ),
			'order'    => 30,
			'children' => array(
				array(
					'title' => __( 'Directory', 'zeko-business' ),
					'url'   => $url( 'directory', 'business-directory' ),
				),
				array(
					'title' => __( 'Add Your Business', 'zeko-business' ),
					'url'   => $url( 'submit', 'add-business' ),
				),
				array(
					'title' => __( 'My Businesses', 'zeko-business' ),
					'url'   => $url( 'dashboard', 'my-businesses' ),
				),
				array(
					'title' => __( 'Business Portal', 'zeko-business' ),
					'url'   => $url( 'portal', 'business-portal' ),
				),
			),
		);

		return $items;
	}

	/**
	 * Notifications.
	 *
	 * @param array $sources Sources.
	 */
	public function register_notifications( array $sources ): array {
		$sources['business'] = array(
			'label' => __( 'Business Updates', 'zeko-business' ),
			'icon'  => 'dashicons-building',
			'types' => array(
				'business_approved'     => __( 'Your business has been approved', 'zeko-business' ),
				'business_suspended'    => __( 'Your business has been suspended', 'zeko-business' ),
				'new_review'            => __( 'New review on your business', 'zeko-business' ),
				'new_follower'          => __( 'New follower on your business', 'zeko-business' ),
				'claim_approved'        => __( 'Your business claim has been approved', 'zeko-business' ),
				'claim_rejected'        => __( 'Your business claim was rejected', 'zeko-business' ),
				'verification_approved' => __( 'Business verified', 'zeko-business' ),
				'verification_rejected' => __( 'Verification request rejected', 'zeko-business' ),
				'staff_invited'         => __( 'You have been invited to manage a business', 'zeko-business' ),
			),
		);
		return $sources;
	}

	/**
	 * Add activity items.
	 *
	 * @param array $items Items.
	 * @param int   $limit Limit.
	 */
	public function add_activity_items( array $items, int $limit = 5 ): array {
		$recent = Services::businesses()->find(
			array(
				'status'   => 'active',
				'orderby'  => 'date_created',
				'order'    => 'DESC',
				'per_page' => min( max( 1, $limit ), 5 ),
				'page'     => 1,
			)
		);

		foreach ( $recent as $biz ) {
			/* translators: %s: business name */
			$items[] = array(
				'type' => 'business_created',
				'icon' => 'dashicons-building',
				/* translators: %s: business name */
				'text' => sprintf( __( 'New business registered: %s', 'zeko-business' ), $biz->name ),
				'time' => sprintf(
					/* translators: %s: human readable time difference */
					__( '%s ago', 'zeko-business' ),
					human_time_diff( strtotime( $biz->date_created ), time() )
				),
				'url'  => home_url( '/businesses/' . $biz->slug . '/' ),
			);
		}
		return $items;
	}

	/**
	 * Add profile stats.
	 *
	 * @param array $stats Stats.
	 * @param int   $user_id User id.
	 */
	public function add_profile_stats( array $stats, int $user_id ): array {
		$count = Services::businesses()->count( array( 'owner_id' => $user_id ) );
		if ( $count > 0 ) {
			$stats['businesses'] = array(
				'count' => $count,
				'label' => __( 'Businesses', 'zeko-business' ),
				'icon'  => 'dashicons-building',
				'url'   => $this->directory_url(),
			);
		}
		return $stats;
	}

	/**
	 * Directory url.
	 */
	public function directory_url(): string {
		$pages = get_option( 'zbe_pages', array() );

		if ( ! empty( $pages['directory'] ) ) {
			$permalink = get_permalink( (int) $pages['directory'] );
			if ( $permalink ) {
				return $permalink;
			}
		}

		return home_url( '/business-directory/' );
	}

	/**
	 * Render profile businesses.
	 *
	 * @param int $user_id User id.
	 */
	public function render_profile_businesses( int $user_id ): void {
		$businesses = Services::businesses()->get_by_owner( $user_id, 10 );
		if ( empty( $businesses ) ) {
			return;
		}

		$portal_url = \ZBE\Plugin::page_url( 'portal' );

		echo '<section class="zbp-profile-businesses">';
		echo '<h3>' . esc_html__( 'Businesses', 'zeko-business' ) . '</h3>';
		echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem">';
		foreach ( $businesses as $biz ) {
			$listing_url = home_url( '/businesses/' . $biz->slug . '/' );
			printf(
				'<a href="%s" class="zbp-business-card"><div class="zbp-business-card__info"><span class="zbp-business-card__name">%s</span><span class="zbp-business-card__meta"><span>★ %s</span></span><span class="zbp-business-card__meta"><a href="%s" style="font-size:0.75rem;color:var(--color-primary);text-decoration:none">%s</a></span></div></a>',
				esc_url( $listing_url ),
				esc_html( $biz->name ),
				esc_html( number_format( (float) $biz->avg_rating, 1 ) ),
				esc_url( $portal_url . '?business=' . (int) $biz->id ),
				esc_html__( 'Manage', 'zeko-business' )
			);
		}
		echo '</div></section>';
	}

	/**
	 * Add integration tabs.
	 *
	 * @param array $tabs Tabs.
	 */
	public function add_integration_tabs( array $tabs ): array {
		if ( $this->jobs->is_active() ) {
			$tabs['jobs'] = __( 'Jobs', 'zeko-business' );
		}
		if ( $this->shop->is_active() ) {
			$tabs['products'] = __( 'Products', 'zeko-business' );
		}
		if ( $this->qa->is_active() ) {
			$tabs['qa'] = __( 'Q&A', 'zeko-business' );
		}
		if ( $this->learn->is_active() ) {
			$tabs['courses'] = __( 'Courses', 'zeko-business' );
		}
		if ( $this->freelance->is_active() ) {
			$tabs['freelance'] = __( 'Projects', 'zeko-business' );
		}
		if ( $this->mentor->is_active() ) {
			$tabs['mentoring'] = __( 'Mentoring', 'zeko-business' );
		}
		if ( $this->rewards->is_active() ) {
			$tabs['rewards'] = __( 'Rewards', 'zeko-business' );
		}
		return $tabs;
	}

	/**
	 * Render integration tabs.
	 *
	 * @param string $tab Tab.
	 * @param int    $business_id Business id.
	 */
	public function render_integration_tabs( string $tab, int $business_id ): void {
		switch ( $tab ) {
			case 'jobs':
				if ( $this->jobs->is_active() ) {
					$jobs = $this->jobs->get_business_jobs( $business_id );
					if ( ! empty( $jobs ) ) {
						echo '<h3>' . esc_html__( 'Jobs', 'zeko-business' ) . '</h3>';
						foreach ( $jobs as $job ) {
							$url = home_url( '/jobs/' . ( $job->post_name ?? '' ) );
							printf(
								'<div class="zbp-service-card" style="margin-bottom:1rem"><div class="zbp-service-card__body"><div class="zbp-service-card__name"><a href="%s" style="color:var(--color-primary);text-decoration:none">%s</a></div></div></div>',
								esc_url( $url ),
								esc_html( $job->post_title ?? '' )
							);
						}
					}
				}
				break;

			case 'products':
				if ( $this->shop->is_active() && class_exists( 'Zeko_Shop' ) ) {
					$products = \Zeko_Shop::instance()->get_db()->get_products_by_external( 'business', $business_id );
					if ( ! empty( $products ) ) {
						echo '<h3>' . esc_html__( 'Products', 'zeko-business' ) . '</h3>';
						foreach ( $products as $product ) {
							$pid        = (int) ( $product['product_id'] ?? 0 );
							$title      = $product['title'] ?? '';
							$price      = (float) ( $product['price'] ?? 0 );
							$url        = home_url( '/shop/' );
							$price_html = $price > 0 ? '<span style="color:var(--color-text-light);font-size:0.875rem;margin-left:0.5rem">' . esc_html( number_format( $price, 2 ) ) . '</span>' : '';
							printf(
								'<div class="zbp-service-card" style="margin-bottom:1rem"><div class="zbp-service-card__body"><div class="zbp-service-card__name"><a href="%s" style="color:var(--color-primary);text-decoration:none">%s</a>%s</div></div></div>',
								esc_url( $url ),
								esc_html( $title ),
								wp_kses_post( $price_html )
							);
						}
					}
				}
				break;

			case 'qa':
				if ( $this->qa->is_active() ) {
					$questions = $this->qa->get_business_questions( $business_id );
					if ( ! empty( $questions ) ) {
						echo '<h3>' . esc_html__( 'Questions', 'zeko-business' ) . '</h3>';
						foreach ( $questions as $q ) {
							printf( '<div class="zbp-service-card" style="margin-bottom:1rem"><div class="zbp-service-card__body"><div class="zbp-service-card__name">%s</div></div></div>', esc_html( $q->post_title ?? '' ) );
						}
					}
				}
				break;

			case 'courses':
				if ( $this->learn->is_active() ) {
					$courses = $this->learn->get_business_courses( $business_id );
					if ( ! empty( $courses ) ) {
						echo '<h3>' . esc_html__( 'Courses', 'zeko-business' ) . '</h3>';
						foreach ( $courses as $course ) {
							$url = home_url( '/courses/' . ( $course->post_name ?? '' ) );
							printf(
								'<div class="zbp-service-card" style="margin-bottom:1rem"><div class="zbp-service-card__body"><div class="zbp-service-card__name"><a href="%s" style="color:var(--color-primary);text-decoration:none">%s</a></div></div></div>',
								esc_url( $url ),
								esc_html( $course->post_title ?? '' )
							);
						}
					}
				}
				break;

			case 'freelance':
				if ( $this->freelance->is_active() ) {
					$projects = $this->freelance->get_business_projects( $business_id );
					if ( ! empty( $projects ) ) {
						echo '<h3>' . esc_html__( 'Projects', 'zeko-business' ) . '</h3>';
						foreach ( $projects as $project ) {
							$url = home_url( '/projects/' . ( $project->slug ?? '' ) );
							printf(
								'<div class="zbp-service-card" style="margin-bottom:1rem"><div class="zbp-service-card__body"><div class="zbp-service-card__name"><a href="%s" style="color:var(--color-primary);text-decoration:none">%s</a></div></div></div>',
								esc_url( $url ),
								esc_html( $project->title ?? $project->post_title ?? '' )
							);
						}
					}
				}
				break;

			case 'mentoring':
				if ( $this->mentor->is_active() ) {
					$sessions = $this->mentor->get_business_sessions( $business_id );
					if ( ! empty( $sessions ) ) {
						echo '<h3>' . esc_html__( 'Mentoring Sessions', 'zeko-business' ) . '</h3>';
						foreach ( $sessions as $session ) {
							printf(
								'<div class="zbp-service-card" style="margin-bottom:1rem"><div class="zbp-service-card__body"><div class="zbp-service-card__name">%s</div></div></div>',
								esc_html( $session->topic ?? $session->title ?? '' )
							);
						}
					}
				}
				break;

			case 'rewards':
				if ( $this->rewards->is_active() ) {
					$badges = $this->rewards->get_business_badges( $business_id );
					if ( ! empty( $badges ) ) {
						echo '<h3>' . esc_html__( 'Earned Badges', 'zeko-business' ) . '</h3>';
						foreach ( $badges as $badge ) {
							printf(
								'<div class="zbp-service-card" style="margin-bottom:1rem"><div class="zbp-service-card__body"><div class="zbp-service-card__name">%s</div></div></div>',
								esc_html( $badge->badge_name ?? $badge->title ?? '' )
							);
						}
					}
				}
				break;
		}
	}

	/**
	 * Add to global search.
	 *
	 * @param array  $results Results.
	 * @param string $query Query.
	 */
	public function add_to_global_search( array $results, string $query ): array {
		if ( '' === trim( $query ) ) {
			return $results;
		}

		$hits = array();

		// Name matches (highest relevance).
		foreach ( Services::businesses()->find(
			array(
				'status'   => 'active',
				'search'   => $query,
				'per_page' => 8,
				'page'     => 1,
				'orderby'  => 'directory',
				'order'    => 'DESC',
			)
		) as $biz ) {
			$hits[ (int) $biz->id ] = array(
				'biz'   => $biz,
				'score' => 3,
			);
		}

		// City matches (medium relevance).
		foreach ( Services::businesses()->find(
			array(
				'status'   => 'active',
				'search'   => $query,
				'per_page' => 8,
				'page'     => 1,
				'city'     => $query,
			)
		) as $biz ) {
			$id = (int) $biz->id;
			if ( isset( $hits[ $id ] ) ) {
				$hits[ $id ]['score'] = max( $hits[ $id ]['score'], 2 );
			} else {
				$hits[ $id ] = array(
					'biz'   => $biz,
					'score' => 2,
				);
			}
		}

		// Description substring (lower relevance) — raw query only.
		global $wpdb;
		$table = 'zbp_businesses';
		$like  = '%' . $wpdb->esc_like( $query ) . '%';
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
        //phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$desc_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, slug FROM {$table} WHERE status = 'active' AND description LIKE %s ORDER BY avg_rating DESC LIMIT 8",
				$like
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		foreach ( $desc_rows as $row ) {
			$id = (int) $row->id;
			if ( ! isset( $hits[ $id ] ) ) {
				$biz = Services::businesses()->get( $id );
				if ( $biz ) {
					$hits[ $id ] = array(
						'biz'   => $biz,
						'score' => 1,
					);
				}
			}
		}

		// Sort by score descending, then rating descending.
		uasort(
			$hits,
			function ( $a, $b ) {
				$diff = $b['score'] - $a['score'];
				if ( 0 !== $diff ) {
					return $diff;
				}
				return ( $b['biz']->avg_rating <=> $a['biz']->avg_rating );
			}
		);

		foreach ( array_slice( array_values( $hits ), 0, 5 ) as $hit ) {
			/** Business entity for the hit. @var \ZBE\Entity\Business $biz */
			$biz     = $hit['biz'];
			$post_id = (int) $biz->post_id;
			$thumb   = $post_id > 0 ? get_the_post_thumbnail_url( $post_id, 'thumbnail' ) : false;

			$entry = array(
				'type'  => 'business',
				'title' => $biz->name,
				'url'   => home_url( '/businesses/' . $biz->slug . '/' ),
				'icon'  => 'dashicons-building',
			);

			if ( $thumb ) {
				$entry['thumbnail'] = $thumb;
			}

			$results[] = $entry;
		}

		return $results;
	}
}
