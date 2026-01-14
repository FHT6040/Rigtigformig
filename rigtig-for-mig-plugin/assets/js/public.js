/**
 * Rigtig for mig - Public JavaScript
 */

(function($) {
    'use strict';
    
    // Rating form submission
    $('#rfm-submit-rating-form').on('submit', function(e) {
        e.preventDefault();


        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var rating = $form.find('input[name="rating"]:checked').val();
        var review = $form.find('textarea[name="review"]').val();
        var expert_id = $form.find('input[name="expert_id"]').val();


        if (!rating) {
            alert(rfmData.strings.error || 'Vælg venligst en rating');
            return;
        }

        // Disable button
        $button.prop('disabled', true).text(rfmData.strings.loading || 'Indlæser...');

        var ratingData = {
            action: 'rfm_submit_rating',
            nonce: rfmData.nonce,
            expert_id: expert_id,
            rating: rating,
            review: review
        };


        $.ajax({
            url: rfmData.ajaxurl,
            type: 'POST',
            data: ratingData,
            success: function(response) {

                if (response.success) {
                    // Show success message
                    showNotification(response.data.message, 'success');

                    // Reload page after 2 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotification(response.data.message, 'error');
                    $button.prop('disabled', false).text('Send bedømmelse');
                }
            },
            error: function(xhr, status, error) {
                showNotification(rfmData.strings.error || 'Der opstod en fejl', 'error');
                $button.prop('disabled', false).text('Send bedømmelse');
            }
        });
    });
    
    // Show notification
    function showNotification(message, type) {
        var $notification = $('<div class="rfm-notification rfm-notification-' + type + '">' +
            '<p>' + message + '</p>' +
            '<button class="rfm-notification-close">×</button>' +
            '</div>');
        
        $('body').append($notification);
        
        $notification.find('.rfm-notification-close').on('click', function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        });
        
        setTimeout(function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Expert search
    $('#rfm-expert-search-form').on('submit', function() {
        // Form will submit normally, but you can add custom validation here if needed
        return true;
    });
    
    // Smooth scroll to ratings section
    $('.rfm-expert-rating').on('click', function(e) {
        if ($(e.target).closest('a').length === 0) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: $('.rfm-ratings-section').offset().top - 100
            }, 500);
        }
    });
    
    // Logout handler
    $(document).on('click', '#rfm-logout-btn', function(e) {
        e.preventDefault();


        var $button = $(this);
        var originalText = $button.text();

        // Disable button and show loading
        $button.prop('disabled', true).text('Logger ud...');

        var logoutData = {
            action: 'rfm_logout',
            nonce: rfmData.nonce
        };


        $.ajax({
            url: rfmData.ajaxurl,
            type: 'POST',
            data: logoutData,
            success: function(response) {

                if (response.success) {
                    // Show success message briefly
                    showNotification(response.data.message, 'success');

                    // Redirect after 1 second
                    setTimeout(function() {
                        window.location.href = response.data.redirect;
                    }, 1000);
                } else {
                    showNotification(response.data.message, 'error');
                    $button.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                showNotification('Der opstod en fejl ved logout', 'error');
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // =========================================================================
    // NOTE: User dashboard handlers are in the shortcode inline script
    // This prevents conflicts and ensures handlers load at the right time
    // =========================================================================
    
    // Handle image upload in repeater fields
    $(document).on('change', '.rfm-image-upload', function(e) {
        var $input = $(this);
        var file = e.target.files[0];
        var $imageField = $input.closest('.rfm-image-field');
        
        if (file && file.type.match('image.*')) {
            var reader = new FileReader();
            
            reader.onload = function(e) {
                // Hide upload wrapper
                $imageField.find('.rfm-image-upload-wrapper').hide();
                
                // Show preview
                var $preview = $('<div class="rfm-image-preview">' +
                    '<img src="' + e.target.result + '" alt="Diplom/Certifikat">' +
                    '<button type="button" class="rfm-image-remove">✕ Fjern billede</button>' +
                    '</div>');
                
                $imageField.prepend($preview);
            };
            
            reader.readAsDataURL(file);
        }
    });
    
    // Handle image removal in repeater fields
    $(document).on('click', '.rfm-image-remove', function(e) {
        e.preventDefault();
        
        if (confirm('Er du sikker på at du vil fjerne dette billede?')) {
            var $imageField = $(this).closest('.rfm-image-field');
            
            // Remove preview
            $imageField.find('.rfm-image-preview').remove();
            
            // Remove hidden input with image ID
            $imageField.find('input[type="hidden"]').remove();
            
            // Clear file input and show upload wrapper
            $imageField.find('.rfm-image-upload').val('');
            $imageField.find('.rfm-image-upload-wrapper').show();
        }
    });
    
    // ========================================
    // Education Repeater Functionality
    // ========================================
    
    var educationIndex = $('.rfm-education-item').length;
    
    // Add new education
    $(document).on('click', '#rfm-add-education', function(e) {
        e.preventDefault();
        
        var $container = $('#rfm-educations-container');
        var maxItems = parseInt($container.data('max'));
        var currentCount = $container.find('.rfm-education-item').length;
        
        // Check limit
        if (currentCount >= maxItems) {
            $('#rfm-education-limit-notice').show();
            return;
        }
        
        // Get template and replace index
        var template = $('#rfm-education-template').html();
        var newItem = template.replace(/__INDEX__/g, educationIndex);
        
        // Append new item
        $container.append(newItem);
        
        // Increment index
        educationIndex++;
        
        // Check if we've hit the limit
        if ($container.find('.rfm-education-item').length >= maxItems) {
            $('#rfm-education-limit-notice').show();
            $(this).prop('disabled', true);
        }
        
        // Scroll to new item
        var $newItem = $container.find('.rfm-education-item').last();
        $('html, body').animate({
            scrollTop: $newItem.offset().top - 100
        }, 300);
        
        // Focus on first input
        $newItem.find('input[type="text"]').first().focus();
    });
    
    // Remove education
    $(document).on('click', '.rfm-education-remove', function(e) {
        e.preventDefault();
        
        if (confirm('Er du sikker på at du vil fjerne denne uddannelse?')) {
            var $item = $(this).closest('.rfm-education-item');
            var $container = $('#rfm-educations-container');
            var maxItems = parseInt($container.data('max'));
            
            // Remove item with animation
            $item.slideUp(300, function() {
                $(this).remove();
                
                // Re-enable add button if under limit
                if ($container.find('.rfm-education-item').length < maxItems) {
                    $('#rfm-add-education').prop('disabled', false);
                    $('#rfm-education-limit-notice').hide();
                }
            });
        }
    });
    
    // ========================================
    // Education Image Upload
    // ========================================
    
    // Trigger file input when clicking upload button
    $(document).on('click', '.rfm-upload-education-image', function(e) {
        e.preventDefault();
        $(this).closest('.rfm-image-upload-wrapper').find('.rfm-education-image-input').click();
    });
    
    // Handle education image file selection
    $(document).on('change', '.rfm-education-image-input', function(e) {
        var $input = $(this);
        var file = e.target.files[0];
        var $wrapper = $input.closest('.rfm-image-upload-wrapper');
        var $preview = $wrapper.find('.rfm-image-preview');
        var $imageIdInput = $wrapper.find('.rfm-education-image-id');
        var $buttons = $wrapper.find('.rfm-image-buttons');
        
        if (!file) return;
        
        // Validate file type
        var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (allowedTypes.indexOf(file.type) === -1) {
            alert('Ugyldig filtype. Kun JPG, PNG, GIF og WebP er tilladt.');
            $input.val('');
            return;
        }
        
        // Validate file size (5MB)
        var maxSize = 5 * 1024 * 1024;
        if (file.size > maxSize) {
            alert('Filen er for stor. Maksimum 5MB.');
            $input.val('');
            return;
        }
        
        // Show loading state
        $wrapper.addClass('rfm-uploading');
        
        // Create form data for upload
        var formData = new FormData();
        formData.append('action', 'rfm_upload_education_image');
        formData.append('nonce', rfmData.nonce);
        formData.append('education_image', file);
        
        // Upload via AJAX
        $.ajax({
            url: rfmData.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $wrapper.removeClass('rfm-uploading');
                
                if (response.success) {
                    // Update hidden input with attachment ID
                    $imageIdInput.val(response.data.attachment_id);
                    
                    // Update preview
                    $preview.addClass('has-image').html(response.data.image_html);
                    
                    // Update buttons
                    $buttons.html(
                        '<button type="button" class="rfm-btn rfm-btn-small rfm-upload-education-image">Skift billede</button>' +
                        '<button type="button" class="rfm-btn rfm-btn-small rfm-btn-danger rfm-remove-education-image">Fjern</button>'
                    );
                    
                    showNotification(response.data.message, 'success');
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            error: function() {
                $wrapper.removeClass('rfm-uploading');
                showNotification('Der opstod en fejl ved upload. Prøv igen.', 'error');
            }
        });
    });
    
    // Remove education image
    $(document).on('click', '.rfm-remove-education-image', function(e) {
        e.preventDefault();
        
        if (confirm('Er du sikker på at du vil fjerne dette billede?')) {
            var $wrapper = $(this).closest('.rfm-image-upload-wrapper');
            var $preview = $wrapper.find('.rfm-image-preview');
            var $imageIdInput = $wrapper.find('.rfm-education-image-id');
            var $buttons = $wrapper.find('.rfm-image-buttons');
            var $fileInput = $wrapper.find('.rfm-education-image-input');
            
            // Clear values
            $imageIdInput.val('');
            $fileInput.val('');
            
            // Update preview
            $preview.removeClass('has-image').html('');
            
            // Update buttons
            $buttons.html(
                '<button type="button" class="rfm-btn rfm-btn-small rfm-upload-education-image">Upload billede</button>'
            );
        }
    });
    
    // =========================================
    // Category Limit Handler (Registration)
    // =========================================
    
    // Handle category limits on registration page
    var $regCategories = $('#rfm-registration-categories');
    if ($regCategories.length) {
        var limitFree = parseInt($regCategories.data('limit-free')) || 1;
        var limitStandard = parseInt($regCategories.data('limit-standard')) || 2;
        var limitPremium = parseInt($regCategories.data('limit-premium')) || 99;
        
        function updateCategoryLimitReg() {
            var selectedPlan = $('input[name="plan"]:checked').val() || 'free';
            var maxCategories = limitFree;
            
            if (selectedPlan === 'standard') {
                maxCategories = limitStandard;
            } else if (selectedPlan === 'premium') {
                maxCategories = limitPremium;
            }
            
            var $checkboxes = $regCategories.find('.rfm-category-checkbox');
            var checkedCount = $checkboxes.filter(':checked').length;
            
            // Disable unchecked checkboxes if limit reached
            if (checkedCount >= maxCategories) {
                $checkboxes.not(':checked').prop('disabled', true);
                $('#rfm-category-limit-notice').show();
            } else {
                $checkboxes.prop('disabled', false);
                $('#rfm-category-limit-notice').hide();
            }
        }
        
        // Update on plan change
        $('input[name="plan"]').on('change', function() {
            // Uncheck all categories when plan changes
            $regCategories.find('.rfm-category-checkbox').prop('checked', false).prop('disabled', false);
            $('#rfm-category-limit-notice').hide();
            updateCategoryLimitReg();
        });
        
        // Update on category change
        $regCategories.on('change', '.rfm-category-checkbox', updateCategoryLimitReg);
        
        // Initial check
        updateCategoryLimitReg();
    }
    
    // =========================================
    // Category Limit Handler (Dashboard)
    // =========================================
    
    var $dashCategories = $('#rfm-dashboard-categories');
    if ($dashCategories.length) {
        var maxCategories = parseInt($dashCategories.data('max')) || 1;
        
        function updateCategoryLimitDash() {
            var $checkboxes = $dashCategories.find('.rfm-category-checkbox');
            var checkedCount = $checkboxes.filter(':checked').length;
            
            // Disable unchecked checkboxes if limit reached
            if (checkedCount >= maxCategories) {
                $checkboxes.not(':checked').prop('disabled', true);
                $('#rfm-category-limit-notice-dashboard').show();
            } else {
                $checkboxes.prop('disabled', false);
                $('#rfm-category-limit-notice-dashboard').hide();
            }
        }
        
        // Update on category change
        $dashCategories.on('change', '.rfm-category-checkbox', updateCategoryLimitDash);
        
        // Initial check
        updateCategoryLimitDash();
    }
    
    // =========================================
    // Specialization Limit Handler (Dashboard)
    // =========================================
    
    var $dashSpecs = $('#rfm-dashboard-specializations');
    if ($dashSpecs.length) {
        var maxSpecs = parseInt($dashSpecs.data('max')) || 1;
        
        function updateSpecLimitDash() {
            var $checkboxes = $dashSpecs.find('.rfm-specialization-checkbox');
            var checkedCount = $checkboxes.filter(':checked').length;
            
            // Disable unchecked checkboxes if limit reached
            if (checkedCount >= maxSpecs) {
                $checkboxes.not(':checked').prop('disabled', true);
                $('#rfm-specialization-limit-notice').show();
            } else {
                $checkboxes.prop('disabled', false);
                $('#rfm-specialization-limit-notice').hide();
            }
        }
        
        // Update on specialization change
        $dashSpecs.on('change', '.rfm-specialization-checkbox', updateSpecLimitDash);
        
        // Initial check
        updateSpecLimitDash();
    }
    
    // ========================================
    // Category Education Image Upload Handler
    // ========================================
    
    // Click handler for upload button
    $(document).on('click', '.rfm-upload-cat-education-image', function(e) {
        e.preventDefault();
        var $wrapper = $(this).closest('.rfm-form-field');
        var $input = $wrapper.find('.rfm-cat-education-image-input');
        $input.trigger('click');
    });
    
    // File input change handler
    $(document).on('change', '.rfm-cat-education-image-input', function() {
        var $input = $(this);
        var $wrapper = $input.closest('.rfm-form-field');
        var $preview = $wrapper.find('.rfm-image-preview');
        var $hiddenInput = $wrapper.find('.rfm-cat-education-image-id');
        var $uploadBtn = $wrapper.find('.rfm-upload-cat-education-image');
        var $removeBtn = $wrapper.find('.rfm-remove-cat-education-image');
        
        if (!this.files || !this.files[0]) {
            return;
        }
        
        var file = this.files[0];
        
        // Validate file type
        var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (allowedTypes.indexOf(file.type) === -1) {
            alert('Ugyldig filtype. Kun JPG, PNG, GIF og WebP er tilladt.');
            return;
        }
        
        // Validate file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('Filen er for stor. Maksimum 5MB.');
            return;
        }
        
        // Show loading state
        $wrapper.addClass('rfm-uploading');
        $uploadBtn.prop('disabled', true).text('Uploader...');
        
        // Get expert ID from form
        var expertId = $wrapper.closest('form').find('[name="expert_id"]').val();
        
        // Create FormData
        var formData = new FormData();
        formData.append('action', 'rfm_upload_education_image');
        formData.append('education_image', file);
        formData.append('expert_id', expertId);
        formData.append('nonce', rfmData.nonce);
        
        // Upload via AJAX
        $.ajax({
            url: rfmData.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $wrapper.removeClass('rfm-uploading');
                
                if (response.success) {
                    // Update hidden input with attachment ID
                    $hiddenInput.val(response.data.attachment_id);
                    
                    // Update preview
                    $preview.html(response.data.image_html).addClass('has-image');
                    
                    // Update button text
                    $uploadBtn.prop('disabled', false).text('Skift billede');
                    
                    // Show remove button
                    if ($removeBtn.length === 0) {
                        $uploadBtn.after('<button type="button" class="rfm-btn rfm-btn-small rfm-btn-danger rfm-remove-cat-education-image">Fjern</button>');
                    } else {
                        $removeBtn.show();
                    }
                } else {
                    alert(response.data.message || 'Der opstod en fejl ved upload.');
                    $uploadBtn.prop('disabled', false).text('Upload billede');
                }
            },
            error: function() {
                $wrapper.removeClass('rfm-uploading');
                alert('Der opstod en fejl ved upload. Prøv igen.');
                $uploadBtn.prop('disabled', false).text('Upload billede');
            }
        });
    });
    
    // Remove image handler
    $(document).on('click', '.rfm-remove-cat-education-image', function(e) {
        e.preventDefault();
        var $wrapper = $(this).closest('.rfm-form-field');
        var $preview = $wrapper.find('.rfm-image-preview');
        var $hiddenInput = $wrapper.find('.rfm-cat-education-image-id');
        var $uploadBtn = $wrapper.find('.rfm-upload-cat-education-image');
        
        // Clear the hidden input
        $hiddenInput.val('');
        
        // Clear the preview
        $preview.html('').removeClass('has-image');
        
        // Update button text
        $uploadBtn.text('Upload billede');
        
        // Hide remove button
        $(this).hide();
    });

    // ========================================
    // MESSAGE SYSTEM - Send Message Modal
    // ========================================

    // Open send message modal
    $(document).on('click', '#rfm-send-message-btn', function(e) {
        e.preventDefault();
        var expertId = $(this).data('expert-id');

        var $modal = $('#rfm-message-modal');
        if ($modal.length > 0) {
            $modal.data('expert-id', expertId).show();
        }
    });

    // Close send message modal
    $(document).on('click', '#rfm-message-modal .rfm-modal-close, #rfm-message-modal', function(e) {
        if (e.target === this) {
            $('#rfm-message-modal').hide();
            // Clear form
            $('#rfm-message-subject').val('');
            $('#rfm-message-text').val('');
        }
    });

    // Submit message from expert profile
    $(document).on('submit', '#rfm-message-form', function(e) {
        e.preventDefault();

        var $form = $(this);
        var expertId = $('#rfm-message-modal').data('expert-id');
        var subject = $('#rfm-message-subject').val().trim();
        var message = $('#rfm-message-text').val().trim();

        if (!subject || !message) {
            alert('Udfyld venligst både emne og besked.');
            return;
        }

        var $btn = $form.find('button[type="submit"]');
        var originalText = $btn.text();
        $btn.prop('disabled', true).text('Sender...');

        $.ajax({
            url: rfmData.ajaxurl,
            type: 'POST',
            data: {
                action: 'rfm_send_message',
                nonce: rfmData.nonce,
                expert_id: expertId,
                subject: subject,
                message: message
            },
            success: function(response) {
                if (response.success) {
                    alert('Din besked er sendt!');
                    $('#rfm-message-modal').hide();
                    $form[0].reset();
                } else {
                    alert('Fejl: ' + (response.data || 'Kunne ikke sende besked'));
                }
            },
            error: function() {
                alert('Der opstod en fejl. Prøv igen senere.');
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });

})(jQuery);
