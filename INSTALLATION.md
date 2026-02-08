# GEODocs WordPress Plugin - Installation & Setup Guide

## 📦 What's Been Created

A complete, production-ready WordPress plugin with:
- ✅ AI-powered document analysis using OpenRouter
- ✅ Custom Post Types & Taxonomies (WordPress native)
- ✅ Frontend shortcode interface for users
- ✅ Admin settings page under Settings menu
- ✅ Complete REST API
- ✅ Responsive design with Tailwind CSS
- ✅ Full file upload handling
- ✅ Document categorization & metadata extraction

## 📁 File Structure

```
geodocs/
├── geodocs.php                          # Main plugin file (all PHP logic)
├── uninstall.php                        # Cleanup on uninstall
├── readme.txt                           # WordPress plugin readme
└── assets/
    ├── js/
    │   ├── admin-script.js              # Admin settings interface
    │   └── frontend-script.js           # Frontend user interface
    └── css/
        ├── admin-style.css              # Admin styles
        └── frontend-style.css           # Frontend styles
```

**Total: 7 files, ~4500 lines of code**

## 🚀 Installation Instructions

### Step 1: Upload Plugin

**Option A: Via WordPress Admin**
1. Zip the `geodocs` folder
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin"
4. Choose the ZIP file
5. Click "Install Now"
6. Click "Activate"

**Option B: Via FTP**
1. Upload the `geodocs` folder to `/wp-content/plugins/`
2. Go to WordPress Admin → Plugins
3. Find "GEODocs" and click "Activate"

### Step 2: Configure Settings

1. Go to **Settings → GEODocs**
2. Enter your **OpenRouter API Key**
   - Get free key at: https://openrouter.ai/keys
   - Paste it in the "OpenRouter API Key" field
3. (Optional) Click "Test API Key" to verify it works
4. (Optional) Click "Load All Available Models" to browse AI models
5. Configure other settings:
   - **Site Name**: Your site name (for OpenRouter attribution)
   - **Max File Size**: Maximum upload size in MB (default: 10)
   - **Allowed File Types**: Comma-separated (default: pdf,jpg,jpeg,png,gif,webp)
   - **Enable Activity Logging**: Track document activities
6. Click "Save Settings"

### Step 3: Create a Frontend Page

1. Go to **Pages → Add New**
2. Title: "My Documents" (or any title you want)
3. Add the shortcode in the content area:
   ```
   [geodocs]
   ```
4. Click "Publish"
5. View the page - you now have a document management interface!

### Step 4: Test Everything

1. **As a User:**
   - Login to your WordPress site
   - Visit the page with `[geodocs]` shortcode
   - Click "Upload Document"
   - Drag & drop a PDF or image
   - Wait 5-10 seconds for AI analysis
   - Document should appear categorized!

2. **As Admin:**
   - Visit Settings → GEODocs
   - Check statistics dashboard
   - View all categories
   - Test API key
   - Browse available models

## 🎨 Shortcode Options

### Basic Usage
```
[geodocs]
```

### With Custom Attributes
```
[geodocs view="list" per_page="20" show_upload="true" show_search="true" show_filters="true"]
```

### Available Attributes

| Attribute | Options | Default | Description |
|-----------|---------|---------|-------------|
| `view` | `grid`, `list` | `grid` | Display mode |
| `per_page` | `1-100` | `12` | Documents per page |
| `show_upload` | `true`, `false` | `true` | Show upload button |
| `show_search` | `true`, `false` | `true` | Show search bar |
| `show_filters` | `true`, `false` | `true` | Show category filters |

### Examples

**Minimal view (no upload, no filters):**
```
[geodocs show_upload="false" show_filters="false"]
```

**List view with 50 items per page:**
```
[geodocs view="list" per_page="50"]
```

**Read-only mode:**
```
[geodocs show_upload="false"]
```

## 🔐 User Access

### For Regular Users
- **Access**: Any logged-in user can use the shortcode
- **Permissions**: Can only see and manage their own documents
- **Interface**: Frontend shortcode only

