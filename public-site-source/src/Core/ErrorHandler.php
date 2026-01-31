<?php

namespace App\Core;

use App\Config;
use Throwable;

class ErrorHandler
{
    private static ?Config $config = null;

    private static function getConfig(): Config
    {
        if (self::$config === null) {
            self::$config = Config::instance();
        }
        return self::$config;
    }

    private static function isDevelopment(): bool
    {
        try {
            $config = self::getConfig();
            return ($config->project_meta['environment'] ?? 'production') === 'development';
        } catch (Throwable $e) {
            // If config fails, assume production for safety
            error_log("Config access failed in ErrorHandler: " . $e->getMessage());
            return false;
        }
    }

    public static function initialize(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleFatalError']);
    }

    public static function handleError(
        int $errno,
        string $errstr,
        string $errfile = '',
        int $errline = 0
    ): bool {
        // Don't handle suppressed errors (@)
        if (!(error_reporting() & $errno)) {
            return false;
        }

        if (self::isDevelopment()) {
            echo "<pre>Error [$errno]: " . htmlspecialchars($errstr) .
                " in " . htmlspecialchars($errfile) . ":" . $errline . "</pre>";
            return true;
        }

        // Log error details in production
        error_log("Error [$errno]: $errstr in $errfile:$errline");

        self::renderErrorPage();
        return true;
    }

    public static function handleException(Throwable $exception): void
    {
        if (self::isDevelopment()) {
            echo "<pre>Exception: " . htmlspecialchars($exception->getMessage()) . "\n" .
                htmlspecialchars($exception->getTraceAsString()) . "</pre>";
            return;
        }

        // Log exception details in production
        error_log(
            "Exception: " . $exception->getMessage() .
            " in " . $exception->getFile() . ":" . $exception->getLine() . "\n" .
            $exception->getTraceAsString()
        );

        self::renderErrorPage();
    }

    public static function handleFatalError(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            if (self::isDevelopment()) {
                echo "<pre>Fatal Error [{$error['type']}]: " .
                    htmlspecialchars($error['message']) .
                    " in " . htmlspecialchars($error['file']) . ":" . $error['line'] . "</pre>";
            } else {
                error_log(
                    "Fatal Error [{$error['type']}]: {$error['message']} " .
                    "in {$error['file']}:{$error['line']}"
                );
                self::renderErrorPage();
            }
        }
    }

    private static function renderErrorPage(): void
    {
        if (!headers_sent()) {
            http_response_code(500);
        }

        $errorViewPath = __DIR__ . '/../Views/pages/error.php';

        if (file_exists($errorViewPath)) {
            require $errorViewPath;
        } else {
            echo '<!DOCTYPE html><html><head><title>Error</title></head><body>' .
                '<h1>An error occurred</h1>' .
                '<p>We apologize for the inconvenience. Please try again later.</p>' .
                '</body></html>';
        }

        exit();
    }
}