<?php 

// AJAX handler for the Pull action
function wp_github_clone_pull() {
    // Verifies nonce
    if (!check_ajax_referer('wp_github_clone_manual_pull', 'nonce', false)) {
        wp_send_json(array(
            'success' => false,
            'message' => 'Nonce verification failed.'
        ));
        return;
    }

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

    if (!is_dir($repo_path)) {
        wp_send_json(array(
            'success' => false,
            'message' => "Failed to pull changes. Directory does not exist."
        ));
        return;
    }

    // Check if the repository is on a branch
    $current_branch = trim(shell_exec("cd {$repo_path} && git branch --show-current"));
    if (empty($current_branch)) {
        wp_send_json(array(
            'success' => false,
            'message' => "Cannot pull changes. Repository is in a detached HEAD state.",
        ));
        return;
    }

    // Execute git pull for the current branch
    $output = shell_exec("cd {$repo_path} && git pull origin {$current_branch} 2>&1");

    // Check for success or failure
    if (strpos($output, 'Already up to date.') !== false || strpos($output, 'Fast-forward') !== false) {
        wp_send_json(array(
            'success' => true,
            'message' => "Successfully pulled {$repo_name} from branch {$current_branch}.",
            'details' => $output
        ));
    } else {
        wp_send_json(array(
            'success' => false,
            'message' => "Failed to pull changes from {$repo_name}.",
            'details' => $output
        ));
    }
}

add_action('wp_ajax_wp_github_clone_pull', 'wp_github_clone_pull');

function wp_github_clone_switch_branch_ajax_handler() {
    check_ajax_referer('wp_github_clone_nonce', 'nonce');

    $repo_name = sanitize_text_field($_POST['repo']);
    $branch_name = sanitize_text_field($_POST['branch']);

    if (empty($repo_name) || empty($branch_name)) {
        wp_send_json_error(['message' => 'Missing repository name or branch.']);
        return;
    }

    $repo_type = get_option("wp_github_clone_type_{$repo_name}", 'theme');
    $repo_path = ($repo_type === 'plugin') ? WP_CONTENT_DIR . '/plugins/' . $repo_name : WP_CONTENT_DIR . '/themes/' . $repo_name;

    if (!is_dir($repo_path)) {
        wp_send_json_error(['message' => 'Repository directory not found.']);
        return;
    }

    $output = ''; // Initialize the variable

    // Check if the branch is remote (starts with 'origin/')
    $is_remote_branch = strpos($branch_name, 'origin/') === 0;
    $local_branch_name = $is_remote_branch ? substr($branch_name, 7) : $branch_name; // Remove 'origin/' prefix for local branch

    // Switch to the branch
    if ($is_remote_branch) {
        // Switch to the local branch
        $output = shell_exec("cd " . escapeshellarg($repo_path) . " && git checkout " . escapeshellarg($branch_name) . " 2>&1");
    } else {
        // Switch to a local branch
        $output = shell_exec("cd " . escapeshellarg($repo_path) . " && git checkout " . escapeshellarg($local_branch_name) . " 2>&1");
    }

    // Check for success or failure
    if (strpos($output, 'Switched to branch') !== false || strpos($output, 'Already on') !== false || strpos($output, 'Branch') !== false) {
        wp_send_json_success([
            'message' => "Switched to branch {$local_branch_name}.",
            'details' => $output
        ]);
    } else {
        wp_send_json_error([
            'message' => "Failed to switch branch.",
            'details' => $output
        ]);
    }
}

add_action('wp_ajax_wp_github_clone_switch_branch', 'wp_github_clone_switch_branch_ajax_handler');


