# BCMS — Comprehensive Implementation Audit & Gap Analysis

**Date:** 2026-11-03  
**Auditor:** Senior PHP/MySQL Architect & QA Engineer  
**System:** Building Construction Management System (BCMS)  
**Workspace:** `d:\bcms`

---

## 1. Executive Summary

This audit evaluates the current state of the Building Construction Management System against the academic requirements specified in the project synopsis and five architectural diagrams (DFD Level 0/1, ERD, UML Class Diagram, and Use Case Diagram).

The audit verified that all 12 database models (`UserModel`, `ClientModel`, `ProjectModel`, `WorkerModel`, `AssignmentModel`, `AttendanceModel`, `TaskModel`, `MaterialModel`, `PurchaseModel`, `UsageModel`, `ExpenseModel`, `PaymentModel`) are present and well-structured. However, **11 critical public application controllers and user-facing modules are missing**, preventing users from managing projects, recording attendance, assigning tasks, tracking material purchases/usage, recording expenses and payments, calculating wages and budgets, and generating reports.

Additionally, foundational issues in configuration management, session handling on Windows drive boundaries, PDO driver attributes under PHP 8.5+, SQL cartesian joins in database views, and setup wizard security were identified and scheduled for remediation.

---

## 2. Requirement Traceability Matrix

| Requirement / Module | Primary File(s) | Diagram Reference | Status | Audit Findings & Required Action |
|---|---|---|---|---|
| **Authentication & Session** | `public/login.php`, `public/logout.php`, `UserModel.php` | Use Cases 1, 2 | **Implemented** | Working; session regeneration and password verification intact. |
| **System Setup Wizard** | `public/setup.php` | Architecture | **Partially Implemented (Security Risk)** | Step 2 lacks a check to prevent unauthorized admin creation once system is already initialized. |
| **Executive Dashboard** | `public/dashboard.php` | DFD 0, DFD 1 (Process 1-6), Use Case 3 | **Partially Implemented** | Code is complete, but navigation links to uncreated pages produce 404s. |
| **Client Management** | `public/clients.php`, `ClientModel.php` | DFD 1 (Proc 1), ERD `clients`, Use Case 4 | **Implemented** | Full CRUD, search, validation, CSRF, and activity logging functional. |
| **Project Management** | `public/projects.php`, `ProjectModel.php` | DFD 1 (Proc 1), ERD `projects`, Use Cases 5, 15 | **Missing** | Needs project list, creation modal, client linkage, budget, date validation, status/progress updates, client-role filtering. |
| **Project Details View** | `public/project_view.php`, Multiple Models | DFD 1 (Proc 1), Use Case 6 | **Missing** | Needs multi-tab view: Overview, Tasks, Worker Assignments, Daily Attendance, Materials (Purchases/Usage), Expenses, Payments, Financial Budget breakdown. |
| **Worker Management** | `public/workers.php`, `WorkerModel.php` | DFD 1 (Proc 2), ERD `workers`, Use Case 7 | **Implemented** | Full CRUD, wage validation, skill filtering, toggle status. |
| **Worker Profile & Details** | `public/worker_view.php`, `WorkerModel.php` | DFD 1 (Proc 2), Use Cases 8, 25 | **Implemented** | Worker details, assignment history, attendance breakdown, wage calculations. |
| **Worker Assignments** | `public/assignments.php`, `AssignmentModel.php` | DFD 1 (Proc 2), ERD `worker_assignments`, Use Case 9 | **Implemented** | Active assignment tracking, duplicate prevention, unassign with date validation. |
| **Attendance Management** | `public/attendance.php`, `AttendanceModel.php` | DFD 1 (Proc 2), ERD `attendance`, Use Cases 10, 11 | **Missing** | Daily attendance sheet by project and date, duplicate prevention, present/absent/half-day, working days derivation, worker self-view. |
| **Task & Progress Management** | `public/tasks.php`, `TaskModel.php` | DFD 1 (Proc 4), ERD `tasks`, Use Cases 12, 13, 14 | **Missing** | Task creation, worker assignment, status transitions, progress updates (0-100%), auto-complete at 100%, worker-role view and update. |
| **Material Catalog** | `public/materials.php`, `MaterialModel.php` | DFD 1 (Proc 3), ERD `materials`, Use Cases 16, 19 | **Implemented** | Catalog CRUD, stock threshold alerts, inventory adjustments. |
| **Material Purchases** | `public/purchases.php`, `PurchaseModel.php` | DFD 1 (Proc 3), ERD `material_purchases`, Use Case 17 | **Missing** | Record purchases, increment/sync stock, supplier and price validation, project spend tracking. |
| **Material Usage** | `public/usage.php`, `UsageModel.php` | DFD 1 (Proc 3), ERD `material_usage`, Use Case 18 | **Missing** | Record material consumption, decrement stock safely, check sufficient stock to prevent negative inventory, project linkage. |
| **Project Expenses** | `public/expenses.php`, `ExpenseModel.php` | DFD 1 (Proc 5), ERD `expenses`, Use Cases 20, 21 | **Missing** | Record expenses by category (material, labour, equipment, transport, other), project filtering, total expense calculations. |
| **Project Payments** | `public/payments.php`, `PaymentModel.php` | DFD 1 (Proc 5), ERD `payments`, Use Cases 22, 23 | **Missing** | Record client payments, enforce client-to-project ownership (IDOR check), payment modes, client view for own payments. |
| **Reports Engine** | `public/reports.php`, All Models | DFD 1 (Proc 6), Use Cases 26, 27, 28, 29 | **Missing** | 4 distinct reports: Project Financial, Worker Attendance & Wage, Expense Category Analysis, Client Payments. |
| **Wage Calculation Module** | `public/wages.php`, `WorkerModel.php`, `AttendanceModel.php` | DFD 1 (Proc 2), Use Case 25 | **Missing** | Dedicated wage calculation screen with project/worker/date filtering, formula transparency: present (1.0) + half_day (0.5) × daily_wage. |
| **Budget & Cost Tracking** | `public/budget.php`, `ProjectModel.php`, `ExpenseModel.php` | DFD 1 (Proc 1, 5), Use Cases 21, 24, 30 | **Missing** | Dedicated budget utilization dashboard: budget vs actual expenses, remaining budget, overrun indicators, cost breakdown. |

