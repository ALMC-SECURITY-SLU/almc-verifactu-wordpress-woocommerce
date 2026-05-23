<?php
/**
 * ALMC VeriFactu Webhook Handler
 *
 * Receives and processes webhook notifications from VeriFactu SaaS.
 *
 * @package ALMC_VeriFactu
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ALMC_VF_Webhook_Handler {

    /**
     * Maximum accepted request body size (bytes). Anything larger is rejected
     * with 413 BEFORE the HMAC check, to prevent CPU/memory waste on payloads
     * that cannot possibly be legitimate.
     */
    const MAX_BODY_BYTES = 102400; // 100 KiB

    /**
     * Maximum clock skew (seconds) allowed between SaaS-issued timestamp and
     * local time when replay-protection is active. Outside this window the
     * request is rejected even with a valid signature.
     */
    const TIMESTAMP_TOLERANCE = 300; // 5 minutes

    /**
     * Rate-limit window for HMAC failures (seconds) and max attempts per IP.
     */
    const RATE_LIMIT_WINDOW   = 60;
    const RATE_LIMIT_MAX_FAILS = 60;

    /**
     * Initialize webhook handler.
     */
    public static function init() {
        add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
        add_action( 'template_redirect', array( __CLASS__, 'handle_webhook' ) );

        // Ensure rewrite rule exists.
        add_action( 'init', array( __CLASS__, 'register_rewrite_rule' ) );
    }

    /**
     * Register the rewrite rule.
     */
    public static function register_rewrite_rule() {
        add_rewrite_rule(
            '^almc-verifactu/webhook/?$',
            'index.php?almc_vf_webhook=1',
            'top'
        );
    }

    /**
     * Register custom query variable.
     *
     * @param array $vars Existing query vars.
     * @return array Modified query vars.
     */
    public static function add_query_vars( $vars ) {
        $vars[] = 'almc_vf_webhook';
        return $vars;
    }

    /**
     * Handle incoming webhook request.
     */
    public static function handle_webhook() {
        if ( ! get_query_var( 'almc_vf_webhook' ) ) {
            return;
        }

        // Only accept POST requests.
        $request_method = isset( $_SERVER['REQUEST_METHOD'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
            : '';
        if ( 'POST' !== $request_method ) {
            status_header( 405 );
            wp_send_json_error( array( 'message' => 'Method not allowed' ), 405 );
            exit;
        }

        // Rate limit: too many recent HMAC failures from this IP → 429.
        $client_ip = self::client_ip();
        if ( self::is_rate_limited( $client_ip ) ) {
            status_header( 429 );
            wp_send_json_error( array( 'message' => 'Too many requests' ), 429 );
            exit;
        }

        // Body-size guardrail BEFORE reading php://input fully.
        $content_length = isset( $_SERVER['CONTENT_LENGTH'] )
            ? (int) $_SERVER['CONTENT_LENGTH']
            : 0;
        if ( $content_length > self::MAX_BODY_BYTES ) {
            status_header( 413 );
            wp_send_json_error( array( 'message' => 'Payload too large' ), 413 );
            exit;
        }

        // Read raw body (length-bounded — php://input is single-pass, fine).
        $raw_body = file_get_contents( 'php://input', false, null, 0, self::MAX_BODY_BYTES + 1 );

        if ( empty( $raw_body ) ) {
            status_header( 400 );
            wp_send_json_error( array( 'message' => 'Empty body' ), 400 );
            exit;
        }
        if ( strlen( $raw_body ) > self::MAX_BODY_BYTES ) {
            status_header( 413 );
            wp_send_json_error( array( 'message' => 'Payload too large' ), 413 );
            exit;
        }

        // Verify HMAC signature (and timestamp if SaaS sent X-Webhook-Timestamp).
        $sig_check = self::verify_signature( $raw_body );
        if ( true !== $sig_check ) {
            self::record_failure( $client_ip );
            status_header( 401 );
            wp_send_json_error( array( 'message' => is_string( $sig_check ) ? $sig_check : 'Invalid signature' ), 401 );
            exit;
        }

        // Parse JSON payload.
        $payload = json_decode( $raw_body, true );

        if ( ! is_array( $payload ) ) {
            status_header( 400 );
            wp_send_json_error( array( 'message' => 'Invalid JSON' ), 400 );
            exit;
        }

        // Whitelist + sanitize before forwarding anywhere (including do_action callbacks).
        // json_decode() parses but does NOT sanitize, so this is required by WP guidelines
        // when the decoded array is passed to third-party code via do_action.
        $payload = self::sanitize_payload( $payload );

        // Process the webhook event.
        $result = self::process_event( $payload );

        if ( is_wp_error( $result ) ) {
            status_header( 422 );
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
            exit;
        }

        wp_send_json_success( array( 'message' => 'Webhook processed' ) );
        exit;
    }

    /**
     * Sanitize the decoded webhook payload.
     *
     * Applies a strict whitelist of expected fields and types BEFORE the array is
     * forwarded to any third-party code (e.g. via the `almc_vf_webhook_processed`
     * action). Unknown keys are dropped on purpose; this is the right place to
     * neutralise malicious data that json_decode() happily preserved.
     *
     * @param array $payload Decoded JSON payload.
     * @return array Sanitised payload with the same shape.
     */
    private static function sanitize_payload( $payload ) {
        $top_event = isset( $payload['event_type'] ) && is_scalar( $payload['event_type'] )
            ? sanitize_text_field( (string) $payload['event_type'] )
            : '';
        $top_uuid = isset( $payload['invoice_uuid'] ) && is_scalar( $payload['invoice_uuid'] )
            ? sanitize_text_field( (string) $payload['invoice_uuid'] )
            : '';
        $top_status = isset( $payload['status'] ) && is_scalar( $payload['status'] )
            ? sanitize_text_field( (string) $payload['status'] )
            : '';
        $top_error = isset( $payload['error'] ) && is_scalar( $payload['error'] )
            ? sanitize_text_field( (string) $payload['error'] )
            : '';

        $top_aeat = array();
        if ( isset( $payload['aeat_response'] ) && is_array( $payload['aeat_response'] ) ) {
            $top_aeat = self::sanitize_aeat_response( $payload['aeat_response'] );
        }

        $data_in = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : array();
        $data_out = array(
            'invoice_uuid'   => isset( $data_in['invoice_uuid'] ) && is_scalar( $data_in['invoice_uuid'] )
                ? sanitize_text_field( (string) $data_in['invoice_uuid'] )
                : '',
            'uuid'           => isset( $data_in['uuid'] ) && is_scalar( $data_in['uuid'] )
                ? sanitize_text_field( (string) $data_in['uuid'] )
                : '',
            'status'         => isset( $data_in['status'] ) && is_scalar( $data_in['status'] )
                ? sanitize_text_field( (string) $data_in['status'] )
                : '',
            'last_error'     => isset( $data_in['last_error'] ) && is_scalar( $data_in['last_error'] )
                ? sanitize_text_field( (string) $data_in['last_error'] )
                : '',
            'huella'         => isset( $data_in['huella'] ) && is_scalar( $data_in['huella'] )
                ? sanitize_text_field( (string) $data_in['huella'] )
                : '',
            'invoice_number' => isset( $data_in['invoice_number'] ) && is_scalar( $data_in['invoice_number'] )
                ? sanitize_text_field( (string) $data_in['invoice_number'] )
                : '',
            'aeat_response'  => isset( $data_in['aeat_response'] ) && is_array( $data_in['aeat_response'] )
                ? self::sanitize_aeat_response( $data_in['aeat_response'] )
                : array(),
        );

        return array(
            'event_type'    => $top_event,
            'invoice_uuid'  => $top_uuid,
            'status'        => $top_status,
            'error'         => $top_error,
            'aeat_response' => $top_aeat,
            'data'          => $data_out,
        );
    }

    /**
     * Sanitize the nested `aeat_response` structure (scalars only, one level deep).
     *
     * @param array $aeat AEAT response sub-array.
     * @return array
     */
    private static function sanitize_aeat_response( $aeat ) {
        $clean = array();
        foreach ( $aeat as $k => $v ) {
            $key = is_string( $k ) ? sanitize_key( $k ) : (string) (int) $k;
            if ( '' === $key ) {
                continue;
            }
            if ( is_scalar( $v ) ) {
                $clean[ $key ] = sanitize_text_field( (string) $v );
            } elseif ( is_array( $v ) ) {
                // One level of nesting (covers Verifactu's RespuestaLinea/etc.).
                $sub = array();
                foreach ( $v as $kk => $vv ) {
                    if ( ! is_scalar( $vv ) ) {
                        continue;
                    }
                    $sub_key = is_string( $kk ) ? sanitize_key( $kk ) : (string) (int) $kk;
                    if ( '' === $sub_key ) {
                        continue;
                    }
                    $sub[ $sub_key ] = sanitize_text_field( (string) $vv );
                }
                $clean[ $key ] = $sub;
            }
        }
        return $clean;
    }

    /**
     * Verify the webhook HMAC signature (and replay-protection timestamp).
     *
     * If the SaaS sends `X-Webhook-Timestamp: <unix>`, the HMAC is computed
     * over `timestamp . "." . raw_body` and the timestamp must fall within
     * +/- TIMESTAMP_TOLERANCE of local time. This binds the signature to
     * a moment in time, so a captured webhook cannot be replayed indefinitely.
     *
     * If the timestamp header is absent, falls back to the v1.0 behaviour
     * (HMAC over raw_body only) for backwards compatibility with SaaS
     * versions that do not yet emit the header.
     *
     * @param string $raw_body Raw request body.
     * @return true|string True on success, or an error string for the response.
     */
    private static function verify_signature( $raw_body ) {
        $secret = get_option( 'almc_vf_webhook_secret', '' );

        // If no secret configured, skip verification (not recommended in production).
        if ( empty( $secret ) ) {
            return true;
        }

        // Check for signature header.
        $signature = isset( $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ) )
            : '';

        if ( empty( $signature ) ) {
            return 'Missing signature';
        }

        // Strip optional "sha256=" prefix.
        if ( 0 === strpos( $signature, 'sha256=' ) ) {
            $signature = substr( $signature, 7 );
        }

        // Replay-protection: when present, the timestamp is part of the signed payload.
        $timestamp_header = isset( $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ) )
            : '';

        if ( '' !== $timestamp_header ) {
            // Must be a positive integer.
            if ( ! ctype_digit( $timestamp_header ) ) {
                return 'Invalid timestamp';
            }
            $ts = (int) $timestamp_header;
            if ( abs( time() - $ts ) > self::TIMESTAMP_TOLERANCE ) {
                return 'Stale timestamp';
            }
            $signed_body = $timestamp_header . '.' . $raw_body;
        } else {
            // Backwards-compatible path: SaaS has not been upgraded yet.
            $signed_body = $raw_body;
        }

        $expected = hash_hmac( 'sha256', $signed_body, $secret );

        return hash_equals( $expected, $signature ) ? true : 'Invalid signature';
    }

    /**
     * Best-effort client IP detection (header + REMOTE_ADDR fallback).
     * Honors HTTP_X_FORWARDED_FOR / HTTP_X_REAL_IP only if WP is behind a trusted
     * proxy (controlled by the standard `pre_get_client_ip` filter chain).
     *
     * @return string
     */
    private static function client_ip() {
        $candidates = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
        foreach ( $candidates as $key ) {
            if ( empty( $_SERVER[ $key ] ) ) {
                continue;
            }
            // X-Forwarded-For can be a comma-separated list; the first hop is the client.
            $raw = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
            $ip  = trim( explode( ',', $raw )[0] );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }
        return '0.0.0.0';
    }

    /**
     * Check whether the given IP has exceeded the failure rate-limit window.
     *
     * @param string $ip Client IP.
     * @return bool
     */
    private static function is_rate_limited( $ip ) {
        $key   = 'almc_vf_wh_fail_' . md5( $ip );
        $fails = (int) get_transient( $key );
        return $fails >= self::RATE_LIMIT_MAX_FAILS;
    }

    /**
     * Record a failed HMAC attempt against the rate-limit counter.
     *
     * @param string $ip Client IP.
     * @return void
     */
    private static function record_failure( $ip ) {
        $key   = 'almc_vf_wh_fail_' . md5( $ip );
        $fails = (int) get_transient( $key );
        set_transient( $key, $fails + 1, self::RATE_LIMIT_WINDOW );
    }

    /**
     * Process a webhook event.
     *
     * @param array $payload Decoded JSON payload.
     * @return true|WP_Error
     */
    private static function process_event( $payload ) {
        $event_type   = isset( $payload['event_type'] ) ? sanitize_text_field( $payload['event_type'] ) : '';
        $invoice_uuid = isset( $payload['invoice_uuid'] ) ? sanitize_text_field( $payload['invoice_uuid'] ) : '';
        $status       = isset( $payload['status'] ) ? sanitize_text_field( $payload['status'] ) : '';

        if ( empty( $invoice_uuid ) ) {
            // Maybe it's nested in 'data'.
            if ( isset( $payload['data']['invoice_uuid'] ) ) {
                $invoice_uuid = sanitize_text_field( $payload['data']['invoice_uuid'] );
            }
            if ( isset( $payload['data']['uuid'] ) ) {
                $invoice_uuid = sanitize_text_field( $payload['data']['uuid'] );
            }
            if ( isset( $payload['data']['status'] ) ) {
                $status = sanitize_text_field( $payload['data']['status'] );
            }
        }

        if ( empty( $invoice_uuid ) ) {
            return new WP_Error( 'almc_vf_no_uuid', 'No invoice UUID in payload.' );
        }

        // Find the WooCommerce order by invoice UUID.
        $order = self::find_order_by_uuid( $invoice_uuid );

        if ( ! $order ) {
            // Not necessarily an error -- the invoice might not be from this WooCommerce.
            return true;
        }

        $old_status = $order->get_meta( '_almc_vf_status' );

        // Update status.
        if ( ! empty( $status ) ) {
            $order->update_meta_data( '_almc_vf_status', $status );
        }

        // Store additional data from the payload (payload is already sanitised, see sanitize_payload()).
        if ( ! empty( $payload['aeat_response'] ) ) {
            $order->update_meta_data( '_almc_vf_aeat_response', wp_json_encode( $payload['aeat_response'] ) );
        } elseif ( ! empty( $payload['data']['aeat_response'] ) ) {
            $order->update_meta_data( '_almc_vf_aeat_response', wp_json_encode( $payload['data']['aeat_response'] ) );
        }
        if ( ! empty( $payload['error'] ) ) {
            $order->update_meta_data( '_almc_vf_last_error', $payload['error'] );
        } elseif ( ! empty( $payload['data']['last_error'] ) ) {
            $order->update_meta_data( '_almc_vf_last_error', $payload['data']['last_error'] );
        }
        if ( ! empty( $payload['data']['huella'] ) ) {
            $order->update_meta_data( '_almc_vf_huella', $payload['data']['huella'] );
        }

        $order->save();

        // Add order note.
        $note = sprintf(
            /* translators: 1: event type, 2: old status, 3: new status */
            __( 'VeriFactu Webhook: %1$s. Estado: "%2$s" -> "%3$s".', 'almc-electronic-invoicing-verifactu' ),
            ! empty( $event_type ) ? $event_type : 'status_update',
            $old_status,
            $status
        );
        $order->add_order_note( $note );

        /**
         * Action hook for external integrations.
         *
         * @param WC_Order $order   The WooCommerce order.
         * @param string   $status  The new VeriFactu status.
         * @param array    $payload The full webhook payload.
         */
        do_action( 'almc_vf_webhook_processed', $order, $status, $payload );

        return true;
    }

    /**
     * Find a WooCommerce order by VeriFactu invoice UUID.
     *
     * @param string $uuid Invoice UUID.
     * @return WC_Order|false Order instance or false if not found.
     */
    private static function find_order_by_uuid( $uuid ) {
        // Use WooCommerce HPOS-compatible query if available.
        if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
            && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
        ) {
            $orders = wc_get_orders( array(
                'meta_key'   => '_almc_vf_invoice_uuid',
                'meta_value' => $uuid,
                'limit'      => 1,
            ) );

            return ! empty( $orders ) ? $orders[0] : false;
        }

        // Legacy meta query.
        global $wpdb;
        $order_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_almc_vf_invoice_uuid' AND meta_value = %s LIMIT 1",
                $uuid
            )
        );

        if ( $order_id ) {
            return wc_get_order( (int) $order_id );
        }

        return false;
    }
}
