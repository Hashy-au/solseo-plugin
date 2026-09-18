<?php
/**
 * Drives the long running jobs, one chunk per request.
 *
 * The browser is the runner. A page with a job on it asks for one chunk,
 * waits for the answer, and asks for the next, so the person who pressed the
 * button watches it happen and can stop it. WP-Cron was the other option and
 * it is not a promise: it fires when somebody visits the site, so a quiet site
 * never finishes, and the person who pressed the button is told nothing.
 *
 * Closing the tab stops the work with its place saved. Re-opening the screen
 * offers to carry on.
 *
 * @package SolSEO
 */

namespace SolSEO\Jobs;

defined( 'ABSPATH' ) || exit;

/**
 * The job registry, the endpoint and the state machine.
 */
class Runner {

	/** Where every job's state is kept. */
	const OPTION = 'solseo_jobs';

	/** How long one chunk may hold the lock. */
	const LOCK = 30;

	/** How many failed object IDs are worth keeping. */
	const FAILED_KEPT = 200;

	/**
	 * Hook the endpoint in.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * The jobs this site can run.
	 *
	 * A fourth job is one class file and one line here, or a filter callback
	 * from an add-on with no change to this plugin at all.
	 *
	 * @return array Keyed by job id, holding class names.
	 */
	public static function jobs() {
		$jobs = array(
			'import' => __NAMESPACE__ . '\\Import_Job',
			'images' => __NAMESPACE__ . '\\Image_Job',
			'score'  => __NAMESPACE__ . '\\Score_Job',
		);

		/**
		 * Filter the jobs the runner will drive.
		 *
		 * Each entry is keyed by job id and holds the name of a class
		 * extending SolSEO\Jobs\Job.
		 *
		 * @param array $jobs Job id to class name.
		 */
		$jobs = (array) apply_filters( 'solseo_job_types', $jobs );

		return array_filter(
			$jobs,
			static function ( $job_class ) {
				return is_string( $job_class ) && is_subclass_of( $job_class, __NAMESPACE__ . '\\Job' );
			}
		);
	}

	/**
	 * The class behind a job id.
	 *
	 * @param string $id Job id.
	 * @return string|null
	 */
	public static function job( $id ) {
		$jobs = self::jobs();

		return isset( $jobs[ $id ] ) ? $jobs[ $id ] : null;
	}