---

## 3. Foundational & Architectural Deficiencies

### 3.1. Session Initialization on Windows Drive Boundaries
* **Issue:** PHP CLI executed from drive `D:` fails with `session_start(): open(\xampp\tmp\sess_..., O_RDWR) failed: No such file or directory` when `session.save_path` is relative in `php.ini`.
* **Fix:** Update `config/app.php:startSession()` to detect invalid/non-existent session save paths and fallback safely to `sys_get_temp_dir()` or `STORAGE_PATH . '/sessions'`.

### 3.2. Duplicate Constant Warnings
* **Issue:** `CURRENCY_SYMBOL`, `APP_NAME`, and related constants were defined with bare `define()` calls without `if (!defined(...))` guards, causing warnings when scripts or tests include `bootstrap.php` or `app.php`.
* **Fix:** Wrap all constant declarations in `config/app.php` with `if (!defined(...))` guards.

### 3.3. Database Initialization & PHP 8.5 Compatibility
* **Issue:** `PDO::MYSQL_ATTR_INIT_COMMAND` is deprecated in PHP 8.5+ in favor of `\Pdo\Mysql::ATTR_INIT_COMMAND`.
* **Issue:** `config/database.php:db()` terminated execution via `die(...)` containing HTML, which prevented CLI tests and callers from catching `Throwable` and reporting graceful failures.
* **Fix:** Check `defined('\Pdo\Mysql::ATTR_INIT_COMMAND')` before falling back to `PDO::MYSQL_ATTR_INIT_COMMAND`. In `db()`, throw `PDOException` or only die when not in CLI mode.

### 3.4. Cartesian Product in Database View `v_project_expense_totals`
* **Issue:** `database/schema.sql` defines `v_project_expense_totals` by performing simultaneous `LEFT JOIN expenses` and `LEFT JOIN payments` on `projects`. When a project has $M$ expenses and $N$ payments, the result set contains $M \times N$ rows, artificially multiplying `SUM(e.amount)` and `SUM(py.amount)`.
* **Fix:** Refactor the view to pre-aggregate expenses and payments in independent subqueries before joining with `projects`.

### 3.5. Duplicate Amount Inaccuracy in `PaymentModel::getSummaryByProject`
* **Issue:** `PaymentModel::getSummaryByProject` uses `SUM(DISTINCT pay.amount)`, which drops legitimate identical payments (e.g., two milestone payments of identical value).
* **Fix:** Use independent subqueries or explicit scalar sums for total payments.

### 3.6. Setup Wizard Security
* **Issue:** `public/setup.php` allows unrestricted POST requests to Step 2 to create administrator accounts at any time.
* **Fix:** Add lock check: if an administrator user already exists in the `users` table or `storage/installed.lock` exists, block account creation and require administrative authentication.

### 3.7. Apache Direct Access & Root `.htaccess`
* **Issue:** Root `.htaccess` contained blanket `Deny from all`, which breaks Apache setups pointing DocumentRoot to the project parent or root folder.
* **Fix:** Provide clean rewrite to `public/` and deny direct access to dotfiles, `.env`, `config/`, `app/`, `database/`, and `storage/`.

---

## 4. Implementation Plan & Priority Order

1. **Phase 2 — Foundational Fixes:**
   - Patch `config/app.php` (constants guards, robust session fallback).
   - Patch `config/database.php` (PHP 8.5 driver attribute, non-fatal CLI error handling).
   - Patch `database/schema.sql` (cartesian product in `v_project_expense_totals`).
   - Patch `PaymentModel.php` (`SUM(DISTINCT)` removal).
   - Secure `public/setup.php` (admin lock).
   - Update `.htaccess` rules across root and subdirectories.

2. **Phase 3 — Core Priority 1 Pages:**
   - `public/projects.php`
   - `public/project_view.php`
   - `public/attendance.php`
   - `public/tasks.php`

3. **Phase 3 — Core Priority 2 Pages:**
   - `public/purchases.php`
   - `public/usage.php`
   - `public/expenses.php`
   - `public/payments.php`

4. **Phase 3 — Core Priority 3 Pages:**
   - `public/reports.php`
   - `public/wages.php`
   - `public/budget.php`

5. **Phase 4 & 5 — Verification & Database Audit:**
   - Review and verify business rules (stock, duplicate attendance, wage calculations, budget calculations).
   - Ensure reproducible schema migration notes.

6. **Phase 6 — Navigation & UI Polish:**
   - Update `app/views/layout/sidebar.php` with all module links.
   - Verify responsive tables, empty states, and badge styling.

7. **Phase 7 & 8 — Security & Testing:**
   - Run PHP syntax checks on all files.
   - Run and enhance test suite (`tests/test_basic.php` and comprehensive business logic tests).

8. **Phase 9 — Documentation:**
   - Update `README.md` and `TASKS.md`.

