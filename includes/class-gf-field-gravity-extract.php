<?php
/**
 * Gravity Extract Custom Field Type
 */

if (!defined('ABSPATH')) {
    exit;
}

class GF_Field_Gravity_Extract extends GF_Field
{

    /**
     * Field type
     */
    public $type = 'gravity_extract';

    /**
     * Get field title for form editor
     */
    public function get_form_editor_field_title()
    {
        return esc_attr__('Gravity Extract', 'gravity-extract');
    }

    /**
     * Get field button info
     */
    public function get_form_editor_button()
    {
        return array(
            'group' => 'advanced_fields',
            'text' => $this->get_form_editor_field_title(),
        );
    }

    /**
     * Get field icon
     */
    public function get_form_editor_field_icon()
    {
        return 'gform-icon--upload';
    }

    /**
     * Get field settings
     */
    public function get_form_editor_field_settings()
    {
        return array(
            'label_setting field_setting',
            'description_setting field_setting',
            'rules_setting',
            'admin_label_setting',
            'label_placement_setting',
            'description_placement_setting',
            'css_class_setting',
            'visibility_setting',
            // Custom settings
            'gravity_extract_api_key_setting',
            'gravity_extract_model_setting',
            'gravity_extract_target_field_setting',
            'gravity_extract_field_mappings_setting',
            'file_extensions_setting field_setting',
        );
    }

    /**
     * Check if field is conditional logic supported
     */
    public function is_conditional_logic_supported()
    {
        return true;
    }

    /**
     * Get field input
     */
    public function get_field_input($form, $value = '', $entry = null)
    {
        $form_id = absint($form['id']);
        $field_id = absint($this->id);
        $id = "input_{$form_id}_{$field_id}";

        $disabled_text = $this->is_form_editor() ? 'disabled="disabled"' : '';
        $tabindex = $this->is_form_editor() ? '' : $this->get_tabindex();
        $required = $this->isRequired ? 'aria-required="true"' : '';

        $max_file_size = '10MB';
        $allowed_extensions = !empty($this->allowedExtensions) ? $this->allowedExtensions : 'jpg, jpeg, png, webp, pdf';
        $allowed_types = $this->get_mime_types($allowed_extensions);

        // Get existing file value
        $file_url = '';
        if (!empty($value)) {
            $file_url = is_array($value) ? (isset($value[0]) ? $value[0] : '') : $value;
        }

        $html = '<div class="gravity-extract-field-wrap" data-field-id="' . esc_attr($field_id) . '">';

        // In form editor, show simple file input like native file upload field
        if ($this->is_form_editor()) {
            $html .= '<div class="ginput_container ginput_container_fileupload">';
            $html .= sprintf(
                '<input type="file" name="input_%s" id="%s" class="medium" disabled="disabled" />',
                $field_id,
                $id
            );
            $html .= '</div>';
            $html .= sprintf(
                '<span class="gfield_description gform_fileupload_rules" id="gfield_upload_rules_%s_%s">%s</span>',
                $form_id,
                $field_id,
                sprintf(esc_html__('Max size: %s. Allowed types: %s', 'gravity-extract'), $max_file_size, str_replace(',', ', ', $allowed_extensions))
            );
        } else {
            // Frontend: Full upload area with drag & drop
            $html .= '<div class="gravity-extract-upload-area">';
            $html .= '<div class="gravity-extract-upload-zone" id="' . esc_attr($id) . '_zone">';
            $html .= '<div class="gravity-extract-upload-icon">';
            $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>';
            $html .= '</div>';
            $html .= '<div class="gravity-extract-upload-text">';
            $html .= '<span class="gravity-extract-upload-primary">' . esc_html__('Drop your document image here', 'gravity-extract') . '</span>';
            $html .= '<span class="gravity-extract-upload-secondary">' . esc_html__('or click to browse', 'gravity-extract') . '</span>';
            $html .= '<span class="gravity-extract-upload-info">' . sprintf(esc_html__('Max size: %s. Allowed types: %s', 'gravity-extract'), $max_file_size, str_replace(',', ', ', $allowed_extensions)) . '</span>';
            $html .= '</div>';
            $html .= '</div>';

            // Hidden file input
            $html .= sprintf(
                '<input type="file" name="input_%s" id="%s" class="gravity-extract-file-input" accept="%s" %s %s />',
                $field_id,
                $id,
                esc_attr($allowed_types),
                $tabindex,
                $required
            );
            $html .= '</div>';
        }

        // Preview area
        $html .= '<div class="gravity-extract-preview" style="display: none;">';
        $html .= '<div class="gravity-extract-preview-image">';
        $html .= '<img src="" alt="Preview" />';
        $html .= '</div>';
        $html .= '<div class="gravity-extract-preview-info">';
        $html .= '<span class="gravity-extract-filename"></span>';
        $html .= '<button type="button" class="gravity-extract-remove">' . esc_html__('Remove', 'gravity-extract') . '</button>';
        $html .= '</div>';
        $html .= '</div>';

        // Status area
        $html .= '<div class="gravity-extract-status" style="display: none;">';
        $html .= '<div class="gravity-extract-spinner"></div>';
        $html .= '<span class="gravity-extract-status-text"></span>';
        $html .= '</div>';

        // Hidden field for storing file path/URL
        $html .= sprintf(
            '<input type="hidden" name="input_%s_file" id="%s_file" class="gravity-extract-file-value" value="%s" />',
            $field_id,
            $id,
            esc_attr($file_url)
        );

        $html .= '</div>';

        return $html;
    }

