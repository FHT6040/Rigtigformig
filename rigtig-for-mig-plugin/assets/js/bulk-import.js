jQuery(document).ready(function($) {
    'use strict';
    
    let importData = null;
    
    // Preview button
    $('#preview-import').on('click', function(e) {
        e.preventDefault();
        
        const fileInput = $('#import_file')[0];
        
        if (!fileInput.files || !fileInput.files[0]) {
            alert('Vælg venligst en fil først');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'rfm_preview_import');
        formData.append('nonce', rfmBulkImport.nonce);
        formData.append('file', fileInput.files[0]);
        
        // Show loading
        $('#preview-import').prop('disabled', true).text('⏳ Læser fil...');
        $('#rfm-preview-section').hide();
        $('#rfm-import-results').hide();
        
        $.ajax({
            url: rfmBulkImport.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    importData = response.data;
                    
                    // Show preview
                    $('#rfm-preview-content').html(response.data.preview_html);
                    $('#rfm-preview-stats').html(response.data.stats_html);
                    $('#rfm-preview-section').slideDown();
                    
                    // Auto-check "create parent" if there are missing parents
                    if (response.data.missing_parents && response.data.missing_parents.length > 0) {
                        $('#create_parent_checkbox').prop('checked', true);
                        
                        // Show alert about missing parents
                        const parentList = response.data.missing_parents.join(', ');
                        alert('⚠️ VIGTIGT: Din fil indeholder specialiseringer med forældrekategorier der ikke eksisterer (' + parentList + ').\n\n' +
                              'Checkboxen "Opret forældre automatisk" er blevet markeret for dig.\n\n' +
                              'Hvis du fjerner markeringen, vil specialiseringerne blive importeret uden forældrer.');
                    }
                    
                    // Enable import button if there's something to import
                    if (response.data.to_import.length > 0) {
                        $('#execute-import').prop('disabled', false);
                    } else {
                        $('#execute-import').prop('disabled', true);
                        alert('Ingen nye specialiseringer at importere. Alle findes allerede.');
                    }
                    
                    // Scroll to preview
                    $('html, body').animate({
                        scrollTop: $('#rfm-preview-section').offset().top - 50
                    }, 500);
                } else {
                    alert('Fejl: ' + (response.data.message || 'Ukendt fejl'));
                }
            },
            error: function(xhr, status, error) {
                alert('Netværksfejl: ' + error);
            },
            complete: function() {
                $('#preview-import').prop('disabled', false).text('👁️ Preview Import');
            }
        });
    });
    
    // Import form submission
    $('#rfm-bulk-import-form').on('submit', function(e) {
        if (!importData || importData.to_import.length === 0) {
            e.preventDefault();
            alert('Ingen data at importere. Kør preview først.');
            return;
        }
        
        if (!confirm('Er du sikker på at du vil importere ' + importData.to_import.length + ' specialiseringer?')) {
            e.preventDefault();
            return;
        }
        
        // Show progress
        $('#rfm-import-progress').slideDown();
        $('#execute-import').prop('disabled', true).text('⏳ Importerer...');
        
        // Update progress bar (simulated)
        let progress = 0;
        const total = importData.to_import.length;
        const interval = setInterval(function() {
            progress += Math.random() * 20;
            if (progress > 90) progress = 90; // Stop at 90% until actual completion
            
            $('.rfm-progress-fill').css('width', progress + '%');
            $('.rfm-progress-text').text(Math.floor(progress / 100 * total) + ' af ' + total + ' importeret');
        }, 200);
        
        // Store interval ID to clear it later
        $(this).data('progress-interval', interval);
    });
    
    // Show results if present in URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('imported')) {
        const imported = parseInt(urlParams.get('imported'));
        const skipped = parseInt(urlParams.get('skipped'));
        const errors = parseInt(urlParams.get('errors'));
        const parentWarnings = urlParams.get('parent_warnings');
        const errorDetails = urlParams.get('error_details');
        
        let resultsHTML = '';
        
        // Success notice
        if (imported > 0) {
            resultsHTML += '<div class="notice notice-success">';
            resultsHTML += '<h3>✅ Import Gennemført!</h3>';
            resultsHTML += '<ul>';
            resultsHTML += '<li><strong>Importeret:</strong> ' + imported + ' specialiseringer</li>';
            if (skipped > 0) {
                resultsHTML += '<li><strong>Sprunget over:</strong> ' + skipped + ' (duplikater)</li>';
            }
            resultsHTML += '</ul>';
            resultsHTML += '<p><a href="edit-tags.php?taxonomy=rfm_specialization&post_type=rfm_expert" class="button button-primary">Se Specialiseringer</a></p>';
            resultsHTML += '</div>';
        }
        
        // Parent warnings
        if (parentWarnings) {
            try {
                const warnings = JSON.parse(atob(parentWarnings));
                if (warnings.length > 0) {
                    resultsHTML += '<div class="notice notice-warning">';
                    resultsHTML += '<h3>⚠️ Forældreadvarsler</h3>';
                    resultsHTML += '<p>Nogle specialiseringer har forældrer der ikke eksisterer. De blev importeret uden forældre.</p>';
                    resultsHTML += '<ul style="list-style: disc; margin-left: 20px;">';
                    warnings.forEach(function(warning) {
                        resultsHTML += '<li>' + warning + '</li>';
                    });
                    resultsHTML += '</ul>';
                    resultsHTML += '<p><strong>Løsning:</strong> Aktivér "Opret forældre automatisk" checkboxen og importer igen.</p>';
                    resultsHTML += '</div>';
                }
            } catch(e) {
                console.error('Error parsing parent warnings:', e);
            }
        }
        
        // Errors
        if (errors > 0) {
            resultsHTML += '<div class="notice notice-error">';
            resultsHTML += '<h3>❌ Fejl ved Import</h3>';
            resultsHTML += '<p><strong>' + errors + '</strong> specialiseringer kunne ikke importeres.</p>';
            
            if (errorDetails) {
                try {
                    const errorMessages = JSON.parse(atob(errorDetails));
                    if (errorMessages.length > 0) {
                        resultsHTML += '<ul style="list-style: disc; margin-left: 20px;">';
                        errorMessages.forEach(function(msg) {
                            resultsHTML += '<li>' + msg + '</li>';
                        });
                        resultsHTML += '</ul>';
                    }
                } catch(e) {
                    console.error('Error parsing error details:', e);
                }
            }
            resultsHTML += '</div>';
        }
        
        // If nothing was imported
        if (imported === 0 && errors === 0) {
            resultsHTML += '<div class="notice notice-info">';
            resultsHTML += '<h3>ℹ️ Ingen Import</h3>';
            resultsHTML += '<p>Der blev ikke importeret nogen specialiseringer.</p>';
            if (skipped > 0) {
                resultsHTML += '<p>Alle ' + skipped + ' specialiseringer findes allerede i systemet.</p>';
            }
            resultsHTML += '</div>';
        }
        
        $('#rfm-results-content').html(resultsHTML);
        $('#rfm-import-results').show();
        
        // Scroll to results
        $('html, body').animate({
            scrollTop: $('#rfm-import-results').offset().top - 50
        }, 500);
        
        // Clean URL
        window.history.replaceState({}, document.title, window.location.pathname + '?page=rfm-bulk-import');
    }
    
    // File input change - reset preview
    $('#import_file').on('change', function() {
        $('#rfm-preview-section').hide();
        $('#execute-import').prop('disabled', true);
        importData = null;
    });
    
    // Download template
    $('#download-template').on('click', function(e) {
        e.preventDefault();
        
        // Create CSV template
        const csv = 'Navn,Korttitel,Beskrivelse,Forælder\n' +
                    'Eksempel Specialisering,eksempel-spec,Dette er en beskrivelse,\n' +
                    'Yoga,yoga,Yoga instruktion,Krop & Bevægelse\n';
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', 'specialisering-import-template.csv');
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
});
