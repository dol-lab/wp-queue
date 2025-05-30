<?php
/**
 * Datababase Connection interface Class file.
 *
 * @package WP_Queue
 */

namespace WP_Queue\Connections;

use Exception;
use WP_Queue\Job;

/**
 * Database Connection
 */
class DatabaseConnection implements ConnectionInterface {

	/**
	 * Database connection.
	 *
	 * @var \wpdb
	 */
	protected $database;

	/**
	 * Database table for jobs.
	 *
	 * @var string
	 */
	protected $jobs_table;

	/**
	 * Database table for job failures.
	 *
	 * @var string
	 */
	protected $failures_table;

	/**
	 * DatabaseQueue constructor.
	 *
	 * @param \wpdb $wpdb WPDB global object.
	 */
	public function __construct( $wpdb ) {
		$this->database       = $wpdb;
		$this->jobs_table     = $this->database->prefix . 'queue_jobs';
		$this->failures_table = $this->database->prefix . 'queue_failures';
	}

	/**
	 * Push a job onto the queue.
	 *
	 * @param Job    $job       Job to push to queue.
	 * @param int    $delay     Delay time for job.
	 * @param string $category  Category tag to label jobs in db.
	 *
	 * @return bool|int
	 */
	public function push( Job $job, $delay = 0, $category = '' ) {
		$result = $this->database->insert(
			$this->jobs_table,
			array(
				'job'          => serialize( $job ),
				'category'     => $category,
				'available_at' => $this->datetime( $delay ),
				'created_at'   => $this->datetime(),
			)
		);

		if ( ! $result ) {
			return false;
		}

		return $this->database->insert_id;
	}

	/**
	 * Retrieve a job from the queue.
	 *
	 * @return bool|Job
	 */
	public function pop() {
		$this->release_reserved();

		$sql = $this->database->prepare(
			"
			SELECT * FROM {$this->jobs_table}
			WHERE reserved_at IS NULL
			AND available_at <= %s
			ORDER BY available_at, id
			LIMIT 1
		",
			$this->datetime()
		);

		$raw_job = $this->database->get_row( $sql );

		if ( is_null( $raw_job ) ) {
			return false;
		}

		$job = $this->vitalize_job( $raw_job );

		$this->reserve( $job );

		return $job;
	}

	/**
	 * Delete a job from the queue.
	 *
	 * @param Job $job Job to delete.
	 *
	 * @return bool
	 */
	public function delete( $job ) {
		$where = array(
			'id' => $job->id(),
		);

		if ( $this->database->delete( $this->jobs_table, $where ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Release a job back onto the queue.
	 *
	 * @param Job $job Job to release.
	 *
	 * @return bool
	 */
	public function release( $job ) {
		$data  = array(
			'job'         => serialize( $job ),
			'attempts'    => $job->attempts(),
			'reserved_at' => null,
		);
		$where = array(
			'id' => $job->id(),
		);

		if ( $this->database->update( $this->jobs_table, $data, $where ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Push a job onto the failure queue.
	 *
	 * @param Job       $job        Failed Job.
	 * @param Exception $exception  Exception thrown.
	 *
	 * @return bool
	 */
	public function failure( $job, Exception $exception ) {
		$insert = $this->database->insert(
			$this->failures_table,
			array(
				'job'       => serialize( $job ),
				'error'     => $this->format_exception( $exception ),
				'failed_at' => $this->datetime(),
			)
		);

		if ( $insert ) {
			$this->delete( $job );

			return true;
		}

		return false;
	}

	/**
	 * Get total jobs in the queue.
	 *
	 * @return int
	 */
	public function jobs() {
		$sql = "SELECT COUNT(*) FROM {$this->jobs_table}";

		return (int) $this->database->get_var( $sql );
	}

	/**
	 * Get total jobs in the failures queue.
	 *
	 * @return int
	 */
	public function failed_jobs() {
		$sql = "SELECT COUNT(*) FROM {$this->failures_table}";

		return (int) $this->database->get_var( $sql );
	}

	/**
	 * Reserve a job in the queue.
	 *
	 * @param Job $job Job to reserve.
	 */
	protected function reserve( $job ) {
		$data = array(
			'reserved_at' => $this->datetime(),
		);

		$this->database->update(
			$this->jobs_table,
			$data,
			array(
				'id' => $job->id(),
			)
		);
	}

	/**
	 * Release reserved jobs back onto the queue.
	 */
	protected function release_reserved() {
		$expired = $this->datetime( -300 );

		$sql = $this->database->prepare(
			"
				UPDATE {$this->jobs_table}
				SET attempts = attempts + 1, reserved_at = NULL
				WHERE reserved_at <= %s",
			$expired
		);

		$this->database->query( $sql );
	}

	/**
	 * Vitalize Job with latest data.
	 *
	 * @param mixed $raw_job Raw job class string.
	 *
	 * @return Job
	 */
	protected function vitalize_job( $raw_job ) {
		$job = unserialize( $raw_job->job );

		$job->set_id( $raw_job->id );
		$job->set_attempts( $raw_job->attempts );
		$job->set_reserved_at( empty( $raw_job->reserved_at ) ? null : current_time( $raw_job->reserved_at ) );
		$job->set_available_at( current_time( $raw_job->available_at ) );
		$job->set_created_at( current_time( $raw_job->created_at ) );

		return $job;

	}

	/**
	 * Get MySQL datetime.
	 *
	 * @param int $offset Seconds, can pass negative int.
	 *
	 * @return string
	 */
	protected function datetime( $offset = 0 ) {
		$timestamp = time() + $offset;

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Format an exception error string.
	 *
	 * @param Exception $exception Exception to format.
	 *
	 * @return string
	 */
	protected function format_exception( Exception $exception ) {
		$string = get_class( $exception );

		if ( ! empty( $exception->getMessage() ) ) {
			$string .= " : {$exception->getMessage()}";
		}

		if ( ! empty( $exception->getCode() ) ) {
			$string .= " (#{$exception->getCode()})";
		}

		return $string;
	}

}
