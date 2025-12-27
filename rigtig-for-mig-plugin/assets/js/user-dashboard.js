/**
 * Rigtig for mig - User Dashboard JavaScript
 *
 * @package Rigtig_For_Mig
 * @since 3.4.1
 *
 * FIX v3.6.2: Changed to use global rfmData object (from rfm-public script)
 * instead of rfmUserDashboard to match Expert Dashboard pattern and fix caching issues
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Profile form handler
        $('#rfm-user-profile-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $messages = $form.find('.rfm-form-messages');
            var originalText = $button.text();

            var formData = {
                action: 'rfm_update_user_profile',
                nonce: rfmData.nonce,  // Changed from rfmUserDashboard
                display_name: $('#user_display_name').val(),
                phone: $('#user_phone').val(),
                bio: $('#user_bio').val()
            };

            $button.prop('disabled', true).text('Gemmer...');  // Hardcoded instead of rfmUserDashboard.strings

            $.ajax({
                url: rfmData.ajaxurl,  // Changed from rfmUserDashboard
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $messages.html('<div class="rfm-message rfm-message-success">' + response.data.message + '</div>');
                    } else {
                        $messages.html('<div class="rfm-message rfm-message-error">' + (response.data.message || 'Der opstod en fejl') + '</div>');
                    }
                    $button.prop('disabled', false).text(originalText);
                },
                error: function(xhr, status, error) {
                    $messages.html('<div class="rfm-message rfm-message-error">Der opstod en fejl: ' + error + '</div>');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });

        // Avatar upload
        $('#user_avatar_upload').on('change', function(e) {
            var file = e.target.files[0];
            if (!file) return;

            // Preview
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#user-avatar-preview').attr('src', e.target.result);
            };
            reader.readAsDataURL(file);

            // Upload
            var formData = new FormData();
            formData.append('action', 'rfm_upload_user_avatar');
            formData.append('nonce', rfmData.nonce);  // Changed from rfmUserDashboard
            formData.append('avatar', file);

            $.ajax({
                url: rfmData.ajaxurl,  // Changed from rfmUserDashboard
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
                    $('.rfm-form-messages').html('<div class="rfm-message rfm-message-error">Der opstod en fejl</div>');
                }
            });
        });

        // Password change form handler
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
                $messages.html('<div class="rfm-message rfm-message-error">Udfyld alle felter</div>');
                return;
            }

            if (new_password !== confirm_password) {
                $messages.html('<div class="rfm-message rfm-message-error">De nye adgangskoder matcher ikke</div>');
                return;
            }

            if (new_password.length < 8) {
                $messages.html('<div class="rfm-message rfm-message-error">Ny adgangskode skal være mindst 8 tegn</div>');
                return;
            }

            $button.prop('disabled', true).text('Skifter adgangskode...');

            $.ajax({
                url: rfmData.ajaxurl,  // Changed from rfmUserDashboard
                type: 'POST',
                data: {
                    action: 'rfm_update_user_profile',
                    nonce: rfmData.nonce,  // Changed from rfmUserDashboard
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
                    $messages.html('<div class="rfm-message rfm-message-error">Der opstod en fejl</div>');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });

        // Logout button
        $('#rfm-logout-btn').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            $button.prop('disabled', true).text('Logger ud...');

            $.ajax({
                url: rfmData.ajaxurl,  // Changed from rfmUserDashboard
                type: 'POST',
                data: {
                    action: 'rfm_logout',
                    nonce: rfmData.nonce  // Changed from rfmUserDashboard
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

                    // Force hard reload without cache
                    if (response.data && response.data.clear_cache) {
                        window.location.replace(response.data.redirect || rfmData.homeUrl);  // Changed from rfmUserDashboard
                    } else {
                        window.location.href = response.data.redirect || rfmData.homeUrl;  // Changed from rfmUserDashboard
                    }
                },
                error: function(xhr, status, error) {
                    // Force redirect anyway
                    window.location.replace(rfmData.homeUrl);  // Changed from rfmUserDashboard
                }
            });
        });

        // Download user data (GDPR)
        $('#rfm-download-data').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var originalText = $button.text();

            $button.prop('disabled', true).text('Downloader...');

            $.ajax({
                url: rfmData.ajaxurl,  // Changed from rfmUserDashboard
                type: 'POST',
                data: {
                    action: 'rfm_update_user_profile',
                    nonce: rfmData.nonce,  // Changed from rfmUserDashboard
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

                        $('.rfm-gdpr-info').prepend('<div class="rfm-message rfm-message-success">Data downloadet</div>');
                        setTimeout(function() {
                            $('.rfm-message-success').fadeOut();
                        }, 3000);
                    } else {
                        $('.rfm-gdpr-info').prepend('<div class="rfm-message rfm-message-error">' + response.data.message + '</div>');
                    }
                    $button.prop('disabled', false).text(originalText);
                },
                error: function(xhr, status, error) {
                    $('.rfm-gdpr-info').prepend('<div class="rfm-message rfm-message-error">Der opstod en fejl</div>');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });

        // Delete account modal handlers
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
                $('.rfm-modal-messages').html('<div class="rfm-message rfm-message-error">Indtast din adgangskode</div>');
                return;
            }

            if (!confirm('Er du sikker? Dette kan ikke fortrydes!')) {
                return;
            }

            $(this).prop('disabled', true).text('Sletter...');

            $.ajax({
                url: rfmData.ajaxurl,  // Changed from rfmUserDashboard
                type: 'POST',
                data: {
                    action: 'rfm_delete_user_account',
                    nonce: rfmData.nonce,  // Changed from rfmUserDashboard
                    password: password
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        window.location.href = rfmData.homeUrl;  // Changed from rfmUserDashboard
                    } else {
                        $('.rfm-modal-messages').html('<div class="rfm-message rfm-message-error">' + response.data.message + '</div>');
                        $('#rfm-confirm-delete').prop('disabled', false).text('Bekræft sletning');
                    }
                }
            });
        });
    });

})(jQuery);
