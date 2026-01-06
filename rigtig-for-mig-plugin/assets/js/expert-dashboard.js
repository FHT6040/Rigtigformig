/**
 * Expert Dashboard JavaScript
 *
 * SIMPLE APPROACH: No client-side validation, checkboxes work naturally,
 * server handles all logic and validation.
 *
 * @package Rigtig_For_Mig
 * @since 3.8.15
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // ========================================
        // AJAX URL CONFIGURATION
        // ========================================
        // Determine AJAX URL with fallback
        var ajaxUrl = '';
        if (typeof rfmDashboard !== 'undefined' && rfmDashboard.ajaxurl) {
            ajaxUrl = rfmDashboard.ajaxurl;
        } else {
            // Hardcoded fallback to ajax-handler.php
            var pluginUrl = window.location.origin + '/wp-content/plugins/rigtig-for-mig-plugin/';
            ajaxUrl = pluginUrl + 'ajax-handler.php';
        }

        console.log('=== RFM EXPERT DASHBOARD INITIALIZED ===');
        console.log('AJAX URL:', ajaxUrl);
        console.log('rfmDashboard object:', typeof rfmDashboard !== 'undefined' ? rfmDashboard : 'NOT DEFINED');

        // ========================================
        // TAB SWITCHING
        // ========================================
        $('.rfm-tab-btn').on('click', function() {
            var tab = $(this).data('tab');
            $('.rfm-tab-btn').removeClass('active');
            $(this).addClass('active');
            $('.rfm-tab-content').removeClass('active');
            $('[data-tab-content="' + tab + '"]').addClass('active');
            $('html, body').animate({
                scrollTop: $('.rfm-dashboard-tabs').offset().top - 50
            }, 300);
        });

        // ========================================
        // GENERAL PROFILE FORM SUBMISSION
        // ========================================
        $('#rfm-general-profile-form').on('submit', function(e) {
            e.preventDefault();
            console.log('=== FORM SUBMIT TRIGGERED ===');

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $message = $('#rfm-tabbed-dashboard-message');
            var formData = $form.serialize() + '&action=rfm_save_general_profile';

            console.log('Form data:', formData);
            console.log('Posting to:', ajaxUrl);

            // Disable button
            $button.prop('disabled', true).text('Gemmer...');
            $message.html('');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    console.log('=== AJAX SUCCESS ===');
                    console.log('Response:', response);

                    if (response.success) {
                        $message.html('<div class="rfm-success">' + response.data.message + '</div>');
                        setTimeout(function() {
                            // Force reload bypassing cache
                            var cacheBuster = '?_=' + new Date().getTime();
                            window.location.href = window.location.pathname + window.location.search + cacheBuster;
                        }, 1000);
                    } else {
                        $message.html('<div class="rfm-error">' + response.data.message + '</div>');
                        $button.prop('disabled', false).text('Gem generelle oplysninger');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('=== AJAX ERROR ===');
                    console.log('Status:', status);
                    console.log('Error:', error);
                    console.log('Response:', xhr.responseText);

                    $message.html('<div class="rfm-error">Der opstod en fejl. Prøv igen.</div>');
                    $button.prop('disabled', false).text('Gem generelle oplysninger');
                }
            });
        });

        // ========================================
        // CATEGORY PROFILE FORM SUBMISSION
        // ========================================
        $('.rfm-category-profile-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var originalText = $button.text();
            var $message = $('#rfm-tabbed-dashboard-message');
            var formData = $form.serialize() + '&action=rfm_save_category_profile';

            console.log('=== CATEGORY FORM SUBMIT ===');
            console.log('Posting to:', ajaxUrl);

            $button.prop('disabled', true).text('Gemmer...');
            $message.html('');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
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
                    $message.html('<div class="rfm-error">Der opstod en fejl. Prøv igen.</div>');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });

        // ========================================
        // CATEGORY SELECTION WITH SMART FEEDBACK
        // ========================================
        // Checkboxes work naturally, but we provide helpful visual feedback
        // when the user exceeds their plan's category limit
        console.log('Category checkboxes: Natural behavior with smart feedback');

        // CRITICAL: Remove any event handlers from other scripts (public.js, etc.)
        // Use setTimeout to ensure this runs AFTER other scripts have initialized
        setTimeout(function() {
            var $categoryContainer = $('#rfm-tabbed-categories');
            if ($categoryContainer.length) {
                var $categoryCheckboxes = $categoryContainer.find('.rfm-category-checkbox');
                var maxCategories = parseInt($categoryContainer.data('max')) || 1;
                var $notice = $('#rfm-category-limit-notice');

                console.log('Initializing category selection...');
                console.log('Found ' + $categoryCheckboxes.length + ' checkboxes, limit: ' + maxCategories);

                // Clean up any interfering event handlers from other scripts
                $categoryCheckboxes.off('change');
                $categoryContainer.off('change', '.rfm-category-checkbox');
                $(document).off('change', '.rfm-category-checkbox');
                $('body').off('change', '.rfm-category-checkbox');

                // Function to update category limit feedback
                function updateCategoryFeedback() {
                    var checkedCount = $categoryCheckboxes.filter(':checked').length;

                    if (checkedCount > maxCategories) {
                        // Show helpful notice when over limit
                        // User can still check/uncheck freely, but they know only first N will be saved
                        $notice.show();
                        console.log('⚠ Category count: ' + checkedCount + '/' + maxCategories + ' (over limit - only first ' + maxCategories + ' will be saved)');
                    } else {
                        // Hide notice when at or under limit
                        $notice.hide();
                        if (checkedCount > 0) {
                            console.log('✓ Category count: ' + checkedCount + '/' + maxCategories);
                        }
                    }
                }

                // Attach our change handler for real-time feedback
                $categoryCheckboxes.on('change', updateCategoryFeedback);

                // Run once on page load to set initial state
                updateCategoryFeedback();

                // GUARD: Prevent other scripts from disabling checkboxes
                setInterval(function() {
                    $categoryCheckboxes.prop('disabled', false);
                }, 500);

                console.log('✓ Category selection feedback initialized');
            }
        }, 100); // Wait 100ms for other scripts to initialize

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
        $('#rfm-logout-btn').on('click', function(e) {
            e.preventDefault();

            var logoutNonce = (typeof rfmDashboard !== 'undefined' && rfmDashboard.logoutNonce)
                ? rfmDashboard.logoutNonce
                : '';

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rfm_expert_logout',
                    nonce: logoutNonce
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
