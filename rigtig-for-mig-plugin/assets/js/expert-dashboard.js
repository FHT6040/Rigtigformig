/**
 * Expert Dashboard JavaScript
 *
 * Handles all dashboard interactions including tab switching, form submissions,
 * limit enforcement, and education management.
 *
 * Part of Phase 2 Refactoring
 *
 * @package Rigtig_For_Mig
 * @since 3.6.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // ========================================
        // TAB SWITCHING
        // ========================================
        // Handles click events on tab buttons to switch between different dashboard views
        $('.rfm-tab-btn').on('click', function() {
            var tab = $(this).data('tab');

            // Update active tab button
            $('.rfm-tab-btn').removeClass('active');
            $(this).addClass('active');

            // Update active tab content
            $('.rfm-tab-content').removeClass('active');
            $('[data-tab-content="' + tab + '"]').addClass('active');

            // Scroll to top of tabs
            $('html, body').animate({
                scrollTop: $('.rfm-dashboard-tabs').offset().top - 50
            }, 300);
        });

        // ========================================
        // GENERAL PROFILE FORM SUBMISSION
        // ========================================
        // Handles form submission for general/basic expert profile information
        $('#rfm-general-profile-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $message = $('#rfm-tabbed-dashboard-message');

            // Disable button and show loading state
            $button.prop('disabled', true).text(rfmDashboard.strings.savingText || 'Gemmer...');
            $message.html('');

            $.ajax({
                url: rfmData.ajaxurl,
                type: 'POST',
                data: $form.serialize() + '&action=rfm_save_general_profile&nonce=' + $form.find('[name="rfm_tabbed_nonce"]').val(),
                success: function(response) {
                    if (response.success) {
                        $message.html('<div class="rfm-success">' + response.data.message + '</div>');
                        // Reload page to update category tabs
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        $message.html('<div class="rfm-error">' + response.data.message + '</div>');
                        $button.prop('disabled', false).text(rfmDashboard.strings.submitGeneralText || 'Gem generelle oplysninger');
                    }
                },
                error: function() {
                    $message.html('<div class="rfm-error">' + (rfmDashboard.strings.errorText || 'Der opstod en fejl. Prøv igen.') + '</div>');
                    $button.prop('disabled', false).text(rfmDashboard.strings.submitGeneralText || 'Gem generelle oplysninger');
                }
            });
        });

        // ========================================
        // CATEGORY PROFILE FORM SUBMISSION
        // ========================================
        // Handles form submission for category-specific profile information
        $('.rfm-category-profile-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var originalText = $button.text();
            var $message = $('#rfm-tabbed-dashboard-message');

            // Disable button and show loading state
            $button.prop('disabled', true).text(rfmDashboard.strings.savingText || 'Gemmer...');
            $message.html('');

            $.ajax({
                url: rfmData.ajaxurl,
                type: 'POST',
                data: $form.serialize() + '&action=rfm_save_category_profile&nonce=' + $form.find('[name="rfm_tabbed_nonce"]').val(),
                success: function(response) {
                    if (response.success) {
                        $message.html('<div class="rfm-success">' + response.data.message + '</div>');
                        $('html, body').animate({
                            scrollTop: $message.offset().top - 100
                        }, 300);
                    } else {
                        $message.html('<div class="rfm-error">' + response.data.message + '</div>');
                    }
                    $button.prop('disabled', false).text(originalText);
                },
                error: function() {
                    $message.html('<div class="rfm-error">' + (rfmDashboard.strings.errorText || 'Der opstod en fejl. Prøv igen.') + '</div>');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });

        // ========================================
        // CATEGORY CHECKBOX LIMIT
        // ========================================
        // Enforces the maximum number of categories that can be selected based on subscription plan
        var $catCheckboxes = $('#rfm-tabbed-categories');
        var maxCats = parseInt($catCheckboxes.data('max')) || 1;

        console.log('RFM Expert Dashboard: Category limit initialized. Max categories:', maxCats);

        function updateCategoryLimit() {
            var $checkboxes = $catCheckboxes.find('.rfm-category-checkbox');
            var checkedCount = $checkboxes.filter(':checked').length;

            console.log('RFM: Updating category limit. Checked:', checkedCount, 'Max:', maxCats);

            if (checkedCount >= maxCats) {
                $checkboxes.not(':checked').prop('disabled', true);
                $('#rfm-category-limit-notice').show();
                console.log('RFM: Category limit reached. Disabling unchecked boxes.');
            } else {
                $checkboxes.prop('disabled', false);
                $('#rfm-category-limit-notice').hide();
                console.log('RFM: Category limit not reached. Enabling all boxes.');
            }
        }

        $catCheckboxes.on('change', '.rfm-category-checkbox', updateCategoryLimit);
        updateCategoryLimit();

        // ========================================
        // SPECIALIZATION LIMITS PER CATEGORY
        // ========================================
        // Enforces the maximum number of specializations per category based on subscription plan
        $('.rfm-specialization-checkboxes').each(function() {
            var $container = $(this);
            var maxSpecs = parseInt($container.data('max')) || 1;

            function updateSpecLimit() {
                var $checkboxes = $container.find('.rfm-spec-checkbox');
                var checkedCount = $checkboxes.filter(':checked').length;

                if (checkedCount >= maxSpecs) {
                    $checkboxes.not(':checked').prop('disabled', true);
                    $container.siblings('.rfm-spec-limit-notice').show();
                } else {
                    $checkboxes.prop('disabled', false);
                    $container.siblings('.rfm-spec-limit-notice').hide();
                }
            }

            $container.on('change', '.rfm-spec-checkbox', updateSpecLimit);
            updateSpecLimit();
        });

        // ========================================
        // ADD EDUCATION FOR CATEGORY
        // ========================================
        // Handles the "Add Education" button to dynamically insert new education fields
        $('.rfm-add-category-education').on('click', function() {
            var categoryId = $(this).data('category-id');
            var $container = $(this).closest('.rfm-category-education-section').find('.rfm-category-educations-container');
            var maxEducations = parseInt($container.data('max')) || 1;
            var currentCount = $container.find('.rfm-category-education-item').length;

            // Check if we've reached the limit
            if (currentCount >= maxEducations) {
                $(this).siblings('.rfm-cat-education-limit-notice').show();
                return;
            }

            // Get template and replace placeholders with actual values
            var template = $('#rfm-category-education-template').html();
            var newIndex = Date.now();

            template = template.replace(/__INDEX__/g, newIndex);
            template = template.replace(/__CATEGORY_ID__/g, categoryId);

            $container.append(template);

            // Check limit again and show notice if needed
            if ($container.find('.rfm-category-education-item').length >= maxEducations) {
                $(this).siblings('.rfm-cat-education-limit-notice').show();
            }
        });

        // ========================================
        // REMOVE EDUCATION
        // ========================================
        // Handles the removal of education items with smooth animation
        $(document).on('click', '.rfm-category-education-remove', function() {
            var $item = $(this).closest('.rfm-category-education-item');
            var $container = $item.closest('.rfm-category-educations-container');

            $item.slideUp(300, function() {
                $(this).remove();
                // Hide limit notice if items are now below limit
                $container.closest('.rfm-category-education-section').find('.rfm-cat-education-limit-notice').hide();
            });
        });

        // ========================================
        // LOGOUT HANDLER
        // ========================================
        // Handles the logout button click and redirects user to login page
        $('#rfm-logout-btn').on('click', function(e) {
            e.preventDefault();

            $.ajax({
                url: rfmData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rfm_expert_logout',
                    nonce: rfmDashboard.logoutNonce
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect;
                    }
                }
            });
        });
    });

})(jQuery);
