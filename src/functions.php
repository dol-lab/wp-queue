<?php
include_once( 'WP_Queue/Queue.php');
include_once( 'WP_Queue/QueueManager.php');
use WP_Queue\Queue;
use WP_Queue\QueueManager;

if ( ! function_exists( 'wp_queue' ) ) {
	/**
	 * Return Queue instance.
	 *
	 * @param string $connection
	 *
	 * @return Queue
	 */
	function wp_queue( $connection = '' ) {
		if( empty( $connection ) ) {
			$connection = apply_filters( 'wp_queue_default_connection', 'database' );
		}

		return QueueManager::resolve( $connection );
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

		$table_jobs = $wpdb->prefix . 'queue_jobs';
		$table_failures = $wpdb->prefix . 'queue_failures';

		$wpdb->query( "TRUNCATE TABLE $table_jobs" );
		$wpdb->query( "TRUNCATE TABLE $table_failures" );

	}
}

if ( ! function_exists( 'wp_queue_uninstall_tables' ) ) {
	/**
	 * Un-Install database tables
	 */
	function wp_queue_uninstall_tables() {

		global $wpdb;

		$table_jobs = $wpdb->prefix . 'queue_jobs';
		$table_failures = $wpdb->prefix . 'queue_failures';

		$wpdb->query( "DROP TABLE IF EXISTS $table_jobs" );
		$wpdb->query( "DROP TABLE IF EXISTS $table_failures" );

	}
}

if ( ! function_exists( 'wp_queue_count_jobs' ) ) {

	/**
	 * WP Queue Count Jobs.
	 *
	 * @access public
	 * @param string $category (default: '')
	 * @return void
	 */
	function wp_queue_count_jobs( $category = '' ) {

		global $wpdb;

		$job_count = $wpdb->get_var( "SELECT COUNT(*) FROM " . $wpdb->prefix . 'queue_jobs' . "");

		return $job_count;

	}
}
