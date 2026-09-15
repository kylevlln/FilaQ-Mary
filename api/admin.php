<?php
/**
 * FilaQ admin API — user management, counters/services, logs, settings, stats.
 * Every action requires an ACTIVE ADMIN session.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/queue.php';

header('Content-Type: application/json; charset=utf-8');

function admin_json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function admin_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return $_POST;
    $data = json_decode($raw, true);
    if (!is_array($data)) throw new RuntimeException('Invalid JSON body.');
    return $data;
}

try {
    $user = require_login();
    if ($user['role'] !== 'ADMIN') {
        admin_json_out(['ok' => false, 'message' => 'Administrator access required.'], 403);
    }

    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
    $data = admin_body();

    switch ($action) {

        /* ----------------------- USERS ----------------------- */

        case 'users': {
            $rows = fetch_all(
                'SELECT id, full_name, username, email, phone, role, status, last_login, counter_id, created_at
                 FROM users ORDER BY FIELD(role,"ADMIN","STAFF","CUSTOMER"), created_at DESC'
            );
            admin_json_out(['ok' => true, 'data' => $rows]);
        }

        case 'approve-user': {
            $id = (int) ($data['id'] ?? 0);
            $role = in_array($data['role'] ?? '', ['STAFF', 'CUSTOMER'], true) ? $data['role'] : null;
            if ($id <= 0) admin_json_out(['ok' => false, 'message' => 'Invalid user.'], 422);
            $target = fetch_one('SELECT * FROM users WHERE id = ?', [$id]);
            if (!$target) admin_json_out(['ok' => false, 'message' => 'User not found.'], 404);

            $counterId = null;
            if ($role === 'STAFF') {
                $counterId = isset($data['counter_id']) && (int) $data['counter_id'] > 0 ? (int) $data['counter_id'] : null;
            }
            $effectiveRole = $role ?? $target['role'];
            exec_write('UPDATE users SET status = "ACTIVE", role = ?, counter_id = ? WHERE id = ?', [$effectiveRole, $counterId, $id]);
            log_activity('USER_APPROVED', "Approved {$target['username']} as {$effectiveRole}", (int) $user['id']);
            admin_json_out(['ok' => true, 'message' => 'User approved and activated.']);
        }

        case 'suspend-user': {
            $id = (int) ($data['id'] ?? 0);
            if ($id === (int) $user['id']) admin_json_out(['ok' => false, 'message' => 'You cannot suspend your own account.'], 422);
            exec_write('UPDATE users SET status = "SUSPENDED" WHERE id = ? AND role != "ADMIN"', [$id]);
            admin_json_out(['ok' => true, 'message' => 'User suspended.']);
        }

        case 'reactivate-user': {
            $id = (int) ($data['id'] ?? 0);
            exec_write('UPDATE users SET status = "ACTIVE" WHERE id = ?', [$id]);
            admin_json_out(['ok' => true, 'message' => 'User reactivated.']);
        }

        case 'delete-user': {
            $id = (int) ($data['id'] ?? 0);
            if ($id === (int) $user['id']) admin_json_out(['ok' => false, 'message' => 'You cannot delete your own account.'], 422);
            exec_write('DELETE FROM users WHERE id = ? AND role != "ADMIN"', [$id]);
            admin_json_out(['ok' => true, 'message' => 'User deleted.']);
        }

        /* --------------------- COUNTERS ---------------------- */

        case 'counters': {
            $rows = fetch_all(
                'SELECT c.*, (SELECT COUNT(*) FROM users u WHERE u.counter_id = c.id AND u.role="STAFF") AS staff_count
                 FROM counters c ORDER BY c.id'
            );
            admin_json_out(['ok' => true, 'data' => $rows]);
        }

        case 'save-counter': {
            $id = (int) ($data['id'] ?? 0);
            $name = trim((string) ($data['name'] ?? ''));
            $location = trim((string) ($data['location'] ?? ''));
            if ($name === '') admin_json_out(['ok' => false, 'message' => 'Counter name is required.'], 422);
            $active = !empty($data['is_active']) ? 1 : 0;
            if ($id > 0) {
                exec_write('UPDATE counters SET name = ?, location = ?, is_active = ? WHERE id = ?', [$name, $location, $active, $id]);
                admin_json_out(['ok' => true, 'message' => 'Counter updated.']);
            }
            exec_write('INSERT INTO counters (name, location, is_active) VALUES (?, ?, ?)', [$name, $location, $active]);
            admin_json_out(['ok' => true, 'message' => 'Counter added.']);
        }

        case 'delete-counter': {
            $id = (int) ($data['id'] ?? 0);
            exec_write('DELETE FROM counters WHERE id = ?', [$id]);
            admin_json_out(['ok' => true, 'message' => 'Counter removed.']);
        }

        case 'delete-service': {
            $id = (int) ($data['id'] ?? 0);
            exec_write('DELETE FROM services WHERE id = ?', [$id]);
            admin_json_out(['ok' => true, 'message' => 'Service removed.']);
        }

        /* ---------------------- SERVICES --------------------- */

        case 'services': {
            $rows = fetch_all('SELECT * FROM services ORDER BY name');
            admin_json_out(['ok' => true, 'data' => $rows]);
        }

        case 'save-service': {
            $id = (int) ($data['id'] ?? 0);
            $name = trim((string) ($data['name'] ?? ''));
            $desc = trim((string) ($data['description'] ?? ''));
            $time = max(60, (int) ($data['avg_service_time_sec'] ?? 300));
            $active = !empty($data['is_active']) ? 1 : 0;
            if ($name === '') admin_json_out(['ok' => false, 'message' => 'Service name is required.'], 422);
            if ($id > 0) {
                exec_write('UPDATE services SET name = ?, description = ?, avg_service_time_sec = ?, is_active = ? WHERE id = ?', [$name, $desc, $time, $active, $id]);
                admin_json_out(['ok' => true, 'message' => 'Service updated.']);
            }
            exec_write('INSERT INTO services (name, description, avg_service_time_sec, is_active) VALUES (?, ?, ?, ?)', [$name, $desc, $time, $active]);
            admin_json_out(['ok' => true, 'message' => 'Service added.']);
        }

        /* ----------------------- LOGS ------------------------ */

        case 'logs': {
            $limit = min(300, max(1, (int) ($_GET['limit'] ?? 100)));
            $rows = fetch_all(
                'SELECT l.id, l.action, l.details, l.ip_address, l.created_at,
                        IFNULL(u.username, "system") AS username
                 FROM activity_logs l
                 LEFT JOIN users u ON u.id = l.user_id
                 ORDER BY l.id DESC
                 LIMIT ' . (int) $limit
            );
            admin_json_out(['ok' => true, 'data' => $rows]);
        }

        /* --------------------- SETTINGS ---------------------- */

        case 'settings': {
            $rows = fetch_all('SELECT setting_key, setting_value FROM system_settings ORDER BY setting_key');
            $map = [];
            foreach ($rows as $r) $map[$r['setting_key']] = $r['setting_value'];
            admin_json_out(['ok' => true, 'data' => $map]);
        }

        case 'save-settings': {
            $allowed = ['org_name', 'queue_prefix', 'open_time', 'close_time', 'announcement', 'allow_registration', 'estimate_lookback_minutes'];
            foreach ($allowed as $key) {
                if (array_key_exists($key, $data)) {
                    exec_write(
                        'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = ?',
                        [$key, (string) $data[$key], (int) $user['id']]
                    );
                }
            }
            log_activity('SETTINGS_UPDATED', 'Updated system settings', (int) $user['id']);
            admin_json_out(['ok' => true, 'message' => 'Settings saved.']);
        }

        /* ----------------------- STATS ----------------------- */

        case 'stats': {
            $today = [
                'issued'    => (int) (fetch_one("SELECT COUNT(*) AS c FROM queue_tickets WHERE DATE(issued_at) = CURDATE()")['c'] ?? 0),
                'completed' => (int) (fetch_one("SELECT COUNT(*) AS c FROM queue_tickets WHERE status='COMPLETED' AND DATE(completed_at) = CURDATE()")['c'] ?? 0),
                'skipped'   => (int) (fetch_one("SELECT COUNT(*) AS c FROM queue_tickets WHERE status='SKIPPED' AND DATE(completed_at) = CURDATE()")['c'] ?? 0),
                'waiting'   => (int) (fetch_one("SELECT COUNT(*) AS c FROM queue_tickets WHERE status IN ('WAITING','CALLED')")['c'] ?? 0),
                'users'     => (int) (fetch_one('SELECT COUNT(*) AS c FROM users')['c'] ?? 0),
                'pending'   => (int) (fetch_one("SELECT COUNT(*) AS c FROM users WHERE status='PENDING'")['c'] ?? 0),
            ];
            $week = fetch_all(
                'SELECT stat_date, issued_count, served_count, skipped_count, avg_wait_min
                 FROM daily_stats WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) ORDER BY stat_date'
            );
            $byService = fetch_all(
                'SELECT s.name, COUNT(t.id) AS cnt
                 FROM services s LEFT JOIN queue_tickets t ON t.service_id = s.id AND DATE(t.issued_at) = CURDATE()
                 GROUP BY s.id, s.name ORDER BY cnt DESC'
            );
            admin_json_out(['ok' => true, 'data' => ['today' => $today, 'week' => $week, 'by_service' => $byService]]);
        }

        default:
            admin_json_out(['ok' => false, 'message' => 'Unknown action.'], 404);
    }
} catch (Throwable $t) {
    error_log('[FilaQ admin API] ' . $t->getMessage());
    admin_json_out(['ok' => false, 'message' => 'An unexpected error occurred.'], 500);
}