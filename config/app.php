<?php
/**
 * BCMS — Application Configuration & Global Helpers
 */

declare(strict_types=1);

// ── App constants ──────────────────────────────────────────
if (!defined('APP_NAME'))         define('APP_NAME',    getenv('APP_NAME')  ?: 'Building Construction Management System');
if (!defined('APP_ENV'))          define('APP_ENV',     getenv('APP_ENV')   ?: 'development');
if (!defined('APP_URL'))          define('APP_URL',     getenv('APP_URL')   ?: 'http://localhost/bcms/public');
if (!defined('APP_KEY'))          define('APP_KEY',     getenv('APP_KEY')   ?: 'bcms_default_key_change_me');

// ── Paths ─────────────────────────────────────────────────
if (!defined('BASE_PATH'))        define('BASE_PATH',   dirname(__DIR__));
if (!defined('APP_PATH'))         define('APP_PATH',    BASE_PATH . '/app');
if (!defined('VIEW_PATH'))        define('VIEW_PATH',   APP_PATH  . '/views');
if (!defined('STORAGE_PATH'))     define('STORAGE_PATH',BASE_PATH . '/storage');
if (!defined('LOG_PATH'))         define('LOG_PATH',    STORAGE_PATH . '/logs');

// ── Session ────────────────────────────────────────────────
if (!defined('SESSION_NAME'))     define('SESSION_NAME',     getenv('SESSION_NAME')     ?: 'bcms_session');
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', (int)(getenv('SESSION_LIFETIME') ?: 7200));

// ── Currency ───────────────────────────────────────────────
if (!defined('CURRENCY_SYMBOL'))  define('CURRENCY_SYMBOL', '₹');
if (!defined('CURRENCY_CODE'))    define('CURRENCY_CODE',   'INR');

// ── Roles ──────────────────────────────────────────────────
if (!defined('ROLE_ADMIN'))       define('ROLE_ADMIN',  'admin');
if (!defined('ROLE_CLIENT'))      define('ROLE_CLIENT', 'client');
if (!defined('ROLE_WORKER'))      define('ROLE_WORKER', 'worker');

// ============================================================
// GLOBAL UTILITY FUNCTIONS
// ============================================================

/**
 * Start or resume the secure session.
 */
function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Ensure session save path is valid and actually writable (handles Windows CLI & sandbox boundaries)
        $currentPath = session_save_path();
        $canWrite = false;
        if (!empty($currentPath) && @is_dir($currentPath)) {
            $testFile = rtrim($currentPath, '\\/') . '/.sess_test_' . uniqid('', true);
            if (@file_put_contents($testFile, '1') !== false) {
                @unlink($testFile);
                $canWrite = true;
            }
        }

        if (!$canWrite) {
            $sessionsDir = STORAGE_PATH . '/sessions';
            if (!is_dir($sessionsDir)) {
                @mkdir($sessionsDir, 0755, true);
            }
            if (is_dir($sessionsDir) && is_writable($sessionsDir)) {
                session_save_path($sessionsDir);
            } else {
                $tempDir = sys_get_temp_dir();
                if (is_dir($tempDir) && is_writable($tempDir)) {
                    session_save_path($tempDir);
                }
            }
        }

        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false,   // Set true when using HTTPS
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        @session_start();
    }
}

/**
 * Get the logged-in user array from session, or null.
 */
function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Check if user is logged in.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user']['user_id']);
}

/**
 * Check if the logged-in user has a specific role.
 */
function hasRole(string $role): bool
{
    return ($_SESSION['user']['role'] ?? '') === $role;
}

/**
 * Require login; redirect to login page if not authenticated.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('error', 'Please log in to access this page.');
        redirect('/login.php');
    }
}

/**
 * Require a specific role; redirect with error if unauthorized.
 */
function requireRole(string ...$roles): void
{
    requireLogin();
    $userRole = $_SESSION['user']['role'] ?? '';
    if (!in_array($userRole, $roles, true)) {
        setFlash('error', 'You do not have permission to access this page.');
        redirect('/dashboard.php');
    }
}