### For Administrators
- **Access**: Everything users can do, PLUS:
- **Settings**: Can configure plugin settings
- **API**: Can manage OpenRouter API keys
- **Models**: Can browse and select AI models

### For Non-logged-in Users
- See login prompt with link to WordPress login page
- Cannot access any documents

## 🏷️ Document Categories

The plugin comes with 12 pre-configured categories:

1. 🧾 **Invoices & Receipts** - Bills, receipts, invoices
2. ⚖️ **Legal Contracts** - Agreements, contracts, legal docs
3. 🎨 **Marketing Assets** - Brochures, flyers, designs
4. 👥 **HR & Employee** - Resumes, employment docs
5. 💰 **Business Finance** - Financial statements, reports
6. 🆔 **Personal Identity** - IDs, passports, licenses
7. 🏥 **Medical Records** - Health docs, prescriptions
8. ✈️ **Travel & Tickets** - Boarding passes, bookings
9. 🏠 **Home & Utilities** - Utility bills, home docs
10. 🎓 **Education & Courses** - Certificates, transcripts
11. 🛡️ **Insurance Docs** - Insurance policies, claims
12. 📁 **Other** - Everything else

AI automatically categorizes uploaded documents into these categories.

## 🤖 AI Features

### What AI Analyzes

When you upload a document, the AI extracts:

1. **Title**: Descriptive title (max 60 characters)
2. **Description**: Brief summary (2-3 sentences)
3. **Category**: Best matching category from the 12 options
4. **Metadata**:
   - 📅 Dates (in YYYY-MM-DD format)
   - 💵 Amounts/prices (with currency)
   - 🏢 Company names
   - 👤 Person names
   - #️⃣ Document numbers/IDs
   - 📧 Email addresses
   - 📞 Phone numbers

### Supported AI Models

Default: **Google Gemini 2.0 Flash** (Free tier available!)

You can also use:
- Google Gemini 1.5 Flash
- Anthropic Claude 3 Haiku
- OpenAI GPT-4 Vision
- And 100+ other models via OpenRouter

### Cost Estimate

With default Gemini model:
- **$0.075** per 1M input tokens
- **$0.30** per 1M output tokens
- **~1000 documents ≈ $0.50 - $1.00**

OpenRouter offers a **FREE TIER** for testing!

## 🔧 Customization

### Change Default Settings

Edit these in `geodocs.php` (lines 65-70):
```php
add_option('geodocs_openrouter_api_key', '');
add_option('geodocs_default_model', 'google/gemini-2.0-flash-exp:free');
add_option('geodocs_site_name', get_bloginfo('name'));
add_option('geodocs_max_file_size', 10); // MB
add_option('geodocs_allowed_file_types', 'pdf,jpg,jpeg,png,gif,webp');
add_option('geodocs_enable_logging', true);
```

### Add More Categories

In `geodocs.php`, find `create_default_categories()` function and add:
```php
['name' => 'My Category', 'color' => 'bg-teal-500', 'icon' => '🎯'],
```

Available colors: blue, indigo, purple, pink, red, orange, yellow, green, teal, cyan, gray

### Customize Colors

Edit CSS files in `assets/css/`:
- `frontend-style.css` - Change color variables at the top
- `admin-style.css` - Admin interface colors

## 📊 Features Breakdown

### ✅ Completed Features

**Core Functionality:**
- [x] Custom Post Type for documents
- [x] Taxonomy for categories
- [x] WordPress native data structure
- [x] No custom SQL tables
- [x] User-specific documents
- [x] Multi-user support

**AI Integration:**
- [x] OpenRouter API integration
- [x] Multiple AI model support
- [x] Automatic document analysis
- [x] Title extraction
- [x] Description generation
- [x] Category classification
- [x] Metadata extraction (dates, amounts, entities)
- [x] Error handling & fallbacks

