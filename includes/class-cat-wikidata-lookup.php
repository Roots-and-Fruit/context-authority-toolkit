<?php
/**
 * Editor-only Wikidata sameAs lookup via REST.
 *
 * @package ContextAuthorityToolkit
 */

namespace ContextAuthorityToolkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides a capability-checked Wikidata search for the term editor.
 *
 * Lookup is read-only: it never writes post meta. Editors append chosen
 * canonical entity URLs to cat_same_as through the existing sidebar control.
 */
class Cat_Wikidata_Lookup {
	/**
	 * REST API namespace.
	 */
	const REST_NAMESPACE = 'context-authority-toolkit/v1';

	/**
	 * REST route path (no leading slash).
	 */
	const REST_ROUTE = 'wikidata-search';

	/**
	 * Allowed HTTP hosts for outbound Wikidata requests.
	 *
	 * @var string[]
	 */
	const ALLOWED_HOSTS = array(
		'www.wikidata.org',
		'wikidata.org',
	);

	/**
	 * Outbound request timeout in seconds.
	 */
	const REQUEST_TIMEOUT = 5;

	/**
	 * Maximum search results returned to the editor.
	 */
	const RESULT_CAP = 8;

	/**
	 * Maximum remote response body size in bytes.
	 */
	const MAX_BODY_BYTES = 65536;

	/**
	 * Wire REST hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the editor-only Wikidata search route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_search' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'post_id' => array(
						'description'       => __( 'Glossary term post ID being edited.', 'context-authority-toolkit' ),
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'search'  => array(
						'description'       => __( 'Wikidata entity search string.', 'context-authority-toolkit' ),
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Require edit_post on the term being edited.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return bool|\WP_Error
	 */
	public function permission_callback( $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( $post_id <= 0 ) {
			return new \WP_Error(
				'cat_wikidata_invalid_post',
				__( 'A valid term post ID is required.', 'context-authority-toolkit' ),
				array( 'status' => 400 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error(
				'cat_wikidata_forbidden',
				__( 'You are not allowed to look up Wikidata for this term.', 'context-authority-toolkit' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Handle Wikidata search requests.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_search( $request ) {
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$search = trim( $search );

		if ( '' === $search ) {
			return new \WP_Error(
				'cat_wikidata_empty_search',
				__( 'Search text is required.', 'context-authority-toolkit' ),
				array( 'status' => 400 )
			);
		}

		$results = $this->fetch_search_results( $search );
		if ( is_wp_error( $results ) ) {
			return $results;
		}

		return rest_ensure_response(
			array(
				'results' => $results,
			)
		);
	}

	/**
	 * Build the allowlisted Wikidata wbsearchentities URL.
	 *
	 * The client never supplies a URL; this helper is the sole request builder.
	 *
	 * @param string $search   Sanitized search text.
	 * @param string $language Language/uselang code.
	 * @return string|\WP_Error Absolute URL or error when host is not allowlisted.
	 */
	public function build_api_url( $search, $language = 'en' ) {
		$language = sanitize_key( (string) $language );
		if ( '' === $language ) {
			$language = 'en';
		}

		$query = array(
			'action'   => 'wbsearchentities',
			'format'   => 'json',
			'language' => $language,
			'uselang'  => $language,
			'type'     => 'item',
			'limit'    => self::RESULT_CAP,
			'search'   => $search,
		);

		$url = add_query_arg( $query, 'https://www.wikidata.org/w/api.php' );
		if ( ! $this->is_allowed_request_url( $url ) ) {
			return new \WP_Error(
				'cat_wikidata_host_blocked',
				__( 'Wikidata request host is not allowed.', 'context-authority-toolkit' ),
				array( 'status' => 500 )
			);
		}

		return $url;
	}

	/**
	 * Build a canonical Wikidata entity wiki URL from a Q-id.
	 *
	 * @param string $qid Entity id such as Q42.
	 * @return string Canonical URL or empty string when invalid.
	 */
	public function canonical_entity_url( $qid ) {
		$qid = strtoupper( trim( (string) $qid ) );
		if ( ! preg_match( '/^Q[0-9]+$/', $qid ) ) {
			return '';
		}

		return 'https://www.wikidata.org/wiki/' . $qid;
	}

	/**
	 * Whether a host is on the Wikidata allowlist.
	 *
	 * @param string $host Hostname.
	 * @return bool
	 */
	public function is_allowed_host( $host ) {
		$host = strtolower( (string) $host );
		return in_array( $host, self::ALLOWED_HOSTS, true );
	}

	/**
	 * Fetch and normalize Wikidata search results.
	 *
	 * @param string $search Sanitized search text.
	 * @return array[]|\WP_Error List of { id, label, description, url }.
	 */
	public function fetch_search_results( $search ) {
		$language = $this->resolve_language();
		$url      = $this->build_api_url( $search, $language );
		if ( is_wp_error( $url ) ) {
			return $url;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => self::REQUEST_TIMEOUT,
				'redirection' => 0,
				'headers'     => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'cat_wikidata_http_error',
				__( 'Wikidata lookup failed.', 'context-authority-toolkit' ),
				array( 'status' => 502 )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error(
				'cat_wikidata_bad_status',
				__( 'Wikidata lookup failed.', 'context-authority-toolkit' ),
				array( 'status' => 502 )
			);
		}

		$body = (string) wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > self::MAX_BODY_BYTES ) {
			return new \WP_Error(
				'cat_wikidata_body_too_large',
				__( 'Wikidata lookup failed.', 'context-authority-toolkit' ),
				array( 'status' => 502 )
			);
		}

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return new \WP_Error(
				'cat_wikidata_invalid_json',
				__( 'Wikidata lookup failed.', 'context-authority-toolkit' ),
				array( 'status' => 502 )
			);
		}

		return $this->normalize_search_results( $decoded );
	}

	/**
	 * Resolve language code from the site locale.
	 *
	 * @return string
	 */
	private function resolve_language() {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$locale = strtolower( str_replace( '_', '-', (string) $locale ) );
		$parts  = explode( '-', $locale );

		return sanitize_key( $parts[0] ? $parts[0] : 'en' );
	}

	/**
	 * Confirm a fully built request URL uses an allowlisted host.
	 *
	 * @param string $url Absolute URL.
	 * @return bool
	 */
	private function is_allowed_request_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
			return false;
		}

		if ( ! in_array( strtolower( (string) $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return false;
		}

		return $this->is_allowed_host( $parts['host'] );
	}

	/**
	 * Map Wikidata JSON into editor-safe result objects.
	 *
	 * @param array $decoded Decoded API payload.
	 * @return array[]
	 */
	private function normalize_search_results( array $decoded ) {
		$raw = array();
		if ( ! empty( $decoded['search'] ) && is_array( $decoded['search'] ) ) {
			$raw = $decoded['search'];
		}

		$results = array();
		foreach ( $raw as $item ) {
			if ( count( $results ) >= self::RESULT_CAP ) {
				break;
			}

			if ( ! is_array( $item ) || empty( $item['id'] ) ) {
				continue;
			}

			$id  = (string) $item['id'];
			$url = $this->canonical_entity_url( $id );
			if ( '' === $url ) {
				continue;
			}

			$label       = isset( $item['label'] ) ? sanitize_text_field( (string) $item['label'] ) : $id;
			$description = isset( $item['description'] ) ? sanitize_text_field( (string) $item['description'] ) : '';

			$results[] = array(
				'id'          => $id,
				'label'       => $label,
				'description' => $description,
				'url'         => $url,
			);
		}

		return $results;
	}
}