	/**
	 * Register the endpoint.
	 */
	public static function register_routes() {
		register_rest_route(
			'solseo/v1',
			'/job',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'act' ),
					'permission_callback' => array( __CLASS__, 'may_run' ),
					'args'                => array(
						'job'    => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
						'action' => array(
							'required' => true,
							'type'     => 'string',
							'enum'     => array( 'start', 'step', 'stop' ),
						),
						'token'  => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
						'args'   => array(
							'type'    => 'object',
							'default' => array(),
						),
					),
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'read' ),
					'permission_callback' => array( __CLASS__, 'may_run' ),
					'args'                => array(
						'job' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);
	}

	/**
	 * Whether this request may drive this job.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return bool
	 */
	public static function may_run( $request ) {
		$job_class = self::job( (string) $request->get_param( 'job' ) );

		if ( ! $job_class ) {
			return false;
		}

		return current_user_can( $job_class::capability() );
	}

	/**
	 * Answer with a job's state.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public static function read( $request ) {
		return rest_ensure_response( self::state( (string) $request->get_param( 'job' ) ) );
	}

	/**
	 * Start, step or stop a job.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function act( $request ) {
		$id        = (string) $request->get_param( 'job' );
		$action    = (string) $request->get_param( 'action' );
		$job_class = self::job( $id );

		if ( ! $job_class ) {
			return new \WP_Error( 'solseo_unknown_job', __( 'That is not a job this site can run.', 'solseo' ), array( 'status' => 400 ) );
		}

		if ( 'start' === $action ) {
			return self::start( $job_class, $id, (array) $request->get_param( 'args' ) );
		}

		$state = self::state( $id );
		$token = (string) $request->get_param( 'token' );

		if ( '' === $state['token'] || $token !== $state['token'] ) {
			return new \WP_Error(
				'solseo_job_moved',
				__( 'This job is being run somewhere else. Reload the page to see where it is up to.', 'solseo' ),
				array(
					'status' => 409,
					'state'  => $state,
				)
			);
		}

		if ( 'stop' === $action ) {
			$state['status']  = 'stopped';
			$state['message'] = __( 'Stopped. Your place is saved.', 'solseo' );

			return rest_ensure_response( self::save( $id, $state ) );
		}

		return self::step( $job_class, $id, $state );
	}

	/**
	 * Plan a job and put it in the running state.
	 *
	 * @param string $job_class Job class name.
	 * @param string $id    Job id.
	 * @param array  $args  Raw arguments.
	 * @return \WP_REST_Response|\WP_Error
	 */
	protected static function start( $job_class, $id, array $args ) {
		$plan = $job_class::plan( $args );

		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		$state = self::blank( $id, $job_class::chunk() );

		$state['status']     = 'running';
		$state['args']       = isset( $plan['args'] ) ? (array) $plan['args'] : array();
		$state['total']      = isset( $plan['total'] ) ? (int) $plan['total'] : 0;
		$state['cursor']     = isset( $plan['cursor'] ) ? (int) $plan['cursor'] : 0;
		$state['token']      = self::token();
		$state['user_id']    = get_current_user_id();
		$state['started_at'] = current_time( 'mysql', true );
		$state['message']    = $job_class::label( $state['args'] );

		return rest_ensure_response( self::save( $id, $state ) );
	}

	/**
	 * Do one chunk, or one verification pass.
	 *
	 * @param string $job_class Job class name.
	 * @param string $id    Job id.
	 * @param array  $state The job state.
	 * @return \WP_REST_Response|\WP_Error
	 */
	protected static function step( $job_class, $id, array $state ) {
		if ( ! in_array( $state['status'], array( 'running', 'verifying', 'stopped' ), true ) ) {
			return rest_ensure_response( $state );
		}

		if ( ! self::lock( $id ) ) {
			return new \WP_Error(
				'solseo_job_busy',
				__( 'Another chunk of this job is still running.', 'solseo' ),
				array(
					'status' => 409,
					'state'  => $state,
				)
			);
		}

		/*
		 * Which of the two this step is has to be decided before anything
		 * touches the status, and it is decided in one pure function so a test
		 * can ask. Reading it after setting the status to running is how the
		 * verification became unreachable, and the job that could never finish
		 * looked exactly like one that was working.
		 */
		if ( 'verify' === self::next_action( $state ) ) {
			$state = self::advance( $state, array( 'verify' => $job_class::verify( $state ) ) );

			self::unlock( $id );

			return rest_ensure_response( self::save( $id, $state ) );
		}

		$state['status'] = 'running';

		/*
		 * A chunk that was started and never reported is a chunk that fell
		 * over. Halve it and go again from where it started. At one object a
		 * second failure means that object is the problem, so it is named and
		 * stepped over rather than stopping the job for good.
		 */
		if ( $state['attempting'] ) {
			$state = self::retry( $state );
		}

		$state['attempting'] = $state['cursor'] + 1;

		self::save( $id, $state );

		$state = self::advance( $state, $job_class::work( $state ) );

		if ( 'verifying' === $state['status'] ) {
			$state['message'] = __( 'Checking the work landed.', 'solseo' );
		}

		self::unlock( $id );

		return rest_ensure_response( self::save( $id, $state ) );
	}

	/**
	 * What the next step of a job should do.
	 *
	 * Pure, and separate from the step that does it, because getting this
	 * wrong produces a job that runs for ever while looking like one that is
	 * working: the bar sits at full, the message never changes, and nothing
	 * anywhere reports an error.
	 *
	 * @param array $state The job state.
	 * @return string One of work, verify or nothing.
	 */
	public static function next_action( array $state ) {
		if ( 'verifying' === $state['status'] ) {
			return 'verify';
		}

		if ( in_array( $state['status'], array( 'running', 'stopped' ), true ) ) {
			return 'work';
		}

		return 'nothing';
	}

	/**
	 * Fold a chunk's result into the state.
	 *
	 * Pure: no database, no options, no WordPress. This is the state machine,
	 * and keeping it pure is what lets the tests exercise every branch of it
	 * with nothing loaded.
	 *
	 * @param array $state  The job state.
	 * @param array $result What work() or verify() returned.
	 * @return array
	 */
	public static function advance( array $state, array $result ) {
		$state['attempting'] = 0;

		if ( isset( $result['verify'] ) ) {
			$verify = (array) $result['verify'];

			$state['status']   = 'done';
			$state['verified'] = ! empty( $verify['ok'] );
			$state['message']  = isset( $verify['message'] ) ? (string) $verify['message'] : '';

			return self::record( $state, isset( $verify['failed'] ) ? (array) $verify['failed'] : array() );
		}

		$state['cursor']   = isset( $result['cursor'] ) ? (int) $result['cursor'] : $state['cursor'];
		$state['done']    += isset( $result['done'] ) ? (int) $result['done'] : 0;
		$state['changed'] += isset( $result['changed'] ) ? (int) $result['changed'] : 0;
		$state['chunk']    = (int) $state['full_chunk'];

		$state = self::record( $state, isset( $result['failed'] ) ? (array) $result['failed'] : array() );

		if ( ! empty( $result['finished'] ) ) {
			$state['status'] = 'verifying';
		}

		return $state;
	}

	/**
	 * Halve a chunk that fell over, or step past the one object that did.
	 *
	 * Pure.
	 *
	 * @param array $state The job state.
	 * @return array
	 */
	public static function retry( array $state ) {
		if ( (int) $state['chunk'] > 1 ) {
			$state['chunk'] = max( 1, (int) floor( $state['chunk'] / 2 ) );

			return $state;
		}

		$state               = self::record( $state, array( (int) $state['attempting'] ) );
		$state['cursor']     = (int) $state['attempting'];
		$state['done']      += 1;
		$state['attempting'] = 0;
		$state['chunk']      = (int) $state['full_chunk'];

		return $state;
	}

	/**
	 * Note the objects a chunk could not deal with.
	 *
	 * The list is capped because a site with four thousand bad rows must not
	 * put four thousand numbers in an option. The count is kept in full.
	 *
	 * Pure.
	 *
	 * @param array $state  The job state.
	 * @param array $failed Object IDs.
	 * @return array
	 */
	protected static function record( array $state, array $failed ) {
		if ( ! $failed ) {
			return $state;
		}

		$state['failed_total'] += count( $failed );

		foreach ( $failed as $id ) {
			if ( count( $state['failed'] ) >= self::FAILED_KEPT ) {
				break;
			}

			$state['failed'][] = (int) $id;
		}

		return $state;
	}

	/**
	 * A job that has never run.
	 *
	 * Pure.
	 *
	 * @param string $id    Job id.
	 * @param int    $chunk How many objects a chunk covers.
	 * @return array
	 */
	public static function blank( $id, $chunk = 100 ) {
		return array(
			'job'          => (string) $id,
			'status'       => 'idle',
			'args'         => array(),
			'total'        => 0,
			'done'         => 0,
			'changed'      => 0,
			'cursor'       => 0,
			'chunk'        => (int) $chunk,
			'full_chunk'   => (int) $chunk,
			'failed'       => array(),
			'failed_total' => 0,
			'attempting'   => 0,
			'verified'     => false,
			'message'      => '',
			'token'        => '',
			'user_id'      => 0,
			'started_at'   => '',
			'touched_at'   => '',
		);
	}

	/**
	 * A job's stored state, or a blank one.
	 *
	 * @param string $id Job id.
	 * @return array
	 */
	public static function state( $id ) {
		$all       = (array) get_option( self::OPTION, array() );
		$job_class = self::job( $id );
		$chunk     = $job_class ? $job_class::chunk() : 100;

		if ( ! isset( $all[ $id ] ) || ! is_array( $all[ $id ] ) ) {
			return self::blank( $id, $chunk );
		}

		return array_merge( self::blank( $id, $chunk ), $all[ $id ] );
	}

	/**
	 * Write a job's state back.
	 *
	 * @param string $id    Job id.
	 * @param array  $state The job state.
	 * @return array The state as stored.
	 */
	public static function save( $id, array $state ) {
		$state['touched_at'] = current_time( 'mysql', true );

		$all        = (array) get_option( self::OPTION, array() );
		$all[ $id ] = $state;

		update_option( self::OPTION, $all, false );

		return $state;
	}

	/**
	 * Forget a job.
	 *
	 * @param string $id Job id.
	 */
	public static function forget( $id ) {
		$all = (array) get_option( self::OPTION, array() );

		unset( $all[ $id ] );

		update_option( self::OPTION, $all, false );
	}

	/**
	 * Take the lock for one chunk.
	 *
	 * @param string $id Job id.
	 * @return bool
	 */
	protected static function lock( $id ) {
		if ( get_transient( 'solseo_job_lock_' . $id ) ) {
			return false;
		}

		set_transient( 'solseo_job_lock_' . $id, 1, self::LOCK );

		return true;
	}

	/**
	 * Let the next chunk in.
	 *
	 * @param string $id Job id.
	 */
	protected static function unlock( $id ) {
		delete_transient( 'solseo_job_lock_' . $id );
	}

	/**
	 * A fresh token, so a second tab is told rather than fighting.
	 *
	 * @return string
	 */
	protected static function token() {
		return substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 12 );
	}
}
