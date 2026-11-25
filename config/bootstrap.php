<?php
/**
 * BCMS — Application Bootstrap
 * Include this at the top of every public PHP file.
 */

declare(strict_types=1);

// Absolute path to project root (one level above /public)
define('BCMS_ROOT', dirname(__DIR__));

// Error handling: show errors in dev, log in production
$env = getenv('APP_ENV') ?: 'development';
if ($env === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
    ini_set('log_errors', '1');
    ini_set('error_log', BCMS_ROOT . '/storage/logs/php_errors.log');
}

// Load config
require_once BCMS_ROOT . '/config/database.php';
require_once BCMS_ROOT . '/config/app.php';

// Start session
startSession();

// Auto-load model/service files
$autoloadDirs = [
    BCMS_ROOT . '/app/models',
    BCMS_ROOT . '/app/services',
    BCMS_ROOT . '/app/middleware',
];
foreach ($autoloadDirs as $dir) {
    if (is_dir($dir)) {
        foreach (glob($dir . '/*.php') as $file) {
            require_once $file;
        }
    }
}
