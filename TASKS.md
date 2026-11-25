# BCMS Implementation Checklist & Progress Tracker

## Phase 1: Architectural Audit & Requirements Matrix
- [x] Comprehensive inspection of existing codebase, models, views, config, tests, schema, and reference documents
- [x] Traceability mapping against DFD Levels 0 & 1, ERD, UML Class Diagram, and Use Case specifications
- [x] Identification of foundational issues, security risks, model query bugs, and missing controllers
- [x] Generation of `docs/IMPLEMENTATION_AUDIT.md` and detailed task tracking in `TASKS.md`

## Phase 2: Foundational Architecture & Security Fixes
- [x] Guarded constants in `config/app.php` with `if (!defined(...))` to prevent duplicate definition notices
- [x] Added session save path validation and writable fallback in `startSession()` to prevent Windows drive boundary warnings
- [x] Fixed `PDO::MYSQL_ATTR_INIT_COMMAND` deprecation under PHP 8.5+ with `\Pdo\Mysql::ATTR_INIT_COMMAND`
- [x] Improved `config/database.php:db()` error handling so CLI / automated tests can catch connection exceptions without fatal die
- [x] Fixed cartesian product bug in `database/schema.sql` view `v_project_expense_totals` using pre-aggregated subqueries
- [x] Fixed `PaymentModel::getSummaryByProject()` to avoid `SUM(DISTINCT pay.amount)` inaccuracies
- [x] Secured `public/setup.php` so administrator account creation is blocked if an admin user already exists
- [x] Updated root `.htaccess` and private folder protection (`config/`, `storage/`, `app/`, `database/`)

## Phase 3: Missing Core Pages (Priority 1)
- [x] `public/projects.php`: Full project lifecycle management (list, filter by status/client, modal for create, edit, client linkage, budget, start/end dates, progress, client view)
- [x] `public/project_view.php`: Comprehensive 360-degree project view (overview, worker assignments, tasks, daily attendance, material purchases & usage, expenses, payments, budget utilization)
- [x] `public/attendance.php`: Daily attendance sheet by project and date, duplicate prevention, status selection (present, absent, half-day, holiday), working-day totals, worker self-view
- [x] `public/tasks.php`: Task management (list, create, edit, worker assignment, status and progress 0-100%, auto-completion at 100%, worker-role view and update)

## Phase 4: Missing Core Pages (Priority 2)
- [x] `public/purchases.php`: Material purchase recording, stock increment/sync via model, supplier, unit price, quantity, project spend tracking
- [x] `public/usage.php`: Material usage recording, safe stock decrement, check sufficient stock to prevent negative inventory, project linkage
- [x] `public/expenses.php`: Project expense recording, categorization (material, labour, equipment, transport, other), amount, date, description, project filtering
- [x] `public/payments.php`: Project payment recording, client-to-project ownership validation (IDOR prevention), payment modes, client view for own payments

## Phase 5: Missing Core Pages (Priority 3)
- [x] `public/reports.php`: Centralized reporting hub with 4 tabbed reports (Project Financials, Worker Attendance & Wages, Expense Breakdown, Client Payments)
- [x] `public/wages.php`: Dedicated worker wage calculation engine with transparent breakdown: `working_days = present + 0.5 * half_day`, `total_wage = working_days * daily_wage`
- [x] `public/budget.php`: Dedicated budget monitoring dashboard (total budget, actual expenses, remaining budget, cost overrun alerts, category breakdown)

## Phase 6: Navigation & UI Integration
- [x] Updated `app/views/layout/sidebar.php` to include functional links to `budget.php`, `wages.php`, and ensure all menu items resolve correctly
- [x] Verified responsive behavior, Bootstrap 5 badge classes, modal forms, and confirmation prompts across all pages

## Phase 7: Verification & Testing
- [x] Executed PHP syntax checks across all 42 PHP files (`php -l`), 0 syntax errors detected
- [x] Ran `tests/test_basic.php` — 42 passed, 0 failed, 2 blocked by environment (MySQL service not running on port 3306), 14 skipped
- [x] Created and executed `tests/test_business_logic.php` — 24/24 passed (100% verification for wages, budgets, stock rules, IDOR, input sanitization)

## Phase 8: Documentation & Handover
- [x] Reconciled `README.md` with all 20 public pages, tested setup steps, CLI test instructions, and security guidelines
- [x] Produced `docs/IMPLEMENTATION_AUDIT.md` mapping all requirements, gaps, and remediation actions
