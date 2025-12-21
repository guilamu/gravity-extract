<?php
/**
 * Main Gravity Extract Plugin Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Gravity_Extract
{

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->register_field();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies()
    {
        require_once GRAVITY_EXTRACT_PATH . 'includes/class-poe-api-service.php';
        require_once GRAVITY_EXTRACT_PATH . 'includes/class-gf-field-gravity-extract.php';
    }

    /**
     * Register the custom field type
     */
    private function register_field()
    {
        GF_Fields::register(new GF_Field_Gravity_Extract());
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Admin scripts and styles
        add_action('gform_editor_js_set_default_values', array($this, 'field_default_values'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Frontend scripts and styles
        add_action('gform_enqueue_scripts', array($this, 'enqueue_frontend_scripts'), 10, 2);

        // AJAX handlers
        add_action('wp_ajax_gravity_extract_get_models', array($this, 'ajax_get_models'));
        add_action('wp_ajax_gravity_extract_analyze', array($this, 'ajax_analyze_image'));
        add_action('wp_ajax_nopriv_gravity_extract_analyze', array($this, 'ajax_analyze_image'));
        add_action('wp_ajax_gravity_extract_upload_file', array($this, 'ajax_upload_file'));
        add_action('wp_ajax_nopriv_gravity_extract_upload_file', array($this, 'ajax_upload_file'));
        add_action('wp_ajax_gravity_extract_automap_fields', array($this, 'ajax_automap_fields'));

        // Pre-submission hook for auto-crop
        add_filter('gform_pre_submission', array($this, 'process_auto_crop'));

        // Field settings
        add_action('gform_field_standard_settings', array($this, 'field_settings'), 10, 2);
        add_action('gform_field_advanced_settings', array($this, 'field_advanced_settings'), 10, 2);

        // Tooltips
        add_filter('gform_tooltips', array($this, 'add_tooltips'));
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        if (!in_array($hook, array('toplevel_page_gf_edit_forms', 'forms_page_gf_edit_forms'))) {
            // Check for form editor
            $page = rgget('page');
            $view = rgget('view');
            if ($page !== 'gf_edit_forms' || $view !== 'settings') {
                if ($page !== 'gf_edit_forms' || !empty($view)) {
                    // Not form editor, but check if it's the form list
                }
            }
        }

        // Only load on form editor
        if (GFForms::get_page() !== 'form_editor') {
            return;
        }

        wp_enqueue_style(
            'gravity-extract-admin',
            GRAVITY_EXTRACT_URL . 'css/gravity-extract-admin.css',
            array(),
            GRAVITY_EXTRACT_VERSION
        );

        wp_enqueue_script(
            'gravity-extract-admin',
            GRAVITY_EXTRACT_URL . 'js/gravity-extract-admin.js',
            array('jquery', 'gform_form_editor'),
            GRAVITY_EXTRACT_VERSION,
            true
        );

        wp_localize_script('gravity-extract-admin', 'gravityExtractAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gravity_extract_admin'),
            'strings' => array(
                'fetchingModels' => __('Fetching models...', 'gravity-extract'),
                'selectModel' => __('Select a model', 'gravity-extract'),
                'noModels' => __('No image-capable models found', 'gravity-extract'),
                'errorFetching' => __('Error fetching models', 'gravity-extract'),
                'selectTargetField' => __('Select target field', 'gravity-extract'),
                'noTextFields' => __('No text/paragraph fields in form', 'gravity-extract'),
            ),
        ));
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts($form, $ajax)
    {
        // Check if form has our field type
        $has_field = false;
        foreach ($form['fields'] as $field) {
            if ($field->type === 'gravity_extract') {
                $has_field = true;
                break;
            }
        }

        if (!$has_field) {
            return;
        }

        wp_enqueue_style(
            'gravity-extract-frontend',
            GRAVITY_EXTRACT_URL . 'css/gravity-extract-frontend.css',
            array(),
            GRAVITY_EXTRACT_VERSION
        );

        wp_enqueue_script(
            'gravity-extract-frontend',
            GRAVITY_EXTRACT_URL . 'js/gravity-extract-frontend.js',
            array('jquery'),
            GRAVITY_EXTRACT_VERSION,
            true
        );

        // Collect field settings
        $fields_config = array();
        foreach ($form['fields'] as $field) {
            if ($field->type === 'gravity_extract') {
                $config = $field->gravityExtractConfig ? $field->gravityExtractConfig : array();
                $fields_config[$field->id] = array(
                    'model' => $field->gravityExtractModel,
                    'hasApiKey' => !empty($field->gravityExtractApiKey),
                    'profile' => isset($config['profile']) ? $config['profile'] : '',
                    'mappings' => isset($config['mappings']) ? $config['mappings'] : array(),
                );
            }
        }

        wp_localize_script('gravity-extract-frontend', 'gravityExtractFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gravity_extract_frontend'),
            'formId' => $form['id'],
            'fields' => $fields_config,
            'strings' => array(
                'uploading' => __('Uploading...', 'gravity-extract'),
                'analyzing' => __('Analyzing document with AI...', 'gravity-extract'),
                'complete' => __('Extraction complete!', 'gravity-extract'),
                'error' => __('Error analyzing image', 'gravity-extract'),
                'invalidFile' => __('Please upload an image file (JPEG, PNG, or WebP)', 'gravity-extract'),
            ),
        ));
    }

    /**
     * Field default values in editor
     */
    public function field_default_values()
    {
        ?>
        case 'gravity_extract':
        if (!field.label) {
        field.label = <?php echo json_encode(__('Gravity Extract', 'gravity-extract')); ?>;
        }
        break;
        <?php
    }

    /**
     * Standard field settings
     */
    public function field_settings($position, $form_id)
    {
        if ($position !== 25) {
            return;
        }
        ?>
        <li class="gravity_extract_accepted_types_setting field_setting">
            <label class="section_label" for="gravity_extract_accepted_types">
                <?php esc_html_e('Accepted File Types', 'gravity-extract'); ?>
            </label>
            <span
                class="description"><?php esc_html_e('Only image files (JPEG, PNG, WebP) are accepted for AI analysis.', 'gravity-extract'); ?></span>
        </li>
        <?php
    }

    /**
     * Advanced field settings
     */
    public function field_advanced_settings($position, $form_id)
    {
        if ($position !== 50) {
            return;
        }
        ?>
        <li class="gravity_extract_api_key_setting field_setting">
            <label class="section_label" for="gravity_extract_api_key">
                <?php esc_html_e('POE API Key', 'gravity-extract'); ?>
                <?php gform_tooltip('gravity_extract_api_key'); ?>
            </label>
            <input type="password" id="gravity_extract_api_key" class="fieldwidth-1" style="width: 100%;"
                onchange="SetFieldProperty('gravityExtractApiKey', this.value);"
                onkeyup="SetFieldProperty('gravityExtractApiKey', this.value); gravityExtractFetchModels(this.value);" />
        </li>

        <li class="gravity_extract_model_setting field_setting">
            <label class="section_label" for="gravity_extract_model">
                <?php esc_html_e('AI Model', 'gravity-extract'); ?>
                <?php gform_tooltip('gravity_extract_model'); ?>
            </label>
            <select id="gravity_extract_model" class="fieldwidth-3"
                onchange="SetFieldProperty('gravityExtractModel', this.value);">
                <option value=""><?php esc_html_e('Enter API key first', 'gravity-extract'); ?></option>
            </select>
        </li>

        <li class="gravity_extract_mapping_profile_setting field_setting">
            <label class="section_label" for="gravity_extract_mapping_profile">
                <?php esc_html_e('Mapping Profile', 'gravity-extract'); ?>
                <?php gform_tooltip('gravity_extract_mapping_profile'); ?>
            </label>
            <select id="gravity_extract_mapping_profile" class="fieldwidth-3"
                onchange="gravityExtractOnProfileChange(this.value);">
                <option value=""><?php esc_html_e('Select a profile', 'gravity-extract'); ?></option>
                <option value="supplier_invoice"><?php esc_html_e('Supplier invoice (B2B)', 'gravity-extract'); ?></option>
                <option value="sales_invoice"><?php esc_html_e('Sales invoice', 'gravity-extract'); ?></option>
                <option value="credit_note"><?php esc_html_e('Credit note', 'gravity-extract'); ?></option>
                <option value="generic_receipt"><?php esc_html_e('Generic receipt', 'gravity-extract'); ?></option>
                <option value="restaurant_hotel"><?php esc_html_e('Restaurant / Hotel', 'gravity-extract'); ?></option>
                <option value="minimal_light"><?php esc_html_e('Minimal (light)', 'gravity-extract'); ?></option>
            </select>
        </li>

        <li class="gravity_extract_field_mappings_setting field_setting">
            <label class="section_label">
                <?php esc_html_e('Field Mappings', 'gravity-extract'); ?>
                <?php gform_tooltip('gravity_extract_field_mappings'); ?>
            </label>
            <div id="gravity_extract_mappings_container" class="gravity-extract-mappings-container">
                <p class="description">
                    <?php esc_html_e('Select a mapping profile above to configure field mappings.', 'gravity-extract'); ?>
                </p>
            </div>
        </li>

        <li class="gravity_extract_auto_crop_setting field_setting">
            <input type="checkbox" id="gravity_extract_auto_crop"
                onclick="SetFieldProperty('gravityExtractAutoCrop', this.checked);" />
            <label for="gravity_extract_auto_crop" class="inline">
                <?php esc_html_e('Enable auto-crop document', 'gravity-extract'); ?>
            </label>
            <p class="description">
                <?php esc_html_e('Uses OpenCV if available, falls back to basic GD cropping otherwise.', 'gravity-extract'); ?>
            </p>
        </li>
        <?php
    }

    /**
     * Add tooltips
     */
    public function add_tooltips($tooltips)
    {
        $tooltips['gravity_extract_api_key'] = sprintf(
            '<h6>%s</h6>%s',
            __('POE API Key', 'gravity-extract'),
            __('Enter your POE API key to enable AI-powered document extraction. Get your key from poe.com.', 'gravity-extract')
        );

        $tooltips['gravity_extract_model'] = sprintf(
            '<h6>%s</h6>%s',
            __('AI Model', 'gravity-extract'),
            __('Select the AI model to use for image analysis. Only models that support image input are shown.', 'gravity-extract')
        );

        $tooltips['gravity_extract_mapping_profile'] = sprintf(
            '<h6>%s</h6>%s',
            __('Mapping Profile', 'gravity-extract'),
            __('Select the type of document to extract. Each profile defines which fields to extract from the document.', 'gravity-extract')
        );

        $tooltips['gravity_extract_field_mappings'] = sprintf(
            '<h6>%s</h6>%s',
            __('Field Mappings', 'gravity-extract'),
            __('Map each extracted value to a form field. The AI will extract data and populate the mapped fields.', 'gravity-extract')
        );

        return $tooltips;
    }

    /**
     * AJAX: Get available models
     */
    public function ajax_get_models()
    {
        check_ajax_referer('gravity_extract_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            error_log('Gravity Extract: Unauthorized access attempt to get_models');
            wp_send_json_error(array('message' => __('Unauthorized', 'gravity-extract')));
            return;
        }

        $api_key = sanitize_text_field($_POST['api_key'] ?? '');

        if (empty($api_key)) {
            error_log('Gravity Extract: No API key provided');
            wp_send_json_error(array('message' => __('API key is required', 'gravity-extract')));
            return;
        }

        error_log('Gravity Extract: Fetching models with API key: ' . substr($api_key, 0, 10) . '...');

        $models = Gravity_Extract_POE_API::get_models($api_key);

        if (is_wp_error($models)) {
            error_log('Gravity Extract: Error fetching models: ' . $models->get_error_message());
            wp_send_json_error(array('message' => $models->get_error_message()));
            return;
        }

        error_log('Gravity Extract: Found ' . count($models) . ' image-capable models');
        wp_send_json_success(array('models' => $models));
    }

    /**
     * AJAX: Analyze image
     */
    public function ajax_analyze_image()
    {
        check_ajax_referer('gravity_extract_frontend', 'nonce');

        $form_id = intval($_POST['form_id'] ?? 0);
        $field_id = intval($_POST['field_id'] ?? 0);
        $image_base64 = $_POST['image_base64'] ?? '';

        error_log('Gravity Extract: Starting AJAX image analysis. Form: ' . $form_id . ', Field: ' . $field_id);
        error_log('Gravity Extract: Image data length: ' . strlen($image_base64));

        if (empty($form_id) || empty($field_id) || empty($image_base64)) {
            wp_send_json_error(array('message' => __('Missing required parameters', 'gravity-extract')));
        }

        // Get form and field
        $form = GFAPI::get_form($form_id);
        if (!$form) {
            wp_send_json_error(array('message' => __('Form not found', 'gravity-extract')));
        }

        $field = null;
        foreach ($form['fields'] as $f) {
            if ($f->id == $field_id) {
                $field = $f;
                break;
            }
        }

        if (!$field || $field->type !== 'gravity_extract') {
            wp_send_json_error(array('message' => __('Field not found', 'gravity-extract')));
        }

        $api_key = $field->gravityExtractApiKey;
        $model = $field->gravityExtractModel;
        $config = $field->gravityExtractConfig ? $field->gravityExtractConfig : array();
        $profile = isset($config['profile']) ? $config['profile'] : '';
        $mappings = isset($config['mappings']) ? $config['mappings'] : array();

        if (empty($api_key) || empty($model)) {
            wp_send_json_error(array('message' => __('Field not configured properly', 'gravity-extract')));
        }

        if (empty($profile)) {
            wp_send_json_error(array('message' => __('No mapping profile selected', 'gravity-extract')));
        }

        // Remove data URL prefix if present
        if (strpos($image_base64, 'base64,') !== false) {
            $image_base64 = explode('base64,', $image_base64)[1];
        }

        // Get the keys to extract based on mappings (only mapped keys)
        $keys_to_extract = array_keys($mappings);

        if (empty($keys_to_extract)) {
            // No fields mapped, likely user only wants auto-crop or file upload
            wp_send_json_success(array(
                'extracted_data' => array(),
                'mappings' => array(),
                'message' => __('No fields mapped for extraction', 'gravity-extract'),
            ));
        }

        // Call POE API with profile-specific extraction
        $result = Gravity_Extract_POE_API::analyze_image_with_keys($api_key, $model, $image_base64, $profile, $keys_to_extract);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'extracted_data' => $result['extracted_data'],
            'mappings' => $mappings,
        ));
    }

    /**
     * AJAX: Upload file to WordPress
     */
    public function ajax_upload_file()
    {
        error_log('Gravity Extract: ajax_upload_file called');
        error_log('Gravity Extract: $_FILES: ' . print_r($_FILES, true));
        error_log('Gravity Extract: $_POST: ' . print_r($_POST, true));

        check_ajax_referer('gravity_extract_frontend', 'nonce');

        if (empty($_FILES['file'])) {
            error_log('Gravity Extract: No file in $_FILES');
            wp_send_json_error(array('message' => __('No file uploaded', 'gravity-extract')));
            return;
        }

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        $field_id = isset($_POST['field_id']) ? intval($_POST['field_id']) : 0;

        // Include WordPress file handling functions
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Upload file
        $file = $_FILES['file'];
        error_log('Gravity Extract: Attempting to upload file: ' . $file['name']);
        $upload = wp_handle_upload($file, array('test_form' => false));

        if (isset($upload['error'])) {
            error_log('Gravity Extract: Upload error: ' . $upload['error']);
            wp_send_json_error(array('message' => $upload['error']));
            return;
        }

        error_log('Gravity Extract: File uploaded successfully to: ' . $upload['url']);

        // Convert PDF to JPEG if necessary
        $converted_from_pdf = false;
        if ($upload['type'] === 'application/pdf') {
            error_log('Gravity Extract: PDF detected, converting to JPEG');
            $jpeg_path = $this->convert_pdf_to_jpeg($upload['file']);

            if ($jpeg_path) {
                // Update upload array to point to JPEG
                $upload['file'] = $jpeg_path;
                $upload['url'] = str_replace('.pdf', '.jpg', $upload['url']);
                $upload['type'] = 'image/jpeg';
                $converted_from_pdf = true;
                error_log('Gravity Extract: PDF conversion successful, now using JPEG: ' . $upload['url']);
            } else {
                error_log('Gravity Extract: PDF conversion failed');
                wp_send_json_error(array('message' => __('PDF conversion failed. Please try uploading an image instead.', 'gravity-extract')));
                return;
            }
        }


        // Check if auto-crop is enabled for this field
        if ($form_id && $field_id && !$converted_from_pdf) {
            $form = GFAPI::get_form($form_id);
            if ($form) {
                $field = GFFormsModel::get_field($form, $field_id);
                if ($field && !empty($field->gravityExtractAutoCrop)) {
                    error_log('Gravity Extract: Auto-crop enabled for field ' . $field_id);

                    // Process auto-crop
                    $cropped_file = $upload['file'] . '_cropped';
                    $crop_method = self::detect_autocrop_method();
                    $crop_setting = get_option('gravity_extract_crop_method', 'auto');
                    $success = false;
                    $method_used = 'none';

                    error_log(sprintf('Gravity Extract: Auto-crop processing. Method: %s, Setting: %s', $crop_method, $crop_setting));

                    // Try OpenCV first (unless gd_only)
                    if ($crop_setting !== 'gd_only' && $crop_method === 'opencv') {
                        $success = $this->crop_with_opencv($upload['file'], $cropped_file);
                        if ($success) {
                            $method_used = 'opencv';
                        }
                    }

                    // Try GD fallback (unless opencv_only)
                    if (!$success && $crop_setting !== 'opencv_only') {
                        if (extension_loaded('gd')) {
                            // GD modifies in place
                            $success = self::gd_autocrop_fallback($upload['file']);
                            if ($success) {
                                $method_used = 'gd';
                            }
                        }
                    }

                    // If OpenCV succeeded, replace original with cropped
                    if ($success && $method_used === 'opencv' && file_exists($cropped_file)) {
                        if (rename($cropped_file, $upload['file'])) {
                            error_log('Gravity Extract: Auto-crop successful using ' . $method_used);
                            // Update URL to reflect cropped image
                            $upload['url'] = str_replace(basename($upload['url']), basename($upload['file']), $upload['url']);
                        } else {
                            error_log('Gravity Extract: Failed to replace with cropped file');
                            @unlink($cropped_file);
                        }
                    } elseif ($success && $method_used === 'gd') {
                        error_log('Gravity Extract: Auto-crop successful using GD');
                    } else {
                        error_log('Gravity Extract: Auto-crop failed, using original image');
                        @unlink($cropped_file);
                    }
                }
            }
        }

        $response_data = array(
            'url' => $upload['url'],
            'file' => $upload['file']
        );

        // Check if field has mappings configured - if so, analyze the image
        if ($form_id && $field_id) {
            $form = GFAPI::get_form($form_id);
            if ($form) {
                $field = GFFormsModel::get_field($form, $field_id);
                $config = $field && $field->gravityExtractConfig ? $field->gravityExtractConfig : array();
                $mappings = isset($config['mappings']) && is_object($config['mappings']) ? (array) $config['mappings'] : (isset($config['mappings']) ? $config['mappings'] : array());

                // Only proceed with analysis if there are mappings configured
                if ($field && !empty($mappings)) {
                    error_log('Gravity Extract: Field has mappings configured, analyzing image after upload');

                    $api_key = $field->gravityExtractApiKey;
                    $model = $field->gravityExtractModel;
                    $profile = isset($config['profile']) ? $config['profile'] : '';

                    if (empty($api_key) || empty($model)) {
                        wp_send_json_error(array('message' => __('Field not configured properly for analysis', 'gravity-extract')));
                    }

                    if (empty($profile)) {
                        wp_send_json_error(array('message' => __('No mapping profile selected for analysis', 'gravity-extract')));
                    }

                    $keys_to_extract = array_keys($mappings);

                    if (empty($keys_to_extract)) {
                        // No fields mapped, proceed with just file upload
                        $response_data['extracted_data'] = array();
                        $response_data['mappings'] = array();
                        $response_data['message'] = __('No fields mapped for extraction', 'gravity-extract');
                        wp_send_json_success($response_data);
                        return;
                    }

                    // Optimize image before analysis to reduce token usage
                    $optimized_file = $this->optimize_image_for_analysis($upload['file']);
                    $file_to_analyze = $optimized_file ? $optimized_file : $upload['file'];

                    // Read the file content and convert to base64
                    $file_content = file_get_contents($file_to_analyze);
                    if ($file_content === false) {
                        wp_send_json_error(array('message' => __('Failed to read uploaded file for analysis', 'gravity-extract')));
                    }
                    $image_base64 = base64_encode($file_content);

                    // Clean up optimized temp file if it was created
                    if ($optimized_file && $optimized_file !== $upload['file']) {
                        @unlink($optimized_file);
                    }

                    error_log('Gravity Extract: Calling POE API for analysis after upload. Image data length: ' . strlen($image_base64));

                    // Call POE API with profile-specific extraction
                    $result = Gravity_Extract_POE_API::analyze_image_with_keys($api_key, $model, $image_base64, $profile, $keys_to_extract);

                    if (is_wp_error($result)) {
                        error_log('Gravity Extract: Error during analysis after upload: ' . $result->get_error_message());
                        wp_send_json_error(array('message' => $result->get_error_message()));
                    }

                    $response_data['extracted_data'] = $result['extracted_data'];
                    $response_data['mappings'] = $mappings;
                    $response_data['message'] = __('File uploaded and analyzed successfully', 'gravity-extract');
                    wp_send_json_success($response_data);
                    return; // Exit after sending JSON
                }
            }
        }

        // Return the uploaded file URL if no analysis was performed
        wp_send_json_success($response_data);
    }

    /**
     * AJAX: Automap fields using AI
     */
    public function ajax_automap_fields()
    {
        check_ajax_referer('gravity_extract_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'gravity-extract')));
        }

        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $model = sanitize_text_field($_POST['model'] ?? '');
        $keys = isset($_POST['keys']) ? array_map('sanitize_text_field', $_POST['keys']) : array();
        $form_fields = isset($_POST['form_fields']) ? $_POST['form_fields'] : array();

        if (empty($api_key) || empty($model)) {
            wp_send_json_error(array('message' => __('API key and Model are required', 'gravity-extract')));
        }

        if (empty($keys) || empty($form_fields)) {
            wp_send_json_error(array('message' => __('No keys or fields provided to map', 'gravity-extract')));
        }

        // Sanitize form fields
        $clean_fields = array();
        foreach ($form_fields as $f) {
            $clean_fields[] = array(
                'id' => sanitize_text_field($f['id']),
                'label' => sanitize_text_field($f['label'])
            );
        }

        $mappings = Gravity_Extract_POE_API::automap_fields($api_key, $model, $keys, $clean_fields);

        if (is_wp_error($mappings)) {
            wp_send_json_error(array('message' => $mappings->get_error_message()));
        }

        wp_send_json_success(array('mappings' => $mappings));
    }

    /**
     * Process auto-crop on form submission
     */
    public function process_auto_crop($form)
    {
        foreach ($form['fields'] as $field) {
            if ($field->type !== 'gravity_extract') {
                continue;
            }

            // Check if auto-crop is enabled for this field
            if (empty($field->gravityExtractAutoCrop)) {
                continue;
            }

            $input_name = 'input_' . $field->id;
            if (!isset($_FILES[$input_name]) || $_FILES[$input_name]['error'] !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmp_file = $_FILES[$input_name]['tmp_name'];
            $cropped_file = $tmp_file . '_cropped';
            $crop_method = self::detect_autocrop_method();
            $crop_setting = get_option('gravity_extract_crop_method', 'auto');
            $success = false;
            $method_used = 'none';

            error_log(sprintf('Gravity Extract: Processing auto-crop for field %d. Method detected: %s, Setting: %s', $field->id, $crop_method, $crop_setting));
            error_log('Gravity Extract: Temp file: ' . $tmp_file);

            // Cascading fallback logic
            if ($crop_setting === 'disabled') {
                error_log('Gravity Extract: Auto-crop disabled by setting');
                continue;
            }

            // Try OpenCV first (unless gd_only)
            if ($crop_setting !== 'gd_only' && $crop_method === 'opencv') {
                $success = $this->crop_with_opencv($tmp_file, $cropped_file);
                if ($success) {
                    $method_used = 'opencv';
                }
            }

            // Try GD fallback (unless opencv_only)
            if (!$success && $crop_setting !== 'opencv_only') {
                if (extension_loaded('gd')) {
                    $success = self::gd_autocrop_fallback($tmp_file);
                    if ($success) {
                        $method_used = 'gd';
                    }
                }
            }

            // Apply filter hook for customization
            $tmp_file = apply_filters('gform_invoice_extract_cropped_image', $tmp_file, $field, $method_used, $success);

            if ($success && $method_used === 'opencv' && file_exists($cropped_file)) {
                if (rename($cropped_file, $tmp_file)) {
                    error_log('Gravity Extract: Successfully cropped using ' . $method_used . ' for field ' . $field->id);
                } else {
                    error_log('Gravity Extract: Failed to replace temp file');
                    @unlink($cropped_file);
                }
            } elseif ($success && $method_used === 'gd') {
                error_log('Gravity Extract: Successfully cropped using GD for field ' . $field->id);
            } else {
                error_log('Gravity Extract: Auto-crop failed, using original for field ' . $field->id);
                @unlink($cropped_file);
            }
        }

        return $form;
    }

    /**
     * Crop image using OpenCV Python script
     */
    private function crop_with_opencv($input_file, $output_file)
    {
        $script_path = GRAVITY_EXTRACT_PATH . 'document_crop.py';

        if (!file_exists($script_path)) {
            error_log('Gravity Extract: document_crop.py not found');
            return false;
        }

        $command = sprintf(
            'python3 %s %s %s 2>&1',
            escapeshellarg($script_path),
            escapeshellarg($input_file),
            escapeshellarg($output_file)
        );

        $output = shell_exec($command);

        if (file_exists($output_file)) {
            return true;
        }

        error_log('Gravity Extract: OpenCV failed. Output: ' . trim($output));
        return false;
    }

    /**
     * GD-based auto-crop fallback
     */
    public static function gd_autocrop_fallback($file_path)
    {
        if (!file_exists($file_path) || !is_writable($file_path)) {
            error_log('Gravity Extract: GD - file not writable: ' . $file_path);
            return false;
        }

        $mime = mime_content_type($file_path);
        $image = null;

        switch ($mime) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($file_path);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($file_path);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $image = @imagecreatefromwebp($file_path);
                }
                break;
            default:
                error_log('Gravity Extract: GD - unsupported format: ' . $mime);
                return false;
        }

        if (!$image) {
            error_log('Gravity Extract: GD - failed to load image');
            return false;
        }

        $cropped = imagecropauto($image, IMG_CROP_DEFAULT);
        if ($cropped === false) {
            $cropped = imagecropauto($image, IMG_CROP_THRESHOLD, 0.5, 16777215);
        }

        if ($cropped === false) {
            imagedestroy($image);
            return false;
        }

        $success = false;
        switch ($mime) {
            case 'image/jpeg':
                $success = imagejpeg($cropped, $file_path, 90);
                break;
            case 'image/png':
                $success = imagepng($cropped, $file_path, 9);
                break;
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    $success = imagewebp($cropped, $file_path, 90);
                }
                break;
        }

        imagedestroy($image);
        imagedestroy($cropped);

        return $success;
    }

    /**
     * Optimize image for AI analysis
     * Converts to grayscale, resizes to max 1024px, saves as JPEG 60% quality
     */
    private function optimize_image_for_analysis($file_path)
    {
        if (!file_exists($file_path)) {
            error_log('Gravity Extract: Optimize - file not found: ' . $file_path);
            return false;
        }

        $mime = mime_content_type($file_path);
        $image = null;

        // Load image based on type
        switch ($mime) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($file_path);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($file_path);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $image = @imagecreatefromwebp($file_path);
                }
                break;
            default:
                error_log('Gravity Extract: Optimize - unsupported format: ' . $mime);
                return false;
        }

        if (!$image) {
            error_log('Gravity Extract: Optimize - failed to load image');
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Calculate new dimensions (max 1024px on longest side)
        $max_size = 1024;
        if ($width > $max_size || $height > $max_size) {
            if ($width > $height) {
                $new_width = $max_size;
                $new_height = intval(($height / $width) * $max_size);
            } else {
                $new_height = $max_size;
                $new_width = intval(($width / $height) * $max_size);
            }
        } else {
            $new_width = $width;
            $new_height = $height;
        }

        // Create resized image
        $resized = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        imagedestroy($image);

        // Save to temporary file as JPEG with 60% quality
        $temp_file = $file_path . '_optimized.jpg';
        $success = imagejpeg($resized, $temp_file, 60);
        imagedestroy($resized);

        if ($success) {
            error_log(sprintf(
                'Gravity Extract: Image optimized - %dx%d -> %dx%d, JPEG 60%%',
                $width,
                $height,
                $new_width,
                $new_height
            ));
            return $temp_file;
        }

        error_log('Gravity Extract: Optimize - failed to save optimized image');
        return false;
    }

    /**
     * Convert PDF to JPEG (all pages merged into one tall image)
     * Uses Imagick to convert PDF documents to JPEG before processing
     * 
     * @param string $pdf_path Path to the PDF file
     * @param int $max_pages Maximum number of pages to process (0 = unlimited)
     * @return string|false Path to the generated JPEG or false on failure
     */
    private function convert_pdf_to_jpeg($pdf_path, $max_pages = 10)
    {
        if (!extension_loaded('imagick')) {
            error_log('Gravity Extract: Imagick extension not available for PDF conversion');
            return false;
        }

        try {
            // First, count the pages in the PDF
            $imagick = new Imagick();
            $imagick->setResolution(150, 150);
            $imagick->pingImage($pdf_path);
            $total_pages = $imagick->getNumberImages();
            $imagick->clear();
            $imagick->destroy();

            error_log(sprintf('Gravity Extract: PDF has %d pages', $total_pages));

            // Limit pages if needed
            $pages_to_process = $max_pages > 0 ? min($total_pages, $max_pages) : $total_pages;
            error_log(sprintf('Gravity Extract: Processing %d pages', $pages_to_process));

            // Convert each page to an image
            $page_images = array();
            $total_width = 0;
            $total_height = 0;

            for ($i = 0; $i < $pages_to_process; $i++) {
                $page_imagick = new Imagick();
                $page_imagick->setBackgroundColor('white');
                $page_imagick->setResolution(150, 150);
                $page_imagick->readImage($pdf_path . '[' . $i . ']');
                $page_imagick = $page_imagick->flattenImages();
                $page_imagick->setImageColorspace(Imagick::COLORSPACE_SRGB);

                // Track dimensions
                $width = $page_imagick->getImageWidth();
                $height = $page_imagick->getImageHeight();
                $total_width = max($total_width, $width);
                $total_height += $height;

                $page_images[] = array(
                    'imagick' => $page_imagick,
                    'width' => $width,
                    'height' => $height
                );

                error_log(sprintf('Gravity Extract: Page %d converted - %dx%d', $i + 1, $width, $height));
            }

            // Create final merged image
            $merged = new Imagick();
            $merged->newImage($total_width, $total_height, new ImagickPixel('white'));
            $merged->setImageFormat('jpeg');

            // Composite each page onto the merged image
            $y_offset = 0;
            foreach ($page_images as $page) {
                // Center horizontally if page is narrower than max width
                $x_offset = ($total_width - $page['width']) / 2;
                $merged->compositeImage($page['imagick'], Imagick::COMPOSITE_OVER, (int) $x_offset, $y_offset);
                $y_offset += $page['height'];

                // Clean up page image
                $page['imagick']->clear();
                $page['imagick']->destroy();
            }

            // Set quality and save
            $merged->setImageCompressionQuality(85);

            // Generate JPEG filename
            $jpeg_path = preg_replace('/\.pdf$/i', '.jpg', $pdf_path);

            // Write JPEG
            $merged->writeImage($jpeg_path);

            error_log(sprintf(
                'Gravity Extract: PDF merged to JPEG - %s (%dx%d, %d pages)',
                basename($jpeg_path),
                $total_width,
                $total_height,
                $pages_to_process
            ));

            $merged->clear();
            $merged->destroy();

            // Delete original PDF
            @unlink($pdf_path);

            return $jpeg_path;
        } catch (Exception $e) {
            error_log('Gravity Extract: PDF conversion failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Detect available auto-crop method (cached)
     */
    public static function detect_autocrop_method()
    {
        $cached = get_transient('gravity_extract_crop_method');
        if ($cached !== false) {
            return $cached;
        }

        $method = 'none';

        $python_version = @shell_exec('python3 --version 2>&1');
        if ($python_version && strpos($python_version, 'Python') !== false) {
            $cv2_check = @shell_exec('python3 -c "import cv2" 2>&1');
            if (empty($cv2_check) || (strpos($cv2_check, 'Error') === false && strpos($cv2_check, 'No module') === false)) {
                $method = 'opencv';
            }
        }

        if ($method === 'none' && extension_loaded('gd')) {
            $method = 'gd';
        }

        set_transient('gravity_extract_crop_method', $method, HOUR_IN_SECONDS);
        return $method;
    }

    /**
     * Get available methods status
     */
    public static function get_crop_methods_status()
    {
        $opencv_available = false;
        $gd_available = extension_loaded('gd');

        $python_version = @shell_exec('python3 --version 2>&1');
        if ($python_version && strpos($python_version, 'Python') !== false) {
            $cv2_check = @shell_exec('python3 -c "import cv2" 2>&1');
            if (empty($cv2_check) || (strpos($cv2_check, 'Error') === false && strpos($cv2_check, 'No module') === false)) {
                $opencv_available = true;
            }
        }

        return array(
            'opencv' => $opencv_available,
            'gd' => $gd_available,
        );
    }
}

