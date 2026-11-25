# BCMS — Architecture Documentation

## System Architecture

BCMS follows a **Front Controller + MVC-style** architecture for simplicity with XAMPP/PHP.

```
Browser Request
      │
      ▼
 public/*.php  ← Entry point for each module (front controllers)
      │
      ├── config/bootstrap.php  ← Session, config, autoload models
      │
      ├── app/models/*.php      ← Data access (PDO + SQL)
      │
      ├── app/services/*.php    ← Business logic (calculations, validations)
      │
      └── app/views/layout/*.php ← Reusable HTML partials (header, sidebar, footer)
```

## Directory Structure

```
bcms/
├── public/               ← Apache DocumentRoot
│   ├── *.php             ← Page controllers (one per module)
│   └── assets/           ← CSS, JS, images (served directly)
│
├── app/
│   ├── models/           ← Database models (one per entity)
│   ├── views/layout/     ← Shared layout components
│   ├── services/         ← Business services (calculations)
│   └── middleware/       ← Auth checking
│
├── config/
│   ├── bootstrap.php     ← Auto-included in every page
│   ├── app.php           ← Constants and utility functions
│   └── database.php      ← PDO connection singleton + helpers
│
├── database/
│   ├── schema.sql        ← Database DDL (reproducible)
│   └── seed.sql          ← Demo data
│
└── storage/
    ├── logs/             ← Error and activity logs
    └── uploads/          ← User file uploads (protected)
```

## Request Flow

1. User opens `http://localhost/bcms/public/dashboard.php`
2. `dashboard.php` includes `config/bootstrap.php`
3. Bootstrap loads `.env`, includes `config/app.php`, `config/database.php`, auto-loads all model files
4. Page checks `requireLogin()` / `requireRole()`
5. Page instantiates needed models (e.g., `new ProjectModel()`)
6. Models execute PDO prepared statements against MySQL
7. PHP renders HTML using layout partials + inline data
8. Browser displays the page

## Security Architecture

```
All Requests
    │
    ├── Session validation (requireLogin)
    ├── Role check (requireRole / hasRole)
    ├── CSRF token validation (validateCsrf on POST)
    ├── Input sanitization (strInput, intInput, decimalInput, dateInput)
    ├── PDO prepared statements (SQL injection prevention)
    ├── htmlspecialchars output (XSS prevention)
    └── Record-level ownership check (IDOR prevention)
```

## Database Layer

- Singleton PDO connection via `db()` function
- Helper functions: `dbQuery()`, `dbQueryOne()`, `dbExecute()`, `dbInsert()`
- All queries use prepared statements with parameterized values
- MySQL views for calculated aggregates (v_material_stock, v_project_expense_totals, v_worker_attendance_summary)
- Foreign key constraints enforce data integrity
- Unique constraints prevent duplicate attendance, assignment records

## Role-Based Access Control

| Module | Admin | Client | Worker |
|--------|-------|--------|--------|
| Dashboard | Full stats | Own project stats | Own task stats |
| Projects | CRUD + all | View own only | — |
| Clients | Full CRUD | View own | — |
| Workers | Full CRUD | — | View own |
| Assignments | Manage | — | View own |
| Attendance | Mark + view all | — | View own |
| Tasks | CRUD + assign | — | View + update progress |
| Materials | Full CRUD | — | — |
| Purchases | Full CRUD | — | — |
| Material Usage | Full CRUD | — | — |
| Expenses | Full CRUD | — | — |
| Payments | Full CRUD | View own | — |
| Reports | All | — | — |
| Wages | Calculate all | — | — |
| Budget | All | — | — |

## Key Business Rules

1. **Stock Management**: `stock_quantity = SUM(purchases) - SUM(usage) + SUM(adj_in) - SUM(adj_out)`
2. **Remaining Budget**: `remaining = project.budget - SUM(expenses.amount)`
3. **Wage Calculation**: `wage = (present_days + half_days × 0.5) × daily_wage`
4. **Project Progress**: Calculated as `AVG(task.progress)` for all tasks in the project, or updated directly by admin
5. **Duplicate Prevention**: Unique constraints on `attendance(worker_id, project_id, attendance_date)` and `worker_assignments(project_id, worker_id, is_active)`
