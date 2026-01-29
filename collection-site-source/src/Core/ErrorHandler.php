<?php

namespace App\Core;

class ErrorHandler
{
    public static function initialize(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
    }

    public static function handleError($errno, $errstr, $errfile, $errline): void
    {
        if (ENVIRONMENT === 'development') {
            echo "<pre>Error [$errno]: $errstr in $errfile:$errline</pre>";
            return;
        }

        // Log error details in production
        error_log("Error [$errno]: $errstr in $errfile:$errline");

        self::renderErrorPage();
    }

    public static function handleException($exception): void
    {
        if (ENVIRONMENT === 'development') {
            echo "<pre>Exception: " . $exception->getMessage() . "\n" .
                $exception->getTraceAsString() . "</pre>";
            return;
        }

        // Log exception details in production
        error_log("Exception: " . $exception->getMessage() . "\n" .
            $exception->getTraceAsString());

        self::renderErrorPage();
    }

    private static function renderErrorPage(): void
    {
        http_response_code(500);
        require __DIR__ . '/../Views/pages/error.php'; // a simple error view
        exit();
    }
}