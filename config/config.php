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
        redirect('login.php');
    }
    if ($user['status'] !== 'ACTIVE') {
        session_destroy();
        set_flash('Your account is not yet active. Please wait for administrator approval.', 'warn');
        redirect('login.php');
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
        exit('403 — Forbidden. ' . APP_NAME);
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