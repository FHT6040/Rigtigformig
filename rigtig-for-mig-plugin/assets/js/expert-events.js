/**
 * Expert Events JavaScript
 *
 * Handles event creation, editing, deletion, image upload, and file upload
 * in the expert dashboard "Kurser & Events" tab.
 *
 * @package Rigtig_For_Mig
 * @since 3.14.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // ========================================
        // AJAX URL & NONCE CONFIGURATION
        // ========================================
        var ajaxUrl = '';
        if (typeof rfmDashboard !== 'undefined' && rfmDashboard.ajaxurl) {
            ajaxUrl = rfmDashboard.ajaxurl;
        } else {
            var pluginUrl = window.location.origin + '/wp-content/plugins/rigtig-for-mig-plugin/';
            ajaxUrl = pluginUrl + 'ajax-handler.php';
        }

        // Event AJAX handlers require 'rfm_dashboard_tabbed' nonce action,
        // so prioritize the form nonce over the localized rfmDashboard.nonce.
        var nonce = $('input[name="rfm_tabbed_nonce"]').val() || '';
        if (!nonce && typeof rfmDashboard !== 'undefined' && rfmDashboard.nonce) {
            nonce = rfmDashboard.nonce;
        }

        // ========================================
        // NEW EVENT BUTTON
        // ========================================
        $('#rfm-new-event-btn').on('click', function() {
            // Reset form
            $('#rfm-event-id').val('0');
            $('#rfm-event-title').val('');
            $('#rfm-event-content').val('');
            $('#rfm-event-category').val('');
            $('#rfm-event-type').val('');
            $('#rfm-event-date').val('');
            $('#rfm-event-time-start').val('');
            $('#rfm-event-time-end').val('');
            $('#rfm-event-location').val('');
            $('#rfm-event-format').val('');
            $('#rfm-event-audience').val('');
            $('#rfm-event-price').val('');
            $('#rfm-event-url').val('');
            $('#rfm-event-what-you-get').val('');
            $('#rfm-event-who-for').val('');
            $('#rfm-event-who-not-for').val('');
            $('#rfm-event-image-id').val('0');
            $('#rfm-event-file-id').val('0');
            $('#rfm-event-image-preview').html('<span class="rfm-no-image">Intet billede valgt</span>');
            $('#rfm-remove-event-image-btn').hide();
            $('#rfm-event-file-preview').html('<span class="rfm-no-file">Ingen fil vedhæftet</span>');
            $('#rfm-remove-event-file-btn').hide();
            $('#rfm-event-editor-title').text('Nyt event');
            $('#rfm-submit-event-btn').text('Indsend til godkendelse');
            $('#rfm-event-editor-message').hide().html('');

            // Show editor, hide button
            $('#rfm-event-editor').slideDown(300);
            $(this).hide();
        });

        // ========================================
        // CANCEL EVENT
        // ========================================
        $('#rfm-cancel-event-btn').on('click', function() {
            $('#rfm-event-editor').slideUp(300);
            $('#rfm-new-event-btn').show();
        });

        // ========================================
        // EDIT EVENT BUTTON
        // ========================================
        $(document).on('click', '.rfm-edit-event-btn', function() {
            var $btn = $(this);

            // Populate form from data attributes
            $('#rfm-event-id').val($btn.data('event-id'));
            $('#rfm-event-title').val($btn.data('title'));
            $('#rfm-event-content').val($btn.data('content'));
            $('#rfm-event-category').val($btn.data('category'));
            $('#rfm-event-type').val($btn.data('event-type') || '');
            $('#rfm-event-date').val($btn.data('date'));
            $('#rfm-event-time-start').val($btn.data('time-start'));
            $('#rfm-event-time-end').val($btn.data('time-end'));
            $('#rfm-event-location').val($btn.data('location'));
            $('#rfm-event-format').val($btn.data('format') || '');
            $('#rfm-event-audience').val($btn.data('audience') || '');
            $('#rfm-event-price').val($btn.data('price'));
            $('#rfm-event-url').val($btn.data('url'));
            $('#rfm-event-what-you-get').val($btn.data('what-you-get'));
            $('#rfm-event-who-for').val($btn.data('who-for'));
            $('#rfm-event-who-not-for').val($btn.data('who-not-for'));

            // Image
            var imageId = $btn.data('image-id');
            var imageUrl = $btn.data('image-url');
            $('#rfm-event-image-id').val(imageId || '0');
            if (imageId && imageUrl) {
                $('#rfm-event-image-preview').html('<img src="' + imageUrl + '" alt="" />');
                $('#rfm-remove-event-image-btn').show();
            } else {
                $('#rfm-event-image-preview').html('<span class="rfm-no-image">Intet billede valgt</span>');
                $('#rfm-remove-event-image-btn').hide();
            }

            // File
            var fileId = $btn.data('file-id');
            var fileName = $btn.data('file-name');
            $('#rfm-event-file-id').val(fileId || '0');
            if (fileId && fileName) {
                $('#rfm-event-file-preview').html('<span class="rfm-file-name">' + fileName + '</span>');
                $('#rfm-remove-event-file-btn').show();
            } else {
                $('#rfm-event-file-preview').html('<span class="rfm-no-file">Ingen fil vedhæftet</span>');
                $('#rfm-remove-event-file-btn').hide();
            }

            $('#rfm-event-editor-title').text('Rediger event');
            $('#rfm-submit-event-btn').text('Gem ændringer');
            $('#rfm-event-editor-message').hide().html('');

            // Show editor
            $('#rfm-event-editor').slideDown(300);
            $('#rfm-new-event-btn').hide();

            // Scroll to editor
            $('html, body').animate({
                scrollTop: $('#rfm-event-editor').offset().top - 80
            }, 300);
        });

        // ========================================
        // EVENT FORM SUBMISSION
        // ========================================
        $('#rfm-event-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $('#rfm-submit-event-btn');
            var $message = $('#rfm-event-editor-message');
            var originalText = $button.text();

            // Validate required fields
            var title = $('#rfm-event-title').val().trim();
            var content = $('#rfm-event-content').val().trim();
            var category = $('#rfm-event-category').val();
            var date = $('#rfm-event-date').val();

            if (!title) {
                $message.html('<div class="rfm-error">Eventet skal have en titel.</div>').show();
                return;
            }
            if (!content) {
                $message.html('<div class="rfm-error">Eventet skal have en beskrivelse.</div>').show();
                return;
            }
            if (!category) {
                $message.html('<div class="rfm-error">Vælg en kategori.</div>').show();
                return;
            }
            if (!date) {
                $message.html('<div class="rfm-error">Eventet skal have en dato.</div>').show();
                return;
            }

            // Disable button
            $button.prop('disabled', true).text('Gemmer...');
            $message.hide();

            var formData = {
                action: 'rfm_save_event',
                nonce: nonce,
                expert_id: $form.find('input[name="expert_id"]').val(),
                event_id: $('#rfm-event-id').val(),
                event_title: title,
                event_content: content,
                event_category: category,
                event_type: $('#rfm-event-type').val(),
                event_date: date,
                event_time_start: $('#rfm-event-time-start').val(),
                event_time_end: $('#rfm-event-time-end').val(),
                event_location: $('#rfm-event-location').val(),
                event_format: $('#rfm-event-format').val(),
                event_audience: $('#rfm-event-audience').val(),
                event_price: $('#rfm-event-price').val(),
                event_url: $('#rfm-event-url').val(),
                event_what_you_get: $('#rfm-event-what-you-get').val(),
                event_who_for: $('#rfm-event-who-for').val(),
                event_who_not_for: $('#rfm-event-who-not-for').val(),
                event_image_id: $('#rfm-event-image-id').val(),
                event_file_id: $('#rfm-event-file-id').val()
            };

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $message.html('<div class="rfm-success">' + response.data.message + '</div>').show();
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        $message.html('<div class="rfm-error">' + (response.data.message || 'Der opstod en fejl.') + '</div>').show();
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    $message.html('<div class="rfm-error">Der opstod en netværksfejl. Prøv igen.</div>').show();
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });

        // ========================================
        // DELETE EVENT
        // ========================================
        $(document).on('click', '.rfm-delete-event-btn', function() {
            if (!confirm('Er du sikker på at du vil slette dette event?')) {
                return;
            }

            var $btn = $(this);
            var eventId = $btn.data('event-id');
            var $item = $btn.closest('.rfm-event-item');
            var expertId = $('#rfm-event-form input[name="expert_id"]').val();

            $btn.prop('disabled', true).text('Sletter...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rfm_delete_event',
                    nonce: nonce,
                    expert_id: expertId,
                    event_id: eventId
                },
                success: function(response) {
                    if (response.success) {
                        $item.slideUp(300, function() {
                            $(this).remove();
                            if ($('.rfm-event-item').length === 0) {
                                $('#rfm-event-list').html(
                                    '<div class="rfm-no-articles"><p>Du har ingen events endnu.</p></div>'
                                );
                            }
                        });
                    } else {
                        alert(response.data.message || 'Kunne ikke slette eventet.');
                        $btn.prop('disabled', false).text('Slet');
                    }
                },
                error: function() {
                    alert('Der opstod en netværksfejl.');
                    $btn.prop('disabled', false).text('Slet');
                }
            });
        });

        // ========================================
        // EVENT IMAGE UPLOAD
        // ========================================
        $('#rfm-upload-event-image-btn').on('click', function() {
            $('#rfm-event-image-input').trigger('click');
        });

        $('#rfm-event-image-input').on('change', function() {
            var file = this.files[0];
            if (!file) return;

            // Validate file type
            var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (allowedTypes.indexOf(file.type) === -1) {
                alert('Ugyldig filtype. Kun JPG, PNG, GIF og WebP er tilladt.');
                return;
            }

            // Validate size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('Filen er for stor. Maksimum 5MB.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'rfm_upload_event_image');
            formData.append('nonce', nonce);
            formData.append('expert_id', $('#rfm-event-form input[name="expert_id"]').val());
            formData.append('event_image', file);

            var $btn = $('#rfm-upload-event-image-btn');
            $btn.prop('disabled', true).text('Uploader...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $('#rfm-event-image-id').val(response.data.attachment_id);
                        $('#rfm-event-image-preview').html(
                            '<img src="' + response.data.image_url + '" alt="" />'
                        );
                        $('#rfm-remove-event-image-btn').show();
                    } else {
                        alert(response.data.message || 'Upload fejlede.');
                    }
                    $btn.prop('disabled', false).text('Upload billede');
                },
                error: function() {
                    alert('Der opstod en netværksfejl ved upload.');
                    $btn.prop('disabled', false).text('Upload billede');
                }
            });

            // Reset file input
            $(this).val('');
        });

        // ========================================
        // REMOVE EVENT IMAGE
        // ========================================
        $('#rfm-remove-event-image-btn').on('click', function() {
            $('#rfm-event-image-id').val('0');
            $('#rfm-event-image-preview').html('<span class="rfm-no-image">Intet billede valgt</span>');
            $(this).hide();
        });

        // ========================================
        // EVENT FILE UPLOAD
        // ========================================
        $('#rfm-upload-event-file-btn').on('click', function() {
            $('#rfm-event-file-input').trigger('click');
        });

        $('#rfm-event-file-input').on('change', function() {
            var file = this.files[0];
            if (!file) return;

            // Validate file type
            var allowedTypes = ['application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'image/jpeg', 'image/png'];
            if (allowedTypes.indexOf(file.type) === -1) {
                alert('Ugyldig filtype. Kun PDF, DOC, DOCX, JPG og PNG er tilladt.');
                return;
            }

            // Validate size (10MB)
            if (file.size > 10 * 1024 * 1024) {
                alert('Filen er for stor. Maksimum 10MB.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'rfm_upload_event_file');
            formData.append('nonce', nonce);
            formData.append('expert_id', $('#rfm-event-form input[name="expert_id"]').val());
            formData.append('event_file', file);

            var $btn = $('#rfm-upload-event-file-btn');
            $btn.prop('disabled', true).text('Uploader...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $('#rfm-event-file-id').val(response.data.attachment_id);
                        $('#rfm-event-file-preview').html(
                            '<span class="rfm-file-name">' + response.data.file_name + '</span>'
                        );
                        $('#rfm-remove-event-file-btn').show();
                    } else {
                        alert(response.data.message || 'Upload fejlede.');
                    }
                    $btn.prop('disabled', false).text('Upload fil');
                },
                error: function() {
                    alert('Der opstod en netværksfejl ved upload.');
                    $btn.prop('disabled', false).text('Upload fil');
                }
            });

            // Reset file input
            $(this).val('');
        });

        // ========================================
        // REMOVE EVENT FILE
        // ========================================
        $('#rfm-remove-event-file-btn').on('click', function() {
            $('#rfm-event-file-id').val('0');
            $('#rfm-event-file-preview').html('<span class="rfm-no-file">Ingen fil vedhæftet</span>');
            $(this).hide();
        });

    });
})(jQuery);
