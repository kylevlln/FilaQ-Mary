-- 002_service_code_prefix.sql
-- FilaQ migration: relational ticket-code prefixes.
--   1. Adds services.code_prefix (VARCHAR(4)) so each service's ticket prefix
--      is an explicit, admin-chosen code instead of a truncation of its name.
--   2. Backfills existing rows with the prefix the old logic would have derived
--      (first letters of the service name), so current ticket codes stay valid.
-- Additive only; no existing column values are changed apart from the new one.

ALTER TABLE services
  ADD COLUMN code_prefix VARCHAR(4) NOT NULL DEFAULT '' AFTER name;

UPDATE services
SET code_prefix = UPPER(LEFT(
      CASE WHEN REGEXP_REPLACE(UPPER(name), '[^A-Z0-9]+', '') = '' THEN 'SVC'
           ELSE REGEXP_REPLACE(UPPER(name), '[^A-Z0-9]+', '')
      END, 3))
WHERE code_prefix = '';