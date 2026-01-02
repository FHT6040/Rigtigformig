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

    // ========================================
    // SAFETY CHECK: Verify dependencies
    // ========================================
    if (typeof rfmUserDashboard === 'undefined') {
        console.error('RFM User Dashboard: rfmUserDashboard object is not defined! Cannot initialize.');
        console.error('RFM User Dashboard: This likely means the script localization failed or is cached.');
        console.error('RFM User Dashboard: Please clear all caches and reload the page.');
        return;
    }

    // Debug logging if enabled
    if (rfmUserDashboard.debug) {
        console.log('RFM User Dashboard v' + rfmUserDashboard.version + ' initialized');
        console.log('AJAX URL:', rfmUserDashboard.ajaxurl);
        console.log('Nonce available:', rfmUserDashboard.nonce ? 'Yes' : 'No');
    }

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

            // Use nonce from localized data (fresh on every page load)
            var nonce = rfmUserDashboard.nonce || $form.find('[name="rfm_user_nonce"]').val();

            // Collect form data
            var formData = {
                action: 'rfm_update_user_profile',
                rfm_user_nonce: nonce,
                display_name: $('#user_display_name').val(),
                phone: $('#user_phone').val(),
                bio: $('#user_bio').val()
            };

            // Validate display name
            if (!formData.display_name || formData.display_name.trim() === '') {
                $message.html('<div class="rfm-error">Visningsnavn er påkrævet</div>');
                return;
            }

            if (rfmUserDashboard.debug) {
                console.log('RFM User Dashboard: Submitting profile update', formData);
            }

            $button.prop('disabled', true).text(rfmUserDashboard.strings.savingText);
            $message.html('');

            $.ajax({
                url: rfmUserDashboard.ajaxurl,
                type: 'POST',
                data: formData,
                cache: false,  // Prevent caching
                dataType: 'json',  // Expect JSON response
                success: function(response) {
                    if (rfmUserDashboard.debug) {
                        console.log('RFM User Dashboard: AJAX Success Response:', response);
                    }

                    if (response.success) {
                        $message.html('<div class="rfm-success">' + response.data.message + '</div>');

                        // Scroll to message
                        $('html, body').animate({
                            scrollTop: $message.offset().top - 100
                        }, 300);
                    } else {
                        var errorMsg = (response.data && response.data.message) ? response.data.message : rfmUserDashboard.strings.errorText;
                        $message.html('<div class="rfm-error">' + errorMsg + '</div>');

                        if (rfmUserDashboard.debug && response.data && response.data.debug) {
                            console.error('RFM User Dashboard: Error details:', response.data.debug);
                        }
                    }
                    $button.prop('disabled', false).text(originalText);
                },
                error: function(xhr, status, error) {
                    console.error('RFM User Dashboard: AJAX Error');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Status Code:', xhr.status);
                    console.error('Response Text:', xhr.responseText);

                    var errorMessage = rfmUserDashboard.strings.errorText;

                    // Check for redirect (302) or other HTTP errors
                    if (xhr.status === 302 || xhr.status === 301) {
                        errorMessage = 'Session udløbet eller nonce fejl. Genindlæs siden og prøv igen.';
                        console.error('RFM User Dashboard: 302 Redirect detected - likely nonce failure or session expired');
                    } else if (xhr.status === 403) {
                        errorMessage = 'Sikkerhedstjek fejlede. Genindlæs siden og prøv igen.';
                    } else if (xhr.status === 401) {
                        errorMessage = 'Du er ikke logget ind. Logger ind igen...';
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else if (xhr.status === 0) {
                        errorMessage = 'Netværksfejl eller CORS problem. Tjek din internetforbindelse.';
                    }

                    // Try to parse error response as JSON
                    try {
                        var errorData = JSON.parse(xhr.responseText);
                        if (errorData.data && errorData.data.message) {
                            errorMessage = errorData.data.message;
                        }
                    } catch(e) {
                        // Not JSON - might be HTML redirect
                        if (xhr.responseText.indexOf('<!DOCTYPE') !== -1 || xhr.responseText.indexOf('<html') !== -1) {
                            console.error('RFM User Dashboard: Received HTML instead of JSON - likely a redirect or error page');
                            errorMessage = 'Server returnerede forkert svar format. Tjek konsollen for detaljer.';
                        }
                    }

                    $message.html('<div class="rfm-error">' + errorMessage + '</div>');
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

            // Use nonce from localized data (fresh on every page load)
            var nonce = rfmUserDashboard.nonce || $('[name="rfm_user_nonce"]').val();

            if (rfmUserDashboard.debug) {
                console.log('RFM User Dashboard: Logout request initiated');
            }

            $button.prop('disabled', true).text(rfmUserDashboard.strings.loggingOut);

            $.ajax({
                url: rfmUserDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rfm_user_logout',
                    rfm_user_nonce: nonce
                },
                cache: false,  // Prevent caching
                dataType: 'json',  // Expect JSON response
                success: function(response) {
                    if (rfmUserDashboard.debug) {
                        console.log('RFM User Dashboard: Logout successful', response);
                    }

                    if (response.success && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        // Fallback redirect to home
                        window.location.href = '/';
                    }
                },
                error: function(xhr, status, error) {
                    console.error('RFM User Dashboard: Logout Error');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Status Code:', xhr.status);

                    // Force redirect to home on error (logout anyway for safety)
                    window.location.href = '/';
                }
            });
        });
    });

})(jQuery);
