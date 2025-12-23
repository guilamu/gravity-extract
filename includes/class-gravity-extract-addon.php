<?php
/**
 * Gravity Extract Form Settings Addon
 * Adds a "Gravity Extract" tab to form settings for API key and model configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure GFAddOn exists
if (!class_exists('GFAddOn')) {
    return;
}

class Gravity_Extract_Addon extends GFAddOn
{

    protected $_version = GRAVITY_EXTRACT_VERSION;
    protected $_min_gravityforms_version = '2.5';
    protected $_slug = 'gravity-extract';
    protected $_path = 'gravity-extract/gravity-extract.php';
    protected $_full_path = __FILE__;
    protected $_title = 'Gravity Extract';
    protected $_short_title = 'Gravity Extract';

    /**
     * Get addon title (translatable)
     */
    public function get_title()
    {
        return __('Gravity Extract', 'gravity-extract');
    }

    /**
     * Get addon short title (translatable)
     */
    public function get_short_title()
    {
        return __('Gravity Extract', 'gravity-extract');
    }

    /**
     * Singleton instance
     */
    private static $_instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Pre-init: Register hooks before the addon is fully initialized
     */
    public function pre_init()
    {
        parent::pre_init();
    }

    /**
     * Initialize the addon
     */
    public function init()
    {
        parent::init();

        // Load plugin textdomain for translations
        load_plugin_textdomain('gravity-extract', false, basename(dirname(GRAVITY_EXTRACT_FILE)) . '/languages/');
    }

    /**
     * Initialize admin hooks
     */
    public function init_admin()
    {
        parent::init_admin();

        // AJAX handler for fetching models in form settings
        add_action('wp_ajax_gravity_extract_get_models_form_settings', array($this, 'ajax_get_models_for_form_settings'));

        // Add modal HTML to admin footer
        add_action('admin_footer', array($this, 'render_profile_manager_modal'));

        // Enqueue jQuery UI for sortable
        add_action('admin_enqueue_scripts', array($this, 'enqueue_profile_manager_scripts'));
    }

    /**
     * Enqueue scripts for profile manager
     */
    public function enqueue_profile_manager_scripts($hook)
    {
        // Only on form settings and form editor pages
        $is_gf_page = strpos($hook, 'gf_') !== false ||
            strpos($hook, 'gravityforms') !== false ||
            (isset($_GET['page']) && strpos($_GET['page'], 'gf_') === 0);

        if (!$is_gf_page) {
            return;
        }

        // Enqueue jQuery UI Sortable
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-draggable');

        // Add inline script with profile manager data
        // Attach to the gravity-extract-admin script (already enqueued by class-gravity-extract.php)
        $profiles_manager = Gravity_Extract_Mapping_Profiles_Manager::get_instance();
        $custom_profiles = $profiles_manager->get_all_profiles();
        $master_fields = $profiles_manager->get_master_fields();

        wp_localize_script('gravity-extract-admin', 'gravityExtractProfiles', array(
            'customProfiles' => $custom_profiles,
            'masterFields' => $master_fields,
            'nonce' => wp_create_nonce('gravity_extract_profiles'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n' => array(
                'confirmDelete' => __('Are you sure you want to delete this profile?', 'gravity-extract'),
                'enterProfileName' => __('Enter profile name:', 'gravity-extract'),
                'enterNewName' => __('Enter new name for duplicate:', 'gravity-extract'),
                'profileSaved' => __('Profile saved successfully!', 'gravity-extract'),
                'profileDeleted' => __('Profile deleted successfully!', 'gravity-extract'),
                'profileImported' => __('Profile imported successfully!', 'gravity-extract'),
                'error' => __('An error occurred. Please try again.', 'gravity-extract'),
                'noFieldsEnabled' => __('Please enable at least one field.', 'gravity-extract'),
                'nameRequired' => __('Profile name is required.', 'gravity-extract'),
                'manageProfiles' => __('Manage Mapping Profiles', 'gravity-extract'),
                'editProfile' => __('Edit Profile', 'gravity-extract'),
                'newProfile' => __('New Profile', 'gravity-extract'),
                'noProfiles' => __('No custom profiles yet. Click "New Profile" to create one.', 'gravity-extract'),
                'field' => __('field', 'gravity-extract'),
                'fields' => __('fields', 'gravity-extract'),
                'customSuffix' => __('(Custom)', 'gravity-extract'),
                'invalidJson' => __('Invalid JSON file', 'gravity-extract'),
                'detectionFailed' => __('Detection failed. Please try again.', 'gravity-extract'),
                'fieldsDetected' => __('Fields detected!', 'gravity-extract'),
                'noFieldsDetected' => __('No fields detected', 'gravity-extract'),
            )
        ));
    }

    /**
     * Define form settings fields
     * These appear in the form settings under the "Gravity Extract" tab
     */
    public function form_settings_fields($form)
    {
        return array(
            array(
                'title' => esc_html__('Gravity Extract Settings', 'gravity-extract'),
                'description' => esc_html__('Configure the POE API credentials and AI model for document extraction in this form.', 'gravity-extract'),
                'fields' => array(
                    array(
                        'name' => 'gravity_extract_api_key',
                        'label' => esc_html__('POE API Key', 'gravity-extract'),
                        'type' => 'text',
                        'input_type' => 'password',
                        'class' => 'medium',
                        'description' => '<a href="https://poe.com/api_key" target="_blank">' . esc_html__('Get your API key from poe.com', 'gravity-extract') . ' →</a>',
                        'tooltip' => esc_html__('Enter your POE API key for AI document analysis.', 'gravity-extract'),
                        'feedback_callback' => array($this, 'validate_api_key'),
                    ),
                    array(
                        'name' => 'gravity_extract_model',
                        'label' => esc_html__('AI Model', 'gravity-extract'),
                        'type' => 'select',
                        'default_value' => 'Gemini-3-Flash',
                        'choices' => $this->get_model_choices($form),
                        'tooltip' => esc_html__('Select the AI model to use for image analysis. Only models that support image input are shown.', 'gravity-extract'),
                    ),
                    array(
                        'name' => 'gravity_extract_info',
                        'type' => 'html',
                        'html' => '<p class="description">' .
                            esc_html__('After saving the API key, refresh this page to load available AI models.', 'gravity-extract') .
                            '</p>',
                    ),
                    array(
                        'name' => 'gravity_extract_profile_manager',
                        'type' => 'html',
                        'html' => '<div class="ge-profile-manager-section">' .
                            '<hr style="margin: 20px 0;">' .
                            '<h4>' . esc_html__('Mapping Profiles', 'gravity-extract') . '</h4>' .
                            '<p class="description">' . esc_html__('Create and manage custom document mapping profiles.', 'gravity-extract') . '</p>' .
                            '<button type="button" class="button button-secondary" id="ge-open-profile-manager">' .
                            esc_html__('Manage Mapping Profiles', 'gravity-extract') .
                            '</button>' .
                            '</div>',
                    ),
                ),
            ),
        );
    }

    /**
     * Get model choices for the dropdown
     */
    private function get_model_choices($form)
    {
        $default_model = 'Gemini-3-Flash';
        $choices = array(
            array(
                'label' => esc_html__('Select a model (save API key first)', 'gravity-extract'),
                'value' => '',
            ),
        );

        // Get saved settings
        $settings = $this->get_form_settings($form);
        $api_key = rgar($settings, 'gravity_extract_api_key');
        $saved_model = rgar($settings, 'gravity_extract_model');

        if (!empty($api_key)) {
            // Fetch models from API
            require_once GRAVITY_EXTRACT_PATH . 'includes/class-poe-api-service.php';
            $models = Gravity_Extract_POE_API::get_models($api_key);

            if (!is_wp_error($models) && !empty($models)) {
                $choices = array(
                    array(
                        'label' => esc_html__('Select a model', 'gravity-extract'),
                        'value' => '',
                    ),
                );

                $has_default = false;
                foreach ($models as $model) {
                    $is_selected = false;

                    // If user has saved a model, use that
                    if (!empty($saved_model) && $model['id'] === $saved_model) {
                        $is_selected = true;
                    }
                    // Otherwise, default to Gemini-2-Flash
                    elseif (empty($saved_model) && $model['id'] === $default_model) {
                        $is_selected = true;
                        $has_default = true;
                    }

                    $choices[] = array(
                        'label' => $model['name'],
                        'value' => $model['id'],
                        'isSelected' => $is_selected,
                    );
                }
            }
        }

        return $choices;
    }

    /**
     * Validate API key field
     */
    public function validate_api_key($value)
    {
        if (empty($value)) {
            return null; // No feedback for empty
        }

        if (strlen($value) < 10) {
            return false; // Invalid
        }

        return true; // Valid
    }

    /**
     * AJAX handler for fetching models (for dynamic refresh)
     */
    public function ajax_get_models_for_form_settings()
    {
        check_ajax_referer('gf_form_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'gravity-extract')));
            return;
        }

        $api_key = sanitize_text_field($_POST['api_key'] ?? '');

        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('API key is required', 'gravity-extract')));
            return;
        }

        require_once GRAVITY_EXTRACT_PATH . 'includes/class-poe-api-service.php';
        $models = Gravity_Extract_POE_API::get_models($api_key);

        if (is_wp_error($models)) {
            wp_send_json_error(array('message' => $models->get_error_message()));
            return;
        }

        wp_send_json_success(array('models' => $models));
    }

    /**
     * Get form settings for a specific form
     * Helper method to access settings by form ID
     */
    public static function get_form_extract_settings($form_id)
    {
        $form = GFAPI::get_form($form_id);
        if (!$form) {
            return array();
        }

        $instance = self::get_instance();
        return $instance->get_form_settings($form);
    }

    /**
     * Render the profile manager modal HTML
     */
    public function render_profile_manager_modal()
    {
        // Only render on Gravity Forms admin pages
        $screen = get_current_screen();

        // Check for GF form editor and settings pages
        $is_gf_page = false;
        if ($screen) {
            $is_gf_page = strpos($screen->id, 'gf_') !== false ||
                strpos($screen->id, 'gravityforms') !== false ||
                strpos($screen->id, 'toplevel_page_gf') !== false;
        }

        // Also check GET params for form settings
        if (!$is_gf_page && isset($_GET['page']) && strpos($_GET['page'], 'gf_') === 0) {
            $is_gf_page = true;
        }

        if (!$is_gf_page) {
            return;
        }
        ?>
        <!-- Gravity Extract Profile Manager Modal -->
        <div id="ge-profile-modal-overlay" class="ge-profile-modal-overlay">
            <div class="ge-profile-modal">
                <!-- Modal Header -->
                <div class="ge-modal-header">
                    <h2 id="ge-modal-title"><?php esc_html_e('Manage Mapping Profiles', 'gravity-extract'); ?></h2>
                    <button type="button" class="ge-modal-close" id="ge-close-modal"
                        aria-label="<?php esc_attr_e('Close', 'gravity-extract'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>

                <!-- Modal Content -->
                <div class="ge-modal-content">
                    <!-- Profile List View -->
                    <div id="ge-profile-list-view" class="ge-view">
                        <div class="ge-profile-actions-bar">
                            <button type="button" class="button button-primary" id="ge-new-profile">
                                <span class="dashicons dashicons-plus-alt2"></span>
                                <?php esc_html_e('New Profile', 'gravity-extract'); ?>
                            </button>
                            <button type="button" class="button" id="ge-import-profile">
                                <span class="dashicons dashicons-upload"></span>
                                <?php esc_html_e('Import', 'gravity-extract'); ?>
                            </button>
                            <input type="file" id="ge-import-file" accept=".json" style="display: none;">
                        </div>

                        <div id="ge-profiles-list" class="ge-profiles-list">
                            <!-- Profiles will be rendered here by JavaScript -->
                            <p class="ge-no-profiles">
                                <?php esc_html_e('No custom profiles yet. Click "New Profile" to create one.', 'gravity-extract'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Profile Editor View -->
                    <div id="ge-profile-editor-view" class="ge-view" style="display: none;">
                        <div class="ge-editor-header">
                            <button type="button" class="button" id="ge-back-to-list">
                                <span class="dashicons dashicons-arrow-left-alt"></span>
                                <?php esc_html_e('Back to List', 'gravity-extract'); ?>
                            </button>
                        </div>

                        <div class="ge-profile-name-row">
                            <label for="ge-profile-name"><?php esc_html_e('Profile Name', 'gravity-extract'); ?></label>
                            <div class="ge-profile-name-input-row">
                                <input type="text" id="ge-profile-name" class="regular-text"
                                    placeholder="<?php esc_attr_e('Enter profile name...', 'gravity-extract'); ?>">
                                <button type="button" class="button button-secondary" id="ge-detect-fields">
                                    <?php esc_html_e('Detect available fields from sample', 'gravity-extract'); ?>
                                    <span id="ge-detect-spinner" class="spinner"></span>
                                </button>
                                <input type="file" id="ge-sample-file" accept="image/*,.pdf" style="display: none;">
                            </div>
                            <input type="hidden" id="ge-profile-slug" value="">
                        </div>

                        <div class="ge-fields-columns">
                            <!-- Available Fields Column -->
                            <div class="ge-field-column">
                                <h3><?php esc_html_e('Available Fields', 'gravity-extract'); ?></h3>
                                <p class="description">
                                    <?php esc_html_e('Drag fields to the right to enable them', 'gravity-extract'); ?>
                                </p>
                                <ul id="ge-available-fields" class="ge-field-list ge-sortable">
                                    <!-- Fields will be rendered here by JavaScript -->
                                </ul>
                            </div>

                            <!-- Enabled Fields Column -->
                            <div class="ge-field-column">
                                <h3><?php esc_html_e('Enabled Fields', 'gravity-extract'); ?></h3>
                                <p class="description">
                                    <?php esc_html_e('These fields will be extracted from documents', 'gravity-extract'); ?>
                                </p>
                                <ul id="ge-enabled-fields" class="ge-field-list ge-sortable">
                                    <!-- Fields will be rendered here by JavaScript -->
                                </ul>
                            </div>
                        </div>

                        <div class="ge-editor-footer">
                            <button type="button" class="button button-primary button-large" id="ge-save-profile">
                                <?php esc_html_e('Save Profile', 'gravity-extract'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Field label edit inline dialog -->
        <div id="ge-label-edit-dialog" class="ge-label-edit-dialog" style="display: none;">
            <label><?php esc_html_e('Custom Label:', 'gravity-extract'); ?></label>
            <input type="text" id="ge-custom-label-input" class="regular-text">
            <div class="ge-dialog-buttons">
                <button type="button" class="button button-primary"
                    id="ge-save-label"><?php esc_html_e('OK', 'gravity-extract'); ?></button>
                <button type="button" class="button"
                    id="ge-cancel-label"><?php esc_html_e('Cancel', 'gravity-extract'); ?></button>
            </div>
        </div>
        <?php
    }
}
