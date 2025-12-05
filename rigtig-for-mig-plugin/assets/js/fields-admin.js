/**
 * Rigtig For Mig - Fields Admin JavaScript
 * Version: 2.7.0
 * Handles all admin interactions for field management
 */

(function($) {
    'use strict';
    
    const FieldsAdmin = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Modal controls
            $(document).on('click', '#rfm-add-group-btn', this.openAddGroupModal);
            $(document).on('click', '.rfm-add-field-btn', this.openAddFieldModal);
            $(document).on('click', '.rfm-edit-field-btn', this.openEditFieldModal);
            $(document).on('click', '.rfm-modal-close', this.closeModal);
            $(document).on('click', '.rfm-modal', function(e) {
                if ($(e.target).hasClass('rfm-modal')) {
                    FieldsAdmin.closeModal();
                }
            });
            
            // Form submissions
            $(document).on('submit', '#rfm-add-group-form', this.handleAddGroup);
            $(document).on('submit', '#rfm-field-form', this.handleSaveField);
            
            // Delete actions
            $(document).on('click', '.rfm-delete-group-btn', this.handleDeleteGroup);
            $(document).on('click', '.rfm-delete-field-btn', this.handleDeleteField);
            
            // Field type change
            $(document).on('change', '#field_type', this.handleFieldTypeChange);
            
            // Sub-fields management
            $(document).on('click', '#add_sub_field_btn', this.addSubField);
            $(document).on('click', '.remove-sub-field', this.removeSubField);
        },
        
        openAddGroupModal: function() {
            $('#rfm-add-group-modal').fadeIn(200);
            $('#group_key').focus();
        },
        
        openAddFieldModal: function() {
            const groupKey = $(this).data('group');
            $('#field_group_key').val(groupKey);
            $('#field_mode').val('add');
            $('#rfm-field-modal-title').text('Tilføj Nyt Felt');
            $('#rfm-field-form')[0].reset();
            $('#field_key').prop('readonly', false);
            $('#field_max_items_row, #field_sub_fields_row').hide();
            $('#sub_fields_list').empty();
            $('#rfm-field-modal').fadeIn(200);
        },
        
        openEditFieldModal: function() {
            const groupKey = $(this).data('group');
            const fieldKey = $(this).data('field');
            
            $('#field_group_key').val(groupKey);
            $('#field_mode').val('edit');
            $('#field_key').val(fieldKey).prop('readonly', true);
            $('#rfm-field-modal-title').text('Rediger Felt');
            
            // Load field data
            $.ajax({
                url: rfmFieldsAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rfm_get_field_data',
                    nonce: rfmFieldsAdmin.nonce,
                    group_key: groupKey,
                    field_key: fieldKey
                },
                success: function(response) {
                    if (response.success) {
                        FieldsAdmin.populateFieldForm(response.data.field_data);
                    }
                }
            });
            
            $('#rfm-field-modal').fadeIn(200);
        },
        
        populateFieldForm: function(fieldData) {
            $('#field_label').val(fieldData.label);
            $('#field_type').val(fieldData.type).trigger('change');
            $('#field_subscription').val(fieldData.subscription_required);
            $('#field_required').prop('checked', fieldData.required);
            $('#field_description').val(fieldData.description || '');
            
            if (fieldData.type === 'repeater') {
                $('#field_max_items').val(fieldData.max_items || '');
                
                // Populate sub-fields
                if (fieldData.sub_fields) {
                    $('#sub_fields_list').empty();
                    $.each(fieldData.sub_fields, function(key, subField) {
                        FieldsAdmin.addSubFieldRow(key, subField);
                    });
                }
            }
        },
        
        closeModal: function() {
            $('.rfm-modal').fadeOut(200);
        },
        
        handleAddGroup: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $button = $form.find('button[type="submit"]');
            
            $button.prop('disabled', true).text('Opretter...');
            
            $.ajax({
                url: rfmFieldsAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rfm_save_field_group',
                    nonce: rfmFieldsAdmin.nonce,
                    group_key: $('#group_key').val(),
                    group_label: $('#group_label').val()
                },
                success: function(response) {
                    if (response.success) {
                        FieldsAdmin.showNotice('success', rfmFieldsAdmin.strings.saved);
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        FieldsAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    FieldsAdmin.showNotice('error', rfmFieldsAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Opret Felt-gruppe');
                }
            });
        },
        
        handleSaveField: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $button = $form.find('button[type="submit"]');
            
            // Collect form data
            const fieldData = {
                type: $('#field_type').val(),
                label: $('#field_label').val(),
                required: $('#field_required').is(':checked'),
                subscription_required: $('#field_subscription').val(),
                description: $('#field_description').val()
            };
            
            // Handle repeater-specific data
            if (fieldData.type === 'repeater') {
                fieldData.max_items = $('#field_max_items').val();
                fieldData.sub_fields = {};
                
                $('#sub_fields_list .sub-field-item').each(function() {
                    const key = $(this).find('.sub-field-key').val();
                    if (key) {
                        fieldData.sub_fields[key] = {
                            type: $(this).find('.sub-field-type').val(),
                            label: $(this).find('.sub-field-label').val(),
                            required: $(this).find('.sub-field-required').is(':checked')
                        };
                    }
                });
            }
            
            $button.prop('disabled', true).text('Gemmer...');
            
            $.ajax({
                url: rfmFieldsAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rfm_save_field',
                    nonce: rfmFieldsAdmin.nonce,
                    group_key: $('#field_group_key').val(),
                    field_key: $('#field_key').val(),
                    field_data: fieldData
                },
                success: function(response) {
                    if (response.success) {
                        FieldsAdmin.showNotice('success', rfmFieldsAdmin.strings.saved);
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        FieldsAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    FieldsAdmin.showNotice('error', rfmFieldsAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Gem Felt');
                }
            });
        },
        
        handleDeleteGroup: function() {
            const groupKey = $(this).data('group');
            
            if (!confirm(rfmFieldsAdmin.strings.confirm_delete_group)) {
                return;
            }
            
            $.ajax({
                url: rfmFieldsAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rfm_delete_field_group',
                    nonce: rfmFieldsAdmin.nonce,
                    group_key: groupKey
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        },
        
        handleDeleteField: function() {
            const groupKey = $(this).data('group');
            const fieldKey = $(this).data('field');
            
            if (!confirm(rfmFieldsAdmin.strings.confirm_delete_field)) {
                return;
            }
            
            $.ajax({
                url: rfmFieldsAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rfm_delete_field',
                    nonce: rfmFieldsAdmin.nonce,
                    group_key: groupKey,
                    field_key: fieldKey
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        },
        
        handleFieldTypeChange: function() {
            const fieldType = $(this).val();
            
            if (fieldType === 'repeater') {
                $('#field_max_items_row, #field_sub_fields_row').show();
            } else {
                $('#field_max_items_row, #field_sub_fields_row').hide();
            }
        },
        
        addSubField: function() {
            FieldsAdmin.addSubFieldRow('', {
                type: 'text',
                label: '',
                required: false
            });
        },
        
        addSubFieldRow: function(key, data) {
            const index = $('#sub_fields_list .sub-field-item').length;
            const $row = $('<div class="sub-field-item"></div>');
            
            $row.html(`
                <div class="sub-field-header">
                    <strong>Underfelt ${index + 1}</strong>
                    <span class="remove-sub-field dashicons dashicons-trash"></span>
                </div>
                <div class="sub-field-inputs">
                    <input type="text" class="sub-field-key" placeholder="Nøgle (eks: navn)" value="${key}" ${key ? 'readonly' : ''}>
                    <input type="text" class="sub-field-label" placeholder="Label" value="${data.label || ''}">
                    <select class="sub-field-type">
                        <option value="text" ${data.type === 'text' ? 'selected' : ''}>Tekst</option>
                        <option value="textarea" ${data.type === 'textarea' ? 'selected' : ''}>Tekst (lang)</option>
                        <option value="number" ${data.type === 'number' ? 'selected' : ''}>Tal</option>
                        <option value="date" ${data.type === 'date' ? 'selected' : ''}>Dato</option>
                        <option value="url" ${data.type === 'url' ? 'selected' : ''}>URL</option>
                    </select>
                </div>
                <label style="margin-top: 5px;">
                    <input type="checkbox" class="sub-field-required" ${data.required ? 'checked' : ''}>
                    Påkrævet
                </label>
            `);
            
            $('#sub_fields_list').append($row);
        },
        
        removeSubField: function() {
            $(this).closest('.sub-field-item').remove();
        },
        
        showNotice: function(type, message) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').first().after($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        if ($('.rfm-fields-admin-wrap').length) {
            FieldsAdmin.init();
        }
    });
    
})(jQuery);
