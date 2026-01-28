<?php

spl_autoload_register(/**
 * @throws Exception
 */ function ($class) {
    // Project namespace prefix
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../src/'; // adjust to your actual src folder

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // not our namespace, move on
        return;
    }

    // Get the relative class name
    $relativeClass = substr($class, $len);

    // Replace namespace separators with directory separators, append .php
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    } else {
        throw new \Exception("Autoloader: Class {$class} not found at {$file}");
    }
});
