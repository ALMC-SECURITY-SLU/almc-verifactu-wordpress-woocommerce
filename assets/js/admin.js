/**
 * ALMC VeriFactu - Admin JavaScript
 *
 * Handles AJAX interactions for the settings page and order metabox.
 *
 * @package ALMC_VeriFactu
 */

(function ($) {
    'use strict';

    /**
     * Test Connection button handler.
     */
    $(document).on('click', '#almc-vf-test-connection', function (e) {
        e.preventDefault();

        var $button = $(this);
        var $result = $('#almc-vf-test-result');

        $button.prop('disabled', true);
        $result
            .removeClass('success error')
            .html(almcVf.i18n.testing + ' <span class="almc-vf-spinner"></span>')
            .show();

        $.ajax({
            url: almcVf.ajaxUrl,
            type: 'POST',
            data: {
                action: 'almc_vf_test_connection',
                nonce: almcVf.nonce
            },
            success: function (response) {
                if (response.success) {
                    $result
                        .removeClass('error')
                        .addClass('success')
                        .text(response.data.message || almcVf.i18n.success);
                } else {
                    $result
                        .removeClass('success')
                        .addClass('error')
                        .text(response.data.message || almcVf.i18n.error);
                }
            },
            error: function () {
                $result
                    .removeClass('success')
                    .addClass('error')
                    .text(almcVf.i18n.error);
            },
            complete: function () {
                $button.prop('disabled', false);
            }
        });
    });

    /**
     * Manual Submit button handler.
     */
    $(document).on('click', '.almc-vf-submit-btn', function (e) {
        e.preventDefault();

        if (!confirm(almcVf.i18n.confirmSubmit)) {
            return;
        }

        var $button = $(this);
        var orderId = $button.data('order-id');
        var $metabox = $button.closest('.almc-vf-metabox');
        var $message = $metabox.find('.almc-vf-ajax-message');

        $button.prop('disabled', true);
        $message
            .removeClass('success error')
            .html(almcVf.i18n.submitting + ' <span class="almc-vf-spinner"></span>')
            .show();

        $.ajax({
            url: almcVf.ajaxUrl,
            type: 'POST',
            data: {
                action: 'almc_vf_manual_submit',
                nonce: almcVf.nonce,
                order_id: orderId
            },
            success: function (response) {
                if (response.success) {
                    $message
                        .removeClass('error')
                        .addClass('success')
                        .text(response.data.message || almcVf.i18n.success);

                    // Reload metabox after a short delay to show updated data.
                    setTimeout(function () {
                        location.reload();
                    }, 1500);
                } else {
                    $message
                        .removeClass('success')
                        .addClass('error')
                        .text(response.data.message || almcVf.i18n.error);
                    $button.prop('disabled', false);
                }
            },
            error: function () {
                $message
                    .removeClass('success')
                    .addClass('error')
                    .text(almcVf.i18n.error);
                $button.prop('disabled', false);
            }
        });
    });

    /**
     * Check Status button handler.
     */
    $(document).on('click', '.almc-vf-check-status-btn', function (e) {
        e.preventDefault();

        var $button = $(this);
        var orderId = $button.data('order-id');
        var $metabox = $button.closest('.almc-vf-metabox');
        var $message = $metabox.find('.almc-vf-ajax-message');

        $button.prop('disabled', true);
        $message
            .removeClass('success error')
            .html(almcVf.i18n.checking + ' <span class="almc-vf-spinner"></span>')
            .show();

        $.ajax({
            url: almcVf.ajaxUrl,
            type: 'POST',
            data: {
                action: 'almc_vf_check_status',
                nonce: almcVf.nonce,
                order_id: orderId
            },
            success: function (response) {
                if (response.success) {
                    $message
                        .removeClass('error')
                        .addClass('success')
                        .text(response.data.message || almcVf.i18n.success);

                    // Reload to show updated status.
                    setTimeout(function () {
                        location.reload();
                    }, 1000);
                } else {
                    $message
                        .removeClass('success')
                        .addClass('error')
                        .text(response.data.message || almcVf.i18n.error);
                }
            },
            error: function () {
                $message
                    .removeClass('success')
                    .addClass('error')
                    .text(almcVf.i18n.error);
            },
            complete: function () {
                $button.prop('disabled', false);
            }
        });
    });

    /**
     * Copy to clipboard handler.
     */
    $(document).on('click', '.almc-vf-copy-btn', function (e) {
        e.preventDefault();

        var targetId = $(this).data('target');
        var $target = $('#' + targetId);
        var text = $target.text().trim();

        if (!text) {
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                showCopiedFeedback(e.target);
            });
        } else {
            // Fallback for older browsers.
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
            showCopiedFeedback(e.target);
        }
    });

    /**
     * Show brief "copied" feedback on a button.
     *
     * @param {HTMLElement} btn The button element.
     */
    function showCopiedFeedback(btn) {
        var $btn = $(btn);
        var original = $btn.text();
        $btn.text(almcVf.i18n.copied);
        setTimeout(function () {
            $btn.text(original);
        }, 1500);
    }

    /**
     * Toggle AEAT response visibility.
     */
    $(document).on('click', '.almc-vf-toggle-response', function (e) {
        e.preventDefault();
        $(this).next('.almc-vf-response-data').slideToggle(200);
    });

})(jQuery);
