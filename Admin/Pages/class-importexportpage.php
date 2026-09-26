<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Admin\Pages;

use ZBE\Core\Services;

defined( 'ABSPATH' ) || exit;

/** Class ImportExportPage. */
class ImportExportPage {
	/**
	 * Render.
	 */
	public static function render(): void {
		$result_msg  = '';
		$result_type = '';
		$migrator    = null;

		if ( ! empty( $_POST['zbp_action'] ) ) {
			check_admin_referer( 'zbp_import_export' );

			$action = sanitize_text_field( wp_unslash( $_POST['zbp_action'] ) );

			if ( 'export_businesses' === $action ) {
				require_once ZBE_PLUGIN_DIR . '/Import/class-exporter.php';
				\ZBE\Import\Exporter::businesses();
				return;
			}

			if ( 'export_reviews' === $action ) {
				require_once ZBE_PLUGIN_DIR . '/Import/class-exporter.php';
				\ZBE\Import\Exporter::reviews();
				return;
			}

			if ( 'export_services' === $action ) {
				require_once ZBE_PLUGIN_DIR . '/Import/class-exporter.php';
				\ZBE\Import\Exporter::services();
				return;
			}

			if ( 'import_csv' === $action ) {
				$type = sanitize_text_field( wp_unslash( $_POST['import_type'] ?? '' ) );
				if ( empty( $_FILES['csv_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- uploaded CSV; validated by Zeko_Core_Upload::validate_file() below.
					$result_msg  = 'Please select a CSV file.';
					$result_type = 'error';
				} else {
					$csv_valid = class_exists( '\Zeko_Core_Upload' ) ? \Zeko_Core_Upload::validate_file( $_FILES['csv_file'], 'csv' ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- uploaded CSV; validated here by Zeko_Core_Upload::validate_file().

					if ( is_wp_error( $csv_valid ) ) {
						$result_msg  = $csv_valid->get_error_message();
						$result_type = 'error';
					} else {
						require_once ZBE_PLUGIN_DIR . '/Import/class-importer.php';
						$importer = new \ZBE\Import\Importer(
							'zbp_' . $type,
							array( 'id', 'business_id', 'name', 'slug', 'status', 'email', 'phone', 'website', 'address', 'city', 'state', 'country', 'zip', 'avg_rating', 'review_count', 'date_created' )
						);

						switch ( $type ) {
							case 'businesses':
								$res = $importer->import_businesses_csv( $_FILES['csv_file']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- file already validated by Zeko_Core_Upload::validate_file().
								break;
							case 'reviews':
								$res = $importer->import_reviews_csv( $_FILES['csv_file']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- file already validated by Zeko_Core_Upload::validate_file().
								break;
							case 'services':
								$res = $importer->import_services_csv( $_FILES['csv_file']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- file already validated before Upload::validate_file().
								break;
							default:
								$res = array(
									'success' => false,
									'error'   => 'Unknown import type',
								);
						}

						if ( $res['success'] ) {
							$result_msg  = sprintf(
								'Import complete: %d imported, %d skipped, %d failed.',
								$res['imported'],
								$res['skipped'] ?? 0,
								$res['failed']
							);
							$result_type = 'success';
						} else {
							$result_msg  = 'Import failed: ' . ( $res['error'] ?? 'Unknown error' );
							$result_type = 'error';
						}
					}
				}
			}

			if ( 'migrate' === $action ) {
				$source = sanitize_text_field( wp_unslash( $_POST['migrate_source'] ?? '' ) );

				if ( empty( $source ) ) {
					$result_msg  = 'Please select a migration source.';
					$result_type = 'error';
				} else {
					require_once ZBE_PLUGIN_DIR . '/Import/class-migrator.php';
					$migrator = new \ZBE\Import\Migrator();

					$button = sanitize_key( $_POST['zbp_button'] ?? 'migrate' );

					if ( 'deactivate_source' === $button ) {
						$res         = self::run_deactivate_source( $source );
						$result_msg  = $res['message'];
						$result_type = $res['type'];
					} elseif ( 'preview' === $button ) {
						$preview = $migrator->preview( $source );

						if ( ! empty( $preview['success'] ) ) {
							$result_type = 'info';
							$result_msg  = self::preview_html( $preview );
						} else {
							$result_msg  = 'Preview failed: ' . ( $preview['error'] ?? 'Unknown error' );
							$result_type = 'error';
						}
					} else {
						$force = ! empty( $_POST['force_reimport'] );
						$res   = $migrator->start_migration( $source, $force );

						if ( ! empty( $res['background'] ) ) {
							$result_msg  = sprintf(
								'Background import of %s queued (%d businesses). WordPress cron will process it in batches; this page will show progress on the next visit.',
								$res['source'],
								$res['total']
							);
							$result_type = 'info';
						} elseif ( ! empty( $res['success'] ) ) {
							$result_msg  = sprintf(
								'Migration from %s complete: %d imported, %d skipped, %d failed, %d replaced. Categories added: %d. Photos: %d. Reviews: %d. Followers: %d.',
								$res['source'],
								$res['imported'],
								$res['skipped'] ?? 0,
								$res['failed'],
								$res['replaced'] ?? 0,
								$res['categories_added'] ?? 0,
								$res['photos_imported'] ?? 0,
								$res['reviews_imported'] ?? 0,
								$res['followers_imported'] ?? 0
							);
							$result_type = ( $res['failed'] > 0 || ! empty( $res['warnings'] ) ) ? 'warning' : 'success';

							if ( ! empty( $res['warnings'] ) ) {
								$result_msg .= self::warnings_html( $res['warnings'] );
							}
						} else {
							$result_msg  = 'Migration failed: ' . ( $res['error'] ?? 'Unknown error' );
							$result_type = 'error';
						}
					}
				}
			}

			if ( 'cancel_migrate' === $action ) {
				require_once ZBE_PLUGIN_DIR . '/Import/class-migrator.php';
				$migrator = new \ZBE\Import\Migrator();
				$migrator->cancel_job( sanitize_text_field( wp_unslash( $_POST['migrate_source'] ?? '' ) ) );
				$result_msg  = 'Migration job cancelled. Snapshot, pending batches and its progress state were removed.';
				$result_type = 'info';
			}

			if ( 'deactivate_source' === $action ) {
				$source = sanitize_text_field( wp_unslash( $_POST['migrate_source'] ?? '' ) );

				$result_msg  = self::run_deactivate_source( $source );
				$result_type = $result_msg['type'];
				$result_msg  = $result_msg['message'];
			}

			if ( 'purge_logs' === $action ) {
				require_once ZBE_PLUGIN_DIR . '/Import/class-migrator.php';
				$migrator = new \ZBE\Import\Migrator();
				$days     = max( 1, min( 3650, (int) sanitize_text_field( wp_unslash( $_POST['retention_days'] ?? 90 ) ) ) );
				$removed  = $migrator->purge_logs( $days );
				update_option( 'zbe_import_log_retention_days', $days, false );
				$auto        = (int) get_option( 'zbe_import_log_retention_days', 0 );
				$result_msg  = sprintf(
					'Purged %d import-log row(s) older than %d days. Automatic retention is %s.',
					$removed,
					$days,
					$auto > 0 ? 'ON (daily sweep, capped at once per day)' : 'OFF'
				);
				$result_type = 'info';
			}
		}

		if ( ! class_exists( 'ZBE\Import\Migrator' ) || null === $migrator ) {
			require_once ZBE_PLUGIN_DIR . '/Import/class-migrator.php';
			$migrator = new \ZBE\Import\Migrator();
		}

		$sources    = $migrator->get_available_sources();
		$jobs       = $migrator->get_all_progress();
		$import_log = Services::import_logs()->recent( 20 );
		$retention  = (int) get_option( 'zbe_import_log_retention_days', 0 ) ?: 90;

		require_once ZBE_PLUGIN_DIR . '/Import/class-migrationadvisor.php';
		$advisor = new \ZBE\Import\MigrationAdvisor();

		?>
		<div class="wrap zbp-admin-wrap">
			<h1>Import &amp; Export</h1>

			<?php if ( $result_msg ) : ?>
				<div class="notice notice-<?php echo esc_attr( $result_type ); ?> is-dismissible">
					<p><?php echo wp_kses_post( $result_msg ); ?></p>
				</div>
			<?php endif; ?>

			<div class="zbp-dashboard-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

				<div class="zbp-settings-section">
					<h2>Export Data</h2>
					<p>Download your business directory data as CSV files.</p>
					<form method="post">
						<?php wp_nonce_field( 'zbp_import_export' ); ?>
						<input type="hidden" name="zbp_action" value="export_businesses" />
						<button type="submit" class="button button-primary">Export Businesses (CSV)</button>
					</form>
					<br/>
					<form method="post" style="display:inline">
						<?php wp_nonce_field( 'zbp_import_export' ); ?>
						<input type="hidden" name="zbp_action" value="export_reviews" />
						<button type="submit" class="button">Export Reviews (CSV)</button>
					</form>
					<form method="post" style="display:inline;margin-left:0.5rem">
						<?php wp_nonce_field( 'zbp_import_export' ); ?>
						<input type="hidden" name="zbp_action" value="export_services" />
						<button type="submit" class="button">Export Services (CSV)</button>
					</form>
				</div>

				<div class="zbp-settings-section">
					<h2>Import CSV</h2>
					<p>Import businesses, reviews, or services from CSV files.</p>
					<form method="post" enctype="multipart/form-data">
						<?php wp_nonce_field( 'zbp_import_export' ); ?>
						<input type="hidden" name="zbp_action" value="import_csv" />
						<div class="zbp-portal-form__row">
							<label class="zbp-form-label">Import Type</label>
							<select name="import_type" class="zbp-form-select" required>
								<option value="">Select type...</option>
								<option value="businesses">Businesses</option>
								<option value="reviews">Reviews</option>
								<option value="services">Services</option>
							</select>
						</div>
						<div class="zbp-portal-form__row">
							<label class="zbp-form-label">CSV File</label>
							<input type="file" name="csv_file" accept=".csv" required class="zbp-form-input" />
						</div>
						<button type="submit" class="button button-primary">Import CSV</button>
					</form>
				</div>

				<div class="zbp-settings-section" style="grid-column:1/-1">
					<h2>Migrate from Other Plugins</h2>
					<p>Automatically migrate business data from other directory plugins. Detected sources:</p>

					<div class="notice notice-info" style="margin:0 0 1rem">
						<p><strong>Switch to Zeko Business.</strong> Once you migrate a source, stop using that plugin and standardize on Zeko Business for your directory (listings, reviews, services, payments and analytics all live here). You can safely <strong>deactivate</strong> the old plugin below — your migrated data stays in Zeko Business. Keep the old plugin deactivated for a while; <em>only delete it</em> (Plugins → Installed Plugins → Delete) once you're confident everything you need has come across. Deleting an old plugin won't remove anything from Zeko Business.</p>
					</div>

					<?php if ( empty( $sources ) ) : ?>
						<p><em>No compatible plugins detected. Install and activate a supported directory plugin, then return here.</em></p>
					<?php else : ?>
					<form method="post">
							<?php wp_nonce_field( 'zbp_import_export' ); ?>
							<input type="hidden" name="zbp_action" value="migrate" />
							<input type="hidden" name="zbp_button" value="migrate" />
							<table class="widefat zbp-business-table" style="max-width:900px">
								<thead>
									<tr>
										<th>Source</th>
										<th>Version</th>
										<th>Data included</th>
										<th>Status</th>
										<th>Migration</th>
										<th>Action</th>
									</tr>
								</thead>
								<tbody>
								<?php
								foreach ( $sources as $key => $src ) :
									$migrated   = $advisor->is_fully_migrated( (string) $key );
									$deact_ok   = $migrated && $advisor->is_plugin_active( (string) $key );
									$plugin_lbl = $advisor->plugin_label( (string) $key );
									?>
									<tr>
										<td><strong><?php echo esc_html( $src['name'] ); ?></strong></td>
										<td><?php echo esc_html( $src['version'] ); ?></td>
										<td><?php echo esc_html( $src['capabilities'] ?? 'Listings' ); ?></td>
										<td>
											<span class="zbp-badge zbp-badge--<?php echo 'active' === $src['status'] ? 'active' : 'pending'; ?>">
												<?php echo esc_html( ucfirst( $src['status'] ) ); ?>
											</span>
										</td>
										<td>
											<?php if ( $migrated ) : ?>
												<span class="zbp-badge zbp-badge--active">Migrated (<?php echo (int) $advisor->imported_count( (string) $key ); ?>)</span>
											<?php else : ?>
												<span class="zbp-badge zbp-badge--pending">Not migrated</span>
											<?php endif; ?>
											<?php if ( $plugin_lbl && ! $advisor->is_plugin_active( (string) $key ) ) : ?>
												<div style="margin-top:0.25rem;color:#666;font-size:12px"><?php echo esc_html( $plugin_lbl ); ?> inactive</div>
											<?php endif; ?>
										</td>
										<td>
											<label>
												<input type="radio" name="migrate_source" value="<?php echo esc_attr( $key ); ?>" />
												Select
											</label>
											<?php if ( $deact_ok ) : ?>
												<div style="margin-top:0.35rem">
													<button type="submit" class="button" name="zbp_button" value="deactivate_source"
															onclick="return confirm('Deactivate <?php echo esc_js( $plugin_lbl ?: $key ); ?>? Its files and data are kept; your migrated Zeko Business data is unaffected and you can reactivate it anytime.');">
														Deactivate plugin
													</button>
												</div>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
							<br/>
							<label style="display:inline-block;margin-right:1.5rem">
								<input type="checkbox" name="force_reimport" value="1" />
								Re-import sources already in the import log (replaces the previously imported businesses)
							</label>
							<br/><br/>
							<button type="submit" class="button button-secondary" name="zbp_button" value="preview">
								Preview Selected Source
							</button>
							<button type="submit" class="button button-primary" name="zbp_button" value="migrate" onclick="return confirm('This will import all listings from the selected plugin into Zeko Business. Existing data will not be duplicated (unless re-import is ticked). Continue?')">
								Start Migration
							</button>
						</form>
					<?php endif; ?>
				</div>

					<?php if ( ! empty( $jobs ) ) : ?>
				<div class="zbp-settings-section" style="grid-column:1/-1">
					<h2>Migration Jobs</h2>
					<table class="widefat zbp-business-table" style="max-width:760px">
						<thead>
							<tr>
								<th>Source</th>
								<th>Status</th>
								<th>Progress</th>
								<th>Results</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $jobs as $key => $job ) : ?>
							<?php
							$pct   = (int) $job['total'] > 0 ? round( ( (int) $job['processed'] ) / (int) $job['total'] * 100 ) : 0;
							$badge = 'failed' === $job['status'] ? 'suspended' : ( 'completed' === $job['status'] ? 'active' : 'pending' );
							?>
							<tr>
								<td><strong><?php echo esc_html( $job['label'] ?? $key ); ?></strong></td>
								<td><span class="zbp-badge zbp-badge--<?php echo esc_attr( $badge ); ?>"><?php echo esc_html( ucfirst( $job['status'] ) ); ?></span></td>
								<td style="min-width:200px">
									<?php if ( (int) $job['total'] > 0 ) : ?>
										<div style="background:#e5e5e5;border-radius:999px;height:12px;overflow:hidden">
											<div style="width:<?php echo esc_attr( $pct ); ?>%;height:100%;background:#2271b1"></div>
										</div>
										<small><?php echo (int) $job['processed']; ?> / <?php echo (int) $job['total']; ?> (<?php echo (int) $pct; ?>%)</small>
									<?php else : ?>
										<small>—</small>
									<?php endif; ?>
								</td>
								<td>
									<small>
										imported <?php echo (int) $job['imported']; ?> · skipped <?php echo (int) $job['skipped']; ?> ·
										failed <?php echo (int) $job['failed']; ?> · replaced <?php echo (int) ( $job['replaced'] ?? 0 ); ?><br/>
										photos <?php echo (int) $job['photos_imported']; ?> · reviews <?php echo (int) $job['reviews_imported']; ?> ·
										followers <?php echo (int) $job['followers_imported']; ?>
										<?php if ( ! empty( $job['errors'] ) ) : ?>
											<br/>
											<?php foreach ( array_slice( $job['errors'], 0, 3 ) as $err ) : ?>
												<span style="color:#b32d2e">· <?php echo esc_html( $err ); ?></span><br/>
											<?php endforeach; ?>
										<?php endif; ?>
									</small>
								</td>
								<td>
									<form method="post" style="display:inline">
										<?php wp_nonce_field( 'zbp_import_export' ); ?>
										<input type="hidden" name="zbp_action" value="cancel_migrate" />
										<input type="hidden" name="migrate_source" value="<?php echo esc_attr( $key ); ?>" />
										<button type="submit" class="button button-link-delete"><?php echo 'completed' === $job['status'] ? 'Clear' : 'Cancel'; ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p><small>Background jobs are chained as WordPress cron events — they advance on site traffic / system cron and on every admin page load. You can leave this screen and come back later.</small></p>
				</div>
				<?php endif; ?>

				<div class="zbp-settings-section" style="grid-column:1/-1">
					<h2>Import Log</h2>
						<?php if ( empty( $import_log ) ) : ?>
						<p><em>No imports performed yet.</em></p>
					<?php else : ?>
						<table class="widefat zbp-business-table">
							<thead>
								<tr>
									<th>Source</th>
									<th>Source ID</th>
									<th>Business ID</th>
									<th>Status</th>
									<th>Error</th>
									<th>Date</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ( $import_log as $log ) : ?>
								<tr>
									<td><?php echo esc_html( $log->source ); ?></td>
									<td><?php echo esc_html( $log->source_id ); ?></td>
									<td><?php echo (int) $log->business_id; ?></td>
									<td>
										<span class="zbp-badge zbp-badge--<?php echo 'imported' === $log->status ? 'active' : 'suspended'; ?>">
											<?php echo esc_html( ucfirst( $log->status ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $log->error_message ?: '—' ); ?></td>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $log->date_created ) ) ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
					<br/>
					<form method="post" style="max-width:760px">
							<?php wp_nonce_field( 'zbp_import_export' ); ?>
						<input type="hidden" name="zbp_action" value="purge_logs" />
						<label for="retention_days">Keep import logs for <input type="number" id="retention_days" name="retention_days" min="1" max="3650" value="<?php echo esc_attr( $retention ); ?>" /> days</label>
						<button type="submit" class="button">Purge logs now &amp; apply retention</button>
						<p><small>Setting a value enables a daily automatic sweep (capped to run at most once per day). A value of 0 disables automatic retention but you can still purge manually.</small></p>
					</form>
				</div>

			</div>
		</div>
			<?php
	}

	/**
	 * Preview html.
	 *
	 * @param array $p P.
	 */
	private static function preview_html( array $p ): string {
		$mode = ! empty( $p['background'] )
			? 'Large source — the import will run in the background via WordPress cron.'
			: 'Small source — the import will run in this request when you start it.';

		$html = sprintf(
			'<strong>Preview of %s</strong> — %d business(es) detected. %s',
			esc_html( $p['source'] ),
			(int) $p['total'],
			esc_html( $mode )
		);

		$c     = $p['counts'];
		$html .= '<br/>Data to migrate: ' . esc_html(
			sprintf(
				'categories %d · photos %d · reviews %d · followers %d',
				(int) $c['categories'],
				(int) $c['photos'],
				(int) $c['reviews'],
				(int) $c['followers']
			)
		);

		if ( ! empty( $p['sample'] ) ) {
			$html .= '<table class="widefat" style="margin-top:0.5rem;max-width:760px">
                <thead><tr><th>Title</th><th>Status</th><th>City</th><th>Email</th><th>Categories</th><th>Photos / Reviews</th></tr></thead><tbody>';
			foreach ( $p['sample'] as $row ) {
				$html .= '<tr><td>' . esc_html( $row['title'] )
					. '</td><td>' . esc_html( $row['status'] )
					. '</td><td>' . esc_html( $row['city'] )
					. '</td><td>' . esc_html( $row['email'] )
					. '</td><td>' . esc_html( implode( ', ', $row['categories'] ) )
					. '</td><td>' . (int) $row['photos'] . ' / ' . (int) $row['reviews'] . '</td></tr>';
			}
			$html .= '</tbody></table>';
		}

		if ( ! empty( $p['warnings'] ) ) {
			$html .= '<details style="margin-top:0.5rem"><summary>Source warnings</summary><ul>';
			foreach ( array_slice( $p['warnings'], 0, 5 ) as $w ) {
				$html .= '<li>' . esc_html( $w ) . '</li>';
			}
			$html .= '</ul></details>';
		}

		return $html;
	}

	/**
	 * Warnings html.
	 *
	 * @param array $warnings Warnings.
	 */
	private static function warnings_html( array $warnings ): string {
		$html  = ' <details><summary>View warnings</summary><ul>';
		$slice = array_slice( $warnings, 0, 10 );
		foreach ( $slice as $w ) {
			$html .= '<li>' . esc_html( $w ) . '</li>';
		}
		if ( count( $warnings ) > 10 ) {
			$html .= '<li><em>… and ' . ( count( $warnings ) - 10 ) . ' more.</em></li>';
		}
		return $html . '</ul></details>';
	}

	/**
	 * Runs the deactivation of a source plugin, returning [message, type].
	 *
	 * @param string $source Source.
	 */
	private static function run_deactivate_source( string $source ): array {
		require_once ZBE_PLUGIN_DIR . '/Import/class-migrationadvisor.php';
		$advisor = new \ZBE\Import\MigrationAdvisor();

		if ( $advisor->deactivate( $source ) ) {
			return array(
				'message' => sprintf(
					/* translators: %s: source plugin name. */
					'%s has been deactivated. Your migrated Zeko Business directory is unaffected; the plugin files and data were kept in case you need them. You can now delete it via Plugins → Installed Plugins when you are ready.',
					esc_html( $advisor->plugin_label( $source ) ?: $source )
				),
				'type'    => 'success',
			);
		}

		return array(
			'message' => 'Could not deactivate the source plugin. Make sure its migration has completed and the plugin is still active.',
			'type'    => 'error',
		);
	}
}