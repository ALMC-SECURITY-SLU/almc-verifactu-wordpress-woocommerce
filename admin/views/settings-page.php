<?php
/**
 * Settings page template.
 *
 * @package ALMC_VeriFactu
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this is a partial included from render_settings_page(); variables here are include-scoped locals, not real globals.
?>
<div class="wrap almc-vf-settings">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <div class="almc-vf-connection-status">
        <?php
        $api = ALMC_VF_Api_Client::instance();
        if ( $api->is_configured() ) :
            ?>
            <span class="almc-vf-badge almc-vf-badge-info">
                <?php esc_html_e( 'API configurada', 'almc-electronic-invoicing-verifactu' ); ?>
            </span>
        <?php else : ?>
            <span class="almc-vf-badge almc-vf-badge-warning">
                <?php esc_html_e( 'API no configurada', 'almc-electronic-invoicing-verifactu' ); ?>
            </span>
        <?php endif; ?>
    </div>

    <?php
    // Panel "Cómo empezar" — guía visual del setup.
    $setup            = ALMC_VF_Admin::get_setup_steps();
    $steps            = $setup['steps'];
    $completed_count  = $setup['completed_count'];
    $total_count      = $setup['total_count'];
    include ALMC_VF_PLUGIN_DIR . 'admin/views/getting-started-panel.php';
    ?>

    <form method="post" action="options.php">
        <?php
        settings_fields( ALMC_VF_Settings::OPTION_GROUP );
        do_settings_sections( 'almc-electronic-invoicing-verifactu' );
        ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e( 'Probar conexion', 'almc-electronic-invoicing-verifactu' ); ?></th>
                <td>
                    <button type="button" id="almc-vf-test-connection" class="button button-secondary">
                        <?php esc_html_e( 'Probar conexion', 'almc-electronic-invoicing-verifactu' ); ?>
                    </button>
                    <span id="almc-vf-test-result" class="almc-vf-test-result"></span>
                    <p class="description">
                        <?php esc_html_e( 'Guarda los ajustes antes de probar la conexion.', 'almc-electronic-invoicing-verifactu' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e( 'Informacion de Webhook', 'almc-electronic-invoicing-verifactu' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e( 'URL del Webhook', 'almc-electronic-invoicing-verifactu' ); ?></th>
                <td>
                    <code id="almc-vf-webhook-url"><?php echo esc_html( home_url( '/almc-verifactu/webhook/' ) ); ?></code>
                    <button type="button" class="button button-small almc-vf-copy-btn" data-target="almc-vf-webhook-url">
                        <?php esc_html_e( 'Copiar', 'almc-electronic-invoicing-verifactu' ); ?>
                    </button>
                    <p class="description">
                        <?php esc_html_e( 'Configura esta URL en tu panel de VeriFactu para recibir notificaciones de estado.', 'almc-electronic-invoicing-verifactu' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <details class="almc-vf-advanced" style="margin:1.5em 0;">
            <summary style="cursor:pointer;font-weight:600;padding:.6em 0;">
                <?php esc_html_e( 'Configuracion avanzada', 'almc-electronic-invoicing-verifactu' ); ?>
            </summary>
            <p class="description" style="margin:.6em 0;">
                <?php esc_html_e( 'No toques esto a menos que tengas una instancia self-hosted de VeriFactu o lo indique el soporte de ALMC.', 'almc-electronic-invoicing-verifactu' ); ?>
            </p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="almc_vf_api_url">
                            <?php esc_html_e( 'URL de la API', 'almc-electronic-invoicing-verifactu' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="url"
                               id="almc_vf_api_url"
                               name="almc_vf_api_url"
                               class="regular-text"
                               value="<?php echo esc_attr( get_option( 'almc_vf_api_url', ALMC_VF_Settings::DEFAULT_API_URL ) ); ?>"
                               placeholder="<?php echo esc_attr( ALMC_VF_Settings::DEFAULT_API_URL ); ?>" />
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s is the default API URL */
                                esc_html__( 'Por defecto: %s. Cambia solo si conectas a otra instancia.', 'almc-electronic-invoicing-verifactu' ),
                                '<code>' . esc_html( ALMC_VF_Settings::DEFAULT_API_URL ) . '</code>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
            </table>
        </details>

        <?php submit_button( __( 'Guardar ajustes', 'almc-electronic-invoicing-verifactu' ) ); ?>
    </form>
</div>
