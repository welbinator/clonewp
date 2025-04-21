<?php
/*
Plugin Name: CloneWP
Plugin URI: https://yourwebsite.com/plugin
Description: A plugin to clone and display GitHub repositories.
Version: 1.1.0
Author: Your Name
Author URI: https://yourwebsite.com/
License: GPL2
Text Domain: clonewp
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define( 'CLONEWP_VERSION', '1.1.0' );
define( 'CLONEWP_PATH', plugin_dir_path( __FILE__ ) );
define( 'CLONEWP_URL', plugin_dir_url( __FILE__ ) );
define( 'CLONEWP_MIN_WP_VERSION', '5.8' );
define( 'CLONEWP_MIN_PHP_VERSION', '7.4' );

if ( file_exists( CLONEWP_PATH . 'github-update.php' ) ) {
	include_once CLONEWP_PATH . 'github-update.php';
}

require_once plugin_dir_path(__FILE__) . 'includes/class-clonewp.php';
require_once plugin_dir_path(__FILE__) . 'includes/functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-handlers.php';

function run_wp_github_clone() {
    $plugin = new WP_GitHub_Clone();
    $plugin->run();
}

run_wp_github_clone();

// enqueue scripts
function wp_github_clone_enqueue_scripts($hook) {
    if ($hook != 'settings_page_clonewp') {
        return;
    }

    wp_enqueue_script('clonewp-script', plugin_dir_url(__FILE__) . 'admin/js/script.js', array('jquery'), '1.1.0', true);
    wp_localize_script('clonewp-script', 'wpGithubClone', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wp_github_clone_nonce'),
        'manual_pull_nonce' => wp_create_nonce('wp_github_clone_manual_pull')
    ));
}
add_action('admin_enqueue_scripts', 'wp_github_clone_enqueue_scripts');


// enqueue styles
function wp_github_clone_enqueue_admin_styles($hook) {
    if ($hook != 'settings_page_clonewp') {
        return;
    }

    wp_enqueue_style('clonewp-admin-style', plugin_dir_url(__FILE__) . 'admin/css/style.css', array(), '1.1.0');
}
add_action('admin_enqueue_scripts', 'wp_github_clone_enqueue_admin_styles');












