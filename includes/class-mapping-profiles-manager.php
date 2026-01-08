<?php
/**
 * Mapping Profiles Manager for Gravity Extract
 * Handles CRUD operations for custom document mapping profiles
 */

if (!defined('ABSPATH')) {
    exit;
}

class Gravity_Extract_Mapping_Profiles_Manager
{

    /**
     * Option name for storing custom profiles
     */
    const OPTION_NAME = 'gravity_extract_custom_profiles';

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
     * Constructor
     */
    private function __construct()
    {
        $this->register_ajax_handlers();
    }

    /**
     * Register AJAX handlers for profile operations
     */
    private function register_ajax_handlers()
    {
        add_action('wp_ajax_gravity_extract_get_profiles', array($this, 'ajax_get_profiles'));
        add_action('wp_ajax_gravity_extract_get_profile', array($this, 'ajax_get_profile'));
        add_action('wp_ajax_gravity_extract_save_profile', array($this, 'ajax_save_profile'));
        add_action('wp_ajax_gravity_extract_delete_profile', array($this, 'ajax_delete_profile'));
        add_action('wp_ajax_gravity_extract_duplicate_profile', array($this, 'ajax_duplicate_profile'));
        add_action('wp_ajax_gravity_extract_import_profile', array($this, 'ajax_import_profile'));
        add_action('wp_ajax_gravity_extract_get_master_fields', array($this, 'ajax_get_master_fields'));
        add_action('wp_ajax_gravity_extract_detect_fields', array($this, 'ajax_detect_fields'));
    }

    /**
     * Get all custom profiles
     * 
     * @return array
     */
    public function get_all_profiles()
    {
        $profiles = get_option(self::OPTION_NAME, array());
        return is_array($profiles) ? $profiles : array();
    }

    /**
     * Get a single profile by slug
     * 
     * @param string $slug Profile slug
     * @return array|null Profile data or null if not found
     */
    public function get_profile($slug)
    {
        $profiles = $this->get_all_profiles();
        return isset($profiles[$slug]) ? $profiles[$slug] : null;
    }

    /**
     * Save a profile
     * 
     * @param string $slug Profile slug
     * @param array $data Profile data (name, fields)
     * @return bool Success
     */
    public function save_profile($slug, $data)
    {
        $profiles = $this->get_all_profiles();
        $profiles[$slug] = $data;
        return update_option(self::OPTION_NAME, $profiles);
    }

    /**
     * Delete a profile
     * 
     * @param string $slug Profile slug
     * @return bool Success
     */
    public function delete_profile($slug)
    {
        $profiles = $this->get_all_profiles();
        if (!isset($profiles[$slug])) {
            return false;
        }
        unset($profiles[$slug]);
        return update_option(self::OPTION_NAME, $profiles);
    }

    /**
     * Duplicate a profile
     * 
     * @param string $slug Original profile slug
     * @param string $new_name New profile name
     * @return string|false New slug on success, false on failure
     */
    public function duplicate_profile($slug, $new_name)
    {
        $original = $this->get_profile($slug);
        if (!$original) {
            return false;
        }

        $new_slug = $this->generate_slug($new_name);
        $new_data = array(
            'name' => $new_name,
            'fields' => $original['fields']
        );

        if ($this->save_profile($new_slug, $new_data)) {
            return $new_slug;
        }
        return false;
    }

