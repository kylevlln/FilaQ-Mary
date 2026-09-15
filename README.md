# FilaQ — A Smart Queue Management System

> Be seen. Be served. Beautifully.

FilaQ is a smart queue management system for small businesses, school
registrars, and office front desks. It runs **entirely on XAMPP** — no
internet connection required — and uses a **relational, normalized MySQL
database**.

## Features

| Area | What it does |
| --- | --- |
| **Queue Numbers** | One-click ticket issuance with per-service numbering (`GI-001`, `REG-012`) |
| **Estimated Wait Times** | Live estimates blended from configured service times and real recent throughput |
| **Queue Management** | Staff call, skip, start, and complete tickets (keyboard shortcuts: N / C / S) |
| **Queue Tracking** | Customers & guests check their spot with a short tracking code |
| **Live Board** | Animated public display of "now serving" and upcoming numbers |
| **Records** | Daily stats, wait-time history, served/skipped counts for admin review |
| **Admin Monitoring** | Activity log of every login, ticket action, and setting change |
| **User Management** | Approval workflow: staff accounts start **PENDING** until an admin activates them |
| **Privacy & Terms** | Dedicated pages (linked from the landing page footer) |

## Design

- **Colors**: muted pastel palette — pastel orange `#f4c9a8`, dark pastel
  orange `#c97b4a`, muted cyan `#9cc9c9`, muted pink `#e3b6c3` on warm paper `#fffaf4`.
- **Fonts**: Playfair Display (elegant serif) for headings, Poppins for body.
- **Motion**: animated landing hero, floating gradient blobs, staggered card
  entrances, pulse on live tickets, graceful toasts for every action.

## Requirements

- XAMPP (Apache + PHP 7.4+) with MySQL/MariaDB running
- A modern browser (Chrome, Edge, Firefox)

## Installation (XAMPP)

1. Copy this folder into `C:\xampp\htdocs\` so the app is at
   `C:\xampp\htdocs\FilaQ Mary\`.
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Import the database:
   - Open <http://localhost/phpmyadmin>
   - `Import` → choose `sql/filaq_database.sql` → **Go**
4. Open the app: <http://localhost/FilaQ%20Mary/>

## Default Admin Account

| Username | Password |
| --- | --- |
| `admin` | `Admin@123` |

**Change this password immediately after your first login.**

## Create a staff account

Everyone starts as a **Customer** (active immediately) or **Staff**
(pending). To go through the approval flow:

1. Register as a **Staff** account — status will be `PENDING`.
2. Sign in as `admin` and open **Users**.
3. Click **Approve** and assign the staff member to a counter.
4. The staff member can now sign in and use the **Queue Desk**.

## Project structure

```
FilaQ Mary/
├── admin/           Admin pages (dashboard, users, counters/services, logs)
├── api/             JSON endpoints (queue.php, staff.php, admin.php)
├── assets/          css/ and js/ (design system + front-end logic)
├── config/          Database connection + helpers (PDO, prepared statements)
├── customer/        Take a number & track queue pages
├── includes/        Shared queue business logic
├── logs/            PHP error log (blocked from web)
├── sql/             Normalized database schema + seed data
├── staff/           Queue Desk
├── display.php      Public live board
├── index.php        Landing page (big animated FilaQ title)
├── login.php / register.php / logout.php
├── privacy.php / terms.php
└── .htaccess        Apache hardening
```

## Offline / local operation

FilaQ binds to `localhost` only and stores everything in the local MariaDB
instance. No external CDN scripts are loaded at runtime; the Google Fonts
`@import` in `assets/css/style.css` is optional — the design falls back
gracefully to Georgia/Arial if you are offline.

## Notes for the demo/test

- Wait-time estimates are snapshot values saved with each ticket.
- The **Live Board** (`display.php`) auto-refreshes every 5 seconds.
- The Queue Desk auto-refreshes every 8 seconds and plays a soft "ding"
  when a new ticket is called.