# Gravity Extract

AI-powered document data extraction for Gravity Forms. Upload invoices or receipts and automatically populate form fields using powerful AI vision models.

## Document Upload
-   **Drag & Drop Interface:** Easy-to-use upload area for document images and PDFs.
-   **Multi-Format Support:** Handles JPEG, PNG, WebP, and multi-page PDFs (automatically converted).
-   **Real-Time Feedback:** Visual progress indicators and immediate preview of uploaded documents.

## AI-Powered Extraction
-   **Vision Model Integration:** Connects with GPT-4o, Claude, and Gemini via POE API.
-   **Smart Data Parsing:** Extracts merchant details, amounts, dates, and line items with high accuracy.
-   **Structured JSON Output:** Returns clean, structured data ready for form population.

## Field Mapping
-   **Flexible Mapping System:** Associate extracted data with any Gravity Forms field type.
-   **AI Auto-Mapping:** Automatically suggests the best connections between extracted data and form fields.
-   **Custom Profiles:** Save, export, and reuse mapping configurations for different document types.

## Key Features
-   **AI Field Detection:** Automatically detects available fields in custom documents
-   **Multilingual:** Works with content in any language
-   **Translation-Ready:** All strings are internationalized
-   **Secure:** POE API Integration ensures data privacy
-   **GitHub Updates:** Automatic updates from GitHub releases

## Requirements
-   POE API Key (Free account required)
-   WordPress 5.0 or higher
-   PHP 7.4 or higher

## Installation
1.  Upload the `gravity-extract` folder to `/wp-content/plugins/`
2.  Activate the plugin through the **Plugins** menu in WordPress
3.  Go to **Forms → Settings → Gravity Extract** and configure your POE API Key
4.  Add a **Gravity Extract** field to your form to start using it

## FAQ
### How do I get a POE API Key?
You need a free account at [poe.com](https://poe.com). Once logged in, visit your settings or the API section to generate a key.

### Does it work with PDFs?
Yes, multi-page PDFs are supported. The plugin converts them to images for the AI model to analyze.

### Can I customize the extraction prompt?
Yes, use the `gravity_extract_prompt` filter:
```php
add_filter('gravity_extract_prompt', function($prompt) {
    return $prompt . "\nAlso extract the warranty period.";
});
```

## Project Structure
```
.
├── gravity-extract.php              # Main plugin file
├── README.md
├── css
│   ├── gravity-extract-admin.css    # Admin styles
│   └── gravity-extract-frontend.css # Frontend styles
├── includes
│   ├── class-gravity-extract.php        # Core plugin class
│   ├── class-github-updater.php         # GitHub auto-updates
│   ├── class-gravity-extract-addon.php  # GF Addon settings
│   ├── class-gf-field-gravity-extract.php # Custom GF field
│   └── class-mapping-profiles-manager.php # Profiles manager
└── js
    ├── gravity-extract-admin.js     # Admin scripts
    └── gravity-extract-frontend.js  # Frontend scripts
```

## Changelog

### 1.0.2
-   **Security:** Fixed SSL verification issue in POE API service
-   **Performance:** Increased memory limit for image processing
-   **Improvement:** Cleaned up debug logs for better production hygiene

### 1.0.1
-   **New:** Added GitHub auto-update support
-   **Improved:** Updated README documentation structure

### 1.0.0
-   Initial release
-   AI-powered document extraction
-   Gravity Forms integration
-   Drag and drop upload
-   Field mapping profiles

## License
This project is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0) - see the [LICENSE](LICENSE) file for details.

---

<p align="center">
  Made with love for the WordPress community
</p>
