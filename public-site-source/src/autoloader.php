<?php

spl_autoload_register(function ($class) {
    // Project namespace prefix
    $prefix = 'App\\';

    // Base directories to search
    $baseDirs = [
        __DIR__ . '/../src/',
        __DIR__ . '/../config/'  // add your second directory
    ];

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relativeClass = substr($class, $len);
    $relativeFile = str_replace('\\', '/', $relativeClass) . '.php';

    // Try each base directory
    foreach ($baseDirs as $baseDir) {
        $file = $baseDir . $relativeFile;
        if (file_exists($file)) {
            require $file;
            return;
        }
    }

    // Not found in any directory
    throw new \Exception("Autoloader: Class {$class} not found");
});