    /**
     * Get value to save to entry
     */
    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead)
    {
        // The file URL will be set via JavaScript when file is uploaded
        $file_value = rgpost('input_' . $this->id . '_file');

        error_log('Gravity Extract: get_value_save_entry called');
        error_log('Gravity Extract: Field ID: ' . $this->id);
        error_log('Gravity Extract: $value parameter: ' . print_r($value, true));
        error_log('Gravity Extract: $input_name: ' . $input_name);
        error_log('Gravity Extract: $_POST for this field: ' . print_r($_POST['input_' . $this->id . '_file'] ?? 'NOT SET', true));
        error_log('Gravity Extract: $file_value being returned: ' . print_r($file_value, true));
        error_log('Gravity Extract: All $_POST data: ' . print_r($_POST, true));

        return $file_value;
    }

    /**
     * Get value to display in entry detail
     */
    public function get_value_entry_detail($value, $currency = '', $use_text = false, $format = 'html', $media = 'screen')
    {
        if (empty($value)) {
            return '';
        }

        if ($format === 'html') {
            // Display as image
            return sprintf(
                '<a href="%s" target="_blank"><img src="%s" alt="Invoice" style="max-width: 300px; height: auto;" /></a>',
                esc_url($value),
                esc_url($value)
            );
        }

        return $value;
    }

    /**
     * Get value to display in entry list
     */
    public function get_value_entry_list($value, $entry, $field_id, $columns, $form)
    {
        if (empty($value)) {
            return '';
        }

        return sprintf(
            '<a href="%s" target="_blank">%s</a>',
            esc_url($value),
            esc_html__('View Image', 'gravity-extract')
        );
    }

    /**
     * Validate field value
     */
    public function validate($value, $form)
    {
        // Check required
        if ($this->isRequired) {
            $file_value = rgpost('input_' . $this->id . '_file');
            if (empty($file_value) && empty($_FILES['input_' . $this->id]['name'])) {
                $this->failed_validation = true;
                $this->validation_message = empty($this->errorMessage)
                    ? esc_html__('This field is required.', 'gravity-extract')
                    : $this->errorMessage;
            }
        }
    }

    /**
     * Get merge tag value
     */
    public function get_value_merge_tag($value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br)
    {
        return $value;
    }
    /**
     * Get MIME types for extensions
     * 
     * @param string $extensions Comma-separated list of extensions
     * @return string Comma-separated list of MIME types
     */
    protected function get_mime_types($extensions)
    {
        $mime_types = array();
        $exts = array_map('trim', explode(',', $extensions));

        foreach ($exts as $ext) {
            $ext = strtolower(ltrim($ext, '.'));
            switch ($ext) {
                case 'jpg':
                case 'jpeg':
                    $mime_types[] = 'image/jpeg';
                    break;
                case 'png':
                    $mime_types[] = 'image/png';
                    break;
                case 'gif':
                    $mime_types[] = 'image/gif';
                    break;
                case 'webp':
                    $mime_types[] = 'image/webp';
                    break;
                case 'pdf':
                    $mime_types[] = 'application/pdf';
                    break;
                default:
                    // Try to guess using WP function
                    $filetype = wp_check_filetype('test.' . $ext);
                    if (!empty($filetype['type'])) {
                        $mime_types[] = $filetype['type'];
                    }
                    break;
            }
        }

        return implode(', ', array_unique($mime_types));
    }
}
