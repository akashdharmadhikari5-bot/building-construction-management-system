<?php
/**
 * BCMS — Worker Assignments Management
 * Admin-only page for deploying construction workers to specific project sites.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

// Authorization: Admin only
requireRole(ROLE_ADMIN);

$assignmentModel = new AssignmentModel();
$projectModel    = new ProjectModel();
$workerModel     = new WorkerModel();

// ── Handle POST Requests ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = strInput($_POST['action'] ?? '', 50);

    try {
        if ($action === 'assign') {
            $projectId    = intInput($_POST['project_id'] ?? null, 1);
            $workerId     = intInput($_POST['worker_id'] ?? null, 1);
            $assignedDate = dateInput($_POST['assigned_date'] ?? date('Y-m-d'));

            if (!$projectId) {
                throw new InvalidArgumentException('Please select a valid project.');
            }
            if (!$workerId) {
                throw new InvalidArgumentException('Please select a valid worker.');
            }
            if (!$assignedDate) {
                throw new InvalidArgumentException('A valid assigned date is required.');
            }

            // Check duplicate active assignment
            if ($assignmentModel->isAssigned($projectId, $workerId)) {
                throw new InvalidArgumentException('Worker is already actively assigned to this project.');
            }

            $assignmentId = $assignmentModel->assign($projectId, $workerId, $assignedDate);

            $worker  = $workerModel->getById($workerId);
            $project = $projectModel->getById($projectId);
            $workerName  = $worker['name'] ?? "Worker #{$workerId}";
            $projectName = $project['project_name'] ?? "Project #{$projectId}";

            logActivity('assign_worker', 'assignments', $assignmentId, "Assigned {$workerName} to {$projectName}");
            setFlash('success', "Worker '{$workerName}' assigned to project successfully.");
            redirect("/assignments.php?project_id={$projectId}");

        } elseif ($action === 'unassign') {
            $assignmentId = intInput($_POST['assignment_id'] ?? null, 1);
            $projectId    = intInput($_POST['project_id'] ?? null, 1);
            $endDate      = dateInput($_POST['end_date'] ?? date('Y-m-d'));

            if (!$assignmentId) {
                throw new InvalidArgumentException('Invalid assignment ID.');
            }
            if (!$endDate) {
                throw new InvalidArgumentException('A valid end date is required.');
            }

            $assignmentModel->unassign($assignmentId, $endDate);

            logActivity('unassign_worker', 'assignments', $assignmentId, "Ended assignment #{$assignmentId} on {$endDate}");
            setFlash('success', 'Worker has been unassigned from the project.');
            redirect($projectId ? "/assignments.php?project_id={$projectId}" : '/assignments.php');

        } else {
            throw new InvalidArgumentException('Unrecognized action.');
        }
    } catch (Throwable $e) {
        $refProject = intInput($_POST['project_id'] ?? null, 1);
        setFlash('error', $e->getMessage());
        redirect($refProject ? "/assignments.php?project_id={$refProject}" : '/assignments.php');
    }
}

// ── GET Data & Project Selection ───────────────────────────────────────────
$projects = $projectModel->getAll();

// Determine selected project
$selectedProjectId = intInput($_GET['project_id'] ?? null, 1);
if (!$selectedProjectId && !empty($projects)) {
    // Default to the first active or planning project, or simply the first one
    $selectedProjectId = (int)$projects[0]['project_id'];
}

$selectedProject   = $selectedProjectId ? $projectModel->getById($selectedProjectId) : null;
$assignments       = $selectedProjectId ? $assignmentModel->getByProject($selectedProjectId) : [];
$unassignedWorkers = $selectedProjectId ? $assignmentModel->getUnassignedWorkers($selectedProjectId) : [];

// Aggregate metrics for selected project site crew
$activeAssignedCount = 0;
$dailyLaborCost      = 0.0;
foreach ($assignments as $asg) {
    if ((bool)$asg['is_active']) {
        $activeAssignedCount++;
        $dailyLaborCost += (float)$asg['daily_wage'];
    }
}

$pageTitle = 'Worker Assignments';
require_once BCMS_ROOT . '/app/views/layout/header.php';
require_once BCMS_ROOT . '/app/views/layout/sidebar.php';
require_once BCMS_ROOT . '/app/views/layout/topnav.php';
?>

<main class="bcms-main">
    <div class="container-fluid">
        <!-- Breadcrumb & Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small">
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Assignments</li>
                    </ol>
                </nav>
                <h2 class="h4 mb-0 fw-bold text-dark">
                    <i class="bi bi-person-check me-2 text-primary"></i>Worker Assignments
                </h2>
                <p class="text-muted small mb-0">Deploy construction crew members to project sites and manage active assignments.</p>
            </div>
            <div>
                <?php if ($selectedProjectId && !empty($unassignedWorkers)): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignWorkerModal">
                        <i class="bi bi-person-plus-fill me-1"></i> Assign Worker to Site
                    </button>
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

        <!-- Project Selector Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="<?= APP_URL ?>/assignments.php" class="row g-3 align-items-end">
                    <div class="col-md-6 col-lg-5">
                        <label for="project_id" class="form-label small fw-bold text-dark mb-1">
                            <i class="bi bi-building me-1 text-primary"></i>Select Construction Project
                        </label>
                        <select class="form-select" id="project_id" name="project_id" onchange="this.form.submit()">
                            <?php if (empty($projects)): ?>
                                <option value="">No projects available</option>
                            <?php else: ?>
                                <?php foreach ($projects as $p): ?>
                                    <option value="<?= (int)$p['project_id'] ?>" <?= $selectedProjectId === (int)$p['project_id'] ? 'selected' : '' ?>>
                                        <?= e($p['project_name']) ?> — <?= e($p['location']) ?> (<?= ucfirst($p['status']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <?php if ($selectedProject): ?>
                        <div class="col-md-6 col-lg-7 d-flex flex-wrap align-items-center gap-3">
                            <div class="p-2 px-3 bg-light rounded border">
                                <span class="small text-muted d-block">Client:</span>
                                <span class="fw-semibold small text-dark"><?= e($selectedProject['client_name']) ?></span>
                            </div>
                            <div class="p-2 px-3 bg-light rounded border">
                                <span class="small text-muted d-block">Location:</span>
                                <span class="fw-semibold small text-dark"><?= e($selectedProject['location']) ?></span>
                            </div>
                            <div class="p-2 px-3 bg-light rounded border">
                                <span class="small text-muted d-block">Project Status:</span>
                                <span class="<?= statusBadge($selectedProject['status']) ?>"><?= ucfirst($selectedProject['status']) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($selectedProject): ?>
            <!-- Crew Summary Metric Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="card bcms-stat-card border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center">
                            <div class="bcms-stat-icon-wrapper stat-icon-primary me-3">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div>
                                <div class="bcms-stat-label">Active Site Crew</div>
                                <div class="bcms-stat-value text-primary"><?= $activeAssignedCount ?></div>
                                <div class="bcms-stat-subtext">Assigned & on-site</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card bcms-stat-card stat-success border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center">
                            <div class="bcms-stat-icon-wrapper stat-icon-success me-3">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                            <div>
                                <div class="bcms-stat-label">Daily Labor Cost</div>
                                <div class="bcms-stat-value text-success font-monospace" style="font-size: 1.45rem;">
                                    <?= formatCurrency($dailyLaborCost) ?>
                                </div>
                                <div class="bcms-stat-subtext">Sum of active daily wages</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card bcms-stat-card stat-accent border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center">
                            <div class="bcms-stat-icon-wrapper stat-icon-accent me-3">
                                <i class="bi bi-person-plus"></i>
                            </div>
                            <div>
                                <div class="bcms-stat-label">Available Workers</div>
                                <div class="bcms-stat-value text-warning"><?= count($unassignedWorkers) ?></div>
                                <div class="bcms-stat-subtext">Ready for deployment</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card bcms-stat-card border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center">
                            <div class="bcms-stat-icon-wrapper bg-light text-secondary me-3">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div>
                                <div class="bcms-stat-label">Total Assignments</div>
                                <div class="bcms-stat-value text-dark"><?= count($assignments) ?></div>
                                <div class="bcms-stat-subtext">Past & current crew</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Site Assignments Table -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <span class="card-header-title">
                        <i class="bi bi-person-lines-fill me-2 text-primary"></i>
                        Site Crew & Assignments for "<?= e($selectedProject['project_name']) ?>"
                        <span class="badge bg-secondary rounded-pill ms-2"><?= count($assignments) ?></span>
                    </span>
                    <?php if (!empty($unassignedWorkers)): ?>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#assignWorkerModal">
                            <i class="bi bi-person-plus-fill me-1"></i> Quick Assign
                        </button>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-3" style="width: 50px;">#</th>
                                <th>Worker Name</th>
                                <th>Phone</th>
                                <th>Skill / Trade</th>
                                <th class="text-end">Daily Wage (₹)</th>
                                <th>Assigned Date</th>
                                <th>End Date</th>
                                <th class="text-center">Status</th>
                                <th class="text-end pe-3" style="width: 140px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($assignments)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="bi bi-person-x fs-1 d-block text-muted opacity-50 mb-2"></i>
                                        No workers are currently assigned to this project site.
                                        <?php if (!empty($unassignedWorkers)): ?>
                                            <div class="mt-3">
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#assignWorkerModal">
                                                    <i class="bi bi-person-plus me-1"></i> Assign First Worker
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="mt-2 text-muted small">
                                                All active workers are assigned, or no active workers exist.
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($assignments as $idx => $asg): ?>
                                    <tr>
                                        <td class="ps-3 text-muted small"><?= $idx + 1 ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bcms-user-avatar me-2" style="width: 34px; height: 34px; font-size: 0.8rem;">
                                                    <?= e(mb_strtoupper(mb_substr($asg['worker_name'], 0, 1))) ?>
                                                </div>
                                                <div>
                                                    <a href="<?= APP_URL ?>/worker_view.php?worker_id=<?= (int)$asg['worker_id'] ?>" class="fw-semibold text-dark text-decoration-none hover-primary">
                                                        <?= e($asg['worker_name']) ?>
                                                    </a>
                                                    <div class="small text-muted">ID: #<?= (int)$asg['worker_id'] ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="small">
                                            <a href="tel:<?= e($asg['worker_phone']) ?>" class="text-decoration-none text-muted">
                                                <?= e($asg['worker_phone']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= e($asg['skill']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold font-monospace">
                                            <?= formatCurrency($asg['daily_wage']) ?>
                                        </td>
                                        <td class="small"><?= formatDate($asg['assigned_date']) ?></td>
                                        <td class="small">
                                            <?= $asg['end_date'] ? formatDate($asg['end_date']) : '<span class="text-success fw-semibold">Present</span>' ?>
                                        </td>
                                        <td class="text-center">
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
                                        <td class="text-end pe-3">
                                            <?php if ((bool)$asg['is_active']): ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-danger btn-unassign"
                                                    data-id="<?= (int)$asg['assignment_id'] ?>"
                                                    data-name="<?= e($asg['worker_name']) ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#unassignModal"
                                                    title="End Worker Assignment"
                                                >
                                                    <i class="bi bi-person-dash me-1"></i> Unassign
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">Finished</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <div class="alert alert-info border-0 shadow-sm p-4 text-center">
                <i class="bi bi-info-circle fs-2 d-block mb-2 text-primary"></i>
                <h5 class="fw-bold">No Construction Projects Available</h5>
                <p class="text-muted mb-0">Please create a construction project first before assigning workers.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Assign Worker Modal -->
<?php if ($selectedProject): ?>
<div class="modal fade" id="assignWorkerModal" tabindex="-1" aria-labelledby="assignWorkerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="<?= APP_URL ?>/assignments.php" novalidate id="assignWorkerForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="project_id" value="<?= $selectedProjectId ?>">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="assignWorkerModalLabel">
                        <i class="bi bi-person-plus-fill me-2"></i>Assign Worker to Site
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Target Project Site</label>
                        <input
                            type="text"
                            class="form-control bg-light"
                            value="<?= e($selectedProject['project_name']) ?> (<?= e($selectedProject['location']) ?>)"
                            readonly
                        >
                    </div>

                    <div class="mb-3">
                        <label for="modal_worker_id" class="form-label fw-semibold">
                            Select Worker <span class="text-danger">*</span>
                        </label>
                        <?php if (empty($unassignedWorkers)): ?>
                            <div class="alert alert-warning py-2 small mb-0">
                                All active workers are already assigned to this project site.
                            </div>
                        <?php else: ?>
                            <select class="form-select" id="modal_worker_id" name="worker_id" required>
                                <option value="">-- Choose Unassigned Worker --</option>
                                <?php foreach ($unassignedWorkers as $uw): ?>
                                    <option value="<?= (int)$uw['worker_id'] ?>">
                                        <?= e($uw['name']) ?> — <?= e($uw['skill']) ?> (₹<?= number_format((float)$uw['daily_wage'], 2) ?>/day)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Only active workers not currently deployed to this project are listed.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="modal_assigned_date" class="form-label fw-semibold">
                            Assignment Start Date <span class="text-danger">*</span>
                        </label>
                        <input
                            type="date"
                            class="form-control"
                            id="modal_assigned_date"
                            name="assigned_date"
                            value="<?= date('Y-m-d') ?>"
                            required
                        >
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" <?= empty($unassignedWorkers) ? 'disabled' : '' ?>>
                        <i class="bi bi-person-check-fill me-1"></i> Confirm Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Unassign Worker Modal -->
<div class="modal fade" id="unassignModal" tabindex="-1" aria-labelledby="unassignModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="<?= APP_URL ?>/assignments.php" novalidate id="unassignForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="unassign">
                <input type="hidden" name="project_id" value="<?= $selectedProjectId ?>">
                <input type="hidden" name="assignment_id" id="unassign_assignment_id" value="">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold" id="unassignModalLabel">
                        <i class="bi bi-person-dash-fill me-2"></i>End Worker Assignment
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <p class="mb-3">
                        Are you sure you want to end the project assignment for <strong id="unassign_worker_name">this worker</strong>?
                    </p>

                    <div class="mb-3">
                        <label for="unassign_end_date" class="form-label fw-semibold">
                            Assignment End Date <span class="text-danger">*</span>
                        </label>
                        <input
                            type="date"
                            class="form-control"
                            id="unassign_end_date"
                            name="end_date"
                            value="<?= date('Y-m-d') ?>"
                            required
                        >
                        <div class="form-text">Assignment status will be updated to Completed.</div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-check-lg me-1"></i> Confirm Unassign
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Populate Unassign Modal
    const unassignBtns = document.querySelectorAll('.btn-unassign');
    unassignBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('unassign_assignment_id').value = this.dataset.id || '';
            document.getElementById('unassign_worker_name').textContent = this.dataset.name || 'this worker';
        });
    });
});
</script>
<?php endif; ?>

<?php
require_once BCMS_ROOT . '/app/views/layout/footer.php';
