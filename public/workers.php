<?php
/**
 * BCMS — Worker Management
 * Admin-only page for managing construction workers, skills, daily wages, and assignments.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

// Authorization: Only admin can manage workers
requireRole(ROLE_ADMIN);

$workerModel = new WorkerModel();

// ── Handle POST Requests ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = strInput($_POST['action'] ?? '', 50);

    try {
        if ($action === 'add_worker') {
            $name      = strInput($_POST['name'] ?? '', 150);
            $phone     = strInput($_POST['phone'] ?? '', 20);
            $skill     = strInput($_POST['skill'] ?? '', 100);
            $dailyWage = decimalInput($_POST['daily_wage'] ?? null);
            $isActive  = isset($_POST['is_active']) ? 1 : 0;

            if ($name === '') {
                throw new InvalidArgumentException('Worker name is required.');
            }
            if ($phone === '') {
                throw new InvalidArgumentException('Phone number is required.');
            }
            if ($skill === '') {
                throw new InvalidArgumentException('Skill/Trade is required.');
            }
            if ($dailyWage === null || $dailyWage < 0) {
                throw new InvalidArgumentException('Please enter a valid daily wage rate.');
            }

            $newId = $workerModel->create([
                'name'       => $name,
                'phone'      => $phone,
                'skill'      => $skill,
                'daily_wage' => $dailyWage,
                'is_active'  => $isActive,
            ]);

            logActivity('create_worker', 'workers', $newId, "Added worker: {$name} ({$skill}) at ₹{$dailyWage}/day");
            setFlash('success', "Worker '{$name}' created successfully.");
            redirect('/workers.php');

        } elseif ($action === 'edit_worker') {
            $workerId  = intInput($_POST['worker_id'] ?? null, 1);
            $name      = strInput($_POST['name'] ?? '', 150);
            $phone     = strInput($_POST['phone'] ?? '', 20);
            $skill     = strInput($_POST['skill'] ?? '', 100);
            $dailyWage = decimalInput($_POST['daily_wage'] ?? null);
            $isActive  = isset($_POST['is_active']) ? 1 : 0;

            if (!$workerId) {
                throw new InvalidArgumentException('Invalid worker record ID.');
            }
            if ($name === '') {
                throw new InvalidArgumentException('Worker name is required.');
            }
            if ($phone === '') {
                throw new InvalidArgumentException('Phone number is required.');
            }
            if ($skill === '') {
                throw new InvalidArgumentException('Skill/Trade is required.');
            }
            if ($dailyWage === null || $dailyWage < 0) {
                throw new InvalidArgumentException('Please enter a valid daily wage rate.');
            }

            $workerModel->update($workerId, [
                'name'       => $name,
                'phone'      => $phone,
                'skill'      => $skill,
                'daily_wage' => $dailyWage,
                'is_active'  => $isActive,
            ]);

            logActivity('update_worker', 'workers', $workerId, "Updated worker #{$workerId} details: {$name}");
            setFlash('success', "Worker '{$name}' updated successfully.");
            redirect('/workers.php');

        } elseif ($action === 'toggle_status') {
            $workerId = intInput($_POST['worker_id'] ?? null, 1);
            if (!$workerId) {
                throw new InvalidArgumentException('Invalid worker ID.');
            }

            $worker = $workerModel->getById($workerId);
            if (!$worker) {
                throw new InvalidArgumentException('Worker not found.');
            }

            $workerModel->toggleStatus($workerId);
            $newStatus = (int)!$worker['is_active'] ? 'activated' : 'deactivated';
            logActivity('toggle_status', 'workers', $workerId, "Worker '{$worker['name']}' was {$newStatus}");
            setFlash('success', "Worker '{$worker['name']}' has been {$newStatus}.");
            redirect('/workers.php');

        } elseif ($action === 'seed_all_trades') {
            $trades = [
                ['name' => 'Praveen Sharma',  'phone' => '9110011001', 'skill' => 'Site Supervisor',            'daily_wage' => 950.00],
                ['name' => 'Karan Singh',     'phone' => '9110011002', 'skill' => 'Excavator / JCB Operator',   'daily_wage' => 850.00],
                ['name' => 'Santosh Yadav',   'phone' => '9110011003', 'skill' => 'Foundation Laborer',         'daily_wage' => 450.00],
                ['name' => 'Mohan Tiwari',    'phone' => '9011223344', 'skill' => 'Head Mason (Rajmistri)',     'daily_wage' => 750.00],
                ['name' => 'Keshav Nair',     'phone' => '9088990011', 'skill' => 'Brickwork Mason',            'daily_wage' => 700.00],
                ['name' => 'Dilip Jadhav',    'phone' => '9077889900', 'skill' => 'Steel Fixer (Bar Bender)',   'daily_wage' => 750.00],
                ['name' => 'Babu Lal Gurjar', 'phone' => '9110011004', 'skill' => 'Shuttering Carpenter',       'daily_wage' => 720.00],
                ['name' => 'Dinesh Chauhan',  'phone' => '9110011005', 'skill' => 'Concrete Vibrator Operator', 'daily_wage' => 650.00],
                ['name' => 'Ganesh Patil',    'phone' => '9055667788', 'skill' => 'General Helper / Laborer',   'daily_wage' => 450.00],
                ['name' => 'Vijay Solanki',   'phone' => '9110011006', 'skill' => 'Curing Worker (Tari waala)', 'daily_wage' => 400.00],
                ['name' => 'Ajay Yadav',      'phone' => '9033445566', 'skill' => 'Plumber (Sanitary Fitter)',  'daily_wage' => 750.00],
                ['name' => 'Rakesh Verma',    'phone' => '9044556677', 'skill' => 'Electrician (Wiring)',       'daily_wage' => 800.00],
                ['name' => 'Mukesh Rathod',   'phone' => '9110011007', 'skill' => 'Waterproofing Specialist',   'daily_wage' => 700.00],
                ['name' => 'Nitin Kulkarni',  'phone' => '9110011008', 'skill' => 'Tile & Marble Mason',        'daily_wage' => 750.00],
                ['name' => 'Imran Khan',      'phone' => '9110011009', 'skill' => 'MS Welder & Fabricator',     'daily_wage' => 700.00],
                ['name' => 'Sunil Joshi',     'phone' => '9110011010', 'skill' => 'POP & False Ceiling Worker', 'daily_wage' => 680.00],
                ['name' => 'Ramrao Bhosle',   'phone' => '9066778899', 'skill' => 'Master Painter',             'daily_wage' => 650.00],
                ['name' => 'Suresh Kumar',    'phone' => '9022334455', 'skill' => 'Interior Finish Carpenter',  'daily_wage' => 750.00],
                ['name' => 'Anand Shinde',    'phone' => '9110011011', 'skill' => 'Glass & Aluminum Glazier',   'daily_wage' => 680.00],
                ['name' => 'Deepak Pawar',    'phone' => '9110011012', 'skill' => 'Site Safety & Cleanup',      'daily_wage' => 450.00],
            ];
            $addedCount = 0;
            foreach ($trades as $t) {
                $exists = dbQueryOne('SELECT worker_id FROM workers WHERE name = ? AND skill = ?', [$t['name'], $t['skill']]);
                if (!$exists) {
                    $workerModel->create([
                        'name'       => $t['name'],
                        'phone'      => $t['phone'],
                        'skill'      => $t['skill'],
                        'daily_wage' => $t['daily_wage'],
                        'is_active'  => 1,
                    ]);
                    $addedCount++;
                }
            }
            logActivity('seed_workers', 'workers', 0, "Loaded {$addedCount} full lifecycle construction trades");
            setFlash('success', "Loaded {$addedCount} specialized construction workers covering all project phases from Foundation to Handover!");
            redirect('/workers.php');

        } else {
            throw new InvalidArgumentException('Unrecognized action.');
        }
    } catch (Throwable $e) {
        setFlash('error', $e->getMessage());
        redirect('/workers.php');
    }
}

// ── GET Data & Filtering ───────────────────────────────────────────────────
$searchParam = strInput($_GET['search'] ?? '', 100);
$skillParam  = strInput($_GET['skill'] ?? '', 100);
$statusParam = isset($_GET['status']) && $_GET['status'] !== '' ? strInput($_GET['status'], 5) : '';

$filterArray = [];
if ($searchParam !== '') {
    $filterArray['search'] = $searchParam;
}
if ($skillParam !== '') {
    $filterArray['skill'] = $skillParam;
}
if ($statusParam !== '') {
    $filterArray['is_active'] = (int)$statusParam;
}

$workers  = $workerModel->getAll($filterArray);
$skills   = $workerModel->getSkills();

// Aggregate counters
$totalAllWorkers = count($workerModel->getAll());
$totalActive     = count($workerModel->getAll(['is_active' => 1]));
$totalInactive   = $totalAllWorkers - $totalActive;

$pageTitle = 'Workers Management';
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
                        <li class="breadcrumb-item active" aria-current="page">Workers</li>
                    </ol>
                </nav>
                <h2 class="h4 mb-0 fw-bold text-dark">
                    <i class="bi bi-person-badge me-2 text-primary"></i>Worker Management
                </h2>
                <p class="text-muted small mb-0">Manage construction personnel, trades, daily wage rates, and availability.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="<?= APP_URL ?>/workers.php" class="d-inline" onsubmit="return confirm('Load 20 real-world specialized construction trades into the database?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="seed_all_trades">
                    <button type="submit" class="btn btn-outline-primary shadow-sm" title="Add 20 specialized trades covering full project lifecycle">
                        <i class="bi bi-people-fill me-1"></i> Load All 20 Real-World Trades
                    </button>
                </form>
                <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addWorkerModal">
                    <i class="bi bi-person-plus-fill me-1"></i> Add New Worker
                </button>
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

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card bcms-stat-card border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="bcms-stat-icon-wrapper stat-icon-primary me-3">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div>
                            <div class="bcms-stat-label">Total Workers</div>
                            <div class="bcms-stat-value"><?= $totalAllWorkers ?></div>
                            <div class="bcms-stat-subtext">Registered workforce</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card bcms-stat-card stat-success border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="bcms-stat-icon-wrapper stat-icon-success me-3">
                            <i class="bi bi-person-check-fill"></i>
                        </div>
                        <div>
                            <div class="bcms-stat-label">Active Workers</div>
                            <div class="bcms-stat-value text-success"><?= $totalActive ?></div>
                            <div class="bcms-stat-subtext">Available for work</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card bcms-stat-card stat-danger border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="bcms-stat-icon-wrapper stat-icon-danger me-3">
                            <i class="bi bi-person-x-fill"></i>
                        </div>
                        <div>
                            <div class="bcms-stat-label">Inactive Workers</div>
                            <div class="bcms-stat-value text-danger"><?= $totalInactive ?></div>
                            <div class="bcms-stat-subtext">Deactivated/Archived</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card bcms-stat-card stat-accent border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="bcms-stat-icon-wrapper stat-icon-accent me-3">
                            <i class="bi bi-tools"></i>
                        </div>
                        <div>
                            <div class="bcms-stat-label">Trade Categories</div>
                            <div class="bcms-stat-value text-warning"><?= count($skills) ?></div>
                            <div class="bcms-stat-subtext">Distinct skill trades</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="<?= APP_URL ?>/workers.php" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label for="search" class="form-label small fw-semibold text-muted mb-1">Search Worker / Skill / Phone</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input
                                type="text"
                                class="form-control border-start-0"
                                id="search"
                                name="search"
                                placeholder="Search by name, phone or trade..."
                                value="<?= e($searchParam) ?>"
                            >
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="skill" class="form-label small fw-semibold text-muted mb-1">Filter by Trade / Skill</label>
                        <select class="form-select" id="skill" name="skill">
                            <option value="">All Skills / Trades</option>
                            <?php foreach ($skills as $s): ?>
                                <option value="<?= e($s) ?>" <?= $skillParam === $s ? 'selected' : '' ?>>
                                    <?= e($s) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label small fw-semibold text-muted mb-1">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="1" <?= $statusParam === '1' ? 'selected' : '' ?>>Active Only</option>
                            <option value="0" <?= $statusParam === '0' ? 'selected' : '' ?>>Inactive Only</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel me-1"></i> Filter
                        </button>
                        <?php if ($searchParam !== '' || $skillParam !== '' || $statusParam !== ''): ?>
                            <a href="<?= APP_URL ?>/workers.php" class="btn btn-outline-secondary" title="Reset Filters">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Workers Table Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <span class="card-header-title">
                    <i class="bi bi-list-ul me-2 text-primary"></i>
                    Workers List
                    <span class="badge bg-secondary rounded-pill ms-2"><?= count($workers) ?></span>
                </span>
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
                            <th class="text-center">Active Projects</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-3" style="width: 220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($workers)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block text-muted opacity-50 mb-2"></i>
                                    No workers found matching your filter criteria.
                                    <div class="mt-2">
                                        <a href="<?= APP_URL ?>/workers.php" class="btn btn-sm btn-outline-primary">
                                            Clear Filters
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($workers as $idx => $w): ?>
                                <tr>
                                    <td class="ps-3 text-muted small"><?= $idx + 1 ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bcms-user-avatar me-2" style="width: 34px; height: 34px; font-size: 0.8rem;">
                                                <?= e(mb_strtoupper(mb_substr($w['name'], 0, 1))) ?>
                                            </div>
                                            <div>
                                                <a href="<?= APP_URL ?>/worker_view.php?worker_id=<?= (int)$w['worker_id'] ?>" class="fw-semibold text-dark text-decoration-none hover-primary">
                                                    <?= e($w['name']) ?>
                                                </a>
                                                <div class="small text-muted">ID: #<?= (int)$w['worker_id'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="tel:<?= e($w['phone']) ?>" class="text-decoration-none text-muted small">
                                            <i class="bi bi-telephone me-1 text-primary"></i><?= e($w['phone']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-hammer me-1 text-muted"></i><?= e($w['skill']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold text-dark font-monospace">
                                        <?= formatCurrency($w['daily_wage']) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php $activeAssignments = (int)($w['active_assignments'] ?? 0); ?>
                                        <?php if ($activeAssignments > 0): ?>
                                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                                <?= $activeAssignments ?> active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((bool)$w['is_active']): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle me-1"></i>Active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-dash-circle me-1"></i>Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            <a
                                                href="<?= APP_URL ?>/worker_view.php?worker_id=<?= (int)$w['worker_id'] ?>"
                                                class="btn btn-outline-secondary"
                                                title="View Worker Profile & Wages"
                                            >
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary btn-edit-worker"
                                                title="Edit Worker"
                                                data-id="<?= (int)$w['worker_id'] ?>"
                                                data-name="<?= e($w['name']) ?>"
                                                data-phone="<?= e($w['phone']) ?>"
                                                data-skill="<?= e($w['skill']) ?>"
                                                data-daily-wage="<?= e((string)$w['daily_wage']) ?>"
                                                data-is-active="<?= (int)$w['is_active'] ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editWorkerModal"
                                            >
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" action="<?= APP_URL ?>/workers.php" class="d-inline" onsubmit="return confirm('Are you sure you want to <?= (bool)$w['is_active'] ? 'deactivate' : 'reactivate' ?> <?= e(addslashes($w['name'])) ?>?');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="worker_id" value="<?= (int)$w['worker_id'] ?>">
                                                <button
                                                    type="submit"
                                                    class="btn <?= (bool)$w['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                                    title="<?= (bool)$w['is_active'] ? 'Deactivate Worker' : 'Reactivate Worker' ?>"
                                                >
                                                    <i class="bi <?= (bool)$w['is_active'] ? 'bi-person-slash' : 'bi-person-check' ?>"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Add Worker Modal -->
<div class="modal fade" id="addWorkerModal" tabindex="-1" aria-labelledby="addWorkerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="<?= APP_URL ?>/workers.php" novalidate id="addWorkerForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_worker">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="addWorkerModalLabel">
                        <i class="bi bi-person-plus-fill me-2"></i>Add New Construction Worker
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="add_name" class="form-label fw-semibold">
                            Full Name <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="add_name"
                            name="name"
                            required
                            maxlength="150"
                            placeholder="e.g. Mohan Kumar"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="add_phone" class="form-label fw-semibold">
                            Phone Number <span class="text-danger">*</span>
                        </label>
                        <input
                            type="tel"
                            class="form-control"
                            id="add_phone"
                            name="phone"
                            required
                            maxlength="20"
                            placeholder="e.g. 9876543210"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="add_skill" class="form-label fw-semibold">
                            Skill / Trade <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="add_skill"
                            name="skill"
                            list="skillSuggestions"
                            required
                            maxlength="100"
                            placeholder="e.g. Mason, Electrician, Carpenter, Plumber, Helper"
                        >
                        <datalist id="skillSuggestions">
                            <?php foreach ($skills as $s): ?>
                                <option value="<?= e($s) ?>"></option>
                            <?php endforeach; ?>
                            <option value="Mason"></option>
                            <option value="Electrician"></option>
                            <option value="Carpenter"></option>
                            <option value="Plumber"></option>
                            <option value="Painter"></option>
                            <option value="Steel Fixer"></option>
                            <option value="Helper / Laborer"></option>
                            <option value="Site Supervisor"></option>
                        </datalist>
                    </div>

                    <div class="mb-3">
                        <label for="add_daily_wage" class="form-label fw-semibold">
                            Daily Wage Rate (₹) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-control"
                                id="add_daily_wage"
                                name="daily_wage"
                                required
                                placeholder="e.g. 650.00"
                            >
                        </div>
                        <div class="form-text">Per standard full working day (half-day is paid at 50%).</div>
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="add_is_active" name="is_active" value="1" checked>
                        <label class="form-check-label fw-semibold" for="add_is_active">Active Worker (Available for Assignment)</label>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Save Worker
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Worker Modal -->
<div class="modal fade" id="editWorkerModal" tabindex="-1" aria-labelledby="editWorkerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="<?= APP_URL ?>/workers.php" novalidate id="editWorkerForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="edit_worker">
                <input type="hidden" name="worker_id" id="edit_worker_id" value="">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="editWorkerModalLabel">
                        <i class="bi bi-pencil-square me-2"></i>Edit Worker Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label fw-semibold">
                            Full Name <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="edit_name"
                            name="name"
                            required
                            maxlength="150"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="edit_phone" class="form-label fw-semibold">
                            Phone Number <span class="text-danger">*</span>
                        </label>
                        <input
                            type="tel"
                            class="form-control"
                            id="edit_phone"
                            name="phone"
                            required
                            maxlength="20"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="edit_skill" class="form-label fw-semibold">
                            Skill / Trade <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="edit_skill"
                            name="skill"
                            list="skillSuggestionsEdit"
                            required
                            maxlength="100"
                        >
                        <datalist id="skillSuggestionsEdit">
                            <?php foreach ($skills as $s): ?>
                                <option value="<?= e($s) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="mb-3">
                        <label for="edit_daily_wage" class="form-label fw-semibold">
                            Daily Wage Rate (₹) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-control"
                                id="edit_daily_wage"
                                name="daily_wage"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="edit_is_active" name="is_active" value="1">
                        <label class="form-check-label fw-semibold" for="edit_is_active">Active Worker</label>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Update Worker
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Populate Edit Modal on button click
    const editBtns = document.querySelectorAll('.btn-edit-worker');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('edit_worker_id').value = this.dataset.id || '';
            document.getElementById('edit_name').value      = this.dataset.name || '';
            document.getElementById('edit_phone').value     = this.dataset.phone || '';
            document.getElementById('edit_skill').value     = this.dataset.skill || '';
            document.getElementById('edit_daily_wage').value= this.dataset.dailyWage || '';
            document.getElementById('edit_is_active').checked = (this.dataset.isActive === '1');
        });
    });
});
</script>

<?php
require_once BCMS_ROOT . '/app/views/layout/footer.php';
