<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Domain;

use ZBE\Repository\MentionRepository;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/email-html.php';

/**
 * Daily owner digest of new question/mention activity.
 *
 * Each business owner is emailed, at most once per day, an aggregate of the
 * questions that started mentioning any business they own since the previous
 * digest run. Uses `wp_mail` so real delivery depends on an SMTP/email setup;
 * the aggregation + last-sent bookkeeping are testable in CLI.
 *
 * The "last sent" watermark is stored in an option (`zbe_digest_last_sent`
 * in UTC). Only mention rows created strictly after the watermark are
 * included, which keeps a single daily run idempotent and prevents re-emailing
 * the same items on a second run within the same window.
 */
class DigestService {

	const OPTION_LAST_SENT = 'zbe_digest_last_sent';

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
	 * Cron entry point. Collects new owner-visible mentions since the last
	 * run, emails one digest per owner, then advances the watermark.
	 *
	 * @return int[] sent owner IDs (empty if none).
	 */
	public function run(): array {
		$since = $this->last_sent_ts();

		// First run ever: default to the previous 24h window so an admin turning.
		// the digest on doesn't dump the entire historical backlog.
		if ( $since < 1 ) {
			$since = time() - DAY_IN_SECONDS;
		}

		$rows = $this->repo->get_new_questions_since( $since );

		$sent = array();
		foreach ( $this->group_by_owner( $rows ) as $owner_id => $items ) {
			if ( $this->send_owner_digest( $owner_id, $items ) ) {
				$sent[] = $owner_id;
			}
		}

		// Always advance the watermark, even when nothing was emailed, so the.
		// next run only sees genuinely new items.
		update_option( self::OPTION_LAST_SENT, time(), false );

		return $sent;
	}

	/**
	 * Unix timestamp of the last successful digest run (0 if never set).
	 */
	public function last_sent_ts(): int {
		return (int) get_option( self::OPTION_LAST_SENT, 0 );
	}

	/**
	 * Group digest rows by owner, keyed by owner_id.
	 *
	 * @return array<int, array<int, object>>
	 * @param array $rows Rows from MentionRepository::get_new_questions_since().
	 */
	private function group_by_owner( array $rows ): array {
		$grouped = array();

		foreach ( $rows as $row ) {
			$owner_id = (int) $row->owner_id;
			if ( $owner_id < 1 ) {
				continue;
			}
			if ( ! isset( $grouped[ $owner_id ] ) ) {
				$grouped[ $owner_id ] = array();
			}
			$grouped[ $owner_id ][] = $row;
		}

		return $grouped;
	}

	/**
	 * Build and send a single digest email to one owner.
	 * email accepted the send (wp_mail returned true).
	 *
	 * @return bool True if the owner (a) has a deliverable address and (b) the
	 * @param int   $owner_id Owner id.
	 * @param array $items Items.
	 */
	private function send_owner_digest( int $owner_id, array $items ): bool {
		$user = get_userdata( $owner_id );
		if ( ! $user || ! $user->user_email ) {
			return false;
		}

		$business = get_option( 'blogname', '' );
		$total    = count( $items );
		$plural   = ( 1 === $total ) ? '' : 's';
		$subject  = sprintf(
			/* translators: %1$d: number of new items, %2$s: site name */
			__( '[%2$s] %1$d new question%3$s for your business', 'zeko-business' ),
			$total,
			$business,
			$plural
		);

		$message = $this->render_message( $items, $business );

		$email_parts = zbe_wrap_email( $message, $business, '', $subject );
		$headers     = $email_parts['html'] ? array( 'Content-Type: text/html; charset=UTF-8' ) : array();

		return (bool) wp_mail( $user->user_email, $subject, $email_parts['body'], $headers );
	}

	/**
	 * Render the plain-text digest body.
	 *
	 * @param array  $items Items.
	 * @param string $site_name Site name.
	 */
	private function render_message( array $items, string $site_name ): string {
		$total   = count( $items );
		$plural  = ( 1 === $total ) ? '' : 's';
		$lines   = array();
		$lines[] = sprintf(
			/* translators: %1$s: site name, %2$d: number of items */
			__( 'Howdy! %1$s has %2$d new question%3$s for one of your businesses:', 'zeko-business' ),
			$site_name,
			$total,
			$plural
		);
		$lines[] = '';

		$seen = array();
		foreach ( $items as $item ) {
			$business_name = (string) $item->business_name;
			$title         = (string) $item->q_title;

			// A question can mention multiple businesses; avoid listing the same.
			// question twice for one owner.
			if ( isset( $seen[ $item->question_id ] ) ) {
				continue;
			}
			$seen[ $item->question_id ] = true;

			$lines[] = sprintf(
				/* translators: %1$s: business name, %2$s: question title */
				__( '- %1$s: "%2$s"', 'zeko-business' ),
				$business_name,
				$title
			);
		}

		$lines[] = '';
		$lines[] = __( 'You can reply to any of these from the Questions area of your dashboard.', 'zeko-business' );
		$lines[] = __( 'This is your daily summary. You are receiving it because you own a business listed in the directory.', 'zeko-business' );

		return implode( "\n", $lines );
	}
}