    /**
     * Generate a slug from a name
     * 
     * @param string $name Profile name
     * @return string Slug
     */
    public function generate_slug($name)
    {
        $slug = sanitize_title($name);
        $slug = 'custom_' . $slug;

        // Ensure uniqueness
        $profiles = $this->get_all_profiles();
        $original_slug = $slug;
        $counter = 1;

        while (isset($profiles[$slug])) {
            $slug = $original_slug . '_' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get master list of all available fields from built-in profiles
     * 
     * @return array Field definitions with keys and labels
     */
    public function get_master_fields()
    {
        // This list matches keyLabels in gravity-extract-admin.js
        return array(
            'full_extraction' => array('label' => '★ Full Extraction (all text)', 'key' => 'full_extraction'),
            'supplier_name' => array('label' => 'Supplier Name', 'key' => 'supplier_name'),
            'supplier_vat_number' => array('label' => 'Supplier VAT Number', 'key' => 'supplier_vat_number'),
            'supplier_address_line1' => array('label' => 'Supplier Address', 'key' => 'supplier_address_line1'),
            'supplier_address_line2' => array('label' => 'Supplier Address Line 2', 'key' => 'supplier_address_line2'),
            'supplier_city' => array('label' => 'Supplier City', 'key' => 'supplier_city'),
            'supplier_postcode' => array('label' => 'Supplier Postcode', 'key' => 'supplier_postcode'),
            'supplier_country' => array('label' => 'Supplier Country', 'key' => 'supplier_country'),
            'customer_name' => array('label' => 'Customer Name', 'key' => 'customer_name'),
            'customer_address' => array('label' => 'Customer Address', 'key' => 'customer_address'),
            'invoice_number' => array('label' => 'Invoice Number', 'key' => 'invoice_number'),
            'invoice_date' => array('label' => 'Invoice Date', 'key' => 'invoice_date'),
            'invoice_due_date' => array('label' => 'Invoice Due Date', 'key' => 'invoice_due_date'),
            'purchase_order_number' => array('label' => 'Purchase Order Number', 'key' => 'purchase_order_number'),
            'currency' => array('label' => 'Currency', 'key' => 'currency'),
            'amount_subtotal_excl_tax' => array('label' => 'Subtotal (excl. tax)', 'key' => 'amount_subtotal_excl_tax'),
            'amount_total_excl_tax' => array('label' => 'Total (excl. tax)', 'key' => 'amount_total_excl_tax'),
            'amount_total_tax' => array('label' => 'Total Tax', 'key' => 'amount_total_tax'),
            'amount_total_incl_tax' => array('label' => 'Total (incl. tax)', 'key' => 'amount_total_incl_tax'),
            'seller_name' => array('label' => 'Seller Name', 'key' => 'seller_name'),
            'seller_vat_number' => array('label' => 'Seller VAT Number', 'key' => 'seller_vat_number'),
            'buyer_name' => array('label' => 'Buyer Name', 'key' => 'buyer_name'),
            'buyer_address' => array('label' => 'Buyer Address', 'key' => 'buyer_address'),
            'credit_note_number' => array('label' => 'Credit Note Number', 'key' => 'credit_note_number'),
            'credit_note_date' => array('label' => 'Credit Note Date', 'key' => 'credit_note_date'),
            'original_invoice_number' => array('label' => 'Original Invoice Number', 'key' => 'original_invoice_number'),
            'original_invoice_date' => array('label' => 'Original Invoice Date', 'key' => 'original_invoice_date'),
            'credit_reason' => array('label' => 'Credit Reason', 'key' => 'credit_reason'),
            'credit_subtotal_excl_tax' => array('label' => 'Credit Subtotal (excl. tax)', 'key' => 'credit_subtotal_excl_tax'),
            'credit_total_tax' => array('label' => 'Credit Total Tax', 'key' => 'credit_total_tax'),
            'credit_total_incl_tax' => array('label' => 'Credit Total (incl. tax)', 'key' => 'credit_total_incl_tax'),
            'merchant_name' => array('label' => 'Merchant Name', 'key' => 'merchant_name'),
            'merchant_vat_number' => array('label' => 'Merchant VAT Number', 'key' => 'merchant_vat_number'),
            'merchant_address_line1' => array('label' => 'Merchant Address', 'key' => 'merchant_address_line1'),
            'merchant_address_line2' => array('label' => 'Merchant Address Line 2', 'key' => 'merchant_address_line2'),
            'merchant_postcode' => array('label' => 'Merchant Postcode', 'key' => 'merchant_postcode'),
            'merchant_city' => array('label' => 'Merchant City', 'key' => 'merchant_city'),
            'merchant_country' => array('label' => 'Merchant Country', 'key' => 'merchant_country'),
            'receipt_number' => array('label' => 'Receipt Number', 'key' => 'receipt_number'),
            'receipt_date' => array('label' => 'Receipt Date', 'key' => 'receipt_date'),
            'payment_method' => array('label' => 'Payment Method', 'key' => 'payment_method'),
            'total_amount' => array('label' => 'Total Amount', 'key' => 'total_amount'),
            'tax_amount' => array('label' => 'Tax Amount', 'key' => 'tax_amount'),
            'document_type' => array('label' => 'Document Type', 'key' => 'document_type'),
            'document_number' => array('label' => 'Document Number', 'key' => 'document_number'),
            'document_date' => array('label' => 'Document Date', 'key' => 'document_date'),
            'starting_point' => array('label' => 'Starting Point', 'key' => 'starting_point'),
            'point_of_arrival' => array('label' => 'Point of Arrival', 'key' => 'point_of_arrival'),
            'trip_length' => array('label' => 'Trip Length (km/miles)', 'key' => 'trip_length'),
            'toll_amount' => array('label' => 'Toll Amount', 'key' => 'toll_amount'),
            'gas_amount' => array('label' => 'Gas Amount', 'key' => 'gas_amount'),
            'bank_user_id_first_name' => array('label' => 'Account Holder First Name', 'key' => 'bank_user_id_first_name'),
            'bank_user_id_last_name' => array('label' => 'Account Holder Last Name', 'key' => 'bank_user_id_last_name'),
            'bank_user_id_gender' => array('label' => 'Account Holder Gender', 'key' => 'bank_user_id_gender'),
            'bank_BIC' => array('label' => 'BIC/SWIFT Code', 'key' => 'bank_BIC'),
            'bank_IBAN' => array('label' => 'IBAN', 'key' => 'bank_IBAN'),
            'bank_name' => array('label' => 'Bank Name', 'key' => 'bank_name'),
            'bank_address' => array('label' => 'Bank Address', 'key' => 'bank_address'),
            'bank_city' => array('label' => 'Bank City', 'key' => 'bank_city'),
            'bank_postal_code' => array('label' => 'Bank Postal Code', 'key' => 'bank_postal_code'),
            'bank_country' => array('label' => 'Bank Country', 'key' => 'bank_country'),
        );
    }

    // =====================================
    // AJAX Handlers
    // =====================================

    /**
     * AJAX: Get all profiles
     */
    public function ajax_get_profiles()
    {
        check_ajax_referer('gravity_extract_profiles', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'gravity-extract')));
            return;
        }

