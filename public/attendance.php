<?php
/**
 * BCMS — Daily Attendance Management
 * Daily worker attendance sheet, bulk marking, duplicate prevention, and worker self-view.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireLogin();

$user     = currentUser();
$userRole = $user['role'] ?? ROLE_WORKER;
$isAdmin  = ($userRole === ROLE_ADMIN);
$isWorker = ($userRole === ROLE_WORKER);

$attendanceModel = new AttendanceModel();
$projectModel    = new ProjectModel();
$workerModel     = new WorkerModel();

// ── Handle POST Actions (Admin only) ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        setFlash('error', 'Only administrators can record worker attendance.');
        redirect('/attendance.php');
    }

    validateCsrf();
    $action = strInput($_POST['action'] ?? '', 50);

    // 1. SAVE DAILY SHEET (Bulk or Individual)
    if ($action === 'save_daily_sheet') {
        $projectId = intInput($_POST['project_id'] ?? null, 1);
        $date      = dateInput($_POST['attendance_date'] ?? null);
        $records   = $_POST['attendance'] ?? []; // array of [worker_id => ['status' => ..., 'remarks' => ...]]

        if (!$projectId) {
            setFlash('error', 'Please select a valid construction project.');
        } elseif (!$date) {
            setFlash('error', 'A valid attendance date is required.');
        } elseif (empty($records) || !is_array($records)) {
            setFlash('error', 'No worker records provided to mark attendance.');
        } else {
            $savedCount = 0;
            $errors = [];

            foreach ($records as $wid => $data) {
                $workerId = intInput($wid, 1);
                $status   = strInput($data['status'] ?? '', 50);
                $remarks  = strInput($data['remarks'] ?? '', 255);

                if (!$workerId || !in_array($status, AttendanceModel::STATUSES, true)) {
                    continue; // Skip unmarked or invalid lines
                }

                try {
                    $attendanceModel->recordOrUpdate($workerId, $projectId, $date, $status, $remarks ?: null);
                    $savedCount++;
                } catch (Throwable $e) {
                    $errors[] = "Worker #{$workerId}: " . $e->getMessage();
                }
            }

            if ($savedCount > 0) {
                logActivity('create', 'attendance', $projectId, "Marked attendance for {$savedCount} workers on {$date}");
                setFlash('success', "Successfully recorded attendance for {$savedCount} worker(s) on {$date}!");
            }
            if (!empty($errors)) {
                setFlash('error', 'Some records could not be updated: ' . implode(', ', array_slice($errors, 0, 3)));
            }
        }
        redirect("/attendance.php?project_id={$projectId}&date={$date}");
    }

    // 2. SINGLE RECORD CORRECTION / UPDATE
    if ($action === 'update_single') {
        $attendanceId = intInput($_POST['attendance_id'] ?? null, 1);
        $status       = strInput($_POST['status'] ?? '', 50);
        $remarks      = strInput($_POST['remarks'] ?? '', 255);
        $projectId    = intInput($_POST['project_id'] ?? null, 1);
        $date         = dateInput($_POST['attendance_date'] ?? date('Y-m-d'));

        if (!$attendanceId || !in_array($status, AttendanceModel::STATUSES, true)) {
            setFlash('error', 'Invalid attendance record or status.');
        } else {
            try {
                $attendanceModel->updateAttendance($attendanceId, $status, $remarks ?: null);
                logActivity('update', 'attendance', $attendanceId, "Corrected attendance #{$attendanceId} to {$status}");
                setFlash('success', 'Attendance record updated successfully.');
            } catch (Throwable $e) {
                setFlash('error', $e->getMessage());
            }
        }
        redirect("/attendance.php?project_id={$projectId}&date={$date}");
    }
}

// ── GET Logic & View Preparation ──────────────────────────────────────────────
$projects = [];
$selectedProjectId = intInput($_GET['project_id'] ?? null, 1);
$selectedDate      = dateInput($_GET['date'] ?? date('Y-m-d')) ?? date('Y-m-d');
$dailySheet        = [];
$recentHistory     = [];
$workerHistory     = [];
$workerWageSummary = null;

try {
    if ($isAdmin) {
        $projects = $projectModel->getAll(['status' => 'active']);
        // If no project selected yet, default to first active project
        if (!$selectedProjectId && !empty($projects)) {
            $selectedProjectId = (int)$projects[0]['project_id'];
        }

        if ($selectedProjectId) {
            $dailySheet = $attendanceModel->getDailySheet($selectedProjectId, $selectedDate);
            $recentHistory = $attendanceModel->getByProject($selectedProjectId, ['limit' => 30]);
        }
    } elseif ($isWorker) {
        $myWorkerId = (int)($user['linked_id'] ?? 0);
        if ($myWorkerId > 0) {
            $workerHistory = dbQuery(
                'SELECT a.*, p.project_name
                 FROM attendance a
                 JOIN projects p ON p.project_id = a.project_id
                 WHERE a.worker_id = ?
                 ORDER BY a.attendance_date DESC
                 LIMIT 50',
                [$myWorkerId]
            );

            // Compute summary
            $summaryRow = dbQueryOne(
                'SELECT
                    COUNT(CASE WHEN status = "present" THEN 1 END) AS present_count,
                    COUNT(CASE WHEN status = "half_day" THEN 1 END) AS half_day_count,
                    COUNT(CASE WHEN status = "absent" THEN 1 END) AS absent_count,
                    (COUNT(CASE WHEN status = "present" THEN 1 END) + COUNT(CASE WHEN status = "half_day" THEN 1 END) * 0.5) AS working_days
                 FROM attendance
                 WHERE worker_id = ?',
                [$myWorkerId]
            );
            $workerWageSummary = $summaryRow;
        }
    }
} catch (Throwable $e) {
    setFlash('error', 'Error loading attendance data: ' . $e->getMessage());
}

$pageTitle = $isWorker ? 'My Attendance Record' : 'Attendance Sheet';
$activeNav = 'attendance';

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
      <p class="text-muted small mb-0">Record daily labor presence, half-days, and compute accurate working days.</p>
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

  <?php if ($isWorker): ?>
    <!-- WORKER VIEW: PERSONAL ATTENDANCE LOG -->
    <div class="row g-3 mb-4">
      <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted small">Total Present Days</div>
            <div class="fs-4 fw-bold text-success mt-1"><?= (int)($workerWageSummary['present_count'] ?? 0) ?></div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted small">Half-Days Logged</div>
            <div class="fs-4 fw-bold text-secondary mt-1"><?= (int)($workerWageSummary['half_day_count'] ?? 0) ?></div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted small">Absences Recorded</div>
            <div class="fs-4 fw-bold text-danger mt-1"><?= (int)($workerWageSummary['absent_count'] ?? 0) ?></div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted small">Computed Working Days</div>
            <div class="fs-4 fw-bold text-primary mt-1"><?= number_format((float)($workerWageSummary['working_days'] ?? 0.0), 1) ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white py-3 border-0">
        <h5 class="mb-0 fw-bold">My Attendance History</h5>
      </div>
      <div class="card-body p-0">
        <?php if (empty($workerHistory)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-calendar-x fs-2"></i>
            <p class="mt-2 mb-0">No attendance entries recorded for your profile yet.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-4">Date</th>
                  <th>Project Site</th>
                  <th>Status</th>
                  <th>Remarks</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($workerHistory as $wh): ?>
                  <tr>
                    <td class="ps-4 fw-semibold"><?= formatDate($wh['attendance_date']) ?></td>
                    <td><?= e($wh['project_name']) ?></td>
                    <td>
                      <span class="<?= statusBadge($wh['status']) ?> text-capitalize px-2 py-1">
                        <?= str_replace('_', ' ', e($wh['status'])) ?>
                      </span>
                    </td>
                    <td class="text-muted small"><?= e($wh['remarks'] ?? '—') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

  <?php elseif ($isAdmin): ?>
    <!-- ADMIN VIEW: DAILY ATTENDANCE SHEET -->
    <!-- Project & Date Selector -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <form method="GET" action="<?= APP_URL ?>/attendance.php" class="row g-3 align-items-end">
          <div class="col-md-5">
            <label class="form-label fw-semibold small">Construction Project Site <span class="text-danger">*</span></label>
            <select name="project_id" class="form-select" onchange="this.form.submit()" required>
              <option value="">Select Project...</option>
              <?php foreach ($projects as $pr): ?>
                <option value="<?= $pr['project_id'] ?>" <?= $selectedProjectId === (int)$pr['project_id'] ? 'selected' : '' ?>>
                  <?= e($pr['project_name']) ?> (<?= e($pr['location']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small">Attendance Date <span class="text-danger">*</span></label>
            <input type="date" name="date" class="form-control" value="<?= e($selectedDate) ?>" onchange="this.form.submit()" required>
          </div>
          <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-arrow-clockwise me-1"></i>Load Sheet
            </button>
            <a href="<?= APP_URL ?>/attendance.php?date=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary" title="Reset to Today">
              Today
            </a>
          </div>
        </form>
      </div>
    </div>

    <?php if ($selectedProjectId): ?>
      <!-- Daily Sheet Table Form -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div>
            <h5 class="mb-0 fw-bold">Daily Labor Sheet &bull; <?= formatDate($selectedDate) ?></h5>
            <span class="text-muted small"><?= count($dailySheet) ?> worker(s) actively deployed to this site</span>
          </div>
          <?php if (!empty($dailySheet)): ?>
            <div class="btn-group btn-group-sm">
              <button type="button" class="btn btn-outline-success" id="markAllPresentBtn">
                <i class="bi bi-check-all me-1"></i>All Present
              </button>
              <button type="button" class="btn btn-outline-secondary" id="markAllAbsentBtn">
                <i class="bi bi-x me-1"></i>All Absent
              </button>
            </div>
          <?php endif; ?>
        </div>

        <div class="card-body p-0">
          <?php if (empty($dailySheet)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-people-fill fs-2"></i>
              <h6 class="mt-2">No Active Worker Deployments</h6>
              <p class="small mb-3">No workers are currently assigned to this project on <?= formatDate($selectedDate) ?>.</p>
              <a href="<?= APP_URL ?>/assignments.php?project_id=<?= $selectedProjectId ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-person-plus me-1"></i>Deploy Workers to Site
              </a>
            </div>
          <?php else: ?>
            <form method="POST" action="<?= APP_URL ?>/attendance.php">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="save_daily_sheet">
              <input type="hidden" name="project_id" value="<?= $selectedProjectId ?>">
              <input type="hidden" name="attendance_date" value="<?= e($selectedDate) ?>">

              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-4">Worker Name / Trade</th>
                      <th>Wage / Day</th>
                      <th style="min-width: 320px;">Attendance Status</th>
                      <th style="min-width: 220px;">Notes / Shift Remarks</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($dailySheet as $row): ?>
                      <?php
                        $wid = (int)$row['worker_id'];
                        $curStatus = $row['attendance_status'] ?? 'present';
                      ?>
                      <tr>
                        <td class="ps-4">
                          <div class="fw-bold"><?= e($row['worker_name']) ?></div>
                          <div class="small text-muted"><span class="badge bg-light text-dark border me-1"><?= e($row['skill']) ?></span> <?= e($row['worker_phone']) ?></div>
                        </td>
                        <td>
                          <div class="fw-semibold text-dark"><?= formatCurrency($row['daily_wage']) ?></div>
                        </td>
                        <td>
                          <div class="btn-group btn-group-sm w-100" role="group">
                            <input type="radio" class="btn-check status-radio status-present" name="attendance[<?= $wid ?>][status]" id="st_<?= $wid ?>_present" value="present" <?= $curStatus === 'present' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-success" for="st_<?= $wid ?>_present">Present</label>

                            <input type="radio" class="btn-check status-radio status-half" name="attendance[<?= $wid ?>][status]" id="st_<?= $wid ?>_half" value="half_day" <?= $curStatus === 'half_day' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-secondary" for="st_<?= $wid ?>_half">Half-Day</label>

                            <input type="radio" class="btn-check status-radio status-absent" name="attendance[<?= $wid ?>][status]" id="st_<?= $wid ?>_absent" value="absent" <?= $curStatus === 'absent' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-danger" for="st_<?= $wid ?>_absent">Absent</label>

                            <input type="radio" class="btn-check status-radio status-holiday" name="attendance[<?= $wid ?>][status]" id="st_<?= $wid ?>_holiday" value="holiday" <?= $curStatus === 'holiday' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-warning text-dark" for="st_<?= $wid ?>_holiday">Holiday</label>
                          </div>
                        </td>
                        <td class="pe-4">
                          <input type="text" name="attendance[<?= $wid ?>][remarks]" class="form-control form-control-sm" placeholder="Optional remark (e.g. late 1hr)..." value="<?= e($row['remarks'] ?? '') ?>">
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="card-footer bg-light py-3 d-flex justify-content-between align-items-center">
                <span class="text-muted small">Attendance changes overwrite prior records for this project and date.</span>
                <button type="submit" class="btn btn-success px-4">
                  <i class="bi bi-save me-1"></i>Save Daily Attendance
                </button>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const markAllPresentBtn = document.getElementById('markAllPresentBtn');
  if (markAllPresentBtn) {
    markAllPresentBtn.addEventListener('click', function() {
      document.querySelectorAll('.status-present').forEach(radio => radio.checked = true);
    });
  }

  const markAllAbsentBtn = document.getElementById('markAllAbsentBtn');
  if (markAllAbsentBtn) {
    markAllAbsentBtn.addEventListener('click', function() {
      document.querySelectorAll('.status-absent').forEach(radio => radio.checked = true);
    });
  }
});
</script>

<?php
require_once VIEW_PATH . '/layout/footer.php';

