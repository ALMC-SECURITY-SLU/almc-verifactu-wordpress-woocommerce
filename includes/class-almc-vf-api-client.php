<?php
/**
 * ALMC VeriFactu API Client
 *
 * HTTP client wrapper for the VeriFactu SaaS API.
 *
 * @package ALMC_VeriFactu
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ALMC_VF_Api_Client {

    /**
     * API base URL.
     *
     * @var string
     */
    private $api_url;

    /**
     * API key.
     *
     * @var string
     */
    private $api_key;

    /**
     * API secret (for future HMAC signing).
     *
     * @var string
     */
    private $api_secret;

    /**
     * Singleton instance.
     *
     * @var ALMC_VF_Api_Client|null
     */
    private static $instance = null;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->api_url    = rtrim( get_option( 'almc_vf_api_url', ALMC_VF_Settings::DEFAULT_API_URL ), '/' );
        $this->api_key    = get_option( 'almc_vf_api_key', '' );
        $this->api_secret = get_option( 'almc_vf_api_secret', '' );
    }

    /**
     * Get singleton instance.
     *
     * @return ALMC_VF_Api_Client
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Check if the API client is properly configured.
     *
     * @return bool
     */
    public function is_configured() {
        return ! empty( $this->api_url ) && ! empty( $this->api_key );
    }

    /**
     * Perform an HTTP request to the VeriFactu API.
     *
     * @param string $method   HTTP method (GET, POST, PUT, DELETE).
     * @param string $endpoint API endpoint (e.g., /invoices).
     * @param array  $data     Request body data or query parameters.
     * @return array|WP_Error  Decoded response array or WP_Error on failure.
     */
    public function request( $method, $endpoint, $data = array() ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error(
                'almc_vf_not_configured',
                __( 'El cliente API de VeriFactu no esta configurado. Introduce la URL y clave API.', 'almc-electronic-invoicing-verifactu' )
            );
        }

        $url = $this->api_url . '/' . ltrim( $endpoint, '/' );

        $args = array(
            'method'  => strtoupper( $method ),
            'headers' => array(
                'X-Api-Key'    => $this->api_key,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'User-Agent'   => 'ALMC-VeriFactu-WooCommerce/' . ALMC_VF_VERSION,
            ),
            'timeout' => 30,
        );

        if ( in_array( $args['method'], array( 'POST', 'PUT', 'PATCH' ), true ) && ! empty( $data ) ) {
            $args['body'] = wp_json_encode( $data );
        } elseif ( 'GET' === $args['method'] && ! empty( $data ) ) {
            $url = add_query_arg( $data, $url );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $body, true );

        if ( $code >= 400 ) {
            $error_message = isset( $decoded['message'] )
                ? $decoded['message']
                /* translators: %d: HTTP status code returned by the VeriFactu API */
                : sprintf( __( 'Error de la API (%d)', 'almc-electronic-invoicing-verifactu' ), $code );

            $error_detail = isset( $decoded['detail'] ) ? $decoded['detail'] : '';

            return new WP_Error(
                'almc_vf_api_error',
                $error_message,
                array(
                    'status'  => $code,
                    'detail'  => $error_detail,
                    'body'    => $decoded,
                )
            );
        }

        if ( null === $decoded ) {
            return new WP_Error(
                'almc_vf_invalid_response',
                __( 'Respuesta no valida del servidor.', 'almc-electronic-invoicing-verifactu' )
            );
        }

        return $decoded;
    }

    /**
     * Test the API connection.
     *
     * @return array|WP_Error
     */
    public function test_connection() {
        // Health endpoint is public, no auth needed.
        $url  = $this->api_url . '/health';
        $args = array(
            'method'  => 'GET',
            'headers' => array(
                'Accept'     => 'application/json',
                'User-Agent' => 'ALMC-VeriFactu-WooCommerce/' . ALMC_VF_VERSION,
            ),
            'timeout' => 15,
        );

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code ) {
            return new WP_Error(
                'almc_vf_health_failed',
                sprintf(
                    /* translators: %d: HTTP status code returned by the VeriFactu API health endpoint */
                    __( 'El servidor respondio con codigo %d', 'almc-electronic-invoicing-verifactu' ),
                    $code
                )
            );
        }

        // Also test authenticated endpoint if API key is set.
        if ( ! empty( $this->api_key ) ) {
            $auth_test = $this->request( 'GET', '/series' );
            if ( is_wp_error( $auth_test ) ) {
                return new WP_Error(
                    'almc_vf_auth_failed',
                    __( 'Servidor accesible pero la clave API no es valida.', 'almc-electronic-invoicing-verifactu' ),
                    array( 'health' => $body )
                );
            }
        }

        return array(
            'success' => true,
            'health'  => $body,
            'message' => __( 'Conexion exitosa.', 'almc-electronic-invoicing-verifactu' ),
        );
    }

    /**
     * Create an invoice.
     *
     * @param array $data Invoice payload.
     * @return array|WP_Error
     */
    public function create_invoice( $data ) {
        return $this->request( 'POST', '/invoices', $data );
    }

    /**
     * Submit an invoice to AEAT.
     *
     * @param string $uuid Invoice UUID.
     * @return array|WP_Error
     */
    public function submit_invoice( $uuid ) {
        return $this->request( 'POST', '/invoices/' . $uuid . '/submit' );
    }

    /**
     * Get an invoice by UUID.
     *
     * @param string $uuid Invoice UUID.
     * @return array|WP_Error
     */
    public function get_invoice( $uuid ) {
        return $this->request( 'GET', '/invoices/' . $uuid );
    }

    /**
     * List invoices with optional filters.
     *
     * @param array $params Query parameters (status, from, to, external_id, per_page).
     * @return array|WP_Error
     */
    public function list_invoices( $params = array() ) {
        return $this->request( 'GET', '/invoices', $params );
    }

    /**
     * Create a new series.
     *
     * @param array $data Series data (code, description).
     * @return array|WP_Error
     */
    public function create_series( $data ) {
        return $this->request( 'POST', '/series', $data );
    }

    /**
     * List all series.
     *
     * @return array|WP_Error
     */
    public function list_series() {
        return $this->request( 'GET', '/series' );
    }

    /**
     * Query the status of an invoice at AEAT.
     *
     * @param string $uuid Invoice UUID.
     * @return array|WP_Error
     */
    public function query_invoice( $uuid ) {
        return $this->request( 'POST', '/invoices/' . $uuid . '/query' );
    }

    /**
     * Cancel an invoice.
     *
     * @param string $uuid Invoice UUID.
     * @return array|WP_Error
     */
    public function cancel_invoice( $uuid ) {
        return $this->request( 'POST', '/invoices/' . $uuid . '/cancel' );
    }

    /**
     * Get async job status.
     *
     * @param string $job_uuid Job UUID.
     * @return array|WP_Error
     */
    public function get_job_status( $job_uuid ) {
        return $this->request( 'GET', '/jobs/' . $job_uuid );
    }

    /**
     * Get current usage.
     *
     * @return array|WP_Error
     */
    public function get_usage() {
        return $this->request( 'GET', '/usage' );
    }
}
