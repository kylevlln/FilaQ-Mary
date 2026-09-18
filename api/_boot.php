<?php
/**
 * FilaQ API bootstrap — shared JSON helpers.
 * Every API endpoint responds with `{ok, data?, message?}`.
 * Kept in one place so response shape and body parsing behave identically everywhere.
 */

header('Content-Type: application/json; charset=utf-8');

/** Send a JSON response and stop. */
function json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

/**
 * Read the request body. Accepts either a JSON payload or a regular POST form.
 * Throws on malformed JSON when a body is present.
 */
function body_data(): array
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