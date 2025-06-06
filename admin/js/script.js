jQuery(document).ready(function($) {
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
    $(document).on('click', '.pull-repo', function (e) {
        e.preventDefault();

        var repoName = $(this).data('repo-name');

        // Send AJAX request to WordPress to initiate the git pull
        $.ajax({
            type: 'POST',
            url: wpGithubClone.ajax_url,
            data: {
                action: 'wp_github_clone_pull',
                repo: repoName,
                nonce: wpGithubClone.manual_pull_nonce // Use the localized nonce
            },
            success: function (response) {
                console.log(response); // Debug the response structure

                if (response.success) {
                    // Accessing message and details under response.data
                    alert("Success: " + response.data.message + "\n\nDetails:\n" + response.data.details);
                } else {
                    alert("Error: " + response.data.message + "\n\nDetails:\n" + response.data.details);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("Request failed: " + textStatus + " - " + errorThrown);
                alert("An unexpected error occurred. Check the console for more details.");
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
        var repoVisibility = $('input[name="repo_visibility"]:checked').val();

        $.ajax({
            type: 'POST',
            url: wpGithubClone.ajax_url,
            data: {
                action: 'wp_github_clone',
                clone_type: cloneType, // sending the selected type (theme or plugin)
                github_url: githubUrl,
                github_pat: githubPat,
                repo_visibility: repoVisibility, // sending the visibility (public or private)
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
        var patField = document.querySelector('#github-pat'); // Corrected ID of your PAT input field
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

     // When the type dropdown changes
     $('#clone-type').on('change', function() {
        if ($(this).val() === 'theme' || $(this).val() === 'plugin') {
            $('#github-privacy-wrapper').css('display', 'flex');
            $('#github-url-wrapper, #github-pat-wrapper, #github-button-wrapper').hide();
        } else {
            $('.github-privacy-wrapper, #github-url-wrapper, #github-pat-wrapper, #github-button-wrapper').hide();
        }
    });

    // When the privacy radio buttons change
    $('input[name="repo_visibility"]').on('change', function() {
        if ($(this).val() === 'public' || $(this).val() === 'private') {
            $('#github-url-wrapper, #github-button-wrapper').css('display', 'flex');
            if ($(this).val() === 'private') {
                $('#github-pat-wrapper').css('display', 'flex');
            } else {
                $('#github-pat-wrapper').hide();
            }
        } else {
            $('#github-url-wrapper, #github-pat-wrapper, #github-button-wrapper').hide();
        }
    });

    // Event listener for the Switch button
    $(document).on('click', '.switch-branch', function (e) {
        e.preventDefault();

        var repoName = $(this).data('repo-name');
        var branchName = $(this).siblings('.branch-dropdown').val();

        // Send AJAX request to switch branch
        $.ajax({
            type: 'POST',
            url: wpGithubClone.ajax_url,
            data: {
                action: 'wp_github_clone_switch_branch',
                repo: repoName,
                branch: branchName,
                nonce: wpGithubClone.nonce
            },
            success: function (response) {
                console.log('Switch Branch Response:', response); // Debug response structure
                if (response.success) {
                    alert("Successfully switched to branch " + branchName); // Show success message
                    $(this).closest('li').find('.current-branch').text(branchName); // Update displayed branch
                } else if (response.details) {
                    alert("Error: " + response.message + "\n\nDetails:\n" + response.details); // Show detailed error message
                } else {
                    alert("An error occurred while switching the branch. No additional details available.");
                }
            }.bind(this),
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("Request failed: " + textStatus + " - " + errorThrown);
                alert("An unexpected error occurred. Check the console for more details.");
            }
        }); 
        
    });

    $(document).on('click', '.fetch-all', function (e) {
        e.preventDefault();
    
        var repoName = $(this).data('repo-name');
    
        // Send AJAX request to fetch all branches
        $.ajax({
            type: 'POST',
            url: wpGithubClone.ajax_url,
            data: {
                action: 'wp_github_clone_fetch_all',
                repo: repoName,
                nonce: wpGithubClone.nonce
            },
            success: function (response) {
                console.log('Fetch All Response:', response);
    
                if (response.success) {
                    alert(response.data.message); // Display success message
    
                    // Dynamically update the branch dropdown
                    if (response.data.localBranches) {
                        updateBranchDropdown(repoName, response.data.localBranches);
                    }
                    if (response.data.trackingErrors && response.data.trackingErrors.length > 0) {
                        console.warn('Tracking Errors:', response.data.trackingErrors);
                    }
                } else {
                    console.error('Unexpected response structure:', response);
                    alert("Failed to update branches. Check the console for details.");
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("Fetch All Request failed: " + textStatus + " - " + errorThrown);
                alert("An unexpected error occurred. Check the console for more details.");
            }
        });
    });
    
    function updateBranchDropdown(repoName, branches) {
        var branchDropdown = $('.branch-dropdown[data-repo-name="' + repoName + '"]');
        branchDropdown.empty(); // Clear existing options
    
        // Add new branches
        $.each(branches, function (index, branch) {
            // Remove the "*" indicator from the current branch
            branchDropdown.append('<option value="' + branch.replace(/^\* /, '') + '">' + branch + '</option>');
        });
    }  
    

});