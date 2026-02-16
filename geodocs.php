<?php 
/**
 * Plugin Name: GEODocs
 * Plugin URI: https://geopard.digital/
 * Description: AI-powered document organizer using OpenRouter & Gemini. Upload, analyze, and organize your documents with artificial intelligence.
 * Version: 0.4
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
define('GEODOCS_VERSION', '0.4');
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
                            const modelSelect = document.getElementById('model');
                            modelSelect.innerHTML = '';

                            models.forEach(model => {
                                const option = document.createElement('option');
                                option.value = model.id;
                                option.textContent = model.name;
                                modelSelect.appendChild(option);
                            });

                            modelsList.innerHTML = '<p class=\"text-sm text-green-600\"><i class=\"fas fa-check\"></i> Loaded ' + models.length + ' vision models</p>';
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
        // Only enqueue if shortcode is present
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'geodocs')) {
            return;
        }

        // Tailwind CSS
        wp_enqueue_script('tailwind-cdn', 'https://cdn.tailwindcss.com', [], null, false);

        // Font Awesome
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', [], '6.4.0');

        // Inline frontend styles
        wp_add_inline_style('font-awesome', $this->get_frontend_inline_styles());

        // Localize script
        wp_localize_script('tailwind-cdn', 'geodocs', [
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

        // Inline frontend JS
        wp_add_inline_script('tailwind-cdn', $this->get_frontend_inline_scripts());
    }

    /**
     * Get frontend inline styles
     */
    private function get_frontend_inline_styles() {
        return '
        .geodocs-drop-zone {
            border: 4px dashed #cbd5e1;
            transition: all 0.3s;
        }
        .geodocs-drop-zone.drag-over {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
        .geodocs-split-view {
            display: none;
        }
        .geodocs-split-view.active {
            display: flex;
        }
        .geodocs-progress-step {
            opacity: 0.3;
        }
        .geodocs-progress-step.active {
            opacity: 1;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .geodocs-pulse {
            animation: pulse 1.5s ease-in-out infinite;
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
        this.renderApp();
        this.loadDocuments();
        this.setupEventListeners();
    }

    renderApp() {
        const container = document.getElementById('geodocs-app');
        container.innerHTML = `
            <div class="max-w-7xl mx-auto p-6">
                <!-- Header with Upload Zone -->
                <div class="geodocs-drop-zone rounded-2xl p-12 mb-8 text-center cursor-pointer" id="drop-zone">
                    <input type="file" id="file-input" multiple accept="image/*" class="hidden">
                    <div class="mb-4">
                        <i class="fas fa-cloud-upload-alt text-6xl text-blue-500"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-700 mb-2">Drop Images Here</h3>
                    <p class="text-slate-500 mb-4">or click to browse from your device</p>
                    <button class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-camera mr-2"></i>Take Photo / Upload Image
                    </button>
                </div>

                <!-- Upload Progress -->
                <div id="upload-progress" class="hidden bg-white rounded-lg shadow-sm p-6 mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-semibold text-slate-800">Processing Documents</h4>
                        <span id="queue-counter" class="text-sm text-slate-600">0/0</span>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center gap-4">
                            <div class="geodocs-progress-step active" id="step-upload">
                                <i class="fas fa-upload text-blue-600"></i>
                                <span class="text-sm ml-2">Uploading file...</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="geodocs-progress-step" id="step-scan">
                                <i class="fas fa-search text-blue-600"></i>
                                <span class="text-sm ml-2">Scanning document...</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="geodocs-progress-step" id="step-extract">
                                <i class="fas fa-brain text-blue-600"></i>
                                <span class="text-sm ml-2">Extracting metadata...</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="geodocs-progress-step" id="step-done">
                                <i class="fas fa-check-circle text-green-600"></i>
                                <span class="text-sm ml-2">Done!</span>
                            </div>
                        </div>
                    </div>
                    <div class="w-full bg-slate-200 rounded-full h-2 mt-4">
                        <div id="progress-bar" class="bg-blue-600 h-2 rounded-full transition-all" style="width: 0%"></div>
                    </div>
                </div>

                <!-- Search & Filters -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                    <div class="flex gap-4">
                        <input type="text" id="search-input" placeholder="Search documents..."
                               class="flex-1 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <button id="clear-search" class="px-4 py-2 text-slate-600 hover:text-slate-800">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Categories -->
                <div class="mb-6 overflow-x-auto">
                    <div id="categories-filter" class="flex gap-3">
                        <!-- Categories rendered here -->
                    </div>
                </div>

                <!-- Documents Grid -->
                <div id="documents-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Documents rendered here -->
                </div>

                <!-- Split Screen Viewer -->
                <div id="split-viewer" class="geodocs-split-view fixed inset-0 bg-black bg-opacity-50 z-50">
                    <div class="bg-white h-full max-w-7xl mx-auto flex">
                        <div class="w-3/5 p-6 overflow-auto">
                            <img id="viewer-image" class="w-full h-auto rounded-lg" src="" alt="">
                        </div>
                        <div class="w-2/5 bg-slate-50 p-6 overflow-auto border-l">
                            <button id="close-viewer" class="mb-4 text-slate-600 hover:text-slate-800">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                            <div id="viewer-details">
                                <!-- Details rendered here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        this.renderCategories();
    }

    renderCategories() {
        const container = document.getElementById('categories-filter');
        let html = `
            <button class="px-4 py-2 rounded-lg font-medium ${!this.selectedCategory ? 'bg-blue-600 text-white' : 'bg-white text-slate-700'} hover:shadow-md transition"
                    onclick="app.filterByCategory(null)">
                All Documents
            </button>
        `;

        geodocs.categories.forEach(cat => {
            const isActive = this.selectedCategory === cat.id;
            html += `
                <button class="px-4 py-2 rounded-lg font-medium ${isActive ? 'bg-blue-600 text-white' : 'bg-white text-slate-700'} hover:shadow-md transition"
                        onclick="app.filterByCategory(${cat.id})">
                    <span class="mr-2">${cat.icon}</span>
                    ${cat.name}
                </button>
            `;
        });

        container.innerHTML = html;
    }

    setupEventListeners() {
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');

        dropZone.addEventListener('click', () => fileInput.click());

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('drag-over');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            this.handleFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', (e) => {
            this.handleFiles(e.target.files);
        });

        document.getElementById('search-input').addEventListener('input', (e) => {
            this.searchQuery = e.target.value;
            this.loadDocuments();
        });

        document.getElementById('clear-search').addEventListener('click', () => {
            document.getElementById('search-input').value = '';
            this.searchQuery = '';
            this.loadDocuments();
        });

        document.getElementById('close-viewer').addEventListener('click', () => {
            document.getElementById('split-viewer').classList.remove('active');
        });
    }

    handleFiles(files) {
        Array.from(files).forEach(file => {
            if (file.type.startsWith('image/')) {
                this.uploadQueue.push(file);
            }
        });

        if (!this.processing) {
            this.processQueue();
        }
    }

    async processQueue() {
        if (this.uploadQueue.length === 0) {
            this.processing = false;
            document.getElementById('upload-progress').classList.add('hidden');
            this.loadDocuments();
            return;
        }

        this.processing = true;
        document.getElementById('upload-progress').classList.remove('hidden');

        const file = this.uploadQueue.shift();
        const total = this.uploadQueue.length + 1;
        const current = total - this.uploadQueue.length;

        document.getElementById('queue-counter').textContent = `${current}/${total}`;

        await this.uploadFile(file);

        setTimeout(() => this.processQueue(), 500);
    }

    async uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file);

        try {
            // Step 1: Upload
            this.updateProgress(25, ['upload']);

            // Step 2: Scan
            setTimeout(() => this.updateProgress(50, ['upload', 'scan']), 500);

            const response = await fetch(geodocs.restUrl + 'documents', {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': geodocs.nonce
                },
                body: formData
            });

            if (!response.ok) throw new Error('Upload failed');

            // Step 3: Extract
            this.updateProgress(75, ['upload', 'scan', 'extract']);

            await response.json();

            // Step 4: Done
            this.updateProgress(100, ['upload', 'scan', 'extract', 'done']);

        } catch (error) {
            console.error('Upload error:', error);
        }
    }

    updateProgress(percent, activeSteps) {
        document.getElementById('progress-bar').style.width = percent + '%';

        ['upload', 'scan', 'extract', 'done'].forEach(step => {
            const el = document.getElementById('step-' + step);
            if (activeSteps.includes(step)) {
                el.classList.add('active');
                if (step !== 'done') el.classList.add('geodocs-pulse');
            } else {
                el.classList.remove('geodocs-pulse');
            }
        });
    }

    async loadDocuments() {
        let url = geodocs.restUrl + 'documents?per_page=-1';
        if (this.selectedCategory) url += '&category=' + this.selectedCategory;
        if (this.searchQuery) url += '&search=' + encodeURIComponent(this.searchQuery);

        const response = await fetch(url, {
            headers: { 'X-WP-Nonce': geodocs.nonce }
        });

        const data = await response.json();
        this.currentDocuments = data.documents;
        this.renderDocuments();
    }

    renderDocuments() {
        const container = document.getElementById('documents-grid');

        if (this.currentDocuments.length === 0) {
            container.innerHTML = `
                <div class="col-span-full text-center py-12 text-slate-500">
                    <i class="fas fa-inbox text-6xl mb-4"></i>
                    <p class="text-xl">No documents found</p>
                </div>
            `;
            return;
        }

        container.innerHTML = this.currentDocuments.map(doc => `
            <div class="bg-white rounded-lg shadow-sm overflow-hidden cursor-pointer hover:shadow-lg transition"
                 onclick="app.viewDocument(${doc.id})">
                <div class="aspect-video bg-slate-100 flex items-center justify-center overflow-hidden">
                    <img src="${geodocs.restUrl}download/${doc.id}"
                         class="w-full h-full object-cover"
                         alt="${doc.title}">
                </div>
                <div class="p-4">
                    <h3 class="font-semibold text-slate-800 mb-2">${doc.title}</h3>
                    <p class="text-sm text-slate-600 line-clamp-2">${doc.description}</p>
                    ${doc.category ? `
                        <div class="mt-3">
                            <span class="${doc.category.color} text-white text-xs px-2 py-1 rounded">
                                ${doc.category.icon} ${doc.category.name}
                            </span>
                        </div>
                    ` : ''}
                </div>
            </div>
        `).join('');
    }

    filterByCategory(categoryId) {
        this.selectedCategory = categoryId;
        this.renderCategories();
        this.loadDocuments();
    }

    async viewDocument(id) {
        const doc = this.currentDocuments.find(d => d.id === id);
        if (!doc) return;

        document.getElementById('viewer-image').src = geodocs.restUrl + 'download/' + id;
        document.getElementById('viewer-details').innerHTML = `
            <h2 class="text-2xl font-bold text-slate-800 mb-4">${doc.title}</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Description</label>
                    <textarea id="edit-description" class="w-full px-3 py-2 border border-slate-300 rounded-lg" rows="4">${doc.description}</textarea>
                </div>

                ${doc.category ? `
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Category</label>
                        <div class="${doc.category.color} text-white px-4 py-2 rounded-lg inline-block">
                            ${doc.category.icon} ${doc.category.name}
                        </div>
                    </div>
                ` : ''}

                ${Object.keys(doc.metadata).length > 0 ? `
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Extracted Data</label>
                        <div class="bg-slate-100 rounded-lg p-4 text-sm">
                            ${Object.entries(doc.metadata).map(([key, value]) => {
                                if (Array.isArray(value) && value.length > 0) {
                                    return `<div class="mb-2"><strong>${key}:</strong> ${value.join(', ')}</div>`;
                                }
                                return '';
                            }).join('')}
                        </div>
                    </div>
                ` : ''}

                <div class="flex gap-3">
                    <button onclick="app.saveDocument(${id})" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                    <button onclick="app.deleteDocument(${id})" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;

        document.getElementById('split-viewer').classList.add('active');
    }

    async saveDocument(id) {
        const description = document.getElementById('edit-description').value;

        await fetch(geodocs.restUrl + 'documents/' + id, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': geodocs.nonce
            },
            body: JSON.stringify({ description })
        });

        this.loadDocuments();
        document.getElementById('split-viewer').classList.remove('active');
    }

    async deleteDocument(id) {
        if (!confirm('Are you sure you want to delete this document?')) return;

        await fetch(geodocs.restUrl + 'documents/' + id, {
            method: 'DELETE',
            headers: { 'X-WP-Nonce': geodocs.nonce }
        });

        this.loadDocuments();
        document.getElementById('split-viewer').classList.remove('active');
    }
}

