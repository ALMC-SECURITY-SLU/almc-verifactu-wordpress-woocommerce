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
     * Show a setup checklist notice on admin pages when the plugin is not yet configured.
     */
    public static function maybe_show_setup_notice() {
        // Permite ocultarlo permanentemente.
        if ( get_option( 'almc_vf_setup_notice_dismissed' ) ) {
            return;
        }
        // Solo a quien puede gestionar WooCommerce.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        // No spammear en pages no relevantes (solo dashboard, plugins, WC).
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $allowed_screens = array( 'dashboard', 'plugins', 'woocommerce_page_wc-admin', 'woocommerce_page_wc-orders', 'woocommerce_page_almc-verifactu' );
        if ( ! $screen || ! in_array( $screen->id, $allowed_screens, true ) ) {
            return;
        }
        // Si la API ya está configurada Y hay serie, asumimos que el setup está hecho.
        $api_key = get_option( 'almc_vf_api_key' );
        $series  = get_option( 'almc_vf_series_code' );
        if ( ! empty( $api_key ) && ! empty( $series ) ) {
            return;
        }

        $settings_url = admin_url( 'admin.php?page=almc-verifactu' );
        $signup_url   = 'https://almc.es/verifactu/register';
        $nonce        = wp_create_nonce( 'almc_vf_dismiss_setup_notice' );

        ?>
        <div class="notice notice-info is-dismissible almc-vf-setup-notice" data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <h3 style="margin-top:.5em;">
                <?php esc_html_e( '¡Bienvenido a ALMC VeriFactu!', 'almc-verifactu' ); ?>
            </h3>
            <p>
                <?php esc_html_e( 'Para empezar a emitir facturas a la AEAT desde tu tienda, completa estos pasos:', 'almc-verifactu' ); ?>
            </p>
            <ol style="margin-left:1.5em;">
                <li>
                    <a href="<?php echo esc_url( $signup_url ); ?>" target="_blank" rel="noopener">
                        <?php esc_html_e( 'Crear cuenta en VeriFactu SaaS', 'almc-verifactu' ); ?>
                    </a>
                    <?php esc_html_e( ' (plan gratuito hasta 10 facturas/mes)', 'almc-verifactu' ); ?>
                </li>
                <li><?php esc_html_e( 'Subir tu certificado digital de representante (.p12 / .pfx) al panel VeriFactu', 'almc-verifactu' ); ?></li>
                <li><?php esc_html_e( 'Crear una serie de facturación (p.ej. "A" o "WC-2026") en el panel VeriFactu', 'almc-verifactu' ); ?></li>
                <li><?php esc_html_e( 'Generar una clave API en la sección "API Keys" del panel', 'almc-verifactu' ); ?></li>
                <li>
                    <a href="<?php echo esc_url( $settings_url ); ?>">
                        <strong><?php esc_html_e( 'Configurar el plugin con tu API key y código de serie', 'almc-verifactu' ); ?></strong>
                    </a>
                </li>
            </ol>
            <p>
                <a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Ir a los ajustes', 'almc-verifactu' ); ?>
                </a>
                <a href="https://almc.es/verifactu/plugin/woocommerce" target="_blank" rel="noopener" class="button">
                    <?php esc_html_e( 'Documentación', 'almc-verifactu' ); ?>
                </a>
            </p>
            <script>
            (function(){
                var el = document.querySelector('.almc-vf-setup-notice');
                if (!el) return;
                el.addEventListener('click', function(e){
                    if (!e.target.classList.contains('notice-dismiss')) return;
                    var fd = new FormData();
                    fd.append('action', 'almc_vf_dismiss_setup_notice');
                    fd.append('nonce', el.dataset.nonce);
                    fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' });
                });
            })();
            </script>
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
                ALMC_VF_PLUGIN_DIR . 'almc-verifactu.php',
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
            __( 'VeriFactu', 'almc-verifactu' ),
            __( 'VeriFactu', 'almc-verifactu' ),
            'manage_woocommerce',
            'almc-verifactu',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    /**
     * Enqueue admin CSS and JS.
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_assets( $hook ) {
        // Load on settings page and order edit pages.
        $is_settings = 'woocommerce_page_almc-verifactu' === $hook;
        $is_order    = in_array( $hook, array( 'post.php', 'post-new.php', 'woocommerce_page_wc-orders' ), true );

        if ( ! $is_settings && ! $is_order ) {
            return;
        }

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
                'testing'       => __( 'Probando conexion...', 'almc-verifactu' ),
                'submitting'    => __( 'Enviando a VeriFactu...', 'almc-verifactu' ),
                'checking'      => __( 'Consultando estado...', 'almc-verifactu' ),
                'success'       => __( 'Correcto', 'almc-verifactu' ),
                'error'         => __( 'Error', 'almc-verifactu' ),
                'copied'        => __( 'UUID copiado', 'almc-verifactu' ),
                'confirmSubmit' => __( 'Enviar esta factura a VeriFactu?', 'almc-verifactu' ),
            ),
        ) );
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
        $settings_url = admin_url( 'admin.php?page=almc-verifactu' );

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
                'title'        => __( '1. Crea tu cuenta en VeriFactu SaaS', 'almc-verifactu' ),
                'description'  => __( 'Regístrate gratis en almc.es/verifactu. Necesitarás los datos fiscales de tu empresa (razón social y NIF/CIF). Plan gratuito incluido hasta 10 facturas/mes.', 'almc-verifactu' ),
                'cta_text'     => __( 'Crear cuenta', 'almc-verifactu' ),
                'cta_url'      => 'https://almc.es/verifactu/register',
                'cta_external' => true,
                'done'         => false, // No tenemos forma de detectarlo desde el plugin
            ),
            array(
                'title'        => __( '2. Sube tu certificado digital al panel VeriFactu', 'almc-verifactu' ),
                'description'  => __( 'En tu panel de VeriFactu entra en "Certificados" y sube tu archivo .p12 o .pfx con su contraseña. Se guardará cifrado (HKDF + AES-256). Si no tienes uno, lo obtienes gratis en la FNMT.', 'almc-verifactu' ),
                'cta_text'     => __( 'Ir a Certificados', 'almc-verifactu' ),
                'cta_url'      => 'https://almc.es/verifactu/certificates',
                'cta_external' => true,
                'done'         => false,
            ),
            array(
                'title'        => __( '3. Crea una serie de facturación', 'almc-verifactu' ),
                'description'  => __( 'En "Series" del panel VeriFactu, crea una serie nueva (p.ej. "A" o "WC-2026"). Esa serie será la que use el plugin para numerar las facturas que emitas desde WooCommerce.', 'almc-verifactu' ),
                'cta_text'     => __( 'Ir a Series', 'almc-verifactu' ),
                'cta_url'      => 'https://almc.es/verifactu/series',
                'cta_external' => true,
                'done'         => ! empty( $series_code ),
            ),
            array(
                'title'        => __( '4. Genera una clave API y pégala abajo', 'almc-verifactu' ),
                'description'  => sprintf(
                    /* translators: %s anchor to API Keys */
                    __( 'En la sección %s del panel VeriFactu pulsa "Crear nueva clave". Cópiala (solo se muestra una vez) y pégala en el campo "Clave API" de este mismo formulario.', 'almc-verifactu' ),
                    '<a href="https://almc.es/verifactu/api-keys" target="_blank" rel="noopener">' . esc_html__( '"API Keys"', 'almc-verifactu' ) . '</a>'
                ),
                'cta_text'     => __( 'Generar clave API', 'almc-verifactu' ),
                'cta_url'      => 'https://almc.es/verifactu/api-keys',
                'cta_external' => true,
                'done'         => ! empty( $api_key ),
            ),
            array(
                'title'        => __( '5. Configura el plugin y envía tu primera factura', 'almc-verifactu' ),
                'description'  => __( 'Pega la clave API y el código de serie aquí debajo, marca el estado de pedido que dispara el envío automático (lo normal: "Completado") y guarda. Después haz un pedido test y márcalo completado — verás la factura aparecer en el metabox "VeriFactu" del pedido.', 'almc-verifactu' ),
                'cta_text'     => $first_invoice_sent ? __( 'Ver mis facturas', 'almc-verifactu' ) : __( 'Ir a pedidos', 'almc-verifactu' ),
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
                __( 'VeriFactu', 'almc-verifactu' ),
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
                __( 'VeriFactu', 'almc-verifactu' ),
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
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'almc-verifactu' ) ) );
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
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'almc-verifactu' ) ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => __( 'ID de pedido no valido.', 'almc-verifactu' ) ) );
        }

        $result = ALMC_VF_Order_Handler::manual_submit( $order_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
            ) );
        }

        $data = isset( $result['data'] ) ? $result['data'] : $result;

        wp_send_json_success( array(
            'message' => __( 'Factura enviada correctamente.', 'almc-verifactu' ),
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
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'almc-verifactu' ) ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => __( 'ID de pedido no valido.', 'almc-verifactu' ) ) );
        }

        $result = ALMC_VF_Order_Handler::refresh_status( $order_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
            ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Estado actualizado.', 'almc-verifactu' ),
            'status'  => isset( $result['status'] ) ? $result['status'] : '',
            'data'    => $result,
        ) );
    }
}
