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
                            // Force reload bypassing cache with proper URL construction
                            var currentUrl = new URL(window.location.href);
                            currentUrl.searchParams.set('_', new Date().getTime());
                            window.location.href = currentUrl.toString();
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
        // Wait for all other scripts to initialize, then protect checkbox states
        setTimeout(function() {
            var $categoryContainer = $('#rfm-tabbed-categories');
            if ($categoryContainer.length) {
                var $categoryCheckboxes = $categoryContainer.find('.rfm-category-checkbox');
                var maxCategories = parseInt($categoryContainer.data('max')) || 1;
                var $notice = $('#rfm-category-limit-notice');

                console.log('Initializing category selection...');
                console.log('Found ' + $categoryCheckboxes.length + ' checkboxes, limit: ' + maxCategories);

                // SAVE INITIAL CHECKED STATES FROM PHP HTML ATTRIBUTE
                // Use defaultChecked to read the ORIGINAL HTML checked attribute set by PHP
                // This bypasses any JavaScript manipulation that may have occurred
                var initialCheckedStates = {};
                $categoryCheckboxes.each(function() {
                    var $cb = $(this);
                    var id = $cb.val();
                    // Read from HTML attribute, not JavaScript state
                    initialCheckedStates[id] = $cb.prop('defaultChecked');

                    // IMMEDIATELY restore if JavaScript state differs from HTML
                    if ($cb.prop('defaultChecked') !== $cb.prop('checked')) {
                        console.log('⚡ IMMEDIATELY RESTORING checkbox ' + id + ' to match HTML attribute');
                        $cb.prop('checked', $cb.prop('defaultChecked'));
                    }
                });

                // DEBUG: Log initial checkbox states as rendered by PHP
                console.log('=== INITIAL CHECKBOX STATES (from PHP) ===');
                $categoryCheckboxes.each(function() {
                    var $cb = $(this);
                    var id = $cb.val();
                    var name = $cb.siblings('span').text();
                    var isChecked = $cb.is(':checked');
                    var hasCheckedAttr = typeof $cb.attr('checked') !== 'undefined';
                    console.log('Category ' + id + ' (' + name + '): checked=' + isChecked + ', has checked attr=' + hasCheckedAttr);
                });

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
                $categoryCheckboxes.on('change', function() {
                    // Update our saved state when user makes a change
                    var id = $(this).val();
                    initialCheckedStates[id] = $(this).is(':checked');
                    updateCategoryFeedback();
                });

                // Run once on page load to set initial state
                updateCategoryFeedback();

                // GUARD: Protect checkboxes from interference by other scripts
                // This will restore the correct checked state if something tries to uncheck them
                setInterval(function() {
                    $categoryCheckboxes.each(function() {
                        var $cb = $(this);
                        var id = $cb.val();
                        var shouldBeChecked = initialCheckedStates[id];
                        var currentlyChecked = $cb.is(':checked');

                        // Re-enable if disabled
                        if ($cb.prop('disabled')) {
                            $cb.prop('disabled', false);
                        }

                        // RESTORE checked state if it was changed by external script
                        if (shouldBeChecked !== currentlyChecked) {
                            console.log('⚡ RESTORING checkbox ' + id + ' to ' + (shouldBeChecked ? 'CHECKED' : 'UNCHECKED'));
                            $cb.prop('checked', shouldBeChecked);
                        }
                    });
                }, 500);

                console.log('✓ Category selection feedback initialized');
                console.log('✓ Checkbox state protection ACTIVE');

                // DEBUG: Log states again after 2 seconds to see if protection worked
                setTimeout(function() {
                    console.log('=== CHECKBOX STATES AFTER 2 SECONDS ===');
                    $categoryCheckboxes.each(function() {
                        var $cb = $(this);
                        var id = $cb.val();
                        var name = $cb.siblings('span').text();
                        var isChecked = $cb.is(':checked');
                        var isDisabled = $cb.prop('disabled');
                        console.log('Category ' + id + ' (' + name + '): checked=' + isChecked + ', disabled=' + isDisabled);
                    });
                }, 2000);
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
        // MESSAGE SYSTEM HANDLERS
        // ========================================

        // Load conversations when Messages tab is clicked
        $(document).on('click', '.rfm-tab-btn[data-tab="messages"]', function() {
            loadExpertConversations();
        });

        function loadExpertConversations() {
            var $loadingDiv = $('.rfm-messages-loading');
            var $listDiv = $('#rfm-expert-conversations-list');
            var $noMsgDiv = $('#rfm-expert-no-messages');

            $loadingDiv.show();
            $listDiv.hide();
            $noMsgDiv.hide();

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rfm_get_conversations',
                    nonce: (typeof rfmDashboard !== 'undefined' && rfmDashboard.nonce) ? rfmDashboard.nonce : '',
                    type: 'expert'
                },
                success: function(response) {
                    $loadingDiv.hide();

                    if (response.success && response.data.conversations && response.data.conversations.length > 0) {
                        displayExpertConversations(response.data.conversations);
                        $listDiv.show();
                    } else {
                        $noMsgDiv.show();
                    }

                    // Update unread count badge
                    if (response.data.unread_count > 0) {
                        $('#rfm-expert-unread-count').text(response.data.unread_count).show();
                    } else {
                        $('#rfm-expert-unread-count').hide();
                    }
                },
                error: function() {
                    $loadingDiv.hide();
                    $noMsgDiv.html('<p style="color: #e74c3c;">Der opstod en fejl. Prøv igen senere.</p>').show();
                }
            });
        }

        function displayExpertConversations(conversations) {
            var $list = $('#rfm-expert-conversations-list');
            $list.empty();

            conversations.forEach(function(conv) {
                var unreadBadge = conv.unread_count > 0
                    ? '<span class="rfm-unread-badge">' + conv.unread_count + '</span>'
                    : '';

                var lastMessagePreview = conv.last_message
                    ? '<p class="rfm-conv-preview">' + conv.last_message.substring(0, 80) + (conv.last_message.length > 80 ? '...' : '') + '</p>'
                    : '';

                var $convItem = $('<div class="rfm-conversation-item" data-expert-id="' + conv.expert_id + '" data-user-id="' + conv.user_id + '">' +
                    '<div class="rfm-conv-header">' +
                        '<strong>' + conv.user_name + '</strong> - <em>' + conv.expert_name + '</em>' +
                        unreadBadge +
                    '</div>' +
                    lastMessagePreview +
                    '<span class="rfm-conv-date">' + conv.last_message_at + '</span>' +
                '</div>');

                $list.append($convItem);
            });
        }

        // Open conversation thread when conversation item is clicked
        $(document).on('click', '.rfm-conversation-item', function() {
            var expertId = $(this).data('expert-id');
            var userId = $(this).data('user-id');
            openExpertConversationThread(expertId, userId);
        });

        function openExpertConversationThread(expertId, userId) {
            // Create modal if it doesn't exist
            if ($('#rfm-expert-thread-modal').length === 0) {
                $('body').append(
                    '<div id="rfm-expert-thread-modal" class="rfm-modal">' +
                        '<div class="rfm-modal-content">' +
                            '<span class="rfm-modal-close">&times;</span>' +
                            '<div class="rfm-thread-messages" id="rfm-expert-thread-messages"></div>' +
                            '<form id="rfm-expert-reply-form" style="margin-top: 20px;">' +
                                '<textarea id="rfm-expert-reply-text" rows="4" placeholder="Skriv dit svar..." required style="width: 100%; padding: 10px;"></textarea>' +
                                '<button type="submit" class="rfm-btn rfm-btn-primary" style="margin-top: 10px;">Send svar</button>' +
                            '</form>' +
                        '</div>' +
                    '</div>'
                );
            }

            var $modal = $('#rfm-expert-thread-modal');
            var $messages = $('#rfm-expert-thread-messages');

            $messages.html('<p style="text-align: center; padding: 20px;">Indlæser samtale...</p>');
            $modal.show();

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rfm_get_conversation',
                    nonce: (typeof rfmDashboard !== 'undefined' && rfmDashboard.nonce) ? rfmDashboard.nonce : '',
                    expert_id: expertId,
                    user_id: userId
                },
                success: function(response) {
                    if (response.success && response.data.messages) {
                        displayExpertThread(response.data.messages, expertId, userId);
                    } else {
                        $messages.html('<p style="color: #e74c3c;">Kunne ikke indlæse beskeder.</p>');
                    }
                }
            });
        }

        function displayExpertThread(messages, expertId, userId) {
            var $messages = $('#rfm-expert-thread-messages');
            $messages.empty();

            messages.forEach(function(msg) {
                var isSent = msg.sender_id != userId; // Expert sent it
                var msgClass = isSent ? 'rfm-msg-sent' : 'rfm-msg-received';

                var $msg = $('<div class="rfm-message ' + msgClass + '">' +
                    '<div class="rfm-msg-sender"><strong>' + msg.sender_name + '</strong></div>' +
                    '<div class="rfm-msg-content">' + msg.message + '</div>' +
                    '<div class="rfm-msg-date">' + msg.created_at + '</div>' +
                '</div>');

                $messages.append($msg);
            });

            // Scroll to bottom
            $messages.scrollTop($messages[0].scrollHeight);

            // Store expertId and userId for reply
            $('#rfm-expert-reply-form').data('expert-id', expertId).data('user-id', userId);
        }

        // Close expert thread modal
        $(document).on('click', '#rfm-expert-thread-modal .rfm-modal-close, #rfm-expert-thread-modal', function(e) {
            if (e.target === this) {
                $('#rfm-expert-thread-modal').hide();
            }
        });

        // Send expert reply
        $(document).on('submit', '#rfm-expert-reply-form', function(e) {
            e.preventDefault();

            var $form = $(this);
            var expertId = $form.data('expert-id');
            var userId = $form.data('user-id');
            var message = $('#rfm-expert-reply-text').val().trim();

            if (!message) return;

            var $btn = $form.find('button[type="submit"]');
            $btn.prop('disabled', true).text('Sender...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rfm_send_message',
                    nonce: (typeof rfmDashboard !== 'undefined' && rfmDashboard.nonce) ? rfmDashboard.nonce : '',
                    expert_id: expertId,
                    recipient_id: userId,
                    subject: 'Svar',
                    message: message
                },
                success: function(response) {
                    if (response.success) {
                        $('#rfm-expert-reply-text').val('');
                        // Reload conversation
                        openExpertConversationThread(expertId, userId);
                        // Reload conversations list
                        loadExpertConversations();
                    } else {
                        alert('Fejl: ' + (response.data || 'Kunne ikke sende besked'));
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Send svar');
                }
            });
        });

        // Load unread count on page load
        if ($('.rfm-tab-btn[data-tab="messages"]').length > 0) {
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rfm_get_conversations',
                    nonce: (typeof rfmDashboard !== 'undefined' && rfmDashboard.nonce) ? rfmDashboard.nonce : '',
                    type: 'expert'
                },
                success: function(response) {
                    if (response.success && response.data.unread_count > 0) {
                        $('#rfm-expert-unread-count').text(response.data.unread_count).show();
                    }
                }
            });
        }

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
