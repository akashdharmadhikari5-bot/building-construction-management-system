<?php
/**
 * BCMS — Database Configuration & PDO Connection
 */

declare(strict_types=1);

// Load .env file if present
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
}

if (!defined('DB_HOST'))    define('DB_HOST',    getenv('DB_HOST')     ?: '127.0.0.1');
if (!defined('DB_PORT'))    define('DB_PORT',    getenv('DB_PORT')     ?: '3306');
if (!defined('DB_NAME'))    define('DB_NAME',    getenv('DB_DATABASE') ?: 'bcms_db');
if (!defined('DB_USER'))    define('DB_USER',    getenv('DB_USERNAME') ?: 'root');
if (!defined('DB_PASS'))    define('DB_PASS',    getenv('DB_PASSWORD') ?: '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO instance.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        try {
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // Use PHP 8.5+ constant if available, otherwise fallback
            if (defined('\Pdo\Mysql::ATTR_INIT_COMMAND')) {
                $options[\Pdo\Mysql::ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
            } elseif (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
            }

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Never expose credentials in error output
            error_log('DB Connection failed: ' . $e->getMessage());
            if (php_sapi_name() === 'cli') {
                throw $e;
            }
            die('<div style="font-family:sans-serif;padding:20px;color:#c00;background:#fff3f3;border:1px solid #c00;border-radius:6px;margin:40px auto;max-width:600px">'
                . '<h2>Database Connection Error</h2>'
                . '<p>Could not connect to MySQL. Please check your <code>.env</code> configuration and ensure MySQL is running in XAMPP.</p>'
                . '<p><strong>Common fix:</strong> Start Apache and MySQL in XAMPP Control Panel, then refresh this page.</p>'
                . '</div>');
        }
    }
    return $pdo;
}

/**
 * Alias for db() for compatibility.
 */
function getDb(): PDO
{
    return db();
}

/**
 * Helper: Run a SELECT query and return all rows.
 */
function dbQuery(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Helper: Run a SELECT query and return one row.
 */
function dbQueryOne(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

/**
 * Helper: Run INSERT/UPDATE/DELETE and return affected rows.
 */
function dbExecute(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Helper: Run INSERT and return last insert ID.
 */
function dbInsert(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) db()->lastInsertId();
}
