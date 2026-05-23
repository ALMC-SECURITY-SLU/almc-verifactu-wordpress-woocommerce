<?php
/**
 * ALMC VeriFactu Settings
 *
 * WordPress Settings API integration for plugin configuration.
 *
 * @package ALMC_VeriFactu
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ALMC_VF_Settings {

    /**
     * Option group name.
     */
    const OPTION_GROUP = 'almc_vf_settings';

    /**
     * Default API URL. Single source of truth; usado en register_setting,
     * api-client y settings-page (details "configuracion avanzada").
     */
    const DEFAULT_API_URL = 'https://almc.es/api/verifactu/v1';

    /**
     * Initialize settings.
     */
    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    /**
     * Register all plugin settings with the WordPress Settings API.
     */
    public static function register_settings() {

        // ── Connection Section ──
        add_settings_section(
            'almc_vf_section_connection',
            __( 'Conexion API', 'almc-verifactu' ),
            array( __CLASS__, 'section_connection_callback' ),
            'almc-verifactu'
        );

        // API URL — registrada pero NO mostrada en el flujo principal.
        // Se renderiza en la sección "Configuración avanzada" del template
        // (settings-page.php) dentro de un <details> colapsado. El usuario
        // típico no debería tocarla.
        register_setting( self::OPTION_GROUP, 'almc_vf_api_url', array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => self::DEFAULT_API_URL,
        ) );

        // API Key.
        register_setting( self::OPTION_GROUP, 'almc_vf_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        add_settings_field(
            'almc_vf_api_key',
            __( 'Clave API', 'almc-verifactu' ),
            array( __CLASS__, 'field_text' ),
            'almc-verifactu',
            'almc_vf_section_connection',
            array(
                'id'          => 'almc_vf_api_key',
                'description' => __( 'Tu clave API de VeriFactu (X-Api-Key).', 'almc-verifactu' ),
                'class'       => 'regular-text',
            )
        );

        // API Secret.
        register_setting( self::OPTION_GROUP, 'almc_vf_api_secret', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        add_settings_field(
            'almc_vf_api_secret',
            __( 'Secreto API', 'almc-verifactu' ),
            array( __CLASS__, 'field_password' ),
            'almc-verifactu',
            'almc_vf_section_connection',
            array(
                'id'          => 'almc_vf_api_secret',
                'description' => __( 'Secreto API (para firma HMAC, uso futuro).', 'almc-verifactu' ),
                'class'       => 'regular-text',
            )
        );

        // Environment.
        register_setting( self::OPTION_GROUP, 'almc_vf_environment', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'sandbox',
        ) );
        add_settings_field(
            'almc_vf_environment',
            __( 'Entorno', 'almc-verifactu' ),
            array( __CLASS__, 'field_select' ),
            'almc-verifactu',
            'almc_vf_section_connection',
            array(
                'id'      => 'almc_vf_environment',
                'options' => array(
                    'sandbox'    => __( 'Sandbox (pruebas)', 'almc-verifactu' ),
                    'production' => __( 'Produccion', 'almc-verifactu' ),
                ),
                'description' => __( 'Selecciona el entorno de la API.', 'almc-verifactu' ),
            )
        );

        // ── Invoice Section ──
        add_settings_section(
            'almc_vf_section_invoice',
            __( 'Facturacion', 'almc-verifactu' ),
            array( __CLASS__, 'section_invoice_callback' ),
            'almc-verifactu'
        );

        // Series Code.
        register_setting( self::OPTION_GROUP, 'almc_vf_series_code', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'WC',
        ) );
        add_settings_field(
            'almc_vf_series_code',
            __( 'Codigo de serie', 'almc-verifactu' ),
            array( __CLASS__, 'field_text' ),
            'almc-verifactu',
            'almc_vf_section_invoice',
            array(
                'id'          => 'almc_vf_series_code',
                'default'     => 'WC',
                'description' => __( 'Codigo de serie para las facturas (p.ej. WC-2026). Debe existir en VeriFactu.', 'almc-verifactu' ),
                'class'       => 'regular-text',
            )
        );

        // Auto Submit.
        register_setting( self::OPTION_GROUP, 'almc_vf_auto_submit', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'no',
        ) );
        add_settings_field(
            'almc_vf_auto_submit',
            __( 'Envio automatico', 'almc-verifactu' ),
            array( __CLASS__, 'field_checkbox' ),
            'almc-verifactu',
            'almc_vf_section_invoice',
            array(
                'id'          => 'almc_vf_auto_submit',
                'label'       => __( 'Enviar automaticamente las facturas a la AEAT al completar el pedido.', 'almc-verifactu' ),
            )
        );

        // Auto Submit Statuses.
        register_setting( self::OPTION_GROUP, 'almc_vf_auto_submit_statuses', array(
            'type'              => 'array',
            'sanitize_callback' => array( __CLASS__, 'sanitize_statuses' ),
            'default'           => array( 'completed' ),
        ) );
        add_settings_field(
            'almc_vf_auto_submit_statuses',
            __( 'Estados para envio', 'almc-verifactu' ),
            array( __CLASS__, 'field_multiselect' ),
            'almc-verifactu',
            'almc_vf_section_invoice',
            array(
                'id'      => 'almc_vf_auto_submit_statuses',
                'options' => array(
                    'processing' => __( 'Procesando', 'almc-verifactu' ),
                    'completed'  => __( 'Completado', 'almc-verifactu' ),
                    'on-hold'    => __( 'En espera', 'almc-verifactu' ),
                ),
                'description' => __( 'Estados del pedido que dispararan el envio automatico.', 'almc-verifactu' ),
            )
        );

        // NIF Field Name.
        register_setting( self::OPTION_GROUP, 'almc_vf_nif_field', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '_billing_nif',
        ) );
        add_settings_field(
            'almc_vf_nif_field',
            __( 'Campo NIF', 'almc-verifactu' ),
            array( __CLASS__, 'field_text' ),
            'almc-verifactu',
            'almc_vf_section_invoice',
            array(
                'id'          => 'almc_vf_nif_field',
                'default'     => '_billing_nif',
                'description' => __( 'Nombre del meta campo donde se almacena el NIF/CIF del cliente.', 'almc-verifactu' ),
                'class'       => 'regular-text',
            )
        );

        // ── Webhook Section ──
        add_settings_section(
            'almc_vf_section_webhook',
            __( 'Webhook', 'almc-verifactu' ),
            array( __CLASS__, 'section_webhook_callback' ),
            'almc-verifactu'
        );

        // Webhook Secret (read-only).
        register_setting( self::OPTION_GROUP, 'almc_vf_webhook_secret', array(
            'type'              => 'string',
            'sanitize_callback' => array( __CLASS__, 'sanitize_webhook_secret' ),
            'default'           => '',
        ) );
        add_settings_field(
            'almc_vf_webhook_secret',
            __( 'Secreto Webhook', 'almc-verifactu' ),
            array( __CLASS__, 'field_readonly' ),
            'almc-verifactu',
            'almc_vf_section_webhook',
            array(
                'id'          => 'almc_vf_webhook_secret',
                'description' => __( 'Secreto para verificar las notificaciones entrantes. Se genera automaticamente.', 'almc-verifactu' ),
                'class'       => 'regular-text',
            )
        );
    }

    // ── Section Callbacks ──

    public static function section_connection_callback() {
        echo '<p>' . esc_html__( 'Configura la conexion con la API de VeriFactu SaaS.', 'almc-verifactu' ) . '</p>';
    }

    public static function section_invoice_callback() {
        echo '<p>' . esc_html__( 'Configura como se generan y envian las facturas.', 'almc-verifactu' ) . '</p>';
    }

    public static function section_webhook_callback() {
        $webhook_url = home_url( '/almc-verifactu/webhook/' );
        echo '<p>' . sprintf(
            /* translators: %s: webhook URL */
            esc_html__( 'Configura esta URL en tu panel de VeriFactu para recibir notificaciones: %s', 'almc-verifactu' ),
            '<code>' . esc_html( $webhook_url ) . '</code>'
        ) . '</p>';
    }

    // ── Field Renderers ──

    public static function field_text( $args ) {
        $value = get_option( $args['id'], isset( $args['default'] ) ? $args['default'] : '' );
        $class = isset( $args['class'] ) ? $args['class'] : 'regular-text';
        printf(
            '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="%3$s" />',
            esc_attr( $args['id'] ),
            esc_attr( $value ),
            esc_attr( $class )
        );
        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    public static function field_password( $args ) {
        $value = get_option( $args['id'], '' );
        $class = isset( $args['class'] ) ? $args['class'] : 'regular-text';
        printf(
            '<input type="password" id="%1$s" name="%1$s" value="%2$s" class="%3$s" />',
            esc_attr( $args['id'] ),
            esc_attr( $value ),
            esc_attr( $class )
        );
        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    public static function field_readonly( $args ) {
        $value = get_option( $args['id'], '' );
        $class = isset( $args['class'] ) ? $args['class'] : 'regular-text';
        printf(
            '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="%3$s" readonly="readonly" />',
            esc_attr( $args['id'] ),
            esc_attr( $value ),
            esc_attr( $class )
        );
        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    public static function field_select( $args ) {
        $value = get_option( $args['id'], '' );
        printf( '<select id="%1$s" name="%1$s">', esc_attr( $args['id'] ) );
        foreach ( $args['options'] as $key => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $key ),
                selected( $value, $key, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    public static function field_checkbox( $args ) {
        $value = get_option( $args['id'], 'no' );
        printf(
            '<label><input type="checkbox" id="%1$s" name="%1$s" value="yes" %2$s /> %3$s</label>',
            esc_attr( $args['id'] ),
            checked( $value, 'yes', false ),
            esc_html( $args['label'] )
        );
    }

    public static function field_multiselect( $args ) {
        $values = get_option( $args['id'], array() );
        if ( ! is_array( $values ) ) {
            $values = array();
        }
        foreach ( $args['options'] as $key => $label ) {
            printf(
                '<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s</label>',
                esc_attr( $args['id'] ),
                esc_attr( $key ),
                checked( in_array( $key, $values, true ), true, false ),
                esc_html( $label )
            );
        }
        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    // ── Sanitizers ──

    public static function sanitize_statuses( $input ) {
        if ( ! is_array( $input ) ) {
            return array( 'completed' );
        }
        $allowed = array( 'processing', 'completed', 'on-hold' );
        return array_values( array_intersect( $input, $allowed ) );
    }

    public static function sanitize_webhook_secret( $input ) {
        // If empty, generate a new one.
        if ( empty( $input ) ) {
            return wp_generate_password( 40, false );
        }
        return sanitize_text_field( $input );
    }
}
