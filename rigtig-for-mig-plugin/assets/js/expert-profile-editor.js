/**
 * Expert Profile Editor JavaScript
 *
 * Handles profile editing form submission with file uploads,
 * success/error messaging, and page reloading.
 *
 * Features:
 * - Multipart form data handling for file uploads (profile and banner images)
 * - FormData API for secure file transmission
 * - AJAX submission to rfm_update_expert_profile action
 * - User feedback through success/error messages
 * - Automatic page reload after successful profile update
 * - Button state management during submission
 *
 * Part of Phase 2 Refactoring
 *
 * @package Rigtig_For_Mig
 * @since 3.6.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        /**
         * Profile Edit Form Submission
         *
         * Handles the form submit event for the expert profile editor.
         * - Prevents default form submission
         * - Creates FormData object for multipart file uploads
         * - Submits via AJAX with proper handling for binary data
         * - Manages button state and user messaging
         * - Reloads page on success to display updated profile
         */
        $('#rfm-profile-edit-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $message = $('#rfm-profile-edit-message');

            // Button State Management
            // Disable submit button and update text to saving state
            $button.prop('disabled', true).text(rfmProfileEditor.strings.savingText);
            $message.html('');

            // FormData Handling
            // Create FormData object to handle multipart form data with file uploads
            // This is required for proper file transmission via AJAX
            var formData = new FormData(this);
            formData.append('action', 'rfm_update_expert_profile');

            // AJAX Call to Backend Handler
            $.ajax({
                url: rfmData.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,  // Required: prevents jQuery from processing FormData
                contentType: false,  // Required: lets browser set proper Content-Type header

                /**
                 * Success Handler
                 * Processes successful profile update responses
                 */
                success: function(response) {
                    if (response.success) {
                        // Display success message to user
                        $message.html('<div class="rfm-success">' + response.data.message + '</div>');

                        // Reset button to original state
                        $button.prop('disabled', false).text(rfmProfileEditor.strings.submitText);

                        // Scroll to message for visibility
                        $('html, body').animate({
                            scrollTop: $message.offset().top - 100
                        }, 500);

                        // Reload page after 2 seconds to display new images and updated data
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        // Display error message from server
                        $message.html('<div class="rfm-error">' + response.data.message + '</div>');

                        // Re-enable button for retry
                        $button.prop('disabled', false).text(rfmProfileEditor.strings.submitText);
                    }
                },

                /**
                 * Error Handler
                 * Handles AJAX request failures or network errors
                 */
                error: function() {
                    // Display generic error message using localized string
                    $message.html('<div class="rfm-error">' + rfmProfileEditor.strings.errorText + '</div>');

                    // Re-enable button for user to retry
                    $button.prop('disabled', false).text(rfmProfileEditor.strings.submitText);
                }
            });
        });

    });

})(jQuery);
