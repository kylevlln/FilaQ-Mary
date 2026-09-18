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
 * Format: "prefix-NNN" e.g. COR-014.
 *
 * Uses an atomic upsert on ticket_sequences (one row per service per day):
 *   INSERT ... ON DUPLICATE KEY UPDATE next_no = LAST_INSERT_ID(next_no + 1)
 * then reads the new value via lastInsertId(). Two concurrent requests can
 * therefore never be handed the same number (the old COUNT() approach could).
 */
function next_ticket_code(int $serviceId, string $prefix = 'GEN'): string
{
    $stmt = db()->prepare(
        'INSERT INTO ticket_sequences (service_id, day, next_no)
         VALUES (?, ?, LAST_INSERT_ID(1))
         ON DUPLICATE KEY UPDATE next_no = LAST_INSERT_ID(next_no + 1)'
    );
    $stmt->execute([$serviceId, date('Y-m-d')]);
    return sprintf('%s-%03d', $prefix, (int) db()->lastInsertId());
}

/**
 * Fallback used when a service has no code_prefix yet: the old behaviour,
 * i.e. the first letters of its name ("Documentary Requirements" -> "DOC").
 */
function derive_code_prefix(string $name): string
{
    $candidate = strtoupper(preg_replace('/[^A-Z0-9]+/', '', $name));
    return strtoupper(substr(($candidate !== '' ? $candidate : 'SVC'), 0, 3));
}

/**
 * Accepts the admin-entered prefix (or nothing) and returns a safe ticket
 * prefix: 2-4 uppercase letters/numbers, falling back to the name-derived
 * one when the value is missing or out of range.
 */
function normalize_code_prefix($prefix, string $name): string
{
    $p = strtoupper(trim((string) $prefix));
    $p = preg_replace('/[^A-Z0-9]/', '', $p);
    $len = strlen($p);
    if ($len < 2 || $len > 4) {
        return derive_code_prefix($name);
    }
    return $p;
}

/* ------------------------------------------------------------------ *
 *  Wait-time estimation (queueing-theory inspired)
 * ------------------------------------------------------------------ */

/**
 * Wait estimates for every active service, computed in ONE pass.
 * Equivalent to calling estimate_wait_minutes_for_service() per service but
 * without the per-service query loop (N+1): all counting is done in two
 * grouped queries, then combined in PHP.
 *
 * Approach (unchanged logic):
 *   1. Service's configured average service time (sec).
 *   2. Multiply by tickets already waiting ahead for that service.
 *   3. When there is recent completed data, use the live average throughput
 *      instead of the configured default, so estimates adapt to reality.
 */
function service_wait_estimate_map(): array
{
    $services = fetch_all('SELECT id, avg_service_time_sec FROM services WHERE is_active = 1');

    $aheadRows = fetch_all(
        'SELECT service_id, COUNT(*) AS c
         FROM queue_tickets
         WHERE status IN ("WAITING","CALLED")
         GROUP BY service_id'
    );
    $ahead = [];
    foreach ($aheadRows as $r) {
        $ahead[(int) $r['service_id']] = (int) $r['c'];
    }

    // Live throughput: average seconds per served ticket over the last 90 minutes.
    $thpRows = fetch_all(
        'SELECT service_id, AVG(actual_wait_sec) AS avgw, COUNT(*) AS c
         FROM queue_tickets
         WHERE status = "COMPLETED" AND serve_started_at >= DATE_SUB(NOW(), INTERVAL 90 MINUTE)
         GROUP BY service_id'
    );
    $liveSec = [];
    foreach ($thpRows as $r) {
        if ((int) $r['c'] > 0 && (int) $r['avgw'] > 0) {
            $liveSec[(int) $r['service_id']] = (float) $r['avgw'];
        }
    }

    $out = [];
    foreach ($services as $s) {
        $id = (int) $s['id'];
        $effectiveSec = $liveSec[$id] ?? max(60, (int) $s['avg_service_time_sec']);
        $out[$id] = (int) ceil(($ahead[$id] ?? 0) * $effectiveSec / 60.0);
    }
    return $out;
}

/** Estimated wait (minutes) for a NEW ticket issued to one service. */
function estimate_wait_minutes_for_service(int $serviceId): int
{
    return service_wait_estimate_map()[$serviceId] ?? 0;
}

/**
 * Estimated wait (minutes) for ONE specific ticket given its position in line.
 * All tickets ahead of it share the same service, so one query suffices.
 */
function remaining_wait_for_ticket(int $ticketId): int
{
    $ticket = fetch_one('SELECT * FROM queue_tickets WHERE id = ?', [$ticketId]);
    if ($ticket === null) {
        return 0;
    }

    $row = fetch_one(
        'SELECT COUNT(t.id) AS ahead, MAX(s.avg_service_time_sec) AS avg_sec
         FROM queue_tickets t
         JOIN services s ON s.id = t.service_id
         WHERE t.service_id = ? AND t.id < ? AND t.status IN ("WAITING","CALLED")',
        [$ticket['service_id'], $ticketId]
    );

    $totalSec = (int) ($row['ahead'] ?? 0) * max(60, (int) ($row['avg_sec'] ?? 300));
    return (int) ceil($totalSec / 60.0);
}

/* ------------------------------------------------------------------ *
 *  Queue lists
 * ------------------------------------------------------------------ */

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

    $code = next_ticket_code($serviceId, normalize_code_prefix($service['code_prefix'] ?? '', $service['name']));
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
    if ($ticket['status'] !== 'SERVING') {
        throw new RuntimeException('Only a ticket currently being served can be marked complete.');
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