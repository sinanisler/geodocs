=== GEODocs ===
Contributors: geoparddigital
Tags: documents, ai, pdf, management, organization, openrouter, document-management
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 8.0
Stable tag: 0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered document organizer using OpenRouter & Gemini. Upload, analyze, and organize your documents with artificial intelligence.

== Description ==

**GEODocs** is a powerful WordPress plugin that uses artificial intelligence to automatically organize and categorize your documents. Simply upload a PDF or image, and let AI do the rest!

= Features =

* 🤖 **AI-Powered Analysis** - Automatically extract titles, descriptions, and metadata
* 📁 **Smart Categorization** - Documents are automatically sorted into 12 predefined categories
* 🔍 **Advanced Search** - Find documents quickly with full-text search
* 🏷️ **Category Filtering** - Filter documents by category with beautiful UI
* 📤 **Easy Upload** - Drag & drop or click to upload
* 🔐 **User Privacy** - Each user sees only their own documents
* 💼 **Multi-User Support** - Perfect for teams and organizations
* 🎨 **Beautiful Interface** - Modern, responsive design with Tailwind CSS
* ⚡ **Lightning Fast** - Optimized performance with REST API
* 🔧 **Easy Setup** - Simple configuration in WordPress Settings

= Supported File Types =

* PDF Documents
* Images (JPG, JPEG, PNG, GIF, WebP)

= Document Categories =

* 🧾 Invoices & Receipts
* ⚖️ Legal Contracts
* 🎨 Marketing Assets
* 👥 HR & Employee
* 💰 Business Finance
* 🆔 Personal Identity
* 🏥 Medical Records
* ✈️ Travel & Tickets
* 🏠 Home & Utilities
* 🎓 Education & Courses
* 🛡️ Insurance Docs
* 📁 Other

= AI-Powered Metadata Extraction =

GEODocs automatically extracts:
* Dates
* Amounts and prices
* Company names
* Person names
* Document numbers
* Email addresses
* Phone numbers
* And more!

= How It Works =

1. **Install & Activate** - Install the plugin and activate it
2. **Configure API** - Add your OpenRouter API key in Settings → GEODocs
3. **Add Shortcode** - Add `[geodocs]` to any page
4. **Upload Documents** - Users can upload documents via the frontend
5. **AI Magic** - Documents are automatically analyzed and categorized
6. **Search & Organize** - Find and manage documents easily

= OpenRouter Integration =

GEODocs uses OpenRouter to access powerful AI models like Google Gemini. OpenRouter provides:
* Access to 100+ AI models
* Pay-as-you-go pricing
* Automatic failover
* Free tier available
* Enterprise-grade security

Get your free API key at [openrouter.ai](https://openrouter.ai/keys)

= Shortcode Usage =

Basic usage:
`[geodocs]`

With custom attributes:
`[geodocs view="list" per_page="20" show_upload="true"]`

**Shortcode Attributes:**
* `view` - Display mode: "grid" or "list" (default: "grid")
* `per_page` - Number of documents per page (default: 12)
* `show_upload` - Show upload button: "true" or "false" (default: "true")
* `show_search` - Show search bar: "true" or "false" (default: "true")
* `show_filters` - Show category filters: "true" or "false" (default: "true")

= Privacy & Security =

* Each user can only see and manage their own documents
* Files are stored securely in WordPress uploads directory
* API keys are stored encrypted in WordPress options
* All inputs are sanitized and validated
* CSRF protection with WordPress nonces

= Requirements =

* WordPress 6.0 or higher
* PHP 8.0 or higher
* OpenRouter API key (free tier available)
* Modern web browser with JavaScript enabled

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Go to Plugins → Add New
3. Search for "GEODocs"
4. Click "Install Now" and then "Activate"
5. Go to Settings → GEODocs
6. Add your OpenRouter API key
7. Add `[geodocs]` shortcode to any page

= Manual Installation =

1. Download the plugin ZIP file
2. Log in to your WordPress admin panel
3. Go to Plugins → Add New → Upload Plugin
4. Choose the ZIP file and click "Install Now"
5. Activate the plugin
6. Go to Settings → GEODocs
7. Add your OpenRouter API key
8. Add `[geodocs]` shortcode to any page

= Getting OpenRouter API Key =

1. Visit [openrouter.ai](https://openrouter.ai/keys)
2. Sign up for a free account
3. Go to Keys section
4. Create a new API key
5. Copy the key and paste it in GEODocs settings

== Frequently Asked Questions ==

= Is GEODocs free? =

Yes! GEODocs is completely free. However, you need an OpenRouter API key which has a free tier. You only pay for the AI processing you use.

= How much does AI processing cost? =

With the default Gemini 2.0 Flash model, costs are approximately:
* $0.075 per 1M input tokens
* $0.30 per 1M output tokens

In practice, this means analyzing 1000 documents costs less than $1. OpenRouter also offers a free tier!

= Can I use my own AI model? =

Yes! GEODocs supports any vision-capable model on OpenRouter, including:
* Google Gemini
* Anthropic Claude
* OpenAI GPT-4 Vision
* And many more!

= Is my data private? =

Yes! Your documents are:
* Stored on your WordPress server
* Only sent to OpenRouter for AI analysis
* Never shared with third parties
* Each user can only see their own documents

= Can multiple users use GEODocs? =

Absolutely! GEODocs is designed for multi-user environments. Each user has their own private document library.

= What file types are supported? =

Currently supported:
* PDF documents
* Images: JPG, JPEG, PNG, GIF, WebP

= Can I customize the categories? =

The plugin comes with 12 predefined categories. Custom category management will be added in a future version.

= Does it work on mobile? =

Yes! GEODocs is fully responsive and works great on mobile devices.

= Can I export my documents? =

Document export functionality is planned for a future version.

= Where are files stored? =

Files are stored in your WordPress uploads directory under `/uploads/geodocs/`.

= Can admins see all documents? =

Currently, each user sees only their own documents. Multi-user admin dashboard is planned for a future version.

== Screenshots ==

1. Frontend document grid with AI-categorized documents
2. Upload interface with drag & drop
3. Document detail view with extracted metadata
4. Category filtering system
5. Admin settings page
6. AI model selection
7. Mobile responsive design

== Changelog ==

= 0.1 - 2026-02-09 =
* Initial release
* AI-powered document analysis
* 12 predefined categories
* Upload PDF and images
* Search and filter documents
* User-specific document libraries
* OpenRouter integration
* Responsive frontend interface
* Admin settings page
* Shortcode support

== Upgrade Notice ==

= 0.1 =
Initial release of GEODocs. Start organizing your documents with AI today!

== Support ==

For support, feature requests, or bug reports, please visit:
* Website: [geopard.digital](https://geopard.digital/)
* Documentation: [geopard.digital/geodocs-docs](https://geopard.digital/geodocs-docs)

== Credits ==

* Developed by [Geopard Digital](https://geopard.digital/)
* Powered by [OpenRouter](https://openrouter.ai/)
* UI built with [Tailwind CSS](https://tailwindcss.com/)
* Icons by [Font Awesome](https://fontawesome.com/)

== Privacy Policy ==

GEODocs processes documents using OpenRouter's API. When you upload a document:
1. The file is stored on your WordPress server
2. The file is sent to OpenRouter for AI analysis
3. OpenRouter processes it using your selected AI model
4. Results are returned and stored in WordPress
5. The AI provider does not retain your data

For more information, see:
* OpenRouter Privacy Policy: https://openrouter.ai/privacy
* Your WordPress privacy policy

== License ==

This plugin is licensed under the GPL v2 or later.

Copyright (C) 2026 Geopard Digital

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
