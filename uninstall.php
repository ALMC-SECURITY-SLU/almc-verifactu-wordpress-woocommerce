<?php
/**
 * Uninstall handler for ALMC Electronic Invoicing for VeriFactu.
 *
 * Runs when the user deletes the plugin from the WordPress admin
 * (Plugins → Deleted). Removes credentials, settings and transients,
 * but PRESERVES the per-order invoice metadata
 * (`_almc_vf_invoice_uuid`, `_almc_vf_aeat_response`, etc.) because
 * Spanish fiscal regulation (Art. 30 Código de Comercio / RD 1007/2023)
 * requires invoice traceability to be retained for 4 years.
 *
 * Anyone who needs a full hard-delete (e.g. dev environment teardown)
 * can run the WP-CLI commands documented at the bottom of this file.
 *
 * @package ALMC_Electronic_Invoicing_VeriFactu
 */

// Guard: must only run via WordPress uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

/* ── 1. Options ─────────────────────────────────────────────────────── */

$options = array(
    // Connection
    'almc_vf_api_url',
    'almc_vf_api_key',
    'almc_vf_api_secret',
    'almc_vf_environment',
    // Invoicing
    'almc_vf_series_code',
    'almc_vf_auto_submit',
    'almc_vf_auto_submit_statuses',
    'almc_vf_nif_field',
    // Webhook
    'almc_vf_webhook_secret',
    // UI state
    'almc_vf_setup_notice_dismissed',
);

foreach ( $options as $option_name ) {
    delete_option( $option_name );
    // Also delete from multisite if applicable.
    if ( is_multisite() ) {
        delete_site_option( $option_name );
    }
}

/* ── 2. Transients (rate limit + caches) ────────────────────────────── */

// Bulk-delete any transient whose key starts with almc_vf_. We use a
// direct query because the public API has no LIKE-based bulk delete.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one-off cleanup during uninstall, no caching needed.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like( '_transient_almc_vf_' ) . '%',
        $wpdb->esc_like( '_transient_timeout_almc_vf_' ) . '%'
    )
);

/* ── 3. Object cache flush for this plugin's group ──────────────────── */

if ( function_exists( 'wp_cache_flush_group' ) ) {
    wp_cache_flush_group( 'almc_vf' );
}

/* ── 4. NOT removed (preserved for fiscal audit, 4 years) ───────────── */

// We intentionally KEEP:
//   - _almc_vf_invoice_uuid          (order meta)
//   - _almc_vf_status                (order meta)
//   - _almc_vf_invoice_number        (order meta)
//   - _almc_vf_last_error            (order meta)
//   - _almc_vf_aeat_response         (order meta)
//   - _almc_vf_huella                (order meta — AEAT chain hash)
//   - _almc_vf_job_id                (order meta)
//
// Reason: RD 1007/2023 (VeriFactu) + Código de Comercio Art. 30 require
// the issuer to retain invoice records (including AEAT receipts and the
// SHA-256 chain hash) for at least 4 years. Deleting them on uninstall
// would put the store owner in breach of their fiscal obligations.
//
// If a developer needs to fully wipe a dev environment, use WP-CLI:
//
//   wp db query "DELETE FROM wp_postmeta WHERE meta_key LIKE '_almc_vf_%'"
//   wp db query "DELETE FROM wp_wc_orders_meta WHERE meta_key LIKE '_almc_vf_%'"
//
// or, for a single order:
//
//   wp post meta delete <order_id> _almc_vf_invoice_uuid
