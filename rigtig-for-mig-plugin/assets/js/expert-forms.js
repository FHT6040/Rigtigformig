/**
 * Rigtig for mig - Expert Forms JavaScript (Login & Registration)
 *
 * @package Rigtig_For_Mig
 * @since 3.4.1
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Expert Login Form
        $('#rfm-expert-login-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $message = $('#rfm-login-message');

            $button.prop('disabled', true).text(rfmExpertForms.strings.loggingIn);
            $message.html('');

            $.ajax({
                url: rfmExpertForms.ajaxurl,
                type: 'POST',
                data: $form.serialize() + '&action=rfm_expert_login',
                success: function(response) {
                    if (response.success) {
                        $message.html('<div class="rfm-success">' + response.data.message + '</div>');

                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 1000);
                    } else {
                        $message.html('<div class="rfm-error">' + response.data.message + '</div>');
                        $button.prop('disabled', false).text(rfmExpertForms.strings.login);
                    }
                },
                error: function() {
                    $message.html('<div class="rfm-error">' + rfmExpertForms.strings.error + '</div>');
                    $button.prop('disabled', false).text(rfmExpertForms.strings.login);
                }
            });
        });

        // Expert Registration Form
        $('#rfm-expert-registration-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $message = $('#rfm-registration-message');

            // Validate passwords match
            if ($('#reg_password').val() !== $('#reg_password_confirm').val()) {
                $message.html('<div class="rfm-error">' + rfmExpertForms.strings.passwordMismatch + '</div>');
                return;
            }

            $button.prop('disabled', true).text(rfmExpertForms.strings.creating);

            $.ajax({
                url: rfmExpertForms.ajaxurl,
                type: 'POST',
                data: $form.serialize() + '&action=rfm_submit_expert_registration',
                success: function(response) {
                    if (response.success) {
                        $message.html('<div class="rfm-success">' + response.data.message + '</div>');
                        $form[0].reset();

                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 2000);
                    } else {
                        $message.html('<div class="rfm-error">' + response.data.message + '</div>');
                        $button.prop('disabled', false).text(rfmExpertForms.strings.createProfile);
                    }
                },
                error: function() {
                    $message.html('<div class="rfm-error">' + rfmExpertForms.strings.error + '</div>');
                    $button.prop('disabled', false).text(rfmExpertForms.strings.createProfile);
                }
            });
        });

    });

})(jQuery);
