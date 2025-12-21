# Gravity Extract

**AI-powered document data extraction for Gravity Forms**

Gravity Extract is a WordPress plugin that integrates with Gravity Forms to automatically extract data from uploaded documents (invoices, receipts, tickets, etc.) using AI vision models. Upload a document image or PDF, and watch as form fields are automatically populated with extracted information.

> 💡 **100% Free to use!** This plugin works with [POE's free API](https://poe.com), giving you access to powerful AI models like GPT-4o, Claude, and Gemini at no cost. A free [POE account](https://poe.com) is required.

![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)
![Gravity Forms](https://img.shields.io/badge/Gravity%20Forms-2.5%2B-green)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)
![License](https://img.shields.io/badge/License-AGPL%20v3-orange)

---

## ✨ Features

### 📄 Document Upload
- **Drag & drop** or click to upload document images
- Supports **JPEG, PNG, WebP, and PDF** formats
- **Multi-page PDF support** - All pages merged into one image for AI analysis
- Up to 10 pages processed by default (configurable)
- Real-time upload progress with visual feedback

### 🤖 AI-Powered Extraction
- **Free to use** with [POE](https://poe.com) API (account required)
- Access to multiple vision models: GPT-4o, Claude, Gemini, and more
- Extracts structured data: merchant details, dates, amounts, addresses, line items
- Smart field detection for invoices, receipts, and general documents
- Returns clean JSON for reliable field population

### 🔗 Field Mapping
- Map extracted data to any Gravity Forms field type
- Support for complex fields: Address (with sub-fields), Date, Time, Dropdown
- **AI Auto-Mapping**: Automatically suggest best matches between extracted data and form fields
- **Mapping Profiles**: Save and reuse mapping configurations

### 🎨 Modern UI
- Clean, responsive upload interface
- Live preview of uploaded documents
- Status indicators for processing stages
- Seamless integration with Gravity Forms editor

---

## 📋 Requirements

| Requirement | Minimum Version |
|-------------|-----------------|
| WordPress | 5.0+ |
| Gravity Forms | 2.5+ |
| PHP | 7.4+ |
| PHP Extensions | Imagick (for PDF) |

### Optional (recommended)
- **Imagick PHP extension** - for PDF to JPEG conversion

---

## 🚀 Installation

1. **Download** the plugin ZIP file
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Upload the ZIP and click **Install Now**
4. **Activate** the plugin
5. Configure your POE API key (see Configuration)

### Manual Installation
```bash
cd /wp-content/plugins/
unzip gravity-extract.zip
```

---

## ⚙️ Configuration

### 1. Create a POE Account (Required)

> ⚠️ **A [POE](https://poe.com) account is mandatory** for this plugin to work. POE provides free access to AI models.

1. Go to **[poe.com](https://poe.com)** and create a free account (or sign in)
2. Navigate to **Settings → API Keys** (or visit [poe.com/api_key](https://poe.com/api_key))
3. Create a new API key
4. Copy the key to use in the plugin settings

> 💡 The free POE API tier includes generous usage limits for personal and small business use.

### 2. Plugin Settings
Navigate to **Settings → Gravity Extract** in WordPress admin:

| Setting | Description |
|---------|-------------|
| **POE API Key** | Your POE API key for AI model access |
| **Default Model** | AI model to use (default: `Gemini-3.0-Flash`) |

### 3. Field Settings
In the Gravity Forms editor, add a **Gravity Extract** field and configure:

- **API Key** (override global setting)
- **AI Model** (override global setting)
- **Field Mappings** - Map extracted data to form fields

---

## 📖 Usage

### Adding the Field
1. Edit a Gravity Form
2. Add an **Advanced → Gravity Extract** field
3. Configure field settings in the sidebar

### Creating Field Mappings
1. In the field settings, click **Field Mappings**
2. Select an **Extracted Field** (e.g., `merchant_name`, `total_amount`)
3. Select a **Target Field** from your form
4. Click **Add Mapping**

### AI Auto-Mapping
1. Click the **AI Auto-Map** button
2. The AI analyzes your form fields and extracted data keys
3. Suggested mappings are automatically created
4. Review and adjust as needed

### Mapping Profiles
1. Create your mappings
2. Enter a profile name and click **Save Profile**
3. Load saved profiles from the dropdown

---

## 🔧 Extracted Data Fields

The AI extracts the following data categories:

### Merchant Information
| Field Key | Description |
|-----------|-------------|
| `merchant_name` | Business/store name |
| `merchant_address` | Full address |
| `merchant_city` | City |
| `merchant_postal_code` | ZIP/postal code |
| `merchant_country` | Country |
| `merchant_phone` | Phone number |
| `merchant_email` | Email address |
| `merchant_siret` | SIRET number (France) |
| `merchant_vat_number` | VAT/Tax ID |

### Transaction Details
| Field Key | Description |
|-----------|-------------|
| `invoice_number` | Invoice/receipt number |
| `invoice_date` | Document date |
| `invoice_time` | Transaction time |
| `payment_method` | Payment method used |
| `currency` | Currency code |

### Amounts
| Field Key | Description |
|-----------|-------------|
| `subtotal` | Amount before tax |
| `tax_amount` | Tax amount |
| `tax_rate` | Tax percentage |
| `total_amount` | Total including tax |
| `tip_amount` | Tip/gratuity |
| `discount_amount` | Discount applied |

### Mileage Expenses (Map Screenshots)
| Field Key | Description |
|-----------|-------------|
| `starting_point` | Departure location |
| `point_of_arrival` | Arrival location |
| `trip_length` | Distance (with units stripped) |
| `toll_amount` | Cost of tolls (numeric) |
| `gas_amount` | Cost of fuel (numeric) |

### Items
| Field Key | Description |
|-----------|-------------|
| `items` | Array of line items |
| `items[].description` | Item description |
| `items[].quantity` | Quantity |
| `items[].unit_price` | Price per unit |
| `items[].total` | Line total |

---

## 🖥️ Server Requirements for Advanced Features

### PDF Support
Requires **Imagick PHP extension** with PDF policy enabled:

```bash
# Debian/Ubuntu
sudo apt-get install php-imagick ghostscript

# Enable PDF in ImageMagick policy
sudo nano /etc/ImageMagick-6/policy.xml
# Change: <policy domain="coder" rights="none" pattern="PDF" />
# To: <policy domain="coder" rights="read|write" pattern="PDF" />

sudo systemctl restart apache2
```
---

## 📁 File Structure

```
gravity-extract/
├── gravity-extract.php          # Main plugin file
├── includes/
│   ├── class-gravity-extract.php       # Core plugin class
│   ├── class-gf-field-gravity-extract.php  # Custom GF field
│   └── class-poe-api-service.php       # POE API integration
├── js/
│   ├── gravity-extract-admin.js        # Admin JavaScript
│   └── gravity-extract-frontend.js     # Frontend JavaScript
├── css/
│   ├── gravity-extract-admin.css       # Admin styles
│   └── gravity-extract-frontend.css    # Frontend styles
└── README.md
```

---

## 🔌 Hooks & Filters

### Filters

```php
// Modify extraction prompt
add_filter('gravity_extract_prompt', function($prompt) {
    return $prompt . "\nAlso extract: custom_field";
});

// Modify extracted data before population
add_filter('gravity_extract_data', function($data, $form_id, $field_id) {
    // Modify $data array
    return $data;
}, 10, 3);
```

---

## 🐛 Troubleshooting

### PDF Upload Fails
- Check if Imagick extension is installed: `php -m | grep imagick`
- Verify ImageMagick PDF policy is enabled
- Check server error logs for specific errors

### Fields Not Populating
- Verify field mappings are correctly configured
- Check browser console for JavaScript errors
- Enable WordPress debug logging

### Debug Logging
Enable WordPress debug logging to troubleshoot:
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs at `/wp-content/debug.log`

---

## 📄 License

This plugin is licensed under the **GNU Affero General Public License v3 (AGPL-3.0)**.

```
Gravity Extract - AI Document Data Extraction for Gravity Forms
Copyright (C) 2024

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as published
by the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU Affero General Public License for more details.
```

---

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📞 Support

- **Issues**: Open a GitHub issue for bugs and feature requests
- **Documentation**: Check this README and inline code comments

---

## 🙏 Acknowledgments

- [Gravity Forms](https://www.gravityforms.com/) - Form plugin for WordPress
- [POE](https://poe.com/) - AI API platform

---

<p align="center">
  Made with ❤️ for the WordPress community
</p>



