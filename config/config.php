<?php
/**
 * FilaQ configuration
 * Central database connection + helper functions
 * Runs on XAMPP (Apache + MySQL).
 */

declare(strict_types=1);

session_start();

/* ------------------------------------------------------------------ *
 *  Database constants (XAMPP defaults)
 * ------------------------------------------------------------------ */
const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'filaq_db';

/* ------------------------------------------------------------------ *
 *  Application constants
 * ------------------------------------------------------------------ */
define('APP_NAME', 'FilaQ');
define('APP_TAGLINE', 'A Smart Queue Management System');
define('BASE_PATH', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

/**
 * Site-relative URL root (e.g. "/FilaQ-Mary", or "" when the app is installed
 * directly at the document root). Worked out from the CURRENT request so it
 * keeps working even when the app is reached through a symlink/junction
 * (e.g. an XAMPP htdocs subfolder that really lives on another drive):
 * realpath(DOCUMENT_ROOT) would not share a prefix with the app folder then,
 * so instead we count how many directories the current script sits below the
 * app root (filesystem, junction-resolved) and strip that many path segments
 * off the script's URL. Redirects therefore work from every subfolder — a
 * plain relative redirect like "login.php" would resolve to
 * "/admin/login.php" when a session expires on an admin page, which 404s.
 */
$webRoot = '';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptFile = $_SERVER['SCRIPT_FILENAME'] ?? '';
if ($scriptName !== '' && $scriptFile !== '') {
    $baseReal = realpath(BASE_PATH) ?: '';
    $dirReal = realpath(dirname($scriptFile)) ?: '';
    $depth = 0;
    if ($baseReal !== '' && $dirReal !== '') {
        $baseNorm = str_replace('\\', '/', strtolower($baseReal));
        $dirNorm = str_replace('\\', '/', strtolower($dirReal));
        if (str_starts_with($dirNorm, $baseNorm)) {
            $after = substr($dirNorm, strlen($baseNorm));
            if ($after === '' || str_starts_with($after, '/')) {
                $depth = $after === '' ? 0 : substr_count(trim($after, '/'), '/') + 1;
            }
        }
    }
    $segments = array_values(array_filter(explode('/', str_replace('\\', '/', dirname($scriptName))), fn($s) => $s !== ''));
    if (count($segments) > $depth) {
        $segments = array_slice($segments, 0, count($segments) - $depth);
    } else {
        $segments = [];
    }
    if ($segments) {
        $webRoot = '/' . implode('/', $segments);
    }
}
define('APP_ROOT_URL', $webRoot);

/** Absolute-on-site path, e.g. url('login.php') → "/FilaQ-Mary/login.php". */
function url(string $path): string
{
    return APP_ROOT_URL . '/' . ltrim($path, '/');
}

/* ------------------------------------------------------------------ *
 *  Global error reporting (never leak errors to visitors)
 * ------------------------------------------------------------------ */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/logs/php-error.log');

/* ------------------------------------------------------------------ *
 *  PDO connection (prepared statements protect against SQL injection)
 * ------------------------------------------------------------------ */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('[FilaQ] DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit('FilaQ is having trouble reaching its database. Please start XAMPP (Apache + MySQL) and try again.');
        }
    }
    return $pdo;
}

/* ------------------------------------------------------------------ *
 *  Helpers
 * ------------------------------------------------------------------ */

/** Escape output for safe HTML rendering. */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/** Redirect helper. */
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/** Fetch a single row or null. */
function fetch_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Fetch all rows. */
function fetch_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Execute a write statement and return affected row count. */
function exec_write(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Log an activity (kept for admin monitoring).
 */
function log_activity(string $action, ?string $details = null, ?int $userId = null): void
{
    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = (int) $_SESSION['user_id'];
    }
    try {
        exec_write(
            'INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)',
            [$userId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? 'local']
        );
    } catch (Throwable $e) {
        error_log('[FilaQ] Failed to log activity: ' . $e->getMessage());
    }
}

/* ------------------------------------------------------------------ *
 *  Session / auth helpers
 * ------------------------------------------------------------------ */

/** Returns the currently logged in user or null. */
function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return fetch_one('SELECT id, full_name, username, email, phone, role, status, counter_id FROM users WHERE id = ?', [(int) $_SESSION['user_id']]);
}

/** Ensure user is logged in; redirects to login otherwise. */
function require_login(): ?array
{
    $user = current_user();
    if ($user === null) {
        set_flash('Please sign in to continue.', 'info');
        redirect(url('login.php'));
    }
    if ($user['status'] !== 'ACTIVE') {
        session_destroy();
        set_flash('Your account is not yet active. Please wait for administrator approval.', 'warn');
        redirect(url('login.php'));
    }
    return $user;
}

/** Ensure a specific role. */
function require_role(string $role): void
{
    $user = require_login();
    if ($user['role'] !== $role) {
        set_flash('You do not have permission to view that page.', 'error');
        http_response_code(403);
        exit('403 Forbidden. ' . APP_NAME);
    }
}

/** Set a one-time flash message. */
function set_flash(string $message, string $type = 'info'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

/** Render and consume any flash message set previously. */
function get_flash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}