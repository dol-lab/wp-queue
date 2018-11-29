<?php
/**
 * WP_Queue Library.
 *
 * @package WP_Queue
 */

use WP_Queue\Queue;
use WP_Queue\QueueManager;

/*
---------------------------------------------------------------------------------------------------------------------
Plugin Name: WP Queue
Version: 2.0.1
Plugin URI: https://github.com/wp-queue/wp-queue
Description: A plugin for background processes
Author: The WP Queue Team.
Author URI: https://www.wp-queue.com
Text Domain: wp-queue
License: GPL v3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
----------------------------------------------------------------------------------------------------------------------
*/
// Define Versions.
global $wpqueue_db_version;
$wpqueue_db_version = '1.0.0';

register_activation_hook( __FILE__, 'wp_queue_options' );
require_once trailingslashit( dirname( __FILE__ ) ) . 'autoloader.php';

if ( ! function_exists( 'wp_queue_prep_major_change' ) ) {
	/**
	 * Function executed on plugins_loaded hook.
	 */
	add_action(
		'plugins_loaded',
		function() {
			global $wpqueue_db_version;

			// If version dont match, prep imforza for a major version change.
			if ( get_site_option( 'wpqueue_db_version' ) !== $wpqueue_db_version ) {
				wp_queue_prep_major_change();
			}
		}
	);

	/**
	 * This function preps ands applies any major changes between versions of the plugin.
	 */
	function wp_queue_prep_major_change() {
		// Current Database version.
		global $wpqueue_db_version;

		// Last version.
		$installed_ver = get_option( 'wpqueue_db_version' );

		// Do all the update magic in here.
		if ( $installed_ver !== $wpqueue_db_version ) {

			/* v1.0.0 */
			if ( version_compare( $installed_ver, '1.0.0', '<' ) ) {
				// Do something.
			}
			/* End v1.0.0 Update */

			/* Never modify */
			update_option( 'wpqueue_db_version', $wpqueue_db_version ); // Update option to latest version.
		}
	}
}

if ( ! function_exists( 'wp_queue' ) ) {
	/**
	 * Return Queue instance.
	 *
	 * @param string $connection Connection to initialize.
	 *
	 * @return Queue
	 */
	function wp_queue( $connection = '' ) {
		if ( empty( $connection ) ) {
			$connection = apply_filters( 'wp_queue_default_connection', 'database' );
		}

		return QueueManager::resolve( $connection );
	}
}


if ( ! function_exists( 'wp_queue_wpdb_init' ) ) {

	/**
	 * Initialize DB tables in WPDB global.
	 *
	 * @return void
	 */
	function wp_queue_wpdb_init() {
		global $wpdb;

		// Register table with wpdb.
		if ( ! isset( $wpdb->queue_jobs ) ) {
			$wpdb->queue_jobs = $wpdb->prefix . 'queue_jobs';
			$wpdb->tables[]   = 'queue_jobs';
		}

		if ( ! isset( $wpdb->queue_failures ) ) {
			$wpdb->queue_failures = $wpdb->prefix . 'queue_failures';
			$wpdb->tables[]       = 'queue_failures';
		}
	}
}

if ( ! function_exists( 'wp_queue_uninstall_options' ) ) {
	/**
	 * WP Queue Uninstall Options.
	 *
	 * @access public
	 * @return void
	 */
	function wp_queue_uninstall_options() {

		delete_option( 'wp_queue_version' );
		delete_option( 'wp_queue_db_version' );
		delete_option( 'wp_queue_api_version' );
		delete_option( 'wp_queue_debug' );

	}
}

