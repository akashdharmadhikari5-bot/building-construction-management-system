<?php
/**
 * BCMS — Budget & Cost Monitoring Module
 * Track project budgets against actual recorded expenditures, remaining budget, cost overrun alerts, and client billing balances.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireRole(ROLE_ADMIN);

$projectModel = new ProjectModel();

$searchQuery  = strInput($_GET['search'] ?? '', 100);
$statusFilter = strInput($_GET['status'] ?? '', 50);

$budgetList = [];
$totals = [
    'budget'           => 0.0,
    'expenses'         => 0.0,
    'remaining'        => 0.0,
    'payments'         => 0.0,
    'overrun_projects' => 0,
    'total_projects'   => 0,
];

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
    $where = [];

    if ($searchQuery !== '') {
        $where[] = '(p.project_name LIKE ? OR p.location LIKE ? OR c.name LIKE ?)';
        $term = "%{$searchQuery}%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    if ($statusFilter !== '' && in_array($statusFilter, ProjectModel::STATUSES, true)) {
        $where[] = 'p.status = ?';
        $params[] = $statusFilter;
    }

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY p.budget DESC';

    $rows = dbQuery($sql, $params);

    foreach ($rows as $r) {
        $b   = (float)$r['budget'];
        $exp = (float)$r['total_expenses'];
        $pay = (float)$r['total_payments'];
        $rem = $b - $exp;
        $util= $b > 0 ? round(($exp / $b) * 100, 1) : 0.0;
        $overrun = ($exp > $b);

        $totals['budget']   += $b;
        $totals['expenses'] += $exp;
        $totals['remaining']+= $rem;
        $totals['payments'] += $pay;
        $totals['total_projects']++;
        if ($overrun) {
            $totals['overrun_projects']++;
        }

        $budgetList[] = [
            'project_id'     => (int)$r['project_id'],
            'project_name'   => $r['project_name'],
            'location'       => $r['location'],
            'client_name'    => $r['client_name'],
            'status'         => $r['status'],
            'progress'       => (int)$r['progress'],
            'budget'         => $b,
            'total_expenses' => $exp,
            'remaining'      => $rem,
            'utilization'    => $util,
            'is_overrun'     => $overrun,
            'total_payments' => $pay,
        ];
    }
} catch (Throwable $e) {
    setFlash('error', 'Error calculating budget metrics: ' . $e->getMessage());
}

$avgUtil = $totals['budget'] > 0 ? round(($totals['expenses'] / $totals['budget']) * 100, 1) : 0.0;

$pageTitle = 'Budget & Financial Monitoring';
$activeNav = 'budget';

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
      <p class="text-muted small mb-0">Monitor project expenditures, remaining capital allocations, and avoid cost overruns.</p>
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

  <!-- KPI Cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Total Sanctioned Budget</div>
          <div class="fs-4 fw-bold text-dark mt-1"><?= formatCurrency($totals['budget']) ?></div>
          <div class="small text-muted mt-1"><?= $totals['total_projects'] ?> Active Projects</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Total Incurred Expenses</div>
          <div class="fs-4 fw-bold text-danger mt-1"><?= formatCurrency($totals['expenses']) ?></div>
          <div class="small text-muted mt-1">Portfolio Utilization: <strong><?= $avgUtil ?>%</strong></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Total Remaining Capital</div>
          <div class="fs-4 fw-bold <?= $totals['remaining'] >= 0 ? 'text-success' : 'text-danger' ?> mt-1">
            <?= formatCurrency($totals['remaining']) ?>
          </div>
          <div class="small text-muted mt-1">
            <?= $totals['overrun_projects'] > 0 ? "<span class='text-danger fw-bold'>{$totals['overrun_projects']} project(s) over budget</span>" : "<span class='text-success'>All projects within budget</span>" ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Client Payments Collected</div>
          <div class="fs-4 fw-bold text-primary mt-1"><?= formatCurrency($totals['payments']) ?></div>
          <div class="small text-muted mt-1">Collected Revenue</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Search & Filter Bar -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <form method="GET" action="<?= APP_URL ?>/budget.php" class="row g-3 align-items-center">
        <div class="col-md-5">
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Search project name, client, site location..." value="<?= e($searchQuery) ?>">
          </div>
        </div>
        <div class="col-md-4">
          <select name="status" class="form-select">
            <option value="">All Project Statuses</option>
            <?php foreach (ProjectModel::STATUSES as $st): ?>
              <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Filter</button>
          <a href="<?= APP_URL ?>/budget.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
        </div>
      </form>
    </div>
  </div>

  <!-- Budget Breakdown Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between">
      <h5 class="mb-0 fw-bold">Project Budget Ledger (<?= count($budgetList) ?>)</h5>
    </div>
    <div class="card-body p-0">
      <?php if (empty($budgetList)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-cash-coin fs-2"></i>
          <h5 class="mt-3">No projects found</h5>
          <p class="small">No construction projects match the given filters.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-4">Project / Location</th>
                <th>Client</th>
                <th>Sanctioned Budget</th>
                <th>Expenses Incurred</th>
                <th>Remaining Capital</th>
                <th>Budget Utilization</th>
                <th>Health Status</th>
                <th class="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($budgetList as $b): ?>
                <tr>
                  <td class="ps-4">
                    <div class="fw-bold text-dark">
                      <a href="<?= APP_URL ?>/project_view.php?project_id=<?= $b['project_id'] ?>" class="text-decoration-none">
                        <?= e($b['project_name']) ?>
                      </a>
                    </div>
                    <div class="small text-muted"><?= e($b['location']) ?></div>
                  </td>
                  <td><?= e($b['client_name']) ?></td>
                  <td class="fw-bold text-dark"><?= formatCurrency($b['budget']) ?></td>
                  <td class="fw-bold text-danger"><?= formatCurrency($b['total_expenses']) ?></td>
                  <td class="fw-bold <?= $b['remaining'] >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= formatCurrency($b['remaining']) ?>
                  </td>
                  <td style="min-width: 140px;">
                    <div class="d-flex justify-content-between small fw-bold mb-1">
                      <span><?= $b['utilization'] ?>%</span>
                    </div>
                    <div class="progress" style="height: 6px;">
                      <div class="progress-bar <?= $b['utilization'] > 100 ? 'bg-danger' : ($b['utilization'] >= 80 ? 'bg-warning' : 'bg-success') ?>" style="width: <?= min(100.0, $b['utilization']) ?>%;"></div>
                    </div>
                  </td>
                  <td>
                    <?php if ($b['is_overrun']): ?>
                      <span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>Over Budget</span>
                    <?php elseif ($b['utilization'] >= 80): ?>
                      <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i>High Util (&ge;80%)</span>
                    <?php else: ?>
                      <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Within Budget</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end pe-4">
                    <a href="<?= APP_URL ?>/project_view.php?project_id=<?= $b['project_id'] ?>" class="btn btn-sm btn-outline-primary" title="View 360 Financials">
                      <i class="bi bi-eye"></i> Details
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-bold">
              <tr>
                <td class="ps-4" colspan="2">TOTALS FOR FILTERED SET</td>
                <td><?= formatCurrency($totals['budget']) ?></td>
                <td class="text-danger"><?= formatCurrency($totals['expenses']) ?></td>
                <td class="<?= $totals['remaining'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatCurrency($totals['remaining']) ?></td>
                <td>Avg: <?= $avgUtil ?>%</td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
require_once VIEW_PATH . '/layout/footer.php';

