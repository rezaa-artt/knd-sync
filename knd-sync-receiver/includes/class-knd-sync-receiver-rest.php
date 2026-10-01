<?php
/**
 * REST API routes for receiver.
 *
 * @package KND_Sync_Receiver
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers /knd-sync/v1 endpoints.
 */
final class KND_Sync_Receiver_REST {

	public const NAMESPACE = 'knd-sync/v1';

	/**
	 * @var KND_Sync_Receiver_Settings
	 */
	private KND_Sync_Receiver_Settings $settings;

	/**
	 * @var KND_Sync_Receiver_Logger
	 */
	private KND_Sync_Receiver_Logger $logger;

	/**
	 * @var KND_Sync_Receiver_Auth
	 */
	private KND_Sync_Receiver_Auth $auth;

	/**
	 * @var KND_Sync_Receiver_Post
	 */
	private KND_Sync_Receiver_Post $post;

	/**
	 * Constructor.
	 *
	 * @param KND_Sync_Receiver_Settings $settings Settings.
	 * @param KND_Sync_Receiver_Logger   $logger Logger.
	 * @param KND_Sync_Receiver_Auth     $auth Auth.
	 * @param KND_Sync_Receiver_Post     $post Post handler.
	 */
	public function __construct(
		KND_Sync_Receiver_Settings $settings,
		KND_Sync_Receiver_Logger $logger,
		KND_Sync_Receiver_Auth $auth,
		KND_Sync_Receiver_Post $post
	) {
		$this->settings = $settings;
		$this->logger   = $logger;
		$this->auth     = $auth;
		$this->post     = $post;
	}

	/**
	 * Hook REST registration.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this->auth, 'permission_callback' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/post',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'upsert_post' ),
				'permission_callback' => array( $this->auth, 'permission_callback' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview_post' ),
				'permission_callback' => array( $this->auth, 'permission_callback' ),
			)
		);
	}

	/**
	 * Connection status endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function status( WP_REST_Request $request ): WP_REST_Response {
		$request_id = $this->request_id( $request );

		$this->logger->info(
			'status_ok',
			__( 'Connection test succeeded.', 'knd-sync' ),
			array(
				'request_id'    => $request_id,
				'response_code' => 200,
			)
		);

		return new WP_REST_Response(
			array(
				'success'     => true,
				'request_id'  => $request_id,
				'status'      => 'connected',
				'plugin'      => 'knd-sync-receiver',
				'version'     => KND_SYNC_RECEIVER_VERSION,
				'site'        => home_url( '/' ),
				'time'        => gmdate( 'c' ),
				'hmac_ready'  => (string) $this->settings->get( 'hmac_secret', '' ) !== '',
			),
			200
		);
	}

	/**
	 * Create or update a post from payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function upsert_post( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$request_id = $this->request_id( $request );
		$payload    = $request->get_json_params();

		if ( ! is_array( $payload ) ) {
			return $this->error_response( 'INVALID_PAYLOAD', __( 'JSON payload required.', 'knd-sync' ), 400, $request_id );
		}

		$result = $this->post->upsert( $payload, $request_id, false );

		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500;
			$code   = $result->get_error_code();

			$this->logger->error(
				'post_upsert_failed',
				$result->get_error_message(),
				array(
					'request_id'    => $request_id,
					'response_code' => $status,
					'error'         => $code,
					'source_post_id'=> (int) ( $payload['source']['post_id'] ?? 0 ),
				)
			);

			return $this->error_response( (string) $code, $result->get_error_message(), $status, $request_id );
		}

		$status_code = ! empty( $result['created'] ) ? 201 : 200;

		$this->logger->success(
			'post_upserted',
			__( 'Post synchronized successfully.', 'knd-sync' ),
			array(
				'request_id'     => $request_id,
				'response_code'  => $status_code,
				'source_post_id' => (int) $result['source_post_id'],
				'target_post_id' => (int) $result['target_post_id'],
			)
		);

		return new WP_REST_Response(
			array(
				'success'         => true,
				'request_id'      => $request_id,
				'source_post_id'  => (int) $result['source_post_id'],
				'target_post_id'  => (int) $result['target_post_id'],
				'status'          => (string) $result['status'],
				'created'         => (bool) $result['created'],
			),
			$status_code
		);
	}

	/**
	 * Dry-run preview without persisting.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function preview_post( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$request_id = $this->request_id( $request );
		$payload    = $request->get_json_params();

		if ( ! is_array( $payload ) ) {
			return $this->error_response( 'INVALID_PAYLOAD', __( 'JSON payload required.', 'knd-sync' ), 400, $request_id );
		}

		$result = $this->post->upsert( $payload, $request_id, true );
		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 422;
			return $this->error_response( (string) $result->get_error_code(), $result->get_error_message(), $status, $request_id );
		}

		return new WP_REST_Response(
			array(
				'success'    => true,
				'request_id' => $request_id,
				'preview'    => $result,
			),
			200
		);
	}

	/**
	 * Resolve request id from header or generate.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	private function request_id( WP_REST_Request $request ): string {
		$header = (string) $request->get_header( 'x-knd-sync-request-id' );
		if ( $header !== '' ) {
			return sanitize_text_field( $header );
		}
		return wp_generate_uuid4();
	}

	/**
	 * Standard error envelope.
	 *
	 * @param string $code Code.
	 * @param string $message Message.
	 * @param int    $status HTTP status.
	 * @param string $request_id Request id.
	 */
	private function error_response( string $code, string $message, int $status, string $request_id ): WP_Error {
		return new WP_Error(
			$code,
			$message,
			array(
				'status'     => $status,
				'request_id' => $request_id,
				'success'    => false,
				'error_code' => $code,
			)
		);
	}
}
