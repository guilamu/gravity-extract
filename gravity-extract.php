<?php
/**
 * Plugin Name: Gravity Extract
 * Description: Adds a new upload field to Gravity Forms that uses AI to extract invoice information from images.
 * Version: 1.0.0
 * Author: Gravity Extract
 * Text Domain: gravity-extract
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('GRAVITY_EXTRACT_VERSION', '1.0.0');
define('GRAVITY_EXTRACT_FILE', __FILE__);
define('GRAVITY_EXTRACT_PATH', plugin_dir_path(__FILE__));
define('GRAVITY_EXTRACT_URL', plugin_dir_url(__FILE__));

/**
 * Check for Gravity Forms dependency
 */
function gravity_extract_check_dependencies()
{
    if (!class_exists('GFForms')) {
        add_action('admin_notices', 'gravity_extract_missing_gf_notice');
        return false;
    }
    return true;
}

/**
 * Admin notice for missing Gravity Forms
 */
function gravity_extract_missing_gf_notice()
{
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('Gravity Extract requires Gravity Forms to be installed and activated.', 'gravity-extract'); ?>
        </p>
    </div>
    <?php
}

/**
 * Initialize the plugin on Gravity Forms load
 */
function gravity_extract_init()
{
    if (!gravity_extract_check_dependencies()) {
        return;
    }

    // Load the main class
    require_once GRAVITY_EXTRACT_PATH . 'includes/class-gravity-extract.php';

    // Initialize
    Gravity_Extract::get_instance();

    // Register the form settings addon
    require_once GRAVITY_EXTRACT_PATH . 'includes/class-gravity-extract-addon.php';
    GFAddOn::register('Gravity_Extract_Addon');
}
add_action('gform_loaded', 'gravity_extract_init', 5);

/**
 * Plugin activation hook
 */
function gravity_extract_activate()
{
    // Store activation flag
    update_option('gravity_extract_activated', true);
}
register_activation_hook(__FILE__, 'gravity_extract_activate');
