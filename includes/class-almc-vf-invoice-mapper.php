<?php
/**
 * ALMC VeriFactu Invoice Mapper
 *
 * Maps WooCommerce orders to VeriFactu invoice payloads.
 *
 * @package ALMC_VeriFactu
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ALMC_VF_Invoice_Mapper {

    /**
     * Map a WooCommerce order to a VeriFactu invoice payload.
     *
     * @param WC_Order $order WooCommerce order instance.
     * @return array Invoice payload for the API.
     */
    public static function map_order( $order ) {
        $series_code = get_option( 'almc_vf_series_code', 'WC' );
        $auto_submit = 'yes' === get_option( 'almc_vf_auto_submit', 'no' );
        $nif_field   = get_option( 'almc_vf_nif_field', '_billing_nif' );

        // Build recipient name.
        $company = $order->get_billing_company();
        $recipient_name = ! empty( $company )
            ? $company
            : trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

        // Get NIF from order meta.
        $recipient_nif = $order->get_meta( $nif_field );
        if ( empty( $recipient_nif ) ) {
            // Fallback: try common field names.
            $fallback_fields = array( '_billing_nif', '_billing_vat', '_billing_cif', 'billing_nif', 'billing_vat' );
            foreach ( $fallback_fields as $field ) {
                $value = $order->get_meta( $field );
                if ( ! empty( $value ) ) {
                    $recipient_nif = $value;
                    break;
                }
            }
        }

        // Issue date.
        $date_created = $order->get_date_created();
        $issue_date = $date_created ? $date_created->format( 'Y-m-d' ) : gmdate( 'Y-m-d' );

        // Map order items.
        $items     = array();
        $tax_data  = array();

        foreach ( $order->get_items() as $item ) {
            $quantity   = (float) $item->get_quantity();
            $subtotal   = (float) $item->get_subtotal();   // Before tax, before discounts applied per-line.
            $tax_total  = (float) $item->get_total_tax();
            $line_total = (float) $item->get_total();       // After discounts, before tax.

            $unit_price = $quantity > 0 ? round( $line_total / $quantity, 6 ) : 0;

            // Calculate tax rate.
            $tax_rate = 0;
            if ( $line_total > 0 && $tax_total > 0 ) {
                $tax_rate = round( ( $tax_total / $line_total ) * 100, 2 );
            }

            $items[] = array(
                'description' => $item->get_name(),
                'quantity'    => $quantity,
                'unit_price'  => $unit_price,
                'tax_rate'    => $tax_rate,
            );

            // Aggregate tax data by rate.
            $rate_key = (string) $tax_rate;
            if ( ! isset( $tax_data[ $rate_key ] ) ) {
                $tax_data[ $rate_key ] = array(
                    'tax_base'   => 0,
                    'tax_amount' => 0,
                    'tax_rate'   => $tax_rate,
                );
            }
            $tax_data[ $rate_key ]['tax_base']   += $line_total;
            $tax_data[ $rate_key ]['tax_amount'] += $tax_total;
        }

        // Include shipping as an item if it has cost.
        $shipping_total = (float) $order->get_shipping_total();
        $shipping_tax   = (float) $order->get_shipping_tax();

        if ( $shipping_total > 0 ) {
            $shipping_tax_rate = 0;
            if ( $shipping_tax > 0 ) {
                $shipping_tax_rate = round( ( $shipping_tax / $shipping_total ) * 100, 2 );
            }

            $items[] = array(
                'description' => __( 'Gastos de envio', 'almc-verifactu' ),
                'quantity'    => 1,
                'unit_price'  => $shipping_total,
                'tax_rate'    => $shipping_tax_rate,
            );

            $rate_key = (string) $shipping_tax_rate;
            if ( ! isset( $tax_data[ $rate_key ] ) ) {
                $tax_data[ $rate_key ] = array(
                    'tax_base'   => 0,
                    'tax_amount' => 0,
                    'tax_rate'   => $shipping_tax_rate,
                );
            }
            $tax_data[ $rate_key ]['tax_base']   += $shipping_total;
            $tax_data[ $rate_key ]['tax_amount'] += $shipping_tax;
        }

        // Include fees as items.
        foreach ( $order->get_fees() as $fee ) {
            $fee_total = (float) $fee->get_total();
            $fee_tax   = (float) $fee->get_total_tax();

            if ( abs( $fee_total ) < 0.01 ) {
                continue;
            }

            $fee_tax_rate = 0;
            if ( $fee_total > 0 && $fee_tax > 0 ) {
                $fee_tax_rate = round( ( $fee_tax / $fee_total ) * 100, 2 );
            }

            $items[] = array(
                'description' => $fee->get_name(),
                'quantity'    => 1,
                'unit_price'  => $fee_total,
                'tax_rate'    => $fee_tax_rate,
            );

            $rate_key = (string) $fee_tax_rate;
            if ( ! isset( $tax_data[ $rate_key ] ) ) {
                $tax_data[ $rate_key ] = array(
                    'tax_base'   => 0,
                    'tax_amount' => 0,
                    'tax_rate'   => $fee_tax_rate,
                );
            }
            $tax_data[ $rate_key ]['tax_base']   += $fee_total;
            $tax_data[ $rate_key ]['tax_amount'] += $fee_tax;
        }

        // Build tax lines.
        $tax_lines = array();
        foreach ( $tax_data as $tl ) {
            $tax_lines[] = array(
                'tax_rate'   => round( $tl['tax_rate'], 2 ),
                'tax_base'   => round( $tl['tax_base'], 2 ),
                'tax_amount' => round( $tl['tax_amount'], 2 ),
            );
        }

        // Build the payload.
        $payload = array(
            'external_id'       => 'wc-' . $order->get_id(),
            'series_code'       => $series_code,
            'issue_date'        => $issue_date,
            'invoice_type'      => 'F1',
            'recipient_name'    => $recipient_name,
            'recipient_nif'     => $recipient_nif ? $recipient_nif : '',
            'recipient_country' => $order->get_billing_country() ? $order->get_billing_country() : 'ES',
            'description'       => sprintf(
                /* translators: %s: order number */
                __( 'Pedido #%s', 'almc-verifactu' ),
                $order->get_order_number()
            ),
            'items'             => $items,
            'auto_submit'       => $auto_submit,
            'metadata'          => array(
                'source'   => 'woocommerce',
                'order_id' => $order->get_id(),
                'site_url' => get_site_url(),
            ),
        );

        // Add tax lines if we have them.
        if ( ! empty( $tax_lines ) ) {
            $payload['tax_lines'] = $tax_lines;
        }

        return $payload;
    }

    /**
     * Validate that an order has the minimum required data for VeriFactu.
     *
     * @param WC_Order $order WooCommerce order.
     * @return true|WP_Error True if valid, WP_Error with details if not.
     */
    public static function validate_order( $order ) {
        $errors = array();
        $nif_field = get_option( 'almc_vf_nif_field', '_billing_nif' );

        // Check NIF.
        $nif = $order->get_meta( $nif_field );
        if ( empty( $nif ) ) {
            // Try fallbacks.
            $fallback_fields = array( '_billing_nif', '_billing_vat', '_billing_cif', 'billing_nif', 'billing_vat' );
            $found = false;
            foreach ( $fallback_fields as $field ) {
                if ( ! empty( $order->get_meta( $field ) ) ) {
                    $found = true;
                    break;
                }
            }
            if ( ! $found ) {
                $errors[] = __( 'El pedido no tiene NIF/CIF del cliente.', 'almc-verifactu' );
            }
        }

        // Check items.
        if ( count( $order->get_items() ) < 1 ) {
            $errors[] = __( 'El pedido no tiene articulos.', 'almc-verifactu' );
        }

        // Check billing name.
        $name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        $company = $order->get_billing_company();
        if ( empty( $name ) && empty( $company ) ) {
            $errors[] = __( 'El pedido no tiene nombre ni empresa de facturacion.', 'almc-verifactu' );
        }

        if ( ! empty( $errors ) ) {
            return new WP_Error(
                'almc_vf_validation_error',
                implode( ' ', $errors ),
                array( 'errors' => $errors )
            );
        }

        return true;
    }
}
