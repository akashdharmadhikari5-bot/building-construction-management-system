<?php
/**
 * BCMS — Projects Management
 * Full project lifecycle management for Admin, with client-filtered view for Client role.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireLogin();

$user = currentUser();
$userRole = $user['role'] ?? ROLE_WORKER;
$isClient = ($userRole === ROLE_CLIENT);
$isAdmin  = ($userRole === ROLE_ADMIN);

// Workers do not have access to full projects catalog
if (!$isAdmin && !$isClient) {
    setFlash('error', 'You do not have permission to access the projects page.');
    redirect('/dashboard.php');
}

$projectModel = new ProjectModel();
$clientModel  = new ClientModel();

// ── Handle POST Actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = strInput($_POST['action'] ?? '', 50);

    // 1. ADD / SUBMIT PROJECT (Admin can create for any client; Client can submit for themselves)
    if ($action === 'add' || $action === 'client_request') {
        $clientId = $isAdmin
            ? intInput($_POST['client_id'] ?? null, 1)
            : (int)($user['linked_id'] ?? 0);

        $projectName = strInput($_POST['project_name'] ?? '', 200);
        $location    = strInput($_POST['location'] ?? '', 300);
        $budget      = decimalInput($_POST['budget'] ?? null);
        $startDate   = dateInput($_POST['start_date'] ?? null);
        $endDate     = dateInput($_POST['end_date'] ?? null);
        $status      = $isAdmin ? strInput($_POST['status'] ?? ProjectModel::STATUS_PLANNING, 50) : ProjectModel::STATUS_PLANNING;
        $progress    = $isAdmin ? intInput($_POST['progress'] ?? 0, 0) : 0;
        $description = strInput($_POST['description'] ?? '', 2000);

        if (!$clientId || $clientId <= 0) {
            setFlash('error', 'Valid client profile is required.');
        } elseif ($projectName === '') {
            setFlash('error', 'Project name is required.');
        } elseif ($location === '') {
            setFlash('error', 'Project location is required.');
        } elseif ($budget === null || $budget <= 0.0) {
            setFlash('error', 'Project budget must be greater than zero.');
        } elseif (!$startDate || !$endDate) {
            setFlash('error', 'Valid start and end dates are required.');
        } elseif (strtotime($startDate) >= strtotime($endDate)) {
            setFlash('error', 'Start date must be earlier than the completion end date.');
        } else {
            try {
                $newId = $projectModel->create([
                    'client_id'    => $clientId,
                    'project_name' => $projectName,
                    'location'     => $location,
                    'budget'       => $budget,
                    'start_date'   => $startDate,
                    'end_date'     => $endDate,
                    'status'       => $status,
                    'progress'     => min(100, max(0, $progress ?? 0)),
                    'description'  => $description ?: null,
                ]);

                logActivity('create', 'projects', $newId, "Created project: {$projectName} by {$userRole}");
                $successMsg = $isClient 
                    ? "Your construction project details have been submitted successfully! It is now in Planning."
                    : "Project '{$projectName}' created successfully!";
                setFlash('success', $successMsg);
            } catch (Throwable $e) {
                setFlash('error', 'Error creating project: ' . $e->getMessage());
            }
        }
        redirect('/projects.php');
    }

    // 2. DELETE PROJECT (Admin can delete any; Client can delete their own project)
    if ($action === 'delete') {
        $projectId = intInput($_POST['project_id'] ?? null, 1);
        if (!$projectId) {
            setFlash('error', 'Invalid project ID.');
        } else {
            $project = $projectModel->getById($projectId);
            if (!$project) {
                setFlash('error', 'Project not found.');
            } elseif ($isClient && (int)$project['client_id'] !== (int)($user['linked_id'] ?? 0)) {
                setFlash('error', 'You can only remove your own projects.');
            } else {
                try {
                    $projectModel->delete($projectId);
                    logActivity('delete', 'projects', $projectId, "Deleted project: {$project['project_name']}");
                    setFlash('success', "Project '{$project['project_name']}' was removed successfully.");
                } catch (Throwable $e) {
                    setFlash('error', 'Error removing project: ' . $e->getMessage());
                }
            }
        }
        redirect('/projects.php');
    }

    // 3. ADMIN-ONLY ACTIONS (Edit, Progress, Status change)
    if (!$isAdmin) {
        setFlash('error', 'Only administrators can modify or delete projects.');
        redirect('/projects.php');
    }

    // 2. EDIT PROJECT
    if ($action === 'edit') {
        $projectId   = intInput($_POST['project_id'] ?? null, 1);
        $clientId    = intInput($_POST['client_id'] ?? null, 1);
        $projectName = strInput($_POST['project_name'] ?? '', 200);
        $location    = strInput($_POST['location'] ?? '', 300);
        $budget      = decimalInput($_POST['budget'] ?? null);
        $startDate   = dateInput($_POST['start_date'] ?? null);
        $endDate     = dateInput($_POST['end_date'] ?? null);
        $status      = strInput($_POST['status'] ?? ProjectModel::STATUS_PLANNING, 50);
        $progress    = intInput($_POST['progress'] ?? 0, 0);
        $description = strInput($_POST['description'] ?? '', 2000);

        if (!$projectId) {
            setFlash('error', 'Invalid project ID.');
        } elseif (!$clientId) {
            setFlash('error', 'Please select a valid client.');
        } elseif ($projectName === '') {
            setFlash('error', 'Project name is required.');
        } elseif ($location === '') {
            setFlash('error', 'Project location is required.');
        } elseif ($budget === null || $budget <= 0.0) {
            setFlash('error', 'Project budget must be greater than zero.');
        } elseif (!$startDate || !$endDate) {
            setFlash('error', 'Valid start and end dates are required.');
        } elseif (strtotime($startDate) >= strtotime($endDate)) {
            setFlash('error', 'Start date must be earlier than the completion end date.');
        } else {
            try {
                $projectModel->update($projectId, [
                    'client_id'    => $clientId,
                    'project_name' => $projectName,
                    'location'     => $location,
                    'budget'       => $budget,
                    'start_date'   => $startDate,
                    'end_date'     => $endDate,
                    'status'       => $status,
                    'progress'     => min(100, max(0, $progress ?? 0)),
                    'description'  => $description ?: null,
                ]);

                logActivity('update', 'projects', $projectId, "Updated project: {$projectName}");
                setFlash('success', "Project '{$projectName}' updated successfully!");
            } catch (Throwable $e) {
                setFlash('error', 'Error updating project: ' . $e->getMessage());
            }
        }
        redirect('/projects.php');
    }

    // 3. UPDATE PROGRESS
    if ($action === 'update_progress') {
        $projectId = intInput($_POST['project_id'] ?? null, 1);
        $progress  = intInput($_POST['progress'] ?? 0, 0);

        if (!$projectId || $progress === null || $progress < 0 || $progress > 100) {
            setFlash('error', 'Invalid project ID or progress value (0-100).');
        } else {
            try {
                $projectModel->updateProgress($projectId, $progress);
                logActivity('update', 'projects', $projectId, "Updated progress to {$progress}%");
                setFlash('success', "Project progress updated to {$progress}%.");
            } catch (Throwable $e) {
                setFlash('error', 'Error updating progress: ' . $e->getMessage());
            }
        }
        redirect('/projects.php');
    }

    // 4. UPDATE STATUS
    if ($action === 'update_status') {
        $projectId = intInput($_POST['project_id'] ?? null, 1);
        $status    = strInput($_POST['status'] ?? '', 50);

        if (!$projectId || !in_array($status, ProjectModel::STATUSES, true)) {
            setFlash('error', 'Invalid project ID or status value.');
        } else {
            try {
                $projectModel->updateStatus($projectId, $status);
                logActivity('update', 'projects', $projectId, "Changed status to {$status}");
                setFlash('success', "Project status changed to '" . ucfirst($status) . "'.");
            } catch (Throwable $e) {
                setFlash('error', 'Error updating status: ' . $e->getMessage());
            }
        }
        redirect('/projects.php');
    }
}

// ── GET Request: Filters & Listing ───────────────────────────────────────────
$filters = [];
$searchQuery  = strInput($_GET['search'] ?? '', 100);
$statusFilter = strInput($_GET['status'] ?? '', 50);
$clientFilter = intInput($_GET['client_id'] ?? null, 1);

if ($searchQuery !== '') {
    $filters['search'] = $searchQuery;
}
if ($statusFilter !== '' && in_array($statusFilter, ProjectModel::STATUSES, true)) {
    $filters['status'] = $statusFilter;
}

// Client role enforcement: restrict to client's own projects
if ($isClient) {
    $filters['client_id'] = (int)($user['linked_id'] ?? 0);
} elseif ($clientFilter) {
    $filters['client_id'] = $clientFilter;
}

$projects = [];
$clientsList = [];
$stats = [
    'total'     => 0,
    'active'    => 0,
    'planning'  => 0,
    'completed' => 0,
    'budget'    => 0.0,
];

try {
    $projects = $projectModel->getAll($filters);
    $clientsList = $isAdmin ? $clientModel->getAll() : [];

    foreach ($projects as $p) {
        $stats['total']++;
        if ($p['status'] === 'active')    $stats['active']++;
        if ($p['status'] === 'planning')  $stats['planning']++;
        if ($p['status'] === 'completed') $stats['completed']++;
        $stats['budget'] += (float)$p['budget'];
    }
} catch (Throwable $e) {
    setFlash('error', 'Database query error: ' . $e->getMessage());
}

$pageTitle = $isClient ? 'My Construction Projects' : 'Projects Management';
$activeNav = 'projects';

// Top navigation action button
$topNavAction = '';
if ($isAdmin) {
    $topNavAction = '<button type="button" class="btn btn-primary btn-sm px-3 shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#addProjectModal"><i class="bi bi-plus-circle me-1"></i>New Construction Project</button>';
} elseif ($isClient) {
    $topNavAction = '<button type="button" class="btn btn-primary btn-sm px-3 shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#clientProjectModal"><i class="bi bi-plus-circle me-1"></i>Submit Project Details</button>';
}

require_once VIEW_PATH . '/layout/header.php';
require_once VIEW_PATH . '/layout/sidebar.php';
require_once VIEW_PATH . '/layout/topnav.php';
?>

<main class="bcms-main bcms-content">
  <div class="container-fluid">
    <!-- Breadcrumbs & Header -->
    <div class="bcms-page-header d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
      <div>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
          </ol>
        </nav>
        <h2 class="h4 mb-0 fw-bold"><?= e($pageTitle) ?></h2>
        <p class="text-muted small mb-0">Track construction sites, budgets, milestones and live progress.</p>
      </div>
      <?php if ($isAdmin): ?>
        <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addProjectModal">
          <i class="bi bi-plus-circle me-1"></i>New Construction Project
        </button>
      <?php elseif ($isClient): ?>
        <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#clientProjectModal">
          <i class="bi bi-plus-circle me-1"></i>Submit Project Details
        </button>
      <?php endif; ?>
    </div>

    <!-- Flash Messages -->
    <?php if ($flashSuccess = getFlash('success')): ?>
      <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= e($flashSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if ($flashError = getFlash('error')): ?>
      <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($flashError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Summary Metric Cards (Active Sites, In Planning, Total Portfolio Budget) -->
    <div class="row g-3 mb-4">
      <!-- Active Sites -->
      <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm h-100 border-start border-success border-4">
          <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
            <div>
              <div class="text-uppercase tracking-wider text-muted fw-bold small mb-1">Active Sites</div>
              <div class="fs-2 fw-bold text-success mb-1"><?= number_format($stats['active']) ?></div>
              <div class="small text-muted"><i class="bi bi-play-circle-fill text-success me-1"></i>Currently ongoing execution</div>
            </div>
            <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success">
              <i class="bi bi-buildings fs-2"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- In Planning -->
      <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm h-100 border-start border-warning border-4">
          <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
            <div>
              <div class="text-uppercase tracking-wider text-muted fw-bold small mb-1">In Planning</div>
              <div class="fs-2 fw-bold text-warning mb-1"><?= number_format($stats['planning']) ?></div>
              <div class="small text-muted"><i class="bi bi-clock-history text-warning me-1"></i>Pre-construction & blueprint</div>
            </div>
            <div class="rounded-3 p-3 bg-warning bg-opacity-10 text-warning">
              <i class="bi bi-clipboard-data fs-2"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Total Portfolio Budget -->
      <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm h-100 border-start border-primary border-4">
          <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
            <div>
              <div class="text-uppercase tracking-wider text-muted fw-bold small mb-1"><?= $isClient ? 'My Project Budget' : 'Total Portfolio Budget' ?></div>
              <div class="fs-2 fw-bold text-primary mb-1"><?= formatCurrency($stats['budget']) ?></div>
              <div class="small text-muted"><i class="bi bi-wallet2 text-primary me-1"></i>Total capital allocated</div>
            </div>
            <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary">
              <i class="bi bi-cash-stack fs-2"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

  <!-- Filter & Search Bar -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <form method="GET" action="<?= APP_URL ?>/projects.php" class="row g-3 align-items-center">
        <div class="col-md-4">
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Search by name, site location..." value="<?= e($searchQuery) ?>">
          </div>
        </div>
        <div class="col-md-3">
          <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <?php foreach (ProjectModel::STATUSES as $st): ?>
              <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($isAdmin): ?>
          <div class="col-md-3">
            <select name="client_id" class="form-select">
              <option value="">All Clients</option>
              <?php foreach ($clientsList as $cl): ?>
                <option value="<?= $cl['client_id'] ?>" <?= $clientFilter === (int)$cl['client_id'] ? 'selected' : '' ?>><?= e($cl['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-funnel me-1"></i>Filter
          </button>
          <a href="<?= APP_URL ?>/projects.php" class="btn btn-outline-secondary" title="Reset Filters">
            <i class="bi bi-arrow-counterclockwise"></i>
          </a>
        </div>
      </form>
    </div>
  </div>

  <!-- Projects Grid / Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between">
      <h5 class="mb-0 fw-bold">Project List (<?= count($projects) ?>)</h5>
    </div>
    <div class="card-body p-0">
      <?php if (empty($projects)): ?>
        <div class="text-center py-5">
          <i class="bi bi-building-add text-primary" style="font-size: 3rem;"></i>
          <h5 class="mt-3"><?= $isClient ? 'No construction projects yet' : 'No projects found' ?></h5>
          <p class="text-muted small"><?= $isClient ? 'Submit your construction site and requirements online to get started.' : 'No construction projects match your selected filters.' ?></p>
          <?php if ($isAdmin): ?>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addProjectModal">
              <i class="bi bi-plus-circle me-1"></i>Create First Project
            </button>
          <?php elseif ($isClient): ?>
            <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#clientProjectModal">
              <i class="bi bi-plus-circle me-1"></i>Submit Project Details Online
            </button>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-4">Project / Location</th>
                <th>Client</th>
                <th>Budget</th>
                <th>Timeline</th>
                <th>Status</th>
                <th>Progress</th>
                <th class="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($projects as $p): ?>
                <?php
                  $progressVal = (int)$p['progress'];
                  $progClass = progressColor($progressVal);
                ?>
                <tr>
                  <td class="ps-4">
                    <div class="fw-bold text-dark">
                      <a href="<?= APP_URL ?>/project_view.php?project_id=<?= (int)$p['project_id'] ?>" class="text-decoration-none text-dark hover-primary">
                        <?= e($p['project_name']) ?>
                      </a>
                    </div>
                    <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($p['location']) ?></div>
                  </td>
                  <td>
                    <div class="fw-semibold"><?= e($p['client_name'] ?? '—') ?></div>
                    <div class="small text-muted"><?= e($p['client_phone'] ?? '') ?></div>
                  </td>
                  <td>
                    <div class="fw-bold text-dark"><?= formatCurrency($p['budget']) ?></div>
                  </td>
                  <td>
                    <div class="small text-nowrap"><strong>Start:</strong> <?= formatDate($p['start_date']) ?></div>
                    <div class="small text-nowrap text-muted"><strong>Target:</strong> <?= formatDate($p['end_date']) ?></div>
                  </td>
                  <td>
                    <span class="<?= statusBadge($p['status']) ?> text-capitalize px-2 py-1">
                      <?= str_replace('_', ' ', e($p['status'])) ?>
                    </span>
                  </td>
                  <td style="min-width: 140px;">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                      <span class="small fw-bold"><?= $progressVal ?>%</span>
                    </div>
                    <div class="progress" style="height: 6px;">
                      <div class="progress-bar <?= $progClass ?>" role="progressbar" style="width: <?= $progressVal ?>%;" aria-valuenow="<?= $progressVal ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                  </td>
                  <td class="text-end pe-4 text-nowrap">
                    <a href="<?= APP_URL ?>/project_view.php?project_id=<?= (int)$p['project_id'] ?>" class="btn btn-sm btn-outline-primary" title="View 360 Details">
                      <i class="bi bi-eye"></i> Details
                    </a>
                    <?php if ($isAdmin): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary edit-proj-btn"
                        data-id="<?= (int)$p['project_id'] ?>"
                        data-name="<?= e($p['project_name']) ?>"
                        data-client="<?= (int)$p['client_id'] ?>"
                        data-location="<?= e($p['location']) ?>"
                        data-budget="<?= (float)$p['budget'] ?>"
                        data-start="<?= e($p['start_date']) ?>"
                        data-end="<?= e($p['end_date']) ?>"
                        data-status="<?= e($p['status']) ?>"
                        data-progress="<?= (int)$p['progress'] ?>"
                        data-desc="<?= e($p['description'] ?? '') ?>"
                        title="Edit Project Details"
                      >
                        <i class="bi bi-pencil"></i>
                      </button>
                    <?php endif; ?>
                    <?php if ($isAdmin || $isClient): ?>
                      <form method="POST" action="<?= APP_URL ?>/projects.php" class="d-inline" onsubmit="return confirm('Are you sure you want to remove this project?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="project_id" value="<?= (int)$p['project_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove Project">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
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
</main>

<?php if ($isAdmin): ?>
<!-- Add Project Modal -->
<div class="modal fade" id="addProjectModal" tabindex="-1" aria-labelledby="addProjectModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= APP_URL ?>/projects.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold" id="addProjectModalLabel"><i class="bi bi-plus-circle me-2"></i>Create New Project</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold">Project Name <span class="text-danger">*</span></label>
              <input type="text" name="project_name" class="form-control" placeholder="e.g. Skyline Towers Block A" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Client <span class="text-danger">*</span></label>
              <select name="client_id" class="form-select" required>
                <option value="">Select Client...</option>
                <?php foreach ($clientsList as $cl): ?>
                  <option value="<?= $cl['client_id'] ?>"><?= e($cl['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label fw-semibold">Construction Site Location <span class="text-danger">*</span></label>
              <input type="text" name="location" class="form-control" placeholder="e.g. Plot 45, Baner Road, Pune" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Project Budget (<?= CURRENCY_SYMBOL ?>) <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="1" name="budget" class="form-control" placeholder="0.00" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Commencement Date <span class="text-danger">*</span></label>
              <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Target Completion Date <span class="text-danger">*</span></label>
              <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d', strtotime('+6 months')) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Initial Status</label>
              <select name="status" class="form-select">
                <option value="planning" selected>Planning</option>
                <option value="active">Active</option>
                <option value="on_hold">On Hold</option>
              </select>
            </div>
            <div class="col-md-12">
              <label class="form-label fw-semibold">Project Scope & Description</label>
              <textarea name="description" class="form-control" rows="3" placeholder="Enter key project specifications, structural details, architectural notes..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Create Project</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Project Modal -->
<div class="modal fade" id="editProjectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= APP_URL ?>/projects.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="project_id" id="edit_project_id">
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Project</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold">Project Name <span class="text-danger">*</span></label>
              <input type="text" name="project_name" id="edit_project_name" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Client <span class="text-danger">*</span></label>
              <select name="client_id" id="edit_client_id" class="form-select" required>
                <?php foreach ($clientsList as $cl): ?>
                  <option value="<?= $cl['client_id'] ?>"><?= e($cl['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label fw-semibold">Site Location <span class="text-danger">*</span></label>
              <input type="text" name="location" id="edit_location" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Budget (<?= CURRENCY_SYMBOL ?>) <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="1" name="budget" id="edit_budget" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Commencement Date <span class="text-danger">*</span></label>
              <input type="date" name="start_date" id="edit_start_date" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Target Completion Date <span class="text-danger">*</span></label>
              <input type="date" name="end_date" id="edit_end_date" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Current Status</label>
              <select name="status" id="edit_status" class="form-select">
                <?php foreach (ProjectModel::STATUSES as $st): ?>
                  <option value="<?= $st ?>"><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Overall Progress (0-100%)</label>
              <input type="number" min="0" max="100" name="progress" id="edit_progress" class="form-control">
            </div>
            <div class="col-md-8">
              <label class="form-label fw-semibold">Project Scope & Notes</label>
              <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
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
  const editModal = new bootstrap.Modal(document.getElementById('editProjectModal'));
  document.querySelectorAll('.edit-proj-btn').forEach(button => {
    button.addEventListener('click', function() {
      document.getElementById('edit_project_id').value = this.dataset.id;
      document.getElementById('edit_project_name').value = this.dataset.name;
      document.getElementById('edit_client_id').value = this.dataset.client;
      document.getElementById('edit_location').value = this.dataset.location;
      document.getElementById('edit_budget').value = this.dataset.budget;
      document.getElementById('edit_start_date').value = this.dataset.start;
      document.getElementById('edit_end_date').value = this.dataset.end;
      document.getElementById('edit_status').value = this.dataset.status;
      document.getElementById('edit_progress').value = this.dataset.progress;
      document.getElementById('edit_description').value = this.dataset.desc;
      editModal.show();
    });
  });
});
</script>
<?php endif; // end of $isAdmin modals ?>

<?php if ($isClient): ?>
<!-- Client Project Submission Modal -->
<div class="modal fade" id="clientProjectModal" tabindex="-1" aria-labelledby="clientProjectModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= APP_URL ?>/projects.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="client_request">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold" id="clientProjectModalLabel">
            <i class="bi bi-building-add me-2"></i>Submit Construction Project Details
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="alert alert-info py-2 small mb-3">
            <i class="bi bi-info-circle me-1"></i> Fill in your construction site details and requirements online. Your project will be placed in the <strong>Planning</strong> phase for review and execution.
          </div>
          <div class="row g-3">
            <div class="col-md-12">
              <label class="form-label fw-semibold">Project Name <span class="text-danger">*</span></label>
              <input type="text" name="project_name" class="form-control" placeholder="e.g. My 3BHK Residence, Royal Villa, Baner Complex" required>
            </div>
            <div class="col-md-12">
              <label class="form-label fw-semibold">Construction Site Location / Plot Address <span class="text-danger">*</span></label>
              <input type="text" name="location" class="form-control" placeholder="e.g. Plot 42, Green Valley, Baner Road, Pune - 411045" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Estimated Budget (<?= CURRENCY_SYMBOL ?>) <span class="text-danger">*</span></label>
              <input type="number" step="1000" min="1000" name="budget" class="form-control" placeholder="e.g. 2500000" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Desired Start Date <span class="text-danger">*</span></label>
              <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Target Completion Date <span class="text-danger">*</span></label>
              <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d', strtotime('+6 months')) ?>" required>
            </div>
            <div class="col-md-12">
              <label class="form-label fw-semibold">Construction Requirements & Scope Notes</label>
              <textarea name="description" class="form-control" rows="3" placeholder="Describe requirements e.g. G+2 residential structure with parking, modular kitchen, terrace garden..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-send-check me-1"></i>Submit Project Details</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const clientModalEl = document.getElementById('clientProjectModal');
  if (clientModalEl) {
    const clientModal = new bootstrap.Modal(clientModalEl);
    document.querySelectorAll('[data-bs-target="#clientProjectModal"]').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        clientModal.show();
      });
    });
  }
});
</script>
<?php endif; // end of $isClient modal ?>

<?php
require_once VIEW_PATH . '/layout/footer.php';

