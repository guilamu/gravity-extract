<?php
/**
 * POE API Service for Gravity Extract
 * Handles communication with POE API for image analysis
 */

if (!defined('ABSPATH')) {
    exit;
}

class Gravity_Extract_POE_API
{

    /**
     * POE API base URL
     */
    private const API_BASE_URL = 'https://api.poe.com';

    /**
     * Get available models that support image input
     *
     * @param string $api_key POE API key
     * @return array|WP_Error Array of models or error
     */
    public static function get_models($api_key)
    {
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key is required', 'gravity-extract'));
        }

        // Check cache
        $cache_key = 'gravity_extract_poe_models_' . md5($api_key);
        $cached_models = get_transient($cache_key);

        if ($cached_models !== false) {
            error_log('Gravity Extract POE API: Using cached models');
            return $cached_models;
        }

        error_log('Gravity Extract POE API: Calling ' . self::API_BASE_URL . '/v1/models');

        $response = wp_remote_get(self::API_BASE_URL . '/v1/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'timeout' => 30,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            error_log('Gravity Extract POE API: WP Error - ' . $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        error_log('Gravity Extract POE API: Response status ' . $status_code);

        if ($status_code !== 200) {
            $error_body = wp_remote_retrieve_body($response);
            error_log('Gravity Extract POE API: Error body - ' . substr($error_body, 0, 500));
            return new WP_Error('api_error', sprintf(__('API returned status %d', 'gravity-extract'), $status_code));
        }

        $raw_body = wp_remote_retrieve_body($response);
        error_log('Gravity Extract POE API: Response length ' . strlen($raw_body) . ' bytes');

        $body = json_decode($raw_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Gravity Extract POE API: JSON decode error - ' . json_last_error_msg());
            return new WP_Error('json_error', __('Failed to parse API response', 'gravity-extract'));
        }

        $models = array();
        $total_models = count($body['data'] ?? array());
        error_log('Gravity Extract POE API: Total models in response: ' . $total_models);

        foreach ($body['data'] ?? array() as $model) {
            $input_modalities = $model['architecture']['input_modalities'] ?? array();

            // Only include models that support image input
            if (in_array('image', $input_modalities)) {
                $models[] = array(
                    'id' => $model['id'],
                    'name' => $model['metadata']['display_name'] ?? $model['id'],
                );
            }
        }

        error_log('Gravity Extract POE API: Found ' . count($models) . ' image-capable models');

        // Sort alphabetically by name
        usort($models, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $models;
    }

    /**
     * Analyze an image using POE API
     *
     * @param string $api_key POE API key
     * @param string $model Model ID to use
     * @param string $image_base64 Base64 encoded image
     * @return array|WP_Error Extracted data or error
     */
    public static function analyze_image($api_key, $model, $image_base64)
    {
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key is required', 'gravity-extract'));
        }

        if (empty($model)) {
            return new WP_Error('missing_model', __('Model is required', 'gravity-extract'));
        }

        if (empty($image_base64)) {
            return new WP_Error('missing_image', __('Image is required', 'gravity-extract'));
        }

        // Build the prompt for invoice extraction
        $prompt = self::get_invoice_extraction_prompt();

        // Build multimodal content
        $message_content = array(
            array(
                'type' => 'text',
                'text' => $prompt
            ),
            array(
                'type' => 'image_url',
                'image_url' => array(
                    'url' => 'data:image/webp;base64,' . $image_base64
                )
            )
        );

        // Build payload
        $messages = array(
            array(
                'role' => 'user',
                'content' => $message_content
            )
        );

        $response = self::make_api_request($api_key, $model, $messages);

        if (is_wp_error($response)) {
            return $response;
        }

        // Response is already parsed by make_api_request
        if (isset($response['choices'][0]['message']['content'])) {
            $content = $response['choices'][0]['message']['content'];
            return self::parse_response_content($content);
        }

        return new WP_Error('invalid_response', __('Invalid API response', 'gravity-extract'));
    }

