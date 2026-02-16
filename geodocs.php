<?php 
/**
 * Plugin Name: GEODocs
 * Plugin URI: https://geopard.digital/
 * Description: AI-powered document organizer using OpenRouter & Gemini. Upload, analyze, and organize your documents with artificial intelligence.
 * Version: 0.1
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
define('GEODOCS_VERSION', '0.1');
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
        
        // Set default options
        add_option('geodocs_openrouter_api_key', '');
        add_option('geodocs_default_model', 'google/gemini-2.0-flash-exp:free');
        add_option('geodocs_site_name', get_bloginfo('name'));
        add_option('geodocs_max_file_size', 10); // MB
        add_option('geodocs_allowed_file_types', 'pdf,jpg,jpeg,png,gif,webp');
        add_option('geodocs_enable_logging', true);
        
        // Create uploads directory
        $upload_dir = wp_upload_dir();
        $geodocs_dir = $upload_dir['basedir'] . '/geodocs';
        if (!file_exists($geodocs_dir)) {
            wp_mkdir_p($geodocs_dir);
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
        
        // Admin CSS
        wp_enqueue_style(
            'geodocs-admin-css',
            GEODOCS_PLUGIN_URL . 'assets/css/admin-style.css',
            [],
            GEODOCS_VERSION
        );
        
        // Admin JS
        wp_enqueue_script(
            'geodocs-admin-js',
            GEODOCS_PLUGIN_URL . 'assets/js/admin-script.js',
            [],
            GEODOCS_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('geodocs-admin-js', 'geodocs', [
            'restUrl' => rest_url('geodocs/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'pluginUrl' => GEODOCS_PLUGIN_URL,
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
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
        
        // Frontend CSS
        wp_enqueue_style(
            'geodocs-frontend-css',
            GEODOCS_PLUGIN_URL . 'assets/css/frontend-style.css',
            [],
            GEODOCS_VERSION
        );
        
        // Frontend JS
        wp_enqueue_script(
            'geodocs-frontend-js',
            GEODOCS_PLUGIN_URL . 'assets/js/frontend-script.js',
            [],
            GEODOCS_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('geodocs-frontend-js', 'geodocs', [
            'restUrl' => rest_url('geodocs/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'currentUser' => [
                'id' => get_current_user_id(),
                'name' => wp_get_current_user()->display_name,
                'email' => wp_get_current_user()->user_email,
            ],
            'pluginUrl' => GEODOCS_PLUGIN_URL,
            'uploadsUrl' => wp_upload_dir()['baseurl'],
            'categories' => $this->get_categories_for_js(),
            'maxFileSize' => get_option('geodocs_max_file_size', 10) * 1024 * 1024,
            'allowedTypes' => explode(',', get_option('geodocs_allowed_file_types', 'pdf,jpg,jpeg,png,gif,webp')),
        ]);
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
            'fileUrl' => get_post_meta($post->ID, '_geodocs_file_url', true),
            'fileType' => get_post_meta($post->ID, '_geodocs_file_type', true),
            'fileSize' => get_post_meta($post->ID, '_geodocs_file_size', true),
            'metadata' => json_decode(get_post_meta($post->ID, '_geodocs_metadata', true), true) ?: [],
            'createdAt' => strtotime($post->post_date),
            'userId' => $post->post_author,
        ];
    }
    
    /**
     * Create document
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
        
        // Validate file type
        $allowed_types = $this->get_allowed_mime_types();
        if (!in_array($file['type'], $allowed_types)) {
            return new WP_Error('invalid_file', __('Invalid file type', 'geodocs'), ['status' => 400]);
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
        update_post_meta($post_id, '_geodocs_file_url', $upload['url']);
        update_post_meta($post_id, '_geodocs_file_type', $file['type']);
        update_post_meta($post_id, '_geodocs_file_size', $file['size']);
        update_post_meta($post_id, '_geodocs_metadata', json_encode($analysis['metadata']));
        
        // Log activity
        $this->log_activity('document_created', $post_id, get_current_user_id());
        
        $post = get_post($post_id);
        return rest_ensure_response($this->format_document($post));
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
     * Analyze document with OpenRouter
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
            'allowedFileTypes' => get_option('geodocs_allowed_file_types', 'pdf,jpg,jpeg,png,gif,webp'),
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
     * Get allowed MIME types
     */
    private function get_allowed_mime_types() {
        return [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'application/pdf',
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
            return '<div class="geodocs-login-required">
                <p>' . __('Please log in to access your documents.', 'geodocs') . '</p>
                <a href="' . wp_login_url(get_permalink()) . '" class="button">' . __('Log In', 'geodocs') . '</a>
            </div>';
        }
        
        $atts = shortcode_atts([
            'view' => 'grid', // grid or list
            'per_page' => 12,
            'show_upload' => 'true',
            'show_search' => 'true',
            'show_filters' => 'true',
        ], $atts);
        
        ob_start();
        ?>
        <div id="geodocs-app" 
             class="geodocs-frontend-app"
             data-view="<?php echo esc_attr($atts['view']); ?>"
             data-per-page="<?php echo esc_attr($atts['per_page']); ?>"
             data-show-upload="<?php echo esc_attr($atts['show_upload']); ?>"
             data-show-search="<?php echo esc_attr($atts['show_search']); ?>"
             data-show-filters="<?php echo esc_attr($atts['show_filters']); ?>">
            <!-- React-like app will be rendered here by JavaScript -->
            <div class="geodocs-loading">
                <div class="geodocs-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
                <p><?php _e('Loading GEODocs...', 'geodocs'); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render settings page
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
        $allowed_file_types = get_option('geodocs_allowed_file_types', 'pdf,jpg,jpeg,png,gif,webp');
        $enable_logging = get_option('geodocs_enable_logging', true);
        
        // Get statistics
        $total_docs = wp_count_posts('geodocs_document')->publish;
        $total_users = count(get_users(['fields' => 'ID']));
        ?>
        
        <div class="wrap bg-slate-50 min-h-screen">
            <div class="max-w-6xl mx-auto py-8">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-4xl font-bold text-slate-800 mb-2">
                        <i class="fas fa-file-alt text-blue-600"></i>
                        <?php _e('GEODocs Settings', 'geodocs'); ?>
                    </h1>
                    <p class="text-slate-600">
                        <?php _e('Configure your AI-powered document management system', 'geodocs'); ?>
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
                                <i class="fas fa-file-alt text-2xl text-blue-600"></i>
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
                
                <!-- Settings Form -->
                <div class="bg-white rounded-lg shadow-sm">
                    <div class="border-b border-slate-200 p-6">
                        <h2 class="text-2xl font-semibold text-slate-800">
                            <i class="fas fa-cog text-slate-600"></i>
                            <?php _e('Plugin Configuration', 'geodocs'); ?>
                        </h2>
                    </div>
                    
                    <form method="post" id="geodocs-settings-form" class="p-6">
                        <?php wp_nonce_field('geodocs_settings'); ?>
                        
                        <!-- General Settings -->
                        <div class="mb-8">
                            <h3 class="text-xl font-semibold text-slate-800 mb-4 flex items-center gap-2">
                                <i class="fas fa-sliders-h text-blue-600"></i>
                                <?php _e('General Settings', 'geodocs'); ?>
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
                                        <?php _e('Comma-separated list (e.g., pdf,jpg,jpeg,png)', 'geodocs'); ?>
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
                        
                        <!-- AI Configuration -->
                        <div class="mb-8 border-t border-slate-200 pt-8">
                            <h3 class="text-xl font-semibold text-slate-800 mb-4 flex items-center gap-2">
                                <i class="fas fa-robot text-purple-600"></i>
                                <?php _e('AI Configuration', 'geodocs'); ?>
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
                        
                        <!-- Shortcode Usage -->
                        <div class="mb-8 border-t border-slate-200 pt-8">
                            <h3 class="text-xl font-semibold text-slate-800 mb-4 flex items-center gap-2">
                                <i class="fas fa-code text-green-600"></i>
                                <?php _e('Frontend Usage', 'geodocs'); ?>
                            </h3>
                            
                            <div class="bg-slate-50 rounded-lg p-4">
                                <p class="text-sm text-slate-700 mb-3">
                                    <?php _e('Use this shortcode to display GEODocs on any page:', 'geodocs'); ?>
                                </p>
                                <code class="block bg-slate-800 text-green-400 p-4 rounded-lg font-mono text-sm mb-3">
                                    [geodocs]
                                </code>
                                <p class="text-sm text-slate-700 mb-2">
                                    <?php _e('With custom attributes:', 'geodocs'); ?>
                                </p>
                                <code class="block bg-slate-800 text-green-400 p-4 rounded-lg font-mono text-sm">
                                    [geodocs view="list" per_page="20" show_upload="true"]
                                </code>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="flex items-center justify-between border-t border-slate-200 pt-6">
                            <button type="submit" 
                                    name="geodocs_save_settings" 
                                    class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition shadow-sm">
                                <i class="fas fa-save"></i>
                                <?php _e('Save Settings', 'geodocs'); ?>
                            </button>
                            
                            <a href="<?php echo admin_url('options-general.php?page=geodocs-settings'); ?>" 
                               class="text-slate-600 hover:text-slate-800">
                                <?php _e('Reset to Defaults', 'geodocs'); ?>
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Categories Management -->
                <div class="bg-white rounded-lg shadow-sm mt-8">
                    <div class="border-b border-slate-200 p-6">
                        <h2 class="text-2xl font-semibold text-slate-800">
                            <i class="fas fa-folder text-yellow-600"></i>
                            <?php _e('Document Categories', 'geodocs'); ?>
                        </h2>
                    </div>
                    
                    <div class="p-6">
                        <div id="categories-manager" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <?php
                            $categories = get_terms(['taxonomy' => 'geodocs_category', 'hide_empty' => false]);
                            foreach ($categories as $cat) {
                                $icon = get_term_meta($cat->term_id, 'icon', true);
                                $color = get_term_meta($cat->term_id, 'color', true);
                                ?>
                                <div class="<?php echo esc_attr($color); ?> text-white rounded-lg p-4 text-center">
                                    <div class="text-3xl mb-2"><?php echo esc_html($icon); ?></div>
                                    <div class="font-semibold text-sm"><?php echo esc_html($cat->name); ?></div>
                                    <div class="text-xs opacity-75"><?php echo esc_html($cat->count); ?> docs</div>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
    }
}

// Initialize plugin
new GEODocs();
