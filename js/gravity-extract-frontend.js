/**
 * Gravity Extract - Frontend JavaScript
 * Handles file upload, AI analysis, and field population using mappings
 */

(function ($) {
    'use strict';

    var GravityExtract = {
        init: function () {
            if (typeof gravityExtractFrontend === 'undefined') {
                return;
            }

            this.bindEvents();
        },

        bindEvents: function () {
            var self = this;

            // File input change
            $(document).on('change', '.gravity-extract-file-input', function (e) {
                self.handleFileSelect(e.target);
            });

            // Drag and drop
            $(document).on('dragover dragenter', '.gravity-extract-upload-zone', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });

            $(document).on('dragleave dragend', '.gravity-extract-upload-zone', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });

            $(document).on('drop', '.gravity-extract-upload-zone', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');

                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    var $wrap = $(this).closest('.gravity-extract-field-wrap');
                    var $input = $wrap.find('.gravity-extract-file-input');

                    var dataTransfer = new DataTransfer();
                    dataTransfer.items.add(files[0]);
                    $input[0].files = dataTransfer.files;

                    self.handleFileSelect($input[0]);
                }
            });

            // Click on upload zone triggers file input
            $(document).on('click', '.gravity-extract-upload-zone', function () {
                $(this).closest('.gravity-extract-field-wrap').find('.gravity-extract-file-input').click();
            });

            // Remove button
            $(document).on('click', '.gravity-extract-remove', function (e) {
                e.preventDefault();
                var $wrap = $(this).closest('.gravity-extract-field-wrap');
                self.resetField($wrap);
            });

            // Log field values before form submission
            $(document).on('submit', 'form.gform_wrapper form', function (e) {
                console.log('Gravity Extract: Form submitting');
                $('.gravity-extract-file-value').each(function () {
                    var fieldId = $(this).attr('id');
                    var value = $(this).val();
                    console.log('Gravity Extract: Hidden field', fieldId, '=', value);
                });
            });
        },

        handleFileSelect: function (input) {
            var self = this;
            var $wrap = $(input).closest('.gravity-extract-field-wrap');
            var file = input.files[0];

            if (!file) {
                return;
            }

            // Validate file type
            var validTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
            if (validTypes.indexOf(file.type) === -1) {
                alert(gravityExtractFrontend.strings.invalidFile);
                this.resetField($wrap);
                return;
            }

            // Validate file size (10MB max)
            var maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('File size exceeds 10MB limit');
                this.resetField($wrap);
                return;
            }

            // Show preview
            this.showPreview($wrap, file);

            console.log('Gravity Extract: Starting file upload process');

            // Show loading indicator
            var $status = $wrap.find('.gravity-extract-status');
            var $statusText = $status.find('.gravity-extract-status-text');
            $status.show().removeClass('success error').addClass('loading');
            $statusText.text('Processing image...');

            // Upload file to server first
            this.uploadFile($wrap, file, function (fileUrl, extractedData, mappings) {
                console.log('Gravity Extract: File uploaded successfully, URL:', fileUrl);

                // Save file URL to hidden field
                var $hiddenField = $wrap.find('.gravity-extract-file-value');
                console.log('Gravity Extract: Found hidden field elements:', $hiddenField.length);
                console.log('Gravity Extract: Hidden field ID:', $hiddenField.attr('id'));
                console.log('Gravity Extract: Hidden field name:', $hiddenField.attr('name'));
                console.log('Gravity Extract: Current value before set:', $hiddenField.val());

                $hiddenField.val(fileUrl);

                console.log('Gravity Extract: Value after set:', $hiddenField.val());
                console.log('Gravity Extract: File URL saved to hidden field');

                // CRITICAL: Clear the file input to prevent Gravity Forms from processing it again
                var $fileInput = $wrap.find('.gravity-extract-file-input');
                $fileInput.val('');
                console.log('Gravity Extract: Cleared file input to prevent re-processing');

                // Verify it stuck
                setTimeout(function () {
                    console.log('Gravity Extract: Value after 100ms:', $hiddenField.val());
                }, 100);

                // If server returned extracted data (from analysis after crop), populate fields
                if (extractedData && mappings) {
                    console.log('Gravity Extract: Server analyzed the image, populating fields');
                    self.populateMappedFields(gravityExtractFrontend.formId, extractedData, mappings);

                    // Show success status
                    var $status = $wrap.find('.gravity-extract-status');
                    var $statusText = $status.find('.gravity-extract-status-text');
                    $status.show().removeClass('loading').addClass('success');
                    $statusText.text(gravityExtractFrontend.strings.complete);

                    // Hide status after delay
                    setTimeout(function () {
                        $status.fadeOut();
                    }, 3000);
                }

                // Update preview to show actual uploaded image (especially for PDFs converted to JPEG)
                var $preview = $wrap.find('.gravity-extract-preview');
                var $img = $preview.find('img');
                if ($img.length && fileUrl) {
                    $img.attr('src', fileUrl);
                    console.log('Gravity Extract: Updated preview with server file URL:', fileUrl);
                }

                // If no server analysis, run client-side analysis
                if (!extractedData || !mappings) {
                    console.log('Gravity Extract: No server analysis, running client-side analysis');
                    // Fallback: analyze client-side if server didn't return data
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        var base64 = e.target.result;
                        self.analyzeImage($wrap, base64);
                    };
                    reader.readAsDataURL(file);
                }
            });
        },

        uploadFile: function ($wrap, file, callback) {
            console.log('Gravity Extract: uploadFile called with file:', file.name);

            // Prevent duplicate uploads
            if ($wrap.data('uploading')) {
                console.log('Gravity Extract: Upload already in progress, skipping duplicate request');
                return;
            }

            $wrap.data('uploading', true);

            var fieldId = $wrap.data('field-id');
            var formId = gravityExtractFrontend.formId;

            var formData = new FormData();
            formData.append('action', 'gravity_extract_upload_file');
            formData.append('nonce', gravityExtractFrontend.nonce);
            formData.append('file', file);
            formData.append('form_id', formId);
            formData.append('field_id', fieldId);

            console.log('Gravity Extract: Sending AJAX upload request');

            $.ajax({
                url: gravityExtractFrontend.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    console.log('Gravity Extract: Upload response:', response);
                    $wrap.data('uploading', false);  // Clear upload lock
                    if (response.success && response.data.url) {
                        callback(response.data.url, response.data.extracted_data, response.data.mappings);
                    } else {
                        console.error('File upload failed:', response);
                        alert('File upload failed. Please try again.');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('File upload request failed:', status, error);
                    console.error('XHR:', xhr);
                    $wrap.data('uploading', false);  // Clear upload lock
                    alert('File upload failed. Please try again.');
                }
            });
        },

        showPreview: function ($wrap, file) {
            var $zone = $wrap.find('.gravity-extract-upload-area');
            var $preview = $wrap.find('.gravity-extract-preview');
            var $img = $preview.find('img');
            var $filename = $preview.find('.gravity-extract-filename');

            var objectUrl = URL.createObjectURL(file);
            $img.attr('src', objectUrl);
            $filename.text(file.name + ' (' + this.formatFileSize(file.size) + ')');

            $zone.hide();
            $preview.show();
        },

        analyzeImage: function ($wrap, base64) {
            var self = this;
            var fieldId = $wrap.data('field-id');
            var formId = gravityExtractFrontend.formId;
            var fieldConfig = gravityExtractFrontend.fields[fieldId];

            if (!fieldConfig || !fieldConfig.hasApiKey) {
                console.error('Gravity Extract: Field not configured');
                return;
            }

            // Show loading status
            var $status = $wrap.find('.gravity-extract-status');
            var $statusText = $status.find('.gravity-extract-status-text');
            $status.show().addClass('loading');
            $statusText.text(gravityExtractFrontend.strings.analyzing);

            // Send to server for analysis
            $.ajax({
                url: gravityExtractFrontend.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gravity_extract_analyze',
                    nonce: gravityExtractFrontend.nonce,
                    form_id: formId,
                    field_id: fieldId,
                    image_base64: base64
                },
                success: function (response) {
                    $status.removeClass('loading');

                    if (response.success) {
                        $statusText.text(gravityExtractFrontend.strings.complete);
                        $status.addClass('success');

                        // Populate mapped fields
                        if (response.data.extracted_data && response.data.mappings) {
                            self.populateMappedFields(formId, response.data.extracted_data, response.data.mappings);
                        }

                        // Note: File URL is already set during upload, don't overwrite it

                        // Hide status after a delay
                        setTimeout(function () {
                            $status.fadeOut();
                        }, 3000);
                    } else {
                        $statusText.text(gravityExtractFrontend.strings.error + ': ' + (response.data.message || 'Unknown error'));
                        $status.addClass('error');
                    }
                },
                error: function () {
                    $status.removeClass('loading').addClass('error');
                    $statusText.text(gravityExtractFrontend.strings.error);
                }
            });
        },

        populateMappedFields: function (formId, extractedData, mappings) {
            var self = this;
            var populatedCount = 0;

            // Iterate through mappings and populate fields
            Object.keys(mappings).forEach(function (key) {
                var targetFieldId = mappings[key];
                var value = extractedData[key];

                if (targetFieldId && value !== null && value !== undefined && value !== '') {
                    var populated = self.populateField(formId, targetFieldId, value);
                    if (populated) {
                        populatedCount++;
                    }
                }
            });

            console.log('Gravity Extract: Populated ' + populatedCount + ' fields');
        },

        populateField: function (formId, fieldId, value) {
            var self = this;

            // Try to find the field container first to determine field type
            var $fieldContainer = $('#field_' + formId + '_' + fieldId);

            // Check for time field (has hour/minute sub-inputs)
            var $hourInput = $('#input_' + formId + '_' + fieldId + '_1');
            var $minuteInput = $('#input_' + formId + '_' + fieldId + '_2');

            if ($hourInput.length > 0 && $minuteInput.length > 0) {
                // This is a time field - parse the time value
                return self.populateTimeField($hourInput, $minuteInput, value);
            }

            // Check for dropdown/select field
            var $selectField = $('#input_' + formId + '_' + fieldId);
            if ($selectField.is('select')) {
                return self.populateDropdownField($selectField, value);
            }

            // Standard text/textarea field
            var $targetInput = self.findFormField(formId, fieldId);
            if ($targetInput.length > 0) {
                $targetInput.val(value);
                $targetInput.trigger('change');
                self.highlightField($targetInput);
                return true;
            }

            return false;
        },

        populateTimeField: function ($hourInput, $minuteInput, value) {
            var self = this;
            // Try to parse time from various formats: "13:02:05", "1:30 PM", "13:02", etc.
            var timeParts = null;

            // Try HH:MM:SS or HH:MM format
            var match = String(value).match(/(\d{1,2})[:h](\d{2})(?:[:m](\d{2}))?\s*(AM|PM)?/i);
            if (match) {
                var hours = parseInt(match[1], 10);
                var minutes = match[2];
                var ampm = match[4];

                // Convert to 24-hour if needed, or handle AM/PM for 12-hour fields
                if (ampm) {
                    if (ampm.toUpperCase() === 'PM' && hours < 12) {
                        hours += 12;
                    } else if (ampm.toUpperCase() === 'AM' && hours === 12) {
                        hours = 0;
                    }
                }

                // Check if the hour input is a select (12-hour format) or input (24-hour)
                if ($hourInput.is('select')) {
                    // 12-hour format with AM/PM
                    var displayHour = hours % 12;
                    if (displayHour === 0) displayHour = 12;
                    $hourInput.val(displayHour);

                    // Set AM/PM if there's a third input
                    var $ampmInput = $hourInput.closest('.gfield').find('select').eq(2);
                    if ($ampmInput.length > 0) {
                        $ampmInput.val(hours >= 12 ? 'pm' : 'am');
                    }
                } else {
                    // 24-hour format or text input
                    $hourInput.val(hours.toString().padStart(2, '0'));
                }

                $minuteInput.val(minutes);
                $hourInput.trigger('change');
                $minuteInput.trigger('change');
                self.highlightField($hourInput);
                self.highlightField($minuteInput);
                return true;
            }

            return false;
        },

        populateDropdownField: function ($selectField, value) {
            var self = this;
            var valueLower = String(value).toLowerCase().trim();
            var matched = false;

            // First try exact value match
            $selectField.find('option').each(function () {
                if ($(this).val().toLowerCase() === valueLower ||
                    $(this).text().toLowerCase() === valueLower) {
                    $selectField.val($(this).val());
                    matched = true;
                    return false; // break
                }
            });

            // If no exact match, try partial/contains match
            if (!matched) {
                $selectField.find('option').each(function () {
                    var optionText = $(this).text().toLowerCase();
                    var optionVal = $(this).val().toLowerCase();

                    // Check if value contains option or option contains value
                    if (valueLower.indexOf(optionText) !== -1 || optionText.indexOf(valueLower) !== -1 ||
                        valueLower.indexOf(optionVal) !== -1 || optionVal.indexOf(valueLower) !== -1) {
                        $selectField.val($(this).val());
                        matched = true;
                        return false; // break
                    }
                });
            }

            if (matched) {
                $selectField.trigger('change');
                self.highlightField($selectField);
            }

            return matched;
        },

        highlightField: function ($field) {
            $field.addClass('gravity-extract-highlight');
            setTimeout(function () {
                $field.removeClass('gravity-extract-highlight');
            }, 1500);
        },

        findFormField: function (formId, fieldId) {
            // Handle fractional IDs (e.g. 2.3 for address city)
            // GF uses dots in name (input_2.3) but underscores in ID (input_1_2_3)
            var fieldIdString = String(fieldId);
            var safeId = fieldIdString.replace('.', '_');

            // Try different input ID patterns used by Gravity Forms
            var selectors = [
                '#input_' + formId + '_' + safeId,
                '#input_' + safeId,
                'input[name="input_' + fieldIdString + '"]',
                'textarea[name="input_' + fieldIdString + '"]',
                'select[name="input_' + fieldIdString + '"]',
                '#gform_' + formId + ' #input_' + formId + '_' + safeId
            ];

            for (var i = 0; i < selectors.length; i++) {
                var $el = $(selectors[i]);
                if ($el.length > 0) {
                    return $el;
                }
            }

            return $();
        },

        resetField: function ($wrap) {
            var $input = $wrap.find('.gravity-extract-file-input');
            var $zone = $wrap.find('.gravity-extract-upload-area');
            var $preview = $wrap.find('.gravity-extract-preview');
            var $status = $wrap.find('.gravity-extract-status');
            var $fileValue = $wrap.find('.gravity-extract-file-value');

            $input.val('');
            $preview.hide();
            $status.hide().removeClass('loading success error');
            $zone.show();
            $fileValue.val('');
        },

        formatFileSize: function (bytes) {
            if (bytes === 0) return '0 Bytes';
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    };

    // Initialize when document is ready
    $(document).ready(function () {
        GravityExtract.init();
    });

    // Reinitialize on AJAX form render
    $(document).on('gform_post_render', function () {
        GravityExtract.init();
    });

})(jQuery);
