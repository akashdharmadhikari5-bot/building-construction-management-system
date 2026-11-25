<?php
/**
 * BCMS — Material Consumption & Usage
 * Record material consumption at project sites, safely decrement inventory and prevent negative stock.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireRole(ROLE_ADMIN);

$usageModel    = new UsageModel();
$materialModel = new MaterialModel();
$projectModel  = new ProjectModel();

// ── Handle POST: Record Material Consumption ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = strInput($_POST['action'] ?? '', 50);

    if ($action === 'add') {
        $projectId   = intInput($_POST['project_id'] ?? null, 1);
        $materialId  = intInput($_POST['material_id'] ?? null, 1);
        $quantity    = decimalInput($_POST['quantity'] ?? null);
        $usageDate   = dateInput($_POST['usage_date'] ?? date('Y-m-d'));
        $description = strInput($_POST['description'] ?? '', 300);

        if (!$projectId) {
            setFlash('error', 'Please select a valid construction project.');
        } elseif (!$materialId) {
            setFlash('error', 'Please select a valid inventory material.');
        } elseif ($quantity === null || $quantity <= 0.0) {
            setFlash('error', 'Consumption quantity must be greater than zero.');
        } elseif (!$usageDate) {
            setFlash('error', 'A valid usage date is required.');
        } else {
            try {
                $usageId = $usageModel->create([
                    'project_id'  => $projectId,
                    'material_id' => $materialId,
                    'quantity'    => $quantity,
                    'usage_date'  => $usageDate,
                    'description' => $description ?: null,
                ]);

                $mat = $materialModel->getById($materialId);
                $matName = $mat['material_name'] ?? "Material #{$materialId}";

                logActivity('create', 'materials', $usageId, "Consumed {$quantity} of {$matName}");
                setFlash('success', "Successfully recorded consumption of {$quantity} {$mat['unit']} of '{$matName}'!");
            } catch (InvalidArgumentException $e) {
                setFlash('error', $e->getMessage());
            } catch (Throwable $e) {
                setFlash('error', 'Error recording material usage: ' . $e->getMessage());
            }
        }
        redirect('/usage.php' . ($projectId ? "?project_id={$projectId}" : ''));
    }
}

// ── GET: Filters & Listing ────────────────────────────────────────────────────
$filters = [];
$projectFilter = intInput($_GET['project_id'] ?? null, 1);
$materialFilter= intInput($_GET['material_id'] ?? null, 1);
$fromDate      = dateInput($_GET['from_date'] ?? null);
$toDate        = dateInput($_GET['to_date'] ?? null);
$searchQuery   = strInput($_GET['search'] ?? '', 100);

if ($projectFilter)  $filters['project_id']  = $projectFilter;
if ($materialFilter) $filters['material_id'] = $materialFilter;
if ($fromDate)       $filters['from_date']   = $fromDate;
if ($toDate)         $filters['to_date']     = $toDate;
if ($searchQuery)    $filters['search']      = $searchQuery;

$usages       = [];
$materials    = [];
$projects     = [];
$totalEntries = 0;

try {
    $usages    = $usageModel->getAll($filters);
    $materials = $materialModel->getAll();
    $projects  = $projectModel->getAll(['status' => 'active']);
    $totalEntries = count($usages);
} catch (Throwable $e) {
    setFlash('error', 'Error loading material consumption records: ' . $e->getMessage());
}

$pageTitle = 'Material Consumption';
$activeNav = 'usage';

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
          <li class="breadcrumb-item"><a href="<?= APP_URL ?>/materials.php">Materials</a></li>
          <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
        </ol>
      </nav>
      <h2 class="h4 mb-0 fw-bold"><?= e($pageTitle) ?></h2>
      <p class="text-muted small mb-0">Record material usage on site, track inventory deductions, and prevent stock-outs.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUsageModal">
      <i class="bi bi-arrow-up-right-circle me-1"></i>Record Material Usage
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

  <!-- KPI Cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary me-3">
            <i class="bi bi-card-checklist fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Consumption Logs</div>
            <div class="fs-4 fw-bold"><?= number_format($totalEntries) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-warning bg-opacity-10 text-warning me-3">
            <i class="bi bi-building fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Active Project Sites</div>
            <div class="fs-4 fw-bold text-warning"><?= count($projects) ?> Sites</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-info bg-opacity-10 text-info me-3">
            <i class="bi bi-box-seam fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Materials Tracked</div>
            <div class="fs-4 fw-bold text-info"><?= count($materials) ?> Types</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Search & Filter Bar -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <form method="GET" action="<?= APP_URL ?>/usage.php" class="row g-3 align-items-center">
        <div class="col-md-3">
          <select name="project_id" class="form-select">
            <option value="">All Projects</option>
            <?php foreach ($projects as $pr): ?>
              <option value="<?= $pr['project_id'] ?>" <?= $projectFilter === (int)$pr['project_id'] ? 'selected' : '' ?>>
                <?= e($pr['project_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="material_id" class="form-select">
            <option value="">All Materials</option>
            <?php foreach ($materials as $m): ?>
              <option value="<?= $m['material_id'] ?>" <?= $materialFilter === (int)$m['material_id'] ? 'selected' : '' ?>>
                <?= e($m['material_name']) ?> (Stock: <?= number_format((float)($m['computed_stock'] ?? $m['stock_quantity']), 1) ?> <?= e($m['unit']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <input type="date" name="from_date" class="form-control" value="<?= e($fromDate ?? '') ?>" placeholder="From date">
        </div>
        <div class="col-md-2">
          <input type="date" name="to_date" class="form-control" value="<?= e($toDate ?? '') ?>" placeholder="To date">
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Filter</button>
          <a href="<?= APP_URL ?>/usage.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
        </div>
      </form>
    </div>
  </div>

  <!-- Usage Records Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between">
      <h5 class="mb-0 fw-bold">Consumption Logs (<?= count($usages) ?>)</h5>
    </div>
    <div class="card-body p-0">
      <?php if (empty($usages)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-arrow-up-right-circle fs-2"></i>
          <h5 class="mt-3">No usage records found</h5>
          <p class="small">Log material consumption when items are dispatched or used on site.</p>
          <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addUsageModal">
            <i class="bi bi-plus-circle me-1"></i>Record Usage
          </button>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-4">Date</th>
                <th>Material</th>
                <th>Quantity Used</th>
                <th>Project Site</th>
                <th class="pe-4">Work Description / Purpose</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($usages as $u): ?>
                <tr>
                  <td class="ps-4 fw-semibold text-nowrap"><?= formatDate($u['usage_date']) ?></td>
                  <td>
                    <div class="fw-bold"><?= e($u['material_name']) ?></div>
                    <div class="small text-muted">ID: #<?= (int)$u['material_id'] ?></div>
                  </td>
                  <td>
                    <span class="badge bg-danger-subtle text-danger fs-6 fw-bold">
                      -<?= number_format((float)$u['quantity'], 2) ?> <?= e($u['unit']) ?>
                    </span>
                  </td>
                  <td>
                    <a href="<?= APP_URL ?>/project_view.php?project_id=<?= (int)$u['project_id'] ?>" class="text-decoration-none fw-semibold">
                      <?= e($u['project_name']) ?>
                    </a>
                  </td>
                  <td class="pe-4 text-muted small"><?= e($u['description'] ?? '—') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Add Usage Modal -->
<div class="modal fade" id="addUsageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= APP_URL ?>/usage.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold"><i class="bi bi-arrow-up-right-circle me-2"></i>Record Material Consumption</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-6">
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
              <label class="form-label fw-semibold">Material Item <span class="text-danger">*</span></label>
              <select name="material_id" id="usage_material_select" class="form-select" required>
                <option value="">Select Material...</option>
                <?php foreach ($materials as $m): ?>
                  <?php $avail = (float)($m['computed_stock'] ?? $m['stock_quantity']); ?>
                  <option value="<?= $m['material_id'] ?>" data-stock="<?= $avail ?>" data-unit="<?= e($m['unit']) ?>">
                    <?= e($m['material_name']) ?> (Available: <?= number_format($avail, 2) ?> <?= e($m['unit']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Quantity to Deduct <span class="text-danger">*</span></label>
              <input type="number" step="0.001" min="0.001" name="quantity" class="form-control" placeholder="e.g. 50" required>
              <div class="form-text text-muted" id="availStockHint">Select a material above to verify current stock.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Usage Date <span class="text-danger">*</span></label>
              <input type="date" name="usage_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-12">
              <label class="form-label fw-semibold">Work Location / Purpose Description</label>
              <input type="text" name="description" class="form-control" placeholder="e.g. Ground floor slab casting, pillar reinforcement...">
            </div>
          </div>
          <div class="alert alert-warning small py-2 mt-3 mb-0">
            <i class="bi bi-shield-exclamation me-1"></i>The system will check inventory availability before deducting. If requested quantity exceeds in-stock levels, the transaction will be rejected to prevent negative inventory.
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check2-circle me-1"></i>Record Consumption</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const matSelect = document.getElementById('usage_material_select');
  const stockHint = document.getElementById('availStockHint');
  if (matSelect && stockHint) {
    matSelect.addEventListener('change', function() {
      const opt = this.options[this.selectedIndex];
      const stock = opt.getAttribute('data-stock');
      const unit = opt.getAttribute('data-unit');
      if (stock !== null) {
        stockHint.innerHTML = `<span class="text-success fw-bold">Available In Stock: ${stock} ${unit}</span>`;
      } else {
        stockHint.innerText = 'Select a material above to verify current stock.';
      }
    });
  }
});
</script>

<?php
require_once VIEW_PATH . '/layout/footer.php';

