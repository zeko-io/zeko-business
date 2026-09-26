<?php
/**
 * File doc comment.
 *
 * @package Zeko_ZEKO_BUSINESS
 */

namespace ZBE\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Advises on switching to Zeko Business after migrating from a supported
 * external directory plugin.
 *
 * Wraps the "should you switch and can you disable the old plugin" logic so
 * the Import/Export page and the admin notice share one source of truth. It
 * never deletes foreign data; it only ever offers a deactivation (which is
 * reversible) once a source has been migrated successfully.
 */
class MigrationAdvisor {

	/**
	 * PLUGIN FILES.
	 *
	 * @var array<string,
	 */
	private const PLUGIN_FILES = array(
		'bpbp'         => array( 'bp-business-profile/bp-business-profile.php', 'bp-business-profile/plugin.php' ),
		'wpbm'         => array( 'business-directory-plugin/business-directory-plugin.php' ),
		'geodirectory' => array( 'geodirectory/geodirectory.php' ),
		'directorist'  => array( 'directorist/directorist-base.php', 'directorist/directorist.php' ),
		'wpbp'         => array( 'business-directory-5-star/business-directory-5-star.php' ),
		'hivepress'    => array( 'hivepress/hivepress.php' ),
		'listdom'      => array( 'listdom/listdom.php' ),
	);

	/**
	 * The basename of the installed plugin for a source, or null when it is
	 * not installed. Falls back to a loose basename match so renamed files and
	 * future releases still resolve.
	 *
	 * @param string $source Source.
	 */
	public function plugin_file( string $source ): ?string {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed = array_keys( get_plugins() );
		$known     = self::PLUGIN_FILES[ $source ] ?? array();

		foreach ( $known as $file ) {
			if ( in_array( $file, $installed, true ) ) {
				return $file;
			}
		}

		foreach ( $installed as $file ) {
			if ( $this->basename_matches( $file, $source ) ) {
				return $file;
			}
		}

		return null;
	}

	/**
	 * The user-facing label of the installed source plugin, from its header.
	 *
	 * @param string $source Source.
	 */
	public function plugin_label( string $source ): string {
		$file = $this->plugin_file( $source );

		if ( ! $file ) {
			return '';
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all = get_plugins();

		return isset( $all[ $file ]['Name'] ) ? (string) $all[ $file ]['Name'] : $file;
	}

	/**
	 * Whether the source's plugin is currently active on this install.
	 *
	 * @param string $source Source.
	 */
	public function is_plugin_active( string $source ): bool {
		$file = $this->plugin_file( $source );

		if ( ! $file ) {
			return false;
		}

		if ( function_exists( 'is_plugin_active' ) ) {
			return is_plugin_active( $file );
		}

		return in_array( $file, (array) get_option( 'active_plugins', array() ), true );
	}

	/**
	 * How many successful imports exist for a source (from the import log).
	 *
	 * @return int
	 * @param string $source Source.
	 */
	public function imported_count( string $source ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'zbp_import_log';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE source = %s AND status = 'imported'", $source ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return max( 0, (int) $count );
	}

	/**
	 * Whether a source has been migrated successfully — i.e. it once existed
	 * and at least one business was imported from it. This is the gate for
	 * showing the "deactivate old plugin" action.
	 *
	 * @param string $source Source.
	 */
	public function is_fully_migrated( string $source ): bool {
		return $this->imported_count( $source ) > 0;
	}

	/**
	 * Deactivate the installed plugin for a source. Refuses to run when the
	 * migration hasn't completed, nothing is installed, or the plugin is
	 * already inactive. Never deletes files or data.
	 *
	 * @param string $source Source.
	 */
	public function deactivate( string $source ): bool {
		if ( ! $this->is_fully_migrated( $source ) ) {
			return false;
		}

		$file = $this->plugin_file( $source );

		if ( ! $file || ! $this->is_plugin_active( $source ) ) {
			return false;
		}

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( $file, true );

		/**
		 * Fires after a supported directory plugin has been deactivated
		 * following a successful migration.
		 *
		 * @param string $source      Adapter key.
		 * @param string $plugin_file The deactivated plugin basename.
		 */
		do_action( 'zbe_migration_source_deactivated', $source, $file );

		return true;
	}

	/**
	 * Loose basename match (assumes standard `dir/dir.php` layout).
	 *
	 * @param string $file File.
	 * @param string $source Source.
	 */
	private function basename_matches( string $file, string $source ): bool {
		$expected = '';
		switch ( $source ) {
			case 'bpbp':
				$expected = 'bp-business';
				break;
			case 'wpbm':
				$expected = 'business-directory';
				break;
			case 'wpbp':
				$expected = 'business-directory-5';
				break;
			default:
				$expected = $source;
		}

		return false !== stripos( $file, $expected . '/' );
	}
}
