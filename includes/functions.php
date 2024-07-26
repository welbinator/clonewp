<?php

/**
 * Clone a GitHub repo.
 *
 * @param string $github_url           The GitHub repository URL.
 * @param string $pat                  The personal access token.
 * @param string $destination_directory The destination directory to clone the repo into.
 * @return array                       An array containing success status and a message.
 */
function clone_github_repo($github_url, $pat, $destination_directory, $repo_type, $repo_access_type) {
    // Validate the GitHub URL
    if (!is_valid_github_url($github_url)) {
        return array(
            'success' => false,
            'message' => 'Invalid GitHub URL.'
        );
    }

    // Extract the repo name from the GitHub URL
    $parts = explode('/', rtrim($github_url, '/'));
    $repo_name_with_git = end($parts);
    $repo_name = preg_replace('/\.git$/', '', $repo_name_with_git); // Remove .git suffix if present

    $full_destination_path = rtrim($destination_directory, '/') . '/' . $repo_name;

    // Check if the directory already exists
    if (is_dir($full_destination_path)) {
        return array(
            'success' => false,
            'message' => "Repository {$repo_name} already exists in the destination directory."
        );
    }

    // Add PAT to the URL if the repository is private
    if ($repo_access_type === 'private' && !empty($pat)) {
        // Construct the URL with the PAT
        $parsed_url = parse_url($github_url);
        $github_url = $parsed_url['scheme'] . '://' . urlencode($pat) . ':x-oauth-basic@' . $parsed_url['host'] . $parsed_url['path'];
    }

    // Construct the git clone command
    $command = "git clone {$github_url} {$full_destination_path} 2>&1";

    // Run the git clone command
    putenv("GIT_TERMINAL_PROMPT=0"); // This prevents git from asking for credentials
    putenv("GIT_SSL_NO_VERIFY=true"); // Bypass SSL verification, might not be needed based on your setup

    $output = shell_exec($command);

    // If the clone was successful
    if (strpos($output, 'Checking out files') !== false || strpos($output, 'Cloning into') !== false) {
        // Store the repository access type in the database
        update_option("wp_github_clone_repo_type_{$repo_name}", $repo_type);
        update_option("wp_github_clone_access_type_{$repo_name}", $repo_access_type);
        
        // If the repo is private, store the PAT
        if ($repo_access_type === 'private' && $pat) {
            update_option("wp_github_clone_token_{$repo_name}", $pat);
        }

        return array(
            'success' => true,
            'message' => "Successfully cloned {$repo_name} into the specified directory.",
            'repo_name' => $repo_name
        );
    } else {
        return array(
            'success' => false,
            'message' => "Failed to clone {$github_url}.",
            'details' => $output // Providing the git error message can be helpful for debugging.
        );
    }
}





function pull_repo_changes($local_path) {
    // Extract the repo name from the local path
    $parts = explode('/', rtrim($local_path, '/'));
    $repo_name = end($parts);

    // Check if the repository is private
    $repo_access_type = get_option("wp_github_clone_access_type_{$repo_name}", 'public');

    // If the repository is private, retrieve the stored PAT
    $pat = '';
    if ($repo_access_type === 'private') {
        $pat = get_option("wp_github_clone_token_{$repo_name}", '');
        if (!$pat) {
            return array(
                'success' => false,
                'message' => "No Personal Access Token found for {$repo_name}."
            );
        }
    }

    // Set up the git environment
    putenv("GIT_TERMINAL_PROMPT=0"); // Prevent git from asking for credentials
    if ($pat) {
        putenv("GIT_ASKPASS=true"); // Use the PAT for authentication
        putenv("GIT_USERNAME={$pat}"); // Use the PAT as the username
    }

    // Execute the git pull command
    $output = shell_exec("cd {$local_path} && git pull 2>&1");

    // Check the output to determine if the pull was successful
    if (strpos($output, 'Already up to date.') !== false || strpos($output, 'Fast-forward') !== false) {
        return array(
            'success' => true,
            'message' => "Successfully pulled changes for {$repo_name}.",
            'details' => $output
        );
    } else {
        return array(
            'success' => false,
            'message' => "Failed to pull changes for {$repo_name}.",
            'details' => $output
        );
    }
}




function delete_local_repo($local_path) {
    $path_parts = explode('/', rtrim($local_path, '/'));
    $repo_name = end($path_parts);

    // Delete the PAT associated with the repo.
    delete_option('wp_github_clone_token_' . $repo_name);

    $command = 'rm -rf ' . escapeshellarg($local_path);
    $output = shell_exec($command);

    if(!empty($output)) {
        wp_github_clone_log($output);
        return array('success' => false, 'message' => 'Failed to delete repository.');
    }

    return array('success' => true, 'message' => 'Repository deleted successfully.');
}

