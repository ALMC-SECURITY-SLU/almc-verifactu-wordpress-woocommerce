<?php
/**
 * Order metabox template.
 *
 * @package ALMC_VeriFactu
 * @var WC_Order $order    The WooCommerce order.
 * @var int      $order_id The order ID.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this is a partial included from render_metabox_content(); variables are include-scoped locals, not real globals.

$vf_uuid           = $order->get_meta( '_almc_vf_invoice_uuid' );
$vf_status         = $order->get_meta( '_almc_vf_status' );
$vf_invoice_number = $order->get_meta( '_almc_vf_invoice_number' );
$vf_last_error     = $order->get_meta( '_almc_vf_last_error' );
$vf_aeat_response  = $order->get_meta( '_almc_vf_aeat_response' );
$vf_huella         = $order->get_meta( '_almc_vf_huella' );
$is_configured     = ALMC_VF_Api_Client::instance()->is_configured();

// Status badge class mapping.
$badge_classes = array(
    'draft'     => 'almc-vf-badge-draft',
    'queued'    => 'almc-vf-badge-queued',
    'submitted' => 'almc-vf-badge-submitted',
    'accepted'  => 'almc-vf-badge-accepted',
    'rejected'  => 'almc-vf-badge-rejected',
    'error'     => 'almc-vf-badge-rejected',
    'cancelled' => 'almc-vf-badge-cancelled',
);

$badge_labels = array(
    'draft'     => __( 'Borrador', 'almc-electronic-invoicing-verifactu' ),
    'queued'    => __( 'En cola', 'almc-electronic-invoicing-verifactu' ),
    'submitted' => __( 'Enviada', 'almc-electronic-invoicing-verifactu' ),
    'accepted'  => __( 'Aceptada', 'almc-electronic-invoicing-verifactu' ),
    'rejected'  => __( 'Rechazada', 'almc-electronic-invoicing-verifactu' ),
    'error'     => __( 'Error', 'almc-electronic-invoicing-verifactu' ),
    'cancelled' => __( 'Anulada', 'almc-electronic-invoicing-verifactu' ),
);
?>

<div class="almc-vf-metabox" data-order-id="<?php echo esc_attr( $order_id ); ?>">

    <?php if ( ! $is_configured ) : ?>
        <p class="almc-vf-notice-warning">
            <?php
            printf(
                /* translators: 1: opening anchor tag, 2: closing anchor tag */
                wp_kses_post( __( 'Plugin no configurado. %1$sConfigurar ajustes%2$s', 'almc-electronic-invoicing-verifactu' ) ),
                '<a href="' . esc_url( admin_url( 'admin.php?page=almc-electronic-invoicing-verifactu' ) ) . '">',
                '</a>'
            );
            ?>
        </p>

    <?php elseif ( ! empty( $vf_uuid ) ) : ?>

        <!-- Status Badge -->
        <div class="almc-vf-status-row">
            <strong><?php esc_html_e( 'Estado:', 'almc-electronic-invoicing-verifactu' ); ?></strong>
            <span class="almc-vf-badge <?php echo esc_attr( isset( $badge_classes[ $vf_status ] ) ? $badge_classes[ $vf_status ] : 'almc-vf-badge-draft' ); ?>">
                <?php echo esc_html( isset( $badge_labels[ $vf_status ] ) ? $badge_labels[ $vf_status ] : $vf_status ); ?>
            </span>
        </div>

        <!-- Invoice Number -->
        <?php if ( ! empty( $vf_invoice_number ) ) : ?>
            <div class="almc-vf-info-row">
                <strong><?php esc_html_e( 'Factura:', 'almc-electronic-invoicing-verifactu' ); ?></strong>
                <span><?php echo esc_html( $vf_invoice_number ); ?></span>
            </div>
        <?php endif; ?>

        <!-- UUID -->
        <div class="almc-vf-info-row">
            <strong><?php esc_html_e( 'UUID:', 'almc-electronic-invoicing-verifactu' ); ?></strong>
            <code class="almc-vf-uuid" id="almc-vf-uuid-<?php echo esc_attr( $order_id ); ?>"><?php echo esc_html( $vf_uuid ); ?></code>
            <button type="button" class="button button-small almc-vf-copy-btn" data-target="almc-vf-uuid-<?php echo esc_attr( $order_id ); ?>" title="<?php esc_attr_e( 'Copiar UUID', 'almc-electronic-invoicing-verifactu' ); ?>">
                <?php esc_html_e( 'Copiar', 'almc-electronic-invoicing-verifactu' ); ?>
            </button>
        </div>

        <!-- Huella -->
        <?php if ( ! empty( $vf_huella ) ) : ?>
            <div class="almc-vf-info-row">
                <strong><?php esc_html_e( 'Huella:', 'almc-electronic-invoicing-verifactu' ); ?></strong>
                <code class="almc-vf-uuid"><?php echo esc_html( substr( $vf_huella, 0, 20 ) . '...' ); ?></code>
            </div>
        <?php endif; ?>

        <!-- Error -->
        <?php if ( ! empty( $vf_last_error ) ) : ?>
            <div class="almc-vf-error-row">
                <strong><?php esc_html_e( 'Error:', 'almc-electronic-invoicing-verifactu' ); ?></strong>
                <span class="almc-vf-error-text"><?php echo esc_html( $vf_last_error ); ?></span>
            </div>
        <?php endif; ?>

        <!-- AEAT Response (collapsible) -->
        <?php if ( ! empty( $vf_aeat_response ) ) : ?>
            <div class="almc-vf-aeat-response">
                <a href="#" class="almc-vf-toggle-response">
                    <?php esc_html_e( 'Ver respuesta AEAT', 'almc-electronic-invoicing-verifactu' ); ?> &#9660;
                </a>
                <pre class="almc-vf-response-data" style="display:none;"><?php echo esc_html( wp_json_encode( json_decode( $vf_aeat_response ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="almc-vf-actions">
            <?php if ( in_array( $vf_status, array( 'draft', 'error' ), true ) ) : ?>
                <button type="button" class="button button-primary almc-vf-submit-btn" data-order-id="<?php echo esc_attr( $order_id ); ?>">
                    <?php esc_html_e( 'Enviar a VeriFactu', 'almc-electronic-invoicing-verifactu' ); ?>
                </button>
            <?php endif; ?>

            <button type="button" class="button almc-vf-check-status-btn" data-order-id="<?php echo esc_attr( $order_id ); ?>">
                <?php esc_html_e( 'Consultar estado', 'almc-electronic-invoicing-verifactu' ); ?>
            </button>
        </div>

        <div class="almc-vf-ajax-message" style="display:none;"></div>

    <?php else : ?>

        <!-- No invoice yet -->
        <p><?php esc_html_e( 'Este pedido no tiene factura en VeriFactu.', 'almc-electronic-invoicing-verifactu' ); ?></p>

        <div class="almc-vf-actions">
            <button type="button" class="button button-primary almc-vf-submit-btn" data-order-id="<?php echo esc_attr( $order_id ); ?>">
                <?php esc_html_e( 'Enviar a VeriFactu', 'almc-electronic-invoicing-verifactu' ); ?>
            </button>
        </div>

        <div class="almc-vf-ajax-message" style="display:none;"></div>

    <?php endif; ?>

</div>
