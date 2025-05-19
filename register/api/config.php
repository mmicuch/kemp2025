<?php
/**
 * Configuration file for the registration system
 * This file loads environment variables from .env file and sets up error handling
 */

// Set error reporting
ini_set('display_errors', 0); // Changed to 0 to disable displaying errors in production
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Make sure we have a valid path for logs
// First check if we can use the directory above
$parentDir = dirname(__DIR__);
$logDir = $parentDir . '/logs';

// Try to create the logs directory
if (!is_dir($logDir)) {
    // Try to create it, but if it fails, use the current directory
    if (!@mkdir($logDir, 0755, true)) {
        // Fall back to the current directory
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
    }
}

// Set the error log to the logs directory if possible, otherwise use the default
if (is_dir($logDir) && is_writable($logDir)) {
    ini_set('error_log', $logDir . '/php_errors.log');
} else {
    // Just use PHP's default error log location
    error_log("Could not create or write to logs directory: " . $logDir);
}

// Load environment variables from .env file
function loadEnv() {
    $envFile = dirname(__DIR__) . '/.env';

    if (!file_exists($envFile)) {
        $envFile = __DIR__ . '/.env';
        if (!file_exists($envFile)) {
            error_log('Environment file (.env) not found in parent or current directory');
            return false;
        }
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse line
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Remove quotes if present
            if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            }

            // Set as environment variable
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return true;
}

// Load environment variables
if (loadEnv()) {
    error_log('Loaded .env file. DB_HOST: ' . getenv('DB_HOST'));
} else {
    error_log('Failed to load .env file, using default values');
}

// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'kemp');

// Security configuration
define('SECURITY_CODE_LEADER', getenv('SECURITY_CODE_LEADER') ?: 'hbvb935jjkajd983');
define('SECURITY_CODE_GUEST', getenv('SECURITY_CODE_GUEST') ?: 'jh78ggt5vbq384');

// Application configuration
define('APP_URL', getenv('APP_URL') ?: 'https://kemp.baptist.sk');

/**
 * Function to sanitize user input
 *
 * @param string $input The user input to sanitize
 * @return string The sanitized input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Function to verify security code
 *
 * @param string $code The security code to verify
 * @param string $type The registration type ('veduci' or 'host')
 * @return bool Whether the code is valid for the given type
 */
function verifySecurityCode($code, $type) {
    if (empty($code) || empty($type)) {
        return false;
    }

    if ($type === 'veduci' && $code === SECURITY_CODE_LEADER) {
        return true;
    } elseif ($type === 'host' && $code === SECURITY_CODE_GUEST) {
        return true;
    }

    return false;
}

/**
 * Function to log messages
 *
 * @param string $message The message to log
 * @param string $level The log level (info, warning, error, debug)
 */
function logMessage($message, $level = 'info') {
    // Don't log debug messages in production
    if ($level === 'debug') {
        return;
    }

    // Just use PHP's default error log
    error_log("[$level]: $message");
}
