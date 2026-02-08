# GEODocs - Developer Documentation

## 🏗️ Architecture Overview

### Design Philosophy
- **WordPress Native**: Uses CPT, Taxonomies, Post Meta - no custom tables
- **Single File Backend**: All PHP logic in one file for simplicity
- **Separate Frontend/Admin JS**: Clean separation of concerns
- **CDN Resources Only**: Tailwind CSS and Font Awesome via CDN
- **RESTful API**: WordPress REST API for all data operations
- **Progressive Enhancement**: Works without JS for basic viewing

### Technology Stack

**Backend:**
- PHP 8.0+
- WordPress 6.0+
- WordPress REST API
- OpenRouter API

**Frontend:**
- Vanilla JavaScript (ES6+)
- Tailwind CSS (CDN)
- Font Awesome 6 (CDN)
- Fetch API for AJAX

**AI:**
- OpenRouter (https://openrouter.ai/)
- Default: Google Gemini 2.0 Flash
- Vision-capable models for document analysis

## 📂 Code Organization

### Main Plugin File: `geodocs.php`

```php
geodocs.php
├── Plugin Header (metadata)
├── Security Check (ABSPATH)
├── Constants Definition
├── GEODocs Class
│   ├── Constructor (hooks registration)
│   ├── Activation/Deactivation
│   ├── Post Type Registration
│   ├── Taxonomy Registration
│   ├── Category Creation
│   ├── Admin Menu
│   ├── Script Enqueueing
│   ├── REST API Routes
│   ├── API Handlers
│   │   ├── Documents CRUD
│   │   ├── Categories
│   │   ├── Settings
│   │   └── Models
│   ├── OpenRouter Integration
│   ├── File Handling
│   ├── Shortcode Rendering
│   └── Settings Page
└── Plugin Initialization
```

**Key Methods:**

- `register_post_type()` - Creates `geodocs_document` CPT
- `register_taxonomy()` - Creates `geodocs_category` taxonomy
- `register_rest_routes()` - Defines API endpoints
- `analyze_with_openrouter()` - AI document analysis
- `render_frontend_shortcode()` - Generates shortcode output
- `render_settings_page()` - Admin settings interface

### Frontend Script: `assets/js/frontend-script.js`

```javascript
frontend-script.js
├── Global State (AppState object)
├── Initialization (DOMContentLoaded)
├── API Functions
│   ├── apiRequest() - Fetch wrapper
│   ├── loadDocuments()
│   ├── uploadDocument()
│   ├── deleteDocument()
│   └── updateDocument()
├── Render Functions
│   ├── renderApp() - Main render
│   ├── getDashboardHTML()
│   ├── getUploadHTML()
│   ├── getDocumentDetailHTML()
│   └── Component generators
├── Event Handlers
│   ├── attachEventListeners()
│   ├── Drag & drop
│   ├── Search
│   └── File upload
├── View Management
│   ├── showDashboard()
│   ├── showUpload()
│   ├── viewDocument()
│   └── Navigation
├── Utility Functions
│   ├── formatDate()
│   ├── formatFileSize()
│   ├── escapeHtml()
│   └── showNotification()
└── Public API Export
```

**State Management:**
- Simple object-based state (AppState)
- No frameworks - vanilla JS
- Re-render on state change
- Event delegation for dynamic content

### Admin Script: `assets/js/admin-script.js`

```javascript
admin-script.js
├── Initialization
├── API Key Testing
├── Model Loading & Selection
├── UI Interactions
└── Notifications
```

**Features:**
- Tests OpenRouter API key
- Fetches available models
- Interactive model selection
- Visual feedback

### Stylesheets

**Frontend CSS:** `assets/css/frontend-style.css`
- Complete component library
- Responsive design
- Animations & transitions
- Accessibility features
- Print styles

**Admin CSS:** `assets/css/admin-style.css`
- Enhances WordPress admin
- Custom animations
- Admin-specific styling

## 🔌 REST API Endpoints

### Documents

```
GET    /wp-json/geodocs/v1/documents
       Query: ?category=<id>&search=<term>&per_page=<n>&page=<n>
       Returns: { documents: [], total: n, pages: n }

POST   /wp-json/geodocs/v1/documents
       Body: FormData with 'file'
       Returns: Document object with AI analysis

GET    /wp-json/geodocs/v1/documents/<id>
       Returns: Single document object

PUT    /wp-json/geodocs/v1/documents/<id>
       Body: { title, description, categoryId }
       Returns: Updated document object

DELETE /wp-json/geodocs/v1/documents/<id>
       Returns: { success: true }
```

### Categories

```
GET    /wp-json/geodocs/v1/categories
       Returns: Array of category objects with counts
```

### Models (Admin Only)

```
GET    /wp-json/geodocs/v1/models
       Returns: Array of available AI models from OpenRouter
```

### Settings (Admin Only)

```
GET    /wp-json/geodocs/v1/settings
       Returns: Plugin settings object

POST   /wp-json/geodocs/v1/settings
       Body: Settings object
       Returns: Updated settings
```

## 🗄️ Data Structure

### WordPress Custom Post Type: `geodocs_document`

**Post Fields:**
```php
[
    'post_type' => 'geodocs_document',
    'post_title' => 'AI-generated title',
    'post_content' => 'AI-generated description',
    'post_author' => user_id,
    'post_status' => 'publish',
    'post_date' => 'YYYY-MM-DD HH:MM:SS'
]
```

**Post Meta:**
```php
[
    '_geodocs_file_url' => 'https://.../uploads/file.pdf',
    '_geodocs_file_type' => 'application/pdf',
    '_geodocs_file_size' => 12345678, // bytes
    '_geodocs_metadata' => '{"dates":[],"amounts":[],"entities":[]}'
]
```

### WordPress Taxonomy: `geodocs_category`

**Term:**
```php
[
    'term_id' => 123,
    'name' => 'Invoices & Receipts',
    'slug' => 'invoices-receipts',
    'taxonomy' => 'geodocs_category'
]
```

**Term Meta:**
```php
[
    'color' => 'bg-blue-500',
    'icon' => '🧾'
]
```

### WordPress Options

```php
[
    'geodocs_openrouter_api_key' => 'sk-or-v1-...',
    'geodocs_default_model' => 'google/gemini-2.0-flash-exp:free',
    'geodocs_site_name' => 'My Site',
    'geodocs_max_file_size' => 10, // MB
    'geodocs_allowed_file_types' => 'pdf,jpg,jpeg,png,gif,webp',
    'geodocs_enable_logging' => true,
    'geodocs_activity_log' => [] // Last 100 activities
]
```

## 🔒 Security Implementation

### WordPress Nonces
```php
// Generation
wp_create_nonce('wp_rest')

// Verification (automatic in REST API)
check_ajax_referer('wp_rest', 'X-WP-Nonce')
```

### Capability Checks
```php
// User permission (any logged-in user)
is_user_logged_in()

// Admin permission
current_user_can('manage_options')

// Author verification
$post->post_author == get_current_user_id()
```

### Input Sanitization
```php
sanitize_text_field()    // Text inputs
sanitize_textarea_field() // Textareas
sanitize_file_name()      // File names
absint()                  // Integers
```

### Output Escaping
```php
esc_html()      // HTML content
esc_attr()      // HTML attributes
esc_url()       // URLs
wp_kses_post()  // WordPress allowed HTML
```

### File Upload Validation
```php
// Type validation
$allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
in_array($file['type'], $allowed_types)

// Size validation
$file['size'] <= $max_size

// WordPress upload handler
wp_handle_upload($file, ['test_form' => false])
```

## 🤖 OpenRouter Integration

### API Request Structure

```php
wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
    'headers' => [
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json',
        'HTTP-Referer' => home_url(),
        'X-Title' => get_option('geodocs_site_name')
    ],
    'body' => json_encode([
        'model' => 'google/gemini-2.0-flash-exp:free',
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => $data_uri]]
                ]
            ]
        ],
        'temperature' => 0.3,
        'max_tokens' => 1000
    ]),
    'timeout' => 30
]);
```

### AI Prompt Template

```
Analyze this document image and provide a detailed analysis in JSON format.

Please identify:
1. A clear, concise title (max 60 characters)
2. A brief description (2-3 sentences)
3. The most appropriate category from: [list of 12 categories]
4. Extract metadata: dates, amounts, entities, document numbers, etc.

Return ONLY valid JSON:
{
  "title": "...",
  "description": "...",
  "category": "...",
  "metadata": {...}
}
```

### Response Parsing

```php
// Extract JSON from markdown code blocks
preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)

// Parse JSON
$analysis = json_decode($content, true)

// Map category name to term ID
$term = get_term_by('name', $analysis['category'], 'geodocs_category')
$analysis['category'] = $term->term_id
```

## 🎨 Frontend State Management

### AppState Object

```javascript
const AppState = {
    // User data
    currentUser: { id, name, email },
    
    // Data
    documents: [],
    categories: [],
    
    // UI state
    currentView: 'dashboard', // dashboard | upload | document-detail
    selectedDocument: null,
    selectedCategory: null,
    searchQuery: '',
    viewMode: 'grid', // grid | list
    
    // Pagination
    currentPage: 1,
    totalPages: 1,
    perPage: 12,
    
    // Loading states
    loading: false,
    
    // Settings
    showUpload: true,
    showSearch: true,
    showFilters: true
};
```

### Render Cycle

```
User Action
    ↓
Update AppState
    ↓
renderApp()
    ↓
Generate HTML
    ↓
Update DOM
    ↓
attachEventListeners()
    ↓
Ready for next action
```

### Event Handling

```javascript
// File upload
fileInput.addEventListener('change', handleFileUpload)

// Drag & drop
dropZone.addEventListener('dragover', preventDefault)
dropZone.addEventListener('drop', handleFileUpload)

// Search (debounced)
searchInput.addEventListener('input', debounce(loadDocuments, 500))
```

## 📱 Responsive Design

### Breakpoints

```css
/* Mobile first */
.geodocs-grid {
    grid-template-columns: 1fr; /* Mobile: Single column */
}

/* Tablet: 768px+ */
@media (min-width: 768px) {
    .geodocs-grid {
        grid-template-columns: repeat(2, 1fr); /* 2 columns */
    }
}

/* Desktop: 1024px+ */
@media (min-width: 1024px) {
    .geodocs-grid {
        grid-template-columns: repeat(3, 1fr); /* 3 columns */
    }
}
```

### Mobile Optimizations

- Touch-friendly buttons (min 44x44px)
- Swipe-friendly cards
- Reduced animations on mobile
- Optimized images
- Lazy loading
- Simplified navigation

## ⚡ Performance Optimizations

### Backend

1. **Efficient Queries**
   ```php
   // Only fetch needed fields
   'fields' => 'ids'
   
   // Limit results
   'posts_per_page' => 12
   ```

2. **Caching**
   - WordPress object cache (automatic)
   - Transients for API responses
   - Term counts cached

3. **Lazy Loading**
   - Images use native lazy loading
   - Documents loaded on demand
   - Paginated results

### Frontend

1. **CDN Resources**
   - Tailwind CSS from CDN
   - Font Awesome from CDN
   - Cached by browsers

2. **Minimal JS**
   - No frameworks
   - Vanilla JS only
   - ~600 lines total

3. **Debouncing**
   - Search input (500ms)
   - Prevents excessive API calls

4. **Optimistic Updates**
   - Immediate UI feedback
   - Background sync

## 🧪 Testing Checklist

### Installation
- [ ] Plugin activates without errors
- [ ] CPT and taxonomy created
- [ ] Default categories created
- [ ] Settings page accessible
- [ ] No PHP warnings/errors

### Admin Settings
- [ ] API key can be saved
- [ ] API key test works
- [ ] Models can be loaded
- [ ] Model selection works
- [ ] Settings save correctly
- [ ] Statistics display correctly
- [ ] Categories show with counts

### Frontend Shortcode
- [ ] Shortcode renders correctly
- [ ] Login check works
- [ ] Upload button appears
- [ ] Search bar appears
- [ ] Category filters appear
- [ ] View mode toggle works
- [ ] Responsive on mobile

### Document Upload
- [ ] Drag & drop works
- [ ] File browser works
- [ ] File validation works
- [ ] Size validation works
- [ ] Type validation works
- [ ] AI analysis runs
- [ ] Document appears in list
- [ ] Metadata extracted
- [ ] Category assigned

### Document Management
- [ ] Documents display in grid
- [ ] Documents display in list
- [ ] Search finds documents
- [ ] Category filter works
- [ ] View detail page opens
- [ ] Edit title works
- [ ] Delete works with confirmation
- [ ] Download file works
- [ ] Pagination works

### Security
- [ ] Non-logged users see login prompt
- [ ] Users see only own documents
- [ ] Admin can't see user docs (by design)
- [ ] XSS prevented (escaped output)
- [ ] CSRF prevented (nonces)
- [ ] SQL injection prevented (prepared queries)
- [ ] File upload validated

### Browser Testing
- [ ] Chrome
- [ ] Firefox
- [ ] Safari
- [ ] Edge
- [ ] Mobile browsers

## 🔄 Workflow Examples

### Upload Document Workflow

```
User clicks "Upload Document"
    ↓
AppState.currentView = 'upload'
    ↓
renderApp() shows upload form
    ↓
User drags file onto drop zone
    ↓
handleFileUpload() validates file
    ↓
uploadDocument() sends to API
    ↓
PHP: wp_handle_upload()
    ↓
PHP: analyze_with_openrouter()
    ↓
OpenRouter: AI analyzes image
    ↓
PHP: Parse AI response
    ↓
PHP: Create wp_post
    ↓
PHP: Save metadata
    ↓
API returns document object
    ↓
JS: Add to AppState.documents
    ↓
JS: Show success notification
    ↓
JS: Switch to dashboard
    ↓
Document appears in list!
```

### Search Workflow

```
User types in search box
    ↓
Input event fires
    ↓
Debounce waits 500ms
    ↓
AppState.searchQuery updated
    ↓
loadDocuments() called
    ↓
API request: ?search=query
    ↓
PHP: WP_Query with 's' param
    ↓
Returns matching documents
    ↓
JS: Update AppState.documents
    ↓
renderApp() shows results
```

## 🚀 Deployment Checklist

### Pre-Deployment
- [ ] Test on staging site
- [ ] Test with different PHP versions
- [ ] Test with different WP versions
- [ ] Test on mobile devices
- [ ] Check all browser consoles for errors
- [ ] Verify no PHP warnings
- [ ] Test with large files
- [ ] Test with many documents
- [ ] Verify API key security

### Production
- [ ] Backup site before install
- [ ] Install on production
- [ ] Configure API key
- [ ] Test upload workflow
- [ ] Monitor PHP error log
- [ ] Monitor JavaScript console
- [ ] Set appropriate file size limits
- [ ] Configure allowed file types
- [ ] Test with real users

### Post-Deployment
- [ ] Monitor API usage
- [ ] Monitor server resources
- [ ] Check error logs
- [ ] Collect user feedback
- [ ] Document any issues
- [ ] Plan updates/improvements

## 📝 Maintenance

### Regular Tasks
- Update API keys if needed
- Monitor OpenRouter usage
- Clean up old logs
- Review error logs
- Update AI models
- Test new WordPress versions
- Update documentation

### Backups
- WordPress database (includes all documents metadata)
- `/wp-content/uploads/geodocs/` folder (actual files)
- Plugin settings (wp_options table)

### Updates
- WordPress core updates - test compatibility
- PHP version updates - verify minimum requirements
- OpenRouter API changes - monitor changelog

## 🎓 Learning Resources

### WordPress Development
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress REST API](https://developer.wordpress.org/rest-api/)
- [Custom Post Types](https://developer.wordpress.org/plugins/post-types/)

### OpenRouter
- [OpenRouter Documentation](https://openrouter.ai/docs)
- [API Reference](https://openrouter.ai/docs/api-reference)
- [Model List](https://openrouter.ai/models)

### JavaScript
- [MDN Web Docs](https://developer.mozilla.org/)
- [Fetch API](https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API)

### CSS
- [Tailwind CSS Docs](https://tailwindcss.com/docs)
- [CSS Grid](https://css-tricks.com/snippets/css/complete-guide-grid/)

---

**Happy Coding! 🚀**
