-- 001_optimize.sql
-- FilaQ optimization migration (relational only).
--   1. Atomic per-service-per-day ticket numbering (removes COUNT+1 race).
--   2. UNIQUE(session_code) — "track my ticket" currently full-scans.
--      Existing duplicate codes (from data made before this migration) are
--      disambiguated with a random 4-hex-char suffix before the key is added.
--   3. Composite indexes for the wait / position / queue queries.
-- Additive only; no existing column values are changed.

-- --- 1. Disambiguate any pre-existing duplicate session codes -------------
CREATE TEMPORARY TABLE tmp_dup_session AS
  SELECT session_code, MIN(id) AS keep_id
  FROM queue_tickets
  WHERE session_code IS NOT NULL
  GROUP BY session_code
  HAVING COUNT(*) > 1;

UPDATE queue_tickets t
JOIN tmp_dup_session d ON d.session_code = t.session_code AND t.id <> d.keep_id
SET t.session_code = CONCAT(t.session_code, SUBSTRING(MD5(RAND()), 1, 4));

DROP TEMPORARY TABLE tmp_dup_session;

-- --- 2. Atomic ticket sequences (one row per service per day) --------------
CREATE TABLE IF NOT EXISTS ticket_sequences (
  service_id INT UNSIGNED NOT NULL,
  day        DATE        NOT NULL,
  next_no    INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (service_id, day),
  CONSTRAINT fk_seq_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- --- 3. Queue indexes -------------------------------------------------------
ALTER TABLE queue_tickets
  ADD UNIQUE KEY uq_session (session_code),
  ADD KEY idx_svc_status_id (service_id, status, id),
  ADD KEY idx_counter_status (counter_id, status),
  ADD KEY idx_status_issued (status, issued_at);