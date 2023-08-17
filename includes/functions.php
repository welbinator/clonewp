<?php

function clone_github_repo($repo_url, $pat) {
    // Extract repo name from the URL
    $repo_parts = explode('/', rtrim($repo_url, '/')); 
    $repo_name = end($repo_parts);
    $theme_dir = WP_CONTENT_DIR . '/themes/' . $repo_name;
    
    if (is_dir($theme_dir)) {
        return array('success' => false, 'message' => "The directory for repository {$repo_name} already exists.");
    }

    $authenticated_repo_url = str_replace('https://', 'https://' . $pat . '@', $repo_url);
    $command = 'git clone ' . escapeshellarg($authenticated_repo_url) . ' ' . escapeshellarg($theme_dir) . ' 2>&1';
    $output = shell_exec($command);

    if(strpos($output, 'fatal:') !== false) {
        wp_github_clone_log($output);
        return array('success' => false, 'message' => 'Failed to clone repository.');
    }

    // Save the PAT associated with the repo.
    update_option('wp_github_clone_token_' . $repo_name, $pat);
    
    return array('success' => true, 'message' => 'Repository cloned successfully.');
}

function pull_repo_changes($local_path) {
    $path_parts = explode('/', rtrim($local_path, '/'));
    $repo_name = end($path_parts);
    $pat = get_option('wp_github_clone_token_' . $repo_name);
    
    if (!$pat) {
        return array('success' => false, 'message' => 'Personal Access Token not found.');
    }

    $command = 'git -C ' . escapeshellarg($local_path) . ' -c "user.password=' . escapeshellarg($pat) . '" pull 2>&1';
    $output = shell_exec($command);

    if(strpos($output, 'fatal:') !== false) {
        wp_github_clone_log($output);
        return array('success' => false, 'message' => 'Failed to pull changes.');
    }

    return array('success' => true, 'message' => 'Changes pulled successfully.');
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
    $stored_repos_with_pat = get_option('wp_github_clone_repos', []); // Retrieve saved repositories with PATs

    $dir = new DirectoryIterator(WP_CONTENT_DIR . '/themes');
    foreach ($dir as $fileinfo) {
        if ($fileinfo->isDir() && !$fileinfo->isDot()) {
            if (file_exists($fileinfo->getPathname() . '/.git')) {
                $repo_name = $fileinfo->getFilename();
                $repos[$repo_name] = isset($stored_repos_with_pat[$repo_name]) ? $stored_repos_with_pat[$repo_name] : '';
            }
        }
    }

    return $repos;
}


function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object !== "." && $object !== "..") {
                if (is_dir($dir . "/" . $object))
                    rrmdir($dir . "/" . $object);
                else
                    unlink($dir . "/" . $object);
            }
        }
        rmdir($dir);
    }
}
