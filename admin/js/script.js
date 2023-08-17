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
                console.log(response); // Log the full response
                if (response.success) {
                    alert(response.message + "\n\nDetails:\n" + response.details);
                } else {
                    alert(response.message + "\n\nError Details:\n" + response.details);
                }
            }
            ,
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Request failed: " + textStatus + " - " + errorThrown);
                alert('An unexpected error occurred. Check the console for more details.');
            }
            
        });
    });

    // Event listener for the Delete button
    $(document).on('click', '.delete-repo', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to delete this repository? This action cannot be undone.')) {
            return;
        }

        var repoName = $(this).data('repo-name');
        var $repoListItem = $(this).closest('li'); // This selects the parent list item

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
                    $repoListItem.remove();  // Remove the repository list item from the page
                } else {
                    alert(response.message);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Request failed: " + textStatus + " - " + errorThrown);
                alert('An unexpected error occurred. Check the console for more details.');
            }
            
        });
    });

    // Handle form submissions for cloning repositories
    $('#tab-clone form').submit(function(e) {
        e.preventDefault();

        var githubUrl = $('#github-url').val();
        var githubPat = $('#github-pat').val();

        console.log("GitHub URL: ", githubUrl);
console.log("GitHub PAT: ", githubPat);
console.log("AJAX URL: ", wpGithubClone.ajax_url);
console.log("Nonce: ", wpGithubClone.nonce);


        $.ajax({
            type: 'POST',
            url: wpGithubClone.ajax_url,
            data: {
                action: 'wp_github_clone',
                github_url: githubUrl,
                github_pat: githubPat,
                nonce: wpGithubClone.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);

                    // Append new repo to the list
                    var newRepoHtml = `
                        <li>
                            ${response.repo_name} 
                            <button class="pull-repo" data-repo-name="${response.repo_name}">Pull</button>
                            <button class="delete-repo" data-repo-name="${response.repo_name}">Delete</button>
                        </li>
                    `;

                    // If the ul doesn't exist, we create it.
                    if ($('#tab-repos ul').length === 0) {
                        $('#tab-repos').append('<h2>Cloned Repositories</h2><ul></ul>');
                    }

                    $('#tab-repos ul').append(newRepoHtml);

                } else {
                    alert(response.message);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Request failed: " + textStatus + " - " + errorThrown);
                alert('An unexpected error occurred. Check the console for more details.');
            }
            
        });
    });

    $('.nav-tab').click(function(e) {
        e.preventDefault();
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.tab-content').hide();
        $($(this).attr('href')).show();
    });

});
