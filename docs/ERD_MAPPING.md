# BCMS — ERD Mapping Document

## ERD Entity-to-Table Mapping

| ERD Entity | MySQL Table | Primary Key | Foreign Keys | Notes |
|-----------|-------------|-------------|--------------|-------|
| CLIENT | `clients` | `client_id` (UNSIGNED INT AUTO_INCREMENT) | — | |
| PROJECT | `projects` | `project_id` (UNSIGNED INT AUTO_INCREMENT) | `client_id → clients.client_id` | FK: RESTRICT on delete |
| WORKER | `workers` | `worker_id` (UNSIGNED INT AUTO_INCREMENT) | — | Added `is_active` for soft delete |
| WORKER_ASSIGNMENT | `worker_assignments` | `assignment_id` (UNSIGNED INT AUTO_INCREMENT) | `project_id → projects.project_id`, `worker_id → workers.worker_id` | UNIQUE on (project_id, worker_id, is_active) |
| ATTENDANCE | `attendance` | `attendance_id` (UNSIGNED INT AUTO_INCREMENT) | `worker_id → workers.worker_id`, `project_id → projects.project_id` | UNIQUE on (worker_id, project_id, attendance_date) |
| TASK | `tasks` | `task_id` (UNSIGNED INT AUTO_INCREMENT) | `project_id → projects.project_id`, `worker_id → workers.worker_id` | FK worker: SET NULL on delete |
| MATERIAL | `materials` | `material_id` (UNSIGNED INT AUTO_INCREMENT) | — | Added `low_stock_threshold` |
| MATERIAL_PURCHASE | `material_purchases` | `purchase_id` (UNSIGNED INT AUTO_INCREMENT) | `project_id → projects.project_id`, `material_id → materials.material_id` | |
| MATERIAL_USAGE | `material_usage` | `usage_id` (UNSIGNED INT AUTO_INCREMENT) | `project_id → projects.project_id`, `material_id → materials.material_id` | |
| EXPENSE | `expenses` | `expense_id` (UNSIGNED INT AUTO_INCREMENT) | `project_id → projects.project_id` | |
| PAYMENT | `payments` | `payment_id` (UNSIGNED INT AUTO_INCREMENT) | `project_id → projects.project_id`, `client_id → clients.client_id` | |

## ERD Field Mapping

### CLIENT → clients
| ERD Field | Column | Type | Constraints |
|-----------|--------|------|-------------|
| client_id (PK) | `client_id` | INT UNSIGNED | AUTO_INCREMENT, NOT NULL |
| name | `name` | VARCHAR(150) | NOT NULL |
| phone | `phone` | VARCHAR(20) | NOT NULL |
| email | `email` | VARCHAR(200) | NOT NULL |
| address | `address` | TEXT | NOT NULL |

### PROJECT → projects
| ERD Field | Column | Type | Constraints |
|-----------|--------|------|-------------|
| project_id (PK) | `project_id` | INT UNSIGNED | AUTO_INCREMENT, NOT NULL |
| client_id (FK) | `client_id` | INT UNSIGNED | NOT NULL, FK → clients |
| project_name | `project_name` | VARCHAR(200) | NOT NULL |
| location | `location` | VARCHAR(300) | NOT NULL |
| budget | `budget` | DECIMAL(15,2) | NOT NULL, CHECK > 0 |
| start_date | `start_date` | DATE | NOT NULL |
| end_date | `end_date` | DATE | NOT NULL |
| status | `status` | ENUM | NOT NULL, DEFAULT 'planning' |
| progress | `progress` | TINYINT UNSIGNED | NOT NULL, CHECK 0-100 |

### WORKER → workers
| ERD Field | Column | Type | Constraints |
|-----------|--------|------|-------------|
| worker_id (PK) | `worker_id` | INT UNSIGNED | AUTO_INCREMENT, NOT NULL |
| name | `name` | VARCHAR(150) | NOT NULL |
| phone | `phone` | VARCHAR(20) | NOT NULL |
| skill | `skill` | VARCHAR(100) | NOT NULL |
| daily_wage | `daily_wage` | DECIMAL(10,2) | NOT NULL, CHECK >= 0 |

### WORKER_ASSIGNMENT → worker_assignments
| ERD Field | Column | Type | Constraints |
|-----------|--------|------|-------------|
| assignment_id (PK) | `assignment_id` | INT UNSIGNED | AUTO_INCREMENT, NOT NULL |
| project_id (FK) | `project_id` | INT UNSIGNED | NOT NULL, FK → projects |
| worker_id (FK) | `worker_id` | INT UNSIGNED | NOT NULL, FK → workers |
| assigned_date | `assigned_date` | DATE | NOT NULL |

### ATTENDANCE → attendance
| ERD Field | Column | Type | Constraints |
|-----------|--------|------|-------------|
| attendance_id (PK) | `attendance_id` | INT UNSIGNED | AUTO_INCREMENT, NOT NULL |
| worker_id (FK) | `worker_id` | INT UNSIGNED | NOT NULL, FK → workers |
| project_id (FK) | `project_id` | INT UNSIGNED | NOT NULL, FK → projects |
| attendance_date | `attendance_date` | DATE | NOT NULL |
| status | `status` | ENUM('present','absent','half_day','holiday') | NOT NULL |

