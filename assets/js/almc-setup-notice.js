/**
 * ALMC Electronic Invoicing for VeriFactu — setup notice dismiss handler.
 *
 * Sends an AJAX request to permanently dismiss the welcome / setup-checklist
 * admin notice. Enqueued from ALMC_VF_Admin::enqueue_assets() on the screens
 * where the notice can appear.
 *
 * Requires the localized object `almcVfSetupNotice` with keys:
 *   - ajaxUrl: WP ajax endpoint
 *   - nonce:   nonce for action `almc_vf_dismiss_setup_notice`
 */
( function () {
    'use strict';

    if ( typeof window.almcVfSetupNotice === 'undefined' ) {
        return;
    }

    document.addEventListener( 'DOMContentLoaded', function () {
        var el = document.querySelector( '.almc-vf-setup-notice' );
        if ( ! el ) {
            return;
        }

        el.addEventListener( 'click', function ( e ) {
            if ( ! e.target.classList.contains( 'notice-dismiss' ) ) {
                return;
            }
            var fd = new FormData();
            fd.append( 'action', 'almc_vf_dismiss_setup_notice' );
            fd.append( 'nonce', window.almcVfSetupNotice.nonce );
            fetch( window.almcVfSetupNotice.ajaxUrl, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            } );
        } );
    } );
} )();
