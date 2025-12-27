/**
 * User Dashboard JavaScript
 *
 * Handles all user dashboard interactions including profile management,
 * avatar upload, password changes, GDPR data download, and account deletion.
 *
 * Follows the same pattern as Expert Dashboard:
 * - Uses global rfmData for ajaxurl
 * - Uses specific rfmUserDashboard for nonce, homeUrl, and strings
 *
 * @package Rigtig_For_Mig
 * @since 3.6.4
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
            var $messages = $form.find('.rfm-form-messages');
            var originalText = $button.text();

            var formData = {
                action: 'rfm_update_user_profile',
                nonce: rfmUserDashboard.nonce,
                display_name: $('#user_display_name').val(),
                phone: $('#user_phone').val(),
                bio: $('#user_bio').val()
            };

            $button.prop('disabled', true).text(rfmUserDashboard.strings.saving);
            $messages.html('');

            $.ajax({
                url: rfmData.ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $messages.html('<div class="rfm-message rfm-message-success">' + response.data.message + '</div>');
                    } else {
                        $messages.html('<div class="rfm-message rfm-message-error">' + (response.data.message || rfmUserDashboard.strings.error) + '</div>');
                    }
                    $button.prop('disabled', false).text(originalText);
                },
                error: function(xhr, status, error) {
                    $messages.html('<div class="rfm-message rfm-message-error">' + rfmUserDashboard.strings.error + '</div>');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });

        // ========================================
        // AVATAR UPLOAD
        // ========================================
        $('#user_avatar_upload').on('change', function(e) {
            var file = e.target.files[0];
            if (!file) return;

            // Client-side validation
            var maxSize = 2 * 1024 * 1024; // 2MB
            if (file.size > maxSize) {
                $('.rfm-form-messages').html('<div class="rfm-message rfm-message-error">Billedet må maksimalt være 2 MB</div>');
                return;
            }

            var allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                $('.rfm-form-messages').html('<div class="rfm-message rfm-message-error">Kun JPG, PNG og GIF er tilladt</div>');
                return;
            }

            // Preview image
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#user-avatar-preview').attr('src', e.target.result);
            };
            reader.readAsDataURL(file);

            // Upload to server
            var formData = new FormData();
            formData.append('action', 'rfm_upload_user_avatar');
            formData.append('nonce', rfmUserDashboard.nonce);
            formData.append('avatar', file);

            $.ajax({
                url: rfmData.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $('.rfm-form-messages').html('<div class="rfm-message rfm-message-success">' + response.data.message + '</div>');
                    } else {
                        $('.rfm-form-messages').html('<div class="rfm-message rfm-message-error">' + response.data.message + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('.rfm-form-messages').html('<div class="rfm-message rfm-message-error">' + rfmUserDashboard.strings.error + '</div>');
                }
            });
        });

        // ========================================
        // PASSWORD CHANGE FORM
        // ========================================
        $('#rfm-password-change-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $messages = $form.find('.rfm-password-messages');
            var originalText = $button.text();

            var current_password = $('#current_password').val();
            var new_password = $('#new_password').val();
            var confirm_password = $('#confirm_password').val();

            // Client-side validation
            if (!current_password || !new_password || !confirm_password) {
                $messages.html('<div class="rfm-message rfm-message-error">' + rfmUserDashboard.strings.fillAllFields + '</div>');
                return;
            }

            if (new_password !== confirm_password) {
                $messages.html('<div class="rfm-message rfm-message-error">' + rfmUserDashboard.strings.passwordMismatch + '</div>');
                return;
            }

            if (new_password.length < 8) {
                $messages.html('<div class="rfm-message rfm-message-error">' + rfmUserDashboard.strings.passwordTooShort + '</div>');
                return;
            }

            $button.prop('disabled', true).text(rfmUserDashboard.strings.changingPassword);
            $messages.html('');

            $.ajax({
                url: rfmData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rfm_update_user_profile',
                    nonce: rfmUserDashboard.nonce,
                    current_password: current_password,
                    new_password: new_password
                },
                success: function(response) {
                    if (response.success) {
                        $messages.html('<div class="rfm-message rfm-message-success">' + response.data.message + '</div>');
                        $form[0].reset();
                    } else {
                        $messages.html('<div class="rfm-message rfm-message-error">' + response.data.message + '</div>');
                    }
                    $button.prop('disabled', false).text(originalText);
                },
                error: function(xhr, status, error) {
                    $messages.html('<div class="rfm-message rfm-message-error">' + rfmUserDashboard.strings.error + '</div>');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });

        // ========================================
        // LOGOUT BUTTON
        // ========================================
        $('#rfm-logout-btn').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            $button.prop('disabled', true).text(rfmUserDashboard.strings.loggingOut);

            $.ajax({
                url: rfmData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rfm_logout',
                    nonce: rfmUserDashboard.nonce
                },
                cache: false,
                success: function(response) {
                    // Clear service worker caches if available
                    if ('caches' in window) {
                        caches.keys().then(function(names) {
                            for (let name of names) {
                                caches.delete(name);
                            }
                        });
                    }

                    // Redirect to home or login page
                    var redirectUrl = response.data && response.data.redirect
                        ? response.data.redirect
                        : rfmUserDashboard.homeUrl;

                    window.location.replace(redirectUrl);
                },
                error: function(xhr, status, error) {
                    // Force redirect to home on error
                    window.location.replace(rfmUserDashboard.homeUrl);
                }
            });
        });

        // ========================================
        // DOWNLOAD USER DATA (GDPR)
        // ========================================
        $('#rfm-download-data').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var originalText = $button.text();

            $button.prop('disabled', true).text(rfmUserDashboard.strings.downloading);

            $.ajax({
                url: rfmData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rfm_update_user_profile',
                    nonce: rfmUserDashboard.nonce,
                    download_data: true
                },
                success: function(response) {
                    if (response.success) {
                        // Create download link
                        var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(response.data.user_data, null, 2));
                        var downloadAnchorNode = document.createElement('a');
                        downloadAnchorNode.setAttribute("href", dataStr);
                        downloadAnchorNode.setAttribute("download", "mine-data-" + new Date().toISOString().split('T')[0] + ".json");
                        document.body.appendChild(downloadAnchorNode);
                        downloadAnchorNode.click();
                        downloadAnchorNode.remove();

                        $('.rfm-gdpr-info').prepend('<div class="rfm-message rfm-message-success">' + rfmUserDashboard.strings.dataDownloaded + '</div>');
                        setTimeout(function() {
                            $('.rfm-message-success').fadeOut();
                        }, 3000);
                    } else {
                        $('.rfm-gdpr-info').prepend('<div class="rfm-message rfm-message-error">' + response.data.message + '</div>');
                    }
                    $button.prop('disabled', false).text(originalText);
                },
                error: function(xhr, status, error) {
                    $('.rfm-gdpr-info').prepend('<div class="rfm-message rfm-message-error">' + rfmUserDashboard.strings.error + '</div>');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });

        // ========================================
        // DELETE ACCOUNT MODAL
        // ========================================
        $('#rfm-delete-account').on('click', function() {
            $('#rfm-delete-modal').fadeIn();
        });

        $('#rfm-cancel-delete').on('click', function() {
            $('#rfm-delete-modal').fadeOut();
            $('#delete_confirm_password').val('');
            $('.rfm-modal-messages').html('');
        });

        $('#rfm-confirm-delete').on('click', function() {
            var password = $('#delete_confirm_password').val();

            if (!password) {
                $('.rfm-modal-messages').html('<div class="rfm-message rfm-message-error">' + rfmUserDashboard.strings.enterPassword + '</div>');
                return;
            }

            if (!confirm(rfmUserDashboard.strings.finalWarning)) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true).text(rfmUserDashboard.strings.deleting);

            $.ajax({
                url: rfmData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rfm_delete_user_account',
                    nonce: rfmUserDashboard.nonce,
                    password: password
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        window.location.href = rfmUserDashboard.homeUrl;
                    } else {
                        $('.rfm-modal-messages').html('<div class="rfm-message rfm-message-error">' + response.data.message + '</div>');
                        $button.prop('disabled', false).text(rfmUserDashboard.strings.confirmDelete);
                    }
                },
                error: function(xhr, status, error) {
                    $('.rfm-modal-messages').html('<div class="rfm-message rfm-message-error">' + rfmUserDashboard.strings.error + '</div>');
                    $button.prop('disabled', false).text(rfmUserDashboard.strings.confirmDelete);
                }
            });
        });
    });

})(jQuery);
