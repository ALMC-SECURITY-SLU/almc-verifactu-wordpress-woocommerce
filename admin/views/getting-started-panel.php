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

$pct = $total_count > 0 ? round( ( $completed_count / $total_count ) * 100 ) : 0;
$all_done = $completed_count === $total_count;
?>
<div class="almc-vf-onboarding <?php echo $all_done ? 'almc-vf-onboarding--complete' : ''; ?>">
    <div class="almc-vf-onboarding__header">
        <h2 class="almc-vf-onboarding__title">
            <?php if ( $all_done ) : ?>
                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                <?php esc_html_e( 'Configuración completa', 'almc-verifactu' ); ?>
            <?php else : ?>
                <span class="dashicons dashicons-flag" aria-hidden="true"></span>
                <?php esc_html_e( 'Cómo empezar', 'almc-verifactu' ); ?>
            <?php endif; ?>
        </h2>
        <div class="almc-vf-onboarding__progress">
            <span class="almc-vf-onboarding__counter">
                <?php
                printf(
                    /* translators: %1$d completados, %2$d total */
                    esc_html__( '%1$d de %2$d pasos', 'almc-verifactu' ),
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
            <?php esc_html_e( 'Todo listo. Cada vez que un pedido cambie al estado configurado, su factura se enviará automáticamente a la AEAT.', 'almc-verifactu' ); ?>
        </p>
    <?php endif; ?>
</div>

<style>
.almc-vf-onboarding {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-left: 4px solid #2271b1;
    border-radius: 4px;
    padding: 20px 24px;
    margin: 20px 0 30px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.almc-vf-onboarding--complete {
    border-left-color: #00a32a;
}
.almc-vf-onboarding__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 16px;
}
.almc-vf-onboarding__title {
    margin: 0 !important;
    padding: 0 !important;
    font-size: 1.3em;
    display: flex;
    align-items: center;
    gap: 8px;
}
.almc-vf-onboarding__title .dashicons {
    color: #2271b1;
    font-size: 24px;
    width: 24px;
    height: 24px;
}
.almc-vf-onboarding--complete .almc-vf-onboarding__title .dashicons {
    color: #00a32a;
}
.almc-vf-onboarding__progress {
    flex: 1;
    max-width: 280px;
    min-width: 200px;
}
.almc-vf-onboarding__counter {
    display: block;
    font-size: .9em;
    color: #50575e;
    margin-bottom: 6px;
    text-align: right;
}
.almc-vf-onboarding__bar {
    background: #f0f0f1;
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
}
.almc-vf-onboarding__bar-fill {
    background: #2271b1;
    height: 100%;
    transition: width .3s ease;
}
.almc-vf-onboarding--complete .almc-vf-onboarding__bar-fill {
    background: #00a32a;
}
.almc-vf-onboarding__steps {
    list-style: none;
    margin: 0;
    padding: 0;
    counter-reset: step;
}
.almc-vf-onboarding__step {
    display: flex;
    gap: 16px;
    padding: 14px 0;
    border-top: 1px solid #f0f0f1;
}
.almc-vf-onboarding__step:first-child {
    border-top: none;
    padding-top: 4px;
}
.almc-vf-onboarding__step-num {
    flex: 0 0 36px;
    width: 36px;
    height: 36px;
    background: #f0f0f1;
    color: #2c3338;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 16px;
}
.almc-vf-onboarding__step.is-done .almc-vf-onboarding__step-num {
    background: #00a32a;
    color: #fff;
}
.almc-vf-onboarding__step.is-done .almc-vf-onboarding__step-num .dashicons {
    font-size: 22px;
    width: 22px;
    height: 22px;
}
.almc-vf-onboarding__step-body {
    flex: 1;
    min-width: 0;
}
.almc-vf-onboarding__step-title {
    margin: 0 0 4px;
    font-size: 1em;
    font-weight: 600;
}
.almc-vf-onboarding__step.is-done .almc-vf-onboarding__step-title {
    color: #50575e;
    text-decoration: line-through;
    text-decoration-color: rgba(80, 87, 94, .4);
}
.almc-vf-onboarding__step-desc {
    margin: 0 0 10px;
    color: #50575e;
    font-size: .95em;
    line-height: 1.5;
}
.almc-vf-onboarding__footer {
    margin: 16px 0 0;
    padding: 12px 16px;
    background: #f6f7f7;
    border-radius: 4px;
    color: #1e1e1e;
    font-style: italic;
}
</style>
