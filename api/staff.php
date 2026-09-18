<?php
/**
 * FilaQ staff API — today's queue, records and per-counter stats.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/queue.php';
require_once __DIR__ . '/_boot.php';

/**
 * Today's headline numbers for the staff desk.
 * Shared by the queue action (merged into one response) and the stats action.
 */
function staff_stats(): array
{
    return [
        'waiting'   => (int) (fetch_one('SELECT COUNT(*) AS c FROM queue_tickets WHERE status IN ("WAITING","CALLED")')['c'] ?? 0),
        'serving'   => (int) (fetch_one('SELECT COUNT(*) AS c FROM queue_tickets WHERE status = "SERVING"')['c'] ?? 0),
        'issued'    => (int) (fetch_one('SELECT COUNT(*) AS c FROM queue_tickets WHERE status != "CANCELLED" AND DATE(issued_at) = CURDATE()')['c'] ?? 0),
        'completed' => (int) (fetch_one('SELECT COUNT(*) AS c FROM queue_tickets WHERE status = "COMPLETED" AND DATE(completed_at) = CURDATE()')['c'] ?? 0),
        'skipped'   => (int) (fetch_one('SELECT COUNT(*) AS c FROM queue_tickets WHERE status = "SKIPPED" AND DATE(completed_at) = CURDATE()')['c'] ?? 0),
        'avg_wait'  => (int) (fetch_one('SELECT COALESCE(AVG(actual_wait_sec)/60,0) AS a FROM queue_tickets WHERE status = "COMPLETED" AND DATE(issued_at) = CURDATE()')['a'] ?? 0),
    ];
}

try {
    $user = require_login();
    if ($user['role'] !== 'STAFF' && $user['role'] !== 'ADMIN') {
        json_out(['ok' => false, 'message' => 'Forbidden.'], 403);
    }

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'queue': {
            // Waiting + currently called/serving tickets with service names.
            // The position is computed server-side per row in ONE statement,
            // avoiding a separate lookup (N+1) for every ticket in the list.
            $rows = fetch_all(
                'SELECT t.id, t.ticket_code, t.customer_name, t.status, t.issued_at,
                        t.estimated_wait_sec, s.name AS service_name,
                        (SELECT COUNT(*) FROM queue_tickets p
                         WHERE p.service_id = t.service_id
                           AND p.status IN ("WAITING","CALLED") AND p.id < t.id) AS position
                 FROM queue_tickets t
                 JOIN services s ON s.id = t.service_id
                 WHERE t.status IN ("WAITING","CALLED","SERVING")
                 ORDER BY FIELD(t.status,"SERVING","CALLED","WAITING"), t.id ASC
                 LIMIT 40'
            );
            // Stats ride along in the same response so the staff desk makes ONE request per poll.
            json_out(['ok' => true, 'data' => $rows, 'stats' => staff_stats()]);
            break;
        }

        case 'stats': {
            json_out(['ok' => true, 'data' => staff_stats()]);
            break;
        }

        case 'records': {
            $limit = min(200, max(1, (int) ($_GET['limit'] ?? 30)));
            $rows = fetch_all(
                'SELECT t.ticket_code, t.status, t.customer_name, t.issued_at, t.called_at, t.completed_at,
                        t.actual_wait_sec, s.name AS service_name, IFNULL(c.name, "—") AS counter_name
                 FROM queue_tickets t
                 JOIN services s ON s.id = t.service_id
                 LEFT JOIN counters c ON c.id = t.counter_id
                 WHERE DATE(t.issued_at) = CURDATE()
                 ORDER BY t.id DESC
                 LIMIT ' . (int) $limit
            );
            json_out(['ok' => true, 'data' => $rows]);
            break;
        }

        default:
            json_out(['ok' => false, 'message' => 'Unknown action.'], 404);
    }
} catch (Throwable $t) {
    error_log('[FilaQ staff API] ' . $t->getMessage());
    json_out(['ok' => false, 'message' => 'An unexpected error occurred.'], 500);
}