// Initialize app
let app;
document.addEventListener('DOMContentLoaded', () => {
    app = new GeoDocsApp();
});
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
    }

    /**
     * Secure file download endpoint
     */
    public function download_document($request) {
        $id = $request->get_param('id');
        $post = get_post($id);

        if (!$post || $post->post_type !== 'geodocs_document') {
            return new WP_Error('not_found', __('Document not found', 'geodocs'), ['status' => 404]);
        }

        // Check ownership
        if ($post->post_author != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('unauthorized', __('Unauthorized', 'geodocs'), ['status' => 403]);
        }

        $file_url = get_post_meta($id, '_geodocs_file_url', true);
        if (!$file_url) {
            return new WP_Error('no_file', __('No file attached', 'geodocs'), ['status' => 404]);
        }

        $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $file_url);

        if (!file_exists($file_path)) {
            return new WP_Error('file_missing', __('File not found on server', 'geodocs'), ['status' => 404]);
        }

        // Stream the file
        $mime_type = get_post_meta($id, '_geodocs_file_type', true);
        header('Content-Type: ' . $mime_type);
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
            'fileUrl' => rest_url('geodocs/v1/download/' . $post->ID),
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

        // Upload file
        $upload = wp_handle_upload($file, ['test_form' => false]);

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

        // Save file metadata
        update_post_meta($post_id, '_geodocs_file_url', $new_filename['url']);
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
            return new WP_Error('no_api_key', __('OpenRouter API key not configured', 'geodocs'), ['status' => 400]);
        }

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
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['choices'][0]['message']['content'])) {
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
                <p class="text-lg text-slate-700 mb-4">' . __('Please log in to access your documents.', 'geodocs') . '</p>
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
            update_option('geodocs_openrouter_api_key', sanitize_text_field($_POST['api_key']));
            update_option('geodocs_default_model', sanitize_text_field($_POST['model']));
            update_option('geodocs_site_name', sanitize_text_field($_POST['site_name']));
            update_option('geodocs_max_file_size', absint($_POST['max_file_size']));
            update_option('geodocs_allowed_file_types', sanitize_text_field($_POST['allowed_file_types']));
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
                            <div class="geodocs-tab px-6 py-4 font-semibold" data-tab="categories">
                                <i class="fas fa-folder mr-2"></i><?php _e('Categories', 'geodocs'); ?>
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

                            <div class="bg-slate-50 rounded-lg p-6">
                                <h4 class="font-bold text-slate-800 mb-4">Frontend Shortcode</h4>
                                <p class="text-sm text-slate-700 mb-3">
                                    Use this shortcode to display GEODocs on any page:
                                </p>
                                <code class="block bg-slate-800 text-green-400 p-4 rounded-lg font-mono text-sm">
                                    [geodocs]
                                </code>
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
                                    <select id="model"
                                            name="model"
                                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="google/gemini-2.0-flash-exp:free" <?php selected($model, 'google/gemini-2.0-flash-exp:free'); ?>>
                                            Google Gemini 2.0 Flash (Free)
                                        </option>
                                        <option value="google/gemini-flash-1.5" <?php selected($model, 'google/gemini-flash-1.5'); ?>>
                                            Google Gemini 1.5 Flash
                                        </option>
                                        <option value="anthropic/claude-3-haiku" <?php selected($model, 'anthropic/claude-3-haiku'); ?>>
                                            Anthropic Claude 3 Haiku
                                        </option>
                                    </select>
                                    <div class="mt-3">
                                        <button type="button" id="load-models" class="px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition">
                                            <i class="fas fa-sync-alt"></i>
                                            <?php _e('Load All Available Models', 'geodocs'); ?>
                                        </button>
                                    </div>
                                    <div id="models-list" class="hidden mt-4"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Categories Tab -->
                        <div id="tab-categories" class="geodocs-tab-content">
                            <h3 class="text-2xl font-bold text-slate-800 mb-6">
                                <i class="fas fa-folder text-yellow-600 mr-2"></i>Document Categories
                            </h3>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <?php
                                $categories = get_terms(['taxonomy' => 'geodocs_category', 'hide_empty' => false]);
                                foreach ($categories as $cat) {
                                    $icon = get_term_meta($cat->term_id, 'icon', true);
                                    $color = get_term_meta($cat->term_id, 'color', true);
                                    ?>
                                    <div class="<?php echo esc_attr($color); ?> text-white rounded-lg p-6 text-center shadow-sm hover:shadow-md transition">
                                        <div class="text-4xl mb-3"><?php echo esc_html($icon); ?></div>
                                        <div class="font-semibold"><?php echo esc_html($cat->name); ?></div>
                                        <div class="text-sm opacity-90 mt-1"><?php echo esc_html($cat->count); ?> documents</div>
                                    </div>
                                    <?php
                                }
                                ?>
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
