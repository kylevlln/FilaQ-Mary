<?php
/**
 * FilaQ shared queue logic.
 * Ticket numbering, wait-time estimation and queue operations.
 * Keeps business rules in one place so staff/admin/API all behave identically.
 */

require_once __DIR__ . '/../config/config.php';

/* ------------------------------------------------------------------ *
 *  Settings
 * ------------------------------------------------------------------ */

function setting(string $key, ?string $default = null): ?string
{
    $row = fetch_one('SELECT setting_value FROM system_settings WHERE setting_key = ?', [$key]);
    return $row['setting_value'] ?? $default;
}

/* ------------------------------------------------------------------ *
 *  Ticket numbering
 * ------------------------------------------------------------------ */

/**
 * Returns the next ticket code for a service on a given date.
 * Format: "prefix-NNN" e.g. GEN-007.
 * Relies on counting today's tickets per service, avoiding a race-prone sequence table.
 */
function next_ticket_code(int $serviceId, string $prefix = 'GEN'): string
{
    $today = date('Y-m-d');
    $row = fetch_one(
        'SELECT COUNT(*) AS c FROM queue_tickets
         WHERE service_id = ? AND DATE(issued_at) = ? AND ticket_code IS NOT NULL',
        [$serviceId, $today]
    );
    $next = ((int) ($row['c'] ?? 0)) + 1;
    return sprintf('%s-%03d', $prefix, $next);
}

/* ------------------------------------------------------------------ *
 *  Wait-time estimation (queueing-theory inspired)
 * ------------------------------------------------------------------ */

/**
 * Estimates how many minutes a NEW ticket issued for a service will wait.
 *
 * Approach:
 *   1. Service's configured average service time (sec).
 *   2. Multiply by tickets already waiting ahead for that service.
 *   3. Blend with a utilisation factor: how long, on average, this service
 *      has actually taken per person recently (live throughput), so
 *      estimates adapt when counters are faster or slower than the default.
 */
function estimate_wait_minutes_for_service(int $serviceId): int
{
    $service = fetch_one('SELECT id, name, avg_service_time_sec FROM services WHERE id = ?', [$serviceId]);
    if ($service === null) {
        return 0;
    }

    $defaultSec = max(60, (int) $service['avg_service_time_sec']);

    $ahead = (int) (fetch_one(
        'SELECT COUNT(*) AS c FROM queue_tickets
         WHERE service_id = ? AND status IN ("WAITING","CALLED")',
        [$serviceId]
    )['c'] ?? 0);

    // Live throughput: average actual minutes-per-served-ticket over the last 90 minutes.
    $throughput = fetch_one(
        'SELECT AVG(actual_wait_sec) AS avgw, COUNT(*) AS c
         FROM queue_tickets
         WHERE service_id = ? AND status = "COMPLETED" AND serve_started_at >= DATE_SUB(NOW(), INTERVAL 90 MINUTE)',
        [$serviceId]
    );

    $liveSec = null;
    if ($throughput && (int) $throughput['c'] > 0 && (int) $throughput['avgw'] > 0) {
        $liveSec = (float) $throughput['avgw'];
    }

    $effectiveSec = $liveSec !== null ? $liveSec : (float) $defaultSec;

    return (int) ceil(($ahead * $effectiveSec) / 60.0);
}

/**
 * Estimated wait (minutes) for ONE specific ticket given its position in line.
 */
function remaining_wait_for_ticket(int $ticketId): int
{
    $ticket = fetch_one('SELECT * FROM queue_tickets WHERE id = ?', [$ticketId]);
    if ($ticket === null) {
        return 0;
    }

    $ahead = fetch_all(
        'SELECT id, service_id FROM queue_tickets
         WHERE service_id = ?
           AND id < ?
           AND status IN ("WAITING","CALLED")
         ORDER BY id ASC',
        [$ticket['service_id'], $ticketId]
    );

    $totalSec = 0.0;
    foreach ($ahead as $t) {
        $sev = (int) (fetch_one('SELECT avg_service_time_sec FROM services WHERE id = ?', [$t['service_id']])['avg_service_time_sec'] ?? 300);
        $totalSec += max(60, $sev);
    }

    return (int) ceil($totalSec / 60.0);
}

/* ------------------------------------------------------------------ *
 *  Queue lists
 * ------------------------------------------------------------------ */

function waiting_tickets(int $serviceId, int $limit = 8): array
{
    return fetch_all(
        'SELECT * FROM queue_tickets
         WHERE service_id = ? AND status IN ("WAITING","CALLED")
         ORDER BY FIELD(status, "CALLED","WAITING"), id ASC
         LIMIT ?',
        [$serviceId, $limit]
    );
}

/** Number waiting ahead of a given ticket. */
function position_of_ticket(int $ticketId): int
{
    $ticket = fetch_one('SELECT service_id FROM queue_tickets WHERE id = ?', [$ticketId]);
    if ($ticket === null) {
        return 0;
    }
    return (int) (fetch_one(
        'SELECT COUNT(*) AS c FROM queue_tickets
         WHERE service_id = ? AND status IN ("WAITING","CALLED") AND id < ?',
        [$ticket['service_id'], $ticketId]
    )['c'] ?? 0);
}

/* ------------------------------------------------------------------ *
 *  Actions (all share the same validation + audit hooks)
 * ------------------------------------------------------------------ */

