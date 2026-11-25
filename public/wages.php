<?php
/**
 * BCMS — Worker Wage Calculation Module
 * Derives labor wages from recorded daily attendance records with transparent mathematical breakdowns.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireRole(ROLE_ADMIN);

$workerModel     = new WorkerModel();
$projectModel    = new ProjectModel();
$attendanceModel = new AttendanceModel();

$projectId = intInput($_GET['project_id'] ?? null, 1);
$workerId  = intInput($_GET['worker_id'] ?? null, 1);
$fromDate  = dateInput($_GET['from_date'] ?? date('Y-m-01')); // default start of current month
$toDate    = dateInput($_GET['to_date'] ?? date('Y-m-d'));     // default today

$projects = [];
$workers  = [];
$wageList = [];
$stats    = [
    'total_wages'        => 0.0,
    'total_working_days' => 0.0,
    'workers_count'      => 0,
];

try {
    $projects = $projectModel->getAll();
    $workers  = $workerModel->getAll(['is_active' => 1]);

    // Build query for wage calculations
    $sql = 'SELECT
                w.worker_id,
                w.name AS worker_name,
                w.phone AS worker_phone,
                w.skill,
                w.daily_wage,
                COUNT(CASE WHEN a.status = "present" THEN 1 END) AS present_count,
                COUNT(CASE WHEN a.status = "half_day" THEN 1 END) AS half_day_count,
                COUNT(CASE WHEN a.status = "absent" THEN 1 END) AS absent_count,
                COUNT(CASE WHEN a.status = "holiday" THEN 1 END) AS holiday_count
            FROM workers w
            LEFT JOIN attendance a ON a.worker_id = w.worker_id';
    $params = [];
    $where = [];

    if ($projectId) {
        $where[] = 'a.project_id = ?';
        $params[] = $projectId;
    }
    if ($workerId) {
        $where[] = 'w.worker_id = ?';
        $params[] = $workerId;
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

    $sql .= ' GROUP BY w.worker_id, w.name, w.phone, w.skill, w.daily_wage ORDER BY w.name ASC';

    $rows = dbQuery($sql, $params);

    foreach ($rows as $r) {
        $present  = (int)$r['present_count'];
        $halfDay  = (int)$r['half_day_count'];
        $absent   = (int)$r['absent_count'];
        $holiday  = (int)$r['holiday_count'];
        $wageRate = (float)$r['daily_wage'];

        // Formula: Present (1.0) + Half-Day (0.5)
        $workingDays = (float)($present + ($halfDay * 0.5));
        $totalWage   = round($workingDays * $wageRate, 2);

        $stats['total_working_days'] += $workingDays;
        $stats['total_wages']        += $totalWage;
        if ($workingDays > 0 || $absent > 0) {
            $stats['workers_count']++;
        }

        $wageList[] = [
            'worker_id'    => (int)$r['worker_id'],
            'worker_name'  => $r['worker_name'],
            'worker_phone' => $r['worker_phone'],
            'skill'        => $r['skill'],
            'daily_wage'   => $wageRate,
            'present'      => $present,
            'half_day'     => $halfDay,
            'absent'       => $absent,
            'holiday'      => $holiday,
            'working_days' => $workingDays,
            'total_wage'   => $totalWage,
        ];
    }
} catch (Throwable $e) {
    setFlash('error', 'Error calculating wages: ' . $e->getMessage());
}

$pageTitle = 'Worker Wage Calculations';
$activeNav = 'wages';

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
          <li class="breadcrumb-item"><a href="<?= APP_URL ?>/workers.php">Workers</a></li>
          <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
        </ol>
      </nav>
      <h2 class="h4 mb-0 fw-bold"><?= e($pageTitle) ?></h2>
      <p class="text-muted small mb-0">Transparent payroll calculations derived strictly from verified site attendance logs.</p>
    </div>
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>Print Payroll Sheet
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

  <!-- Formula Transparency Banner -->
  <div class="alert alert-info border-0 shadow-sm d-flex align-items-center gap-3 mb-4">
    <div class="fs-2 text-primary"><i class="bi bi-calculator"></i></div>
    <div>
      <h6 class="fw-bold mb-1">Verified Academic Formula Derivation</h6>
      <span class="small">
        <strong>Working Days</strong> = <code>Present Days (1.0) + Half-Days (0.5)</code> &bull;
        <strong>Gross Total Wage</strong> = <code>Working Days &times; Daily Wage Rate</code>.
        Absences and holidays receive 0.0 wage allowance.
      </span>
    </div>
  </div>

  <!-- KPI Summary Cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success me-3">
            <i class="bi bi-cash-stack fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Total Wages Payable</div>
            <div class="fs-4 fw-bold text-success"><?= formatCurrency($stats['total_wages']) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary me-3">
            <i class="bi bi-person-check fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Total Labor Days Deployed</div>
            <div class="fs-4 fw-bold text-primary"><?= number_format($stats['total_working_days'], 1) ?> Days</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-warning bg-opacity-10 text-warning me-3">
            <i class="bi bi-people fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Active Workers Deployed</div>
            <div class="fs-4 fw-bold text-dark"><?= number_format($stats['workers_count']) ?> Laborers</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <form method="GET" action="<?= APP_URL ?>/wages.php" class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Project Site</label>
          <select name="project_id" class="form-select">
            <option value="">All Projects</option>
            <?php foreach ($projects as $pr): ?>
              <option value="<?= $pr['project_id'] ?>" <?= $projectId === (int)$pr['project_id'] ? 'selected' : '' ?>>
                <?= e($pr['project_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Specific Worker</label>
          <select name="worker_id" class="form-select">
            <option value="">All Workers</option>
            <?php foreach ($workers as $w): ?>
              <option value="<?= $w['worker_id'] ?>" <?= $workerId === (int)$w['worker_id'] ? 'selected' : '' ?>>
                <?= e($w['name']) ?> (<?= e($w['skill']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold">From Date</label>
          <input type="date" name="from_date" class="form-control" value="<?= e($fromDate) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold">To Date</label>
          <input type="date" name="to_date" class="form-control" value="<?= e($toDate) ?>">
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-calculator me-1"></i>Calculate</button>
          <a href="<?= APP_URL ?>/wages.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
        </div>
      </form>
    </div>
  </div>

  <!-- Wage Calculation Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between">
      <h5 class="mb-0 fw-bold">Wage Breakdown Sheet (<?= count($wageList) ?> workers)</h5>
      <span class="text-muted small">Period: <?= formatDate($fromDate) ?> &rarr; <?= formatDate($toDate) ?></span>
    </div>
    <div class="card-body p-0">
      <?php if (empty($wageList)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-person-x fs-2"></i>
          <h5 class="mt-3">No attendance records found</h5>
          <p class="small">Mark attendance in the attendance module to generate wage calculations.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-4">Worker / Trade</th>
                <th>Daily Rate</th>
                <th>Full Day (1.0)</th>
                <th>Half Day (0.5)</th>
                <th>Absent (0.0)</th>
                <th>Working Days</th>
                <th class="pe-4 text-end">Total Payable Wage</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($wageList as $w): ?>
                <tr>
                  <td class="ps-4">
                    <div class="fw-bold">
                      <a href="<?= APP_URL ?>/worker_view.php?id=<?= $w['worker_id'] ?>" class="text-decoration-none">
                        <?= e($w['worker_name']) ?>
                      </a>
                    </div>
                    <div class="small text-muted"><span class="badge bg-light text-dark border"><?= e($w['skill']) ?></span> <?= e($w['worker_phone']) ?></div>
                  </td>
                  <td class="fw-semibold"><?= formatCurrency($w['daily_wage']) ?></td>
                  <td class="text-success fw-bold"><?= $w['present'] ?></td>
                  <td class="text-secondary"><?= $w['half_day'] ?></td>
                  <td class="text-danger"><?= $w['absent'] ?></td>
                  <td class="fw-bold text-dark fs-6"><?= number_format($w['working_days'], 1) ?> days</td>
                  <td class="pe-4 text-end fw-bold text-success fs-5"><?= formatCurrency($w['total_wage']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-bold">
              <tr>
                <td class="ps-4" colspan="5">TOTAL PAYROLL FOR SELECTED PERIOD</td>
                <td class="fs-6"><?= number_format($stats['total_working_days'], 1) ?> days</td>
                <td class="pe-4 text-end text-success fs-5"><?= formatCurrency($stats['total_wages']) ?></td>
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

