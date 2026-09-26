<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Domain;

use ZBE\Repository\MentionRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Structured business-mention layer on top of zeko-qa questions.
 *
 * Zeko-qa has no business relation on questions, so zeko-business maintains a
 * light index (zbe_question_mentions) mapping question_id <-> business_id. The
 * index is populated whenever zeko-qa fires `zeko_qa_question_created`, by
 * scanning the question title/content for the business name (matched_on =
 * 'business') and the owner display name (matched_on = 'owner').
 * It also powers the "Ask this business" prefill: the single-listing Q&A tab
 * links to the zeko-qa ask form with ?ask_business=<id>, which this service
 * turns into a default title of "@BusinessName".
 */
class MentionService {

	/**
	 * Repo.
	 *
	 * @var mixed Repo.
	 */
	private $repo;

	/**
	 * Construct.
	 *
	 * @param MentionRepository $repo Repo.
	 */
	public function __construct( MentionRepository $repo ) {
		$this->repo = $repo;
	}

	/**
	 * Register the plugin hooks that keep the index current and prefill the
	 * ask form. Call once (from Ecosystem::init or bootstrap).
	 */
	public function register_hooks(): void {
		add_action( 'zeko_qa_question_created', array( $this, 'handle_question_created' ), 10, 3 );
		add_filter( 'zeko_qa_ask_defaults', array( $this, 'ask_defaults' ), 10, 2 );
	}

	/**
	 * Called by zeko-qa when a question is inserted. Scans the title/content
	 * for mentions of any approved business (name or owner name) and records
	 * them in the index.
	 *
	 * @param int       $question_id * @param int   $user_id.
	 * @param int|float $user_id User id.
	 * @param array     $data Raw insert data (may hold title/content).
	 */
	public function handle_question_created( $question_id, $user_id = 0, $data = array() ): void {
		$question_id = (int) $question_id;
		if ( $question_id < 1 ) {
			return;
		}

		// Replace any existing index rows for this question, then rebuild.
		$this->repo->delete_for_question( $question_id );

		$title   = isset( $data['title'] ) ? (string) $data['title'] : '';
		$content = isset( $data['content'] ) ? (string) $data['content'] : '';

		if ( '' === $title && '' === $content ) {
			$q = $this->repo->get_question( $question_id );
			if ( $q ) {
				$title   = (string) $q->title;
				$content = (string) $q->content;
			}
		}

		$haystack = mb_strtolower( $title . "\n" . $content );
		if ( '' === trim( $haystack ) ) {
			return;
		}

		$businesses = $this->repo->get_active_businesses();
		if ( empty( $businesses ) ) {
			return;
		}

		foreach ( $businesses as $biz ) {
			$name_l = (string) $biz->name_l;

			// Business-name match (also honours the explicit "@Name" mention tag).
			if ( '' !== $name_l && false !== mb_strpos( $haystack, $name_l ) ) {
				$this->repo->add( $question_id, (int) $biz->id, 'business' );
				continue;
			}

			// Owner display-name match.
			if ( (int) $biz->owner_id > 0 ) {
				$owner = get_userdata( (int) $biz->owner_id );
				if ( $owner && '' !== $owner->display_name ) {
					$owner_l = mb_strtolower( $owner->display_name );
					if ( mb_strlen( $owner_l ) > 2 && false !== mb_strpos( $haystack, $owner_l ) ) {
						$this->repo->add( $question_id, (int) $biz->id, 'owner' );
					}
				}
			}
		}
	}

	/**
	 * Questions that mention a business (matched_on = 'business') for the
	 * single-listing "Q&A" tab. Uses the structured index with a LIKE fallback
	 * so pre-indexed/historical questions still surface.
	 *
	 * @return object[]
	 * @param int $business_id Business id.
	 */
	public function get_business_questions( int $business_id ): array {
		$ids = $this->repo->get_question_ids_for_business( $business_id, 'business', 20 );

		if ( empty( $ids ) ) {
			return array();
		}

		return $this->repo->get_questions( $ids );
	}

	/**
	 * Mentions matched on the owner's name for the single-listing "Requests" tab.
	 *
	 * @return object[]
	 * @param int $business_id Business id.
	 */
	public function get_owner_mentions( int $business_id ): array {
		$ids = $this->repo->get_question_ids_for_business( $business_id, 'owner', 20 );

		if ( empty( $ids ) ) {
			return array();
		}

		return $this->repo->get_questions( $ids );
	}

	/**
	 * All questions mentioning any business owned by the given user (portal
	 * "Requests & Mentions" tab).
	 *
	 * @return object[]
	 * @param int $owner_id Owner id.
	 */
	public function get_owner_portal_mentions( int $owner_id ): array {
		$ids = $this->repo->get_question_ids_for_owner( $owner_id, 50 );

		if ( empty( $ids ) ) {
			return array();
		}

		return $this->repo->get_questions( $ids );
	}

	/**
	 * Populate zeko-qa ask-form defaults when arriving from the
	 * "Ask this business" CTA (?ask_business=<id>).
	 *
	 * @param mixed $defaults Defaults.
	 * @param array $atts Atts.
	 */
	public function ask_defaults( $defaults, $atts = array() ) {
		unset( $atts );
		$defaults = is_array( $defaults ) ? $defaults : array(
			'title'            => '',
			'content'          => '',
			'mention_business' => 0,
		);

		if ( ! empty( $defaults['title'] ) ) {
			return $defaults;
		}

		$business_id = isset( $_GET['ask_business'] ) ? (int) $_GET['ask_business'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $business_id < 1 ) {
			return $defaults;
		}

		$biz = $this->get_business( $business_id );
		if ( ! $biz || '' === (string) $biz->name ) {
			return $defaults;
		}

		$defaults['title']            = '@' . $biz->name . ' ';
		$defaults['mention_business'] = $business_id;

		return $defaults;
	}

	/**
	 * Public URL for the "Ask this business" CTA.
	 *
	 * @param int $business_id Business id.
	 */
	public function ask_url( int $business_id ): string {
		$page = $this->ask_page_url();

		return add_query_arg( array( 'ask_business' => $business_id ), $page );
	}

	/**
	 * Business.
	 *
	 * @param int $business_id Business id.
	 */
	private function get_business( int $business_id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'zbp_businesses';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name FROM {$table} WHERE id = %d",
				$business_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return $row ?: null;
	}

	/**
	 * Ask page url.
	 */
	private function ask_page_url(): string {
		$page = get_page_by_path( 'ask-a-question' );
		if ( $page ) {
			return get_permalink( $page );
		}

		// Fall back to a search for the ask-form shortcode page.
		$shortcode = 'zeko_qa_ask_form';
		if ( shortcode_exists( $shortcode ) ) {
			$pages = get_posts(
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'fields'      => 'ids',
					'numberposts' => -1,
				)
			);
			foreach ( $pages as $pid ) {
				if ( has_shortcode( get_post_field( 'post_content', $pid ), $shortcode ) ) {
					return get_permalink( $pid );
				}
			}
		}

		return home_url( '/' );
	}
}