        $profiles = $this->get_all_profiles();
        wp_send_json_success(array('profiles' => $profiles));
    }

    /**
     * AJAX: Get single profile
     */
    public function ajax_get_profile()
    {
        check_ajax_referer('gravity_extract_profiles', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'gravity-extract')));
            return;
        }

        $slug = sanitize_text_field($_POST['slug'] ?? '');
        if (empty($slug)) {
            wp_send_json_error(array('message' => __('Profile slug is required', 'gravity-extract')));
            return;
        }

        $profile = $this->get_profile($slug);
        if (!$profile) {
            wp_send_json_error(array('message' => __('Profile not found', 'gravity-extract')));
            return;
        }

        wp_send_json_success(array('profile' => $profile, 'slug' => $slug));
    }

    /**
     * AJAX: Save profile (create or update)
     */
    public function ajax_save_profile()
    {
        check_ajax_referer('gravity_extract_profiles', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'gravity-extract')));
            return;
        }

        $name = sanitize_text_field($_POST['name'] ?? '');
        $slug = sanitize_text_field($_POST['slug'] ?? '');
        $fields_json = wp_unslash($_POST['fields'] ?? '[]');
        $fields = json_decode($fields_json, true);

        if (empty($name)) {
            wp_send_json_error(array('message' => __('Profile name is required', 'gravity-extract')));
            return;
        }

        if (!is_array($fields) || empty($fields)) {
            wp_send_json_error(array('message' => __('At least one field must be enabled', 'gravity-extract')));
            return;
        }

        // If no slug provided, generate one (new profile)
        if (empty($slug)) {
            $slug = $this->generate_slug($name);
        }

        $data = array(
            'name' => $name,
            'fields' => $fields
        );

        if ($this->save_profile($slug, $data)) {
            wp_send_json_success(array(
                'message' => __('Profile saved successfully', 'gravity-extract'),
                'slug' => $slug,
                'profile' => $data
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save profile', 'gravity-extract')));
        }
    }

    /**
     * AJAX: Delete profile
     */
    public function ajax_delete_profile()
    {
        check_ajax_referer('gravity_extract_profiles', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'gravity-extract')));
            return;
        }

        $slug = sanitize_text_field($_POST['slug'] ?? '');
        if (empty($slug)) {
            wp_send_json_error(array('message' => __('Profile slug is required', 'gravity-extract')));
            return;
        }

        if ($this->delete_profile($slug)) {
            wp_send_json_success(array('message' => __('Profile deleted successfully', 'gravity-extract')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete profile', 'gravity-extract')));
        }
    }

    /**
     * AJAX: Duplicate profile
     */
    public function ajax_duplicate_profile()
    {
        check_ajax_referer('gravity_extract_profiles', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'gravity-extract')));
            return;
        }

        $slug = sanitize_text_field($_POST['slug'] ?? '');
        $new_name = sanitize_text_field($_POST['new_name'] ?? '');

        if (empty($slug)) {
            wp_send_json_error(array('message' => __('Profile slug is required', 'gravity-extract')));
            return;
        }

        if (empty($new_name)) {
            wp_send_json_error(array('message' => __('New profile name is required', 'gravity-extract')));
            return;
        }

        $new_slug = $this->duplicate_profile($slug, $new_name);
        if ($new_slug) {
            wp_send_json_success(array(
                'message' => __('Profile duplicated successfully', 'gravity-extract'),
                'slug' => $new_slug,
                'profile' => $this->get_profile($new_slug)
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to duplicate profile', 'gravity-extract')));
        }
    }

    /**
     * AJAX: Import profile from JSON
     */
    public function ajax_import_profile()
    {
        check_ajax_referer('gravity_extract_profiles', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'gravity-extract')));
            return;
        }

        $json_data = wp_unslash($_POST['json_data'] ?? '');
        if (empty($json_data)) {
            wp_send_json_error(array('message' => __('No import data provided', 'gravity-extract')));
            return;
        }

        $data = json_decode($json_data, true);
        if (!$data || !isset($data['name']) || !isset($data['fields'])) {
            wp_send_json_error(array('message' => __('Invalid profile format', 'gravity-extract')));
            return;
        }

        $slug = $this->generate_slug($data['name']);
        $profile_data = array(
            'name' => sanitize_text_field($data['name']),
            'fields' => $data['fields']
        );

        if ($this->save_profile($slug, $profile_data)) {
            wp_send_json_success(array(
                'message' => __('Profile imported successfully', 'gravity-extract'),
                'slug' => $slug,
                'profile' => $profile_data
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to import profile', 'gravity-extract')));
        }
    }

    /**
     * AJAX: Get master fields list
     */
    public function ajax_get_master_fields()
    {
        check_ajax_referer('gravity_extract_profiles', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'gravity-extract')));
            return;
        }

        $fields = $this->get_master_fields();
        wp_send_json_success(array('fields' => $fields));
    }

    /**
     * AJAX: Detect fields from sample document using AI
     */
    public function ajax_detect_fields()
    {
        check_ajax_referer('gravity_extract_profiles', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'gravity-extract')));
            return;
        }

        // Check for uploaded file
        if (empty($_FILES['sample_file'])) {
            wp_send_json_error(array('message' => __('No file uploaded', 'gravity-extract')));
            return;
        }

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;

        // Get API settings from form settings
        $api_key = '';
        $model = '';

        if ($form_id) {
            $form_settings = Gravity_Extract_Addon::get_form_extract_settings($form_id);
            $api_key = isset($form_settings['gravity_extract_api_key']) ? $form_settings['gravity_extract_api_key'] : '';
            $model = isset($form_settings['gravity_extract_model']) ? $form_settings['gravity_extract_model'] : '';
        }

        if (empty($api_key) || empty($model)) {
            wp_send_json_error(array('message' => __('Please configure API Key and Model in Form Settings → Gravity Extract first.', 'gravity-extract')));
            return;
        }

        // Include WordPress file handling functions
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        // Upload file to temp location
        $file = $_FILES['sample_file'];
        $upload = wp_handle_upload($file, array('test_form' => false));

        if (isset($upload['error'])) {
            wp_send_json_error(array('message' => $upload['error']));
            return;
        }

        $file_path = $upload['file'];
        $mime_type = $upload['type'];

        // Convert PDF to JPEG if necessary
        if ($mime_type === 'application/pdf') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Gravity Extract: Detect - PDF detected, converting to JPEG');
            }
            $jpeg_path = $this->convert_pdf_to_jpeg_static($file_path);

            if ($jpeg_path) {
                $file_path = $jpeg_path;
            } else {
                @unlink($upload['file']);
                wp_send_json_error(array('message' => __('PDF conversion failed. Please try uploading an image instead.', 'gravity-extract')));
                return;
            }
        }

        // Optimize image for analysis
        $optimized_file = $this->optimize_image_static($file_path);
        $file_to_analyze = $optimized_file ? $optimized_file : $file_path;

        // Read file and convert to base64
        $file_content = file_get_contents($file_to_analyze);
        if ($file_content === false) {
            @unlink($file_path);
            if ($optimized_file)
                @unlink($optimized_file);
            wp_send_json_error(array('message' => __('Failed to read uploaded file', 'gravity-extract')));
            return;
        }

        $image_base64 = base64_encode($file_content);

        // Clean up temp files
        @unlink($file_path);
        if ($optimized_file && $optimized_file !== $file_path) {
            @unlink($optimized_file);
        }

        // Call POE API to detect fields
        $result = Gravity_Extract_POE_API::detect_document_fields($api_key, $model, $image_base64);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        if (isset($result['success']) && $result['success'] && isset($result['fields'])) {
            wp_send_json_success(array(
                'fields' => $result['fields'],
                'message' => sprintf(__('Detected %d fields from document', 'gravity-extract'), count($result['fields']))
            ));
        } else {
            wp_send_json_error(array('message' => __('No fields detected from document', 'gravity-extract')));
        }
    }

    /**
     * Convert PDF to JPEG (static helper)
     */
    private function convert_pdf_to_jpeg_static($pdf_path, $max_pages = 10)
    {
        if (!extension_loaded('imagick')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Gravity Extract: Imagick extension not available for PDF conversion');
            }
            return false;
        }

        try {
            $imagick = new \Imagick();
            $imagick->setResolution(150, 150);
            $imagick->pingImage($pdf_path);
            $total_pages = $imagick->getNumberImages();
            $imagick->clear();
            $imagick->destroy();

            $pages_to_process = $max_pages > 0 ? min($total_pages, $max_pages) : $total_pages;
            $page_images = array();
            $total_width = 0;
            $total_height = 0;

            for ($i = 0; $i < $pages_to_process; $i++) {
                $page_imagick = new \Imagick();
                $page_imagick->setBackgroundColor('white');
                $page_imagick->setResolution(150, 150);
                $page_imagick->readImage($pdf_path . '[' . $i . ']');
                $page_imagick = $page_imagick->flattenImages();
                $page_imagick->setImageColorspace(\Imagick::COLORSPACE_SRGB);

                $width = $page_imagick->getImageWidth();
                $height = $page_imagick->getImageHeight();
                $total_width = max($total_width, $width);
                $total_height += $height;

                $page_images[] = array(
                    'imagick' => $page_imagick,
                    'width' => $width,
                    'height' => $height
                );
            }

            $merged = new \Imagick();
            $merged->newImage($total_width, $total_height, new \ImagickPixel('white'));
            $merged->setImageFormat('jpeg');

            $y_offset = 0;
            foreach ($page_images as $page) {
                $x_offset = ($total_width - $page['width']) / 2;
                $merged->compositeImage($page['imagick'], \Imagick::COMPOSITE_OVER, (int) $x_offset, $y_offset);
                $y_offset += $page['height'];
                $page['imagick']->clear();
                $page['imagick']->destroy();
            }

            $merged->setImageCompressionQuality(85);
            $jpeg_path = preg_replace('/\.pdf$/i', '.jpg', $pdf_path);
            $merged->writeImage($jpeg_path);

            $merged->clear();
            $merged->destroy();

            @unlink($pdf_path);
            return $jpeg_path;
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Gravity Extract: PDF conversion failed - ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Optimize image for analysis (static helper)
     */
    private function optimize_image_static($file_path)
    {
        if (!file_exists($file_path)) {
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
                return false;
        }

        if (!$image) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);

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

        $resized = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        imagedestroy($image);

        $temp_file = $file_path . '_optimized.jpg';
        $success = imagejpeg($resized, $temp_file, 60);
        imagedestroy($resized);

        return $success ? $temp_file : false;
    }
}
