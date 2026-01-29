<?php
// router.php

// Serve static files directly
if (php_sapi_name() === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

// Otherwise, route everything through index.php
require __DIR__ . '/index.php';
