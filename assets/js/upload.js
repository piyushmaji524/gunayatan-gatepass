/**
 * File Upload Functionality for Gatepass System
 */

// File upload functionality
$(document).ready(function() {
    // Initialize the file uploader if it exists on the page
    if ($('#fileUploader').length) {
        initFileUploader();
    }
    
    // Initialize signature pad if it exists
    if ($('#signatureCanvas').length) {
        initSignaturePad();
    }
});

/**
 * Initialize file uploader
 */
function initFileUploader() {
    // File input change handler
    $('#fileUploader').on('change', function() {
        const fileInput = this;
        const file = fileInput.files[0];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        // Show progress container
        $('#uploadProgressContainer').removeClass('d-none');
        
        // Validate file
        if (file) {
            // Check file size
            if (file.size > maxSize) {
                showUploadError('File is too large. Maximum size is 5MB.');
                resetFileUploader();
                return;
            }
            
            // Check file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            if (!allowedTypes.includes(file.type)) {
                showUploadError('Invalid file type. Allowed types: JPEG, PNG, GIF, PDF.');
                resetFileUploader();
                return;
            }
            
            // Update file info display
            $('#selectedFileName').text(file.name);
            $('#selectedFileSize').text((file.size / 1024).toFixed(2) + ' KB');
            $('#selectedFileType').text(file.type);
            $('#fileInfoContainer').removeClass('d-none');
            
            // Create form data for upload
            const formData = new FormData();
            formData.append('file', file);
            
            // Perform AJAX upload
            $.ajax({
                url: '../upload.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            $('#uploadProgress').css('width', percent + '%').attr('aria-valuenow', percent);
                            $('#uploadProgressText').text(percent + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    // Hide progress and show success
                    $('#uploadProgress').removeClass('bg-primary').addClass('bg-success');
                    $('#uploadProgressText').text('Upload Complete');
                    
                    // Add the file path to a hidden input field
                    $('#attachmentPath').val(response.file.path);
                    
                    // Show success message
                    showUploadSuccess('File uploaded successfully');
                    
                    // If we're in edit mode, update the attachments list
                    if ($('#attachmentsList').length) {
                        const attachmentItem = `
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-file me-2"></i>
                                    ${response.file.name} (${(response.file.size / 1024).toFixed(2)} KB)
                                </div>
                                <a href="../${response.file.path}" class="btn btn-sm btn-primary" target="_blank">
                                    <i class="fas fa-eye me-1"></i>View
                                </a>
                            </li>
                        `;
                        $('#attachmentsList').append(attachmentItem);
                    }
                },
                error: function(xhr) {
                    showUploadError(xhr.responseText || 'Upload failed. Please try again.');
                    resetFileUploader();
                }
            });
        }
    });
    
    // Reset button click handler
    $('#resetFileUpload').on('click', function(e) {
        e.preventDefault();
        resetFileUploader();
    });
}

/**
 * Reset the file uploader to initial state
 */
function resetFileUploader() {
    $('#fileUploader').val('');
    $('#fileInfoContainer').addClass('d-none');
    $('#uploadProgressContainer').addClass('d-none');
    $('#uploadProgress').css('width', '0%').attr('aria-valuenow', 0).removeClass('bg-success').addClass('bg-primary');
    $('#uploadProgressText').text('0%');
    $('#uploadMessage').addClass('d-none').removeClass('alert-success alert-danger');
    $('#attachmentPath').val('');
}

/**
 * Show upload error message
 */
function showUploadError(message) {
    $('#uploadMessage').removeClass('d-none alert-success').addClass('alert-danger').text(message);
}

/**
 * Show upload success message
 */
function showUploadSuccess(message) {
    $('#uploadMessage').removeClass('d-none alert-danger').addClass('alert-success').text(message);
}

/**
 * Initialize signature pad
 */
function initSignaturePad() {
    const canvas = document.getElementById('signatureCanvas');
    const clearButton = document.getElementById('clearSignature');
    const saveButton = document.getElementById('saveSignature');
    const signatureInput = document.getElementById('signatureData');
    
    const signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgb(255, 255, 255)',
        penColor: 'rgb(0, 0, 0)'
    });
    
    // Clear signature button
    clearButton.addEventListener('click', function() {
        signaturePad.clear();
        signatureInput.value = '';
    });
    
    // Save signature button
    saveButton.addEventListener('click', function() {
        if (signaturePad.isEmpty()) {
            alert('Please provide a signature first.');
        } else {
            const signatureData = signaturePad.toDataURL();
            signatureInput.value = signatureData;
            
            // Show preview
            if ($('#signaturePreview').length) {
                $('#signaturePreview').attr('src', signatureData).removeClass('d-none');
                $('#signaturePreviewContainer').removeClass('d-none');
            }
        }
    });
    
    // Resize canvas to fit container
    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const parentWidth = canvas.parentElement.offsetWidth;
        canvas.width = parentWidth - 2; // 2px for border
        canvas.height = 200;
        canvas.getContext('2d').scale(ratio, ratio);
        signaturePad.clear();
    }
    
    // Set initial size
    resizeCanvas();
    
    // Resize on window resize
    window.addEventListener('resize', resizeCanvas);
}
