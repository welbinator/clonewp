jQuery(document).ready(function($) {
    // Event listener for the Pull button
    $(document).on('click', '.pull-repo', function(e) {
        e.preventDefault();

        var repoName = $(this).data('repo-name');
        console.log("Repo Name:", repoName);

        // Send AJAX request to WordPress to initiate the git pull
        $.ajax({
            type: 'POST',
            url: wpGithubClone.ajax_url,
            data: {
                action: 'wp_github_clone_pull',
                repo: repoName,
                nonce: wpGithubClone.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message + "\n\nDetails:\n" + response.details);
                    // Optionally, you can add more UI changes here, e.g., refreshing the page
                } else {
                    alert(response.message + "\n\nError Details:\n" + response.details);
                }
            },
            error: function() {
                alert('An unexpected error occurred.');
            }
        });
    });

    // Event listener for the Delete button
    $(document).on('click', '.delete-repo', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to delete this repository? This action cannot be undone.')) {
            return;
        }

        var repoName = $(this).data('repo');

        // Send AJAX request to WordPress to delete the repository
        $.ajax({
            type: 'POST',
            url: wpGithubClone.ajax_url,
            data: {
                action: 'wp_github_clone_delete',
                repo: repoName,
                nonce: wpGithubClone.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    // Optionally, you can add more UI changes here, e.g., removing the repository from the list or refreshing the page
                } else {
                    alert(response.message);
                }
            },
            error: function() {
                alert('An unexpected error occurred.');
            }
        });
    });
});
