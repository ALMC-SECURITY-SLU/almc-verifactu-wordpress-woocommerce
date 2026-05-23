<?php
/**
 * ALMC VeriFactu Admin
 *
 * Admin pages, metaboxes, and AJAX handlers.
 *
 * @package ALMC_VeriFactu
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ALMC_VF_Admin {

    /**
     * Initialize admin functionality.
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

        // Order metabox (supports both legacy and HPOS).
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_order_metabox' ) );

        // HPOS support for WooCommerce.
        add_action( 'woocommerce_page_wc-orders', array( __CLASS__, 'add_order_metabox_hpos' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_almc_vf_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_almc_vf_manual_submit', array( __CLASS__, 'ajax_manual_submit' ) );
        add_action( 'wp_ajax_almc_vf_check_status', array( __CLASS__, 'ajax_check_status' ) );
        add_action( 'wp_ajax_almc_vf_dismiss_setup_notice', array( __CLASS__, 'ajax_dismiss_setup_notice' ) );

        // Declare HPOS compatibility.
        add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_hpos_compatibility' ) );

        // Welcome / setup notice for new installs.
        add_action( 'admin_notices', array( __CLASS__, 'maybe_show_setup_notice' ) );
    }

    /**
     * Allowed admin screen IDs where the setup notice may appear.
     * Used by both the renderer and the conditional asset enqueue.
     *
     * @return array<string>
     */
    private static function setup_notice_screens() {
        return array(
            'dashboard',
            'plugins',
            'woocommerce_page_wc-admin',
            'woocommerce_page_wc-orders',
            'woocommerce_page_almc-electronic-invoicing-verifactu',
        );
    }

    /**
     * Whether the setup notice should be shown on the current screen.
     */
    private static function should_show_setup_notice() {
        if ( get_option( 'almc_vf_setup_notice_dismissed' ) ) {
            return false;
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return false;
        }
        if ( ! empty( get_option( 'almc_vf_api_key' ) ) && ! empty( get_option( 'almc_vf_series_code' ) ) ) {
            return false;
        }
        return true;
    }

    /**
     * Show a setup checklist notice on admin pages when the plugin is not yet configured.
     *
     * The dismiss handler script lives in assets/js/almc-setup-notice.js and is enqueued
     * from self::enqueue_assets(); the only inline output here is the notice markup itself.
     */
    public static function maybe_show_setup_notice() {
        if ( ! self::should_show_setup_notice() ) {
            return;
        }
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || ! in_array( $screen->id, self::setup_notice_screens(), true ) ) {
            return;
        }

        $settings_url = admin_url( 'admin.php?page=almc-electronic-invoicing-verifactu' );
        $signup_url   = 'https://almc.es/verifactu/register';

        ?>
        <div class="notice notice-info is-dismissible almc-vf-setup-notice">
            <h3 class="almc-vf-setup-notice__title">
                <?php esc_html_e( '¡Bienvenido a ALMC Electronic Invoicing for VeriFactu!', 'almc-electronic-invoicing-verifactu' ); ?>
            </h3>
            <p>
                <?php esc_html_e( 'Para empezar a emitir facturas a la AEAT desde tu tienda, completa estos pasos:', 'almc-electronic-invoicing-verifactu' ); ?>
            </p>
            <ol class="almc-vf-setup-notice__list">
                <li>
                    <a href="<?php echo esc_url( $signup_url ); ?>" target="_blank" rel="noopener">
                        <?php esc_html_e( 'Crear cuenta en VeriFactu SaaS', 'almc-electronic-invoicing-verifactu' ); ?>
                    </a>
                    <?php esc_html_e( ' (plan gratuito hasta 10 facturas/mes)', 'almc-electronic-invoicing-verifactu' ); ?>
                </li>
                <li><?php esc_html_e( 'Subir tu certificado digital de representante (.p12 / .pfx) al panel VeriFactu', 'almc-electronic-invoicing-verifactu' ); ?></li>
                <li><?php esc_html_e( 'Crear una serie de facturación (p.ej. "A" o "WC-2026") en el panel VeriFactu', 'almc-electronic-invoicing-verifactu' ); ?></li>
                <li><?php esc_html_e( 'Generar una clave API en la sección "API Keys" del panel', 'almc-electronic-invoicing-verifactu' ); ?></li>
                <li>
                    <a href="<?php echo esc_url( $settings_url ); ?>">
                        <strong><?php esc_html_e( 'Configurar el plugin con tu API key y código de serie', 'almc-electronic-invoicing-verifactu' ); ?></strong>
                    </a>
                </li>
            </ol>
            <p>
                <a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Ir a los ajustes', 'almc-electronic-invoicing-verifactu' ); ?>
                </a>
                <a href="https://almc.es/verifactu/plugin/woocommerce" target="_blank" rel="noopener" class="button">
                    <?php esc_html_e( 'Documentación', 'almc-electronic-invoicing-verifactu' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * AJAX: dismiss the setup notice permanently.
     */
    public static function ajax_dismiss_setup_notice() {
        check_ajax_referer( 'almc_vf_dismiss_setup_notice', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error();
        }
        update_option( 'almc_vf_setup_notice_dismissed', 1 );
        wp_send_json_success();
    }

    /**
     * Declare High-Performance Order Storage compatibility.
     */
    public static function declare_hpos_compatibility() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                ALMC_VF_PLUGIN_DIR . 'almc-electronic-invoicing-verifactu.php',
                true
            );
        }
    }

    /**
     * Add admin menu page under WooCommerce.
     */
    public static function add_menu_page() {
        add_submenu_page(
            'woocommerce',
            __( 'VeriFactu', 'almc-electronic-invoicing-verifactu' ),
            __( 'VeriFactu', 'almc-electronic-invoicing-verifactu' ),
            'manage_woocommerce',
            'almc-electronic-invoicing-verifactu',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    /**
     * Enqueue admin CSS and JS.
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_assets( $hook ) {
        $is_settings = 'woocommerce_page_almc-electronic-invoicing-verifactu' === $hook;
        $is_order    = in_array( $hook, array( 'post.php', 'post-new.php', 'woocommerce_page_wc-orders' ), true );

        // Setup notice may appear on dashboard / plugins / WC screens; enqueue its
        // CSS + dismiss JS there so we never emit inline <style> or <script>.
        $screen          = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $screen_id       = $screen ? $screen->id : '';
        $is_notice_screen = in_array( $screen_id, self::setup_notice_screens(), true ) && self::should_show_setup_notice();

        if ( ! $is_settings && ! $is_order && ! $is_notice_screen ) {
            return;
        }

        // Settings + order screens get the legacy admin bundle.
        if ( $is_settings || $is_order ) {
            wp_enqueue_style(
                'almc-vf-admin',
                ALMC_VF_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                ALMC_VF_VERSION
            );

            wp_enqueue_script(
                'almc-vf-admin',
                ALMC_VF_PLUGIN_URL . 'assets/js/admin.js',
                array( 'jquery' ),
                ALMC_VF_VERSION,
                true
            );

            wp_localize_script( 'almc-vf-admin', 'almcVf', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'almc_vf_nonce' ),
                'i18n'    => array(
                    'testing'       => __( 'Probando conexion...', 'almc-electronic-invoicing-verifactu' ),
                    'submitting'    => __( 'Enviando a VeriFactu...', 'almc-electronic-invoicing-verifactu' ),
                    'checking'      => __( 'Consultando estado...', 'almc-electronic-invoicing-verifactu' ),
                    'success'       => __( 'Correcto', 'almc-electronic-invoicing-verifactu' ),
                    'error'         => __( 'Error', 'almc-electronic-invoicing-verifactu' ),
                    'copied'        => __( 'UUID copiado', 'almc-electronic-invoicing-verifactu' ),
                    'confirmSubmit' => __( 'Enviar esta factura a VeriFactu?', 'almc-electronic-invoicing-verifactu' ),
                ),
            ) );
        }

        // Settings page also renders the onboarding ("Cómo empezar") panel.
        if ( $is_settings ) {
            wp_enqueue_style(
                'almc-vf-onboarding',
                ALMC_VF_PLUGIN_URL . 'assets/css/almc-onboarding.css',
                array(),
                ALMC_VF_VERSION
            );
        }

        // Setup-notice screens need the dismiss handler + minimal styling.
        if ( $is_notice_screen ) {
            wp_enqueue_style(
                'almc-vf-onboarding',
                ALMC_VF_PLUGIN_URL . 'assets/css/almc-onboarding.css',
                array(),
                ALMC_VF_VERSION
            );
            wp_enqueue_script(
                'almc-vf-setup-notice',
                ALMC_VF_PLUGIN_URL . 'assets/js/almc-setup-notice.js',
                array(),
                ALMC_VF_VERSION,
                true
            );
            wp_localize_script( 'almc-vf-setup-notice', 'almcVfSetupNotice', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'almc_vf_dismiss_setup_notice' ),
            ) );
        }
    }

    /**
     * Render the settings page.
     */
    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        include ALMC_VF_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    /**
     * Compute setup steps for the "Cómo empezar" panel.
     * Each step has: title, description, cta_text, cta_url, cta_external, done.
     *
     * @return array{steps: array, completed_count: int, total_count: int}
     */
    public static function get_setup_steps() {
        $api_key      = get_option( 'almc_vf_api_key', '' );
        $series_code  = get_option( 'almc_vf_series_code', '' );
        $auto_submit  = get_option( 'almc_vf_auto_submit', 'no' );
        $settings_url = admin_url( 'admin.php?page=almc-electronic-invoicing-verifactu' );

        // Step 5 "done": ¿hay algún pedido WC con UUID Verifactu?
        // Cacheado para que repintar la página no machaque la BD.
        $first_invoice_sent = wp_cache_get( 'almc_vf_first_invoice_sent', 'almc_vf' );
        if ( false === $first_invoice_sent ) {
            $first_invoice_sent = 0;
            global $wpdb;
            if ( $wpdb ) {
                // HPOS table (WooCommerce 8+).
                $hpos_table = $wpdb->prefix . 'wc_orders_meta';
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix, no untrusted input, lookup cached for 5min.
                $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos_table ) );
                if ( $exists === $hpos_table ) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $hpos_table es identifier seguro (prefix de $wpdb + literal), no es input externo. Resultado se cachea.
                    $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$hpos_table} WHERE meta_key = '_almc_vf_invoice_uuid' AND meta_value <> ''" );
                    $first_invoice_sent = $count > 0 ? 1 : 0;
                }
                if ( ! $first_invoice_sent ) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- lookup unico cacheado 5min.
                    $count = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> %s",
                        '_almc_vf_invoice_uuid',
                        ''
                    ) );
                    $first_invoice_sent = $count > 0 ? 1 : 0;
                }
            }
            wp_cache_set( 'almc_vf_first_invoice_sent', $first_invoice_sent, 'almc_vf', 5 * MINUTE_IN_SECONDS );
        }
        $first_invoice_sent = (bool) $first_invoice_sent;

        $steps = array(
            array(
                'title'        => __( '1. Crea tu cuenta en VeriFactu SaaS', 'almc-electronic-invoicing-verifactu' ),
                'description'  => __( 'Regístrate gratis en almc.es/verifactu. Necesitarás los datos fiscales de tu empresa (razón social y NIF/CIF). Plan gratuito incluido hasta 10 facturas/mes.', 'almc-electronic-invoicing-verifactu' ),
                'cta_text'     => __( 'Crear cuenta', 'almc-electronic-invoicing-verifactu' ),
                'cta_url'      => 'https://almc.es/verifactu/register',
                'cta_external' => true,
                'done'         => false, // No tenemos forma de detectarlo desde el plugin
            ),
            array(
                'title'        => __( '2. Sube tu certificado digital al panel VeriFactu', 'almc-electronic-invoicing-verifactu' ),
                'description'  => __( 'En tu panel de VeriFactu entra en "Certificados" y sube tu archivo .p12 o .pfx con su contraseña. Se guardará cifrado (HKDF + AES-256). Si no tienes uno, lo obtienes gratis en la FNMT.', 'almc-electronic-invoicing-verifactu' ),
                'cta_text'     => __( 'Ir a Certificados', 'almc-electronic-invoicing-verifactu' ),
                'cta_url'      => 'https://almc.es/verifactu/certificates',
                'cta_external' => true,
                'done'         => false,
            ),
            array(
                'title'        => __( '3. Crea una serie de facturación', 'almc-electronic-invoicing-verifactu' ),
                'description'  => __( 'En "Series" del panel VeriFactu, crea una serie nueva (p.ej. "A" o "WC-2026"). Esa serie será la que use el plugin para numerar las facturas que emitas desde WooCommerce.', 'almc-electronic-invoicing-verifactu' ),
                'cta_text'     => __( 'Ir a Series', 'almc-electronic-invoicing-verifactu' ),
                'cta_url'      => 'https://almc.es/verifactu/series',
                'cta_external' => true,
                'done'         => ! empty( $series_code ),
            ),
            array(
                'title'        => __( '4. Genera una clave API y pégala abajo', 'almc-electronic-invoicing-verifactu' ),
                'description'  => sprintf(
                    /* translators: %s anchor to API Keys */
                    __( 'En la sección %s del panel VeriFactu pulsa "Crear nueva clave". Cópiala (solo se muestra una vez) y pégala en el campo "Clave API" de este mismo formulario.', 'almc-electronic-invoicing-verifactu' ),
                    '<a href="https://almc.es/verifactu/api-keys" target="_blank" rel="noopener">' . esc_html__( '"API Keys"', 'almc-electronic-invoicing-verifactu' ) . '</a>'
                ),
                'cta_text'     => __( 'Generar clave API', 'almc-electronic-invoicing-verifactu' ),
                'cta_url'      => 'https://almc.es/verifactu/api-keys',
                'cta_external' => true,
                'done'         => ! empty( $api_key ),
            ),
            array(
                'title'        => __( '5. Configura el plugin y envía tu primera factura', 'almc-electronic-invoicing-verifactu' ),
                'description'  => __( 'Pega la clave API y el código de serie aquí debajo, marca el estado de pedido que dispara el envío automático (lo normal: "Completado") y guarda. Después haz un pedido test y márcalo completado — verás la factura aparecer en el metabox "VeriFactu" del pedido.', 'almc-electronic-invoicing-verifactu' ),
                'cta_text'     => $first_invoice_sent ? __( 'Ver mis facturas', 'almc-electronic-invoicing-verifactu' ) : __( 'Ir a pedidos', 'almc-electronic-invoicing-verifactu' ),
                'cta_url'      => admin_url( 'admin.php?page=wc-orders' ),
                'cta_external' => false,
                'done'         => $first_invoice_sent,
            ),
        );

        $completed_count = 0;
        foreach ( $steps as $s ) {
            if ( ! empty( $s['done'] ) ) {
                $completed_count++;
            }
        }

        return array(
            'steps'           => $steps,
            'completed_count' => $completed_count,
            'total_count'     => count( $steps ),
        );
    }

    /**
     * Add order metabox (legacy post-type orders).
     */
    public static function add_order_metabox() {
        $screen = get_current_screen();
        if ( $screen && 'shop_order' === $screen->id ) {
            add_meta_box(
                'almc_vf_order_metabox',
                __( 'VeriFactu', 'almc-electronic-invoicing-verifactu' ),
                array( __CLASS__, 'render_order_metabox' ),
                'shop_order',
                'side',
                'high'
            );
        }
    }

    /**
     * Add order metabox for HPOS orders screen.
     */
    public static function add_order_metabox_hpos() {
        $screen = get_current_screen();
        if ( $screen && 'woocommerce_page_wc-orders' === $screen->id ) {
            add_meta_box(
                'almc_vf_order_metabox',
                __( 'VeriFactu', 'almc-electronic-invoicing-verifactu' ),
                array( __CLASS__, 'render_order_metabox_hpos' ),
                $screen->id,
                'side',
                'high'
            );
        }
    }

    /**
     * Render order metabox (legacy).
     *
     * @param WP_Post $post Current post object.
     */
    public static function render_order_metabox( $post ) {
        $order = wc_get_order( $post->ID );
        if ( $order ) {
            self::render_metabox_content( $order );
        }
    }

    /**
     * Render order metabox (HPOS).
     *
     * @param WC_Order $order WooCommerce order object.
     */
    public static function render_order_metabox_hpos( $order ) {
        if ( $order instanceof WC_Order ) {
            self::render_metabox_content( $order );
        }
    }

    /**
     * Render the metabox content.
     *
     * @param WC_Order $order WooCommerce order.
     */
    private static function render_metabox_content( $order ) {
        $order_id = $order->get_id();
        include ALMC_VF_PLUGIN_DIR . 'admin/views/order-metabox.php';
    }

    // ── AJAX Handlers ──

    /**
     * AJAX: Test API connection.
     */
    public static function ajax_test_connection() {
        check_ajax_referer( 'almc_vf_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'almc-electronic-invoicing-verifactu' ) ) );
        }

        $api    = ALMC_VF_Api_Client::instance();
        $result = $api->test_connection();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
            ) );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Manual invoice submission.
     */
    public static function ajax_manual_submit() {
        check_ajax_referer( 'almc_vf_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'almc-electronic-invoicing-verifactu' ) ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => __( 'ID de pedido no valido.', 'almc-electronic-invoicing-verifactu' ) ) );
        }

        $result = ALMC_VF_Order_Handler::manual_submit( $order_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
            ) );
        }

        $data = isset( $result['data'] ) ? $result['data'] : $result;

        wp_send_json_success( array(
            'message' => __( 'Factura enviada correctamente.', 'almc-electronic-invoicing-verifactu' ),
            'status'  => isset( $data['status'] ) ? $data['status'] : 'queued',
            'uuid'    => isset( $data['uuid'] ) ? $data['uuid'] : '',
        ) );
    }

    /**
     * AJAX: Check invoice status.
     */
    public static function ajax_check_status() {
        check_ajax_referer( 'almc_vf_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'almc-electronic-invoicing-verifactu' ) ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => __( 'ID de pedido no valido.', 'almc-electronic-invoicing-verifactu' ) ) );
        }

        $result = ALMC_VF_Order_Handler::refresh_status( $order_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
            ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Estado actualizado.', 'almc-electronic-invoicing-verifactu' ),
            'status'  => isset( $result['status'] ) ? $result['status'] : '',
            'data'    => $result,
        ) );
    }
}
