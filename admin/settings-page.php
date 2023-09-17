<?php
if (!current_user_can('manage_options')) {
    return;
}

$error_message = '';
$success_message = '';

// Retrieve the list of cloned repositories and their PATs.
$cloned_repositories = get_cloned_repositories();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="#tab-clone" class="nav-tab nav-tab-active">Clone</a>
        <a href="#tab-repos" class="nav-tab">Repos</a>
    </h2>

    <div class="tab-content" id="tab-clone">
    <h3>Clone a new Repository from GitHub</h3>
    
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

    <form method="post" id="github-clone-form">
    <?php wp_nonce_field('wp_github_clone_nonce'); ?>

        <label for="clone-type">Type:</label>
        <select id="clone-type" name="clone-type">
            <option value="select" selected>Make a selection</option>
            <option value="theme">Theme</option>
            <option value="plugin">Plugin</option>
        </select>

        <br><br>
        <div id="github-url-wrapper">
            <label for="github-url">GitHub Repository URL:</label>
            <input type="text" id="github-url" name="github-url">
        </div>
        <br><br>

        <div id="github-pat-wrapper">
            <label for="github-pat">GitHub Personal Access Token:</label>
            <input type="password" id="github-pat" name="github-pat"> <!-- Use password type to hide token from view -->
        </div>
        <br><br>

        <div id="github-button-wrapper">
            <input type="submit" value="Clone Repository" class="button-primary" id="clone-button">
        </div>
    </form>
    </div>

    <div class="tab-content" id="tab-repos" style="display:none;">
    <!-- Display list of cloned repositories with Pull and Delete buttons -->
    <?php if (!empty($cloned_repositories)): ?>
    <h2>Cloned Repositories</h2>
    <ul>
        <?php foreach ($cloned_repositories as $repo_name): ?>
            <li>
                <?php echo esc_html($repo_name); ?>
                <button class="pull-repo" data-repo-name="<?php echo esc_attr($repo_name); ?>">Pull</button>
                <button class="delete-repo" data-repo-name="<?php echo esc_attr($repo_name); ?>">Delete</button>
                <button class="nvm-install" data-repo-name="<?php echo esc_attr($repo_name); ?>">nvm install</button>
                <button class="composer-install" data-repo-name="<?php echo esc_attr($repo_name); ?>">composer install</button>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

</div>
