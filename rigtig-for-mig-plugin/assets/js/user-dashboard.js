/**
 * User Dashboard JavaScript
 *
 * v3.7.0 - Complete clean rebuild following Expert Dashboard pattern
 *
 * Minimal, focused implementation:
 * - Profile form submission
 * - Logout functionality
 * - Clean error handling
 * - Uses rfmUserDashboard for ajaxurl (no dependency on rfm-public)
 *
 * @package Rigtig_For_Mig
 * @since 3.7.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // ========================================
        // PROFILE FORM SUBMISSION
        // ========================================
        $('#rfm-user-profile-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $message = $('#rfm-user-dashboard-message');
            var originalText = $button.text();

            // Collect form data
            var formData = {
                action: 'rfm_update_user_profile',
                rfm_user_nonce: $form.find('[name="rfm_user_nonce"]').val(),
                display_name: $('#user_display_name').val(),
                phone: $('#user_phone').val(),
                bio: $('#user_bio').val()
            };

            // Validate display name
            if (!formData.display_name || formData.display_name.trim() === '') {
                $message.html('<div class="rfm-error">Visningsnavn er påkrævet</div>');
                return;
            }

            $button.prop('disabled', true).text(rfmUserDashboard.strings.savingText);
            $message.html('');

            $.ajax({
                url: rfmUserDashboard.ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $message.html('<div class="rfm-success">' + response.data.message + '</div>');

                        // Scroll to message
                        $('html, body').animate({
                            scrollTop: $message.offset().top - 100
                        }, 300);
                    } else {
                        $message.html('<div class="rfm-error">' + (response.data.message || rfmUserDashboard.strings.errorText) + '</div>');
                    }
                    $button.prop('disabled', false).text(originalText);
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    console.log('Response:', xhr.responseText);
                    $message.html('<div class="rfm-error">' + rfmUserDashboard.strings.errorText + '</div>');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });

        // ========================================
        // LOGOUT BUTTON
        // ========================================
        $('#rfm-user-logout-btn').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $message = $('#rfm-user-dashboard-message');
            var originalText = $button.text();

            $button.prop('disabled', true).text(rfmUserDashboard.strings.loggingOut);

            $.ajax({
                url: rfmUserDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rfm_user_logout',
                    rfm_user_nonce: $('[name="rfm_user_nonce"]').val()
                },
                success: function(response) {
                    if (response.success && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        // Fallback redirect to home
                        window.location.href = '/';
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Logout Error:', status, error);
                    // Force redirect to home on error
                    window.location.href = '/';
                }
            });
        });
    });

})(jQuery);
