<?php  
/**
 * Plugin Name: GEODocs
 * Plugin URI: https://geopard.digital/
 * Description: AI-powered document organizer using OpenRouter & Gemini. Upload, analyze, and organize your documents with artificial intelligence.
 * Version: 0.5
 * Author: Geopard Digital
 * Author URI: https://geopard.digital/
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: geodocs
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('GEODOCS_VERSION', '0.5');
define('GEODOCS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GEODOCS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GEODOCS_PLUGIN_FILE', __FILE__);

/**
 * Main GEODocs Plugin Class
 */
class GEODocs {

    /**
     * Constructor
     */
    public function __construct() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // WordPress hooks
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomy']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('template_redirect', [$this, 'handle_image_request']);
        add_filter('query_vars', [$this, 'add_query_vars']);

        // Shortcode
        add_shortcode('geodocs', [$this, 'render_frontend_shortcode']);
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Register CPT and taxonomy
        $this->register_post_type();
        $this->register_taxonomy();
        flush_rewrite_rules();

        // Create default categories
        $this->create_default_categories();

        // Set default options (removed PDF support)
        add_option('geodocs_openrouter_api_key', '');
        add_option('geodocs_default_model', 'google/gemini-2.0-flash-exp:free');
        add_option('geodocs_site_name', get_bloginfo('name'));
        add_option('geodocs_max_file_size', 10); // MB
        add_option('geodocs_allowed_file_types', 'jpg,jpeg,png,gif,webp');
        add_option('geodocs_enable_logging', true);

        // Create uploads directory
        $upload_dir = wp_upload_dir();
        $geodocs_dir = $upload_dir['basedir'] . '/geodocs';
        if (!file_exists($geodocs_dir)) {
            wp_mkdir_p($geodocs_dir);
        }