    /**
     * Make API request to POE
     */
    private static function make_api_request($api_key, $model, $messages)
    {
        $payload = array(
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => 2000,
        );

        $response = wp_remote_post(self::API_BASE_URL . '/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($payload),
            'timeout' => 60,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            error_log('Gravity Extract POE API Error: ' . $body);
            return new WP_Error('api_error', sprintf(__('API returned status %d', 'gravity-extract'), $status_code));
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Parse response content (helper)
     */
    private static function parse_response_content($content)
    {
        $json = self::extract_json_from_text($content);

        if ($json) {
            return array(
                'success' => true,
                'extracted_text' => self::format_extracted_data($json),
                'raw_data' => $json,
            );
        }

        // Return raw text if not JSON
        return array(
            'success' => true,
            'extracted_text' => $content,
            'raw_data' => null,
        );
    }

    /**
     * Helper: Extract JSON from text (robust)
     */
    private static function extract_json_from_text($text)
    {
        // 1. Try to find markdown code block
        if (preg_match('/```json\s*(.*?)\s*```/s', $text, $matches)) {
            $json = json_decode($matches[1], true);
            if ($json)
                return $json;
        }

        // 2. Try to find outer braces (greedy match from first { to last })
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json)
                return $json;
        }

        // 3. Fallback: try raw text
        return json_decode($text, true);
    }

    /**
     * Get the prompt for invoice extraction
     *
     * @return string
     */
    private static function get_invoice_extraction_prompt()
    {
        return "You are an expert at extracting information from documents, receipts, and invoices.

Analyze this image and extract ALL relevant information. Return ONLY valid JSON in the following format:

{
  \"vendor\": {
    \"name\": \"Company/vendor name\",
    \"address\": \"Full address if visible\",
    \"phone\": \"Phone number if visible\",
    \"email\": \"Email if visible\",
    \"website\": \"Website if visible\"
  },
  \"invoice_details\": {
    \"invoice_number\": \"Invoice/receipt number\",
    \"date\": \"Invoice date (YYYY-MM-DD format)\",
    \"due_date\": \"Due date if applicable\",
    \"payment_terms\": \"Payment terms if visible\"
  },
  \"customer\": {
    \"name\": \"Customer name if visible\",
    \"address\": \"Customer address if visible\"
  },
  \"line_items\": [
    {
      \"description\": \"Item description\",
      \"quantity\": \"Quantity\",
      \"unit_price\": \"Unit price\",
      \"total\": \"Line total\"
    }
  ],
  \"totals\": {
    \"subtotal\": \"Subtotal amount\",
    \"tax\": \"Tax amount\",
    \"discount\": \"Discount if any\",
    \"total\": \"Total amount\",
    \"currency\": \"Currency code (EUR, USD, etc.)\"
  },
  \"additional_info\": \"Any other relevant information\"
}

RULES:
1. Extract ALL visible text precisely
2. Use null for fields not found in the image
3. For amounts, include currency symbol if visible
4. For dates, convert to YYYY-MM-DD format when possible
5. Return ONLY the JSON, no explanations

