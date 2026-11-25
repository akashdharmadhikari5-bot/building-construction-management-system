<?php
/**
 * BCMS — Worker Profile & Detail View
 * Accessible by Admin (all workers) and Worker (own profile only).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

// Only Admin and Worker roles can access this page
requireRole(ROLE_ADMIN, ROLE_WORKER);

$workerModel     = new WorkerModel();
$taskModel       = new TaskModel();
$attendanceModel = new AttendanceModel();

$currentUser = currentUser();
$isAdmin     = hasRole(ROLE_ADMIN);
$isWorker    = hasRole(ROLE_WORKER);

// ── IDOR Protection & Permission Check ──────────────────────────────────────
$requestedId = intInput($_GET['worker_id'] ?? null, 1);

if ($isWorker) {
    // Workers can strictly only view their own linked worker profile
    $ownWorkerId = (int)($currentUser['linked_id'] ?? 0);
    if ($ownWorkerId <= 0) {
        setFlash('error', 'Your user account is not linked to any worker record.');
        redirect('/dashboard.php');
    }

    if ($requestedId !== null && $requestedId !== $ownWorkerId) {
        setFlash('error', 'You are only authorized to view your own worker profile.');
        redirect('/worker_view.php?worker_id=' . $ownWorkerId);
    }
    $workerId = $ownWorkerId;
} else {
    // Admin must provide a valid worker ID
    if (!$requestedId) {
        setFlash('error', 'Please select a worker to view.');
        redirect('/workers.php');
    }
    $workerId = $requestedId;
}

// Fetch worker record
$worker = $workerModel->getById($workerId);
if (!$worker) {
    setFlash('error', "Worker #{$workerId} not found.");
    redirect($isAdmin ? '/workers.php' : '/dashboard.php');
}

// Fetch assignments and assigned tasks
$assignments = $workerModel->getAssignments($workerId);
$tasks       = $taskModel->getByWorker($workerId);

// ── Attendance for Selected Month ───────────────────────────────────────────
$selectedMonth = strInput($_GET['month'] ?? date('Y-m'), 7);
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$monthlyAttendance = dbQuery(
    'SELECT a.*, p.project_name
     FROM attendance a
     JOIN projects p ON p.project_id = a.project_id
     WHERE a.worker_id = ? AND a.attendance_date LIKE ?
     ORDER BY a.attendance_date DESC',
    [$workerId, $selectedMonth . '%']
);

$presentCount = 0;
$halfDayCount = 0;
$absentCount  = 0;
$holidayCount = 0;

foreach ($monthlyAttendance as $att) {
    match ($att['status']) {
        'present'  => $presentCount++,
        'half_day' => $halfDayCount++,
        'absent'   => $absentCount++,
        'holiday'  => $holidayCount++,
        default    => null,
    };
}

$monthlyWorkingDays = (float)($presentCount + ($halfDayCount * 0.5));
$monthlyEarnedWage  = round($monthlyWorkingDays * (float)$worker['daily_wage'], 2);

// ── Wage Calculation Form Handling ─────────────────────────────────────────
$calcProjectId = intInput($_GET['calc_project_id'] ?? null, 1);
$calcFromDate  = dateInput($_GET['from_date'] ?? null);
$calcToDate    = dateInput($_GET['to_date'] ?? null);
$wageReport    = null;

if ($calcProjectId) {
    try {
        $wageReport = $workerModel->calculateWage($workerId, $calcProjectId, $calcFromDate, $calcToDate);
    } catch (Throwable $e) {
        $calcError = $e->getMessage();
    }
}

$pageTitle = $worker['name'] . ' — Worker Profile';
require_once BCMS_ROOT . '/app/views/layout/header.php';
require_once BCMS_ROOT . '/app/views/layout/sidebar.php';
require_once BCMS_ROOT . '/app/views/layout/topnav.php';
?>

<main class="bcms-main">
    <div class="container-fluid">
        <!-- Breadcrumb & Top Bar -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small">
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
                        <?php if ($isAdmin): ?>
                            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/workers.php">Workers</a></li>
                        <?php endif; ?>
                        <li class="breadcrumb-item active" aria-current="page"><?= e($worker['name']) ?></li>
                    </ol>
                </nav>
                <div class="d-flex align-items-center gap-2">
                    <h2 class="h4 mb-0 fw-bold text-dark"><?= e($worker['name']) ?></h2>
                    <span class="badge bg-light text-dark border">
                        <i class="bi bi-hammer me-1 text-muted"></i><?= e($worker['skill']) ?>
                    </span>
                    <?php if ((bool)$worker['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <?php if ($isAdmin): ?>
                    <a href="<?= APP_URL ?>/workers.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back to Workers List
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Flash Notifications -->
        <?php if ($msg = getFlash('success')): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= e($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($msg = getFlash('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left Column: Profile Card + Wage Calculator -->
            <div class="col-lg-4">
                <!-- Worker Info Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body text-center p-4">
                        <div class="bcms-user-avatar mx-auto mb-3" style="width: 72px; height: 72px; font-size: 1.75rem;">
                            <?= e(mb_strtoupper(mb_substr($worker['name'], 0, 1))) ?>
                        </div>
                        <h4 class="h5 fw-bold mb-1 text-dark"><?= e($worker['name']) ?></h4>
                        <div class="text-muted small mb-3">Worker ID: #<?= (int)$worker['worker_id'] ?></div>

                        <div class="p-3 bg-light rounded text-start mb-3">
                            <div class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted small">Phone</span>
                                <a href="tel:<?= e($worker['phone']) ?>" class="fw-semibold small text-decoration-none text-dark">
                                    <i class="bi bi-telephone text-primary me-1"></i><?= e($worker['phone']) ?>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted small">Trade / Skill</span>
                                <span class="fw-semibold small text-dark"><?= e($worker['skill']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted small">Daily Wage Rate</span>
                                <span class="fw-bold text-success font-monospace"><?= formatCurrency($worker['daily_wage']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted small">Status</span>
                                <span>
                                    <?php if ((bool)$worker['is_active']): ?>
                                        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted small">Joined Date</span>
                                <span class="text-dark small"><?= formatDate($worker['created_at']) ?></span>
                            </div>
                        </div>

                        <?php if ($isAdmin): ?>
                            <div class="d-grid">
                                <a href="<?= APP_URL ?>/assignments.php" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-person-check me-1"></i> Assign to Project
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Wage Calculation Form Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <span class="card-header-title">
                            <i class="bi bi-calculator me-2 text-primary"></i>
                            Project Wage Calculator
                        </span>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="<?= APP_URL ?>/worker_view.php" class="mb-3">
                            <input type="hidden" name="worker_id" value="<?= $workerId ?>">
                            <?php if (!empty($selectedMonth)): ?>
                                <input type="hidden" name="month" value="<?= e($selectedMonth) ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="calc_project_id" class="form-label small fw-semibold">Select Project <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="calc_project_id" name="calc_project_id" required>
                                    <option value="">-- Choose Project --</option>
                                    <?php foreach ($assignments as $a): ?>
                                        <option value="<?= (int)$a['project_id'] ?>" <?= $calcProjectId === (int)$a['project_id'] ? 'selected' : '' ?>>
                                            <?= e($a['project_name']) ?> <?= !(bool)$a['is_active'] ? '(Past)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label for="from_date" class="form-label small fw-semibold">From Date</label>
                                    <input
                                        type="date"
                                        class="form-control form-control-sm"
                                        id="from_date"
                                        name="from_date"
                                        value="<?= e($calcFromDate ?? '') ?>"
                                    >
                                </div>
                                <div class="col-6">
                                    <label for="to_date" class="form-label small fw-semibold">To Date</label>
                                    <input
                                        type="date"
                                        class="form-control form-control-sm"
                                        id="to_date"
                                        name="to_date"
                                        value="<?= e($calcToDate ?? '') ?>"
                                    >
                                </div>
                            </div>

                            <button type="submit" class="btn btn-sm btn-primary w-100">
                                <i class="bi bi-calculator-fill me-1"></i> Calculate Wage
                            </button>
                        </form>

                        <!-- Wage Calculation Result -->
                        <?php if ($wageReport): ?>
                            <div class="p-3 bg-light border rounded">
                                <h6 class="fw-bold mb-2 text-dark small text-uppercase">
                                    <i class="bi bi-receipt me-1 text-primary"></i>Wage Calculation Breakdown
                                </h6>
                                <div class="small text-muted mb-2">
                                    Period: <strong><?= $calcFromDate ? formatDate($calcFromDate) : 'Start' ?></strong> to <strong><?= $calcToDate ? formatDate($calcToDate) : 'Present' ?></strong>
                                </div>
                                <table class="table table-sm table-borderless mb-2 small">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted">Present Full Days:</td>
                                            <td class="text-end fw-semibold"><?= $wageReport['present_days'] ?> days</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Half Days (50%):</td>
                                            <td class="text-end fw-semibold"><?= $wageReport['half_days'] ?> days</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Absent Days:</td>
                                            <td class="text-end text-danger"><?= $wageReport['absent_days'] ?> days</td>
                                        </tr>
                                        <tr class="border-top">
                                            <td class="fw-bold">Total Working Days:</td>
                                            <td class="text-end fw-bold text-dark"><?= number_format($wageReport['working_days'], 1) ?> days</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Daily Wage Rate:</td>
                                            <td class="text-end font-monospace"><?= formatCurrency($wageReport['daily_wage']) ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class="p-2 bg-success-subtle text-success-emphasis border border-success-subtle rounded text-center">
                                    <span class="small d-block text-uppercase fw-semibold">Total Calculated Wage</span>
                                    <span class="fs-4 fw-bold font-monospace"><?= formatCurrency($wageReport['total_wage']) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Monthly Attendance, Current Projects, Tasks -->
            <div class="col-lg-8">
                <!-- Attendance Summary Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <span class="card-header-title">
                            <i class="bi bi-calendar-check me-2 text-primary"></i>
                            Monthly Attendance Summary
                        </span>
                        <!-- Month Selector Form -->
                        <form method="GET" action="<?= APP_URL ?>/worker_view.php" class="d-flex align-items-center gap-2">
                            <input type="hidden" name="worker_id" value="<?= $workerId ?>">
                            <?php if ($calcProjectId): ?>
                                <input type="hidden" name="calc_project_id" value="<?= $calcProjectId ?>">
                                <input type="hidden" name="from_date" value="<?= e($calcFromDate ?? '') ?>">
                                <input type="hidden" name="to_date" value="<?= e($calcToDate ?? '') ?>">
                            <?php endif; ?>
                            <input
                                type="month"
                                class="form-control form-control-sm"
                                name="month"
                                value="<?= e($selectedMonth) ?>"
                                onchange="this.form.submit()"
                            >
                        </form>
                    </div>
                    <div class="card-body">
                        <!-- Stat Highlights for Month -->
                        <div class="row g-2 mb-4 text-center">
                            <div class="col-4 col-md-2">
                                <div class="p-2 border rounded bg-light">
                                    <div class="small text-muted">Present</div>
                                    <div class="fs-5 fw-bold text-success"><?= $presentCount ?></div>
                                </div>
                            </div>
                            <div class="col-4 col-md-2">
                                <div class="p-2 border rounded bg-light">
                                    <div class="small text-muted">Half Day</div>
                                    <div class="fs-5 fw-bold text-warning"><?= $halfDayCount ?></div>
                                </div>
                            </div>
                            <div class="col-4 col-md-2">
                                <div class="p-2 border rounded bg-light">
                                    <div class="small text-muted">Absent</div>
                                    <div class="fs-5 fw-bold text-danger"><?= $absentCount ?></div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="p-2 border rounded bg-light">
                                    <div class="small text-muted">Working Days</div>
                                    <div class="fs-5 fw-bold text-dark"><?= number_format($monthlyWorkingDays, 1) ?></div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="p-2 border rounded bg-success-subtle text-success-emphasis border-success-subtle">
                                    <div class="small">Earned Wage</div>
                                    <div class="fs-5 fw-bold font-monospace"><?= formatCurrency($monthlyEarnedWage) ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Daily Attendance Log for Selected Month -->
                        <h6 class="fw-bold text-dark small text-uppercase mb-2">
                            Daily Records (<?= date('F Y', strtotime($selectedMonth . '-01')) ?>)
                        </h6>
                        <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light text-muted small text-uppercase sticky-top">
                                    <tr>
                                        <th>Date</th>
                                        <th>Project</th>
                                        <th>Status</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($monthlyAttendance)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted small">
                                                No attendance records found for <?= date('F Y', strtotime($selectedMonth . '-01')) ?>.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($monthlyAttendance as $rec): ?>
                                            <tr>
                                                <td class="fw-semibold small"><?= formatDate($rec['attendance_date']) ?></td>
                                                <td class="small"><?= e($rec['project_name']) ?></td>
                                                <td>
                                                    <span class="<?= statusBadge($rec['status']) ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $rec['status'])) ?>
                                                    </span>
                                                </td>
                                                <td class="small text-muted"><?= e($rec['remarks'] ?: '—') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Project Assignments Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <span class="card-header-title">
                            <i class="bi bi-building me-2 text-primary"></i>
                            Project Assignments
                            <span class="badge bg-secondary rounded-pill ms-2"><?= count($assignments) ?></span>
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th>Project Name</th>
                                    <th>Location</th>
                                    <th>Assigned Date</th>
                                    <th>End Date</th>
                                    <th>Project Status</th>
                                    <th>Assignment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($assignments)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted small">
                                            No project assignments recorded for this worker.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($assignments as $asg): ?>
                                        <tr>
                                            <td class="fw-semibold">
                                                <?= e($asg['project_name']) ?>
                                            </td>
                                            <td class="small text-muted"><?= e($asg['location']) ?></td>
                                            <td class="small"><?= formatDate($asg['assigned_date']) ?></td>
                                            <td class="small"><?= formatDate($asg['end_date']) ?></td>
                                            <td>
                                                <span class="<?= statusBadge($asg['project_status']) ?>">
                                                    <?= ucfirst($asg['project_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ((bool)$asg['is_active']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle me-1"></i>Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="bi bi-clock-history me-1"></i>Completed
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Assigned Tasks Card -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <span class="card-header-title">
                            <i class="bi bi-check2-square me-2 text-primary"></i>
                            Assigned Tasks
                            <span class="badge bg-secondary rounded-pill ms-2"><?= count($tasks) ?></span>
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th>Task</th>
                                    <th>Project</th>
                                    <th>Due Date</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tasks)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted small">
                                            No tasks currently assigned to this worker.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tasks as $t): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= e($t['task_name']) ?></div>
                                                <?php if (!empty($t['description'])): ?>
                                                    <div class="small text-muted text-truncate" style="max-width: 200px;">
                                                        <?= e($t['description']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small"><?= e($t['project_name']) ?></td>
                                            <td class="small"><?= formatDate($t['due_date']) ?></td>
                                            <td style="width: 140px;">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div
                                                            class="progress-bar <?= progressColor((int)$t['progress']) ?>"
                                                            role="progressbar"
                                                            style="width: <?= (int)$t['progress'] ?>%"
                                                            aria-valuenow="<?= (int)$t['progress'] ?>"
                                                            aria-valuemin="0"
                                                            aria-valuemax="100"
                                                        ></div>
                                                    </div>
                                                    <span class="small fw-semibold"><?= (int)$t['progress'] ?>%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="<?= statusBadge($t['status']) ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $t['status'])) ?>
                                                </span>
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
    </div>
</main>

<?php
require_once BCMS_ROOT . '/app/views/layout/footer.php';