        // Secure the uploads directory with .htaccess
        $this->create_secure_htaccess($geodocs_dir);
    }

    /**
     * Create .htaccess to secure uploads directory
     */
    private function create_secure_htaccess($dir) {
        $htaccess_file = $dir . '/.htaccess';
        $htaccess_content = "# GEODocs Security - Deny direct access\n";
        $htaccess_content .= "Options -Indexes\n";
        $htaccess_content .= "<FilesMatch \"\\.(jpg|jpeg|png|gif|webp)$\">\n";
        $htaccess_content .= "    Require all denied\n";
        $htaccess_content .= "</FilesMatch>\n";

        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, $htaccess_content);
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Register Custom Post Type
     */
    public function register_post_type() {
        register_post_type('geodocs_document', [
            'labels' => [
                'name' => __('Documents', 'geodocs'),
                'singular_name' => __('Document', 'geodocs'),
                'add_new' => __('Add New', 'geodocs'),
                'add_new_item' => __('Add New Document', 'geodocs'),
                'edit_item' => __('Edit Document', 'geodocs'),
                'view_item' => __('View Document', 'geodocs'),
                'search_items' => __('Search Documents', 'geodocs'),
            ],
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'author', 'custom-fields'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'has_archive' => false,
            'rewrite' => false,
        ]);
    }

    /**
     * Register Taxonomy
     */
    public function register_taxonomy() {
        register_taxonomy('geodocs_category', 'geodocs_document', [
            'labels' => [
                'name' => __('Document Categories', 'geodocs'),
                'singular_name' => __('Category', 'geodocs'),
                'search_items' => __('Search Categories', 'geodocs'),
                'all_items' => __('All Categories', 'geodocs'),
                'edit_item' => __('Edit Category', 'geodocs'),
                'update_item' => __('Update Category', 'geodocs'),
                'add_new_item' => __('Add New Category', 'geodocs'),
            ],
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => true,
            'hierarchical' => false,
            'rewrite' => false,
        ]);
    }

    /**
     * Create default categories
     */
    public function create_default_categories() {
        $categories = [
            ['name' => 'Invoices & Receipts', 'color' => 'bg-blue-500', 'icon' => '🧾'],
            ['name' => 'Legal Contracts', 'color' => 'bg-indigo-500', 'icon' => '⚖️'],
            ['name' => 'Marketing Assets', 'color' => 'bg-pink-500', 'icon' => '🎨'],
            ['name' => 'HR & Employee', 'color' => 'bg-yellow-500', 'icon' => '👥'],
            ['name' => 'Business Finance', 'color' => 'bg-green-500', 'icon' => '💰'],
            ['name' => 'Personal Identity', 'color' => 'bg-purple-500', 'icon' => '🆔'],
            ['name' => 'Medical Records', 'color' => 'bg-red-500', 'icon' => '🏥'],
            ['name' => 'Travel & Tickets', 'color' => 'bg-blue-400', 'icon' => '✈️'],
            ['name' => 'Home & Utilities', 'color' => 'bg-gray-500', 'icon' => '🏠'],
            ['name' => 'Education & Courses', 'color' => 'bg-green-400', 'icon' => '🎓'],
            ['name' => 'Insurance Docs', 'color' => 'bg-red-400', 'icon' => '🛡️'],
            ['name' => 'Other', 'color' => 'bg-gray-400', 'icon' => '📁'],
        ];

        foreach ($categories as $cat) {
            if (!term_exists($cat['name'], 'geodocs_category')) {
                $term = wp_insert_term($cat['name'], 'geodocs_category');
                if (!is_wp_error($term)) {
                    update_term_meta($term['term_id'], 'color', $cat['color']);
                    update_term_meta($term['term_id'], 'icon', $cat['icon']);
                }
            }
        }
    }

    /**
     * Add settings page under Settings menu
     */
    public function add_settings_page() {
        add_options_page(
            __('GEODocs Settings', 'geodocs'),
            __('GEODocs', 'geodocs'),
            'manage_options',
            'geodocs-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on settings page
        if ($hook !== 'settings_page_geodocs-settings') {
            return;
        }

        // Tailwind CSS
        wp_enqueue_script('tailwind-cdn', 'https://cdn.tailwindcss.com', [], null, false);

        // Font Awesome
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', [], '6.4.0');

        // Inline admin styles
        wp_add_inline_style('font-awesome', $this->get_admin_inline_styles());

        // Localize script data
        wp_localize_script('tailwind-cdn', 'geodocs', [
            'restUrl' => rest_url('geodocs/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'pluginUrl' => GEODOCS_PLUGIN_URL,
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);

        // Inline admin JS
        wp_add_inline_script('tailwind-cdn', $this->get_admin_inline_scripts());
    }

    /**
     * Get inline admin styles
     */
    private function get_admin_inline_styles() {
        return '
        .geodocs-tab-content { display: none; }
        .geodocs-tab-content.active { display: block; }
        .geodocs-tab { cursor: pointer; transition: all 0.2s; }
        .geodocs-tab:hover { background-color: #f1f5f9; }
        .geodocs-tab.active { background-color: #3b82f6; color: white; }
        ';
    }

    /**
     * Get inline admin scripts
     */
    private function get_admin_inline_scripts() {
        return "
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching
            const tabs = document.querySelectorAll('.geodocs-tab');
            const contents = document.querySelectorAll('.geodocs-tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const target = tab.dataset.tab;
                    tabs.forEach(t => t.classList.remove('active'));
                    contents.forEach(c => c.classList.remove('active'));
                    tab.classList.add('active');
                    document.getElementById('tab-' + target).classList.add('active');
                });
            });

            // Test API Key
            const testBtn = document.getElementById('test-api-key');
            if (testBtn) {
                testBtn.addEventListener('click', async function() {
                    const resultSpan = document.getElementById('api-test-result');
                    resultSpan.innerHTML = '<i class=\"fas fa-spinner fa-spin\"></i> Testing...';

                    try {
                        const response = await fetch(geodocs.restUrl + 'models', {
                            headers: {
                                'X-WP-Nonce': geodocs.nonce
                            }
                        });

                        if (response.ok) {
                            resultSpan.innerHTML = '<span class=\"text-green-600\"><i class=\"fas fa-check-circle\"></i> API Key is valid!</span>';
                        } else {
                            resultSpan.innerHTML = '<span class=\"text-red-600\"><i class=\"fas fa-times-circle\"></i> API Key is invalid</span>';
                        }
                    } catch (error) {
                        resultSpan.innerHTML = '<span class=\"text-red-600\"><i class=\"fas fa-times-circle\"></i> Error testing API</span>';
                    }
                });
            }

            // Load models
            const loadModelsBtn = document.getElementById('load-models');
            if (loadModelsBtn) {
                loadModelsBtn.addEventListener('click', async function() {
                    const modelsList = document.getElementById('models-list');
                    const modelInput = document.getElementById('model');
                    const modelDatalist = document.getElementById('model-datalist');
                    
                    modelsList.classList.remove('hidden');
                    modelsList.innerHTML = '<p class=\"text-sm\"><i class=\"fas fa-spinner fa-spin\"></i> Loading models...</p>';

                    try {
                        const response = await fetch(geodocs.restUrl + 'models', {
                            headers: {
                                'X-WP-Nonce': geodocs.nonce
                            }
                        });

                        if (response.ok) {
                            const models = await response.json();
                            
                            // Clear and populate datalist (keep current input value intact)
                            modelDatalist.innerHTML = '';
                            models.forEach(model => {
                                const option = document.createElement('option');
                                option.value = model.id;
                                option.textContent = model.name;
                                modelDatalist.appendChild(option);
                            });

                            modelsList.innerHTML = '<p class=\"text-sm text-green-600\"><i class=\"fas fa-check\"></i> Loaded ' + models.length + ' vision models. Start typing to see suggestions.</p>';
                        } else {
                            modelsList.innerHTML = '<p class=\"text-sm text-red-600\"><i class=\"fas fa-times\"></i> Failed to load models</p>';
                        }
                    } catch (error) {
                        modelsList.innerHTML = '<p class=\"text-sm text-red-600\"><i class=\"fas fa-times\"></i> Error: ' + error.message + '</p>';
                    }
                });
            }
        });
        ";
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        // More lenient check - enqueue on singular pages (works with page builders)
        // The shortcode itself will check if user is logged in
        if (!is_singular()) {
            return;
        }

        // Check if shortcode exists - but don't fail if page builders hide it
        global $post;
        $has_shortcode = false;

        if (is_a($post, 'WP_Post')) {
            // Check post content
            if (has_shortcode($post->post_content, 'geodocs')) {
                $has_shortcode = true;
            }

            // Also check for common page builder meta fields
            $page_builder_content = get_post_meta($post->ID, '_elementor_data', true);
            if (!$has_shortcode && !empty($page_builder_content)) {
                $has_shortcode = strpos($page_builder_content, '[geodocs]') !== false ||
                                 strpos($page_builder_content, 'geodocs') !== false;
            }
        }

        // If we can't detect the shortcode, enqueue anyway on pages/posts
        // The overhead is minimal and ensures it works with all page builders
        if (!$has_shortcode && !is_page() && !is_single()) {
            return;
        }

        // Register a local script handle to attach everything to (more reliable than CDN)
        wp_register_script('geodocs-init', '', [], GEODOCS_VERSION, false);
        wp_enqueue_script('geodocs-init');

        // Tailwind CSS
        wp_enqueue_script('tailwind-cdn', 'https://cdn.tailwindcss.com', [], null, false);

        // Font Awesome
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', [], '6.4.0');

        // Toastify for notifications
        wp_enqueue_style('toastify-css', 'https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css', [], '1.12.0');
        wp_enqueue_script('toastify-js', 'https://cdn.jsdelivr.net/npm/toastify-js', [], '1.12.0', true);

        // Inline frontend styles
        wp_add_inline_style('font-awesome', $this->get_frontend_inline_styles());

        // Localize script - attach to our reliable local handle
        wp_localize_script('geodocs-init', 'geodocs', [
            'restUrl' => rest_url('geodocs/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'currentUser' => [
                'id' => get_current_user_id(),
                'name' => wp_get_current_user()->display_name,
                'email' => wp_get_current_user()->user_email,
            ],
            'pluginUrl' => GEODOCS_PLUGIN_URL,
            'categories' => $this->get_categories_for_js(),
            'maxFileSize' => get_option('geodocs_max_file_size', 10) * 1024 * 1024,
            'allowedTypes' => explode(',', get_option('geodocs_allowed_file_types', 'jpg,jpeg,png,gif,webp')),
        ]);

        // Inline frontend JS - attach to our reliable local handle
        wp_add_inline_script('geodocs-init', $this->get_frontend_inline_scripts());
    }

    /**
     * Get frontend inline styles
     */
    private function get_frontend_inline_styles() {
        return '
        #geodocs-app {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .geodocs-drop-zone {
            transition: all 0.3s ease;
        }
        .geodocs-drop-zone.drag-over {
            border-color: #000 !important;
            background: #f5f5f5 !important;
            transform: scale(1.01);
        }
        .category-drop-zone {
            transition: all 0.2s ease;
        }
        .category-drop-zone.drag-over-category {
            background: #f5f5f5;
            border-radius: 0.375rem;
        }
        .category-drop-zone.drag-over-category button {
            background: #000 !important;
            color: white !important;
        }
        .geodocs-split-view {
            display: none;
            animation: fadeIn 0.2s ease-in-out;
        }
        .geodocs-split-view.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .geodocs-progress-step {
            opacity: 0.3;
            transition: all 0.3s ease;
        }
        .geodocs-progress-step.active {
            opacity: 1;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .geodocs-pulse {
            animation: pulse 1.5s ease-in-out infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .geodocs-spin {
            animation: spin 1s linear infinite;
        }
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        #geodocs-app ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        #geodocs-app ::-webkit-scrollbar-track {
            background: #f5f5f5;
        }
        #geodocs-app ::-webkit-scrollbar-thumb {
            background: #d1d1d1;
            border-radius: 4px;
        }
        #geodocs-app ::-webkit-scrollbar-thumb:hover {
            background: #999;
        }
        #geodocs-app [draggable="true"] {
            cursor: grab;
        }
        #geodocs-app [draggable="true"]:active {
            cursor: grabbing;
        }
        .category-item {
            position: relative;
            display:flex
        }
        .category-actions {
            opacity: 0;
            transition: opacity 0.2s ease;
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: white;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 10;
        }
        .category-item:hover .category-actions {
            opacity: 1;
        }
        @media (max-width: 768px) {
            .category-actions {
                opacity: 1;
                position: static;
                transform: none;
                box-shadow: none;
                background: transparent;
            }
        }
        .category-menu-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 4px;
            background: white;
            border: 1px solid #e5e5e5;
            border-radius: 0.375rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            min-width: 120px;
            z-index: 1000;
        }
        .category-menu-dropdown.active {
            display: block;
        }
        @media (max-width: 768px) {
            .category-menu-dropdown {
                position: fixed;
                right: auto;
                left: 50%;
                top: 50%;
                transform: translate(-50%, -50%);
                min-width: 200px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.3);
                border: 2px solid #ddd;
                z-index: 10000;
            }
        }
        #mobile-bottom-menu {
            display: none;
        }
        @media (max-width: 768px) {
            #mobile-bottom-menu {
                display: flex;
            }
            .desktop-search {
                display: none !important;
            }
            .desktop-buttons {
                display: none !important;
            }
            .main-content-wrapper {
                padding-bottom: 80px;
            }
        }
        @media (min-width: 769px) {
            .mobile-search {
                display: none !important;
            }
        }
        .user-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 0.5rem;
            background: white;
            border: 1px solid #e5e5e5;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            z-index: 50;
            min-width: 180px;
        }
        .user-dropdown.active {
            display: block;
        }
        @media (max-width: 768px) {
            .user-dropdown {
                position: fixed;
                right: 1rem;
                top: 4rem;
            }
        }
        ';
    }

    /**
     * Get categories for JavaScript
     */
    private function get_categories_for_js() {
        $terms = get_terms(['taxonomy' => 'geodocs_category', 'hide_empty' => false]);
        $categories = [];

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $categories[] = [
                    'id' => $term->term_id,
                    'slug' => $term->slug,
                    'name' => $term->name,
                    'color' => get_term_meta($term->term_id, 'color', true),
                    'icon' => get_term_meta($term->term_id, 'icon', true),
                ];
            }
        }

        return $categories;
    }

    /**
     * Get frontend inline scripts (comprehensive batch upload & UI)
     */
    private function get_frontend_inline_scripts() {
        ob_start();
        ?>
class GeoDocsApp {
    constructor() {
        this.uploadQueue = [];
        this.processing = false;
        this.currentDocuments = [];
        this.selectedCategory = null;
        this.searchQuery = '';
        this.init();
    }

    init() {
        console.log('[GEODocs] Initializing app...', {
            restUrl: geodocs.restUrl,
            categories: geodocs.categories,
            currentUser: geodocs.currentUser
        });

        // Verify geodocs object exists
        if (typeof geodocs === 'undefined') {
            console.error('[GEODocs] ERROR: geodocs object not found! Check script enqueuing.');
            return;
        }

        this.renderApp();
        this.loadDocuments();
        this.setupEventListeners();
        console.log('[GEODocs] App initialized successfully!');
    }

    renderApp() {
        const container = document.getElementById('geodocs-app');
        container.innerHTML = `
            <div class="geodocs-app-container min-h-screen bg-white flex flex-col">
                <!-- Top App Bar -->
                <div class="geodocs-header bg-white shadow-sm sticky top-0 z-40 border-b border-gray-200">
                    <div class="px-6 py-3 flex items-center justify-between">
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="geodocs-logo-link flex items-center gap-3 hover:opacity-80 transition-opacity no-underline" title="Go to Homepage" style="text-decoration: none;">
                            <div class="geodocs-logo bg-black text-white rounded-lg">
                                <img src="/wp-content/uploads/2026/02/cropped-godocs.png" width="44" alt="GEODocs Logo" />
                            </div>
                            <div>
                                <h1 class="geodocs-title text-xl font-bold text-black">GEODocs</h1>
                            </div>
                        </a>
                        
                        <div class="flex-1">
                            <!-- Search Bar - Desktop (Left Side) -->
                            <div class="geodocs-search-desktop desktop-search relative w-80 ml-6">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                <input type="text" id="search-input" placeholder="Search documents..."
                                       class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-1 focus:ring-black focus:border-black text-sm">
                            </div>
                        </div>
                        
                        <div class="geodocs-header-actions flex items-center gap-3">
                            <!-- Action Buttons (Desktop Only) -->
                            <div class="geodocs-action-buttons desktop-buttons flex items-center gap-3">
                                <button onclick="document.getElementById('file-input').click()"
                                        class="px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800 transition-colors text-sm font-medium flex items-center gap-2">
                                    <i class="fas fa-upload"></i>
                                    Select Images
                                </button>

                                <button onclick="app.takePicture()"
                                        class="px-4 py-2 bg-white border border-gray-300 text-black rounded-lg hover:bg-gray-50 transition-colors text-sm font-medium flex items-center gap-2">
                                    <i class="fas fa-camera"></i>
                                    Take Picture
                                </button>
                            </div>

                            <input type="file" id="file-input" multiple accept="image/*" class="hidden">
                            <input type="file" id="camera-input" accept="image/*" capture="user" class="hidden">

                            <!-- User Avatar with Dropdown -->
                            <div class="geodocs-user-menu relative" id="user-menu-container">
                                <button onclick="app.toggleUserMenu()" class="bg-black text-white rounded-full w-9 h-9 flex items-center justify-center font-semibold text-sm hover:bg-gray-800 transition-colors">
                                    ${geodocs.currentUser.name.charAt(0).toUpperCase()}
                                </button>
                                <div id="user-dropdown" class="user-dropdown">
                                    <div class="px-4 py-3 border-b border-gray-200">
                                        <p class="text-sm font-medium text-black">${geodocs.currentUser.name}</p>
                                        <p class="text-xs text-gray-500">${geodocs.currentUser.email}</p>
                                    </div>
                                    <button onclick="event.preventDefault(); app.showProfileEdit()"
                                       class="w-full text-left block px-4 py-2 text-sm text-black hover:bg-gray-50 transition-colors">
                                        <i class="fas fa-user-edit mr-2"></i>Edit Profile
                                    </button>
                                    <a href="<?php echo wp_logout_url(get_permalink()); ?>"
                                       class="block px-4 py-2 text-sm text-black hover:bg-gray-50 transition-colors">
                                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search Bar - Mobile -->
                <div class="geodocs-search-mobile mobile-search bg-white border-b border-gray-200 px-4 py-3">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" id="search-input-mobile" placeholder="Search documents..."
                               class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-1 focus:ring-black focus:border-black text-sm">
                    </div>
                </div>

                <!-- Main Content Area: Sidebar + Main -->
                <div class="geodocs-main-wrapper flex flex-1 overflow-hidden main-content-wrapper">
                    <!-- Left Sidebar (Hidden on Mobile) -->
                    <div class="geodocs-sidebar w-60 bg-white border-r border-gray-200 flex-col hidden md:flex">
                        <!-- Categories List -->
                        <div class="geodocs-categories-list flex-1 overflow-y-auto py-2 px-2">
                            <div class="geodocs-categories-header flex items-center justify-between px-3 py-2 mb-1">
                                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Categories</h3>
                                <button onclick="app.showAddCategoryDialog()"
                                        class="text-gray-400 hover:text-black transition-colors"
                                        title="Add Category">
                                    <i class="fas fa-plus text-xs"></i>
                                </button>
                            </div>
                            <div id="categories-filter">
                                <!-- Categories rendered here -->
                            </div>
                        </div>

                        <!-- Upload Progress -->
                        <div id="upload-progress" class="geodocs-upload-progress hidden border-t border-gray-200 p-3 bg-gray-50">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-xs font-semibold text-black flex items-center gap-2">
                                    <i class="fas fa-spinner geodocs-spin"></i>
                                    Processing
                                </h4>
                                <span id="queue-counter" class="text-xs text-gray-600">0/0</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                <div id="progress-bar" class="bg-black h-1.5 rounded-full transition-all duration-500" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content Area -->
                    <div id="main-document-area" class="geodocs-main-content geodocs-drop-zone flex-1 overflow-y-auto bg-gray-50">
                        <!-- Documents Header -->
                        <div id="documents-header" class="geodocs-documents-header bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-black" id="current-category-title">All Documents</h2>
                                <p class="text-xs text-gray-500" id="documents-count">0 documents</p>
                            </div>
                        </div>

                        <!-- Documents Grid (with drop zone) -->
                        <div class="geodocs-documents-container p-6" id="documents-container">
                            <div id="documents-grid" class="geodocs-documents-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4 min-h-[200px]">
                                <!-- Documents rendered here -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile Bottom Menu -->
                <div id="mobile-bottom-menu" class="geodocs-mobile-menu fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 safe-area-inset-bottom">
                    <div class="flex items-center justify-around py-3 px-2 w-full">
                        <button onclick="document.getElementById('file-input').click()" 
                                class="flex flex-col items-center gap-1 text-gray-600 hover:text-black transition-colors">
                            <i class="fas fa-upload text-xl"></i>
                            <span class="text-xs">Upload</span>
                        </button>
                        <button onclick="app.takePicture()" 
                                class="flex flex-col items-center gap-1 text-gray-600 hover:text-black transition-colors">
                            <i class="fas fa-camera text-xl"></i>
                            <span class="text-xs">Camera</span>
                        </button>
                        <button onclick="app.toggleMobileCategories()" 
                                class="flex flex-col items-center gap-1 text-gray-600 hover:text-black transition-colors">
                            <i class="fas fa-folder text-xl"></i>
                            <span class="text-xs">Categories</span>
                        </button>
                        <button onclick="app.toggleMobileUserMenu()" 
                                class="flex flex-col items-center gap-1 text-gray-600 hover:text-black transition-colors">
                            <i class="fas fa-user text-xl"></i>
                            <span class="text-xs">Profile</span>
                        </button>
                    </div>
                </div>

                <!-- Mobile Categories Sheet -->
                <div id="mobile-categories-sheet" class="geodocs-mobile-categories-sheet fixed inset-0 bg-black bg-opacity-50 z-50 hidden" onclick="app.toggleMobileCategories()">
                    <div class="fixed bottom-0 left-0 right-0 bg-white rounded-t-2xl max-h-[70vh] overflow-auto" onclick="event.stopPropagation()">
                        <div class="sticky top-0 bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between">
                            <h3 class="font-semibold text-black">Categories</h3>
                            <button onclick="app.toggleMobileCategories()" class="text-gray-500 hover:text-black">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        <div id="mobile-categories-list" class="p-4">
                            <!-- Categories rendered here -->
                        </div>
                    </div>
                </div>

                <!-- Mobile User Menu Sheet -->
                <div id="mobile-user-sheet" class="geodocs-mobile-user-sheet fixed inset-0 bg-black bg-opacity-50 z-50 hidden" onclick="app.toggleMobileUserMenu()">
                    <div class="fixed bottom-0 left-0 right-0 bg-white rounded-t-2xl" onclick="event.stopPropagation()">
                        <div class="sticky top-0 bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between">
                            <h3 class="font-semibold text-black">Profile</h3>
                            <button onclick="app.toggleMobileUserMenu()" class="text-gray-500 hover:text-black">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        <div class="p-4">
                            <div class="text-center mb-4">
                                <div class="bg-black text-white rounded-full w-16 h-16 flex items-center justify-center font-bold text-2xl mx-auto mb-2">
                                    ${geodocs.currentUser.name.charAt(0).toUpperCase()}
                                </div>
                                <p class="font-medium text-black">${geodocs.currentUser.name}</p>
                                <p class="text-sm text-gray-500">${geodocs.currentUser.email}</p>
                            </div>
                            <button onclick="app.showProfileEdit(); app.toggleMobileUserMenu()"
                                   class="w-full px-4 py-3 text-left text-black hover:bg-gray-50 transition-colors rounded-lg mb-2 flex items-center gap-2">
                                <i class="fas fa-user-edit text-lg"></i>
                                <span>Edit Profile</span>
                            </button>
                            <a href="<?php echo wp_logout_url(get_permalink()); ?>"
                               class="block w-full px-4 py-3 text-left text-black hover:bg-gray-50 transition-colors rounded-lg flex items-center gap-2">
                                <i class="fas fa-sign-out-alt text-lg"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Split Screen Viewer -->
                <div id="split-viewer" class="geodocs-split-viewer geodocs-split-view fixed inset-0 bg-black bg-opacity-80 z-50">
                    <div class="bg-white h-full w-full flex">
                        <div class="flex-1 p-8 overflow-auto bg-black flex items-center justify-center">
                            <img id="viewer-image" class="max-w-full max-h-full object-contain" src="" alt="">
                        </div>
                        <div class="w-96 bg-white p-6 overflow-auto border-l border-gray-200">
                            <button id="close-viewer" class="mb-4 px-3 py-2 bg-gray-100 text-black hover:bg-gray-200 rounded-lg transition-colors text-sm flex items-center gap-2">
                                <i class="fas fa-times"></i>
                                Close
                            </button>
                            <div id="viewer-details">
                                <!-- Details rendered here -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Edit Modal -->
                <div id="profile-edit-modal" class="geodocs-split-view fixed inset-0 bg-black bg-opacity-80 z-50">
                    <div class="bg-white rounded-2xl max-w-md w-full mx-4 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-bold text-black">Edit Profile</h2>
                            <button id="close-profile-edit" class="text-gray-500 hover:text-black transition-colors">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                        <form id="profile-edit-form" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-black mb-2">
                                    <i class="fas fa-user mr-2"></i>Display Name
                                </label>
                                <input type="text" id="profile-name" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent"
                                       placeholder="Your name">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-black mb-2">
                                    <i class="fas fa-lock mr-2"></i>New Password
                                </label>
                                <input type="password" id="profile-password" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent"
                                       placeholder="Leave blank to keep current">
                                <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-black mb-2">
                                    <i class="fas fa-lock mr-2"></i>Confirm Password
                                </label>
                                <input type="password" id="profile-password-confirm" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent"
                                       placeholder="Confirm new password">
                            </div>
                            <button type="submit" class="w-full px-4 py-3 bg-black text-white rounded-lg hover:bg-gray-800 transition-colors font-medium">
                                <i class="fas fa-save mr-2"></i>Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        `;

        this.renderCategories();
    }

    renderCategories() {
        const container = document.getElementById('categories-filter');
        const mobileContainer = document.getElementById('mobile-categories-list');
        
        let html = `
            <button class="group w-full px-3 py-2 rounded-md text-left transition-all ${!this.selectedCategory ? 'bg-black text-white font-medium' : 'text-gray-700 hover:bg-gray-100'}"
                    onclick="app.filterByCategory(null)">
                <div class="flex items-center gap-2">
                    <i class="fas fa-th-large text-sm"></i>
                    <span class="flex-1 text-sm">All Documents</span>
                    <span class="text-xs ${!this.selectedCategory ? 'text-white' : 'text-gray-400'}">${this.currentDocuments.length}</span>
                </div>
            </button>
        `;

        // Add drag and drop support for each category
        geodocs.categories.forEach(cat => {
            const isActive = this.selectedCategory === cat.id;
            const count = this.getCategoryDocCount(cat.id);
            html += `
                <div class="group category-drop-zone category-item"
                     data-category-id="${cat.id}"
                     ondragover="event.preventDefault(); this.classList.add('drag-over-category')"
                     ondragleave="this.classList.remove('drag-over-category')"
                     ondrop="app.handleCategoryDrop(event, ${cat.id})">
                    <button class="w-full px-3 py-2 rounded-md text-left transition-all relative ${isActive ? 'bg-black text-white font-medium' : 'text-gray-700 hover:bg-gray-100'}"
                            onclick="app.filterByCategory(${cat.id})">
                        <div class="flex items-center gap-2 pr-8">
                            <span class="text-base">${cat.icon}</span>
                            <span class="flex-1 truncate text-sm">${cat.name}</span>
                            <span class="text-xs ${isActive ? 'text-white' : 'text-gray-400'}">${count}</span>
                        </div>
                        <div class="category-actions">
                            <button onclick="event.stopPropagation(); app.toggleCategoryMenu(${cat.id})"
                                    class="p-1.5 hover:bg-gray-100 rounded transition-colors"
                                    title="Options">
                                <i class="fas fa-ellipsis-v text-sm text-gray-600"></i>
                            </button>
                            <div id="category-menu-${cat.id}" class="category-menu-dropdown">
                                <button onclick="event.stopPropagation(); app.renameCategory(${cat.id})"
                                        class="w-full px-4 py-2 text-left text-sm hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-pen text-xs mr-2"></i>Rename
                                </button>
                                <button onclick="event.stopPropagation(); app.deleteCategory(${cat.id})"
                                        class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 transition-colors">
                                    <i class="fas fa-trash text-xs mr-2"></i>Delete
                                </button>
                            </div>
                        </div>
                    </button>
                </div>
            `;
        });

        if (container) container.innerHTML = html;
        if (mobileContainer) mobileContainer.innerHTML = html;
    }

    getCategoryDocCount(categoryId) {
        // This will be updated after loading documents
        const docs = this.currentDocuments.filter(doc => doc.category && doc.category.id === categoryId);
        return docs.length;
    }

    // Toast notification helper
    showToast(message, type = 'success') {
        if (typeof Toastify !== 'undefined') {
            const colors = {
                success: '#000',
                error: '#DC2626',
                info: '#3B82F6'
            };
            
            // Longer duration for mobile (especially for info/processing messages)
            const duration = type === 'info' ? 4000 : (type === 'success' ? 3500 : 3000);
            
            Toastify({
                text: message,
                duration: duration,
                gravity: 'top',
                position: 'center', // Center for better mobile visibility
                style: {
                    background: colors[type] || '#6B7280',
                    borderRadius: '0.5rem',
                    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto',
                    fontSize: '14px',
                    padding: '12px 20px',
                    boxShadow: '0 4px 6px rgba(0,0,0,0.2)',
                    zIndex: '9999'
                }
            }).showToast();
        } else {
            console.log('[Toast]', message);
        }
    }

    // User menu toggle
    toggleUserMenu() {
        const dropdown = document.getElementById('user-dropdown');
        dropdown.classList.toggle('active');

        // Close when clicking outside
        if (dropdown.classList.contains('active')) {
            setTimeout(() => {
                document.addEventListener('click', function closeMenu(e) {
                    if (!document.getElementById('user-menu-container').contains(e.target)) {
                        dropdown.classList.remove('active');
                        document.removeEventListener('click', closeMenu);
                    }
                });
            }, 10);
        }
    }

    // Take picture functionality
    takePicture() {
        document.getElementById('camera-input').click();
    }

    setupEventListeners() {
        const fileInput = document.getElementById('file-input');
        const cameraInput = document.getElementById('camera-input');
        const mainDocumentArea = document.getElementById('main-document-area');

        // File input change
        fileInput.addEventListener('change', (e) => {
            this.handleFiles(e.target.files);
            e.target.value = ''; // Reset input
        });

        // Camera input change - only camera
        cameraInput.addEventListener('change', (e) => {
            this.handleFiles(e.target.files);
            e.target.value = ''; // Reset input
        });

        // Drag and drop on entire main document area
        if (mainDocumentArea) {
            mainDocumentArea.addEventListener('dragover', (e) => {
                // Check if it's a file drag (not document reordering)
                if (e.dataTransfer.types.includes('Files') && !e.dataTransfer.types.includes('document-id')) {
                    e.preventDefault();
                    mainDocumentArea.classList.add('drag-over');
                }
            });

            mainDocumentArea.addEventListener('dragleave', (e) => {
                // Only remove if leaving the main area completely
                if (e.target === mainDocumentArea) {
                    mainDocumentArea.classList.remove('drag-over');
                }
            });

            mainDocumentArea.addEventListener('drop', (e) => {
                // Check if it's a file drop (images)
                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    e.preventDefault();
                    mainDocumentArea.classList.remove('drag-over');
                    this.handleFiles(e.dataTransfer.files);
                }
            });
        }

        // Search functionality (desktop and mobile)
        const searchInput = document.getElementById('search-input');
        const searchInputMobile = document.getElementById('search-input-mobile');
        let searchTimeout;
        
        const handleSearch = (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.searchQuery = e.target.value;
                this.loadDocuments();
                // Sync search inputs
                if (searchInput && e.target !== searchInput) searchInput.value = e.target.value;
                if (searchInputMobile && e.target !== searchInputMobile) searchInputMobile.value = e.target.value;
            }, 300);
        };
        
        if (searchInput) searchInput.addEventListener('input', handleSearch);
        if (searchInputMobile) searchInputMobile.addEventListener('input', handleSearch);

        // Close split viewer
        document.getElementById('close-viewer').addEventListener('click', () => {
            document.getElementById('split-viewer').classList.remove('active');
        });

        // Load categories from localStorage if available
        const savedCategories = localStorage.getItem('geodocs_custom_categories');
        if (savedCategories) {
            try {
                geodocs.categories = JSON.parse(savedCategories);
                console.log('[GEODocs] Loaded custom categories from localStorage');
            } catch (e) {
                console.error('[GEODocs] Error loading custom categories:', e);
            }
        }
    }

    handleFiles(files) {
        Array.from(files).forEach(file => {
            if (file.type.startsWith('image/')) {
                this.uploadQueue.push(file);
            }
        });

        if (!this.processing) {
            // Track total queue size for progress calculation
            this.totalQueueSize = this.uploadQueue.length;
            this.processQueue();
        }
    }

    async processQueue() {
        if (this.uploadQueue.length === 0) {
            this.processing = false;
            document.getElementById('upload-progress').classList.add('hidden');
            this.updateProgress(0);
            
            // Show completion notification (especially visible on mobile)
            const completedMsg = '✓ All uploads completed!';
            this.showToast(completedMsg, 'success');
            console.log('[GEODocs] Processing complete');
            return;
        }

        this.processing = true;
        const progressEl = document.getElementById('upload-progress');
        progressEl.classList.remove('hidden');
        
        // Show processing notification on first file (especially for mobile users)
        if (this.uploadQueue.length === this.totalQueueSize) {
            this.showToast('🔄 Processing documents...', 'info');
        }

        const file = this.uploadQueue.shift();
        const totalFiles = this.totalQueueSize;
        const remaining = this.uploadQueue.length;
        const current = totalFiles - remaining;

        document.getElementById('queue-counter').textContent = `${current}/${totalFiles}`;
        const progressPercent = (current / totalFiles) * 100;
        this.updateProgress(progressPercent);

        await this.uploadFile(file);
        
        // Reload documents after each upload to show immediately (especially important on mobile)
        await this.loadDocuments();

        setTimeout(() => this.processQueue(), 300);
    }

    async uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await fetch(geodocs.restUrl + 'documents', {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': geodocs.nonce
                },
                body: formData
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => null);
                const errorMsg = errorData?.message || `Upload failed (${response.status})`;
                throw new Error(errorMsg);
            }

            const result = await response.json();
            console.log('[GEODocs] Upload successful:', result);
            
            // Show success notification with document title and category (longer duration for mobile)
            const categoryInfo = result.category ? ` → ${result.category.icon} ${result.category.name}` : '';
            this.showToast(`✓ ${result.title}${categoryInfo}`, 'success');

        } catch (error) {
            console.error('[GEODocs] Upload error:', error);
            this.showToast(`✗ ${file.name}: ${error.message}`, 'error');
        }
    }

    updateProgress(percent) {
        document.getElementById('progress-bar').style.width = percent + '%';
    }

    async loadDocuments() {
        let url = geodocs.restUrl + 'documents?per_page=-1';
        if (this.selectedCategory) url += '&category=' + this.selectedCategory;
        if (this.searchQuery) url += '&search=' + encodeURIComponent(this.searchQuery);

        console.log('[GEODocs] Loading documents from:', url);

        try {
            const response = await fetch(url, {
                headers: { 'X-WP-Nonce': geodocs.nonce }
            });

            if (!response.ok) {
                console.error('[GEODocs] Failed to load documents. Status:', response.status);
                if (response.status === 404) {
                    console.error('[GEODocs] 404 Error - REST API route not found. Go to Settings → Permalinks and click Save.');
                }
                this.currentDocuments = [];
                this.renderDocuments();
                return;
            }

            const data = await response.json();
            console.log('[GEODocs] Loaded documents:', data);
            this.currentDocuments = data.documents;
            this.renderDocuments();
        } catch (error) {
            console.error('[GEODocs] Error loading documents:', error);
            this.currentDocuments = [];
            this.renderDocuments();
        }
    }

    renderDocuments() {
        const container = document.getElementById('documents-grid');

        // Update document count
        document.getElementById('documents-count').textContent = `${this.currentDocuments.length} document${this.currentDocuments.length !== 1 ? 's' : ''}`;

        if (this.currentDocuments.length === 0) {
            container.innerHTML = `
                <div class="col-span-full flex flex-col items-center justify-center py-16 text-center">
                    <div class="bg-gray-100 rounded-full p-8 mb-4">
                        <i class="fas fa-images text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-black mb-2">No documents yet</h3>
                    <p class="text-sm text-gray-500 mb-4">Upload images or drag and drop them here</p>
                    <button onclick="document.getElementById('file-input').click()"
                            class="px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800 transition-colors text-sm">
                        Upload Images
                    </button>
                </div>
            `;
            return;
        }

        container.innerHTML = this.currentDocuments.map(doc => `
            <div class="group bg-white rounded-lg shadow-sm overflow-hidden cursor-move hover:shadow-md transition-all border border-gray-200 hover:border-black"
                 draggable="true"
                 ondragstart="event.dataTransfer.setData('document-id', ${doc.id}); this.classList.add('opacity-50')"
                 ondragend="this.classList.remove('opacity-50')"
                 onclick="if (!event.defaultPrevented) app.viewDocument(${doc.id})">
                <div class="aspect-video bg-gray-100 flex items-center justify-center overflow-hidden relative">
                    <img src="${doc.fileUrl}"
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                         alt="${doc.title}"
                         draggable="false">
                    <div class="absolute top-2 right-2 bg-white px-2 py-1 rounded text-xs font-medium text-black">
                        ${doc.fileType ? doc.fileType.split('/')[1].toUpperCase() : 'IMG'}
                    </div>
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all flex items-center justify-center">
                        <div class="text-white text-xs opacity-0 group-hover:opacity-100 transition-opacity">
                            <i class="fas fa-grip-vertical mr-1"></i>
                            Drag to category
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <h3 class="font-semibold text-black mb-1 text-sm truncate">${doc.title}</h3>
                    <p class="text-xs text-gray-600 line-clamp-2 mb-2">${doc.description}</p>
                    <div class="flex items-center justify-between text-xs">
                        ${doc.category ? `
                            <span class="bg-black text-white px-2 py-1 rounded flex items-center gap-1">
                                <span>${doc.category.icon}</span>
                                <span>${doc.category.name}</span>
                            </span>
                        ` : '<span class="text-gray-400">No category</span>'}
                        <span class="text-gray-400">
                            ${new Date(doc.createdAt * 1000).toLocaleDateString()}
                        </span>
                    </div>
                </div>
            </div>
        `).join('');

        // Re-render categories to update counts
        this.renderCategories();
    }

    filterByCategory(categoryId) {
        this.selectedCategory = categoryId;

        // Update header title
        const titleEl = document.getElementById('current-category-title');
        if (categoryId === null) {
            titleEl.textContent = 'All Documents';
        } else {
            const cat = geodocs.categories.find(c => c.id === categoryId);
            if (cat) {
                titleEl.innerHTML = `<span class="text-2xl mr-2">${cat.icon}</span>${cat.name}`;
            }
        }

        this.renderCategories();
        this.loadDocuments();
    }

    // Drag and Drop functionality
    handleCategoryDrop(event, categoryId) {
        event.preventDefault();
        event.currentTarget.classList.remove('drag-over-category');

        const docId = event.dataTransfer.getData('document-id');
        if (docId) {
            this.moveDocumentToCategory(parseInt(docId), categoryId);
        }
    }

    async moveDocumentToCategory(docId, categoryId) {
        try {
            const response = await fetch(geodocs.restUrl + 'documents/' + docId, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': geodocs.nonce
                },
                body: JSON.stringify({ categoryId: categoryId })
            });

            if (response.ok) {
                console.log('[GEODocs] Document moved to category:', categoryId);
                this.showToast('Document moved successfully', 'success');
                this.loadDocuments();
            } else {
                this.showToast('Failed to move document', 'error');
            }
        } catch (error) {
            console.error('[GEODocs] Error moving document:', error);
            this.showToast('Error moving document', 'error');
        }
    }

    // Category Management
    showAddCategoryDialog() {
        const name = prompt('Category Name:');
        if (!name) return;

        const icon = prompt('Category Icon (emoji):') || '📁';

        this.addCategory(name, icon);
    }

    async addCategory(name, icon) {
        // For now, store in localStorage since we need backend support
        const newCategory = {
            id: Date.now(),
            name: name,
            icon: icon,
            color: 'bg-black',
            slug: name.toLowerCase().replace(/\s+/g, '-')
        };

        geodocs.categories.push(newCategory);
        localStorage.setItem('geodocs_custom_categories', JSON.stringify(geodocs.categories));

        this.renderCategories();
        this.showToast('Category added successfully', 'success');
    }

    renameCategory(categoryId) {
        const cat = geodocs.categories.find(c => c.id === categoryId);
        if (!cat) return;

        const newName = prompt('New category name:', cat.name);
        if (newName && newName !== cat.name) {
            cat.name = newName;
            localStorage.setItem('geodocs_custom_categories', JSON.stringify(geodocs.categories));
            this.renderCategories();
            this.showToast('Category renamed successfully', 'success');
        }
    }

    deleteCategory(categoryId) {
        const cat = geodocs.categories.find(c => c.id === categoryId);
        if (!cat) return;

        if (confirm(`Delete category "${cat.name}"?`)) {
            geodocs.categories = geodocs.categories.filter(c => c.id !== categoryId);
            localStorage.setItem('geodocs_custom_categories', JSON.stringify(geodocs.categories));
            this.renderCategories();
            this.loadDocuments();
            this.showToast('Category deleted successfully', 'success');
        }
    }

    toggleViewMode() {
        // Placeholder for grid/list view toggle
        console.log('[GEODocs] Toggle view mode');
    }

    toggleCategoryMenu(categoryId) {
        const menu = document.getElementById(`category-menu-${categoryId}`);
        const allMenus = document.querySelectorAll('.category-menu-dropdown');
        
        // Close all other menus
        allMenus.forEach(m => {
            if (m !== menu) m.classList.remove('active');
        });
        
        // Toggle current menu
        if (menu) {
            menu.classList.toggle('active');
            
            // Close when clicking outside
            if (menu.classList.contains('active')) {
                setTimeout(() => {
                    document.addEventListener('click', function closeMenu(e) {
                        if (!menu.contains(e.target)) {
                            menu.classList.remove('active');
                            document.removeEventListener('click', closeMenu);
                        }
                    });
                }, 10);
            }
        }
    }

    toggleMobileCategories() {
        const sheet = document.getElementById('mobile-categories-sheet');
        if (sheet) {
            sheet.classList.toggle('hidden');
        }
    }

    toggleMobileUserMenu() {
        const sheet = document.getElementById('mobile-user-sheet');
        if (sheet) {
            sheet.classList.toggle('hidden');
        }
    }

    async showProfileEdit() {
        const modal = document.getElementById('profile-edit-modal');
        
        // Load current profile data
        try {
            const response = await fetch(geodocs.restUrl + 'profile', {
                headers: { 'X-WP-Nonce': geodocs.nonce }
            });
            
            if (response.ok) {
                const profile = await response.json();
                document.getElementById('profile-name').value = profile.name;
            }
        } catch (error) {
            console.error('[GEODocs] Error loading profile:', error);
        }
        
        modal.classList.add('active');
        
        // Setup form submission
        const form = document.getElementById('profile-edit-form');
        form.onsubmit = async (e) => {
            e.preventDefault();
            
            const name = document.getElementById('profile-name').value;
            const password = document.getElementById('profile-password').value;
            const passwordConfirm = document.getElementById('profile-password-confirm').value;
            
            // Validate
            if (!name) {
                this.showToast('Please enter your name', 'error');
                return;
            }
            
            if (password && password !== passwordConfirm) {
                this.showToast('Passwords do not match', 'error');
                return;
            }
            
            if (password && password.length < 8) {
                this.showToast('Password must be at least 8 characters', 'error');
                return;
            }
            
            // Save profile
            try {
                const data = { name };
                if (password) {
                    data.password = password;
                }
                
                const response = await fetch(geodocs.restUrl + 'profile', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': geodocs.nonce
                    },
                    body: JSON.stringify(data)
                });
                
                if (response.ok) {
                    this.showToast('Profile updated successfully!', 'success');
                    
                    // Update current user data
                    geodocs.currentUser.name = name;
                    
                    // Close modal
                    modal.classList.remove('active');
                    
                    // Clear password fields
                    document.getElementById('profile-password').value = '';
                    document.getElementById('profile-password-confirm').value = '';
                    
                    // Refresh the page to update UI
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    const error = await response.json();
                    this.showToast(error.message || 'Failed to update profile', 'error');
                }
            } catch (error) {
                console.error('[GEODocs] Error saving profile:', error);
                this.showToast('Error updating profile', 'error');
            }
        };
        
        // Close button
        document.getElementById('close-profile-edit').onclick = () => {
            modal.classList.remove('active');
        };
    }

    async viewDocument(id) {
        const doc = this.currentDocuments.find(d => d.id === id);
        if (!doc) return;

        document.getElementById('viewer-image').src = doc.fileUrl;
        document.getElementById('viewer-details').innerHTML = `
            <h2 class="text-xl font-bold text-black mb-4">${doc.title}</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-black mb-2">Description</label>
                    <textarea id="edit-description" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black focus:border-black" rows="4">${doc.description}</textarea>
                </div>

                ${doc.category ? `
                    <div>
                        <label class="block text-xs font-semibold text-black mb-2">Category</label>
                        <div class="bg-black text-white px-3 py-2 rounded-lg inline-flex items-center gap-2 text-sm">
                            <span>${doc.category.icon}</span>
                            <span>${doc.category.name}</span>
                        </div>
                    </div>
                ` : ''}

                ${Object.keys(doc.metadata).length > 0 ? `
                    <div>
                        <label class="block text-xs font-semibold text-black mb-2">Extracted Data</label>
                        <div class="bg-gray-50 rounded-lg p-3 text-xs">
                            ${Object.entries(doc.metadata).map(([key, value]) => {
                                if (Array.isArray(value) && value.length > 0) {
                                    return `<div class="mb-1"><strong class="text-black">${key}:</strong> <span class="text-gray-600">${value.join(', ')}</span></div>`;
                                }
                                return '';
                            }).join('')}
                        </div>
                    </div>
                ` : ''}

                <div class="flex gap-2 pt-2">
                    <button onclick="app.saveDocument(${id})" class="flex-1 px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800 transition-colors text-sm font-medium">
                        <i class="fas fa-save mr-2"></i>Save
                    </button>
                    <button onclick="app.deleteDocument(${id})" class="px-4 py-2 bg-white border border-red-500 text-red-500 rounded-lg hover:bg-red-50 transition-colors text-sm font-medium">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;

        document.getElementById('split-viewer').classList.add('active');
    }

    async saveDocument(id) {
        const description = document.getElementById('edit-description').value;

        try {
            const response = await fetch(geodocs.restUrl + 'documents/' + id, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': geodocs.nonce
                },
                body: JSON.stringify({ description })
            });

            if (response.ok) {
                this.showToast('Document saved successfully', 'success');
                this.loadDocuments();
                document.getElementById('split-viewer').classList.remove('active');
            } else {
                this.showToast('Failed to save document', 'error');
            }
        } catch (error) {
            console.error('Save error:', error);
            this.showToast('Error saving document', 'error');
        }
    }

    async deleteDocument(id) {
        if (!confirm('Delete this document permanently?')) return;

        try {
            const response = await fetch(geodocs.restUrl + 'documents/' + id, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': geodocs.nonce }
            });

            if (response.ok) {
                this.showToast('Document deleted successfully', 'success');
                this.loadDocuments();
                document.getElementById('split-viewer').classList.remove('active');
            } else {
                this.showToast('Failed to delete document', 'error');
            }
        } catch (error) {
            console.error('Delete error:', error);
            this.showToast('Error deleting document', 'error');
        }
    }
}

// Initialize app
let app;

// More robust initialization that works even if DOMContentLoaded already fired
function initGeoDocsApp() {
    const container = document.getElementById('geodocs-app');
    if (container && !app) {
        app = new GeoDocsApp();
    } else if (!container) {
        // Container not found yet, try again shortly
        setTimeout(initGeoDocsApp, 100);
    }
}

// Try immediate initialization if DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initGeoDocsApp);
} else {
    // DOM already loaded, initialize immediately
    initGeoDocsApp();
}
        <?php
        return ob_get_clean();
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Documents endpoints
        register_rest_route('geodocs/v1', '/documents', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_documents'],
                'permission_callback' => [$this, 'check_user_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_document'],
                'permission_callback' => [$this, 'check_user_permission'],
            ],
        ]);

        register_rest_route('geodocs/v1', '/documents/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_document'],
                'permission_callback' => [$this, 'check_user_permission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_document'],
                'permission_callback' => [$this, 'check_user_permission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_document'],
                'permission_callback' => [$this, 'check_user_permission'],
            ],
        ]);

        // Secure download endpoint
        register_rest_route('geodocs/v1', '/download/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'download_document'],
            'permission_callback' => [$this, 'check_user_permission'],
        ]);

        // Categories endpoint
        register_rest_route('geodocs/v1', '/categories', [
            'methods' => 'GET',
            'callback' => [$this, 'get_categories'],
            'permission_callback' => [$this, 'check_user_permission'],
        ]);

        // Models endpoint (admin only)
        register_rest_route('geodocs/v1', '/models', [
            'methods' => 'GET',
            'callback' => [$this, 'get_openrouter_models'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Settings endpoints (admin only)
        register_rest_route('geodocs/v1', '/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_settings'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
        ]);

        // Profile endpoints
        register_rest_route('geodocs/v1', '/profile', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_profile'],
                'permission_callback' => [$this, 'check_user_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_profile'],
                'permission_callback' => [$this, 'check_user_permission'],
            ],
        ]);
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'geodocs_image';
        return $vars;
    }

    /**
     * Force uploads into the secure geodocs directory
     */
    public function custom_upload_dir($dirs) {
        $dirs['subdir'] = '/geodocs';
        $dirs['path'] = $dirs['basedir'] . '/geodocs';
        $dirs['url'] = $dirs['baseurl'] . '/geodocs';
        return $dirs;
    }

    /**
     * Handle image requests via custom query variable (works with cookie auth)
     */
    public function handle_image_request() {
        $doc_id = get_query_var('geodocs_image');
        
        if (!$doc_id) {
            return;
        }

        // Check if user is logged in (cookie authentication)
        if (!is_user_logged_in()) {
            status_header(401);
            wp_die('Unauthorized - Please log in', 'Unauthorized', ['response' => 401]);
        }

        $post = get_post($doc_id);

        if (!$post || $post->post_type !== 'geodocs_document') {
            status_header(404);
            wp_die('Document not found', 'Not Found', ['response' => 404]);
        }

        // Check ownership - document owner OR admin
        $current_user_id = get_current_user_id();
        if ($post->post_author != $current_user_id && !current_user_can('manage_options')) {
            status_header(403);
            wp_die('Unauthorized access', 'Forbidden', ['response' => 403]);
        }

        // Get explicit file path, fallback to old replacement method for legacy docs
        $file_path = get_post_meta($doc_id, '_geodocs_file_path', true);
        if (!$file_path) {
            $file_url = get_post_meta($doc_id, '_geodocs_file_url', true);
            if (!$file_url) {
                status_header(404);
                wp_die('No file attached', 'Not Found', ['response' => 404]);
            }
            $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $file_url);
        }

        if (!file_exists($file_path)) {
            status_header(404);
            wp_die('File not found on server', 'Not Found', ['response' => 404]);
        }

        // Stream the file
        $mime_type = get_post_meta($doc_id, '_geodocs_file_type', true);
        
        // Force 200 OK header to override WordPress's automatic 404 assignment
        status_header(200);
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: private, max-age=3600');
        header('Pragma: private');
        
        // Output the file
        readfile($file_path);
        exit;
    }

    /**
     * Secure file download endpoint (REST API - for downloads, not image display)
     */
    public function download_document($request) {
        $id = $request->get_param('id');
        $post = get_post($id);

        if (!$post || $post->post_type !== 'geodocs_document') {
            return new WP_Error('not_found', __('Document not found', 'geodocs'), ['status' => 404]);
        }

        // Check ownership - IMPORTANT: Allow document owner OR admin
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return new WP_Error('unauthorized', __('You must be logged in', 'geodocs'), ['status' => 401]);
        }
        
        if ($post->post_author != $current_user_id && !current_user_can('manage_options')) {
            return new WP_Error('unauthorized', __('Unauthorized access', 'geodocs'), ['status' => 403]);
        }

        // Use explicit path
        $file_path = get_post_meta($id, '_geodocs_file_path', true);
        if (!$file_path) {
            $file_url = get_post_meta($id, '_geodocs_file_url', true);
            if (!$file_url) {
                return new WP_Error('no_file', __('No file attached', 'geodocs'), ['status' => 404]);
            }
            $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $file_url);
        }

        if (!file_exists($file_path)) {
            return new WP_Error('file_missing', __('File not found on server', 'geodocs'), ['status' => 404]);
        }

        // Stream the file
        $mime_type = get_post_meta($id, '_geodocs_file_type', true);
        
        // Force 200 OK Header
        status_header(200);
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    }

    /**
     * Permission callbacks
     */
    public function check_user_permission() {
        return is_user_logged_in();
    }

    public function check_admin_permission() {
        return current_user_can('manage_options');
    }

    /**
     * Get documents
     */
    public function get_documents($request) {
        $user_id = get_current_user_id();
        $category = $request->get_param('category');
        $search = $request->get_param('search');
        $per_page = $request->get_param('per_page') ?: -1;
        $page = $request->get_param('page') ?: 1;

        $args = [
            'post_type' => 'geodocs_document',
            'post_status' => 'publish',
            'author' => $user_id,
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        // Filter by category
        if ($category) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'geodocs_category',
                    'field' => 'term_id',
                    'terms' => $category,
                ],
            ];
        }

        // Search
        if ($search) {
            $args['s'] = sanitize_text_field($search);
        }

        $query = new WP_Query($args);
        $documents = [];

        foreach ($query->posts as $post) {
            $documents[] = $this->format_document($post);
        }

        return rest_ensure_response([
            'documents' => $documents,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
        ]);
    }

    /**
     * Get single document
     */
    public function get_document($request) {
        $id = $request->get_param('id');
        $post = get_post($id);

        if (!$post || $post->post_type !== 'geodocs_document') {
            return new WP_Error('not_found', __('Document not found', 'geodocs'), ['status' => 404]);
        }

        if ($post->post_author != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('unauthorized', __('Unauthorized', 'geodocs'), ['status' => 403]);
        }

        return rest_ensure_response($this->format_document($post));
    }

    /**
     * Format document for API response
     */
    private function format_document($post) {
        $categories = wp_get_post_terms($post->ID, 'geodocs_category');
        $category_data = null;

        if (!empty($categories) && !is_wp_error($categories)) {
            $cat = $categories[0];
            $category_data = [
                'id' => $cat->term_id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'color' => get_term_meta($cat->term_id, 'color', true),
                'icon' => get_term_meta($cat->term_id, 'icon', true),
            ];
        }

        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'description' => $post->post_content,
            'categoryId' => $category_data ? $category_data['id'] : null,
            'category' => $category_data,
            'fileUrl' => add_query_arg('geodocs_image', $post->ID, home_url('/')),
            'downloadUrl' => rest_url('geodocs/v1/download/' . $post->ID),
            'fileType' => get_post_meta($post->ID, '_geodocs_file_type', true),
            'fileSize' => get_post_meta($post->ID, '_geodocs_file_size', true),
            'metadata' => json_decode(get_post_meta($post->ID, '_geodocs_metadata', true), true) ?: [],
            'createdAt' => strtotime($post->post_date),
            'userId' => $post->post_author,
        ];
    }

    /**
     * Create document with smart file renaming
     */
    public function create_document($request) {
        // Handle file upload
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        if (!isset($_FILES['file'])) {
            return new WP_Error('no_file', __('No file uploaded', 'geodocs'), ['status' => 400]);
        }

        $file = $_FILES['file'];

        // Validate file type (images only)
        $allowed_types = $this->get_allowed_mime_types();
        if (!in_array($file['type'], $allowed_types)) {
            return new WP_Error('invalid_file', __('Invalid file type. Only images allowed', 'geodocs'), ['status' => 400]);
        }

        // Validate file size
        $max_size = get_option('geodocs_max_file_size', 10) * 1024 * 1024;
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', __('File too large', 'geodocs'), ['status' => 400]);
        }

        // Force upload into the secure geodocs directory
        add_filter('upload_dir', [$this, 'custom_upload_dir']);
        $upload = wp_handle_upload($file, ['test_form' => false]);
        remove_filter('upload_dir', [$this, 'custom_upload_dir']);

        if (isset($upload['error'])) {
            return new WP_Error('upload_failed', $upload['error'], ['status' => 500]);
        }

        // Analyze with OpenRouter AI
        $analysis = $this->analyze_with_openrouter($upload['file'], $file['type']);

        if (is_wp_error($analysis)) {
            // If AI analysis fails, use basic info
            $analysis = [
                'title' => sanitize_file_name($file['name']),
                'description' => __('Document uploaded successfully', 'geodocs'),
                'category' => null,
                'metadata' => [],
            ];
        }

        // Smart rename file based on AI title
        $new_filename = $this->smart_rename_file($upload['file'], $analysis['title'], $analysis['metadata']);

        // Create post
        $post_id = wp_insert_post([
            'post_type' => 'geodocs_document',
            'post_title' => $analysis['title'],
            'post_content' => $analysis['description'],
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ]);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Set category
        if (!empty($analysis['category'])) {
            wp_set_post_terms($post_id, [$analysis['category']], 'geodocs_category');
        }

        // Save file metadata - Save both URL and exact absolute Path
        update_post_meta($post_id, '_geodocs_file_url', $new_filename['url']);
        update_post_meta($post_id, '_geodocs_file_path', $new_filename['file']);
        update_post_meta($post_id, '_geodocs_file_type', $file['type']);
        update_post_meta($post_id, '_geodocs_file_size', $file['size']);
        update_post_meta($post_id, '_geodocs_metadata', json_encode($analysis['metadata']));
        update_post_meta($post_id, '_geodocs_original_filename', $file['name']);

        // Log activity
        $this->log_activity('document_created', $post_id, get_current_user_id());

        $post = get_post($post_id);
        return rest_ensure_response($this->format_document($post));
    }

    /**
     * Smart file renaming based on AI analysis
     */
    private function smart_rename_file($filepath, $title, $metadata) {
        $pathinfo = pathinfo($filepath);
        $extension = $pathinfo['extension'];

        // Create smart filename from title
        $base_name = sanitize_file_name($title);
        $base_name = preg_replace('/[^a-z0-9-_]/i', '-', $base_name);
        $base_name = substr($base_name, 0, 60); // Limit length

        // Add date if available
        if (isset($metadata['dates']) && !empty($metadata['dates'])) {
            $date = $metadata['dates'][0];
            $base_name = $date . '-' . $base_name;
        } else {
            $base_name = date('Y-m-d') . '-' . $base_name;
        }

        $new_filename = $base_name . '.' . $extension;
        $new_filepath = $pathinfo['dirname'] . '/' . $new_filename;

        // Rename file
        if (rename($filepath, $new_filepath)) {
            $upload_dir = wp_upload_dir();
            return [
                'file' => $new_filepath,
                'url' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $new_filepath)
            ];
        }

        return [
            'file' => $filepath,
            'url' => str_replace($pathinfo['dirname'], str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $pathinfo['dirname']), $filepath)
        ];
    }

    /**
     * Update document
     */
    public function update_document($request) {
        $id = $request->get_param('id');
        $post = get_post($id);

        if (!$post || $post->post_type !== 'geodocs_document') {
            return new WP_Error('not_found', __('Document not found', 'geodocs'), ['status' => 404]);
        }

        if ($post->post_author != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('unauthorized', __('Unauthorized', 'geodocs'), ['status' => 403]);
        }

        $data = $request->get_json_params();

        $update_data = ['ID' => $id];

        if (isset($data['title'])) {
            $update_data['post_title'] = sanitize_text_field($data['title']);
        }

        if (isset($data['description'])) {
            $update_data['post_content'] = sanitize_textarea_field($data['description']);
        }

        wp_update_post($update_data);

        if (isset($data['categoryId'])) {
            wp_set_post_terms($id, [(int)$data['categoryId']], 'geodocs_category');
        }

        // Log activity
        $this->log_activity('document_updated', $id, get_current_user_id());

        $post = get_post($id);
        return rest_ensure_response($this->format_document($post));
    }

    /**
     * Delete document
     */
    public function delete_document($request) {
        $id = $request->get_param('id');
        $post = get_post($id);

        if (!$post || $post->post_type !== 'geodocs_document') {
            return new WP_Error('not_found', __('Document not found', 'geodocs'), ['status' => 404]);
        }

        if ($post->post_author != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('unauthorized', __('Unauthorized', 'geodocs'), ['status' => 403]);
        }

        // Delete file
        $file_url = get_post_meta($id, '_geodocs_file_url', true);
        if ($file_url) {
            $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $file_url);
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        // Log activity
        $this->log_activity('document_deleted', $id, get_current_user_id());

        wp_delete_post($id, true);

        return rest_ensure_response(['success' => true]);
    }

    /**
     * Get categories
     */
    public function get_categories() {
        $terms = get_terms(['taxonomy' => 'geodocs_category', 'hide_empty' => false]);
        $categories = [];

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                // Count documents for current user
                $user_id = get_current_user_id();
                $count = $this->get_category_count($term->term_id, $user_id);

                $categories[] = [
                    'id' => $term->term_id,
                    'slug' => $term->slug,
                    'name' => $term->name,
                    'color' => get_term_meta($term->term_id, 'color', true),
                    'icon' => get_term_meta($term->term_id, 'icon', true),
                    'count' => $count,
                ];
            }
        }

        return rest_ensure_response($categories);
    }

    /**
     * Get category count for user
     */
    private function get_category_count($term_id, $user_id) {
        $args = [
            'post_type' => 'geodocs_document',
            'author' => $user_id,
            'tax_query' => [
                [
                    'taxonomy' => 'geodocs_category',
                    'field' => 'term_id',
                    'terms' => $term_id,
                ],
            ],
            'fields' => 'ids',
        ];

        $query = new WP_Query($args);
        return $query->found_posts;
    }

    /**
     * Get OpenRouter models
     */
    public function get_openrouter_models() {
        $api_key = get_option('geodocs_openrouter_api_key');

        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('OpenRouter API key not configured', 'geodocs'), ['status' => 400]);
        }

        // Fetch models from OpenRouter
        $response = wp_remote_get('https://openrouter.ai/api/v1/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['data'])) {
            return new WP_Error('api_error', __('Failed to fetch models', 'geodocs'), ['status' => 500]);
        }

        // Filter to only show vision models
        $models = array_filter($body['data'], function($model) {
            $modalities = $model['architecture']['modality'] ?? '';
            return stripos($modalities, 'image') !== false || stripos($modalities, 'vision') !== false;
        });

        // Format for frontend
        $formatted = array_map(function($model) {
            return [
                'id' => $model['id'],
                'name' => $model['name'],
                'description' => $model['description'] ?? '',
                'context_length' => $model['context_length'] ?? 0,
                'pricing' => [
                    'prompt' => $model['pricing']['prompt'] ?? '0',
                    'completion' => $model['pricing']['completion'] ?? '0',
                ],
            ];
        }, $models);

        return rest_ensure_response(array_values($formatted));
    }

    /**
     * Analyze document with OpenRouter (images only)
     */
    private function analyze_with_openrouter($file_path, $mime_type) {
        $api_key = get_option('geodocs_openrouter_api_key');
        $model = get_option('geodocs_default_model', 'google/gemini-2.0-flash-exp:free');

        if (empty($api_key)) {
            error_log('[GEODocs] AI analysis skipped: API key not configured');
            return new WP_Error('no_api_key', __('OpenRouter API key not configured', 'geodocs'), ['status' => 400]);
        }

        // Log AI analysis attempt
        error_log('[GEODocs] Starting AI analysis with model: ' . $model);

        // Read file and convert to base64
        $file_data = base64_encode(file_get_contents($file_path));
        $data_uri = "data:{$mime_type};base64,{$file_data}";

        // Prepare prompt
        $prompt = "Analyze this document image and provide a detailed analysis in JSON format.

Please identify:
1. A clear, concise title (max 60 characters)
2. A brief description (2-3 sentences explaining what this document is)
3. The most appropriate category from this list: Invoices & Receipts, Legal Contracts, Marketing Assets, HR & Employee, Business Finance, Personal Identity, Medical Records, Travel & Tickets, Home & Utilities, Education & Courses, Insurance Docs, Other
4. Extract any important metadata such as:
   - Dates (in YYYY-MM-DD format)
   - Amounts/prices (with currency if visible)
   - Names of people or companies
   - Document numbers or IDs
   - Email addresses or phone numbers
   - Any other relevant information

Return ONLY valid JSON in this exact format:
{
  \"title\": \"Document title here\",
  \"description\": \"Brief description here\",
  \"category\": \"Category name here\",
  \"metadata\": {
    \"dates\": [\"2024-01-01\"],
    \"amounts\": [\"$100.00\"],
    \"entities\": [\"Company Name\", \"Person Name\"],
    \"document_numbers\": [\"INV-12345\"],
    \"emails\": [\"email@example.com\"],
    \"phones\": [\"+1234567890\"],
    \"other\": {}
  }
}";

        // Call OpenRouter API
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_option('geodocs_site_name', get_bloginfo('name')),
            ],
            'body' => json_encode([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => $data_uri,
                                ],
                            ],
                        ],
                    ],
                ],
                'temperature' => 0.3,
                'max_tokens' => 1000,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('[GEODocs] AI API request failed: ' . $response->get_error_message());
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['choices'][0]['message']['content'])) {
            error_log('[GEODocs] AI API returned unexpected response: ' . print_r($body, true));
            return new WP_Error('api_error', __('Failed to analyze document', 'geodocs'), ['status' => 500]);
        }

        // Parse AI response
        $content = $body['choices'][0]['message']['content'];

        // Extract JSON from response (AI might wrap it in markdown)
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $content = $matches[1];
        } elseif (preg_match('/```\s*(.*?)\s*```/s', $content, $matches)) {
            $content = $matches[1];
        }

        $content = trim($content);
        $analysis = json_decode($content, true);

        if (!$analysis || !isset($analysis['title'])) {
            error_log('[GEODocs] Failed to parse AI response. Content: ' . $content);
            return new WP_Error('parse_error', __('Failed to parse AI response', 'geodocs'), ['status' => 500]);
        }

        // Map category name to term ID
        if (isset($analysis['category'])) {
            $category_term = get_term_by('name', $analysis['category'], 'geodocs_category');
            if ($category_term) {
                $analysis['category'] = $category_term->term_id;
            } else {
                // Default to "Other"
                $other_term = get_term_by('name', 'Other', 'geodocs_category');
                $analysis['category'] = $other_term ? $other_term->term_id : null;
            }
        }

        error_log('[GEODocs] AI analysis successful. Title: ' . $analysis['title']);
        return $analysis;
    }

    /**
     * Get settings
     */
    public function get_settings() {
        return rest_ensure_response([
            'apiKey' => get_option('geodocs_openrouter_api_key', ''),
            'defaultModel' => get_option('geodocs_default_model', 'google/gemini-2.0-flash-exp:free'),
            'siteName' => get_option('geodocs_site_name', get_bloginfo('name')),
            'maxFileSize' => get_option('geodocs_max_file_size', 10),
            'allowedFileTypes' => get_option('geodocs_allowed_file_types', 'jpg,jpeg,png,gif,webp'),
            'enableLogging' => get_option('geodocs_enable_logging', true),
        ]);
    }

    /**
     * Update settings
     */
    public function update_settings($request) {
        $data = $request->get_json_params();

        if (isset($data['apiKey'])) {
            update_option('geodocs_openrouter_api_key', sanitize_text_field($data['apiKey']));
        }

        if (isset($data['defaultModel'])) {
            update_option('geodocs_default_model', sanitize_text_field($data['defaultModel']));
        }

        if (isset($data['siteName'])) {
            update_option('geodocs_site_name', sanitize_text_field($data['siteName']));
        }

        if (isset($data['maxFileSize'])) {
            update_option('geodocs_max_file_size', absint($data['maxFileSize']));
        }

        if (isset($data['allowedFileTypes'])) {
            update_option('geodocs_allowed_file_types', sanitize_text_field($data['allowedFileTypes']));
        }

        if (isset($data['enableLogging'])) {
            update_option('geodocs_enable_logging', (bool)$data['enableLogging']);
        }

        return $this->get_settings();
    }

    /**
     * Get profile
     */
    public function get_profile() {
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        if (!$user) {
            return new WP_Error('user_not_found', __('User not found', 'geodocs'), ['status' => 404]);
        }

        return rest_ensure_response([
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'username' => $user->user_login,
        ]);
    }

    /**
     * Update profile
     */
    public function update_profile($request) {
        $user_id = get_current_user_id();
        $data = $request->get_json_params();

        $user_data = ['ID' => $user_id];

        // Update display name
        if (isset($data['name']) && !empty($data['name'])) {
            $user_data['display_name'] = sanitize_text_field($data['name']);
        }

        // Update password if provided
        if (isset($data['password']) && !empty($data['password'])) {
            if (strlen($data['password']) < 8) {
                return new WP_Error('weak_password', __('Password must be at least 8 characters', 'geodocs'), ['status' => 400]);
            }
            $user_data['user_pass'] = $data['password'];
        }

        $result = wp_update_user($user_data);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'success' => true,
            'message' => __('Profile updated successfully', 'geodocs'),
        ]);
    }

    /**
     * Get allowed MIME types (images only)
     */
    private function get_allowed_mime_types() {
        return [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ];
    }

    /**
     * Log activity
     */
    private function log_activity($action, $document_id, $user_id) {
        if (!get_option('geodocs_enable_logging', true)) {
            return;
        }

        $log = get_option('geodocs_activity_log', []);

        $log[] = [
            'action' => $action,
            'document_id' => $document_id,
            'user_id' => $user_id,
            'timestamp' => time(),
        ];

        // Keep only last 100 entries
        if (count($log) > 100) {
            $log = array_slice($log, -100);
        }

        update_option('geodocs_activity_log', $log);
    }

    /**
     * Render frontend shortcode
     */
    public function render_frontend_shortcode($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<div class="geodocs-login-required p-12 text-center bg-white rounded-lg shadow-sm">
                <i class="fas fa-lock text-6xl text-slate-300 mb-4"></i>
                <p class="text-lg text-slate-700 mb-4">' . __('Please log in to access.', 'geodocs') . '</p>
                <a href="' . wp_login_url(get_permalink()) . '" class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">' . __('Log In', 'geodocs') . '</a>
            </div>';
        }

        return '<div id="geodocs-app"></div>';
    }

    /**
     * Render settings page with tabbed interface
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'geodocs'));
        }

        // Handle form submission
        if (isset($_POST['geodocs_save_settings']) && check_admin_referer('geodocs_settings')) {
            // Sanitize and save settings
            if (isset($_POST['api_key'])) {
                update_option('geodocs_openrouter_api_key', sanitize_text_field($_POST['api_key']));
            }
            if (isset($_POST['model'])) {
                update_option('geodocs_default_model', sanitize_text_field($_POST['model']));
            }
            if (isset($_POST['site_name'])) {
                update_option('geodocs_site_name', sanitize_text_field($_POST['site_name']));
            }
            if (isset($_POST['max_file_size'])) {
                update_option('geodocs_max_file_size', absint($_POST['max_file_size']));
            }
            if (isset($_POST['allowed_file_types'])) {
                update_option('geodocs_allowed_file_types', sanitize_text_field($_POST['allowed_file_types']));
            }
            update_option('geodocs_enable_logging', isset($_POST['enable_logging']));

            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'geodocs') . '</p></div>';
        }

        $api_key = get_option('geodocs_openrouter_api_key', '');
        $model = get_option('geodocs_default_model', 'google/gemini-2.0-flash-exp:free');
        $site_name = get_option('geodocs_site_name', get_bloginfo('name'));
        $max_file_size = get_option('geodocs_max_file_size', 10);
        $allowed_file_types = get_option('geodocs_allowed_file_types', 'jpg,jpeg,png,gif,webp');
        $enable_logging = get_option('geodocs_enable_logging', true);

        // Get statistics
        $total_docs = wp_count_posts('geodocs_document')->publish;
        $total_users = count(get_users(['fields' => 'ID']));
        ?>

        <div class="wrap bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto py-8">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-4xl font-bold text-slate-800 mb-2">
                        <i class="fas fa-file-image text-blue-600"></i>
                        <?php _e('GEODocs Settings', 'geodocs'); ?>
                    </h1>
                    <p class="text-slate-600">
                        <?php _e('Configure your AI-powered image document organizer', 'geodocs'); ?>
                    </p>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-slate-600"><?php _e('Total Documents', 'geodocs'); ?></p>
                                <p class="text-3xl font-bold text-slate-800"><?php echo esc_html($total_docs); ?></p>
                            </div>
                            <div class="bg-blue-100 rounded-full p-4">
                                <i class="fas fa-file-image text-2xl text-blue-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-slate-600"><?php _e('Active Users', 'geodocs'); ?></p>
                                <p class="text-3xl font-bold text-slate-800"><?php echo esc_html($total_users); ?></p>
                            </div>
                            <div class="bg-green-100 rounded-full p-4">
                                <i class="fas fa-users text-2xl text-green-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-slate-600"><?php _e('API Status', 'geodocs'); ?></p>
                                <p class="text-lg font-semibold <?php echo empty($api_key) ? 'text-red-600' : 'text-green-600'; ?>">
                                    <?php echo empty($api_key) ? __('Not Configured', 'geodocs') : __('Active', 'geodocs'); ?>
                                </p>
                            </div>
                            <div class="<?php echo empty($api_key) ? 'bg-red-100' : 'bg-green-100'; ?> rounded-full p-4">
                                <i class="fas fa-plug text-2xl <?php echo empty($api_key) ? 'text-red-600' : 'text-green-600'; ?>"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabbed Interface -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <!-- Tab Navigation -->
                    <div class="border-b border-slate-200">
                        <div class="flex">
                            <div class="geodocs-tab active px-6 py-4 font-semibold" data-tab="overview">
                                <i class="fas fa-chart-bar mr-2"></i><?php _e('Overview', 'geodocs'); ?>
                            </div>
                            <div class="geodocs-tab px-6 py-4 font-semibold" data-tab="ai">
                                <i class="fas fa-robot mr-2"></i><?php _e('AI Configuration', 'geodocs'); ?>
                            </div>

                            <div class="geodocs-tab px-6 py-4 font-semibold" data-tab="advanced">
                                <i class="fas fa-cog mr-2"></i><?php _e('Advanced', 'geodocs'); ?>
                            </div>
                        </div>
                    </div>

                    <form method="post" id="geodocs-settings-form" class="p-6">
                        <?php wp_nonce_field('geodocs_settings'); ?>

                        <!-- Overview Tab -->
                        <div id="tab-overview" class="geodocs-tab-content active">
                            <h3 class="text-2xl font-bold text-slate-800 mb-6">Plugin Overview</h3>

                            <div class="bg-blue-50 border-l-4 border-blue-500 p-6 mb-6">
                                <h4 class="font-bold text-blue-800 mb-2">
                                    <i class="fas fa-info-circle mr-2"></i>How GEODocs Works
                                </h4>
                                <p class="text-blue-700 mb-4">
                                    GEODocs uses AI vision models to automatically analyze and categorize your image documents. Simply upload a photo of a receipt, invoice, or any document, and AI will extract relevant information.
                                </p>
                                <ul class="list-disc list-inside text-blue-700 space-y-2">
                                    <li>📸 Upload images or take photos with your camera</li>
                                    <li>🤖 AI automatically analyzes and categorizes documents</li>
                                    <li>🔍 Extracts dates, amounts, names, and other metadata</li>
                                    <li>🔐 Secure storage with private file access</li>
                                    <li>🎨 Beautiful split-screen viewer for easy editing</li>
                                </ul>
                            </div>

                            <div class="bg-slate-50 rounded-lg p-6 mb-6">
                                <h4 class="font-bold text-slate-800 mb-4">Frontend Shortcode</h4>
                                <p class="text-sm text-slate-700 mb-3">
                                    Use this shortcode to display GEODocs on any page:
                                </p>
                                <code class="block bg-slate-800 text-green-400 p-4 rounded-lg font-mono text-sm">
                                    [geodocs]
                                </code>
                            </div>

                            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-6">
                                <h4 class="font-bold text-yellow-800 mb-2">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>Troubleshooting
                                </h4>
                                <div class="text-yellow-700 space-y-2">
                                    <p><strong>If the shortcode doesn't appear:</strong></p>
                                    <ol class="list-decimal list-inside space-y-1 ml-3">
                                        <li>Ensure you're logged in (GEODocs requires authentication)</li>
                                        <li>Check browser console (F12) for JavaScript errors</li>
                                        <li>Verify the shortcode is properly placed: <code class="bg-yellow-100 px-2 py-1 rounded">[geodocs]</code></li>
                                    </ol>

                                    <p class="mt-4"><strong>If you see 404 errors on REST API calls:</strong></p>
                                    <ol class="list-decimal list-inside space-y-1 ml-3">
                                        <li>Go to <strong>Settings → Permalinks</strong></li>
                                        <li>Click <strong>"Save Changes"</strong> (even without changing anything)</li>
                                        <li>This flushes rewrite rules and fixes REST API routes</li>
                                    </ol>

                                    <p class="mt-4"><strong>Test REST API:</strong></p>
                                    <p class="text-sm">
                                        Visit this URL while logged in:
                                        <a href="<?php echo esc_url(rest_url('geodocs/v1/categories')); ?>"
                                           target="_blank"
                                           class="text-blue-600 hover:underline break-all">
                                            <?php echo esc_url(rest_url('geodocs/v1/categories')); ?>
                                        </a>
                                    </p>
                                    <p class="text-sm mt-1">You should see JSON data with categories. If you see a 404 error, flush permalinks.</p>
                                </div>
                            </div>
                        </div>

                        <!-- AI Configuration Tab -->
                        <div id="tab-ai" class="geodocs-tab-content">
                            <h3 class="text-2xl font-bold text-slate-800 mb-6">
                                <i class="fas fa-robot text-purple-600 mr-2"></i>AI Configuration
                            </h3>

                            <div class="space-y-6">
                                <div>
                                    <label for="api_key" class="block text-sm font-medium text-slate-700 mb-2">
                                        <?php _e('OpenRouter API Key', 'geodocs'); ?>
                                    </label>
                                    <input type="password"
                                           id="api_key"
                                           name="api_key"
                                           value="<?php echo esc_attr($api_key); ?>"
                                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           placeholder="sk-or-v1-...">
                                    <p class="mt-1 text-sm text-slate-500">
                                        <?php _e('Get your API key from', 'geodocs'); ?>
                                        <a href="https://openrouter.ai/keys" target="_blank" class="text-blue-600 hover:underline">
                                            OpenRouter <i class="fas fa-external-link-alt text-xs"></i>
                                        </a>
                                    </p>
                                    <?php if (!empty($api_key)): ?>
                                        <button type="button" id="test-api-key" class="mt-3 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                            <i class="fas fa-check-circle"></i>
                                            <?php _e('Test API Key', 'geodocs'); ?>
                                        </button>
                                        <span id="api-test-result" class="ml-3"></span>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label for="model" class="block text-sm font-medium text-slate-700 mb-2">
                                        <?php _e('AI Model', 'geodocs'); ?>
                                    </label>
                                    <input type="text"
                                           id="model"
                                           name="model"
                                           list="model-datalist"
                                           value="<?php echo esc_attr($model); ?>"
                                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           placeholder="Enter model ID (e.g., google/gemini-2.0-flash-exp:free)">
                                    <datalist id="model-datalist">
                                        <option value="google/gemini-2.0-flash-exp:free">Google Gemini 2.0 Flash (Free)</option>
                                        <option value="google/gemini-flash-1.5">Google Gemini 1.5 Flash</option>
                                        <option value="anthropic/claude-3-haiku">Anthropic Claude 3 Haiku</option>
                                        <option value="google/gemini-pro-1.5">Google Gemini Pro 1.5</option>
                                        <option value="openai/gpt-4-vision-preview">OpenAI GPT-4 Vision</option>
                                    </datalist>
                                    <p class="mt-1 text-sm text-slate-500">
                                        <?php _e('Enter the model ID directly or click "Load Available Models" below to see all options', 'geodocs'); ?>
                                    </p>
                                    <div class="mt-3">
                                        <button type="button" id="load-models" class="px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition">
                                            <i class="fas fa-sync-alt"></i>
                                            <?php _e('Load Available Models', 'geodocs'); ?>
                                        </button>
                                    </div>
                                    <div id="models-list" class="hidden mt-4"></div>
                                </div>
                            </div>
                        </div>



                        <!-- Advanced Tab -->
                        <div id="tab-advanced" class="geodocs-tab-content">
                            <h3 class="text-2xl font-bold text-slate-800 mb-6">
                                <i class="fas fa-cog text-slate-600 mr-2"></i>Advanced Settings
                            </h3>

                            <div class="space-y-6">
                                <div>
                                    <label for="site_name" class="block text-sm font-medium text-slate-700 mb-2">
                                        <?php _e('Site Name', 'geodocs'); ?>
                                    </label>
                                    <input type="text"
                                           id="site_name"
                                           name="site_name"
                                           value="<?php echo esc_attr($site_name); ?>"
                                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <p class="mt-1 text-sm text-slate-500">
                                        <?php _e('Used for OpenRouter attribution', 'geodocs'); ?>
                                    </p>
                                </div>

                                <div>
                                    <label for="max_file_size" class="block text-sm font-medium text-slate-700 mb-2">
                                        <?php _e('Max File Size (MB)', 'geodocs'); ?>
                                    </label>
                                    <input type="number"
                                           id="max_file_size"
                                           name="max_file_size"
                                           value="<?php echo esc_attr($max_file_size); ?>"
                                           min="1"
                                           max="100"
                                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>

                                <div>
                                    <label for="allowed_file_types" class="block text-sm font-medium text-slate-700 mb-2">
                                        <?php _e('Allowed File Types', 'geodocs'); ?>
                                    </label>
                                    <input type="text"
                                           id="allowed_file_types"
                                           name="allowed_file_types"
                                           value="<?php echo esc_attr($allowed_file_types); ?>"
                                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <p class="mt-1 text-sm text-slate-500">
                                        <?php _e('Comma-separated list (Images only: jpg,jpeg,png,gif,webp)', 'geodocs'); ?>
                                    </p>
                                </div>

                                <div>
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox"
                                               name="enable_logging"
                                               <?php checked($enable_logging); ?>
                                               class="rounded text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm font-medium text-slate-700">
                                            <?php _e('Enable Activity Logging', 'geodocs'); ?>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button (visible on all tabs) -->
                        <div class="flex items-center justify-between border-t border-slate-200 pt-6 mt-8">
                            <button type="submit"
                                    name="geodocs_save_settings"
                                    class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition shadow-sm">
                                <i class="fas fa-save mr-2"></i>
                                <?php _e('Save Settings', 'geodocs'); ?>
                            </button>

                            <p class="text-sm text-slate-500">
                                <i class="fas fa-shield-alt text-green-600 mr-2"></i>
                                Files are secured with .htaccess protection
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php
    }
}

// Initialize plugin
new GEODocs();
