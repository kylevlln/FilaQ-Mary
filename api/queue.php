<?php
/**
 * FilaQ queue API — JSON endpoints, always guarded with try/catch.
 * All responses: { ok, data?, message?, error? }
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/queue.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

/** Read a JSON request body or $_POST (throws on malformed JSON only when body is present). */
function request_data(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return $_POST;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Request body must be valid JSON.');
    }
    return $data;
}

try {
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    switch ($action) {
        /* ---------------- PUBLIC / lightly-guarded ---------------- */

        case 'services': {
            $services = fetch_all('SELECT id, name, description, avg_service_time_sec, CONCAT_WS(" ", name) AS label FROM services WHERE is_active = 1 ORDER BY name');
            foreach ($services as &$s) {
                $s['est_wait_min'] = estimate_wait_minutes_for_service((int) $s['id']);
            }
            unset($s);
            json_out(['ok' => true, 'data' => $services]);
            break;
        }

        case 'live': {
            // Public display: currently called/serving + upcoming across services, by counter.
            $serviceId = isset($_GET['service_id']) ? (int) $_GET['service_id'] : null;

            $now = fetch_one(
                'SELECT t.ticket_code, t.status, c.name AS counter_name, s.name AS service_name
                 FROM queue_tickets t
                 JOIN services s ON s.id = t.service_id
                 LEFT JOIN counters c ON c.id = t.counter_id
                 WHERE t.status IN ("CALLED","SERVING")
                   AND (? IS NULL OR t.service_id = ?)
                 ORDER BY FIELD(t.status, "SERVING","CALLED"), t.id DESC
                 LIMIT 3',
                [$serviceId, $serviceId]
            );

            $upcoming = fetch_all(
                'SELECT t.ticket_code, s.name AS service_name
                 FROM queue_tickets t
                 JOIN services s ON s.id = t.service_id
                 WHERE t.status = "WAITING"
                   AND (? IS NULL OR t.service_id = ?)
                 ORDER BY t.id ASC
                 LIMIT 10',
                [$serviceId, $serviceId]
            );

            $queues = fetch_all(
                'SELECT s.id AS service_id, s.name AS service_name,
                        COALESCE(SUM(t.status = "WAITING"), 0) AS waiting,
                        (SELECT ticket_code FROM queue_tickets WHERE service_id = s.id AND status IN ("CALLED","SERVING") ORDER BY id DESC LIMIT 1) AS current_code
                 FROM services s
                 LEFT JOIN queue_tickets t ON t.service_id = s.id AND t.status = "WAITING"
                 WHERE s.is_active = 1
                 GROUP BY s.id, s.name
                 ORDER BY s.name'
            );
            foreach ($queues as &$q) {
                $q['est_wait_min'] = estimate_wait_minutes_for_service((int) $q['service_id']);
            }
            unset($q);

            json_out(['ok' => true, 'data' => ['now' => $now, 'upcoming' => $upcoming, 'queues' => $queues]]);
            break;
        }

        case 'track': {
            // Track a ticket by its session code (no login needed for customers).
            $code = trim((string) ($_GET['code'] ?? ''));
            if ($code === '') {
                json_out(['ok' => false, 'message' => 'Please provide a tracking code.'], 422);
            }
            $ticket = fetch_one(
                'SELECT t.id, t.ticket_code, t.status, t.issued_at,
                        t.estimated_wait_sec, s.name AS service_name, s.avg_service_time_sec
                 FROM queue_tickets t JOIN services s ON s.id = t.service_id
                 WHERE t.session_code = ? LIMIT 1',
                [$code]
            );
            if ($ticket === null) {
                json_out(['ok' => false, 'message' => 'No ticket found with that code. Check the spelling and try again.'], 404);
            }
            $ticket['position'] = position_of_ticket((int) $ticket['id']);
            $ticket['remaining_wait_min'] = remaining_wait_for_ticket((int) $ticket['id']);
            json_out(['ok' => true, 'data' => $ticket]);
            break;
        }

        case 'take-ticket': {
            $data = request_data();
            $serviceId = (int) ($data['service_id'] ?? 0);
            $name  = trim((string) ($data['customer_name'] ?? ''));
            $phone = trim((string) ($data['contact'] ?? ''));
            if ($serviceId <= 0) {
                json_out(['ok' => false, 'message' => 'Please choose a service first.'], 422);
            }
            $ticket = take_ticket($serviceId, $name !== '' ? $name : null, $phone !== '' ? $phone : null);
            json_out(['ok' => true, 'data' => $ticket, 'message' => 'Your number is ready!']);
            break;
        }

        /* ---------------- STAFF + ADMIN guarded actions ---------------- */

        case 'call': {
            $u = require_login();
            if ($u['role'] !== 'STAFF' && $u['role'] !== 'ADMIN') {
                json_out(['ok' => false, 'message' => 'Only staff can call numbers.'], 403);
            }
            $data = request_data();
            $ticketId = (int) ($data['ticket_id'] ?? 0);
            $counterId = (int) ($data['counter_id'] ?? $u['counter_id'] ?? 0);
            if ($ticketId <= 0) json_out(['ok' => false, 'message' => 'Invalid ticket.'], 422);
            if ($counterId <= 0) json_out(['ok' => false, 'message' => 'No counter assigned to your account. Ask the administrator.'], 422);
            call_ticket($ticketId, $counterId);
            json_out(['ok' => true, 'message' => 'Ticket called.']);
            break;
        }

        case 'start': {
            $u = require_login();
            if ($u['role'] !== 'STAFF' && $u['role'] !== 'ADMIN') json_out(['ok' => false, 'message' => 'Staff only.'], 403);
            $data = request_data();
            start_serving((int) ($data['ticket_id'] ?? 0));
            json_out(['ok' => true, 'message' => 'Serving started.']);
            break;
        }

        case 'complete': {
            $u = require_login();
            if ($u['role'] !== 'STAFF' && $u['role'] !== 'ADMIN') json_out(['ok' => false, 'message' => 'Staff only.'], 403);
            $data = request_data();
            complete_ticket((int) ($data['ticket_id'] ?? 0));
            json_out(['ok' => true, 'message' => 'Service completed.']);
            break;
        }

        case 'skip': {
            $u = require_login();
            if ($u['role'] !== 'STAFF' && $u['role'] !== 'ADMIN') json_out(['ok' => false, 'message' => 'Staff only.'], 403);
            $data = request_data();
            skip_ticket((int) ($data['ticket_id'] ?? 0));
            json_out(['ok' => true, 'message' => 'Ticket skipped.']);
            break;
        }

        default:
            json_out(['ok' => false, 'message' => 'Unknown action.'], 404);
    }
} catch (Throwable $t) {
    error_log('[FilaQ API] ' . $action . ' → ' . $t->getMessage());
    $msg = $t instanceof RuntimeException ? $t->getMessage() : 'An unexpected error occurred. Please try again.';
    json_out(['ok' => false, 'message' => $msg], 500);
}