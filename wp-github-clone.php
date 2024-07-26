<?php
/*
Plugin Name: WP GitHub Clone
Plugin URI: https://yourwebsite.com/plugin
Description: A plugin to clone and display GitHub repositories.
Version: 1.0.0
Author: Your Name
Author URI: https://yourwebsite.com/
License: GPL2
Text Domain: wp-github-clone
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-wp-github-clone.php';
require_once plugin_dir_path(__FILE__) . 'includes/functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';

function run_wp_github_clone() {
    $plugin = new WP_GitHub_Clone();
    $plugin->run();
}

run_wp_github_clone();

// enqueue scripts
function wp_github_clone_enqueue_scripts($hook) {
    if ($hook != 'settings_page_wp-github-clone') {
        return;
    }

    wp_enqueue_script('wp-github-clone-script', plugin_dir_url(__FILE__) . 'admin/js/script.js', array('jquery'), '1.0.0', true);
    wp_localize_script('wp-github-clone-script', 'wpGithubClone', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wp_github_clone_nonce'),
        'manual_pull_nonce' => wp_create_nonce('wp_github_clone_manual_pull')
    ));
}
add_action('admin_enqueue_scripts', 'wp_github_clone_enqueue_scripts');


// enqueue styles
function wp_github_clone_enqueue_admin_styles($hook) {
    if ($hook != 'settings_page_wp-github-clone') {
        return;
    }

    wp_enqueue_style('wp-github-clone-admin-style', plugin_dir_url(__FILE__) . 'admin/css/style.css', array(), '1.0.0');
}
add_action('admin_enqueue_scripts', 'wp_github_clone_enqueue_admin_styles');

// AJAX handler for the Pull action
function wp_github_clone_pull() {
    error_log("POST data: " . print_r($_POST, true));

    check_ajax_referer('wp_github_clone_nonce', 'nonce');

    if (!isset($_POST['repo']) || empty($_POST['repo'])) {
        wp_send_json(array(
            'success' => false,
            'message' => "Repository name not provided."
        ));
        return;
    }

    $repo_name = sanitize_text_field($_POST['repo']);

    // Retrieve the repository type from the database
    $repo_type = get_option("wp_github_clone_type_{$repo_name}", 'theme'); // Default to 'theme' if not found

    // Determine the path based on the repository type
    $repo_path = ($repo_type === 'plugin') ? WP_CONTENT_DIR . '/plugins/' . $repo_name : WP_CONTENT_DIR . '/themes/' . $repo_name;

    error_log("Checking repo path: " . $repo_path);
    
    if (is_dir($repo_path)) {
        // Execute git pull for the repository
        chdir($repo_path);
        $output = shell_exec('git pull');
        wp_send_json(array(
            'success' => true,
            'message' => "Successfully pulled {$repo_name} from {$repo_type}s", // 'themes' or 'plugins'
            'details' => $output
        ));
    } else {
        error_log("Directory not found in {$repo_type}s for repo: " . $repo_name);
        wp_send_json(array(
            'success' => false,
            'message' => "Failed to pull {$repo_name}. Directory not found in {$repo_type}s." // 'themes' or 'plugins'
        ));
    }
}



add_action('wp_ajax_wp_github_clone_pull', 'wp_github_clone_pull');



function wp_github_clone_admin_notices() {
    if (get_transient('wp_github_clone_pull_success')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Successfully pulled from GitHub', 'wp-github-clone'); ?></p>
        </div>
        <?php
        delete_transient('wp_github_clone_pull_success'); // Delete the transient to ensure it only shows once
    }
}
add_action('admin_notices', 'wp_github_clone_admin_notices');


function wp_github_clone_delete() {
    check_ajax_referer('wp_github_clone_nonce', 'nonce');

    if (!isset($_POST['repo']) || empty($_POST['repo'])) {
        $errorDetails = isset($_POST['repo']) ? "Repo name was empty." : "Repo index not set in POST request.";
        wp_send_json(array(
            'success' => false,
            'message' => "Repository name not provided.",
            'details' => $errorDetails
        ));
        return;
    }
    
    $repo_name = sanitize_text_field($_POST['repo']);
    
    error_log("Attempting to delete repository: " . $repo_name);

    // Check both the themes and plugins directories
    $theme_repo_path = WP_CONTENT_DIR . '/themes/' . $repo_name;
    $plugin_repo_path = WP_CONTENT_DIR . '/plugins/' . $repo_name;

    if (is_dir($theme_repo_path)) {
        rrmdir($theme_repo_path);
        wp_send_json(array(
            'success' => true,
            'message' => "Successfully deleted {$repo_name} from themes"
        ));
    } elseif (is_dir($plugin_repo_path)) {
        rrmdir($plugin_repo_path);
        wp_send_json(array(
            'success' => true,
            'message' => "Successfully deleted {$repo_name} from plugins"
        ));
    } else {
        wp_send_json(array(
            'success' => false,
            'message' => "Failed to delete {$repo_name}. Directory not found in themes or plugins."
        ));
    }
}
add_action('wp_ajax_wp_github_clone_delete', 'wp_github_clone_delete');

// New rrmdir function
function rrmdir($dir) {
    if (!is_dir($dir)) {
        return;
    }

    $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($files as $file) {
        if ($file->isDir()) {
            if (!rmdir($file->getRealPath())) {
                error_log("Failed to delete directory: " . $file->getRealPath());
            }
        } else {
            if (!unlink($file->getRealPath())) {
                error_log("Failed to delete file: " . $file->getRealPath());
            }
        }
    }

    if (!rmdir($dir)) {
        error_log("Failed to delete main directory: " . $dir);
    }
}




add_action('wp_ajax_test_github_clone_delete', 'wp_github_clone_delete');



// AJAX handler for the Clone action
function wp_github_clone_ajax() {
    check_ajax_referer('wp_github_clone_nonce', 'nonce');

    $github_url = isset($_POST['github-url']) ? sanitize_text_field($_POST['github-url']) : '';
    $pat = isset($_POST['github-pat']) ? sanitize_text_field($_POST['github-pat']) : '';
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'theme';
    $repo_access_type = isset($_POST['repo-access-type']) ? sanitize_text_field($_POST['repo-access-type']) : 'public';

    if (empty($github_url)) {
        wp_send_json(array(
            'success' => false,
            'message' => "Missing GitHub URL.",
        ));
        return;
    }

    if ($repo_access_type === 'private' && empty($pat)) {
        wp_send_json(array(
            'success' => false,
            'message' => "Missing Personal Access Token for a private repository.",
        ));
        return;
    }

    // Decide the destination directory based on type
    $destination_directory = WP_CONTENT_DIR . '/themes'; // Default to themes
    if ($type === 'plugin') {
        $destination_directory = WP_CONTENT_DIR . '/plugins';
    }

    $clone_result = clone_github_repo($github_url, $pat, $destination_directory, $type, $access_type);


    if ($clone_result['success']) {
        wp_send_json(array(
            'success' => true,
            'message' => "Successfully cloned {$github_url}",
        ));
    } else {
        wp_send_json(array(
            'success' => false,
            'message' => $clone_result['message'],
        ));
    }
}

add_action('wp_ajax_wp_github_clone_ajax', 'wp_github_clone_ajax');