JSON:";
    }

    /**
     * Format extracted JSON data into readable text
     *
     * @param array $data Extracted data
     * @return string Formatted text
     */
    private static function format_extracted_data($data)
    {
        $lines = array();

        // Vendor info
        if (!empty($data['vendor'])) {
            $vendor = $data['vendor'];
            $lines[] = '=== VENDOR ===';
            if (!empty($vendor['name']))
                $lines[] = 'Name: ' . $vendor['name'];
            if (!empty($vendor['address']))
                $lines[] = 'Address: ' . $vendor['address'];
            if (!empty($vendor['phone']))
                $lines[] = 'Phone: ' . $vendor['phone'];
            if (!empty($vendor['email']))
                $lines[] = 'Email: ' . $vendor['email'];
            if (!empty($vendor['website']))
                $lines[] = 'Website: ' . $vendor['website'];
            $lines[] = '';
        }

        // Invoice details
        if (!empty($data['invoice_details'])) {
            $invoice = $data['invoice_details'];
            $lines[] = '=== INVOICE DETAILS ===';
            if (!empty($invoice['invoice_number']))
                $lines[] = 'Invoice #: ' . $invoice['invoice_number'];
            if (!empty($invoice['date']))
                $lines[] = 'Date: ' . $invoice['date'];
            if (!empty($invoice['due_date']))
                $lines[] = 'Due Date: ' . $invoice['due_date'];
            if (!empty($invoice['payment_terms']))
                $lines[] = 'Payment Terms: ' . $invoice['payment_terms'];
            $lines[] = '';
        }

        // Customer info
        if (!empty($data['customer']) && (!empty($data['customer']['name']) || !empty($data['customer']['address']))) {
            $customer = $data['customer'];
            $lines[] = '=== CUSTOMER ===';
            if (!empty($customer['name']))
                $lines[] = 'Name: ' . $customer['name'];
            if (!empty($customer['address']))
                $lines[] = 'Address: ' . $customer['address'];
            $lines[] = '';
        }

        // Line items
        if (!empty($data['line_items']) && is_array($data['line_items'])) {
            $lines[] = '=== LINE ITEMS ===';
            foreach ($data['line_items'] as $index => $item) {
                $lines[] = ($index + 1) . '. ' . ($item['description'] ?? 'Item');
                if (!empty($item['quantity']))
                    $lines[] = '   Qty: ' . $item['quantity'];
                if (!empty($item['unit_price']))
                    $lines[] = '   Unit Price: ' . $item['unit_price'];
                if (!empty($item['total']))
                    $lines[] = '   Total: ' . $item['total'];
            }
            $lines[] = '';
        }

        // Totals
        if (!empty($data['totals'])) {
            $totals = $data['totals'];
            $currency = !empty($totals['currency']) ? ' ' . $totals['currency'] : '';
            $lines[] = '=== TOTALS ===';
            if (!empty($totals['subtotal']))
                $lines[] = 'Subtotal: ' . $totals['subtotal'] . $currency;
            if (!empty($totals['tax']))
                $lines[] = 'Tax: ' . $totals['tax'] . $currency;
            if (!empty($totals['discount']))
                $lines[] = 'Discount: ' . $totals['discount'] . $currency;
            if (!empty($totals['total']))
                $lines[] = 'TOTAL: ' . $totals['total'] . $currency;
            $lines[] = '';
        }

        // Additional info
        if (!empty($data['additional_info'])) {
            $lines[] = '=== ADDITIONAL INFO ===';
            $lines[] = $data['additional_info'];
        }

        return implode("\n", $lines);
    }

    /**
     * Analyze image and extract specific keys based on profile
     *
     * @param string $api_key POE API key
     * @param string $model AI model to use
     * @param string $image_base64 Base64 encoded image
     * @param string $profile Document profile type
     * @param array $keys_to_extract Keys to extract from the document
     * @return array|WP_Error
     */
    public static function analyze_image_with_keys($api_key, $model, $image_base64, $profile, $keys_to_extract)
    {
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('API key is required', 'gravity-extract'));
        }

        if (empty($model)) {
            return new WP_Error('no_model', __('AI model is required', 'gravity-extract'));
        }

        if (empty($image_base64)) {
            return new WP_Error('no_image', __('Image data is required', 'gravity-extract'));
        }

        if (empty($keys_to_extract)) {
            return new WP_Error('no_keys', __('No fields to extract', 'gravity-extract'));
        }

        error_log('Gravity Extract: Analyzing image with keys: ' . implode(', ', $keys_to_extract));

        // Build dynamic prompt based on keys
        $prompt = self::build_extraction_prompt($profile, $keys_to_extract);

        // Build multimodal message content
        $message_content = array(
            array(
                'type' => 'text',
                'text' => $prompt,
            ),
            array(
                'type' => 'image_url',
                'image_url' => array(
                    'url' => 'data:image/jpeg;base64,' . $image_base64,
                ),
            ),
        );

        error_log('Gravity Extract: Calling POE API with model: ' . $model);

        $payload = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $message_content
                )
            ),
            'temperature' => 0.1,
            'max_tokens' => 2000,
        );

        $response = wp_remote_post(self::API_BASE_URL . '/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($payload),
            'timeout' => 90,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            error_log('Gravity Extract POE API Error: ' . $body);
            return new WP_Error('api_error', sprintf(__('API returned status %d', 'gravity-extract'), $status_code));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['choices'][0]['message']['content'])) {
            $content = $body['choices'][0]['message']['content'];

            // Parse JSON response
            $content_clean = preg_replace('/^```json\s*|\s*```$/', '', trim($content));
            $extracted_data = json_decode($content_clean, true);

            if ($extracted_data && is_array($extracted_data)) {
                return array(
                    'success' => true,
                    'extracted_data' => $extracted_data,
                );
            }

            // Try to extract key-value pairs from text if JSON failed
            $extracted_data = self::parse_text_to_keys($content, $keys_to_extract);
            return array(
                'success' => true,
                'extracted_data' => $extracted_data,
            );
        }

        return new WP_Error('invalid_response', __('Invalid API response', 'gravity-extract'));
    }

    /**
     * Build extraction prompt based on profile and keys
     */
    /**
     * Automap fields functionality
     * Match extracted keys to form field labels using AI
     */
    public static function automap_fields($api_key, $model, $keys, $form_fields)
    {
        $url = 'https://api.poe.com/bot/' . $model; // Placeholder - actual endpoint depends on library or wrapper used
        // Using same endpoint logic as analyze_image but text-only prompt if possible, or same multimodal if needed.
        // Assuming we use the analyze_image wrapper logic or a new text-based call.

        // Construct prompt
        $prompt = "You are a helpful assistant helping to map data fields.\n";
        $prompt .= "I have a list of 'Extracted Keys' from an invoice and a list of 'Form Fields' from a website.\n";
        $prompt .= "Please match the Extracted Keys to the most appropriate Form Field based on their names/labels.\n\n";

        $prompt .= "Extracted Keys:\n" . implode("\n", $keys) . "\n\n";

        $prompt .= "Form Fields:\n";
        foreach ($form_fields as $field) {
            $prompt .= "- ID: " . $field['id'] . ", Label: " . $field['label'] . "\n";
        }

        $prompt .= "\nReturn a JSON object where keys are the 'Extracted Key' and values are the matching 'Form Field ID'.\n";
        $prompt .= "If no good match is found for a key, do not include it in the JSON.\n";
        $prompt .= "The JSON should be strictly valid, no markdown formatting.\n";
        $prompt .= "Example output: {\"vendor_name\": \"1\", \"total_amount\": \"5\"}";

        // Reuse existing request logic but without image
        // We modify the messages structure for text-only
        $messages = array(
            array(
                'role' => 'user',
                'content' => $prompt
            )
        );

        $response = self::make_api_request($api_key, $model, $messages);

        if (is_wp_error($response)) {
            return $response;
        }

        // Automap expects JSON mapping
        if (isset($response['choices'][0]['message']['content'])) {
            $content = $response['choices'][0]['message']['content'];
            $json = self::extract_json_from_text($content);

            if ($json) {
                return $json;
            }

            // Log for debugging
            error_log('Gravity Extract: Automap failed to parse JSON. Content: ' . substr($content, 0, 500));
        }

        return new WP_Error('invalid_automap_response', 'Could not parse automap response');
    }

    /**
     * Build extraction prompt based on profile and keys
     */
    private static function build_extraction_prompt($profile, $keys)
    {
        $profile_descriptions = array(
            'supplier_invoice' => 'a supplier invoice (B2B)',
            'sales_invoice' => 'a sales invoice',
            'credit_note' => 'a credit note',
            'generic_receipt' => 'a receipt',
            'restaurant_hotel' => 'a restaurant or hotel receipt',
            'minimal_light' => 'a document (invoice, receipt, or similar)',
        );

        $doc_type = isset($profile_descriptions[$profile]) ? $profile_descriptions[$profile] : 'a document';

        // Check if full_extraction is requested
        $has_full_extraction = in_array('full_extraction', $keys);

        // Filter out full_extraction from the regular keys list for the prompt
        $regular_keys = array_filter($keys, function ($key) {
            return $key !== 'full_extraction';
        });

        $keys_list = implode(', ', $regular_keys);

        $prompt = "You are an expert at extracting information from {$doc_type}.\n\nAnalyze this image and extract the following information.\n\n";

        if ($has_full_extraction) {
            $prompt .= "IMPORTANT: Include a 'full_extraction' field containing ALL readable text from the document, formatted in a structured way with sections and labels.\n\n";
        }

        if (!empty($regular_keys)) {
            $prompt .= "Extract these specific fields:\n{$keys_list}\n\n";
        }

        $prompt .= "Return ONLY valid JSON with the exact keys requested. Use null for any field you cannot find or determine.\n";
        $prompt .= "Do not add any extra text, explanation, or markdown formatting - just pure JSON.\n\n";

        if ($has_full_extraction) {
            $prompt .= "Example format:\n{\"full_extraction\": \"=== VENDOR ===\\nCompany: ACME Corp\\nAddress: 123 Main St\\n\\n=== INVOICE ===\\nNumber: INV-001\\nDate: 2024-01-15\\n\\n=== TOTALS ===\\nSubtotal: 100.00\\nTax: 25.50\\nTotal: 125.50\", \"supplier_name\": \"ACME Corp\", \"invoice_number\": \"INV-001\"}\n\n";
        } else {
            $prompt .= "Example format:\n{\"supplier_name\": \"ACME Corp\", \"invoice_number\": \"INV-001\", \"amount_total_incl_tax\": \"125.50\"}\n\n";
        }

        $prompt .= "Return values as plain strings. For amounts, include the number only (no currency symbols). For dates, use YYYY-MM-DD format if possible.";

        return $prompt;
    }

    /**
     * Try to parse text response to extract keys
     */
    private static function parse_text_to_keys($text, $keys)
    {
        $extracted = array();
        foreach ($keys as $key) {
            $extracted[$key] = null;
            // Try to find key: value patterns
            $pattern = '/' . preg_quote(str_replace('_', ' ', $key), '/') . '\s*[:\-]?\s*([^\n,]+)/i';
            if (preg_match($pattern, $text, $matches)) {
                $extracted[$key] = trim($matches[1]);
            }
        }
        return $extracted;
    }
}