**Frontend Interface:**
- [x] Shortcode implementation
- [x] Document upload (drag & drop)
- [x] File validation
- [x] Grid view
- [x] List view
- [x] Search functionality
- [x] Category filtering
- [x] Document detail page
- [x] Edit documents
- [x] Delete documents
- [x] Pagination
- [x] Responsive design
- [x] Mobile-friendly
- [x] Loading states
- [x] Empty states
- [x] Notifications

**Admin Interface:**
- [x] Settings page under Settings menu
- [x] API key configuration
- [x] API key testing
- [x] Model selection
- [x] Model browser with pricing
- [x] Statistics dashboard
- [x] Category overview
- [x] File size limits
- [x] Allowed file types
- [x] Activity logging toggle

**Security:**
- [x] WordPress nonces
- [x] Capability checks
- [x] Input sanitization
- [x] Output escaping
- [x] File upload validation
- [x] Author verification
- [x] REST API authentication

**Performance:**
- [x] Optimized queries
- [x] Lazy loading
- [x] CDN resources (Tailwind, Font Awesome)
- [x] Minimal dependencies
- [x] Efficient REST API

### 🎯 Future Enhancements (Ideas)

- [ ] Bulk upload
- [ ] Export documents (CSV/JSON)
- [ ] Document sharing between users
- [ ] Custom categories management UI
- [ ] Document preview modal
- [ ] PDF thumbnails
- [ ] Advanced search (by metadata)
- [ ] Document sorting options
- [ ] Activity log viewer
- [ ] Email notifications
- [ ] Document versioning
- [ ] Tags system
- [ ] Favorite/star documents
- [ ] Dark mode
- [ ] Multi-language support

## 🐛 Troubleshooting

### Problem: AI analysis fails

**Solution:**
1. Check API key is correct
2. Test API key in Settings
3. Check PHP error logs
4. Verify file is valid PDF/image
5. Try different AI model

### Problem: File upload fails

**Solutions:**
1. Check file size (max 10MB by default)
2. Check file type (PDF, JPG, PNG, GIF, WebP)
3. Increase PHP `upload_max_filesize` in php.ini
4. Increase WordPress max upload in wp-config.php:
   ```php
   @ini_set('upload_max_filesize', '20M');
   @ini_set('post_max_size', '20M');
   ```

### Problem: Shortcode shows nothing

**Solutions:**
1. Check user is logged in
2. Check plugin is activated
3. Clear browser cache
4. Check WordPress Permalinks (Settings → Permalinks, click Save)
5. Check JavaScript console for errors

### Problem: Styles look broken

**Solutions:**
1. Hard refresh browser (Ctrl+F5)
2. Check Tailwind CDN is loading
3. Clear all caches (WordPress, browser, CDN)
4. Check for JavaScript errors
5. Try different browser

### Problem: Can't see documents

**Solutions:**
1. Check you're logged in as the document owner
2. Documents are user-specific (by design)
3. Admins need to login as user to see their docs
4. Check WordPress user ID matches document author

## 📞 Support

**Plugin Info:**
- **Name**: GEODocs
- **Version**: 0.1
- **Author**: Geopard Digital
- **Website**: https://geopard.digital/

**Resources:**
- OpenRouter: https://openrouter.ai/
- Tailwind CSS: https://tailwindcss.com/
- Font Awesome: https://fontawesome.com/

**Need Help?**
- Check PHP error logs: `/wp-content/debug.log`
- Check browser console: F12 → Console tab
- Enable WordPress debug mode in `wp-config.php`:
  ```php
  define('WP_DEBUG', true);
  define('WP_DEBUG_LOG', true);
  define('WP_DEBUG_DISPLAY', false);
  ```

## 🎉 You're All Set!

Your GEODocs plugin is now fully functional. Users can:
1. Upload documents via the frontend
2. AI automatically analyzes and categorizes them
3. Search and filter documents
4. View detailed information
5. Edit and delete documents
6. Access from any device (responsive design)

**Enjoy your AI-powered document management system!** 🚀

---

**Made with ❤️ by [Geopard Digital](https://geopard.digital/)**
