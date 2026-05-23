<?php
/**
 * ALMC VeriFactu Order Handler
 *
 * Hooks into WooCommerce order lifecycle to submit invoices.
 *
 * @package ALMC_VeriFactu
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ALMC_VF_Order_Handler {

    /**
     * Initialize order hooks.
     */
    public static function init() {
        $statuses = get_option( 'almc_vf_auto_submit_statuses', array( 'completed' ) );

        if ( ! is_array( $statuses ) ) {
            $statuses = array( 'completed' );
        }

        foreach ( $statuses as $status ) {
            add_action( 'woocommerce_order_status_' . $status, array( __CLASS__, 'maybe_submit_invoice' ), 20, 1 );
        }
    }

    /**
     * Maybe submit an invoice for an order (triggered by status change).
     *
     * @param int $order_id WooCommerce order ID.
     */
    public static function maybe_submit_invoice( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Check if already submitted.
        $existing_uuid = $order->get_meta( '_almc_vf_invoice_uuid' );
        if ( ! empty( $existing_uuid ) ) {
            return;
        }

        // Check if auto-submit is enabled.
        if ( 'yes' !== get_option( 'almc_vf_auto_submit', 'no' ) ) {
            return;
        }

        self::submit_order( $order );
    }

    /**
     * Submit an order to VeriFactu (used by both auto and manual submission).
     *
     * @param WC_Order $order WooCommerce order instance.
     * @return array|WP_Error Result from the API or WP_Error.
     */
    public static function submit_order( $order ) {
        $api = ALMC_VF_Api_Client::instance();

        if ( ! $api->is_configured() ) {
            $error = new WP_Error(
                'almc_vf_not_configured',
                __( 'VeriFactu: Plugin no configurado.', 'almc-electronic-invoicing-verifactu' )
            );
            $order->add_order_note(
                __( 'VeriFactu: No se pudo enviar la factura. El plugin no esta configurado.', 'almc-electronic-invoicing-verifactu' )
            );
            return $error;
        }

        // Validate order data.
        $validation = ALMC_VF_Invoice_Mapper::validate_order( $order );
        if ( is_wp_error( $validation ) ) {
            $order->add_order_note(
                sprintf(
                    /* translators: %s: error message */
                    __( 'VeriFactu: Validacion fallida - %s', 'almc-electronic-invoicing-verifactu' ),
                    $validation->get_error_message()
                )
            );
            return $validation;
        }

        // Map order to invoice payload.
        $payload = ALMC_VF_Invoice_Mapper::map_order( $order );

        // Create invoice via API.
        $result = $api->create_invoice( $payload );

        if ( is_wp_error( $result ) ) {
            $order->add_order_note(
                sprintf(
                    /* translators: %s: error message */
                    __( 'VeriFactu: Error al crear factura - %s', 'almc-electronic-invoicing-verifactu' ),
                    $result->get_error_message()
                )
            );
            $order->update_meta_data( '_almc_vf_status', 'error' );
            $order->update_meta_data( '_almc_vf_last_error', $result->get_error_message() );
            $order->save();
            return $result;
        }

        // Extract data from the response.
        $data = isset( $result['data'] ) ? $result['data'] : $result;

        $invoice_uuid = isset( $data['uuid'] ) ? $data['uuid'] : '';
        $status       = isset( $data['status'] ) ? $data['status'] : 'draft';
        $job_id       = isset( $data['job_id'] ) ? $data['job_id'] : '';

        // Store invoice data in order meta.
        $order->update_meta_data( '_almc_vf_invoice_uuid', $invoice_uuid );
        $order->update_meta_data( '_almc_vf_status', $status );
        $order->update_meta_data( '_almc_vf_invoice_number', isset( $data['invoice_number'] ) ? $data['invoice_number'] : '' );
        $order->update_meta_data( '_almc_vf_last_error', '' );

        if ( ! empty( $job_id ) ) {
            $order->update_meta_data( '_almc_vf_job_id', $job_id );
        }

        $order->save();

        // Add order note.
        $note = sprintf(
            /* translators: 1: invoice number, 2: UUID, 3: status */
            __( 'VeriFactu: Factura creada - %1$s (UUID: %2$s). Estado: %3$s', 'almc-electronic-invoicing-verifactu' ),
            isset( $data['invoice_number'] ) ? $data['invoice_number'] : 'N/A',
            $invoice_uuid,
            $status
        );

        if ( ! empty( $job_id ) ) {
            $note .= sprintf(
                /* translators: %s: job ID */
                __( '. Job de envio: %s', 'almc-electronic-invoicing-verifactu' ),
                $job_id
            );
        }

        $order->add_order_note( $note );

        return $result;
    }

    /**
     * Manual submit from admin. Returns the result for AJAX response.
     *
     * @param int $order_id WooCommerce order ID.
     * @return array|WP_Error
     */
    public static function manual_submit( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'almc_vf_order_not_found', __( 'Pedido no encontrado.', 'almc-electronic-invoicing-verifactu' ) );
        }

        // If already has UUID but status is draft, submit it.
        $existing_uuid = $order->get_meta( '_almc_vf_invoice_uuid' );
        if ( ! empty( $existing_uuid ) ) {
            $status = $order->get_meta( '_almc_vf_status' );
            if ( in_array( $status, array( 'draft', 'error' ), true ) ) {
                return self::submit_existing( $order, $existing_uuid );
            }
            return new WP_Error(
                'almc_vf_already_submitted',
                sprintf(
                    /* translators: %s: current status */
                    __( 'Esta factura ya fue enviada. Estado actual: %s', 'almc-electronic-invoicing-verifactu' ),
                    $status
                )
            );
        }

        return self::submit_order( $order );
    }

    /**
     * Submit an existing invoice that is in draft state.
     *
     * @param WC_Order $order WooCommerce order.
     * @param string   $uuid  Invoice UUID.
     * @return array|WP_Error
     */
    private static function submit_existing( $order, $uuid ) {
        $api    = ALMC_VF_Api_Client::instance();
        $result = $api->submit_invoice( $uuid );

        if ( is_wp_error( $result ) ) {
            $order->add_order_note(
                sprintf(
                    /* translators: %s: error message */
                    __( 'VeriFactu: Error al enviar factura existente - %s', 'almc-electronic-invoicing-verifactu' ),
                    $result->get_error_message()
                )
            );
            $order->update_meta_data( '_almc_vf_last_error', $result->get_error_message() );
            $order->save();
            return $result;
        }

        $data   = isset( $result['data'] ) ? $result['data'] : $result;
        $status = isset( $data['status'] ) ? $data['status'] : 'queued';
        $job_id = isset( $data['job_id'] ) ? $data['job_id'] : '';

        $order->update_meta_data( '_almc_vf_status', $status );
        $order->update_meta_data( '_almc_vf_last_error', '' );

        if ( ! empty( $job_id ) ) {
            $order->update_meta_data( '_almc_vf_job_id', $job_id );
        }

        $order->save();

        $order->add_order_note(
            sprintf(
                /* translators: %s: status */
                __( 'VeriFactu: Factura enviada a la AEAT. Estado: %s', 'almc-electronic-invoicing-verifactu' ),
                $status
            )
        );

        return $result;
    }

    /**
     * Refresh invoice status from the API.
     *
     * @param int $order_id WooCommerce order ID.
     * @return array|WP_Error
     */
    public static function refresh_status( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'almc_vf_order_not_found', __( 'Pedido no encontrado.', 'almc-electronic-invoicing-verifactu' ) );
        }

        $uuid = $order->get_meta( '_almc_vf_invoice_uuid' );
        if ( empty( $uuid ) ) {
            return new WP_Error( 'almc_vf_no_invoice', __( 'Este pedido no tiene factura en VeriFactu.', 'almc-electronic-invoicing-verifactu' ) );
        }

        $api    = ALMC_VF_Api_Client::instance();
        $result = $api->get_invoice( $uuid );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $data       = isset( $result['data'] ) ? $result['data'] : $result;
        $new_status = isset( $data['status'] ) ? $data['status'] : '';
        $old_status = $order->get_meta( '_almc_vf_status' );

        if ( ! empty( $new_status ) && $new_status !== $old_status ) {
            $order->update_meta_data( '_almc_vf_status', $new_status );
            $order->update_meta_data( '_almc_vf_last_error', isset( $data['last_error'] ) ? $data['last_error'] : '' );

            if ( isset( $data['aeat_response'] ) ) {
                $order->update_meta_data( '_almc_vf_aeat_response', wp_json_encode( $data['aeat_response'] ) );
            }

            $order->save();

            $order->add_order_note(
                sprintf(
                    /* translators: 1: old status, 2: new status */
                    __( 'VeriFactu: Estado actualizado de "%1$s" a "%2$s".', 'almc-electronic-invoicing-verifactu' ),
                    $old_status,
                    $new_status
                )
            );
        }

        return $data;
    }
}
