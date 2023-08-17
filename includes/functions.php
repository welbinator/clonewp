<?php

function clone_github_repo($repo_url) {
    // Extract repo name from the URL
    $repo_parts = explode('/', rtrim($repo_url, '/'));  // rtrim to remove trailing slashes, if any
    $repo_name = end($repo_parts);

    // Prepare the directory path
    $theme_dir = WP_CONTENT_DIR . '/themes/' . $repo_name;
    
    // Check if the directory already exists
    if (is_dir($theme_dir)) {
        return array('success' => false, 'message' => "The directory for repository {$repo_name} already exists.");
    }

    // Concatenate the token with the repo_url
    $token = get_option('wp_github_clone_token');
    $authenticated_repo_url = str_replace('https://', 'https://' . $token . '@', $repo_url);
    $concatenated_url = str_replace("https://", "https://{$token}@", $repo_url);

    // Log the concatenated URL for debugging purposes
    // uncomment out the next line if you would like to log the URL to the debug.log file for debugging purposes
    // error_log("Constructed URL: " . $concatenated_url);

    $command = 'git clone ' . escapeshellarg($concatenated_url) . ' ' . escapeshellarg($theme_dir) . ' 2>&1';
    $output = shell_exec($command . ' 2>&1');
    
    if(strpos($output, 'fatal:') !== false) {
        wp_github_clone_log($output);
        return array('success' => false, 'message' => 'Failed to clone repository.');
    }
    
    return array('success' => true, 'message' => 'Repository cloned successfully.');
}




function pull_repo_changes($local_path) {
    $command = 'git -C ' . escapeshellarg($local_path) . ' pull';
    $output = shell_exec($command . ' 2>&1');

    if(strpos($output, 'fatal:') !== false) {
        wp_github_clone_log($output);
        return array('success' => false, 'message' => 'Failed to pull changes.');
    }

    return array('success' => true, 'message' => 'Changes pulled successfully.');
}

function delete_local_repo($local_path) {
    $command = 'rm -rf ' . escapeshellarg($local_path);
    $output = shell_exec($command . ' 2>&1');

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
    $dir = new DirectoryIterator(WP_CONTENT_DIR . '/themes');
    foreach ($dir as $fileinfo) {
        if ($fileinfo->isDir() && !$fileinfo->isDot()) {
            if (file_exists($fileinfo->getPathname() . '/.git')) {
                $repos[] = $fileinfo->getFilename();
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
