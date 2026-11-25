# BCMS — Assumptions & Deviations from Diagrams

## Source of Truth
The diagrams in ALL_DIAGRAMS_BCMS.pdf (DFD Level 0, DFD Level 1, ERD, UML Class, Use Case) and Final_Synopsis.pdf are the primary requirements. All assumptions documented here are the minimum reasonable interpretations where a diagram element was ambiguous.

---

## Assumptions Made

### 1. User Authentication / Login System
**Assumption:** The diagrams specify three actors (Admin/Project Manager, Client, Worker) but do not show a `users` table in the ERD.
**Decision:** Added a `users` table with `role` field (`admin`, `client`, `worker`) and `linked_id` to reference `client_id` or `worker_id`. This is the minimum addition needed for authentication.

### 2. Admin vs Project Manager
**Assumption:** The diagrams use "Admin / Project Manager" as a single actor.
**Decision:** Implemented as a single `admin` role. A separate supervisor/accountant role has NOT been added (as required by the specification: "Do not give clients or workers administrative access").

### 3. Project Progress Calculation
**Assumption:** The UML Class diagram shows `calculateProgress()` on the Project class, but the exact formula is not specified.
**Decision:** Two methods implemented:
  1. **Automatic**: `AVG(task.progress)` for all non-cancelled tasks in the project
  2. **Manual**: Admin can directly set a progress percentage (0-100)
  This is documented in code comments.

### 4. Remaining Budget Formula
**Assumption:** "Remaining budget = project budget minus expenses" — the exact expense scope is unspecified.
**Decision:** `remaining_budget = project.budget - SUM(expenses.amount WHERE project_id = project.project_id)`. All expense types (material, labour, equipment, transport, other) count toward the total.

### 5. Worker Wage Calculation
**Assumption:** The diagram shows `calculateWage()` on the Worker class. The formula for half-days is not explicitly stated.
**Decision:** `working_days = present_days + (half_days × 0.5)`, `total_wage = working_days × daily_wage`. This is the standard industry formula.

### 6. Material Stock Tracking
**Assumption:** The ERD shows `stock_quantity` on the Material entity, but the update mechanism is not specified.
**Decision:** `stock_quantity` is kept synchronized with: `SUM(purchases) - SUM(usage) + SUM(adj_in) - SUM(adj_out)`. A MySQL view `v_material_stock` provides the computed stock. The `stock_quantity` field in the `materials` table is updated (synced) after every purchase, usage, or adjustment operation. Direct edits to stock_quantity are not allowed through the UI.

### 7. `payment_type` Values
**Assumption:** The ERD shows `payment_type` on the Payment entity but doesn't enumerate values.
**Decision:** Used ENUM: `advance`, `milestone`, `final`, `partial`. These are standard construction payment terms.

### 8. `expense_type` Values
**Assumption:** ERD shows `expense_type` on Expense but doesn't enumerate values.
**Decision:** Used ENUM: `material`, `labour`, `equipment`, `transport`, `other`. Aligned with construction industry norms.

### 9. Project Status Values
**Assumption:** ERD shows `status` on Project but doesn't enumerate values.
**Decision:** Used ENUM: `planning`, `active`, `on_hold`, `completed`, `cancelled`.

### 10. Duplicate Attendance Correction
**Assumption:** The specification says "Prevent accidental duplicate entries; implement authorized correction."
**Decision:** A unique constraint on `(worker_id, project_id, attendance_date)` prevents duplicates. Admins can edit existing attendance records (update status + remarks). The original record is updated, not deleted and re-created, to maintain audit trail.

### 11. Worker Assignment Uniqueness
**Assumption:** "Prevent invalid or duplicate active assignments" — interpretation needed.
**Decision:** A unique constraint on `(project_id, worker_id, is_active)` where `is_active=1` prevents a worker from being assigned twice to the same active project. A worker can be re-assigned after being unassigned (`is_active=0`).

### 12. Stock Adjustment Table
**Assumption:** Not in the original ERD, but necessary to fulfill "Update stock through transactions, not arbitrary edits" and "Material stock must reconcile with purchases, usage, returns and approved adjustments."
**Decision:** Added `stock_adjustments` table for authorized admin adjustments. This is an extension required by the business rules, consistent with the ERD spirit.

### 13. Activity Log Table
**Assumption:** Not in the original ERD, but required by: "Log important business changes without logging secrets."
**Decision:** Added `activity_log` table. This is a supporting infrastructure table, not a core entity.

### 14. `users` Table Password Storage
**Assumption:** The ERD does not show a `users` table, only actors.
**Decision:** Passwords are stored as bcrypt hashes (cost=12) using PHP `password_hash()`. Passwords are NEVER stored in plaintext, logged, or transmitted in URLs.

### 15. Client-Project Relationship on Payment
**Assumption:** The Payment entity in ERD has both `project_id` and `client_id`. The sync between them is not explicit.
**Decision:** On payment creation, the server verifies that `project.client_id === payment.client_id` before inserting. This prevents IDOR attacks where someone tries to record a payment for a client that doesn't own the project.

---

## Minor Structural Decisions

- `daily_wage` stored as `DECIMAL(10,2)` on `workers` table (not shown explicitly in ERD field types)
- All monetary amounts use `DECIMAL(12,2)` for precision
- All quantities use `DECIMAL(12,3)` to support fractional units (e.g., 1.5 bags)
- `TIMESTAMP` used for audit fields (`created_at`, `updated_at`)
- `DATE` used for business dates (attendance_date, purchase_date, etc.)
- Soft delete (`is_active=0`) used for workers and assignments instead of hard delete
- MySQL views created for: `v_material_stock`, `v_project_expense_totals`, `v_worker_attendance_summary`
