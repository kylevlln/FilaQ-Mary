# FilaQ

A small queue management system for offices and school desks. Builds on XAMPP (PHP + MySQL).

## Run it

1. Start Apache & MySQL in XAMPP.
2. Import `sql/filaq_database.sql` via phpMyAdmin.
3. Open the folder from `htdocs`.

Default admin: `admin` / `Admin@123`

## Bits & pieces

- `admin/` — admin pages (users, counters/services, logs)
- `staff/` — queue desk (call, skip, complete)
- `customer/` — take a number, track queue
- `display.php` — live board
- `api/` — JSON endpoints
- `sql/` — database schema