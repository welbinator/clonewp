<?php

if (!current_user_can('manage_options')) {
    return;
}

$error_message = '';
$success_message = '';
$cloned_repositories = get_cloned_repositories();

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle GitHub Access Token submission
    if (isset($_POST['save-token'])) {
        if (!empty($_POST['github-access-token'])) {
            $token = sanitize_text_field($_POST['github-access-token']);
            update_option('wp_github_clone_token', $token);
            $success_message = "Access token saved successfully.";
        } else {
            $error_message = 'Please enter a valid GitHub access token.';
        }
    }

    // Handle GitHub URL submission
    elseif (isset($_POST['github-url'])) {
        $github_url = sanitize_text_field($_POST['github-url']);
        
        if (!is_valid_github_url($github_url)) {
            $error_message = 'Invalid GitHub URL provided.';
        } else {
            $result = clone_github_repo($github_url);
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
        }
    }
}

?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Display any messages -->
    <?php if (!empty($error_message)): ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
        <div class="notice notice-success">
            <p><?php echo esc_html($success_message); ?></p>
        </div>
    <?php endif; ?>

    <!-- GitHub Access Token Form -->
    <form method="post">
        <label for="github-access-token">GitHub Access Token:</label>
        <input type="password" id="github-access-token" name="github-access-token" value="<?php echo esc_attr(get_option('wp_github_clone_token')); ?>">
        <input type="submit" name="save-token" value="Save Token" class="button-primary">
    </form>

    <hr>

    <!-- GitHub URL Form -->
    <form method="post">
        <label for="github-url">GitHub Repository URL:</label>
        <input type="text" id="github-url" name="github-url">
        <input type="submit" value="Clone Repository" class="button-primary">
    </form>

    <!-- Display list of cloned repositories with Pull and Delete buttons -->
</div>
<!-- Display list of cloned repositories with Pull and Delete buttons -->
<?php if (!empty($cloned_repositories)): ?>
        <h2>Cloned Repositories</h2>
        <ul>
            <?php foreach ($cloned_repositories as $repo): ?>
                <li>
                    <?php echo esc_html($repo); ?>
                    <button class="pull-repo" data-repo-name="<?php echo esc_attr($repo); ?>">Pull</button>
                    <button class="delete-repo" data-repo-name="<?php echo esc_attr($repo); ?>">Delete</button>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
