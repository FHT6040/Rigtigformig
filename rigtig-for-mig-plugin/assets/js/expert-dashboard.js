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
        // AVATAR IMAGE UPLOAD
        // ========================================
        $('#rfm-avatar-upload').on('change', function(e) {
            var file = e.target.files[0];
            if (!file) return;

            var expertId = $('#rfm-general-profile-form').find('input[name="expert_id"]').val();
            var nonce = $('#rfm-general-profile-form').find('input[name="nonce"]').val();

            // Validate file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('Filen er for stor. Maksimum 5MB.');
                $(this).val('');
                return;
            }

            // Validate file type
            var validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                alert('Ugyldig filtype. Kun JPG, PNG, GIF og WebP er tilladt.');
                $(this).val('');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'rfm_upload_expert_avatar');
            formData.append('avatar_image', file);
            formData.append('expert_id', expertId);
            formData.append('nonce', nonce);

            // Show loading state
            var $preview = $('#rfm-avatar-preview');
            var originalSrc = $preview.attr('src');
            $preview.css('opacity', '0.5');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Update preview image
                        $preview.attr('src', response.data.image_url + '?t=' + new Date().getTime());
                        $preview.css('opacity', '1');
                        alert('✅ ' + response.data.message);
                    } else {
                        alert('❌ ' + response.data.message);
                        $preview.css('opacity', '1');
                    }
                },
                error: function() {
                    alert('Der opstod en fejl ved upload. Prøv igen.');
                    $preview.attr('src', originalSrc);
                    $preview.css('opacity', '1');
                }
            });

            // Clear file input
            $(this).val('');
        });

        // ========================================
        // BANNER IMAGE UPLOAD
        // ========================================
        $('#rfm-banner-upload').on('change', function(e) {
            var file = e.target.files[0];
            if (!file) return;

            var expertId = $('#rfm-general-profile-form').find('input[name="expert_id"]').val();
            var nonce = $('#rfm-general-profile-form').find('input[name="nonce"]').val();

            // Validate file size (10MB for banners)
            if (file.size > 10 * 1024 * 1024) {
                alert('Filen er for stor. Maksimum 10MB.');
                $(this).val('');
                return;
            }

            // Validate file type
            var validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                alert('Ugyldig filtype. Kun JPG, PNG, GIF og WebP er tilladt.');
                $(this).val('');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'rfm_upload_expert_banner');
            formData.append('banner_image', file);
            formData.append('expert_id', expertId);
            formData.append('nonce', nonce);

            // Show loading state
            var $preview = $('#rfm-banner-preview');
            if ($preview.length === 0) {
                // Create preview element if it doesn't exist
                $('#rfm-banner-upload').after('<img id="rfm-banner-preview" style="max-width: 100%; margin-top: 10px; display: block;" />');
                $preview = $('#rfm-banner-preview');
            }
            $preview.css('opacity', '0.5');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Update/show preview image
                        $preview.attr('src', response.data.image_url + '?t=' + new Date().getTime());
                        $preview.css('opacity', '1').show();
                        alert('✅ ' + response.data.message);
                    } else {
                        alert('❌ ' + response.data.message);
                        $preview.css('opacity', '1');
                    }
                },
                error: function() {
                    alert('Der opstod en fejl ved upload. Prøv igen.');
                    $preview.css('opacity', '1');
                }
            });

            // Clear file input
            $(this).val('');
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

                    // Update unread count badge and show/hide mark all as read button
                    if (response.data.unread_count > 0) {
                        $('#rfm-expert-unread-count').text(response.data.unread_count).show();
                        $('#rfm-expert-mark-all-read-btn').show();
                    } else {
                        $('#rfm-expert-unread-count').hide();
                        $('#rfm-expert-mark-all-read-btn').hide();
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

                var messageCount = conv.message_count > 1
                    ? '<span class="rfm-message-count">(' + conv.message_count + ' beskeder)</span>'
                    : '<span class="rfm-message-count">(1 besked)</span>';

                // Build collapsed message list
                var messagesHTML = '';
                if (conv.messages && conv.messages.length > 0) {
                    messagesHTML = '<div class="rfm-conv-messages-collapsed">';
                    conv.messages.forEach(function(msg, index) {
                        var msgPreview = msg.message.length > 60 ? msg.message.substring(0, 60) + '...' : msg.message;
                        var msgClass = index === conv.messages.length - 1 ? 'rfm-msg-preview-last' : 'rfm-msg-preview';
                        messagesHTML += '<div class="' + msgClass + '">' +
                            '↳ ' + msgPreview + ' <span class="rfm-msg-date-inline">(' + msg.created_at + ')</span>' +
                        '</div>';
                    });
                    messagesHTML += '</div>';
                }

                var $convItem = $('<div class="rfm-conversation-item" data-expert-id="' + conv.expert_id + '" data-user-id="' + conv.user_id + '">' +
                    '<div class="rfm-conv-header">' +
                        '<strong>📧 ' + conv.user_name + '</strong> - <em>' + conv.expert_name + '</em>' +
                        unreadBadge +
                        ' ' + messageCount +
                    '</div>' +
                    messagesHTML +
                    '<button class="rfm-btn rfm-btn-small rfm-view-thread-btn-expert" data-expert-id="' + conv.expert_id + '" data-user-id="' + conv.user_id + '">' +
                        'Klik for at se hele samtalen' +
                    '</button>' +
                '</div>');

                $list.append($convItem);
            });
        }

        // Open conversation thread when button is clicked
        $(document).on('click', '.rfm-view-thread-btn-expert', function(e) {
            e.preventDefault();
            e.stopPropagation();
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
                        // Refresh conversations to update unread badges
                        loadExpertConversations();
                        // Update unread count in header
                        updateUnreadCount();
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

        // Update unread count badge
        function updateUnreadCount() {
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rfm_get_unread_count',
                    nonce: (typeof rfmDashboard !== 'undefined' && rfmDashboard.nonce) ? rfmDashboard.nonce : ''
                },
                success: function(response) {
                    if (response.success) {
                        var count = response.data.count;
                        if (count > 0) {
                            $('#rfm-expert-unread-count').text(count).show();
                            $('#rfm-expert-mark-all-read-btn').show();
                        } else {
                            $('#rfm-expert-unread-count').hide();
                            $('#rfm-expert-mark-all-read-btn').hide();
                        }
                    }
                }
            });
        }

        // Mark all messages as read button handler
        $(document).on('click', '#rfm-expert-mark-all-read-btn', function(e) {
            e.preventDefault();

            var $btn = $(this);
            $btn.prop('disabled', true).text('Markerer...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rfm_mark_all_messages_read',
                    nonce: (typeof rfmDashboard !== 'undefined' && rfmDashboard.nonce) ? rfmDashboard.nonce : ''
                },
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        $('#rfm-expert-unread-count').hide();
                        $btn.hide();
                        // Refresh conversation list to remove unread badges
                        loadExpertConversations();
                    } else {
                        alert(response.data.message || 'Der opstod en fejl');
                        $btn.prop('disabled', false).text('Marker alle som læst');
                    }
                },
                error: function() {
                    alert('Der opstod en fejl. Prøv igen senere.');
                    $btn.prop('disabled', false).text('Marker alle som læst');
                }
            });
        });

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
        // BOOKING SETTINGS HANDLER (v3.9.8)
        // ========================================
        $('#rfm-save-booking-settings').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $message = $('#rfm-booking-message');
            var expertId = $('input[name="expert_id"]').val();
            var nonce = $('input[name="rfm_tabbed_nonce"]').val();

            // Get booking form values
            var bookingEnabled = $('#rfm-booking-enabled').is(':checked') ? '1' : '0';
            var bookingUrl = $('#rfm-booking-url').val();
            var bookingButtonText = $('#rfm-booking-button-text').val();

            // Disable button
            $button.prop('disabled', true).html('<i class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></i> Gemmer...');
            $message.hide().removeClass('success error');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rfm_save_booking_settings',
                    nonce: nonce,
                    expert_id: expertId,
                    booking_enabled: bookingEnabled,
                    booking_url: bookingUrl,
                    booking_button_text: bookingButtonText
                },
                success: function(response) {
                    console.log('Booking settings response:', response);

                    if (response.success) {
                        $message.html(response.data.message).addClass('success').show();
                    } else {
                        $message.html(response.data.message || 'Der opstod en fejl.').addClass('error').show();
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Booking settings error:', error);
                    $message.html('Der opstod en fejl. Prøv igen.').addClass('error').show();
                },
                complete: function() {
                    $button.prop('disabled', false).html('<i class="dashicons dashicons-yes"></i> Gem booking-indstillinger');
                }
            });
        });

        // ========================================
        // INTERNAL BOOKING SYSTEM (v3.10.0)
        // ========================================

        // Booking mode selector
        $('input[name="booking_mode"]').on('change', function() {
            var mode = $(this).val();
            $('.rfm-radio-card').removeClass('active');
            $(this).closest('.rfm-radio-card').addClass('active');

            if (mode === 'internal') {
                $('#rfm-booking-external-panel').hide();
                $('#rfm-booking-internal-panel').show();
            } else {
                $('#rfm-booking-external-panel').show();
                $('#rfm-booking-internal-panel').hide();
            }
        });

        // Availability day toggles
        $(document).on('change', '.rfm-day-toggle', function() {
            var $day = $(this).closest('.rfm-availability-day');
            var $slots = $day.find('.rfm-availability-slots');
            if ($(this).is(':checked')) {
                $slots.slideDown(200);
            } else {
                $slots.slideUp(200);
            }
        });

        // Add time slot
        $(document).on('click', '.rfm-add-time-slot', function() {
            var day = $(this).data('day');
            var $container = $(this).closest('.rfm-availability-slots');
            var template = '<div class="rfm-time-slot-row">' +
                '<select class="rfm-time-start" name="availability[' + day + '][start][]">';

            // Generate time options
            for (var h = 6; h <= 22; h++) {
                for (var m = 0; m < 60; m += 30) {
                    var time = ('0' + h).slice(-2) + ':' + ('0' + m).slice(-2);
                    var sel = time === '09:00' ? ' selected' : '';
                    template += '<option value="' + time + '"' + sel + '>' + time + '</option>';
                }
            }
            template += '</select><span class="rfm-time-sep">&mdash;</span>' +
                '<select class="rfm-time-end" name="availability[' + day + '][end][]">';
            for (var h2 = 6; h2 <= 22; h2++) {
                for (var m2 = 0; m2 < 60; m2 += 30) {
                    var time2 = ('0' + h2).slice(-2) + ':' + ('0' + m2).slice(-2);
                    var sel2 = time2 === '17:00' ? ' selected' : '';
                    template += '<option value="' + time2 + '"' + sel2 + '>' + time2 + '</option>';
                }
            }
            template += '</select>' +
                '<button type="button" class="rfm-btn rfm-btn-small rfm-btn-danger rfm-remove-time-slot" title="Fjern">' +
                '<i class="dashicons dashicons-no"></i></button></div>';

            $(this).before(template);
        });

        // Remove time slot
        $(document).on('click', '.rfm-remove-time-slot', function() {
            $(this).closest('.rfm-time-slot-row').remove();
        });

        // Save internal booking settings
        $('#rfm-save-internal-booking').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $message = $('#rfm-internal-booking-message');
            var expertId = $('input[name="expert_id"]').val();
            var nonce = $('input[name="rfm_tabbed_nonce"]').val();

            // Collect availability data
            var availability = [];
            $('.rfm-availability-day').each(function() {
                var day = $(this).data('day');
                var isActive = $(this).find('.rfm-day-toggle').is(':checked');

                if (isActive) {
                    $(this).find('.rfm-time-slot-row').each(function() {
                        var start = $(this).find('.rfm-time-start').val();
                        var end = $(this).find('.rfm-time-end').val();
                        if (start && end) {
                            availability.push({
                                day_of_week: day,
                                start_time: start,
                                end_time: end,
                                is_active: 1
                            });
                        }
                    });
                }
            });

            var mode = $('input[name="booking_mode"]:checked').val() || 'external';
            var duration = $('#rfm-booking-duration').val() || 60;

            $button.prop('disabled', true).html('<i class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></i> Gemmer...');
            $message.hide();

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rfm_save_internal_booking_settings',
                    nonce: nonce,
                    expert_id: expertId,
                    booking_mode: mode,
                    booking_duration: duration,
                    availability: availability
                },
                success: function(response) {
                    if (response.success) {
                        $message.html('<div style="color: #4CAF50; padding: 10px; background: #e8f5e9; border-radius: 5px; margin-top: 10px;">' + response.data.message + '</div>').show();
                    } else {
                        $message.html('<div style="color: #e74c3c; padding: 10px; background: #fbe9e7; border-radius: 5px; margin-top: 10px;">' + (response.data.message || 'Der opstod en fejl.') + '</div>').show();
                    }
                },
                error: function() {
                    $message.html('<div style="color: #e74c3c; padding: 10px; background: #fbe9e7; border-radius: 5px; margin-top: 10px;">Netværksfejl. Prøv igen.</div>').show();
                },
                complete: function() {
                    $button.prop('disabled', false).html('<i class="dashicons dashicons-yes"></i> Gem booking-indstillinger');
                }
            });
        });

        // Accept booking
        $(document).on('click', '.rfm-btn-confirm-booking', function() {
            var bookingId = $(this).data('id');
            var $card = $(this).closest('.rfm-booking-card');
            var nonce = $('input[name="rfm_tabbed_nonce"]').val();

            $(this).prop('disabled', true).html('<i class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></i>');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rfm_update_booking_status',
                    nonce: nonce,
                    booking_id: bookingId,
                    status: 'confirmed'
                },
                success: function(response) {
                    if (response.success) {
                        $card.find('.rfm-booking-status').removeClass('rfm-status-pending').addClass('rfm-status-confirmed').text('Bekræftet');
                        $card.find('.rfm-booking-card-actions').html('<span style="color: #4CAF50; font-weight: 600;">Bekræftet</span>');
                        $card.removeClass('rfm-booking-pending').addClass('rfm-booking-confirmed');
                    } else {
                        alert(response.data.message || 'Fejl');
                    }
                }
            });
        });

        // Cancel/reject booking
        $(document).on('click', '.rfm-btn-cancel-booking', function() {
            if (!confirm('Er du sikker på du vil afvise denne booking?')) return;

            var bookingId = $(this).data('id');
            var $card = $(this).closest('.rfm-booking-card');
            var nonce = $('input[name="rfm_tabbed_nonce"]').val();

            $(this).prop('disabled', true).html('<i class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></i>');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rfm_update_booking_status',
                    nonce: nonce,
                    booking_id: bookingId,
                    status: 'cancelled'
                },
                success: function(response) {
                    if (response.success) {
                        $card.fadeOut(300, function() { $(this).remove(); });
                    } else {
                        alert(response.data.message || 'Fejl');
                    }
                }
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
