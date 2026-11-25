<?php
/**
 * BCMS — Project 360 Details View
 * Comprehensive multi-tabbed project view showing tasks, workers, attendance, materials, expenses, and payments.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireLogin();

$user = currentUser();
$userRole = $user['role'] ?? ROLE_WORKER;
$isClient = ($userRole === ROLE_CLIENT);
$isAdmin  = ($userRole === ROLE_ADMIN);

$projectId = intInput($_GET['project_id'] ?? $_GET['id'] ?? null, 1);
if (!$projectId) {
    setFlash('error', 'Please select a valid project to view.');
    redirect('/projects.php');
}

$projectModel    = new ProjectModel();
$taskModel       = new TaskModel();
$assignmentModel = new AssignmentModel();
$attendanceModel = new AttendanceModel();
$purchaseModel   = new PurchaseModel();
$usageModel      = new UsageModel();
$expenseModel    = new ExpenseModel();
$paymentModel    = new PaymentModel();

$project = null;
try {
    $project = $projectModel->getById($projectId);
} catch (Throwable $e) {
    setFlash('error', 'Database error: ' . $e->getMessage());
    redirect('/projects.php');
}

if (!$project) {
    setFlash('error', "Project #{$projectId} was not found.");
    redirect('/projects.php');
}

// Client ownership validation (IDOR prevention)
if ($isClient && (int)$project['client_id'] !== (int)($user['linked_id'] ?? 0)) {
    setFlash('error', 'Access denied: You can only view your own construction projects.');
    redirect('/projects.php');
}

// ── Handle Quick Actions (Admin Only) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    validateCsrf();
    $action = strInput($_POST['action'] ?? '', 50);

    if ($action === 'update_progress') {
        $progress = intInput($_POST['progress'] ?? 0, 0);
        if ($progress !== null && $progress >= 0 && $progress <= 100) {
            try {
                $projectModel->updateProgress($projectId, $progress);
                logActivity('update', 'projects', $projectId, "Updated project progress to {$progress}%");
                setFlash('success', "Project progress updated to {$progress}%.");
            } catch (Throwable $e) {
                setFlash('error', $e->getMessage());
            }
        }
        redirect("/project_view.php?project_id={$projectId}");
    }

    if ($action === 'sync_task_progress') {
        try {
            $avg = $projectModel->calculateProgressFromTasks($projectId);
            setFlash('success', "Project progress recalculated from tasks: {$avg}%.");
        } catch (Throwable $e) {
            setFlash('error', $e->getMessage());
        }
        redirect("/project_view.php?project_id={$projectId}");
    }

    if ($action === 'update_status') {
        $status = strInput($_POST['status'] ?? '', 50);
        if (in_array($status, ProjectModel::STATUSES, true)) {
            try {
                $projectModel->updateStatus($projectId, $status);
                logActivity('update', 'projects', $projectId, "Changed project status to {$status}");
                setFlash('success', "Project status changed to '" . ucfirst($status) . "'.");
            } catch (Throwable $e) {
                setFlash('error', $e->getMessage());
            }
        }
        redirect("/project_view.php?project_id={$projectId}");
    }
}

// Fetch relational project data
$tasks       = [];
$taskStats   = [];
$assignments = [];
$attendance  = [];
$purchases   = [];
$usages      = [];
$expenses    = [];
$expenseTypes= [];
$payments    = [];
$finSummary  = [];

try {
    $tasks        = $taskModel->getByProject($projectId);
    $taskStats    = $taskModel->getStats($projectId);
    $assignments  = $assignmentModel->getByProject($projectId);
    $attendance   = $attendanceModel->getByProject($projectId, ['limit' => 20]);
    $purchases    = $purchaseModel->getByProject($projectId);
    $usages       = $usageModel->getByProject($projectId);
    $expenses     = $expenseModel->getByProject($projectId);
    $expenseTypes = $expenseModel->getTotalByType($projectId);
    $payments     = $paymentModel->getByProject($projectId);
    $finSummary   = $paymentModel->getSummaryByProject($projectId);
} catch (Throwable $e) {
    // Graceful fallback if database views/tables encountered errors
}

// Financial calculations
$budget          = (float)$project['budget'];
$totalExpenses   = (float)($finSummary['total_expenses'] ?? 0.0);
$totalPayments   = (float)($finSummary['total_payments'] ?? 0.0);
$remainingBudget = $budget - $totalExpenses;
$utilization     = $budget > 0 ? min(100.0, round(($totalExpenses / $budget) * 100, 1)) : 0.0;
$balanceDue      = max(0.0, $budget - $totalPayments);
$progressVal     = (int)$project['progress'];

$pageTitle = $project['project_name'];
$activeNav = 'projects';

require_once VIEW_PATH . '/layout/header.php';
require_once VIEW_PATH . '/layout/sidebar.php';
require_once VIEW_PATH . '/layout/topnav.php';
?>

<div class="bcms-content">
  <!-- Page Header & Actions -->
  <div class="bcms-page-header d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="<?= APP_URL ?>/projects.php">Projects</a></li>
          <li class="breadcrumb-item active"><?= e($project['project_name']) ?></li>
        </ol>
      </nav>
      <div class="d-flex align-items-center gap-2">
        <h2 class="h4 mb-0 fw-bold"><?= e($project['project_name']) ?></h2>
        <span class="<?= statusBadge($project['status']) ?> text-capitalize px-2 py-1">
          <?= str_replace('_', ' ', e($project['status'])) ?>
        </span>
      </div>
      <p class="text-muted small mb-0"><i class="bi bi-geo-alt me-1"></i><?= e($project['location']) ?> &bull; Client: <strong><?= e($project['client_name']) ?></strong></p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= APP_URL ?>/projects.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Projects
      </a>
      <?php if ($isAdmin): ?>
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#quickStatusModal">
          <i class="bi bi-gear me-1"></i>Update Status
        </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Alerts -->
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

  <!-- Financial & Progress Overview Banner -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Sanctioned Budget</div>
          <div class="fs-4 fw-bold text-dark mt-1"><?= formatCurrency($budget) ?></div>
          <div class="small text-muted mt-2">
            <strong>Timeline:</strong> <?= formatDate($project['start_date']) ?> &rarr; <?= formatDate($project['end_date']) ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Actual Expenses</div>
          <div class="fs-4 fw-bold text-danger mt-1"><?= formatCurrency($totalExpenses) ?></div>
          <div class="small text-muted mt-2">
            Utilization: <strong><?= $utilization ?>%</strong> of budget
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Remaining Budget</div>
          <div class="fs-4 fw-bold <?= $remainingBudget >= 0 ? 'text-success' : 'text-danger' ?> mt-1">
            <?= formatCurrency($remainingBudget) ?>
          </div>
          <div class="small text-muted mt-2">
            <?= $remainingBudget >= 0 ? '<span class="text-success"><i class="bi bi-check-circle"></i> Within Budget</span>' : '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> Cost Overrun</span>' ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Payments Received</div>
          <div class="fs-4 fw-bold text-primary mt-1"><?= formatCurrency($totalPayments) ?></div>
          <div class="small text-muted mt-2">
            Balance Due: <strong><?= formatCurrency($balanceDue) ?></strong>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Overall Progress Card -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="fw-bold"><i class="bi bi-activity me-1 text-primary"></i>Overall Construction Progress</span>
        <div class="d-flex align-items-center gap-3">
          <?php if ($isAdmin): ?>
            <form method="POST" action="<?= APP_URL ?>/project_view.php?project_id=<?= $projectId ?>" class="d-inline">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="sync_task_progress">
              <button type="submit" class="btn btn-sm btn-link text-decoration-none p-0" title="Auto-calculate progress from linked tasks">
                <i class="bi bi-arrow-repeat me-1"></i>Calculate from Tasks
              </button>
            </form>
          <?php endif; ?>
          <span class="fs-5 fw-bold <?= progressColor($progressVal) ?> badge"><?= $progressVal ?>% Complete</span>
        </div>
      </div>
      <div class="progress" style="height: 10px;">
        <div class="progress-bar <?= progressColor($progressVal) ?>" role="progressbar" style="width: <?= $progressVal ?>%;" aria-valuenow="<?= $progressVal ?>" aria-valuemin="0" aria-valuemax="100"></div>
      </div>
    </div>
  </div>

  <!-- Multi-Tab Details Navigation -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3 px-3">
      <ul class="nav nav-tabs card-header-tabs" id="projectTabs" role="tablist">
        <li class="nav-item">
          <button class="nav-link active fw-semibold" id="tasks-tab" data-bs-toggle="tab" data-bs-target="#tasks" type="button" role="tab">
            <i class="bi bi-check2-square me-1"></i>Tasks (<?= count($tasks) ?>)
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link fw-semibold" id="workers-tab" data-bs-toggle="tab" data-bs-target="#workers" type="button" role="tab">
            <i class="bi bi-person-badge me-1"></i>Assigned Workers (<?= count($assignments) ?>)
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link fw-semibold" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">
            <i class="bi bi-calendar-check me-1"></i>Attendance (<?= count($attendance) ?>)
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link fw-semibold" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials" type="button" role="tab">
            <i class="bi bi-box-seam me-1"></i>Materials (<?= count($purchases) + count($usages) ?>)
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link fw-semibold" id="expenses-tab" data-bs-toggle="tab" data-bs-target="#expenses" type="button" role="tab">
            <i class="bi bi-cash-stack me-1"></i>Expenses (<?= count($expenses) ?>)
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link fw-semibold" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">
            <i class="bi bi-credit-card-2-front me-1"></i>Payments (<?= count($payments) ?>)
          </button>
        </li>
      </ul>
    </div>

    <div class="card-body p-4">
      <div class="tab-content" id="projectTabContent">
        <!-- TAB 1: TASKS -->
        <div class="tab-pane fade show active" id="tasks" role="tabpanel">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0">Project Tasks & Milestones</h6>
            <?php if ($isAdmin): ?>
              <a href="<?= APP_URL ?>/tasks.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Manage Tasks
              </a>
            <?php endif; ?>
          </div>
          <?php if (empty($tasks)): ?>
            <div class="text-center py-4 text-muted">
              <i class="bi bi-check2-circle fs-2"></i>
              <p class="mt-2 mb-0">No tasks created for this project yet.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Task Name</th>
                    <th>Assigned Worker</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Progress</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($tasks as $t): ?>
                    <tr>
                      <td class="fw-semibold"><?= e($t['task_name']) ?></td>
                      <td><?= e($t['worker_name'] ?? 'Unassigned') ?></td>
                      <td><?= formatDate($t['due_date']) ?></td>
                      <td><span class="<?= statusBadge($t['status']) ?>"><?= ucfirst(str_replace('_', ' ', e($t['status']))) ?></span></td>
                      <td style="width: 140px;">
                        <div class="small fw-bold mb-1"><?= (int)$t['progress'] ?>%</div>
                        <div class="progress" style="height: 5px;">
                          <div class="progress-bar <?= progressColor((int)$t['progress']) ?>" style="width: <?= (int)$t['progress'] ?>%;"></div>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- TAB 2: WORKERS -->
        <div class="tab-pane fade" id="workers" role="tabpanel">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0">Deployments to Site</h6>
            <?php if ($isAdmin): ?>
              <a href="<?= APP_URL ?>/assignments.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-person-plus me-1"></i>Manage Deployments
              </a>
            <?php endif; ?>
          </div>
          <?php if (empty($assignments)): ?>
            <div class="text-center py-4 text-muted">
              <i class="bi bi-people fs-2"></i>
              <p class="mt-2 mb-0">No workers currently assigned to this project.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Worker Name</th>
                    <th>Skill / Trade</th>
                    <th>Daily Wage</th>
                    <th>Assigned Date</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($assignments as $a): ?>
                    <tr>
                      <td class="fw-bold">
                        <a href="<?= APP_URL ?>/worker_view.php?id=<?= (int)$a['worker_id'] ?>" class="text-decoration-none">
                          <?= e($a['worker_name']) ?>
                        </a>
                      </td>
                      <td><span class="badge bg-light text-dark border"><?= e($a['skill']) ?></span></td>
                      <td><?= formatCurrency($a['daily_wage']) ?> / day</td>
                      <td><?= formatDate($a['assigned_date']) ?></td>
                      <td>
                        <span class="badge <?= (bool)$a['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                          <?= (bool)$a['is_active'] ? 'Active Deployment' : 'Ended' ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- TAB 3: ATTENDANCE -->
        <div class="tab-pane fade" id="attendance" role="tabpanel">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0">Recent Attendance Records</h6>
            <?php if ($isAdmin): ?>
              <a href="<?= APP_URL ?>/attendance.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-calendar-check me-1"></i>Mark Daily Attendance
              </a>
            <?php endif; ?>
          </div>
          <?php if (empty($attendance)): ?>
            <div class="text-center py-4 text-muted">
              <i class="bi bi-calendar-x fs-2"></i>
              <p class="mt-2 mb-0">No attendance records logged for this project yet.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Date</th>
                    <th>Worker</th>
                    <th>Status</th>
                    <th>Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($attendance as $att): ?>
                    <tr>
                      <td><?= formatDate($att['attendance_date']) ?></td>
                      <td class="fw-semibold"><?= e($att['worker_name']) ?></td>
                      <td><span class="<?= statusBadge($att['status']) ?>"><?= ucfirst(str_replace('_', ' ', e($att['status']))) ?></span></td>
                      <td class="text-muted small"><?= e($att['remarks'] ?? '—') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- TAB 4: MATERIALS -->
        <div class="tab-pane fade" id="materials" role="tabpanel">
          <div class="row g-4">
            <div class="col-lg-6">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0">Material Purchases (Stock In)</h6>
                <?php if ($isAdmin): ?>
                  <a href="<?= APP_URL ?>/purchases.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-outline-primary">Add Purchase</a>
                <?php endif; ?>
              </div>
              <?php if (empty($purchases)): ?>
                <p class="text-muted small">No purchases recorded for this project.</p>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Material</th>
                        <th>Qty</th>
                        <th>Spend</th>
                        <th>Date</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($purchases as $pur): ?>
                        <tr>
                          <td class="fw-semibold"><?= e($pur['material_name']) ?></td>
                          <td><?= number_format((float)$pur['quantity'], 2) ?> <?= e($pur['unit']) ?></td>
                          <td class="fw-bold"><?= formatCurrency($pur['price']) ?></td>
                          <td><?= formatDate($pur['purchase_date']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>

            <div class="col-lg-6">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0">Material Consumption (Stock Out)</h6>
                <?php if ($isAdmin): ?>
                  <a href="<?= APP_URL ?>/usage.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-outline-primary">Record Usage</a>
                <?php endif; ?>
              </div>
              <?php if (empty($usages)): ?>
                <p class="text-muted small">No material consumption recorded yet.</p>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Material</th>
                        <th>Qty Used</th>
                        <th>Date</th>
                        <th>Work Description</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($usages as $u): ?>
                        <tr>
                          <td class="fw-semibold"><?= e($u['material_name']) ?></td>
                          <td><?= number_format((float)$u['quantity'], 2) ?> <?= e($u['unit']) ?></td>
                          <td><?= formatDate($u['usage_date']) ?></td>
                          <td class="text-muted small"><?= e($u['description'] ?? '—') ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- TAB 5: EXPENSES -->
        <div class="tab-pane fade" id="expenses" role="tabpanel">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h6 class="fw-bold mb-0">Project Expenses</h6>
              <span class="small text-muted">Total incurred: <strong><?= formatCurrency($totalExpenses) ?></strong></span>
            </div>
            <?php if ($isAdmin): ?>
              <a href="<?= APP_URL ?>/expenses.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Record Expense
              </a>
            <?php endif; ?>
          </div>
          <?php if (empty($expenses)): ?>
            <div class="text-center py-4 text-muted">
              <i class="bi bi-cash-stack fs-2"></i>
              <p class="mt-2 mb-0">No expenses recorded for this project yet.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Expense Type</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Description</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($expenses as $ex): ?>
                    <tr>
                      <td><span class="badge bg-light text-dark border text-capitalize"><?= e($ex['expense_type']) ?></span></td>
                      <td class="fw-bold text-danger"><?= formatCurrency($ex['amount']) ?></td>
                      <td><?= formatDate($ex['expense_date']) ?></td>
                      <td class="text-muted"><?= e($ex['description'] ?? '—') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- TAB 6: PAYMENTS -->
        <div class="tab-pane fade" id="payments" role="tabpanel">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h6 class="fw-bold mb-0">Client Payment Ledger</h6>
              <span class="small text-muted">Total received: <strong><?= formatCurrency($totalPayments) ?></strong></span>
            </div>
            <?php if ($isAdmin): ?>
              <a href="<?= APP_URL ?>/payments.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Record Payment
              </a>
            <?php endif; ?>
          </div>
          <?php if (empty($payments)): ?>
            <div class="text-center py-4 text-muted">
              <i class="bi bi-credit-card fs-2"></i>
              <p class="mt-2 mb-0">No payments recorded for this project yet.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Type</th>
                    <th>Mode</th>
                    <th>Reference / Notes</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($payments as $pay): ?>
                    <tr>
                      <td><?= formatDate($pay['payment_date']) ?></td>
                      <td class="fw-bold text-success"><?= formatCurrency($pay['amount']) ?></td>
                      <td><span class="badge bg-primary-subtle text-primary border text-capitalize"><?= e($pay['payment_type']) ?></span></td>
                      <td><span class="badge bg-light text-dark border text-capitalize"><?= str_replace('_', ' ', e($pay['payment_mode'])) ?></span></td>
                      <td class="small text-muted">
                        <?= !empty($pay['reference_no']) ? '<strong>Ref:</strong> ' . e($pay['reference_no']) . ' ' : '' ?>
                        <?= e($pay['notes'] ?? '') ?>
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
  </div>
</div>

<?php if ($isAdmin): ?>
<!-- Quick Status Modal -->
<div class="modal fade" id="quickStatusModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= APP_URL ?>/project_view.php?project_id=<?= $projectId ?>">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_status">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold">Update Project Status</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <label class="form-label fw-semibold">Select Status</label>
          <select name="status" class="form-select mb-3">
            <?php foreach (ProjectModel::STATUSES as $st): ?>
              <option value="<?= $st ?>" <?= $project['status'] === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Status</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
require_once VIEW_PATH . '/layout/footer.php';

