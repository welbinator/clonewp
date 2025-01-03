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

    <div class="github-type-wrapper">
        <label for="clone-type">Type:</label>
        <select id="clone-type" name="clone-type">
            <option value="select" selected>Make a selection</option>
            <option value="theme">Theme</option>
            <option value="plugin">Plugin</option>
        </select>
    </div><!-- github-type-wrapper -->
    <div id="github-privacy-wrapper">
        <label>
            <input type="radio" name="repo_visibility" value="public"> Public
        </label>
        <label>
            <input type="radio" name="repo_visibility" value="private"> Private
        </label>
    </div><!-- github-privacy-wrapper -->

    <div id="github-url-wrapper">
        <label for="github-url"><strong>GitHub Repository URL:</strong></label>
        <input type="text" id="github-url" name="github-url">
    </div>

    <div id="github-pat-wrapper">
        <label for="github-pat"><strong>GitHub Personal Access Token:</strong></label>
        <input type="password" id="github-pat" name="github-pat"> <!-- Use password type to hide token from view -->
    </div>

    <div id="github-button-wrapper">
        <input type="submit" value="Clone Repository" class="button-primary" id="clone-button">
    </div>
</form>

    </div><!-- tab-content -->

    <div class="tab-content" id="tab-repos" style="display:none;">
    <?php if (!empty($cloned_repositories)): ?>
    <h2>Cloned Repositories</h2>
    <ul>
    <?php foreach ($cloned_repositories as $repo_name): ?>
        <?php 
        // Get the current branch
        $repo_type = get_option("wp_github_clone_type_{$repo_name}", 'theme');
        $repo_path = ($repo_type === 'plugin') ? WP_CONTENT_DIR . '/plugins/' . $repo_name : WP_CONTENT_DIR . '/themes/' . $repo_name;
        $current_branch = shell_exec("cd {$repo_path} && git branch --show-current");
        $branches = shell_exec("cd {$repo_path} && git branch");
        $branch_list = array_filter(array_map('trim', explode("\n", $branches)));
        ?>
        <li>
            <strong><?php echo esc_html($repo_name); ?></strong> 
            <br> Current Branch: <span class="current-branch"><?php echo esc_html($current_branch); ?></span>
            <br> Switch Branch:
            <select class="branch-dropdown" data-repo-name="<?php echo esc_attr($repo_name); ?>">
                <?php foreach ($branch_list as $branch): ?>
                <option value="<?php echo esc_attr($branch); ?>"><?php echo esc_html($branch); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="switch-branch" data-repo-name="<?php echo esc_attr($repo_name); ?>">Switch</button>
            <button class="fetch-all" data-repo-name="<?php echo esc_attr($repo_name); ?>">Fetch All</button>
            <button class="pull-repo" data-repo-name="<?php echo esc_attr($repo_name); ?>">Pull</button>
            <button class="delete-repo" data-repo-name="<?php echo esc_attr($repo_name); ?>">Delete</button>
        </li>
    <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div><!-- tab-content -->
</div> <!-- wrap -->


