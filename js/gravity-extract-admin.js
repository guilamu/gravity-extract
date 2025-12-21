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
        gas_amount: 'Gas Amount'
    };

    // Wait for GF form editor to be ready
    $(document).ready(function () {
        if (typeof form === 'undefined') {
            return;
        }

        // Register field settings for gravity_extract
        if (typeof fieldSettings !== 'undefined') {
            fieldSettings.gravity_extract = '.label_setting, .description_setting, .rules_setting, .admin_label_setting, .label_placement_setting, .description_placement_setting, .css_class_setting, .visibility_setting, .gravity_extract_api_key_setting, .gravity_extract_model_setting, .gravity_extract_target_field_setting, .file_extensions_setting, .gravity_extract_mapping_profile_setting, .gravity_extract_field_mappings_setting';
        }
    });

    // Bind field settings on field select
    $(document).on('gform_load_field_settings', function (event, field, form) {
        if (field.type !== 'gravity_extract') {
            return;
        }

        // Set API key value
        $('#gravity_extract_api_key').val(field.gravityExtractApiKey || '');

        // Populate model dropdown
        if (field.gravityExtractApiKey) {
            gravityExtractFetchModels(field.gravityExtractApiKey, field.gravityExtractModel);
        } else {
            $('#gravity_extract_model').html(
                '<option value="">' + gravityExtractAdmin.strings.selectModel + '</option>'
            );
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

        if (!profile || !invoiceMappingProfiles[profile]) {
            $container.html('<p class="description">Select a mapping profile above to configure field mappings.</p>');
            return;
        }

        var keys = invoiceMappingProfiles[profile];
        var formFields = getFormFieldsForMapping();

        var html = '<div style="margin-bottom: 10px;">';
        html += '<button type="button" class="button button-secondary" style="width: 100%;" onclick="gravityExtractAutomapFields(\'' + profile + '\');">Automap with AI ✨</button>';
        html += '<span id="gravity-extract-automap-spinner" class="spinner" style="float: none;"></span>';
        html += '</div>';

        html += '<table class="gravity-extract-mappings-table">';
        html += '<thead><tr><th>Extracted Field</th><th>Target Form Field</th></tr></thead>';
        html += '<tbody>';

        keys.forEach(function (key) {
            var label = keyLabels[key] || key;
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
        var field = GetSelectedField();
        var apiKey = field.gravityExtractApiKey;
        var model = field.gravityExtractModel;

        if (!apiKey || !model) {
            alert(gravityExtractAdmin.strings.apiKeyRequired || 'Please configure API Key and Model first.');
            return;
        }

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
                api_key: apiKey,
                model: model,
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
