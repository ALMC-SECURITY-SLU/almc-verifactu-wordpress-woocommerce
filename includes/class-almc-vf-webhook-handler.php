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

        // Read raw body.
        $raw_body = file_get_contents( 'php://input' );

        if ( empty( $raw_body ) ) {
            status_header( 400 );
            wp_send_json_error( array( 'message' => 'Empty body' ), 400 );
            exit;
        }

        // Verify HMAC signature.
        if ( ! self::verify_signature( $raw_body ) ) {
            status_header( 401 );
            wp_send_json_error( array( 'message' => 'Invalid signature' ), 401 );
            exit;
        }

        // Parse JSON payload.
        $payload = json_decode( $raw_body, true );

        if ( ! is_array( $payload ) ) {
            status_header( 400 );
            wp_send_json_error( array( 'message' => 'Invalid JSON' ), 400 );
            exit;
        }

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
     * Verify the webhook HMAC signature.
     *
     * @param string $raw_body Raw request body.
     * @return bool True if the signature is valid.
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
            return false;
        }

        // Support both "sha256=..." format and raw hash.
        $expected = hash_hmac( 'sha256', $raw_body, $secret );

        if ( 0 === strpos( $signature, 'sha256=' ) ) {
            $signature = substr( $signature, 7 );
        }

        return hash_equals( $expected, $signature );
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

        // Store additional data from the payload.
        if ( isset( $payload['aeat_response'] ) ) {
            $order->update_meta_data( '_almc_vf_aeat_response', wp_json_encode( $payload['aeat_response'] ) );
        }
        if ( isset( $payload['data']['aeat_response'] ) ) {
            $order->update_meta_data( '_almc_vf_aeat_response', wp_json_encode( $payload['data']['aeat_response'] ) );
        }
        if ( isset( $payload['error'] ) ) {
            $order->update_meta_data( '_almc_vf_last_error', sanitize_text_field( $payload['error'] ) );
        }
        if ( isset( $payload['data']['last_error'] ) ) {
            $order->update_meta_data( '_almc_vf_last_error', sanitize_text_field( $payload['data']['last_error'] ) );
        }
        if ( isset( $payload['data']['huella'] ) ) {
            $order->update_meta_data( '_almc_vf_huella', sanitize_text_field( $payload['data']['huella'] ) );
        }

        $order->save();

        // Add order note.
        $note = sprintf(
            /* translators: 1: event type, 2: old status, 3: new status */
            __( 'VeriFactu Webhook: %1$s. Estado: "%2$s" -> "%3$s".', 'almc-verifactu' ),
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
