<?php

if (!class_exists('WP_GitHub_Clone')) {

    class WP_GitHub_Clone {

        public function __construct() {
            add_action('admin_menu', array($this, 'add_admin_menu'));
        }

        public function run() {
            if (is_admin()) {
                $this->admin_hooks();
            }
        }

        private function admin_hooks() {
            // Add hooks specific to the admin panel here
        }

        public function add_admin_menu() {
            add_options_page(
                'WP GitHub Clone Settings',
                'WP GitHub Clone',
                'manage_options',
                'clonewp',
                array($this, 'display_settings_page')
            );
        }

        public function display_settings_page() {
            require_once plugin_dir_path(dirname(__FILE__)) . 'admin/settings-page.php';
        }
    }
}
