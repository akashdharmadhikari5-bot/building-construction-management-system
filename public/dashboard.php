<?php
/**
 * BCMS — Main Dashboard
 * Supports role-based views for Admin, Client, and Worker.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireLogin();

$user = currentUser();
$userRole = $user['role'] ?? 'worker';
$userName = $user['name'] ?? 'User';
$linkedId = (int)($user['linked_id'] ?? 0);

// Models
$projectModel    = new ProjectModel();
$clientModel     = new ClientModel();
$workerModel     = new WorkerModel();
$attendanceModel = new AttendanceModel();
$taskModel       = new TaskModel();
$materialModel   = new MaterialModel();
$expenseModel    = new ExpenseModel();
$paymentModel    = new PaymentModel();

// Data containers
$adminStats       = [];
$statusCounts     = [];
$monthlyExpenses  = [];
$recentProjects   = [];
$recentPayments   = [];
$lowStockItems    = [];

$clientProjects   = [];
$clientPayments   = [];
$clientStats      = [];

$workerTasks      = [];
$workerTodayAtt   = null;
$workerStats      = [];
$workerAttendance = [];

if ($userRole === ROLE_ADMIN) {
    // 1. Projects aggregate stats
    $pStats = $projectModel->getStats();
    
    // Clients count
    $cCountRow = dbQueryOne('SELECT COUNT(*) AS total FROM clients');
    $totalClients = (int)($cCountRow['total'] ?? 0);
    
    // Workers count (active)
    $wCountRow = dbQueryOne('SELECT COUNT(*) AS total FROM workers WHERE is_active = 1');
    $totalWorkers = (int)($wCountRow['total'] ?? 0);

    // Payments total
    $payRow = dbQueryOne('SELECT COALESCE(SUM(amount), 0) AS total FROM payments');
    $totalPayments = (float)($payRow['total'] ?? 0.0);

    // Low stock count & items
    $lowStockCount = $materialModel->getLowStockCount();
    $lowStockItems = $materialModel->getLowStockItems();

    $adminStats = [
        'total_projects'  => $pStats['total_projects'],
        'active_projects' => $pStats['active'],
        'total_clients'   => $totalClients,
        'total_workers'   => $totalWorkers,
        'total_budget'    => $pStats['total_budget'],
        'total_expenses'  => $pStats['total_expenses'],
        'total_payments'  => $totalPayments,
        'low_stock_count' => $lowStockCount,
    ];

    // Status breakdown for Doughnut chart
    $statusCounts = [
        'planning'  => $pStats['planning'],
        'active'    => $pStats['active'],
        'on_hold'   => $pStats['on_hold'],
        'completed' => $pStats['completed'],
        'cancelled' => $pStats['cancelled'],
    ];

    // Monthly expenses for last 6 months (Bar chart)
    $monthQuery = dbQuery(
        'SELECT DATE_FORMAT(expense_date, "%b %Y") AS label,
                DATE_FORMAT(expense_date, "%Y-%m") AS ym,
                COALESCE(SUM(amount), 0) AS total
         FROM expenses
         WHERE expense_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
         GROUP BY ym, label
         ORDER BY ym ASC'
    );
    $monthlyExpenses = $monthQuery;

    // Recent 5 Projects
    $recentProjects = dbQuery(
        'SELECT p.*, c.name AS client_name
         FROM projects p
         JOIN clients c ON c.client_id = p.client_id
         ORDER BY p.project_id DESC
         LIMIT 5'
    );

    // Recent 5 Payments
    $recentPayments = $paymentModel->getRecentPayments(5);

} elseif ($userRole === ROLE_CLIENT) {
    if ($linkedId > 0) {
        $clientStats    = $clientModel->getStats($linkedId);
        $clientProjects = $projectModel->getAll(['client_id' => $linkedId]);
        $clientPayments = $paymentModel->getByClient($linkedId);
    }
} elseif ($userRole === ROLE_WORKER) {
    if ($linkedId > 0) {
        $workerTasks = $taskModel->getByWorker($linkedId);

        // Today's attendance record
        $workerTodayAtt = dbQueryOne(
            'SELECT a.*, p.project_name
             FROM attendance a
             JOIN projects p ON p.project_id = a.project_id
             WHERE a.worker_id = ? AND a.attendance_date = CURRENT_DATE()
             LIMIT 1',
            [$linkedId]
        );

        // Recent 10 attendance records
        $workerAttendance = dbQuery(
            'SELECT a.*, p.project_name
             FROM attendance a
             JOIN projects p ON p.project_id = a.project_id
             WHERE a.worker_id = ?
             ORDER BY a.attendance_date DESC
             LIMIT 10',
            [$linkedId]
        );

        // Task counts
        $pendingCount   = 0;
        $inProgressCount = 0;
        $completedCount = 0;
        foreach ($workerTasks as $t) {
            if ($t['status'] === 'completed') {
                $completedCount++;
            } elseif ($t['status'] === 'in_progress') {
                $inProgressCount++;
            } else {
                $pendingCount++;
            }
        }

        $workerStats = [
            'total_tasks'       => count($workerTasks),
            'pending_tasks'     => $pendingCount,
            'in_progress_tasks' => $inProgressCount,
            'completed_tasks'   => $completedCount,
        ];
    }
}

$pageTitle = 'Dashboard';
require_once VIEW_PATH . '/layout/header.php';
require_once VIEW_PATH . '/layout/sidebar.php';
require_once VIEW_PATH . '/layout/topnav.php';
?>

<main class="bcms-main">
  <div class="container-fluid">

    <!-- Flash Messages -->
    <?php foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'] as $flashKey => $alertClass): ?>
      <?php if ($msg = getFlash($flashKey)): ?>
        <div class="alert alert-<?= $alertClass ?> alert-dismissible fade show mb-4" role="alert">
          <i class="bi bi-<?= $flashKey === 'success' ? 'check-circle-fill' : ($flashKey === 'error' ? 'exclamation-octagon-fill' : 'info-circle-fill') ?> me-2"></i>
          <?= e($msg) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>

    <!-- Welcome Header Banner -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
      <div>
        <h2 class="h4 mb-1 fw-bold text-dark">
          Welcome back, <?= e($userName) ?>!
        </h2>
        <p class="text-muted small mb-0">
          <i class="bi bi-calendar3 me-1"></i> Today is <?= date('l, d F Y') ?> &bull; 
          Role: <span class="badge bg-primary text-uppercase"><?= e($userRole) ?></span>
        </p>
      </div>
      <div class="mt-2 mt-md-0">
        <?php if ($userRole === ROLE_ADMIN): ?>
          <a href="<?= APP_URL ?>/projects.php" class="btn btn-primary btn-sm me-2">
            <i class="bi bi-plus-circle me-1"></i> New Project
          </a>
          <a href="<?= APP_URL ?>/reports.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-graph-up me-1"></i> View Reports
          </a>
        <?php elseif ($userRole === ROLE_CLIENT): ?>
          <a href="<?= APP_URL ?>/projects.php" class="btn btn-primary btn-sm">
            <i class="bi bi-building me-1"></i> My Projects
          </a>
        <?php elseif ($userRole === ROLE_WORKER): ?>
          <a href="<?= APP_URL ?>/tasks.php" class="btn btn-primary btn-sm">
            <i class="bi bi-check2-square me-1"></i> My Tasks
          </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($userRole === ROLE_ADMIN): ?>
      <!-- ======================================================== -->
      <!-- ADMIN DASHBOARD -->
      <!-- ======================================================== -->

      <!-- Top Metric Cards (Row 1: Operational Counts) -->
      <div class="row g-3 mb-3">
        <!-- Total Projects -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card card-hover bcms-stat-card h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="bcms-stat-label">Total Projects</div>
                <div class="bcms-stat-value"><?= number_format($adminStats['total_projects']) ?></div>
                <div class="bcms-stat-subtext text-muted">All construction sites</div>
              </div>
              <div class="bcms-stat-icon-wrapper stat-icon-primary">
                <i class="bi bi-building"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Active Projects -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card card-hover bcms-stat-card stat-accent h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="bcms-stat-label">Active Projects</div>
                <div class="bcms-stat-value text-primary"><?= number_format($adminStats['active_projects']) ?></div>
                <div class="bcms-stat-subtext text-success fw-semibold">
                  <i class="bi bi-arrow-up-right me-1"></i>In Progress
                </div>
              </div>
              <div class="bcms-stat-icon-wrapper stat-icon-accent">
                <i class="bi bi-gear-wide-connected"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Total Clients -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card card-hover bcms-stat-card stat-info h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="bcms-stat-label">Total Clients</div>
                <div class="bcms-stat-value"><?= number_format($adminStats['total_clients']) ?></div>
                <div class="bcms-stat-subtext text-muted">Registered owners</div>
              </div>
              <div class="bcms-stat-icon-wrapper" style="background-color: rgba(13,202,240,0.12); color: #0aa2c0;">
                <i class="bi bi-people-fill"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Total Workers -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card card-hover bcms-stat-card stat-success h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="bcms-stat-label">Active Workers</div>
                <div class="bcms-stat-value text-success"><?= number_format($adminStats['total_workers']) ?></div>
                <div class="bcms-stat-subtext text-muted">Onsite labor force</div>
              </div>
              <div class="bcms-stat-icon-wrapper stat-icon-success">
                <i class="bi bi-person-badge-fill"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Financial Metric Cards (Row 2: Financials & Stock) -->
      <div class="row g-3 mb-4">
        <!-- Total Budget -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card card-hover bcms-stat-card h-100 shadow-sm border-0" style="border-left-color: #0d6efd;">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="bcms-stat-label">Total Budget</div>
                <div class="bcms-stat-value text-primary fs-5"><?= formatCurrency($adminStats['total_budget']) ?></div>
                <div class="bcms-stat-subtext text-muted">Across all projects</div>
              </div>
              <div class="bcms-stat-icon-wrapper stat-icon-primary">
                <i class="bi bi-wallet2"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Total Expenses -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card card-hover bcms-stat-card stat-danger h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="bcms-stat-label">Total Expenses</div>
                <div class="bcms-stat-value text-danger fs-5"><?= formatCurrency($adminStats['total_expenses']) ?></div>
                <div class="bcms-stat-subtext text-muted">Materials, labor, etc.</div>
              </div>
              <div class="bcms-stat-icon-wrapper stat-icon-danger">
                <i class="bi bi-cash-stack"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Total Payments -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card card-hover bcms-stat-card stat-success h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="bcms-stat-label">Total Payments</div>
                <div class="bcms-stat-value text-success fs-5"><?= formatCurrency($adminStats['total_payments']) ?></div>
                <div class="bcms-stat-subtext text-muted">Received from clients</div>
              </div>
              <div class="bcms-stat-icon-wrapper stat-icon-success">
                <i class="bi bi-credit-card-2-front-fill"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Low Stock Items -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card card-hover bcms-stat-card stat-warning h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="bcms-stat-label">Low Stock Items</div>
                <div class="bcms-stat-value <?= $adminStats['low_stock_count'] > 0 ? 'text-danger' : 'text-success' ?>">
                  <?= number_format($adminStats['low_stock_count']) ?>
                </div>
                <div class="bcms-stat-subtext">
                  <?php if ($adminStats['low_stock_count'] > 0): ?>
                    <span class="badge bg-danger">Needs Reorder</span>
                  <?php else: ?>
                    <span class="badge bg-success">Adequate Stock</span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="bcms-stat-icon-wrapper stat-icon-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="row g-4 mb-4">
        <!-- Projects by Status (Doughnut Chart) -->
        <div class="col-12 col-lg-5">
          <div class="card shadow-sm h-100 border-0">
            <div class="card-header bg-white border-bottom py-3">
              <h5 class="card-header-title text-dark">
                <i class="bi bi-pie-chart-fill text-primary me-2"></i>Projects by Status
              </h5>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
              <div style="width: 100%; max-width: 320px; height: 260px;">
                <canvas id="projectStatusChart"></canvas>
              </div>
              <div class="d-flex flex-wrap justify-content-center gap-3 mt-3 small">
                <span><span class="badge bg-warning text-dark me-1">Planning</span> <?= $statusCounts['planning'] ?></span>
                <span><span class="badge bg-success me-1">Active</span> <?= $statusCounts['active'] ?></span>
                <span><span class="badge bg-secondary me-1">On Hold</span> <?= $statusCounts['on_hold'] ?></span>
                <span><span class="badge bg-primary me-1">Completed</span> <?= $statusCounts['completed'] ?></span>
                <span><span class="badge bg-danger me-1">Cancelled</span> <?= $statusCounts['cancelled'] ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Monthly Expenses (Bar Chart) -->
        <div class="col-12 col-lg-7">
          <div class="card shadow-sm h-100 border-0">
            <div class="card-header bg-white border-bottom py-3">
              <h5 class="card-header-title text-dark">
                <i class="bi bi-bar-chart-fill text-accent me-2"></i>Monthly Expenses (Last 6 Months)
              </h5>
            </div>
            <div class="card-body">
              <div style="width: 100%; height: 280px;">
                <canvas id="monthlyExpensesChart"></canvas>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tables Row: Recent Projects & Recent Payments -->
      <div class="row g-4 mb-4">
        <!-- Recent Projects (Last 5) -->
        <div class="col-12 col-lg-7">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
              <h5 class="card-header-title text-dark mb-0">
                <i class="bi bi-building me-2 text-primary"></i>Recent Projects
              </h5>
              <a href="<?= APP_URL ?>/projects.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light small">
                    <tr>
                      <th>Project</th>
                      <th>Client</th>
                      <th>Budget</th>
                      <th style="min-width: 140px;">Progress</th>
                      <th>Status</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($recentProjects)): ?>
                      <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No projects found.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($recentProjects as $proj): ?>
                        <tr>
                          <td>
                            <a href="<?= APP_URL ?>/project_view.php?project_id=<?= (int)$proj['project_id'] ?>" class="fw-semibold text-dark text-decoration-none">
                              <?= e($proj['project_name']) ?>
                            </a>
                            <div class="small text-muted"><?= e($proj['location']) ?></div>
                          </td>
                          <td><?= e($proj['client_name']) ?></td>
                          <td class="fw-semibold"><?= formatCurrency($proj['budget']) ?></td>
                          <td>
                            <div class="d-flex align-items-center">
                              <span class="me-2 small fw-bold"><?= (int)$proj['progress'] ?>%</span>
                              <div class="progress flex-grow-1" style="height: 6px;">
                                <div class="progress-bar <?= progressColor((int)$proj['progress']) ?>" role="progressbar" style="width: <?= (int)$proj['progress'] ?>%"></div>
                              </div>
                            </div>
                          </td>
                          <td><span class="<?= statusBadge($proj['status']) ?>"><?= ucfirst(str_replace('_', ' ', e($proj['status']))) ?></span></td>
                          <td class="text-end">
                            <a href="<?= APP_URL ?>/project_view.php?project_id=<?= (int)$proj['project_id'] ?>" class="btn btn-sm btn-light border" title="View Project Details">
                              <i class="bi bi-arrow-right"></i>
                            </a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Payments (Last 5) -->
        <div class="col-12 col-lg-5">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
              <h5 class="card-header-title text-dark mb-0">
                <i class="bi bi-credit-card-2-front me-2 text-success"></i>Recent Payments
              </h5>
              <a href="<?= APP_URL ?>/payments.php" class="btn btn-sm btn-outline-success">View All</a>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light small">
                    <tr>
                      <th>Project / Client</th>
                      <th>Amount</th>
                      <th>Date</th>
                      <th>Mode</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($recentPayments)): ?>
                      <tr>
                        <td colspan="4" class="text-center py-4 text-muted">No payments recorded.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($recentPayments as $pay): ?>
                        <tr>
                          <td>
                            <div class="fw-semibold text-dark"><?= e($pay['project_name']) ?></div>
                            <small class="text-muted"><?= e($pay['client_name']) ?></small>
                          </td>
                          <td class="fw-bold text-success"><?= formatCurrency($pay['amount']) ?></td>
                          <td class="small text-muted"><?= formatDate($pay['payment_date']) ?></td>
                          <td><span class="badge bg-light text-dark border"><?= ucfirst(str_replace('_', ' ', e($pay['payment_mode']))) ?></span></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Low Stock Alerts Section (If Any) -->
      <?php if (!empty($lowStockItems)): ?>
        <div class="card shadow-sm border-0 border-start border-danger border-4 mb-4">
          <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-header-title text-danger mb-0">
              <i class="bi bi-exclamation-triangle-fill me-2"></i>Low Stock Inventory Alerts (<?= count($lowStockItems) ?> Items)
            </h5>
            <a href="<?= APP_URL ?>/materials.php" class="btn btn-sm btn-danger">Manage Materials</a>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                  <tr>
                    <th>Material</th>
                    <th>Unit</th>
                    <th>Current Stock</th>
                    <th>Threshold</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($lowStockItems as $mat): ?>
                    <tr>
                      <td class="fw-semibold text-dark"><?= e($mat['material_name']) ?></td>
                      <td><?= e($mat['unit']) ?></td>
                      <td class="fw-bold text-danger"><?= number_format((float)$mat['computed_stock'], 2) ?></td>
                      <td><?= number_format((float)$mat['low_stock_threshold'], 2) ?></td>
                      <td>
                        <span class="badge bg-danger">Critical Shortage</span>
                      </td>
                      <td class="text-end">
                        <a href="<?= APP_URL ?>/purchases.php?material_id=<?= (int)$mat['material_id'] ?>" class="btn btn-sm btn-outline-primary">
                          <i class="bi bi-cart-plus me-1"></i>Purchase
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>

    <?php elseif ($userRole === ROLE_CLIENT): ?>
      <!-- ======================================================== -->
      <!-- CLIENT DASHBOARD -->
      <!-- ======================================================== -->

      <!-- Client Metrics -->
      <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card card-hover bcms-stat-card h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="bcms-stat-label">My Projects</div>
                <div class="bcms-stat-value"><?= number_format($clientStats['total_projects'] ?? 0) ?></div>
                <div class="bcms-stat-subtext text-muted">Total contracted</div>
              </div>
              <div class="bcms-stat-icon-wrapper stat-icon-primary">
                <i class="bi bi-building"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card card-hover bcms-stat-card stat-accent h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="bcms-stat-label">Active Projects</div>
                <div class="bcms-stat-value text-primary"><?= number_format($clientStats['active_projects'] ?? 0) ?></div>
                <div class="bcms-stat-subtext text-success fw-semibold">Under Construction</div>
              </div>
              <div class="bcms-stat-icon-wrapper stat-icon-accent">
                <i class="bi bi-tools"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card card-hover bcms-stat-card stat-success h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="bcms-stat-label">Completed Projects</div>
                <div class="bcms-stat-value text-success"><?= number_format($clientStats['completed_projects'] ?? 0) ?></div>
                <div class="bcms-stat-subtext text-muted">Finished successfully</div>
              </div>
              <div class="bcms-stat-icon-wrapper stat-icon-success">
                <i class="bi bi-check-circle-fill"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card card-hover bcms-stat-card stat-info h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="bcms-stat-label">Total Payments Paid</div>
                <div class="bcms-stat-value text-success fs-5"><?= formatCurrency($clientStats['total_payments'] ?? 0.0) ?></div>
                <div class="bcms-stat-subtext text-muted">All paid installments</div>
              </div>
              <div class="bcms-stat-icon-wrapper stat-icon-success">
                <i class="bi bi-wallet2"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Client Projects Table -->
      <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
          <h5 class="card-header-title text-dark mb-0">
            <i class="bi bi-building me-2 text-primary"></i>My Construction Projects
          </h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light small">
                <tr>
                  <th>Project Name</th>
                  <th>Location</th>
                  <th>Contract Budget</th>
                  <th style="min-width: 140px;">Progress</th>
                  <th>Status</th>
                  <th>End Date</th>
                  <th class="text-end">View Details</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($clientProjects)): ?>
                  <tr>
                    <td colspan="7" class="text-center py-4 text-muted">No projects associated with your account.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($clientProjects as $cp): ?>
                    <tr>
                      <td class="fw-semibold text-dark"><?= e($cp['project_name']) ?></td>
                      <td><?= e($cp['location']) ?></td>
                      <td class="fw-bold"><?= formatCurrency($cp['budget']) ?></td>
                      <td>
                        <div class="d-flex align-items-center">
                          <span class="me-2 small fw-bold"><?= (int)$cp['progress'] ?>%</span>
                          <div class="progress flex-grow-1" style="height: 6px;">
                            <div class="progress-bar <?= progressColor((int)$cp['progress']) ?>" role="progressbar" style="width: <?= (int)$cp['progress'] ?>%"></div>
                          </div>
                        </div>
                      </td>
                      <td><span class="<?= statusBadge($cp['status']) ?>"><?= ucfirst(str_replace('_', ' ', e($cp['status']))) ?></span></td>
                      <td class="small text-muted"><?= formatDate($cp['end_date']) ?></td>
                      <td class="text-end">
                        <a href="<?= APP_URL ?>/project_view.php?project_id=<?= (int)$cp['project_id'] ?>" class="btn btn-sm btn-primary">
                          <i class="bi bi-eye me-1"></i>View
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Client Recent Payments Table -->
      <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
          <h5 class="card-header-title text-dark mb-0">
            <i class="bi bi-credit-card-2-front me-2 text-success"></i>My Payment Receipts
          </h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light small">
                <tr>
                  <th>Project</th>
                  <th>Amount</th>
                  <th>Date</th>
                  <th>Payment Type</th>
                  <th>Mode</th>
                  <th>Reference No</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($clientPayments)): ?>
                  <tr>
                    <td colspan="6" class="text-center py-4 text-muted">No payments found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($clientPayments as $cpPay): ?>
                    <tr>
                      <td class="fw-semibold text-dark"><?= e($cpPay['project_name']) ?></td>
                      <td class="fw-bold text-success"><?= formatCurrency($cpPay['amount']) ?></td>
                      <td class="small text-muted"><?= formatDate($cpPay['payment_date']) ?></td>
                      <td><span class="badge bg-light text-dark border"><?= ucfirst(e($cpPay['payment_type'])) ?></span></td>
                      <td><?= ucfirst(str_replace('_', ' ', e($cpPay['payment_mode']))) ?></td>
                      <td><code><?= e($cpPay['reference_no'] ?: '—') ?></code></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    <?php elseif ($userRole === ROLE_WORKER): ?>
      <!-- ======================================================== -->
      <!-- WORKER DASHBOARD -->
      <!-- ======================================================== -->

      <!-- Worker Summary Cards -->
      <div class="row g-3 mb-4">
        <!-- Assigned Tasks -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card card-hover bcms-stat-card h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="bcms-stat-label">Assigned Tasks</div>
                <div class="bcms-stat-value"><?= number_format($workerStats['total_tasks'] ?? 0) ?></div>
                <div class="bcms-stat-subtext text-muted">Total tasks assigned</div>
              </div>
              <div class="bcms-stat-icon-wrapper stat-icon-primary">
                <i class="bi bi-check2-square"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Pending / In Progress Tasks -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card card-hover bcms-stat-card stat-accent h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="bcms-stat-label">Active Tasks</div>
                <div class="bcms-stat-value text-primary">
                  <?= number_format(($workerStats['pending_tasks'] ?? 0) + ($workerStats['in_progress_tasks'] ?? 0)) ?>
                </div>
                <div class="bcms-stat-subtext text-muted">Pending completion</div>
              </div>
              <div class="bcms-stat-icon-wrapper stat-icon-accent">
                <i class="bi bi-hourglass-split"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Completed Tasks -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card card-hover bcms-stat-card stat-success h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="bcms-stat-label">Completed Tasks</div>
                <div class="bcms-stat-value text-success"><?= number_format($workerStats['completed_tasks'] ?? 0) ?></div>
                <div class="bcms-stat-subtext text-muted">Marked done</div>
              </div>
              <div class="bcms-stat-icon-wrapper stat-icon-success">
                <i class="bi bi-check-circle-fill"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Today's Attendance Status -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card card-hover bcms-stat-card stat-info h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="bcms-stat-label">Today's Attendance</div>
                <div class="bcms-stat-value fs-5">
                  <?php if ($workerTodayAtt): ?>
                    <span class="<?= statusBadge($workerTodayAtt['status']) ?>">
                      <?= ucfirst(str_replace('_', ' ', e($workerTodayAtt['status']))) ?>
                    </span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Not Marked Yet</span>
                  <?php endif; ?>
                </div>
                <div class="bcms-stat-subtext text-muted">
                  <?= $workerTodayAtt ? e($workerTodayAtt['project_name']) : 'Awaiting site supervisor' ?>
                </div>
              </div>
              <div class="bcms-stat-icon-wrapper" style="background-color: rgba(13,202,240,0.12); color: #0aa2c0;">
                <i class="bi bi-calendar-check-fill"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Worker Tasks & Attendance Row -->
      <div class="row g-4 mb-4">
        <!-- Assigned Tasks Table -->
        <div class="col-12 col-lg-8">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
              <h5 class="card-header-title text-dark mb-0">
                <i class="bi bi-check2-square text-primary me-2"></i>My Assigned Tasks
              </h5>
              <a href="<?= APP_URL ?>/tasks.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light small">
                    <tr>
                      <th>Task</th>
                      <th>Project</th>
                      <th>Due Date</th>
                      <th style="min-width: 120px;">Progress</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($workerTasks)): ?>
                      <tr>
                        <td colspan="5" class="text-center py-4 text-muted">No tasks assigned yet.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($workerTasks as $wt): ?>
                        <tr>
                          <td class="fw-semibold text-dark"><?= e($wt['task_name']) ?></td>
                          <td><?= e($wt['project_name']) ?></td>
                          <td class="small text-muted"><?= formatDate($wt['due_date']) ?></td>
                          <td>
                            <div class="d-flex align-items-center">
                              <span class="me-2 small fw-bold"><?= (int)$wt['progress'] ?>%</span>
                              <div class="progress flex-grow-1" style="height: 6px;">
                                <div class="progress-bar <?= progressColor((int)$wt['progress']) ?>" role="progressbar" style="width: <?= (int)$wt['progress'] ?>%"></div>
                              </div>
                            </div>
                          </td>
                          <td><span class="<?= statusBadge($wt['status']) ?>"><?= ucfirst(str_replace('_', ' ', e($wt['status']))) ?></span></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Attendance Log -->
        <div class="col-12 col-lg-4">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3">
              <h5 class="card-header-title text-dark mb-0">
                <i class="bi bi-calendar-check text-success me-2"></i>My Recent Attendance
              </h5>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light small">
                    <tr>
                      <th>Date</th>
                      <th>Project</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($workerAttendance)): ?>
                      <tr>
                        <td colspan="3" class="text-center py-4 text-muted">No attendance records.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($workerAttendance as $wa): ?>
                        <tr>
                          <td class="small fw-semibold"><?= formatDate($wa['attendance_date']) ?></td>
                          <td class="small text-truncate" style="max-width: 120px;" title="<?= e($wa['project_name']) ?>">
                            <?= e($wa['project_name']) ?>
                          </td>
                          <td><span class="<?= statusBadge($wa['status']) ?>"><?= ucfirst(str_replace('_', ' ', e($wa['status']))) ?></span></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>
</main>

<?php if ($userRole === ROLE_ADMIN): ?>
<!-- Chart.js Scripts Initialization -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  // 1. Projects by Status Doughnut Chart
  const statusCtx = document.getElementById('projectStatusChart');
  if (statusCtx) {
    new Chart(statusCtx, {
      type: 'doughnut',
      data: {
        labels: ['Planning', 'Active', 'On Hold', 'Completed', 'Cancelled'],
        datasets: [{
          data: [
            <?= (int)$statusCounts['planning'] ?>,
            <?= (int)$statusCounts['active'] ?>,
            <?= (int)$statusCounts['on_hold'] ?>,
            <?= (int)$statusCounts['completed'] ?>,
            <?= (int)$statusCounts['cancelled'] ?>
          ],
          backgroundColor: [
            '#ffc107', // Planning (Amber)
            '#198754', // Active (Green)
            '#6c757d', // On Hold (Grey)
            '#1a3a5c', // Completed (Navy Blue)
            '#dc3545'  // Cancelled (Red)
          ],
          borderWidth: 2,
          borderColor: '#ffffff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const value = context.parsed;
                const pct = total > 0 ? Math.round((value / total) * 100) : 0;
                return `${context.label}: ${value} (${pct}%)`;
              }
            }
          }
        },
        cutout: '68%'
      }
    });
  }

  // 2. Monthly Expenses Bar Chart
  const expenseCtx = document.getElementById('monthlyExpensesChart');
  if (expenseCtx) {
    const labels = <?= json_encode(array_column($monthlyExpenses, 'label')) ?>;
    const totals = <?= json_encode(array_map('floatval', array_column($monthlyExpenses, 'total'))) ?>;

    new Chart(expenseCtx, {
      type: 'bar',
      data: {
        labels: labels.length ? labels : ['No recent data'],
        datasets: [{
          label: 'Expenses (₹)',
          data: totals.length ? totals : [0],
          backgroundColor: 'rgba(240, 130, 29, 0.85)',
          borderColor: '#f0821d',
          borderWidth: 1,
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                return ' Expenses: ₹ ' + Number(context.parsed.y).toLocaleString('en-IN', {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2
                });
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function (value) {
                if (value >= 100000) {
                  return '₹ ' + (value / 100000).toFixed(1) + 'L';
                } else if (value >= 1000) {
                  return '₹ ' + (value / 1000).toFixed(0) + 'k';
                }
                return '₹ ' + value;
              }
            },
            grid: {
              color: '#f1f5f9'
            }
          },
          x: {
            grid: {
              display: false
            }
          }
        }
      }
    });
  }
});
</script>
<?php endif; ?>

<?php
require_once VIEW_PATH . '/layout/footer.php';
