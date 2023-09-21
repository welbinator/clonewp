jQuery(document).ready(function($) {
    jQuery('.nvm-install').on('click', function() {
        var repoName = jQuery(this).data('repo-name');
    
        jQuery.ajax({
            url: wpGithubClone.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_github_clone_nvm_install',
                nonce: wpGithubClone.nonce,
                repo: repoName
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                } else {
                    alert('Error: ' + response.message);
                }
            }
        });
    });

    jQuery('.composer-install').on('click', function() {
        var repoName = jQuery(this).data('repo-name');
    
        jQuery.ajax({
            url: wpGithubClone.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_github_clone_composer_install',
                nonce: wpGithubClone.nonce,
                repo: repoName
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                } else {
                    alert('Error: ' + response.message);
                }
            }
        });
    });
    // When the dropdown value changes
    $('#clone-type').change(function() {
        var selection = $(this).val();
        
        // If the user selected either 'plugin' or 'theme'
        if (selection === 'plugin' || selection === 'theme') {
            // Show the fields and the button
            $('#github-url-wrapper, #github-pat-wrapper, #github-button-wrapper').show();
        } 
    });

    // Event listener for the Pull button
    $(document).on('click', '.pull-repo', function(e) {
        e.preventDefault();

        var repoName = $(this).data('repo-name');
        console.log("Repo Name:", repoName);

        console.log({
            action: 'wp_github_clone_pull',
            repo: repoName,
            nonce: wpGithubClone.nonce
        });
        
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
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Request failed: " + textStatus + " - " + errorThrown);
                alert('An unexpected error occurred. Check the console for more details.');
            }
        });
    });

    // Event listener for the Delete button
    $(document).on('click', '.delete-repo', function(e) {
        console.log("Delete button clicked for repo:", $(this).data('repo-name'));
        e.preventDefault();

        if (!confirm('Are you sure you want to delete this repository? This action cannot be undone.')) {
            return;
        }

        var repoName = $(this).data('repo-name');
        var $repoListItem = $(this).closest('li');

        // Send AJAX request to WordPress to delete the repository
        $.ajax({
            type: 'POST',
            url: wpGithubClone.ajax_url,
            data: {
                action: 'test_github_clone_delete',
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

        var cloneType = $('#clone-type').val();
        var githubUrl = $('#github-url').val();
        var githubPat = $('#github-pat').val();

        $.ajax({
            type: 'POST',
            url: wpGithubClone.ajax_url,
            data: {
                action: 'wp_github_clone',
                clone_type: cloneType, // sending the selected type (theme or plugin)
                github_url: githubUrl,
                github_pat: githubPat,
                nonce: wpGithubClone.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);

                    var newRepoHtml = `
                        <li>
                            ${response.repo_name} 
                            <button class="pull-repo" data-repo-name="${response.repo_name}">Pull</button>
                            <button class="delete-repo" data-repo-name="${response.repo_name}">Delete</button>
                        </li>
                    `;

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
        console.log("AJAX URL:", wpGithubClone.ajax_url);

    });

    document.querySelector('input[name="repo_visibility"]').addEventListener('change', function() {
        var patField = document.querySelector('#PAT_FIELD_ID'); // Replace with the actual ID of your PAT input field
        if (this.value === 'private') {
            patField.required = true;
        } else {
            patField.required = false;
        }
    });
    

    $('.nav-tab').click(function(e) {
        e.preventDefault();

        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.tab-content').hide();
        $($(this).attr('href')).show();
    });



});
