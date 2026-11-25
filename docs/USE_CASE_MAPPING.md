# BCMS — Use Case Mapping Document

## All Use Cases — Actor and Implementation

| # | Use Case | Authorized Actor(s) | Implementing Page | HTTP Method | Notes |
|---|----------|--------------------|--------------------|-------------|-------|
| 1 | **Login / Authentication** | Admin, Client, Worker | `login.php` | GET + POST | CSRF protected, session regeneration |
| 2 | **Logout** | Admin, Client, Worker | `logout.php` | GET | Destroys session |
| 3 | **View Dashboard** | Admin, Client, Worker | `dashboard.php` | GET | Role-filtered content |
| 4 | **Manage Clients** | Admin | `clients.php` | GET + POST | CRUD: list, add, edit, delete |
| 5 | **Manage Projects** | Admin | `projects.php` | GET + POST | CRUD + status/progress update |
| 6 | **View Project Details** | Admin, Client (own) | `project_view.php` | GET | Tabs: tasks, workers, attendance, expenses, payments |
| 7 | **Manage Workers** | Admin | `workers.php` | GET + POST | CRUD + deactivate |
| 8 | **View Worker Details** | Admin, Worker (own) | `worker_view.php` | GET | Attendance, wage, assignments |
| 9 | **Assign Workers** | Admin | `assignments.php` | GET + POST | Assign/unassign to project |
| 10 | **Manage Attendance** | Admin | `attendance.php` | GET + POST | Daily sheet, bulk mark |
| 11 | **View Own Attendance** | Worker | `attendance.php` | GET | Filtered by linked_id |
| 12 | **Manage Tasks** | Admin | `tasks.php` | GET + POST | CRUD + assign |
| 13 | **Assign Tasks** | Admin | `tasks.php` (action=assign_worker) | POST | Links worker to task |
| 14 | **Update Task Progress** | Admin, Worker (own tasks) | `tasks.php` (action=update_progress) | POST | 0-100%, auto-complete at 100 |
| 15 | **Update Project Progress** | Admin | `projects.php` (action=update_progress) | POST | Admin can directly set |
| 16 | **Manage Materials** | Admin | `materials.php` | GET + POST | Catalog CRUD + stock adjustment |
| 17 | **Manage Material Purchase** | Admin | `purchases.php` | GET + POST | Records purchase, syncs stock |
| 18 | **Record Material Usage** | Admin | `usage.php` | GET + POST | Decreases stock, checks sufficiency |
| 19 | **Track Material Stock** | Admin | `materials.php` | GET | Real-time stock from view |
| 20 | **Manage Expenses** | Admin | `expenses.php` | GET + POST | CRUD with project filter |
| 21 | **Calculate Total Expenses** | Admin | `expenses.php`, `budget.php`, `reports.php` | GET | SUM from expense records |
| 22 | **Manage Payments** | Admin | `payments.php` | GET + POST | Record, view, filter |
| 23 | **View Own Payments** | Client | `payments.php` | GET | Filtered by client linked_id |
| 24 | **Calculate Remaining Budget** | Admin | `budget.php`, `project_view.php` | GET | budget - SUM(expenses) |
| 25 | **Calculate Worker Wages** | Admin | `wages.php`, `worker_view.php` | GET | From attendance records |
| 26 | **Generate Project Report** | Admin | `reports.php` (Tab 1) | GET | All projects with financials |
| 27 | **Generate Worker Report** | Admin | `reports.php` (Tab 2) | GET | Attendance + wages |
| 28 | **Generate Expense Report** | Admin | `reports.php` (Tab 3) | GET | By type + date range |
| 29 | **Generate Payment Report** | Admin | `reports.php` (Tab 4) | GET | By project + client |

## Permission Enforcement

Every protected page starts with:
```php
requireLogin();
requireRole('admin');  // or 'client', 'worker' as needed
```

Record-level ownership checks:
- **Client** pages: `WHERE client_id = currentUser()['linked_id']`
- **Worker** pages: `WHERE worker_id = currentUser()['linked_id']`
- **Payment client match**: Verified on `POST /payments.php` before insert

CSRF tokens: All POST actions have `<?= csrfField() ?>` in forms and `validateCsrf()` in handlers.
