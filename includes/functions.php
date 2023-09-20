<?php

/**
 * Clone a GitHub repo.
 *
 * @param string $github_url           The GitHub repository URL.
 * @param string $pat                  The personal access token.
 * @param string $destination_directory The destination directory to clone the repo into.
 * @return array                       An array containing success status and a message.
 */
function clone_github_repo($github_url, $pat, $destination_directory) {
    // Validate the GitHub URL
    if (!is_valid_github_url($github_url)) {
        return array(
            'success' => false,
            'message' => 'Invalid GitHub URL.'
        );
    }

    // Extract the repo name from the GitHub URL
    $parts = explode('/', rtrim($github_url, '/'));
    $repo_name = end($parts);
    
    $full_destination_path = rtrim($destination_directory, '/') . '/' . $repo_name;
    


    // Check if the directory already exists
    if (is_dir($full_destination_path)) {
        return array(
            'success' => false,
            'message' => "Repository {$repo_name} already exists in the destination directory."
        );
    }

    // Run the git clone command
    putenv("GIT_TERMINAL_PROMPT=0"); // This prevents git from asking for credentials
    putenv("GIT_SSL_NO_VERIFY=true"); // Bypass SSL verification, might not be needed based on your setup

    $output = shell_exec("git clone {$github_url} {$full_destination_path} 2>&1");
    


    // If the clone was successful
    if (strpos($output, 'Checking out files') !== false || strpos($output, 'Cloning into') !== false) {
        // Store the personal access token for this repo in the WordPress options table
        if($pat) {
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
    $path_parts = explode('/', rtrim($local_path, '/'));
    $repo_name = end($path_parts);
    $pat = get_option('wp_github_clone_token_' . $repo_name);
    
    // if PAT isn't found, log it but don't return an error yet
    if (!$pat) {
        wp_github_clone_log("No Personal Access Token found for {$repo_name}. Trying to pull without PAT.");
    }

    $command = 'git -C ' . escapeshellarg($local_path);
    if ($pat) {
        $command .= ' -c "user.password=' . escapeshellarg($pat) . '"';
    }
    $command .= ' pull 2>&1';
    $output = shell_exec($command);

    if(strpos($output, 'fatal:') !== false) {
        wp_github_clone_log($output);
        return array('success' => false, 'message' => 'Failed to pull changes.', 'details' => $output);
    }

    return array('success' => true, 'message' => 'Changes pulled successfully.', 'details' => $output);
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

    $log_file = get_theme_root() . "/github-clone-log.txt";

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
//clone ajax handler
function wp_github_clone_ajax_handler() {
    
    check_ajax_referer('wp_github_clone_nonce', 'nonce');
    
    $type = isset($_POST['clone_type']) ? sanitize_text_field($_POST['clone_type']) : 'theme';
    error_log('type is ' . $type);
    
    // Determine the correct destination directory based on the type
    $destination_dir = $type === 'plugin' ? WP_CONTENT_DIR . '/plugins/' : WP_CONTENT_DIR . '/themes/';
    
    if (isset($_POST['github_url']) && isset($_POST['github_pat'])) {
        $response = clone_github_repo($_POST['github_url'], $_POST['github_pat'], $destination_dir);
        wp_send_json($response);
    } else {
        wp_send_json_error(array('message' => 'Missing GitHub URL or PAT.'));
    }
}

add_action('wp_ajax_wp_github_clone', 'wp_github_clone_ajax_handler');

// pull ajax handler
function wp_github_clone_pull_ajax_handler() {
    

    check_ajax_referer('wp_github_clone_nonce', 'nonce');
    
    $type = isset($_POST['clone-type']) ? sanitize_text_field($_POST['clone-type']) : 'theme';
    

    

    $destination_dir = $type === 'plugin' ? WP_CONTENT_DIR . '/plugins/' : WP_CONTENT_DIR . '/themes/';

    if (isset($_POST['repo'])) {
        $local_path = $destination_dir . $_POST['repo'];
        $response = pull_repo_changes($local_path);
        wp_send_json($response);
    } else {
        wp_send_json_error(array('message' => 'Missing repository name.'));
    }
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
