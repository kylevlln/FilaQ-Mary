-- ============================================================
-- FilaQ: A Smart Queue Management System
-- Database Schema (Relational & Normalized)
-- For XAMPP / MySQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS filaq_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE filaq_db;

-- ------------------------------------------------------------
-- users: all accounts (admin, staff, customers)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name    VARCHAR(120) NOT NULL,
  username     VARCHAR(60)  NOT NULL UNIQUE,
  email        VARCHAR(160) NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,
  phone        VARCHAR(30)  NULL,
  role         ENUM('ADMIN','STAFF','CUSTOMER') NOT NULL DEFAULT 'CUSTOMER',
  status       ENUM('PENDING','ACTIVE','SUSPENDED','INACTIVE') NOT NULL DEFAULT 'PENDING',
  counter_id   INT UNSIGNED NULL,
  last_login   DATETIME NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- counters: service windows / stations staffed by staff
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS counters (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(80) NOT NULL,
  location   VARCHAR(120) NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- services: types of service offered at counters
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS services (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name               VARCHAR(100) NOT NULL,
  description        TEXT NULL,
  avg_service_time_sec  INT UNSIGNED NOT NULL DEFAULT 300,  -- seconds per customer
  is_active          TINYINT(1) NOT NULL DEFAULT 1,
  created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- queue_tickets: one row per issued queue number
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS queue_tickets (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_code   VARCHAR(20) NOT NULL UNIQUE,      -- e.g. GEN-0042
  user_id       INT UNSIGNED NULL,                -- who claimed it (may be guest)
  service_id    INT UNSIGNED NOT NULL,
  counter_id    INT UNSIGNED NULL,
  priority      ENUM('NORMAL','PRIORITY') NOT NULL DEFAULT 'NORMAL',
  status        ENUM('WAITING','CALLED','SERVING','COMPLETED','SKIPPED','CANCELLED')
                NOT NULL DEFAULT 'WAITING',
  customer_name VARCHAR(120) NULL,
  contact       VARCHAR(30) NULL,
  called_at     DATETIME NULL,
  serve_started_at DATETIME NULL,
  completed_at  DATETIME NULL,
  skip_count    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  issued_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estimated_wait_sec INT UNSIGNED NULL,           -- snapshot estimate when issued
  actual_wait_sec    INT UNSIGNED NULL,           -- issued -> serving started (seconds)
  actual_service_sec INT UNSIGNED NULL,           -- serving started -> completed (seconds)
  session_code  VARCHAR(12) NULL,                 -- short code for guest tracking/offline
  INDEX idx_status (status),
  INDEX idx_issued (issued_at),
  INDEX idx_service (service_id),
  INDEX idx_counter (counter_id),
  INDEX idx_user (user_id),
  CONSTRAINT fk_ticket_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ticket_counter FOREIGN KEY (counter_id) REFERENCES counters(id) ON DELETE SET NULL,
  CONSTRAINT fk_ticket_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- activity_logs: audit trail for users & staff actions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  action     VARCHAR(80) NOT NULL,
  details    TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_created (created_at),
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- system_settings: key/value settings for the system
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS system_settings (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key  VARCHAR(80) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  updated_by   INT UNSIGNED NULL,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- counters_services: many-to-many linking counters to services
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS counter_services (
  counter_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (counter_id, service_id),
  CONSTRAINT fk_cs_counter FOREIGN KEY (counter_id) REFERENCES counters(id) ON DELETE CASCADE,
  CONSTRAINT fk_cs_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- announcements: messages broadcast to users / queue display
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS announcements (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(140) NOT NULL,
  message    TEXT NOT NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_announce_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- daily_stats: rollup for admin charts (one row per day per metric)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS daily_stats (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stat_date   DATE NOT NULL,
  issued_count INT UNSIGNED NOT NULL DEFAULT 0,
  served_count INT UNSIGNED NOT NULL DEFAULT 0,
  skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
  avg_wait_min DECIMAL(6,1) NULL,
  UNIQUE KEY uq_date (stat_date)
) ENGINE=InnoDB;

-- ============================================================
-- Seed Data
-- ============================================================

-- Default admin account. Password: Admin@123
INSERT INTO users (full_name, username, email, password, role, status)
VALUES ('System Administrator', 'admin', 'admin@filaq.local',
  '$2y$10$EmC8p3OgLOQUTdSRU0u1Zed3lgLB.L.ZAdq/VHPlCzz2DnH6lHBH6',
  'ADMIN', 'ACTIVE');

-- Default counters
INSERT INTO counters (name, location) VALUES
  ('Counter 1 - General', 'Front Office'),
  ('Counter 2 - Registrar', 'Front Office'),
  ('Counter 3 - Cashier', 'Finance Wing');

-- Default services
INSERT INTO services (name, description, avg_service_time_sec) VALUES
  ('General Inquiry',   'General questions and information', 300),
  ('Document Request',  'Request transcripts, certificates', 600),
  ('Payment / Billing', 'Settle dues and payments', 360),
  ('Registration',      'New enrollees and renewal', 720);

-- Link counters to services
INSERT INTO counter_services (counter_id, service_id) VALUES
  (1, 1), (1, 2),
  (2, 2), (2, 4),
  (3, 3), (3, 1);

-- Default system settings
INSERT INTO system_settings (setting_key, setting_value) VALUES
  ('org_name',         'FilaQ Demo Office'),
  ('queue_prefix',     'FQ'),
  ('open_time',        '08:00'),
  ('close_time',       '17:00'),
  ('announcement',     'Welcome to FilaQ! Please take a ticket and have a great day.'),
  ('allow_registration', '1'),
  ('estimate_lookback_minutes', '60');