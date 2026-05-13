<?php
/**
 * Plugin Name: ALMC VeriFactu
 * Plugin URI: https://almc.es/verifactu/
 * Description: Envia automaticamente las facturas de tu tienda online a la AEAT (Verifactu) mediante el SaaS de ALMC. Compatible con WooCommerce.
 * Version: 1.0.0
 * Author: ALMC Security S.L.U.
 * Author URI: https://almc.es
 * License: GPL-2.0-or-later
 * Text Domain: almc-verifactu
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 10.7
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ALMC_VF_VERSION', '1.0.0' );
define( 'ALMC_VF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALMC_VF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ALMC_VF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoload classes.
require_once ALMC_VF_PLUGIN_DIR . 'includes/class-almc-vf-api-client.php';
require_once ALMC_VF_PLUGIN_DIR . 'includes/class-almc-vf-invoice-mapper.php';
require_once ALMC_VF_PLUGIN_DIR . 'includes/class-almc-vf-settings.php';
require_once ALMC_VF_PLUGIN_DIR . 'includes/class-almc-vf-order-handler.php';
require_once ALMC_VF_PLUGIN_DIR . 'includes/class-almc-vf-webhook-handler.php';
require_once ALMC_VF_PLUGIN_DIR . 'admin/class-almc-vf-admin.php';

/**
 * Initialize plugin after all plugins are loaded.
 */
add_action( 'plugins_loaded', 'almc_vf_init' );

function almc_vf_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'almc_vf_woocommerce_missing_notice' );
        return;
    }

    ALMC_VF_Settings::init();
    ALMC_VF_Order_Handler::init();
    ALMC_VF_Webhook_Handler::init();
    ALMC_VF_Admin::init();
}

/**
 * Admin notice when WooCommerce is not active.
 */
function almc_vf_woocommerce_missing_notice() {
    echo '<div class="error"><p><strong>ALMC VeriFactu</strong> requiere WooCommerce activo.</p></div>';
}

/**
 * Activation hook.
 */
register_activation_hook( __FILE__, 'almc_vf_activate' );

function almc_vf_activate() {
    add_rewrite_rule(
        '^almc-verifactu/webhook/?$',
        'index.php?almc_vf_webhook=1',
        'top'
    );
    flush_rewrite_rules();

    // Generate webhook secret if not set.
    if ( ! get_option( 'almc_vf_webhook_secret' ) ) {
        update_option( 'almc_vf_webhook_secret', wp_generate_password( 40, false ) );
    }
}

/**
 * Deactivation hook.
 */
register_deactivation_hook( __FILE__, 'almc_vf_deactivate' );

function almc_vf_deactivate() {
    flush_rewrite_rules();
}

/**
 * Add Settings link to the plugins page.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'almc_vf_plugin_action_links' );

function almc_vf_plugin_action_links( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=almc-verifactu' ) . '">' . __( 'Ajustes', 'almc-verifactu' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
