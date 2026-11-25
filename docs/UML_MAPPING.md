# BCMS — UML Class Diagram Mapping

## Class-to-Implementation Mapping

| UML Class | PHP Implementation | File |
|-----------|-------------------|------|
| Client | `ClientModel` class | `app/models/ClientModel.php` |
| Project | `ProjectModel` class | `app/models/ProjectModel.php` |
| Worker | `WorkerModel` class | `app/models/WorkerModel.php` |
| Attendance | `AttendanceModel` class | `app/models/AttendanceModel.php` |
| Task | `TaskModel` class | `app/models/TaskModel.php` |
| Material | `MaterialModel` class | `app/models/MaterialModel.php` |
| Payment | `PaymentModel` class | `app/models/PaymentModel.php` |
| Expense | `ExpenseModel` class | `app/models/ExpenseModel.php` |
| Report | Multiple methods across models + `reports.php`, `wages.php`, `budget.php` |

---

## Method Mapping

### Client
| UML Method | PHP Method | File | Notes |
|-----------|-----------|------|-------|
| `addProject()` | `ProjectModel::create()` | `ProjectModel.php` | Admin creates project for client |
| `viewProject()` | `ProjectModel::getAll()` filtered by client | `ProjectModel.php` | Client views own projects |
| `makePayment()` | `PaymentModel::create()` | `PaymentModel.php` | Records payment for client's project |

### Project
| UML Method | PHP Method | File | Notes |
|-----------|-----------|------|-------|
| `createProject()` | `ProjectModel::create()` | `ProjectModel.php` | Validates all fields |
| `updateProject()` | `ProjectModel::update()` | `ProjectModel.php` | Updates project info |
| `calculateProgress()` | `ProjectModel::calculateProgressFromTasks()` | `ProjectModel.php` | AVG(task.progress) |
| `calculateRemainingBudget()` | `ProjectModel::calculateRemainingBudget()` | `ProjectModel.php` | budget - SUM(expenses) |

### Payment
| UML Method | PHP Method | File | Notes |
|-----------|-----------|------|-------|
| `recordPayment()` | `PaymentModel::create()` | `PaymentModel.php` | Includes IDOR check |

### Material
| UML Method | PHP Method | File | Notes |
|-----------|-----------|------|-------|
| `addMaterial()` | `MaterialModel::create()` | `MaterialModel.php` | Adds to catalog |
| `updateStock()` | `MaterialModel::syncStock()` | `MaterialModel.php` | Computed from purchases/usage/adjustments |
| `checkStock()` | `MaterialModel::checkSufficientStock()` | `MaterialModel.php` | Returns bool |

### Worker
| UML Method | PHP Method | File | Notes |
|-----------|-----------|------|-------|
| `assignWorker()` | `AssignmentModel::assign()` | `AssignmentModel.php` | Prevents duplicate assignment |
| `calculateWage()` | `WorkerModel::calculateWage()` | `WorkerModel.php` | working_days × daily_wage |

### Attendance
| UML Method | PHP Method | File | Notes |
|-----------|-----------|------|-------|
| `markAttendance()` | `AttendanceModel::markAttendance()` | `AttendanceModel.php` | Throws on duplicate |
| `calculateWorkingDays()` | `AttendanceModel::calculateWorkingDays()` | `AttendanceModel.php` | present + half_day×0.5 |

### Task
| UML Method | PHP Method | File | Notes |
|-----------|-----------|------|-------|
| `createTask()` | `TaskModel::create()` | `TaskModel.php` | With project + optional worker |
| `assignTask()` | `TaskModel::assignWorker()` | `TaskModel.php` | Links worker to task |
| `updateProgress()` | `TaskModel::updateProgress()` | `TaskModel.php` | Auto-sets status to completed at 100% |

### Expense
| UML Method | PHP Method | File | Notes |
|-----------|-----------|------|-------|
| `addExpense()` | `ExpenseModel::create()` | `ExpenseModel.php` | Validates type and amount |
| `calculateTotalExpense()` | `ExpenseModel::getTotalByProject()` | `ExpenseModel.php` | SUM(amount) |

### Report
| UML Method | PHP Method | File | Notes |
|-----------|-----------|------|-------|
| `generateProjectReport()` | Inline logic in `reports.php` (Tab 1) using `ProjectModel`, `ExpenseModel`, `PaymentModel` | `reports.php` | |
| `generateWorkerReport()` | Inline logic in `reports.php` (Tab 2) using `WorkerModel`, `AttendanceModel` | `reports.php` | |
| `generateExpenseReport()` | Inline logic in `reports.php` (Tab 3) using `ExpenseModel` | `reports.php` | |
| `generatePaymentReport()` | Inline logic in `reports.php` (Tab 4) using `PaymentModel` | `reports.php` | |
