/**
 * Rigtig for mig - Expert Registration JavaScript
 *
 * Handles expert registration form submission
 *
 * @package Rigtig_For_Mig
 * @since 3.5.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        /**
         * Expert Registration Form Handler
         */
        $('#rfm-expert-registration-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $message = $('#rfm-registration-message');

            // Validate passwords match
            if ($('#reg_password').val() !== $('#reg_password_confirm').val()) {
                $message.html('<div class="rfm-error">' + rfmRegistration.strings.passwordMismatch + '</div>');
                return;
            }

            // Disable button
            $button.prop('disabled', true).text(rfmRegistration.strings.creating);
            $message.html('');

            $.ajax({
                url: rfmRegistration.ajaxurl,
                type: 'POST',
                data: $form.serialize() + '&action=rfm_submit_expert_registration',
                success: function(response) {
                    if (response.success) {
                        $message.html('<div class="rfm-success">' + response.data.message + '</div>');
                        $form[0].reset();

                        // Redirect after 2 seconds
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 2000);
                    } else {
                        $message.html('<div class="rfm-error">' + response.data.message + '</div>');
                        $button.prop('disabled', false).text(rfmRegistration.strings.createProfile);
                    }
                },
                error: function() {
                    $message.html('<div class="rfm-error">' + rfmRegistration.strings.error + '</div>');
                    $button.prop('disabled', false).text(rfmRegistration.strings.createProfile);
                }
            });
        });

        /**
         * Category Limit Handler
         * Enforces max categories based on selected plan
         */
        $('input[name="plan"]').on('change', function() {
            var plan = $(this).val();
            var $container = $('#rfm-registration-categories');
            var limit = parseInt($container.data('limit-' + plan) || 1);

            // Update category selection based on new limit
            enforc

eCategoryLimit(limit);
        });

        function enforceCategoryLimit(limit) {
            var $checkboxes = $('.rfm-category-checkbox');
            var checkedCount = $checkboxes.filter(':checked').length;

            // Uncheck exceeding categories
            if (checkedCount > limit) {
                var count = 0;
                $checkboxes.filter(':checked').each(function() {
                    if (count >= limit) {
                        $(this).prop('checked', false);
                    }
                    count++;
                });
            }

            // Bind change event to enforce limit
            $checkboxes.off('change.categoryLimit').on('change.categoryLimit', function() {
                var checked = $checkboxes.filter(':checked').length;

                if (checked > limit) {
                    $(this).prop('checked', false);
                    $('#rfm-category-limit-notice').fadeIn();
                    setTimeout(function() {
                        $('#rfm-category-limit-notice').fadeOut();
                    }, 3000);
                }
            });
        }

        // Initialize with default plan (free = 1 category)
        enforceCategoryLimit(1);
    });

})(jQuery);
