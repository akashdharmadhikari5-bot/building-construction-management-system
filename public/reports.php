<?php
/**
 * BCMS — Reports & Analytics
 * Comprehensive reporting hub providing Project Financials, Worker Wages, Expense Analytics, and Payment Summaries.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireRole(ROLE_ADMIN);

$projectModel = new ProjectModel();
$workerModel  = new WorkerModel();
$expenseModel = new ExpenseModel();
$paymentModel = new PaymentModel();

$activeTab = strInput($_GET['tab'] ?? 'projects', 50);
$projectId = intInput($_GET['project_id'] ?? null, 1);
$fromDate  = dateInput($_GET['from_date'] ?? null);
$toDate    = dateInput($_GET['to_date'] ?? null);

$projectsList = [];
try {
    $projectsList = $projectModel->getAll();
} catch (Throwable) {}

// ── 1. PROJECT FINANCIAL REPORT DATA ──────────────────────────────────────────
$projectReport = [];
if ($activeTab === 'projects') {
    try {
        $sql = 'SELECT
                    p.project_id,
                    p.project_name,
                    p.location,
                    p.budget,
                    p.status,
                    p.progress,
                    c.name AS client_name,
                    COALESCE((SELECT SUM(amount) FROM expenses WHERE project_id = p.project_id), 0) AS total_expenses,
                    COALESCE((SELECT SUM(amount) FROM payments WHERE project_id = p.project_id), 0) AS total_payments
                FROM projects p
                JOIN clients c ON c.client_id = p.client_id';
        $params = [];
        if ($projectId) {
            $sql .= ' WHERE p.project_id = ?';
            $params[] = $projectId;
        }
        $sql .= ' ORDER BY p.project_id DESC';
        $projectReport = dbQuery($sql, $params);
    } catch (Throwable $e) {
        setFlash('error', 'Error generating project report: ' . $e->getMessage());
    }
}

// ── 2. WORKER WAGE REPORT DATA ────────────────────────────────────────────────
$workerReport = [];
if ($activeTab === 'workers') {
    try {
        $sql = 'SELECT
                    w.worker_id,
                    w.name AS worker_name,
                    w.skill,
                    w.daily_wage,
                    COUNT(CASE WHEN a.status = "present" THEN 1 END) AS present_days,
                    COUNT(CASE WHEN a.status = "half_day" THEN 1 END) AS half_days,
                    COUNT(CASE WHEN a.status = "absent" THEN 1 END) AS absent_days,
                    (COUNT(CASE WHEN a.status = "present" THEN 1 END) + COUNT(CASE WHEN a.status = "half_day" THEN 1 END) * 0.5) AS working_days,
                    ((COUNT(CASE WHEN a.status = "present" THEN 1 END) + COUNT(CASE WHEN a.status = "half_day" THEN 1 END) * 0.5) * w.daily_wage) AS total_wage
                FROM workers w
                LEFT JOIN attendance a ON a.worker_id = w.worker_id';
        $params = [];
        $where = [];

        if ($projectId) {
            $where[] = 'a.project_id = ?';
            $params[] = $projectId;
        }
        if ($fromDate) {
            $where[] = 'a.attendance_date >= ?';
            $params[] = $fromDate;
        }
        if ($toDate) {
            $where[] = 'a.attendance_date <= ?';
            $params[] = $toDate;
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY w.worker_id, w.name, w.skill, w.daily_wage ORDER BY total_wage DESC, w.name ASC';
        $workerReport = dbQuery($sql, $params);
    } catch (Throwable $e) {
        setFlash('error', 'Error generating worker report: ' . $e->getMessage());
    }
}

// ── 3. EXPENSE CATEGORY REPORT DATA ───────────────────────────────────────────
$expenseReport = [];
$totalAllExpenses = 0.0;
if ($activeTab === 'expenses') {
    try {
        $sql = 'SELECT
                    expense_type,
                    COUNT(*) AS transaction_count,
                    COALESCE(SUM(amount), 0) AS category_total
                FROM expenses';
        $params = [];
        $where = [];

        if ($projectId) {
            $where[] = 'project_id = ?';
            $params[] = $projectId;
        }
        if ($fromDate) {
            $where[] = 'expense_date >= ?';
            $params[] = $fromDate;
        }
        if ($toDate) {
            $where[] = 'expense_date <= ?';
            $params[] = $toDate;
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY expense_type ORDER BY category_total DESC';
        $expenseReport = dbQuery($sql, $params);

        foreach ($expenseReport as $er) {
            $totalAllExpenses += (float)$er['category_total'];
        }
    } catch (Throwable $e) {
        setFlash('error', 'Error generating expense report: ' . $e->getMessage());
    }
}

// ── 4. PAYMENT LEDGER REPORT DATA ─────────────────────────────────────────────
$paymentReport = [];
if ($activeTab === 'payments') {
    try {
        $sql = 'SELECT
                    pay.payment_id,
                    pay.payment_date,
                    pay.amount,
                    pay.payment_type,
                    pay.payment_mode,
                    pay.reference_no,
                    p.project_name,
                    c.name AS client_name
                FROM payments pay
                JOIN projects p ON p.project_id = pay.project_id
                JOIN clients c ON c.client_id = pay.client_id';
        $params = [];
        $where = [];

        if ($projectId) {
            $where[] = 'pay.project_id = ?';
            $params[] = $projectId;
        }
        if ($fromDate) {
            $where[] = 'pay.payment_date >= ?';
            $params[] = $fromDate;
        }
        if ($toDate) {
            $where[] = 'pay.payment_date <= ?';
            $params[] = $toDate;
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY pay.payment_date DESC';
        $paymentReport = dbQuery($sql, $params);
    } catch (Throwable $e) {
        setFlash('error', 'Error generating payment report: ' . $e->getMessage());
    }
}

$pageTitle = 'Management Reports';
$activeNav = 'reports';

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
      <p class="text-muted small mb-0">Consolidated analytics for audits, client billing, labor wages, and cost control.</p>
    </div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Print Report
      </button>
    </div>
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

  <!-- Report Filter Bar -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <form method="GET" action="<?= APP_URL ?>/reports.php" class="row g-3 align-items-end">
        <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Filter Project Site</label>
          <select name="project_id" class="form-select">
            <option value="">All Construction Sites</option>
            <?php foreach ($projectsList as $pr): ?>
              <option value="<?= $pr['project_id'] ?>" <?= $projectId === (int)$pr['project_id'] ? 'selected' : '' ?>>
                <?= e($pr['project_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">From Date</label>
          <input type="date" name="from_date" class="form-control" value="<?= e($fromDate ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">To Date</label>
          <input type="date" name="to_date" class="form-control" value="<?= e($toDate ?? '') ?>">
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter me-1"></i>Apply</button>
          <a href="<?= APP_URL ?>/reports.php?tab=<?= e($activeTab) ?>" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
        </div>
      </form>
    </div>
  </div>

  <!-- Report Tabs -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3 px-3">
      <ul class="nav nav-pills card-header-pills">
        <li class="nav-item">
          <a class="nav-link <?= $activeTab === 'projects' ? 'active' : '' ?>" href="<?= APP_URL ?>/reports.php?tab=projects<?= $projectId ? "&project_id={$projectId}" : '' ?>">
            <i class="bi bi-building me-1"></i>Project Financials
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $activeTab === 'workers' ? 'active' : '' ?>" href="<?= APP_URL ?>/reports.php?tab=workers<?= $projectId ? "&project_id={$projectId}" : '' ?>">
            <i class="bi bi-person-badge me-1"></i>Worker Attendance & Wages
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $activeTab === 'expenses' ? 'active' : '' ?>" href="<?= APP_URL ?>/reports.php?tab=expenses<?= $projectId ? "&project_id={$projectId}" : '' ?>">
            <i class="bi bi-pie-chart me-1"></i>Expense Categories
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $activeTab === 'payments' ? 'active' : '' ?>" href="<?= APP_URL ?>/reports.php?tab=payments<?= $projectId ? "&project_id={$projectId}" : '' ?>">
            <i class="bi bi-credit-card-2-front me-1"></i>Client Payments
          </a>
        </li>
      </ul>
    </div>

    <div class="card-body p-0">
      <!-- 1. PROJECT FINANCIALS TABLE -->
      <?php if ($activeTab === 'projects'): ?>
        <?php if (empty($projectReport)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-building-slash fs-2"></i>
            <p class="mt-2 mb-0">No project records found for the selected filter.</p>
          </div>
        <?php else: ?>
          <?php
            $totB = 0.0; $totE = 0.0; $totP = 0.0; $totR = 0.0; $totBal = 0.0;
          ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-4">Project Name</th>
                  <th>Client</th>
                  <th>Sanctioned Budget</th>
                  <th>Actual Expenses</th>
                  <th>Remaining Budget</th>
                  <th>Payments Collected</th>
                  <th>Balance Due</th>
                  <th class="pe-4">Progress</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($projectReport as $r): ?>
                  <?php
                    $b = (float)$r['budget'];
                    $e = (float)$r['total_expenses'];
                    $p = (float)$r['total_payments'];
                    $rem = $b - $e;
                    $bal = max(0.0, $b - $p);
                    $totB += $b; $totE += $e; $totP += $p; $totR += $rem; $totBal += $bal;
                  ?>
                  <tr>
                    <td class="ps-4 fw-bold">
                      <a href="<?= APP_URL ?>/project_view.php?project_id=<?= (int)$r['project_id'] ?>" class="text-decoration-none">
                        <?= e($r['project_name']) ?>
                      </a>
                      <div class="small text-muted"><?= e($r['location']) ?></div>
                    </td>
                    <td><?= e($r['client_name']) ?></td>
                    <td class="fw-bold"><?= formatCurrency($b) ?></td>
                    <td class="fw-bold text-danger"><?= formatCurrency($e) ?></td>
                    <td class="fw-bold <?= $rem >= 0 ? 'text-success' : 'text-danger' ?>">
                      <?= formatCurrency($rem) ?>
                    </td>
                    <td class="fw-bold text-primary"><?= formatCurrency($p) ?></td>
                    <td class="fw-bold text-muted"><?= formatCurrency($bal) ?></td>
                    <td class="pe-4">
                      <span class="badge <?= progressColor((int)$r['progress']) ?>"><?= (int)$r['progress'] ?>%</span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot class="table-light fw-bold">
                <tr>
                  <td class="ps-4" colspan="2">TOTAL PORTFOLIO SUMMARY</td>
                  <td><?= formatCurrency($totB) ?></td>
                  <td class="text-danger"><?= formatCurrency($totE) ?></td>
                  <td class="<?= $totR >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatCurrency($totR) ?></td>
                  <td class="text-primary"><?= formatCurrency($totP) ?></td>
                  <td><?= formatCurrency($totBal) ?></td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        <?php endif; ?>

      <!-- 2. WORKER WAGES TABLE -->
      <?php elseif ($activeTab === 'workers'): ?>
        <?php if (empty($workerReport)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-people-fill fs-2"></i>
            <p class="mt-2 mb-0">No attendance or wage records found for this period.</p>
          </div>
        <?php else: ?>
          <?php
            $totWorkingDays = 0.0; $totWages = 0.0;
          ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-4">Worker Name</th>
                  <th>Skill / Trade</th>
                  <th>Daily Wage</th>
                  <th>Present</th>
                  <th>Half-Day</th>
                  <th>Absent</th>
                  <th>Total Working Days</th>
                  <th class="pe-4">Calculated Total Wage</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($workerReport as $w): ?>
                  <?php
                    $wd = (float)$w['working_days'];
                    $tw = (float)$w['total_wage'];
                    $totWorkingDays += $wd;
                    $totWages += $tw;
                  ?>
                  <tr>
                    <td class="ps-4 fw-bold">
                      <a href="<?= APP_URL ?>/worker_view.php?id=<?= (int)$w['worker_id'] ?>" class="text-decoration-none">
                        <?= e($w['worker_name']) ?>
                      </a>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?= e($w['skill']) ?></span></td>
                    <td class="fw-semibold"><?= formatCurrency($w['daily_wage']) ?></td>
                    <td class="text-success fw-bold"><?= (int)$w['present_days'] ?></td>
                    <td class="text-secondary"><?= (int)$w['half_days'] ?></td>
                    <td class="text-danger"><?= (int)$w['absent_days'] ?></td>
                    <td class="fw-bold text-dark"><?= number_format($wd, 1) ?> days</td>
                    <td class="pe-4 fw-bold text-success fs-6"><?= formatCurrency($tw) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot class="table-light fw-bold">
                <tr>
                  <td class="ps-4" colspan="6">LABOUR WAGE EXPENDITURE TOTAL</td>
                  <td><?= number_format($totWorkingDays, 1) ?> days</td>
                  <td class="pe-4 text-success fs-6"><?= formatCurrency($totWages) ?></td>
                </tr>
              </tfoot>
            </table>
          </div>
        <?php endif; ?>

      <!-- 3. EXPENSE CATEGORIES TABLE -->
      <?php elseif ($activeTab === 'expenses'): ?>
        <?php if (empty($expenseReport)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-pie-chart fs-2"></i>
            <p class="mt-2 mb-0">No expense records found for the selected filter.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-4">Expense Category</th>
                  <th>Number of Transactions</th>
                  <th>Total Expenditure</th>
                  <th class="pe-4">Share of Total Costs</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($expenseReport as $er): ?>
                  <?php
                    $catAmt = (float)$er['category_total'];
                    $share  = $totalAllExpenses > 0 ? round(($catAmt / $totalAllExpenses) * 100, 1) : 0.0;
                  ?>
                  <tr>
                    <td class="ps-4 fw-bold text-capitalize">
                      <span class="badge bg-light text-dark border me-2"><?= e($er['expense_type']) ?></span>
                    </td>
                    <td><?= number_format((int)$er['transaction_count']) ?> voucher(s)</td>
                    <td class="fw-bold text-danger"><?= formatCurrency($catAmt) ?></td>
                    <td class="pe-4" style="min-width: 160px;">
                      <div class="d-flex align-items-center gap-2">
                        <span class="small fw-bold"><?= $share ?>%</span>
                        <div class="progress flex-grow-1" style="height: 6px;">
                          <div class="progress-bar bg-danger" style="width: <?= $share ?>%;"></div>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot class="table-light fw-bold">
                <tr>
                  <td class="ps-4" colspan="2">TOTAL EXPENSES INCURRED</td>
                  <td class="text-danger"><?= formatCurrency($totalAllExpenses) ?></td>
                  <td class="pe-4">100.0%</td>
                </tr>
              </tfoot>
            </table>
          </div>
        <?php endif; ?>

      <!-- 4. CLIENT PAYMENTS TABLE -->
      <?php elseif ($activeTab === 'payments'): ?>
        <?php if (empty($paymentReport)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-credit-card-2-front fs-2"></i>
            <p class="mt-2 mb-0">No payment receipts found for this period.</p>
          </div>
        <?php else: ?>
          <?php $totPay = 0.0; ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-4">Date</th>
                  <th>Client Name</th>
                  <th>Project Site</th>
                  <th>Amount Received</th>
                  <th>Billing Stage</th>
                  <th>Payment Mode</th>
                  <th class="pe-4">Reference No.</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($paymentReport as $pay): ?>
                  <?php $totPay += (float)$pay['amount']; ?>
                  <tr>
                    <td class="ps-4 fw-semibold"><?= formatDate($pay['payment_date']) ?></td>
                    <td class="fw-bold"><?= e($pay['client_name']) ?></td>
                    <td><?= e($pay['project_name']) ?></td>
                    <td class="fw-bold text-success"><?= formatCurrency($pay['amount']) ?></td>
                    <td><span class="badge bg-primary-subtle text-primary border text-capitalize"><?= e($pay['payment_type']) ?></span></td>
                    <td><span class="badge bg-light text-dark border text-capitalize"><?= str_replace('_', ' ', e($pay['payment_mode'])) ?></span></td>
                    <td class="pe-4 small text-muted"><?= e($pay['reference_no'] ?? '—') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot class="table-light fw-bold">
                <tr>
                  <td class="ps-4" colspan="3">TOTAL REVENUE COLLECTED</td>
                  <td class="text-success fs-6"><?= formatCurrency($totPay) ?></td>
                  <td colspan="3"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
require_once VIEW_PATH . '/layout/footer.php';

