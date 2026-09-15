<?php
/**
 * FilaQ staff API — today's queue, records and per-counter stats.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/queue.php';

header('Content-Type: application/json; charset=utf-8');

function staff_json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    $user = require_login();
    if ($user['role'] !== 'STAFF' && $user['role'] !== 'ADMIN') {
        staff_json_out(['ok' => false, 'message' => 'Forbidden.'], 403);
    }

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'queue': {
            // Waiting + currently called/serving tickets with service names.
            $rows = fetch_all(
                'SELECT t.id, t.ticket_code, t.customer_name, t.status, t.issued_at,
                        t.estimated_wait_sec, s.name AS service_name
                 FROM queue_tickets t
                 JOIN services s ON s.id = t.service_id
                 WHERE t.status IN ("WAITING","CALLED","SERVING")
                 ORDER BY FIELD(t.status,"SERVING","CALLED","WAITING"), t.id ASC
                 LIMIT 40'
            );
            foreach ($rows as &$r) {
                $r['position'] = position_of_ticket((int) $r['id']);
            }
            unset($r);
            staff_json_out(['ok' => true, 'data' => $rows]);
            break;
        }

        case 'stats': {
            $stats = [
                'waiting'  => (int) (fetch_one('SELECT COUNT(*) AS c FROM queue_tickets WHERE status IN ("WAITING","CALLED")')['c'] ?? 0),
                'serving'  => (int) (fetch_one('SELECT COUNT(*) AS c FROM queue_tickets WHERE status = "SERVING"')['c'] ?? 0),
                'issued'   => (int) (fetch_one('SELECT COUNT(*) AS c FROM queue_tickets WHERE status != "CANCELLED" AND DATE(issued_at) = CURDATE()')['c'] ?? 0),
                'completed'=> (int) (fetch_one('SELECT COUNT(*) AS c FROM queue_tickets WHERE status = "COMPLETED" AND DATE(completed_at) = CURDATE()')['c'] ?? 0),
                'skipped'  => (int) (fetch_one('SELECT COUNT(*) AS c FROM queue_tickets WHERE status = "SKIPPED" AND DATE(completed_at) = CURDATE()')['c'] ?? 0),
                'avg_wait' => (int) (fetch_one('SELECT COALESCE(AVG(actual_wait_sec)/60,0) AS a FROM queue_tickets WHERE status = "COMPLETED" AND DATE(issued_at) = CURDATE()')['a'] ?? 0),
            ];
            staff_json_out(['ok' => true, 'data' => $stats]);
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
            staff_json_out(['ok' => true, 'data' => $rows]);
            break;
        }

        default:
            staff_json_out(['ok' => false, 'message' => 'Unknown action.'], 404);
    }
} catch (Throwable $t) {
    error_log('[FilaQ staff API] ' . $t->getMessage());
    staff_json_out(['ok' => false, 'message' => 'An unexpected error occurred.'], 500);
}