/**
 * Redirect to a URL relative to the public root.
 */
function redirect(string $path): never
{
    $base = rtrim(APP_URL, '/');
    header('Location: ' . $base . $path);
    exit();
}

/**
 * Set a flash message in session.
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

/**
 * Get and clear flash messages.
 */
function getFlash(string $type): ?string
{
    $msg = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);
    return $msg;
}

/**
 * Generate a CSRF token and store in session.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Return an HTML hidden input with the CSRF token.
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

/**
 * Validate CSRF token from POST data.
 */
function validateCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        setFlash('error', 'Invalid security token. Please try again.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? APP_URL));
        exit();
    }
}

/**
 * Escape HTML output.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Format currency in INR.
 */
function formatCurrency(float|int|string $amount): string
{
    return CURRENCY_SYMBOL . ' ' . number_format((float)$amount, 2);
}

/**
 * Format a date for display (d M Y).
 */
function formatDate(?string $date): string
{
    if (empty($date)) return '—';
    return date('d M Y', strtotime($date));
}

/**
 * Format a datetime for display.
 */
function formatDateTime(?string $datetime): string
{
    if (empty($datetime)) return '—';
    return date('d M Y, h:i A', strtotime($datetime));
}

/**
 * Get CSS class for project/task status badge.
 */
function statusBadge(string $status): string
{
    return match ($status) {
        'active', 'in_progress', 'present' => 'badge bg-success',
        'completed'                         => 'badge bg-primary',
        'planning', 'pending'               => 'badge bg-warning text-dark',
        'on_hold', 'half_day'               => 'badge bg-secondary',
        'cancelled', 'absent'               => 'badge bg-danger',
        default                             => 'badge bg-light text-dark',
    };
}

/**
 * Get progress bar color class.
 */
function progressColor(int $progress): string
{
    return match(true) {
        $progress >= 80 => 'bg-success',
        $progress >= 50 => 'bg-info',
        $progress >= 25 => 'bg-warning',
        default         => 'bg-danger',
    };
}

/**
 * Log an activity.
 */
function logActivity(string $action, string $module, ?int $recordId = null, ?string $desc = null): void
{
    try {
        $userId = $_SESSION['user']['user_id'] ?? null;
        $ip     = $_SERVER['REMOTE_ADDR'] ?? null;
        dbExecute(
            'INSERT INTO activity_log (user_id, action, module, record_id, description, ip_address) VALUES (?,?,?,?,?,?)',
            [$userId, $action, $module, $recordId, $desc, $ip]
        );
    } catch (Throwable) {
        // Logging must not break the application
    }
}

/**
 * Sanitize and validate integer input.
 */
function intInput(mixed $val, int $min = 0): ?int
{
    $v = filter_var($val, FILTER_VALIDATE_INT);
    return ($v !== false && $v >= $min) ? (int)$v : null;
}

/**
 * Sanitize string input.
 */
function strInput(mixed $val, int $maxLen = 255): string
{
    return mb_substr(trim((string)$val), 0, $maxLen);
}

/**
 * Validate a decimal value >= 0.
 */
function decimalInput(mixed $val): ?float
{
    $v = filter_var($val, FILTER_VALIDATE_FLOAT);
    return ($v !== false && $v >= 0) ? (float)$v : null;
}

/**
 * Validate a date string (Y-m-d).
 */
function dateInput(mixed $val): ?string
{
    $v = trim((string)$val);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        $d = DateTime::createFromFormat('Y-m-d', $v);
        if ($d && $d->format('Y-m-d') === $v) return $v;
    }
    return null;
}

/**
 * Return paginate offset.
 */
function paginate(int $page, int $perPage = 20): array
{
    $page   = max(1, $page);
    $offset = ($page - 1) * $perPage;
    return ['limit' => $perPage, 'offset' => $offset, 'page' => $page];
}
