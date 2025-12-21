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

    // Check Python/OpenCV dependencies and show notice if missing
    add_action('admin_notices', 'gravity_extract_check_python_dependencies');
}
add_action('gform_loaded', 'gravity_extract_init', 5);

/**
 * Check Python and OpenCV dependencies
 */
function gravity_extract_check_python_dependencies()
{
    // Only show on plugin pages or form editor
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'gf_') === false) {
        return;
    }

    // Check if already dismissed
    if (get_transient('gravity_extract_python_notice_dismissed')) {
        return;
    }

    $opencv_available = false;
    $gd_available = extension_loaded('gd');

    // Check Python + OpenCV
    $python_version = @shell_exec('python3 --version 2>&1');
    if ($python_version && strpos($python_version, 'Python') !== false) {
        $cv2_check = @shell_exec('python3 -c "import cv2" 2>&1');
        if (empty($cv2_check) || (strpos($cv2_check, 'Error') === false && strpos($cv2_check, 'No module') === false)) {
            $opencv_available = true;
        }
    }

    // Only show notice if OpenCV is missing (GD is usually available)
    if (!$opencv_available) {
        $opencv_icon = '<span style="color: red;">✗</span>';
        $gd_icon = $gd_available ? '<span style="color: green;">✓</span>' : '<span style="color: red;">✗</span>';
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong><?php esc_html_e('Gravity Extract - Auto-Crop Status:', 'gravity-extract'); ?></strong><br>
                <?php echo $opencv_icon; ?> OpenCV (recommended) -
                <?php
                if ($opencv_available) {
                    esc_html_e('Available', 'gravity-extract');
                } else {
                    printf(
                        esc_html__('Not installed. Install with: %s', 'gravity-extract'),
                        '<code>apt-get install python3-opencv</code>'
                    );
                }
                ?><br>
                <?php echo $gd_icon; ?> GD (fallback) -
                <?php
                if ($gd_available) {
                    esc_html_e('Available', 'gravity-extract');
                } else {
                    esc_html_e('Not available', 'gravity-extract');
                }
                ?>
            </p>
            <p>
                <em><?php esc_html_e('OpenCV provides better document detection. GD provides basic border trimming.', 'gravity-extract'); ?></em>
            </p>
        </div>
        <?php
    }
}

/**
 * Plugin activation hook
 */
function gravity_extract_activate()
{
    // Store activation flag
    update_option('gravity_extract_activated', true);
}
register_activation_hook(__FILE__, 'gravity_extract_activate');
