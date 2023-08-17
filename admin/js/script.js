jQuery(document).ready(function($) {
    $('.pull-repo').on('click', function() {
        var repoName = $(this).data('repo-name');
        
        $.post(wpGithubClone.ajax_url, {
            action: 'wp_github_clone_pull',
            repo: repoName,
            nonce: wpGithubClone.nonce
        }, function(response) {
            if (response.details) {
                console.log("Git Pull Details:", response.details);
            }

            if (response.success) {
                alert("Successfully pulled from GitHub");
            } else {
                alert("Failed to pull. Check the console for details.");
            }
        });
    });

    $('.delete-repo').on('click', function() {
        var repoName = $(this).data('repo-name');

        if (confirm('Are you sure you want to delete the "' + repoName + '" repository? This action cannot be undone.')) {
            $.post(wpGithubClone.ajax_url, {
                action: 'wp_github_clone_delete',
                repo: repoName,
                nonce: wpGithubClone.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    console.error('Error while deleting:', response.message);
                }
            });
        }
    });    
    
});
