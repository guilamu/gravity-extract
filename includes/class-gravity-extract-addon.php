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
    }

    /**
     * Initialize admin hooks
     */
    public function init_admin()
    {
        parent::init_admin();

        // AJAX handler for fetching models in form settings
        add_action('wp_ajax_gravity_extract_get_models_form_settings', array($this, 'ajax_get_models_for_form_settings'));
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
}