if ( ! function_exists( 'wp_queue_install_tables' ) ) {
	/**
	 * Install database tables
	 */
	function wp_queue_install_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$wpdb->hide_errors();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$wpdb->prefix}queue_jobs (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				job longtext NOT NULL,
				category tinytext NOT NULL,
				attempts tinyint(3) NOT NULL DEFAULT 0,
				priority tinyint(4) NOT NULL DEFAULT 0,
				reserved_at datetime DEFAULT NULL,
				available_at datetime NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id)
				) $charset_collate;";

		dbDelta( $sql );

		$sql = "CREATE TABLE {$wpdb->prefix}queue_failures (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				job longtext NOT NULL,
				error text DEFAULT NULL,
				failed_at datetime NOT NULL,
				PRIMARY KEY  (id)
				) $charset_collate;";

		dbDelta( $sql );
	}
}


if ( ! function_exists( 'wp_queue_empty_tables' ) ) {
	/**
	 * Empty database tables.
	 */
	function wp_queue_empty_tables() {

		global $wpdb;

		wp_queue_wpdb_init();

		$wpdb->query( "TRUNCATE TABLE $wpdb->queue_jobs" );
		$wpdb->query( "TRUNCATE TABLE $wpdb->queue_failures" );

	}
}

if ( ! function_exists( 'wp_queue_uninstall_tables' ) ) {
	/**
	 * Un-Install database tables
	 */
	function wp_queue_uninstall_tables() {

		global $wpdb;

		wp_queue_wpdb_init();

		$wpdb->query( "DROP TABLE IF EXISTS $wpdb->queue_jobs" );
		$wpdb->query( "DROP TABLE IF EXISTS $wpdb->queue_failures" );

	}
}

if ( ! function_exists( 'wp_queue_count_jobs' ) ) {

	/**
	 * WP Queue Count Jobs.
	 *
	 * @access public
	 * @param string $category Optional category to label the job type.
	 * @return int             Returns Number of jobs in queue.
	 */
	function wp_queue_count_jobs( $category = '' ) {

		global $wpdb;

		wp_queue_wpdb_init();

		// TODO:
		// Arguments to get count by category.
		// Arguments to get count by attempts.
		// Arguments to get count by priority.
		// Arguments to get count by reserved_at, available_at, created_at dates or date ranges.
		$job_count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->queue_jobs" );

		return $job_count;

	}
}

if ( ! function_exists( 'wp_queue_get_jobs' ) ) {

	/**
	 * WP Queue Count Jobs.
	 *
	 * @access public
	 * @param string $args Arguments.
	 * @return ArrayObject List of jobs from the database.
	 */
	function wp_queue_get_jobs( $args = '' ) {

		global $wpdb;

		wp_queue_wpdb_init();

		// TODO:
		// Arguments to get by category
		// Arguments to get by attempts
		// Arguments to get by priority
		// Arguments to get by reserved_at, available_at, created_at dates or date ranges.
		$jobs = $wpdb->get_results( "SELECT * FROM $wpdb->queue_jobs" );

		return $jobs;

	}
}

if ( ! function_exists( 'wp_queue_get_job_failures' ) ) {

	/**
	 * WP Queue Count Jobs.
	 *
	 * @access public
	 * @param string $args Arguments.
	 * @return ArrayObject  List of falied jobs from the database.
	 */
	function wp_queue_get_job_failures( $args = '' ) {

		global $wpdb;

		wp_queue_wpdb_init();

		// TODO:
		// Arguments to get by category
		// Arguments to get by attempts
		// Arguments to get by priority
		// Arguments to get by reserved_at, available_at, created_at dates or date ranges.
		$failures = $wpdb->get_results( "SELECT * FROM $wpdb->queue_failures" );

		return $failures;

	}
}


if ( ! function_exists( 'wp_queue_debug' ) ) {

	/**
	 * WP Queue Debug Mode
	 *
	 * @access public
	 * @param string $debug_mode (default: 'false') Debug Mode.
	 * @return void
	 */
	function wp_queue_debug( $debug_mode = 'false' ) {

		if ( 'true' === $debug_mode ) {

			update_option( 'wp_queue_debug', 'true', 'yes' );

			add_filter(
				'wp_queue_default_connection',
				function() {
					return 'sync';
				}
			);

		} else {

			update_option( 'wp_queue_debug', 'false', 'no' );

		}

	}
}
