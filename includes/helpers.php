<?php

function is_valid_github_url($url) {
    return preg_match('/https:\/\/github\.com\/[a-zA-Z0-9\-_]+\/[a-zA-Z0-9\-_]+/', $url);
}
