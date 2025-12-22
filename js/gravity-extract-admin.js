/**
 * Gravity Extract - Admin JavaScript
 * Form editor functionality with mapping profiles
 */

(function ($) {
    'use strict';

    var modelFetchTimeout = null;

    // Mapping profiles configuration
    var invoiceMappingProfiles = {
        supplier_invoice: [
            'full_extraction',
            'supplier_name',
            'supplier_vat_number',
            'supplier_address_line1',
            'supplier_address_line2',
            'supplier_postcode',
            'supplier_city',
            'supplier_country',
            'merchant_address_line1',
            'merchant_address_line2',
            'merchant_postcode',
            'merchant_city',
            'merchant_country',
            'customer_name',
            'customer_vat_number',
            'invoice_number',
            'invoice_date',
            'invoice_due_date',
            'purchase_order_number',
            'currency',
            'amount_subtotal_excl_tax',
            'amount_total_excl_tax',
            'amount_total_tax',
            'amount_total_incl_tax'
        ],
        sales_invoice: [
            'full_extraction',
            'seller_name',
            'seller_vat_number',
            'merchant_address_line1',
            'merchant_address_line2',
            'merchant_postcode',
            'merchant_city',
            'merchant_country',
            'customer_name',
            'customer_email',
            'invoice_number',
            'invoice_date',
            'invoice_due_date',
            'order_reference',
            'currency',
            'amount_subtotal_excl_tax',
            'amount_total_excl_tax',
            'amount_discount',
            'amount_total_tax',
            'amount_total_incl_tax',
            'amount_paid',
            'amount_balance_due'
        ],
        credit_note: [
            'full_extraction',
            'credit_note_number',
            'credit_note_date',
            'original_invoice_number',
            'original_invoice_date',
            'credit_reason',
            'merchant_address_line1',
            'merchant_address_line2',
            'merchant_postcode',
            'merchant_city',
            'merchant_country',
            'currency',
            'credit_subtotal_excl_tax',
            'amount_total_excl_tax',
            'credit_total_tax',
            'credit_total_incl_tax'
        ],
        generic_receipt: [
            'full_extraction',
            'merchant_name',
            'merchant_vat_number',
            'merchant_address_line1',
            'merchant_address_line2',
            'merchant_postcode',
            'merchant_city',
            'merchant_country',
            'receipt_number',
            'receipt_date',
            'receipt_time',
            'payment_method',
            'amount_total_excl_tax',
            'amount_total_tax',
            'amount_total_incl_tax'
        ],
        restaurant_hotel: [
            'full_extraction',
            'merchant_name',
            'merchant_address_line1',
            'merchant_address_line2',
            'merchant_postcode',
            'merchant_city',
            'merchant_country',
            'receipt_date',
            'receipt_time',
            'expense_type',
            'number_of_covers',
            'number_of_nights',
            'tax_rate_1',
            'tax_amount_1',
            'tax_rate_2',
            'tax_amount_2',
            'tip_amount',
            'amount_total_excl_tax',
            'amount_total_incl_tax'
        ],
        mileage_expenses: [
            'full_extraction',
            'starting_point',
            'point_of_arrival',
            'trip_length',
            'toll_amount',
            'gas_amount'
        ],
        minimal_light: [
            'full_extraction',
            'document_type',
            'document_number',
            'document_date',
            'supplier_name',
            'supplier_vat_number',
            'merchant_address_line1',
            'merchant_address_line2',
            'merchant_postcode',
            'merchant_city',
            'merchant_country',
            'amount_total_excl_tax',
            'amount_total_tax',
            'amount_total_incl_tax',
            'currency'
        ],
        bank_account_identification: [
            'full_extraction',
            'bank_user_id_first_name',
            'bank_user_id_last_name',
            'bank_user_id_gender',
            'bank_BIC',
            'bank_IBAN',
            'bank_name',
            'bank_address',
            'bank_city',
            'bank_postal_code',
            'bank_country'
        ]
    };

    // Human-readable labels for keys
    var keyLabels = {
        full_extraction: '★ Full Extraction (all text)',
        supplier_name: 'Supplier Name',
        supplier_vat_number: 'Supplier VAT Number',
        supplier_address_line1: 'Supplier Address',
        supplier_address_line2: 'Supplier Address Line 2',
        supplier_postcode: 'Supplier Postcode',
        supplier_city: 'Supplier City',
        supplier_country: 'Supplier Country',
        customer_name: 'Customer Name',
        customer_vat_number: 'Customer VAT Number',
        customer_email: 'Customer Email',
        invoice_number: 'Invoice Number',
        invoice_date: 'Invoice Date',
        invoice_due_date: 'Due Date',
        purchase_order_number: 'PO Number',
        order_reference: 'Order Reference',
        currency: 'Currency',
        amount_subtotal_excl_tax: 'Subtotal (excl. tax)',
        amount_total_excl_tax: 'Total (excl. tax)',
        amount_total_tax: 'Total Tax',
        amount_total_incl_tax: 'Total (incl. tax)',
        amount_discount: 'Discount',
        amount_paid: 'Amount Paid',
        amount_balance_due: 'Balance Due',
        seller_name: 'Seller Name',
        seller_vat_number: 'Seller VAT Number',
        credit_note_number: 'Credit Note Number',
        credit_note_date: 'Credit Note Date',
        original_invoice_number: 'Original Invoice Number',
        original_invoice_date: 'Original Invoice Date',
        credit_reason: 'Credit Reason',
        credit_subtotal_excl_tax: 'Credit Subtotal (excl. tax)',
        credit_total_tax: 'Credit Total Tax',
        credit_total_incl_tax: 'Credit Total (incl. tax)',
        merchant_name: 'Merchant Name',
        merchant_vat_number: 'Merchant VAT Number',
        merchant_address_line1: 'Merchant Address Line 1',
        merchant_address_line2: 'Merchant Address Line 2',
        merchant_postcode: 'Merchant Postcode',
        merchant_city: 'Merchant City',
        merchant_country: 'Merchant Country',
        receipt_number: 'Receipt Number',
        receipt_date: 'Receipt Date',
        receipt_time: 'Receipt Time',
        payment_method: 'Payment Method',
        expense_type: 'Expense Type',
        number_of_covers: 'Number of Covers',
        number_of_nights: 'Number of Nights',
        tax_rate_1: 'Tax Rate 1',
        tax_amount_1: 'Tax Amount 1',
        tax_rate_2: 'Tax Rate 2',
        tax_amount_2: 'Tax Amount 2',
        tip_amount: 'Tip Amount',
        document_type: 'Document Type',
        document_number: 'Document Number',
        document_date: 'Document Date',
        starting_point: 'Starting Point',
        point_of_arrival: 'Point of Arrival',
        trip_length: 'Trip Length (km/miles)',
        toll_amount: 'Toll Amount',
        gas_amount: 'Gas Amount',
        bank_user_id_first_name: 'Account Holder First Name',
        bank_user_id_last_name: 'Account Holder Last Name',
        bank_user_id_gender: 'Account Holder Gender',
        bank_BIC: 'BIC/SWIFT Code',
        bank_IBAN: 'IBAN',
        bank_name: 'Bank Name',
        bank_address: 'Bank Address',
        bank_city: 'Bank City',
        bank_postal_code: 'Bank Postal Code',
        bank_country: 'Bank Country'
    };

    // Wait for GF form editor to be ready
    $(document).ready(function () {
        if (typeof form === 'undefined') {
            return;
        }

        // Register field settings for gravity_extract
        if (typeof fieldSettings !== 'undefined') {
            fieldSettings.gravity_extract = '.label_setting, .description_setting, .rules_setting, .admin_label_setting, .label_placement_setting, .description_placement_setting, .css_class_setting, .visibility_setting, .gravity_extract_mapping_profile_setting, .gravity_extract_field_mappings_setting';
        }
    });

    // Bind field settings on field select
    $(document).on('gform_load_field_settings', function (event, field, form) {
        if (field.type !== 'gravity_extract') {
            return;
        }

        // Set mapping profile
        var config = field.gravityExtractConfig || {};
        console.log('Gravity Extract: Loading field settings, gravityExtractConfig:', field.gravityExtractConfig);
        console.log('Gravity Extract: Config object:', config);

        // PHP json_encode converts empty {} to [], so we need to fix that
        if (config.mappings && Array.isArray(config.mappings)) {
            console.log('Gravity Extract: Converting loaded mappings from array to object');
            config.mappings = {};
        }

        $('#gravity_extract_mapping_profile').val(config.profile || '');

        // Render mappings table
        gravityExtractRenderMappingsTable(config.profile || '', config.mappings || {});
    });

    // Handle profile change
    window.gravityExtractOnProfileChange = function (profile) {
        var field = GetSelectedField();
        // Clone config using JSON to handle Gravity Forms getter/setter properties
        var config = field.gravityExtractConfig ? JSON.parse(JSON.stringify(field.gravityExtractConfig)) : {};

        config.profile = profile;
        config.mappings = {}; // Reset mappings when profile changes

        SetFieldProperty('gravityExtractConfig', config);
        gravityExtractRenderMappingsTable(profile, {});
    };

    // Render mappings table
    function gravityExtractRenderMappingsTable(profile, mappings) {
        var $container = $('#gravity_extract_mappings_container');

        // Check built-in profiles first, then custom profiles from ProfileManager
        var keys = null;
        if (invoiceMappingProfiles[profile]) {
            keys = invoiceMappingProfiles[profile];
        } else if (typeof gravityExtractProfiles !== 'undefined' &&
            gravityExtractProfiles.customProfiles &&
            gravityExtractProfiles.customProfiles[profile]) {
            // Custom profile - get keys from the fields object
            keys = Object.keys(gravityExtractProfiles.customProfiles[profile].fields || {});
        }

        if (!profile || !keys || keys.length === 0) {
            $container.html('<p class="description">Select a mapping profile above to configure field mappings.</p>');
            return;
        }

        var formFields = getFormFieldsForMapping();

        var html = '<div style="margin-bottom: 5px; position: relative;">';
        html += '<button type="button" class="button button-secondary" style="width: 100%;" onclick="gravityExtractAutomapFields(\'' + profile + '\');">Automap with AI ✨ <span id="gravity-extract-automap-spinner" class="spinner" style="float: none; margin: 0; position: absolute; right: 10px; top: 50%; transform: translateY(-50%);"></span></button>';
        html += '</div>';

        html += '<table class="gravity-extract-mappings-table">';
        html += '<thead><tr><th>Extracted Field</th><th>Target Form Field</th></tr></thead>';
        html += '<tbody>';

        // Get custom profile fields for label lookup
        var customFields = null;
        if (typeof gravityExtractProfiles !== 'undefined' &&
            gravityExtractProfiles.customProfiles &&
            gravityExtractProfiles.customProfiles[profile]) {
            customFields = gravityExtractProfiles.customProfiles[profile].fields;
        }

        keys.forEach(function (key) {
            // For custom profiles, use the custom label if available
            var label = keyLabels[key] || key;
            if (customFields && customFields[key] && customFields[key].label) {
                label = customFields[key].label;
            }
            var selectedFieldId = mappings[key] || '';

            html += '<tr data-key="' + key + '">';
            html += '<td class="mapping-key"><code>' + key + '</code><br><small>' + label + '</small></td>';
            html += '<td class="mapping-target">';
            html += '<select class="gravity-extract-mapping-select" onchange="gravityExtractUpdateMapping(\'' + key + '\', this.value);">';
            html += '<option value="">— Not mapped —</option>';

            formFields.forEach(function (f) {
                var selected = selectedFieldId == f.id ? ' selected' : '';
                html += '<option value="' + f.id + '"' + selected + '>' + f.id + ': ' + f.label + '</option>';
            });

            html += '</select>';
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';

        $container.html(html);
    }

    // Get form fields suitable for mapping
    function getFormFieldsForMapping() {
        var fields = [];

        if (typeof form !== 'undefined' && form.fields) {
            form.fields.forEach(function (field) {
                // Include text, textarea, hidden, number, date, time, select (dropdown), and other common fields
                if (['text', 'textarea', 'hidden', 'number', 'date', 'time', 'select', 'email', 'phone', 'website'].indexOf(field.type) !== -1) {
                    fields.push({
                        id: field.id,
                        label: field.label || 'Field ' + field.id,
                        type: field.type
                    });
                }

                // Address fields - add sub-inputs
                if (field.type === 'address' && field.inputs) {
                    field.inputs.forEach(function (input) {
                        if (!input.isHidden) {
                            fields.push({
                                id: input.id,
                                label: (field.label || 'Address') + ' (' + (input.customLabel || input.label) + ')',
                                type: 'address_sub'
                            });
                        }
                    });
                }
            });
        }

        return fields;
    }

    // Update mapping for a specific key
    window.gravityExtractUpdateMapping = function (key, fieldId) {
        var field = GetSelectedField();
        console.log('Gravity Extract: UpdateMapping called with key:', key, 'fieldId:', fieldId);
        console.log('Gravity Extract: field.gravityExtractConfig BEFORE clone:', field.gravityExtractConfig);

        // Clone config using JSON to handle Gravity Forms getter/setter properties
        var config = field.gravityExtractConfig ? JSON.parse(JSON.stringify(field.gravityExtractConfig)) : {};
        console.log('Gravity Extract: config AFTER clone:', config);

        // PHP json_encode converts empty {} to [], so we need to fix that
        if (!config.mappings || Array.isArray(config.mappings)) {
            console.log('Gravity Extract: Converting mappings from array to object');
            config.mappings = {};
        }

        console.log('Gravity Extract: typeof config.mappings:', typeof config.mappings);
        console.log('Gravity Extract: Array.isArray(config.mappings):', Array.isArray(config.mappings));

        if (fieldId) {
            config.mappings[key] = fieldId;
            console.log('Gravity Extract: Set config.mappings[' + key + '] =', fieldId);
        } else {
            delete config.mappings[key];
            console.log('Gravity Extract: Deleted config.mappings[' + key + ']');
        }

        console.log('Gravity Extract: Final config before SetFieldProperty:', config);
        console.log('Gravity Extract: config.mappings:', config.mappings);
        SetFieldProperty('gravityExtractConfig', config);
        console.log('Gravity Extract: field.gravityExtractConfig AFTER SetFieldProperty:', field.gravityExtractConfig);
    };

    // Fetch models from POE API with debouncing
    window.gravityExtractFetchModels = function (apiKey, selectedModel) {
        var $select = $('#gravity_extract_model');

        if (modelFetchTimeout) {
            clearTimeout(modelFetchTimeout);
        }

        if (!apiKey || apiKey.length < 10) {
            $select.html('<option value="">' + gravityExtractAdmin.strings.selectModel + '</option>');
            return;
        }

        modelFetchTimeout = setTimeout(function () {
            $select.html('<option value="">' + gravityExtractAdmin.strings.fetchingModels + '</option>');

            $.ajax({
                url: gravityExtractAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gravity_extract_get_models',
                    nonce: gravityExtractAdmin.nonce,
                    api_key: apiKey
                },
                success: function (response) {
                    if (response.success && response.data.models && response.data.models.length > 0) {
                        var options = '<option value="">' + gravityExtractAdmin.strings.selectModel + '</option>';
                        var defaultModel = 'Gemini-3.0-Flash'; // Default model

                        response.data.models.forEach(function (model) {
                            // Use selected model, or default to Gemini-2.0-Flash if no model selected
                            var isSelected = '';
                            if (selectedModel) {
                                isSelected = selectedModel === model.id ? ' selected' : '';
                            } else if (model.id === defaultModel) {
                                isSelected = ' selected';
                                // Also set the field property
                                SetFieldProperty('gravityExtractModel', model.id);
                            }
                            options += '<option value="' + model.id + '"' + isSelected + '>' + model.name + '</option>';
                        });

                        $select.html(options);
                    } else {
                        var errorMsg = response.data && response.data.message ? response.data.message : gravityExtractAdmin.strings.noModels;
                        $select.html('<option value="">' + errorMsg + '</option>');
                    }
                },
                error: function () {
                    $select.html('<option value="">' + gravityExtractAdmin.strings.errorFetching + '</option>');
                }
            });
        }, 500);
    };

    // Automap fields using AI
    window.gravityExtractAutomapFields = function (profile) {
        var keys = invoiceMappingProfiles[profile] || [];
        var formFields = getFormFieldsForMapping();
        var $spinner = $('#gravity-extract-automap-spinner');

        if (keys.length === 0 || formFields.length === 0) {
            alert('No extracted keys or form fields available to map.');
            return;
        }

        $spinner.addClass('is-active');

        $.ajax({
            url: gravityExtractAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gravity_extract_automap_fields',
                nonce: gravityExtractAdmin.nonce,
                form_id: form.id,
                keys: keys,
                form_fields: formFields
            },
            success: function (response) {
                $spinner.removeClass('is-active');
                if (response.success && response.data.mappings) {
                    var mappings = response.data.mappings;
                    var updateCount = 0;

                    // Apply mappings
                    Object.keys(mappings).forEach(function (key) {
                        var targetId = mappings[key];
                        // Find the dropdown for this key
                        var $select = $('.gravity-extract-mappings-table tr[data-key="' + key + '"] select');
                        if ($select.length > 0) {
                            $select.val(targetId);
                            // Trigger update
                            gravityExtractUpdateMapping(key, targetId);
                            updateCount++;
                        }
                    });

                    if (updateCount > 0) {
                        alert('Successfully automapped ' + updateCount + ' fields!');
                    } else {
                        alert('AI could not find any confident matches.');
                    }
                } else {
                    alert(response.data.message || 'Error occurred during automapping.');
                }
            },
            error: function () {
                $spinner.removeClass('is-active');
                alert('Request failed. Please try again.');
            }
        });
    };

    // Update mappings table when form changes
    $(document).on('gform_field_added gform_field_deleted', function () {
        var field = GetSelectedField();
        if (field && field.type === 'gravity_extract') {
            var config = field.gravityExtractConfig || {};
            gravityExtractRenderMappingsTable(config.profile || '', config.mappings || {});
        }
    });

})(jQuery);