function wp_github_clone_log($message) {
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }

    $log_file = WP_CONTENT_DIR . "/github-clone-log.txt";

    if ($handle = fopen($log_file, 'a')) {
        fwrite($handle, date('Y-m-d H:i:s') . " " . $message . "\n");
        fclose($handle);
    }
}


function get_cloned_repositories() {
    $repos = [];

    // Directories to search for cloned repositories
    $directories = [
        WP_CONTENT_DIR . '/themes',
        WP_CONTENT_DIR . '/plugins'
    ];

    foreach ($directories as $directory) {
        if ($handle = opendir($directory)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && $entry != "wp-github-clone" && is_dir($directory . '/' . $entry) && is_dir($directory . '/' . $entry . '/.git')) {
                    $repos[] = $entry;
                }
                
            }
            closedir($handle);
        }
    }

    // Remove duplicates, if any
    $repos = array_unique($repos, SORT_STRING);
    


    return $repos;
}


//clone ajax handler
function wp_github_clone_ajax_handler() {
    check_ajax_referer('wp_github_clone_nonce', 'nonce');
    
    $github_url = isset($_POST['github_url']) ? sanitize_text_field($_POST['github_url']) : '';
    $pat = isset($_POST['github_pat']) ? sanitize_text_field($_POST['github_pat']) : '';
    $type = isset($_POST['clone_type']) ? sanitize_text_field($_POST['clone_type']) : 'theme';
    $repo_access_type = isset($_POST['repo_visibility']) ? sanitize_text_field($_POST['repo_visibility']) : 'public';
    
    error_log('type is ' . $type);
    error_log('repo_access_type is ' . $repo_access_type);
    
    // Determine the correct destination directory based on the type
    $destination_dir = $type === 'plugin' ? WP_CONTENT_DIR . '/plugins/' : WP_CONTENT_DIR . '/themes/';
    
    if (!empty($github_url) && ($repo_access_type !== 'private' || !empty($pat))) {
        $response = clone_github_repo($github_url, $pat, $destination_dir, $type, $repo_access_type);
        wp_send_json($response);
    } else {
        wp_send_json_error(array('message' => 'Missing GitHub URL or PAT.'));
    }
}
add_action('wp_ajax_wp_github_clone', 'wp_github_clone_ajax_handler');



// pull ajax handler
function wp_github_clone_pull_ajax_handler() {
    check_ajax_referer('wp_github_clone_nonce', 'nonce');

    if (!isset($_POST['repo']) || empty($_POST['repo'])) {
        wp_send_json_error(array('message' => 'Missing repository name.'));
        return;
    }

    $repo_name = sanitize_text_field($_POST['repo']);

    // Retrieve the repository type from the database
    $repo_type = get_option("wp_github_clone_type_{$repo_name}", 'theme'); // Default to 'theme' if not found

    // Determine the path based on the repository type
    $local_path = ($repo_type === 'plugin') ? WP_CONTENT_DIR . '/plugins/' . $repo_name : WP_CONTENT_DIR . '/themes/' . $repo_name;

    $response = pull_repo_changes($local_path);
    wp_send_json($response);
}

add_action('wp_ajax_wp_github_clone_pull', 'wp_github_clone_pull_ajax_handler');

// delete ajax handler
function wp_github_clone_delete_ajax_handler() {
    check_ajax_referer('wp_github_clone_nonce', 'nonce');
    
    $type = isset($_POST['clone-type']) ? $_POST['clone-type'] : 'theme';
    $destination_dir = $type === 'plugin' ? WP_CONTENT_DIR . '/plugins/' : WP_CONTENT_DIR . '/themes/';

    if (isset($_POST['repo'])) {
        $local_path = $destination_dir . $_POST['repo'];
        $response = delete_local_repo($local_path);
        wp_send_json($response);
    } else {
        wp_send_json_error(array('message' => 'Missing repository name.'));
    }
}
add_action('wp_ajax_wp_github_clone_delete', 'wp_github_clone_delete_ajax_handler');


add_action('init', function() {
    if (isset($_GET['manual_pull'])) {
        wp_github_clone_pull();
        exit;
    }
});

function display_repo_access_type($atts) {
    $repo_name = $atts['repo_name'];
    $repo_access_type = get_option("wp_github_clone_access_type_{$repo_name}", 'Not Found');
    return "Access type for {$repo_name}: {$repo_access_type}";
}
add_shortcode('display_access_type', 'display_repo_access_type');

