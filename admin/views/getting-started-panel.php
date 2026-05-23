<?php
/**
 * Panel "Cómo empezar" en la pestaña Settings.
 *
 * Detecta el progreso del setup y muestra los pasos pendientes con CTAs
 * directos. Cuando todos los pasos están completados, se muestra plegado.
 *
 * Variables esperadas (vienen del controller):
 *  - $steps  array de pasos con: title, description, cta_text, cta_url, done
 *  - $completed_count
 *  - $total_count
 *
 * @package ALMC_VeriFactu
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this is a partial included from render_settings_page(); variables here are include-scoped locals, not real globals.

$pct = $total_count > 0 ? round( ( $completed_count / $total_count ) * 100 ) : 0;
$all_done = $completed_count === $total_count;
?>
<div class="almc-vf-onboarding <?php echo $all_done ? 'almc-vf-onboarding--complete' : ''; ?>">
    <div class="almc-vf-onboarding__header">
        <h2 class="almc-vf-onboarding__title">
            <?php if ( $all_done ) : ?>
                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                <?php esc_html_e( 'Configuración completa', 'almc-electronic-invoicing-verifactu' ); ?>
            <?php else : ?>
                <span class="dashicons dashicons-flag" aria-hidden="true"></span>
                <?php esc_html_e( 'Cómo empezar', 'almc-electronic-invoicing-verifactu' ); ?>
            <?php endif; ?>
        </h2>
        <div class="almc-vf-onboarding__progress">
            <span class="almc-vf-onboarding__counter">
                <?php
                printf(
                    /* translators: %1$d completados, %2$d total */
                    esc_html__( '%1$d de %2$d pasos', 'almc-electronic-invoicing-verifactu' ),
                    (int) $completed_count,
                    (int) $total_count
                );
                ?>
            </span>
            <div class="almc-vf-onboarding__bar">
                <div class="almc-vf-onboarding__bar-fill" style="width:<?php echo (int) $pct; ?>%;"></div>
            </div>
        </div>
    </div>

    <ol class="almc-vf-onboarding__steps">
        <?php foreach ( $steps as $i => $step ) : ?>
            <li class="almc-vf-onboarding__step <?php echo ! empty( $step['done'] ) ? 'is-done' : ''; ?>">
                <div class="almc-vf-onboarding__step-num">
                    <?php if ( ! empty( $step['done'] ) ) : ?>
                        <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                    <?php else : ?>
                        <?php echo (int) ( $i + 1 ); ?>
                    <?php endif; ?>
                </div>
                <div class="almc-vf-onboarding__step-body">
                    <h3 class="almc-vf-onboarding__step-title">
                        <?php echo esc_html( $step['title'] ); ?>
                    </h3>
                    <p class="almc-vf-onboarding__step-desc">
                        <?php echo wp_kses_post( $step['description'] ); ?>
                    </p>
                    <?php if ( ! empty( $step['cta_text'] ) ) : ?>
                        <a href="<?php echo esc_url( $step['cta_url'] ); ?>"
                           class="button <?php echo empty( $step['done'] ) ? 'button-primary' : 'button-secondary'; ?>"
                           <?php echo ! empty( $step['cta_external'] ) ? 'target="_blank" rel="noopener"' : ''; ?>>
                            <?php echo esc_html( $step['cta_text'] ); ?>
                            <?php if ( ! empty( $step['cta_external'] ) ) : ?>
                                <span class="dashicons dashicons-external" aria-hidden="true" style="font-size:14px;line-height:1.5;vertical-align:text-bottom;"></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>

    <?php if ( $all_done ) : ?>
        <p class="almc-vf-onboarding__footer">
            <?php esc_html_e( 'Todo listo. Cada vez que un pedido cambie al estado configurado, su factura se enviará automáticamente a la AEAT.', 'almc-electronic-invoicing-verifactu' ); ?>
        </p>
    <?php endif; ?>
</div>
