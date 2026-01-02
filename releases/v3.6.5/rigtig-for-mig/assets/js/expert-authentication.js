/**
 * Rigtig for mig - Expert Authentication JavaScript
 *
 * Handles expert login form submission
 *
 * @package Rigtig_For_Mig
 * @since 3.5.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        /**
         * Expert Login Form Handler
         */
        $('#rfm-expert-login-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $message = $('#rfm-login-message');
            var originalText = $button.text();

            // Disable button
            $button.prop('disabled', true).text(rfmAuth.strings.loggingIn);
            $message.html('');

            $.ajax({
                url: rfmAuth.ajaxurl,
                type: 'POST',
                data: $form.serialize() + '&action=rfm_expert_login',
                success: function(response) {
                    if (response.success) {
                        $message.html('<div class="rfm-success">' + response.data.message + '</div>');

                        // Redirect after 1 second
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 1000);
                    } else {
                        $message.html('<div class="rfm-error">' + response.data.message + '</div>');
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    $message.html('<div class="rfm-error">' + rfmAuth.strings.error + '</div>');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
    });

})(jQuery);
