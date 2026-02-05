<?php

spl_autoload_register(function ($class) {
    // Project namespace prefix
    $prefix = 'App\\';

    // Base directories to search
    $baseDirs = [
        __DIR__ . '/',
        __DIR__ . '/controllers/',
        __DIR__ . '/models/',
    ];

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relativeClass = substr($class, $len);

    // PSR-4-ish: Map namespace to file structure
    // We try to find the class in each base directory by converting namespace separators to directory separators
    $relativeFile = str_replace('\\', '/', $relativeClass) . '.php';

    // Try each base directory
    foreach ($baseDirs as $baseDir) {
        // If the class is like App\Controllers\Config and we are looking in __DIR__/controllers/
        // we might be looking for __DIR__/controllers/Controllers/Config.php
        // So we should also try stripping the first part of the relative path if it matches the folder name

        $pathsToTry = [$baseDir . $relativeFile];

        $parts = explode('/', str_replace('\\', '/', $relativeClass));
        if (count($parts) > 1) {
            $folderName = basename($baseDir);
            // Case-insensitive check for folder name match
            if (strcasecmp($parts[0], $folderName) === 0) {
                $subRelativeFile = implode('/', array_slice($parts, 1)) . '.php';
                $pathsToTry[] = $baseDir . $subRelativeFile;
            }
        }

        foreach ($pathsToTry as $file) {
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }

    // Not found in any directory
    throw new \Exception(
        "Autoloader error:\n" .
        "  Class: {$class}\n" .
        "  Expected relative file: {$relativeFile}\n" .
        "  Checked directories: " . implode(', ', $baseDirs) . "\n" .
        "  No matching file found."
    );


});