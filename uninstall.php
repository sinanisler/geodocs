<?php
/**
 * GEODocs Uninstall Script
 * 
 * This file runs when the plugin is uninstalled (deleted).
 * It cleans up all plugin data from the database.
 * 
 * @package GEODocs
 * @version 0.1
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all documents
$documents = get_posts([
    'post_type' => 'geodocs_document',
    'numberposts' => -1,
    'post_status' => 'any',
]);

foreach ($documents as $document) {
    // Delete associated file
    $file_url = get_post_meta($document->ID, '_geodocs_file_url', true);
    if ($file_url) {
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Delete post and all meta
    wp_delete_post($document->ID, true);
}

// Delete all categories and their meta
$categories = get_terms([
    'taxonomy' => 'geodocs_category',
    'hide_empty' => false,
]);

foreach ($categories as $category) {
    wp_delete_term($category->term_id, 'geodocs_category');
}

// Delete plugin options
delete_option('geodocs_openrouter_api_key');
delete_option('geodocs_default_model');
delete_option('geodocs_site_name');
delete_option('geodocs_max_file_size');
delete_option('geodocs_allowed_file_types');
delete_option('geodocs_enable_logging');
delete_option('geodocs_activity_log');

// Delete uploads directory
$upload_dir = wp_upload_dir();
$geodocs_dir = $upload_dir['basedir'] . '/geodocs';
if (is_dir($geodocs_dir)) {
    // Delete all files in directory
    $files = glob($geodocs_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    // Remove directory
    rmdir($geodocs_dir);
}

// Clear any cached data
wp_cache_flush();
