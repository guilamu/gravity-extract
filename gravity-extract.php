<?php
/**
 * Plugin Name: Gravity Extract
 * Plugin URI: https://github.com/guilamu/gravity-extract
 * Description: Adds a new upload field to Gravity Forms that uses AI to extract invoice information from images.
 * Version: 1.0.2
 * Author: Guilamu
 * Author URI: https://github.com/guilamu
 * Text Domain: gravity-extract
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * Update URI: https://github.com/guilamu/gravity-extract/
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('GRAVITY_EXTRACT_VERSION', '1.0.2');
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

    // Load the GitHub auto-updater
    require_once GRAVITY_EXTRACT_PATH . 'includes/class-github-updater.php';

    // Initialize
    Gravity_Extract::get_instance();

    // Load the mapping profiles manager
    require_once GRAVITY_EXTRACT_PATH . 'includes/class-mapping-profiles-manager.php';
    Gravity_Extract_Mapping_Profiles_Manager::get_instance();

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
