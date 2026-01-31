/**
 * Expert Articles JavaScript
 *
 * Handles article creation, editing, deletion, and image upload
 * in the expert dashboard Articles tab.
 *
 * @package Rigtig_For_Mig
 * @since 3.13.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // ========================================
        // AJAX URL CONFIGURATION
        // ========================================
        var ajaxUrl = '';
        if (typeof rfmDashboard !== 'undefined' && rfmDashboard.ajaxurl) {
            ajaxUrl = rfmDashboard.ajaxurl;
        } else {
            var pluginUrl = window.location.origin + '/wp-content/plugins/rigtig-for-mig-plugin/';
            ajaxUrl = pluginUrl + 'ajax-handler.php';
        }

        // Article AJAX handlers require 'rfm_dashboard_tabbed' nonce action,
        // so prioritize the form nonce over the localized rfmDashboard.nonce
        // which uses a different action ('rfm_expert_dashboard').
        var nonce = $('input[name="rfm_tabbed_nonce"]').val() || '';
        if (!nonce && typeof rfmDashboard !== 'undefined' && rfmDashboard.nonce) {
            nonce = rfmDashboard.nonce;
        }

        // ========================================
        // NEW ARTICLE BUTTON
        // ========================================
        $('#rfm-new-article-btn').on('click', function() {
            // Reset form
            $('#rfm-article-id').val('0');
            $('#rfm-article-title').val('');
            $('#rfm-article-content').val('');
            $('#rfm-article-category').val('');
            $('#rfm-article-image-id').val('0');
            $('#rfm-article-image-preview').html('<span class="rfm-no-image">Intet billede valgt</span>');
            $('#rfm-remove-article-image-btn').hide();
            $('#rfm-article-editor-title').text('Ny artikel');
            $('#rfm-submit-article-btn').text('Indsend til godkendelse');
            $('#rfm-article-editor-message').hide().html('');

            // Show editor, hide list
            $('#rfm-article-editor').slideDown(300);
            $(this).hide();
        });

        // ========================================
        // CANCEL ARTICLE
        // ========================================
        $('#rfm-cancel-article-btn').on('click', function() {
            $('#rfm-article-editor').slideUp(300);
            $('#rfm-new-article-btn').show();
        });

        // ========================================
        // EDIT ARTICLE BUTTON
        // ========================================
        $(document).on('click', '.rfm-edit-article-btn', function() {
            var $btn = $(this);
            var articleId = $btn.data('article-id');
            var title = $btn.data('title');
            var content = $btn.data('content');
            var category = $btn.data('category');
            var imageId = $btn.data('image-id');
            var imageUrl = $btn.data('image-url');

            // Populate form
            $('#rfm-article-id').val(articleId);
            $('#rfm-article-title').val(title);
            $('#rfm-article-content').val(content);
            $('#rfm-article-category').val(category);
            $('#rfm-article-image-id').val(imageId || '0');

            // Set image preview
            if (imageId && imageUrl) {
                $('#rfm-article-image-preview').html('<img src="' + imageUrl + '" alt="" />');
                $('#rfm-remove-article-image-btn').show();
            } else {
                $('#rfm-article-image-preview').html('<span class="rfm-no-image">Intet billede valgt</span>');
                $('#rfm-remove-article-image-btn').hide();
            }

            $('#rfm-article-editor-title').text('Rediger artikel');
            $('#rfm-submit-article-btn').text('Gem ændringer');
            $('#rfm-article-editor-message').hide().html('');

            // Show editor
            $('#rfm-article-editor').slideDown(300);
            $('#rfm-new-article-btn').hide();

            // Scroll to editor
            $('html, body').animate({
                scrollTop: $('#rfm-article-editor').offset().top - 80
            }, 300);
        });

        // ========================================
        // ARTICLE FORM SUBMISSION
        // ========================================
        $('#rfm-article-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $('#rfm-submit-article-btn');
            var $message = $('#rfm-article-editor-message');
            var originalText = $button.text();

            // Validate
            var title = $('#rfm-article-title').val().trim();
            var content = $('#rfm-article-content').val().trim();
            var category = $('#rfm-article-category').val();

            if (!title) {
                $message.html('<div class="rfm-error">Artiklen skal have en titel.</div>').show();
                return;
            }
            if (!content) {
                $message.html('<div class="rfm-error">Artiklen skal have indhold.</div>').show();
                return;
            }
            if (!category) {
                $message.html('<div class="rfm-error">Vælg en kategori.</div>').show();
                return;
            }

            // Disable button
            $button.prop('disabled', true).text('Gemmer...');
            $message.hide();

            var formData = {
                action: 'rfm_save_article',
                nonce: nonce,
                expert_id: $form.find('input[name="expert_id"]').val(),
                article_id: $('#rfm-article-id').val(),
                article_title: title,
                article_content: content,
                article_category: category,
                article_image_id: $('#rfm-article-image-id').val()
            };

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $message.html('<div class="rfm-success">' + response.data.message + '</div>').show();
                        // Reload page after short delay to show updated list
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
        // DELETE ARTICLE
        // ========================================
        $(document).on('click', '.rfm-delete-article-btn', function() {
            if (!confirm('Er du sikker på at du vil slette denne artikel?')) {
                return;
            }

            var $btn = $(this);
            var articleId = $btn.data('article-id');
            var $item = $btn.closest('.rfm-article-item');
            var expertId = $('#rfm-article-form input[name="expert_id"]').val();

            $btn.prop('disabled', true).text('Sletter...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rfm_delete_article',
                    nonce: nonce,
                    expert_id: expertId,
                    article_id: articleId
                },
                success: function(response) {
                    if (response.success) {
                        $item.slideUp(300, function() {
                            $(this).remove();
                            // Check if list is now empty
                            if ($('.rfm-article-item').length === 0) {
                                $('#rfm-article-list').html(
                                    '<div class="rfm-no-articles"><p>Du har ingen artikler endnu.</p></div>'
                                );
                            }
                        });
                    } else {
                        alert(response.data.message || 'Kunne ikke slette artiklen.');
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
        // ARTICLE IMAGE UPLOAD
        // ========================================
        $('#rfm-upload-article-image-btn').on('click', function() {
            $('#rfm-article-image-input').trigger('click');
        });

        $('#rfm-article-image-input').on('change', function() {
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
            formData.append('action', 'rfm_upload_article_image');
            formData.append('nonce', nonce);
            formData.append('expert_id', $('#rfm-article-form input[name="expert_id"]').val());
            formData.append('article_image', file);

            var $btn = $('#rfm-upload-article-image-btn');
            $btn.prop('disabled', true).text('Uploader...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $('#rfm-article-image-id').val(response.data.attachment_id);
                        $('#rfm-article-image-preview').html(
                            '<img src="' + response.data.image_url + '" alt="" />'
                        );
                        $('#rfm-remove-article-image-btn').show();
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
        // REMOVE ARTICLE IMAGE
        // ========================================
        $('#rfm-remove-article-image-btn').on('click', function() {
            $('#rfm-article-image-id').val('0');
            $('#rfm-article-image-preview').html('<span class="rfm-no-image">Intet billede valgt</span>');
            $(this).hide();
        });

    });
})(jQuery);
