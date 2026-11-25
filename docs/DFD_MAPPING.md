# BCMS — DFD Mapping Document

## DFD Level 0 (Context Diagram)

The system boundary is the **Building Construction Management System**.

| Actor | Input to System | Output from System | Implemented By |
|-------|----------------|-------------------|----------------|
| **Client** | Client details, Payment information | Project status, Payment information | `clients.php`, `payments.php`, `projects.php` (client view), `dashboard.php` (client role) |
| **Admin / Project Manager** | Project data, Worker data, Material data, Task data, Expense data, Payment data | Dashboard info, Reports, Progress, Budget, Stock info | All admin pages, `dashboard.php`, `reports.php`, `budget.php` |
| **Worker** | Attendance, Work information | Task assignments, Wage information | `tasks.php` (worker view), `attendance.php` (worker view), `worker_view.php` |

## DFD Level 1 Processes

| Process | Data Store | Admin Page | Client Page | Worker Page | Model |
|---------|-----------|------------|-------------|-------------|-------|
| **1. Project Management** | Project Database (`projects` table) | `projects.php`, `project_view.php` | `projects.php` (filtered) | — | `ProjectModel.php` |
| **2. Worker Management** | Worker Database (`workers` table) | `workers.php`, `worker_view.php` | — | `worker_view.php` (own) | `WorkerModel.php`, `AssignmentModel.php` |
| **3. Material Management** | Material Database (`materials`, `material_purchases`, `material_usage` tables) | `materials.php`, `purchases.php`, `usage.php` | — | — | `MaterialModel.php`, `PurchaseModel.php`, `UsageModel.php` |
| **4. Task & Progress Management** | Task & Progress Database (`tasks` table) | `tasks.php` (all) | — | `tasks.php` (own) | `TaskModel.php` |
| **5. Expense & Payment Management** | Expense & Payment Database (`expenses`, `payments` tables) | `expenses.php`, `payments.php` | `payments.php` (own) | — | `ExpenseModel.php`, `PaymentModel.php` |
| **6. Report Management** | Reads from all databases | `reports.php`, `wages.php`, `budget.php` | — | — | All models (read-only) |

## Data Flows Implemented

### Client → System
- **Client Details**: `POST /clients.php` (action=add_client / edit_client)
- **Payment Information**: `POST /payments.php` (action=record_payment, client-authorized)

### System → Client
- **Project Status**: `GET /projects.php` (filtered by client's linked_id)
- **Payment Information**: `GET /payments.php` (filtered by client)
- **Project Progress**: `GET /project_view.php?project_id=X` (progress bar + status)

### Admin → System
- **Project Data**: `POST /projects.php` (create, update, status, progress)
- **Worker Data**: `POST /workers.php`, `POST /assignments.php`
- **Material Data**: `POST /materials.php`, `POST /purchases.php`, `POST /usage.php`
- **Task Data**: `POST /tasks.php` (create, assign, update)
- **Expense Data**: `POST /expenses.php`
- **Payment Data**: `POST /payments.php`

### System → Admin
- **Dashboard Info**: `GET /dashboard.php` (live stats, charts)
- **Reports**: `GET /reports.php` (project, worker, expense, payment reports)
- **Progress**: `GET /project_view.php`, `GET /projects.php` (progress bars)
- **Budget Info**: `GET /budget.php` (remaining budget, utilization)
- **Stock Info**: `GET /materials.php` (stock levels, low stock alerts)

### Worker ↔ System
- **Task Assignment** (System → Worker): `GET /tasks.php` (worker's assigned tasks)
- **Wage Information** (System → Worker): `GET /worker_view.php` (calculated wage)
- **Attendance** (Worker → System): Marked by admin in `POST /attendance.php`
- **Work Information** (Worker → System): `POST /tasks.php` (update_progress, update_status)
