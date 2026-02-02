<?php

define('DATABASE_HOST', '127.0.0.1');
define('DATABASE_NAME', 'database_name');
define('DATABASE_USER', '');
define('DATABASE_USER_PASSWORD', '');
define('ENVIRONMENT', 'development'); // development/production
// Toggle pretty URLs
define('ENABLE_PRETTY_URLS', true); // set false to use ?page=about-us

define('PK_LENGTH', 5);
define('PK_MAX_HISTORY', 10);
define('GENERATED_PASSWORD_CHARS', '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
define('GENERATED_PASSWORD_LENGTH', 10);
define('GENERATED_PASSWORD_ALGORITHM', PASSWORD_ARGON2ID); //

define('SECRET_DOOR', 'contact-us');
define('secret_room', 'registration');

$pages = [
    'home' => 0,
    'about-us' => 1,
    'garden-design' => 2,
    'maintenance' => 3,
    'tools' => 4,
    'contact-us' => 5,
];


define('PAGES', $pages);
