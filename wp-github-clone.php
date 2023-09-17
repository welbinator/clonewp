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
        'nonce' => wp_create_nonce('wp_github_clone_nonce')
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
   
    check_ajax_referer('wp_github_clone_nonce', 'nonce');

    $repo_name = isset($_POST['repo']) ? sanitize_text_field($_POST['repo']) : '';

    if (empty($repo_name)) {
        wp_send_json(array(
            'success' => false,
            'message' => "Repository name not provided."
        ));
        return;
    }

    $repo_path = WP_CONTENT_DIR . '/themes/' . $repo_name;

    // Fetch the PAT associated with this repo
    $token = get_option('wp_github_clone_token_' . $repo_name);

    // Add the token to the local git config for this repo
    shell_exec("git -C {$repo_path} config credential.helper 'store --file=.git/credentials'");
    shell_exec("git -C {$repo_path} config credential.username {$token}");

    putenv("COMPOSER_HOME=" . sys_get_temp_dir() . "/composer");

    // Capture the output and errors of the git pull command
    $output = shell_exec("git -C {$repo_path} pull 2>&1");

    // Adjust as necessary based on your experience with typical git pull outputs.
    if (strpos($output, 'Already up to date') !== false || strpos($output, 'Fast-forward') !== false) {
        set_transient('wp_github_clone_pull_success', true, 5); // This sets a transient for 5 seconds
        wp_send_json(array(
            'success' => true,
            'message' => "Successfully pulled changes for {$repo_name}",
            'details' => $output // This provides additional info about the pull.
        ));

    } else {
        wp_send_json(array(
            'success' => false,
            'message' => "Failed to pull changes for {$repo_name}",
            'details' => $output // This provides details on why the pull failed.
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

// AJAX handler for the Delete action
// function wp_github_clone_delete() {
//     error_reporting(E_ALL);
// ini_set('display_errors', 1);

//     check_ajax_referer('wp_github_clone_nonce', 'nonce');

//     if (!isset($_POST['repo']) || empty($_POST['repo'])) {
//         $errorDetails = isset($_POST['repo']) ? "Repo name was empty." : "Repo index not set in POST request.";
//         wp_send_json(array(
//             'success' => false,
//             'message' => "Repository name not provided.",
//             'details' => $errorDetails
//         ));
//         return;
//     }
    
//     $repo_name = sanitize_text_field($_POST['repo']);
    
//     error_log("Attempting to delete repository: " . $repo_name);

//     // Check both the themes and plugins directories
//     $theme_repo_path = WP_CONTENT_DIR . '/themes/' . $repo_name;
//     $plugin_repo_path = WP_CONTENT_DIR . '/plugins/' . $repo_name;

//     if (is_dir($theme_repo_path)) {
//         rrmdir($theme_repo_path);
//         wp_send_json(array(
//             'success' => true,
//             'message' => "Successfully deleted {$repo_name} from themes"
//         ));
//     } elseif (is_dir($plugin_repo_path)) {
//         rrmdir($plugin_repo_path);
//         wp_send_json(array(
//             'success' => true,
//             'message' => "Successfully deleted {$repo_name} from plugins"
//         ));
//     } else {
//         wp_send_json(array(
//             'success' => false,
//             'message' => "Failed to delete {$repo_name}. Directory not found in themes or plugins."
//         ));
//     }
// }
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

    if(empty($github_url) || empty($pat)) {
        wp_send_json(array(
            'success' => false,
            'message' => "Missing GitHub URL or PAT.",
        ));
        return;
    }

    // Use the clone_github_repo function to handle the cloning
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'theme';

    // Decide the destination directory based on type
    $destination_directory = WP_CONTENT_DIR . '/themes'; // Default to themes
    if ($type === 'plugin') {
        $destination_directory = WP_CONTENT_DIR . '/plugins';
    }

    $clone_result = clone_github_repo($github_url, $pat, $destination_directory);

    if($clone_result['success']) {
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

function wp_github_clone_nvm_install() {
    check_ajax_referer('wp_github_clone_nonce', 'nonce');

    $repo_name = isset($_POST['repo']) ? sanitize_text_field($_POST['repo']) : '';

    if (empty($repo_name)) {
        wp_send_json(array(
            'success' => false,
            'message' => "Repository name not provided."
        ));
        return;
    }

    $repo_path = WP_CONTENT_DIR . '/themes/' . $repo_name;

    // Execute the nvm install command
    $output = shell_exec("cd {$repo_path} && nvm install 2>&1");

    if (strpos($output, 'Now using node') !== false) {
        wp_send_json(array(
            'success' => true,
            'message' => "Successfully ran nvm install for {$repo_name}",
            'details' => $output
        ));
    } else {
        wp_send_json(array(
            'success' => false,
            'message' => "Failed to run nvm install for {$repo_name}",
            'details' => $output
        ));
    }
}

add_action('wp_ajax_wp_github_clone_nvm_install', 'wp_github_clone_nvm_install');

function wp_github_clone_composer_install() {
    check_ajax_referer('wp_github_clone_nonce', 'nonce');

    $repo_name = isset($_POST['repo']) ? sanitize_text_field($_POST['repo']) : '';

    if (empty($repo_name)) {
        wp_send_json(array(
            'success' => false,
            'message' => "Repository name not provided."
        ));
        return;
    }

    $repo_path = WP_CONTENT_DIR . '/themes/' . $repo_name;

    // Execute the composer install command
    $output = shell_exec("cd {$repo_path} && composer install 2>&1");

    if (strpos($output, 'Generating autoload files') !== false) {
        wp_send_json(array(
            'success' => true,
            'message' => "Successfully ran composer install for {$repo_name}",
            'details' => $output
        ));
    } else {
        wp_send_json(array(
            'success' => false,
            'message' => "Failed to run composer install for {$repo_name}",
            'details' => $output
        ));
    }
}

add_action('wp_ajax_wp_github_clone_composer_install', 'wp_github_clone_composer_install');