// Add field settings to form editor
if (typeof fieldSettings !== 'undefined') {
    fieldSettings.gravity_extract = '.gravity_extract_api_key_setting, .gravity_extract_model_setting, .gravity_extract_mapping_profile_setting, .gravity_extract_field_mappings_setting';
}

/**
 * ================================================
 * Profile Manager Module
 * ================================================
 */
(function ($) {
    'use strict';

    var ProfileManager = {
        currentEditSlug: null,
        customProfiles: {},
        masterFields: {},

        /**
         * Initialize the profile manager
         */
        init: function () {
            // Load data from PHP
            if (typeof gravityExtractProfiles !== 'undefined') {
                this.customProfiles = gravityExtractProfiles.customProfiles || {};
                this.masterFields = gravityExtractProfiles.masterFields || {};
            }

            this.bindEvents();
            this.mergeCustomProfilesIntoDropdown();
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function () {
            var self = this;

            // Open modal
            $(document).on('click', '#ge-open-profile-manager', function (e) {
                e.preventDefault();
                self.openModal();
            });

            // Close modal - close button (works even if clicking nested span)
            $(document).on('click', '#ge-close-modal', function (e) {
                e.preventDefault();
                e.stopPropagation();
                self.closeModal();
            });

            // Close modal - clicking overlay background
            $(document).on('click', '#ge-profile-modal-overlay', function (e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });

            // Escape key closes modal
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && $('#ge-profile-modal-overlay').hasClass('active')) {
                    self.closeModal();
                }
            });

            // New profile button
            $(document).on('click', '#ge-new-profile', function () {
                self.showEditor(null);
            });

            // Back to list
            $(document).on('click', '#ge-back-to-list', function () {
                self.showList();
            });

            // Save profile
            $(document).on('click', '#ge-save-profile', function () {
                self.saveProfile();
            });

            // Edit profile
            $(document).on('click', '.ge-edit-profile', function () {
                var slug = $(this).data('slug');
                self.showEditor(slug);
            });

            // Delete profile
            $(document).on('click', '.ge-delete-profile', function () {
                var slug = $(this).data('slug');
                self.deleteProfile(slug);
            });

            // Duplicate profile
            $(document).on('click', '.ge-duplicate-profile', function () {
                var slug = $(this).data('slug');
                self.duplicateProfile(slug);
            });

            // Export profile
            $(document).on('click', '.ge-export-profile', function () {
                var slug = $(this).data('slug');
                self.exportProfile(slug);
            });

            // Import button
            $(document).on('click', '#ge-import-profile', function () {
                $('#ge-import-file').click();
            });

            // Import file change
            $(document).on('change', '#ge-import-file', function (e) {
                self.importProfile(e.target.files[0]);
                $(this).val(''); // Reset input
            });

            // Edit field label (double-click)
            $(document).on('dblclick', '#ge-enabled-fields .ge-field-item', function () {
                self.editFieldLabel($(this));
            });

            // Edit label button
            $(document).on('click', '.ge-edit-label', function (e) {
                e.stopPropagation();
                self.editFieldLabel($(this).closest('.ge-field-item'));
            });

            // Label dialog buttons
            $(document).on('click', '#ge-save-label', function () {
                self.saveLabelEdit();
            });

            $(document).on('click', '#ge-cancel-label', function () {
                self.cancelLabelEdit();
            });

            // Enter key in label input
            $(document).on('keydown', '#ge-custom-label-input', function (e) {
                if (e.key === 'Enter') {
                    self.saveLabelEdit();
                } else if (e.key === 'Escape') {
                    self.cancelLabelEdit();
                }
            });

            // Detect fields button
            $(document).on('click', '#ge-detect-fields', function () {
                $('#ge-sample-file').click();
            });

            // Sample file selected - detect fields
            $(document).on('change', '#ge-sample-file', function (e) {
                if (e.target.files && e.target.files[0]) {
                    self.detectFieldsFromSample(e.target.files[0]);
                }
                $(this).val(''); // Reset input
            });
        },

        /**
         * Open the modal
         */
        openModal: function () {
            $('#ge-profile-modal-overlay').addClass('active');
            this.showList();
            this.renderProfilesList();
        },

        /**
         * Close the modal
         */
        closeModal: function () {
            $('#ge-profile-modal-overlay').removeClass('active');
            this.cancelLabelEdit();
        },

        /**
         * Show the list view
         */
        showList: function () {
            $('#ge-profile-list-view').show();
            $('#ge-profile-editor-view').hide();
            $('#ge-modal-title').text('Manage Mapping Profiles');
            this.currentEditSlug = null;
            this.renderProfilesList();
        },

        /**
         * Show the editor view
         */
        showEditor: function (slug) {
            $('#ge-profile-list-view').hide();
            $('#ge-profile-editor-view').show();

            this.currentEditSlug = slug;

            if (slug && this.customProfiles[slug]) {
                // Editing existing profile
                var profile = this.customProfiles[slug];
                $('#ge-modal-title').text('Edit Profile');
                $('#ge-profile-name').val(profile.name);
                $('#ge-profile-slug').val(slug);
                this.renderFieldsLists(profile.fields || {});
            } else {
                // New profile
                $('#ge-modal-title').text('New Profile');
                $('#ge-profile-name').val('');
                $('#ge-profile-slug').val('');
                this.renderFieldsLists({});
            }

            this.initSortable();
        },

        /**
         * Render the profiles list
         */
        renderProfilesList: function () {
            var $list = $('#ge-profiles-list');
            var profiles = this.customProfiles;
            var keys = Object.keys(profiles);

            if (keys.length === 0) {
                $list.html('<p class="ge-no-profiles">No custom profiles yet. Click "New Profile" to create one.</p>');
                return;
            }

            var html = '';
            keys.forEach(function (slug) {
                var profile = profiles[slug];
                var fieldCount = Object.keys(profile.fields || {}).length;

                html += '<div class="ge-profile-item">';
                html += '  <div class="ge-profile-item-info">';
                html += '    <div class="ge-profile-item-name">' + self.escapeHtml(profile.name) + '</div>';
                html += '    <div class="ge-profile-item-meta">' + fieldCount + ' field' + (fieldCount !== 1 ? 's' : '') + '</div>';
                html += '  </div>';
                html += '  <div class="ge-profile-item-actions">';
                html += '    <button type="button" class="button ge-edit-profile" data-slug="' + slug + '" title="Edit"><span class="dashicons dashicons-edit"></span></button>';
                html += '    <button type="button" class="button ge-duplicate-profile" data-slug="' + slug + '" title="Duplicate"><span class="dashicons dashicons-admin-page"></span></button>';
                html += '    <button type="button" class="button ge-export-profile" data-slug="' + slug + '" title="Export"><span class="dashicons dashicons-download"></span></button>';
                html += '    <button type="button" class="button ge-delete-profile" data-slug="' + slug + '" title="Delete"><span class="dashicons dashicons-trash"></span></button>';
                html += '  </div>';
                html += '</div>';
            });

            $list.html(html);
        },

        /**
         * Render the available and enabled fields lists
         */
        renderFieldsLists: function (enabledFields) {
            var $available = $('#ge-available-fields');
            var $enabled = $('#ge-enabled-fields');
            var masterFields = this.masterFields;
            var enabledKeys = Object.keys(enabledFields);

            // Available fields (not in enabled)
            var availableHtml = '';
            Object.keys(masterFields).forEach(function (key) {
                if (enabledKeys.indexOf(key) === -1) {
                    var field = masterFields[key];
                    availableHtml += ProfileManager.createFieldItem(key, field.label, null);
                }
            });
            $available.html(availableHtml);

            // Enabled fields (in order)
            var enabledHtml = '';
            enabledKeys.forEach(function (key) {
                var defaultLabel = masterFields[key] ? masterFields[key].label : key;
                var customLabel = enabledFields[key] && enabledFields[key].label !== defaultLabel ? enabledFields[key].label : null;
                enabledHtml += ProfileManager.createFieldItem(key, defaultLabel, customLabel);
            });
            $enabled.html(enabledHtml);
        },

        /**
         * Create a field item HTML
         */
        createFieldItem: function (key, defaultLabel, customLabel) {
            var html = '<li class="ge-field-item" data-key="' + key + '" data-default-label="' + this.escapeHtml(defaultLabel) + '">';
            html += '  <div class="ge-field-item-content">';
            html += '    <span class="ge-field-item-label">' + this.escapeHtml(defaultLabel) + '</span>';
            html += '    <span class="ge-field-item-key">' + key + '</span>';
            if (customLabel) {
                html += '    <span class="ge-field-item-custom-label">→ ' + this.escapeHtml(customLabel) + '</span>';
            }
            html += '  </div>';
            html += '  <div class="ge-field-item-actions">';
            html += '    <button type="button" class="ge-edit-label" title="Edit Label"><span class="dashicons dashicons-edit"></span></button>';
            html += '  </div>';
            html += '</li>';
            return html;
        },

        /**
         * Initialize jQuery UI Sortable
         */
        initSortable: function () {
            var self = this;

            $('#ge-available-fields, #ge-enabled-fields').sortable({
                connectWith: '.ge-sortable',
                placeholder: 'ge-field-item ui-sortable-placeholder',
                cursor: 'grabbing',
                tolerance: 'pointer',
                receive: function (event, ui) {
                    // When item is received into enabled list, optionally prompt for label
                    // For now, just use default
                },
                stop: function () {
                    // Update customLabel display after sort
                }
            }).disableSelection();
        },

        /**
         * Detect fields from a sample document using AI
         */
        detectFieldsFromSample: function (file) {
            var self = this;
            var $spinner = $('#ge-detect-spinner');
            var $button = $('#ge-detect-fields');

            // Show loading state
            $spinner.addClass('is-active');
            $button.prop('disabled', true);

            // Get form_id from URL (needed for API settings)
            var formId = new URLSearchParams(window.location.search).get('id') || 0;

            // Create FormData to upload file (server will handle PDF conversion & optimization)
            var formData = new FormData();
            formData.append('action', 'gravity_extract_detect_fields');
            formData.append('nonce', gravityExtractProfiles.nonce);
            formData.append('form_id', formId);
            formData.append('sample_file', file);

            // Upload file via AJAX
            $.ajax({
                url: gravityExtractProfiles.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    $spinner.removeClass('is-active');
                    $button.prop('disabled', false);

                    if (response.success && response.data.fields) {
                        var detectedFields = response.data.fields;

                        // Clear current available fields and add detected ones
                        var $available = $('#ge-available-fields');
                        var html = '';

                        detectedFields.forEach(function (field) {
                            var key = field.key;
                            var label = field.label || key;
                            html += self.createFieldItem(key, label, null);
                        });

                        $available.html(html);

                        // Re-initialize sortable
                        self.initSortable();

                        self.showToast(response.data.message || 'Fields detected!', 'success');
                    } else {
                        self.showToast(response.data.message || 'No fields detected', 'error');
                    }
                },
                error: function () {
                    $spinner.removeClass('is-active');
                    $button.prop('disabled', false);
                    self.showToast('Detection failed. Please try again.', 'error');
                }
            });
        },

        /**
         * Save the current profile
         */
        saveProfile: function () {
            var self = this;
            var name = $('#ge-profile-name').val().trim();
            var slug = $('#ge-profile-slug').val();

            if (!name) {
                this.showToast(gravityExtractProfiles.i18n.nameRequired, 'error');
                $('#ge-profile-name').focus();
                return;
            }

            // Collect enabled fields
            var fields = {};
            $('#ge-enabled-fields .ge-field-item').each(function () {
                var $item = $(this);
                var key = $item.data('key');
                var defaultLabel = $item.data('default-label');
                var $customLabel = $item.find('.ge-field-item-custom-label');
                var customLabel = $customLabel.length ? $customLabel.text().replace('→ ', '') : defaultLabel;

                fields[key] = { label: customLabel };
            });

            if (Object.keys(fields).length === 0) {
                this.showToast(gravityExtractProfiles.i18n.noFieldsEnabled, 'error');
                return;
            }

            // AJAX save
            $.ajax({
                url: gravityExtractProfiles.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gravity_extract_save_profile',
                    nonce: gravityExtractProfiles.nonce,
                    name: name,
                    slug: slug,
                    fields: JSON.stringify(fields)
                },
                success: function (response) {
                    if (response.success) {
                        self.customProfiles[response.data.slug] = response.data.profile;
                        self.showToast(gravityExtractProfiles.i18n.profileSaved, 'success');
                        self.mergeCustomProfilesIntoDropdown();
                        self.showList();
                    } else {
                        self.showToast(response.data.message || gravityExtractProfiles.i18n.error, 'error');
                    }
                },
                error: function () {
                    self.showToast(gravityExtractProfiles.i18n.error, 'error');
                }
            });
        },

        /**
         * Delete a profile
         */
        deleteProfile: function (slug) {
            var self = this;

            if (!confirm(gravityExtractProfiles.i18n.confirmDelete)) {
                return;
            }

            $.ajax({
                url: gravityExtractProfiles.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gravity_extract_delete_profile',
                    nonce: gravityExtractProfiles.nonce,
                    slug: slug
                },
                success: function (response) {
                    if (response.success) {
                        delete self.customProfiles[slug];
                        self.showToast(gravityExtractProfiles.i18n.profileDeleted, 'success');
                        self.mergeCustomProfilesIntoDropdown();
                        self.renderProfilesList();
                    } else {
                        self.showToast(response.data.message || gravityExtractProfiles.i18n.error, 'error');
                    }
                },
                error: function () {
                    self.showToast(gravityExtractProfiles.i18n.error, 'error');
                }
            });
        },

        /**
         * Duplicate a profile
         */
        duplicateProfile: function (slug) {
            var self = this;
            var profile = this.customProfiles[slug];
            if (!profile) return;

            var newName = prompt(gravityExtractProfiles.i18n.enterNewName, profile.name + ' (Copy)');
            if (!newName) return;

            $.ajax({
                url: gravityExtractProfiles.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gravity_extract_duplicate_profile',
                    nonce: gravityExtractProfiles.nonce,
                    slug: slug,
                    new_name: newName
                },
                success: function (response) {
                    if (response.success) {
                        self.customProfiles[response.data.slug] = response.data.profile;
                        self.showToast(gravityExtractProfiles.i18n.profileSaved, 'success');
                        self.mergeCustomProfilesIntoDropdown();
                        self.renderProfilesList();
                    } else {
                        self.showToast(response.data.message || gravityExtractProfiles.i18n.error, 'error');
                    }
                },
                error: function () {
                    self.showToast(gravityExtractProfiles.i18n.error, 'error');
                }
            });
        },

        /**
         * Export a profile as JSON
         */
        exportProfile: function (slug) {
            var profile = this.customProfiles[slug];
            if (!profile) return;

            var data = {
                name: profile.name,
                fields: profile.fields
            };

            var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = slug + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        },

        /**
         * Import a profile from JSON file
         */
        importProfile: function (file) {
            var self = this;

            if (!file) return;

            var reader = new FileReader();
            reader.onload = function (e) {
                try {
                    var data = JSON.parse(e.target.result);

                    $.ajax({
                        url: gravityExtractProfiles.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'gravity_extract_import_profile',
                            nonce: gravityExtractProfiles.nonce,
                            json_data: JSON.stringify(data)
                        },
                        success: function (response) {
                            if (response.success) {
                                self.customProfiles[response.data.slug] = response.data.profile;
                                self.showToast(gravityExtractProfiles.i18n.profileImported, 'success');
                                self.mergeCustomProfilesIntoDropdown();
                                self.renderProfilesList();
                            } else {
                                self.showToast(response.data.message || gravityExtractProfiles.i18n.error, 'error');
                            }
                        },
                        error: function () {
                            self.showToast(gravityExtractProfiles.i18n.error, 'error');
                        }
                    });
                } catch (err) {
                    self.showToast('Invalid JSON file', 'error');
                }
            };
            reader.readAsText(file);
        },

        /**
         * Edit field label
         */
        editFieldLabel: function ($item) {
            var $dialog = $('#ge-label-edit-dialog');
            var $input = $('#ge-custom-label-input');
            var offset = $item.offset();

            var currentLabel = $item.find('.ge-field-item-custom-label').text().replace('→ ', '') ||
                $item.data('default-label');

            $input.val(currentLabel);
            $dialog.data('target', $item);
            $dialog.css({
                top: offset.top + $item.outerHeight() + 5,
                left: offset.left
            }).show();
            $input.focus().select();
        },

        /**
         * Save label edit
         */
        saveLabelEdit: function () {
            var $dialog = $('#ge-label-edit-dialog');
            var $target = $dialog.data('target');
            var newLabel = $('#ge-custom-label-input').val().trim();
            var defaultLabel = $target.data('default-label');

            // Remove existing custom label
            $target.find('.ge-field-item-custom-label').remove();

            // Add new custom label if different from default
            if (newLabel && newLabel !== defaultLabel) {
                $target.find('.ge-field-item-content').append(
                    '<span class="ge-field-item-custom-label">→ ' + this.escapeHtml(newLabel) + '</span>'
                );
            }

            $dialog.hide();
        },

        /**
         * Cancel label edit
         */
        cancelLabelEdit: function () {
            $('#ge-label-edit-dialog').hide();
        },

        /**
         * Merge custom profiles into the mapping profile dropdown
         */
        mergeCustomProfilesIntoDropdown: function () {
            var $select = $('#gravity_extract_mapping_profile');
            if ($select.length === 0) return;

            // Remove existing custom profile options
            $select.find('option[data-custom="true"]').remove();

            // Add custom profiles
            var self = this;
            Object.keys(this.customProfiles).forEach(function (slug) {
                var profile = self.customProfiles[slug];
                $select.append(
                    '<option value="' + slug + '" data-custom="true">' +
                    self.escapeHtml(profile.name) + ' (Custom)</option>'
                );
            });

            // Also update the invoiceMappingProfiles object for field mappings
            Object.keys(this.customProfiles).forEach(function (slug) {
                var profile = self.customProfiles[slug];
                window.invoiceMappingProfiles = window.invoiceMappingProfiles || {};
                window.invoiceMappingProfiles[slug] = Object.keys(profile.fields || {});
            });
        },

        /**
         * Show toast notification
         */
        showToast: function (message, type) {
            var $toast = $('<div class="ge-toast"></div>');
            $toast.addClass(type || '').text(message);
            $('body').append($toast);

            setTimeout(function () {
                $toast.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * Escape HTML
         */
        escapeHtml: function (text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Store reference to self for use in callbacks
    var self = ProfileManager;

    // Initialize on document ready
    $(document).ready(function () {
        ProfileManager.init();
    });

    // Expose for debugging
    window.GravityExtractProfileManager = ProfileManager;

})(jQuery);