function wp_github_clone_admin_notices() {
    if (get_transient('wp_github_clone_pull_success')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Successfully pulled from GitHub', 'clonewp'); ?></p>
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
    global $wp_filesystem;

    // Initialize the WordPress filesystem
    if (empty($wp_filesystem)) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }

    if (!$wp_filesystem->is_dir($dir)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $filepath = $fileinfo->getRealPath();
        if ($fileinfo->isDir()) {
            if (!$wp_filesystem->rmdir($filepath)) {
                error_log("Failed to delete directory: " . $filepath);
            }
        } else {
            if (!$wp_filesystem->delete($filepath)) {
                error_log("Failed to delete file: " . $filepath);
            }
        }
    }

    if (!$wp_filesystem->rmdir($dir)) {
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

    // Clone the repository
    $clone_result = clone_github_repo($github_url, $pat, $destination_directory, $type, $repo_access_type);

    if ($clone_result['success']) {
        // Determine the repo name from the URL
        $repo_name = basename($github_url, '.git');
        $repo_path = $destination_directory . '/' . $repo_name;

        // Fetch only local branches
        $branch_output = shell_exec("cd {$repo_path} && git branch --format='%(refname:short)' 2>&1");
        $local_branches = array_filter(array_map('trim', explode("\n", $branch_output)));

        wp_send_json(array(
            'success' => true,
            'message' => "Successfully cloned {$github_url}",
            'repo_name' => $repo_name,
            'localBranches' => $local_branches,
        ));
    } else {
        wp_send_json(array(
            'success' => false,
            'message' => $clone_result['message'],
        ));
    }
}


add_action('wp_ajax_wp_github_clone_ajax', 'wp_github_clone_ajax');

function wp_github_clone_fetch_all_ajax_handler() {
    check_ajax_referer('wp_github_clone_nonce', 'nonce');

    $repo_name = sanitize_text_field($_POST['repo']);
    if (empty($repo_name)) {
        wp_send_json_error(['message' => 'Repository name is missing.']);
        return;
    }

    $repo_type = get_option("wp_github_clone_type_{$repo_name}", 'theme');
    $repo_path = ($repo_type === 'plugin') ? WP_CONTENT_DIR . '/plugins/' . $repo_name : WP_CONTENT_DIR . '/themes/' . $repo_name;

    if (!is_dir($repo_path)) {
        wp_send_json_error(['message' => 'Repository directory not found.']);
        return;
    }

    // Run `git fetch --all` to update remote tracking branches
    $fetch_output = shell_exec("cd " . escapeshellarg($repo_path) . " && git fetch --all 2>&1");
    // error_log("Fetch All Command Output for {$repo_name}: {$fetch_output}");

    if (strpos($fetch_output, 'Fetching') !== false || strpos($fetch_output, 'up to date') !== false) {
        // Get remote branches
        $remote_branches_output = shell_exec("cd " . escapeshellarg($repo_path) . " && git branch -r 2>&1");
        $remote_branches = array_filter(array_map('trim', explode("\n", $remote_branches_output)));
        // error_log("Remote Branches Output for {$repo_name}: {$remote_branches_output}");

        $tracking_errors = [];
        foreach ($remote_branches as $remote_branch) {
            if (preg_match('/^origin\/(.+)$/', $remote_branch, $matches) && strpos($remote_branch, '->') === false) {
                $branch_name = $matches[1];
                $track_output = shell_exec("cd " . escapeshellarg($repo_path) . " && git branch --track " . escapeshellarg($branch_name) . " origin/" . escapeshellarg($branch_name) . " 2>&1");
                // error_log("Tracking remote branch {$remote_branch} as local branch {$branch_name}: {$track_output}");
        
                // Check if tracking was successful
                if (strpos($track_output, 'Branch') === false && strpos($track_output, 'already exists') === false) {
                    $tracking_errors[] = "Failed to track branch {$branch_name}. Output: {$track_output}";
                }
            } else {
                error_log("Skipping symbolic reference or invalid branch: {$remote_branch}");
            }
        }
        

        // Log tracking errors if any
        if (!empty($tracking_errors)) {
            error_log("Tracking Errors for {$repo_name}: " . print_r($tracking_errors, true));
        }

        // Get updated local branches
        $local_branches_output = shell_exec("cd " . escapeshellarg($repo_path) . " && git branch 2>&1");
        $local_branches = array_filter(array_map('trim', explode("\n", $local_branches_output)));
        // error_log("Updated Local Branches for {$repo_name}: {$local_branches_output}");

        wp_send_json_success([
            'message' => "Successfully fetched and tracked all branches for {$repo_name}.",
            'details' => $fetch_output,
            'localBranches' => $local_branches, // Return updated local branches
            'trackingErrors' => $tracking_errors // Return tracking errors for debugging
        ]);
    } else {
        error_log("Error: Fetch command failed for {$repo_name}. Output: {$fetch_output}");
        wp_send_json_error([
            'message' => "Failed to fetch branches for {$repo_name}.",
            'details' => $fetch_output ?: 'No output from git.',
        ]);
    }
}




add_action('wp_ajax_wp_github_clone_fetch_all', 'wp_github_clone_fetch_all_ajax_handler');
