-- ============================================================
-- BUILDING CONSTRUCTION MANAGEMENT SYSTEM (BCMS)
-- Database Schema — MySQL 8.0+
-- ============================================================
-- Run this file in phpMyAdmin or MySQL CLI:
--   mysql -u root -p < database/schema.sql
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- Create database
CREATE DATABASE IF NOT EXISTS `bcms_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `bcms_db`;

-- ============================================================
-- USERS (authentication for all roles)
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `user_id`      INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(150)     NOT NULL,
  `email`        VARCHAR(200)     NOT NULL UNIQUE,
  `password`     VARCHAR(255)     NOT NULL,
  `role`         ENUM('admin','client','worker') NOT NULL DEFAULT 'worker',
  `linked_id`    INT UNSIGNED     DEFAULT NULL  COMMENT 'client_id or worker_id',
  `is_active`    TINYINT(1)       NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CLIENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `clients` (
  `client_id`    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(150)     NOT NULL,
  `phone`        VARCHAR(20)      NOT NULL,
  `email`        VARCHAR(200)     NOT NULL,
  `address`      TEXT             NOT NULL,
  `created_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`client_id`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PROJECTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `projects` (
  `project_id`   INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `client_id`    INT UNSIGNED     NOT NULL,
  `project_name` VARCHAR(200)     NOT NULL,
  `location`     VARCHAR(300)     NOT NULL,
  `budget`       DECIMAL(15,2)    NOT NULL CHECK (`budget` > 0),
  `start_date`   DATE             NOT NULL,
  `end_date`     DATE             NOT NULL,
  `status`       ENUM('planning','active','on_hold','completed','cancelled') NOT NULL DEFAULT 'planning',
  `progress`     TINYINT UNSIGNED NOT NULL DEFAULT 0 CHECK (`progress` BETWEEN 0 AND 100),
  `description`  TEXT             DEFAULT NULL,
  `created_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`project_id`),
  INDEX `idx_client_id` (`client_id`),
  INDEX `idx_status` (`status`),
  CONSTRAINT `fk_project_client` FOREIGN KEY (`client_id`)
    REFERENCES `clients` (`client_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- WORKERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `workers` (
  `worker_id`    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(150)     NOT NULL,
  `phone`        VARCHAR(20)      NOT NULL,
  `skill`        VARCHAR(100)     NOT NULL,
  `daily_wage`   DECIMAL(10,2)    NOT NULL CHECK (`daily_wage` >= 0),
  `is_active`    TINYINT(1)       NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`worker_id`),
  INDEX `idx_skill` (`skill`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- WORKER ASSIGNMENTS (many-to-many: project <-> worker)
-- ============================================================
CREATE TABLE IF NOT EXISTS `worker_assignments` (
  `assignment_id`   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `project_id`      INT UNSIGNED  NOT NULL,
  `worker_id`       INT UNSIGNED  NOT NULL,
  `assigned_date`   DATE          NOT NULL,
  `end_date`        DATE          DEFAULT NULL,
  `is_active`       TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `uq_active_assignment` (`project_id`, `worker_id`, `is_active`),
  INDEX `idx_project_id` (`project_id`),
  INDEX `idx_worker_id` (`worker_id`),
  CONSTRAINT `fk_assignment_project` FOREIGN KEY (`project_id`)
    REFERENCES `projects` (`project_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_assignment_worker` FOREIGN KEY (`worker_id`)
    REFERENCES `workers` (`worker_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ATTENDANCE
-- ============================================================
CREATE TABLE IF NOT EXISTS `attendance` (
  `attendance_id`   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `worker_id`       INT UNSIGNED  NOT NULL,
  `project_id`      INT UNSIGNED  NOT NULL,
  `attendance_date` DATE          NOT NULL,
  `status`          ENUM('present','absent','half_day','holiday') NOT NULL DEFAULT 'present',
  `remarks`         VARCHAR(255)  DEFAULT NULL,
  `recorded_by`     INT UNSIGNED  DEFAULT NULL COMMENT 'user_id who recorded',
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `uq_attendance` (`worker_id`, `project_id`, `attendance_date`),
  INDEX `idx_worker_id` (`worker_id`),
  INDEX `idx_project_id` (`project_id`),
  INDEX `idx_date` (`attendance_date`),
  CONSTRAINT `fk_attendance_worker` FOREIGN KEY (`worker_id`)
    REFERENCES `workers` (`worker_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_attendance_project` FOREIGN KEY (`project_id`)
    REFERENCES `projects` (`project_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TASKS
-- ============================================================
CREATE TABLE IF NOT EXISTS `tasks` (
  `task_id`      INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `project_id`   INT UNSIGNED     NOT NULL,
  `worker_id`    INT UNSIGNED     DEFAULT NULL,
  `task_name`    VARCHAR(200)     NOT NULL,
  `description`  TEXT             DEFAULT NULL,
  `status`       ENUM('pending','in_progress','completed','on_hold','cancelled') NOT NULL DEFAULT 'pending',
  `progress`     TINYINT UNSIGNED NOT NULL DEFAULT 0 CHECK (`progress` BETWEEN 0 AND 100),
  `due_date`     DATE             DEFAULT NULL,
  `created_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`task_id`),
  INDEX `idx_project_id` (`project_id`),
  INDEX `idx_worker_id` (`worker_id`),
  INDEX `idx_status` (`status`),
  CONSTRAINT `fk_task_project` FOREIGN KEY (`project_id`)
    REFERENCES `projects` (`project_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_task_worker` FOREIGN KEY (`worker_id`)
    REFERENCES `workers` (`worker_id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MATERIALS (catalog)
-- ============================================================
CREATE TABLE IF NOT EXISTS `materials` (
  `material_id`    INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `material_name`  VARCHAR(200)   NOT NULL,
  `unit`           VARCHAR(50)    NOT NULL COMMENT 'e.g. kg, litre, bag, piece',
  `stock_quantity` DECIMAL(12,3)  NOT NULL DEFAULT 0 CHECK (`stock_quantity` >= 0),
  `low_stock_threshold` DECIMAL(12,3) NOT NULL DEFAULT 10,
  `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`material_id`),
  INDEX `idx_material_name` (`material_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MATERIAL PURCHASES (stock in)
-- ============================================================
CREATE TABLE IF NOT EXISTS `material_purchases` (
  `purchase_id`   INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `project_id`    INT UNSIGNED    NOT NULL,
  `material_id`   INT UNSIGNED    NOT NULL,
  `quantity`      DECIMAL(12,3)   NOT NULL CHECK (`quantity` > 0),
  `price`         DECIMAL(12,2)   NOT NULL CHECK (`price` >= 0),
  `purchase_date` DATE            NOT NULL,
  `supplier`      VARCHAR(200)    DEFAULT NULL,
  `notes`         TEXT            DEFAULT NULL,
  `created_by`    INT UNSIGNED    DEFAULT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`purchase_id`),
  INDEX `idx_project_id` (`project_id`),
  INDEX `idx_material_id` (`material_id`),
  INDEX `idx_purchase_date` (`purchase_date`),
  CONSTRAINT `fk_purchase_project` FOREIGN KEY (`project_id`)
    REFERENCES `projects` (`project_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_purchase_material` FOREIGN KEY (`material_id`)
    REFERENCES `materials` (`material_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MATERIAL USAGE (stock out)
-- ============================================================
CREATE TABLE IF NOT EXISTS `material_usage` (
  `usage_id`      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `project_id`    INT UNSIGNED    NOT NULL,
  `material_id`   INT UNSIGNED    NOT NULL,
  `quantity`      DECIMAL(12,3)   NOT NULL CHECK (`quantity` > 0),
  `usage_date`    DATE            NOT NULL,
  `description`   VARCHAR(300)    DEFAULT NULL,
  `created_by`    INT UNSIGNED    DEFAULT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`usage_id`),
  INDEX `idx_project_id` (`project_id`),
  INDEX `idx_material_id` (`material_id`),
  CONSTRAINT `fk_usage_project` FOREIGN KEY (`project_id`)
    REFERENCES `projects` (`project_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_usage_material` FOREIGN KEY (`material_id`)
    REFERENCES `materials` (`material_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- EXPENSES
-- ============================================================
CREATE TABLE IF NOT EXISTS `expenses` (
  `expense_id`    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `project_id`    INT UNSIGNED    NOT NULL,
  `expense_type`  ENUM('material','labour','equipment','transport','other') NOT NULL,
  `amount`        DECIMAL(12,2)   NOT NULL CHECK (`amount` > 0),
  `expense_date`  DATE            NOT NULL,
  `description`   VARCHAR(300)    DEFAULT NULL,
  `created_by`    INT UNSIGNED    DEFAULT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`expense_id`),
  INDEX `idx_project_id` (`project_id`),
  INDEX `idx_expense_date` (`expense_date`),
  INDEX `idx_expense_type` (`expense_type`),
  CONSTRAINT `fk_expense_project` FOREIGN KEY (`project_id`)
    REFERENCES `projects` (`project_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PAYMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id`    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `project_id`    INT UNSIGNED    NOT NULL,
  `client_id`     INT UNSIGNED    NOT NULL,
  `amount`        DECIMAL(12,2)   NOT NULL CHECK (`amount` > 0),
  `payment_date`  DATE            NOT NULL,
  `payment_type`  ENUM('advance','milestone','final','partial') NOT NULL DEFAULT 'partial',
  `payment_mode`  ENUM('cash','bank_transfer','cheque','upi','other') NOT NULL DEFAULT 'cash',
  `reference_no`  VARCHAR(100)    DEFAULT NULL,
  `notes`         TEXT            DEFAULT NULL,
  `created_by`    INT UNSIGNED    DEFAULT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  INDEX `idx_project_id` (`project_id`),
  INDEX `idx_client_id` (`client_id`),
  INDEX `idx_payment_date` (`payment_date`),
  CONSTRAINT `fk_payment_project` FOREIGN KEY (`project_id`)
    REFERENCES `projects` (`project_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_payment_client` FOREIGN KEY (`client_id`)
    REFERENCES `clients` (`client_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STOCK ADJUSTMENTS (authorized corrections)
-- ============================================================
CREATE TABLE IF NOT EXISTS `stock_adjustments` (
  `adjustment_id` INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `material_id`   INT UNSIGNED    NOT NULL,
  `adjustment_type` ENUM('increase','decrease') NOT NULL,
  `quantity`      DECIMAL(12,3)   NOT NULL CHECK (`quantity` > 0),
  `reason`        VARCHAR(300)    NOT NULL,
  `adjusted_by`   INT UNSIGNED    NOT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`adjustment_id`),
  INDEX `idx_material_id` (`material_id`),
  CONSTRAINT `fk_adjustment_material` FOREIGN KEY (`material_id`)
    REFERENCES `materials` (`material_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_adjustment_user` FOREIGN KEY (`adjusted_by`)
    REFERENCES `users` (`user_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CSRF TOKENS (optional server-side tracking)
-- ============================================================
CREATE TABLE IF NOT EXISTS `csrf_tokens` (
  `token_id`   INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED   NOT NULL,
  `token`      VARCHAR(64)    NOT NULL,
  `created_at` TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP      NOT NULL,
  PRIMARY KEY (`token_id`),
  INDEX `idx_token` (`token`),
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ACTIVITY LOG
-- ============================================================
CREATE TABLE IF NOT EXISTS `activity_log` (
  `log_id`      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED    DEFAULT NULL,
  `action`      VARCHAR(100)    NOT NULL,
  `module`      VARCHAR(50)     NOT NULL,
  `record_id`   INT UNSIGNED    DEFAULT NULL,
  `description` VARCHAR(500)    DEFAULT NULL,
  `ip_address`  VARCHAR(45)     DEFAULT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_module` (`module`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- VIEWS for calculated fields
-- ============================================================

-- Project expense totals (pre-aggregated to avoid cartesian product)
CREATE OR REPLACE VIEW `v_project_expense_totals` AS
SELECT
  p.project_id,
  p.project_name,
  p.budget,
  COALESCE(e.total_expenses, 0) AS total_expenses,
  p.budget - COALESCE(e.total_expenses, 0) AS remaining_budget,
  COALESCE(py.total_payments, 0) AS total_payments
FROM projects p
LEFT JOIN (
  SELECT project_id, SUM(amount) AS total_expenses
  FROM expenses
  GROUP BY project_id
) e ON e.project_id = p.project_id
LEFT JOIN (
  SELECT project_id, SUM(amount) AS total_payments
  FROM payments
  GROUP BY project_id
) py ON py.project_id = p.project_id;

-- Material stock (computed from purchases, usage, adjustments)
CREATE OR REPLACE VIEW `v_material_stock` AS
SELECT
  m.material_id,
  m.material_name,
  m.unit,
  m.low_stock_threshold,
  COALESCE(p.total_purchased, 0) AS total_purchased,
  COALESCE(u.total_used, 0) AS total_used,
  COALESCE(a_in.total_adj_in, 0) AS total_adj_in,
  COALESCE(a_out.total_adj_out, 0) AS total_adj_out,
  (
    COALESCE(p.total_purchased, 0)
    - COALESCE(u.total_used, 0)
    + COALESCE(a_in.total_adj_in, 0)
    - COALESCE(a_out.total_adj_out, 0)
  ) AS computed_stock
FROM materials m
LEFT JOIN (SELECT material_id, SUM(quantity) AS total_purchased FROM material_purchases GROUP BY material_id) p ON p.material_id = m.material_id
LEFT JOIN (SELECT material_id, SUM(quantity) AS total_used FROM material_usage GROUP BY material_id) u ON u.material_id = m.material_id
LEFT JOIN (SELECT material_id, SUM(quantity) AS total_adj_in FROM stock_adjustments WHERE adjustment_type = 'increase' GROUP BY material_id) a_in ON a_in.material_id = m.material_id
LEFT JOIN (SELECT material_id, SUM(quantity) AS total_adj_out FROM stock_adjustments WHERE adjustment_type = 'decrease' GROUP BY material_id) a_out ON a_out.material_id = m.material_id;

-- Worker attendance summary
CREATE OR REPLACE VIEW `v_worker_attendance_summary` AS
SELECT
  a.worker_id,
  a.project_id,
  w.name AS worker_name,
  w.daily_wage,
  COUNT(CASE WHEN a.status = 'present' THEN 1 END) AS present_days,
  COUNT(CASE WHEN a.status = 'half_day' THEN 1 END) AS half_days,
  COUNT(CASE WHEN a.status = 'absent' THEN 1 END) AS absent_days,
  (
    COUNT(CASE WHEN a.status = 'present' THEN 1 END)
    + COUNT(CASE WHEN a.status = 'half_day' THEN 1 END) * 0.5
  ) AS working_days,
  (
    COUNT(CASE WHEN a.status = 'present' THEN 1 END)
    + COUNT(CASE WHEN a.status = 'half_day' THEN 1 END) * 0.5
  ) * w.daily_wage AS calculated_wage
FROM attendance a
JOIN workers w ON w.worker_id = a.worker_id
GROUP BY a.worker_id, a.project_id, w.name, w.daily_wage;