### TASK → tasks
| ERD Field | Column | Type | Constraints |
|-----------|--------|------|-------------|
| task_id (PK) | `task_id` | INT UNSIGNED | AUTO_INCREMENT, NOT NULL |
| project_id (FK) | `project_id` | INT UNSIGNED | NOT NULL, FK → projects |
| worker_id (FK) | `worker_id` | INT UNSIGNED | NULL, FK → workers |
| task_name | `task_name` | VARCHAR(200) | NOT NULL |
| description | `description` | TEXT | NULL |
| status | `status` | ENUM | NOT NULL |
| progress | `progress` | TINYINT UNSIGNED | NOT NULL, CHECK 0-100 |

### MATERIAL → materials
| ERD Field | Column | Type | Constraints |
|-----------|--------|------|-------------|
| material_id (PK) | `material_id` | INT UNSIGNED | AUTO_INCREMENT, NOT NULL |
| material_name | `material_name` | VARCHAR(200) | NOT NULL |
| unit | `unit` | VARCHAR(50) | NOT NULL |
| stock_quantity | `stock_quantity` | DECIMAL(12,3) | NOT NULL, DEFAULT 0, CHECK >= 0 |

### MATERIAL_PURCHASE → material_purchases
| ERD Field | Column | Type | Constraints |
|-----------|--------|------|-------------|
| purchase_id (PK) | `purchase_id` | INT UNSIGNED | AUTO_INCREMENT, NOT NULL |
| project_id (FK) | `project_id` | INT UNSIGNED | NOT NULL, FK → projects |
| material_id (FK) | `material_id` | INT UNSIGNED | NOT NULL, FK → materials |
| quantity | `quantity` | DECIMAL(12,3) | NOT NULL, CHECK > 0 |
| price | `price` | DECIMAL(12,2) | NOT NULL, CHECK >= 0 |
| purchase_date | `purchase_date` | DATE | NOT NULL |

### MATERIAL_USAGE → material_usage
| ERD Field | Column | Type | Constraints |
|-----------|--------|------|-------------|
| usage_id (PK) | `usage_id` | INT UNSIGNED | AUTO_INCREMENT, NOT NULL |
| project_id (FK) | `project_id` | INT UNSIGNED | NOT NULL, FK → projects |
| material_id (FK) | `material_id` | INT UNSIGNED | NOT NULL, FK → materials |
| quantity | `quantity` | DECIMAL(12,3) | NOT NULL, CHECK > 0 |
| usage_date | `usage_date` | DATE | NOT NULL |

### EXPENSE → expenses
| ERD Field | Column | Type | Constraints |
|-----------|--------|------|-------------|
| expense_id (PK) | `expense_id` | INT UNSIGNED | AUTO_INCREMENT, NOT NULL |
| project_id (FK) | `project_id` | INT UNSIGNED | NOT NULL, FK → projects |
| expense_type | `expense_type` | ENUM | NOT NULL |
| amount | `amount` | DECIMAL(12,2) | NOT NULL, CHECK > 0 |
| expense_date | `expense_date` | DATE | NOT NULL |
| description | `description` | VARCHAR(300) | NULL |

### PAYMENT → payments
| ERD Field | Column | Type | Constraints |
|-----------|--------|------|-------------|
| payment_id (PK) | `payment_id` | INT UNSIGNED | AUTO_INCREMENT, NOT NULL |
| project_id (FK) | `project_id` | INT UNSIGNED | NOT NULL, FK → projects |
| client_id (FK) | `client_id` | INT UNSIGNED | NOT NULL, FK → clients |
| amount | `amount` | DECIMAL(12,2) | NOT NULL, CHECK > 0 |
| payment_date | `payment_date` | DATE | NOT NULL |
| payment_type | `payment_type` | ENUM | NOT NULL |

## Relationships

| Relationship | Cardinality | Implemented By |
|-------------|-------------|----------------|
| Client → Projects | 1:many | `projects.client_id → clients.client_id` |
| Project → Worker (via Assignment) | many:many | `worker_assignments` table |
| Worker → Attendance | 1:many | `attendance.worker_id → workers.worker_id` |
| Project → Attendance | 1:many | `attendance.project_id → projects.project_id` |
| Project → Task | 1:many | `tasks.project_id → projects.project_id` |
| Worker → Task | 1:many | `tasks.worker_id → workers.worker_id` |
| Project → Material (via Purchase) | many:many | `material_purchases` table |
| Project → Material (via Usage) | many:many | `material_usage` table |
| Project → Expense | 1:many | `expenses.project_id → projects.project_id` |
| Project → Payment | 1:many | `payments.project_id → projects.project_id` |
| Client → Payment | 1:many | `payments.client_id → clients.client_id` |
