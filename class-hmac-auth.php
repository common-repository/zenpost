<?php
class Zenpost_HMAC_Auth {

	/**
	 * The request timeout.
	 *
	 * Timeout will be 10 minutes
	 *
	 * @const int
	 */
	const REQUEST_TIMEOUT = 600000;

	/**
	 * An error message for if the secret has not been set.
	 *
	 * @const string
	 */
	const ERROR_NO_SECRET = 'You must enter an Authentication Key in the plugin options';

	/**
	 * An error message indicating required authentication information
	 * has not been provided.
	 *
	 * @const string
	 */
	const ERROR_MISSING_FIELDS = 'Missing required authentication fields';

	/**
	 * An error message indicating the request has expired.
	 *
	 * @const string
	 */
	const ERROR_EXPIRED = 'This request has expired';

	/**
	 * An error message indicating the request cannot be authenticated.
	 *
	 * @const string
	 */
	const ERROR_NOT_ALLOWED = 'Invalid authentication';

	/**
	 * The hashing algorithm to use.
	 *
	 * @const string
	 */
	const HASH_ALGO = 'sha256';

	/**
	 * The secret.
	 *
	 * @var string
	 */
	private $secret;

	/**
	 * Instantiate a new Zenpost_HMAC_Auth.
	 *
	 * @param string $secret the secret
	 */
	public function __construct( $secret ) {
		$this->secret = $secret;
	}

	/**
	 * Authenticate a request.
	 *
	 * @param array $payload the payload
	 *
	 * @return bool if the request is valid
	 */
	public function authenticate( $payload ) {
		if ( ! $this->secret ) {
			return array( false, self::ERROR_NO_SECRET );
		}

		if ( ! $this->has_required_fields( $payload ) ) {
			return array( false, self::ERROR_MISSING_FIELDS );
		}

		if ( ! $this->check_timestamp( $payload ) ) {
			return array( false, self::ERROR_EXPIRED );
		}

		$data = ( (string) $payload['timestamp'] ) . $payload['body'];
		$hmac = hash_hmac( self::HASH_ALGO, $data, $this->secret );
		if ( $payload['signature'] !== $hmac ) {
			return array( false, self::ERROR_NOT_ALLOWED );
		}

		return array( true, null );
	}

	/**
	 * Check for required authentication fields.
	 *
	 * Fields required are timestamp, signature, and body.
	 *
	 * @param array $payload the payload
	 *
	 * @return bool if all required fields are present
	 */
	private function has_required_fields( $payload ) {
		$required = array( 'timestamp', 'signature' );
		foreach ( $required as $field ) {
			if ( ! array_key_exists( $field, $payload ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determine if the request has expired.
	 *
	 * This checks if the timestamp is within an acceptable range
	 * specified by REQUEST_TIMEOUT.
	 *
	 * @param array $payload the payload
	 *
	 * @return bool if the request is still valid
	 */
	private function check_timestamp( $payload ) {
		$timestamp = $payload['timestamp'];
		$now = self::get_timestamp();
		$diff = abs( $timestamp - $now );
		return $diff <= self::REQUEST_TIMEOUT;
	}

	/**
	 * Generate a new secret.
	 *
	 * @return string a new secret
	 */
	public static function generate_secret() {
		return uniqid();
	}

	/**
	 * Get a timestamp.
	 *
	 * @return int the timestamp in milliseconds
	 */
	public static function get_timestamp() {
		return round( microtime( true ) * 1000 );
	}
}