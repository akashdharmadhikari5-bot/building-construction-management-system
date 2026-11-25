<?php
/**
 * BCMS — Project Expenses Management
 * Record and analyze project operational expenditures across materials, labor, equipment, transport, and utilities.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireRole(ROLE_ADMIN);

$expenseModel = new ExpenseModel();
$projectModel = new ProjectModel();

// ── Handle POST Actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = strInput($_POST['action'] ?? '', 50);

    // 1. ADD EXPENSE
    if ($action === 'add') {
        $projectId   = intInput($_POST['project_id'] ?? null, 1);
        $expenseType = strInput($_POST['expense_type'] ?? '', 50);
        $amount      = decimalInput($_POST['amount'] ?? null);
        $expenseDate = dateInput($_POST['expense_date'] ?? date('Y-m-d'));
        $description = strInput($_POST['description'] ?? '', 300);

        if (!$projectId) {
            setFlash('error', 'Please select a valid construction project.');
        } elseif (!in_array($expenseType, ExpenseModel::TYPES, true)) {
            setFlash('error', 'Please select a valid expense category.');
        } elseif ($amount === null || $amount <= 0.0) {
            setFlash('error', 'Expense amount must be greater than zero.');
        } elseif (!$expenseDate) {
            setFlash('error', 'A valid expense date is required.');
        } else {
            try {
                $expenseId = $expenseModel->create([
                    'project_id'   => $projectId,
                    'expense_type' => $expenseType,
                    'amount'       => $amount,
                    'expense_date' => $expenseDate,
                    'description'  => $description ?: null,
                ]);

                logActivity('create', 'expenses', $expenseId, "Recorded {$expenseType} expense: " . formatCurrency($amount));
                setFlash('success', 'Project expense recorded successfully!');
            } catch (Throwable $e) {
                setFlash('error', 'Error recording expense: ' . $e->getMessage());
            }
        }
        redirect('/expenses.php' . ($projectId ? "?project_id={$projectId}" : ''));
    }

    // 2. EDIT EXPENSE
    if ($action === 'edit') {
        $expenseId   = intInput($_POST['expense_id'] ?? null, 1);
        $projectId   = intInput($_POST['project_id'] ?? null, 1);
        $expenseType = strInput($_POST['expense_type'] ?? '', 50);
        $amount      = decimalInput($_POST['amount'] ?? null);
        $expenseDate = dateInput($_POST['expense_date'] ?? date('Y-m-d'));
        $description = strInput($_POST['description'] ?? '', 300);

        if (!$expenseId || !$projectId) {
            setFlash('error', 'Invalid expense or project ID.');
        } elseif (!in_array($expenseType, ExpenseModel::TYPES, true)) {
            setFlash('error', 'Invalid expense category.');
        } elseif ($amount === null || $amount <= 0.0) {
            setFlash('error', 'Expense amount must be greater than zero.');
        } elseif (!$expenseDate) {
            setFlash('error', 'A valid expense date is required.');
        } else {
            try {
                $expenseModel->update($expenseId, [
                    'project_id'   => $projectId,
                    'expense_type' => $expenseType,
                    'amount'       => $amount,
                    'expense_date' => $expenseDate,
                    'description'  => $description ?: null,
                ]);

                logActivity('update', 'expenses', $expenseId, "Updated expense #{$expenseId}");
                setFlash('success', 'Expense updated successfully!');
            } catch (Throwable $e) {
                setFlash('error', 'Error updating expense: ' . $e->getMessage());
            }
        }
        redirect('/expenses.php');
    }

    // 3. DELETE EXPENSE
    if ($action === 'delete') {
        $expenseId = intInput($_POST['expense_id'] ?? null, 1);
        if (!$expenseId) {
            setFlash('error', 'Invalid expense ID.');
        } else {
            try {
                $expenseModel->delete($expenseId);
                logActivity('delete', 'expenses', $expenseId, "Deleted expense #{$expenseId}");
                setFlash('success', 'Expense entry removed successfully.');
            } catch (Throwable $e) {
                setFlash('error', 'Error deleting expense: ' . $e->getMessage());
            }
        }
        redirect('/expenses.php');
    }
}

// ── GET: Filters & Listing ────────────────────────────────────────────────────
$filters = [];
$projectFilter = intInput($_GET['project_id'] ?? null, 1);
$typeFilter    = strInput($_GET['expense_type'] ?? '', 50);
$fromDate      = dateInput($_GET['from_date'] ?? null);
$toDate        = dateInput($_GET['to_date'] ?? null);
$searchQuery   = strInput($_GET['search'] ?? '', 100);

if ($projectFilter) $filters['project_id']   = $projectFilter;
if ($typeFilter)    $filters['expense_type'] = $typeFilter;
if ($fromDate)      $filters['from_date']    = $fromDate;
if ($toDate)        $filters['to_date']      = $toDate;
if ($searchQuery)   $filters['search']       = $searchQuery;

$expenses      = [];
$projects      = [];
$totalAmount   = 0.0;
$catBreakdown  = [
    'material'  => 0.0,
    'labour'    => 0.0,
    'equipment' => 0.0,
    'transport' => 0.0,
    'other'     => 0.0,
];

try {
    $expenses = $expenseModel->getAll($filters);
    $projects = $projectModel->getAll();

    foreach ($expenses as $ex) {
        $amt = (float)$ex['amount'];
        $totalAmount += $amt;
        $type = $ex['expense_type'];
        if (isset($catBreakdown[$type])) {
            $catBreakdown[$type] += $amt;
        }
    }
} catch (Throwable $e) {
    setFlash('error', 'Error loading expenses: ' . $e->getMessage());
}

$pageTitle = 'Project Expenses';
$activeNav = 'expenses';

require_once VIEW_PATH . '/layout/header.php';
require_once VIEW_PATH . '/layout/sidebar.php';
require_once VIEW_PATH . '/layout/topnav.php';
?>

<div class="bcms-content">
  <!-- Page Header -->
  <div class="bcms-page-header d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
        </ol>
      </nav>
      <h2 class="h4 mb-0 fw-bold"><?= e($pageTitle) ?></h2>
      <p class="text-muted small mb-0">Record all site expenses, categorize by trade, and analyze costs against project budget.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
      <i class="bi bi-plus-circle me-1"></i>Record New Expense
    </button>
  </div>

  <!-- Flash Messages -->
  <?php if ($flashSuccess = getFlash('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bi bi-check-circle-fill me-2"></i><?= e($flashSuccess) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if ($flashError = getFlash('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($flashError) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- KPI Cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-danger bg-opacity-10 text-danger me-3">
            <i class="bi bi-cash-stack fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Total Filtered Spend</div>
            <div class="fs-4 fw-bold text-danger"><?= formatCurrency($totalAmount) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary me-3">
            <i class="bi bi-box-seam fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Material Costs</div>
            <div class="fs-5 fw-bold text-dark"><?= formatCurrency($catBreakdown['material']) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success me-3">
            <i class="bi bi-people fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Labour Costs</div>
            <div class="fs-5 fw-bold text-dark"><?= formatCurrency($catBreakdown['labour']) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-warning bg-opacity-10 text-warning me-3">
            <i class="bi bi-truck fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Equip & Transport</div>
            <div class="fs-5 fw-bold text-dark"><?= formatCurrency($catBreakdown['equipment'] + $catBreakdown['transport'] + $catBreakdown['other']) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Search & Filter Bar -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <form method="GET" action="<?= APP_URL ?>/expenses.php" class="row g-3 align-items-center">
        <div class="col-md-3">
          <select name="project_id" class="form-select">
            <option value="">All Projects</option>
            <?php foreach ($projects as $pr): ?>
              <option value="<?= $pr['project_id'] ?>" <?= $projectFilter === (int)$pr['project_id'] ? 'selected' : '' ?>>
                <?= e($pr['project_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="expense_type" class="form-select">
            <option value="">All Categories</option>
            <?php foreach (ExpenseModel::TYPES as $ty): ?>
              <option value="<?= $ty ?>" <?= $typeFilter === $ty ? 'selected' : '' ?>><?= ucfirst($ty) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <input type="date" name="from_date" class="form-control" value="<?= e($fromDate ?? '') ?>" placeholder="From date">
        </div>
        <div class="col-md-2">
          <input type="date" name="to_date" class="form-control" value="<?= e($toDate ?? '') ?>" placeholder="To date">
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Filter</button>
          <a href="<?= APP_URL ?>/expenses.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
        </div>
      </form>
    </div>
  </div>

  <!-- Expenses Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between">
      <h5 class="mb-0 fw-bold">Expenses Ledger (<?= count($expenses) ?>)</h5>
    </div>
    <div class="card-body p-0">
      <?php if (empty($expenses)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-receipt fs-2"></i>
          <h5 class="mt-3">No expenses found</h5>
          <p class="small">Record project expenses to track expenditures against your budget.</p>
          <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="bi bi-plus-circle me-1"></i>Record First Expense
          </button>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-4">Date</th>
                <th>Category</th>
                <th>Amount</th>
                <th>Project Site</th>
                <th>Description</th>
                <th class="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($expenses as $ex): ?>
                <tr>
                  <td class="ps-4 fw-semibold text-nowrap"><?= formatDate($ex['expense_date']) ?></td>
                  <td>
                    <span class="badge bg-light text-dark border text-capitalize px-2 py-1">
                      <?= e($ex['expense_type']) ?>
                    </span>
                  </td>
                  <td class="fw-bold text-danger text-nowrap">
                    <?= formatCurrency($ex['amount']) ?>
                  </td>
                  <td>
                    <a href="<?= APP_URL ?>/project_view.php?project_id=<?= (int)$ex['project_id'] ?>" class="text-decoration-none fw-semibold">
                      <?= e($ex['project_name']) ?>
                    </a>
                  </td>
                  <td class="text-muted small text-truncate" style="max-width: 250px;">
                    <?= e($ex['description'] ?? '—') ?>
                  </td>
                  <td class="text-end pe-4 text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary edit-exp-btn"
                      data-id="<?= (int)$ex['expense_id'] ?>"
                      data-project="<?= (int)$ex['project_id'] ?>"
                      data-type="<?= e($ex['expense_type']) ?>"
                      data-amount="<?= (float)$ex['amount'] ?>"
                      data-date="<?= e($ex['expense_date']) ?>"
                      data-desc="<?= e($ex['description'] ?? '') ?>"
                      title="Edit Expense"
                    >
                      <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" action="<?= APP_URL ?>/expenses.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this expense record?');">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="expense_id" value="<?= (int)$ex['expense_id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Expense">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= APP_URL ?>/expenses.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Record New Project Expense</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Project Site <span class="text-danger">*</span></label>
              <select name="project_id" class="form-select" required>
                <option value="">Select Project...</option>
                <?php foreach ($projects as $pr): ?>
                  <option value="<?= $pr['project_id'] ?>" <?= $projectFilter === (int)$pr['project_id'] ? 'selected' : '' ?>>
                    <?= e($pr['project_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Expense Category <span class="text-danger">*</span></label>
              <select name="expense_type" class="form-select" required>
                <option value="">Select Category...</option>
                <?php foreach (ExpenseModel::TYPES as $ty): ?>
                  <option value="<?= $ty ?>"><?= ucfirst($ty) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Expense Amount (<?= CURRENCY_SYMBOL ?>) <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="0.00" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Expense Date <span class="text-danger">*</span></label>
              <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-12">
              <label class="form-label fw-semibold">Expense Description / Purpose</label>
              <textarea name="description" class="form-control" rows="3" placeholder="Vendor bill number, machine hire details, specific site operation..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Record Expense</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Expense Modal -->
<div class="modal fade" id="editExpenseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= APP_URL ?>/expenses.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="expense_id" id="edit_expense_id">
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Expense Entry</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Project Site <span class="text-danger">*</span></label>
              <select name="project_id" id="edit_project_id" class="form-select" required>
                <?php foreach ($projects as $pr): ?>
                  <option value="<?= $pr['project_id'] ?>"><?= e($pr['project_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Expense Category <span class="text-danger">*</span></label>
              <select name="expense_type" id="edit_expense_type" class="form-select" required>
                <?php foreach (ExpenseModel::TYPES as $ty): ?>
                  <option value="<?= $ty ?>"><?= ucfirst($ty) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Expense Amount (<?= CURRENCY_SYMBOL ?>) <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="0.01" name="amount" id="edit_amount" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Expense Date <span class="text-danger">*</span></label>
              <input type="date" name="expense_date" id="edit_expense_date" class="form-control" required>
            </div>
            <div class="col-md-12">
              <label class="form-label fw-semibold">Description</label>
              <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-dark px-4"><i class="bi bi-save me-1"></i>Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const editModal = new bootstrap.Modal(document.getElementById('editExpenseModal'));
  document.querySelectorAll('.edit-exp-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.getElementById('edit_expense_id').value = this.dataset.id;
      document.getElementById('edit_project_id').value = this.dataset.project;
      document.getElementById('edit_expense_type').value = this.dataset.type;
      document.getElementById('edit_amount').value = this.dataset.amount;
      document.getElementById('edit_expense_date').value = this.dataset.date;
      document.getElementById('edit_description').value = this.dataset.desc || '';
      editModal.show();
    });
  });
});
</script>

<?php
require_once VIEW_PATH . '/layout/footer.php';