function take_ticket(int $serviceId, ?string $customerName = null, ?string $contact = null): array
{
    $service = fetch_one('SELECT * FROM services WHERE id = ? AND is_active = 1', [$serviceId]);
    if ($service === null) {
        throw new RuntimeException('That service is not available right now.');
    }

    $code = next_ticket_code($serviceId, substr(strtoupper(preg_replace('/[^A-Z0-9]+/', '', $service['name'])) ?: 'SVC', 0, 3));
    $sessionCode = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $est = estimate_wait_minutes_for_service($serviceId);

    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

    db()->beginTransaction();
    try {
        exec_write(
            'INSERT INTO queue_tickets
               (ticket_code, user_id, service_id, priority, status, estimated_wait_sec, session_code, customer_name, contact)
             VALUES (?, ?, ?, "NORMAL", "WAITING", ?, ?, ?, ?)',
            [$code, $userId, $serviceId, $est * 60, $sessionCode, $customerName, $contact]
        );
        $id = (int) db()->lastInsertId();
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    log_activity('TICKET_ISSUED', "Issued {$code} for {$service['name']}", $userId);
    return fetch_one('SELECT * FROM queue_tickets WHERE id = ?', [$id]) ?? [];
}

function call_ticket(int $ticketId, int $counterId): bool
{
    $ticket = fetch_one('SELECT * FROM queue_tickets WHERE id = ?', [$ticketId]);
    if ($ticket === null || $ticket['status'] !== 'WAITING') {
        throw new RuntimeException('That ticket cannot be called right now.');
    }

    db()->beginTransaction();
    try {
        exec_write(
            'UPDATE queue_tickets SET status = "CALLED", called_at = NOW(), counter_id = ? WHERE id = ?',
            [$counterId, $ticketId]
        );
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    log_activity('TICKET_CALLED', "Called {$ticket['ticket_code']} to counter #{$counterId}");
    return true;
}

function start_serving(int $ticketId): bool
{
    $ticket = fetch_one('SELECT * FROM queue_tickets WHERE id = ?', [$ticketId]);
    if ($ticket === null || $ticket['status'] !== 'CALLED') {
        throw new RuntimeException('That ticket is not currently being called.');
    }

    db()->beginTransaction();
    try {
        exec_write(
            'UPDATE queue_tickets SET status = "SERVING", serve_started_at = NOW() WHERE id = ?',
            [$ticketId]
        );
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    log_activity('TICKET_SERVING', "Started serving {$ticket['ticket_code']}");
    return true;
}

function complete_ticket(int $ticketId): bool
{
    $ticket = fetch_one('SELECT * FROM queue_tickets WHERE id = ?', [$ticketId]);
    if ($ticket === null) {
        throw new RuntimeException('That ticket does not exist.');
    }

    // Compute actual wait/service times in SQL to avoid timezone mismatches
    db()->beginTransaction();
    try {
        exec_write(
            'UPDATE queue_tickets
             SET status = "COMPLETED",
                 completed_at = NOW(),
                 actual_wait_sec = TIMESTAMPDIFF(SECOND, issued_at, serve_started_at),
                 actual_service_sec = TIMESTAMPDIFF(SECOND, serve_started_at, NOW())
             WHERE id = ?',
            [$ticketId]
        );
        touch_daily_stats();
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    log_activity('TICKET_COMPLETED', "Completed {$ticket['ticket_code']}");
    return true;
}

function skip_ticket(int $ticketId): bool
{
    $ticket = fetch_one('SELECT * FROM queue_tickets WHERE id = ?', [$ticketId]);
    if ($ticket === null || !in_array($ticket['status'], ['WAITING', 'CALLED'], true)) {
        throw new RuntimeException('That ticket cannot be skipped.');
    }

    db()->beginTransaction();
    try {
        exec_write(
            'UPDATE queue_tickets SET status = "SKIPPED", completed_at = NOW(), skip_count = skip_count + 1 WHERE id = ?',
            [$ticketId]
        );
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    log_activity('TICKET_SKIPPED', "Skipped {$ticket['ticket_code']}");
    return true;
}

/* ------------------------------------------------------------------ *
 *  Daily stats rollup
 * ------------------------------------------------------------------ */

function touch_daily_stats(): void
{
    try {
        exec_write(
            'INSERT INTO daily_stats (stat_date, issued_count, served_count, skipped_count, avg_wait_min)
             SELECT CURDATE(), 0, 0, 0, NULL
             WHERE NOT EXISTS (SELECT 1 FROM daily_stats WHERE stat_date = CURDATE())'
        );
        exec_write(
            'UPDATE daily_stats
             SET issued_count = (SELECT COUNT(*) FROM queue_tickets WHERE DATE(issued_at) = CURDATE()),
                 served_count = (SELECT COUNT(*) FROM queue_tickets WHERE DATE(completed_at) = CURDATE() AND status = "COMPLETED"),
                 skipped_count = (SELECT COUNT(*) FROM queue_tickets WHERE DATE(completed_at) = CURDATE() AND status = "SKIPPED"),
                 avg_wait_min = (SELECT COALESCE(AVG(actual_wait_sec)/60, 0) FROM queue_tickets WHERE DATE(issued_at) = CURDATE() AND status = "COMPLETED")
             WHERE stat_date = CURDATE()'
        );
    } catch (Throwable $e) {
        error_log('[FilaQ] daily stats update failed: ' . $e->getMessage());
    }
}