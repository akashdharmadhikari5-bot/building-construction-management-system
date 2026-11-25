<?php
/**
 * BCMS — Tasks & Milestones Management
 * Full task lifecycle for Admin; task progress & completion reporting for Worker.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireLogin();

$user     = currentUser();
$userRole = $user['role'] ?? ROLE_WORKER;
$isAdmin  = ($userRole === ROLE_ADMIN);
$isWorker = ($userRole === ROLE_WORKER);

$taskModel    = new TaskModel();
$projectModel = new ProjectModel();
$workerModel  = new WorkerModel();

// ── Handle POST Actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = strInput($_POST['action'] ?? '', 50);

    // 1. ADD TASK (Admin only)
    if ($action === 'add') {
        if (!$isAdmin) {
            setFlash('error', 'Only administrators can create tasks.');
            redirect('/tasks.php');
        }

        $projectId   = intInput($_POST['project_id'] ?? null, 1);
        $taskName    = strInput($_POST['task_name'] ?? '', 200);
        $workerId    = intInput($_POST['worker_id'] ?? null, 1);
        $dueDate     = dateInput($_POST['due_date'] ?? null);
        $status      = strInput($_POST['status'] ?? TaskModel::STATUS_PENDING, 50);
        $progress    = intInput($_POST['progress'] ?? 0, 0);
        $description = strInput($_POST['description'] ?? '', 2000);

        if (!$projectId) {
            setFlash('error', 'Please select a valid construction project.');
        } elseif ($taskName === '') {
            setFlash('error', 'Task title is required.');
        } else {
            try {
                $newId = $taskModel->create([
                    'project_id'  => $projectId,
                    'worker_id'   => $workerId ?: null,
                    'task_name'   => $taskName,
                    'description' => $description ?: null,
                    'status'      => $status,
                    'progress'    => min(100, max(0, $progress ?? 0)),
                    'due_date'    => $dueDate ?: null,
                ]);

                // Sync project progress from tasks
                $projectModel->calculateProgressFromTasks($projectId);

                logActivity('create', 'tasks', $newId, "Created task: {$taskName}");
                setFlash('success', "Task '{$taskName}' created successfully!");
            } catch (Throwable $e) {
                setFlash('error', 'Error creating task: ' . $e->getMessage());
            }
        }
        redirect('/tasks.php' . ($projectId ? "?project_id={$projectId}" : ''));
    }

    // 2. EDIT TASK (Admin only)
    if ($action === 'edit') {
        if (!$isAdmin) {
            setFlash('error', 'Only administrators can edit task definitions.');
            redirect('/tasks.php');
        }

        $taskId      = intInput($_POST['task_id'] ?? null, 1);
        $taskName    = strInput($_POST['task_name'] ?? '', 200);
        $workerId    = intInput($_POST['worker_id'] ?? null, 1);
        $dueDate     = dateInput($_POST['due_date'] ?? null);
        $status      = strInput($_POST['status'] ?? '', 50);
        $progress    = intInput($_POST['progress'] ?? null, 0);
        $description = strInput($_POST['description'] ?? '', 2000);

        if (!$taskId || $taskName === '') {
            setFlash('error', 'Task ID and title are required.');
        } else {
            try {
                $existing = $taskModel->getById($taskId);
                $taskModel->update($taskId, [
                    'task_name'   => $taskName,
                    'worker_id'   => $workerId ?: null,
                    'due_date'    => $dueDate ?: null,
                    'status'      => $status ?: null,
                    'progress'    => $progress !== null ? min(100, max(0, $progress)) : null,
                    'description' => $description ?: null,
                ]);

                if ($existing && !empty($existing['project_id'])) {
                    $projectModel->calculateProgressFromTasks((int)$existing['project_id']);
                }

                logActivity('update', 'tasks', $taskId, "Updated task: {$taskName}");
                setFlash('success', "Task '{$taskName}' updated successfully!");
            } catch (Throwable $e) {
                setFlash('error', 'Error updating task: ' . $e->getMessage());
            }
        }
        redirect('/tasks.php');
    }

    // 3. UPDATE TASK PROGRESS (Admin or Worker)
    if ($action === 'update_progress') {
        $taskId   = intInput($_POST['task_id'] ?? null, 1);
        $progress = intInput($_POST['progress'] ?? null, 0);

        if (!$taskId || $progress === null || $progress < 0 || $progress > 100) {
            setFlash('error', 'Invalid task ID or progress value (0-100).');
        } else {
            try {
                $existing = $taskModel->getById($taskId);
                if (!$existing) {
                    throw new InvalidArgumentException('Task not found.');
                }

                // If worker, ensure task is assigned to them (IDOR prevention)
                if ($isWorker && (int)($existing['worker_id'] ?? 0) !== (int)($user['linked_id'] ?? 0)) {
                    throw new InvalidArgumentException('You can only update tasks assigned to you.');
                }

                $taskModel->updateProgress($taskId, $progress);
                $projectModel->calculateProgressFromTasks((int)$existing['project_id']);

                logActivity('update', 'tasks', $taskId, "Progress updated to {$progress}%");
                setFlash('success', "Task progress updated to {$progress}%.");
            } catch (Throwable $e) {
                setFlash('error', $e->getMessage());
            }
        }
        redirect('/tasks.php');
    }

    // 4. ASSIGN WORKER (Admin only)
    if ($action === 'assign_worker') {
        if (!$isAdmin) {
            setFlash('error', 'Unauthorized.');
            redirect('/tasks.php');
        }

        $taskId   = intInput($_POST['task_id'] ?? null, 1);
        $workerId = intInput($_POST['worker_id'] ?? null, 1);

        if (!$taskId || !$workerId) {
            setFlash('error', 'Invalid task or worker ID.');
        } else {
            try {
                $taskModel->assignWorker($taskId, $workerId);
                logActivity('update', 'tasks', $taskId, "Assigned worker #{$workerId} to task");
                setFlash('success', 'Worker assigned to task successfully.');
            } catch (Throwable $e) {
                setFlash('error', $e->getMessage());
            }
        }
        redirect('/tasks.php');
    }
}

// ── GET Logic: Filters & Data Fetching ────────────────────────────────────────
$filters = [];
$searchQuery  = strInput($_GET['search'] ?? '', 100);
$statusFilter = strInput($_GET['status'] ?? '', 50);
$projectFilter= intInput($_GET['project_id'] ?? null, 1);
$workerFilter = intInput($_GET['worker_id'] ?? null, 1);

if ($searchQuery !== '') {
    $filters['search'] = $searchQuery;
}
if ($statusFilter !== '' && in_array($statusFilter, TaskModel::STATUSES, true)) {
    $filters['status'] = $statusFilter;
}
if ($projectFilter) {
    $filters['project_id'] = $projectFilter;
}

// Worker role enforcement: view only tasks assigned to current worker
if ($isWorker) {
    $filters['worker_id'] = (int)($user['linked_id'] ?? 0);
} elseif ($workerFilter) {
    $filters['worker_id'] = $workerFilter;
}

$tasks       = [];
$projects    = [];
$workersList = [];
$stats       = [
    'total'       => 0,
    'pending'     => 0,
    'in_progress' => 0,
    'completed'   => 0,
];

try {
    $tasks       = $taskModel->getAll($filters);
    $projects    = $projectModel->getAll();
    $workersList = $workerModel->getAll(['is_active' => 1]);

    foreach ($tasks as $t) {
        $stats['total']++;
        if ($t['status'] === 'pending')     $stats['pending']++;
        if ($t['status'] === 'in_progress') $stats['in_progress']++;
        if ($t['status'] === 'completed')   $stats['completed']++;
    }
} catch (Throwable $e) {
    setFlash('error', 'Error loading tasks: ' . $e->getMessage());
}

$pageTitle = $isWorker ? 'My Assigned Tasks' : 'Construction Tasks';
$activeNav = 'tasks';

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
      <p class="text-muted small mb-0">Create tasks, assign site laborers, and track completion milestones.</p>
    </div>
    <?php if ($isAdmin): ?>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
        <i class="bi bi-plus-circle me-1"></i>Create New Task
      </button>
    <?php endif; ?>
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

  <!-- KPI Stats Summary Cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary me-3">
            <i class="bi bi-list-task fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Total Tasks</div>
            <div class="fs-4 fw-bold"><?= number_format($stats['total']) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-warning bg-opacity-10 text-warning me-3">
            <i class="bi bi-hourglass-split fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Pending</div>
            <div class="fs-4 fw-bold text-warning"><?= number_format($stats['pending']) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-info bg-opacity-10 text-info me-3">
            <i class="bi bi-gear-wide-connected fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">In Progress</div>
            <div class="fs-4 fw-bold text-info"><?= number_format($stats['in_progress']) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success me-3">
            <i class="bi bi-check2-circle fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Completed</div>
            <div class="fs-4 fw-bold text-success"><?= number_format($stats['completed']) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Search & Filter Bar -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <form method="GET" action="<?= APP_URL ?>/tasks.php" class="row g-3 align-items-center">
        <div class="col-md-4">
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Search tasks, project, worker..." value="<?= e($searchQuery) ?>">
          </div>
        </div>
        <div class="col-md-3">
          <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <?php foreach (TaskModel::STATUSES as $st): ?>
              <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="project_id" class="form-select">
            <option value="">All Projects</option>
            <?php foreach ($projects as $pr): ?>
              <option value="<?= $pr['project_id'] ?>" <?= $projectFilter === (int)$pr['project_id'] ? 'selected' : '' ?>><?= e($pr['project_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-funnel me-1"></i>Filter
          </button>
          <a href="<?= APP_URL ?>/tasks.php" class="btn btn-outline-secondary" title="Reset Filters">
            <i class="bi bi-arrow-counterclockwise"></i>
          </a>
        </div>
      </form>
    </div>
  </div>

  <!-- Task List Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between">
      <h5 class="mb-0 fw-bold">Tasks List (<?= count($tasks) ?>)</h5>
    </div>
    <div class="card-body p-0">
      <?php if (empty($tasks)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-check2-all fs-2"></i>
          <h5 class="mt-3">No tasks found</h5>
          <p class="small">No construction tasks match your query.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-4">Task Name</th>
                <th>Project Site</th>
                <th>Assigned Worker</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Progress</th>
                <th class="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tasks as $t): ?>
                <?php
                  $progressVal = (int)$t['progress'];
                  $progClass = progressColor($progressVal);
                ?>
                <tr>
                  <td class="ps-4">
                    <div class="fw-bold text-dark"><?= e($t['task_name']) ?></div>
                    <?php if (!empty($t['description'])): ?>
                      <div class="small text-muted text-truncate" style="max-width: 280px;"><?= e($t['description']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a href="<?= APP_URL ?>/project_view.php?project_id=<?= (int)$t['project_id'] ?>" class="text-decoration-none fw-semibold">
                      <?= e($t['project_name']) ?>
                    </a>
                  </td>
                  <td>
                    <?php if (!empty($t['worker_name'])): ?>
                      <span class="badge bg-light text-dark border">
                        <i class="bi bi-person me-1"></i><?= e($t['worker_name']) ?>
                      </span>
                    <?php else: ?>
                      <span class="badge bg-secondary-subtle text-muted">Unassigned</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="small <?= (!empty($t['due_date']) && strtotime($t['due_date']) < time() && $t['status'] !== 'completed') ? 'text-danger fw-bold' : '' ?>">
                      <?= formatDate($t['due_date']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="<?= statusBadge($t['status']) ?> text-capitalize px-2 py-1">
                      <?= str_replace('_', ' ', e($t['status'])) ?>
                    </span>
                  </td>
                  <td style="min-width: 140px;">
                    <div class="d-flex justify-content-between small fw-bold mb-1">
                      <span><?= $progressVal ?>%</span>
                    </div>
                    <div class="progress" style="height: 6px;">
                      <div class="progress-bar <?= $progClass ?>" style="width: <?= $progressVal ?>%;"></div>
                    </div>
                  </td>
                  <td class="text-end pe-4 text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary update-prog-btn"
                      data-id="<?= (int)$t['task_id'] ?>"
                      data-name="<?= e($t['task_name']) ?>"
                      data-progress="<?= $progressVal ?>"
                      title="Update Completion Progress"
                    >
                      <i class="bi bi-sliders"></i> Progress
                    </button>
                    <?php if ($isAdmin): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary edit-task-btn"
                        data-id="<?= (int)$t['task_id'] ?>"
                        data-name="<?= e($t['task_name']) ?>"
                        data-project="<?= (int)$t['project_id'] ?>"
                        data-worker="<?= (int)($t['worker_id'] ?? 0) ?>"
                        data-due="<?= e($t['due_date'] ?? '') ?>"
                        data-status="<?= e($t['status']) ?>"
                        data-progress="<?= $progressVal ?>"
                        data-desc="<?= e($t['description'] ?? '') ?>"
                        title="Edit Task Details"
                      >
                        <i class="bi bi-pencil"></i>
                      </button>
                    <?php endif; ?>
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

<!-- Update Progress Modal (Admin & Worker) -->
<div class="modal fade" id="updateProgressModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= APP_URL ?>/tasks.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_progress">
        <input type="hidden" name="task_id" id="prog_task_id">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold"><i class="bi bi-sliders me-2"></i>Update Task Progress</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-semibold">Task</label>
            <div class="form-control-plaintext fw-bold" id="prog_task_name"></div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold d-flex justify-content-between">
              <span>Progress Percentage (0 - 100%)</span>
              <span id="rangeVal" class="text-primary fw-bold">50%</span>
            </label>
            <input type="range" class="form-range" min="0" max="100" step="5" name="progress" id="prog_input" oninput="document.getElementById('rangeVal').innerText = this.value + '%'">
          </div>
          <div class="alert alert-info small py-2 mb-0">
            <i class="bi bi-info-circle me-1"></i>Setting progress to <strong>100%</strong> will automatically mark the task as <strong>Completed</strong>.
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Save Progress</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($isAdmin): ?>
<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= APP_URL ?>/tasks.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Create New Task</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold">Task Title <span class="text-danger">*</span></label>
              <input type="text" name="task_name" class="form-control" placeholder="e.g. Ground Floor Column Reinforcement" required>
            </div>
            <div class="col-md-4">
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
              <label class="form-label fw-semibold">Assign Worker</label>
              <select name="worker_id" class="form-select">
                <option value="">Leave Unassigned</option>
                <?php foreach ($workersList as $w): ?>
                  <option value="<?= $w['worker_id'] ?>"><?= e($w['name']) ?> (<?= e($w['skill']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Due Target Date</label>
              <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Initial Status</label>
              <select name="status" class="form-select">
                <?php foreach (TaskModel::STATUSES as $st): ?>
                  <option value="<?= $st ?>" <?= $st === 'pending' ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-12">
              <label class="form-label fw-semibold">Description / Work Instructions</label>
              <textarea name="description" class="form-control" rows="3" placeholder="Provide architectural details, safety requirements, materials to use..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Create Task</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= APP_URL ?>/tasks.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="task_id" id="edit_task_id">
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Task</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold">Task Title <span class="text-danger">*</span></label>
              <input type="text" name="task_name" id="edit_task_name" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Assigned Worker</label>
              <select name="worker_id" id="edit_worker_id" class="form-select">
                <option value="">Unassigned</option>
                <?php foreach ($workersList as $w): ?>
                  <option value="<?= $w['worker_id'] ?>"><?= e($w['name']) ?> (<?= e($w['skill']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Due Date</label>
              <input type="date" name="due_date" id="edit_due_date" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Status</label>
              <select name="status" id="edit_status" class="form-select">
                <?php foreach (TaskModel::STATUSES as $st): ?>
                  <option value="<?= $st ?>"><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Progress (0-100%)</label>
              <input type="number" min="0" max="100" name="progress" id="edit_progress" class="form-control">
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
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const updateModal = new bootstrap.Modal(document.getElementById('updateProgressModal'));
  document.querySelectorAll('.update-prog-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.getElementById('prog_task_id').value = this.dataset.id;
      document.getElementById('prog_task_name').innerText = this.dataset.name;
      document.getElementById('prog_input').value = this.dataset.progress;
      document.getElementById('rangeVal').innerText = this.dataset.progress + '%';
      updateModal.show();
    });
  });

  <?php if ($isAdmin): ?>
  const editModal = new bootstrap.Modal(document.getElementById('editTaskModal'));
  document.querySelectorAll('.edit-task-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.getElementById('edit_task_id').value = this.dataset.id;
      document.getElementById('edit_task_name').value = this.dataset.name;
      document.getElementById('edit_worker_id').value = this.dataset.worker || '';
      document.getElementById('edit_due_date').value = this.dataset.due || '';
      document.getElementById('edit_status').value = this.dataset.status;
      document.getElementById('edit_progress').value = this.dataset.progress;
      document.getElementById('edit_description').value = this.dataset.desc || '';
      editModal.show();
    });
  });
  <?php endif; ?>
});
</script>

<?php
require_once VIEW_PATH . '/layout/footer.php';

